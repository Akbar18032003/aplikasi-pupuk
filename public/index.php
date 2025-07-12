<?php
session_start(); // Mulai sesi PHP

// Redirect ke halaman login jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Berdasarkan peran, arahkan ke dashboard yang sesuai
switch ($_SESSION['role']) {
    case 'admin':
        header("Location: ../admin/index.php");
        break;
    case 'sopir':
        header("Location: ../sopir/index.php");
        break;
    case 'pelanggan':
        header("Location: ../pelanggan/index.php");
        break;
    default:
        // Jika peran tidak dikenali atau error, paksa logout
        session_unset();
        session_destroy();
        header("Location: login.php");
        break;
}
exit;
?>