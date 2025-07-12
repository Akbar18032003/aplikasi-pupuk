<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa apakah pengguna sudah login ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

// Logika PHP dari file asli Anda tetap sama
// Ini sudah sangat baik dan tidak perlu diubah.
$pesanan_id_get = $_GET['id'] ?? null;
$order_details = null;
$items_in_order = [];
$message = '';
$message_type = '';

$pesanan_id = filter_var($pesanan_id_get, FILTER_VALIDATE_INT);

if ($pesanan_id === false || $pesanan_id <= 0) {
    $_SESSION['message'] = "ID Pesanan tidak valid.";
    $_SESSION['message_type'] = "error";
    $redirect_page = '../public/login.php';
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') $redirect_page = '../admin/manage_orders.php';
        else if ($_SESSION['role'] === 'pelanggan') $redirect_page = '../pelanggan/track_delivery.php';
        else if ($_SESSION['role'] === 'sopir') $redirect_page = '../sopir/my_deliveries.php';
    }
    header("Location: " . $redirect_page);
    exit;
} else {
    // Kueri sudah baik, menyatukan info pesanan dan pengiriman
    $stmt_order = $conn->prepare("
        SELECT
            p.id, p.tanggal_pesan, p.total_harga, p.alamat_pengiriman,
            p.catatan AS catatan_pelanggan, p.status_pesanan,
            u_pel.id AS id_pelanggan_owner, u_pel.nama_lengkap AS nama_pelanggan,
            u_pel.telepon AS telepon_pelanggan, u_pel.email AS email_pelanggan,
            peng.id AS pengiriman_id_data, peng.status_pengiriman,
            peng.tanggal_kirim, peng.tanggal_selesai AS tanggal_selesai_pengiriman,
            peng.catatan_sopir, peng.gambar_bukti_sampai,
            peng.koordinat_sopir_lat, peng.koordinat_sopir_long,
            u_sop.nama_lengkap AS nama_sopir_pengiriman,
            u_sop.telepon AS telepon_sopir_pengiriman
        FROM pesanan p
        JOIN users u_pel ON p.id_pelanggan = u_pel.id
        LEFT JOIN pengiriman peng ON peng.id_pesanan = p.id
        LEFT JOIN users u_sop ON peng.id_sopir = u_sop.id
        WHERE p.id = ?
    ");

    $stmt_order->bind_param("i", $pesanan_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();

    if ($result_order->num_rows == 1) {
        $order_details = $result_order->fetch_assoc();
        // Cek Otorisasi
        $can_view = false;
        if ($_SESSION['role'] === 'admin') $can_view = true;
        elseif ($_SESSION['role'] === 'pelanggan' && $order_details['id_pelanggan_owner'] == $_SESSION['user_id']) $can_view = true;
        elseif ($_SESSION['role'] === 'sopir' && !empty($order_details['pengiriman_id_data'])) {
            $stmt_check_sopir = $conn->prepare("SELECT id FROM pengiriman WHERE id = ? AND id_sopir = ?");
            $stmt_check_sopir->bind_param("ii", $order_details['pengiriman_id_data'], $_SESSION['user_id']);
            $stmt_check_sopir->execute();
            if ($stmt_check_sopir->get_result()->num_rows > 0) $can_view = true;
            $stmt_check_sopir->close();
        }

        if (!$can_view) {
             $_SESSION['message'] = "Anda tidak diizinkan melihat detail pesanan ini.";
             $_SESSION['message_type'] = "error";
            $redirect_page = '../public/login.php';
            if ($_SESSION['role'] === 'pelanggan') $redirect_page = '../pelanggan/track_delivery.php';
            else if ($_SESSION['role'] === 'sopir') $redirect_page = '../sopir/my_deliveries.php';
            else if ($_SESSION['role'] === 'admin') $redirect_page = '../admin/manage_orders.php';
            header("Location: " . $redirect_page);
            exit;
        }

        $stmt_items = $conn->prepare("
            SELECT dp.jumlah, dp.harga_satuan, pu.nama_pupuk, pu.jenis_pupuk FROM detail_pesanan dp
            JOIN pupuk pu ON dp.id_pupuk = pu.id WHERE dp.id_pesanan = ?");
        $stmt_items->bind_param("i", $pesanan_id);
        $stmt_items->execute();
        $items_in_order = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_items->close();
    } else {
        $_SESSION['message'] = "Pesanan tidak ditemukan.";
        $_SESSION['message_type'] = "error";
        $redirect_page = '../public/login.php';
        if ($_SESSION['role'] === 'admin') $redirect_page = '../admin/manage_orders.php';
        else if ($_SESSION['role'] === 'pelanggan') $redirect_page = '../pelanggan/track_delivery.php';
        else if ($_SESSION['role'] === 'sopir') $redirect_page = '../sopir/my_deliveries.php';
        header("Location: " . $redirect_page);
        exit;
    }
    $stmt_order->close();
}

if (empty($message) && isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

function getOverallOrderStatus($status_pesanan_db, $status_pengiriman_db) {
    if ($status_pesanan_db === 'selesai' || $status_pesanan_db === 'dibatalkan') return $status_pesanan_db;
    return !empty($status_pengiriman_db) ? $status_pengiriman_db : $status_pesanan_db;
}

$current_role = $_SESSION['role'] ?? 'pelanggan'; // Default role jika tidak diset
$username = $_SESSION['username'] ?? 'Tamu';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo htmlspecialchars($pesanan_id_get ?? 'N/A'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

        .sidebar {
            width: 280px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(12px);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); height: 100vh; position: fixed;
            left: 0; top: 0; transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94); z-index: 1000;
        }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1); color: white; }
        .sidebar-header h3 { font-size: 1.4rem; margin-bottom: 5px; font-weight: 600; }
        .sidebar-header p { font-size: 0.9rem; opacity: 0.9; word-wrap: break-word; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 15px 25px; color: #333;
            text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; margin: 5px 0;
        }
        .sidebar-menu a i { width: 20px; margin-right: 15px; font-size: 1.1rem; }
        .sidebar-menu a span { font-weight: 500; }
        .logout-btn { position: absolute; bottom: 20px; left: 20px; right: 20px; }
        .logout-btn a { justify-content: center; padding: 12px; }

        /* [PENTING] Style khusus berdasarkan role */
        .theme-pelanggan .sidebar-header, .theme-pelanggan .sidebar-menu a:hover, .theme-pelanggan .sidebar-menu a.active { background: linear-gradient(to right, #ffc107, #ffb300); }
        .theme-pelanggan .sidebar-menu a:hover, .theme-pelanggan .sidebar-menu a.active { border-left-color: #ff8f00; }

        .theme-admin .sidebar-header, .theme-admin .sidebar-menu a:hover, .theme-admin .sidebar-menu a.active { background: linear-gradient(to right, #2980b9, #3498db); }
        .theme-admin .sidebar-menu a:hover, .theme-admin .sidebar-menu a.active { border-left-color: #2980b9; }

        .theme-sopir .sidebar-header, .theme-sopir .sidebar-menu a:hover, .theme-sopir .sidebar-menu a.active { background: linear-gradient(to right, #27ae60, #2ecc71); }
        .theme-sopir .sidebar-menu a:hover, .theme-sopir .sidebar-menu a.active { border-left-color: #27ae60; }
        
        .sidebar-menu a:hover, .sidebar-menu a.active { color: white; transform: translateX(5px); }

        .logout-btn a {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white !important;
            border-radius: 8px; border-left: none !important;
        }
        .logout-btn a:hover {
            background: linear-gradient(135deg, #ff5252, #d32f2f);
            transform: translateY(-2px) !important; box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .main-content { margin-left: 280px; flex-grow: 1; padding: 40px; min-height: 100vh; }
        .content-card {
            background: rgba(255, 255, 255, 0.97); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 30px 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .content-card h2 {
            font-size: 2.2rem; margin-bottom: 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            background-clip: text;
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; text-align: center;
        }
        .section { margin-bottom: 25px; }
        .section h3 {
            font-size: 1.4rem; color: #333; border-bottom: 2px solid #ddd;
            padding-bottom: 10px; margin-bottom: 20px; display: flex; align-items: center;
        }
        .section h3 i { margin-right: 12px; color: #764ba2; }
        .section p { margin-bottom: 12px; line-height: 1.6; color: #555; }
        .section p strong { color: #222; }

        .order-items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .order-items-table th, .order-items-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .order-items-table th { background-color: #f8f9fa; color: #333; font-weight: 600; }
        .total-summary { text-align: right; margin-top: 20px; font-size: 1.4em; font-weight: bold; color: #2ecc71; }

        .button-group { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px;}
        .btn {
            background-color: #3498db; color: white !important; padding: 12px 20px; border-radius: 8px;
            text-decoration: none; border: none; cursor: pointer; transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); font-weight: 500; display: inline-flex; align-items: center;
        }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4); }
        .btn-back { background-color: #7f8c8d; }
        .btn-back:hover { box-shadow: 0 6px 20px rgba(127, 140, 141, 0.4); }

        .status-badge { display: inline-block; padding: .4em .8em; font-size: .85em; font-weight: 700; line-height: 1; color: #fff; text-align: center; border-radius: .3rem; text-transform: capitalize; }
        .status-badge.pending, .status-badge.menunggu_penugasan { background-color: #ffc107; color: #212529 !important; }
        .status-badge.diproses { background-color: #17a2b8; }
        .status-badge.dalam_perjalanan { background-color: #007bff; }
        .status-badge.selesai, .status-badge.sudah_sampai { background-color: #28a745; }
        .status-badge.dibatalkan { background-color: #343a40; }

        .delivery-proof-image { max-width: 100%; height: auto; max-height: 400px; border-radius: 8px; margin-top: 10px; cursor: pointer; }
        #delivery-map { height: 350px; width: 100%; margin-top: 15px; border-radius: 8px; }

        .mobile-toggle { display: none; position: fixed; top: 15px; left: 15px; width: 45px; height: 45px; border-radius: 50%; z-index: 1001; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        /* Style lainnya dari template Anda di sini... */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .content-card { padding: 25px 20px; }
            .content-card h2 { font-size: 1.8rem; }
            .mobile-toggle, .overlay { display: block; }
            .overlay.active { opacity: 1; }
            .button-group { justify-content: center; }
            .btn { width: 100%; text-align: center; justify-content: center; }
        }
    </style>
</head>
<body class="theme-<?php echo htmlspecialchars($current_role); ?>">
    <div class="overlay" id="overlay"></div>

    <button class="mobile-toggle btn btn-<?php echo htmlspecialchars($current_role); ?>">
        <i class="fas fa-bars"></i>
    </button>

    <!-- [PENTING] Sidebar Dinamis berdasarkan Role Pengguna -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Dashboard <?php echo ucfirst($current_role); ?></h3>
            <p>Halo, <?php echo htmlspecialchars($username); ?>!</p>
        </div>
        <div class="sidebar-menu">
            <?php if ($current_role === 'admin'): ?>
                <a href="../admin/index.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                <a href="../admin/manage_orders.php" class="active"><i class="fas fa-box-open"></i><span>Kelola Pesanan</span></a>
                <a href="../admin/manage_products.php"><i class="fas fa-seedling"></i><span>Kelola Pupuk</span></a>
                <a href="../admin/manage_users.php"><i class="fas fa-users-cog"></i><span>Kelola Pengguna</span></a>
            <?php elseif ($current_role === 'pelanggan'): ?>
                <a href="../pelanggan/index.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
                <a href="../pelanggan/order_pupuk.php"><i class="fas fa-shopping-cart"></i><span>Pesan Pupuk</span></a>
                <a href="../pelanggan/track_delivery.php" class="active"><i class="fas fa-truck"></i><span>Lacak Pesanan</span></a>
                <a href="../pelanggan/order_history.php"><i class="fas fa-history"></i><span>Riwayat Pesanan</span></a>
            <?php elseif ($current_role === 'sopir'): ?>
                <a href="../sopir/index.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                <a href="../sopir/my_deliveries.php" class="active"><i class="fas fa-route"></i><span>Tugas Pengiriman</span></a>
            <?php endif; ?>
        </div>
        <div class="logout-btn">
            <a href="../public/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <main class="main-content" id="main-content">
        <div class="content-card">
            <h2>Detail Pesanan #<?php echo htmlspecialchars($pesanan_id_get ?? 'N/A'); ?></h2>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($order_details): ?>
                <!-- Informasi Pesanan -->
                <div class="section">
                    <h3><i class="fas fa-info-circle"></i>Informasi Pesanan</h3>
                    <p>ID Pesanan: <strong>#<?php echo htmlspecialchars($order_details['id']); ?></strong></p>
                    <p>Tanggal Pemesanan: <strong><?php echo date('d F Y, H:i', strtotime($order_details['tanggal_pesan'])); ?></strong></p>
                    <?php
                        $overall_status = getOverallOrderStatus($order_details['status_pesanan'], $order_details['status_pengiriman']);
                        $status_class = strtolower(str_replace(' ', '_', $overall_status));
                    ?>
                    <p>Status: <strong class="status-badge <?php echo htmlspecialchars($status_class); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $overall_status))); ?></strong></p>
                    <?php if (!empty($order_details['catatan_pelanggan'])): ?>
                        <p>Catatan Pelanggan: <strong><?php echo nl2br(htmlspecialchars($order_details['catatan_pelanggan'])); ?></strong></p>
                    <?php endif; ?>
                </div>

                <!-- Detail Pelanggan -->
                <div class="section">
                    <h3><i class="fas fa-user"></i>Detail Pelanggan</h3>
                    <p>Nama: <strong><?php echo htmlspecialchars($order_details['nama_pelanggan']); ?></strong></p>
                    <p>Telepon: <strong><?php echo htmlspecialchars($order_details['telepon_pelanggan']); ?></strong></p>
                    <p>Alamat Pengiriman: <br><strong><?php echo nl2br(htmlspecialchars($order_details['alamat_pengiriman'])); ?></strong></p>
                </div>
                
                <!-- Rincian Item Pesanan -->
                <div class="section">
                    <h3><i class="fas fa-receipt"></i>Rincian Item Pesanan</h3>
                    <?php if (empty($items_in_order)): ?>
                        <p>Tidak ada item untuk pesanan ini.</p>
                    <?php else: ?>
                        <table class="order-items-table">
                            <thead>
                                <tr><th>Nama Pupuk</th><th style="text-align:right;">Jumlah</th><th style="text-align:right;">Harga Satuan</th><th style="text-align:right;">Subtotal</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_in_order as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nama_pupuk']); ?></td>
                                        <td style="text-align:right;"><?php echo htmlspecialchars($item['jumlah']); ?></td>
                                        <td style="text-align:right;">Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></td>
                                        <td style="text-align:right;">Rp <?php echo number_format($item['jumlah'] * $item['harga_satuan'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="total-summary">Total: Rp <?php echo number_format($order_details['total_harga'], 0, ',', '.'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Detail Pengiriman -->
                <div class="section">
                    <h3><i class="fas fa-truck"></i>Detail Pengiriman</h3>
                    <?php if (!empty($order_details['pengiriman_id_data'])): ?>
                        <p>Sopir: <strong><?php echo htmlspecialchars($order_details['nama_sopir_pengiriman'] ?? 'Belum ditugaskan'); ?></strong></p>
                        <p>Tanggal Dikirim: <strong><?php echo !empty($order_details['tanggal_kirim']) ? date('d F Y', strtotime($order_details['tanggal_kirim'])) : 'Belum ada info'; ?></strong></p>
                        <?php if (!empty($order_details['tanggal_selesai_pengiriman'])): ?>
                            <p>Tanggal Selesai: <strong><?php echo date('d F Y, H:i', strtotime($order_details['tanggal_selesai_pengiriman'])); ?></strong></p>
                        <?php endif; ?>
                        <?php if (!empty($order_details['catatan_sopir'])): ?>
                            <p>Catatan Sopir: <strong><?php echo nl2br(htmlspecialchars($order_details['catatan_sopir'])); ?></strong></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($order_details['gambar_bukti_sampai'])): ?>
                            <h4 style="margin-top:20px; margin-bottom:10px; font-size:1.1em;">Bukti Sampai:</h4>
                            <a href="../uploads/bukti_pengiriman/<?php echo htmlspecialchars($order_details['gambar_bukti_sampai']); ?>" target="_blank">
                                <img src="../uploads/bukti_pengiriman/<?php echo htmlspecialchars($order_details['gambar_bukti_sampai']); ?>" alt="Bukti Pengiriman" class="delivery-proof-image">
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($order_details['koordinat_sopir_lat']) && !empty($order_details['koordinat_sopir_long'])): ?>
                            <h4 style="margin-top:20px; margin-bottom:10px; font-size:1.1em;">Lokasi Terakhir Sopir:</h4>
                            <div id="delivery-map">Memuat peta...</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Informasi pengiriman akan tersedia setelah pesanan diproses oleh admin.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Tombol Aksi -->
                <div class="button-group">
                    <?php /* Logika tombol kembali yang dinamis */
                        $back_link = '#'; $back_text = 'Kembali';
                        if ($current_role === 'admin') { $back_link = '../admin/manage_orders.php'; $back_text = 'Ke Daftar Pesanan'; }
                        elseif ($current_role === 'pelanggan') { $back_link = '../pelanggan/track_delivery.php'; $back_text = 'Ke Pelacakan Pesanan'; }
                        elseif ($current_role === 'sopir') { $back_link = '../sopir/my_deliveries.php'; $back_text = 'Ke Tugas Saya'; }
                    ?>
                    <a href="<?php echo $back_link; ?>" class="btn btn-back"><?php echo $back_text; ?></a>
                    
                    <?php /* Tombol aksi spesifik untuk Admin */
                        if ($current_role === 'admin' && empty($order_details['pengiriman_id_data']) && in_array($order_details['status_pesanan'], ['pending', 'diproses'])) {
                            echo '<a href="../admin/assign_delivery.php?pesanan_id=' . $order_details['id'] . '" class="btn">Tugaskan Pengiriman</a>';
                        } elseif ($current_role === 'admin' && !empty($order_details['pengiriman_id_data']) && $order_details['status_pesanan'] !== 'selesai' && $order_details['status_pesanan'] !== 'dibatalkan') {
                             echo '<a href="../admin/edit_delivery.php?pengiriman_id=' . $order_details['pengiriman_id_data'] . '" class="btn" style="background-color: #e67e22;">Edit Pengiriman</a>';
                        }
                    ?>
                    <?php /* Tombol aksi spesifik untuk Sopir */
                        if ($current_role === 'sopir' && !empty($order_details['pengiriman_id_data']) && !in_array($order_details['status_pengiriman'], ['selesai', 'dibatalkan', 'sudah_sampai'])) {
                            echo '<a href="../sopir/update_status_pengiriman.php?pengiriman_id=' . $order_details['pengiriman_id_data'] . '" class="btn" style="background-color: #27ae60;">Update Status</a>';
                        }
                    ?>
                </div>

            <?php endif; // End if ($order_details) ?>
        </div>
    </main>

    <script>
        // Script untuk toggle sidebar di mobile, sama seperti sebelumnya
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const toggleButton = document.querySelector('.mobile-toggle');

        function toggleSidebar() { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); document.body.classList.toggle('body-no-scroll'); }
        function closeSidebar() { sidebar.classList.remove('active'); overlay.classList.remove('active'); document.body.classList.remove('body-no-scroll'); }

        toggleButton.addEventListener('click', function(e) { e.stopPropagation(); toggleSidebar(); });
        overlay.addEventListener('click', closeSidebar);
        window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

        // Script untuk peta, sama seperti sebelumnya
        <?php if ($order_details && !empty($order_details['koordinat_sopir_lat']) && !empty($order_details['koordinat_sopir_long'])): ?>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                var lat = parseFloat(<?php echo json_encode($order_details['koordinat_sopir_lat']); ?>);
                var lng = parseFloat(<?php echo json_encode($order_details['koordinat_sopir_long']); ?>);
                if (!isNaN(lat) && !isNaN(lng)) {
                    var map = L.map('delivery-map').setView([lat, lng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                    L.marker([lat, lng]).addTo(map).bindPopup('Lokasi terakhir sopir.').openPopup();
                } else {
                     document.getElementById('delivery-map').innerHTML = '<p>Koordinat lokasi tidak valid.</p>';
                }
            } catch (e) {
                console.error("Gagal inisialisasi peta: ", e);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Selalu tutup koneksi di akhir
if (isset($conn)) {
    $conn->close();
}
?>