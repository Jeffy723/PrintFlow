<?php

declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/functions.php';

$page = $_GET['page'] ?? 'dashboard';

if ($page === 'logout') {
    if (!isPost()) {
        redirect(isLoggedIn() ? 'index.php?page=dashboard' : 'index.php?page=login');
    }

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect(isLoggedIn() ? 'index.php?page=dashboard' : 'index.php?page=login');
    }

    logoutUser();
    session_start();
    session_regenerate_id(true);
    flash('success', 'You have been logged out.');
    redirect('index.php?page=login');
}

if (!isLoggedIn() && $page !== 'login') {
    redirect('index.php?page=login');
}

if (isLoggedIn() && $page === 'login') {
    redirect('index.php?page=dashboard');
}

include __DIR__ . '/includes/header.php';

switch ($page) {
    case 'login':
        include __DIR__ . '/pages/auth/login.php';
        break;
    case 'dashboard':
        include __DIR__ . '/pages/dashboard.php';
        break;
    case 'customers':
        include __DIR__ . '/pages/customers/index.php';
        break;
    case 'services':
        include __DIR__ . '/pages/services/index.php';
        break;
    case 'quotations':
        include __DIR__ . '/pages/quotations/index.php';
        break;
    case 'orders':
        include __DIR__ . '/pages/orders/index.php';
        break;
    case 'payments':
        include __DIR__ . '/pages/payments/index.php';
        break;
    case 'deliveries':
        include __DIR__ . '/pages/deliveries/index.php';
        break;
    default:
        http_response_code(404);
        echo '<div class="card"><h2>Page not found</h2><p>The requested page is unavailable.</p></div>';
        break;
}

include __DIR__ . '/includes/footer.php';
