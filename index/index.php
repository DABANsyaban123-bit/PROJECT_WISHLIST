
<?php
include_once __DIR__ . "/../config.php";

function aman($nilai) {
    return htmlspecialchars((string)($nilai ?? ""), ENT_QUOTES, "UTF-8");
}

// Hanya menghitung barang aktif, yaitu barang yang belum dicatat sebagai pengeluaran.
$sql_aktif = "
    SELECT b.*
    FROM barang b
    WHERE NOT EXISTS (
        SELECT 1
        FROM pengeluaran p
        WHERE p.id_barang = b.id_barang
    )
";

// Statistik barang aktif
$query_total = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM barang b
    WHERE NOT EXISTS (
        SELECT 1 FROM pengeluaran p
        WHERE p.id_barang = b.id_barang
    )
");
$total_barang = (int) mysqli_fetch_assoc($query_total)["total"];

$query_belum = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM barang b
    WHERE b.status = 'Belum Dibeli'
    AND NOT EXISTS (
        SELECT 1 FROM pengeluaran p
        WHERE p.id_barang = b.id_barang
    )
");
$belum_dibeli = (int) mysqli_fetch_assoc($query_belum)["total"];

$query_sudah = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM barang b
    WHERE b.status = 'Sudah Dibeli'
    AND NOT EXISTS (
        SELECT 1 FROM pengeluaran p
        WHERE p.id_barang = b.id_barang
    )
");
$sudah_dibeli = (int) mysqli_fetch_assoc($query_sudah)["total"];

$query_kebutuhan = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM barang b
    WHERE b.jenis = 'Kebutuhan'
    AND NOT EXISTS (
        SELECT 1 FROM pengeluaran p
        WHERE p.id_barang = b.id_barang
    )
");
$total_kebutuhan = (int) mysqli_fetch_assoc($query_kebutuhan)["total"];

$query_keinginan = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM barang b
    WHERE b.jenis = 'Keinginan'
    AND NOT EXISTS (
        SELECT 1 FROM pengeluaran p
        WHERE p.id_barang = b.id_barang
    )
");
$total_keinginan = (int) mysqli_fetch_assoc($query_keinginan)["total"];

$persentase = $total_barang > 0
    ? round(($sudah_dibeli / $total_barang) * 100)
    : 0;

// Total pengeluaran minggu ini
$query_minggu = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEARWEEK(tanggal_pengeluaran, 1) = YEARWEEK(CURDATE(), 1)
");
$total_minggu = (float) mysqli_fetch_assoc($query_minggu)["total"];

// Total pengeluaran bulan ini
$query_bulan = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEAR(tanggal_pengeluaran) = YEAR(CURDATE())
      AND MONTH(tanggal_pengeluaran) = MONTH(CURDATE())
");
$total_bulan = (float) mysqli_fetch_assoc($query_bulan)["total"];

// Daftar barang aktif terbaru
$query_barang = mysqli_query($koneksi, "
    SELECT b.id_barang, b.nama_barang, b.kategori, b.jenis,
           b.harga, b.status
    FROM barang b
    WHERE NOT EXISTS (
        SELECT 1 FROM pengeluaran p
        WHERE p.id_barang = b.id_barang
    )
    ORDER BY b.id_barang DESC
    LIMIT 8
");

// Pengeluaran terbaru
$query_pengeluaran = mysqli_query($koneksi, "
    SELECT p.id_pengeluaran, p.nama_pengeluaran, p.jumlah,
           p.tanggal_pengeluaran, p.catatan, b.nama_barang
    FROM pengeluaran p
    LEFT JOIN barang b ON p.id_barang = b.id_barang
    ORDER BY p.tanggal_pengeluaran DESC, p.id_pengeluaran DESC
    LIMIT 5
");

// Data grafik pengeluaran 7 hari terakhir
$query_grafik = mysqli_query($koneksi, "
    SELECT DATE(tanggal_pengeluaran) AS tanggal,
           COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE tanggal_pengeluaran >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND tanggal_pengeluaran < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    GROUP BY DATE(tanggal_pengeluaran)
");

$data_grafik = [];
while ($row = mysqli_fetch_assoc($query_grafik)) {
    $data_grafik[$row["tanggal"]] = (float) $row["total"];
}

$label_grafik = [];
$nilai_grafik = [];

for ($i = 6; $i >= 0; $i--) {
    $tanggal = date("Y-m-d", strtotime("-$i days"));
    $label_grafik[] = date("d/m", strtotime($tanggal));
    $nilai_grafik[] = $data_grafik[$tanggal] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Wishlist</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../STYLES.css">

    <style>
        :root {
            --navy: #101b35;
            --navy-light: #192847;
            --gold: #d8b45a;
            --bg: #f4f6fa;
            --muted: #758096;
        }

        body {
            background: var(--bg);
            color: #202b40;
            font-family: Arial, sans-serif;
        }

        .app-navbar {
            background: var(--navy);
            padding: 15px 0;
        }

        .brand {
            color: #fff;
            font-weight: 800;
            font-size: 21px;
            text-decoration: none;
        }

        .brand span {
            color: var(--gold);
        }

        .nav-link-custom {
            color: #dbe2f0;
            text-decoration: none;
            margin-left: 20px;
            font-size: 14px;
        }

        .nav-link-custom:hover,
        .nav-link-custom.active {
            color: var(--gold);
        }

        .page-container {
            max-width: 1250px;
            margin: 0 auto;
            padding: 32px 20px 50px;
        }

        .welcome-title {
            font-size: clamp(25px, 4vw, 32px);
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 7px;
        }

        .muted {
            color: var(--muted);
        }

        .stat-card,
        .panel {
            background: #fff;
            border: 1px solid #e8ecf3;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(16, 27, 53, .04);
        }

        .stat-card {
            padding: 22px;
            height: 100%;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 800;
            color: var(--navy);
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            background: #f4edd9;
            color: #8d6b18;
            border-radius: 12px;
            font-size: 21px;
        }

        .panel {
            padding: 23px;
            margin-top: 24px;
        }

        .panel-title {
            color: var(--navy);
            font-size: 18px;
            font-weight: 800;
            margin: 0;
        }

        .btn-gold {
            background: var(--gold);
            color: var(--navy);
            border: 0;
            font-weight: 700;
        }

        .btn-gold:hover {
            background: #caa343;
            color: var(--navy);
        }

        .btn-navy {
            background: var(--navy);
            color: #fff;
            border: 0;
        }

        .btn-navy:hover {
            background: var(--navy-light);
            color: #fff;
        }

        .table {
            vertical-align: middle;
            margin-bottom: 0;
        }

        .table thead th {
            color: #778198;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
            border-bottom: 1px solid #e8ecf3;
        }

        .table tbody td {
            padding-top: 14px;
            padding-bottom: 14px;
            border-color: #edf0f5;
        }

        .badge-type {
            background: #f4edd9;
            color: #806019;
            padding: 7px 10px;
            border-radius: 20px;
        }

        .badge-bought {
            background: #dff5e8;
            color: #167343;
        }

        .badge-not-bought {
            background: #fff0d5;
            color: #96610b;
        }

        .progress {
            height: 9px;
            background: #e9edf4;
        }

        .progress-bar {
            background: var(--gold);
        }

        .section-link {
            color: #9b791f;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
        }

        .section-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            color: var(--muted);
            text-align: center;
            padding: 28px 10px;
        }

        @media (max-width: 600px) {
            .page-container {
                padding: 22px 13px 35px;
            }

            .panel {
                padding: 16px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-number {
                font-size: 23px;
            }

            .nav-link-custom {
                margin-left: 10px;
            }
        }
    </style>
</head>

<body>

<nav class="app-navbar">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">
        <a class="brand" href="index.php">Wishlist <span></span></a>

        <div class="d-flex align-items-center flex-wrap">
            <a class="nav-link-custom active" href="index.php">Dashboard</a>
            <a class="nav-link-custom" href="daftar.php">Daftar Barang</a>
            <a class="nav-link-custom" href="../pengeluaran/pengeluaran.php">Pengeluaran</a>
        </div>
    </div>
</nav>

<main class="page-container">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="welcome-title">Dashboard</h1>
            <p class="muted mb-0">Pantau kebutuhan, keinginan, dan pengeluaranmu.</p>
        </div>

        <a href="tambah.php" class="btn btn-gold px-4 py-2">
            + Tambah Barang
        </a>
    </div>

    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Barang Aktif</div>
                        <div class="stat-number"><?= $total_barang ?></div>
                    </div>
                    <div class="stat-icon">📦</div>
                </div>
                <small class="muted">Belum dicatat sebagai pengeluaran</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Kebutuhan</div>
                        <div class="stat-number"><?= $total_kebutuhan ?></div>
                    </div>
                    <div class="stat-icon">🎯</div>
                </div>
                <small class="muted">Barang kategori kebutuhan</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Keinginan</div>
                        <div class="stat-number"><?= $total_keinginan ?></div>
                    </div>
                    <div class="stat-icon">⭐</div>
                </div>
                <small class="muted">Barang kategori keinginan</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Belum Dibeli</div>
                        <div class="stat-number"><?= $belum_dibeli ?></div>
                    </div>
                    <div class="stat-icon">🛒</div>
                </div>
                <small class="muted">Masih ada di daftar aktif</small>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-7">
            <div class="panel h-100">
                <h2 class="panel-title mb-2">Progres Pembelian</h2>
                <p class="muted small mb-3">
                    Barang yang sudah dibeli dan belum dicatat sebagai pengeluaran.
                </p>

                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold">Progres</span>
                    <span class="fw-bold"><?= $persentase ?>%</span>
                </div>

                <div class="progress mb-3">
                    <div class="progress-bar" role="progressbar"
                         style="width: <?= $persentase ?>%"
                         aria-valuenow="<?= $persentase ?>"
                         aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="d-flex justify-content-between flex-wrap gap-2 small">
                    <span class="muted">Sudah dibeli: <strong><?= $sudah_dibeli ?></strong></span>
                    <span class="muted">Belum dibeli: <strong><?= $belum_dibeli ?></strong></span>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel h-100">
                <h2 class="panel-title mb-3">Ringkasan Pengeluaran</h2>

                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div>
                        <div class="muted small">Pengeluaran minggu ini</div>
                        <div class="fw-bold fs-5">Rp <?= number_format($total_minggu, 0, ",", ".") ?></div>
                    </div>
                    <span class="stat-icon">📅</span>
                </div>

                <div class="d-flex justify-content-between align-items-center py-3">
                    <div>
                        <div class="muted small">Pengeluaran bulan ini</div>
                        <div class="fw-bold fs-5">Rp <?= number_format($total_bulan, 0, ",", ".") ?></div>
                    </div>
                    <span class="stat-icon">💰</span>
                </div>

                <a href="../pengeluaran/pengeluaran.php" class="section-link">
                    Lihat semua pengeluaran →
                </a>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="panel-title">Grafik Pengeluaran</h2>
                <p class="muted small mb-0">Pengeluaran selama 7 hari terakhir.</p>
            </div>
        </div>
        <div style="height: 280px;">
            <canvas id="grafikPengeluaran"></canvas>
        </div>
    </div>

    <div class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="panel-title">Daftar Barang Aktif</h2>
                <p class="muted small mb-0">Barang yang belum dicatat dalam pengeluaran.</p>
            </div>
            <a href="daftar.php" class="section-link">Lihat semua →</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($query_barang) > 0): ?>
                    <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>
                        <tr>
                            <td class="fw-semibold"><?= aman($barang["nama_barang"]) ?></td>
                            <td>
                                <span class="badge badge-type"><?= aman($barang["jenis"] ?? "Keinginan") ?></span>
                            </td>
                            <td><?= aman($barang["kategori"]) ?></td>
                            <td>Rp <?= number_format((float)$barang["harga"], 0, ",", ".") ?></td>
                            <td>
                                <?php if ($barang["status"] === "Sudah Dibeli"): ?>
                                    <span class="badge badge-bought">Sudah Dibeli</span>
                                <?php else: ?>
                                    <span class="badge badge-not-bought">Belum Dibeli</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            Belum ada barang aktif. Kamu bisa menambah barang baru.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="panel-title">Pengeluaran Terbaru</h2>
                    <a href="../pengeluaran/pengeluaran.php" class="section-link">Lihat semua →</a>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nama Pengeluaran</th>
                                <th>Barang Terkait</th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (mysqli_num_rows($query_pengeluaran) > 0): ?>
                            <?php while ($pengeluaran = mysqli_fetch_assoc($query_pengeluaran)): ?>
                                <tr>
                                    <td><?= aman(date("d/m/Y", strtotime($pengeluaran["tanggal_pengeluaran"]))) ?></td>
                                    <td class="fw-semibold"><?= aman($pengeluaran["nama_pengeluaran"]) ?></td>
                                    <td><?= aman($pengeluaran["nama_barang"] ?: "-") ?></td>
                                    <td class="fw-semibold">Rp <?= number_format((float)$pengeluaran["jumlah"], 0, ",", ".") ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty-state">Belum ada catatan pengeluaran.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center muted small mt-4">
        Wishlist PJK &copy; <?= date("Y") ?>
    </footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labelGrafik = <?= json_encode($label_grafik) ?>;
const nilaiGrafik = <?= json_encode($nilai_grafik) ?>;

new Chart(document.getElementById("grafikPengeluaran"), {
    type: "line",
    data: {
        labels: labelGrafik,
        datasets: [{
            label: "Pengeluaran (Rp)",
            data: nilaiGrafik,
            borderColor: "#d8b45a",
            backgroundColor: "rgba(216, 180, 90, 0.15)",
            fill: true,
            tension: 0.35,
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
            x: {
                grid: { display: false }
            }
        }
    }
});
</script>

</body>
</html>
