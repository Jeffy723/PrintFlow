<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $deliveryType = trim((string) ($_POST['delivery_type'] ?? 'Pickup'));
    $address = trim((string) ($_POST['address'] ?? ''));
    $scheduledDate = trim((string) ($_POST['scheduled_date'] ?? date('Y-m-d')));
    $status = trim((string) ($_POST['status'] ?? 'Pending'));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($orderId <= 0) {
        flash('error', 'Choose a valid order.');
        redirect('index.php?page=deliveries');
    }

    if ($deliveryType === 'Delivery' && $address === '') {
        flash('error', 'Delivery address is required for delivery orders.');
        redirect('index.php?page=deliveries');
    }

    $stmt = $pdo->prepare('INSERT INTO deliveries (order_id, delivery_type, address, scheduled_date, status, notes) VALUES (:order_id, :delivery_type, :address, :scheduled_date, :status, :notes)');
    $stmt->execute([
        ':order_id' => $orderId,
        ':delivery_type' => $deliveryType,
        ':address' => $address,
        ':scheduled_date' => $scheduledDate,
        ':status' => $status,
        ':notes' => $notes,
    ]);

    flash('success', 'Delivery scheduled successfully.');
    redirect('index.php?page=deliveries');
}

$orders = $pdo->query('SELECT o.id, o.order_number, c.name AS customer_name FROM orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.created_at DESC')->fetchAll();
$deliveries = $pdo->query('SELECT d.*, o.order_number, c.name AS customer_name FROM deliveries d JOIN orders o ON o.id = d.order_id JOIN customers c ON c.id = o.customer_id ORDER BY d.scheduled_date DESC')->fetchAll();
?>

<div class="page-header">
    <h2>Deliveries</h2>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3>Schedule delivery</h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <div class="form-grid">
            <div>
                <label>Order</label>
                <select name="order_id" required>
                    <option value="">Select order</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?php echo e($order['id']); ?>"><?php echo e($order['order_number']); ?> - <?php echo e($order['customer_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Delivery Type</label>
                <select name="delivery_type">
                    <option>Pickup</option>
                    <option>Delivery</option>
                </select>
            </div>
            <div>
                <label>Scheduled Date</label>
                <input type="date" name="scheduled_date" value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
            </div>
            <div>
                <label>Status</label>
                <select name="status">
                    <option>Pending</option>
                    <option>Out for Delivery</option>
                    <option>Delivered</option>
                    <option>Cancelled</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Address</label>
                <textarea name="address"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Notes</label>
                <textarea name="notes"></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Save delivery</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Delivery schedule</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Date</th>
                <th>Status</th>
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
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5"><p class="empty-state">No deliveries scheduled.</p></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
