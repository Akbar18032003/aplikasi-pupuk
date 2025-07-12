<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa peran 'pelanggan' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header("Location: ../public/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pesanan_id = $_GET['pesanan_id'] ?? null;
$order_data = null;
$encoded_address = '';

if (!$pesanan_id || !is_numeric($pesanan_id)) {
    $_SESSION['message'] = "ID Pesanan tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: track_delivery.php");
    exit;
} else {
    // Kueri sudah baik dan efisien. Tidak perlu diubah.
    $stmt_order = $conn->prepare("
        SELECT p.id AS pesanan_id, p.status_pesanan, p.alamat_pengiriman,
               peng.status_pengiriman AS status_pengiriman_detail,
               peng.koordinat_sopir_lat, peng.koordinat_sopir_long
        FROM pesanan p
        LEFT JOIN pengiriman peng ON p.id = peng.id_pesanan
        WHERE p.id = ? AND p.id_pelanggan = ? LIMIT 1
    ");
    $stmt_order->bind_param("ii", $pesanan_id, $user_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();

    if ($result_order->num_rows == 1) {
        $order_data = $result_order->fetch_assoc();
        $encoded_address = urlencode($order_data['alamat_pengiriman']);
    } else {
        $_SESSION['message'] = "Pesanan tidak ditemukan.";
        $_SESSION['message_type'] = "error";
        header("Location: track_delivery.php");
        exit;
    }
    $stmt_order->close();
}

function getOverallStatus($order_data) {
    if ($order_data['status_pesanan'] === 'selesai' || $order_data['status_pesanan'] === 'dibatalkan') return $order_data['status_pesanan'];
    return !empty($order_data['status_pengiriman_detail']) ? $order_data['status_pengiriman_detail'] : $order_data['status_pesanan'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lokasi Peta Pesanan #<?php echo htmlspecialchars($pesanan_id); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Utama Dashboard (sama seperti template lainnya) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; overflow-x: hidden; }
        body.body-no-scroll { overflow: hidden; }

        .sidebar { width: 280px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(12px); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); height: 100vh; position: fixed; left: 0; top: 0; transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94); z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1); background: linear-gradient(135deg, #ffc107, #ff8f00); color: white; }
        .sidebar-header h3 { font-size: 1.4rem; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: #333; text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; margin: 5px 0; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: linear-gradient(to right, #ffc107, #ffb300); color: white; border-left-color: #ff8f00; transform: translateX(5px); }
        .sidebar-menu a i { width: 20px; margin-right: 15px; }
        .logout-btn { position: absolute; bottom: 20px; left: 20px; right: 20px; }
        .logout-btn a { background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white !important; border-radius: 8px; justify-content: center; padding: 12px; }
        
        .main-content { margin-left: 280px; flex-grow: 1; padding: 40px; min-height: 100vh; }
        
        .content-card { background: rgba(255, 255, 255, 0.97); backdrop-filter: blur(10px); border-radius: 20px; padding: 30px 40px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); animation: fadeInUp 0.6s ease-out; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); }}

        .content-card h2 {
            font-size: 2.2rem; margin-bottom: 25px; background: linear-gradient(135deg, #667eea, #764ba2);
            background-clip: text;
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; text-align: left;
        }

        /* Styling untuk info dan peta */
        .map-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 992px) {
            .map-info-grid { grid-template-columns: 300px 1fr; } /* Info di kiri, peta besar di kanan (desktop) */
        }
        
        .order-info-panel { padding: 20px; background-color: #f8f9fa; border-radius: 12px; }
        .order-info-panel h4 { font-size: 1.3rem; margin-bottom: 15px; color: #343a40; }
        .order-info-panel p { margin-bottom: 10px; line-height: 1.6; color: #6c757d; font-size: 0.95rem; }
        .order-info-panel p strong { color: #343a40; }
        .status-badge { font-weight: 600; padding: 6px 14px; border-radius: 20px; color: white; font-size: 0.8em; text-transform: uppercase; }
        .status-badge.pending, .status-badge.menunggu_penugasan { background-color: #ffc107; color: #212529 !important; }
        .status-badge.diproses { background-color: #17a2b8; }
        .status-badge.dalam_perjalanan { background-color: #007bff; }
        .status-badge.selesai, .status-badge.sudah_sampai { background-color: #28a745; }
        .status-badge.dibatalkan { background-color: #6c757d; }

        .map-container iframe {
            width: 100%;
            height: 500px;
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .button-group {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn { padding: 12px 20px; border-radius: 8px; text-decoration: none; border:none; cursor: pointer; font-weight: 500; transition: all 0.2s; text-align: center; display: block; }
        .btn-maps { background-color: #28a745; color: white; }
        .btn-back { background-color: #6c757d; color: white; }

        /* Mobile */
        .mobile-toggle, .overlay { display: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-toggle { display: block; position: fixed; top: 15px; left: 15px; width: 45px; height: 45px; border-radius: 50%; z-index: 1001; }
            .overlay { display: none; } .overlay.active { display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
            .content-card h2 { font-size: 1.8rem; }
            .map-container iframe { height: 350px; }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <button class="mobile-toggle" style="background-color: #ffc107; color: white; border: none; cursor: pointer;"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <!-- Sidebar content -->
        <div class="sidebar-header"><h3>Dashboard Pelanggan</h3><p>Selamat Datang,<br><?php echo htmlspecialchars($_SESSION['username']); ?>!</p></div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="order_pupuk.php"><i class="fas fa-shopping-cart"></i><span>Pesan Pupuk</span></a>
            <a href="track_delivery.php" class="active"><i class="fas fa-truck"></i><span>Lacak Pesanan</span></a>
            <a href="order_history.php"><i class="fas fa-history"></i><span>Riwayat Pesanan</span></a>
        </div>
        <div class="logout-btn"><a href="../public/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
    </div>
    
    <main class="main-content">
        <?php if ($order_data): ?>
            <div class="content-card">
                <h2>Lokasi Peta Pesanan #<?php echo htmlspecialchars($pesanan_id); ?></h2>

                <div class="map-info-grid">
                    <!-- Kolom Informasi Pesanan -->
                    <div class="order-info-panel">
                        <h4>Info Pengiriman</h4>
                        <p><strong>ID Pesanan:</strong><br>#<?php echo htmlspecialchars($order_data['pesanan_id']); ?></p>
                        <?php 
                            $status = getOverallStatus($order_data);
                            $status_class = strtolower(str_replace(' ', '_', $status));
                        ?>
                        <p><strong>Status:</strong><br>
                           <span class="status-badge <?php echo $status_class; ?>">
                               <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?>
                           </span>
                        </p>
                        <p><strong>Alamat Tujuan:</strong><br><?php echo nl2br(htmlspecialchars($order_data['alamat_pengiriman'])); ?></p>
                        
                        <div class="button-group">
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $encoded_address; ?>" target="_blank" class="btn btn-maps"><i class="fas fa-external-link-alt"></i> Buka di Google Maps</a>
                            <a href="track_delivery.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                    
                    <!-- Kolom Peta -->
                    <div class="map-container">
                        <iframe
                            width="100%"
                            height="100%"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade"
                            src="https://maps.google.com/maps?q=<?php echo $encoded_address; ?>&output=embed">
                        </iframe>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Script untuk toggle sidebar mobile
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const toggleButton = document.querySelector('.mobile-toggle');
        function toggleSidebar() { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); document.body.classList.toggle('body-no-scroll'); }
        function closeSidebar() { sidebar.classList.remove('active'); overlay.classList.remove('active'); document.body.classList.remove('body-no-scroll'); }
        toggleButton.addEventListener('click', (e) => { e.stopPropagation(); toggleSidebar(); });
        overlay.addEventListener('click', closeSidebar);
        window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
    </script>
</body>
</html>
<?php
if(isset($conn)) { $conn->close(); }
?>