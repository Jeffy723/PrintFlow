<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $quotationDate = trim((string) ($_POST['quotation_date'] ?? date('Y-m-d')));
    $validUntil = trim((string) ($_POST['valid_until'] ?? ''));
    $discount = (float) ($_POST['discount'] ?? 0);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $serviceIds = $_POST['service_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($customerId <= 0 || $validUntil === '') {
        flash('error', 'Customer and valid date are required.');
        redirect('index.php?page=quotations');
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
        $totalPrice = $unitPrice * $quantity;
        $items[] = [
            'service_id' => $cleanServiceId,
            'description' => $service['name'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
        ];
    }

    if ($items === []) {
        flash('error', 'Add at least one active service item to the quotation.');
        redirect('index.php?page=quotations');
    }

    $subtotal = array_sum(array_column($items, 'total_price'));
    $totalAmount = max($subtotal - $discount, 0);
    $quoteNo = 'Q-' . date('Ymd') . '-' . substr((string) time(), -4);

    $stmt = $pdo->prepare('INSERT INTO quotations (quotation_number, customer_id, created_by, quotation_date, valid_until, status, subtotal, discount, total_amount, notes) VALUES (:quotation_number, :customer_id, :created_by, :quotation_date, :valid_until, :status, :subtotal, :discount, :total_amount, :notes)');
    $stmt->execute([
        ':quotation_number' => $quoteNo,
        ':customer_id' => $customerId,
        ':created_by' => currentUser()['id'],
        ':quotation_date' => $quotationDate,
        ':valid_until' => $validUntil,
        ':status' => 'Draft',
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':total_amount' => $totalAmount,
        ':notes' => $notes,
    ]);

    $quotationId = (int) $pdo->lastInsertId();
    $itemStmt = $pdo->prepare('INSERT INTO quotation_items (quotation_id, service_id, description, quantity, unit_price, total_price, specifications) VALUES (:quotation_id, :service_id, :description, :quantity, :unit_price, :total_price, :specifications)');

    foreach ($items as $item) {
        $itemStmt->execute([
            ':quotation_id' => $quotationId,
            ':service_id' => $item['service_id'],
            ':description' => $item['description'],
            ':quantity' => $item['quantity'],
            ':unit_price' => $item['unit_price'],
            ':total_price' => $item['total_price'],
            ':specifications' => '',
        ]);
    }

    flash('success', 'Quotation created successfully.');
    redirect('index.php?page=quotations');
}

$customers = $pdo->query('SELECT * FROM customers ORDER BY name ASC')->fetchAll();
$services = $pdo->query('SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$quotations = $pdo->query('SELECT q.*, c.name AS customer_name FROM quotations q JOIN customers c ON c.id = q.customer_id ORDER BY q.created_at DESC')->fetchAll();
?>

<div class="page-header">
    <h2>Quotations</h2>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3>Create quotation</h3>
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
                <label>Quotation Date</label>
                <input type="date" name="quotation_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <label>Valid Until</label>
                <input type="date" name="valid_until" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
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
            <button class="btn" type="submit">Save quotation</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Quotation history</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Quotation</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
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
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5"><p class="empty-state">No quotations yet.</p></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
