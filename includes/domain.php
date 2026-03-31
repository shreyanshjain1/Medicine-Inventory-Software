<?php

function db_bind_and_execute(mysqli_stmt $stmt, string $types = '', array $params = []): void
{
    if ($types !== '' && $params) {
        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    $stmt->execute();
}

function db_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    db_bind_and_execute($stmt, $types, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_fetch_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function db_execute(mysqli $conn, string $sql, string $types = '', array $params = []): bool
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    db_bind_and_execute($stmt, $types, $params);
    $ok = $stmt->affected_rows >= 0;
    $stmt->close();
    return $ok;
}

function json_store(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function inventory_sources(): array
{
    return [
        'regular' => [
            'table' => 'inventory',
            'label' => 'Regular',
            'target_type' => 'batch_regular',
        ],
        'outsourced' => [
            'table' => 'inventory_outsourced',
            'label' => 'Outsourced',
            'target_type' => 'batch_outsourced',
        ],
    ];
}

function inventory_source_table(string $sourceKey): string
{
    return inventory_sources()[$sourceKey]['table'] ?? 'inventory';
}

function inventory_source_label(string $sourceKey): string
{
    return inventory_sources()[$sourceKey]['label'] ?? 'Regular';
}

function inventory_target_type(string $sourceKey): string
{
    return inventory_sources()[$sourceKey]['target_type'] ?? 'batch_regular';
}

function default_settings(): array
{
    return [
        'low_stock_default' => '10',
        'expiring_soon_months' => '6',
        'notifications_low_stock' => '1',
        'notifications_expiring_soon' => '1',
        'notifications_expired' => '1',
    ];
}

function fetch_settings(mysqli $conn): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = default_settings();
    $rows = db_fetch_all($conn, 'SELECT setting_key, setting_value FROM app_settings');
    foreach ($rows as $row) {
        $cache[$row['setting_key']] = $row['setting_value'];
    }

    return $cache;
}

function setting_value(mysqli $conn, string $key, $default = null)
{
    $settings = fetch_settings($conn);
    return $settings[$key] ?? $default;
}

function barcode_is_valid(string $value): bool
{
    if ($value === '') {
        return true;
    }

    return (bool) preg_match('/^[A-Z0-9\-\.\ \$\/\+\%]+$/i', $value);
}

function normalize_barcode_value(?string $value): string
{
    return strtoupper(trim((string) $value));
}

function product_signature(array $row): string
{
    return implode('|', [
        strtolower(trim((string) ($row['generic_name'] ?? ''))),
        strtolower(trim((string) ($row['brand_name'] ?? ''))),
        strtolower(trim((string) ($row['dosage_strength'] ?? ''))),
        strtolower(trim((string) ($row['manufacturer'] ?? ''))),
        strtolower(trim((string) ($row['registration_no'] ?? ''))),
    ]);
}

function product_display_name(array $row): string
{
    $parts = array_filter([
        trim((string) ($row['generic_name'] ?? '')),
        trim((string) ($row['brand_name'] ?? '')),
        trim((string) ($row['dosage_strength'] ?? '')),
    ]);

    return implode(' - ', $parts);
}

function low_stock_threshold_for_row(mysqli $conn, array $row): int
{
    $threshold = (int) ($row['low_stock_threshold'] ?? 0);
    if ($threshold > 0) {
        return $threshold;
    }

    $threshold = (int) ($row['default_low_stock_threshold'] ?? 0);
    if ($threshold > 0) {
        return $threshold;
    }

    return (int) setting_value($conn, 'low_stock_default', 10);
}

function expiry_bucket(mysqli $conn, ?string $expDate): string
{
    $date = parse_month_year($expDate);
    if (!$date) {
        return 'unknown';
    }

    $today = new DateTime('first day of this month');
    $soonMonths = (int) setting_value($conn, 'expiring_soon_months', 6);
    $critical = (clone $today)->modify('+1 month');
    $soon = (clone $today)->modify('+' . max(1, $soonMonths) . ' months');

    if ($date < $today) {
        return 'expired';
    }

    if ($date <= $critical) {
        return 'critical';
    }

    if ($date <= $soon) {
        return 'soon';
    }

    return 'healthy';
}

function inventory_row_matches_filters(mysqli $conn, array $row, array $filters = []): bool
{
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $source = trim((string) ($filters['source'] ?? 'all'));
    $stockFilter = trim((string) ($filters['stock_filter'] ?? 'all'));
    $expiryFilter = trim((string) ($filters['expiry_filter'] ?? 'all'));
    $manufacturer = trim((string) ($filters['manufacturer'] ?? ''));
    $distributor = trim((string) ($filters['distributor'] ?? ''));
    $qtyMin = trim((string) ($filters['qty_min'] ?? ''));
    $qtyMax = trim((string) ($filters['qty_max'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));

    if ($source !== '' && $source !== 'all' && $source !== ($row['source_key'] ?? '')) {
        return false;
    }

    $qty = (int) ($row['qty'] ?? 0);
    $threshold = low_stock_threshold_for_row($conn, $row);
    $bucket = expiry_bucket($conn, $row['exp_date'] ?? '');

    if ($stockFilter === 'low_stock' && !($qty > 0 && $qty <= $threshold)) {
        return false;
    }

    if ($stockFilter === 'out_of_stock' && $qty > 0) {
        return false;
    }

    if ($stockFilter === 'healthy' && $qty <= $threshold) {
        return false;
    }

    if ($expiryFilter === 'expired' && $bucket !== 'expired') {
        return false;
    }

    if ($expiryFilter === 'critical' && $bucket !== 'critical') {
        return false;
    }

    if ($expiryFilter === 'expiring_soon' && !in_array($bucket, ['critical', 'soon'], true)) {
        return false;
    }

    if ($expiryFilter === 'healthy' && $bucket !== 'healthy') {
        return false;
    }

    if ($manufacturer !== '' && strcasecmp(trim((string) ($row['manufacturer'] ?? '')), $manufacturer) !== 0) {
        return false;
    }

    if ($distributor !== '' && strcasecmp(trim((string) ($row['distributor_name'] ?? '')), $distributor) !== 0) {
        return false;
    }

    if ($qtyMin !== '' && $qty < (int) $qtyMin) {
        return false;
    }

    if ($qtyMax !== '' && $qty > (int) $qtyMax) {
        return false;
    }

    if ($dateFrom !== '' || $dateTo !== '') {
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        if ($createdAt === '') {
            return false;
        }

        $createdTs = strtotime($createdAt);
        if ($dateFrom !== '' && $createdTs < strtotime($dateFrom . ' 00:00:00')) {
            return false;
        }

        if ($dateTo !== '' && $createdTs > strtotime($dateTo . ' 23:59:59')) {
            return false;
        }
    }

    if ($search !== '') {
        $haystack = strtolower(implode(' ', [
            $row['generic_name'] ?? '',
            $row['brand_name'] ?? '',
            $row['dosage_strength'] ?? '',
            $row['batch_no'] ?? '',
            $row['manufacturer'] ?? '',
            $row['registration_no'] ?? '',
            $row['distributor_name'] ?? '',
            $row['importer_name'] ?? '',
            $row['barcode_value'] ?? '',
            $row['source_type'] ?? '',
        ]));

        if (!str_contains($haystack, $search)) {
            return false;
        }
    }

    return true;
}

function fetch_inventory_rows_by_source(mysqli $conn, string $sourceKey, array $filters = [], bool $includeInactive = false): array
{
    $table = inventory_source_table($sourceKey);
    $where = $includeInactive ? '' : "WHERE t.record_status = 'active'";
    $rows = db_fetch_all(
        $conn,
        "SELECT t.*, p.default_low_stock_threshold, p.product_status, p.barcode_value
         FROM {$table} t
         LEFT JOIN products p ON p.id = t.product_id
         {$where}
         ORDER BY t.generic_name, t.brand_name, t.dosage_strength, t.batch_no"
    );

    $result = [];
    foreach ($rows as $row) {
        $row['source_key'] = $sourceKey;
        $row['source_type'] = inventory_source_label($sourceKey);
        $row['source_table'] = $table;
        $row['target_type'] = inventory_target_type($sourceKey);
        $row['low_stock_threshold_resolved'] = low_stock_threshold_for_row($conn, $row);
        $row['expiry_bucket'] = expiry_bucket($conn, $row['exp_date'] ?? '');
        if (!inventory_row_matches_filters($conn, $row, $filters)) {
            continue;
        }
        $result[] = $row;
    }

    return $result;
}

function fetch_inventory_flat(mysqli $conn, bool $outsourced = false, array $filters = []): array
{
    return fetch_inventory_rows_by_source($conn, $outsourced ? 'outsourced' : 'regular', $filters);
}

function fetch_inventory_grouped(mysqli $conn, bool $outsourced = false, array $filters = []): array
{
    $rows = fetch_inventory_flat($conn, $outsourced, $filters);
    $grouped = [];

    foreach ($rows as $row) {
        $key = implode('|', [
            $row['generic_name'] ?? '',
            $row['brand_name'] ?? '',
            $row['dosage_strength'] ?? '',
        ]);
        $grouped[$key][] = $row;
    }

    return $grouped;
}

function flatten_inventory_with_source(mysqli $conn, array $filters = [], bool $includeInactive = false): array
{
    return array_merge(
        fetch_inventory_rows_by_source($conn, 'regular', $filters, $includeInactive),
        fetch_inventory_rows_by_source($conn, 'outsourced', $filters, $includeInactive)
    );
}

function fetch_dashboard_filter_options(mysqli $conn): array
{
    $rows = flatten_inventory_with_source($conn, [], false);
    $manufacturers = [];
    $distributors = [];

    foreach ($rows as $row) {
        $manufacturer = trim((string) ($row['manufacturer'] ?? ''));
        $distributor = trim((string) ($row['distributor_name'] ?? ''));
        if ($manufacturer !== '') {
            $manufacturers[$manufacturer] = true;
        }
        if ($distributor !== '') {
            $distributors[$distributor] = true;
        }
    }

    ksort($manufacturers);
    ksort($distributors);

    return [
        'manufacturers' => array_keys($manufacturers),
        'distributors' => array_keys($distributors),
    ];
}

function fetch_dashboard_stats(mysqli $conn, array $filters = []): array
{
    $regularRows = fetch_inventory_rows_by_source($conn, 'regular', $filters);
    $outsourcedRows = fetch_inventory_rows_by_source($conn, 'outsourced', $filters);
    $allRows = array_merge($regularRows, $outsourcedRows);

    $stats = [
        'regular_items' => count($regularRows),
        'regular_qty' => (int) array_sum(array_column($regularRows, 'qty')),
        'outsourced_items' => count($outsourcedRows),
        'outsourced_qty' => (int) array_sum(array_column($outsourcedRows, 'qty')),
        'low_stock' => 0,
        'expiring_soon' => 0,
        'expired' => 0,
        'movements_today' => 0,
        'out_of_stock' => 0,
    ];

    foreach ($allRows as $row) {
        $qty = (int) ($row['qty'] ?? 0);
        $threshold = low_stock_threshold_for_row($conn, $row);
        $bucket = expiry_bucket($conn, $row['exp_date'] ?? '');

        if ($qty <= 0) {
            $stats['out_of_stock']++;
        }

        if ($qty > 0 && $qty <= $threshold) {
            $stats['low_stock']++;
        }

        if (in_array($bucket, ['critical', 'soon'], true)) {
            $stats['expiring_soon']++;
        }

        if ($bucket === 'expired') {
            $stats['expired']++;
        }
    }

    $todayIn = db_fetch_one($conn, "SELECT COUNT(*) AS total FROM in_log WHERE DATE(added_at) = CURDATE() AND record_status = 'active'");
    $todayOut = db_fetch_one($conn, "SELECT COUNT(*) AS total FROM out_records WHERE DATE(created_at) = CURDATE() AND record_status = 'active'");
    $todayReturn = db_fetch_one($conn, "SELECT COUNT(*) AS total FROM return_binded_records WHERE DATE(created_at) = CURDATE() AND record_status = 'active'");
    $stats['movements_today'] = (int) (($todayIn['total'] ?? 0) + ($todayOut['total'] ?? 0) + ($todayReturn['total'] ?? 0));

    return $stats;
}

function inventory_health_label(array $stats): array
{
    $label = 'Healthy';
    $class = 'health-good';

    if (($stats['out_of_stock'] ?? 0) > 0 || ($stats['expired'] ?? 0) > 0 || ($stats['low_stock'] ?? 0) > 20) {
        $label = 'Needs Attention';
        $class = 'health-warning';
    }

    if (($stats['out_of_stock'] ?? 0) > 10 || ($stats['expired'] ?? 0) > 10 || ($stats['low_stock'] ?? 0) > 50) {
        $label = 'High Risk';
        $class = 'health-critical';
    }

    return [$label, $class];
}

function fetch_alert_rows(mysqli $conn, array $filters = []): array
{
    $rows = flatten_inventory_with_source($conn, $filters);
    $alerts = [];

    foreach ($rows as $row) {
        $issues = [];
        $qty = (int) ($row['qty'] ?? 0);
        $threshold = low_stock_threshold_for_row($conn, $row);
        $bucket = expiry_bucket($conn, $row['exp_date'] ?? '');

        if ($qty <= 0) {
            $issues[] = 'Out of stock';
        } elseif ($qty <= $threshold) {
            $issues[] = 'Low stock';
        }

        if ($bucket === 'expired') {
            $issues[] = 'Expired batch';
        } elseif ($bucket === 'critical') {
            $issues[] = 'Critical expiry';
        } elseif ($bucket === 'soon') {
            $issues[] = 'Expiring soon';
        }

        if (!$issues) {
            continue;
        }

        $row['issues'] = $issues;
        $alerts[] = $row;
    }

    usort($alerts, static function (array $left, array $right): int {
        return ((int) ($left['qty'] ?? 0)) <=> ((int) ($right['qty'] ?? 0));
    });

    return $alerts;
}

function fetch_alert_summary(mysqli $conn, array $filters = []): array
{
    $summary = [
        'critical_expiry' => 0,
        'expiring_soon' => 0,
        'expired' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0,
        'total' => 0,
    ];

    foreach (fetch_alert_rows($conn, $filters) as $row) {
        $summary['total']++;
        $qty = (int) ($row['qty'] ?? 0);
        if ($qty <= 0) {
            $summary['out_of_stock']++;
        } elseif (in_array('Low stock', $row['issues'], true)) {
            $summary['low_stock']++;
        }

        if (in_array('Expired batch', $row['issues'], true)) {
            $summary['expired']++;
        } elseif (in_array('Critical expiry', $row['issues'], true)) {
            $summary['critical_expiry']++;
        } elseif (in_array('Expiring soon', $row['issues'], true)) {
            $summary['expiring_soon']++;
        }
    }

    return $summary;
}

function fetch_activity_log(mysqli $conn, array $filters = []): array
{
    $type = strtoupper(trim((string) ($filters['type'] ?? 'ALL')));
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    $rows = [];

    if (in_array($type, ['ALL', 'IN'], true)) {
        $inRows = db_fetch_all(
            $conn,
            "SELECT id, generic_name, brand_name, dosage_strength, batch_no, qty_in, added_by, added_at, source_type, record_status
             FROM in_log
             ORDER BY added_at DESC"
        );
        foreach ($inRows as $row) {
            $rows[] = [
                'movement_type' => 'IN',
                'reference' => $row['batch_no'] ?? '',
                'qty' => (int) ($row['qty_in'] ?? 0),
                'actor' => $row['added_by'] ?? '',
                'activity_at' => $row['added_at'] ?? '',
                'batch_no' => $row['batch_no'] ?? '',
                'generic_name' => $row['generic_name'] ?? '',
                'brand_name' => $row['brand_name'] ?? '',
                'dosage_strength' => $row['dosage_strength'] ?? '',
                'source_type' => ucfirst((string) ($row['source_type'] ?? 'regular')),
                'record_status' => $row['record_status'] ?? 'active',
                'entity_type' => 'in_log',
                'entity_id' => (int) ($row['id'] ?? 0),
            ];
        }
    }

    if (in_array($type, ['ALL', 'OUT'], true)) {
        $outRows = db_fetch_all(
            $conn,
            "SELECT o.*, i.batch_no, i.generic_name, i.brand_name, i.dosage_strength
             FROM out_records o
             LEFT JOIN inventory i ON i.id = o.inventory_id
             ORDER BY o.created_at DESC"
        );
        foreach ($outRows as $row) {
            $rows[] = [
                'movement_type' => 'OUT',
                'reference' => trim(($row['customer_name'] ?? '') . ' / ' . ($row['document_type'] ?? '') . '-' . ($row['document_number'] ?? '')),
                'qty' => (int) ($row['qty_out'] ?? 0),
                'actor' => $row['added_by'] ?? '',
                'activity_at' => $row['created_at'] ?? '',
                'batch_no' => $row['batch_no'] ?? '',
                'generic_name' => $row['generic_name'] ?? '',
                'brand_name' => $row['brand_name'] ?? '',
                'dosage_strength' => $row['dosage_strength'] ?? '',
                'source_type' => 'Regular',
                'record_status' => $row['record_status'] ?? 'active',
                'entity_type' => 'out_record',
                'entity_id' => (int) ($row['id'] ?? 0),
            ];
        }
    }

    if (in_array($type, ['ALL', 'RETURN'], true)) {
        $returnRows = db_fetch_all(
            $conn,
            "SELECT r.*, o.customer_name, o.document_type, o.document_number, i.batch_no, i.generic_name, i.brand_name, i.dosage_strength
             FROM return_binded_records r
             LEFT JOIN out_records o ON o.id = r.out_record_id
             LEFT JOIN inventory i ON i.id = o.inventory_id
             ORDER BY r.created_at DESC"
        );
        foreach ($returnRows as $row) {
            $rows[] = [
                'movement_type' => 'RETURN',
                'reference' => trim(($row['customer_name'] ?? '') . ' / ' . ($row['document_type'] ?? '') . '-' . ($row['document_number'] ?? '')),
                'qty' => (int) ($row['qty_returned'] ?? 0),
                'actor' => $row['returned_by'] ?? '',
                'activity_at' => $row['created_at'] ?? '',
                'batch_no' => $row['batch_no'] ?? '',
                'generic_name' => $row['generic_name'] ?? '',
                'brand_name' => $row['brand_name'] ?? '',
                'dosage_strength' => $row['dosage_strength'] ?? '',
                'source_type' => 'Regular',
                'record_status' => $row['record_status'] ?? 'active',
                'entity_type' => 'return_record',
                'entity_id' => (int) ($row['id'] ?? 0),
            ];
        }
    }

    if (in_array($type, ['ALL', 'AUDIT'], true)) {
        $auditRows = db_fetch_all(
            $conn,
            "SELECT id, action_type, entity_type, entity_id, actor_name, summary, created_at
             FROM audit_logs
             ORDER BY created_at DESC"
        );
        foreach ($auditRows as $row) {
            $rows[] = [
                'movement_type' => 'AUDIT',
                'reference' => $row['summary'] ?? '',
                'qty' => 0,
                'actor' => $row['actor_name'] ?? '',
                'activity_at' => $row['created_at'] ?? '',
                'batch_no' => '',
                'generic_name' => strtoupper((string) ($row['action_type'] ?? '')),
                'brand_name' => ucfirst(str_replace('_', ' ', (string) ($row['entity_type'] ?? ''))),
                'dosage_strength' => '',
                'source_type' => 'System',
                'record_status' => 'logged',
                'entity_type' => $row['entity_type'] ?? '',
                'entity_id' => (int) ($row['entity_id'] ?? 0),
            ];
        }
    }

    $filtered = [];
    foreach ($rows as $row) {
        $activityTs = strtotime($row['activity_at'] ?? '');
        if ($dateFrom !== '' && $activityTs < strtotime($dateFrom . ' 00:00:00')) {
            continue;
        }
        if ($dateTo !== '' && $activityTs > strtotime($dateTo . ' 23:59:59')) {
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
                $row['source_type'] ?? '',
                $row['record_status'] ?? '',
            ]));
            if (!str_contains($haystack, $search)) {
                continue;
            }
        }
        $filtered[] = $row;
    }

    usort($filtered, static function (array $left, array $right): int {
        return strtotime($right['activity_at']) <=> strtotime($left['activity_at']);
    });

    return $filtered;
}

function fetch_recent_activity(mysqli $conn, int $limit = 10): array
{
    $activities = array_merge(
        fetch_activity_log($conn, ['type' => 'IN']),
        fetch_activity_log($conn, ['type' => 'OUT']),
        fetch_activity_log($conn, ['type' => 'RETURN']),
        fetch_activity_log($conn, ['type' => 'AUDIT'])
    );

    usort($activities, static function (array $left, array $right): int {
        return strtotime($right['activity_at']) <=> strtotime($left['activity_at']);
    });

    return array_slice($activities, 0, $limit);
}

function create_notification(mysqli $conn, array $payload): void
{
    $sql = "INSERT INTO notifications
            (notification_key, user_id, role_scope, notification_type, severity, title, message, entity_type, entity_id, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ON DUPLICATE KEY UPDATE
                role_scope = VALUES(role_scope),
                notification_type = VALUES(notification_type),
                severity = VALUES(severity),
                title = VALUES(title),
                message = VALUES(message),
                entity_type = VALUES(entity_type),
                entity_id = VALUES(entity_id)";

    $key = $payload['notification_key'] ?? null;
    $userId = (int) ($payload['user_id'] ?? 0);
    $roleScope = $payload['role_scope'] ?? 'all';
    $type = $payload['notification_type'] ?? 'system';
    $severity = $payload['severity'] ?? 'info';
    $title = $payload['title'] ?? '';
    $message = $payload['message'] ?? '';
    $entityType = $payload['entity_type'] ?? null;
    $entityId = (int) ($payload['entity_id'] ?? 0);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }

    $userIdOrNull = $userId > 0 ? $userId : null;
    $entityIdOrNull = $entityId > 0 ? $entityId : null;
    $stmt->bind_param(
        'sissssssi',
        $key,
        $userIdOrNull,
        $roleScope,
        $type,
        $severity,
        $title,
        $message,
        $entityType,
        $entityIdOrNull
    );
    $stmt->execute();
    $stmt->close();
}

function notify_roles(mysqli $conn, array $roles, string $type, string $severity, string $title, string $message, string $entityType = '', int $entityId = 0, ?string $notificationKey = null): void
{
    foreach ($roles as $role) {
        create_notification($conn, [
            'notification_key' => $notificationKey ? $notificationKey . ':' . $role : null,
            'role_scope' => $role,
            'notification_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }
}

function seed_alert_notifications(mysqli $conn): void
{
    $rows = fetch_alert_rows($conn);
    foreach ($rows as $row) {
        foreach ($row['issues'] as $issue) {
            $type = strtolower(str_replace(' ', '_', $issue));
            if ($type === 'low_stock' && setting_value($conn, 'notifications_low_stock', '1') !== '1') {
                continue;
            }
            if ($type === 'expiring_soon' && setting_value($conn, 'notifications_expiring_soon', '1') !== '1') {
                continue;
            }
            if (in_array($type, ['expired_batch', 'critical_expiry'], true) && setting_value($conn, 'notifications_expired', '1') !== '1') {
                continue;
            }

            $key = implode(':', ['alert', $row['source_key'], $row['id'], $type]);
            notify_roles(
                $conn,
                [ROLE_ADMIN, ROLE_MANAGER, ROLE_STAFF, ROLE_VIEWER],
                'alert',
                in_array($type, ['expired_batch', 'critical_expiry', 'out_of_stock'], true) ? 'danger' : 'warning',
                $issue . ': ' . ($row['generic_name'] ?? ''),
                trim(($row['batch_no'] ?? '') . ' | ' . inventory_source_label($row['source_key']) . ' | Available ' . number_format((int) ($row['qty'] ?? 0))),
                $row['target_type'] ?? 'batch_regular',
                (int) ($row['id'] ?? 0),
                $key
            );
        }
    }
}

function notification_where_clause(): array
{
    $role = current_user_role();
    $userId = current_user_id();
    return [
        'sql' => '(user_id = ? OR user_id IS NULL AND (role_scope = ? OR role_scope = "all"))',
        'types' => 'is',
        'params' => [$userId, $role],
    ];
}

function unread_notification_count(mysqli $conn): int
{
    $clause = notification_where_clause();
    $row = db_fetch_one(
        $conn,
        "SELECT COUNT(*) AS total FROM notifications WHERE {$clause['sql']} AND is_read = 0",
        $clause['types'],
        $clause['params']
    );

    return (int) ($row['total'] ?? 0);
}

function fetch_notifications(mysqli $conn, array $filters = []): array
{
    $clause = notification_where_clause();
    $readFilter = trim((string) ($filters['read_filter'] ?? 'all'));
    $typeFilter = trim((string) ($filters['type_filter'] ?? 'all'));
    $search = strtolower(trim((string) ($filters['search'] ?? '')));

    $rows = db_fetch_all(
        $conn,
        "SELECT * FROM notifications WHERE {$clause['sql']} ORDER BY created_at DESC",
        $clause['types'],
        $clause['params']
    );

    $result = [];
    foreach ($rows as $row) {
        if ($readFilter === 'read' && (int) ($row['is_read'] ?? 0) !== 1) {
            continue;
        }
        if ($readFilter === 'unread' && (int) ($row['is_read'] ?? 0) !== 0) {
            continue;
        }
        if ($typeFilter !== '' && $typeFilter !== 'all' && $typeFilter !== ($row['notification_type'] ?? '')) {
            continue;
        }
        if ($search !== '') {
            $haystack = strtolower(implode(' ', [
                $row['title'] ?? '',
                $row['message'] ?? '',
                $row['notification_type'] ?? '',
                $row['severity'] ?? '',
            ]));
            if (!str_contains($haystack, $search)) {
                continue;
            }
        }
        $result[] = $row;
    }

    return $result;
}

function mark_notification_read(mysqli $conn, int $notificationId, bool $isRead = true): void
{
    $clause = notification_where_clause();
    $stmt = $conn->prepare(
        "UPDATE notifications
         SET is_read = ?, read_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END
         WHERE id = ? AND {$clause['sql']}"
    );
    if (!$stmt) {
        return;
    }

    $readInt = $isRead ? 1 : 0;
    $userId = $clause['params'][0];
    $role = $clause['params'][1];
    $stmt->bind_param('iiiis', $readInt, $readInt, $notificationId, $userId, $role);
    $stmt->execute();
    $stmt->close();
}

function mark_all_notifications_read(mysqli $conn): void
{
    $clause = notification_where_clause();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE {$clause['sql']}");
    if (!$stmt) {
        return;
    }

    $userId = $clause['params'][0];
    $role = $clause['params'][1];
    $stmt->bind_param('is', $userId, $role);
    $stmt->execute();
    $stmt->close();
}

function log_audit(mysqli $conn, string $actionType, string $entityType, int $entityId, string $summary, ?string $reason = null, array $oldValues = [], array $newValues = [], array $metadata = []): void
{
    $actor = current_user_actor();
    $stmt = $conn->prepare(
        "INSERT INTO audit_logs
         (actor_id, actor_name, actor_role, action_type, entity_type, entity_id, summary, reason, old_values, new_values, metadata, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        return;
    }

    $oldJson = $oldValues ? json_store($oldValues) : null;
    $newJson = $newValues ? json_store($newValues) : null;
    $metaJson = $metadata ? json_store($metadata) : null;
    $stmt->bind_param(
        'issssisssss',
        $actor['id'],
        $actor['name'],
        $actor['role'],
        $actionType,
        $entityType,
        $entityId,
        $summary,
        $reason,
        $oldJson,
        $newJson,
        $metaJson
    );
    $stmt->execute();
    $stmt->close();
}

function log_correction(mysqli $conn, string $recordType, int $recordId, array $oldValues, array $newValues, string $reason): void
{
    $actor = current_user_actor();
    $stmt = $conn->prepare(
        "INSERT INTO correction_logs
         (actor_id, actor_name, actor_role, record_type, record_id, reason, old_values, new_values, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if ($stmt) {
        $oldJson = json_store($oldValues);
        $newJson = json_store($newValues);
        $stmt->bind_param(
            'isssisss',
            $actor['id'],
            $actor['name'],
            $actor['role'],
            $recordType,
            $recordId,
            $reason,
            $oldJson,
            $newJson
        );
        $stmt->execute();
        $stmt->close();
    }

    log_audit(
        $conn,
        'correction',
        $recordType,
        $recordId,
        'Corrected ' . str_replace('_', ' ', $recordType),
        $reason,
        $oldValues,
        $newValues
    );
}

function fetch_correction_logs(mysqli $conn, array $filters = []): array
{
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $recordType = trim((string) ($filters['record_type'] ?? 'all'));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    $rows = db_fetch_all($conn, 'SELECT * FROM correction_logs ORDER BY created_at DESC');
    $result = [];

    foreach ($rows as $row) {
        if ($recordType !== '' && $recordType !== 'all' && $recordType !== ($row['record_type'] ?? '')) {
            continue;
        }

        $createdTs = strtotime($row['created_at'] ?? '');
        if ($dateFrom !== '' && $createdTs < strtotime($dateFrom . ' 00:00:00')) {
            continue;
        }
        if ($dateTo !== '' && $createdTs > strtotime($dateTo . ' 23:59:59')) {
            continue;
        }

        if ($search !== '') {
            $haystack = strtolower(implode(' ', [
                $row['actor_name'] ?? '',
                $row['actor_role'] ?? '',
                $row['record_type'] ?? '',
                $row['reason'] ?? '',
                $row['old_values'] ?? '',
                $row['new_values'] ?? '',
            ]));
            if (!str_contains($haystack, $search)) {
                continue;
            }
        }

        $result[] = $row;
    }

    return $result;
}

function fetch_products(mysqli $conn, array $filters = []): array
{
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $status = trim((string) ($filters['status'] ?? 'active'));
    $manufacturer = trim((string) ($filters['manufacturer'] ?? ''));

    $rows = db_fetch_all(
        $conn,
        'SELECT p.*,
            (SELECT COUNT(*) FROM inventory i WHERE i.product_id = p.id AND i.record_status = "active") AS regular_batches,
            (SELECT COUNT(*) FROM inventory_outsourced o WHERE o.product_id = p.id AND o.record_status = "active") AS outsourced_batches
         FROM products p
         ORDER BY p.generic_name, p.brand_name, p.dosage_strength'
    );

    $result = [];
    foreach ($rows as $row) {
        if ($status === 'active' && ($row['product_status'] ?? 'active') !== 'active') {
            continue;
        }
        if ($status === 'archived' && ($row['product_status'] ?? 'active') !== 'archived') {
            continue;
        }
        if ($manufacturer !== '' && strcasecmp((string) ($row['manufacturer'] ?? ''), $manufacturer) !== 0) {
            continue;
        }
        if ($search !== '') {
            $haystack = strtolower(implode(' ', [
                $row['generic_name'] ?? '',
                $row['brand_name'] ?? '',
                $row['dosage_strength'] ?? '',
                $row['manufacturer'] ?? '',
                $row['registration_no'] ?? '',
                $row['barcode_value'] ?? '',
                $row['product_type'] ?? '',
            ]));
            if (!str_contains($haystack, $search)) {
                continue;
            }
        }
        $result[] = $row;
    }

    return $result;
}

function fetch_product_by_id(mysqli $conn, int $productId): ?array
{
    return db_fetch_one($conn, 'SELECT * FROM products WHERE id = ? LIMIT 1', 'i', [$productId]);
}

function product_payload_from_request(array $input): array
{
    return [
        'generic_name' => trim((string) ($input['generic_name'] ?? '')),
        'brand_name' => trim((string) ($input['brand_name'] ?? '')),
        'dosage_strength' => trim((string) ($input['dosage_strength'] ?? '')),
        'manufacturer' => trim((string) ($input['manufacturer'] ?? '')),
        'registration_no' => trim((string) ($input['registration_no'] ?? '')),
        'default_low_stock_threshold' => max(1, (int) ($input['default_low_stock_threshold'] ?? 10)),
        'product_type' => trim((string) ($input['product_type'] ?? 'medicine')) ?: 'medicine',
        'product_status' => trim((string) ($input['product_status'] ?? 'active')) === 'archived' ? 'archived' : 'active',
        'barcode_value' => normalize_barcode_value($input['barcode_value'] ?? ''),
    ];
}

function validate_product_payload(mysqli $conn, array $payload, ?int $excludeId = null): array
{
    $errors = [];

    if ($payload['generic_name'] === '' || $payload['brand_name'] === '' || $payload['dosage_strength'] === '') {
        $errors[] = 'Generic name, brand name, and dosage strength are required.';
    }

    if ($payload['manufacturer'] === '') {
        $errors[] = 'Manufacturer is required.';
    }

    if (!barcode_is_valid($payload['barcode_value'])) {
        $errors[] = 'Barcode value must use Code39-safe characters only.';
    }

    $sql = 'SELECT id FROM products WHERE generic_name = ? AND brand_name = ? AND dosage_strength = ? AND manufacturer = ? AND registration_no = ?';
    $types = 'sssss';
    $params = [
        $payload['generic_name'],
        $payload['brand_name'],
        $payload['dosage_strength'],
        $payload['manufacturer'],
        $payload['registration_no'],
    ];

    if ($excludeId) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $excludeId;
    }

    $exists = db_fetch_one($conn, $sql . ' LIMIT 1', $types, $params);
    if ($exists) {
        $errors[] = 'A matching product master record already exists.';
    }

    return $errors;
}

function create_product_record(mysqli $conn, array $payload): int
{
    $actor = current_user_actor();
    $stmt = $conn->prepare(
        'INSERT INTO products
        (generic_name, brand_name, dosage_strength, manufacturer, registration_no, default_low_stock_threshold, product_type, product_status, barcode_value, created_by_id, created_by, updated_by_id, updated_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param(
        'sssssisssisis',
        $payload['generic_name'],
        $payload['brand_name'],
        $payload['dosage_strength'],
        $payload['manufacturer'],
        $payload['registration_no'],
        $payload['default_low_stock_threshold'],
        $payload['product_type'],
        $payload['product_status'],
        $payload['barcode_value'],
        $actor['id'],
        $actor['name'],
        $actor['id'],
        $actor['name']
    );
    $stmt->execute();
    $productId = (int) $stmt->insert_id;
    $stmt->close();

    log_audit($conn, 'create', 'product', $productId, 'Created product master', null, [], $payload);
    notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'product', 'info', 'Product created', $payload['generic_name'] . ' is now available in product master.', 'product', $productId, 'product:create:' . $productId);

    return $productId;
}

function propagate_product_changes_to_batches(mysqli $conn, int $productId, array $payload): void
{
    db_execute(
        $conn,
        'UPDATE inventory SET generic_name = ?, brand_name = ?, dosage_strength = ?, manufacturer = ?, registration_no = ?, updated_at = NOW() WHERE product_id = ?',
        'sssssi',
        [
            $payload['generic_name'],
            $payload['brand_name'],
            $payload['dosage_strength'],
            $payload['manufacturer'],
            $payload['registration_no'],
            $productId,
        ]
    );

    db_execute(
        $conn,
        'UPDATE inventory_outsourced SET generic_name = ?, brand_name = ?, dosage_strength = ?, manufacturer = ?, registration_no = ?, updated_at = NOW() WHERE product_id = ?',
        'sssssi',
        [
            $payload['generic_name'],
            $payload['brand_name'],
            $payload['dosage_strength'],
            $payload['manufacturer'],
            $payload['registration_no'],
            $productId,
        ]
    );

    db_execute(
        $conn,
        'UPDATE in_log SET generic_name = ?, brand_name = ?, dosage_strength = ?, manufacturer = ?, registration_no = ? WHERE product_id = ?',
        'sssssi',
        [
            $payload['generic_name'],
            $payload['brand_name'],
            $payload['dosage_strength'],
            $payload['manufacturer'],
            $payload['registration_no'],
            $productId,
        ]
    );
}

function update_product_record(mysqli $conn, int $productId, array $payload, string $reason): bool
{
    $product = fetch_product_by_id($conn, $productId);
    if (!$product) {
        return false;
    }

    $actor = current_user_actor();
    $stmt = $conn->prepare(
        'UPDATE products
         SET generic_name = ?, brand_name = ?, dosage_strength = ?, manufacturer = ?, registration_no = ?, default_low_stock_threshold = ?, product_type = ?, product_status = ?, barcode_value = ?, updated_by_id = ?, updated_by = ?, updated_at = NOW()
         WHERE id = ?'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'sssssisssisi',
        $payload['generic_name'],
        $payload['brand_name'],
        $payload['dosage_strength'],
        $payload['manufacturer'],
        $payload['registration_no'],
        $payload['default_low_stock_threshold'],
        $payload['product_type'],
        $payload['product_status'],
        $payload['barcode_value'],
        $actor['id'],
        $actor['name'],
        $productId
    );
    $stmt->execute();
    $stmt->close();

    propagate_product_changes_to_batches($conn, $productId, $payload);
    log_correction($conn, 'product', $productId, $product, array_merge($product, $payload), $reason);
    notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'correction', 'info', 'Product corrected', $payload['generic_name'] . ' was updated. Reason: ' . $reason, 'product', $productId, 'product:update:' . $productId . ':' . md5($reason . json_store($payload)));

    return true;
}

function archive_product_record(mysqli $conn, int $productId, string $reason): bool
{
    $product = fetch_product_by_id($conn, $productId);
    if (!$product || ($product['product_status'] ?? 'active') === 'archived') {
        return false;
    }

    $actor = current_user_actor();
    $stmt = $conn->prepare(
        'UPDATE products SET product_status = "archived", archived_at = NOW(), archived_by_id = ?, archived_by = ?, updated_by_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ?'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('isisi', $actor['id'], $actor['name'], $actor['id'], $actor['name'], $productId);
    $stmt->execute();
    $stmt->close();

    log_audit($conn, 'archive', 'product', $productId, 'Archived product master', $reason, $product, ['product_status' => 'archived']);
    notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'product', 'warning', 'Product archived', ($product['generic_name'] ?? 'Product') . ' was archived. Reason: ' . $reason, 'product', $productId, 'product:archive:' . $productId);

    return true;
}

function batch_exists(mysqli $conn, string $batchNo, ?string $excludeSource = null, ?int $excludeId = null): bool
{
    foreach (inventory_sources() as $sourceKey => $source) {
        $sql = "SELECT id FROM {$source['table']} WHERE batch_no = ?";
        $types = 's';
        $params = [$batchNo];
        if ($excludeSource === $sourceKey && $excludeId) {
            $sql .= ' AND id <> ?';
            $types .= 'i';
            $params[] = $excludeId;
        }
        $row = db_fetch_one($conn, $sql . ' LIMIT 1', $types, $params);
        if ($row) {
            return true;
        }
    }

    return false;
}

function fetch_batch_by_source_id(mysqli $conn, string $sourceKey, int $id, bool $includeInactive = true): ?array
{
    $table = inventory_source_table($sourceKey);
    $where = $includeInactive ? 'id = ?' : 'id = ? AND record_status = "active"';
    $row = db_fetch_one(
        $conn,
        "SELECT t.*, p.default_low_stock_threshold, p.barcode_value
         FROM {$table} t
         LEFT JOIN products p ON p.id = t.product_id
         WHERE {$where}
         LIMIT 1",
        'i',
        [$id]
    );

    if (!$row) {
        return null;
    }

    $row['source_key'] = $sourceKey;
    $row['source_type'] = inventory_source_label($sourceKey);
    $row['source_table'] = $table;
    $row['target_type'] = inventory_target_type($sourceKey);
    $row['low_stock_threshold_resolved'] = low_stock_threshold_for_row($conn, $row);
    return $row;
}

function fetch_batch_by_number(mysqli $conn, string $batchNo): ?array
{
    foreach (array_keys(inventory_sources()) as $sourceKey) {
        $table = inventory_source_table($sourceKey);
        $row = db_fetch_one(
            $conn,
            "SELECT t.*, p.default_low_stock_threshold, p.barcode_value
             FROM {$table} t
             LEFT JOIN products p ON p.id = t.product_id
             WHERE t.batch_no = ?
             ORDER BY FIELD(t.record_status, 'active', 'archived', 'voided'), t.id DESC
             LIMIT 1",
            's',
            [$batchNo]
        );
        if ($row) {
            $row['source_key'] = $sourceKey;
            $row['source_type'] = inventory_source_label($sourceKey);
            $row['source_table'] = $table;
            $row['target_type'] = inventory_target_type($sourceKey);
            $row['low_stock_threshold_resolved'] = low_stock_threshold_for_row($conn, $row);
            return $row;
        }
    }

    return null;
}

function fetch_linked_in_log(mysqli $conn, string $sourceKey, int $batchId, string $batchNo = ''): ?array
{
    $table = inventory_source_table($sourceKey);
    $row = db_fetch_one(
        $conn,
        'SELECT * FROM in_log WHERE inventory_table = ? AND inventory_ref_id = ? ORDER BY added_at ASC LIMIT 1',
        'si',
        [$table, $batchId]
    );

    if ($row || $batchNo === '') {
        return $row;
    }

    return db_fetch_one($conn, 'SELECT * FROM in_log WHERE batch_no = ? ORDER BY added_at ASC LIMIT 1', 's', [$batchNo]);
}

function fetch_out_records_for_batch(mysqli $conn, array $batch): array
{
    if (($batch['source_key'] ?? '') !== 'regular') {
        return [];
    }

    return db_fetch_all(
        $conn,
        'SELECT * FROM out_records WHERE inventory_id = ? ORDER BY created_at DESC',
        'i',
        [(int) ($batch['id'] ?? 0)]
    );
}

function fetch_return_records_for_batch(mysqli $conn, array $batch): array
{
    if (($batch['source_key'] ?? '') !== 'regular') {
        return [];
    }

    return db_fetch_all(
        $conn,
        'SELECT r.*, o.customer_name, o.document_type, o.document_number
         FROM return_binded_records r
         INNER JOIN out_records o ON o.id = r.out_record_id
         WHERE o.inventory_id = ?
         ORDER BY r.created_at DESC',
        'i',
        [(int) ($batch['id'] ?? 0)]
    );
}

function fetch_batch_history_bundle(mysqli $conn, string $batchNo): array
{
    $bundle = [
        'batch' => null,
        'in_log' => null,
        'out_records' => [],
        'return_records' => [],
        'summary' => [
            'active_out' => 0,
            'active_returned' => 0,
            'voided_out' => 0,
            'voided_returns' => 0,
        ],
    ];

    $batch = fetch_batch_by_number($conn, $batchNo);
    if (!$batch) {
        return $bundle;
    }

    $bundle['batch'] = $batch;
    $bundle['in_log'] = fetch_linked_in_log($conn, $batch['source_key'], (int) $batch['id'], $batchNo);
    $bundle['out_records'] = fetch_out_records_for_batch($conn, $batch);
    $bundle['return_records'] = fetch_return_records_for_batch($conn, $batch);

    foreach ($bundle['out_records'] as $row) {
        if (($row['record_status'] ?? 'active') === 'active') {
            $bundle['summary']['active_out'] += (int) ($row['qty_out'] ?? 0);
        } else {
            $bundle['summary']['voided_out'] += (int) ($row['qty_out'] ?? 0);
        }
    }

    foreach ($bundle['return_records'] as $row) {
        if (($row['record_status'] ?? 'active') === 'active') {
            $bundle['summary']['active_returned'] += (int) ($row['qty_returned'] ?? 0);
        } else {
            $bundle['summary']['voided_returns'] += (int) ($row['qty_returned'] ?? 0);
        }
    }

    return $bundle;
}

function fetch_out_record_detail(mysqli $conn, int $recordId): ?array
{
    return db_fetch_one(
        $conn,
        'SELECT o.*, i.batch_no, i.generic_name, i.brand_name, i.dosage_strength, i.manufacturer, i.registration_no, i.product_id
         FROM out_records o
         LEFT JOIN inventory i ON i.id = o.inventory_id
         WHERE o.id = ?
         LIMIT 1',
        'i',
        [$recordId]
    );
}

function fetch_return_record_detail(mysqli $conn, int $recordId): ?array
{
    return db_fetch_one(
        $conn,
        'SELECT r.*, o.inventory_id, o.qty_out, o.qty_returned AS out_qty_returned, o.customer_name, o.document_type, o.document_number, i.batch_no, i.generic_name, i.brand_name, i.dosage_strength, i.manufacturer, i.registration_no
         FROM return_binded_records r
         LEFT JOIN out_records o ON o.id = r.out_record_id
         LEFT JOIN inventory i ON i.id = o.inventory_id
         WHERE r.id = ?
         LIMIT 1',
        'i',
        [$recordId]
    );
}

function fetch_in_record_detail(mysqli $conn, int $recordId): ?array
{
    return db_fetch_one($conn, 'SELECT * FROM in_log WHERE id = ? LIMIT 1', 'i', [$recordId]);
}

function note_target_label(string $targetType): string
{
    return match ($targetType) {
        'product' => 'Product',
        'batch_regular' => 'Regular Batch',
        'batch_outsourced' => 'Outsourced Batch',
        'out_record' => 'OUT Transaction',
        'return_record' => 'RETURN Transaction',
        'in_log' => 'IN Transaction',
        default => 'Record',
    };
}

function can_manage_note_record(array $note): bool
{
    if (user_can('notes.manage')) {
        return true;
    }

    return user_can('notes.add') && (int) ($note['created_by_id'] ?? 0) === current_user_id();
}

function fetch_notes(mysqli $conn, string $targetType, int $targetId): array
{
    return db_fetch_all(
        $conn,
        'SELECT * FROM notes WHERE target_type = ? AND target_id = ? AND record_status = "active" ORDER BY created_at DESC',
        'si',
        [$targetType, $targetId]
    );
}

function add_note_record(mysqli $conn, string $targetType, int $targetId, string $noteText): bool
{
    $actor = current_user_actor();
    $stmt = $conn->prepare(
        'INSERT INTO notes
         (target_type, target_id, note_text, created_by_id, created_by, updated_by_id, updated_by, record_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, "active", NOW(), NOW())'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sisisis', $targetType, $targetId, $noteText, $actor['id'], $actor['name'], $actor['id'], $actor['name']);
    $stmt->execute();
    $noteId = (int) $stmt->insert_id;
    $stmt->close();

    log_audit($conn, 'note_add', $targetType, $targetId, 'Added note to ' . note_target_label($targetType), null, [], ['note_id' => $noteId, 'note_text' => $noteText]);
    return true;
}

function update_note_record(mysqli $conn, int $noteId, string $noteText): bool
{
    $note = db_fetch_one($conn, 'SELECT * FROM notes WHERE id = ? LIMIT 1', 'i', [$noteId]);
    if (!$note) {
        return false;
    }

    $actor = current_user_actor();
    $stmt = $conn->prepare('UPDATE notes SET note_text = ?, updated_by_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sisi', $noteText, $actor['id'], $actor['name'], $noteId);
    $stmt->execute();
    $stmt->close();

    log_audit($conn, 'note_update', $note['target_type'] ?? 'note', (int) ($note['target_id'] ?? 0), 'Updated note', null, ['note_text' => $note['note_text'] ?? ''], ['note_text' => $noteText]);
    return true;
}

function delete_note_record(mysqli $conn, int $noteId): bool
{
    $note = db_fetch_one($conn, 'SELECT * FROM notes WHERE id = ? LIMIT 1', 'i', [$noteId]);
    if (!$note) {
        return false;
    }

    $actor = current_user_actor();
    $stmt = $conn->prepare('UPDATE notes SET record_status = "deleted", updated_by_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('isi', $actor['id'], $actor['name'], $noteId);
    $stmt->execute();
    $stmt->close();

    log_audit($conn, 'note_delete', $note['target_type'] ?? 'note', (int) ($note['target_id'] ?? 0), 'Deleted note', null, ['note_text' => $note['note_text'] ?? ''], []);
    return true;
}

function count_active_returns_for_out(mysqli $conn, int $outRecordId, ?int $excludeReturnId = null): int
{
    $sql = 'SELECT COALESCE(SUM(qty_returned), 0) AS total FROM return_binded_records WHERE out_record_id = ? AND record_status = "active"';
    $types = 'i';
    $params = [$outRecordId];

    if ($excludeReturnId) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $excludeReturnId;
    }

    $row = db_fetch_one($conn, $sql, $types, $params);
    return (int) ($row['total'] ?? 0);
}

function derive_return_status(string $recordStatus, int $qtyOut, int $qtyReturned): string
{
    if ($recordStatus === 'voided') {
        return 'Voided';
    }

    if ($qtyReturned <= 0) {
        return 'Delivered';
    }

    if ($qtyReturned >= $qtyOut) {
        return 'Return full';
    }

    return 'Return partial';
}

function correct_inventory_batch(mysqli $conn, string $sourceKey, int $batchId, array $payload, string $reason, array &$errors): bool
{
    $batch = fetch_batch_by_source_id($conn, $sourceKey, $batchId, true);
    if (!$batch) {
        $errors[] = 'Batch was not found.';
        return false;
    }

    $product = fetch_product_by_id($conn, (int) ($payload['product_id'] ?? 0));
    if (!$product) {
        $errors[] = 'Please select a valid product master record.';
        return false;
    }

    $batchNo = trim((string) ($payload['batch_no'] ?? ''));
    $qtyIn = max(1, (int) ($payload['qty_in'] ?? 0));
    $mfgDate = date_input_to_month_year($payload['mfg_date'] ?? '');
    $expDate = date_input_to_month_year($payload['exp_date'] ?? '');
    $importer = trim((string) ($payload['importer_name'] ?? ''));
    $distributor = trim((string) ($payload['distributor_name'] ?? ''));
    $threshold = max(1, (int) ($payload['low_stock_threshold'] ?? 0));

    if ($batchNo === '') {
        $errors[] = 'Batch number is required.';
    }
    if ($mfgDate === '' || $expDate === '') {
        $errors[] = 'Manufacturing and expiry dates are required.';
    }
    if (batch_exists($conn, $batchNo, $sourceKey, $batchId)) {
        $errors[] = 'Batch number already exists.';
    }
    if ($sourceKey === 'outsourced' && $distributor === '') {
        $errors[] = 'Distributor name is required for outsourced batches.';
    }

    $delta = $qtyIn - (int) ($batch['qty_in'] ?? 0);
    $newAvailable = (int) ($batch['qty'] ?? 0) + $delta;
    if ($newAvailable < 0) {
        $errors[] = 'The new quantity in would make available stock negative.';
    }

    if ($errors) {
        return false;
    }

    $table = inventory_source_table($sourceKey);
    $conn->begin_transaction();
    try {
        if ($sourceKey === 'outsourced') {
            $stmt = $conn->prepare(
                "UPDATE {$table}
                 SET product_id = ?, generic_name = ?, brand_name = ?, dosage_strength = ?, batch_no = ?, mfg_date = ?, exp_date = ?, manufacturer = ?, registration_no = ?, importer_name = ?, distributor_name = ?, qty_in = ?, qty = ?, low_stock_threshold = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param(
                'isssssssssssiiii',
                $product['id'],
                $product['generic_name'],
                $product['brand_name'],
                $product['dosage_strength'],
                $batchNo,
                $mfgDate,
                $expDate,
                $product['manufacturer'],
                $product['registration_no'],
                $importer,
                $distributor,
                $qtyIn,
                $newAvailable,
                $threshold,
                $batchId
            );
        } else {
            $stmt = $conn->prepare(
                "UPDATE {$table}
                 SET product_id = ?, generic_name = ?, brand_name = ?, dosage_strength = ?, batch_no = ?, mfg_date = ?, exp_date = ?, manufacturer = ?, registration_no = ?, qty_in = ?, qty = ?, low_stock_threshold = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param(
                'issssssssiiii',
                $product['id'],
                $product['generic_name'],
                $product['brand_name'],
                $product['dosage_strength'],
                $batchNo,
                $mfgDate,
                $expDate,
                $product['manufacturer'],
                $product['registration_no'],
                $qtyIn,
                $newAvailable,
                $threshold,
                $batchId
            );
        }
        $stmt->execute();
        $stmt->close();

        $linkedIn = fetch_linked_in_log($conn, $sourceKey, $batchId, (string) ($batch['batch_no'] ?? ''));
        if ($linkedIn) {
            db_execute(
                $conn,
                'UPDATE in_log
                 SET product_id = ?, generic_name = ?, brand_name = ?, dosage_strength = ?, batch_no = ?, mfg_date = ?, exp_date = ?, manufacturer = ?, registration_no = ?, qty_in = ?, source_type = ?
                 WHERE id = ?',
                'issssssssisi',
                [
                    $product['id'],
                    $product['generic_name'],
                    $product['brand_name'],
                    $product['dosage_strength'],
                    $batchNo,
                    $mfgDate,
                    $expDate,
                    $product['manufacturer'],
                    $product['registration_no'],
                    $qtyIn,
                    $sourceKey,
                    (int) ($linkedIn['id'] ?? 0),
                ]
            );
        }

        $conn->commit();
        log_correction(
            $conn,
            inventory_target_type($sourceKey),
            $batchId,
            $batch,
            [
                'product_id' => $product['id'],
                'generic_name' => $product['generic_name'],
                'brand_name' => $product['brand_name'],
                'dosage_strength' => $product['dosage_strength'],
                'manufacturer' => $product['manufacturer'],
                'registration_no' => $product['registration_no'],
                'batch_no' => $batchNo,
                'mfg_date' => $mfgDate,
                'exp_date' => $expDate,
                'qty_in' => $qtyIn,
                'qty' => $newAvailable,
                'importer_name' => $importer,
                'distributor_name' => $distributor,
                'low_stock_threshold' => $threshold,
            ],
            $reason
        );
        notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'correction', 'info', 'Batch corrected', $batchNo . ' was corrected. Reason: ' . $reason, inventory_target_type($sourceKey), $batchId, 'batch:correction:' . $sourceKey . ':' . $batchId . ':' . md5($reason . $batchNo));
        return true;
    } catch (Throwable $exception) {
        $conn->rollback();
        $errors[] = 'Unable to update the batch: ' . $exception->getMessage();
        return false;
    }
}

function correct_out_record(mysqli $conn, int $recordId, array $payload, string $reason, array &$errors): bool
{
    $record = fetch_out_record_detail($conn, $recordId);
    if (!$record || ($record['record_status'] ?? 'active') !== 'active') {
        $errors[] = 'OUT transaction was not found or is already voided.';
        return false;
    }

    $qtyOut = max(1, (int) ($payload['qty_out'] ?? 0));
    $customer = trim((string) ($payload['customer_name'] ?? ''));
    $documentType = trim((string) ($payload['document_type'] ?? ''));
    $documentNumber = trim((string) ($payload['document_number'] ?? ''));
    $activeReturned = count_active_returns_for_out($conn, $recordId);

    if ($customer === '' || $documentType === '' || $documentNumber === '') {
        $errors[] = 'Customer and document details are required.';
    }
    if ($qtyOut < $activeReturned) {
        $errors[] = 'Quantity out cannot be lower than the active returned quantity.';
    }

    $inventory = fetch_batch_by_source_id($conn, 'regular', (int) ($record['inventory_id'] ?? 0), true);
    if (!$inventory) {
        $errors[] = 'The linked inventory batch was not found.';
    }

    $delta = $qtyOut - (int) ($record['qty_out'] ?? 0);
    $newInventoryQty = (int) ($inventory['qty'] ?? 0) - $delta;
    if ($newInventoryQty < 0) {
        $errors[] = 'The new OUT quantity exceeds available stock.';
    }

    if ($errors) {
        return false;
    }

    $newInventoryQtyOut = (int) ($inventory['qty_out'] ?? 0) + $delta;
    $newReturnStatus = derive_return_status('active', $qtyOut, $activeReturned);

    $conn->begin_transaction();
    try {
        db_execute(
            $conn,
            'UPDATE inventory SET qty = ?, qty_out = ?, updated_at = NOW() WHERE id = ?',
            'iii',
            [$newInventoryQty, $newInventoryQtyOut, (int) $record['inventory_id']]
        );

        db_execute(
            $conn,
            'UPDATE out_records SET qty_out = ?, customer_name = ?, document_type = ?, document_number = ?, return_status = ? WHERE id = ?',
            'issssi',
            [$qtyOut, $customer, $documentType, $documentNumber, $newReturnStatus, $recordId]
        );

        $conn->commit();
        log_correction(
            $conn,
            'out_record',
            $recordId,
            $record,
            [
                'qty_out' => $qtyOut,
                'customer_name' => $customer,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'return_status' => $newReturnStatus,
            ],
            $reason
        );
        notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'correction', 'info', 'OUT transaction corrected', ($record['batch_no'] ?? 'Batch') . ' OUT transaction was corrected. Reason: ' . $reason, 'out_record', $recordId, 'out:correction:' . $recordId . ':' . md5($reason . $qtyOut));
        return true;
    } catch (Throwable $exception) {
        $conn->rollback();
        $errors[] = 'Unable to update the OUT transaction: ' . $exception->getMessage();
        return false;
    }
}

function correct_return_record(mysqli $conn, int $recordId, array $payload, string $reason, array &$errors): bool
{
    $record = fetch_return_record_detail($conn, $recordId);
    if (!$record || ($record['record_status'] ?? 'active') !== 'active') {
        $errors[] = 'RETURN transaction was not found or is already voided.';
        return false;
    }

    $qtyReturned = max(1, (int) ($payload['qty_returned'] ?? 0));
    $otherReturned = count_active_returns_for_out($conn, (int) ($record['out_record_id'] ?? 0), $recordId);

    if ($qtyReturned + $otherReturned > (int) ($record['qty_out'] ?? 0)) {
        $errors[] = 'The corrected return quantity exceeds the original OUT quantity.';
    }

    $inventory = fetch_batch_by_source_id($conn, 'regular', (int) ($record['inventory_id'] ?? 0), true);
    if (!$inventory) {
        $errors[] = 'The linked inventory batch was not found.';
    }

    $delta = $qtyReturned - (int) ($record['qty_returned'] ?? 0);
    $newInventoryQty = (int) ($inventory['qty'] ?? 0) + $delta;
    $newInventoryReturned = (int) ($inventory['qty_returned'] ?? 0) + $delta;
    $newOutReturned = (int) ($record['out_qty_returned'] ?? 0) + $delta;

    if ($newInventoryQty < 0 || $newInventoryReturned < 0 || $newOutReturned < 0) {
        $errors[] = 'The corrected return quantity would make stock history inconsistent.';
    }

    if ($errors) {
        return false;
    }

    $returnStatus = derive_return_status('active', (int) ($record['qty_out'] ?? 0), $newOutReturned);

    $conn->begin_transaction();
    try {
        db_execute(
            $conn,
            'UPDATE inventory SET qty = ?, qty_returned = ?, updated_at = NOW() WHERE id = ?',
            'iii',
            [$newInventoryQty, $newInventoryReturned, (int) $record['inventory_id']]
        );

        db_execute(
            $conn,
            'UPDATE return_binded_records SET qty_returned = ?, updated_at = NOW() WHERE id = ?',
            'ii',
            [$qtyReturned, $recordId]
        );

        db_execute(
            $conn,
            'UPDATE out_records SET qty_returned = ?, return_status = ? WHERE id = ?',
            'isi',
            [$newOutReturned, $returnStatus, (int) $record['out_record_id']]
        );

        $conn->commit();
        log_correction(
            $conn,
            'return_record',
            $recordId,
            $record,
            [
                'qty_returned' => $qtyReturned,
                'out_record_id' => (int) ($record['out_record_id'] ?? 0),
                'return_status' => $returnStatus,
            ],
            $reason
        );
        notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'correction', 'info', 'RETURN transaction corrected', ($record['batch_no'] ?? 'Batch') . ' RETURN transaction was corrected. Reason: ' . $reason, 'return_record', $recordId, 'return:correction:' . $recordId . ':' . md5($reason . $qtyReturned));
        return true;
    } catch (Throwable $exception) {
        $conn->rollback();
        $errors[] = 'Unable to update the RETURN transaction: ' . $exception->getMessage();
        return false;
    }
}

function void_return_record(mysqli $conn, int $recordId, string $reason, array &$errors): bool
{
    $record = fetch_return_record_detail($conn, $recordId);
    if (!$record || ($record['record_status'] ?? 'active') !== 'active') {
        $errors[] = 'RETURN transaction was not found or is already voided.';
        return false;
    }

    $inventory = fetch_batch_by_source_id($conn, 'regular', (int) ($record['inventory_id'] ?? 0), true);
    if (!$inventory) {
        $errors[] = 'The linked inventory batch was not found.';
        return false;
    }

    $qtyReturned = (int) ($record['qty_returned'] ?? 0);
    $newInventoryQty = (int) ($inventory['qty'] ?? 0) - $qtyReturned;
    $newInventoryReturned = (int) ($inventory['qty_returned'] ?? 0) - $qtyReturned;
    $newOutReturned = (int) ($record['out_qty_returned'] ?? 0) - $qtyReturned;

    if ($newInventoryQty < 0 || $newInventoryReturned < 0 || $newOutReturned < 0) {
        $errors[] = 'Voiding this return would make inventory totals negative.';
        return false;
    }

    $actor = current_user_actor();
    $returnStatus = derive_return_status('active', (int) ($record['qty_out'] ?? 0), $newOutReturned);

    $conn->begin_transaction();
    try {
        db_execute(
            $conn,
            'UPDATE inventory SET qty = ?, qty_returned = ?, updated_at = NOW() WHERE id = ?',
            'iii',
            [$newInventoryQty, $newInventoryReturned, (int) ($record['inventory_id'] ?? 0)]
        );
        db_execute(
            $conn,
            'UPDATE out_records SET qty_returned = ?, return_status = ? WHERE id = ?',
            'isi',
            [$newOutReturned, $returnStatus, (int) ($record['out_record_id'] ?? 0)]
        );
        db_execute(
            $conn,
            'UPDATE return_binded_records SET record_status = "voided", void_reason = ?, voided_by = ?, voided_by_id = ?, voided_at = NOW(), updated_at = NOW() WHERE id = ?',
            'ssii',
            [$reason, $actor['name'], $actor['id'], $recordId]
        );
        $conn->commit();

        log_audit($conn, 'void', 'return_record', $recordId, 'Voided RETURN transaction', $reason, $record, ['record_status' => 'voided']);
        notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'reversal', 'warning', 'RETURN transaction voided', ($record['batch_no'] ?? 'Batch') . ' RETURN transaction was voided. Reason: ' . $reason, 'return_record', $recordId, 'return:void:' . $recordId);
        return true;
    } catch (Throwable $exception) {
        $conn->rollback();
        $errors[] = 'Unable to void the RETURN transaction: ' . $exception->getMessage();
        return false;
    }
}

function void_out_record(mysqli $conn, int $recordId, string $reason, array &$errors): bool
{
    $record = fetch_out_record_detail($conn, $recordId);
    if (!$record || ($record['record_status'] ?? 'active') !== 'active') {
        $errors[] = 'OUT transaction was not found or is already voided.';
        return false;
    }

    $inventory = fetch_batch_by_source_id($conn, 'regular', (int) ($record['inventory_id'] ?? 0), true);
    if (!$inventory) {
        $errors[] = 'The linked inventory batch was not found.';
        return false;
    }

    $activeReturnRows = db_fetch_all(
        $conn,
        'SELECT * FROM return_binded_records WHERE out_record_id = ? AND record_status = "active"',
        'i',
        [$recordId]
    );
    $sumActiveReturns = (int) array_sum(array_column($activeReturnRows, 'qty_returned'));
    $newInventoryQty = (int) ($inventory['qty'] ?? 0) - $sumActiveReturns + (int) ($record['qty_out'] ?? 0);
    $newInventoryQtyOut = (int) ($inventory['qty_out'] ?? 0) - (int) ($record['qty_out'] ?? 0);
    $newInventoryReturned = (int) ($inventory['qty_returned'] ?? 0) - $sumActiveReturns;

    if ($newInventoryQty < 0 || $newInventoryQtyOut < 0 || $newInventoryReturned < 0) {
        $errors[] = 'Voiding this OUT transaction would make inventory totals negative.';
        return false;
    }

    $actor = current_user_actor();

    $conn->begin_transaction();
    try {
        db_execute(
            $conn,
            'UPDATE inventory SET qty = ?, qty_out = ?, qty_returned = ?, updated_at = NOW() WHERE id = ?',
            'iiii',
            [$newInventoryQty, $newInventoryQtyOut, $newInventoryReturned, (int) ($record['inventory_id'] ?? 0)]
        );

        foreach ($activeReturnRows as $returnRow) {
            db_execute(
                $conn,
                'UPDATE return_binded_records SET record_status = "voided", void_reason = ?, voided_by = ?, voided_by_id = ?, voided_at = NOW(), updated_at = NOW() WHERE id = ?',
                'ssii',
                [$reason . ' (Cascade from OUT void)', $actor['name'], $actor['id'], (int) ($returnRow['id'] ?? 0)]
            );
        }

        db_execute(
            $conn,
            'UPDATE out_records SET record_status = "voided", return_status = "Voided", void_reason = ?, voided_by = ?, voided_by_id = ?, voided_at = NOW(), updated_at = NOW() WHERE id = ?',
            'ssii',
            [$reason, $actor['name'], $actor['id'], $recordId]
        );

        $conn->commit();

        foreach ($activeReturnRows as $returnRow) {
            log_audit($conn, 'void', 'return_record', (int) ($returnRow['id'] ?? 0), 'Cascade void from OUT reversal', $reason, $returnRow, ['record_status' => 'voided']);
        }
        log_audit($conn, 'void', 'out_record', $recordId, 'Voided OUT transaction', $reason, $record, ['record_status' => 'voided']);
        notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'reversal', 'warning', 'OUT transaction voided', ($record['batch_no'] ?? 'Batch') . ' OUT transaction was voided. Reason: ' . $reason, 'out_record', $recordId, 'out:void:' . $recordId);
        return true;
    } catch (Throwable $exception) {
        $conn->rollback();
        $errors[] = 'Unable to void the OUT transaction: ' . $exception->getMessage();
        return false;
    }
}

function void_in_batch(mysqli $conn, string $sourceKey, int $batchId, string $reason, array &$errors): bool
{
    $batch = fetch_batch_by_source_id($conn, $sourceKey, $batchId, true);
    if (!$batch || ($batch['record_status'] ?? 'active') !== 'active') {
        $errors[] = 'Batch was not found or is already inactive.';
        return false;
    }

    if ((int) ($batch['qty_out'] ?? 0) !== 0 || (int) ($batch['qty_returned'] ?? 0) !== 0 || (int) ($batch['qty'] ?? 0) !== (int) ($batch['qty_in'] ?? 0)) {
        $errors[] = 'Only untouched IN batches can be voided safely.';
        return false;
    }

    if ($sourceKey === 'regular') {
        $activeOut = db_fetch_one(
            $conn,
            'SELECT COUNT(*) AS total FROM out_records WHERE inventory_id = ? AND record_status = "active"',
            'i',
            [$batchId]
        );
        if ((int) ($activeOut['total'] ?? 0) > 0) {
            $errors[] = 'This batch has active OUT transactions and cannot be voided.';
            return false;
        }
    }

    $actor = current_user_actor();
    $table = inventory_source_table($sourceKey);
    $conn->begin_transaction();
    try {
        db_execute(
            $conn,
            "UPDATE {$table} SET record_status = 'voided', qty = 0, void_reason = ?, voided_by = ?, voided_by_id = ?, voided_at = NOW(), updated_at = NOW() WHERE id = ?",
            'ssii',
            [$reason, $actor['name'], $actor['id'], $batchId]
        );

        $linkedIn = fetch_linked_in_log($conn, $sourceKey, $batchId, (string) ($batch['batch_no'] ?? ''));
        if ($linkedIn) {
            db_execute(
                $conn,
                'UPDATE in_log SET record_status = "voided", void_reason = ?, voided_by = ?, voided_by_id = ?, voided_at = NOW() WHERE id = ?',
                'ssii',
                [$reason, $actor['name'], $actor['id'], (int) ($linkedIn['id'] ?? 0)]
            );
        }

        $conn->commit();
        log_audit($conn, 'void', inventory_target_type($sourceKey), $batchId, 'Voided IN batch', $reason, $batch, ['record_status' => 'voided', 'qty' => 0]);
        notify_roles($conn, [ROLE_ADMIN, ROLE_MANAGER], 'reversal', 'warning', 'IN batch voided', ($batch['batch_no'] ?? 'Batch') . ' was voided. Reason: ' . $reason, inventory_target_type($sourceKey), $batchId, 'in:void:' . $sourceKey . ':' . $batchId);
        return true;
    } catch (Throwable $exception) {
        $conn->rollback();
        $errors[] = 'Unable to void the IN batch: ' . $exception->getMessage();
        return false;
    }
}

function report_date_range(array $filters = []): array
{
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));

    if ($dateTo === '') {
        $dateTo = date('Y-m-d');
    }

    if ($dateFrom === '') {
        $dateFrom = date('Y-m-d', strtotime($dateTo . ' -29 days'));
    }

    return [$dateFrom, $dateTo];
}

function build_date_labels(string $dateFrom, string $dateTo): array
{
    $labels = [];
    $cursor = new DateTime($dateFrom);
    $end = new DateTime($dateTo);
    while ($cursor <= $end) {
        $labels[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }
    return $labels;
}

function fetch_report_dataset(mysqli $conn, array $filters = []): array
{
    [$dateFrom, $dateTo] = report_date_range($filters);
    $inventoryFilters = [
        'source' => $filters['source'] ?? 'all',
    ];
    $inventoryRows = flatten_inventory_with_source($conn, $inventoryFilters);
    $alerts = fetch_alert_rows($conn, $inventoryFilters);
    $labels = build_date_labels($dateFrom, $dateTo);
    $inByDay = array_fill_keys($labels, 0);
    $outByDay = array_fill_keys($labels, 0);
    $returnByDay = array_fill_keys($labels, 0);

    $inRows = db_fetch_all($conn, 'SELECT * FROM in_log WHERE record_status = "active" AND DATE(added_at) BETWEEN ? AND ? ORDER BY added_at ASC', 'ss', [$dateFrom, $dateTo]);
    foreach ($inRows as $row) {
        $day = date('Y-m-d', strtotime($row['added_at']));
        if (isset($inByDay[$day])) {
            $inByDay[$day] += (int) ($row['qty_in'] ?? 0);
        }
    }

    $outRows = db_fetch_all($conn, 'SELECT o.*, i.generic_name, i.brand_name, i.dosage_strength FROM out_records o LEFT JOIN inventory i ON i.id = o.inventory_id WHERE o.record_status = "active" AND DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.created_at ASC', 'ss', [$dateFrom, $dateTo]);
    foreach ($outRows as $row) {
        $day = date('Y-m-d', strtotime($row['created_at']));
        if (isset($outByDay[$day])) {
            $outByDay[$day] += (int) ($row['qty_out'] ?? 0);
        }
    }

    $returnRows = db_fetch_all($conn, 'SELECT r.*, o.inventory_id, i.generic_name, i.brand_name, i.dosage_strength FROM return_binded_records r LEFT JOIN out_records o ON o.id = r.out_record_id LEFT JOIN inventory i ON i.id = o.inventory_id WHERE r.record_status = "active" AND DATE(r.created_at) BETWEEN ? AND ? ORDER BY r.created_at ASC', 'ss', [$dateFrom, $dateTo]);
    foreach ($returnRows as $row) {
        $day = date('Y-m-d', strtotime($row['created_at']));
        if (isset($returnByDay[$day])) {
            $returnByDay[$day] += (int) ($row['qty_returned'] ?? 0);
        }
    }

    $topOutgoing = [];
    foreach ($outRows as $row) {
        $key = product_display_name($row);
        $topOutgoing[$key] = ($topOutgoing[$key] ?? 0) + (int) ($row['qty_out'] ?? 0);
    }
    arsort($topOutgoing);

    $topReturned = [];
    foreach ($returnRows as $row) {
        $key = product_display_name($row);
        $topReturned[$key] = ($topReturned[$key] ?? 0) + (int) ($row['qty_returned'] ?? 0);
    }
    arsort($topReturned);

    $supplierSummary = [];
    foreach ($inventoryRows as $row) {
        $name = $row['source_key'] === 'outsourced'
            ? trim((string) ($row['distributor_name'] ?? 'Unassigned'))
            : trim((string) ($row['manufacturer'] ?? 'Unassigned'));
        if (!isset($supplierSummary[$name])) {
            $supplierSummary[$name] = ['qty' => 0, 'rows' => 0];
        }
        $supplierSummary[$name]['qty'] += (int) ($row['qty'] ?? 0);
        $supplierSummary[$name]['rows']++;
    }
    uasort($supplierSummary, static fn(array $left, array $right): int => $right['qty'] <=> $left['qty']);

    $movementByUser = [];
    foreach ($inRows as $row) {
        $name = trim((string) ($row['added_by'] ?? 'Unknown'));
        $movementByUser[$name] = $movementByUser[$name] ?? ['in' => 0, 'out' => 0, 'return' => 0];
        $movementByUser[$name]['in'] += (int) ($row['qty_in'] ?? 0);
    }
    foreach ($outRows as $row) {
        $name = trim((string) ($row['added_by'] ?? 'Unknown'));
        $movementByUser[$name] = $movementByUser[$name] ?? ['in' => 0, 'out' => 0, 'return' => 0];
        $movementByUser[$name]['out'] += (int) ($row['qty_out'] ?? 0);
    }
    foreach ($returnRows as $row) {
        $name = trim((string) ($row['returned_by'] ?? 'Unknown'));
        $movementByUser[$name] = $movementByUser[$name] ?? ['in' => 0, 'out' => 0, 'return' => 0];
        $movementByUser[$name]['return'] += (int) ($row['qty_returned'] ?? 0);
    }

    $sourceSummary = ['Regular' => 0, 'Outsourced' => 0];
    $stockHealth = ['Healthy' => 0, 'Low Stock' => 0, 'Out of Stock' => 0];
    $expirySummary = ['Healthy' => 0, 'Expiring Soon' => 0, 'Critical' => 0, 'Expired' => 0];
    foreach ($inventoryRows as $row) {
        $sourceSummary[$row['source_type']] += (int) ($row['qty'] ?? 0);
        $qty = (int) ($row['qty'] ?? 0);
        $threshold = low_stock_threshold_for_row($conn, $row);
        if ($qty <= 0) {
            $stockHealth['Out of Stock']++;
        } elseif ($qty <= $threshold) {
            $stockHealth['Low Stock']++;
        } else {
            $stockHealth['Healthy']++;
        }

        $bucket = expiry_bucket($conn, $row['exp_date'] ?? '');
        if ($bucket === 'expired') {
            $expirySummary['Expired']++;
        } elseif ($bucket === 'critical') {
            $expirySummary['Critical']++;
        } elseif ($bucket === 'soon') {
            $expirySummary['Expiring Soon']++;
        } else {
            $expirySummary['Healthy']++;
        }
    }

    return [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'inventory_rows' => $inventoryRows,
        'low_stock_rows' => array_values(array_filter($alerts, static fn(array $row): bool => in_array('Low stock', $row['issues'], true))),
        'expiring_rows' => array_values(array_filter($alerts, static fn(array $row): bool => in_array('Expiring soon', $row['issues'], true) || in_array('Critical expiry', $row['issues'], true))),
        'expired_rows' => array_values(array_filter($alerts, static fn(array $row): bool => in_array('Expired batch', $row['issues'], true))),
        'movement_summary' => [
            'in_qty' => (int) array_sum(array_column($inRows, 'qty_in')),
            'out_qty' => (int) array_sum(array_column($outRows, 'qty_out')),
            'return_qty' => (int) array_sum(array_column($returnRows, 'qty_returned')),
            'transaction_count' => count($inRows) + count($outRows) + count($returnRows),
        ],
        'top_outgoing' => array_slice($topOutgoing, 0, 10, true),
        'top_returned' => array_slice($topReturned, 0, 10, true),
        'supplier_summary' => array_slice($supplierSummary, 0, 10, true),
        'movement_by_user' => $movementByUser,
        'source_summary' => $sourceSummary,
        'stock_health' => $stockHealth,
        'expiry_summary' => $expirySummary,
        'chart_data' => [
            'movement_labels' => array_map(static fn(string $label): string => date('M d', strtotime($label)), $labels),
            'movement_in' => array_values($inByDay),
            'movement_out' => array_values($outByDay),
            'movement_return' => array_values($returnByDay),
            'source_labels' => array_keys($sourceSummary),
            'source_values' => array_values($sourceSummary),
            'stock_health_labels' => array_keys($stockHealth),
            'stock_health_values' => array_values($stockHealth),
            'expiry_labels' => array_keys($expirySummary),
            'expiry_values' => array_values($expirySummary),
            'top_moved_labels' => array_keys(array_slice($topOutgoing, 0, 8, true)),
            'top_moved_values' => array_values(array_slice($topOutgoing, 0, 8, true)),
        ],
    ];
}

function code39_patterns(): array
{
    return [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
    ];
}

function code39_svg(string $value, int $height = 70, int $narrow = 2, int $wide = 5): string
{
    $value = normalize_barcode_value($value);
    if ($value === '') {
        return '';
    }

    $patterns = code39_patterns();
    $encoded = '*' . $value . '*';
    $x = 10;
    $bars = [];

    for ($charIndex = 0; $charIndex < strlen($encoded); $charIndex++) {
        $char = $encoded[$charIndex];
        if (!isset($patterns[$char])) {
            continue;
        }

        $pattern = $patterns[$char];
        for ($i = 0; $i < strlen($pattern); $i++) {
            $width = $pattern[$i] === 'w' ? $wide : $narrow;
            if ($i % 2 === 0) {
                $bars[] = '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $height . '" fill="#102033"></rect>';
            }
            $x += $width;
        }
        $x += $narrow;
    }

    $width = $x + 10;
    return '<svg viewBox="0 0 ' . $width . ' ' . ($height + 24) . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . h($value) . '">'
        . implode('', $bars)
        . '<text x="' . ($width / 2) . '" y="' . ($height + 18) . '" text-anchor="middle" font-size="14" font-family="Inter, Arial, sans-serif" fill="#344861">' . h($value) . '</text>'
        . '</svg>';
}
