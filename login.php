<?php
include_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

if (isset($_SESSION["user_id"])) {
    header("Location: index/index.php");
    exit;
}

$error = "";
$username = "";
$has_account = true;

try {
    auth_ensure_users_table($koneksi);
    $account_result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM users");
    $has_account = (int) mysqli_fetch_assoc($account_result)["total"] > 0;
} catch (mysqli_sql_exception $exception) {
    error_log("Wishlist PJK login setup failed: " . $exception->getMessage());
    $error = "Tidak dapat menyiapkan akun. Pastikan database tersedia dan memiliki izin membuat tabel.";
}

if (!$has_account && $error === "") {
    header("Location: setup.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {
    $username_input = $_POST["username"] ?? "";
    $username = is_string($username_input) ? trim($username_input) : "";
    $password = $_POST["password"] ?? "";
    $password = is_string($password) ? $password : "";

    if (!auth_verify_csrf($_POST["csrf_token"] ?? null)) {
        $error = "Sesi formulir kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user["password_hash"])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = (int) $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            header("Location: index/index.php");
            exit;
        }

        $error = "Nama pengguna atau kata sandi tidak sesuai.";
    }
}

$csrf_token = auth_csrf_token();
$setup_complete = ($_GET["setup"] ?? "") === "complete";
$registration_complete = ($_GET["registered"] ?? "") === "1";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk | Wishlist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="UI.css">
</head>
<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-intro">
            <a class="auth-brand" href="index.php">
                <span class="auth-brand-mark"><i class="bi bi-bag-heart-fill"></i></span>
                Wishlist
            </a>
            <div class="auth-intro-copy">
                <span class="auth-kicker">Rencanakan dengan bijak</span>
                <h1>Hal-hal yang kamu inginkan, jadi lebih teratur.</h1>
                <p>Simpan daftar kebutuhan, pantau progres, dan kelola pengeluaran dalam satu tempat.</p>
            </div>
            <div class="auth-intro-note"><i class="bi bi-shield-check"></i> Ruang pribadi untuk rencana belanjamu</div>
        </section>

        <section class="auth-panel-wrap">
            <div class="auth-panel">
                <div class="auth-panel-icon"><i class="bi bi-person-lock"></i></div>
                <span class="auth-kicker">Selamat datang kembali</span>
                <h2>Masuk ke akunmu</h2>
                <p class="auth-description">Masukkan detail akun untuk melanjutkan.</p>

                <?php if ($setup_complete): ?>
                    <div class="alert alert-success" role="status">Akun berhasil dibuat. Silakan masuk.</div>
                <?php elseif ($registration_complete): ?>
                    <div class="alert alert-success" role="status">Pendaftaran berhasil. Silakan masuk.</div>
                <?php endif; ?>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nama pengguna</label>
                        <div class="auth-input-wrap">
                            <i class="bi bi-person"></i>
                            <input class="form-control" type="text" id="username" name="username"
                                   autocomplete="username" maxlength="50" value="<?= htmlspecialchars($username, ENT_QUOTES, "UTF-8") ?>" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Kata sandi</label>
                        <div class="auth-input-wrap">
                            <i class="bi bi-lock"></i>
                            <input class="form-control" type="password" id="password" name="password"
                                   autocomplete="current-password" required>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 auth-submit" type="submit">
                        Masuk <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
                <?php if (!$has_account): ?>
                    <p class="auth-footnote">Belum ada akun? <a href="setup.php">Siapkan akun pertama</a></p>
                <?php else: ?>
                    <p class="auth-footnote">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
                <?php endif; ?>
            </div>
            <div class="auth-copyright">Wishlist <span>&middot;</span> Kelola rencanamu dengan tenang</div>
        </section>
    </main>
</body>
</html>
