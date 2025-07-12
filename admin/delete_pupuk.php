<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$pupuk_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$pupuk_id) {
    $_SESSION['message'] = "ID pupuk tidak valid atau tidak disediakan.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_pupuk.php");
    exit;
}

$check_stmt = $conn->prepare("SELECT id FROM detail_pesanan WHERE id_pupuk = ? LIMIT 1");
$check_stmt->bind_param("i", $pupuk_id);
$check_stmt->execute();

if ($check_stmt->get_result()->num_rows > 0) {
    $_SESSION['message'] = "Pupuk tidak dapat dihapus karena sudah menjadi bagian dari riwayat pesanan. Pertimbangkan untuk menonaktifkan produk, bukan menghapusnya.";
    $_SESSION['message_type'] = "error";

} else {

    $stmt = $conn->prepare("DELETE FROM pupuk WHERE id = ?");
    $stmt->bind_param("i", $pupuk_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = "Pupuk berhasil dihapus secara permanen.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal menghapus pupuk: ID tidak ditemukan.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Terjadi kesalahan pada server saat menghapus pupuk.";
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
}
$check_stmt->close();
close_db_connection($conn);
header("Location: manage_pupuk.php");
exit;
?>