
<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
auth_require_login();

$pesan_error = "";

$nama_pengeluaran = "";
$id_barang_form = "";
$jumlah = "";
$tanggal_pengeluaran = date("Y-m-d");
$catatan = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_barang_raw = $_POST["id_barang"] ?? "";
    $id_barang_raw = is_string($id_barang_raw) ? $id_barang_raw : "";
    $id_barang = $id_barang_raw !== ""
        ? filter_var($id_barang_raw, FILTER_VALIDATE_INT)
        : null;

    $nama_input = $_POST["nama_pengeluaran"] ?? "";
    $jumlah_input = $_POST["jumlah"] ?? "";
    $tanggal_input = $_POST["tanggal_pengeluaran"] ?? "";
    $catatan_input = $_POST["catatan"] ?? "";
    $nama_pengeluaran = is_string($nama_input) ? trim($nama_input) : "";
    $jumlah = is_string($jumlah_input) ? $jumlah_input : "";
    $tanggal_pengeluaran = is_string($tanggal_input) ? $tanggal_input : "";
    $catatan = is_string($catatan_input) ? trim($catatan_input) : "";
    $id_barang_form = $id_barang_raw;

    $tanggal_valid = DateTime::createFromFormat(
        "!Y-m-d",
        $tanggal_pengeluaran
    );

    if (
        !auth_verify_csrf($_POST["csrf_token"] ?? null) ||
        $nama_pengeluaran === "" ||
        strlen($nama_pengeluaran) > 150 ||
        $jumlah === "" ||
        !is_numeric($jumlah) ||
        !is_finite((float) $jumlah) ||
        (float) $jumlah <= 0 ||
        $tanggal_valid === false ||
        $tanggal_valid->format("Y-m-d") !== $tanggal_pengeluaran ||
        strlen($catatan) > 255 ||
        ($id_barang_raw !== "" && (!$id_barang || $id_barang < 1))
    ) {
        $pesan_error = auth_verify_csrf($_POST["csrf_token"] ?? null)
            ? "Periksa kembali nama, jumlah, tanggal, dan data lainnya."
            : "Sesi formulir kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        try {
            mysqli_begin_transaction($koneksi);

            if ($id_barang === null) {
                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO pengeluaran
                        (id_barang, nama_pengeluaran, jumlah,
                         tanggal_pengeluaran, catatan)
                     VALUES (NULL, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sdss",
                    $nama_pengeluaran,
                    $jumlah,
                    $tanggal_pengeluaran,
                    $catatan
                );
            } else {
                $stmt_cek = mysqli_prepare(
                    $koneksi,
                    "SELECT id_barang FROM barang WHERE id_barang = ? FOR UPDATE"
                );
                mysqli_stmt_bind_param($stmt_cek, "i", $id_barang);
                mysqli_stmt_execute($stmt_cek);
                $barang_valid = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));
                mysqli_stmt_close($stmt_cek);

                $stmt_cek = mysqli_prepare(
                    $koneksi,
                    "SELECT id_pengeluaran
                     FROM pengeluaran
                     WHERE id_barang = ?
                     LIMIT 1"
                );
                mysqli_stmt_bind_param($stmt_cek, "i", $id_barang);
                mysqli_stmt_execute($stmt_cek);
                $sudah_dicatat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));
                mysqli_stmt_close($stmt_cek);

                if (!$barang_valid || $sudah_dicatat) {
                    mysqli_rollback($koneksi);
                    $pesan_error = "Barang ini tidak tersedia atau sudah memiliki catatan pengeluaran.";
                } else {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "INSERT INTO pengeluaran
                            (id_barang, nama_pengeluaran, jumlah,
                             tanggal_pengeluaran, catatan)
                         VALUES (?, ?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "isdss",
                        $id_barang,
                        $nama_pengeluaran,
                        $jumlah,
                        $tanggal_pengeluaran,
                        $catatan
                    );
                }
            }

            if ($pesan_error === "" && mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                if ($id_barang !== null) {
                    $stmt_status = mysqli_prepare(
                        $koneksi,
                        "UPDATE barang SET status = 'Sudah Dibeli' WHERE id_barang = ?"
                    );
                    mysqli_stmt_bind_param($stmt_status, "i", $id_barang);
                    mysqli_stmt_execute($stmt_status);
                    mysqli_stmt_close($stmt_status);
                }
                mysqli_commit($koneksi);
                header(
                    "Location: pengeluaran.php?tersimpan="
                    . ($id_barang !== null ? "barang" : "umum")
                );
                exit;
            }

            if ($pesan_error === "") {
                mysqli_stmt_close($stmt);
                mysqli_rollback($koneksi);
                $pesan_error = "Gagal menyimpan pengeluaran.";
            }
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($koneksi);
            error_log("Wishlist PJK expense creation failed: " . $exception->getMessage());
            $pesan_error = "Pengeluaran gagal disimpan. Silakan coba lagi.";
        }
    }
}

// Barang yang bisa dipilih: belum memiliki catatan pengeluaran.
$query_barang = mysqli_query(
    $koneksi,
    "SELECT b.id_barang, b.nama_barang, b.harga
     FROM barang b
     WHERE NOT EXISTS (
           SELECT 1
           FROM pengeluaran p
           WHERE p.id_barang = b.id_barang
       )
     ORDER BY b.nama_barang ASC"
);

if (!$query_barang) {
    die("Gagal mengambil daftar barang: " . mysqli_error($koneksi));
}

$jumlah_barang_tersedia = mysqli_num_rows($query_barang);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengeluaran | Wishlist PJK</title>

    <link rel="stylesheet" href="../STYLES.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="../UI.css">
</head>

<body class="bg-light">

<div class="container py-5 form-page">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">

            <div class="form-page-heading">
                <div>
                    <h1 class="h3 mb-1">Catat pembelian</h1>
                    <p>Hubungkan transaksi dengan barang agar status wishlist ikut diperbarui.</p>
                </div>
                <a class="btn btn-outline-primary" href="../index/index.php">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke dashboard
                </a>
            </div>

            <div class="card border-0 shadow rounded-4">
                <div class="card-body p-4 p-md-5">

                    <h2 class="fw-bold mb-2">Detail pengeluaran</h2>
                    <p class="text-secondary mb-4">
                        Pilih barang wishlist jika transaksi ini adalah pembelian barang tersebut.
                    </p>

                    <?php if ($pesan_error !== ""): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($pesan_error, ENT_QUOTES, "UTF-8") ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="tambah_pengeluaran.php"
                          data-purchase-form data-loading-form>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">

                        <div class="mb-3">
                            <label for="nama_pengeluaran" class="form-label">
                                Nama Pengeluaran
                            </label>
                            <input
                                type="text"
                                id="nama_pengeluaran"
                                name="nama_pengeluaran"
                                class="form-control"
                                data-purchase-name
                                maxlength="150"
                                value="<?= htmlspecialchars($nama_pengeluaran, ENT_QUOTES, "UTF-8") ?>"
                                placeholder="Contoh: Membeli tas ransel"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="id_barang" class="form-label">
                                Barang yang dibeli <span class="text-secondary fw-normal">(opsional)</span>
                            </label>

                            <select
                                id="id_barang"
                                name="id_barang"
                                class="form-select"
                                data-purchase-item>

                                <option value="">Catat pengeluaran tanpa mengaitkan barang</option>

                                <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>
                                    <option
                                        value="<?= (int) $barang["id_barang"] ?>"
                                        data-nama="<?= htmlspecialchars($barang["nama_barang"], ENT_QUOTES, "UTF-8") ?>"
                                        data-harga="<?= htmlspecialchars((string) $barang["harga"], ENT_QUOTES, "UTF-8") ?>"
                                        <?= (string) $id_barang_form === (string) $barang["id_barang"] ? "selected" : "" ?>>

                                        <?= htmlspecialchars($barang["nama_barang"], ENT_QUOTES, "UTF-8") ?>
                                        — Rp <?= number_format((float) $barang["harga"], 0, ",", ".") ?>

                                    </option>
                                <?php endwhile; ?>
                            </select>

                            <?php if ($jumlah_barang_tersedia > 0): ?>
                                <div class="purchase-confirmation" role="status">
                                    <i class="bi bi-arrow-repeat"></i>
                                    <span>Setelah disimpan, barang akan otomatis berubah menjadi <strong>Sudah Dibeli</strong>, hilang dari prioritas dashboard, dan tetap terlihat di daftar barang.</span>
                                </div>
                            <?php else: ?>
                                <div class="form-text mt-2">Tidak ada barang yang bisa dihubungkan. Kamu tetap dapat mencatat pengeluaran tanpa memilih barang.</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="jumlah" class="form-label">
                                Jumlah Pengeluaran (Rp)
                            </label>
                            <input
                                type="number"
                                id="jumlah"
                                name="jumlah"
                                class="form-control"
                                data-purchase-amount
                                min="0.01"
                                step="0.01"
                                value="<?= htmlspecialchars((string) $jumlah, ENT_QUOTES, "UTF-8") ?>"
                                placeholder="Contoh: 150000"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="tanggal_pengeluaran" class="form-label">
                                Tanggal Pengeluaran
                            </label>
                            <input
                                type="date"
                                id="tanggal_pengeluaran"
                                name="tanggal_pengeluaran"
                                class="form-control"
                                value="<?= htmlspecialchars($tanggal_pengeluaran, ENT_QUOTES, "UTF-8") ?>"
                                required>
                        </div>

                        <div class="mb-4">
                            <label for="catatan" class="form-label">
                                Catatan (opsional)
                            </label>
                            <textarea
                                id="catatan"
                                name="catatan"
                                class="form-control"
                                rows="3"
                                maxlength="255"
                                placeholder="Tambahkan catatan..."><?= htmlspecialchars($catatan, ENT_QUOTES, "UTF-8") ?></textarea>
                        </div>

                        <div class="btn-group-actions">
                            <button type="submit" class="btn btn-primary">
                                Simpan Pengeluaran
                            </button>

                            <a href="pengeluaran.php"
                               class="btn btn-outline-secondary">
                                Batal
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
