<?php

function nominal_normalisasi(mixed $nilai): ?float
{
    if (!is_string($nilai) && !is_numeric($nilai)) {
        return null;
    }

    $nilai = trim((string) $nilai);
    $nilai = preg_replace('/^(?:Rp\.?\s*)/i', '', $nilai);
    $nilai = str_replace(' ', '', $nilai);

    if ($nilai === '') {
        return null;
    }

    if (str_contains($nilai, ',')) {
        if (!preg_match('/^\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?$|^\d+(?:,\d{1,2})?$/', $nilai)) {
            return null;
        }

        $nilai = str_replace('.', '', $nilai);
        $nilai = str_replace(',', '.', $nilai);
    } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $nilai)) {
        $nilai = str_replace('.', '', $nilai);
    } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $nilai)) {
        return null;
    }

    $jumlah = (float) $nilai;
    return is_finite($jumlah) ? $jumlah : null;
}

function nominal_format_input(mixed $nilai): string
{
    $jumlah = nominal_normalisasi($nilai);

    if ($jumlah === null) {
        return '';
    }

    $desimal = (int) round(($jumlah - floor($jumlah)) * 100);
    return number_format($jumlah, $desimal > 0 ? 2 : 0, ',', '.');
}

function pembayaran_total_sql(mysqli $koneksi, string $barang_alias = "b"): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $barang_alias)) {
        throw new InvalidArgumentException("Alias barang tidak valid.");
    }

    return "COALESCE((
        SELECT SUM(p.jumlah)
        FROM pengeluaran p
        WHERE p.id_barang = {$barang_alias}.id_barang
          AND " . auth_user_scope($koneksi, "p") . "
    ), 0)";
}

function pembayaran_sinkronkan_status(mysqli $koneksi, int $id_barang): void
{
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT b.harga, COALESCE(SUM(p.jumlah), 0) AS total_bayar
         FROM barang b
         LEFT JOIN pengeluaran p
           ON p.id_barang = b.id_barang
          AND " . auth_user_scope($koneksi, "p") . "
         WHERE b.id_barang = ?
           AND " . auth_user_scope($koneksi, "b") . "
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
        "UPDATE barang
         SET status = ?
         WHERE id_barang = ?
           AND " . auth_user_scope($koneksi, "barang")
    );
    mysqli_stmt_bind_param($stmt, "si", $status, $id_barang);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
