
<?php
include_once __DIR__ . "/../config.php";

$pesan = "";
$nama_barang = "";
$kategori = "";
$jenis = "Keinginan";
$harga = "";
$status = "Belum Dibeli";

$daftar_kategori = [
    "Elektronik",
    "Pakaian",
    "Buku",
    "Aksesoris",
    "Kebutuhan Sekolah",
    "Lainnya"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_barang = trim($_POST["nama_barang"] ?? "");
    $kategori = trim($_POST["kategori"] ?? "");
    $jenis = $_POST["jenis"] ?? "Keinginan";
    $harga = $_POST["harga"] ?? "";
    $status = $_POST["status"] ?? "Belum Dibeli";

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
            "INSERT INTO barang
                (nama_barang, kategori, jenis, harga, status)
             VALUES (?, ?, ?, ?, ?)"
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "sssds",
                $nama_barang,
                $kategori,
                $jenis,
                $harga,
                $status
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: daftar.php");
                exit;
            }

            $pesan = "Barang gagal disimpan. Silakan coba lagi.";
            mysqli_stmt_close($stmt);
        } else {
            $pesan = "Terjadi kesalahan saat menyiapkan data.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Barang - Wishlist PJK</title>

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

                    <h2 class="fw-bold mb-2">Tambah Barang</h2>
                    <p class="text-secondary mb-4">
                        Tentukan barang, jenis, dan harga yang kamu inginkan.
                    </p>

                    <?php if ($pesan !== ""): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($pesan, ENT_QUOTES, "UTF-8") ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="tambah.php">

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
                                value="<?= htmlspecialchars($nama_barang, ENT_QUOTES, "UTF-8") ?>"
                                placeholder="Contoh: Tas Ransel"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="kategori" class="form-label">
                                Kategori Barang
                            </label>
                            <select id="kategori" name="kategori"
                                    class="form-select" required>
                                <option value="">Pilih kategori</option>

                                <?php foreach ($daftar_kategori as $item): ?>
                                    <option
                                        value="<?= htmlspecialchars($item, ENT_QUOTES, "UTF-8") ?>"
                                        <?= $kategori === $item ? "selected" : "" ?>>
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
                                    <?= $jenis === "Kebutuhan" ? "selected" : "" ?>>
                                    Kebutuhan
                                </option>

                                <option value="Keinginan"
                                    <?= $jenis === "Keinginan" ? "selected" : "" ?>>
                                    Keinginan
                                </option>
                            </select>

                            <div class="form-text">
                                Kebutuhan adalah barang penting; keinginan adalah
                                barang yang ingin kamu miliki.
                            </div>
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
                                value="<?= htmlspecialchars((string) $harga, ENT_QUOTES, "UTF-8") ?>"
                                placeholder="Contoh: 150000"
                                required>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">
                                Status Barang
                            </label>
                            <select id="status" name="status"
                                    class="form-select" required>
                                <option value="Belum Dibeli"
                                    <?= $status === "Belum Dibeli" ? "selected" : "" ?>>
                                    Belum Dibeli
                                </option>

                                <option value="Sudah Dibeli"
                                    <?= $status === "Sudah Dibeli" ? "selected" : "" ?>>
                                    Sudah Dibeli
                                </option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit"
                                    class="btn btn-primary flex-fill">
                                Simpan Barang
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
