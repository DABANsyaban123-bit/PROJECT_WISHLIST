
<?php
include_once __DIR__ . "/../config.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: daftar.php");
    exit;
}

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM barang WHERE id_barang = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$barang = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$barang) {
    header("Location: daftar.php");
    exit;
}

$pesan = "";

$daftar_kategori = [
    "Elektronik",
    "Pakaian",
    "Buku",
    "Aksesoris",
    "Kebutuhan Sekolah",
    "Lainnya"
];

if (!in_array($barang["kategori"], $daftar_kategori, true)) {
    $daftar_kategori[] = $barang["kategori"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_barang = trim($_POST["nama_barang"] ?? "");
    $kategori = $_POST["kategori"] ?? "";
    $jenis = $_POST["jenis"] ?? "";
    $harga = $_POST["harga"] ?? "";
    $status = $_POST["status"] ?? "";

    $jenis_valid = ["Kebutuhan", "Keinginan"];
    $status_valid = ["Belum Dibeli", "Sudah Dibeli"];

    if (
        $nama_barang === "" ||
        !in_array($kategori, $daftar_kategori, true) ||
        !in_array($jenis, $jenis_valid, true) ||
        $harga === "" ||
        !is_numeric($harga) ||
        !is_finite((float) $harga) ||
        (float) $harga < 0 ||
        !in_array($status, $status_valid, true)
    ) {
        $pesan = "Periksa kembali data yang kamu masukkan.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE barang
             SET nama_barang = ?,
                 kategori = ?,
                 jenis = ?,
                 harga = ?,
                 status = ?
             WHERE id_barang = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sssdsi",
            $nama_barang,
            $kategori,
            $jenis,
            $harga,
            $status,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: daftar.php");
            exit;
        }

        $pesan = "Barang gagal diperbarui.";
        mysqli_stmt_close($stmt);
    }

    $barang["nama_barang"] = $nama_barang;
    $barang["kategori"] = $kategori;
    $barang["jenis"] = $jenis;
    $barang["harga"] = $harga;
    $barang["status"] = $status;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Barang - Wishlist PJK</title>

    <link rel="stylesheet" href="../STYLES.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">

            <div class="card border-0 shadow rounded-4">
                <div class="card-body p-4 p-md-5">

                    <h2 class="fw-bold mb-2">Edit Barang</h2>
                    <p class="text-secondary mb-4">
                        Perbarui informasi barang wishlist-mu.
                    </p>

                    <?php if ($pesan !== ""): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($pesan, ENT_QUOTES, "UTF-8") ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">
                            <label for="nama_barang" class="form-label">
                                Nama Barang
                            </label>
                            <input
                                type="text"
                                id="nama_barang"
                                name="nama_barang"
                                class="form-control"
                                maxlength="255"
                                value="<?= htmlspecialchars($barang["nama_barang"], ENT_QUOTES, "UTF-8") ?>"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="kategori" class="form-label">
                                Kategori
                            </label>
                            <select id="kategori" name="kategori"
                                    class="form-select" required>
                                <?php foreach ($daftar_kategori as $item): ?>
                                    <option
                                        value="<?= htmlspecialchars($item, ENT_QUOTES, "UTF-8") ?>"
                                        <?= $barang["kategori"] === $item ? "selected" : "" ?>>
                                        <?= htmlspecialchars($item, ENT_QUOTES, "UTF-8") ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="jenis" class="form-label">
                                Jenis Barang
                            </label>
                            <select id="jenis" name="jenis"
                                    class="form-select" required>
                                <option value="Kebutuhan"
                                    <?= $barang["jenis"] === "Kebutuhan" ? "selected" : "" ?>>
                                    Kebutuhan
                                </option>

                                <option value="Keinginan"
                                    <?= $barang["jenis"] === "Keinginan" ? "selected" : "" ?>>
                                    Keinginan
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="harga" class="form-label">
                                Harga (Rp)
                            </label>
                            <input
                                type="number"
                                id="harga"
                                name="harga"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars((string) $barang["harga"], ENT_QUOTES, "UTF-8") ?>"
                                required>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">
                                Status
                            </label>
                            <select id="status" name="status"
                                    class="form-select" required>
                                <option value="Belum Dibeli"
                                    <?= $barang["status"] === "Belum Dibeli" ? "selected" : "" ?>>
                                    Belum Dibeli
                                </option>

                                <option value="Sudah Dibeli"
                                    <?= $barang["status"] === "Sudah Dibeli" ? "selected" : "" ?>>
                                    Sudah Dibeli
                                </option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit"
                                    class="btn btn-primary flex-fill">
                                Simpan Perubahan
                            </button>

                            <a href="daftar.php"
                               class="btn btn-outline-secondary">
                                Kembali
                            </a>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
