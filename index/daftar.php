<?php
include_once __DIR__ . "/../config.php";

// Mengambil semua barang dari database

$cari = trim($_GET['cari'] ?? '');
$kategori = trim($_GET['kategori'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM barang WHERE 1=1";

if ($cari !== '') {
    $cari_aman = mysqli_real_escape_string($koneksi, $cari);
    $sql .= " AND nama_barang LIKE '%$cari_aman%'";
}

if ($kategori !== '') {
    $kategori_aman = mysqli_real_escape_string($koneksi, $kategori);
    $sql .= " AND kategori = '$kategori_aman'";
}

if ($status !== '') {
    $status_aman = mysqli_real_escape_string($koneksi, $status);
    $sql .= " AND status = '$status_aman'";
}

$sql .= " ORDER BY id_barang DESC";

$query_barang = mysqli_query($koneksi, $sql);

if (!$query_barang) {
    die("Gagal mengambil data barang: " . mysqli_error($koneksi));
}

$total_barang = mysqli_num_rows($query_barang);


if (!$query_barang) {
    die("Gagal mengambil data barang: " . mysqli_error($koneksi));
}

$total_barang = mysqli_num_rows($query_barang);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Barang | Wishlist</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../STYLES.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg wishlist-navbar">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Wishlist</a>

            <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarWishlist"
                aria-controls="navbarWishlist" aria-expanded="false"
                aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarWishlist">
                <ul class="navbar-nav ms-auto gap-lg-2">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="daftar.php">Daftar Barang</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pengeluaran/index.php">Pengeluaran</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">Daftar Barang</h2>
                <p class="text-secondary mb-0">
                    Kelola semua barang yang ingin kamu beli.
                </p>
            </div>

            <a href="tambah.php" class="btn btn-primary">
                + Tambah Barang
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <p class="text-secondary mb-2">Total Barang</p>
                        <h3 class="fw-bold mb-0">
                            <?= $total_barang; ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 p-md-4">


                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <h5 class="fw-bold mb-0">Semua Barang</h5>
                </div>

                <form method="GET" action="daftar.php" class="row g-2 mb-4">

                    <div class="col-md-5">
                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari nama barang..."
                            value="<?= htmlspecialchars($_GET['cari'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <select name="kategori" class="form-select">
                            <option value="">Semua Kategori</option>

                            <?php
                            $query_kategori = mysqli_query(
                                $koneksi,
                                "SELECT DISTINCT kategori FROM barang
                 WHERE kategori IS NOT NULL AND kategori != ''
                 ORDER BY kategori ASC"
                            );

                            while ($kat = mysqli_fetch_assoc($query_kategori)):
                            ?>
                                <option
                                    value="<?= htmlspecialchars($kat['kategori']) ?>"
                                    <?= (($_GET['kategori'] ?? '') === $kat['kategori']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kat['kategori']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="Belum Dibeli"
                                <?= (($_GET['status'] ?? '') === 'Belum Dibeli') ? 'selected' : '' ?>>
                                Belum Dibeli
                            </option>
                            <option value="Sudah Dibeli"
                                <?= (($_GET['status'] ?? '') === 'Sudah Dibeli') ? 'selected' : '' ?>>
                                Sudah Dibeli
                            </option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            Cari
                        </button>

                        <a href="daftar.php" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>

                </form>


            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('cariBarang').addEventListener('input', function() {
            const kataKunci = this.value.toLowerCase();

            document.querySelectorAll('#tabelBarang tr').forEach(function(baris) {
                baris.style.display = baris.textContent.toLowerCase().includes(kataKunci) ?
                    '' :
                    'none';
            });
        });
    </script>

</body>

</html>