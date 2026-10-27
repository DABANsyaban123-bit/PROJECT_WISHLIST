<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set("session.use_strict_mode", "1");
    session_set_cookie_params([
        "path" => "/",
        "httponly" => true,
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "samesite" => "Lax",
    ]);
    session_start();
}

function auth_app_base_path(): string
{
    $script_directory = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/"));
    $script_directory = rtrim($script_directory, "/");
    $base_path = preg_replace('~/(?:index|pengeluaran)$~', "", $script_directory);

    return $base_path === "" || $base_path === "." ? "" : $base_path;
}

function auth_redirect_to_login(): never
{
    header("Location: " . auth_app_base_path() . "/login.php");
    exit;
}

function auth_require_login(mysqli $connection): int
{
    $user_id = filter_var(
        $_SESSION["user_id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($user_id === false) {
        unset($_SESSION["user_id"], $_SESSION["username"]);
        auth_redirect_to_login();
    }

    $stmt = mysqli_prepare($connection, "SELECT id, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException("Gagal menyiapkan validasi sesi pengguna.");
    }

    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$user) {
        unset($_SESSION["user_id"], $_SESSION["username"]);
        auth_redirect_to_login();
    }

    $_SESSION["user_id"] = (int) $user["id"];
    $_SESSION["username"] = $user["username"];

    auth_require_ownership_ready($connection);

    return (int) $user["id"];
}

function auth_ownership_columns_exist(mysqli $connection): bool
{
    static $columns_exist = null;

    if ($columns_exist !== null) {
        return $columns_exist;
    }

    $stmt = mysqli_prepare(
        $connection,
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN ('barang', 'pengeluaran')
           AND COLUMN_NAME = 'user_id'"
    );
    if (!$stmt) {
        throw new RuntimeException("Gagal memeriksa skema kepemilikan data.");
    }

    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $count = (int) ($row["total"] ?? 0);
    if ($count !== 0 && $count !== 2) {
        auth_stop_for_incomplete_ownership();
    }

    $columns_exist = $count === 2;
    return $columns_exist;
}

function auth_legacy_mode_notice(mysqli $connection): bool
{
    return !auth_ownership_columns_exist($connection);
}

function auth_require_ownership_ready(mysqli $connection): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    if (!auth_ownership_columns_exist($connection)) {
        $result = mysqli_query($connection, "SELECT COUNT(*) AS total FROM users");
        if (!$result) {
            throw new RuntimeException("Gagal memeriksa jumlah akun.");
        }

        $user_count = (int) mysqli_fetch_assoc($result)["total"];
        mysqli_free_result($result);

        if ($user_count !== 1) {
            auth_stop_for_incomplete_ownership();
        }

        $checked = true;
        return;
    }

    if (!auth_ownership_data_is_valid($connection)) {
        auth_stop_for_incomplete_ownership();
    }

    if (!auth_ownership_constraints_ready($connection)) {
        auth_stop_for_incomplete_ownership();
    }

    $checked = true;
}

function auth_ownership_data_is_valid(mysqli $connection): bool
{
    $result = mysqli_query(
        $connection,
        "SELECT
            (SELECT COUNT(*)
             FROM barang b
             LEFT JOIN users u ON u.id = b.user_id
             WHERE b.user_id IS NULL OR u.id IS NULL) AS invalid_items,
            (SELECT COUNT(*)
             FROM pengeluaran p
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.user_id IS NULL OR u.id IS NULL) AS invalid_expenses,
            (SELECT COUNT(*)
             FROM pengeluaran p
             INNER JOIN barang b ON b.id_barang = p.id_barang
             WHERE p.id_barang IS NOT NULL
               AND p.user_id <> b.user_id) AS cross_account_expenses"
    );

    if (!$result) {
        throw new RuntimeException("Gagal memverifikasi kepemilikan data.");
    }

    $integrity = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return (int) $integrity["invalid_items"] === 0
        && (int) $integrity["invalid_expenses"] === 0
        && (int) $integrity["cross_account_expenses"] === 0;
}

function auth_registration_enabled(mysqli $connection): bool
{
    return auth_ownership_columns_exist($connection)
        && auth_ownership_data_is_valid($connection)
        && auth_ownership_constraints_ready($connection);
}

function auth_ownership_constraints_ready(mysqli $connection): bool
{
    $result = mysqli_query(
        $connection,
        "SELECT
            (SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('barang', 'pengeluaran')
               AND COLUMN_NAME = 'user_id'
               AND IS_NULLABLE = 'NO') AS non_nullable_owners,
            (SELECT COUNT(*)
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'barang'
               AND COLUMN_NAME = 'user_id'
               AND REFERENCED_TABLE_NAME = 'users'
               AND REFERENCED_COLUMN_NAME = 'id') AS item_owner_foreign_keys,
            (SELECT COUNT(*)
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'pengeluaran'
               AND COLUMN_NAME = 'user_id'
               AND REFERENCED_TABLE_NAME = 'users'
               AND REFERENCED_COLUMN_NAME = 'id') AS expense_owner_foreign_keys,
            (SELECT COUNT(*)
             FROM (
                 SELECT CONSTRAINT_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pengeluaran'
                   AND REFERENCED_TABLE_NAME = 'barang'
                 GROUP BY CONSTRAINT_NAME
                 HAVING COUNT(*) = 2
                    AND SUM(COLUMN_NAME = 'id_barang'
                            AND REFERENCED_COLUMN_NAME = 'id_barang') = 1
                    AND SUM(COLUMN_NAME = 'user_id'
                            AND REFERENCED_COLUMN_NAME = 'user_id') = 1
             ) AS ownership_links) AS item_owner_composite_foreign_keys,
            (SELECT COUNT(*)
             FROM (
                 SELECT INDEX_NAME
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'barang'
                   AND NON_UNIQUE = 0
                 GROUP BY INDEX_NAME
                 HAVING COUNT(*) = 2
                    AND SUM(SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'id_barang') = 1
                    AND SUM(SEQ_IN_INDEX = 2 AND COLUMN_NAME = 'user_id') = 1
             ) AS owner_unique_indexes) AS item_owner_unique_indexes,
            (SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('users', 'barang', 'pengeluaran')
               AND ENGINE = 'InnoDB') AS innodb_tables"
    );

    if (!$result) {
        throw new RuntimeException("Gagal memeriksa constraint kepemilikan data.");
    }

    $constraints = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return (int) $constraints["non_nullable_owners"] === 2
        && (int) $constraints["item_owner_foreign_keys"] >= 1
        && (int) $constraints["expense_owner_foreign_keys"] >= 1
        && (int) $constraints["item_owner_composite_foreign_keys"] >= 1
        && (int) $constraints["item_owner_unique_indexes"] >= 1
        && (int) $constraints["innodb_tables"] === 3;
}

function auth_stop_for_incomplete_ownership(): never
{
    http_response_code(503);
    exit("Fitur data sementara dinonaktifkan sampai migrasi kepemilikan akun selesai dan terverifikasi.");
}

function auth_user_scope(mysqli $connection, string $table_alias): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table_alias)) {
        throw new InvalidArgumentException("Alias tabel tidak valid.");
    }

    if (!auth_ownership_columns_exist($connection)) {
        if (!auth_legacy_single_user_mode($connection)) {
            auth_stop_for_incomplete_ownership();
        }

        return "1 = 1";
    }

    $user_id = filter_var(
        $_SESSION["user_id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );
    if ($user_id === false) {
        throw new LogicException("Identitas pengguna belum diverifikasi.");
    }

    return $table_alias . ".user_id = " . (int) $user_id;
}

function auth_legacy_single_user_mode(mysqli $connection): bool
{
    static $is_single_user = null;
    if ($is_single_user !== null) {
        return $is_single_user;
    }

    $result = mysqli_query($connection, "SELECT COUNT(*) AS total FROM users");
    if (!$result) {
        throw new RuntimeException("Gagal memeriksa mode kepemilikan data.");
    }

    $is_single_user = (int) mysqli_fetch_assoc($result)["total"] === 1;
    mysqli_free_result($result);
    return $is_single_user;
}

function auth_owner_insert_columns(mysqli $connection): string
{
    return auth_ownership_columns_exist($connection) ? ", user_id" : "";
}

function auth_owner_insert_values(mysqli $connection): string
{
    if (!auth_ownership_columns_exist($connection)) {
        return "";
    }

    $user_id = filter_var(
        $_SESSION["user_id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );
    if ($user_id === false) {
        throw new LogicException("Identitas pengguna belum diverifikasi.");
    }

    return ", " . (int) $user_id;
}

function auth_ensure_users_table(mysqli $connection): void
{
    if (!mysqli_query($connection, "
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ")) {
        throw new RuntimeException("Gagal menyiapkan tabel akun.");
    }
}

function auth_csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function auth_verify_csrf(mixed $token): bool
{
    return isset($_SESSION["csrf_token"])
        && is_string($token)
        && hash_equals($_SESSION["csrf_token"], $token);
}

function auth_one_time_token(string $key): string
{
    if (!isset($_SESSION["one_time_tokens"]) || !is_array($_SESSION["one_time_tokens"])) {
        $_SESSION["one_time_tokens"] = [];
    }

    if (!isset($_SESSION["one_time_tokens"][$key])) {
        $_SESSION["one_time_tokens"][$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION["one_time_tokens"][$key];
}

function auth_consume_one_time_token(string $key, mixed $token): bool
{
    $valid = isset($_SESSION["one_time_tokens"])
        && is_array($_SESSION["one_time_tokens"])
        && isset($_SESSION["one_time_tokens"][$key])
        && is_string($token)
        && hash_equals($_SESSION["one_time_tokens"][$key], $token);

    if ($valid) {
        unset($_SESSION["one_time_tokens"][$key]);
    }

    return $valid;
}
