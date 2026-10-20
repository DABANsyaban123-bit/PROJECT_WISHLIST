
<?php
include_once __DIR__ . "/../config.php";

$pesan_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_barang = $_POST["id_barang"] !== ""
        ? (int) $_POST["id_barang"]
        : null;

    $nama_pengeluaran = trim($_POST["nama_pengeluaran"]);
    $jumlah = $_POST["jumlah"];
    $tanggal_pengeluaran = $_POST["tanggal_pengeluaran"];
    $catatan = trim($_POST["catatan"]);

    if (
        $nama_pengeluaran === "" ||
        !is_numeric($jumlah) ||
        (float) $jumlah <= 0 ||
        $tanggal_pengeluaran === ""
    ) {
        $pesan_error = "Isi nama, jumlah yang valid, dan tanggal pengeluaran.";
    } else {
        if ($id_barang === null) {
            $stmt = mysqli_prepare($koneksi, "
                INSERT INTO pengeluaran
                (id_barang, nama_pengeluaran, jumlah,
                 tanggal_pengeluaran, catatan)
                VALUES (NULL, ?, ?, ?, ?)
            ");

            mysqli_stmt_bind_param(
                $stmt,
                "sdss",
                $nama_pengeluaran,
                $jumlah,
                $tanggal_pengeluaran,
                $catatan
            );
        } else {
            $stmt = mysqli_prepare($koneksi, "
                INSERT INTO pengeluaran
                (id_barang, nama_pengeluaran, jumlah,
                 tanggal_pengeluaran, catatan)
                VALUES (?, ?, ?, ?, ?)
            ");

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

        if (mysqli_stmt_execute($stmt)) {
            header("Location: pengeluaran.php");
            exit;
        } else {
            $pesan_error = "Gagal menyimpan pengeluaran.";
        }
    }
}

$query_barang = mysqli_query(
    $koneksi,
    "SELECT id_barang, nama_barang FROM barang ORDER BY nama_barang ASC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengeluaran | WishList</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <h2 class="mb-2">Tambah Pengeluaran</h2>
                    <p class="text-secondary mb-4">
                        Catat pengeluaranmu di sini.
                    </p>

                    <?php if ($pesan_error !== ""): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($pesan_error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label">
                                Nama Pengeluaran
                            </label>
                            <input
                                type="text"
                                name="nama_pengeluaran"
                                class="form-control"
                                placeholder="Contoh: Membeli buku"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Barang Terkait (opsional)
                            </label>
                            <select name="id_barang" class="form-select">
                                <option value="">Tidak terkait dengan barang</option>

                                <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>
                                    <option value="<?= (int) $barang['id_barang'] ?>">
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
                                placeholder="Contoh: 25000"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Pengeluaran</label>
                            <input
                                type="date"
                                name="tanggal_pengeluaran"
                                class="form-control"
                                value="<?= date('Y-m-d') ?>"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea
                                name="catatan"
                                class="form-control"
                                rows="3"
                                maxlength="255"
                                placeholder="Tambahkan catatan..."
                            ></textarea>
                        </div>

                        <div class="d-flex gap-2">
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

</body>
</html>
