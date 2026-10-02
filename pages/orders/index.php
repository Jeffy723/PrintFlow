<?php

declare(strict_types=1);

requireLogin();
requireRole(['admin', 'staff']);
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

    if ($quotationId !== null) {
        $quotationStmt = $pdo->prepare('SELECT id, customer_id FROM quotations WHERE id = :id LIMIT 1');
        $quotationStmt->execute([':id' => $quotationId]);
        $quotation = $quotationStmt->fetch();

        if (!$quotation) {
            $errors[] = 'Selected quotation does not exist.';
        } elseif ((int) $quotation['customer_id'] !== $customerId) {
            $errors[] = 'Selected quotation does not belong to the selected customer.';
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
    if (is_array($itemRows)) {
        foreach ($itemRows as $index => $itemData) {
            if (!is_array($itemData)) {
                $errors[] = 'Order item ' . ($index + 1) . ': item data is invalid.';
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
        $pdo->beginTransaction();

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
$quotations = $pdo->query('SELECT * FROM quotations ORDER BY created_at DESC')->fetchAll();
$orders = $pdo->query('SELECT o.*, c.name AS customer_name FROM orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.created_at DESC')->fetchAll();
?>

<div class="page-header">
    <h2>Orders</h2>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3>Create order</h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <div class="form-grid">
            <div>
                <label>Customer</label>
                <select name="customer_id" required>
                    <option value="">Select customer</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo e($customer['id']); ?>"><?php echo e($customer['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Quotation (optional)</label>
                <select name="quotation_id">
                    <option value="">None</option>
                    <?php foreach ($quotations as $quotation): ?>
                        <option value="<?php echo e($quotation['id']); ?>"><?php echo e($quotation['quotation_number']); ?></option>
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
        <div id="order-items" class="order-items-list">
            <div class="order-item-row">
                <div>
                    <label>Service</label>
                    <select name="items[0][service_id]" required>
                        <option value="">Select service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo e($service['id']); ?>"><?php echo e($service['name']); ?> - <?php echo e(money((float) $service['base_price'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Quantity</label>
                    <input type="number" name="items[0][quantity]" min="1" value="1" required>
                </div>
                <div>
                    <label>Specifications</label>
                    <textarea name="items[0][specifications]" rows="2" placeholder="Optional details"></textarea>
                </div>
                <div class="item-actions">
                    <button type="button" class="btn btn-secondary btn-small" data-remove-item>Remove</button>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" data-add-item>Add item</button>
            <button class="btn" type="submit">Save order</button>
        </div>
    </form>
</div>

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
                                    ?>
                                    <form method="post" data-confirm="Move this order from <?php echo e($order['status']); ?> to <?php echo e($nextStatus); ?>?" style="display: inline-block; margin-right: 8px;">
                                        <input type="hidden" name="action" value="transition">
                                        <input type="hidden" name="order_id" value="<?php echo e($order['id']); ?>">
                                        <input type="hidden" name="target_status" value="<?php echo e($nextStatus); ?>">
                                        <button type="submit" class="btn btn-small"><?php echo e($actionLabels[$nextStatus] ?? 'Advance order'); ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (getOrderCancellationError((string) $order['status']) === null): ?>
                                    <form method="post" data-confirm="Cancel order <?php echo e($order['order_number']); ?>? This cannot be undone." style="display: inline-block;">
                                        <input type="hidden" name="action" value="cancel">
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
