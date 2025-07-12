<?php
session_start();
require_once '../config/database.php'; // Sertakan file koneksi database

// --- KEAMANAN: Periksa apakah pengguna sudah login dan memiliki peran 'admin' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

$pupuks = []; // Array untuk menyimpan data pupuk

// Ambil pesan dari session jika ada (setelah tambah/edit/hapus)
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Query untuk mengambil semua data pupuk
$stmt = $conn->prepare("SELECT id, nama_pupuk, jenis_pupuk, deskripsi, harga_per_unit, stok, created_at FROM pupuk ORDER BY nama_pupuk ASC");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pupuks[] = $row;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pupuk - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- CSS DI BAWAH INI DIAMBIL LANGSUNG DARI index.php UNTUK KONSISTENSI -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        /* --- STYLES UNTUK SIDEBAR (SAMA DENGAN index.php) --- */
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .sidebar-header h3 {
            color: #ecf0f1; font-size: 1.4rem; margin-bottom: 5px;
        }

        .sidebar-header p {
            color: #bdc3c7; font-size: 0.9rem;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            margin: 8px 20px;
        }

        .nav-link {
            display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; font-size: 0.95rem; position: relative;
        }

        .nav-link:hover {
            background: rgba(52, 152, 219, 0.2); transform: translateX(5px); color: #3498db;
        }

        /* Style untuk link yang aktif */
        .nav-link.active {
            background: #3498db;
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .nav-link.active:hover {
            background: #3498db;
            color: #ffffff;
            transform: translateX(0); /* Jangan bergerak saat hover jika aktif */
        }
        
        .nav-link i {
            margin-right: 15px; width: 20px; text-align: center; font-size: 1.1rem;
        }

        .nav-link.logout {
            margin-top: 30px; background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .nav-link.logout:hover {
            background: rgba(231, 76, 60, 0.2); color: #e74c3c; transform: translateX(5px);
        }

        .sidebar-toggle {
            display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background: #2c3e50; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* --- STYLES UNTUK KONTEN UTAMA (SAMA DENGAN index.php) --- */
        .main-content {
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }
        
        /* --- STYLE KHUSUS HALAMAN INI (Tabel, Header, dll) --- */
        
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 {
            font-size: 2rem; color: #2c3e50; margin-bottom: 8px; font-weight: 600; display: flex; align-items: center;
        }
        
        .page-header h1 i { margin-right: 15px; color: #27ae60; }
        .page-header p { color: #7f8c8d; font-size: 1rem; }

        .action-buttons {
            display: flex; gap: 15px; margin-bottom: 25px;
        }

        .btn {
            padding: 12px 25px; border-radius: 12px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; cursor: pointer; border: none;
        }
        
        .btn i { margin-right: 8px; }
        .btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3); }
        .btn-secondary { background: linear-gradient(135deg, #bdc3c7, #95a5a6); color: white; }
        .btn-secondary:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(189, 195, 199, 0.4); }

        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; font-weight: 500; display: flex; align-items: center; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
        
        .table-container {
            background: white; border-radius: 15px; overflow-x: auto; box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .data-table {
            width: 100%; border-collapse: collapse;
        }
        
        .data-table th, .data-table td { padding: 18px 20px; text-align: left; border-bottom: 1px solid #ecf0f1; vertical-align: middle;}
        .data-table th { background-color: #f8f9fa; color: #34495e; text-transform: uppercase; font-size: 0.9em; letter-spacing: 0.5px; }
        .data-table tbody tr:hover { background-color: #f1f3f5; }
        .table-actions { display: flex; gap: 10px; }
        .action-btn { padding: 8px 12px; border-radius: 8px; text-decoration: none; font-size: 0.9em; color: white; display: inline-flex; align-items: center; border: none; }
        .action-btn.edit { background: #f39c12; }
        .action-btn.delete { background: #e74c3c; }
        .price { font-weight: 600; color: #27ae60; }
        
        .stock-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; color: white; }
        .stock-low { background-color: #e74c3c; } /* Merah */
        .stock-medium { background-color: #f39c12; } /* Oranye */
        .stock-high { background-color: #27ae60; } /* Hijau */
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-toggle { display: block; }
            .main-content { margin-left: 0; padding: 20px; padding-top: 80px; }
        }

    </style>
</head>
<body>
    <!-- Tombol Toggle Sidebar untuk Mobile (dari index.php) -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar (Struktur HTML diganti agar sama dengan index.php) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Manajemen Pengguna
                </a>
            </div>
            <div class="nav-item">
                <!-- Tambahkan class 'active' di sini -->
                <a href="manage_pupuk.php" class="nav-link active">
                    <i class="fas fa-seedling"></i>
                    Manajemen Pupuk
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_deliveries.php" class="nav-link">
                    <i class="fas fa-truck"></i>
                    Manajemen Pengiriman
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Laporan 
                </a>
            </div>
            <div class="nav-item">
                <a href="../public/logout.php" class="nav-link logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Konten Utama (Struktur ini tidak berubah) -->
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-seedling"></i> Manajemen Pupuk</h1>
            <p>Kelola daftar, stok, dan detail produk pupuk</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="add_pupuk.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Pupuk
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Pupuk</th>
                        <th>Harga/Unit</th>
                        <th>Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pupuks)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Belum ada data pupuk.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pupuks as $pupuk): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pupuk['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($pupuk['nama_pupuk']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($pupuk['jenis_pupuk']); ?></small>
                                </td>
                                <td class="price">Rp <?php echo number_format($pupuk['harga_per_unit'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php
                                    $stok = (int)$pupuk['stok'];
                                    $class = $stok < 10 ? 'stock-low' : ($stok < 50 ? 'stock-medium' : 'stock-high');
                                    ?>
                                    <span class="stock-badge <?php echo $class; ?>">
                                        <?php echo $stok; ?> Unit
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <a href="edit_pupuk.php?id=<?php echo $pupuk['id']; ?>" class="action-btn edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_pupuk.php?id=<?php echo $pupuk['id']; ?>" class="action-btn delete" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus pupuk ini?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- JavaScript (Sama dengan index.php) -->
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
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
close_db_connection($conn); // Tutup koneksi database
?>