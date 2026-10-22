
<?php
include_once __DIR__ . "/../config.php";

function aman($nilai) {
    return htmlspecialchars((string)($nilai ?? ""), ENT_QUOTES, "UTF-8");
}

// Ambil filter dari URL
$cari = trim($_GET["cari"] ?? "");
$filter_jenis = $_GET["jenis"] ?? "";
$filter_kategori = $_GET["kategori"] ?? "";
$filter_status = $_GET["status"] ?? "";

// Susun filter pencarian
$where = "
    WHERE NOT EXISTS (
        SELECT 1
        FROM pengeluaran p
        WHERE p.id_barang = b.id_barang
    )
";

if ($cari !== "") {
    $cari_sql = mysqli_real_escape_string($koneksi, $cari);
    $where .= " AND b.nama_barang LIKE '%$cari_sql%'";
}

if (in_array($filter_jenis, ["Kebutuhan", "Keinginan"], true)) {
    $jenis_sql = mysqli_real_escape_string($koneksi, $filter_jenis);
    $where .= " AND b.jenis = '$jenis_sql'";
}

if ($filter_kategori !== "") {
    $kategori_sql = mysqli_real_escape_string($koneksi, $filter_kategori);
    $where .= " AND b.kategori = '$kategori_sql'";
}

if (in_array($filter_status, ["Belum Dibeli", "Sudah Dibeli"], true)) {
    $status_sql = mysqli_real_escape_string($koneksi, $filter_status);
    $where .= " AND b.status = '$status_sql'";
}

// Ambil daftar barang aktif sesuai filter
$query_barang = mysqli_query($koneksi, "
    SELECT b.id_barang, b.nama_barang, b.kategori, b.jenis,
           b.harga, b.status
    FROM barang b
    $where
    ORDER BY
        CASE WHEN b.jenis = 'Kebutuhan' THEN 1 ELSE 2 END,
        b.id_barang DESC
");

if (!$query_barang) {
    die("Gagal mengambil data barang: " . aman(mysqli_error($koneksi)));
}

// Pisahkan hasil menjadi dua kelompok
$daftar_kebutuhan = [];
$daftar_keinginan = [];

while ($barang = mysqli_fetch_assoc($query_barang)) {
    if (($barang["jenis"] ?? "Keinginan") === "Kebutuhan") {
        $daftar_kebutuhan[] = $barang;
    } else {
        $daftar_keinginan[] = $barang;
    }
}

$total_kebutuhan = count($daftar_kebutuhan);
$total_keinginan = count($daftar_keinginan);
$total_barang = $total_kebutuhan + $total_keinginan;

// Ambil pilihan kategori dari database
$query_kategori = mysqli_query($koneksi, "
    SELECT DISTINCT kategori
    FROM barang
    WHERE kategori IS NOT NULL AND kategori <> ''
    ORDER BY kategori ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Barang | Wishlist PJK</title>

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

        .page-title {
            font-size: clamp(25px, 4vw, 32px);
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 7px;
        }

        .muted {
            color: var(--muted);
        }

        .summary-card,
        .panel {
            background: #fff;
            border: 1px solid #e8ecf3;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(16, 27, 53, .04);
        }

        .summary-card {
            padding: 20px;
            height: 100%;
        }

        .summary-label {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 9px;
        }

        .summary-number {
            color: var(--navy);
            font-size: 27px;
            font-weight: 800;
        }

        .summary-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #f4edd9;
            font-size: 21px;
        }

        .panel {
            padding: 23px;
            margin-top: 24px;
        }

        .panel-title {
            color: var(--navy);
            font-size: 19px;
            font-weight: 800;
            margin: 0;
        }

        .btn-gold {
            background: var(--gold);
            color: var(--navy);
            border: none;
            font-weight: 700;
        }

        .btn-gold:hover {
            background: #caa343;
            color: var(--navy);
        }

        .btn-navy {
            background: var(--navy);
            color: #fff;
            border: none;
        }

        .btn-navy:hover {
            background: var(--navy-light);
            color: #fff;
        }

        .filter-card {
            background: #fff;
            border: 1px solid #e8ecf3;
            border-radius: 16px;
            padding: 22px;
            margin-top: 24px;
        }

        .form-label {
            color: #35415a;
            font-size: 13px;
            font-weight: 700;
        }

        .form-control,
        .form-select {
            min-height: 43px;
            border-color: #dfe4ed;
            border-radius: 9px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(216, 180, 90, .15);
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
            padding-top: 15px;
            padding-bottom: 15px;
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

        .section-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            background: #f4edd9;
            border-radius: 12px;
            font-size: 21px;
        }

        .count-badge {
            background: #eef1f6;
            color: #46516a;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 35px 12px !important;
            color: var(--muted);
        }

        .action-btn {
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            font-weight: 700;
        }

        .edit-btn {
            color: #344b75;
            background: #edf2fb;
        }

        .delete-btn {
            color: #b42332;
            background: #fff0f1;
            border: none;
        }

        @media (max-width: 600px) {
            .page-container {
                padding: 22px 13px 35px;
            }

            .panel,
            .filter-card {
                padding: 16px;
            }

            .summary-card {
                padding: 16px;
            }

            .summary-number {
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
            <a class="nav-link-custom" href="index.php">Dashboard</a>
            <a class="nav-link-custom active" href="daftar.php">Daftar Barang</a>
            <a class="nav-link-custom" href="../pengeluaran/pengeluaran.php">Pengeluaran</a>
        </div>
    </div>
</nav>

<main class="page-container">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="page-title">Daftar Barang</h1>
            <p class="muted mb-0">
                Kelola kebutuhan dan keinginanmu dalam satu tempat.
            </p>
        </div>

        <a href="tambah.php" class="btn btn-gold px-4 py-2">
            + Tambah Barang
        </a>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-12 col-md-4">
            <div class="summary-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="summary-label">Total Barang Aktif</div>
                        <div class="summary-number"><?= $total_barang ?></div>
                    </div>
                    <div class="summary-icon">📦</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="summary-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="summary-label">Kebutuhan</div>
                        <div class="summary-number"><?= $total_kebutuhan ?></div>
                    </div>
                    <div class="summary-icon">🎯</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="summary-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="summary-label">Keinginan</div>
                        <div class="summary-number"><?= $total_keinginan ?></div>
                    </div>
                    <div class="summary-icon">⭐</div>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-card">
        <h2 class="panel-title mb-3">Cari dan Filter Barang</h2>

        <form method="GET" action="daftar.php">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-3">
                    <label for="cari" class="form-label">Nama Barang</label>
                    <input
                        type="text"
                        class="form-control"
                        id="cari"
                        name="cari"
                        placeholder="Cari nama barang..."
                        value="<?= aman($cari) ?>"
                    >
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label for="jenis" class="form-label">Jenis</label>
                    <select class="form-select" id="jenis" name="jenis">
                        <option value="">Semua Jenis</option>
                        <option value="Kebutuhan" <?= $filter_jenis === "Kebutuhan" ? "selected" : "" ?>>
                            Kebutuhan
                        </option>
                        <option value="Keinginan" <?= $filter_jenis === "Keinginan" ? "selected" : "" ?>>
                            Keinginan
                        </option>
                    </select>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label for="kategori" class="form-label">Kategori</label>
                    <select class="form-select" id="kategori" name="kategori">
                        <option value="">Semua Kategori</option>

                        <?php while ($kategori = mysqli_fetch_assoc($query_kategori)): ?>
                            <option
                                value="<?= aman($kategori["kategori"]) ?>"
                                <?= $filter_kategori === $kategori["kategori"] ? "selected" : "" ?>
                            >
                                <?= aman($kategori["kategori"]) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="Belum Dibeli" <?= $filter_status === "Belum Dibeli" ? "selected" : "" ?>>
                            Belum Dibeli
                        </option>
                        <option value="Sudah Dibeli" <?= $filter_status === "Sudah Dibeli" ? "selected" : "" ?>>
                            Sudah Dibeli
                        </option>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-navy px-4">
                        Cari Barang
                    </button>
                    <a href="daftar.php" class="btn btn-outline-secondary px-4">
                        Reset Filter
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- BAGIAN KEBUTUHAN -->
    <section class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="section-icon">🎯</div>
                <div>
                    <h2 class="panel-title">Kebutuhan</h2>
                    <p class="muted small mb-0">Barang yang kamu perlukan.</p>
                </div>
            </div>

            <span class="count-badge"><?= $total_kebutuhan ?> barang</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($total_kebutuhan > 0): ?>
                    <?php foreach ($daftar_kebutuhan as $barang): ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= aman($barang["nama_barang"]) ?>
                            </td>

                            <td><?= aman($barang["kategori"]) ?></td>

                            <td class="fw-semibold">
                                Rp <?= number_format((float)$barang["harga"], 0, ",", ".") ?>
                            </td>

                            <td>
                                <?php if ($barang["status"] === "Sudah Dibeli"): ?>
                                    <span class="badge badge-bought">Sudah Dibeli</span>
                                <?php else: ?>
                                    <span class="badge badge-not-bought">Belum Dibeli</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="d-flex gap-2">
                                    <a
                                        class="action-btn edit-btn"
                                        href="edit.php?id=<?= (int)$barang["id_barang"] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="hapus.php"
                                        onsubmit="return confirm('Yakin ingin menghapus barang ini?')"
                                    >
                                        <input
                                            type="hidden"
                                            name="id_barang"
                                            value="<?= (int)$barang["id_barang"] ?>"
                                        >
                                        <button type="submit" class="action-btn delete-btn">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            Belum ada barang kebutuhan yang cocok dengan filter.
                            <br>
                            <a href="tambah.php" class="section-link d-inline-block mt-2">
                                + Tambahkan kebutuhan
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- BAGIAN KEINGINAN -->
    <section class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="section-icon">⭐</div>
                <div>
                    <h2 class="panel-title">Keinginan</h2>
                    <p class="muted small mb-0">Barang yang ingin kamu miliki.</p>
                </div>
            </div>

            <span class="count-badge"><?= $total_keinginan ?> barang</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($total_keinginan > 0): ?>
                    <?php foreach ($daftar_keinginan as $barang): ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= aman($barang["nama_barang"]) ?>
                            </td>

                            <td><?= aman($barang["kategori"]) ?></td>

                            <td class="fw-semibold">
                                Rp <?= number_format((float)$barang["harga"], 0, ",", ".") ?>
                            </td>

                            <td>
                                <?php if ($barang["status"] === "Sudah Dibeli"): ?>
                                    <span class="badge badge-bought">Sudah Dibeli</span>
                                <?php else: ?>
                                    <span class="badge badge-not-bought">Belum Dibeli</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="d-flex gap-2">
                                    <a
                                        class="action-btn edit-btn"
                                        href="edit.php?id=<?= (int)$barang["id_barang"] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="hapus.php"
                                        onsubmit="return confirm('Yakin ingin menghapus barang ini?')"
                                    >
                                        <input
                                            type="hidden"
                                            name="id_barang"
                                            value="<?= (int)$barang["id_barang"] ?>"
                                        >
                                        <button type="submit" class="action-btn delete-btn">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            Belum ada barang keinginan yang cocok dengan filter.
                            <br>
                            <a href="tambah.php" class="section-link d-inline-block mt-2">
                                + Tambahkan keinginan
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="text-center muted small mt-4">
        Wishlist PJK &copy; <?= date("Y") ?>
    </footer>

</main>

</body>
</html>
