<?php
include_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
auth_require_login();

$cari_input = $_GET["cari"] ?? "";
$cari = is_string($cari_input) ? trim($cari_input) : "";
$dari = is_string($_GET["dari"] ?? null) ? $_GET["dari"] : "";
$sampai = is_string($_GET["sampai"] ?? null) ? $_GET["sampai"] : "";
$bulan = is_string($_GET["bulan"] ?? null) ? $_GET["bulan"] : "";
$urutan = is_string($_GET["urutan"] ?? null) ? $_GET["urutan"] : "terbaru";
$urutan = in_array($urutan, ["terbaru", "terlama"], true) ? $urutan : "terbaru";

foreach (["dari", "sampai"] as $parameter_tanggal) {
    $nilai_tanggal = $$parameter_tanggal;
    if (
        $nilai_tanggal !== "" &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai_tanggal)
    ) {
        $$parameter_tanggal = "";
        continue;
    }
    if ($nilai_tanggal !== "") {
        $tanggal_valid = DateTime::createFromFormat("!Y-m-d", $nilai_tanggal);
        if (!$tanggal_valid || $tanggal_valid->format("Y-m-d") !== $nilai_tanggal) {
            $$parameter_tanggal = "";
        }
    }
}

$bulan_valid = preg_match('/^\d{4}-\d{2}$/', $bulan)
    ? DateTime::createFromFormat("!Y-m", $bulan)
    : false;
if ($bulan !== "" && (!$bulan_valid || $bulan_valid->format("Y-m") !== $bulan)) {
    $bulan = "";
}

// Ringkasan pengeluaran
$query_total = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
");
$total_semua = mysqli_fetch_assoc($query_total)['total'];

$query_minggu = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEARWEEK(tanggal_pengeluaran, 1)
        = YEARWEEK(CURDATE(), 1)
");
$total_minggu = mysqli_fetch_assoc($query_minggu)['total'];

$query_bulan = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEAR(tanggal_pengeluaran) = YEAR(CURDATE())
      AND MONTH(tanggal_pengeluaran) = MONTH(CURDATE())
");
$total_bulan = mysqli_fetch_assoc($query_bulan)['total'];

// Riwayat transaksi dapat disaring tanpa mengubah ringkasan seluruh pengeluaran.
$kondisi = [];
$parameter = [];
$tipe_parameter = "";

if ($cari !== "") {
    $kondisi[] = "(p.nama_pengeluaran LIKE ? OR b.nama_barang LIKE ? OR p.catatan LIKE ?)";
    $kata_kunci = "%" . $cari . "%";
    array_push($parameter, $kata_kunci, $kata_kunci, $kata_kunci);
    $tipe_parameter .= "sss";
}

if ($dari !== "") {
    $kondisi[] = "p.tanggal_pengeluaran >= ?";
    $parameter[] = $dari;
    $tipe_parameter .= "s";
}

if ($sampai !== "") {
    $kondisi[] = "p.tanggal_pengeluaran <= ?";
    $parameter[] = $sampai;
    $tipe_parameter .= "s";
}

if ($bulan !== "") {
    $kondisi[] = "DATE_FORMAT(p.tanggal_pengeluaran, '%Y-%m') = ?";
    $parameter[] = $bulan;
    $tipe_parameter .= "s";
}

$where = $kondisi ? "WHERE " . implode(" AND ", $kondisi) : "";
$arah_urutan = $urutan === "terlama" ? "ASC" : "DESC";
$stmt_pengeluaran = mysqli_prepare($koneksi, "
    SELECT p.*, b.nama_barang, b.harga AS harga_barang,
           (
               SELECT COUNT(*)
               FROM pengeluaran p2
               WHERE p2.id_barang = p.id_barang
                 AND p2.id_pengeluaran <= p.id_pengeluaran
           ) AS nomor_pembayaran,
           (
               SELECT COALESCE(SUM(p3.jumlah), 0)
               FROM pengeluaran p3
               WHERE p3.id_barang = p.id_barang
           ) AS total_pembayaran_barang
    FROM pengeluaran AS p
    LEFT JOIN barang AS b
        ON p.id_barang = b.id_barang
    $where
    ORDER BY p.tanggal_pengeluaran $arah_urutan,
             p.id_pengeluaran $arah_urutan
");

if (!$stmt_pengeluaran) {
    die("Gagal menyiapkan riwayat pengeluaran: " . mysqli_error($koneksi));
}

if ($parameter) {
    mysqli_stmt_bind_param($stmt_pengeluaran, $tipe_parameter, ...$parameter);
}

if (!mysqli_stmt_execute($stmt_pengeluaran)) {
    die("Gagal mengambil riwayat pengeluaran: " . mysqli_stmt_error($stmt_pengeluaran));
}

$query_pengeluaran = mysqli_stmt_get_result($stmt_pengeluaran);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengeluaran | Wishlist PJK</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="../STYLES.css">

    <style>
        body {
            background: var(--cream, #F7F4EC);
            color: var(--text, #24324A);
        }

        .expense-page {
            padding-top: 42px;
            padding-bottom: 48px;
        }

        .page-eyebrow {
            color: #98742E;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        .page-title {
            color: var(--navy, #253C6D);
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .expense-subtitle {
            color: #7B8494;
            margin-bottom: 0;
        }

        .btn-wishlist {
            background: var(--navy, #253C6D);
            color: #fff;
            border: 1px solid var(--navy, #253C6D);
            border-radius: 11px;
            padding: 11px 17px;
            font-weight: 600;
        }

        .btn-wishlist:hover {
            background: var(--navy-light, #30497D);
            color: #fff;
        }

        .expense-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .expense-summary-card,
        .expense-panel {
            background: #fff;
            border: 1px solid rgba(37, 60, 109, .07);
            border-radius: 19px;
            box-shadow: 0 8px 28px rgba(37, 60, 109, .055);
        }

        .expense-summary-card {
            height: 100%;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .expense-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 9px 25px rgba(37, 60, 109, .09);
        }

        .expense-icon {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 13px;
            background: #EEF2FA;
            color: var(--navy, #253C6D);
            font-size: 21px;
            font-weight: 700;
        }

        .expense-icon.gold {
            background: #FBF3E2;
            color: #A77D2D;
        }

        .expense-icon.green {
            background: #E7F5EC;
            color: #23824D;
        }

        .expense-amount {
            color: var(--navy, #253C6D);
            font-size: clamp(20px, 2.5vw, 27px);
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .expense-panel {
            overflow: hidden;
        }

        .expense-panel-header {
            padding: 24px 24px 0;
        }

        .expense-panel-body {
            padding: 24px;
        }

        .expense-search {
            max-width: 300px;
            min-width: 200px;
        }

        .expense-table {
            margin-bottom: 0;
        }

        .expense-table thead th {
            background: #F7F8FB;
            color: var(--navy, #253C6D);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .35px;
            padding: 16px;
            white-space: nowrap;
            border-bottom: 1px solid #E3E8F0;
        }

        .expense-table tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #EDF0F5;
        }

        .expense-table tbody tr:last-child td {
            border-bottom: none;
        }

        .expense-table tbody tr:hover td {
            background: #FAFBFE;
        }

        .expense-name {
            color: #263752;
            font-weight: 650;
        }

        .expense-date {
            white-space: nowrap;
            color: #697589;
            font-size: 13px;
        }

        .expense-price {
            white-space: nowrap;
            color: var(--navy, #253C6D);
            font-weight: 700;
        }

        .expense-note {
            max-width: 220px;
            overflow-wrap: anywhere;
            color: #697589;
            font-size: 13px;
        }

        .expense-actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .expense-actions .btn {
            border-radius: 8px;
            font-weight: 600;
        }

        .expense-empty {
            padding: 45px 16px !important;
            text-align: center;
            color: #7B8494;
        }

        .expense-panel .form-control:focus {
            border-color: var(--gold, #D6AE58);
            box-shadow: 0 0 0 .2rem rgba(214, 174, 88, .17);
        }

        @media (max-width: 767px) {
            .expense-page {
                padding-top: 28px;
            }

            .expense-panel-header,
            .expense-panel-body {
                padding: 18px;
            }

            .expense-search {
                max-width: none;
                width: 100%;
            }

            .expense-table thead th,
            .expense-table tbody td {
                padding: 12px;
            }
        }
    </style>
    <link rel="stylesheet" href="../UI.css">
</head>

<body>

    <!-- NAVBAR -->
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
                        <a class="nav-link" href="../index/index.php">
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="../index/daftar.php">
                            Daftar Barang
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active" href="pengeluaran.php">
                            Pengeluaran
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

    <main class="container expense-page">

        <!-- HEADER -->
        <header class="expense-header">
            <div>
                <div class="page-eyebrow mb-2">Catatan Keuangan</div>

                <h1 class="page-title h2 mb-2">
                    Pengeluaran
                </h1>

                <p class="expense-subtitle">
                    Pantau dan kelola pengeluaran wishlist kamu.
                </p>
            </div>

            <a href="tambah_pengeluaran.php" class="btn btn-wishlist">
                + Tambah Pengeluaran
            </a>
        </header>

        <?php if (($_GET["tersimpan"] ?? "") === "barang"): ?>
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill me-2"></i>
                Pembayaran tersimpan. Status lunas dihitung dari seluruh transaksi yang terkait dengan barang.
            </div>
        <?php elseif (($_GET["tersimpan"] ?? "") === "umum"): ?>
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill me-2"></i>Pengeluaran berhasil ditambahkan.
            </div>
        <?php elseif (($_GET["diperbarui"] ?? "") === "1"): ?>
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill me-2"></i>Pengeluaran berhasil diperbarui.
            </div>
        <?php elseif (($_GET["dihapus"] ?? "") === "1"): ?>
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill me-2"></i>Pengeluaran berhasil dihapus. Status pembayaran barang telah dihitung ulang.
            </div>
        <?php endif; ?>

        <!-- KARTU RINGKASAN -->
        <section class="row g-3 mb-4">

            <div class="col-md-6 col-xl-4">
                <div class="expense-summary-card">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="expense-icon">Rp</div>

                            <div>
                                <p class="text-secondary mb-1">
                                    Total Pengeluaran
                                </p>

                                <small class="text-secondary">
                                    Seluruh riwayat
                                </small>
                            </div>
                        </div>

                        <div class="expense-amount">
                            Rp <?= number_format((float) $total_semua, 0, ',', '.') ?>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="expense-summary-card">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="expense-icon gold">▦</div>

                            <div>
                                <p class="text-secondary mb-1">
                                    Minggu Ini
                                </p>

                                <small class="text-secondary">
                                    Pengeluaran mingguan
                                </small>
                            </div>
                        </div>

                        <div class="expense-amount">
                            Rp <?= number_format((float) $total_minggu, 0, ',', '.') ?>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="expense-summary-card">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="expense-icon green">↗</div>

                            <div>
                                <p class="text-secondary mb-1">
                                    Bulan Ini
                                </p>

                                <small class="text-secondary">
                                    Pengeluaran bulanan
                                </small>
                            </div>
                        </div>

                        <div class="expense-amount">
                            Rp <?= number_format((float) $total_bulan, 0, ',', '.') ?>
                        </div>

                    </div>
                </div>
            </div>

        </section>

        <!-- RIWAYAT PENGELUARAN -->
        <section class="expense-panel">

            <div class="expense-panel-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                    <div>
                        <h2 class="h5 fw-bold mb-1"
                            style="color: var(--navy, #253C6D);">
                            Riwayat Pengeluaran
                        </h2>

                        <p class="text-secondary small mb-0">
                            Semua transaksi pengeluaran kamu.
                        </p>
                    </div>

                </div>

                <form method="GET" action="pengeluaran.php" class="history-filter row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label" for="cariPengeluaran">Cari transaksi</label>
                        <input
                            type="search"
                            id="cariPengeluaran"
                            name="cari"
                            class="form-control"
                            value="<?= htmlspecialchars($cari, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Nama, barang, atau catatan...">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="dari">Dari tanggal</label>
                        <input type="date" id="dari" name="dari" class="form-control"
                               value="<?= htmlspecialchars($dari, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="sampai">Sampai tanggal</label>
                        <input type="date" id="sampai" name="sampai" class="form-control"
                               value="<?= htmlspecialchars($sampai, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="bulan">Bulan</label>
                        <input type="month" id="bulan" name="bulan" class="form-control"
                               value="<?= htmlspecialchars($bulan, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="urutan">Urutan tanggal</label>
                        <select id="urutan" name="urutan" class="form-select">
                            <option value="terbaru" <?= $urutan === "terbaru" ? "selected" : "" ?>>Terbaru</option>
                            <option value="terlama" <?= $urutan === "terlama" ? "selected" : "" ?>>Terlama</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i> Terapkan filter
                        </button>
                        <a href="pengeluaran.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="expense-panel-body">
                <div class="table-responsive">

                    <table class="table expense-table align-middle">

                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Pengeluaran</th>
                                <th>Barang</th>
                                <th>Pembayaran Ke-</th>
                                <th>Jumlah</th>
                                <th>Tanggal</th>
                                <th>Catatan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody id="tabelPengeluaran">

                            <?php
                            $no = 1;
                            $bulan_sebelumnya = "";

                            if (mysqli_num_rows($query_pengeluaran) > 0):
                                while ($data = mysqli_fetch_assoc($query_pengeluaran)):
                                    $bulan_ini = date("Y-m", strtotime($data["tanggal_pengeluaran"]));
                                    if ($bulan_ini !== $bulan_sebelumnya):
                                        $bulan_sebelumnya = $bulan_ini;
                                        $nama_bulan = [
                                            1 => "Januari", "Februari", "Maret", "April",
                                            "Mei", "Juni", "Juli", "Agustus",
                                            "September", "Oktober", "November", "Desember"
                                        ];
                                        $label_bulan = $nama_bulan[(int) date("n", strtotime($data["tanggal_pengeluaran"]))]
                                            . " " . date("Y", strtotime($data["tanggal_pengeluaran"]));
                            ?>
                                <tr class="expense-month-group">
                                    <th colspan="8"><?= htmlspecialchars($label_bulan, ENT_QUOTES, 'UTF-8') ?></th>
                                </tr>
                            <?php endif; ?>
                            ?>

                                <tr>
                                    <td><?= $no++ ?></td>

                                    <td>
                                        <div class="expense-name">
                                            <?= htmlspecialchars(
                                                $data['nama_pengeluaran'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $data['nama_barang'] ?? '-',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= $data['id_barang'] !== null
                                            ? (int) $data['nomor_pembayaran']
                                            : '-' ?>
                                    </td>

                                    <td class="expense-price">
                                        Rp <?= number_format(
                                            (float) $data['jumlah'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                        <?php if ($data['id_barang'] !== null): ?>
                                            <div class="small text-secondary fw-normal">
                                                Total barang:
                                                Rp <?= number_format(
                                                    (float) $data['total_pembayaran_barang'],
                                                    0,
                                                    ',',
                                                    '.'
                                                ) ?>
                                                —
                                                <?= (float) $data['total_pembayaran_barang'] >= (float) $data['harga_barang']
                                                    ? 'Lunas'
                                                    : 'Belum Lunas' ?>
                                            </div>
                                            <?php if (
                                                (float) $data['total_pembayaran_barang'] > (float) $data['harga_barang']
                                            ): ?>
                                                <div class="small text-danger fw-normal">
                                                    Kelebihan pembayaran:
                                                    Rp <?= number_format(
                                                        (float) $data['total_pembayaran_barang'] - (float) $data['harga_barang'],
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>

                                    <td class="expense-date">
                                        <?= date(
                                            'd M Y',
                                            strtotime($data['tanggal_pengeluaran'])
                                        ) ?>
                                    </td>

                                    <td>
                                        <div class="expense-note">
                                            <?= htmlspecialchars(
                                                $data['catatan'] ?? '-',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="expense-actions">

                                            <a
                                                href="edit_pengeluaran.php?id=<?= (int) $data['id_pengeluaran'] ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>

                                            <form
                                                action="hapus_pengeluaran.php"
                                                method="POST"
                                                data-loading-form
                                                onsubmit="return confirm('Yakin ingin menghapus pengeluaran ini?');">

                                                <input
                                                    type="hidden"
                                                    name="id_pengeluaran"
                                                    value="<?= (int) $data['id_pengeluaran'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                    Hapus
                                                </button>

                                            </form>

                                        </div>
                                    </td>
                                </tr>

                            <?php
                                endwhile;
                            else:
                            ?>

                                <tr>
                                    <td colspan="8" class="expense-empty">

                                        <h5 class="fw-bold">
                                            Belum Ada Pengeluaran
                                        </h5>

                                                        <p class="mb-3">
                                                            Transaksi yang cocok dengan filter akan muncul di sini.
                                        </p>

                                        <a
                                            href="tambah_pengeluaran.php"
                                            class="btn btn-wishlist">
                                            + Catat Pengeluaran
                                        </a>

                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>
                    </table>

                </div>
            </div>
        </section>

        <p class="text-secondary small text-center mt-4 mb-0">
            Wishlist PJK — Kelola wishlist dan pengeluaranmu dengan lebih teratur.
        </p>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../app.js" defer></script>

</body>
</html>