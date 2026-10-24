
<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
auth_require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_verify_csrf($_POST["csrf_token"] ?? null)) {
    http_response_code(400);
    exit("Permintaan penghapusan tidak valid.");
}

$id = filter_input(INPUT_POST, "id_pengeluaran", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("ID pengeluaran tidak valid.");
}

$stmt = mysqli_prepare(
    $koneksi,
    "DELETE FROM pengeluaran WHERE id_pengeluaran = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: pengeluaran.php");
    exit;
}

mysqli_stmt_close($stmt);
exit("Gagal menghapus pengeluaran.");
?>
