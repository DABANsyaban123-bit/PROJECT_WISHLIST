
<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
auth_require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_verify_csrf($_POST["csrf_token"] ?? null)) {
    http_response_code(400);
    exit("Permintaan penghapusan tidak valid.");
}

$id = filter_input(
    INPUT_POST,
    "id_barang",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header("Location: daftar.php");
    exit;
}

try {
    mysqli_begin_transaction($koneksi);

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_barang FROM barang WHERE id_barang = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $barang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$barang) {
        mysqli_commit($koneksi);
        header("Location: daftar.php");
        exit;
    }

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_pengeluaran FROM pengeluaran WHERE id_barang = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $memiliki_riwayat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) !== null;
    mysqli_stmt_close($stmt);

    if ($memiliki_riwayat) {
        mysqli_rollback($koneksi);
        header("Location: daftar.php?hapus_tertaut=1");
        exit;
    }

    $stmt = mysqli_prepare($koneksi, "DELETE FROM barang WHERE id_barang = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_commit($koneksi);
    header("Location: daftar.php");
    exit;
} catch (mysqli_sql_exception $exception) {
    mysqli_rollback($koneksi);
    error_log("Wishlist PJK item deletion failed: " . $exception->getMessage());
    http_response_code(500);
    exit("Barang gagal dihapus. Riwayat pembayaran tetap dipertahankan.");
}
?>
