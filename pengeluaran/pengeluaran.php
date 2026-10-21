
<?php
include_once __DIR__ . "/../config.php";

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

// Total seluruh pengeluaran
$query_total = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
");

$total_semua = mysqli_fetch_assoc($query_total)['total'];

// Mengambil riwayat pengeluaran beserta nama barang
$query_pengeluaran = mysqli_query($koneksi, "
    SELECT p.*, b.nama_barang
    FROM pengeluaran AS p
    LEFT JOIN barang AS b
        ON p.id_barang = b.id_barang
    ORDER BY p.tanggal_pengeluaran DESC,
             p.id_pengeluaran DESC
");

if (!$query_pengeluaran) {
    die("Gagal mengambil riwayat pengeluaran: " . mysqli_error($koneksi));
}
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

        .expense-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .expense-subtitle {
            color: #7B8494;
            margin-bottom: 0;
        }

        .expense-summary-card {
            background: #fff;
            border: 1px solid #EAECEF;
            border-radius: 18px;
            box-shadow: 0 5px 20px rgba(37, 60, 109, 0.05);
            height: 100%;
            transition: transform 0.2s ease,
                        box-shadow 0.2s ease;
        }

        .expense-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 9px 25px rgba(37, 60, 109, 0.09);
        }

        .expense-icon {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: #EEF2FA;
            color: var(--navy, #253C6D);
            font-size: 22px;
            flex-shrink: 0;
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
            background: #fff;
            border: 1px solid #EAECEF;
            border-radius: 18px;
            box-shadow: 0 5px 20px rgba(37, 60, 109, 0.05);
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
            border-collapse: separate;
            border-spacing: 0;
        }

        .expense-table thead th {
            background: #F3F5FA;
            color: var(--navy, #253C6D);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.35px;
            padding: 16px;
            white-space: nowrap;
            border-bottom: 1px solid #E3E8F0;
        }

        .expense-table tbody td {
            padding: 17px 16px;
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
            font-weight: 650;
            color: #263752;
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
            min-width: 100px;
            white-space: normal;
            overflow-wrap: anywhere;
            color: #697589;
            font-size: 13px;
        }

        .expense-actions {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .expense-actions .btn {
            border-radius: 8px;
        }

        .expense-empty {
            padding: 48px 16px !important;
            text-align: center;
            color: #7B8494;
        }

        .expense-empty-icon {
            font-size: 38px;
            margin-bottom: 12px;
        }

        @media (max-width: 767px) {
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
                padding: 13px 12px;
            }
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg wishlist-navbar">
    <div class="container">

        <a class="navbar-brand fw-bold" href="../index/index.php">
            Wishlist PJK
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
                    <a class="nav-link active" href="index.php">
                        Pengeluaran
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 py-md-5">

    <!-- HEADER -->
    <div class="expense-header">

        <div>
            <h2 class="fw-bold mb-2">Pengeluaran</h2>

            <p class="expense-subtitle">
                Pantau dan kelola pengeluaran wishlist kamu.
            </p>
        </div>

        <a href="tambah_pengeluaran.php" class="btn btn-primary">
            + Tambah Pengeluaran
        </a>

    </div>

    <!-- KARTU RINGKASAN -->
    <div class="row g-3 mb-4">

        <div class="col-md-6 col-xl-4">
            <div class="expense-summary-card">
                <div class="card-body p-4">

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="expense-icon">
                            &#128176;
                        </div>

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
                        Rp <?= number_format((float) $total_semua, 0, ',', '.'); ?>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="expense-summary-card">
                <div class="card-body p-4">

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="expense-icon gold">
                            &#128197;
                        </div>

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
                        Rp <?= number_format((float) $total_minggu, 0, ',', '.'); ?>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="expense-summary-card">
                <div class="card-body p-4">

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="expense-icon green">
                            &#128200;
                        </div>

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
                        Rp <?= number_format((float) $total_bulan, 0, ',', '.'); ?>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- RIWAYAT PENGELUARAN -->
    <div class="expense-panel">

        <div class="expense-panel-header">

            <div class="d-flex flex-wrap justify-content-between
                        align-items-center gap-3">

                <div>
                    <h4 class="fw-bold mb-1">
                        Riwayat Pengeluaran
                    </h4>

                    <p class="text-secondary small mb-0">
                        Semua transaksi pengeluaran kamu.
                    </p>
                </div>

                <input
                    type="search"
                    id="cariPengeluaran"
                    class="form-control expense-search"
                    placeholder="Cari nama atau barang..."
                    aria-label="Cari riwayat pengeluaran">

            </div>

        </div>

        <div class="expense-panel-body">

            <div class="table-responsive">

                <table class="table expense-table align-middle">

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Pengeluaran</th>
                            <th>Barang</th>
                            <th>Jumlah</th>
                            <th>Tanggal</th>
                            <th>Catatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody id="tabelPengeluaran">

                        <?php
                        $no = 1;

                        if (mysqli_num_rows($query_pengeluaran) > 0):
                            while ($data = mysqli_fetch_assoc($query_pengeluaran)):
                        ?>

                            <tr>

                                <td><?= $no++; ?></td>

                                <td>
                                    <div class="expense-name">
                                        <?= htmlspecialchars(
                                            $data['nama_pengeluaran'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </div>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $data['nama_barang'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </td>

                                <td class="expense-price">
                                    Rp <?= number_format(
                                        (float) $data['jumlah'],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                </td>

                                <td class="expense-date">
                                    <?= date(
                                        'd M Y',
                                        strtotime($data['tanggal_pengeluaran'])
                                    ); ?>
                                </td>

                                <td>
                                    <div class="expense-note">
                                        <?= htmlspecialchars(
                                            $data['catatan'] ?? '-',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="expense-actions">

                                        <a
                                            href="edit_pengeluaran.php?id=<?= (int) $data['id_pengeluaran']; ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>

                                        <form
                                            action="hapus_pengeluaran.php"
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus pengeluaran ini?');">

                                            <input
                                                type="hidden"
                                                name="id_pengeluaran"
                                                value="<?= (int) $data['id_pengeluaran']; ?>">

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
                                <td colspan="7" class="expense-empty">

                                    <div class="expense-empty-icon">
                                        &#128203;
                                    </div>

                                    <h5 class="fw-bold">
                                        Belum Ada Pengeluaran
                                    </h5>

                                    <p class="mb-3">
                                        Pengeluaran yang kamu catat akan
                                        muncul di sini.
                                    </p>

                                    <a
                                        href="tambah_pengeluaran.php"
                                        class="btn btn-primary">
                                        + Catat Pengeluaran
                                    </a>

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

                <div
                    id="hasilKosong"
                    class="text-center text-secondary py-4 d-none">

                    Tidak ada pengeluaran yang cocok dengan pencarian.

                </div>

            </div>

        </div>
    </div>

    <p class="text-secondary small text-center mt-4 mb-0">
        Wishlist PJK &mdash; Kelola wishlist dan pengeluaranmu dengan lebih teratur.
    </p>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const inputCari = document.getElementById('cariPengeluaran');
const tabelPengeluaran = document.getElementById('tabelPengeluaran');
const hasilKosong = document.getElementById('hasilKosong');

inputCari.addEventListener('input', function () {
    const kataKunci = this.value.toLowerCase().trim();
    const semuaBaris = tabelPengeluaran.querySelectorAll('tr');
    let jumlahCocok = 0;

    semuaBaris.forEach(function (baris) {
        const teks = baris.textContent.toLowerCase();

        // Jangan menyembunyikan pesan awal ketika tabel kosong.
        if (baris.querySelector('.expense-empty')) {
            return;
        }

        const cocok = teks.includes(kataKunci);

        baris.style.display = cocok ? '' : 'none';

        if (cocok) {
            jumlahCocok++;
        }
    });

    const punyaData = Array.from(semuaBaris).some(function (baris) {
        return !baris.querySelector('.expense-empty');
    });

    hasilKosong.classList.toggle(
        'd-none',
        !(punyaData && jumlahCocok === 0)
    );
});
</script>

</body>
</html>
