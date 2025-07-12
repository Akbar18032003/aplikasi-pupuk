<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Cek login & role ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$user_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$message = '';
$message_type = '';

if (!$user_id) {
    $_SESSION['message'] = "ID pengguna tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_users.php");
    exit;
}

// --- Proses Update Form ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $alamat = trim($_POST['alamat']);
    $telepon = trim($_POST['telepon']);
    $role = $_POST['role'];

    // Validasi input
    if (empty($username) || empty($nama_lengkap) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($role)) {
        $message = "Semua kolom harus diisi dengan format yang benar (termasuk format email).";
        $message_type = "error";
    } elseif (!in_array($role, ['sopir', 'pelanggan'])) {
        $message = "Peran yang dipilih tidak valid.";
        $message_type = "error";
    } else {
        // Cek duplikasi username/email
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt_check->bind_param("ssi", $username, $email, $user_id);
        $stmt_check->execute();

        if ($stmt_check->get_result()->num_rows > 0) {
            $message = "Username atau email sudah digunakan oleh pengguna lain.";
            $message_type = "error";
        } else {
            // Logika untuk update password kondisional
            if (!empty($_POST['password'])) {
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET username=?, password=?, nama_lengkap=?, email=?, alamat=?, telepon=?, role=? WHERE id=?");
                $stmt_update->bind_param("sssssssi", $username, $hashed_password, $nama_lengkap, $email, $alamat, $telepon, $role, $user_id);
            } else {
                $stmt_update = $conn->prepare("UPDATE users SET username=?, nama_lengkap=?, email=?, alamat=?, telepon=?, role=? WHERE id=?");
                $stmt_update->bind_param("ssssssi", $username, $nama_lengkap, $email, $alamat, $telepon, $role, $user_id);
            }

            if ($stmt_update->execute()) {
                $_SESSION['message'] = "Data pengguna '".htmlspecialchars($username)."' berhasil diperbarui.";
                $_SESSION['message_type'] = "success";
                header("Location: manage_users.php");
                exit;
            } else {
                $message = "Gagal memperbarui data: " . $stmt_update->error;
                $message_type = "error";
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}

// Ambil data awal untuk ditampilkan di form
$stmt_get = $conn->prepare("SELECT id, username, nama_lengkap, email, alamat, telepon, role FROM users WHERE id = ?");
$stmt_get->bind_param("i", $user_id);
$stmt_get->execute();
$result = $stmt_get->get_result();

if ($result->num_rows === 1) {
    $user_data = $result->fetch_assoc();
} else {
    $_SESSION['message'] = "Pengguna tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_users.php");
    exit;
}
$stmt_get->close();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Konsisten */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; transition: transform 0.3s ease; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 1.4rem; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link.active { background: #3498db; font-weight: 600; }
        .nav-link:hover:not(.active) { background: rgba(52,152,219,0.2); }
        .nav-link i { margin-right: 15px; }
        .main-content { margin-left: 280px; padding: 40px; }
        .page-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; }
        .page-header h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; display:flex; align-items:center; }
        .page-header h1 i { margin-right:15px; color:#3498db; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); max-width:800px; margin: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 10px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 15px; border: 2px solid #e9ecef; border-radius: 12px; background: #f8f9fa; font-size: 1rem; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-group small { color: #7f8c8d; font-size: 0.85em; }
        .button-group { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #e9ecef; padding-top: 25px; }
        .btn { padding: 15px 30px; border: none; border-radius: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; }
        .btn i { margin-right: 8px; }
        .btn-primary { background: #3498db; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; }
        .message.error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-user-shield"></i> Admin Panel</h3></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <!-- Aktifkan menu ini -->
            <div class="nav-item"><a href="manage_users.php" class="nav-link active"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-edit"></i> Edit Pengguna</h1>
            <p>Perbarui detail untuk pengguna: <strong><?php echo htmlspecialchars($user_data['username']); ?></strong></p>
        </div>

        <div class="form-container">
             <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="edit_user.php?id=<?php echo $user_id; ?>" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                    </div>
                     <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" required>
                    </div>
                     <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>
                     <div class="form-group">
                        <label for="telepon">Nomor Telepon</label>
                        <input type="tel" id="telepon" name="telepon" value="<?php echo htmlspecialchars($user_data['telepon']); ?>" required>
                    </div>
                     <div class="form-group">
                        <label for="role">Peran (Role)</label>
                        <select id="role" name="role" required>
                            <option value="pelanggan" <?php echo ($user_data['role'] == 'pelanggan') ? 'selected' : ''; ?>>Pelanggan</option>
                            <option value="sopir" <?php echo ($user_data['role'] == 'sopir') ? 'selected' : ''; ?>>Sopir</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password">Password Baru</label>
                        <input type="password" id="password" name="password" placeholder="Isi untuk mengubah password">
                        <small>Kosongkan jika tidak ingin mengubah password.</small>
                    </div>
                    <div class="form-group full-width">
                        <label for="alamat">Alamat Lengkap</label>
                        <textarea id="alamat" name="alamat" rows="3" required><?php echo htmlspecialchars($user_data['alamat']); ?></textarea>
                    </div>
                </div>
                <div class="button-group">
                    <a href="manage_users.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
close_db_connection($conn);
?>