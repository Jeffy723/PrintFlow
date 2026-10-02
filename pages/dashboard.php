<?php

declare(strict_types=1);

requireLogin();

try {
    $pdo = getDatabaseConnection();

    $stats = [
        'customers' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'services' => (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn(),
        'orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'quotations' => (int) $pdo->query('SELECT COUNT(*) FROM quotations')->fetchColumn(),
    ];

    $productionCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'In Production'")->fetchColumn();
    $pendingPayments = (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
    $recentOrders = $pdo->query('SELECT o.id, o.order_number, c.name AS customer_name, o.status, o.total_amount, o.order_date FROM orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.created_at DESC LIMIT 5')->fetchAll();
    $recentQuotations = $pdo->query('SELECT q.id, q.quotation_number, c.name AS customer_name, q.total_amount, q.status FROM quotations q JOIN customers c ON c.id = q.customer_id ORDER BY q.created_at DESC LIMIT 5')->fetchAll();
} catch (Throwable $exception) {
    error_log('Dashboard statistics error: ' . $exception->getMessage());

    $stats = [
        'customers' => 0,
        'services' => 0,
        'orders' => 0,
        'quotations' => 0,
    ];

    $productionCount = 0;
    $pendingPayments = 0;
    $recentOrders = [];
    $recentQuotations = [];
}
?>

<div class="page-header">
    <h2>Dashboard</h2>
</div>

<div class="grid grid-4">
    <div class="stat-box">
        <h3>Customers</h3>
        <div class="value"><?php echo e($stats['customers']); ?></div>
    </div>
    <div class="stat-box">
        <h3>Orders</h3>
        <div class="value"><?php echo e($stats['orders']); ?></div>
    </div>
    <div class="stat-box">
        <h3>Quotations</h3>
        <div class="value"><?php echo e($stats['quotations']); ?></div>
    </div>
    <div class="stat-box">
        <h3>Production</h3>
        <div class="value"><?php echo e($productionCount); ?></div>
    </div>
</div>

<div class="grid" style="grid-template-columns: 1fr 1fr; margin-top: 24px;">
    <div class="card">
        <h3>Recent Orders</h3>
        <?php if ($recentOrders): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?php echo e($order['order_number']); ?></td>
                            <td><?php echo e($order['customer_name']); ?></td>
                            <td><span class="pill"><?php echo e($order['status']); ?></span></td>
                            <td><?php echo e(money((float) $order['total_amount'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="empty-state">No recent orders found.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recent Quotations</h3>
        <?php if ($recentQuotations): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Quote</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentQuotations as $quote): ?>
                        <tr>
                            <td><?php echo e($quote['quotation_number']); ?></td>
                            <td><?php echo e($quote['customer_name']); ?></td>
                            <td><span class="pill"><?php echo e($quote['status']); ?></span></td>
                            <td><?php echo e(money((float) $quote['total_amount'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="empty-state">No quotations recorded yet.</p>
        <?php endif; ?>
    </div>
</div>
