<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
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

// Proses perubahan data
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama = trim($_POST["nama_pengeluaran"] ?? "");
    $id_barang_input = $_POST["id_barang"] ?? "";
    $jumlah_input = $_POST["jumlah"] ?? "";
    $tanggal = $_POST["tanggal_pengeluaran"] ?? "";
    $catatan = trim($_POST["catatan"] ?? "");

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

    $jumlah_valid = is_numeric($jumlah_input)
        && is_finite((float) $jumlah_input)
        && (float) $jumlah_input > 0;

    if (
        $nama === "" ||
        strlen($nama) > 150 ||
        ($id_barang_input !== "" && (!$id_barang || $id_barang < 1)) ||
        !$jumlah_valid ||
        !$tanggal_valid ||
        strlen($catatan) > 255
    ) {
        $pesan_error = "Periksa kembali semua isian.";
    } else {
        // Pastikan barang yang dipilih memang ada
        if ($id_barang !== null) {
            $cek = mysqli_prepare(
                $koneksi,
                "SELECT id_barang FROM barang WHERE id_barang = ?"
            );
            mysqli_stmt_bind_param($cek, "i", $id_barang);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            if (mysqli_stmt_num_rows($cek) === 0) {
                $pesan_error = "Barang yang dipilih tidak ditemukan.";
            }

            mysqli_stmt_close($cek);
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
                    $jumlah_input,
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
                    $jumlah_input,
                    $tanggal,
                    $catatan,
                    $id
                );
            }

            if (mysqli_stmt_execute($update)) {
                mysqli_stmt_close($update);
                header("Location: pengeluaran.php");
                exit;
            }

            $pesan_error = "Gagal memperbarui data pengeluaran.";
            mysqli_stmt_close($update);
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

$query_barang = mysqli_query(
    $koneksi,
    "SELECT id_barang, nama_barang FROM barang ORDER BY nama_barang"
);
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
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="mb-2">Edit Pengeluaran</h2>
                        <p class="text-secondary mb-4">
                            Perbarui informasi pengeluaranmu.
                        </p>

                        <?php if ($pesan_error !== ""): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($pesan_error) ?>
                            </div>
                        <?php endif; ?>

                        <div class="expense-form-card">
                            <form method="POST">
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
                                                <?= (string) $data['id_barang'] === (string) $barang['id_barang'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($barang['nama_barang']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Jumlah (Rp)</label>
                                    <input
                                        type="number"
                                        name="jumlah"
                                        class="form-control"
                                        min="0.01"
                                        step="0.01"
                                        required
                                        value="<?= htmlspecialchars((string) $data['jumlah']) ?>">
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
</body>

</html>