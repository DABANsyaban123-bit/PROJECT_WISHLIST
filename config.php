<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "wishlist_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $koneksi = mysqli_connect($host, $user, $password, $database);
    mysqli_set_charset($koneksi, "utf8mb4");
} catch (mysqli_sql_exception $exception) {
    error_log("Wishlist PJK database connection failed: " . $exception->getMessage());
    http_response_code(500);
    exit("Koneksi database tidak tersedia. Periksa konfigurasi Laragon.");
}
