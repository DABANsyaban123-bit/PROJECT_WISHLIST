
<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../pembayaran.php";
$user_id = auth_require_login($koneksi);

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_verify_csrf($_POST["csrf_token"] ?? null)) {
    http_response_code(400);
    exit("Permintaan penghapusan tidak valid.");
}

$id = filter_input(INPUT_POST, "id_pengeluaran", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("ID pengeluaran tidak valid.");
}

try {
    mysqli_begin_transaction($koneksi);

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_barang FROM pengeluaran
         WHERE id_pengeluaran = ?
           AND " . auth_user_scope($koneksi, "pengeluaran") . "
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $pengeluaran = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$pengeluaran) {
        mysqli_commit($koneksi);
        header("Location: pengeluaran.php");
        exit;
    }

    $id_barang = $pengeluaran["id_barang"] !== null
        ? (int) $pengeluaran["id_barang"]
        : null;

    if ($id_barang !== null) {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id_barang FROM barang
             WHERE id_barang = ?
               AND " . auth_user_scope($koneksi, "barang") . "
             FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, "i", $id_barang);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $stmt = mysqli_prepare(
        $koneksi,
        "DELETE FROM pengeluaran
         WHERE id_pengeluaran = ?
           AND " . auth_user_scope($koneksi, "pengeluaran")
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($id_barang !== null) {
        pembayaran_sinkronkan_status($koneksi, $id_barang);
    }

    mysqli_commit($koneksi);
    header("Location: pengeluaran.php");
    exit;
} catch (mysqli_sql_exception $exception) {
    mysqli_rollback($koneksi);
    error_log("Wishlist PJK expense deletion failed: " . $exception->getMessage());
    http_response_code(500);
    exit("Pengeluaran gagal dihapus. Silakan coba lagi.");
}
?>
