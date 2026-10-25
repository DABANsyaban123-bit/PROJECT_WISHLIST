
<?php
include_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

if (!empty($_SESSION["user_id"])) {
    header("Location: index/index.php");
    exit;
}

auth_ensure_users_table($koneksi);
$result_akun = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM users");
if (!$result_akun) {
    die("Gagal memeriksa status akun: " . mysqli_error($koneksi));
}
$akun_tersedia = (int) mysqli_fetch_assoc($result_akun)["total"] > 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#263f70">
    <title>Wishlist PJK | Rencanakan dengan tenang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="UI.css?v=20261025">
</head>
<body class="landing-page">
    <nav class="landing-nav">
        <a class="landing-brand" href="index.php">
            <span class="landing-brand-icon"><i class="bi bi-bag-heart-fill"></i></span>
            Wishlist <span>PJK</span>
        </a>
        <div class="landing-nav-links">
            <a href="#fitur">Fitur</a>
            <a class="btn btn-outline-primary" href="login.php">Masuk</a>
        </div>
    </nav>

    <main>
        <section class="landing-hero">
            <div class="landing-copy">
                <span class="landing-kicker"><i class="bi bi-stars me-2"></i>Wishlist &amp; perencanaan belanja</span>
                <h1>Rencana yang baik dimulai dari <span>langkah kecil.</span></h1>
                <p>Atur daftar kebutuhan dan keinginan, catat pembayaran bertahap, dan lihat progresmu dengan jelas dalam satu ruang pribadi.</p>
                <div class="landing-actions">
                    <a class="btn btn-primary btn-lg" href="<?= $akun_tersedia ? 'login.php' : 'setup.php' ?>">
                        <?= $akun_tersedia ? 'Masuk ke aplikasi' : 'Mulai siapkan akun' ?>
                        <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                    <a class="btn btn-outline-primary btn-lg" href="#fitur">Kenali fiturnya</a>
                </div>
                <div class="landing-trust">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Ruang pribadi yang hanya dapat diakses setelah masuk.</span>
                </div>
            </div>
            <div class="landing-art" aria-label="Ilustrasi ringkasan wishlist">
                <div class="landing-orbit orbit-one"></div>
                <div class="landing-orbit orbit-two"></div>
                <div class="landing-summary-card">
                    <div class="landing-card-top">
                        <span class="landing-card-symbol"><i class="bi bi-bag-check"></i></span>
                        <span class="landing-card-tag">Rencana belanja</span>
                        <i class="bi bi-three-dots"></i>
                    </div>
                    <p class="landing-card-caption">Progres tabungan</p>
                    <div class="landing-card-value">Terus bergerak <i class="bi bi-arrow-up-right"></i></div>
                    <div class="landing-progress"><span></span></div>
                    <div class="landing-progress-meta"><span>Setiap langkah berarti</span><span>01 / 03</span></div>
                    <div class="landing-card-divider"></div>
                    <div class="landing-list-row">
                        <span class="landing-list-icon"><i class="bi bi-laptop"></i></span>
                        <span><strong>Daftar wishlist</strong><small>Kebutuhan dan keinginan</small></span>
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="landing-list-row">
                        <span class="landing-list-icon gold"><i class="bi bi-wallet2"></i></span>
                        <span><strong>Pembayaran bertahap</strong><small>Catat cicilan dengan rapi</small></span>
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                </div>
                <div class="landing-floating-note"><i class="bi bi-stars"></i><span>Lebih terencana,<br><strong>lebih tenang.</strong></span></div>
            </div>
        </section>

        <section class="landing-features" id="fitur">
            <div class="landing-section-heading">
                <span class="landing-kicker">Sederhana, tapi lengkap</span>
                <h2>Semua rencana belanja di satu tempat.</h2>
                <p>Fokus pada tujuanmu, sementara progres dan catatan tetap tertata.</p>
            </div>
            <div class="landing-feature-grid">
                <article class="landing-feature-card">
                    <span class="landing-feature-icon"><i class="bi bi-list-check"></i></span>
                    <h3>Daftar yang terarah</h3>
                    <p>Pisahkan kebutuhan dan keinginan, lalu temukan barang dengan pencarian dan filter yang mudah.</p>
                </article>
                <article class="landing-feature-card">
                    <span class="landing-feature-icon gold"><i class="bi bi-pie-chart"></i></span>
                    <h3>Progres pembayaran</h3>
                    <p>Catat pembayaran kapan saja. Total, sisa, dan status lunas dihitung dari transaksi yang tercatat.</p>
                </article>
                <article class="landing-feature-card">
                    <span class="landing-feature-icon soft"><i class="bi bi-clock-history"></i></span>
                    <h3>Riwayat tetap tersimpan</h3>
                    <p>Cari dan saring pengeluaran berdasarkan tanggal untuk melihat kembali catatan transaksimu.</p>
                </article>
            </div>
        </section>

        <section class="landing-cta">
            <div>
                <span class="landing-kicker">Mulai dengan satu rencana</span>
                <h2>Siapkan wishlist-mu hari ini.</h2>
            </div>
            <a class="btn btn-primary" href="<?= $akun_tersedia ? 'login.php' : 'setup.php' ?>">
                <?= $akun_tersedia ? 'Masuk dengan aman' : 'Buat akun pertama' ?>
                <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </section>
    </main>

    <footer class="landing-footer">
        <a class="landing-brand" href="index.php">
            <span class="landing-brand-icon"><i class="bi bi-bag-heart-fill"></i></span>
            Wishlist <span>PJK</span>
        </a>
        <span>Rencanakan dengan bijak, jalani dengan tenang.</span>
    </footer>
</body>
</html>
