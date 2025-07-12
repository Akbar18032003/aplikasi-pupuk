<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa apakah pengguna sudah login ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

// (Seluruh blok logika PHP Anda dari awal hingga akhir tetap sama persis seperti yang Anda berikan)
$pesanan_id = $_GET['id'] ?? null;
$order_details = null;
$items_in_order = [];
$message = '';
$message_type = '';

if (!$pesanan_id || !is_numeric($pesanan_id)) {
    // Penanganan error dan redirect
    $_SESSION['message'] = "ID Pesanan tidak valid.";
    $_SESSION['message_type'] = "error";
    $redirect_url = '../public/login.php'; // Default redirect
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') $redirect_url = '../admin/manage_orders.php';
        if ($_SESSION['role'] === 'pelanggan') $redirect_url = '../pelanggan/track_delivery.php';
        if ($_SESSION['role'] === 'sopir') $redirect_url = 'my_deliveries.php';
    }
    header("Location: $redirect_url");
    exit;
}

// Ambil detail pesanan utama
$stmt_order = $conn->prepare("
    SELECT
        p.id, p.tanggal_pesan, p.total_harga, p.alamat_pengiriman, p.catatan, p.status_pesanan,
        u.id AS id_pelanggan_owner, u.nama_lengkap AS nama_pelanggan,
        u.telepon AS telepon_pelanggan, u.email AS email_pelanggan,
        peng.status_pengiriman, peng.gambar_bukti_sampai
    FROM pesanan p
    JOIN users u ON p.id_pelanggan = u.id
    LEFT JOIN pengiriman peng ON peng.id_pesanan = p.id
    WHERE p.id = ?
");
$stmt_order->bind_param("i", $pesanan_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();

if ($result_order->num_rows == 1) {
    $order_details = $result_order->fetch_assoc();

    // --- KEAMANAN: Periksa otorisasi ---
    $can_view = false;
    if ($_SESSION['role'] === 'admin' || ($_SESSION['role'] === 'pelanggan' && $order_details['id_pelanggan_owner'] == $_SESSION['user_id'])) {
        $can_view = true;
    } elseif ($_SESSION['role'] === 'sopir') {
        $stmt_check_sopir = $conn->prepare("SELECT id FROM pengiriman WHERE id_pesanan = ? AND id_sopir = ?");
        $stmt_check_sopir->bind_param("ii", $pesanan_id, $_SESSION['user_id']);
        $stmt_check_sopir->execute();
        if ($stmt_check_sopir->get_result()->num_rows > 0) $can_view = true;
        $stmt_check_sopir->close();
    }

    if (!$can_view) {
        $_SESSION['message'] = "Anda tidak diizinkan melihat detail pesanan ini.";
        $_SESSION['message_type'] = "error";
        $redirect_url = '../public/login.php'; // Default
        if (isset($_SESSION['role'])) {
            if ($_SESSION['role'] === 'pelanggan') $redirect_url = '../pelanggan/track_delivery.php';
            if ($_SESSION['role'] === 'sopir') $redirect_url = 'my_deliveries.php';
            if ($_SESSION['role'] === 'admin') $redirect_url = '../admin/manage_orders.php';
        }
        header("Location: $redirect_url");
        exit;
    }
    // --- AKHIR KEAMANAN OTORISASI ---

    // Ambil detail pupuk dalam pesanan ini
    $stmt_items = $conn->prepare("
        SELECT dp.jumlah, dp.harga_satuan, pu.nama_pupuk, pu.jenis_pupuk
        FROM detail_pesanan dp JOIN pupuk pu ON dp.id_pupuk = pu.id
        WHERE dp.id_pesanan = ?
    ");
    $stmt_items->bind_param("i", $pesanan_id);
    $stmt_items->execute();
    $items_in_order = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

} else {
    $_SESSION['message'] = "Pesanan tidak ditemukan.";
    $_SESSION['message_type'] = "error";
    // Redirect
    $redirect_url = '../public/login.php'; // Default
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') $redirect_url = '../admin/manage_orders.php';
        if ($_SESSION['role'] === 'pelanggan') $redirect_url = '../pelanggan/track_delivery.php';
        if ($_SESSION['role'] === 'sopir') $redirect_url = 'my_deliveries.php';
    }
    header("Location: $redirect_url");
    exit;
}
$stmt_order->close();

if (empty($message) && isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

function getDisplayStatusForDetail($status_pesanan, $status_pengiriman) {
    if ($status_pesanan === 'selesai') return 'selesai';
    return !empty($status_pengiriman) ? $status_pengiriman : $status_pesanan;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo htmlspecialchars($pesanan_id ?? 'N/A'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Asli Anda */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.2); padding: 0; position: fixed; height: 100vh; overflow-y: auto; transition: all 0.3s ease; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; }
        .sidebar-header { padding: 30px 25px; background: linear-gradient(135deg, #28a745, #20c997); color: white; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-header h3 { font-size: 1.4rem; font-weight: 600; margin-bottom: 8px; }
        .sidebar-header .user-info { font-size: 0.9rem; opacity: 0.9; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a { display: flex; align-items: center; padding: 18px 25px; color: #555; text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; font-weight: 500; gap: 15px; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: linear-gradient(90deg, rgba(40, 167, 69, 0.1), transparent); color: #28a745; border-left-color: #28a745; transform: translateX(5px); }
        .sidebar-menu a i { width: 20px; text-align: center; font-size: 1.1rem; }
        .sidebar-menu .menu-section { padding: 15px 25px 8px; font-size: 0.8rem; color: #888; text-transform: uppercase; font-weight: 600; }
        .logout-btn-wrapper { margin-top: 20px; }
        .logout-btn { border-top: 1px solid rgba(0, 0, 0, 0.1); padding-top: 20px; }
        .logout-btn a { color: #dc3545 !important; }
        .logout-btn a:hover { background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), transparent); border-left-color: #dc3545; }
        .main-content { flex: 1; margin-left: 280px; padding: 30px; transition: margin-left 0.3s ease; }
        .container-detail { width: 100%; background-color: rgba(255,255,255,0.95); padding: 25px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); }
        .container-detail h2 { text-align: center; margin-bottom: 25px; color: #2c3e50; }
        .order-info-section, .delivery-proof-section { margin-bottom: 20px; padding: 20px; border: 1px solid #ecf0f1; border-radius: 8px; background-color: #fdfefe; }
        .order-info-section h3, .delivery-proof-section h3 { color: #2980b9; margin-top: 0; margin-bottom: 15px; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px; }
        .order-info-section p, .delivery-proof-section p { margin-bottom: 10px; line-height: 1.6; color: #34495e; }
        .order-info-section p strong { color: #2c3e50; }
        .order-items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .order-items-table th, .order-items-table td { border: 1px solid #bdc3c7; padding: 10px 12px; text-align: left; vertical-align: middle; }
        .order-items-table th { background-color: #e9ecef; color: #495057; font-weight: 600; }
        .total-summary { text-align: right; margin-top: 15px; font-size: 1.2em; font-weight: bold; color: #27ae60; }
        .button-group { text-align: right; margin-top: 25px; border-top: 1px solid #ecf0f1; padding-top: 20px; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
        .button-group a, .button-group button { background-color: #3498db; color: white !important; padding: 10px 18px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; font-size: 0.95em; transition: opacity 0.3s; }
        .button-group a.btn-back, .button-group button.btn-back { background-color: #7f8c8d; }
        .button-group a:hover, .button-group button:hover { opacity: 0.85; }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-badge-detail { display: inline-block; padding: .35em .65em; font-size: .85em; font-weight: 700; line-height: 1; color: #fff; text-align: center; border-radius: .25rem; text-transform: capitalize; }
        .status-badge-detail.pending, .status-badge-detail.diproses { background-color: #17a2b8; }
        .status-badge-detail.menunggu_penugasan { background-color: #6c757d; }
        .status-badge-detail.dalam_perjalanan { background-color: #007bff; }
        .status-badge-detail.sudah_sampai { background-color: #28a745; }
        .status-badge-detail.selesai { background-color: #6610f2; }
        .status-badge-detail.bermasalah { background-color: #dc3545; }
        .status-badge-detail.dibatalkan { background-color: #343a40; }
        .mobile-toggle { display: none; position: fixed; top: 15px; left: 15px; z-index: 1001; background: #28a745; color: white; border: none; padding: 10px 12px; border-radius: 8px; cursor: pointer; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        .overlay.active { display: block; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.mobile-open { transform: translateX(0); } .main-content { margin-left: 0; padding: 15px; } .mobile-toggle { display: block; } .container-detail { padding: 15px; margin-top: 50px; } .container-detail h2 { font-size: 1.5rem; } .order-items-table { font-size: 12px; } .order-items-table th, .order-items-table td { padding: 8px 6px; } .button-group { justify-content: center; } }
        
        /* --- BLOK CSS BARU YANG DITAMBAHKAN --- */
        .delivery-proof-section img#proofImage {
            width: 150px;       /* Thumbnail menjadi kecil */
            height: 100px;      /* Thumbnail menjadi kecil */
            object-fit: cover;  /* Mencegah gambar menjadi gepeng */
            cursor: pointer;    /* Menandakan bisa di-klik */
            transition: transform 0.2s; /* Efek transisi halus */
        }
        .delivery-proof-section img#proofImage:hover {
            transform: scale(1.05); /* Sedikit membesar saat disentuh mouse */
        }
        .modal {
            display: none; position: fixed; z-index: 1001; /* di atas segalanya */
            left: 0; top: 0; width: 100%; height: 100%; overflow: auto;
            background-color: rgba(0,0,0,0.9);
            justify-content: center; align-items: center; /* Posisi gambar ditengah */
        }
        .modal-content {
            margin: auto; display: block; max-width: 90%; max-height: 90vh;
        }
        .modal-close {
            position: absolute; top: 15px; right: 35px; color: #f1f1f1;
            font-size: 40px; font-weight: bold; cursor: pointer;
        }
        /* --- AKHIR BLOK CSS BARU --- */

    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="overlay" onclick="toggleSidebar()"></div>
    <div class="dashboard-wrapper">
        <div class="sidebar" id="sidebar">
            <!-- (Isi Sidebar Anda dari kode asli tidak diubah) -->
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
            <div class="container-detail">
                <h2>Detail Pesanan <?php echo htmlspecialchars($pesanan_id ?? 'N/A'); ?></h2>
                
                <?php if ($order_details): ?>
                    <!-- (Struktur detail lainnya tetap sama seperti kode asli Anda) -->
                    <div class="order-info-section">
                        <h3>Informasi Umum</h3>
                        <p>ID Pesanan: <strong><?php echo htmlspecialchars($order_details['id']); ?></strong></p>
                        <p>Tanggal Pesan: <strong><?php echo date('d F Y, H:i', strtotime($order_details['tanggal_pesan'])); ?></strong></p>
                        <?php
                            $displayed_status = getDisplayStatusForDetail($order_details['status_pesanan'], $order_details['status_pengiriman']);
                            $status_class = strtolower(str_replace(' ', '_', $displayed_status));
                        ?>
                        <p>Status: <span class="status-badge-detail <?php echo htmlspecialchars($status_class); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $displayed_status))); ?></span></p>
                    </div>

                    <div class="order-info-section">
                        <h3>Informasi Pelanggan & Pengiriman</h3>
                        <p>Nama: <strong><?php echo htmlspecialchars($order_details['nama_pelanggan']); ?></strong></p>
                        <p>Telepon: <strong><?php echo htmlspecialchars($order_details['telepon_pelanggan']); ?></strong></p>
                        <p>Alamat Pengiriman: <br><strong><?php echo nl2br(htmlspecialchars($order_details['alamat_pengiriman'])); ?></strong></p>
                        <?php if (!empty($order_details['catatan'])): ?>
                            <p>Catatan: <br><strong><?php echo nl2br(htmlspecialchars($order_details['catatan'])); ?></strong></p>
                        <?php endif; ?>
                    </div>

                    <h3>Rincian Barang</h3>
                    <div style="overflow-x: auto;">
                        <table class="order-items-table">
                            <thead>
                                <tr><th>Nama Pupuk</th><th>Jenis</th><th style="text-align:right;">Jumlah</th><th style="text-align:right;">Harga Satuan</th><th style="text-align:right;">Subtotal</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_in_order as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nama_pupuk']); ?></td>
                                        <td><?php echo htmlspecialchars($item['jenis_pupuk']); ?></td>
                                        <td style="text-align:right;"><?php echo htmlspecialchars($item['jumlah']); ?></td>
                                        <td style="text-align:right;">Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></td>
                                        <td style="text-align:right;">Rp <?php echo number_format($item['jumlah'] * $item['harga_satuan'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="total-summary">Total Harga Pesanan: Rp <?php echo number_format($order_details['total_harga'], 0, ',', '.'); ?></p>

                    <?php if (!empty($order_details['gambar_bukti_sampai'])): ?>
                    <div class="delivery-proof-section">
                        <h3>Bukti Pengiriman Sampai</h3>
                        <!-- --- GAMBAR ASLI DIMODIFIKASI UNTUK FITUR MODAL --- -->
                        <img id="proofImage" src="../uploads/bukti_pengiriman/<?php echo htmlspecialchars($order_details['gambar_bukti_sampai']); ?>" alt="Klik untuk memperbesar bukti pengiriman">
                    </div>
                    <?php endif; ?>

                    <div class="button-group">
                        <a href="my_deliveries.php" class="btn-back">Kembali</a>
                        <button onclick="window.print();">Cetak Detail</button>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- --- HTML BARU YANG DITAMBAHKAN UNTUK MODAL --- -->
    <div id="imageModal" class="modal">
        <span class="modal-close">×</span>
        <img class="modal-content" id="modalImage">
    </div>
    
    <script>
        // Script sidebar asli Anda
        function toggleSidebar() { if (window.innerWidth <= 768) { document.getElementById('sidebar').classList.toggle('mobile-open'); document.querySelector('.overlay').classList.toggle('active'); } }
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !toggle.contains(event.target) && sidebar.classList.contains('mobile-open')) {
                toggleSidebar();
            }
        });
        window.addEventListener('resize', function() { if (window.innerWidth > 768) { document.getElementById('sidebar').classList.remove('mobile-open'); document.querySelector('.overlay').classList.remove('active'); } });

        // --- JAVASCRIPT BARU YANG DITAMBAHKAN ---
        document.addEventListener('DOMContentLoaded', function() {
            // Ambil semua elemen yang diperlukan untuk modal
            var modal = document.getElementById("imageModal");
            var img = document.getElementById("proofImage"); // Menargetkan gambar thumbnail
            var modalImg = document.getElementById("modalImage"); // Gambar besar di dalam modal
            var span = document.getElementsByClassName("modal-close")[0]; // Tombol close

            // Pastikan script hanya berjalan jika gambar bukti ada di halaman
            if (img) {
                // Saat thumbnail diklik, tampilkan modal
                img.onclick = function(){
                    modal.style.display = "flex"; // Gunakan flex untuk positioning tengah
                    modalImg.src = this.src; // Salin URL gambar ke modal
                }
            }
            
            // Saat tombol close (x) diklik, sembunyikan modal
            if(span) {
                span.onclick = function() {
                    modal.style.display = "none";
                }
            }

            // Saat area abu-abu di luar gambar diklik, sembunyikan modal
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }

            // Saat tombol Escape ditekan, sembunyikan modal
            window.addEventListener('keydown', function(event) {
                if(event.key === 'Escape' || event.key === 'Esc') {
                    if (modal.style.display === "flex") {
                        modal.style.display = "none";
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } ?>