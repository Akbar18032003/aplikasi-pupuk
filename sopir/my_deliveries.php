<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sopir') {
    header("Location: ../public/login.php");
    exit;
}

$sopir_id = $_SESSION['user_id'];
$deliveries = [];
$message = '';
$message_type = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$stmt = $conn->prepare("
    SELECT
        peng.id AS pengiriman_id, peng.id_pesanan, peng.tanggal_kirim, peng.tanggal_selesai,
        peng.status_pengiriman, peng.catatan_sopir, p.alamat_pengiriman, p.total_harga,
        u_pelanggan.nama_lengkap AS nama_pelanggan, u_pelanggan.telepon AS telepon_pelanggan
    FROM pengiriman peng
    JOIN pesanan p ON peng.id_pesanan = p.id
    JOIN users u_pelanggan ON p.id_pelanggan = u_pelanggan.id
    WHERE peng.id_sopir = ?
    ORDER BY
        CASE
            WHEN peng.status_pengiriman = 'dalam perjalanan' THEN 1
            WHEN peng.status_pengiriman = 'menunggu penugasan' THEN 2
            WHEN peng.status_pengiriman = 'sudah sampai' THEN 3
            WHEN peng.status_pengiriman = 'bermasalah' THEN 4
            WHEN peng.status_pengiriman = 'selesai' THEN 5
            WHEN peng.status_pengiriman = 'dibatalkan' THEN 6
            ELSE 7
        END,
        peng.tanggal_kirim DESC, p.id DESC
");
$stmt->bind_param("i", $sopir_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }
}
$stmt->close();

function displayDeliveryStatus($status) {
    if (empty($status)) return '-';
    // Menjadikan format lebih rapi, contoh: 'menunggu_penugasan' -> 'Menunggu Penugasan'
    $statusFormatted = ucwords(str_replace('_', ' ', $status));
    $statusClass = strtolower(str_replace(' ', '_', $status));
    return '<span class="status-badge ' . htmlspecialchars($statusClass) . '">' . htmlspecialchars($statusFormatted) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pengiriman Saya - Sopir</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS IDENTIK DENGAN index.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; color: #333;
            /* HAPUS 'overflow-x: hidden;' karena ini hanya menyembunyikan masalah */
        }
        .dashboard-wrapper { display: flex; min-height: 100vh; }

        /* --- Sidebar Styles --- */
        .sidebar {
            width: 280px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2); padding: 0; position: fixed;
            height: 100vh; overflow-y: auto; transition: transform 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000;
        }
        .sidebar-header {
            padding: 30px 25px; background: linear-gradient(135deg, #28a745, #20c997);
            color: white; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-header h3 { font-size: 1.4rem; font-weight: 600; margin-bottom: 8px; }
        .sidebar-header .user-info { font-size: 0.9rem; opacity: 0.9; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 18px 25px; color: #555;
            text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent;
            font-weight: 500; gap: 15px;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.1), transparent);
            color: #28a745; border-left-color: #28a745; transform: translateX(5px);
        }
        .sidebar-menu a i { width: 20px; text-align: center; font-size: 1.1rem; }
        .sidebar-menu .menu-section { padding: 15px 25px 8px; font-size: 0.8rem; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: 1px; }
        .logout-btn-wrapper { margin-top: 20px; }
        .logout-btn { border-top: 1px solid rgba(0, 0, 0, 0.1); padding-top: 10px; }
        .logout-btn a { color: #dc3545 !important; }
        .logout-btn a:hover { background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), transparent); border-left-color: #dc3545; }

        /* --- Main Content & Style Khusus Halaman Ini --- */
        .main-content { 
            flex: 1; margin-left: 280px; padding: 30px; 
            transition: margin-left 0.3s ease-in-out;
            /* PERBAIKAN UTAMA: Tambahkan ini agar flex item bisa menyusut */
            min-width: 0;
        }
        .container { background-color: rgba(255,255,255,0.95); padding: 30px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); }
        h2 { text-align: center; margin-bottom: 25px; color: #333; font-size: 1.8rem; }
        .top-buttons { margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-size: 14px; transition: all 0.3s; border: none; cursor: pointer; font-weight: 500; }
        .btn-refresh { background-color: #17a2b8; color: white; }
        .btn-refresh:hover { background-color: #138496; transform: translateY(-2px); }

        /* PERBAIKAN INI memastikan hanya tabel yang scrollable secara horizontal */
        .table-responsive-wrapper { overflow-x: auto; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; white-space: nowrap; }
        table th, table td { padding: 14px 10px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: middle; }
        table th { background-color: #f8f9fa; font-weight: 600; color: #495057; }
        tbody tr:hover { background-color: #f1f1f1; }
        
        /* Badge Status */
        .status-badge { font-weight: bold; padding: 5px 10px; border-radius: 15px; color: white; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge.menunggu_penugasan { background-color: #6c757d; }
        .status-badge.dalam_perjalanan { background-color: #007bff; }
        .status-badge.sudah_sampai { background-color: #28a745; }
        .status-badge.bermasalah { background-color: #dc3545; }
        .status-badge.dibatalkan { background-color: #343a40; }
        .status-badge.selesai { background-color: #17a2b8; }
        
        .actions { display: flex; gap: 8px; }
        .btn-action { padding: 6px 12px; border-radius: 4px; font-size: 13px; color: white; text-decoration: none; text-align: center; display: block; }
        .btn-update { background-color: #ffc107; color: #212529 !important; } .btn-update:hover { background-color: #e0a800; }
        .btn-detail { background-color: #007bff; } .btn-detail:hover { background-color: #0056b3; }
        
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .text-center { text-align: center; } .text-muted { color: #6c757d; font-size: 0.9em; }
        .no-deliveries { text-align: center; padding: 40px 20px; background-color: #f8f9fa; border-radius: 8px; }
        .no-deliveries p { color: #555; font-size: 1.1rem; }

        /* --- Mobile Responsiveness --- */
        .mobile-toggle { display: none; position: fixed; top: 15px; left: 15px; z-index: 1001; background: #28a745; color: white; border: none; padding: 10px 12px; border-radius: 8px; cursor: pointer; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        .overlay.active { display: block; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 15px; }
            .mobile-toggle { display: block; }
            .container { padding: 20px; margin-top: 60px; }
            h2 { font-size: 1.5rem; }
            .table-responsive-wrapper {
                border: 1px solid #ddd;
                border-radius: 8px;
            }
            table { font-size: 13px; }
            table th, table td { padding: 10px 8px; }
            .btn, .btn-action { padding: 8px 12px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-wrapper">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-truck"></i> Dashboard Sopir</h3>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </div>
            </div>
            <div class="sidebar-menu">
                <div class="menu-section">Menu Utama</div>
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard Utama</a>
                <a href="my_deliveries.php" class="active"><i class="fas fa-shipping-fast"></i> Daftar Pengiriman Saya</a>
                <div class="menu-section logout-btn-wrapper">Lainnya</div>
                <div class="logout-btn">
                    <a href="../public/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="container">
                <h2>Daftar Pengiriman Saya</h2>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <div class="top-buttons">
                    <a href="my_deliveries.php" class="btn btn-refresh"><i class="fas fa-sync-alt"></i> Refresh Data</a>
                </div>

                <?php if (empty($deliveries)): ?>
                    <div class="no-deliveries">
                        <p><i class="fas fa-info-circle"></i> Anda belum memiliki tugas pengiriman saat ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th><th>Pelanggan</th><th>Telepon</th>
                                    <th>Alamat Pengiriman</th><th>Tanggal Kirim</th><th>Status</th><th>Catatan</th><th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deliveries as $delivery): ?>
                                    <tr>
                                        
                                        <td class="text-center"><?php echo htmlspecialchars($delivery['id_pesanan']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($delivery['nama_pelanggan']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($delivery['telepon_pelanggan']); ?></td>
                                        <td style="white-space: normal; min-width: 250px;"><?php echo nl2br(htmlspecialchars($delivery['alamat_pengiriman'])); ?></td>
                                        <td class="text-center">
                                            <?php echo !empty($delivery['tanggal_kirim']) ? date('d-m-Y', strtotime($delivery['tanggal_kirim'])) : '-'; ?>
                                        </td>
                                        <td class="text-center"><?php echo displayDeliveryStatus($delivery['status_pengiriman']); ?></td>
                                        <td style="white-space: normal; min-width: 200px;"><?php echo !empty($delivery['catatan_sopir']) ? nl2br(htmlspecialchars($delivery['catatan_sopir'])) : '-'; ?></td>
                                        <td class="actions">
                                            <?php if (!in_array($delivery['status_pengiriman'], ['selesai', 'dibatalkan'])): ?>
                                                <a href="update_status_pengiriman.php?pengiriman_id=<?php echo $delivery['pengiriman_id']; ?>" class="btn-action btn-update">Update</a>
                                            <?php endif; ?>
                                            <a href="detail_pesanan.php?id=<?php echo $delivery['id_pesanan']; ?>" class="btn-action btn-detail">Detail</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.overlay');
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
            }
        }
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !toggle.contains(event.target) && sidebar.classList.contains('mobile-open')) {
                toggleSidebar();
            }
        });
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.overlay');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            }
        });
    </script>
</body>
</html>
<?php 
if (isset($conn) && $conn instanceof mysqli) { 
    $conn->close(); 
} 
?>