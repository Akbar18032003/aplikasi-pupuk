<?php
session_start();
require_once '../config/database.php';

// --- Keamanan & Otorisasi ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$pesanan_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$pesanan_id) {
    // Redirect cerdas jika ID tidak valid
    $redirect_page = '../' . ($_SESSION['role'] ?? 'public') . '/';
    $redirect_page .= ($_SESSION['role'] === 'admin') ? 'manage_orders.php' : 'dashboard.php'; // Ganti dengan halaman dashboard masing-masing role
    $_SESSION['message'] = "ID Pesanan tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: " . $redirect_page);
    exit;
}

// --- Ambil Detail Pesanan (Query diperbarui) ---
$stmt_order = $conn->prepare("SELECT p.id, 
    p.tanggal_pesan, 
    p.total_harga, 
    p.alamat_pengiriman, 
    p.catatan AS catatan_pelanggan, 
    p.status_pesanan, 
    u_pel.id AS id_pelanggan_owner, 
    u_pel.nama_lengkap AS nama_pelanggan, 
    u_pel.telepon AS telepon_pelanggan, 
    u_pel.email AS email_pelanggan,
    peng.id AS pengiriman_id, 
    peng.status_pengiriman, 
    peng.tanggal_kirim, 
    peng.tanggal_selesai AS tanggal_selesai_pengiriman, 
    peng.catatan_sopir,
    peng.gambar_bukti_sampai, 
    peng.koordinat_sopir_lat, 
    peng.koordinat_sopir_long,
    peng.no_kendaraan, /* <--- [PERUBAHAN 1] Ambil kolom nomor kendaraan */
    u_sop.id AS id_sopir_owner, 
    u_sop.nama_lengkap AS nama_sopir_pengiriman,
    u_sop.telepon AS telepon_sopir_pengiriman
FROM pesanan p 
JOIN users u_pel ON p.id_pelanggan = u_pel.id 
LEFT JOIN pengiriman peng ON peng.id_pesanan = p.id 
LEFT JOIN users u_sop ON peng.id_sopir = u_sop.id 
WHERE p.id = ?");
$stmt_order->bind_param("i", $pesanan_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();
$order_details = $result_order->fetch_assoc();
$stmt_order->close();

if (!$order_details) {
    $_SESSION['message'] = "Pesanan tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    $redirect_page = '../' . ($_SESSION['role'] ?? 'public') . '/';
    $redirect_page .= ($_SESSION['role'] === 'admin') ? 'manage_orders.php' : 'dashboard.php';
    header("Location: " . $redirect_page);
    exit;
}

// Cek otorisasi untuk melihat detail
$can_view = false;
if ($_SESSION['role'] === 'admin' || ($_SESSION['role'] === 'pelanggan' && $order_details['id_pelanggan_owner'] == $_SESSION['user_id']) || ($_SESSION['role'] === 'sopir' && $order_details['id_sopir_owner'] == $_SESSION['user_id'])) {
    $can_view = true;
}

if (!$can_view) {
    $_SESSION['message'] = "Anda tidak diizinkan melihat detail pesanan ini.";
    $_SESSION['message_type'] = "error";
    $redirect_page = '../' . ($_SESSION['role'] ?? 'public') . '/';
    $redirect_page .= ($_SESSION['role'] === 'admin') ? 'manage_orders.php' : 'dashboard.php';
    header("Location: " . $redirect_page);
    exit;
}

// Ambil item dalam pesanan
$items_in_order = [];
$stmt_items = $conn->prepare("SELECT dp.jumlah, dp.harga_satuan, pu.nama_pupuk FROM detail_pesanan dp JOIN pupuk pu ON dp.id_pupuk = pu.id WHERE dp.id_pesanan = ?");
$stmt_items->bind_param("i", $pesanan_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($row_item = $result_items->fetch_assoc()) {
    $items_in_order[] = $row_item;
}
$stmt_items->close();

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);

$back_url = '../public/login.php';
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            $back_url = 'manage_deliveries.php'; 
            break;
        case 'pelanggan':
            $back_url = '../pelanggan/order_history.php';
            break;
        case 'sopir':
            $back_url = '../sopir/my_deliveries.php';
            break;
        default:
            $back_url = '../' . $_SESSION['role'] . '/dashboard.php';
            break;
    }
}

// Fungsi status (sudah baik, kita pertahankan)
function getOverallOrderStatus($status_pesanan_db, $status_pengiriman_db) {
    if ($status_pesanan_db === 'selesai' || $status_pesanan_db === 'dibatalkan') return $status_pesanan_db;
    return $status_pengiriman_db ?? $status_pesanan_db;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan <?php echo htmlspecialchars($pesanan_id); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; transition: transform 0.3s ease; box-shadow: 4px 0 20px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 1.4rem; }
        .sidebar-header p { color: #bdc3c7; font-size: 0.9rem; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link:hover { background: rgba(52,152,219,0.2); color: #3498db; }
        .nav-link.active { background: #3498db; font-weight: 600; }
        .nav-link i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar-toggle { display: none; }
        .main-content { margin-left: 280px; padding: 40px; transition: margin-left 0.3s ease; }
        .page-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; }
        .page-header h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .detail-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .detail-card h3 { color: #3498db; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; margin-bottom: 15px; font-size: 1.2rem; display:flex; align-items:center; }
        .detail-card h3 i { margin-right: 10px; }
        .detail-card p { line-height: 1.7; color: #555; margin-bottom: 10px; }
        .detail-card strong { color: #2c3e50; }
        .item-table { width: 100%; border-collapse: collapse; }
        .item-table th, .item-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .item-table th { background-color: #f8f9fa; }
        .total-summary { text-align: right; margin-top: 15px; font-size: 1.5em; font-weight: bold; color: #27ae60; }
        .delivery-proof-thumbnail { width: 150px; height: auto; border-radius: 8px; margin-top: 10px; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        .delivery-proof-thumbnail:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        #delivery-map { height: 300px; border-radius: 8px; margin-top: 15px; }
        .status-badge { display: inline-block; padding: .5em .8em; font-size: .85em; font-weight: 700; color: #fff; border-radius: 50px; text-transform: capitalize; }
        .status-badge.pending { background-color: #ffc107; color: #333; }
        .status-badge.diproses, .status-badge.dalam_perjalanan { background-color: #17a2b8; }
        .status-badge.menunggu_penugasan { background-color: #6c757d; }
        .status-badge.sudah_sampai { background-color: #28a745; }
        .status-badge.selesai { background-color: #6f42c1; }
        .status-badge.dibatalkan, .status-badge.bermasalah { background-color: #dc3545; }
        .image-modal { display: none; position: fixed; z-index: 1001; padding-top: 50px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); }
        .image-modal-content { margin: auto; display: block; max-width: 80%; max-height: 85%; }
        .image-modal-close { position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; }
        .image-modal-close:hover, .image-modal-close:focus { color: #bbb; text-decoration: none; cursor: pointer; }
        @media print { body { background: #fff; color: #000; } .sidebar, .sidebar-toggle, .page-header h1, .button-group { display: none; } .main-content { margin-left: 0; padding: 0; } .detail-card, .page-header { box-shadow: none; border: 1px solid #ccc; margin-bottom: 20px; } }
        @media (max-width: 992px) { .main-content { margin-left: 0; } .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .sidebar-toggle { display: block; } }
        .button-group { text-align: right; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.2); }
        .btn { display: inline-block; padding: 12px 25px; margin-left: 10px; font-size: 1rem; font-weight: 600; color: #ffffff; text-align: center; vertical-align: middle; background-color: #3498db; border: none; border-radius: 8px; text-decoration: none; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15); opacity: 0.95; }
        .btn:first-child { margin-left: 0; }
    </style>
</head>
<body>
    <!-- Sidebar (tidak ada perubahan) -->
    <div class="sidebar" id="sidebar">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="sidebar-header"><h3><i class="fas fa-user-shield"></i> Admin Panel</h3></div>
            <nav class="sidebar-nav">
                <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
                <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
                <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
                <div class="nav-item"><a href="manage_deliveries.php" class="nav-link"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
                <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
                <div class="nav-item"><a href="../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
            </nav>
        <?php elseif ($_SESSION['role'] === 'pelanggan'): ?>
             <div class="sidebar-header"><h3><i class="fas fa-user"></i> Pelanggan Area</h3></div>
             <nav class="sidebar-nav">
                <div class="nav-item"><a href="../pelanggan/dashboard.php" class="nav-link"><i class="fas fa-home"></i>Dashboard</a></div>
                <div class="nav-item"><a href="../pelanggan/order_history.php" class="nav-link active"><i class="fas fa-history"></i>Riwayat Pesanan</a></div>
                <div class="nav-item"><a href="../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
             </nav>
        <?php elseif ($_SESSION['role'] === 'sopir'): ?>
            <div class="sidebar-header"><h3><i class="fas fa-truck"></i> Sopir Area</h3></div>
            <nav class="sidebar-nav">
                 <div class="nav-item"><a href="../sopir/dashboard.php" class="nav-link"><i class="fas fa-home"></i>Dashboard</a></div>
                 <div class="nav-item"><a href="../sopir/my_deliveries.php" class="nav-link active"><i class="fas fa-route"></i>Pengiriman Saya</a></div>
                 <div class="nav-item"><a href="../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
            </nav>
        <?php endif; ?>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>Detail Pesanan <small><?php echo htmlspecialchars($pesanan_id); ?></small></h1>
        </div>

        <div class="detail-grid">
            <div class="detail-card">
                <h3><i class="fas fa-info-circle"></i>Informasi Pesanan</h3>
                <?php
                    $overall_status = getOverallOrderStatus($order_details['status_pesanan'], $order_details['status_pengiriman']);
                    $status_class = strtolower(str_replace(' ', '_', $overall_status));
                ?>
                <p><strong>Status:</strong> <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($overall_status); ?></span></p>
                <p><strong>Tanggal Pesan:</strong> <?php echo date('d M Y, H:i', strtotime($order_details['tanggal_pesan'])); ?></p>
                <?php if ($order_details['catatan_pelanggan']): ?>
                    <p><strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($order_details['catatan_pelanggan'])); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="detail-card">
                <h3><i class="fas fa-user-circle"></i>Informasi Pelanggan</h3>
                <p><strong>Nama:</strong> <?php echo htmlspecialchars($order_details['nama_pelanggan']); ?></p>
                <p><strong>Kontak:</strong> <?php echo htmlspecialchars($order_details['telepon_pelanggan']); ?></p>
                <p><strong>Alamat Kirim:</strong> <?php echo nl2br(htmlspecialchars($order_details['alamat_pengiriman'])); ?></p>
            </div>
        </div>

        <div class="detail-card" style="margin-bottom:30px;">
            <h3><i class="fas fa-boxes"></i>Item Pesanan</h3>
            <table class="item-table">
                <thead><tr><th>Produk</th><th style="text-align:center;">Jumlah</th><th style="text-align:right;">Harga</th><th style="text-align:right;">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach($items_in_order as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nama_pupuk']); ?></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($item['jumlah']); ?></td>
                        <td style="text-align:right;"><?php echo 'Rp '.number_format($item['harga_satuan']); ?></td>
                        <td style="text-align:right;"><?php echo 'Rp '.number_format($item['harga_satuan'] * $item['jumlah']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="total-summary">Total: <?php echo 'Rp '.number_format($order_details['total_harga'], 0, ',', '.'); ?></p>
        </div>
        
        <?php if ($order_details['pengiriman_id']): ?>
        <div class="detail-grid">
            <div class="detail-card">
                 <h3><i class="fas fa-shipping-fast"></i>Info Pengiriman</h3>
                 <p><strong>Sopir:</strong> <?php echo htmlspecialchars($order_details['nama_sopir_pengiriman'] ?? 'Belum Ditugaskan'); ?></p>
                 <!-- [PERUBAHAN 2] Menampilkan Nomor Kendaraan -->
                 <p><strong>No Kendaraan:</strong> <?php echo htmlspecialchars($order_details['no_kendaraan'] ?? 'Belum Ditugaskan'); ?></p>
                 <p><strong>Dikirim pada:</strong> <?php echo $order_details['tanggal_kirim'] ? date('d M Y', strtotime($order_details['tanggal_kirim'])) : 'Menunggu Jadwal'; ?></p>
                 <p><strong>Selesai pada:</strong> <?php echo $order_details['tanggal_selesai_pengiriman'] ? date('d M Y, H:i', strtotime($order_details['tanggal_selesai_pengiriman'])) : 'Belum Selesai'; ?></p>
                 <?php if($order_details['catatan_sopir']): ?>
                    <p><strong>Catatan Sopir:</strong> <?php echo nl2br(htmlspecialchars($order_details['catatan_sopir'])); ?></p>
                 <?php endif; ?>
            </div>
            <div class="detail-card">
                <?php if ($order_details['gambar_bukti_sampai']): ?>
                    <h3 class="delivery-proof"><i class="fas fa-camera"></i>Bukti Pengiriman</h3>
                    <img id="proofImage" class="delivery-proof-thumbnail" 
                         src="../uploads/bukti_pengiriman/<?php echo htmlspecialchars($order_details['gambar_bukti_sampai']); ?>" 
                         alt="Klik untuk memperbesar bukti pengiriman">
                <?php elseif ($order_details['koordinat_sopir_lat']): ?>
                    <h3><i class="fas fa-map-marker-alt"></i>Peta Lokasi</h3>
                    <div id="delivery-map"></div>
                <?php else: ?>
                    <h3><i class="fas fa-route"></i>Tracking</h3>
                    <p>Informasi lokasi dan bukti pengiriman akan tersedia saat sopir mengupdate status.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grup Tombol Aksi (tidak ada perubahan) -->
        <div class="button-group">
                <?php 
                    $role = $_SESSION['role'];
                    if ($role === 'admin' && empty($order_details['pengiriman_id']) && $order_details['status_pesanan'] === 'pending') {
                        echo '<a href="assign_delivery.php?pesanan_id='.$order_details['id'].'" class="btn" style="background-color: #f39c12;">Tugaskan Pengiriman</a>';
                    }
                    if ($role === 'admin' && $order_details['pengiriman_id'] && $overall_status !== 'selesai' && $overall_status !== 'dibatalkan') {
                        echo '<a href="edit_delivery.php?pengiriman_id='.$order_details['pengiriman_id'].'" class="btn" style="background-color: #e67e22;">Edit Pengiriman</a>';
                    }
                    if ($role === 'sopir' && $overall_status !== 'selesai' && $overall_status !== 'dibatalkan') {
                        echo '<a href="../sopir/update_delivery.php?pengiriman_id='.$order_details['pengiriman_id'].'" class="btn" style="background-color: #2980b9;">Update Status Kirim</a>';
                    }
                ?>
                 <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn" style="background-color: #6c757d;"><i class="fas fa-arrow-left"></i> Kembali</a>
                <button onclick="window.print()" class="btn" style="background-color: #16a085;">Cetak</button>
            </div>
    <!-- Modal Gambar (tidak ada perubahan) -->
    <div id="imageModal" class="image-modal">
      <span class="image-modal-close">×</span>
      <img class="image-modal-content" id="modalImg">
    </div>

    <!-- Script (tidak ada perubahan) -->
    <script>
        <?php if ($order_details && !empty($order_details['koordinat_sopir_lat']) && !empty($order_details['koordinat_sopir_long'])): ?>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                var lat = <?php echo json_encode($order_details['koordinat_sopir_lat']); ?>;
                var lng = <?php echo json_encode($order_details['koordinat_sopir_long']); ?>;
                var map = L.map('delivery-map').setView([lat, lng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                L.marker([lat, lng]).addTo(map).bindPopup('Posisi terakhir sopir.');
            } catch (e) {
                document.getElementById('delivery-map').innerHTML = "Gagal memuat peta.";
            }
        });
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function() {
            var proofImage = document.getElementById("proofImage");
            if (proofImage) {
                var modal = document.getElementById("imageModal");
                var modalImg = document.getElementById("modalImg");
                var span = document.getElementsByClassName("image-modal-close")[0];

                proofImage.onclick = function(){
                    modal.style.display = "block";
                    modalImg.src = this.src;
                }
                span.onclick = function() { 
                    modal.style.display = "none";
                }
                modal.onclick = function(event) {
                    if (event.target == modal) {
                        modal.style.display = "none";
                    }
                }
            }
        });
    </script>
</body>
</html>
<?php
close_db_connection($conn);
?>