<?php
include_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

$create_table = mysqli_query($koneksi, "
    CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (!$create_table) {
    die("Gagal menyiapkan tabel akun: " . mysqli_error($koneksi));
}

$result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM users");

if (!$result) {
    die("Gagal memeriksa akun: " . mysqli_error($koneksi));
}

$has_account = (int) mysqli_fetch_assoc($result)["total"] > 0;
$error = "";
$username = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$has_account) {
    $username_input = $_POST["username"] ?? "";
    $username = is_string($username_input) ? trim($username_input) : "";
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $password = is_string($password) ? $password : "";
    $confirm_password = is_string($confirm_password) ? $confirm_password : "";

    if (!auth_verify_csrf($_POST["csrf_token"] ?? null)) {
        $error = "Sesi formulir kedaluwarsa. Silakan muat ulang halaman.";
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = "Nama pengguna harus 3-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.";
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = "Kata sandi harus terdiri dari 8-72 byte.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi kata sandi tidak sama.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($koneksi, "INSERT INTO users (username, password_hash) VALUES (?, ?)");

        if (!$stmt) {
            die("Gagal menyiapkan akun: " . mysqli_error($koneksi));
        }

        mysqli_stmt_bind_param($stmt, "ss", $username, $hash);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: login.php?setup=complete");
            exit;
        }

        $error = "Akun gagal dibuat. Nama pengguna mungkin sudah digunakan.";
        mysqli_stmt_close($stmt);
    }
}

$csrf_token = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyiapan Akun | Wishlist PJK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="UI.css">
</head>
<body class="auth-page auth-page-simple">
    <main class="auth-simple-layout">
        <a class="auth-brand auth-brand-dark" href="login.php">Wishlist PJK</a>
        <section class="auth-panel">
            <div class="auth-panel-icon"><i class="bi bi-person-plus"></i></div>
            <span class="auth-kicker">Pengaturan awal</span>
            <h1 class="h2">Buat akun pertama</h1>
            <?php if ($has_account): ?>
                <p class="auth-description">Penyiapan akun sudah selesai. Pembuatan akun tambahan dinonaktifkan.</p>
                <a class="btn btn-primary w-100 auth-submit" href="login.php">Lanjut ke login</a>
            <?php else: ?>
                <p class="auth-description">Akun pertama ini akan digunakan untuk masuk ke Wishlist PJK.</p>
                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
                <?php endif; ?>
                <form method="POST" action="setup.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nama pengguna</label>
                        <input class="form-control" type="text" id="username" name="username" maxlength="50"
                               autocomplete="username" value="<?= htmlspecialchars($username, ENT_QUOTES, "UTF-8") ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Kata sandi</label>
                        <input class="form-control" type="password" id="password" name="password" minlength="8" maxlength="72"
                               autocomplete="new-password" required>
                        <div class="form-text">Gunakan kata sandi sepanjang 8-72 byte.</div>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Ulangi kata sandi</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password"
                               maxlength="72" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary w-100 auth-submit" type="submit">Buat akun</button>
                </form>
            <?php endif; ?>
            <p class="auth-footnote"><a href="login.php">Kembali ke halaman login</a></p>
        </section>
    </main>
</body>
</html>
