
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

$cari = trim($_GET["cari"] ?? "");
$kategori = trim($_GET["kategori"] ?? "");
$status = trim($_GET["status"] ?? "");
$jenis_filter = trim($_GET["jenis"] ?? "");

$jenis_valid = ["Kebutuhan", "Keinginan"];
$status_valid = ["Belum Dibeli", "Sudah Dibeli"];

if (!in_array($jenis_filter, $jenis_valid, true)) {
    $jenis_filter = "";
}

if (!in_array($status, $status_valid, true)) {
    $status = "";
}

$sql = "
    SELECT b.*
    FROM barang b
    WHERE 1 = 1
";

$conditions = [];
$params = [];
$types = "";

if ($cari !== "") {
    $conditions[] = "b.nama_barang LIKE ?";
    $params[] = "%" . $cari . "%";
    $types .= "s";
}

if ($kategori !== "") {
    $conditions[] = "b.kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}

if ($status !== "") {
    $conditions[] = "b.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($jenis_filter !== "") {
    $conditions[] = "b.jenis = ?";
    $params[] = $jenis_filter;
    $types .= "s";
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY b.id_barang DESC";

$stmt = mysqli_prepare($koneksi, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$kebutuhan = [];
$keinginan = [];

while ($barang = mysqli_fetch_assoc($result)) {
    if ($barang["jenis"] === "Kebutuhan") {
        $kebutuhan[] = $barang;
    } else {
        $keinginan[] = $barang;
    }
}

mysqli_stmt_close($stmt);

// Ringkasan seluruh barang, tidak terpengaruh filter pencarian.
$query_statistik = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Sudah Dibeli'), 0) AS sudah,
        COALESCE(SUM(status = 'Belum Dibeli'), 0) AS belum,
        COALESCE(SUM(jenis = 'Kebutuhan'), 0) AS kebutuhan,
        COALESCE(SUM(jenis = 'Keinginan'), 0) AS keinginan
    FROM barang
");

$stat = mysqli_fetch_assoc($query_statistik);

$total_barang = (int) $stat["total"];
$total_sudah = (int) $stat["sudah"];
$total_belum = (int) $stat["belum"];
$total_kebutuhan = (int) $stat["kebutuhan"];
$total_keinginan = (int) $stat["keinginan"];

// Kategori yang terdaftar pada semua barang.
$query_kategori = mysqli_query($koneksi, "
    SELECT DISTINCT b.kategori
    FROM barang b
    WHERE b.kategori IS NOT NULL
      AND b.kategori <> ''
    ORDER BY b.kategori ASC
");

$query_error = "";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Barang | Wishlist</title>

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
            Wishlist
        </a>

        <button class="navbar-toggler bg-light" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-grid-1x2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="daftar.php">
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
            <h1>Daftar Barang</h1>
            <p>Lihat semua barang dan statusnya. Barang yang dicatat di pengeluaran akan otomatis ditandai sudah dibeli.</p>
        </div>

        <a href="tambah.php" class="btn btn-gold">
            <i class="bi bi-plus-lg me-1"></i> Tambah Barang
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
                    <div class="stat-number"><?= $total_sudah ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-shield-check"></i></div>
                <div>
                    <div class="stat-label">Kebutuhan</div>
                    <div class="stat-number"><?= $total_kebutuhan ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="bi bi-stars"></i></div>
                <div>
                    <div class="stat-label">Keinginan</div>
                    <div class="stat-number"><?= $total_keinginan ?></div>
                </div>
            </div>
        </div>
    </div>

    <section class="panel item-filter-panel">
        <div class="panel-heading">
            <div>
                <h5><i class="bi bi-funnel me-2"></i>Filter Barang</h5>
                <p class="text-secondary small mb-0">Cari dan saring daftar tanpa perlu memuat ulang halaman.</p>
            </div>
        </div>

        <form action="daftar.php" method="GET" id="filterBarangForm" data-live-filter>
            <div class="row g-3 align-items-end">

                <div class="col-lg-4 col-md-6">
                    <label class="filter-label" for="cari">Cari nama barang</label>
                    <input type="text" class="form-control" id="cari"
                           name="cari" placeholder="Contoh: Jersey, Laptop..."
                           data-filter-search
                           value="<?= e($cari) ?>">
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="filter-label" for="jenis">Jenis</label>
                    <select class="form-select" id="jenis" name="jenis" data-filter-select>
                        <option value="">Semua jenis</option>
                        <option value="Kebutuhan" <?= $jenis_filter === "Kebutuhan" ? "selected" : "" ?>>
                            Kebutuhan
                        </option>
                        <option value="Keinginan" <?= $jenis_filter === "Keinginan" ? "selected" : "" ?>>
                            Keinginan
                        </option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="filter-label" for="kategori">Kategori</label>
                    <select class="form-select" id="kategori" name="kategori" data-filter-select>
                        <option value="">Semua kategori</option>
                        <?php while ($kat = mysqli_fetch_assoc($query_kategori)): ?>
                            <option value="<?= e($kat["kategori"]) ?>"
                                <?= $kategori === $kat["kategori"] ? "selected" : "" ?>>
                                <?= e($kat["kategori"]) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="filter-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status" data-filter-select>
                        <option value="">Semua status</option>
                        <option value="Belum Dibeli" <?= $status === "Belum Dibeli" ? "selected" : "" ?>>
                            Belum Dibeli
                        </option>
                        <option value="Sudah Dibeli" <?= $status === "Sudah Dibeli" ? "selected" : "" ?>>
                            Sudah Dibeli
                        </option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-filter flex-grow-1" data-filter-submit>
                        <i class="bi bi-search me-1"></i> Cari
                    </button>
                    <a href="daftar.php" class="btn btn-reset" title="Reset filter" data-filter-reset>
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>

            </div>
            <div class="filter-feedback mt-3 small text-secondary" aria-live="polite" data-filter-feedback></div>
        </form>
    </section>

    <!-- BAGIAN KEBUTUHAN -->
    <section class="panel">
        <div class="panel-heading">
            <div class="section-title">
                <div class="section-icon icon-gold">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <h5>Daftar Kebutuhan</h5>
                    <p>Barang penting beserta status pembeliannya.</p>
                </div>
            </div>
            <span class="section-count"><?= count($kebutuhan) ?> barang</span>
        </div>

        <div class="table-responsive">
            <table class="table item-table" data-filter-table>
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
                <?php if (count($kebutuhan) > 0): ?>
                    <?php foreach ($kebutuhan as $i => $barang): ?>
                        <tr data-filter-row
                            data-kind="<?= e($barang["jenis"]) ?>"
                            data-category="<?= e($barang["kategori"]) ?>"
                            data-status="<?= e($barang["status"]) ?>">
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= e($barang["nama_barang"]) ?></strong></td>
                            <td><?= e($barang["kategori"]) ?></td>
                            <td class="money"><?= rupiah($barang["harga"]) ?></td>
                            <td>
                                <span class="badge-status <?= $barang["status"] === "Sudah Dibeli" ? "status-sudah" : "status-belum" ?>">
                                    <?= e($barang["status"]) ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <a class="action-btn edit-btn"
                                   href="edit.php?id=<?= (int) $barang["id_barang"] ?>"
                                   title="Edit barang">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form class="d-inline" action="hapus.php" method="POST"
                                      onsubmit="return confirm('Yakin ingin menghapus barang ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                                    <input type="hidden" name="id_barang" value="<?= (int) $barang["id_barang"] ?>">
                                    <button class="action-btn delete-btn" type="submit" title="Hapus barang">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="bi bi-inbox"></i>
                            Belum ada kebutuhan yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php endif; ?>
                    <tr class="filter-no-results d-none">
                        <td colspan="6" class="empty-state">Tidak ada barang yang cocok dengan filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- BAGIAN KEINGINAN -->
    <section class="panel">
        <div class="panel-heading">
            <div class="section-title">
                <div class="section-icon icon-blue">
                    <i class="bi bi-stars"></i>
                </div>
                <div>
                    <h5>Daftar Keinginan</h5>
                    <p>Barang yang ingin kamu miliki setelah kebutuhan terpenuhi.</p>
                </div>
            </div>
            <span class="section-count"><?= count($keinginan) ?> barang</span>
        </div>

        <div class="table-responsive">
            <table class="table item-table" data-filter-table>
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
                <?php if (count($keinginan) > 0): ?>
                    <?php foreach ($keinginan as $i => $barang): ?>
                        <tr data-filter-row
                            data-kind="<?= e($barang["jenis"]) ?>"
                            data-category="<?= e($barang["kategori"]) ?>"
                            data-status="<?= e($barang["status"]) ?>">
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= e($barang["nama_barang"]) ?></strong></td>
                            <td><?= e($barang["kategori"]) ?></td>
                            <td class="money"><?= rupiah($barang["harga"]) ?></td>
                            <td>
                                <span class="badge-status <?= $barang["status"] === "Sudah Dibeli" ? "status-sudah" : "status-belum" ?>">
                                    <?= e($barang["status"]) ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <a class="action-btn edit-btn"
                                   href="edit.php?id=<?= (int) $barang["id_barang"] ?>"
                                   title="Edit barang">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form class="d-inline" action="hapus.php" method="POST"
                                      onsubmit="return confirm('Yakin ingin menghapus barang ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                                    <input type="hidden" name="id_barang" value="<?= (int) $barang["id_barang"] ?>">
                                    <button class="action-btn delete-btn" type="submit" title="Hapus barang">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="bi bi-inbox"></i>
                            Belum ada keinginan yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php endif; ?>
                    <tr class="filter-no-results d-none">
                        <td colspan="6" class="empty-state">Tidak ada barang yang cocok dengan filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="footer-note">
        Wishlist PJK &mdash; Barang yang dicatat sebagai pengeluaran tetap terlihat di sini dengan status Sudah Dibeli.
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../app.js" defer></script>
</body>
</html>
