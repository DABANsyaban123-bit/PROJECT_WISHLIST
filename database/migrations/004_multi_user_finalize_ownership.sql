-- Phase 3: enforce ownership and prevent cross-account expense/item links.
-- Do not run until ALL conditions below are verified:
--   1. A separate, explicitly approved backfill has assigned every existing
--      row according to the verified mapping reviewed in phase 2.
--   2. Independent verification shows zero unowned rows and zero unknown
--      owners.
--   3. A verified backup exists and application writes are paused.
--   4. Updated application code writes the authenticated user_id on every
--      barang and pengeluaran insert, including unlinked expenses.
--   5. users.id is INT UNSIGNED and all three tables use InnoDB.
--   6. barang.id_barang is a primary/unique key, and pengeluaran.id_barang
--      has exactly the same type and signedness as barang.id_barang.
--   7. The two existing foreign keys on pengeluaran.id_barang have the
--      verified names below. Re-check SHOW CREATE TABLE immediately before
--      running; stop and adapt the DROP clauses if the names differ.
--
-- This migration intentionally removes both duplicate single-column
-- pengeluaran.id_barang foreign keys and replaces them with a composite
-- (id_barang, user_id) foreign key. Their ON DELETE SET NULL behavior is
-- intentionally replaced by ON DELETE RESTRICT. This retains linked expense
-- records and prevents a linked item from being deleted until its expenses
-- are explicitly unlinked or otherwise handled. The existing application
-- already refuses to delete items with expense history.
--
-- Supporting indexes from the old foreign keys are not dropped; review and
-- clean up redundant indexes only in a separately approved operation.
--
-- MySQL DDL can implicitly commit. Run and verify this phase as a planned
-- maintenance operation; it is not an atomic transaction.

ALTER TABLE barang
    MODIFY COLUMN user_id INT UNSIGNED NOT NULL,
    ADD INDEX idx_barang_user_id (user_id),
    ADD UNIQUE INDEX uq_barang_id_user_id (id_barang, user_id),
    ADD CONSTRAINT fk_barang_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT;

ALTER TABLE pengeluaran
    MODIFY COLUMN user_id INT UNSIGNED NOT NULL,
    DROP FOREIGN KEY fk_pengeluaran_barang,
    DROP FOREIGN KEY pengeluaran_ibfk_1,
    ADD INDEX idx_pengeluaran_user_id (user_id),
    ADD INDEX idx_pengeluaran_id_barang_user_id (id_barang, user_id),
    ADD CONSTRAINT fk_pengeluaran_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT,
    ADD CONSTRAINT fk_pengeluaran_barang_owner
    FOREIGN KEY (id_barang, user_id)
    REFERENCES barang (id_barang, user_id)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT;

-- Post-migration verification.
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'barang', 'pengeluaran')
  AND COLUMN_NAME IN ('id', 'id_barang', 'user_id')
ORDER BY TABLE_NAME, COLUMN_NAME;

SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('barang', 'pengeluaran')
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;

SELECT 'barang' AS table_name, COUNT(*) AS unowned_rows
FROM barang
WHERE user_id IS NULL
UNION ALL
SELECT 'pengeluaran', COUNT(*)
FROM pengeluaran
WHERE user_id IS NULL;

SELECT COUNT(*) AS cross_account_expense_links
FROM pengeluaran AS p
INNER JOIN barang AS b ON b.id_barang = p.id_barang
WHERE p.id_barang IS NOT NULL
  AND p.user_id <> b.user_id;
