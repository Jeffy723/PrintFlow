<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your request could not be verified. Please try again.');
        redirect('index.php?page=login');
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        flash('error', 'Email and password are required.');
    } else {
        try {
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                loginUser($user);
                redirect('index.php?page=dashboard');
            }

            flash('error', 'Invalid email or password.');
        } catch (Throwable $exception) {
            error_log('Login authentication error: ' . $exception->getMessage());
            flash('error', 'Unable to sign in. Please try again.');
        }
    }
}

$flash = getFlash();
?>

<div class="login-box">
    <h1>Login to PrintFlow</h1>
    <p class="subtitle">Printing job and order management system</p>

    <?php if ($flash): ?>
        <div class="flash flash-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
        <div class="form-grid">
            <div>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="" required>
            </div>
            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Login</button>
        </div>
    </form>

    <p class="muted" style="margin-top: 16px;">Demo admin: admin@printflow.local / admin123</p>
</div>
