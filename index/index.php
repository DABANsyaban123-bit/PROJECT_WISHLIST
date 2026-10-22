<?php
include_once __DIR__ . "/../config.php";

// Menghitung total barang
$query_total = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total FROM barang
");
$total_barang = mysqli_fetch_assoc($query_total)['total'];

// Menghitung barang yang sudah dibeli
$query_sudah = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM barang
    WHERE status = 'Sudah Dibeli'
");
$sudah_dibeli = mysqli_fetch_assoc($query_sudah)['total'];

// Menghitung barang yang belum dibeli
$query_belum = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM barang
    WHERE status != 'Sudah Dibeli'
");
$belum_dibeli = mysqli_fetch_assoc($query_belum)['total'];

$persentase_progres = $total_barang > 0
    ? round(($sudah_dibeli / $total_barang) * 100)
    : 0;

// Preview 5 barang terbaru, tanpa tombol tambah/edit/hapus
$query_barang = mysqli_query($koneksi, "
    SELECT id_barang, nama_barang, kategori, harga, status
    FROM barang
    ORDER BY id_barang DESC
    LIMIT 5
");

if (!$query_barang) {
    die("Gagal mengambil data barang: " . mysqli_error($koneksi));
}

// Total pengeluaran minggu ini
$query_minggu = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEARWEEK(tanggal_pengeluaran, 1)
        = YEARWEEK(CURDATE(), 1)
");
$total_minggu = mysqli_fetch_assoc($query_minggu)['total'];

// Total pengeluaran bulan ini
$query_bulan = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEAR(tanggal_pengeluaran) = YEAR(CURDATE())
      AND MONTH(tanggal_pengeluaran) = MONTH(CURDATE())
");
$total_bulan = mysqli_fetch_assoc($query_bulan)['total'];

// Data grafik pengeluaran 7 hari terakhir
$query_grafik = mysqli_query($koneksi, "
    SELECT
        DATE(tanggal_pengeluaran) AS tanggal,
        SUM(jumlah) AS total
    FROM pengeluaran
    WHERE tanggal_pengeluaran >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND tanggal_pengeluaran < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    GROUP BY DATE(tanggal_pengeluaran)
    ORDER BY tanggal ASC
");

$label_grafik = [];
$data_grafik = [];

while ($row = mysqli_fetch_assoc($query_grafik)) {
    $label_grafik[] = date('d/m', strtotime($row['tanggal']));
    $data_grafik[] = (float) $row['total'];
}

// Lima pengeluaran terbaru
$query_pengeluaran_terbaru = mysqli_query($koneksi, "
    SELECT *
    FROM pengeluaran
    ORDER BY tanggal_pengeluaran DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Wishlist PJK</title>

    <link rel="stylesheet" href="../STYLES.css">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <style>
        .dashboard-page {
            padding-top: clamp(28px, 5vw, 52px);
            padding-bottom: 48px;
        }

        .dashboard-subtitle {
            color: var(--muted);
            margin-bottom: 0;
        }

        .dashboard-stat-card,
        .dashboard-panel {
            height: 100%;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
        }

        .dashboard-stat-card {
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .dashboard-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .dashboard-stat-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 13px;
            background: #EEF2FA;
            color: var(--navy);
            font-size: 21px;
            font-weight: 700;
        }

        .dashboard-stat-icon.gold {
            background: #FBF3E2;
            color: #A77D2D;
        }

        .dashboard-stat-icon.green {
            background: #E7F5EC;
            color: #23824D;
        }

        .dashboard-stat-value {
            color: var(--navy);
            font-size: clamp(25px, 3vw, 34px);
            font-weight: 750;
            line-height: 1.2;
        }

        .dashboard-progress {
            height: 11px;
        }

        .dashboard-panel-heading {
            padding: 22px 24px 0;
        }

        .dashboard-panel-body {
            padding: 22px 24px 24px;
        }

        .dashboard-chart {
            height: 290px;
        }

        .dashboard-table {
            margin-bottom: 0;
        }

        .dashboard-table thead th {
            color: var(--navy);
            background: #F3F5FA;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .35px;
            padding: 15px 16px;
            white-space: nowrap;
            border-bottom: 1px solid #E3E8F0;
        }

        .dashboard-table tbody td {
            padding: 15px 16px;
            border-bottom: 1px solid #EDF0F5;
            vertical-align: middle;
        }

        .dashboard-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .dashboard-table tbody tr:hover td {
            background: #FAFBFE;
        }

        .dashboard-item-name {
            color: var(--navy);
            font-weight: 650;
        }

        .dashboard-category {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 8px;
            background: #EEF1F8;
            color: #43577F;
            font-size: .78rem;
            font-weight: 600;
        }

        .dashboard-status {
            display: inline-block;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: .76rem;
            font-weight: 700;
            white-space: nowrap;
        }

        @media (max-width: 575.98px) {

            .dashboard-panel-heading,
            .dashboard-panel-body {
                padding-left: 18px;
                padding-right: 18px;
            }

            .dashboard-chart {
                height: 240px;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg wishlist-navbar">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index/index.php">
                Wishlist
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarWishlist"
                aria-controls="navbarWishlist"
                aria-expanded="false"
                aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarWishlist">
                <ul class="navbar-nav ms-auto gap-lg-2">
                    <li class="nav-item">
                        <a class="nav-link active" href="../index/index.php">
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="../index/daftar.php">
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

    <main class="container dashboard-page">

        <header class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <p class="text-uppercase small fw-bold mb-1"
                    style="color: var(--gold);">
                    Ringkasan wishlist
                </p>

                <h1 class="fw-bold mb-1">Dashboard</h1>

                <p class="dashboard-subtitle">
                    Kelola barang impian dan pantau pengeluaranmu.
                </p>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="daftar.php" class="btn btn-primary">
                    Lihat Daftar Barang
                </a>

                <a href="../pengeluaran/pengeluaran.php"
                    class="btn btn-outline-primary">
                    + Catat Pengeluaran
                </a>
            </div>
        </header>

        <!-- Statistik barang -->
        <section class="row g-3 mb-4" aria-label="Ringkasan barang">

            <div class="col-sm-6 col-xl-4">
                <div class="dashboard-stat-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="dashboard-stat-icon">▤</div>
                            <p class="text-secondary mb-0">Total Barang</p>
                        </div>

                        <div class="dashboard-stat-value">
                            <?= $total_barang ?>
                        </div>

                        <small class="text-secondary">
                            Seluruh daftar wishlist
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-4">
                <div class="dashboard-stat-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="dashboard-stat-icon green">✓</div>
                            <p class="text-secondary mb-0">Sudah Dibeli</p>
                        </div>

                        <div class="dashboard-stat-value">
                            <?= $sudah_dibeli ?>
                        </div>

                        <small class="text-secondary">
                            Barang yang sudah dimiliki
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-4">
                <div class="dashboard-stat-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="dashboard-stat-icon gold">◷</div>
                            <p class="text-secondary mb-0">Belum Dibeli</p>
                        </div>

                        <div class="dashboard-stat-value">
                            <?= $belum_dibeli ?>
                        </div>

                        <small class="text-secondary">
                            Barang yang masih diinginkan
                        </small>
                    </div>
                </div>
            </div>

        </section>

        <!-- Progres wishlist -->
        <section class="dashboard-panel mb-4">
            <div class="dashboard-panel-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Progres Wishlist</h2>
                        <p class="text-secondary small mb-0">
                            Persentase barang yang sudah dibeli
                        </p>
                    </div>

                    <span class="fw-bold" style="color: var(--navy);">
                        <?= $persentase_progres ?>%
                    </span>
                </div>

                <div
                    class="progress dashboard-progress"
                    role="progressbar"
                    aria-valuenow="<?= $persentase_progres ?>"
                    aria-valuemin="0"
                    aria-valuemax="100">

                    <div
                        class="progress-bar"
                        style="width: <?= (int) $persentase_progres ?>%; background-color: #253C6D;">
                    </div>
                </div>

                <p class="small text-secondary mt-2 mb-0">
                    <?= $sudah_dibeli ?> dari <?= $total_barang ?> barang sudah dibeli.
                </p>
            </div>
        </section>

        <!-- Statistik pengeluaran -->
        <section class="row g-3 mb-4" aria-label="Ringkasan pengeluaran">

            <div class="col-md-6">
                <div class="dashboard-stat-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="dashboard-stat-icon gold">Rp</div>

                            <div>
                                <p class="text-secondary mb-1">
                                    Pengeluaran Minggu Ini
                                </p>
                                <small class="text-secondary">
                                    Akumulasi minggu berjalan
                                </small>
                            </div>
                        </div>

                        <div class="dashboard-stat-value">
                            Rp <?= number_format((float) $total_minggu, 0, ',', '.') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="dashboard-stat-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="dashboard-stat-icon">▤</div>

                            <div>
                                <p class="text-secondary mb-1">
                                    Pengeluaran Bulan Ini
                                </p>
                                <small class="text-secondary">
                                    Akumulasi bulan berjalan
                                </small>
                            </div>
                        </div>

                        <div class="dashboard-stat-value">
                            Rp <?= number_format((float) $total_bulan, 0, ',', '.') ?>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <!-- Grafik -->
        <section class="dashboard-panel mb-4" aria-labelledby="chartTitle">
            <div class="dashboard-panel-heading">
                <h2 id="chartTitle" class="h5 fw-bold mb-1">
                    Grafik Pengeluaran
                </h2>

                <p class="text-secondary small mb-0">
                    Pengeluaran selama 7 hari terakhir
                </p>
            </div>

            <div class="dashboard-panel-body">
                <div class="dashboard-chart">
                    <canvas id="grafikPengeluaran"></canvas>
                </div>
            </div>
        </section>

        <!-- Tabel barang dan pengeluaran terbaru -->
        <section class="row g-4">

            <div class="col-xl-7">
                <div class="dashboard-panel">

                    <div class="dashboard-panel-heading d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Daftar Barang</h2>
                            <p class="text-secondary small mb-0">
                                Barang wishlist terbaru
                            </p>
                        </div>

                        <a href="daftar.php"
                            class="btn btn-sm btn-outline-primary">
                            Lihat Semua
                        </a>
                    </div>

                    <div class="dashboard-panel-body">
                        <div class="table-responsive">

                            <table class="table table-hover align-middle wishlist-table dashboard-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama Barang</th>
                                        <th>Kategori</th>
                                        <th>Harga</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (mysqli_num_rows($query_barang) > 0): ?>
                                        <?php $no = 1; ?>

                                        <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>
                                            <tr>
                                                <td><?= $no++ ?></td>

                                                <td class="dashboard-item-name">
                                                    <?= htmlspecialchars(
                                                        $barang['nama_barang'] ?? '-',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <span class="dashboard-category">
                                                        <?= htmlspecialchars(
                                                            $barang['kategori'] ?? 'Tanpa kategori',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </span>
                                                </td>

                                                <td class="text-nowrap">
                                                    Rp <?= number_format(
                                                            (float) $barang['harga'],
                                                            0,
                                                            ',',
                                                            '.'
                                                        ) ?>
                                                </td>

                                                <td>
                                                    <?php if ($barang['status'] === 'Sudah Dibeli'): ?>
                                                        <span
                                                            class="dashboard-status"
                                                            style="background:#E5F5EB;color:#237447;">
                                                            ✓ Sudah Dibeli
                                                        </span>
                                                    <?php else: ?>
                                                        <span
                                                            class="dashboard-status"
                                                            style="background:#FFF3D9;color:#936713;">
                                                            ◷ Belum Dibeli
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>

                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5"
                                                class="text-center text-secondary py-4">
                                                Belum ada barang. Tambahkan barang melalui halaman Daftar Barang.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="dashboard-panel">

                    <div class="dashboard-panel-heading d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1">
                                Pengeluaran Terbaru
                            </h2>

                            <p class="text-secondary small mb-0">
                                Catatan pengeluaran terakhir
                            </p>
                        </div>

                        <a
                            href="../pengeluaran/pengeluaran.php"
                            class="btn btn-sm btn-outline-primary">
                            Lihat Semua
                        </a>
                    </div>

                    <div class="dashboard-panel-body">
                        <div class="table-responsive">

                            <table class="table align-middle dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Keterangan</th>
                                        <th>Jumlah</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (mysqli_num_rows($query_pengeluaran_terbaru) > 0): ?>
                                        <?php while ($pengeluaran = mysqli_fetch_assoc($query_pengeluaran_terbaru)): ?>
                                            <tr>
                                                <td class="text-nowrap text-secondary small">
                                                    <?= htmlspecialchars(
                                                        $pengeluaran['tanggal_pengeluaran'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </td>

                                                <td class="fw-semibold">
                                                    <?= htmlspecialchars(
                                                        $pengeluaran['nama_pengeluaran'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </td>

                                                <td class="text-nowrap fw-bold"
                                                    style="color: var(--navy);">
                                                    Rp <?= number_format(
                                                            (float) $pengeluaran['jumlah'],
                                                            0,
                                                            ',',
                                                            '.'
                                                        ) ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>

                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3"
                                                class="text-center text-secondary py-4">
                                                Belum ada data pengeluaran.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>

        </section>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const labelGrafik = <?= json_encode($label_grafik) ?>;
        const dataGrafik = <?= json_encode($data_grafik) ?>;

        const canvas = document.getElementById('grafikPengeluaran');

        if (canvas) {
            new Chart(canvas, {
                type: 'bar',

                data: {
                    labels: labelGrafik,

                    datasets: [{
                        label: 'Pengeluaran (Rp)',
                        data: dataGrafik,
                        backgroundColor: 'rgba(37, 60, 109, 0.88)',
                        borderColor: '#D6AE58',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    scales: {
                        x: {
                            grid: {
                                display: false
                            },

                            ticks: {
                                color: '#748096'
                            }
                        },

                        y: {
                            beginAtZero: true,

                            grid: {
                                color: '#E8EAF0'
                            },

                            ticks: {
                                color: '#748096',

                                callback: function(value) {
                                    return 'Rp ' +
                                        new Intl.NumberFormat('id-ID')
                                        .format(value);
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>

</body>

</html>