<?php
session_start();
require_once '../config/database.php';

// --- Keamanan: Cek login & role admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Ambil pesan notifikasi dari session
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);

// --- DATA UNTUK LAPORAN ---
// 1. Total Pendapatan dari pesanan yang statusnya 'selesai'
$stmt_revenue = $conn->prepare("SELECT SUM(total_harga) AS total_revenue FROM pesanan WHERE status_pesanan = 'selesai'");
$stmt_revenue->execute();
$total_revenue = $stmt_revenue->get_result()->fetch_assoc()['total_revenue'] ?? 0;
$stmt_revenue->close();

// 2. Total Semua Pesanan
$stmt_orders = $conn->prepare("SELECT COUNT(*) AS total_orders FROM pesanan");
$stmt_orders->execute();
$total_pesanan = $stmt_orders->get_result()->fetch_assoc()['total_orders'] ?? 0;
$stmt_orders->close();

// 3. Total Stok Pupuk
$stmt_stock = $conn->prepare("SELECT SUM(stok) AS total_stock FROM pupuk");
$stmt_stock->execute();
$total_stok = $stmt_stock->get_result()->fetch_assoc()['total_stock'] ?? 0;
$stmt_stock->close();

// 4. Rincian Stok Pupuk
$rincian_pupuk = $conn->query("SELECT nama_pupuk, jenis_pupuk, stok, harga_per_unit FROM pupuk ORDER BY stok DESC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan & Statistik - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS 100% Konsisten */
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
        .page-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; display:flex; align-items:center; }
        .page-header h1 i { margin-right:15px; color:#9b59b6; }
        .page-header .action-buttons a { text-decoration: none; color: white; background: #27ae60; padding: 12px 20px; border-radius: 10px; font-weight: 600; display:inline-flex; align-items: center; }
        .page-header .action-buttons i { margin-right: 8px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .summary-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 20px; }
        .summary-card .icon { font-size: 2.5em; width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; }
        .icon-revenue { background: #2ecc71; }
        .icon-orders { background: #3498db; }
        .icon-stock { background: #e67e22; }
        .summary-card .info .label { font-size: 1rem; color: #7f8c8d; margin-bottom: 5px; }
        .summary-card .info .value { font-size: 1.8rem; font-weight: 700; color: #2c3e50; }
        .table-container { background: white; border-radius: 15px; overflow-x: auto; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .table-header { padding: 20px; border-bottom: 1px solid #ecf0f1; font-size: 1.2rem; font-weight: 600; color: #34495e; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 18px 20px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .data-table th { background-color: #f8f9fa; text-transform: uppercase; font-size: 0.9em; }
        .data-table tbody tr:hover { background-color: #f1f3f5; }
        .text-right { text-align: right !important; }

        @media print { /* CSS untuk cetak */
            body { background: white; color: black; }
            .sidebar, .page-header { display: none; }
            .main-content { margin-left: 0; padding: 10px; }
            .summary-card, .table-container { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link active"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link" style="margin-top:20px; background-color:rgba(231, 76, 60, 0.1);"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-chart-pie"></i> Laporan & Statistik</h1>
                <p>Ringkasan data penting dari aktivitas bisnis.</p>
            </div>
            <div class="action-buttons">
                <a href="print_report.php" target="_blank">   
                    <i class="fas fa-print"></i> Cetak Laporan
                </a>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="icon icon-revenue"><i class="fas fa-dollar-sign"></i></div>
                <div class="info">
                    <div class="label">Total Pendapatan</div>
                    <div class="value">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon icon-orders"><i class="fas fa-box-open"></i></div>
                <div class="info">
                    <div class="label">Total Pesanan</div>
                    <div class="value"><?php echo number_format($total_pesanan); ?></div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon icon-stock"><i class="fas fa-warehouse"></i></div>
                <div class="info">
                    <div class="label">Total Stok Tersedia</div>
                    <div class="value"><?php echo number_format($total_stok); ?> Unit</div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">Laporan Stok Pupuk</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Pupuk</th>
                        <th>Jenis</th>
                        <th class="text-right">Stok</th>
                        <th class="text-right">Harga Satuan</th>
                        <th class="text-right">Nilai Stok</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $total_nilai_stok = 0;
                        foreach ($rincian_pupuk as $pupuk):
                            $nilai_item = $pupuk['stok'] * $pupuk['harga_per_unit'];
                            $total_nilai_stok += $nilai_item;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pupuk['nama_pupuk']); ?></td>
                            <td><?php echo htmlspecialchars($pupuk['jenis_pupuk']); ?></td>
                            <td class="text-right"><?php echo number_format($pupuk['stok']); ?></td>
                            <td class="text-right">Rp <?php echo number_format($pupuk['harga_per_unit']); ?></td>
                            <td class="text-right"><strong>Rp <?php echo number_format($nilai_item); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                 <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total Nilai Semua Stok</th>
                        <th class="text-right">Rp <?php echo number_format($total_nilai_stok); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>
<?php
if(isset($conn)) {
    close_db_connection($conn);
}
?>