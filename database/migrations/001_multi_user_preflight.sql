-- Wishlist PJK: read-only preflight for multi-user ownership migration.
-- Select the intended database before running this file.
-- Stop if a table/column is missing, the schema differs from the application
-- contract, or the storage engines are not InnoDB.

SELECT DATABASE() AS selected_database;
SELECT VERSION() AS server_version;

SHOW TABLES;

SELECT
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'barang', 'pengeluaran')
ORDER BY TABLE_NAME;

SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_KEY,
    EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'barang', 'pengeluaran')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    SEQ_IN_INDEX,
    COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'barang', 'pengeluaran')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'barang', 'pengeluaran')
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;

SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    UPDATE_RULE,
    DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'barang', 'pengeluaran')
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- These statements show the exact persisted DDL; compare it with application
-- assumptions before proceeding.
SHOW CREATE TABLE users;
SHOW CREATE TABLE barang;
SHOW CREATE TABLE pengeluaran;

-- Record the existing row counts and the number of legacy accounts.
SELECT 'users' AS table_name, COUNT(*) AS row_count FROM users
UNION ALL
SELECT 'barang', COUNT(*) FROM barang
UNION ALL
SELECT 'pengeluaran', COUNT(*) FROM pengeluaran;

-- Existing expense-to-item links must resolve before migration.
SELECT COUNT(*) AS orphaned_expenses
FROM pengeluaran AS p
LEFT JOIN barang AS b ON b.id_barang = p.id_barang
WHERE p.id_barang IS NOT NULL
  AND b.id_barang IS NULL;

-- Flag duplicated expense-to-item foreign keys for review.
SELECT
    COUNT(*) AS expense_to_item_foreign_key_count,
    GROUP_CONCAT(CONSTRAINT_NAME ORDER BY CONSTRAINT_NAME) AS constraint_names
FROM information_schema.KEY_COLUMN_USAGE
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pengeluaran'
  AND COLUMN_NAME = 'id_barang'
  AND REFERENCED_TABLE_NAME = 'barang'
  AND REFERENCED_COLUMN_NAME = 'id_barang';

-- Confirm id_barang's actual type and key/index status above. The composite
-- ownership foreign key in phase 004 requires these column types to match.
