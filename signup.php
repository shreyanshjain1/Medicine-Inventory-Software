<?php
require_once 'includes/common.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'employee');

    if ($name === '' || $email === '' || $password === '' || !in_array($role, ['employee', 'manager'], true)) {
        $error = 'Please complete all required fields.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = 'That email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $name, $email, $hash, $role);
            $stmt->execute();
            $stmt->close();
            $success = 'Account created successfully. You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - PITC Inventory v2</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell" style="min-height:100vh;display:grid;place-items:center;">
    <div class="card" style="max-width:720px;width:100%;">
        <h1 class="page-title">Create account</h1>
        <p class="page-subtitle">Use the existing users table and create a new account without changing your database structure.</p>
        <?php if ($error): ?><div class="flash flash-error"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="flash flash-success"><?php echo h($success); ?></div><?php endif; ?>
        <form method="post" class="form-grid" style="margin-top:18px;">
            <div class="field">
                <label for="name">Full Name</label>
                <input id="name" type="text" name="name" required>
            </div>
            <div class="field">
                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="employee">Employee</option>
                    <option value="manager">Manager</option>
                </select>
            </div>
            <div style="grid-column:1 / -1;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-top:10px;">
                <a href="login.php">Back to login</a>
                <button class="btn btn-primary" type="submit">Create Account</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
