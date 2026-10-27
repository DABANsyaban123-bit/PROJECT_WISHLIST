# Multi-user migration runbook

These files are a staged migration plan, not an installer. Do not run any
schema-changing statement until the data owner has approved the mapping and a
verified backup is available.

## Required sequence

1. Stop application writes and make a timestamped full backup of `wishlist_db`
   outside the project directory. Verify that the backup file is non-empty and
   restore it into a separate temporary database before relying on it.
2. Run `001_multi_user_preflight.sql` as read-only inspection. Compare the
   complete table definitions, indexes, engines, column types, foreign keys,
   row counts, and orphan count with the live database. Stop if anything
   differs from the assumptions documented in the SQL files.
3. Review every row returned by `003_multi_user_legacy_owner_review.sql` with
   the data owner. Prepare and independently review an explicit mapping for
   every legacy `barang.id_barang` and `pengeluaran.id_pengeluaran`.
   Relationship to an item is not proof of expense ownership. If any row is
   uncertain, stop; do not generate or run a blanket backfill.
4. Only after explicit approval, apply `002_multi_user_add_nullable_owner.sql`.
   MySQL DDL may commit each `ALTER TABLE` independently. Verify both columns
   before proceeding; keep application writes paused.
5. Apply only the separately reviewed backfill statements based on the
   confirmed mapping. This repository intentionally does not contain a
   guessed backfill. Verify that every owner exists and that every linked
   expense and item has the same owner.
6. Re-run the relevant read-only checks from phases 001 and 003. Continue only
   when there are zero unowned rows, zero unknown owners, zero orphaned
   expenses, and zero cross-account expense links.
7. Review the live `SHOW CREATE TABLE` output immediately before applying
   `004_multi_user_finalize_ownership.sql`. It expects the two single-column
   `pengeluaran.id_barang` foreign keys named `fk_pengeluaran_barang` and
   `pengeluaran_ibfk_1`. Stop and adapt the reviewed migration if names or
   definitions differ.
8. Phase 004 intentionally replaces both `ON DELETE SET NULL` foreign keys
   with one composite ownership foreign key using `ON DELETE RESTRICT`. This
   prevents deleting a wishlist item that still has linked transaction
   history. Do not apply it unless that behavior has been approved.
9. Run and review every post-migration check in phase 004, including the
   cross-account link count. Resume application writes only after all checks
   pass and the deployed PHP code is verified.

## Recovery

If a migration fails, keep writes stopped and inspect the actual schema before
retrying; never assume a failed MySQL DDL statement rolled back. Preserve the
original backup. To validate recovery, restore the backup into a separate,
empty temporary database first. Do not import a backup over the live database
until the recovery plan has been reviewed and explicitly approved.
