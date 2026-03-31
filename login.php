<?php
require_once 'includes/common.php';

if (isset($_SESSION['user'])) {
    redirect('dashboard.php');
}

$error = '';

if (request_is_post()) {
    verify_csrf_or_fail();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $user = db_fetch_one($conn, 'SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1', 's', [$email]);
        if ($user && password_verify($password, (string) ($user['password'] ?? ''))) {
            $user['role'] = normalize_role($user['role'] ?? '');
            $_SESSION['user'] = $user;
            set_flash('success', 'Welcome back, ' . ($user['name'] ?? 'User') . '.');
            seed_alert_notifications($conn);
            redirect('dashboard.php');
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PITC Inventory Flagship</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card card">
        <div class="auth-promo">
            <div>
                <img src="images/logo.png" alt="PITC logo" class="auth-logo">
                <div class="eyebrow">Medicine Inventory Platform</div>
                <h1>PITC Inventory Flagship</h1>
                <p>Role-aware inventory, safer corrections, reversible transactions, product master control, alerts, analytics, charts, notes, and barcode-ready operations in one PHP/MySQL workspace.</p>
            </div>
            <div class="auth-feature-list">
                <div class="auth-feature">Admin, manager, staff, and viewer permissions</div>
                <div class="auth-feature">Correction logs, void flows, and append-only audit trail</div>
                <div class="auth-feature">Product master, notifications, reports, exports, and labels</div>
            </div>
        </div>
        <div class="auth-form-wrap">
            <div>
                <h2 class="page-title">Sign in</h2>
                <p class="page-subtitle">Use your active account to continue into the inventory workspace.</p>
                <?php if ($error !== ''): ?>
                    <div class="flash flash-error"><?php echo h($error); ?></div>
                <?php endif; ?>
                <?php if ($flash = get_flash()): ?>
                    <div class="flash <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
                        <?php echo h($flash['message']); ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="card auth-form-card">
                    <?php echo csrf_field(); ?>
                    <div class="field">
                        <label for="email">Email Address</label>
                        <input id="email" type="email" name="email" placeholder="name@company.com" required>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Login</button>
                    <p class="muted auth-footnote">
                        <?php
                        $userCount = db_fetch_one($conn, 'SELECT COUNT(*) AS total FROM users');
                        if ((int) ($userCount['total'] ?? 0) === 0): ?>
                            No users exist yet. <a href="signup.php">Create the first admin account</a>.
                        <?php else: ?>
                            Need a new account? Ask an admin to create one or open the bootstrap signup only if this is a fresh install.
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
