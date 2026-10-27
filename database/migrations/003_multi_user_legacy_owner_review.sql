-- Phase 2: review legacy data and prepare an explicit ownership mapping.
-- This file is READ-ONLY. It does not assign an owner or update any row.
--
-- An account count of one does not prove that account owns all legacy data.
-- Confirm the owner of every item and every expense with the data owner before
-- preparing a separate reviewed backfill. Do not infer expense ownership only
-- from a linked item unless that rule has been explicitly confirmed.
--
-- If ownership is unclear, stop here. Do not run phase 004.

SELECT id AS candidate_user_id, username
FROM users
ORDER BY id;

SELECT id_barang, nama_barang, kategori, jenis, harga
FROM barang
ORDER BY id_barang;

SELECT
    p.id_pengeluaran,
    p.id_barang,
    b.nama_barang AS linked_item_name,
    p.nama_pengeluaran,
    p.jumlah,
    p.tanggal_pengeluaran,
    p.catatan
FROM pengeluaran AS p
LEFT JOIN barang AS b ON b.id_barang = p.id_barang
ORDER BY p.id_pengeluaran;

-- The reviewed mapping must identify one verified users.id for every
-- id_barang and every id_pengeluaran. Keep the mapping with the migration
-- record and validate it before generating any UPDATE statements.
