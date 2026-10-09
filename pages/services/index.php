<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $basePrice = (float) ($_POST['base_price'] ?? 0);
    $unit = trim((string) ($_POST['unit'] ?? 'piece'));
    if ($unit === '') {
        $unit = 'piece';
    }
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        flash('error', 'Service name is required.');
        redirect('index.php?page=services');
    }

    if ($basePrice < 0) {
        flash('error', 'Base price cannot be negative.');
        redirect('index.php?page=services');
    }

    $duplicateSql = $id ? 'SELECT id FROM services WHERE name = :name AND id != :id LIMIT 1' : 'SELECT id FROM services WHERE name = :name LIMIT 1';
    $duplicateStmt = $pdo->prepare($duplicateSql);
    $duplicateParams = [':name' => $name];
    if ($id) {
        $duplicateParams[':id'] = $id;
    }
    $duplicateStmt->execute($duplicateParams);
    if ($duplicateStmt->fetch()) {
        flash('error', 'Service name must be unique.');
        redirect('index.php?page=services');
    }

    if ($id) {
        $stmt = $pdo->prepare('UPDATE services SET name = :name, description = :description, base_price = :base_price, unit = :unit, is_active = :is_active WHERE id = :id');
        $stmt->execute([':name' => $name, ':description' => $description, ':base_price' => $basePrice, ':unit' => $unit, ':is_active' => $isActive, ':id' => $id]);
        flash('success', 'Service updated.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO services (name, description, base_price, unit, is_active) VALUES (:name, :description, :base_price, :unit, :is_active)');
        $stmt->execute([':name' => $name, ':description' => $description, ':base_price' => $basePrice, ':unit' => $unit, ':is_active' => $isActive]);
        flash('success', 'Service created.');
    }
    redirect('index.php?page=services');
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare('DELETE FROM services WHERE id = :id')->execute([':id' => $id]);
    flash('success', 'Service deleted.');
    redirect('index.php?page=services');
}

$editService = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editService = $stmt->fetch();
}

$search = trim((string) ($_GET['q'] ?? ''));
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE name LIKE :q OR description LIKE :q ORDER BY created_at DESC');
    $stmt->execute([':q' => '%' . $search . '%']);
    $services = $stmt->fetchAll();
} else {
    $services = $pdo->query('SELECT * FROM services ORDER BY created_at DESC')->fetchAll();
}
?>

<div class="page-header">
    <h2>Services</h2>
    <a class="btn btn-secondary" href="index.php?page=services">Refresh</a>
</div>

<div class="card" style="margin-bottom: 24px;">
    <h3><?php echo $editService ? 'Edit service' : 'Add service'; ?></h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <?php if ($editService): ?>
            <input type="hidden" name="id" value="<?php echo e($editService['id']); ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div>
                <label>Name *</label>
                <input type="text" name="name" value="<?php echo e($editService['name'] ?? ''); ?>" required>
            </div>
            <div>
                <label>Base Price</label>
                <input type="number" name="base_price" step="0.01" min="0" value="<?php echo e($editService['base_price'] ?? '0'); ?>">
            </div>
            <div>
                <label>Unit</label>
                <input type="text" name="unit" value="<?php echo e($editService['unit'] ?? 'piece'); ?>">
            </div>
            <div>
                <label>Active</label>
                <select name="is_active">
                    <option value="1" <?php echo ($editService['is_active'] ?? 1) == 1 ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo ($editService['is_active'] ?? 1) == 0 ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Description</label>
                <textarea name="description"><?php echo e($editService['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-actions business-form-actions">
            <button class="btn" type="submit"><?php echo $editService ? 'Update service' : 'Save service'; ?></button>
            <?php if ($editService): ?>
                <a class="btn btn-secondary" href="index.php?page=services">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="search-wrap business-search">
        <form method="get">
            <input type="hidden" name="page" value="services">
            <label>Search services</label>
            <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Search by name or description">
        </form>
    </div>

    <div class="business-table-wrap">
    <table class="table business-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Unit</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($services): ?>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?php echo e($service['name']); ?></td>
                        <td><?php echo e(money((float) $service['base_price'])); ?></td>
                        <td><?php echo e($service['unit']); ?></td>
                        <td>
                            <?php if ((int) $service['is_active'] === 1): ?>
                                <span class="pill pill-success">Active</span>
                            <?php else: ?>
                                <span class="pill pill-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions business-actions">
                            <a class="btn btn-secondary" href="index.php?page=services&edit=<?php echo e($service['id']); ?>">Edit</a>
                            <a class="btn btn-danger" href="index.php?page=services&delete=<?php echo e($service['id']); ?>" data-confirm="Delete this service?">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">
                        <p class="empty-state">No services match your search.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
