<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/pembayaran.php";

$user_id = auth_require_login($koneksi);
$statement = mysqli_prepare($koneksi, "SELECT username, password_hash FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($statement, "i", $user_id);
mysqli_stmt_execute($statement);
$account = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

if (!$account) {
    auth_redirect_to_login();
}

$username = $account["username"];
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action_input = $_POST["profile_action"] ?? "";
    $action = is_string($action_input) ? $action_input : "";
    $current_password_input = $_POST["current_password"] ?? "";
    $current_password = is_string($current_password_input) ? $current_password_input : "";

    if (!auth_verify_csrf($_POST["csrf_token"] ?? null)) {
        $error = "Sesi formulir kedaluwarsa. Silakan muat ulang halaman.";
    } elseif (!password_verify($current_password, $account["password_hash"])) {
        $error = "Kata sandi saat ini tidak sesuai.";
    } elseif ($action === "update_profile") {
        $username_input = $_POST["username"] ?? "";
        $username = is_string($username_input) ? trim($username_input) : "";

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $error = "Nama pengguna harus 3-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.";
        } else {
            try {
                $statement = mysqli_prepare($koneksi, "UPDATE users SET username = ? WHERE id = ?");
                mysqli_stmt_bind_param($statement, "si", $username, $user_id);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);

                $_SESSION["username"] = $username;
                $account["username"] = $username;
                $success = "Informasi akun berhasil diperbarui.";
            } catch (mysqli_sql_exception $exception) {
                error_log("Wishlist profile update failed: " . $exception->getMessage());
                $error = "Profil gagal diperbarui. Nama pengguna mungkin sudah digunakan.";
            }
        }
    } elseif ($action === "change_password") {
        $new_password_input = $_POST["new_password"] ?? "";
        $confirm_password_input = $_POST["confirm_password"] ?? "";
        $new_password = is_string($new_password_input) ? $new_password_input : "";
        $confirm_password = is_string($confirm_password_input) ? $confirm_password_input : "";

        if (strlen($new_password) < 8 || strlen($new_password) > 72) {
            $error = "Kata sandi baru harus terdiri dari 8-72 byte.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Konfirmasi kata sandi baru tidak sama.";
        } else {
            try {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $statement = mysqli_prepare($koneksi, "UPDATE users SET password_hash = ? WHERE id = ?");
                mysqli_stmt_bind_param($statement, "si", $new_hash, $user_id);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);

                session_regenerate_id(true);
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                $account["password_hash"] = $new_hash;
                $success = "Kata sandi berhasil diperbarui.";
            } catch (mysqli_sql_exception $exception) {
                error_log("Wishlist password update failed: " . $exception->getMessage());
                $error = "Kata sandi gagal diperbarui. Silakan coba kembali.";
            }
        }
    } else {
        $error = "Permintaan perubahan profil tidak valid.";
    }
}

$csrf_token = auth_csrf_token();
$total_bayar_sql = pembayaran_total_sql($koneksi, "b");
$query_statistik = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS total_barang,
        COALESCE(SUM(b.harga), 0) AS total_target,
        COALESCE(SUM($total_bayar_sql), 0) AS total_pembayaran,
        COALESCE(SUM(GREATEST(b.harga - ($total_bayar_sql), 0)), 0) AS total_sisa,
        COALESCE(SUM(($total_bayar_sql) >= b.harga), 0) AS barang_lunas
    FROM barang b
    WHERE " . auth_user_scope($koneksi, "b")
);
$statistik = mysqli_fetch_assoc($query_statistik);

$query_pengeluaran_statistik = mysqli_query($koneksi, "
    SELECT COUNT(*) AS jumlah_transaksi, COALESCE(SUM(p.jumlah), 0) AS total_pengeluaran
    FROM pengeluaran p
    WHERE " . auth_user_scope($koneksi, "p")
);
$statistik_pengeluaran = mysqli_fetch_assoc($query_pengeluaran_statistik);

$stmt_barang = mysqli_prepare($koneksi, "
    SELECT b.id_barang, b.nama_barang, b.kategori, b.jenis, b.harga,
           $total_bayar_sql AS total_dibayar,
           GREATEST(b.harga - ($total_bayar_sql), 0) AS sisa_pembayaran
    FROM barang b
    WHERE " . auth_user_scope($koneksi, "b") . "
    ORDER BY b.id_barang DESC
");
mysqli_stmt_execute($stmt_barang);
$daftar_barang = mysqli_stmt_get_result($stmt_barang);

$dari_input = $_GET["dari"] ?? "";
$sampai_input = $_GET["sampai"] ?? "";
$bulan_input = $_GET["bulan"] ?? "";
$dari = is_string($dari_input) ? $dari_input : "";
$sampai = is_string($sampai_input) ? $sampai_input : "";
$bulan = is_string($bulan_input) ? $bulan_input : "";

foreach (["dari", "sampai"] as $parameter_tanggal) {
    $nilai_tanggal = $$parameter_tanggal;
    if ($nilai_tanggal === "") {
        continue;
    }

    $tanggal_valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai_tanggal)
        ? DateTime::createFromFormat("!Y-m-d", $nilai_tanggal)
        : false;
    if (!$tanggal_valid || $tanggal_valid->format("Y-m-d") !== $nilai_tanggal) {
        $$parameter_tanggal = "";
    }
}

$bulan_valid = preg_match('/^\d{4}-\d{2}$/', $bulan)
    ? DateTime::createFromFormat("!Y-m", $bulan)
    : false;
if ($bulan !== "" && (!$bulan_valid || $bulan_valid->format("Y-m") !== $bulan)) {
    $bulan = "";
}
if ($bulan !== "") {
    $dari = "";
    $sampai = "";
}

$kondisi_riwayat = [auth_user_scope($koneksi, "p")];
$parameter_riwayat = [];
$tipe_parameter = "";

if ($bulan !== "") {
    $kondisi_riwayat[] = "DATE_FORMAT(p.tanggal_pengeluaran, '%Y-%m') = ?";
    $parameter_riwayat[] = $bulan;
    $tipe_parameter .= "s";
} else {
    if ($dari !== "") {
        $kondisi_riwayat[] = "p.tanggal_pengeluaran >= ?";
        $parameter_riwayat[] = $dari;
        $tipe_parameter .= "s";
    }

    if ($sampai !== "") {
        $kondisi_riwayat[] = "p.tanggal_pengeluaran <= ?";
        $parameter_riwayat[] = $sampai;
        $tipe_parameter .= "s";
    }
}

$stmt_riwayat = mysqli_prepare($koneksi, "
    SELECT p.nama_pengeluaran, p.jumlah, p.tanggal_pengeluaran,
           b.nama_barang
    FROM pengeluaran p
    LEFT JOIN barang b
      ON b.id_barang = p.id_barang
     AND " . auth_user_scope($koneksi, "b") . "
    WHERE " . implode(" AND ", $kondisi_riwayat) . "
    ORDER BY p.tanggal_pengeluaran DESC, p.id_pengeluaran DESC
");
if ($parameter_riwayat !== []) {
    mysqli_stmt_bind_param($stmt_riwayat, $tipe_parameter, ...$parameter_riwayat);
}
mysqli_stmt_execute($stmt_riwayat);
$riwayat = mysqli_stmt_get_result($stmt_riwayat);
$jumlah_lunas = (int) $statistik["barang_lunas"];
$jumlah_berjalan = (int) $statistik["total_barang"] - $jumlah_lunas;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil | Wishlist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="UI.css">
</head>
<body class="bg-light">
    <main class="profile-page">
        <header class="form-page-heading">
            <div>
                <span class="text-uppercase small fw-bold text-secondary">Wishlist</span>
                <h1 class="h3 mb-1">Profil akun</h1>
                <p>Kelola informasi akun dan tinjau seluruh rencana serta transaksi milikmu.</p>
            </div>
            <a class="btn btn-outline-primary" href="index/index.php"><i class="bi bi-arrow-left me-1"></i>Kembali ke dashboard</a>
        </header>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
        <?php elseif ($success !== ""): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?></div>
        <?php endif; ?>

        <section class="mb-4" aria-label="Statistik akun">
            <div class="row row-cols-2 row-cols-md-3 row-cols-xxl-6 g-3">
                <div class="col">
                    <article class="card profile-stat-card h-100">
                        <div class="card-body">
                            <span class="profile-stat-icon"><i class="bi bi-bag-check"></i></span>
                            <div class="text-secondary small">Total barang</div>
                            <div class="h4 mb-0"><?= (int) $statistik["total_barang"] ?></div>
                        </div>
                    </article>
                </div>
                <div class="col">
                    <article class="card profile-stat-card h-100">
                        <div class="card-body">
                            <span class="profile-stat-icon gold"><i class="bi bi-bullseye"></i></span>
                            <div class="text-secondary small">Total nilai target</div>
                            <div class="h5 mb-0">Rp <?= number_format((float) $statistik["total_target"], 0, ",", ".") ?></div>
                        </div>
                    </article>
                </div>
                <div class="col">
                    <article class="card profile-stat-card h-100">
                        <div class="card-body">
                            <span class="profile-stat-icon"><i class="bi bi-credit-card"></i></span>
                            <div class="text-secondary small">Total pembayaran</div>
                            <div class="h5 mb-0">Rp <?= number_format((float) $statistik["total_pembayaran"], 0, ",", ".") ?></div>
                        </div>
                    </article>
                </div>
                <div class="col">
                    <article class="card profile-stat-card h-100">
                        <div class="card-body">
                            <span class="profile-stat-icon gold"><i class="bi bi-wallet2"></i></span>
                            <div class="text-secondary small">Total pengeluaran</div>
                            <div class="h5 mb-0">Rp <?= number_format((float) $statistik_pengeluaran["total_pengeluaran"], 0, ",", ".") ?></div>
                        </div>
                    </article>
                </div>
                <div class="col">
                    <article class="card profile-stat-card h-100">
                        <div class="card-body">
                            <span class="profile-stat-icon green"><i class="bi bi-check-circle"></i></span>
                            <div class="text-secondary small">Barang tercapai</div>
                            <div class="h4 mb-0"><?= $jumlah_lunas ?></div>
                        </div>
                    </article>
                </div>
                <div class="col">
                    <article class="card profile-stat-card h-100">
                        <div class="card-body">
                            <span class="profile-stat-icon blue"><i class="bi bi-hourglass-split"></i></span>
                            <div class="text-secondary small">Masih berjalan</div>
                            <div class="h4 mb-0"><?= $jumlah_berjalan ?></div>
                        </div>
                    </article>
                </div>
            </div>
            <p class="small text-secondary mt-2 mb-0">
                Sisa target pembayaran: Rp <?= number_format((float) $statistik["total_sisa"], 0, ",", ".") ?>
                · <?= (int) $statistik_pengeluaran["jumlah_transaksi"] ?> transaksi pengeluaran tercatat.
            </p>
        </section>

        <?php if (auth_legacy_mode_notice($koneksi)): ?>
            <div class="alert alert-warning" role="status">
                Database masih memakai mode satu akun. Data lama belum dipetakan ke <code>user_id</code>;
                pendaftaran akun tambahan dan isolasi multi-user belum aktif.
            </div>
        <?php endif; ?>

        <section class="mb-4" aria-label="Pengaturan akun">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-1">Informasi akun</h2>
                            <p class="text-secondary small mb-4">Nama pengguna: <strong><?= htmlspecialchars($account["username"], ENT_QUOTES, "UTF-8") ?></strong></p>
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                                <input type="hidden" name="profile_action" value="update_profile">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nama pengguna</label>
                                    <input class="form-control" type="text" id="username" name="username"
                                           maxlength="50" autocomplete="username"
                                           value="<?= htmlspecialchars($username, ENT_QUOTES, "UTF-8") ?>" required>
                                </div>
                                <div class="mb-4">
                                    <label for="profile_current_password" class="form-label">Konfirmasi kata sandi saat ini</label>
                                    <input class="form-control" type="password" id="profile_current_password" name="current_password"
                                           autocomplete="current-password" required>
                                </div>
                                <button class="btn btn-primary" type="submit">Simpan informasi</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-1">Ganti kata sandi</h2>
                            <p class="text-secondary small mb-4">Gunakan kata sandi baru minimal 8 karakter.</p>
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                                <input type="hidden" name="profile_action" value="change_password">
                                <div class="mb-3">
                                    <label for="password_current" class="form-label">Kata sandi saat ini</label>
                                    <input class="form-control" type="password" id="password_current" name="current_password"
                                           autocomplete="current-password" required>
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label">Kata sandi baru</label>
                                        <input class="form-control" type="password" id="new_password" name="new_password"
                                               autocomplete="new-password" minlength="8" maxlength="72" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">Ulangi kata sandi baru</label>
                                        <input class="form-control" type="password" id="confirm_password" name="confirm_password"
                                               autocomplete="new-password" minlength="8" maxlength="72" required>
                                    </div>
                                </div>
                                <button class="btn btn-primary" type="submit">Perbarui kata sandi</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card mb-4" aria-labelledby="wishlist-items-heading">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1" id="wishlist-items-heading">Daftar barang</h2>
                        <p class="text-secondary small mb-0">Seluruh barang dalam wishlist akun ini.</p>
                    </div>
                    <a class="btn btn-outline-primary btn-sm" href="index/daftar.php">Kelola wishlist</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nama barang</th>
                                <th>Kategori</th>
                                <th>Jenis</th>
                                <th class="text-end">Target</th>
                                <th class="text-end">Dibayar</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($daftar_barang) > 0): ?>
                                <?php while ($barang = mysqli_fetch_assoc($daftar_barang)): ?>
                                    <?php $barang_lunas = (float) $barang["total_dibayar"] >= (float) $barang["harga"]; ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($barang["nama_barang"], ENT_QUOTES, "UTF-8") ?></strong></td>
                                        <td><?= htmlspecialchars($barang["kategori"], ENT_QUOTES, "UTF-8") ?></td>
                                        <td><?= htmlspecialchars($barang["jenis"], ENT_QUOTES, "UTF-8") ?></td>
                                        <td class="text-end text-nowrap">Rp <?= number_format((float) $barang["harga"], 0, ",", ".") ?></td>
                                        <td class="text-end text-nowrap">Rp <?= number_format((float) $barang["total_dibayar"], 0, ",", ".") ?></td>
                                        <td><span class="badge <?= $barang_lunas ? "text-bg-success" : "text-bg-warning" ?>"><?= $barang_lunas ? "Tercapai" : "Berjalan" ?></span></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="index/edit.php?id=<?= (int) $barang["id_barang"] ?>">Edit</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-secondary py-5">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Wishlist masih kosong. Tambahkan barang untuk mulai merencanakan.
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="expense-history-heading">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1" id="expense-history-heading">Riwayat pengeluaran</h2>
                        <p class="text-secondary small mb-0">Filter transaksi berdasarkan bulan atau rentang tanggal.</p>
                    </div>
                    <a class="btn btn-outline-primary btn-sm" href="pengeluaran/pengeluaran.php">Kelola pengeluaran</a>
                </div>
                <form method="GET" action="profile.php" class="profile-history-filter row g-3 align-items-end mb-3">
                    <div class="col-sm-6 col-lg-3">
                        <label for="bulan" class="form-label">Bulan</label>
                        <input class="form-control" type="month" id="bulan" name="bulan" value="<?= htmlspecialchars($bulan, ENT_QUOTES, "UTF-8") ?>">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label for="dari" class="form-label">Dari tanggal</label>
                        <input class="form-control" type="date" id="dari" name="dari" value="<?= htmlspecialchars($dari, ENT_QUOTES, "UTF-8") ?>">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label for="sampai" class="form-label">Sampai tanggal</label>
                        <input class="form-control" type="date" id="sampai" name="sampai" value="<?= htmlspecialchars($sampai, ENT_QUOTES, "UTF-8") ?>">
                    </div>
                    <div class="col-sm-6 col-lg-3 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">Terapkan</button>
                        <a class="btn btn-outline-secondary" href="profile.php">Reset</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Pengeluaran</th>
                                <th>Barang terkait</th>
                                <th class="text-end">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($riwayat) > 0): ?>
                                <?php while ($transaksi = mysqli_fetch_assoc($riwayat)): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= htmlspecialchars(date("d/m/Y", strtotime($transaksi["tanggal_pengeluaran"])), ENT_QUOTES, "UTF-8") ?></td>
                                        <td><?= htmlspecialchars($transaksi["nama_pengeluaran"], ENT_QUOTES, "UTF-8") ?></td>
                                        <td><?= htmlspecialchars($transaksi["nama_barang"] ?? "Umum", ENT_QUOTES, "UTF-8") ?></td>
                                        <td class="text-end text-nowrap">Rp <?= number_format((float) $transaksi["jumlah"], 0, ",", ".") ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-secondary py-5">
                                    <i class="bi bi-receipt fs-3 d-block mb-2"></i>
                                    Tidak ada transaksi untuk filter ini.
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
