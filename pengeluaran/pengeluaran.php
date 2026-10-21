<?php
include_once __DIR__ . "/../config.php";

// Total pengeluaran minggu ini
$query_minggu = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEARWEEK(tanggal_pengeluaran, 1)
    = YEARWEEK(CURDATE(), 1)
");

$data_minggu = mysqli_fetch_assoc($query_minggu);
$total_minggu = $data_minggu['total'];

// Total pengeluaran bulan ini
$query_bulan = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM pengeluaran
    WHERE YEAR(tanggal_pengeluaran) = YEAR(CURDATE())
    AND MONTH(tanggal_pengeluaran) = MONTH(CURDATE())
");

$data_bulan = mysqli_fetch_assoc($query_bulan);
$total_bulan = $data_bulan['total'];

// Mengambil riwayat pengeluaran

$query_pengeluaran = mysqli_query($koneksi, "
    SELECT p.*, b.nama_barang
    FROM pengeluaran AS p
    LEFT JOIN barang AS b
        ON p.id_barang = b.id_barang
    ORDER BY p.tanggal_pengeluaran DESC,
             p.id_pengeluaran DESC
");

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengeluaran | WishList</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container py-5">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Pengeluaran</h1>
                <p class="text-secondary">
                    Pantau pengeluaran mingguan dan bulananmu.
                </p>
            </div>

            <a href="../index/index.php" class="btn btn-outline-secondary">
                Kembali
            </a>
        </div>

        <!-- Ringkasan pengeluaran -->
        <div class="row g-3 mb-4">

            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <p class="text-secondary mb-2">
                            Pengeluaran Minggu Ini
                        </p>

                        <h3>
                            Rp <?= number_format($total_minggu, 0, ',', '.') ?>
                        </h3>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <p class="text-secondary mb-2">
                            Pengeluaran Bulan Ini
                        </p>

                        <h3>
                            Rp <?= number_format($total_bulan, 0, ',', '.') ?>
                        </h3>
                    </div>
                </div>
            </div>

        </div>

        <!-- Tombol tambah -->
        <div class="mb-3">
            <a href="tambah_pengeluaran.php" class="btn btn-primary">
                + Tambah Pengeluaran
            </a>
        </div>

        <!-- Tabel riwayat -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">

                <h5 class="mb-3">Riwayat Pengeluaran</h5>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">

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

                        <tbody>
                            <?php
                            $no = 1;

                            if (mysqli_num_rows($query_pengeluaran) > 0):
                                while ($data = mysqli_fetch_assoc($query_pengeluaran)):
                            ?>
                                    <tr>
                                        <td><?= $no++ ?></td>

                                        <td>
                                            <?= htmlspecialchars($data['nama_pengeluaran']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($data['nama_barang'] ?? '-') ?>
                                        </td>

                                        <td>
                                            Rp <?= number_format($data['jumlah'], 0, ',', '.') ?>
                                        </td>

                                        <td>
                                            <?= date('d-m-Y', strtotime($data['tanggal_pengeluaran'])) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($data['catatan'] ?? '-') ?>


                                        <td>
                                            <div class="d-flex gap-2">
                                                <a
                                                    href="edit_pengeluaran.php?id=<?= (int) $data['id_pengeluaran'] ?>"
                                                    class="btn btn-sm btn-warning">
                                                    Edit
                                                </a>

                                                <form
                                                    action="hapus_pengeluaran.php"
                                                    method="POST"
                                                    onsubmit="return confirm('Yakin ingin menghapus pengeluaran ini?');">
                                                    <input
                                                        type="hidden"
                                                        name="id_pengeluaran"
                                                        value="<?= (int) $data['id_pengeluaran'] ?>">

                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>

                                        </td>
                                    </tr>
                                <?php
                                endwhile;
                            else:
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-4">
                                        Belum ada data pengeluaran.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>
                </div>x

            </div>
        </div>

    </div>

</body>

</html>