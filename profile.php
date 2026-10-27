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
    $username_input = $_POST["username"] ?? "";
    $current_password_input = $_POST["current_password"] ?? "";
    $new_password_input = $_POST["new_password"] ?? "";
    $confirm_password_input = $_POST["confirm_password"] ?? "";
    $username = is_string($username_input) ? trim($username_input) : "";
    $current_password = is_string($current_password_input) ? $current_password_input : "";
    $new_password = is_string($new_password_input) ? $new_password_input : "";
    $confirm_password = is_string($confirm_password_input) ? $confirm_password_input : "";
    $password_change_requested = $new_password !== "" || $confirm_password !== "";

    if (!auth_verify_csrf($_POST["csrf_token"] ?? null)) {
        $error = "Sesi formulir kedaluwarsa. Silakan muat ulang halaman.";
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = "Nama pengguna harus 3-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.";
    } elseif (!password_verify($current_password, $account["password_hash"])) {
        $error = "Kata sandi saat ini tidak sesuai.";
    } elseif ($password_change_requested && (strlen($new_password) < 8 || strlen($new_password) > 72)) {
        $error = "Kata sandi baru harus terdiri dari 8-72 byte.";
    } elseif ($password_change_requested && $new_password !== $confirm_password) {
        $error = "Konfirmasi kata sandi baru tidak sama.";
    } else {
        try {
            if ($password_change_requested) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $statement = mysqli_prepare(
                    $koneksi,
                    "UPDATE users SET username = ?, password_hash = ? WHERE id = ?"
                );
                mysqli_stmt_bind_param($statement, "ssi", $username, $new_hash, $user_id);
            } else {
                $statement = mysqli_prepare(
                    $koneksi,
                    "UPDATE users SET username = ? WHERE id = ?"
                );
                mysqli_stmt_bind_param($statement, "si", $username, $user_id);
            }

            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $_SESSION["username"] = $username;

            if ($password_change_requested) {
                session_regenerate_id(true);
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                $account["password_hash"] = $new_hash;
            }

            $account["username"] = $username;
            $success = "Profil berhasil diperbarui.";
        } catch (mysqli_sql_exception $exception) {
            error_log("Wishlist PJK profile update failed: " . $exception->getMessage());
            $error = "Profil gagal diperbarui. Nama pengguna mungkin sudah digunakan.";
        }
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

$stmt_riwayat = mysqli_prepare($koneksi, "
    SELECT p.nama_pengeluaran, p.jumlah, p.tanggal_pengeluaran,
           b.nama_barang
    FROM pengeluaran p
    LEFT JOIN barang b
      ON b.id_barang = p.id_barang
     AND " . auth_user_scope($koneksi, "b") . "
    WHERE " . auth_user_scope($koneksi, "p") . "
    ORDER BY p.tanggal_pengeluaran DESC, p.id_pengeluaran DESC
    LIMIT 5
");
mysqli_stmt_execute($stmt_riwayat);
$riwayat = mysqli_stmt_get_result($stmt_riwayat);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil | Wishlist PJK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="UI.css">
</head>
<body class="bg-light">
    <main class="container py-5 form-page">
        <div class="form-page-heading">
            <div>
                <h1 class="h3 mb-1">Profil akun</h1>
                <p>Kelola akun dan lihat ringkasan rencana keuanganmu.</p>
            </div>
            <a class="btn btn-outline-primary" href="index/index.php">Kembali ke dashboard</a>
        </div>

        <section class="mb-4" aria-label="Statistik akun">
            <div class="row g-3">
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-secondary small mb-2">Total barang</div>
                            <div class="h4 mb-0"><?= (int) $statistik["total_barang"] ?></div>
                            <div class="small text-secondary mt-2"><?= (int) $statistik["barang_lunas"] ?> sudah lunas</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-secondary small mb-2">Total target</div>
                            <div class="h5 mb-0">Rp <?= number_format((float) $statistik["total_target"], 0, ",", ".") ?></div>
                            <div class="small text-secondary mt-2">Seluruh wishlist milikmu</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-secondary small mb-2">Pembayaran barang</div>
                            <div class="h5 mb-0">Rp <?= number_format((float) $statistik["total_pembayaran"], 0, ",", ".") ?></div>
                            <div class="small text-secondary mt-2">Sisa target Rp <?= number_format((float) $statistik["total_sisa"], 0, ",", ".") ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-secondary small mb-2">Total pengeluaran</div>
                            <div class="h5 mb-0">Rp <?= number_format((float) $statistik_pengeluaran["total_pengeluaran"], 0, ",", ".") ?></div>
                            <div class="small text-secondary mt-2"><?= (int) $statistik_pengeluaran["jumlah_transaksi"] ?> transaksi tercatat</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-lg-5">
                <section class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-4">Informasi akun</h2>
                        <?php if (auth_legacy_mode_notice($koneksi)): ?>
                            <div class="alert alert-warning" role="status">
                                Data akun masih berjalan pada skema satu akun. Registrasi dan isolasi multi-user menunggu
                                migrasi serta pemetaan data lama yang telah dikonfirmasi.
                            </div>
                        <?php endif; ?>
                        <?php if ($error !== ""): ?>
                            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
                        <?php elseif ($success !== ""): ?>
                            <div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?></div>
                        <?php endif; ?>
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nama pengguna</label>
                                <input class="form-control" type="text" id="username" name="username"
                                       maxlength="50" autocomplete="username"
                                       value="<?= htmlspecialchars($username, ENT_QUOTES, "UTF-8") ?>" required>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Kata sandi saat ini</label>
                                <input class="form-control" type="password" id="current_password" name="current_password"
                                       autocomplete="current-password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Kata sandi baru <span class="text-secondary fw-normal">(opsional)</span></label>
                                <input class="form-control" type="password" id="new_password" name="new_password"
                                       autocomplete="new-password" minlength="8" maxlength="72">
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Konfirmasi kata sandi baru</label>
                                <input class="form-control" type="password" id="confirm_password" name="confirm_password"
                                       autocomplete="new-password" maxlength="72">
                            </div>
                            <button class="btn btn-primary" type="submit">Simpan perubahan</button>
                        </form>
                    </div>
                </section>
            </div>

            <section class="col-lg-7" aria-labelledby="recent-expenses-heading">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                            <h2 class="h5 mb-0" id="recent-expenses-heading">Riwayat terbaru</h2>
                            <a class="small" href="pengeluaran/pengeluaran.php">Lihat semua</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Pengeluaran</th>
                                        <th>Barang</th>
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
                                        <tr>
                                            <td colspan="4" class="text-center text-secondary py-4">
                                                Belum ada transaksi pengeluaran.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
