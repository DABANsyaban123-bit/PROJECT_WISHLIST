
<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../pembayaran.php";
$user_id = auth_require_login($koneksi);

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: daftar.php");
    exit;
}

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM barang
     WHERE id_barang = ? AND " . auth_user_scope($koneksi, "barang")
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
    $nama_input = $_POST["nama_barang"] ?? "";
    $kategori_input = $_POST["kategori"] ?? "";
    $jenis_input = $_POST["jenis"] ?? "";
    $harga_post = $_POST["harga"] ?? "";
    $nama_barang = is_string($nama_input) ? trim($nama_input) : "";
    $kategori = is_string($kategori_input) ? $kategori_input : "";
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
        try {
            mysqli_begin_transaction($koneksi);
            $kunci_barang = mysqli_prepare(
                $koneksi,
                "SELECT id_barang FROM barang
                 WHERE id_barang = ? AND " . auth_user_scope($koneksi, "barang") . "
                 FOR UPDATE"
            );
            mysqli_stmt_bind_param($kunci_barang, "i", $id);
            mysqli_stmt_execute($kunci_barang);
            mysqli_stmt_close($kunci_barang);

            $cek_pembayaran = mysqli_prepare(
                $koneksi,
                "SELECT COALESCE(SUM(jumlah), 0) AS total_dibayar
                 FROM pengeluaran
                 WHERE id_barang = ?
                   AND " . auth_user_scope($koneksi, "pengeluaran")
            );
            mysqli_stmt_bind_param($cek_pembayaran, "i", $id);
            mysqli_stmt_execute($cek_pembayaran);
            $pembayaran_tercatat = mysqli_fetch_assoc(mysqli_stmt_get_result($cek_pembayaran));
            mysqli_stmt_close($cek_pembayaran);

            if ((float) $harga < (float) $pembayaran_tercatat["total_dibayar"]) {
                mysqli_rollback($koneksi);
                $pesan = "Harga target tidak dapat lebih rendah daripada total pembayaran yang sudah tercatat.";
                throw new DomainException($pesan);
            }

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE barang
                 SET nama_barang = ?,
                     kategori = ?,
                     jenis = ?,
                     harga = ?
                 WHERE id_barang = ?
                   AND " . auth_user_scope($koneksi, "barang")
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssdi",
                $nama_barang,
                $kategori,
                $jenis,
                $harga,
                $id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            pembayaran_sinkronkan_status($koneksi, $id);
            mysqli_commit($koneksi);
            header("Location: daftar.php");
            exit;
        } catch (DomainException $exception) {
            $pesan = $exception->getMessage();
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($koneksi);
            error_log("Wishlist PJK item update failed: " . $exception->getMessage());
            $pesan = "Barang gagal diperbarui.";
        }

    }

    $barang["nama_barang"] = $nama_barang;
    $barang["kategori"] = $kategori;
    $barang["jenis"] = $jenis;
    $barang["harga"] = $harga;
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
    <link rel="stylesheet" href="../UI.css">
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

                    <form method="POST" data-loading-form>
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
                                type="text"
                                id="harga"
                                name="harga"
                                class="form-control"
                                inputmode="decimal"
                                data-rupiah-input
                                value="<?= htmlspecialchars(nominal_format_input($barang["harga"]), ENT_QUOTES, "UTF-8") ?>"
                                required>
                            <div class="form-text">Pemisah ribuan otomatis; gunakan koma untuk nilai sen.</div>
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

<script src="../app.js" defer></script>
</body>
</html>
