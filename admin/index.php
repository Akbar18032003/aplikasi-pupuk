<?php
session_start();
require_once '../config/database.php';

// --- Keamanan: Cek login & role admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}
// --- Akhir Keamanan ---

// --- Ambil Data Statistik ---
// Total Pesanan
$total_pesanan = $conn->query("SELECT COUNT(*) AS total FROM pesanan")->fetch_assoc()['total'] ?? 0;
// Total Pengguna berdasarkan Role
$users_by_role = [];
$result_users = $conn->query("SELECT role, COUNT(*) AS count FROM users WHERE role IN ('sopir', 'pelanggan') GROUP BY role");
if ($result_users) {
    while($row = $result_users->fetch_assoc()) {
        $users_by_role[$row['role']] = $row['count'];
    }
}
// Total Stok Pupuk
$total_stok = $conn->query("SELECT SUM(stok) AS total_stok FROM pupuk")->fetch_assoc()['total_stok'] ?? 0;
// Pesanan Terbaru (5 terakhir)
$recent_orders = $conn->query("SELECT p.id, u.nama_lengkap, p.status_pesanan FROM pesanan p JOIN users u ON p.id_pelanggan = u.id ORDER BY p.tanggal_pesan DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS 100% Konsisten */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; transition: transform 0.3s ease; box-shadow: 4px 0 20px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 1.4rem; }
        .sidebar-header p { color: #bdc3c7; font-size: 0.9rem; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link.active { background: #3498db; font-weight: 600; box-shadow: 0 4px 15px rgba(52,152,219,0.3); }
        .nav-link.active:hover { transform: translateX(0); }
        .nav-link:hover:not(.active) { background: rgba(52,152,219,0.2); transform: translateX(5px); color: #3498db; }
        .nav-link i { margin-right: 15px; width: 20px; text-align: center; }
        .main-content { margin-left: 280px; padding: 40px; transition: margin-left 0.3s ease; }
        
        .page-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; }
        .page-header h1 { font-size: 2.2rem; color: #2c3e50; font-weight: 700; }
        .page-header p { color: #7f8c8d; font-size: 1.1rem; }
        
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 20px; }
        .stat-card .icon { font-size: 2.5em; width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; flex-shrink: 0; }
        .icon-orders { background: #3498db; }
        .icon-drivers { background: #e67e22; }
        .icon-customers { background: #2ecc71; }
        .icon-stock { background: #9b59b6; }
        .stat-card .info .label { font-size: 1rem; color: #7f8c8d; margin-bottom: 5px; }
        .stat-card .info .value { font-size: 1.8rem; font-weight: 700; color: #2c3e50; }
        
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .card h3 { font-size: 1.2rem; color: #34495e; margin-bottom: 15px; border-bottom: 1px solid #ecf0f1; padding-bottom: 10px; }
        
        .quick-links a { display: block; padding: 15px; margin-bottom: 10px; border-radius: 10px; text-decoration: none; color: #2c3e50; background: #f8f9fa; font-weight: 600; transition: all 0.2s ease; }
        .quick-links a:hover { background: #e9ecef; transform: translateX(5px); }
        .quick-links i { margin-right: 12px; color: #3498db; }

        .recent-orders-list li { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #ecf0f1; }
        .recent-orders-list li:last-child { border-bottom: none; }
        .status-badge { display: inline-block; padding: .4em .8em; font-size: .8em; font-weight: 700; color: #fff; border-radius: 50px; text-transform: capitalize; }
        .status-badge.pending { background-color: #ffc107; color: #333; }
        .status-badge.diproses { background-color: #17a2b8; }
        .status-badge.selesai { background-color: #28a745; }
        .status-badge.dibatalkan { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <!-- Beri class 'active' pada Dashboard -->
            <div class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link" style="margin-top:20px; background-color:rgba(231, 76, 60, 0.1);"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>Selamat Datang di Dashboard</h1>
            <p>Ini adalah ringkasan aktivitas sistem Anda.</p>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="icon icon-orders"><i class="fas fa-box-open"></i></div>
                <div class="info">
                    <div class="label">Total Pesanan</div>
                    <div class="value"><?php echo number_format($total_pesanan); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon icon-drivers"><i class="fas fa-truck-moving"></i></div>
                <div class="info">
                    <div class="label">Jumlah Sopir</div>
                    <div class="value"><?php echo number_format($users_by_role['sopir'] ?? 0); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon icon-customers"><i class="fas fa-users"></i></div>
                <div class="info">
                    <div class="label">Jumlah Pelanggan</div>
                    <div class="value"><?php echo number_format($users_by_role['pelanggan'] ?? 0); ?></div>
                </div>
            </div>
             <div class="stat-card">
                <div class="icon icon-stock"><i class="fas fa-warehouse"></i></div>
                <div class="info">
                    <div class="label">Stok Pupuk</div>
                    <div class="value"><?php echo number_format($total_stok); ?> Unit</div>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="card">
                <h3>Pesanan Terbaru</h3>
                <?php if (empty($recent_orders)): ?>
                    <p>Belum ada pesanan.</p>
                <?php else: ?>
                    <ul class="recent-orders-list" style="list-style:none;">
                        <?php foreach($recent_orders as $order): ?>
                        <li>
                            <span>
                                <strong><?php echo $order['id']; ?></strong> - 
                                <?php echo htmlspecialchars($order['nama_lengkap']); ?>
                            </span>
                            <span class="status-badge <?php echo strtolower($order['status_pesanan']); ?>"><?php echo htmlspecialchars($order['status_pesanan']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card">
                <h3>Akses Cepat</h3>
                <div class="quick-links">
                    <a href="add_user.php"><i class="fas fa-user-plus"></i> Tambah Pengguna Baru</a>
                    <a href="add_pupuk.php"><i class="fas fa-plus-circle"></i> Tambah Pupuk Baru</a>
                    <a href="manage_deliveries.php"><i class="fas fa-truck"></i> Kelola Pengiriman</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
if(isset($conn)) {
    close_db_connection($conn);
}
?>