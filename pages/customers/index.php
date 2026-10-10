<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();
$formValues = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
];
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect('index.php?page=customers');
    }

    $idInput = trim((string) ($_POST['id'] ?? ''));
    $id = $idInput === '' ? null : filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = (string) ($_POST['phone'] ?? '');
    $email = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $formValues = compact('name', 'phone', 'email', 'address');

    if ($id === false) {
        $formError = 'The selected customer is invalid.';
    } elseif ($name === '' || $phone === '') {
        $formError = 'Name and phone are required.';
    } elseif (preg_match('/\A[6-9][0-9]{9}\z/', $phone) !== 1) {
        $formError = 'Phone must be a 10-digit Indian mobile number starting with 6, 7, 8, or 9.';
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE customers SET name = :name, phone = :phone, email = :email, address = :address WHERE id = :id');
                $stmt->execute([':name' => $name, ':phone' => $phone, ':email' => $email, ':address' => $address, ':id' => $id]);
                flash('success', 'Customer updated successfully.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email, address) VALUES (:name, :phone, :email, :address)');
                $stmt->execute([':name' => $name, ':phone' => $phone, ':email' => $email, ':address' => $address]);
                flash('success', 'Customer created successfully.');
            }
            redirect('index.php?page=customers');
        } catch (Throwable $exception) {
            error_log('Customer save error: ' . $exception->getMessage());
            $formError = 'Customer could not be saved. Please review the details and try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect('index.php?page=customers');
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        flash('error', 'The selected customer is invalid.');
        redirect('index.php?page=customers');
    }

    try {
        $pdo->prepare('DELETE FROM customers WHERE id = :id')->execute([':id' => $id]);
        flash('success', 'Customer deleted.');
    } catch (Throwable $exception) {
        error_log('Customer deletion error: ' . $exception->getMessage());
        flash('error', 'Customer could not be deleted. The customer may be linked to existing business records.');
    }
    redirect('index.php?page=customers');
}

$editCustomer = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && isset($id) && is_int($id)) {
    $editId = $id;
} else {
    $editId = null;
}
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editCustomer = $stmt->fetch();
    if ($editCustomer && $formError === null) {
        $formValues = $editCustomer;
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE name LIKE :name_q OR phone LIKE :phone_q OR email LIKE :email_q ORDER BY created_at DESC');
    $pattern = '%' . $search . '%';
    $stmt->execute([':name_q' => $pattern, ':phone_q' => $pattern, ':email_q' => $pattern]);
    $customers = $stmt->fetchAll();
} else {
    $customers = $pdo->query('SELECT * FROM customers ORDER BY created_at DESC')->fetchAll();
}
?>

<div class="page-header">
    <h2>Customers</h2>
    <a class="btn btn-secondary" href="index.php?page=customers">Refresh</a>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3><?php echo $editCustomer ? 'Edit customer' : 'Add customer'; ?></h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
        <?php if ($editCustomer): ?>
            <input type="hidden" name="id" value="<?php echo e($editCustomer['id']); ?>">
        <?php endif; ?>

        <?php if ($formError !== null): ?>
            <div class="flash flash-error"><?php echo e($formError); ?></div>
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>Name *</label>
                <input type="text" name="name" value="<?php echo e($formValues['name'] ?? ''); ?>" required>
            </div>
            <div>
                <label>Phone *</label>
                <input type="tel" name="phone" value="<?php echo e($formValues['phone'] ?? ''); ?>" pattern="[6-9][0-9]{9}" minlength="10" maxlength="10" inputmode="numeric" title="Enter exactly 10 digits starting with 6, 7, 8, or 9." required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?php echo e($formValues['email'] ?? ''); ?>">
            </div>
            <div>
                <label>Address</label>
                <textarea name="address"><?php echo e($formValues['address'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-actions business-form-actions">
            <button class="btn" type="submit"><?php echo $editCustomer ? 'Update customer' : 'Save customer'; ?></button>
            <?php if ($editCustomer): ?>
                <a class="btn btn-secondary" href="index.php?page=customers">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="search-wrap business-search">
        <form method="get">
            <input type="hidden" name="page" value="customers">
            <label>Search customers</label>
            <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Search by name, phone or email">
        </form>
    </div>

    <div class="business-table-wrap">
    <table class="table business-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Address</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($customers): ?>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo e($customer['name']); ?></td>
                        <td><?php echo e($customer['phone']); ?></td>
                        <td><?php echo e($customer['email'] ?: '-'); ?></td>
                        <td><?php echo e($customer['address'] ?: '-'); ?></td>
                        <td class="actions business-actions">
                            <a class="btn btn-secondary" href="index.php?page=customers&edit=<?php echo e($customer['id']); ?>">Edit</a>
                            <form method="post" style="display: inline-block;" data-confirm="Delete this customer?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                                <input type="hidden" name="id" value="<?php echo e($customer['id']); ?>">
                                <button class="btn btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">
                        <p class="empty-state">No customers match your search.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
