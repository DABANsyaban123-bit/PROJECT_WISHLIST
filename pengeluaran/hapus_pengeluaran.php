
<?php
include_once __DIR__ . "/../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: pengeluaran.php");
    exit;
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
