-- Phase 1: add nullable ownership columns without changing or deleting rows.
-- Preconditions:
--   * A verified backup exists.
--   * 001_multi_user_preflight.sql was reviewed against this database.
--   * Application writes are paused until ownership backfill is complete.
--   * Neither table already has a user_id column.
--   * The existing rows remain untouched; do not infer their owner from the
--     number of accounts.
--
-- Run only this phase, then verify both columns with information_schema.
-- MySQL DDL can commit each ALTER independently. If only one succeeds, inspect
-- both tables and finish the missing ALTER manually before continuing.

ALTER TABLE barang
    ADD COLUMN user_id INT UNSIGNED NULL;

ALTER TABLE pengeluaran
    ADD COLUMN user_id INT UNSIGNED NULL;
