<?php

declare(strict_types=1);

requireLogin();
$pdo = getDatabaseConnection();
$formValues = [
    'name' => '',
    'description' => '',
    'base_price' => '0',
    'unit' => 'piece',
    'is_active' => '1',
];
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect('index.php?page=services');
    }

    $idInput = trim((string) ($_POST['id'] ?? ''));
    $id = $idInput === '' ? null : filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $basePriceInput = trim((string) ($_POST['base_price'] ?? '0'));
    $unit = trim((string) ($_POST['unit'] ?? 'piece'));
    if ($unit === '') {
        $unit = 'piece';
    }
    $isActiveInput = (string) ($_POST['is_active'] ?? '');
    $formValues = [
        'name' => $name,
        'description' => $description,
        'base_price' => $basePriceInput,
        'unit' => $unit,
        'is_active' => $isActiveInput,
    ];

    if ($id === false) {
        $formError = 'The selected service is invalid.';
    } elseif ($name === '') {
        $formError = 'Service name is required.';
    } elseif (preg_match('/\A\d+(?:\.\d{1,2})?\z/', $basePriceInput) !== 1 || (float) $basePriceInput > 99999999.99) {
        $formError = 'Base price must be a valid non-negative amount with up to two decimal places.';
    } elseif (!in_array($isActiveInput, ['0', '1'], true)) {
        $formError = 'Service status is invalid.';
    }

    if ($formError === null) {
        $basePrice = (float) $basePriceInput;
        $isActive = (int) $isActiveInput;

        try {
            $duplicateSql = $id ? 'SELECT id FROM services WHERE name = :name AND id != :id LIMIT 1' : 'SELECT id FROM services WHERE name = :name LIMIT 1';
            $duplicateStmt = $pdo->prepare($duplicateSql);
            $duplicateParams = [':name' => $name];
            if ($id) {
                $duplicateParams[':id'] = $id;
            }
            $duplicateStmt->execute($duplicateParams);
            if ($duplicateStmt->fetch()) {
                $formError = 'Service name must be unique.';
            } else {
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
        } catch (Throwable $exception) {
            error_log('Service save error: ' . $exception->getMessage());
            $formError = 'Service could not be saved. Please review the details and try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect('index.php?page=services');
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        flash('error', 'The selected service is invalid.');
        redirect('index.php?page=services');
    }

    try {
        $pdo->prepare('DELETE FROM services WHERE id = :id')->execute([':id' => $id]);
        flash('success', 'Service deleted.');
    } catch (Throwable $exception) {
        error_log('Service deletion error: ' . $exception->getMessage());
        flash('error', 'Service could not be deleted because it may be used by existing business records.');
    }
    redirect('index.php?page=services');
}

$editService = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && isset($id) && is_int($id)) {
    $editId = $id;
} else {
    $editId = null;
}
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editService = $stmt->fetch();
    if ($editService && $formError === null) {
        $formValues = $editService;
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE name LIKE :name_q OR description LIKE :description_q ORDER BY created_at DESC');
    $pattern = '%' . $search . '%';
    $stmt->execute([':name_q' => $pattern, ':description_q' => $pattern]);
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
        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
        <?php if ($editService): ?>
            <input type="hidden" name="id" value="<?php echo e($editService['id']); ?>">
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
                <label>Base Price</label>
                <input type="number" name="base_price" step="0.01" min="0" max="99999999.99" value="<?php echo e($formValues['base_price'] ?? '0'); ?>">
            </div>
            <div>
                <label>Unit</label>
                <input type="text" name="unit" value="<?php echo e($formValues['unit'] ?? 'piece'); ?>">
            </div>
            <div>
                <label>Active</label>
                <select name="is_active">
                    <option value="1" <?php echo (string) ($formValues['is_active'] ?? 1) === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo (string) ($formValues['is_active'] ?? 1) === '0' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Description</label>
                <textarea name="description"><?php echo e($formValues['description'] ?? ''); ?></textarea>
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
                            <form method="post" style="display: inline-block;" data-confirm="Delete this service?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                                <input type="hidden" name="id" value="<?php echo e($service['id']); ?>">
                                <button class="btn btn-danger" type="submit">Delete</button>
                            </form>
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
