<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$pupuk_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$pupuk_data = null;
$message = '';
$message_type = '';

if (!$pupuk_id) {
    $_SESSION['message'] = "ID pupuk tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_pupuk.php");
    exit;
}

// Proses formulir saat di-submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_pupuk = trim($_POST['nama_pupuk']);
    $jenis_pupuk = trim($_POST['jenis_pupuk']);
    $kemasan = trim($_POST['kemasan']); // --- DITAMBAHKAN ---
    $deskripsi = trim($_POST['deskripsi']);
    $harga_per_unit = filter_var($_POST['harga_per_unit'], FILTER_VALIDATE_FLOAT);
    $stok = filter_var($_POST['stok'], FILTER_VALIDATE_INT);

    // --- DIUBAH --- Validasi diperbarui untuk menyertakan 'kemasan'
    if (empty($nama_pupuk) || empty($jenis_pupuk) || empty($kemasan) || empty($deskripsi) || $harga_per_unit === false || $stok === false) {
        $message = "Semua kolom harus diisi dengan format yang benar.";
        $message_type = "error";
    } elseif ($harga_per_unit < 0 || $stok < 0) {
        $message = "Harga dan stok tidak boleh negatif.";
        $message_type = "error";
    } else {
        // Cek duplikasi nama, kecuali untuk ID saat ini
        $stmt_check = $conn->prepare("SELECT id FROM pupuk WHERE nama_pupuk = ? AND id != ?");
        $stmt_check->bind_param("si", $nama_pupuk, $pupuk_id);
        $stmt_check->execute();

        if ($stmt_check->get_result()->num_rows > 0) {
            $message = "Nama pupuk ini sudah digunakan oleh produk lain.";
            $message_type = "error";
        } else {
            // --- DIUBAH --- Query UPDATE diperbarui dengan kolom 'kemasan'
            $stmt_update = $conn->prepare("UPDATE pupuk SET nama_pupuk = ?, jenis_pupuk = ?, kemasan = ?, deskripsi = ?, harga_per_unit = ?, stok = ? WHERE id = ?");
            
            // --- DIUBAH --- bind_param diperbarui (s,s,s,s,d,i,i -> ssssdii) dan $kemasan ditambahkan
            $stmt_update->bind_param("ssssdii", $nama_pupuk, $jenis_pupuk, $kemasan, $deskripsi, $harga_per_unit, $stok, $pupuk_id);

            if ($stmt_update->execute()) {
                $_SESSION['message'] = "Data pupuk '".htmlspecialchars($nama_pupuk)."' berhasil diperbarui.";
                $_SESSION['message_type'] = "success";
                header("Location: manage_pupuk.php");
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

// Ambil data pupuk untuk ditampilkan di form (Query sudah benar, menyertakan 'kemasan')
$stmt_get = $conn->prepare("SELECT id, nama_pupuk, jenis_pupuk, kemasan, deskripsi, harga_per_unit, stok FROM pupuk WHERE id = ?");
$stmt_get->bind_param("i", $pupuk_id);
$stmt_get->execute();
$result = $stmt_get->get_result();

if ($result->num_rows === 1) {
    $pupuk_data = $result->fetch_assoc();
} else {
    $_SESSION['message'] = "Pupuk dengan ID tersebut tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_pupuk.php");
    exit;
}
$stmt_get->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pupuk - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Gaya CSS Anda tetap sama */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 1.4rem; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; }
        .nav-link.active { background: #3498db; font-weight: 600; }
        .nav-link:hover:not(.active) { background: rgba(52,152,219,0.2); }
        .nav-link i { margin-right: 15px; }
        .main-content { margin-left: 280px; padding: 40px; }
        .page-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; }
        .page-header h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; display:flex; align-items:center; }
        .page-header h1 i { margin-right:15px; color:#27ae60; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); max-width:800px; margin: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 10px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input, .form-group textarea { width: 100%; padding: 15px; border: 2px solid #e9ecef; border-radius: 12px; background: #f8f9fa; font-size: 1rem; }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
        .button-group { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #e9ecef; padding-top: 25px; }
        .btn { padding: 15px 30px; border: none; border-radius: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; }
        .btn i { margin-right: 8px; }
        .btn-primary { background: #27ae60; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .message.success { background-color: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="sidebar">
        <!-- Sidebar HTML tetap sama -->
        <div class="sidebar-header"><h3><i class="fas fa-user-shield"></i> Admin Panel</h3></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link active"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-edit"></i> Edit Data Pupuk</h1>
            <p>Perbarui detail untuk produk: <strong><?php echo htmlspecialchars($pupuk_data['nama_pupuk']); ?></strong></p>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="edit_pupuk.php?id=<?php echo $pupuk_id; ?>" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nama_pupuk">Nama Pupuk</label>
                        <input type="text" id="nama_pupuk" name="nama_pupuk" value="<?php echo htmlspecialchars($_POST['nama_pupuk'] ?? $pupuk_data['nama_pupuk']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="jenis_pupuk">Jenis Pupuk</label>
                        <input type="text" id="jenis_pupuk" name="jenis_pupuk" value="<?php echo htmlspecialchars($_POST['jenis_pupuk'] ?? $pupuk_data['jenis_pupuk']); ?>" required>
                    </div>

                    <!-- --- DITAMBAHKAN ---: Input untuk Kemasan -->
                    <div class="form-group">
                        <label for="kemasan">Kemasan (contoh: Karung 50kg)</label>
                        <input type="text" id="kemasan" name="kemasan" 
                               value="<?php echo htmlspecialchars($_POST['kemasan'] ?? $pupuk_data['kemasan']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="harga_per_unit">Harga per Unit (Rp)</label>
                        <input type="number" id="harga_per_unit" name="harga_per_unit" step="any" min="0" value="<?php echo htmlspecialchars($_POST['harga_per_unit'] ?? $pupuk_data['harga_per_unit']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="stok">Stok Saat Ini</label>
                        <input type="number" id="stok" name="stok" min="0" value="<?php echo htmlspecialchars($_POST['stok'] ?? $pupuk_data['stok']); ?>" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" rows="5" required><?php echo htmlspecialchars($_POST['deskripsi'] ?? $pupuk_data['deskripsi']); ?></textarea>
                    </div>
                </div>
                <div class="button-group">
                    <a href="manage_pupuk.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
if(isset($conn)) {
    close_db_connection($conn);
}
?>