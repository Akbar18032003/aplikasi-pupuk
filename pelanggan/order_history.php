<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa apakah pengguna sudah login dan memiliki peran 'pelanggan' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

$user_id = $_SESSION['user_id'];
$orders = []; // Array untuk menyimpan data pesanan
$error_message = null;

// Query untuk mengambil HANYA pesanan yang sudah selesai atau dibatalkan (Riwayat)
try {
    $stmt = $conn->prepare("
        SELECT
            p.id AS pesanan_id,
            p.tanggal_pesan,
            p.total_harga,
            p.status_pesanan,
            peng.tanggal_selesai
        FROM pesanan p
        LEFT JOIN pengiriman peng ON p.id = peng.id_pesanan
        WHERE p.id_pelanggan = ? AND p.status_pesanan IN ('selesai', 'dibatalkan')
        ORDER BY p.tanggal_pesan DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch(Exception $e) {
    // Handle error jika query gagal
    $error_message = "Terjadi kesalahan saat mengambil data riwayat pesanan.";
    // Untuk debugging, bisa uncomment baris berikut:
    // error_log("Error di order_history.php: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Dashboard Pelanggan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Menggunakan Style Mobile-Friendly dari template utama */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            overflow-x: hidden;
        }
        body.body-no-scroll { overflow: hidden; }

        /* Sidebar Styles */
        .sidebar {
            width: 280px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(12px);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); height: 100vh; position: fixed;
            left: 0; top: 0; transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94); z-index: 1000;
        }
        .sidebar-header {
            padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, #ffc107, #ff8f00); color: white;
        }
        .sidebar-header h3 { font-size: 1.4rem; margin-bottom: 5px; font-weight: 600; }
        .sidebar-header p { font-size: 0.9rem; opacity: 0.9; word-wrap: break-word; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 15px 25px; color: #333;
            text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; margin: 5px 0;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(to right, #ffc107, #ffb300); color: white;
            border-left-color: #ff8f00; transform: translateX(5px);
        }
        .sidebar-menu a i { width: 20px; margin-right: 15px; font-size: 1.1rem; }
        .sidebar-menu a span { font-weight: 500; }
        .logout-btn { position: absolute; bottom: 20px; left: 20px; right: 20px; }
        .logout-btn a {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white !important;
            border-radius: 8px; justify-content: center; border-left: none !important; transform: none !important; padding: 12px;
        }
        .logout-btn a:hover {
            background: linear-gradient(135deg, #ff5252, #d32f2f);
            transform: translateY(-2px) !important; box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px; flex-grow: 1; padding: 40px; min-height: 100vh;
            width: calc(100% - 280px); transition: margin-left 0.3s ease;
        }
        .content-card {
            background: rgba(255, 255, 255, 0.97); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 30px 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); }}

        .content-card h2 {
            font-size: 2.2rem; margin-bottom: 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            text-align: left;
        }
        
        /* Tabel Responsif */
        .table-container {
            overflow-x: auto;
        }
        .history-table {
            width: 100%; border-collapse: collapse; min-width: 650px;
        }
        .history-table thead {
            background-color: #f1f3f5;
        }
        .history-table th, .history-table td {
            padding: 15px; text-align: left; border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .history-table th { font-weight: 600; color: #495057; }
        .history-table tbody tr:hover { background-color: #f8f9fa; }
        .status-badge {
            font-weight: 600; padding: 6px 14px; border-radius: 20px;
            color: white; font-size: 0.8em; text-transform: uppercase; display: inline-block;
        }
        .status-badge.selesai { background-color: #28a745; }
        .status-badge.dibatalkan { background-color: #dc3545; }

        .btn-detail {
            background: #6c757d; color: white; padding: 8px 16px;
            border-radius: 6px; text-decoration: none; font-size: 0.9em;
            transition: background-color 0.2s ease, transform 0.2s ease;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-detail:hover { background-color: #5a6268; transform: translateY(-2px); }

        .no-history {
            text-align: center; color: #6c757d; padding: 40px;
            border: 2px dashed #e9ecef; border-radius: 10px; margin-top: 20px;
        }

        /* Mobile Styles */
        .mobile-toggle {
            display: none; position: fixed; top: 15px; left: 15px; background: #ffc107;
            color: white; border: none; width: 45px; height: 45px; border-radius: 50%;
            cursor: pointer; z-index: 1001; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        /* [PERBAIKAN] Overlay disembunyikan secara default, hanya aktif saat dibutuhkan */
        .overlay {
            display: none; /* Sembunyikan secara default */
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: 999; opacity: 0;
            transition: opacity 0.3s ease;
        }
        .overlay.active {
            display: block; /* Hanya tampil jika class 'active' ada */
            opacity: 1;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; width: 100%; }
            .content-card { padding: 25px 20px; }
            .content-card h2 { font-size: 1.8rem; }
            
            /* [PERBAIKAN] Hanya .mobile-toggle yang butuh 'display: block' di sini. */
            /* .overlay tidak perlu, karena sudah diatur oleh class .active */
            .mobile-toggle { 
                display: block; 
            }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>

    <button class="mobile-toggle" id="mobile-toggle-btn">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Dashboard Pelanggan</h3>
            <p>Selamat Datang, <br><?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="order_pupuk.php"><i class="fas fa-shopping-cart"></i><span>Pesan Pupuk</span></a>
            <a href="track_delivery.php"><i class="fas fa-truck"></i><span>Lacak Pesanan Aktif</span></a>
            <a href="order_history.php" class="active"><i class="fas fa-history"></i><span>Riwayat Pesanan</span></a>
        </div>
        <div class="logout-btn">
            <a href="../public/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <main class="main-content" id="main-content">
        <div class="content-card">
            <h2>Riwayat Pesanan Anda</h2>
            <p style="margin-top:-20px; margin-bottom: 25px; color:#666;">Daftar semua pesanan yang telah selesai atau dibatalkan.</p>
            
            <?php if (isset($error_message)): ?>
                <div class="no-history" style="border-color: #dc3545; color: #dc3545;">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php elseif (empty($orders)): ?>
                <div class="no-history">
                    <p>Anda belum memiliki riwayat pesanan.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID Pesanan</th>
                                <th>Tanggal Pesan</th>
                                <th>Tanggal Selesai</th>
                                <th style="text-align: right;">Total</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo htmlspecialchars($order['pesanan_id']); ?></strong></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($order['tanggal_pesan'])); ?></td>
                                    <td>
                                        <?php 
                                            if ($order['status_pesanan'] === 'selesai' && !empty($order['tanggal_selesai'])) {
                                                echo date('d M Y, H:i', strtotime($order['tanggal_selesai']));
                                            } else {
                                                echo '—'; // Menggunakan em-dash untuk visual yang lebih baik
                                            }
                                        ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 500;">Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></td>
                                    <td style="text-align: center;">
                                        <span class="status-badge <?php echo htmlspecialchars(strtolower($order['status_pesanan'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status_pesanan'])); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="detail_pesanan.php?id=<?php echo $order['pesanan_id']; ?>" class="btn-detail">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const toggleButton = document.getElementById('mobile-toggle-btn');

            if (sidebar && overlay && toggleButton) {
                
                function toggleSidebar() {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.classList.toggle('body-no-scroll');
                }

                function closeSidebar() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.classList.remove('body-no-scroll');
                }

                toggleButton.addEventListener('click', function(event) {
                    event.stopPropagation();
                    toggleSidebar();
                });
                
                overlay.addEventListener('click', closeSidebar);
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    if (sidebar && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                }
            });
        });
    </script>

</body>
</html>
<?php
if(isset($conn)) {
    $conn->close();
}
?>