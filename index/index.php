
<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
auth_require_login();

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function rupiah($angka) {
    return "Rp " . number_format((float) $angka, 0, ",", ".");
}

$query_total = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Sudah Dibeli'), 0) AS sudah,
        COALESCE(SUM(status = 'Belum Dibeli'), 0) AS belum,
        COALESCE(SUM(jenis = 'Kebutuhan'), 0) AS kebutuhan,
        COALESCE(SUM(jenis = 'Keinginan'), 0) AS keinginan
    FROM barang
");

$stat = mysqli_fetch_assoc($query_total);

$total_barang = (int) ($stat["total"] ?? 0);
$sudah_dibeli = (int) ($stat["sudah"] ?? 0);
$belum_dibeli = (int) ($stat["belum"] ?? 0);
$total_kebutuhan = (int) ($stat["kebutuhan"] ?? 0);
$total_keinginan = (int) ($stat["keinginan"] ?? 0);

$persentase = $total_barang > 0
    ? round(($sudah_dibeli / $total_barang) * 100)
    : 0;

// Pengeluaran minggu ini.
$query_minggu = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEARWEEK(tanggal_pengeluaran, 1)
        = YEARWEEK(CURDATE(), 1)
");
$total_minggu = (float) mysqli_fetch_assoc($query_minggu)["total"];

// Pengeluaran bulan ini.
$query_bulan = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEAR(tanggal_pengeluaran) = YEAR(CURDATE())
      AND MONTH(tanggal_pengeluaran) = MONTH(CURDATE())
");
$total_bulan = (float) mysqli_fetch_assoc($query_bulan)["total"];

// Barang terbaru yang masih aktif.
$query_kebutuhan = mysqli_query($koneksi, "
    SELECT b.*
    FROM barang b
    WHERE b.jenis = 'Kebutuhan'
      AND NOT EXISTS (
          SELECT 1
          FROM pengeluaran p
          WHERE p.id_barang = b.id_barang
      )
    ORDER BY b.id_barang DESC
    LIMIT 5
");

$query_keinginan = mysqli_query($koneksi, "
    SELECT b.*
    FROM barang b
    WHERE b.jenis = 'Keinginan'
      AND NOT EXISTS (
          SELECT 1
          FROM pengeluaran p
          WHERE p.id_barang = b.id_barang
      )
    ORDER BY b.id_barang DESC
    LIMIT 5
");

// Pengeluaran terbaru.
$query_pengeluaran = mysqli_query($koneksi, "
    SELECT *
    FROM pengeluaran
    ORDER BY tanggal_pengeluaran DESC, id_pengeluaran DESC
    LIMIT 5
");

// Grafik pengeluaran tujuh hari terakhir.
$query_grafik = mysqli_query($koneksi, "
    SELECT
        DATE(tanggal_pengeluaran) AS tanggal,
        COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE tanggal_pengeluaran >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND tanggal_pengeluaran < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    GROUP BY DATE(tanggal_pengeluaran)
    ORDER BY tanggal ASC
");

$data_grafik = [];

while ($row = mysqli_fetch_assoc($query_grafik)) {
    $data_grafik[$row["tanggal"]] = (float) $row["total"];
}

$label_grafik = [];
$nilai_grafik = [];

for ($i = 6; $i >= 0; $i--) {
    $tanggal = date("Y-m-d", strtotime("-" . $i . " days"));
    $label_grafik[] = date("d/m", strtotime($tanggal));
    $nilai_grafik[] = $data_grafik[$tanggal] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Wishlist PJK</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    
<style>
    :root {
        --navy: #263f70;
        --navy-dark: #20365f;
        --gold: #a77b2e;
        --cream: #f7f4ec;
        --muted: #7b879b;
        --border: #edf0f5;
    }

    body {
        background: var(--cream) !important;
        color: #171717;
        font-family: Arial, Helvetica, sans-serif;
    }

    .navbar-custom {
        background: var(--navy) !important;
        min-height: 82px;
        padding: 12px 0 !important;
        box-shadow: 0 3px 12px rgba(30, 48, 80, .08);
    }

    .navbar-brand {
        font-size: 23px !important;
        font-weight: 800 !important;
        color: white !important;
    }

    .brand-mark {
        display: none !important;
    }

    .navbar-custom .nav-link {
        color: white !important;
        font-size: 16px;
        padding: 14px 18px !important;
        margin-left: 8px !important;
        border-radius: 11px !important;
        transition: background .2s ease;
    }

    .navbar-custom .nav-link:hover,
    .navbar-custom .nav-link.active {
        background: rgba(255, 255, 255, .14) !important;
    }

    .page-container {
        max-width: 1600px !important;
        padding: 48px 30px 50px !important;
    }

    .page-heading {
        margin-bottom: 34px !important;
    }

    .page-heading h1 {
        color: var(--navy) !important;
        font-size: 36px !important;
        font-weight: 800 !important;
        letter-spacing: -.8px;
    }

    .page-heading p {
        color: var(--muted) !important;
        font-size: 16px !important;
        line-height: 1.7;
    }

    .btn-gold {
        background: var(--navy) !important;
        border-color: var(--navy) !important;
        color: white !important;
        border-radius: 13px !important;
        padding: 17px 23px !important;
        font-size: 16px;
        font-weight: 700;
        box-shadow: none !important;
    }

    .btn-gold:hover {
        background: var(--navy-dark) !important;
        border-color: var(--navy-dark) !important;
        color: white !important;
        transform: translateY(-1px);
    }

    .stat-card,
    .panel {
        background: white !important;
        border: 1px solid #f0f1f4 !important;
        border-radius: 23px !important;
        box-shadow: 0 12px 35px rgba(35, 50, 80, .045) !important;
    }

    .stat-card {
        padding: 28px !important;
        min-height: 175px;
        align-items: flex-start !important;
        flex-wrap: wrap;
        gap: 18px !important;
    }

    .stat-icon {
        width: 55px !important;
        height: 55px !important;
        border-radius: 16px !important;
        font-size: 23px !important;
    }

    .icon-navy {
        background: #edf2fb !important;
        color: var(--navy) !important;
    }

    .icon-gold {
        background: #fbf2df !important;
        color: #b17c21 !important;
    }

    .icon-green {
        background: #e6f5ec !important;
        color: #228452 !important;
    }

    .icon-blue {
        background: #eaf1fc !important;
        color: #31588d !important;
    }

    .stat-label {
        font-size: 15px !important;
        color: #758195 !important;
    }

    .stat-number {
        font-size: 29px !important;
        color: var(--navy) !important;
        font-weight: 800 !important;
    }

    .panel {
        padding: 28px !important;
        margin-bottom: 28px !important;
    }

    .panel-heading {
        margin-bottom: 24px !important;
    }

    .panel-heading h5 {
        font-size: 21px !important;
        color: var(--navy) !important;
        font-weight: 800 !important;
    }

    .panel-heading a {
        color: var(--navy) !important;
        font-weight: 700;
    }

    .form-control,
    .form-select {
        min-height: 52px !important;
        border: 1px solid #d9dfeb !important;
        border-radius: 13px !important;
        padding: 12px 15px !important;
        font-size: 15px !important;
        background-color: white !important;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #7288b2 !important;
        box-shadow: 0 0 0 3px rgba(38, 63, 112, .09) !important;
    }

    .btn-filter {
        background: var(--navy) !important;
        border-radius: 12px !important;
        min-height: 52px !important;
    }

    .btn-reset {
        border-radius: 12px !important;
        min-height: 52px !important;
    }

    .table-responsive {
        border-radius: 14px;
    }

    .table {
        color: #161616 !important;
    }

    .table thead th {
        background: #f6f7fa !important;
        color: var(--navy) !important;
        padding: 20px 18px !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
        border-bottom: 1px solid #e3e7ef !important;
    }

    .table tbody td {
        padding: 19px 18px !important;
        font-size: 14px !important;
        border-bottom: 1px solid #edf0f4 !important;
    }

    .table tbody tr:last-child td {
        border-bottom: none !important;
    }

    .table tbody tr:hover {
        background: #fbfcfe;
    }

    .money {
        color: #171717 !important;
        font-weight: 800 !important;
    }

    .badge-status {
        padding: 8px 12px !important;
        border-radius: 9px !important;
    }

    .section-title h5 {
        color: var(--navy) !important;
        font-size: 19px !important;
    }

    .section-icon {
        border-radius: 13px !important;
    }

    .section-count {
        border-radius: 10px !important;
        padding: 8px 12px !important;
    }

    .action-btn {
        width: auto !important;
        min-width: 48px;
        height: 39px !important;
        padding: 0 11px;
        border-radius: 10px !important;
        font-size: 14px;
        background: white !important;
    }

    .edit-btn {
        border-color: var(--navy) !important;
        color: var(--navy) !important;
    }

    .delete-btn {
        border-color: #ef4444 !important;
        color: #ef4444 !important;
    }

    .edit-btn:hover {
        background: #edf2fb !important;
    }

    .delete-btn:hover {
        background: #fff0f0 !important;
    }

    .progress {
        height: 12px !important;
        border-radius: 20px !important;
    }

    .progress-bar {
        background: var(--gold) !important;
    }

    .type-summary {
        border-radius: 14px !important;
        padding: 19px !important;
        background: #fcfcfd !important;
    }

    .footer-note {
        color: var(--muted);
        padding: 18px 0;
    }

    @media (max-width: 767px) {
        .page-container {
            padding: 28px 15px !important;
        }

        .page-heading h1 {
            font-size: 29px !important;
        }

        .stat-card {
            min-height: 140px;
            padding: 18px !important;
            gap: 12px !important;
        }

        .stat-number {
            font-size: 22px !important;
        }

        .panel {
            padding: 18px !important;
        }

        .table thead th,
        .table tbody td {
            padding: 14px 12px !important;
        }
    }
</style>
    <link rel="stylesheet" href="../UI.css">

</head>

<body>

<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="index.php">
            <span class="brand-mark"><i class="bi bi-bag-heart-fill"></i></span>
            Wishlist PJK
        </a>

        <button class="navbar-toggler bg-light" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">
                        <i class="bi bi-grid-1x2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="daftar.php">
                        <i class="bi bi-list-check me-1"></i> Daftar Barang
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../pengeluaran/pengeluaran.php">
                        <i class="bi bi-wallet2 me-1"></i> Pengeluaran
                    </a>
                </li>
                <li class="nav-item ms-lg-2">
                    <form method="POST" action="../logout.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                        <button class="nav-logout" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Keluar</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="page-container">

    <div class="page-heading">
        <div>
            <h1>Dashboard</h1>
            <p>Kelola daftar belanja dan pantau pengeluaranmu dari satu tempat.</p>
        </div>
        <a href="daftar.php" class="btn btn-gold">
            <i class="bi bi-list-check me-1"></i> Kelola Daftar Barang
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-navy"><i class="bi bi-bag"></i></div>
                <div>
                    <div class="stat-label">Total Barang</div>
                    <div class="stat-number"><?= $total_barang ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-label">Sudah Dibeli</div>
                    <div class="stat-number"><?= $sudah_dibeli ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="stat-label">Belum Dibeli</div>
                    <div class="stat-number"><?= $belum_dibeli ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="bi bi-wallet2"></i></div>
                <div>
                    <div class="stat-label">Pengeluaran Bulan Ini</div>
                    <div class="stat-number" style="font-size:19px">
                        <?= rupiah($total_bulan) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-1">
        <div class="col-lg-7">
            <section class="panel">
                <div class="panel-heading">
                    <h5><i class="bi bi-graph-up-arrow me-2"></i>Progres Wishlist</h5>
                    <span class="badge text-bg-light"><?= $persentase ?>%</span>
                </div>

                <p class="text-secondary small mb-3">
                    Progres pembelian dari seluruh barang di daftar.
                </p>

                <div class="progress">
                    <div class="progress-bar" role="progressbar"
                         style="width: <?= $persentase ?>%"
                         aria-valuenow="<?= $persentase ?>"
                         aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="progress-meta">
                    <span><?= $sudah_dibeli ?> dari <?= $total_barang ?> barang</span>
                    <span><?= $persentase ?>% selesai</span>
                </div>

                <hr class="my-4" style="border-color:var(--border)">

                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="type-summary">
                            <div>
                                <strong><i class="bi bi-shield-check me-1"></i>Kebutuhan</strong>
                                <small>Barang kebutuhan aktif</small>
                            </div>
                            <div class="type-number"><?= $total_kebutuhan ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="type-summary">
                            <div>
                                <strong><i class="bi bi-stars me-1"></i>Keinginan</strong>
                                <small>Barang keinginan aktif</small>
                            </div>
                            <div class="type-number"><?= $total_keinginan ?></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="panel">
                <div class="panel-heading">
                    <h5><i class="bi bi-wallet2 me-2"></i>Ringkasan Pengeluaran</h5>
                    <a href="../pengeluaran/pengeluaran.php">Lihat semua</a>
                </div>

                <div class="type-summary mb-3">
                    <div>
                        <strong>Pengeluaran minggu ini</strong>
                        <small>Total dari Senin sampai Minggu</small>
                    </div>
                    <div class="text-end">
                        <div class="type-number" style="font-size:18px">
                            <?= rupiah($total_minggu) ?>
                        </div>
                    </div>
                </div>

                <div class="type-summary">
                    <div>
                        <strong>Pengeluaran bulan ini</strong>
                        <small>Total bulan berjalan</small>
                    </div>
                    <div class="text-end">
                        <div class="type-number" style="font-size:18px">
                            <?= rupiah($total_bulan) ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="panel">
        <div class="panel-heading">
            <h5><i class="bi bi-bar-chart-line me-2"></i>Grafik Pengeluaran 7 Hari Terakhir</h5>
        </div>
        <div style="height:280px">
            <canvas id="grafikPengeluaran"></canvas>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h5><i class="bi bi-shield-check me-2"></i>Daftar Kebutuhan</h5>
            <a href="daftar.php?jenis=Kebutuhan">Lihat semua</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($query_kebutuhan) > 0): ?>
                    <?php while ($barang = mysqli_fetch_assoc($query_kebutuhan)): ?>
                        <tr>
                            <td><strong><?= e($barang["nama_barang"]) ?></strong></td>
                            <td><?= e($barang["kategori"]) ?></td>
                            <td class="money"><?= rupiah($barang["harga"]) ?></td>
                            <td>
                                <span class="badge-status <?= $barang["status"] === "Sudah Dibeli" ? "status-sudah" : "status-belum" ?>">
                                    <?= e($barang["status"]) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state">Belum ada kebutuhan aktif.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h5><i class="bi bi-stars me-2"></i>Daftar Keinginan</h5>
            <a href="daftar.php?jenis=Keinginan">Lihat semua</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($query_keinginan) > 0): ?>
                    <?php while ($barang = mysqli_fetch_assoc($query_keinginan)): ?>
                        <tr>
                            <td><strong><?= e($barang["nama_barang"]) ?></strong></td>
                            <td><?= e($barang["kategori"]) ?></td>
                            <td class="money"><?= rupiah($barang["harga"]) ?></td>
                            <td>
                                <span class="badge-status <?= $barang["status"] === "Sudah Dibeli" ? "status-sudah" : "status-belum" ?>">
                                    <?= e($barang["status"]) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state">Belum ada keinginan aktif.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h5><i class="bi bi-clock-history me-2"></i>Pengeluaran Terbaru</h5>
            <a href="../pengeluaran/pengeluaran.php">Lihat semua</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nama Pengeluaran</th>
                        <th>Catatan</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($query_pengeluaran) > 0): ?>
                    <?php while ($pengeluaran = mysqli_fetch_assoc($query_pengeluaran)): ?>
                        <tr>
                            <td><?= e(date("d/m/Y", strtotime($pengeluaran["tanggal_pengeluaran"]))) ?></td>
                            <td><strong><?= e($pengeluaran["nama_pengeluaran"]) ?></strong></td>
                            <td><?= e($pengeluaran["catatan"] ?: "-") ?></td>
                            <td class="money"><?= rupiah($pengeluaran["jumlah"]) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state">Belum ada catatan pengeluaran.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="footer-note">Wishlist PJK &mdash; Catat kebutuhan, rencanakan keinginan, kelola pengeluaran.</div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labelGrafik = <?= json_encode($label_grafik) ?>;
const nilaiGrafik = <?= json_encode($nilai_grafik) ?>;

new Chart(document.getElementById("grafikPengeluaran"), {
    type: "line",
    data: {
        labels: labelGrafik,
        datasets: [{
            label: "Pengeluaran",
            data: nilaiGrafik,
            borderColor: "#d8ad55",
            backgroundColor: "rgba(216, 173, 85, 0.13)",
            fill: true,
            tension: 0.35,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return "Rp " + Number(context.raw).toLocaleString("id-ID");
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return "Rp " + Number(value).toLocaleString("id-ID");
                    }
                },
                grid: { color: "#edf0f5" }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
</body>
</html>
