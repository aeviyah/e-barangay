-- Non-destructive, repeatable migration for the merged E-Barangay schema.
-- Back up the database first. This migration never drops or recreates app tables.
-- It targets the canonical database name configured in config/database.php.
USE ebarangay_management;

DROP PROCEDURE IF EXISTS ebarangay_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE ebarangay_add_column_if_missing(
    IN table_name_arg VARCHAR(64),
    IN column_name_arg VARCHAR(64),
    IN definition_arg TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = table_name_arg
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = table_name_arg
          AND column_name = column_name_arg
    ) THEN
        SET @migration_sql = CONCAT(
            'ALTER TABLE `', REPLACE(table_name_arg, '`', '``'), '` ADD COLUMN `',
            REPLACE(column_name_arg, '`', '``'), '` ', definition_arg
        );
        PREPARE migration_stmt FROM @migration_sql;
        EXECUTE migration_stmt;
        DEALLOCATE PREPARE migration_stmt;
    END IF;
END$$
DELIMITER ;

-- Resident classifications and resident record history fields.
CALL ebarangay_add_column_if_missing('residents', 'is_ofw', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL ebarangay_add_column_if_missing('residents', 'is_other', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL ebarangay_add_column_if_missing('residents', 'is_youth', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL ebarangay_add_column_if_missing('residents', 'is_head_of_family', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL ebarangay_add_column_if_missing('residents', 'pwd_id_number', 'VARCHAR(80) NULL');
CALL ebarangay_add_column_if_missing('residents', 'solo_parent_id_number', 'VARCHAR(80) NULL');
CALL ebarangay_add_column_if_missing('residents', 'special_classification', 'VARCHAR(150) NULL');
CALL ebarangay_add_column_if_missing('residents', 'status_notes', 'TEXT NULL');
CALL ebarangay_add_column_if_missing('residents', 'status_changed_at', 'DATETIME NULL');

-- Soft-delete fields retain request and payment rows for restore/undo.
CALL ebarangay_add_column_if_missing('service_records', 'deleted_at', 'DATETIME NULL');
CALL ebarangay_add_column_if_missing('service_records', 'deleted_by', 'INT NULL');
CALL ebarangay_add_column_if_missing('service_records', 'deleted_status', 'VARCHAR(80) NULL');
CALL ebarangay_add_column_if_missing('payments', 'deleted_at', 'DATETIME NULL');
CALL ebarangay_add_column_if_missing('payments', 'deleted_by', 'INT NULL');

DROP PROCEDURE IF EXISTS ebarangay_add_column_if_missing;

CREATE TABLE IF NOT EXISTS public_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(150) NOT NULL,
    contact_type VARCHAR(60) NOT NULL DEFAULT 'Office',
    value VARCHAR(255) NOT NULL,
    availability VARCHAR(150),
    status VARCHAR(30) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL
);

-- Insert demo contact rows only when that contact label is absent.
INSERT INTO public_contacts (label, contact_type, value, availability, status, created_at)
SELECT seed.label, seed.contact_type, seed.value, seed.availability, seed.status, NOW()
FROM (
    SELECT 'Barangay Hall' AS label, 'Phone' AS contact_type, '0917-000-0000' AS value, 'Mon-Fri, 8:00 AM - 5:00 PM' AS availability, 'Active' AS status
    UNION ALL SELECT 'Tanod Desk', 'Phone', '0917-111-1111', '24/7', 'Active'
    UNION ALL SELECT 'Barangay Email', 'Email', 'info@barangay.test', 'Replies within office hours', 'Active'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM public_contacts existing WHERE existing.label = seed.label
);
