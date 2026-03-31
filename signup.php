<?php
require_once 'includes/common.php';

$existingUsers = db_fetch_one($conn, 'SELECT COUNT(*) AS total FROM users');
$isBootstrap = (int) ($existingUsers['total'] ?? 0) === 0;

if (!$isBootstrap) {
    require_login();
    require_permission('signup.manage');
}

$error = '';
$success = '';
$old = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'role' => trim((string) ($_POST['role'] ?? ($isBootstrap ? 'admin' : 'staff'))),
];

if (request_is_post()) {
    verify_csrf_or_fail();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = normalize_role($_POST['role'] ?? ($isBootstrap ? 'admin' : 'staff'));

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!$isBootstrap && !in_array($role, [ROLE_MANAGER, ROLE_STAFF, ROLE_VIEWER], true)) {
        $error = 'Only staff, manager, or viewer accounts can be created from this screen.';
    } else {
        $exists = db_fetch_one($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1', 's', [$email]);
        if ($exists) {
            $error = 'That email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('INSERT INTO users (name, email, password, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
            if ($stmt) {
                $stmt->bind_param('ssss', $name, $email, $hash, $role);
                $stmt->execute();
                $newUserId = (int) $stmt->insert_id;
                $stmt->close();

                log_audit($conn, 'create', 'user', $newUserId, 'Created user account', null, [], ['name' => $name, 'email' => $email, 'role' => $role]);
                $success = $isBootstrap
                    ? 'Admin account created successfully. You can now sign in.'
                    : 'User account created successfully.';
                if ($isBootstrap) {
                    set_flash('success', $success);
                    redirect('login.php');
                }
            } else {
                $error = 'Unable to create the account right now.';
            }
        }
    }
}

$availableRoles = $isBootstrap
    ? [ROLE_ADMIN => 'Admin']
    : [
        ROLE_MANAGER => 'Manager',
        ROLE_STAFF => 'Staff',
        ROLE_VIEWER => 'Viewer',
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - PITC Inventory Flagship</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="auth-shell auth-shell-compact">
    <div class="card auth-simple-card">
        <div class="eyebrow"><?php echo $isBootstrap ? 'Initial Setup' : 'User Provisioning'; ?></div>
        <h1 class="page-title"><?php echo $isBootstrap ? 'Create the first admin account' : 'Create a new user'; ?></h1>
        <p class="page-subtitle">
            <?php echo $isBootstrap
                ? 'Bootstrap the flagship inventory system with a secure admin account.'
                : 'Provision a new account with the appropriate operational role.'; ?>
        </p>

        <?php if ($error !== ''): ?>
            <div class="flash flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="flash flash-success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid" style="margin-top:18px;">
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="name">Full Name</label>
                <input id="name" type="text" name="name" value="<?php echo h($old['name']); ?>" required>
            </div>
            <div class="field">
                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" value="<?php echo h($old['email']); ?>" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <?php foreach ($availableRoles as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" <?php echo selected_attr($old['role'], $value); ?>>
                            <?php echo h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-full">
                <div class="form-submit-row">
                    <div class="form-submit-meta">
                        <?php echo $isBootstrap
                            ? 'The first account is the highest-trust user and should be stored securely.'
                            : 'Managers can create, correct, export, and reverse transactions. Staff can create transactions. Viewers stay read-only.'; ?>
                    </div>
                    <div class="inline-actions">
                        <a class="btn btn-outline" href="<?php echo $isBootstrap ? 'login.php' : 'dashboard.php'; ?>">
                            <?php echo $isBootstrap ? 'Back to Login' : 'Back to Dashboard'; ?>
                        </a>
                        <button class="btn btn-primary" type="submit">Create Account</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>
