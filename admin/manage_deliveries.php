<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa apakah pengguna sudah login dan memiliki peran 'admin' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

// Ambil pesan notifikasi dari session
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);

// Query tidak perlu diubah, karena sudah mengambil semua data yang dibutuhkan
$stmt = $conn->prepare("
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesan,
        p.status_pesanan,
        u_pelanggan.nama_lengkap AS nama_pelanggan,
        peng.id AS pengiriman_id,
        peng.status_pengiriman,
        u_sopir.nama_lengkap AS nama_sopir
    FROM pesanan p
    JOIN users u_pelanggan ON p.id_pelanggan = u_pelanggan.id
    LEFT JOIN pengiriman peng ON p.id = peng.id_pesanan
    LEFT JOIN users u_sopir ON peng.id_sopir = u_sopir.id
    ORDER BY
        CASE p.status_pesanan
            WHEN 'pending' THEN 1
            WHEN 'diproses' THEN 2
            ELSE 3
        END,
        p.tanggal_pesan ASC
");
$stmt->execute();
$orders_to_manage = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengiriman - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS ini SAMA PERSIS dengan file pertama Anda untuk tampilan dashboard yang konsisten */
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
        .page-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header .header-text h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; display:flex; align-items:center; }
        .page-header .header-text h1 i { margin-right:15px; color:#f39c12; }
        .page-header .header-text p { color: #7f8c8d; }
        .print-btn { background-color: #3498db; color: white; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; transition: background-color 0.3s ease; }
        .print-btn:hover { background-color: #2980b9; }
        .table-container { background: white; border-radius: 15px; overflow-x: auto; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 18px 20px; text-align: left; border-bottom: 1px solid #ecf0f1; vertical-align: middle; }
        .data-table th { background-color: #f8f9fa; color: #34495e; text-transform: uppercase; font-size: 0.9em; }
        .data-table tbody tr:hover { background-color: #f1f3f5; }
        .status-badge { display: inline-block; padding: .4em .8em; font-size: .8em; font-weight: 700; color: #fff; border-radius: 50px; text-transform: capitalize; }
        .status-badge.pending, .status-badge.menunggu_penugasan { background-color: #ffc107; color: #333; }
        .status-badge.diproses, .status-badge.dalam_perjalanan { background-color: #007bff; }
        .status-badge.sudah_sampai { background-color: #28a745; }
        .status-badge.selesai { background-color:#6f42c1; }
        .status-badge.dibatalkan, .status-badge.bermasalah { background-color: #dc3545; }
        .table-actions { display: flex; gap: 10px; }
        .action-btn { padding: 8px 12px; border-radius: 8px; text-decoration: none; color: white; display: inline-flex; align-items: center; border: none; font-size:0.9em; transition: transform 0.2s ease; gap: 5px;}
        .action-btn:hover { transform: translateY(-2px); }
        .action-btn.assign { background-color: #f39c12; }
        .action-btn.edit { background-color: #e67e22; }
        /* --- DITAMBAHKAN ---: Style untuk tombol Cetak Surat Jalan --- */
        .action-btn.print-sj { background-color: #5bc0de; } 
        .action-btn.detail { background-color: #27ae60; }
        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .message.info { background-color: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="sidebar">
        <!-- Sidebar HTML Tetap Sama -->
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link active"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link" style="margin-top:20px; background-color:rgba(231, 76, 60, 0.1);"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div class="header-text">
                <h1><i class="fas fa-truck-loading"></i> Manajemen Pengiriman</h1>
                <p>Kelola semua pesanan, tugaskan sopir, dan pantau status pengiriman hingga selesai.</p>
            </div>
            <div>
                <form action="print_deliveries_list.php" method="GET" target="_blank" style="display: flex; align-items: center; gap: 10px;">
                    <label for="tanggal_cetak" style="font-weight: 600; color: #34495e;">Cetak per Tanggal:</label>
                    <input type="date" id="tanggal_cetak" name="tanggal" 
                        value="<?php echo date('Y-m-d'); ?>" 
                        required
                        style="padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
                    <button type="submit" class="print-btn">
                        <i class="fas fa-print"></i> Cetak
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Info Pelanggan</th>
                        <th>Info Pengiriman</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders_to_manage)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px;">Tidak ada pesanan yang perlu dikelola.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders_to_manage as $order): ?>
                            <tr>
                                <!-- Kolom data lainnya tetap sama -->
                                <td><strong><?php echo htmlspecialchars($order['pesanan_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['nama_pelanggan']); ?><br><small>Tgl Pesan: <?php echo date('d M Y', strtotime($order['tanggal_pesan'])); ?></small></td>
                                <td><strong>Sopir:</strong> <?php echo htmlspecialchars($order['nama_sopir'] ?? 'Belum Ada'); ?></td>
                                <td>
                                    <?php
                                        $status_display = $order['status_pengiriman'] ?? $order['status_pesanan'];
                                        $status_class = strtolower(str_replace(' ', '_', $status_display));
                                    ?>
                                    <span class="status-badge <?php echo htmlspecialchars($status_class); ?>"><?php echo htmlspecialchars($status_display); ?></span>
                                </td>
                                
                                <!-- --- DIUBAH ---: Blok Aksi diperbarui --- -->
                                <td class="table-actions">
                                    <?php if (empty($order['pengiriman_id'])): ?>
                                        <a href="assign_delivery.php?pesanan_id=<?php echo $order['pesanan_id']; ?>" class="action-btn assign" title="Tugaskan Sopir">
                                            <i class="fas fa-user-plus"></i> Tugaskan
                                        </a>
                                    <?php else: ?>
                                        <a href="edit_delivery.php?pengiriman_id=<?php echo $order['pengiriman_id']; ?>" class="action-btn edit" title="Edit Pengiriman">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <!-- Ini adalah tombol baru yang ditambahkan -->
                                        <a href="print_surat_jalan.php?pengiriman_id=<?php echo $order['pengiriman_id']; ?>" target="_blank" class="action-btn print-sj" title="Cetak Surat Jalan">
                                            <i class="fas fa-file-alt"></i> SJ
                                        </a>
                                    <?php endif; ?>
                                    <a href="detail_pesanan.php?id=<?php echo $order['pesanan_id']; ?>" class="action-btn detail" title="Lihat Detail Pesanan">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
close_db_connection($conn);
?>