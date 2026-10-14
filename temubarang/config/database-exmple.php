<?php
// Contoh konfigurasi lokal TemuBarang.
// Salin menjadi database.php dan sesuaikan
// dengan pengaturan MySQL milik masing-masing.

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'temubarang';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die('Koneksi database gagal.');
}

mysqli_set_charset($conn, 'utf8mb4');