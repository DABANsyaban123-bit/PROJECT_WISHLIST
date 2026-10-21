
<?php
include_once __DIR__ . "/../config.php";

// Ambil filter dari URL
$cari = trim($_GET['cari'] ?? '');
$kategori = trim($_GET['kategori'] ?? '');
$status = trim($_GET['status'] ?? '');

// Mengambil seluruh kategori dari database
$query_kategori = mysqli_query(
    $koneksi,
    "SELECT DISTINCT kategori
     FROM barang
     WHERE kategori IS NOT NULL AND kategori != ''
     ORDER BY kategori ASC"
);

if (!$query_kategori) {
    die("Gagal mengambil kategori: " . mysqli_error($koneksi));
}

// Menghitung seluruh barang, tidak terpengaruh filter
$query_total = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total FROM barang"
);

if (!$query_total) {
    die("Gagal menghitung barang: " . mysqli_error($koneksi));
}

$total_semua = (int) mysqli_fetch_assoc($query_total)['total'];

// Membuat query pencarian dan filter
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
    die("Gagal mengambil daftar barang: " . mysqli_error($koneksi));
}

$total_hasil = mysqli_num_rows($query_barang);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar Barang | Wishlist PJK</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../STYLES.css">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg wishlist-navbar">
    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            Wishlist
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarWishlist"
            aria-controls="navbarWishlist"
            aria-expanded="false"
            aria-label="Buka navigasi"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarWishlist">
            <ul class="navbar-nav ms-auto gap-lg-2">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active" href="daftar.php">
                        Daftar Barang
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="../pengeluaran/pengeluaran.php">
                        Pengeluaran
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>

<!-- KONTEN UTAMA -->
<main class="container py-4 py-md-5">

    <!-- HEADER HALAMAN -->
    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <div>
            <h2 class="fw-bold mb-1">Daftar Barang</h2>

            <p class="text-secondary mb-0">
                Temukan dan kelola barang yang ingin kamu beli.
            </p>
        </div>

    </div>

    <!-- KARTU RINGKASAN -->
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">

                    <p class="text-secondary mb-2">
                        Total Seluruh Barang
                    </p>

                    <h3 class="fw-bold mb-0">
                        <?= $total_semua; ?>
                    </h3>

                    <small class="text-secondary">
                        Semua data wishlist kamu
                    </small>

                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">

                    <p class="text-secondary mb-2">
                        Hasil Pencarian
                    </p>

                    <h3 class="fw-bold mb-0">
                        <?= $total_hasil; ?>
                    </h3>

                    <small class="text-secondary">
                        Barang sesuai filter saat ini
                    </small>

                </div>
            </div>
        </div>

    </div>

    <!-- DAFTAR BARANG -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-3 p-md-4">

            <h4 class="fw-bold mb-3">Semua Barang</h4>

            <!-- FORM PENCARIAN DAN FILTER -->
            <form method="GET" action="daftar.php"
                  class="row g-2 mb-4">

                <div class="col-md-5">
                    <input
                        type="search"
                        name="cari"
                        class="form-control"
                        placeholder="Cari nama barang..."
                        value="<?= htmlspecialchars($cari, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="col-md-3">
                    <select name="kategori" class="form-select">

                        <option value="">Semua Kategori</option>

                        <?php while ($kat = mysqli_fetch_assoc($query_kategori)): ?>

                            <option
                                value="<?= htmlspecialchars($kat['kategori'], ENT_QUOTES, 'UTF-8'); ?>"
                                <?= $kategori === $kat['kategori'] ? 'selected' : ''; ?>
                            >
                                <?= htmlspecialchars($kat['kategori'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select">

                        <option value="">Semua Status</option>

                        <option
                            value="Belum Dibeli"
                            <?= $status === 'Belum Dibeli' ? 'selected' : ''; ?>
                        >
                            Belum Dibeli
                        </option>

                        <option
                            value="Sudah Dibeli"
                            <?= $status === 'Sudah Dibeli' ? 'selected' : ''; ?>
                        >
                            Sudah Dibeli
                        </option>

                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">

                    <button type="submit"
                            class="btn btn-primary flex-grow-1">
                        Cari
                    </button>

                    <a href="daftar.php"
                       class="btn btn-outline-secondary">
                        Reset
                    </a>

                </div>

            </form>

            <!-- TABEL BARANG -->
            <div class="table-responsive">

                <table class="table wishlist-table align-middle mb-0">

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($total_hasil > 0): ?>

                            <?php $no = 1; ?>

                            <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>

                                <tr>

                                    <td><?= $no++; ?></td>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($barang['nama_barang'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($barang['kategori'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td class="text-nowrap">
                                        Rp <?= number_format((float) ($barang['harga'] ?? 0), 0, ',', '.'); ?>
                                    </td>

                                    <td>
                                        <?php if (($barang['status'] ?? '') === 'Sudah Dibeli'): ?>

                                            <span class="badge text-bg-success">
                                                Sudah Dibeli
                                            </span>

                                        <?php else: ?>

                                            <span class="badge text-bg-warning">
                                                Belum Dibeli
                                            </span>

                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2">

                                            <a
                                                href="edit.php?id=<?= (int) $barang['id_barang']; ?>"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Edit
                                            </a>

                                            <a
                                                href="hapus.php?id=<?= (int) $barang['id_barang']; ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Yakin ingin menghapus barang ini?')"
                                            >
                                                Hapus
                                            </a>

                                        </div>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6"
                                    class="text-center py-5">

                                    <h5 class="fw-bold">
                                        Barang tidak ditemukan
                                    </h5>

                                    <p class="text-secondary mb-3">
                                        Coba ubah kata kunci atau filter
                                        pencarianmu.
                                    </p>

                                    <a href="daftar.php"
                                       class="btn btn-outline-primary">
                                        Tampilkan Semua Barang
                                    </a>

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
