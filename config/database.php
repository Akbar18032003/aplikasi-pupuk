<?php
// Konfigurasi koneksi database
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Default XAMPP tanpa password
define('DB_PASSWORD', '');
define('DB_NAME', 'db_pengiriman_pupuk');

// Buat koneksi menggunakan MySQLi
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Set karakter encoding
$conn->set_charset("utf8");

// Fungsi untuk menutup koneksi (bisa dipanggil di akhir skrip)
function close_db_connection($conn) {
    $conn->close();
}
?>