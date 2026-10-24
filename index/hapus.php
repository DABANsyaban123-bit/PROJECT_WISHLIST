
<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
auth_require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_verify_csrf($_POST["csrf_token"] ?? null)) {
    http_response_code(400);
    exit("Permintaan penghapusan tidak valid.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$id = filter_input(
    INPUT_POST,
    "id_barang",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare(
    $koneksi,
    "DELETE FROM barang WHERE id_barang = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: index.php");
exit;
?>
