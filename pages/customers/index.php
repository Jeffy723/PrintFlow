<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));

    if ($name === '' || $phone === '') {
        flash('error', 'Name and phone are required.');
    } else {
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
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare('DELETE FROM customers WHERE id = :id')->execute([':id' => $id]);
    flash('success', 'Customer deleted.');
    redirect('index.php?page=customers');
}

$editCustomer = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editCustomer = $stmt->fetch();
}

$search = trim((string) ($_GET['q'] ?? ''));
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE name LIKE :q OR phone LIKE :q OR email LIKE :q ORDER BY created_at DESC');
    $stmt->execute([':q' => '%' . $search . '%']);
    $customers = $stmt->fetchAll();
} else {
    $customers = $pdo->query('SELECT * FROM customers ORDER BY created_at DESC')->fetchAll();
}
?>

<div class="page-header">
    <h2>Customers</h2>
    <a class="btn" href="index.php?page=customers">Refresh</a>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3><?php echo $editCustomer ? 'Edit customer' : 'Add customer'; ?></h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <?php if ($editCustomer): ?>
            <input type="hidden" name="id" value="<?php echo e($editCustomer['id']); ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>Name *</label>
                <input type="text" name="name" value="<?php echo e($editCustomer['name'] ?? ''); ?>" required>
            </div>
            <div>
                <label>Phone *</label>
                <input type="text" name="phone" value="<?php echo e($editCustomer['phone'] ?? ''); ?>" required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?php echo e($editCustomer['email'] ?? ''); ?>">
            </div>
            <div>
                <label>Address</label>
                <textarea name="address"><?php echo e($editCustomer['address'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?php echo $editCustomer ? 'Update customer' : 'Save customer'; ?></button>
            <?php if ($editCustomer): ?>
                <a class="btn btn-secondary" href="index.php?page=customers">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="search-wrap">
        <form method="get">
            <input type="hidden" name="page" value="customers">
            <label>Search customers</label>
            <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Search by name, phone or email">
        </form>
    </div>

    <table class="table">
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
                        <td class="actions">
                            <a class="btn btn-secondary" href="index.php?page=customers&edit=<?php echo e($customer['id']); ?>">Edit</a>
                            <a class="btn btn-danger" href="index.php?page=customers&delete=<?php echo e($customer['id']); ?>" data-confirm="Delete this customer?">Delete</a>
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
