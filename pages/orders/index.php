<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $quotationId = !empty($_POST['quotation_id']) ? (int) $_POST['quotation_id'] : null;
    $orderDate = trim((string) ($_POST['order_date'] ?? date('Y-m-d')));
    $deadline = trim((string) ($_POST['deadline'] ?? ''));
    $discount = (float) ($_POST['discount'] ?? 0);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'Pending'));
    $serviceIds = $_POST['service_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($customerId <= 0 || $deadline === '') {
        flash('error', 'Customer and deadline are required for an order.');
        redirect('index.php?page=orders');
    }

    $items = [];
    foreach ($serviceIds as $index => $serviceId) {
        $cleanServiceId = (int) $serviceId;
        $quantity = (int) ($quantities[$index] ?? 0);
        if ($cleanServiceId <= 0 || $quantity <= 0) {
            continue;
        }

        $serviceStmt = $pdo->prepare('SELECT id, name, base_price FROM services WHERE id = :id AND is_active = 1 LIMIT 1');
        $serviceStmt->execute([':id' => $cleanServiceId]);
        $service = $serviceStmt->fetch();

        if (!$service) {
            continue;
        }

        $unitPrice = (float) $service['base_price'];
        $items[] = [
            'service_id' => $cleanServiceId,
            'description' => $service['name'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
        ];
    }

    if ($items === []) {
        flash('error', 'Add at least one order item.');
        redirect('index.php?page=orders');
    }

    $subtotal = array_sum(array_column($items, 'total_price'));
    $totalAmount = max($subtotal - $discount, 0);
    $orderNo = 'ORD-' . date('Ymd') . '-' . substr((string) time(), -4);

    $stmt = $pdo->prepare('INSERT INTO orders (order_number, customer_id, created_by, quotation_id, order_date, deadline, status, subtotal, discount, total_amount, notes) VALUES (:order_number, :customer_id, :created_by, :quotation_id, :order_date, :deadline, :status, :subtotal, :discount, :total_amount, :notes)');
    $stmt->execute([
        ':order_number' => $orderNo,
        ':customer_id' => $customerId,
        ':created_by' => currentUser()['id'],
        ':quotation_id' => $quotationId,
        ':order_date' => $orderDate,
        ':deadline' => $deadline,
        ':status' => $status,
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
            ':specifications' => '',
        ]);
    }

    flash('success', 'Order created successfully.');
    redirect('index.php?page=orders');
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
                <label>Order Status</label>
                <select name="status">
                    <option>Pending</option>
                    <option>Confirmed</option>
                    <option>In Production</option>
                    <option>Ready</option>
                    <option>Completed</option>
                    <option>Cancelled</option>
                </select>
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
        <div class="form-grid">
            <div>
                <label>Service</label>
                <select name="service_id[]">
                    <option value="">Select service</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo e($service['id']); ?>"><?php echo e($service['name']); ?> - <?php echo e(money((float) $service['base_price'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Quantity</label>
                <input type="number" name="quantity[]" min="1" value="1">
            </div>
        </div>

        <div class="form-actions">
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
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5"><p class="empty-state">No orders yet.</p></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
