
<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../pembayaran.php";
$user_id = auth_require_login($koneksi);

$pesan = "";
$nama_barang = "";
$kategori = "";
$jenis = "Keinginan";
$harga = "";
$harga_input = "";
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
    $nama_input = $_POST["nama_barang"] ?? "";
    $kategori_input = $_POST["kategori"] ?? "";
    $jenis_input = $_POST["jenis"] ?? "Keinginan";
    $harga_post = $_POST["harga"] ?? "";
    $nama_barang = is_string($nama_input) ? trim($nama_input) : "";
    $kategori = is_string($kategori_input) ? trim($kategori_input) : "";
    $jenis = is_string($jenis_input) ? $jenis_input : "";
    $harga_input = is_string($harga_post) ? $harga_post : "";
    $harga = nominal_normalisasi($harga_input);
    $jenis_valid = ["Kebutuhan", "Keinginan"];

    if (
        !auth_verify_csrf($_POST["csrf_token"] ?? null) ||
        $nama_barang === "" ||
        strlen($nama_barang) > 255 ||
        !in_array($kategori, $daftar_kategori, true) ||
        !in_array($jenis, $jenis_valid, true) ||
        $harga === null ||
        $harga <= 0
    ) {
        $pesan = auth_verify_csrf($_POST["csrf_token"] ?? null)
            ? "Periksa kembali data yang kamu masukkan."
            : "Sesi formulir kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO barang
                (nama_barang, kategori, jenis, harga, status"
                . auth_owner_insert_columns($koneksi) . ")
             VALUES (?, ?, ?, ?, ?" . auth_owner_insert_values($koneksi) . ")"
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
    <title>Tambah Barang - Wishlist</title>

    <link rel="stylesheet" href="../STYLES.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="../UI.css">
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

                    <form method="POST" action="tambah.php" data-loading-form>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">

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
                                type="text"
                                id="harga"
                                name="harga"
                                class="form-control"
                                inputmode="decimal"
                                data-rupiah-input
                                value="<?= htmlspecialchars(nominal_format_input($harga_input), ENT_QUOTES, "UTF-8") ?>"
                                placeholder="Contoh: 20.000.000"
                                required>
                            <div class="form-text">Pemisah ribuan otomatis; gunakan koma untuk nilai sen.</div>
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

<script src="../app.js" defer></script>
</body>
</html>
