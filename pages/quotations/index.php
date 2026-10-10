<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();

$validStatuses = ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect('index.php?page=quotations');
    }

    $idInput = trim((string) ($_POST['id'] ?? ''));
    $id = $idInput === '' ? null : filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $customerIdInput = trim((string) ($_POST['customer_id'] ?? ''));
    $customerId = filter_var($customerIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quotationDate = (string) ($_POST['quotation_date'] ?? date('Y-m-d'));
    $validUntil = (string) ($_POST['valid_until'] ?? '');
    $discountInput = (string) ($_POST['discount'] ?? '0');
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'Draft'));
    $serviceIds = $_POST['service_id'] ?? null;
    $quantities = $_POST['quantity'] ?? null;
    $itemIds = $_POST['item_id'] ?? null;
    $specifications = $_POST['specifications'] ?? null;

    $validDate = static function (string $date): bool {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;
    };

    if ($id === false) {
        flash('error', 'The selected quotation is invalid.');
        redirect('index.php?page=quotations');
    }
    if ($customerId === false) {
        flash('error', 'A valid customer is required.');
        redirect('index.php?page=quotations');
    }

    if (!$validDate($quotationDate) || !$validDate($validUntil)) {
        flash('error', 'Enter valid quotation and expiry dates.');
        redirect('index.php?page=quotations');
    }

    if (preg_match('/\A\d+(?:\.\d{1,2})?\z/', $discountInput) !== 1 || (float) $discountInput > 99999999.99) {
        flash('error', 'Discount must be a valid non-negative amount with up to two decimal places.');
        redirect('index.php?page=quotations');
    }
    $discount = (float) $discountInput;

    if (!in_array($status, $validStatuses, true)) {
        flash('error', 'Quotation status is invalid.');
        redirect('index.php?page=quotations');
    }

    if ($validUntil < $quotationDate) {
        flash('error', 'Valid until date cannot be before quotation date.');
        redirect('index.php?page=quotations');
    }

    if (!is_array($serviceIds) || !is_array($quantities) || !is_array($itemIds) || !is_array($specifications)
        || count($serviceIds) !== count($quantities)
        || count($serviceIds) !== count($itemIds)
        || count($serviceIds) !== count($specifications)) {
        flash('error', 'Quotation items are invalid. Please review the item rows and try again.');
        redirect('index.php?page=quotations');
    }

    $customerStmt = $pdo->prepare('SELECT id FROM customers WHERE id = :id LIMIT 1');
    $customerStmt->execute([':id' => $customerId]);
    if (!$customerStmt->fetch()) {
        flash('error', 'The selected customer could not be found.');
        redirect('index.php?page=quotations');
    }

    $existingItems = [];
    if ($id !== null) {
        $existingQuoteStmt = $pdo->prepare('SELECT id FROM quotations WHERE id = :id LIMIT 1');
        $existingQuoteStmt->execute([':id' => $id]);
        if (!$existingQuoteStmt->fetch()) {
            flash('error', 'Quotation not found.');
            redirect('index.php?page=quotations');
        }
        $existingItemsStmt = $pdo->prepare('SELECT qi.*, s.is_active FROM quotation_items qi JOIN services s ON s.id = qi.service_id WHERE qi.quotation_id = :quotation_id');
        $existingItemsStmt->execute([':quotation_id' => $id]);
        foreach ($existingItemsStmt->fetchAll() as $existingItem) {
            $existingItems[(int) $existingItem['id']] = $existingItem;
        }
    }

    $items = [];
    $seenItemIds = [];
    foreach ($serviceIds as $index => $serviceId) {
        if (!is_scalar($serviceId) || !is_scalar($quantities[$index] ?? null)
            || !is_scalar($itemIds[$index] ?? null) || !is_scalar($specifications[$index] ?? null)) {
            flash('error', 'Quotation item values are invalid.');
            redirect('index.php?page=quotations');
        }
        $serviceIdInput = (string) $serviceId;
        $quantityInput = (string) $quantities[$index];
        $itemIdInput = trim((string) $itemIds[$index]);
        $itemSpecs = trim((string) $specifications[$index]);

        if ($serviceIdInput === '' && $itemIdInput === '' && $itemSpecs === '') {
            continue;
        }

        $cleanServiceId = filter_var($serviceIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($quantityInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        $itemId = $itemIdInput === '' ? null : filter_var($itemIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($cleanServiceId === false || $quantity === false || ($itemIdInput !== '' && $itemId === false)) {
            flash('error', 'Each quotation item needs a valid service and positive whole-number quantity.');
            redirect('index.php?page=quotations');
        }
        if ($itemId !== null) {
            if ($id === null || !isset($existingItems[$itemId]) || isset($seenItemIds[$itemId])) {
                flash('error', 'A quotation item does not belong to this quotation.');
                redirect('index.php?page=quotations');
            }
            $seenItemIds[$itemId] = true;
        }

        $serviceStmt = $pdo->prepare('SELECT id, name, base_price, is_active FROM services WHERE id = :id LIMIT 1');
        $serviceStmt->execute([':id' => $cleanServiceId]);
        $service = $serviceStmt->fetch();

        if (!$service) {
            flash('error', 'A selected service could not be found.');
            redirect('index.php?page=quotations');
        }

        $isHistoricalInactiveService = (int) $service['is_active'] !== 1
            && $itemId !== null
            && (int) $existingItems[$itemId]['service_id'] === (int) $cleanServiceId;
        if ((int) $service['is_active'] !== 1 && !$isHistoricalInactiveService) {
            flash('error', 'Inactive services can only remain on their existing quotation item.');
            redirect('index.php?page=quotations');
        }

        $unitPrice = $isHistoricalInactiveService
            ? (float) $existingItems[$itemId]['unit_price']
            : (float) $service['base_price'];
        $totalPrice = round($unitPrice * $quantity, 2);
        if (!is_finite($unitPrice) || $unitPrice < 0 || $totalPrice > 99999999.99) {
            flash('error', 'A quotation item price exceeds the supported amount.');
            redirect('index.php?page=quotations');
        }

        $items[] = [
            'id' => $itemId,
            'service_id' => $cleanServiceId,
            'description' => $isHistoricalInactiveService ? $existingItems[$itemId]['description'] : $service['name'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'specifications' => $itemSpecs,
        ];
    }

    if ($items === []) {
        flash('error', 'Add at least one valid service item to the quotation.');
        redirect('index.php?page=quotations');
    }

    $subtotal = round(array_sum(array_column($items, 'total_price')), 2);
    if ($subtotal > 99999999.99 || $discount > $subtotal) {
        flash('error', 'Discount cannot exceed the quotation subtotal, and totals must fit the supported amount.');
        redirect('index.php?page=quotations');
    }
    $totalAmount = round($subtotal - $discount, 2);

    try {
        $pdo->beginTransaction();
        if ($id !== null) {
            $lockStmt = $pdo->prepare('SELECT id FROM quotations WHERE id = :id FOR UPDATE');
            $lockStmt->execute([':id' => $id]);
            if (!$lockStmt->fetch()) {
                throw new RuntimeException('Quotation no longer exists.');
            }

            $stmt = $pdo->prepare('UPDATE quotations SET customer_id = :customer_id, quotation_date = :quotation_date, valid_until = :valid_until, status = :status, subtotal = :subtotal, discount = :discount, total_amount = :total_amount, notes = :notes WHERE id = :id');
            $stmt->execute([
                ':customer_id' => $customerId,
                ':quotation_date' => $quotationDate,
                ':valid_until' => $validUntil,
                ':status' => $status,
                ':subtotal' => $subtotal,
                ':discount' => $discount,
                ':total_amount' => $totalAmount,
                ':notes' => $notes,
                ':id' => $id,
            ]);
            $pdo->prepare('DELETE FROM quotation_items WHERE quotation_id = :quotation_id')->execute([':quotation_id' => $id]);
            $quotationId = $id;
        } else {
            $quoteNo = 'Q-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $pdo->prepare('INSERT INTO quotations (quotation_number, customer_id, created_by, quotation_date, valid_until, status, subtotal, discount, total_amount, notes) VALUES (:quotation_number, :customer_id, :created_by, :quotation_date, :valid_until, :status, :subtotal, :discount, :total_amount, :notes)');
            $stmt->execute([
                ':quotation_number' => $quoteNo,
                ':customer_id' => $customerId,
                ':created_by' => currentUser()['id'],
                ':quotation_date' => $quotationDate,
                ':valid_until' => $validUntil,
                ':status' => $status,
                ':subtotal' => $subtotal,
                ':discount' => $discount,
                ':total_amount' => $totalAmount,
                ':notes' => $notes,
            ]);
            $quotationId = (int) $pdo->lastInsertId();
        }

        $itemStmt = $pdo->prepare('INSERT INTO quotation_items (quotation_id, service_id, description, quantity, unit_price, total_price, specifications) VALUES (:quotation_id, :service_id, :description, :quantity, :unit_price, :total_price, :specifications)');
        foreach ($items as $item) {
            $itemStmt->execute([
                ':quotation_id' => $quotationId,
                ':service_id' => $item['service_id'],
                ':description' => $item['description'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':total_price' => $item['total_price'],
                ':specifications' => $item['specifications'],
            ]);
        }
        $pdo->commit();
        flash('success', $id !== null ? 'Quotation updated successfully.' : 'Quotation created successfully.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Quotation save error: ' . $exception->getMessage());
        flash('error', 'Quotation could not be saved. No changes were kept; please review the details and try again.');
    }
    redirect('index.php?page=quotations');
}

$search = trim((string) ($_GET['q'] ?? ''));
$viewId = isset($_GET['view']) && is_numeric($_GET['view']) ? (int) $_GET['view'] : null;
$editId = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int) $_GET['edit'] : null;

$customers = $pdo->query('SELECT * FROM customers ORDER BY name ASC')->fetchAll();
$services = $pdo->query('SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

$viewQuotation = null;
$viewItems = [];
if ($viewId !== null) {
    $viewStmt = $pdo->prepare('SELECT q.*, c.name AS customer_name FROM quotations q JOIN customers c ON c.id = q.customer_id WHERE q.id = :id LIMIT 1');
    $viewStmt->execute([':id' => $viewId]);
    $viewQuotation = $viewStmt->fetch();

    if ($viewQuotation) {
        $viewItemsStmt = $pdo->prepare('SELECT * FROM quotation_items WHERE quotation_id = :quotation_id ORDER BY id ASC');
        $viewItemsStmt->execute([':quotation_id' => $viewId]);
        $viewItems = $viewItemsStmt->fetchAll();
    }
}

$editQuotation = null;
$editItems = [];
if ($editId !== null) {
    $editStmt = $pdo->prepare('SELECT q.*, c.name AS customer_name FROM quotations q JOIN customers c ON c.id = q.customer_id WHERE q.id = :id LIMIT 1');
    $editStmt->execute([':id' => $editId]);
    $editQuotation = $editStmt->fetch();

    if ($editQuotation) {
        $editItemsStmt = $pdo->prepare('SELECT qi.*, s.is_active FROM quotation_items qi JOIN services s ON s.id = qi.service_id WHERE qi.quotation_id = :quotation_id ORDER BY qi.id ASC');
        $editItemsStmt->execute([':quotation_id' => $editId]);
        $editItems = $editItemsStmt->fetchAll();
    }
}

if ($search !== '') {
    $stmt = $pdo->prepare('SELECT q.*, c.name AS customer_name FROM quotations q JOIN customers c ON c.id = q.customer_id WHERE q.quotation_number LIKE :number_q OR c.name LIKE :customer_q OR q.status LIKE :status_q ORDER BY q.created_at DESC');
    $pattern = '%' . $search . '%';
    $stmt->execute([':number_q' => $pattern, ':customer_q' => $pattern, ':status_q' => $pattern]);
    $quotations = $stmt->fetchAll();
} else {
    $quotations = $pdo->query('SELECT q.*, c.name AS customer_name FROM quotations q JOIN customers c ON c.id = q.customer_id ORDER BY q.created_at DESC')->fetchAll();
}
?>

<div class="page-header">
    <h2>Quotations</h2>
    <a class="btn btn-secondary" href="index.php?page=quotations">Refresh</a>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3><?php echo $editQuotation ? 'Edit quotation' : 'Create quotation'; ?></h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
        <?php if ($editQuotation): ?>
            <input type="hidden" name="id" value="<?php echo e($editQuotation['id']); ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>Customer</label>
                <select name="customer_id" required>
                    <option value="">Select customer</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo e($customer['id']); ?>" <?php echo (($editQuotation['customer_id'] ?? '') == $customer['id']) ? 'selected' : ''; ?>><?php echo e($customer['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Quotation Date</label>
                <input type="date" name="quotation_date" value="<?php echo e($editQuotation['quotation_date'] ?? date('Y-m-d')); ?>">
            </div>
            <div>
                <label>Valid Until</label>
                <input type="date" name="valid_until" value="<?php echo e($editQuotation['valid_until'] ?? date('Y-m-d', strtotime('+7 days'))); ?>" required>
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <?php foreach ($validStatuses as $statusOption): ?>
                        <option value="<?php echo e($statusOption); ?>" <?php echo (($editQuotation['status'] ?? 'Draft') === $statusOption) ? 'selected' : ''; ?>><?php echo e($statusOption); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Discount</label>
                <input type="number" name="discount" step="0.01" min="0" max="99999999.99" value="<?php echo e($editQuotation['discount'] ?? '0'); ?>">
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Notes</label>
                <textarea name="notes"><?php echo e($editQuotation['notes'] ?? ''); ?></textarea>
            </div>
        </div>

        <style>
            .quotation-items-panel {
                margin-top: 24px;
                padding: 18px;
                border: 1px solid var(--border);
                border-radius: 12px;
                background: #f8fafc;
            }

            .quotation-items-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 16px;
            }

            .quotation-items-header h4 {
                margin: 0;
            }

            .quotation-item-grid {
                display: grid;
                grid-template-columns: minmax(180px, 2fr) minmax(80px, 0.7fr) minmax(140px, 1.5fr) minmax(110px, 1fr) minmax(110px, 1fr) 88px;
                align-items: center;
                gap: 12px;
            }

            .quotation-item-labels {
                padding: 0 12px 8px;
                color: var(--muted);
                font-size: 0.76rem;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .quotation-item-row {
                padding: 10px 12px;
                border: 1px solid var(--border);
                border-radius: 10px;
                background: white;
            }

            .quotation-item-row + .quotation-item-row {
                margin-top: 8px;
            }

            .quotation-item-row select,
            .quotation-item-row input {
                min-height: 42px;
            }

            .quotation-item-row label,
            .quotation-item-mobile-label {
                display: none;
            }

            .quotation-item-price {
                font-weight: 600;
                white-space: nowrap;
            }

            .quotation-item-remove {
                padding: 9px 10px;
                color: var(--error);
                border: 1px solid #f0caca;
                background: #fff7f7;
            }

            .quotation-item-remove:hover:not(:disabled) {
                background: #fee2e2;
            }

            .quotation-item-remove:disabled {
                cursor: not-allowed;
                opacity: 0.5;
            }

            @media (max-width: 760px) {
                .quotation-items-panel {
                    padding: 14px;
                }

                .quotation-item-labels {
                    display: none;
                }

                .quotation-item-row {
                    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                    gap: 10px;
                    padding: 12px;
                }

                .quotation-item-service,
                .quotation-item-specifications,
                .quotation-item-remove-cell {
                    grid-column: 1 / -1;
                }

                .quotation-item-row label,
                .quotation-item-mobile-label {
                    display: block;
                    margin-bottom: 5px;
                    font-size: 0.8rem;
                }

                .quotation-item-remove-cell {
                    display: flex;
                    justify-content: flex-end;
                }

                .quotation-item-remove-cell label {
                    display: none;
                }
            }

            @media (max-width: 420px) {
                .quotation-items-header {
                    align-items: flex-start;
                }

                .quotation-items-header .btn {
                    padding: 9px 11px;
                    white-space: nowrap;
                }
            }
        </style>

        <section class="quotation-items-panel" aria-labelledby="quotation-items-title">
            <div class="quotation-items-header">
                <h4 id="quotation-items-title">Items</h4>
                <button class="btn btn-secondary" type="button" data-add-quotation-item>+ Add Item</button>
            </div>
        <?php $itemRows = $editItems ?: [['service_id' => '', 'quantity' => 1], ['service_id' => '', 'quantity' => 1], ['service_id' => '', 'quantity' => 1]]; ?>
            <div class="quotation-item-grid quotation-item-labels" aria-hidden="true">
                <span>Service</span>
                <span>Quantity</span>
                <span>Specifications</span>
                <span>Unit Price</span>
                <span>Item Total</span>
                <span>Remove</span>
            </div>
            <div id="quotation-items">
            <?php foreach ($itemRows as $itemRow): ?>
                <div class="quotation-item-grid quotation-item-row">
                    <input type="hidden" name="item_id[]" value="<?php echo e((string) ($itemRow['id'] ?? '')); ?>">
                    <div class="quotation-item-service">
                        <label>Service</label>
                        <select name="service_id[]">
                            <option value="">Select service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo e($service['id']); ?>" data-price="<?php echo e($service['base_price']); ?>" <?php echo ((string) ($itemRow['service_id'] ?? '') === (string) $service['id']) ? 'selected' : ''; ?>><?php echo e($service['name']); ?> - <?php echo e(money((float) $service['base_price'])); ?></option>
                            <?php endforeach; ?>
                            <?php if (isset($itemRow['id']) && isset($itemRow['is_active']) && (int) $itemRow['is_active'] !== 1): ?>
                                <option value="<?php echo e($itemRow['service_id']); ?>" data-price="<?php echo e($itemRow['unit_price']); ?>" selected><?php echo e($itemRow['description']); ?> (Inactive - historical item)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="quotation-item-quantity">
                        <label>Quantity</label>
                        <input type="number" name="quantity[]" min="1" step="1" value="<?php echo e((string) ($itemRow['quantity'] ?? 1)); ?>">
                    </div>
                    <div class="quotation-item-specifications">
                        <label>Specifications</label>
                        <input type="text" name="specifications[]" value="<?php echo e((string) ($itemRow['specifications'] ?? '')); ?>" maxlength="2000">
                    </div>
                    <div class="quotation-item-price">
                        <span class="quotation-item-mobile-label">Unit Price</span>
                        <span data-unit-price>—</span>
                    </div>
                    <div class="quotation-item-price">
                        <span class="quotation-item-mobile-label">Item Total</span>
                        <span data-item-total>—</span>
                    </div>
                    <div class="quotation-item-remove-cell">
                        <label>Remove</label>
                        <button class="btn quotation-item-remove" type="button" data-remove-quotation-item aria-label="Remove item">Remove</button>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </section>

        <div class="form-actions business-form-actions quotation-form-actions">
            <button class="btn" type="submit"><?php echo $editQuotation ? 'Update quotation' : 'Save quotation'; ?></button>
            <?php if ($editQuotation): ?>
                <a class="btn btn-secondary" href="index.php?page=quotations">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    const quotationItems = document.getElementById('quotation-items');
    const addQuotationItemButton = document.querySelector('[data-add-quotation-item]');

    const updateQuotationItemRemoveButtons = () => {
        const removeButtons = quotationItems.querySelectorAll('[data-remove-quotation-item]');
        removeButtons.forEach((button) => {
            button.disabled = removeButtons.length === 1;
        });
    };

    const formatQuotationItemPrice = (amount) => `₹${amount.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const updateQuotationItemPrices = (row) => {
        const serviceSelect = row.querySelector('select[name="service_id[]"]');
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        const unitPrice = Number(selectedOption.dataset.price);
        const quantity = Number.parseInt(row.querySelector('input[name="quantity[]"]').value, 10);
        const hasService = serviceSelect.value !== '' && Number.isFinite(unitPrice);

        row.querySelector('[data-unit-price]').textContent = hasService ? formatQuotationItemPrice(unitPrice) : '—';
        row.querySelector('[data-item-total]').textContent = hasService && quantity > 0
            ? formatQuotationItemPrice(unitPrice * quantity)
            : '—';
    };

    const updateAllQuotationItemPrices = () => {
        quotationItems.querySelectorAll('.quotation-item-row').forEach(updateQuotationItemPrices);
    };

    addQuotationItemButton.addEventListener('click', () => {
        const newRow = quotationItems.querySelector('.quotation-item-row').cloneNode(true);
        newRow.querySelector('select[name="service_id[]"]').selectedIndex = 0;
        newRow.querySelector('input[name="item_id[]"]').value = '';
        newRow.querySelector('input[name="quantity[]"]').value = '1';
        newRow.querySelector('input[name="specifications[]"]').value = '';
        quotationItems.appendChild(newRow);
        updateQuotationItemRemoveButtons();
        updateQuotationItemPrices(newRow);
    });

    quotationItems.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-quotation-item]');
        if (removeButton && quotationItems.querySelectorAll('.quotation-item-row').length > 1) {
            removeButton.closest('.quotation-item-row').remove();
            updateQuotationItemRemoveButtons();
        }
    });

    quotationItems.addEventListener('change', (event) => {
        if (event.target.matches('select[name="service_id[]"]')) {
            updateQuotationItemPrices(event.target.closest('.quotation-item-row'));
        }
    });

    quotationItems.addEventListener('input', (event) => {
        if (event.target.matches('input[name="quantity[]"]')) {
            updateQuotationItemPrices(event.target.closest('.quotation-item-row'));
        }
    });

    updateQuotationItemRemoveButtons();
    updateAllQuotationItemPrices();
</script>

<?php if ($viewQuotation): ?>
    <div class="card" style="margin-bottom: 24px;">
        <h3>Quotation details</h3>
        <div class="form-grid">
            <div>
                <label>Number</label>
                <p><?php echo e($viewQuotation['quotation_number']); ?></p>
            </div>
            <div>
                <label>Customer</label>
                <p><?php echo e($viewQuotation['customer_name']); ?></p>
            </div>
            <div>
                <label>Quotation Date</label>
                <p><?php echo e($viewQuotation['quotation_date']); ?></p>
            </div>
            <div>
                <label>Valid Until</label>
                <p><?php echo e($viewQuotation['valid_until']); ?></p>
            </div>
            <div>
                <label>Status</label>
                <p><span class="pill"><?php echo e($viewQuotation['status']); ?></span></p>
            </div>
            <div>
                <label>Discount</label>
                <p><?php echo e(money((float) $viewQuotation['discount'])); ?></p>
            </div>
        </div>

        <div class="business-table-wrap" style="margin-top: 20px;">
            <table class="table business-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Qty</th>
                        <th>Specifications</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($viewItems): ?>
                        <?php foreach ($viewItems as $item): ?>
                            <tr>
                                <td><?php echo e($item['description']); ?></td>
                                <td><?php echo e((string) $item['quantity']); ?></td>
                                <td><?php echo e($item['specifications'] ?: '-'); ?></td>
                                <td><?php echo e(money((float) $item['unit_price'])); ?></td>
                                <td><?php echo e(money((float) $item['total_price'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5"><p class="empty-state">No items found.</p></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="form-grid" style="margin-top: 20px;">
            <div>
                <label>Subtotal</label>
                <p><?php echo e(money((float) $viewQuotation['subtotal'])); ?></p>
            </div>
            <div>
                <label>Total</label>
                <p><?php echo e(money((float) $viewQuotation['total_amount'])); ?></p>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Notes</label>
                <p><?php echo e($viewQuotation['notes'] ?: '-'); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="search-wrap business-search">
        <form method="get">
            <input type="hidden" name="page" value="quotations">
            <label>Search quotations</label>
            <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Search by quotation number, customer or status">
        </form>
    </div>

    <div class="business-table-wrap">
    <table class="table business-table">
        <thead>
            <tr>
                <th>Quotation</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($quotations): ?>
                <?php foreach ($quotations as $quotation): ?>
                    <tr>
                        <td><?php echo e($quotation['quotation_number']); ?></td>
                        <td><?php echo e($quotation['customer_name']); ?></td>
                        <td><?php echo e($quotation['quotation_date']); ?></td>
                        <td><span class="pill"><?php echo e($quotation['status']); ?></span></td>
                        <td><?php echo e(money((float) $quotation['total_amount'])); ?></td>
                        <td class="actions business-actions">
                            <a class="btn btn-secondary" href="index.php?page=quotations&view=<?php echo e($quotation['id']); ?>">View</a>
                            <a class="btn btn-secondary" href="index.php?page=quotations&edit=<?php echo e($quotation['id']); ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6"><p class="empty-state">No quotations match your search.</p></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
