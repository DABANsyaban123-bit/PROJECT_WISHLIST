
<?php
include "config.php";

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
