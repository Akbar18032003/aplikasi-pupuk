<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$message = ''; 
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_pupuk = trim($_POST['nama_pupuk']);
    $jenis_pupuk = trim($_POST['jenis_pupuk']);
    $kemasan = trim($_POST['kemasan']);
    $deskripsi = trim($_POST['deskripsi']);
    $harga_per_unit = filter_var($_POST['harga_per_unit'], FILTER_VALIDATE_FLOAT); // Ini menghasilkan float/double
    $stok = filter_var($_POST['stok'], FILTER_VALIDATE_INT); // Ini menghasilkan integer

    if (empty($nama_pupuk) || empty($jenis_pupuk) || empty($kemasan) || empty($deskripsi) || $harga_per_unit === false || $stok === false) {
        $message = "Semua kolom harus diisi dengan format yang benar (harga dan stok harus angka).";
        $message_type = "error";
    } elseif ($harga_per_unit < 0 || $stok < 0) {
        $message = "Harga dan stok tidak boleh negatif.";
        $message_type = "error";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM pupuk WHERE nama_pupuk = ?");
        $check_stmt->bind_param("s", $nama_pupuk);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "Nama pupuk ini sudah terdaftar.";
            $message_type = "error";
        } else {
            // --- INI ADALAH BAGIAN YANG ANDA MINTA UNTUK DITAMBAHKAN ---

            // 1. Prepare statement sesuai permintaan Anda
            $stmt = $conn->prepare("INSERT INTO pupuk (nama_pupuk, jenis_pupuk, kemasan, deskripsi, harga_per_unit, stok) VALUES (?, ?, ?, ?, ?, ?)");
            
            // 2. bind_param dengan tipe yang sudah dikoreksi agar tidak error.
            //    Penjelasan detail ada di bawah blok kode.
            $stmt->bind_param("ssssdi", $nama_pupuk, $jenis_pupuk, $kemasan, $deskripsi, $harga_per_unit, $stok);

            if ($stmt->execute()) {
                $message = "Pupuk baru berhasil ditambahkan!";
                $message_type = "success";
                $_POST = array(); 
            } else {
                $message = "Gagal menambahkan pupuk: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pupuk Baru - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS tidak ada perubahan, sama persis seperti sebelumnya */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; transition: transform 0.3s ease; box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(0, 0, 0, 0.1); }
        .sidebar-header h3 { color: #ecf0f1; font-size: 1.4rem; margin-bottom: 5px; }
        .sidebar-header p { color: #bdc3c7; font-size: 0.9rem; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; font-size: 0.95rem; position: relative; }
        .nav-link:hover { background: rgba(52, 152, 219, 0.2); transform: translateX(5px); color: #3498db; }
        .nav-link.active { background: #3498db; color: #ffffff; font-weight: 600; box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3); }
        .nav-link.active:hover { background: #3498db; color: #ffffff; transform: translateX(0); }
        .nav-link i { margin-right: 15px; width: 20px; text-align: center; font-size: 1.1rem; }
        .nav-link.logout { margin-top: 30px; background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.3); }
        .nav-link.logout:hover { background: rgba(231, 76, 60, 0.2); color: #e74c3c; transform: translateX(5px); }
        .sidebar-toggle { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background: #2c3e50; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
        .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; transition: margin-left 0.3s ease; }
        .page-header { background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); }
        .page-header h1 { font-size: 2rem; color: #2c3e50; margin-bottom: 8px; font-weight: 600; }
        .page-header p { color: #7f8c8d; font-size: 1rem; }
        .form-container { background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); max-width: 800px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 10px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 600; font-size: 0.95rem; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea { width: 100%; padding: 15px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; transition: all 0.3s ease; background: #f8f9fa; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1); }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .button-group { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #e9ecef; padding-top: 25px; }
        .btn { padding: 15px 30px; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; transition: all 0.3s ease; }
        .btn i { margin-right: 8px; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; font-weight: 500; display: flex; align-items: center; animation: slideDown 0.3s ease; }
        .message i { margin-right: 12px; font-size: 1.2rem; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .sidebar-toggle { display: block; } .main-content { margin-left: 0; padding: 20px; padding-top: 80px; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <!-- Sidebar HTML tetap sama -->
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link active"><i class="fas fa-seedling"></i> Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link"><i class="fas fa-truck"></i> Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-plus-circle"></i> Tambah Pupuk Baru</h1>
            <p>Tambahkan produk pupuk organik baru ke dalam sistem inventaris.</p>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="add_pupuk.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nama_pupuk">Nama Pupuk</label>
                        <input type="text" id="nama_pupuk" name="nama_pupuk" value="<?php echo htmlspecialchars($_POST['nama_pupuk'] ?? ''); ?>" placeholder="Contoh: Pupuk Kompos Premium" required>
                    </div>

                    <div class="form-group">
                        <label for="jenis_pupuk">Jenis Pupuk</label>
                        <input type="text" id="jenis_pupuk" name="jenis_pupuk" value="<?php echo htmlspecialchars($_POST['jenis_pupuk'] ?? ''); ?>" placeholder="Contoh: Organik, NPK" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="kemasan">Kemasan</label>
                        <input type="text" id="kemasan" name="kemasan" value="<?php echo htmlspecialchars($_POST['kemasan'] ?? ''); ?>" placeholder="Contoh: Karung 50kg, Botol 1L" required>
                    </div>

                    <div class="form-group">
                        <label for="harga_per_unit">Harga per Unit (Rp)</label>
                        <input type="number" id="harga_per_unit" name="harga_per_unit" step="any" min="0" value="<?php echo htmlspecialchars($_POST['harga_per_unit'] ?? ''); ?>" placeholder="Contoh: 25000" required>
                    </div>

                    <div class="form-group">
                        <label for="stok">Stok Awal</label>
                        <input type="number" id="stok" name="stok" min="0" value="<?php echo htmlspecialchars($_POST['stok'] ?? ''); ?>" placeholder="Contoh: 100" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="deskripsi">Deskripsi Produk</label>
                        <textarea id="deskripsi" name="deskripsi" placeholder="Jelaskan keunggulan dan kandungan nutrisi pupuk..." required><?php echo htmlspecialchars($_POST['deskripsi'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="button-group">
                    <a href="manage_pupuk.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pupuk</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Javascript tidak berubah
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
    </script>
</body>
</html>

<?php
if (isset($conn)) {
    close_db_connection($conn);
}
?>