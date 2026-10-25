<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../pembayaran.php";
auth_require_login();

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("ID pengeluaran tidak valid.");
}

// Ambil data pengeluaran
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM pengeluaran WHERE id_pengeluaran = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasil);

if (!$data) {
    exit("Data pengeluaran tidak ditemukan.");
}

$pesan_error = "";
$id_barang_dipilih = (string) ($data["id_barang"] ?? "");

// Proses perubahan data
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_input = $_POST["nama_pengeluaran"] ?? "";
    $id_barang_input = $_POST["id_barang"] ?? "";
    $jumlah_post = $_POST["jumlah"] ?? "";
    $tanggal_post = $_POST["tanggal_pengeluaran"] ?? "";
    $catatan_input = $_POST["catatan"] ?? "";
    $nama = is_string($nama_input) ? trim($nama_input) : "";
    $id_barang_input = is_string($id_barang_input) ? $id_barang_input : "";
    $jumlah_input = is_string($jumlah_post) ? $jumlah_post : "";
    $jumlah = nominal_normalisasi($jumlah_input);
    $tanggal = is_string($tanggal_post) ? $tanggal_post : "";
    $catatan = is_string($catatan_input) ? trim($catatan_input) : "";
    $id_barang_dipilih = $id_barang_input;

    $id_barang = null;

    if ($id_barang_input !== "") {
        $id_barang = filter_var(
            $id_barang_input,
            FILTER_VALIDATE_INT
        );
    }

    $tanggal_valid = DateTime::createFromFormat("!Y-m-d", $tanggal);
    $tanggal_valid = $tanggal_valid
        && $tanggal_valid->format("Y-m-d") === $tanggal;

    $jumlah_valid = $jumlah !== null && $jumlah > 0;

    if (
        !auth_verify_csrf($_POST["csrf_token"] ?? null) ||
        $nama === "" ||
        strlen($nama) > 150 ||
        ($id_barang_input !== "" && (!$id_barang || $id_barang < 1)) ||
        !$jumlah_valid ||
        !$tanggal_valid ||
        strlen($catatan) > 255
    ) {
        $pesan_error = auth_verify_csrf($_POST["csrf_token"] ?? null)
            ? "Periksa kembali semua isian."
            : "Sesi formulir kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        try {
            mysqli_begin_transaction($koneksi);

            $cek_lama = mysqli_prepare(
                $koneksi,
                "SELECT id_barang
                 FROM pengeluaran
                 WHERE id_pengeluaran = ?
                 FOR UPDATE"
            );
            mysqli_stmt_bind_param($cek_lama, "i", $id);
            mysqli_stmt_execute($cek_lama);
            $pengeluaran_terkunci = mysqli_fetch_assoc(mysqli_stmt_get_result($cek_lama));
            mysqli_stmt_close($cek_lama);

            if (!$pengeluaran_terkunci) {
                throw new RuntimeException("Pengeluaran tidak ditemukan saat dikunci.");
            }

            $id_barang_lama = $pengeluaran_terkunci["id_barang"] !== null
                ? (int) $pengeluaran_terkunci["id_barang"]
                : null;
            $barang_dikunci = array_values(array_unique(array_filter(
                [$id_barang_lama, $id_barang],
                static function ($barang_id): bool {
                    return $barang_id !== null;
                }
            )));
            sort($barang_dikunci, SORT_NUMERIC);
            foreach ($barang_dikunci as $barang_id_dikunci) {
                $kunci_barang = mysqli_prepare(
                    $koneksi,
                    "SELECT id_barang FROM barang WHERE id_barang = ? FOR UPDATE"
                );
                mysqli_stmt_bind_param($kunci_barang, "i", $barang_id_dikunci);
                mysqli_stmt_execute($kunci_barang);
                mysqli_stmt_close($kunci_barang);
            }

            $barang_baru_valid = true;

            if ($id_barang !== null) {
                $cek_barang = mysqli_prepare(
                    $koneksi,
                    "SELECT id_barang, harga FROM barang WHERE id_barang = ?"
                );
                mysqli_stmt_bind_param($cek_barang, "i", $id_barang);
                mysqli_stmt_execute($cek_barang);
                $barang_tujuan = mysqli_fetch_assoc(mysqli_stmt_get_result($cek_barang));
                mysqli_stmt_close($cek_barang);

                $barang_baru_valid = $barang_tujuan !== null;
                if ($barang_baru_valid && $id_barang !== $id_barang_lama) {
                    $cek_total = mysqli_prepare(
                        $koneksi,
                        "SELECT COALESCE(SUM(jumlah), 0) AS total_bayar
                     FROM pengeluaran
                     WHERE id_barang = ?"
                    );
                    mysqli_stmt_bind_param($cek_total, "i", $id_barang);
                    mysqli_stmt_execute($cek_total);
                    $total_bayar = mysqli_fetch_assoc(mysqli_stmt_get_result($cek_total));
                    mysqli_stmt_close($cek_total);

                    $barang_baru_valid = (float) $total_bayar["total_bayar"]
                        < (float) $barang_tujuan["harga"];
                }

                if (!$barang_baru_valid) {
                    mysqli_rollback($koneksi);
                    $pesan_error = "Barang tidak tersedia atau sudah lunas.";
                }
            }

            if ($pesan_error === "") {
                if ($id_barang === null) {
                    $update = mysqli_prepare($koneksi, "
                        UPDATE pengeluaran
                        SET id_barang = NULL,
                            nama_pengeluaran = ?,
                            jumlah = ?,
                            tanggal_pengeluaran = ?,
                            catatan = ?
                        WHERE id_pengeluaran = ?
                    ");

                    mysqli_stmt_bind_param(
                        $update,
                        "sdssi",
                        $nama,
                        $jumlah,
                        $tanggal,
                        $catatan,
                        $id
                    );
                } else {
                    $update = mysqli_prepare($koneksi, "
                        UPDATE pengeluaran
                        SET id_barang = ?,
                            nama_pengeluaran = ?,
                            jumlah = ?,
                            tanggal_pengeluaran = ?,
                            catatan = ?
                        WHERE id_pengeluaran = ?
                    ");

                    mysqli_stmt_bind_param(
                        $update,
                        "isdssi",
                        $id_barang,
                        $nama,
                        $jumlah,
                        $tanggal,
                        $catatan,
                        $id
                    );
                }

                if (mysqli_stmt_execute($update)) {
                    mysqli_stmt_close($update);
                    if ($id_barang_lama !== null) {
                        pembayaran_sinkronkan_status($koneksi, $id_barang_lama);
                    }
                    if ($id_barang !== null && $id_barang !== $id_barang_lama) {
                        pembayaran_sinkronkan_status($koneksi, $id_barang);
                    }

                    mysqli_commit($koneksi);
                    header("Location: pengeluaran.php?diperbarui=1");
                    exit;
                }

                $pesan_error = "Gagal memperbarui data pengeluaran.";
                mysqli_stmt_close($update);
                mysqli_rollback($koneksi);
            }
        } catch (mysqli_sql_exception | RuntimeException $exception) {
            mysqli_rollback($koneksi);
            error_log("Wishlist PJK expense update failed: " . $exception->getMessage());
            $pesan_error = "Pengeluaran gagal diperbarui. Silakan coba lagi.";
        }
    }

    // Tampilkan kembali isian terbaru jika validasi gagal
    if ($pesan_error !== "") {
        $data["nama_pengeluaran"] = $nama;
        $data["id_barang"] = $id_barang;
        $data["jumlah"] = $jumlah_input;
        $data["tanggal_pengeluaran"] = $tanggal;
        $data["catatan"] = $catatan;
    }
}

$stmt_barang = mysqli_prepare(
    $koneksi,
    "SELECT b.id_barang, b.nama_barang
     FROM barang b
     ORDER BY b.nama_barang"
);
mysqli_stmt_execute($stmt_barang);
$query_barang = mysqli_stmt_get_result($stmt_barang);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengeluaran | WishList</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="../UI.css">
</head>

<body class="bg-light">
    <div class="container py-5 form-page">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="form-page-heading">
                    <div>
                        <h1 class="h3 mb-1">Edit pengeluaran</h1>
                        <p>Perubahan transaksi akan memperbarui total dan status pembayaran barang.</p>
                    </div>
                    <a class="btn btn-outline-primary" href="pengeluaran.php">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="mb-2">Detail pengeluaran</h2>
                        <p class="text-secondary mb-4">
                            Perbarui informasi pengeluaranmu.
                        </p>

                        <?php if ($pesan_error !== ""): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($pesan_error) ?>
                            </div>
                        <?php endif; ?>

                        <div class="expense-form-card">
                            <form method="POST" data-loading-form>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                                <div class="mb-3">
                                    <label class="form-label">Nama Pengeluaran</label>
                                    <input
                                        type="text"
                                        name="nama_pengeluaran"
                                        class="form-control"
                                        maxlength="150"
                                        required
                                        value="<?= htmlspecialchars((string) $data['nama_pengeluaran']) ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Barang Terkait</label>
                                    <select name="id_barang" class="form-select">
                                        <option value="">Tidak terkait dengan barang</option>

                                        <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>
                                            <option
                                                value="<?= (int) $barang['id_barang'] ?>"
                                                <?= $id_barang_dipilih === (string) $barang['id_barang'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($barang['nama_barang']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Jumlah (Rp)</label>
                                    <input
                                        type="text"
                                        name="jumlah"
                                        class="form-control"
                                        inputmode="decimal"
                                        data-rupiah-input
                                        required
                                        value="<?= htmlspecialchars(nominal_format_input($data['jumlah'])) ?>">
                                    <div class="form-text">Pemisah ribuan otomatis; gunakan koma untuk nilai sen.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Tanggal Pengeluaran</label>
                                    <input
                                        type="date"
                                        name="tanggal_pengeluaran"
                                        class="form-control"
                                        required
                                        value="<?= htmlspecialchars((string) $data['tanggal_pengeluaran']) ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Catatan</label>
                                    <textarea
                                        name="catatan"
                                        class="form-control"
                                        maxlength="255"
                                        rows="3"><?= htmlspecialchars((string) ($data['catatan'] ?? '')) ?></textarea>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        Simpan Perubahan
                                    </button>

                                    <a href="pengeluaran.php" class="btn btn-outline-secondary">
                                        Batal
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../app.js" defer></script>
</body>

</html>