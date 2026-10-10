<?php

declare(strict_types=1);

requireLogin();
requireRole(['admin', 'staff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    flash('error', 'Your request could not be verified. Please try again.');
    redirect('index.php?page=orders');
}

$pdo = getDatabaseConnection();
$isAdmin = currentUser()['role'] === 'admin';

function isValidDateString(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function sanitizePositiveInt(mixed $value): int
{
    if (is_int($value)) {
        return $value > 0 ? $value : 0;
    }

    if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
        return (int) trim($value);
    }

    return 0;
}

function sanitizeNonNegativeFloat(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }

    $parsed = (float) $value;

    return $parsed;
}

function recordOrderManagementException(Throwable $exception): void
{
    error_log('Order management error: ' . $exception->getMessage());
}

function getOrderForwardTransitions(): array
{
    return [
        'Pending' => ['Confirmed'],
        'Confirmed' => ['In Production'],
        'In Production' => ['Ready'],
        'Ready' => ['Completed'],
        'Completed' => [],
        'Cancelled' => [],
    ];
}

function getNextOrderStatus(string $currentStatus): ?string
{
    $nextStatuses = getOrderForwardTransitions()[$currentStatus] ?? [];

    return $nextStatuses[0] ?? null;
}

function getOrderTransitionError(string $currentStatus, string $requestedStatus): ?string
{
    if ($currentStatus === 'Completed') {
        return 'This order has already been completed.';
    }

    if ($currentStatus === 'Cancelled') {
        return 'This order has already been cancelled.';
    }

    $allowedStatuses = ['Pending', 'Confirmed', 'In Production', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($requestedStatus, $allowedStatuses, true)) {
        return 'Selected order status is invalid.';
    }

    if (in_array($requestedStatus, getOrderForwardTransitions()[$currentStatus] ?? [], true)) {
        return null;
    }

    return 'This order cannot move from ' . $currentStatus . ' to ' . $requestedStatus . '.';
}

function getOrderCancellationError(string $currentStatus): ?string
{
    if (in_array($currentStatus, ['Pending', 'Confirmed'], true)) {
        return null;
    }

    if ($currentStatus === 'In Production' || $currentStatus === 'Ready') {
        return 'This order cannot be cancelled after production has started.';
    }

    if ($currentStatus === 'Completed') {
        return 'This order has already been completed.';
    }

    if ($currentStatus === 'Cancelled') {
        return 'This order has already been cancelled.';
    }

    return 'This order cannot be cancelled from ' . $currentStatus . '.';
}

function getOrderCompletionError(array $deliveryRecords): ?string
{
    if ($deliveryRecords === []) {
        return 'Schedule a Pickup or Delivery record before completing this order. The fulfilment type is not recorded.';
    }

    if (count($deliveryRecords) !== 1) {
        return 'This order has multiple fulfilment records. Resolve the duplicate records before completing it.';
    }

    $delivery = $deliveryRecords[0];
    if ($delivery['delivery_type'] === 'Delivery' && $delivery['status'] !== 'Delivered') {
        return 'This delivery order cannot be completed until its delivery is marked Delivered.';
    }

    if ($delivery['delivery_type'] === 'Pickup' && $delivery['status'] === 'Cancelled') {
        return 'This pickup record is cancelled. Schedule a valid fulfilment record before completing this order.';
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['transition', 'cancel'], true)) {
    requireRole(['admin']);

    $orderId = sanitizePositiveInt($_POST['order_id'] ?? 0);

    if ($orderId <= 0) {
        flash('error', 'The selected order is invalid.');
        redirect('index.php?page=orders');
    }

    try {
        $pdo->beginTransaction();
        $orderStmt = $pdo->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1 FOR UPDATE');
        $orderStmt->execute([':id' => $orderId]);
        $order = $orderStmt->fetch();

        if (!$order) {
            $pdo->rollBack();
            flash('error', 'The selected order could not be found.');
            redirect('index.php?page=orders');
        }

        $currentStatus = (string) $order['status'];
        $isCancellation = ($_POST['action'] ?? '') === 'cancel';
        $requestedStatus = $isCancellation
            ? 'Cancelled'
            : trim((string) ($_POST['target_status'] ?? ''));
        $transitionError = $isCancellation
            ? getOrderCancellationError($currentStatus)
            : getOrderTransitionError($currentStatus, $requestedStatus);

        if ($transitionError !== null) {
            $pdo->rollBack();
            flash('error', $transitionError);
            redirect('index.php?page=orders');
        }

        if ($requestedStatus === 'Completed') {
            $deliveryStmt = $pdo->prepare(
                'SELECT id, delivery_type, status FROM deliveries
                WHERE order_id = :order_id
                FOR UPDATE'
            );
            $deliveryStmt->execute([':order_id' => $orderId]);
            $deliveryRecords = $deliveryStmt->fetchAll();
            $completionError = getOrderCompletionError($deliveryRecords);
            if ($completionError !== null) {
                $pdo->rollBack();
                flash('error', $completionError);
                redirect('index.php?page=orders');
            }
        }

        $updateStmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id AND status = :current_status');
        $updateStmt->execute([
            ':status' => $requestedStatus,
            ':id' => $orderId,
            ':current_status' => $currentStatus,
        ]);

        if ($updateStmt->rowCount() !== 1) {
            $pdo->rollBack();
            flash('error', 'The order status changed before this action could be applied. Please try again.');
            redirect('index.php?page=orders');
        }

        $pdo->commit();
        flash('success', $isCancellation
            ? 'Order cancelled successfully.'
            : 'Order moved to ' . $requestedStatus . '.');
        redirect('index.php?page=orders');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        recordOrderManagementException($exception);
        flash('error', 'Order status could not be updated. Please try again.');
        redirect('index.php?page=orders');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $customerId = sanitizePositiveInt($_POST['customer_id'] ?? 0);
    $quotationRaw = $_POST['quotation_id'] ?? '';
    $quotationId = trim((string) $quotationRaw) === '' ? null : sanitizePositiveInt($quotationRaw);
    $orderDate = trim((string) ($_POST['order_date'] ?? date('Y-m-d')));
    $deadline = trim((string) ($_POST['deadline'] ?? ''));
    $discount = sanitizeNonNegativeFloat($_POST['discount'] ?? 0);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $itemRows = $_POST['items'] ?? [];
    $errors = [];

    if ($customerId <= 0) {
        $errors[] = 'Customer is required.';
    } else {
        $customerStmt = $pdo->prepare('SELECT id FROM customers WHERE id = :id LIMIT 1');
        $customerStmt->execute([':id' => $customerId]);
        if (!$customerStmt->fetch()) {
            $errors[] = 'Selected customer does not exist.';
        }
    }

    if ($quotationId !== null && $quotationId <= 0) {
        $errors[] = 'Quotation selection is invalid.';
    }

    $quotationItemsById = [];
    if ($quotationId !== null) {
        try {
            $pdo->beginTransaction();
            $quotationStmt = $pdo->prepare('SELECT id, customer_id, status FROM quotations WHERE id = :id LIMIT 1 FOR UPDATE');
            $quotationStmt->execute([':id' => $quotationId]);
            $quotation = $quotationStmt->fetch();

            if (!$quotation) {
                $errors[] = 'Selected quotation does not exist.';
            } elseif ((int) $quotation['customer_id'] !== $customerId) {
                $errors[] = 'Selected quotation does not belong to the selected customer.';
            } elseif ($quotation['status'] !== 'Accepted') {
                $errors[] = 'Only accepted quotations can be linked to a new order.';
            } else {
                $quotationItemsStmt = $pdo->prepare(
                    'SELECT qi.id, qi.service_id, qi.description, qi.quantity, qi.unit_price, qi.specifications, s.name AS service_name
                    FROM quotation_items qi
                    JOIN services s ON s.id = qi.service_id
                    WHERE qi.quotation_id = :quotation_id
                    ORDER BY qi.id ASC
                    FOR UPDATE'
                );
                $quotationItemsStmt->execute([':quotation_id' => $quotationId]);
                foreach ($quotationItemsStmt->fetchAll() as $quotationItem) {
                    $quotationItemsById[(int) $quotationItem['id']] = $quotationItem;
                }
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            recordOrderManagementException($exception);
            flash('error', 'Quotation details could not be verified. Please try again.');
            redirect('index.php?page=orders');
        }
    }

    if ($orderDate === '' || !isValidDateString($orderDate)) {
        $errors[] = 'Order date is invalid.';
    }

    if ($deadline === '' || !isValidDateString($deadline)) {
        $errors[] = 'Deadline is required and must be a valid date.';
    }

    if ($deadline !== '' && $orderDate !== '' && isValidDateString($orderDate) && isValidDateString($deadline) && $deadline < $orderDate) {
        $errors[] = 'Deadline cannot be earlier than the order date.';
    }

    if ($deadline !== '' && isValidDateString($deadline) && $deadline < date('Y-m-d')) {
        $errors[] = 'Deadline cannot be in the past for a new order.';
    }

    if ($discount === null) {
        $errors[] = 'Discount must be a valid non-negative number.';
    } elseif ($discount < 0) {
        $errors[] = 'Discount cannot be negative.';
    }

    if (!is_array($itemRows) || $itemRows === []) {
        $errors[] = 'Add at least one order item.';
    }

    $items = [];
    if ($quotationId !== null && $quotation !== false && (int) $quotation['customer_id'] === $customerId) {
        if ($quotationItemsById === []) {
            $errors[] = 'The selected quotation has no items. Add items to the quotation before creating an order.';
        } elseif (!is_array($itemRows) || count($itemRows) !== count($quotationItemsById)) {
            $errors[] = 'Order items do not match the selected quotation.';
        } else {
            $seenQuotationItemIds = [];
            foreach ($itemRows as $index => $itemData) {
                $itemLabel = 'Order item ' . ((int) $index + 1);
                if (!is_array($itemData)) {
                    $errors[] = $itemLabel . ': item data is invalid.';
                    continue;
                }

                if (array_key_exists('unit_price', $itemData) || array_key_exists('total_price', $itemData)) {
                    $errors[] = $itemLabel . ': prices and totals must not be submitted by the browser.';
                    continue;
                }

                $quotationItemId = sanitizePositiveInt($itemData['quotation_item_id'] ?? 0);
                $serviceId = sanitizePositiveInt($itemData['service_id'] ?? 0);
                $quantity = sanitizePositiveInt($itemData['quantity'] ?? 0);
                $specificationsValue = $itemData['specifications'] ?? '';

                if ($quotationItemId <= 0 || !isset($quotationItemsById[$quotationItemId]) || isset($seenQuotationItemIds[$quotationItemId])) {
                    $errors[] = $itemLabel . ': selected quotation item is invalid.';
                    continue;
                }

                $seenQuotationItemIds[$quotationItemId] = true;
                $quotationItem = $quotationItemsById[$quotationItemId];
                if ($serviceId !== (int) $quotationItem['service_id']) {
                    $errors[] = $itemLabel . ': service does not match the selected quotation item.';
                    continue;
                }

                if ($quantity <= 0) {
                    $errors[] = $itemLabel . ': quantity must be greater than 0.';
                    continue;
                }

                if (!is_string($specificationsValue)) {
                    $errors[] = $itemLabel . ': specifications are invalid.';
                    continue;
                }

                $unitPrice = (float) $quotationItem['unit_price'];
                $items[] = [
                    'service_id' => (int) $quotationItem['service_id'],
                    'description' => $quotationItem['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity,
                    'specifications' => trim($specificationsValue),
                ];
            }

            if (count($seenQuotationItemIds) !== count($quotationItemsById)) {
                $errors[] = 'Every item from the selected quotation must be included in the order.';
            }
        }
    } elseif (is_array($itemRows)) {
        foreach ($itemRows as $index => $itemData) {
            if (!is_array($itemData)) {
                $errors[] = 'Order item ' . ($index + 1) . ': item data is invalid.';
                continue;
            }

            if (array_key_exists('unit_price', $itemData) || array_key_exists('total_price', $itemData)) {
                $errors[] = 'Order item ' . ($index + 1) . ': prices and totals must not be submitted by the browser.';
                continue;
            }

            if (isset($itemData['quotation_item_id']) && trim((string) $itemData['quotation_item_id']) !== '') {
                $errors[] = 'Quotation items cannot be submitted without selecting their quotation.';
                continue;
            }

            $serviceId = sanitizePositiveInt($itemData['service_id'] ?? 0);
            $quantity = sanitizePositiveInt($itemData['quantity'] ?? 0);
            $specifications = trim((string) ($itemData['specifications'] ?? ''));
            $itemLabel = 'Order item ' . ($index + 1);

            if ($serviceId <= 0) {
                $errors[] = $itemLabel . ': select a valid service.';
                continue;
            }

            if ($quantity <= 0) {
                $errors[] = $itemLabel . ': quantity must be greater than 0.';
                continue;
            }

            $serviceStmt = $pdo->prepare('SELECT id, name, base_price FROM services WHERE id = :id AND is_active = 1 LIMIT 1');
            $serviceStmt->execute([':id' => $serviceId]);
            $service = $serviceStmt->fetch();

            if (!$service) {
                $errors[] = $itemLabel . ': selected service is invalid or inactive.';
                continue;
            }

            $unitPrice = (float) $service['base_price'];
            $totalPrice = $unitPrice * $quantity;

            $items[] = [
                'service_id' => $serviceId,
                'description' => $service['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'specifications' => $specifications,
            ];
        }
    }

    if ($items === []) {
        $errors[] = 'At least one valid order item is required.';
    }

    if ($errors === []) {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['total_price'];
        }

        if ($discount > $subtotal) {
            $errors[] = 'Discount cannot exceed the order subtotal.';
        }
    }

    if ($errors !== []) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', implode(' | ', $errors));
        redirect('index.php?page=orders');
    }

    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float) $item['total_price'];
    }

    $totalAmount = $subtotal - $discount;
    $orderNo = 'ORD-' . date('Ymd') . '-' . substr((string) time(), -4);

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        if ($quotationId !== null) {
            $lockedQuotationStmt = $pdo->prepare('SELECT customer_id, status FROM quotations WHERE id = :id LIMIT 1 FOR UPDATE');
            $lockedQuotationStmt->execute([':id' => $quotationId]);
            $lockedQuotation = $lockedQuotationStmt->fetch();

            if (!$lockedQuotation
                || (int) $lockedQuotation['customer_id'] !== $customerId
                || $lockedQuotation['status'] !== 'Accepted') {
                $pdo->rollBack();
                flash('error', 'Only an accepted quotation belonging to the selected customer can be linked to a new order.');
                redirect('index.php?page=orders');
            }
        }

        $stmt = $pdo->prepare('INSERT INTO orders (order_number, customer_id, created_by, quotation_id, order_date, deadline, status, subtotal, discount, total_amount, notes) VALUES (:order_number, :customer_id, :created_by, :quotation_id, :order_date, :deadline, :status, :subtotal, :discount, :total_amount, :notes)');
        $stmt->execute([
            ':order_number' => $orderNo,
            ':customer_id' => $customerId,
            ':created_by' => currentUser()['id'],
            ':quotation_id' => $quotationId,
            ':order_date' => $orderDate,
            ':deadline' => $deadline,
            ':status' => 'Pending',
            ':subtotal' => $subtotal,
            ':discount' => $discount,
            ':total_amount' => $totalAmount,
            ':notes' => $notes,
        ]);

        $orderId = (int) $pdo->lastInsertId();
        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, service_id, description, quantity, unit_price, total_price, specifications) VALUES (:order_id, :service_id, :description, :quantity, :unit_price, :total_price, :specifications)');

        foreach ($items as $item) {
            $itemStmt->execute([
                ':order_id' => $orderId,
                ':service_id' => $item['service_id'],
                ':description' => $item['description'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':total_price' => $item['total_price'],
                ':specifications' => $item['specifications'],
            ]);
        }

        $pdo->commit();
        flash('success', 'Order created successfully.');
        redirect('index.php?page=orders');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        recordOrderManagementException($exception);
        flash('error', 'Order creation failed. Please review your entries and try again.');
        redirect('index.php?page=orders');
    }
}

$customers = $pdo->query('SELECT * FROM customers ORDER BY name ASC')->fetchAll();
$services = $pdo->query('SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$quotations = $pdo->query("SELECT id, quotation_number, customer_id FROM quotations WHERE status = 'Accepted' ORDER BY created_at DESC")->fetchAll();
$orders = $pdo->query(
    'SELECT o.*, c.name AS customer_name,
        COALESCE(d.fulfilment_count, 0) AS fulfilment_count,
        d.delivery_type AS fulfilment_type,
        d.delivery_status AS fulfilment_status
    FROM orders o
    JOIN customers c ON c.id = o.customer_id
    LEFT JOIN (
        SELECT order_id, COUNT(*) AS fulfilment_count,
            MIN(delivery_type) AS delivery_type, MIN(status) AS delivery_status
        FROM deliveries
        GROUP BY order_id
    ) d ON d.order_id = o.id
    ORDER BY o.created_at DESC'
)->fetchAll();
?>

<div class="page-header">
    <h2>Orders</h2>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3>Create order</h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
        <div class="form-grid">
            <div>
                <label>Customer</label>
                <select name="customer_id" id="order-customer" required>
                    <option value="">Select customer</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo e($customer['id']); ?>"><?php echo e($customer['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Quotation (optional)</label>
                <select name="quotation_id" id="order-quotation">
                    <option value="">None</option>
                    <?php foreach ($quotations as $quotation): ?>
                        <option value="<?php echo e($quotation['id']); ?>" data-customer-id="<?php echo e($quotation['customer_id']); ?>" hidden disabled><?php echo e($quotation['quotation_number']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Order Date</label>
                <input type="date" name="order_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <label>Deadline</label>
                <input type="date" name="deadline" value="<?php echo date('Y-m-d', strtotime('+5 days')); ?>" required>
            </div>
            <div>
                <label>Discount</label>
                <input type="number" name="discount" step="0.01" min="0" value="0">
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Notes</label>
                <textarea name="notes"></textarea>
            </div>
        </div>

        <h4 style="margin-top: 20px;">Items</h4>
        <div id="order-item-rows" class="order-items-list"></div>
        <p id="order-item-message" class="empty-state" role="status" aria-live="polite"></p>
        <div class="form-grid" style="margin-top: 16px;">
            <div><strong>Subtotal:</strong> <output id="order-subtotal">₹0.00</output></div>
            <div><strong>Total:</strong> <output id="order-total">₹0.00</output></div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="add-order-item">Add item</button>
            <button class="btn" type="submit">Save order</button>
        </div>
    </form>
</div>

<script>
    (() => {
        const customerSelect = document.getElementById('order-customer');
        const quotationSelect = document.getElementById('order-quotation');
        const quotationOptions = Array.from(quotationSelect.options).slice(1);
        const itemList = document.getElementById('order-item-rows');
        const itemMessage = document.getElementById('order-item-message');
        const addItemButton = document.getElementById('add-order-item');
        const discountInput = document.querySelector('input[name="discount"]');
        const subtotalOutput = document.getElementById('order-subtotal');
        const totalOutput = document.getElementById('order-total');
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        const activeServices = <?php
            echo json_encode(
                array_map(static fn (array $service): array => [
                    'id' => (int) $service['id'],
                    'name' => (string) $service['name'],
                    'base_price' => (float) $service['base_price'],
                ], $services),
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
            );
        ?>;
        const moneyFormatter = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
        let nextIndex = 0;
        let itemsDirty = false;
        let previousCustomerId = customerSelect.value;
        let requestNumber = 0;
        let itemsRevision = 0;
        let pendingQuotationLoad = null;

        function updateTotals() {
            let subtotal = 0;
            itemList.querySelectorAll('.order-item-row').forEach((row) => {
                const quantity = Number(row.querySelector('[name$="[quantity]"]').value) || 0;
                const unitPrice = Number(row.dataset.unitPrice) || 0;
                const itemTotal = quantity * unitPrice;
                row.querySelector('[data-item-total]').textContent = moneyFormatter.format(itemTotal);
                subtotal += itemTotal;
            });

            const discount = Number(discountInput.value) || 0;
            subtotalOutput.textContent = moneyFormatter.format(subtotal);
            totalOutput.textContent = moneyFormatter.format(Math.max(0, subtotal - discount));
        }

        function renumberRows() {
            itemList.querySelectorAll('.order-item-row').forEach((row, index) => {
                row.dataset.index = String(index);
                row.querySelectorAll('[data-field]').forEach((field) => {
                    field.name = `items[${index}][${field.dataset.field}]`;
                });
            });
            nextIndex = itemList.querySelectorAll('.order-item-row').length;
        }

        function createRow(item = null) {
            const index = nextIndex++;
            const row = document.createElement('div');
            row.className = 'order-item-row';
            row.dataset.index = String(index);
            row.dataset.unitPrice = item ? String(item.unit_price) : '0';

            if (item) {
                const quotationItemId = document.createElement('input');
                quotationItemId.type = 'hidden';
                quotationItemId.dataset.field = 'quotation_item_id';
                quotationItemId.name = `items[${index}][quotation_item_id]`;
                quotationItemId.value = String(item.quotation_item_id);
                row.appendChild(quotationItemId);

                const serviceId = document.createElement('input');
                serviceId.type = 'hidden';
                serviceId.dataset.field = 'service_id';
                serviceId.name = `items[${index}][service_id]`;
                serviceId.value = String(item.service_id);
                row.appendChild(serviceId);
            }

            const serviceField = document.createElement('div');
            const serviceLabel = document.createElement('label');
            serviceLabel.textContent = 'Service';
            serviceField.appendChild(serviceLabel);

            if (item) {
                const serviceText = document.createElement('p');
                serviceText.textContent = `${item.service_name} — ${item.description}`;
                serviceField.appendChild(serviceText);
                if (!item.is_active) {
                    const inactiveNote = document.createElement('small');
                    inactiveNote.textContent = 'Inactive service retained from this historical quotation.';
                    serviceField.appendChild(inactiveNote);
                }
            } else {
                const serviceSelect = document.createElement('select');
                serviceSelect.required = true;
                serviceSelect.dataset.field = 'service_id';
                serviceSelect.name = `items[${index}][service_id]`;
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Select service';
                serviceSelect.appendChild(emptyOption);
                activeServices.forEach((service) => {
                    const option = document.createElement('option');
                    option.value = String(service.id);
                    option.dataset.unitPrice = String(service.base_price);
                    option.textContent = `${service.name} - ${moneyFormatter.format(service.base_price)}`;
                    serviceSelect.appendChild(option);
                });
                serviceField.appendChild(serviceSelect);
            }
            row.appendChild(serviceField);

            const quantityField = document.createElement('div');
            const quantityLabel = document.createElement('label');
            quantityLabel.textContent = 'Quantity';
            const quantityInput = document.createElement('input');
            quantityInput.type = 'number';
            quantityInput.min = '1';
            quantityInput.value = item ? String(item.quantity) : '1';
            quantityInput.required = true;
            quantityInput.dataset.field = 'quantity';
            quantityInput.name = `items[${index}][quantity]`;
            quantityField.append(quantityLabel, quantityInput);
            row.appendChild(quantityField);

            const specificationField = document.createElement('div');
            const specificationLabel = document.createElement('label');
            specificationLabel.textContent = 'Specifications';
            const specifications = document.createElement('textarea');
            specifications.rows = 2;
            specifications.placeholder = 'Optional details';
            specifications.dataset.field = 'specifications';
            specifications.name = `items[${index}][specifications]`;
            specifications.value = item ? item.specifications : '';
            specificationField.append(specificationLabel, specifications);
            row.appendChild(specificationField);

            const price = document.createElement('p');
            price.append('Unit price: ', document.createTextNode(moneyFormatter.format(Number(row.dataset.unitPrice))));
            price.append(' | Item total: ');
            const itemTotal = document.createElement('output');
            itemTotal.dataset.itemTotal = '';
            price.appendChild(itemTotal);
            row.appendChild(price);

            const actions = document.createElement('div');
            actions.className = 'item-actions';
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn btn-secondary btn-small';
            removeButton.dataset.removeItem = '';
            removeButton.textContent = 'Remove';
            removeButton.disabled = Boolean(item);
            actions.appendChild(removeButton);
            row.appendChild(actions);

            itemList.appendChild(row);
            return row;
        }

        function resetToDirectOrder(message = '') {
            itemList.replaceChildren();
            nextIndex = 0;
            createRow();
            itemsRevision++;
            itemMessage.textContent = message;
            addItemButton.disabled = false;
            itemsDirty = false;
            updateTotals();
        }

        function invalidateQuotationLoad() {
            requestNumber++;
            if (pendingQuotationLoad !== null) {
                addItemButton.disabled = pendingQuotationLoad.addItemDisabled;
                itemMessage.textContent = pendingQuotationLoad.itemMessage;
                pendingQuotationLoad = null;
            }
        }

        function filterQuotations() {
            const customerId = customerSelect.value;
            quotationOptions.forEach((option) => {
                const belongsToCustomer = customerId !== '' && option.dataset.customerId === customerId;
                option.hidden = !belongsToCustomer;
                option.disabled = !belongsToCustomer;
            });
        }

        function hasUnsavedItems() {
            return itemsDirty && itemList.querySelectorAll('.order-item-row').length > 0;
        }

        async function loadSelectedQuotation(previousQuotationId) {
            invalidateQuotationLoad();
            const currentRequest = requestNumber;
            const quotationId = quotationSelect.value;
            if (hasUnsavedItems() && !window.confirm('Replace the current order items with the selected quotation or direct-order items?')) {
                quotationSelect.value = previousQuotationId;
                return;
            }

            if (quotationId === '') {
                resetToDirectOrder();
                return;
            }

            const customerId = customerSelect.value;
            const startingItemsRevision = itemsRevision;
            pendingQuotationLoad = {
                addItemDisabled: addItemButton.disabled,
                itemMessage: itemMessage.textContent,
            };
            itemMessage.textContent = 'Loading quotation items…';
            addItemButton.disabled = true;

            try {
                const response = await fetch('<?php echo e(assetUrl('pages/orders/quotation-items.php')); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    credentials: 'same-origin',
                    body: new URLSearchParams({
                        csrf_token: csrfToken,
                        customer_id: customerId,
                        quotation_id: quotationId,
                    }),
                });
                const result = await response.json();
                if (!response.ok || !Array.isArray(result.items)) {
                    throw new Error(result.error || 'Quotation items could not be loaded.');
                }
                if (currentRequest !== requestNumber
                    || quotationSelect.value !== quotationId
                    || customerSelect.value !== customerId) {
                    return;
                }
                if (itemsRevision !== startingItemsRevision) {
                    quotationSelect.value = previousQuotationId;
                    addItemButton.disabled = pendingQuotationLoad.addItemDisabled;
                    pendingQuotationLoad = null;
                    itemMessage.textContent = 'Order items changed while the quotation was loading. Your items were preserved; select the quotation again to replace them.';
                    return;
                }

                itemList.replaceChildren();
                nextIndex = 0;
                if (result.items.length === 0) {
                    itemMessage.textContent = 'This quotation has no items. Add items to the quotation before creating an order.';
                } else {
                    result.items.forEach((item) => createRow(item));
                    itemMessage.textContent = 'Review the quotation items. You can adjust quantities and specifications before saving.';
                }
                itemsRevision++;
                addItemButton.disabled = true;
                pendingQuotationLoad = null;
                itemsDirty = false;
                updateTotals();
            } catch (error) {
                if (currentRequest !== requestNumber) {
                    return;
                }
                quotationSelect.value = previousQuotationId;
                addItemButton.disabled = pendingQuotationLoad.addItemDisabled;
                pendingQuotationLoad = null;
                itemMessage.textContent = error.message || 'Quotation items could not be loaded.';
            }
        }

        let previousQuotationId = quotationSelect.value;
        quotationSelect.addEventListener('change', async () => {
            const selectedQuotation = quotationSelect.selectedOptions[0];
            if (quotationSelect.value !== '' && selectedQuotation.dataset.customerId !== customerSelect.value) {
                quotationSelect.value = previousQuotationId;
                itemMessage.textContent = 'Choose a quotation belonging to the selected customer.';
                return;
            }
            const selectedId = quotationSelect.value;
            await loadSelectedQuotation(previousQuotationId);
            previousQuotationId = quotationSelect.value === selectedId ? selectedId : quotationSelect.value;
        });

        customerSelect.addEventListener('change', () => {
            const oldCustomerId = previousCustomerId;
            filterQuotations();
            const selectedQuotation = quotationSelect.selectedOptions[0];
            if (quotationSelect.value !== '' && selectedQuotation.dataset.customerId !== customerSelect.value) {
                if (hasUnsavedItems() && !window.confirm('Changing the customer will clear the quotation items. Continue?')) {
                    customerSelect.value = oldCustomerId;
                    filterQuotations();
                    return;
                }
                invalidateQuotationLoad();
                quotationSelect.value = '';
                previousQuotationId = '';
                resetToDirectOrder('Quotation items were cleared because the selected customer changed.');
            }
            previousCustomerId = customerSelect.value;
        });

        itemList.addEventListener('input', () => {
            itemsRevision++;
            itemsDirty = true;
            updateTotals();
        });
        itemList.addEventListener('change', (event) => {
            const serviceSelect = event.target.closest('select[data-field="service_id"]');
            if (serviceSelect) {
                const selectedOption = serviceSelect.selectedOptions[0];
                serviceSelect.closest('.order-item-row').dataset.unitPrice = selectedOption.dataset.unitPrice || '0';
            }
            itemsRevision++;
            itemsDirty = true;
            updateTotals();
        });
        itemList.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-item]');
            if (!removeButton || removeButton.disabled) {
                return;
            }
            const rows = itemList.querySelectorAll('.order-item-row');
            if (rows.length > 1) {
                removeButton.closest('.order-item-row').remove();
                renumberRows();
                itemsRevision++;
                itemsDirty = true;
                updateTotals();
            }
        });
        addItemButton.addEventListener('click', () => {
            createRow();
            itemsRevision++;
            itemsDirty = true;
            itemMessage.textContent = '';
            updateTotals();
        });
        discountInput.addEventListener('input', updateTotals);

        resetToDirectOrder();
        filterQuotations();
    })();
</script>

<div class="card">
    <h3>Order list</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Deadline</th>
                <th>Total</th>
                <?php if ($isAdmin): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($orders): ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo e($order['order_number']); ?></td>
                        <td><?php echo e($order['customer_name']); ?></td>
                        <td><span class="pill"><?php echo e($order['status']); ?></span></td>
                        <td><?php echo e($order['deadline']); ?></td>
                        <td><?php echo e(money((float) $order['total_amount'])); ?></td>
                        <?php if ($isAdmin): ?>
                            <td>
                                <?php $nextStatus = getNextOrderStatus((string) $order['status']); ?>
                                <?php if ($nextStatus !== null): ?>
                                    <?php
                                    $actionLabels = [
                                        'Confirmed' => 'Confirm order',
                                        'In Production' => 'Start production',
                                        'Ready' => 'Mark ready',
                                        'Completed' => 'Complete order',
                                    ];
                                    $completionError = null;
                                    if ($nextStatus === 'Completed') {
                                        $fulfilmentCount = (int) $order['fulfilment_count'];
                                        $completionRecords = $fulfilmentCount === 1
                                            ? [[
                                                'delivery_type' => $order['fulfilment_type'],
                                                'status' => $order['fulfilment_status'],
                                            ]]
                                            : array_fill(0, $fulfilmentCount, []);
                                        $completionError = getOrderCompletionError($completionRecords);
                                    }
                                    ?>
                                    <?php if ($completionError !== null): ?>
                                        <button type="button" class="btn btn-small" disabled title="<?php echo e($completionError); ?>"><?php echo e($actionLabels[$nextStatus] ?? 'Advance order'); ?></button>
                                        <small><?php echo e($completionError); ?></small>
                                    <?php else: ?>
                                        <form method="post" data-confirm="Move this order from <?php echo e($order['status']); ?> to <?php echo e($nextStatus); ?>?" style="display: inline-block; margin-right: 8px;">
                                            <input type="hidden" name="action" value="transition">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                                            <input type="hidden" name="order_id" value="<?php echo e($order['id']); ?>">
                                            <input type="hidden" name="target_status" value="<?php echo e($nextStatus); ?>">
                                            <button type="submit" class="btn btn-small"><?php echo e($actionLabels[$nextStatus] ?? 'Advance order'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (getOrderCancellationError((string) $order['status']) === null): ?>
                                    <form method="post" data-confirm="Cancel order <?php echo e($order['order_number']); ?>? This cannot be undone." style="display: inline-block;">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                                        <input type="hidden" name="order_id" value="<?php echo e($order['id']); ?>">
                                        <button type="submit" class="btn btn-secondary btn-small">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo $isAdmin ? '6' : '5'; ?>"><p class="empty-state">No orders yet.</p></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
