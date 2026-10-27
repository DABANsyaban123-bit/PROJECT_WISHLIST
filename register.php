<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

if (isset($_SESSION["user_id"])) {
    header("Location: index/index.php");
    exit;
}

$error = "";
$username = "";
$registration_enabled = auth_registration_enabled($koneksi);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username_input = $_POST["username"] ?? "";
    $password_input = $_POST["password"] ?? "";
    $confirm_input = $_POST["confirm_password"] ?? "";
    $username = is_string($username_input) ? trim($username_input) : "";
    $password = is_string($password_input) ? $password_input : "";
    $confirm_password = is_string($confirm_input) ? $confirm_input : "";

    if (!auth_verify_csrf($_POST["csrf_token"] ?? null)) {
        $error = "Sesi formulir kedaluwarsa. Silakan muat ulang halaman.";
    } elseif (!$registration_enabled || !auth_registration_enabled($koneksi)) {
        $error = "Pendaftaran belum tersedia sampai migrasi kepemilikan data selesai dan terverifikasi.";
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = "Nama pengguna harus 3-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.";
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = "Kata sandi harus terdiri dari 8-72 byte.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi kata sandi tidak sama.";
    } else {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $statement = mysqli_prepare(
                $koneksi,
                "INSERT INTO users (username, password_hash) VALUES (?, ?)"
            );
            mysqli_stmt_bind_param($statement, "ss", $username, $password_hash);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            header("Location: login.php?registered=1");
            exit;
        } catch (mysqli_sql_exception $exception) {
            error_log("Wishlist PJK account registration failed: " . $exception->getMessage());
            $error = "Akun gagal dibuat. Nama pengguna mungkin sudah digunakan.";
        }
    }
}

$csrf_token = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar | Wishlist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="UI.css">
</head>
<body class="auth-page auth-page-simple">
    <main class="auth-simple-layout">
        <a class="auth-brand auth-brand-dark" href="index.php">Wishlist</a>
        <section class="auth-panel">
            <div class="auth-panel-icon"><i class="bi bi-person-plus"></i></div>
            <span class="auth-kicker">Akun baru</span>
            <h1 class="h2">Daftar Wishlist</h1>
            <?php if (!$registration_enabled): ?>
                <div class="alert alert-warning" role="status">
                    Pendaftaran ditutup sementara sampai migrasi kepemilikan data selesai dan diverifikasi.
                    Data lama tetap dapat diakses melalui akun yang sudah ada.
                </div>
            <?php elseif ($error !== ""): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
            <?php endif; ?>

            <?php if ($registration_enabled): ?>
                <p class="auth-description">Buat akun untuk mengelola wishlist dan catatan keuangan pribadimu.</p>
                <form method="POST" action="register.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nama pengguna</label>
                        <input class="form-control" type="text" id="username" name="username"
                               autocomplete="username" maxlength="50"
                               value="<?= htmlspecialchars($username, ENT_QUOTES, "UTF-8") ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Kata sandi</label>
                        <input class="form-control" type="password" id="password" name="password"
                               autocomplete="new-password" minlength="8" maxlength="72" required>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Konfirmasi kata sandi</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password"
                               autocomplete="new-password" maxlength="72" required>
                    </div>
                    <button class="btn btn-primary w-100 auth-submit" type="submit">Buat akun</button>
                </form>
            <?php endif; ?>
            <p class="auth-footnote"><a href="login.php">Kembali ke login</a></p>
        </section>
    </main>
</body>
</html>
