<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$flash = getFlash();
$user = currentUser();
$currentPage = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrintFlow</title>
    <link rel="stylesheet" href="<?php echo e(assetUrl('public/css/style.css')); ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <script src="<?php echo e(assetUrl('public/js/script.js')); ?>" defer></script>
</head>
<body>
    <?php if ($flash): ?>
        <div class="flash flash-<?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($user): ?>
        <div class="app-shell">
            <aside class="sidebar">
                <div class="brand-block">
                    <h1>PrintFlow</h1>
                    <span><?php echo e($user['role']); ?></span>
                </div>
                <nav>
                    <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="index.php?page=dashboard">Dashboard</a>
                    <a class="nav-link <?php echo $currentPage === 'customers' ? 'active' : ''; ?>" href="index.php?page=customers">Customers</a>
                    <a class="nav-link <?php echo $currentPage === 'services' ? 'active' : ''; ?>" href="index.php?page=services">Services</a>
                    <a class="nav-link <?php echo $currentPage === 'quotations' ? 'active' : ''; ?>" href="index.php?page=quotations">Quotations</a>
                    <a class="nav-link <?php echo $currentPage === 'orders' ? 'active' : ''; ?>" href="index.php?page=orders">Orders</a>
                    <a class="nav-link <?php echo $currentPage === 'payments' ? 'active' : ''; ?>" href="index.php?page=payments">Payments</a>
                    <a class="nav-link <?php echo $currentPage === 'deliveries' ? 'active' : ''; ?>" href="index.php?page=deliveries">Deliveries</a>
                    <form method="post" action="index.php?page=logout">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                        <button class="nav-link nav-logout" type="submit">Logout</button>
                    </form>
                </nav>
            </aside>
            <main class="main-content">
                <header class="topbar">
                    <div>
                        <h2>Welcome, <?php echo e($user['name']); ?></h2>
                    </div>
                    <div class="topbar-actions">
                        <span class="badge badge-<?php echo e($user['role']); ?>"><?php echo e(strtoupper($user['role'])); ?></span>
                    </div>
                </header>
    <?php endif; ?>
