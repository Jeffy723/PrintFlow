<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();

$validDeliveryTypes = ['Pickup', 'Delivery'];
$validStatuses = ['Pending', 'Out for Delivery', 'Delivered', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect('index.php?page=deliveries');
    }

    $idInput = trim((string) ($_POST['id'] ?? ''));
    $id = $idInput === '' ? null : filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $orderId = filter_var(trim((string) ($_POST['order_id'] ?? '')), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $deliveryType = trim((string) ($_POST['delivery_type'] ?? 'Pickup'));
    $address = trim((string) ($_POST['address'] ?? ''));
    $scheduledDate = (string) ($_POST['scheduled_date'] ?? date('Y-m-d'));
    $status = trim((string) ($_POST['status'] ?? 'Pending'));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($id === false) {
        flash('error', 'The selected delivery is invalid.');
        redirect('index.php?page=deliveries');
    }
    if ($orderId === false) {
        flash('error', 'Choose a valid order.');
        redirect('index.php?page=deliveries');
    }

    if (!in_array($deliveryType, $validDeliveryTypes, true)) {
        flash('error', 'Delivery type is invalid.');
        redirect('index.php?page=deliveries');
    }

    if ($deliveryType === 'Delivery' && $address === '') {
        flash('error', 'Delivery address is required for delivery orders.');
        redirect('index.php?page=deliveries');
    }

    if (!in_array($status, $validStatuses, true)) {
        flash('error', 'Delivery status is invalid.');
        redirect('index.php?page=deliveries');
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $scheduledDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if ($parsedDate === false
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $parsedDate->format('Y-m-d') !== $scheduledDate) {
        flash('error', 'Enter a valid scheduled date.');
        redirect('index.php?page=deliveries');
    }

    try {
        $pdo->beginTransaction();
        $orderStmt = $pdo->prepare('SELECT id, status, total_amount FROM orders WHERE id = :id FOR UPDATE');
        $orderStmt->execute([':id' => $orderId]);
        $orderData = $orderStmt->fetch();
        if (!$orderData) {
            throw new DomainException('The selected order was not found.');
        }

        $previousDelivery = null;
        if ($id !== null) {
            $existingStmt = $pdo->prepare('SELECT order_id, delivery_type, status, delivered_at FROM deliveries WHERE id = :id FOR UPDATE');
            $existingStmt->execute([':id' => $id]);
            $previousDelivery = $existingStmt->fetch();
            if (!$previousDelivery) {
                throw new DomainException('The selected delivery was not found.');
            }
        }

        $wasDeliveredForSameOrder = $previousDelivery
            && $previousDelivery['status'] === 'Delivered'
            && (int) $previousDelivery['order_id'] === (int) $orderId;
        if ($previousDelivery && (int) $previousDelivery['order_id'] !== (int) $orderId) {
            throw new DomainException('An existing delivery cannot be reassigned to a different order.');
        }

        if ($previousDelivery && $previousDelivery['delivery_type'] !== $deliveryType
            && ($previousDelivery['status'] !== 'Pending' || $status !== 'Pending')) {
            throw new DomainException('Delivery type cannot be changed after dispatch or cancellation.');
        }

        if ($previousDelivery === null && $status !== 'Pending') {
            throw new DomainException('A new delivery must be scheduled as Pending before it can be dispatched.');
        }

        if ($previousDelivery === null) {
            if (in_array($orderData['status'], ['Completed', 'Cancelled'], true)) {
                throw new DomainException('A delivery cannot be scheduled for a completed or cancelled order.');
            }

            $existingDeliveryStmt = $pdo->prepare('SELECT id FROM deliveries WHERE order_id = :order_id LIMIT 1 FOR UPDATE');
            $existingDeliveryStmt->execute([':order_id' => $orderId]);
            if ($existingDeliveryStmt->fetch()) {
                throw new DomainException('This order already has a fulfilment record. Edit that record instead of creating a duplicate.');
            }
        }

        if ($previousDelivery && $status !== $previousDelivery['status']) {
            $allowedNextStatuses = [
                'Pending' => ['Out for Delivery', 'Cancelled'],
                'Out for Delivery' => ['Delivered', 'Cancelled'],
                'Delivered' => [],
                'Cancelled' => [],
            ];

            if (!in_array($status, $allowedNextStatuses[$previousDelivery['status']] ?? [], true)) {
                throw new DomainException(
                    'A delivery cannot move from ' . $previousDelivery['status'] . ' to ' . $status . '.'
                );
            }

            if (in_array($status, ['Out for Delivery', 'Delivered'], true)
                && $orderData['status'] !== 'Ready') {
                $message = $status === 'Out for Delivery'
                    ? 'The order must be Ready before the delivery can be dispatched. Current order status: ' . $orderData['status'] . '.'
                    : 'The order must be Ready before its delivery can be marked Delivered. Current order status: ' . $orderData['status'] . '.';
                throw new DomainException($message);
            }
        }

        if ($status === 'Delivered' && $previousDelivery && $previousDelivery['status'] !== 'Delivered') {
            $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = :order_id');
            $paidStmt->execute([':order_id' => $orderId]);
            $totalAmountCents = (int) round((float) $orderData['total_amount'] * 100);
            $totalPaidCents = (int) round((float) $paidStmt->fetchColumn() * 100);
            if ($totalPaidCents < $totalAmountCents) {
                $outstandingCents = $totalAmountCents - $totalPaidCents;
                throw new DomainException(
                    'The order must be fully paid before it can be marked Delivered. Outstanding balance: '
                    . money($outstandingCents / 100) . '.'
                );
            }
        }

        if ($status === 'Delivered') {
            $deliveredAt = $wasDeliveredForSameOrder && $previousDelivery['delivered_at'] !== null
                ? $previousDelivery['delivered_at']
                : date('Y-m-d H:i:s');
        } else {
            $deliveredAt = $previousDelivery['delivered_at'] ?? null;
        }

        if ($id !== null) {
            $stmt = $pdo->prepare('UPDATE deliveries SET order_id = :order_id, delivery_type = :delivery_type, address = :address, scheduled_date = :scheduled_date, status = :status, delivered_at = :delivered_at, notes = :notes WHERE id = :id');
            $stmt->execute([
                ':order_id' => $orderId,
                ':delivery_type' => $deliveryType,
                ':address' => $address,
                ':scheduled_date' => $scheduledDate,
                ':status' => $status,
                ':delivered_at' => $deliveredAt,
                ':notes' => $notes,
                ':id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO deliveries (order_id, delivery_type, address, scheduled_date, status, delivered_at, notes) VALUES (:order_id, :delivery_type, :address, :scheduled_date, :status, :delivered_at, :notes)');
            $stmt->execute([
                ':order_id' => $orderId,
                ':delivery_type' => $deliveryType,
                ':address' => $address,
                ':scheduled_date' => $scheduledDate,
                ':status' => $status,
                ':delivered_at' => $deliveredAt,
                ':notes' => $notes,
            ]);
        }
        $pdo->commit();
        flash('success', $id !== null ? 'Delivery updated successfully.' : 'Delivery scheduled successfully.');
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Delivery save error: ' . $exception->getMessage());
        flash('error', 'Delivery could not be saved. Please review the details and try again.');
    }

    redirect('index.php?page=deliveries');
}

$viewId = isset($_GET['view']) && is_numeric($_GET['view']) ? (int) $_GET['view'] : null;
$editId = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int) $_GET['edit'] : null;

$orders = $pdo->query('SELECT o.id, o.order_number, c.name AS customer_name FROM orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.created_at DESC')->fetchAll();

$viewDelivery = null;
if ($viewId !== null) {
    $viewStmt = $pdo->prepare('SELECT d.*, o.order_number, c.name AS customer_name FROM deliveries d JOIN orders o ON o.id = d.order_id JOIN customers c ON c.id = o.customer_id WHERE d.id = :id LIMIT 1');
    $viewStmt->execute([':id' => $viewId]);
    $viewDelivery = $viewStmt->fetch();
}

$editDelivery = null;
if ($editId !== null) {
    $editStmt = $pdo->prepare(
        'SELECT d.*, o.order_number, o.status AS order_status, o.total_amount,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.order_id = o.id) AS total_paid,
            c.name AS customer_name
        FROM deliveries d
        JOIN orders o ON o.id = d.order_id
        JOIN customers c ON c.id = o.customer_id
        WHERE d.id = :id LIMIT 1'
    );
    $editStmt->execute([':id' => $editId]);
    $editDelivery = $editStmt->fetch();
}

$availableStatusOptions = ['Pending'];
if ($editDelivery) {
    $availableStatusOptions = [$editDelivery['status']];
    $nextDeliveryStatuses = [
        'Pending' => ['Out for Delivery', 'Cancelled'],
        'Out for Delivery' => ['Delivered', 'Cancelled'],
        'Delivered' => [],
        'Cancelled' => [],
    ];
    foreach ($nextDeliveryStatuses[$editDelivery['status']] ?? [] as $nextDeliveryStatus) {
        if ($nextDeliveryStatus === 'Out for Delivery' && $editDelivery['order_status'] !== 'Ready') {
            continue;
        }
        if ($nextDeliveryStatus === 'Delivered'
            && ($editDelivery['order_status'] !== 'Ready'
                || (float) $editDelivery['total_paid'] < (float) $editDelivery['total_amount'])) {
            continue;
        }
        $availableStatusOptions[] = $nextDeliveryStatus;
    }
}

$deliveries = $pdo->query('SELECT d.*, o.order_number, c.name AS customer_name FROM deliveries d JOIN orders o ON o.id = d.order_id JOIN customers c ON c.id = o.customer_id ORDER BY d.scheduled_date DESC')->fetchAll();
?>

<div class="page-header">
    <h2>Deliveries</h2>
    <a class="btn btn-secondary" href="index.php?page=deliveries">Refresh</a>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3><?php echo $editDelivery ? 'Edit delivery' : 'Schedule delivery'; ?></h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
        <?php if ($editDelivery): ?>
            <input type="hidden" name="id" value="<?php echo e($editDelivery['id']); ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>Order</label>
                <select name="order_id" required>
                    <option value="">Select order</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?php echo e($order['id']); ?>" <?php echo (($editDelivery['order_id'] ?? '') == $order['id']) ? 'selected' : ''; ?>><?php echo e($order['order_number']); ?> - <?php echo e($order['customer_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Delivery Type</label>
                <select name="delivery_type">
                    <?php foreach ($validDeliveryTypes as $deliveryTypeOption): ?>
                        <option value="<?php echo e($deliveryTypeOption); ?>" <?php echo (($editDelivery['delivery_type'] ?? 'Pickup') === $deliveryTypeOption) ? 'selected' : ''; ?>><?php echo e($deliveryTypeOption); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Scheduled Date</label>
                <input type="date" name="scheduled_date" value="<?php echo e($editDelivery['scheduled_date'] ?? date('Y-m-d', strtotime('+2 days'))); ?>" required>
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <?php foreach ($availableStatusOptions as $statusOption): ?>
                        <option value="<?php echo e($statusOption); ?>" <?php echo (($editDelivery['status'] ?? 'Pending') === $statusOption) ? 'selected' : ''; ?>><?php echo e($statusOption); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Dispatch requires a Ready order. Marking it Delivered also requires the order to be fully paid.</small>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Address</label>
                <textarea name="address"><?php echo e($editDelivery['address'] ?? ''); ?></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Notes</label>
                <textarea name="notes"><?php echo e($editDelivery['notes'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-actions business-form-actions">
            <button class="btn" type="submit"><?php echo $editDelivery ? 'Update delivery' : 'Save delivery'; ?></button>
            <?php if ($editDelivery): ?>
                <a class="btn btn-secondary" href="index.php?page=deliveries">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($viewDelivery): ?>
    <div class="card" style="margin-bottom: 24px;">
        <h3>Delivery details</h3>
        <div class="form-grid">
            <div>
                <label>Order</label>
                <p><?php echo e($viewDelivery['order_number'] . ' - ' . $viewDelivery['customer_name']); ?></p>
            </div>
            <div>
                <label>Type</label>
                <p><?php echo e($viewDelivery['delivery_type']); ?></p>
            </div>
            <div>
                <label>Scheduled Date</label>
                <p><?php echo e($viewDelivery['scheduled_date']); ?></p>
            </div>
            <div>
                <label>Status</label>
                <p><span class="pill"><?php echo e($viewDelivery['status']); ?></span></p>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Address</label>
                <p><?php echo e($viewDelivery['address'] ?: '-'); ?></p>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Notes</label>
                <p><?php echo e($viewDelivery['notes'] ?: '-'); ?></p>
            </div>
            <div>
                <label>Delivered At</label>
                <p><?php echo e($viewDelivery['delivered_at'] ?: '-'); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Delivery schedule</h3>
    <div class="business-table-wrap">
    <table class="table business-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($deliveries): ?>
                <?php foreach ($deliveries as $delivery): ?>
                    <tr>
                        <td><?php echo e($delivery['order_number']); ?></td>
                        <td><?php echo e($delivery['customer_name']); ?></td>
                        <td><?php echo e($delivery['delivery_type']); ?></td>
                        <td><?php echo e($delivery['scheduled_date']); ?></td>
                        <td><span class="pill"><?php echo e($delivery['status']); ?></span></td>
                        <td class="actions business-actions">
                            <a class="btn btn-secondary" href="index.php?page=deliveries&view=<?php echo e($delivery['id']); ?>">View</a>
                            <a class="btn btn-secondary" href="index.php?page=deliveries&edit=<?php echo e($delivery['id']); ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6"><p class="empty-state">No deliveries scheduled.</p></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
