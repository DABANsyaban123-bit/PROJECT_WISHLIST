
<?php
include_once __DIR__ . "/../config.php";

// Helper untuk mengamankan output HTML
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Ambil filter dari URL
$cari = trim($_GET['cari'] ?? '');
$kategori = trim($_GET['kategori'] ?? '');
$status = trim($_GET['status'] ?? '');

// Ambil daftar kategori
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

// Ambil statistik keseluruhan
$query_statistik = mysqli_query(
    $koneksi,
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status = 'Sudah Dibeli' THEN 1 ELSE 0 END), 0) AS dibeli,
        COALESCE(SUM(CASE WHEN status = 'Belum Dibeli' THEN 1 ELSE 0 END), 0) AS belum
     FROM barang"
);

if (!$query_statistik) {
    die("Gagal mengambil statistik: " . mysqli_error($koneksi));
}

$statistik = mysqli_fetch_assoc($query_statistik);
$total_semua = (int) $statistik['total'];
$total_dibeli = (int) $statistik['dibeli'];
$total_belum = (int) $statistik['belum'];

// Buat query daftar barang berdasarkan filter
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

    <title>Daftar Barang | Wishlist</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../STYLES.css">

    <style>
        :root {
            --wishlist-navy: #253C6D;
            --wishlist-navy-light: #30497D;
            --wishlist-gold: #D6AE58;
            --wishlist-cream: #F7F4EC;
            --wishlist-text: #24324A;
        }

        body {
            background: var(--wishlist-cream);
            color: var(--wishlist-text);
            min-height: 100vh;
        }

        .wishlist-navbar {
            background: var(--wishlist-navy);
            box-shadow: 0 4px 18px rgba(37, 60, 109, .12);
        }

        .wishlist-navbar .navbar-brand {
            color: #fff;
            letter-spacing: .3px;
        }

        .wishlist-navbar .nav-link {
            color: rgba(255, 255, 255, .78);
            border-radius: 9px;
            padding: 9px 14px;
            transition: .2s ease;
        }

        .wishlist-navbar .nav-link:hover,
        .wishlist-navbar .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, .12);
        }

        .wishlist-navbar .navbar-toggler {
            border-color: rgba(255, 255, 255, .35);
        }

        .wishlist-navbar .navbar-toggler-icon {
            filter: brightness(0) invert(1);
        }

        .page-eyebrow {
            color: #98742E;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        .page-title {
            color: var(--wishlist-navy);
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .btn-wishlist {
            background: var(--wishlist-navy);
            color: #fff;
            border: 1px solid var(--wishlist-navy);
            border-radius: 11px;
            padding: 11px 17px;
            font-weight: 600;
        }

        .btn-wishlist:hover {
            background: var(--wishlist-navy-light);
            color: #fff;
        }

        .btn-gold {
            background: var(--wishlist-gold);
            color: #253047;
            border: 1px solid var(--wishlist-gold);
            border-radius: 10px;
            font-weight: 700;
        }

        .btn-gold:hover {
            background: #c69b3e;
            color: #202d45;
        }

        .summary-card,
        .content-card {
            background: #fff;
            border: 1px solid rgba(37, 60, 109, .07);
            border-radius: 19px;
            box-shadow: 0 8px 28px rgba(37, 60, 109, .055);
        }

        .summary-card {
            height: 100%;
            padding: 22px;
            position: relative;
            overflow: hidden;
        }

        .summary-card::after {
            content: "";
            position: absolute;
            right: -20px;
            top: -24px;
            width: 85px;
            height: 85px;
            border-radius: 50%;
            background: var(--accent, rgba(214, 174, 88, .13));
        }

        .summary-label {
            color: #7B8495;
            font-size: .88rem;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .summary-number {
            color: var(--wishlist-navy);
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .summary-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--icon-bg, #F5F0E2);
            color: var(--icon-color, #98742E);
            font-size: 1.2rem;
            margin-bottom: 18px;
        }

        .content-card {
            overflow: hidden;
        }

        .section-heading {
            color: var(--wishlist-navy);
            font-weight: 800;
        }

        .filter-panel {
            background: #F8F9FC;
            border: 1px solid #E9EDF4;
            border-radius: 14px;
            padding: 16px;
        }

        .form-control,
        .form-select {
            min-height: 44px;
            border-radius: 10px;
            border-color: #DDE3ED;
            font-size: .91rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--wishlist-gold);
            box-shadow: 0 0 0 .2rem rgba(214, 174, 88, .17);
        }

        .result-count {
            background: #F5F0E2;
            color: #866626;
            border-radius: 999px;
            padding: 6px 11px;
            font-size: .8rem;
            font-weight: 700;
        }

        .wishlist-table {
            margin-bottom: 0;
        }

        .wishlist-table thead th {
            background: #F7F8FB;
            color: #68748A;
            border-bottom: 1px solid #E8ECF3;
            padding: 15px 16px;
            font-size: .76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
        }

        .wishlist-table tbody td {
            padding: 17px 16px;
            border-bottom: 1px solid #F0F2F6;
            vertical-align: middle;
        }

        .wishlist-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .wishlist-table tbody tr:hover {
            background: #FCFBF7;
        }

        .item-name {
            color: var(--wishlist-navy);
            font-weight: 700;
        }

        .category-badge {
            display: inline-block;
            background: #EEF1F8;
            color: #43577F;
            border-radius: 8px;
            padding: 6px 9px;
            font-size: .78rem;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: .76rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-bought {
            background: #E5F5EB;
            color: #237447;
        }

        .status-pending {
            background: #FFF3D9;
            color: #936713;
        }

        .action-btn {
            min-width: 60px;
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 600;
        }

        .empty-state {
            padding: 55px 20px !important;
            text-align: center;
        }

        .empty-icon {
            width: 62px;
            height: 62px;
            display: grid;
            place-items: center;
            border-radius: 19px;
            background: #F5F0E2;
            color: #98742E;
            font-size: 1.7rem;
            margin: 0 auto 16px;
        }

        .page-footer {
            color: #8992A2;
            font-size: .8rem;
        }

        @media (max-width: 767px) {
            .page-title {
                font-size: 1.7rem;
            }

            .summary-card {
                padding: 18px;
            }

            .summary-number {
                font-size: 1.7rem;
            }

            .wishlist-table thead th,
            .wishlist-table tbody td {
                padding: 13px 12px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg wishlist-navbar">
    <div class="container py-1">
        <a class="navbar-brand fw-bold" href="index.php">
            Wishlist <span style="color: var(--wishlist-gold)"></span>
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
            <ul class="navbar-nav ms-auto gap-lg-2 mt-3 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Dashboard</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active" href="daftar.php">Daftar Barang</a>
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

<main class="container py-4 py-md-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="page-eyebrow mb-2">Koleksi Wishlist Kamu</div>
            <h1 class="page-title h2 mb-2">Daftar Barang</h1>
            <p class="text-secondary mb-0">
                Kelola barang impianmu dan pantau progres pembeliannya.
            </p>
        </div>

        <a href="tambah.php" class="btn btn-wishlist">
            <span class="me-1">+</span> Tambah Barang
        </a>
    </div>

    <!-- Statistik -->
    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6 col-lg-4">
            <div class="summary-card" style="--accent: rgba(214,174,88,.13)">
                <div class="summary-icon">▤</div>
                <div class="summary-label">Total Seluruh Barang</div>
                <div class="summary-number"><?= $total_semua ?></div>
                <div class="text-secondary small mt-2">
                    Semua barang di wishlist
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-4">
            <div class="summary-card"
                 style="--accent: rgba(43,153,94,.10); --icon-bg:#E5F5EB; --icon-color:#237447">
                <div class="summary-icon">✓</div>
                <div class="summary-label">Sudah Dibeli</div>
                <div class="summary-number"><?= $total_dibeli ?></div>
                <div class="text-secondary small mt-2">
                    Barang yang berhasil dimiliki
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-4">
            <div class="summary-card"
                 style="--accent: rgba(214,174,88,.13); --icon-bg:#FFF3D9; --icon-color:#936713">
                <div class="summary-icon">◷</div>
                <div class="summary-label">Belum Dibeli</div>
                <div class="summary-number"><?= $total_belum ?></div>
                <div class="text-secondary small mt-2">
                    Barang yang masih diincar
                </div>
            </div>
        </div>

    </div>

    <!-- Tabel dan filter -->
    <section class="content-card">

        <div class="p-3 p-md-4">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h2 class="section-heading h5 mb-1">Semua Barang</h2>
                    <p class="text-secondary small mb-0">
                        Cari dan filter barang sesuai kebutuhanmu.
                    </p>
                </div>

                <span class="result-count">
                    <?= $total_hasil ?> hasil
                </span>
            </div>

            <form method="GET" action="daftar.php" class="filter-panel mb-4">
                <div class="row g-3">

                    <div class="col-12 col-lg-4">
                        <label for="cari" class="form-label small fw-semibold">
                            Cari nama barang
                        </label>
                        <input
                            type="search"
                            id="cari"
                            name="cari"
                            class="form-control"
                            placeholder="Contoh: Headphone..."
                            value="<?= e($cari) ?>"
                        >
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <label for="kategori" class="form-label small fw-semibold">
                            Kategori
                        </label>
                        <select name="kategori" id="kategori" class="form-select">
                            <option value="">Semua Kategori</option>

                            <?php while ($kat = mysqli_fetch_assoc($query_kategori)): ?>
                                <option
                                    value="<?= e($kat['kategori']) ?>"
                                    <?= $kategori === $kat['kategori'] ? 'selected' : '' ?>
                                >
                                    <?= e($kat['kategori']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <label for="status" class="form-label small fw-semibold">
                            Status Pembelian
                        </label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="Belum Dibeli"
                                <?= $status === 'Belum Dibeli' ? 'selected' : '' ?>>
                                Belum Dibeli
                            </option>
                            <option value="Sudah Dibeli"
                                <?= $status === 'Sudah Dibeli' ? 'selected' : '' ?>>
                                Sudah Dibeli
                            </option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-wishlist flex-grow-1">
                            Cari
                        </button>
                        <a href="daftar.php"
                           class="btn btn-outline-secondary"
                           title="Reset filter">
                            Reset
                        </a>
                    </div>

                </div>
            </form>

            <div class="table-responsive">
                <table class="table wishlist-table align-middle">
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
                            <?php
                                $sudah_dibeli = ($barang['status'] ?? '') === 'Sudah Dibeli';
                            ?>

                            <tr>
                                <td class="text-secondary small">
                                    <?= $no++ ?>
                                </td>

                                <td>
                                    <div class="item-name">
                                        <?= e($barang['nama_barang'] ?? '-') ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="category-badge">
                                        <?= e($barang['kategori'] ?: 'Tanpa kategori') ?>
                                    </span>
                                </td>

                                <td class="text-nowrap fw-semibold">
                                    Rp <?= number_format((float) ($barang['harga'] ?? 0), 0, ',', '.') ?>
                                </td>

                                <td>
                                    <span class="status-badge <?= $sudah_dibeli ? 'status-bought' : 'status-pending' ?>">
                                        <?= $sudah_dibeli ? 'Sudah Dibeli' : 'Belum Dibeli' ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a
                                            href="edit.php?id=<?= (int) $barang['id_barang'] ?>"
                                            class="btn btn-sm btn-outline-primary action-btn"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="hapus.php?id=<?= (int) $barang['id_barang'] ?>"
                                            onsubmit="return confirm('Yakin ingin menghapus barang ini?')"
                                        >
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $barang['id_barang'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger action-btn"
                                            >
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <div class="empty-icon">⌕</div>
                                <h3 class="section-heading h5">
                                    Barang tidak ditemukan
                                </h3>
                                <p class="text-secondary mb-3">
                                    Coba kata kunci atau filter yang berbeda.
                                </p>
                                <a href="daftar.php" class="btn btn-gold px-4 py-2">
                                    Tampilkan Semua Barang
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    <footer class="page-footer text-center mt-4">
        Wishlist PJK &copy; <?= date('Y') ?> · Kelola wishlist dengan lebih teratur.
    </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
