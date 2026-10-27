<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil | Wishlist PJK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="UI.css">
</head>
<body class="bg-light">
    <main class="container py-5 form-page">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="form-page-heading">
                    <div>
                        <h1 class="h3 mb-1">Profil akun</h1>
                        <p>Perbarui nama pengguna atau kata sandi akunmu.</p>
                    </div>
                    <a class="btn btn-outline-primary" href="index/index.php">Kembali ke dashboard</a>
                </div>
                <section class="card border-0 shadow-sm">
                    <div class="card-body p-4">
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
        </div>
    </main>
</body>
</html>
