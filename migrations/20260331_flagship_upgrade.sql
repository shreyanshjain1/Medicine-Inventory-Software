START TRANSACTION;

ALTER TABLE users
    MODIFY role ENUM('manager', 'employee', 'admin', 'staff', 'viewer') NOT NULL DEFAULT 'staff',
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE users
SET role = 'staff'
WHERE role = 'employee' OR role = '';

SET @admin_count := (SELECT COUNT(*) FROM users WHERE role = 'admin');
UPDATE users
SET role = 'admin'
WHERE id = (
    SELECT seed_admin.id
    FROM (
        SELECT id
        FROM users
        WHERE role = 'manager'
        ORDER BY id ASC
        LIMIT 1
    ) AS seed_admin
) AND @admin_count = 0;

ALTER TABLE users
    MODIFY role ENUM('admin', 'manager', 'staff', 'viewer') NOT NULL DEFAULT 'staff';

CREATE TABLE products (
    id INT(11) NOT NULL AUTO_INCREMENT,
    generic_name VARCHAR(255) NOT NULL DEFAULT '',
    brand_name VARCHAR(255) NOT NULL DEFAULT '',
    dosage_strength VARCHAR(255) NOT NULL DEFAULT '',
    manufacturer VARCHAR(255) NOT NULL DEFAULT '',
    registration_no VARCHAR(255) NOT NULL DEFAULT '',
    default_low_stock_threshold INT(11) NOT NULL DEFAULT 10,
    product_type VARCHAR(100) NOT NULL DEFAULT 'medicine',
    product_status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    barcode_value VARCHAR(120) DEFAULT NULL,
    created_by_id INT(11) DEFAULT NULL,
    created_by VARCHAR(100) DEFAULT NULL,
    updated_by_id INT(11) DEFAULT NULL,
    updated_by VARCHAR(100) DEFAULT NULL,
    archived_by_id INT(11) DEFAULT NULL,
    archived_by VARCHAR(100) DEFAULT NULL,
    archived_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_master (generic_name, brand_name, dosage_strength, manufacturer, registration_no),
    KEY idx_products_status (product_status),
    KEY idx_products_barcode (barcode_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO products
    (generic_name, brand_name, dosage_strength, manufacturer, registration_no, default_low_stock_threshold, product_type, product_status, created_by, updated_by, created_at, updated_at)
SELECT DISTINCT
    COALESCE(generic_name, ''),
    COALESCE(brand_name, ''),
    COALESCE(dosage_strength, ''),
    COALESCE(manufacturer, ''),
    COALESCE(registration_no, ''),
    10,
    'medicine',
    'active',
    'System Migration',
    'System Migration',
    NOW(),
    NOW()
FROM inventory
UNION
SELECT DISTINCT
    COALESCE(generic_name, ''),
    COALESCE(brand_name, ''),
    COALESCE(dosage_strength, ''),
    COALESCE(manufacturer, ''),
    COALESCE(registration_no, ''),
    10,
    'medicine',
    'active',
    'System Migration',
    'System Migration',
    NOW(),
    NOW()
FROM inventory_outsourced;

ALTER TABLE inventory
    ADD COLUMN product_id INT(11) DEFAULT NULL AFTER id,
    ADD COLUMN low_stock_threshold INT(11) DEFAULT NULL AFTER qty_returned,
    ADD COLUMN record_status ENUM('active', 'archived', 'voided') NOT NULL DEFAULT 'active' AFTER low_stock_threshold,
    ADD COLUMN void_reason TEXT DEFAULT NULL AFTER record_status,
    ADD COLUMN voided_by VARCHAR(100) DEFAULT NULL AFTER void_reason,
    ADD COLUMN voided_by_id INT(11) DEFAULT NULL AFTER voided_by,
    ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER voided_by_id,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER voided_at,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD KEY idx_inventory_product (product_id),
    ADD KEY idx_inventory_status (record_status),
    ADD CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;

ALTER TABLE inventory_outsourced
    ADD COLUMN product_id INT(11) DEFAULT NULL AFTER id,
    ADD COLUMN low_stock_threshold INT(11) DEFAULT NULL AFTER qty_returned,
    ADD COLUMN record_status ENUM('active', 'archived', 'voided') NOT NULL DEFAULT 'active' AFTER low_stock_threshold,
    ADD COLUMN void_reason TEXT DEFAULT NULL AFTER record_status,
    ADD COLUMN voided_by VARCHAR(100) DEFAULT NULL AFTER void_reason,
    ADD COLUMN voided_by_id INT(11) DEFAULT NULL AFTER voided_by,
    ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER voided_by_id,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD KEY idx_inventory_outsourced_product (product_id),
    ADD KEY idx_inventory_outsourced_status (record_status),
    ADD CONSTRAINT fk_inventory_outsourced_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;

UPDATE inventory i
INNER JOIN products p
    ON p.generic_name = COALESCE(i.generic_name, '')
    AND p.brand_name = COALESCE(i.brand_name, '')
    AND p.dosage_strength = COALESCE(i.dosage_strength, '')
    AND p.manufacturer = COALESCE(i.manufacturer, '')
    AND p.registration_no = COALESCE(i.registration_no, '')
SET i.product_id = p.id;

UPDATE inventory_outsourced o
INNER JOIN products p
    ON p.generic_name = COALESCE(o.generic_name, '')
    AND p.brand_name = COALESCE(o.brand_name, '')
    AND p.dosage_strength = COALESCE(o.dosage_strength, '')
    AND p.manufacturer = COALESCE(o.manufacturer, '')
    AND p.registration_no = COALESCE(o.registration_no, '')
SET o.product_id = p.id;

ALTER TABLE in_log
    MODIFY generic_name VARCHAR(255) DEFAULT NULL,
    MODIFY brand_name VARCHAR(255) DEFAULT NULL,
    MODIFY dosage_strength VARCHAR(255) DEFAULT NULL,
    MODIFY mfg_date VARCHAR(7) DEFAULT NULL,
    MODIFY exp_date VARCHAR(7) DEFAULT NULL,
    ADD COLUMN inventory_table VARCHAR(50) DEFAULT NULL AFTER batch_no,
    ADD COLUMN inventory_ref_id INT(11) DEFAULT NULL AFTER inventory_table,
    ADD COLUMN product_id INT(11) DEFAULT NULL AFTER inventory_ref_id,
    ADD COLUMN source_type ENUM('regular', 'outsourced') NOT NULL DEFAULT 'regular' AFTER qty_in,
    ADD COLUMN record_status ENUM('active', 'voided') NOT NULL DEFAULT 'active' AFTER added_at,
    ADD COLUMN void_reason TEXT DEFAULT NULL AFTER record_status,
    ADD COLUMN voided_by VARCHAR(100) DEFAULT NULL AFTER void_reason,
    ADD COLUMN voided_by_id INT(11) DEFAULT NULL AFTER voided_by,
    ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER voided_by_id,
    ADD KEY idx_in_log_inventory_ref (inventory_table, inventory_ref_id),
    ADD KEY idx_in_log_product (product_id);

UPDATE in_log l
LEFT JOIN inventory i ON i.batch_no = l.batch_no
SET l.generic_name = COALESCE(l.generic_name, i.generic_name),
    l.brand_name = COALESCE(l.brand_name, i.brand_name),
    l.dosage_strength = COALESCE(l.dosage_strength, i.dosage_strength),
    l.mfg_date = COALESCE(l.mfg_date, i.mfg_date),
    l.exp_date = COALESCE(l.exp_date, i.exp_date),
    l.manufacturer = COALESCE(l.manufacturer, i.manufacturer),
    l.registration_no = COALESCE(l.registration_no, i.registration_no),
    l.qty_in = COALESCE(l.qty_in, i.qty_in),
    l.inventory_table = COALESCE(l.inventory_table, 'inventory'),
    l.inventory_ref_id = COALESCE(l.inventory_ref_id, i.id),
    l.product_id = COALESCE(l.product_id, i.product_id),
    l.source_type = 'regular'
WHERE i.id IS NOT NULL;

UPDATE in_log l
LEFT JOIN inventory_outsourced o ON o.batch_no = l.batch_no
SET l.generic_name = COALESCE(l.generic_name, o.generic_name),
    l.brand_name = COALESCE(l.brand_name, o.brand_name),
    l.dosage_strength = COALESCE(l.dosage_strength, o.dosage_strength),
    l.mfg_date = COALESCE(l.mfg_date, o.mfg_date),
    l.exp_date = COALESCE(l.exp_date, o.exp_date),
    l.manufacturer = COALESCE(l.manufacturer, o.manufacturer),
    l.registration_no = COALESCE(l.registration_no, o.registration_no),
    l.qty_in = COALESCE(l.qty_in, o.qty_in),
    l.inventory_table = COALESCE(l.inventory_table, 'inventory_outsourced'),
    l.inventory_ref_id = COALESCE(l.inventory_ref_id, o.id),
    l.product_id = COALESCE(l.product_id, o.product_id),
    l.source_type = 'outsourced'
WHERE o.id IS NOT NULL AND (l.inventory_ref_id IS NULL OR l.inventory_table IS NULL);

INSERT INTO in_log
    (generic_name, brand_name, dosage_strength, batch_no, inventory_table, inventory_ref_id, product_id, mfg_date, exp_date, manufacturer, registration_no, qty_in, source_type, added_by, added_at, record_status)
SELECT
    i.generic_name,
    i.brand_name,
    i.dosage_strength,
    i.batch_no,
    'inventory',
    i.id,
    i.product_id,
    i.mfg_date,
    i.exp_date,
    i.manufacturer,
    i.registration_no,
    i.qty_in,
    'regular',
    'System Migration',
    i.created_at,
    'active'
FROM inventory i
LEFT JOIN in_log l ON l.inventory_table = 'inventory' AND l.inventory_ref_id = i.id
WHERE l.id IS NULL;

INSERT INTO in_log
    (generic_name, brand_name, dosage_strength, batch_no, inventory_table, inventory_ref_id, product_id, mfg_date, exp_date, manufacturer, registration_no, qty_in, source_type, added_by, added_at, record_status)
SELECT
    o.generic_name,
    o.brand_name,
    o.dosage_strength,
    o.batch_no,
    'inventory_outsourced',
    o.id,
    o.product_id,
    o.mfg_date,
    o.exp_date,
    o.manufacturer,
    o.registration_no,
    o.qty_in,
    'outsourced',
    'System Migration',
    o.created_at,
    'active'
FROM inventory_outsourced o
LEFT JOIN in_log l ON l.inventory_table = 'inventory_outsourced' AND l.inventory_ref_id = o.id
WHERE l.id IS NULL;

ALTER TABLE out_records
    MODIFY document_type VARCHAR(50) NOT NULL,
    MODIFY return_status VARCHAR(50) NOT NULL DEFAULT 'Delivered',
    ADD COLUMN record_status ENUM('active', 'voided') NOT NULL DEFAULT 'active' AFTER added_by,
    ADD COLUMN void_reason TEXT DEFAULT NULL AFTER record_status,
    ADD COLUMN voided_by VARCHAR(100) DEFAULT NULL AFTER void_reason,
    ADD COLUMN voided_by_id INT(11) DEFAULT NULL AFTER voided_by,
    ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER voided_by_id,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER voided_at,
    ADD KEY idx_out_records_status (record_status);

UPDATE out_records
SET return_status = CASE
    WHEN qty_returned >= qty_out AND qty_out > 0 THEN 'Return full'
    WHEN qty_returned > 0 THEN 'Return partial'
    ELSE 'Delivered'
END;

ALTER TABLE return_binded_records
    ADD COLUMN record_status ENUM('active', 'voided') NOT NULL DEFAULT 'active' AFTER created_at,
    ADD COLUMN void_reason TEXT DEFAULT NULL AFTER record_status,
    ADD COLUMN voided_by VARCHAR(100) DEFAULT NULL AFTER void_reason,
    ADD COLUMN voided_by_id INT(11) DEFAULT NULL AFTER voided_by,
    ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER voided_by_id,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER voided_at,
    ADD KEY idx_return_records_status (record_status);

CREATE TABLE correction_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    actor_id INT(11) DEFAULT NULL,
    actor_name VARCHAR(100) DEFAULT NULL,
    actor_role VARCHAR(50) DEFAULT NULL,
    record_type VARCHAR(100) NOT NULL,
    record_id INT(11) NOT NULL,
    reason TEXT NOT NULL,
    old_values LONGTEXT DEFAULT NULL,
    new_values LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_correction_record (record_type, record_id),
    KEY idx_correction_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE audit_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    actor_id INT(11) DEFAULT NULL,
    actor_name VARCHAR(100) DEFAULT NULL,
    actor_role VARCHAR(50) DEFAULT NULL,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT(11) NOT NULL,
    summary VARCHAR(255) NOT NULL,
    reason TEXT DEFAULT NULL,
    old_values LONGTEXT DEFAULT NULL,
    new_values LONGTEXT DEFAULT NULL,
    metadata LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_action (action_type),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE notes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    target_type VARCHAR(100) NOT NULL,
    target_id INT(11) NOT NULL,
    note_text TEXT NOT NULL,
    created_by_id INT(11) DEFAULT NULL,
    created_by VARCHAR(100) DEFAULT NULL,
    updated_by_id INT(11) DEFAULT NULL,
    updated_by VARCHAR(100) DEFAULT NULL,
    record_status ENUM('active', 'deleted') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notes_target (target_type, target_id),
    KEY idx_notes_status (record_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    notification_key VARCHAR(190) DEFAULT NULL,
    user_id INT(11) DEFAULT NULL,
    role_scope VARCHAR(50) NOT NULL DEFAULT 'all',
    notification_type VARCHAR(100) NOT NULL DEFAULT 'system',
    severity VARCHAR(30) NOT NULL DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id INT(11) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_key (notification_key),
    KEY idx_notifications_user (user_id),
    KEY idx_notifications_read (is_read),
    KEY idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE app_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO app_settings (setting_key, setting_value) VALUES
    ('low_stock_default', '10'),
    ('expiring_soon_months', '6'),
    ('notifications_low_stock', '1'),
    ('notifications_expiring_soon', '1'),
    ('notifications_expired', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

COMMIT;
