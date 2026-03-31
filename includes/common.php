<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

const ROLE_ADMIN = 'admin';
const ROLE_MANAGER = 'manager';
const ROLE_STAFF = 'staff';
const ROLE_VIEWER = 'viewer';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function request_is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function normalize_role(?string $role): string
{
    $role = strtolower(trim((string) $role));
    return match ($role) {
        'admin', 'manager', 'staff', 'viewer' => $role,
        'employee' => ROLE_STAFF,
        default => ROLE_VIEWER,
    };
}

function role_labels(): array
{
    return [
        ROLE_ADMIN => 'Admin',
        ROLE_MANAGER => 'Manager',
        ROLE_STAFF => 'Staff',
        ROLE_VIEWER => 'Viewer',
    ];
}

function permission_matrix(): array
{
    return [
        ROLE_ADMIN => [
            'dashboard.view',
            'inventory.view',
            'inventory.in.create',
            'inventory.out.create',
            'inventory.return.create',
            'history.view',
            'alerts.view',
            'activity.view',
            'exports.view',
            'reports.view',
            'products.view',
            'products.manage',
            'corrections.view',
            'corrections.manage',
            'reversals.manage',
            'notes.view',
            'notes.add',
            'notes.manage',
            'notifications.view',
            'notifications.manage',
            'barcode.view',
            'signup.manage',
        ],
        ROLE_MANAGER => [
            'dashboard.view',
            'inventory.view',
            'inventory.in.create',
            'inventory.out.create',
            'inventory.return.create',
            'history.view',
            'alerts.view',
            'activity.view',
            'exports.view',
            'reports.view',
            'products.view',
            'products.manage',
            'corrections.view',
            'corrections.manage',
            'reversals.manage',
            'notes.view',
            'notes.add',
            'notes.manage',
            'notifications.view',
            'notifications.manage',
            'barcode.view',
        ],
        ROLE_STAFF => [
            'dashboard.view',
            'inventory.view',
            'inventory.in.create',
            'inventory.out.create',
            'inventory.return.create',
            'history.view',
            'alerts.view',
            'activity.view',
            'exports.view',
            'reports.view',
            'products.view',
            'notes.view',
            'notes.add',
            'notifications.view',
            'barcode.view',
        ],
        ROLE_VIEWER => [
            'dashboard.view',
            'inventory.view',
            'history.view',
            'alerts.view',
            'activity.view',
            'exports.view',
            'reports.view',
            'products.view',
            'notes.view',
            'notifications.view',
            'barcode.view',
        ],
    ];
}

function current_user(): array
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return [];
    }

    $user = $_SESSION['user'];
    $user['role'] = normalize_role($user['role'] ?? '');
    return $user;
}

function current_user_id(): int
{
    return (int) (current_user()['id'] ?? 0);
}

function current_user_name(): string
{
    return trim((string) (current_user()['name'] ?? 'User')) ?: 'User';
}

function current_user_role(): string
{
    return normalize_role(current_user()['role'] ?? '');
}

function current_user_role_label(): string
{
    $labels = role_labels();
    return $labels[current_user_role()] ?? 'Viewer';
}

function current_user_actor(): array
{
    return [
        'id' => current_user_id(),
        'name' => current_user_name(),
        'role' => current_user_role(),
    ];
}

function require_login(): void
{
    if (!isset($_SESSION['user'])) {
        set_flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function user_can(string $permission): bool
{
    $role = current_user_role();
    $matrix = permission_matrix();
    return in_array($permission, $matrix[$role] ?? [], true);
}

function require_permission(string $permission, string $redirectTo = 'dashboard.php'): void
{
    require_login();
    if (!user_can($permission)) {
        set_flash('error', 'You do not have permission to access that area.');
        redirect($redirectTo);
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_or_fail(): void
{
    $posted = (string) ($_POST['csrf_token'] ?? '');
    if ($posted === '' || !hash_equals(csrf_token(), $posted)) {
        set_flash('error', 'The form session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

function selected_attr($value, $expected): string
{
    return (string) $value === (string) $expected ? 'selected' : '';
}

function checked_attr($value, $expected): string
{
    return (string) $value === (string) $expected ? 'checked' : '';
}

function month_year(?string $date): string
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('m/Y', $timestamp) : '';
}

function parse_month_year(?string $value): ?DateTime
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('m/Y', $value);
    if (!$date) {
        return null;
    }

    $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 1);
    $date->setTime(0, 0, 0);
    return $date;
}

function date_input_to_month_year(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('m/Y', $timestamp) : '';
}

function month_year_to_date_input(?string $value): string
{
    $date = parse_month_year($value);
    return $date ? $date->format('Y-m-d') : '';
}

function format_datetime(?string $value, string $fallback = 'N/A'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y h:i A', $timestamp) : $fallback;
}

function format_date(?string $value, string $fallback = 'N/A'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y', $timestamp) : $fallback;
}

function expiry_badge_class(?string $expDate): string
{
    $date = parse_month_year($expDate);
    if (!$date) {
        return 'expiry-safe';
    }

    $today = new DateTime('first day of this month');
    $critical = (clone $today)->modify('+1 month');
    $soon = (clone $today)->modify('+6 months');

    if ($date < $today) {
        return 'expiry-critical';
    }

    if ($date <= $critical) {
        return 'expiry-critical';
    }

    if ($date <= $soon) {
        return 'expiry-warning';
    }

    return 'expiry-safe';
}

function expiry_state(?string $expDate): string
{
    $date = parse_month_year($expDate);
    if (!$date) {
        return 'Unknown';
    }

    $today = new DateTime('first day of this month');
    if ($date < $today) {
        return 'Expired';
    }

    return match (expiry_badge_class($expDate)) {
        'expiry-critical' => 'Critical',
        'expiry-warning' => 'Expiring Soon',
        default => 'Healthy',
    };
}

function current_script_name(): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? '');
}

require_once __DIR__ . '/domain.php';

function navigation_items(): array
{
    return [
        [
            'label' => 'Dashboard',
            'href' => 'dashboard.php',
            'permission' => 'dashboard.view',
        ],
        [
            'label' => 'Inventory IN',
            'href' => 'form_in.php',
            'permission' => 'inventory.in.create',
        ],
        [
            'label' => 'Inventory OUT',
            'href' => 'form_out.php',
            'permission' => 'inventory.out.create',
        ],
        [
            'label' => 'Inventory RETURN',
            'href' => 'form_return.php',
            'permission' => 'inventory.return.create',
        ],
        [
            'label' => 'Products',
            'href' => 'products.php',
            'permission' => 'products.view',
        ],
        [
            'label' => 'Reports',
            'href' => 'reports.php',
            'permission' => 'reports.view',
        ],
        [
            'label' => 'Alerts',
            'href' => 'alerts.php',
            'permission' => 'alerts.view',
        ],
        [
            'label' => 'Activity',
            'href' => 'activity_logs.php',
            'permission' => 'activity.view',
        ],
        [
            'label' => 'Corrections',
            'href' => 'correction_logs.php',
            'permission' => 'corrections.view',
        ],
        [
            'label' => 'Exports',
            'href' => 'export_center.php',
            'permission' => 'exports.view',
        ],
        [
            'label' => 'Notifications',
            'href' => 'notifications.php',
            'permission' => 'notifications.view',
        ],
    ];
}

function render_app_nav(mysqli $conn, string $pageTitle, string $pageSubtitle = '', string $eyebrow = 'Medicine Inventory Platform', array $actions = []): void
{
    $flash = get_flash();
    $current = current_script_name();
    $unreadCount = function_exists('unread_notification_count') ? unread_notification_count($conn) : 0;
    ?>
    <div class="page-stack">
        <section class="card page-hero">
            <div class="page-hero-top">
                <div>
                    <div class="eyebrow"><?php echo h($eyebrow); ?></div>
                    <h1 class="hero-page-title"><?php echo h($pageTitle); ?></h1>
                    <?php if ($pageSubtitle !== ''): ?>
                        <p class="hero-page-subtitle"><?php echo h($pageSubtitle); ?></p>
                    <?php endif; ?>
                </div>
                <div class="page-hero-actions">
                    <div class="hero-pill"><span class="hero-pill-label">User</span><strong><?php echo h(current_user_name()); ?></strong></div>
                    <div class="hero-pill"><span class="hero-pill-label">Role</span><strong><?php echo h(current_user_role_label()); ?></strong></div>
                    <?php if (user_can('notifications.view')): ?>
                        <a class="hero-pill hero-pill-link" href="notifications.php">
                            <span class="hero-pill-label">Notifications</span>
                            <strong><?php echo number_format($unreadCount); ?> unread</strong>
                        </a>
                    <?php endif; ?>
                    <form action="logout.php" method="post" class="logout-form-inline">
                        <?php echo csrf_field(); ?>
                        <button class="btn btn-danger" type="submit">Logout</button>
                    </form>
                </div>
            </div>
            <div class="nav-shell">
                <div class="nav-links">
                    <?php foreach (navigation_items() as $item): ?>
                        <?php if (!user_can($item['permission'])) {
                            continue;
                        } ?>
                        <a class="nav-link <?php echo $current === basename($item['href']) ? 'is-active' : ''; ?>" href="<?php echo h($item['href']); ?>">
                            <?php echo h($item['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($actions): ?>
                    <div class="nav-actions">
                        <?php foreach ($actions as $action): ?>
                            <?php echo $action; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($flash): ?>
                <div class="flash <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
                    <?php echo h($flash['message']); ?>
                </div>
            <?php endif; ?>
        </section>
    <?php
}

function close_page_stack(): void
{
    echo '</div>';
}
