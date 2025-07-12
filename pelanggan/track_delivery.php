<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa peran 'pelanggan' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header("Location: ../public/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$orders = [];
$message = '';
$message_type = '';

// Ambil pesan notifikasi dari session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// --- LOGIKA KONFIRMASI PENERIMAAN (Logika ini sudah baik, tidak perlu diubah) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'confirm_receipt') {
    $pengiriman_id = filter_var($_POST['pengiriman_id'], FILTER_VALIDATE_INT);
    $pesanan_id = filter_var($_POST['pesanan_id'], FILTER_VALIDATE_INT);

    if (!$pengiriman_id || !$pesanan_id) {
        $_SESSION['message'] = "ID tidak valid.";
        $_SESSION['message_type'] = "error";
    } else {
        $conn->begin_transaction();
        try {
            // Validasi kepemilikan dan status
            $stmt_check = $conn->prepare("SELECT p.id FROM pesanan p JOIN pengiriman peng ON p.id = peng.id_pesanan WHERE p.id = ? AND peng.id = ? AND p.id_pelanggan = ? AND peng.status_pengiriman = 'sudah sampai' AND p.status_pesanan NOT IN ('selesai', 'dibatalkan')");
            $stmt_check->bind_param("iii", $pesanan_id, $pengiriman_id, $user_id);
            $stmt_check->execute();

            if ($stmt_check->get_result()->num_rows == 1) {
                // Update tabel pengiriman dan pesanan
                $stmt_up_peng = $conn->prepare("UPDATE pengiriman SET status_pengiriman = 'selesai', tanggal_selesai = NOW() WHERE id = ?");
                $stmt_up_peng->bind_param("i", $pengiriman_id);
                $stmt_up_peng->execute();
                
                $stmt_up_pes = $conn->prepare("UPDATE pesanan SET status_pesanan = 'selesai' WHERE id = ?");
                $stmt_up_pes->bind_param("i", $pesanan_id);
                $stmt_up_pes->execute();

                $conn->commit();
                $_SESSION['message'] = "Penerimaan pesanan #" . $pesanan_id . " berhasil dikonfirmasi. Terima kasih!";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception("Konfirmasi tidak dapat dilakukan.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: track_delivery.php");
    exit;
}

// Query untuk mengambil semua pesanan, dipisah antara aktif dan histori
$active_orders = [];
$completed_orders = [];

try {
    $stmt = $conn->prepare("
        SELECT p.id AS pesanan_id, p.tanggal_pesan, p.total_harga, p.status_pesanan,
               peng.id AS pengiriman_id, peng.status_pengiriman AS status_pengiriman_detail,
               peng.tanggal_kirim, peng.tanggal_selesai
        FROM pesanan p
        LEFT JOIN pengiriman peng ON p.id = peng.id_pesanan
        WHERE p.id_pelanggan = ?
        ORDER BY FIELD(p.status_pesanan, 'pending', 'diproses', 'dalam perjalanan', 'sudah sampai', 'selesai', 'dibatalkan'), p.tanggal_pesan DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (in_array($row['status_pesanan'], ['selesai', 'dibatalkan'])) {
            $completed_orders[] = $row;
        } else {
            $active_orders[] = $row;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    $message = "Gagal mengambil data pesanan."; $message_type = "error";
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
    <title>Lacak Pesanan - Dashboard Pelanggan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Utama Dashboard (disalin dari template lain untuk konsistensi) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; overflow-x: hidden; }
        body.body-no-scroll { overflow: hidden; }
        
        .sidebar { width: 280px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(12px); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); height: 100vh; position: fixed; left: 0; top: 0; transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94); z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1); background: linear-gradient(135deg, #ffc107, #ff8f00); color: white; }
        .sidebar-header h3 { font-size: 1.4rem; margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: #333; text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; margin: 5px 0; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: linear-gradient(to right, #ffc107, #ffb300); color: white; border-left-color: #ff8f00; transform: translateX(5px); }
        .sidebar-menu a i { width: 20px; margin-right: 15px; }
        .logout-btn { position: absolute; bottom: 20px; left: 20px; right: 20px; }
        .logout-btn a { background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white !important; border-radius: 8px; justify-content: center; padding: 12px; }
        .main-content { margin-left: 280px; flex-grow: 1; padding: 40px; min-height: 100vh; }

        /* CSS Spesifik untuk halaman Lacak Pesanan */
        .page-header { margin-bottom: 30px; }
        .page-header h2 { font-size: 2.5rem; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .page-header p { font-size: 1.1rem; color: rgba(255,255,255,0.8); }

        .content-card { background: rgba(255, 255, 255, 0.97); backdrop-filter: blur(10px); border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); margin-bottom: 30px; animation: fadeInUp 0.6s ease-out; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); }}
        .content-card h3 { font-size: 1.5rem; color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }

        /* Kartu Pesanan */
        .order-card { background: #fff; border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; }
        .order-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .order-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 15px; }
        .order-header .order-id { font-size: 1.3rem; color: #667eea; font-weight: 600; }
        .status-badge { font-weight: 600; padding: 6px 14px; border-radius: 20px; color: white; font-size: 0.8rem; text-transform: uppercase; text-align: center; }
        .status-badge.pending, .status-badge.menunggu_penugasan { background-color: #ffc107; color: #212529 !important; }
        .status-badge.diproses { background-color: #17a2b8; }
        .status-badge.dalam_perjalanan { background-color: #007bff; }
        .status-badge.selesai, .status-badge.sudah_sampai { background-color: #28a745; }
        .status-badge.dibatalkan { background-color: #6c757d; }

        .order-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px 20px; margin-bottom: 15px; }
        .order-details p { margin: 0; line-height: 1.5; color: #6c757d; font-size: 0.95rem; }
        .order-details strong { color: #343a40; }
        .order-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin-top: 15px; border-top: 1px solid #f1f3f5; padding-top: 15px; }
        .btn { padding: 8px 16px; border-radius: 8px; text-decoration: none; border:none; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .btn-primary { background-color: #667eea; color: white; }
        .btn-success { background-color: #28a745; color: white; }
        
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .toast { /* Toast styles from previous example */ visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 1001; bottom: 30px; left: 50%; transform: translateX(-50%); opacity: 0; transition: all 0.5s; }
        .toast.show { visibility: visible; opacity: 1; transform: translate(-50%, -10px); }
        .toast.success { background-color: #28a745; } .toast.error { background-color: #dc3545; }
        
        /* Mobile */
        .mobile-toggle, .overlay { display: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-toggle { display: block; position: fixed; top: 15px; left: 15px; width: 45px; height: 45px; border-radius: 50%; z-index: 1001; }
            .overlay { display: none; } .overlay.active { display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
            .page-header h2 { font-size: 2rem; }
            .order-actions { justify-content: stretch; } .order-actions .btn, .order-actions form { flex-grow: 1; } .order-actions button { width: 100%; }
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
        <div class="page-header">
            <h2>Lacak Pesanan Anda</h2>
            <p>Pantau status pesanan aktif dan konfirmasi penerimaan di sini.</p>
        </div>

        <div class="content-card">
            <h3><i class="fas fa-shipping-fast"></i> Pesanan Aktif</h3>
            <?php if (empty($active_orders)): ?>
                <div class="empty-state">
                    <p>Anda tidak memiliki pesanan yang sedang aktif saat ini.</p>
                    <a href="order_pupuk.php" class="btn btn-primary" style="margin-top: 15px;">Pesan Sekarang</a>
                </div>
            <?php else: ?>
                <?php foreach ($active_orders as $order): 
                    $status = getOverallStatus($order);
                    $status_class = strtolower(str_replace(' ', '_', $status));
                ?>
                <div class="order-card">
                    <div class="order-header">
                        <span class="order-id">Pesanan <?php echo $order['pesanan_id']; ?></span>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?></span>
                    </div>
                    <div class="order-details">
                        <p><strong>Tgl. Pesan:</strong> <?php echo date('d M Y', strtotime($order['tanggal_pesan'])); ?></p>
                        <p><strong>Total:</strong> Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></p>
                        <?php if(!empty($order['tanggal_kirim'])): ?>
                            <p><strong>Tgl. Kirim:</strong> <?php echo date('d M Y', strtotime($order['tanggal_kirim'])); ?></p>
                        <?php endif; ?>
                    </div>
                     <?php if ($status === 'sudah sampai'): ?>
                        <p style="color: #28a745; font-style: italic; margin-top:10px;">Paket telah sampai di lokasi. Mohon konfirmasi penerimaan barang Anda.</p>
                    <?php endif; ?>
                    <div class="order-actions">
                        <a href="detail_pesanan.php?id=<?php echo $order['pesanan_id']; ?>" class="btn btn-primary">Lihat Detail</a>
                        <?php if ($status === 'sudah sampai'): ?>
                            <form action="track_delivery.php" method="POST">
                                <input type="hidden" name="action" value="confirm_receipt">
                                <input type="hidden" name="pengiriman_id" value="<?php echo $order['pengiriman_id']; ?>">
                                <input type="hidden" name="pesanan_id" value="<?php echo $order['pesanan_id']; ?>">
                                <button type="submit" class="btn btn-success" onclick="return confirm('Konfirmasi penerimaan pesanan ini?');">Konfirmasi Terima</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($completed_orders)): ?>
        <div class="content-card">
            <h3><i class="fas fa-history"></i> Riwayat Singkat (<?php echo count($completed_orders); ?> Pesanan)</h3>
            <p style="text-align: center; color: #6c757d; margin-top:-15px; margin-bottom: 20px;">Ini adalah beberapa pesanan Anda yang sudah selesai. Untuk daftar lengkap, kunjungi halaman riwayat.</p>
            <div style="text-align: center;">
                 <a href="order_history.php" class="btn btn-primary" style="background-color:#764ba2;">Lihat Semua Riwayat Pesanan</a>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <div id="toast" class="toast"></div>

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

        // Script untuk menampilkan Toast Notification
        document.addEventListener('DOMContentLoaded', function() {
            const message = <?php echo json_encode($message); ?>;
            const messageType = <?php echo json_encode($message_type); ?>;
            if (message) {
                const toast = document.getElementById('toast');
                toast.textContent = message;
                toast.className = 'toast show ' + messageType;
                setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 3000);
            }
        });
    </script>
</body>
</html>
<?php
if(isset($conn)) { $conn->close(); }
?>