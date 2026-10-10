<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();
$validPaymentMethods = ['Cash', 'UPI', 'Card', 'Bank Transfer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect('index.php?page=payments');
    }

    $orderId = filter_var(trim((string) ($_POST['order_id'] ?? '')), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $amountInput = (string) ($_POST['amount'] ?? '');
    $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'Cash'));
    $paymentDate = (string) ($_POST['payment_date'] ?? date('Y-m-d'));
    $referenceNumber = trim((string) ($_POST['reference_number'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($orderId === false) {
        flash('error', 'Select a valid order to record a payment.');
        redirect('index.php?page=payments');
    }

    if (preg_match('/\A\d+(?:\.\d{1,2})?\z/', $amountInput) !== 1 || strlen(explode('.', $amountInput)[0]) > 8) {
        flash('error', 'Enter a valid payment amount with up to two decimal places.');
        redirect('index.php?page=payments');
    }
    [$wholeAmount, $fractionAmount] = array_pad(explode('.', $amountInput, 2), 2, '0');
    $amountCents = ((int) $wholeAmount * 100) + (int) str_pad($fractionAmount, 2, '0');
    if ($amountCents <= 0) {
        flash('error', 'Payment amount must be greater than zero.');
        redirect('index.php?page=payments');
    }
    $amount = $amountCents / 100;

    if (!in_array($paymentMethod, $validPaymentMethods, true)) {
        flash('error', 'Payment method is invalid.');
        redirect('index.php?page=payments');
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if ($parsedDate === false
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $parsedDate->format('Y-m-d') !== $paymentDate) {
        flash('error', 'Enter a valid payment date.');
        redirect('index.php?page=payments');
    }

    try {
        $pdo->beginTransaction();
        $order = $pdo->prepare('SELECT total_amount FROM orders WHERE id = :id FOR UPDATE');
        $order->execute([':id' => $orderId]);
        $orderData = $order->fetch();
        if (!$orderData) {
            throw new DomainException('The selected order was not found.');
        }

        $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE order_id = :order_id');
        $paidStmt->execute([':order_id' => $orderId]);
        $totalPaid = (float) $paidStmt->fetchColumn();
        $remainingBalance = (float) $orderData['total_amount'] - $totalPaid;
        if ($amount > $remainingBalance + 0.00001) {
            throw new DomainException('Payment total cannot exceed the order total.');
        }

        $stmt = $pdo->prepare('INSERT INTO payments (order_id, amount, payment_method, payment_date, reference_number, notes, created_by) VALUES (:order_id, :amount, :payment_method, :payment_date, :reference_number, :notes, :created_by)');
        $stmt->execute([
            ':order_id' => $orderId,
            ':amount' => $amount,
            ':payment_method' => $paymentMethod,
            ':payment_date' => $paymentDate,
            ':reference_number' => $referenceNumber,
            ':notes' => $notes,
            ':created_by' => currentUser()['id'],
        ]);
        $pdo->commit();
        flash('success', 'Payment recorded successfully.');
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Payment save error: ' . $exception->getMessage());
        flash('error', 'Payment could not be recorded. Please try again.');
    }
    redirect('index.php?page=payments');
}

$orders = $pdo->query('SELECT o.id, o.order_number, c.name AS customer_name, o.total_amount, COALESCE(SUM(p.amount), 0) AS total_paid, (o.total_amount - COALESCE(SUM(p.amount), 0)) AS remaining_balance FROM orders o JOIN customers c ON c.id = o.customer_id LEFT JOIN payments p ON p.order_id = o.id GROUP BY o.id, o.order_number, c.name, o.total_amount ORDER BY o.created_at DESC')->fetchAll();
$payments = $pdo->query('SELECT p.*, o.order_number, c.name AS customer_name FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id ORDER BY p.payment_date DESC')->fetchAll();
?>

<div class="page-header">
    <h2>Payments</h2>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3>Outstanding balances</h3>
    <div class="business-table-wrap">
    <table class="table business-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Order Total</th>
                <th>Paid</th>
                <th>Remaining</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($orders): ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo e($order['order_number']); ?></td>
                        <td><?php echo e($order['customer_name']); ?></td>
                        <td><?php echo e(money((float) $order['total_amount'])); ?></td>
                        <td><?php echo e(money((float) $order['total_paid'])); ?></td>
                        <td><?php echo e(money((float) $order['remaining_balance'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5"><p class="empty-state">No orders found.</p></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3>Record payment</h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
        <div class="form-grid">
            <div>
                <label>Order</label>
                <select name="order_id" required>
                    <option value="">Select order</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?php echo e($order['id']); ?>"><?php echo e($order['order_number']); ?> - <?php echo e($order['customer_name']); ?> (Balance: <?php echo e(money((float) $order['remaining_balance'])); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Amount</label>
                <input type="number" name="amount" step="0.01" min="0.01" required>
            </div>
            <div>
                <label>Payment Method</label>
                <select name="payment_method">
                    <?php foreach ($validPaymentMethods as $method): ?>
                        <option value="<?php echo e($method); ?>"><?php echo e($method); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Payment Date</label>
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <label>Reference Number</label>
                <input type="text" name="reference_number">
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Notes</label>
                <textarea name="notes"></textarea>
            </div>
        </div>

        <div class="form-actions business-form-actions">
            <button class="btn" type="submit">Record payment</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Payment history</h3>
    <div class="business-table-wrap">
    <table class="table business-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Method</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($payments): ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo e($payment['order_number']); ?></td>
                        <td><?php echo e($payment['customer_name']); ?></td>
                        <td><?php echo e($payment['payment_method']); ?></td>
                        <td><?php echo e(money((float) $payment['amount'])); ?></td>
                        <td><?php echo e($payment['payment_date']); ?></td>
                        <td><?php echo e($payment['reference_number'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6"><p class="empty-state">No payments recorded yet.</p></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
