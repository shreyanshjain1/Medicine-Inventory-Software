<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function require_login(): void {
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function current_user_name(): string {
    $user = current_user();
    return $user['name'] ?? 'User';
}

function current_user_role(): string {
    $user = current_user();
    return ucfirst($user['role'] ?? 'User');
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function month_year(?string $date): string {
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('m/Y', $ts) : '';
}

function parse_month_year(?string $value): ?DateTime {
    if (!$value) {
        return null;
    }
    $dt = DateTime::createFromFormat('m/Y', $value);
    return $dt ?: null;
}

function expiry_badge_class(?string $expDate): string {
    $date = parse_month_year($expDate);
    if (!$date) {
        return '';
    }
    $today = new DateTime('first day of this month');
    $oneMonth = (clone $today)->modify('+1 month');
    $sixMonths = (clone $today)->modify('+6 months');

    if ($date <= $oneMonth) {
        return 'expiry-critical';
    }
    if ($date <= $sixMonths) {
        return 'expiry-warning';
    }
    return 'expiry-safe';
}

function expiry_state(?string $expDate): string {
    return match (expiry_badge_class($expDate)) {
        'expiry-critical' => 'Critical',
        'expiry-warning' => 'Expiring Soon',
        default => 'Healthy',
    };
}

function inventory_table_columns(bool $outsourced = false): array {
    $columns = [
        'generic_name' => 'Generic Name',
        'brand_name' => 'Brand Name',
        'dosage_strength' => 'Dosage & Strength',
        'batch_no' => 'Batch No',
        'mfg_date' => 'Mfg Date',
        'exp_date' => 'Exp Date',
        'manufacturer' => 'Manufacturer',
        'registration_no' => 'Reg. No',
    ];

    if ($outsourced) {
        $columns['distributor_name'] = 'Distributor';
    }

    $columns['qty'] = 'Available Qty';
    $columns['qty_in'] = 'Qty In';
    $columns['qty_out'] = 'Qty Out';
    $columns['qty_returned'] = 'Qty Returned';

    return $columns;
}

function fetch_inventory_grouped(mysqli $conn, bool $outsourced = false): array {
    $table = $outsourced ? 'inventory_outsourced' : 'inventory';
    $rows = [];
    $sql = $outsourced
        ? "SELECT *, distributor_name FROM {$table} ORDER BY generic_name, brand_name, dosage_strength, batch_no"
        : "SELECT * FROM {$table} ORDER BY generic_name, brand_name, dosage_strength, batch_no";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key = $row['generic_name'] . '|' . $row['brand_name'] . '|' . $row['dosage_strength'];
            $rows[$key][] = $row;
        }
    }
    return $rows;
}

function fetch_inventory_flat(mysqli $conn, bool $outsourced = false): array {
    $table = $outsourced ? 'inventory_outsourced' : 'inventory';
    $sql = $outsourced
        ? "SELECT *, distributor_name FROM {$table} ORDER BY generic_name, brand_name, dosage_strength, batch_no"
        : "SELECT * FROM {$table} ORDER BY generic_name, brand_name, dosage_strength, batch_no";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function flatten_inventory_with_source(mysqli $conn): array {
    $rows = [];
    foreach (fetch_inventory_flat($conn, false) as $row) {
        $row['source_type'] = 'Regular';
        $rows[] = $row;
    }
    foreach (fetch_inventory_flat($conn, true) as $row) {
        $row['source_type'] = 'Outsourced';
        $rows[] = $row;
    }
    return $rows;
}

function fetch_dashboard_stats(mysqli $conn): array {
    $stats = [
        'regular_items' => 0,
        'regular_qty' => 0,
        'outsourced_items' => 0,
        'outsourced_qty' => 0,
        'low_stock' => 0,
        'expiring_soon' => 0,
        'movements_today' => 0,
        'out_of_stock' => 0,
    ];

    $queries = [
        'regular' => "SELECT COUNT(*) AS total_rows, COALESCE(SUM(qty),0) AS total_qty, COALESCE(SUM(CASE WHEN qty <= 10 THEN 1 ELSE 0 END),0) AS low_stock, COALESCE(SUM(CASE WHEN qty <= 0 THEN 1 ELSE 0 END),0) AS out_of_stock FROM inventory",
        'outsourced' => "SELECT COUNT(*) AS total_rows, COALESCE(SUM(qty),0) AS total_qty, COALESCE(SUM(CASE WHEN qty <= 10 THEN 1 ELSE 0 END),0) AS low_stock, COALESCE(SUM(CASE WHEN qty <= 0 THEN 1 ELSE 0 END),0) AS out_of_stock FROM inventory_outsourced",
        'movements' => "SELECT (
            (SELECT COUNT(*) FROM out_records WHERE DATE(created_at) = CURDATE()) +
            (SELECT COUNT(*) FROM return_binded_records WHERE DATE(created_at) = CURDATE()) +
            (SELECT COUNT(*) FROM in_log WHERE DATE(added_at) = CURDATE())
        ) AS total_movements"
    ];

    if ($res = $conn->query($queries['regular'])) {
        $row = $res->fetch_assoc();
        $stats['regular_items'] = (int)$row['total_rows'];
        $stats['regular_qty'] = (int)$row['total_qty'];
        $stats['low_stock'] += (int)$row['low_stock'];
        $stats['out_of_stock'] += (int)$row['out_of_stock'];
    }

    if ($res = $conn->query($queries['outsourced'])) {
        $row = $res->fetch_assoc();
        $stats['outsourced_items'] = (int)$row['total_rows'];
        $stats['outsourced_qty'] = (int)$row['total_qty'];
        $stats['low_stock'] += (int)$row['low_stock'];
        $stats['out_of_stock'] += (int)$row['out_of_stock'];
    }

    if ($res = $conn->query($queries['movements'])) {
        $row = $res->fetch_assoc();
        $stats['movements_today'] = (int)$row['total_movements'];
    }

    foreach (array_merge(fetch_inventory_flat($conn, false), fetch_inventory_flat($conn, true)) as $item) {
        $date = parse_month_year($item['exp_date'] ?? '');
        if (!$date) {
            continue;
        }
        $today = new DateTime('first day of this month');
        $soon = (clone $today)->modify('+6 months');
        if ($date <= $soon) {
            $stats['expiring_soon']++;
        }
    }

    return $stats;
}

function fetch_recent_activity(mysqli $conn, int $limit = 10): array {
    $items = [];

    $queries = [
        "SELECT 'OUT' AS movement_type, CONCAT(customer_name, ' / ', document_type, '-', document_number) AS reference, qty_out AS qty, added_by AS actor, created_at AS activity_at FROM out_records ORDER BY created_at DESC LIMIT {$limit}",
        "SELECT 'RETURN' AS movement_type, CONCAT('OUT #', out_record_id) AS reference, qty_returned AS qty, returned_by AS actor, created_at AS activity_at FROM return_binded_records ORDER BY created_at DESC LIMIT {$limit}",
        "SELECT 'IN' AS movement_type, batch_no AS reference, 0 AS qty, added_by AS actor, added_at AS activity_at FROM in_log ORDER BY added_at DESC LIMIT {$limit}"
    ];

    foreach ($queries as $query) {
        if ($res = $conn->query($query)) {
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
        }
    }

    usort($items, static function ($a, $b) {
        return strtotime($b['activity_at']) <=> strtotime($a['activity_at']);
    });

    return array_slice($items, 0, $limit);
}

function fetch_activity_log(mysqli $conn, array $filters = []): array {
    $items = [];
    $search = trim((string)($filters['search'] ?? ''));
    $type = strtoupper(trim((string)($filters['type'] ?? 'ALL')));
    $dateFrom = trim((string)($filters['date_from'] ?? ''));
    $dateTo = trim((string)($filters['date_to'] ?? ''));

    $queries = [
        "SELECT 'OUT' AS movement_type, CONCAT(customer_name, ' / ', document_type, '-', document_number) AS reference, qty_out AS qty, added_by AS actor, created_at AS activity_at, batch_no, generic_name, brand_name, dosage_strength, 'Regular' AS source_type FROM out_records o JOIN inventory i ON o.inventory_id = i.id",
        "SELECT 'RETURN' AS movement_type, CONCAT(customer_name, ' / ', document_type, '-', document_number) AS reference, qty_returned AS qty, returned_by AS actor, r.created_at AS activity_at, batch_no, generic_name, brand_name, dosage_strength, 'Regular' AS source_type FROM return_binded_records r JOIN out_records o ON r.out_record_id = o.id JOIN inventory i ON o.inventory_id = i.id",
        "SELECT 'IN' AS movement_type, batch_no AS reference, 0 AS qty, added_by AS actor, added_at AS activity_at, batch_no, generic_name, brand_name, dosage_strength, source_type FROM (
            SELECT l.added_by, l.added_at, i.batch_no, i.generic_name, i.brand_name, i.dosage_strength, 'Regular' AS source_type
            FROM in_log l JOIN inventory i ON l.batch_no = i.batch_no
            UNION ALL
            SELECT l.added_by, l.added_at, o.batch_no, o.generic_name, o.brand_name, o.dosage_strength, 'Outsourced' AS source_type
            FROM in_log l JOIN inventory_outsourced o ON l.batch_no = o.batch_no
        ) AS in_union"
    ];

    foreach ($queries as $query) {
        if ($res = $conn->query($query)) {
            while ($row = $res->fetch_assoc()) {
                if ($type !== 'ALL' && strtoupper($row['movement_type']) !== $type) {
                    continue;
                }
                if ($dateFrom !== '' && strtotime($row['activity_at']) < strtotime($dateFrom . ' 00:00:00')) {
                    continue;
                }
                if ($dateTo !== '' && strtotime($row['activity_at']) > strtotime($dateTo . ' 23:59:59')) {
                    continue;
                }
                if ($search !== '') {
                    $haystack = strtolower(implode(' ', [
                        $row['movement_type'] ?? '',
                        $row['reference'] ?? '',
                        $row['actor'] ?? '',
                        $row['batch_no'] ?? '',
                        $row['generic_name'] ?? '',
                        $row['brand_name'] ?? '',
                        $row['dosage_strength'] ?? '',
                        $row['source_type'] ?? ''
                    ]));
                    if (!str_contains($haystack, strtolower($search))) {
                        continue;
                    }
                }
                $items[] = $row;
            }
        }
    }

    usort($items, static function ($a, $b) {
        return strtotime($b['activity_at']) <=> strtotime($a['activity_at']);
    });

    return $items;
}

function fetch_alert_rows(mysqli $conn): array {
    $rows = [];
    foreach (flatten_inventory_with_source($conn) as $row) {
        $issues = [];
        if ((int)($row['qty'] ?? 0) <= 0) {
            $issues[] = 'Out of stock';
        } elseif ((int)($row['qty'] ?? 0) <= 10) {
            $issues[] = 'Low stock';
        }
        $expiryState = expiry_state($row['exp_date'] ?? '');
        if ($expiryState === 'Critical') {
            $issues[] = 'Critical expiry';
        } elseif ($expiryState === 'Expiring Soon') {
            $issues[] = 'Expiring soon';
        }
        if ($issues) {
            $row['issues'] = $issues;
            $rows[] = $row;
        }
    }

    usort($rows, static function ($a, $b) {
        $aPriority = ((int)($a['qty'] ?? 0) <= 0 ? 100 : 0) + (expiry_badge_class($a['exp_date'] ?? '') === 'expiry-critical' ? 80 : 0) + (expiry_badge_class($a['exp_date'] ?? '') === 'expiry-warning' ? 40 : 0);
        $bPriority = ((int)($b['qty'] ?? 0) <= 0 ? 100 : 0) + (expiry_badge_class($b['exp_date'] ?? '') === 'expiry-critical' ? 80 : 0) + (expiry_badge_class($b['exp_date'] ?? '') === 'expiry-warning' ? 40 : 0);
        return $bPriority <=> $aPriority;
    });

    return $rows;
}

function fetch_alert_summary(mysqli $conn): array {
    $rows = fetch_alert_rows($conn);
    $summary = [
        'critical_expiry' => 0,
        'expiring_soon' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0,
        'total' => count($rows),
    ];
    foreach ($rows as $row) {
        if ((int)($row['qty'] ?? 0) <= 0) {
            $summary['out_of_stock']++;
        } elseif ((int)($row['qty'] ?? 0) <= 10) {
            $summary['low_stock']++;
        }
        $class = expiry_badge_class($row['exp_date'] ?? '');
        if ($class === 'expiry-critical') {
            $summary['critical_expiry']++;
        } elseif ($class === 'expiry-warning') {
            $summary['expiring_soon']++;
        }
    }
    return $summary;
}

function inventory_health_label(array $stats): array {
    $label = 'Healthy';
    $class = 'health-good';
    if (($stats['low_stock'] ?? 0) > 100 || ($stats['expiring_soon'] ?? 0) > 50 || ($stats['out_of_stock'] ?? 0) > 0) {
        $label = 'Needs Attention';
        $class = 'health-warning';
    }
    if (($stats['low_stock'] ?? 0) > 200 || ($stats['expiring_soon'] ?? 0) > 100 || ($stats['out_of_stock'] ?? 0) > 20) {
        $label = 'High Risk';
        $class = 'health-critical';
    }
    return [$label, $class];
}
