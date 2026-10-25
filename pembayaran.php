<?php

function pembayaran_total_sql(string $barang_alias = "b"): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $barang_alias)) {
        throw new InvalidArgumentException("Alias barang tidak valid.");
    }

    return "COALESCE((
        SELECT SUM(p.jumlah)
        FROM pengeluaran p
        WHERE p.id_barang = {$barang_alias}.id_barang
    ), 0)";
}

function pembayaran_sinkronkan_status(mysqli $koneksi, int $id_barang): void
{
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT b.harga, COALESCE(SUM(p.jumlah), 0) AS total_bayar
         FROM barang b
         LEFT JOIN pengeluaran p ON p.id_barang = b.id_barang
         WHERE b.id_barang = ?
         GROUP BY b.id_barang, b.harga"
    );
    mysqli_stmt_bind_param($stmt, "i", $id_barang);
    mysqli_stmt_execute($stmt);
    $barang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$barang) {
        return;
    }

    $status = (float) $barang["total_bayar"] >= (float) $barang["harga"]
        ? "Sudah Dibeli"
        : "Belum Dibeli";
    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE barang SET status = ? WHERE id_barang = ?"
    );
    mysqli_stmt_bind_param($stmt, "si", $status, $id_barang);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
