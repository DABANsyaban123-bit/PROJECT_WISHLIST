
<?php
include_once __DIR__ . "/../config.php";

// Mengambil semua barang dari database
$query_barang = mysqli_query($koneksi, "SELECT * FROM barang ORDER BY id_barang DESC");

if (!$query_barang) {
    die("Gagal mengambil data barang: " . mysqli_error($koneksi));
}

$total_barang = mysqli_num_rows($query_barang);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Barang | Wishlist PJK</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg wishlist-navbar">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">Wishlist PJK</a>

        <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#navbarWishlist"
            aria-controls="navbarWishlist" aria-expanded="false"
            aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarWishlist">
            <ul class="navbar-nav ms-auto gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="daftar.php">Daftar Barang</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../pengeluaran/index.php">Pengeluaran</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 py-md-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Daftar Barang</h2>
            <p class="text-secondary mb-0">
                Kelola semua barang yang ingin kamu beli.
            </p>
        </div>

        <a href="tambah.php" class="btn btn-primary">
            + Tambah Barang
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <p class="text-secondary mb-2">Total Barang</p>
                    <h3 class="fw-bold mb-0">
                        <?= $total_barang; ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h5 class="fw-bold mb-0">Semua Barang</h5>

                <input
                    type="search"
                    id="cariBarang"
                    class="form-control"
                    placeholder="Cari nama barang..."
                    style="max-width: 280px;"
                >
            </div>

            <div class="table-responsive">
                <table class="table wishlist-table align-middle mb-0">
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
                    <tbody id="tabelBarang">
                        <?php if ($total_barang > 0): ?>
                            <?php $no = 1; ?>
                            <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>
                                <tr>
                                    <td><?= $no++; ?></td>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($barang['nama_barang'] ?? '-'); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($barang['kategori'] ?? '-'); ?>
                                    </td>

                                    <td>
                                        Rp <?= number_format((float) ($barang['harga'] ?? 0), 0, ',', '.'); ?>
                                    </td>

                                    <td>
                                        <?php if (($barang['status'] ?? '') === 'Sudah Dibeli'): ?>
                                            <span class="badge text-bg-success">Sudah Dibeli</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-warning">Belum Dibeli</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="edit.php?id=<?= (int) $barang['id_barang']; ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>

                                            <a href="hapus.php?id=<?= (int) $barang['id_barang']; ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Yakin ingin menghapus barang ini?')">
                                                Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">
                                    Belum ada barang. Klik Tambah Barang untuk memulai wishlist-mu.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById('cariBarang').addEventListener('input', function () {
    const kataKunci = this.value.toLowerCase();

    document.querySelectorAll('#tabelBarang tr').forEach(function (baris) {
        baris.style.display = baris.textContent.toLowerCase().includes(kataKunci)
            ? ''
            : 'none';
    });
});
</script>

</body>
</html>
