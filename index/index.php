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

$persentase_progres = $total_barang > 0
    ? round(($sudah_dibeli / $total_barang) * 100)
    : 0;

// Mengambil seluruh data barang

$cari = trim($_GET['cari'] ?? '');
$kategori = trim($_GET['kategori'] ?? '');

$sql = "
    SELECT *
    FROM barang
    WHERE 1 = 1
";

if ($cari !== '') {
    $sql .= " AND nama_barang LIKE '%" .
        mysqli_real_escape_string($koneksi, $cari) . "%'";
}

if ($kategori !== '') {
    $sql .= " AND kategori = '" .
        mysqli_real_escape_string($koneksi, $kategori) . "'";
}


$status = $_GET['status'] ?? '';

if (in_array($status, ['Belum Dibeli', 'Sudah Dibeli'], true)) {
    $sql .= " AND status = '" .
        mysqli_real_escape_string($koneksi, $status) . "'";
}


$sql .= " ORDER BY id_barang DESC";

$query_barang = mysqli_query($koneksi, $sql);


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

// Data pengeluaran selama 7 hari terakhir
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
    $label_grafik[] = date(
        'd/m',
        strtotime($row['tanggal'])
    );

    $data_grafik[] = (float) $row['total'];
}

// Pengeluaran terbaru
$query_pengeluaran_terbaru = mysqli_query(
    $koneksi,
    "SELECT * FROM pengeluaran
     ORDER BY tanggal_pengeluaran DESC
     LIMIT 5"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WishList - Beranda</title>
    
    <link rel="stylesheet" href="../style.css">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <style>
        :root {
            --navy: #253C6D;
            --navy-light: #30497D;
            --gold: #D6AE58;
            --cream: #F7F4EC;
            --text: #24324A;
        }

        body {
            background: var(--cream) !important;
            color: var(--text);
        }

        h1 {
            color: var(--navy);
            letter-spacing: -1px;
        }

        .card {
            border-radius: 18px !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(37, 60, 109, 0.10) !important;
        }

        .btn-primary {
            background: var(--navy);
            border-color: var(--navy);
        }

        .btn-primary:hover {
            background: var(--navy-light);
            border-color: var(--navy-light);
        }

        .btn-outline-primary {
            color: var(--navy);
            border-color: var(--navy);
        }

        .btn-outline-primary:hover {
            background: var(--navy);
            border-color: var(--navy);
        }

        .spending-card {
            background: var(--navy);
            color: white;
            border-bottom: 4px solid var(--gold) !important;
        }

        .spending-card small {
            color: #DCE4F3;
        }

        .table thead th {
            color: var(--navy);
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }
    </style>

</head>

<body class="bg-light">

    <div class="container py-5">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h1 class="fw-bold">WishList</h1>
                <p class="text-secondary mb-0">
                    Kelola barang impian dan pengeluaranmu.
                </p>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="tambah.php" class="btn btn-primary">
                    + Tambah Barang
                </a>

                <a href="../pengeluaran/pengeluaran.php" class="btn btn-outline-primary">
                    Pengeluaran
                </a>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <p class="text-secondary mb-1">Total Barang</p>
                        <h2 class="fw-bold"><?= $total_barang ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <p class="text-secondary mb-1">Sudah Dibeli</p>
                        <h2 class="fw-bold text-success"><?= $sudah_dibeli ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <p class="text-secondary mb-1">Belum Dibeli</p>
                        <h2 class="fw-bold text-warning"><?= $belum_dibeli ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between
                        align-items-center mb-2">
                            <h5 class="fw-bold mb-0">
                                Progres Wishlist
                            </h5>

                            <span class="fw-bold">
                                <?= $persentase_progres ?>%
                            </span>
                        </div>

                        <div class="progress" style="height: 12px;">
                            <div
                                class="progress-bar"
                                role="progressbar"
                                style="width: <?= $persentase_progres ?>%; background-color: #253C6D;"
                                aria-valuenow="<?= $persentase_progres ?>"
                                aria-valuemin="0"
                                aria-valuemax="100">
                            </div>
                        </div>

                        <p class="text-secondary small mt-2 mb-0">
                            <?= $sudah_dibeli ?> dari <?= $total_barang ?>
                            barang wishlist sudah dibeli.
                        </p>

                    </div>
                </div>
            </div>




            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 spending-card">
                    <div class="card-body p-4">
                        <p class="mb-2">Pengeluaran Minggu Ini</p>
                        <h3 class="fw-bold">
                            Rp <?= number_format((float) $total_minggu, 0, ',', '.') ?>
                        </h3>
                        <small>Akumulasi pengeluaran minggu berjalan</small>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 spending-card">
                    <div class="card-body p-4">
                        <p class="mb-2">Pengeluaran Bulan Ini</p>
                        <h3 class="fw-bold">
                            Rp <?= number_format((float) $total_bulan, 0, ',', '.') ?>
                        </h3>
                        <small>Akumulasi pengeluaran bulan berjalan</small>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1">
                        Grafik Pengeluaran
                    </h5>

                    <p class="text-secondary small mb-4">
                        Pengeluaran selama 7 hari terakhir
                    </p>

                    <div style="height: 300px;">
                        <canvas id="grafikPengeluaran"></canvas>
                    </div>
                </div>
            </div>

        </div>

        <!-- Daftar Barang -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">

                <h4 class="fw-bold mb-3">Daftar Barang</h4>


                <form method="GET" action="index.php" class="row g-2 mb-4">

                    <div class="col-md-6">
                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari nama barang..."
                            value="<?= htmlspecialchars($_GET['cari'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <select name="kategori" class="form-select">
                            <option value="">Semua Kategori</option>

                            <?php
                            $query_kategori = mysqli_query(
                                $koneksi,
                                "SELECT DISTINCT kategori FROM barang ORDER BY kategori ASC"
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


                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>

                            <option
                                value="Belum Dibeli"
                                <?= (($_GET['status'] ?? '') === 'Belum Dibeli') ? 'selected' : '' ?>>
                                Belum Dibeli
                            </option>

                            <option
                                value="Sudah Dibeli"
                                <?= (($_GET['status'] ?? '') === 'Sudah Dibeli') ? 'selected' : '' ?>>
                                Sudah Dibeli
                            </option>
                        </select>
                    </div>


                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            Cari
                        </button>

                        <a href="index.php" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>

                </form>


                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
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
                            <?php if (mysqli_num_rows($query_barang) > 0): ?>
                                <?php $no = 1; ?>

                                <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>

                                        <td>
                                            <?= htmlspecialchars($barang['nama_barang']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($barang['kategori']) ?>
                                        </td>

                                        <td>
                                            Rp <?= number_format((float) $barang['harga'], 0, ',', '.') ?>
                                        </td>


                                        <td>
                                            <?php if ($barang['status'] === 'Sudah Dibeli'): ?>
                                                <span class="badge rounded-pill text-bg-success">
                                                    ✓ Sudah Dibeli
                                                </span>
                                            <?php else: ?>
                                                <span class="badge rounded-pill text-bg-warning">
                                                    ◷ Belum Dibeli
                                                </span>
                                            <?php endif; ?>
                                        </td>


                                        <td>
                                            <div class="d-flex gap-2">
                                                <a
                                                    href="edit.php?id=<?= (int) $barang['id_barang'] ?>"
                                                    class="btn btn-sm btn-warning">
                                                    Edit
                                                </a>

                                                <form
                                                    action="hapus.php"
                                                    method="POST"
                                                    onsubmit="return confirm('Yakin ingin menghapus barang ini?');">
                                                    <input
                                                        type="hidden"
                                                        name="id_barang"
                                                        value="<?= (int) $barang['id_barang'] ?>">

                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-4">
                                        Belum ada barang. Yuk, tambahkan barang impianmu!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>

                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between
                    align-items-center mb-3">
                                <h5 class="fw-bold mb-0">
                                    Pengeluaran Terbaru
                                </h5>

                                <a href="../pengeluaran/"
                                    class="btn btn-sm btn-outline-primary">
                                    Lihat Semua
                                </a>
                            </div>

                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Keterangan</th>
                                            <th>Jumlah</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php if (
                                            mysqli_num_rows($query_pengeluaran_terbaru) > 0
                                        ): ?>

                                            <?php while (
                                                $pengeluaran = mysqli_fetch_assoc(
                                                    $query_pengeluaran_terbaru
                                                )
                                            ): ?>
                                                <tr>
                                                    <td>
                                                        <?= htmlspecialchars(
                                                            $pengeluaran['tanggal_pengeluaran']
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <?= htmlspecialchars(
                                                            $pengeluaran['nama_pengeluaran']
                                                        ) ?>
                                                    </td>

                                                    <td class="fw-semibold">
                                                        Rp <?= number_format(
                                                                $pengeluaran['jumlah'],
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

            </div>
        </div>

    </div>


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
                        backgroundColor: '#253C6D',
                        borderColor: '#D6AE58',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
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