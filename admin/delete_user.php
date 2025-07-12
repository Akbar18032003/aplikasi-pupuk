<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$user_id = $_GET['id'] ?? null;

if (!$user_id || !is_numeric($user_id)) {
    $_SESSION['message'] = "ID pengguna tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_users.php");
    exit;
}
$stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('sopir', 'pelanggan')");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Pengguna berhasil dihapus.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Gagal menghapus pengguna atau pengguna tidak ditemukan (mungkin Admin lain).";
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "Terjadi kesalahan saat menghapus pengguna: " . $stmt->error;
    $_SESSION['message_type'] = "error";
}

$stmt->close();
close_db_connection($conn);
header("Location: manage_users.php");
exit;
?>