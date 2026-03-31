<?php
require_once 'includes/common.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            set_flash('success', 'Welcome back, ' . ($user['name'] ?? 'User') . '.');
            header('Location: dashboard.php');
            exit;
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
    <title>Login - PITC Inventory v2</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell" style="min-height:100vh;display:grid;place-items:center;">
    <div class="card" style="max-width:1080px;width:100%;display:grid;grid-template-columns:1.1fr .9fr;overflow:hidden;padding:0;">
        <div style="padding:40px;background:linear-gradient(135deg,#0b57d0,#5e97f6);color:#fff;display:flex;flex-direction:column;justify-content:space-between;gap:24px;">
            <div>
                <img src="images/logo.png" alt="Logo" style="width:84px;height:84px;border-radius:18px;background:#fff;padding:8px;">
                <h1 style="margin:18px 0 10px;font-size:36px;line-height:1.15;">PITC Inventory v2</h1>
                <p style="margin:0;font-size:16px;max-width:520px;opacity:.92;">A cleaner and more production-minded medicine inventory workspace with stronger flows, better exports, and a more polished dashboard.</p>
            </div>
            <div style="display:grid;gap:12px;">
                <div class="badge" style="background:rgba(255,255,255,.14);color:#fff;padding:12px 14px;">Inventory overview, movements, and expiry visibility</div>
                <div class="badge" style="background:rgba(255,255,255,.14);color:#fff;padding:12px 14px;">Excel export and print-ready PDF views</div>
                <div class="badge" style="background:rgba(255,255,255,.14);color:#fff;padding:12px 14px;">Improved forms for IN, OUT, and RETURN</div>
            </div>
        </div>
        <div style="padding:40px;display:flex;align-items:center;">
            <div style="width:100%;">
                <h2 class="page-title">Sign in</h2>
                <p class="page-subtitle" style="margin-bottom:20px;">Use your existing account to continue.</p>
                <?php if ($error): ?>
                    <div class="flash flash-error"><?php echo h($error); ?></div>
                <?php endif; ?>
                <form method="post" class="card" style="box-shadow:none;border:1px solid var(--line);padding:20px;">
                    <div class="field">
                        <label for="email">Email Address</label>
                        <input id="email" type="email" name="email" placeholder="name@company.com" required>
                    </div>
                    <div class="field" style="margin-top:14px;">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button class="btn btn-primary" type="submit" style="width:100%;margin-top:18px;">Login</button>
                    <p class="muted" style="margin:16px 0 0;">No account yet? <a href="signup.php">Create one here</a>.</p>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
