<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}


$pesanan_id = $_GET['pesanan_id'] ?? null;
$order_data = null;
$sopir_list = [];
$message = '';
$message_type = '';


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

if (!$pesanan_id || !is_numeric($pesanan_id)) {
    $_SESSION['message'] = "ID Pesanan tidak valid.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_deliveries.php");
    exit;
} else {
    // Query untuk mengambil data pesanan tetap sama
    $stmt_order = $conn->prepare("SELECT p.id, p.tanggal_pesan, p.total_harga, p.alamat_pengiriman, p.status_pesanan, u.nama_lengkap AS nama_pelanggan, u.telepon AS telepon_pelanggan FROM pesanan p JOIN users u ON p.id_pelanggan = u.id WHERE p.id = ? AND p.status_pesanan NOT IN ('selesai', 'dibatalkan', 'diproses') LIMIT 1");
    $stmt_order->bind_param("i", $pesanan_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();

    if ($result_order->num_rows == 1) {
        $order_data = $result_order->fetch_assoc();
        // Pemeriksaan apakah pengiriman sudah ada, tetap sama
        $check_pengiriman = $conn->prepare("SELECT id FROM pengiriman WHERE id_pesanan = ?");
        $check_pengiriman->bind_param("i", $pesanan_id);
        $check_pengiriman->execute();
        if ($check_pengiriman->get_result()->num_rows > 0) {
            $_SESSION['message'] = "Pesanan ini sudah ditugaskan. Gunakan 'Edit Pengiriman' untuk mengubahnya.";
            $_SESSION['message_type'] = "info";
            header("Location: manage_deliveries.php");
            exit;
        }
        $check_pengiriman->close();
    } else {
        $_SESSION['message'] = "Pesanan tidak ditemukan atau tidak valid untuk ditugaskan.";
        $_SESSION['message_type'] = "error";
        header("Location: manage_deliveries.php");
        exit;
    }
    $stmt_order->close();

    // Query untuk mengambil daftar sopir, tetap sama
    $stmt_sopir = $conn->prepare("SELECT id, nama_lengkap FROM users WHERE role = 'sopir' ORDER BY nama_lengkap ASC");
    $stmt_sopir->execute();
    $result_sopir = $stmt_sopir->get_result();
    while ($row_sopir = $result_sopir->fetch_assoc()) {
        $sopir_list[] = $row_sopir;
    }
    $stmt_sopir->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_driver'])) {
    $selected_sopir_id = filter_var($_POST['sopir_id'], FILTER_VALIDATE_INT);
    $tanggal_kirim = $_POST['tanggal_kirim'];
    $no_kendaraan = trim($_POST['no_kendaraan']); // --- DITAMBAHKAN ---
    $status_pengiriman_awal = 'ditugaskan'; // Lebih baik 'ditugaskan' dari pada 'menunggu penugasan'

    // --- DIUBAH --- Validasi diperbarui untuk menyertakan 'no_kendaraan'
    if ($selected_sopir_id === false || empty($tanggal_kirim) || empty($no_kendaraan)) {
        $message = "Sopir, tanggal kirim, dan nomor kendaraan harus diisi.";
        $message_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            // --- DIUBAH --- Query INSERT diperbarui dengan kolom 'no_kendaraan'
            $stmt_insert = $conn->prepare("INSERT INTO pengiriman (id_pesanan, id_sopir, tanggal_kirim, no_kendaraan, status_pengiriman) VALUES (?, ?, ?, ?, ?)");
            // --- DIUBAH --- bind_param diperbarui (iiss -> iisss) dan $no_kendaraan ditambahkan
            $stmt_insert->bind_param("iisss", $pesanan_id, $selected_sopir_id, $tanggal_kirim, $no_kendaraan, $status_pengiriman_awal);
            $stmt_insert->execute();
            $stmt_insert->close();

            // Query UPDATE pesanan tetap sama
            $stmt_update = $conn->prepare("UPDATE pesanan SET status_pesanan = 'diproses' WHERE id = ?");
            $stmt_update->bind_param("i", $pesanan_id);
            $stmt_update->execute();
            $stmt_update->close();

            $conn->commit();
            $_SESSION['message'] = "Pengiriman untuk pesanan #{$pesanan_id} berhasil ditugaskan.";
            $_SESSION['message_type'] = "success";
            header("Location: manage_deliveries.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Gagal menugaskan pengiriman: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugaskan Pengiriman - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS tetap sama */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; transition: transform 0.3s ease; box-shadow: 4px 0 20px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-header h3 { color: #ecf0f1; font-size: 1.4rem; }
        .sidebar-header p { color: #bdc3c7; font-size: 0.9rem; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link:hover { background: rgba(52,152,219,0.2); transform: translateX(5px); color: #3498db; }
        .nav-link.active { background: #3498db; color: #fff; font-weight: 600; }
        .nav-link.active:hover { transform: translateX(0); }
        .nav-link i { margin-right: 15px; width: 20px; text-align: center; }
        .nav-link.logout { margin-top: 30px; background: rgba(231,76,60,0.1); border: 1px solid rgba(231,76,60,0.3); }
        .sidebar-toggle { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background: #2c3e50; color: white; border: none; padding: 12px; border-radius: 8px; }
        .main-content { margin-left: 280px; padding: 40px; transition: margin-left 0.3s ease; }
        .page-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .page-header h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; display: flex; align-items: center; }
        .page-header h1 i { margin-right: 15px; color: #f39c12; }
        .page-header p { color: #7f8c8d; font-size: 1rem; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); max-width: 700px; margin: auto; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 15px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; background: #f8f9fa; }
        .order-details-box { background: #f8f9fa; padding: 25px; border-radius: 12px; border: 1px solid #e9ecef; margin-bottom: 30px; }
        .order-details-box h3 { color: #2c3e50; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; margin-bottom: 15px; }
        .order-details-box p { line-height: 1.6; color: #555; margin-bottom: 8px; }
        .order-details-box strong { color: #34495e; }
        .button-group { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #e9ecef; padding-top: 25px; }
        .btn { padding: 15px 30px; border: none; border-radius: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; }
        .btn-primary { background: #f39c12; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
        .message.info { background-color: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <!-- Sidebar HTML tetap sama -->
        <div class="sidebar-header"><h3><i class="fas fa-user-shield"></i> Admin Panel</h3><p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link active"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-shipping-fast"></i> Tugaskan Pengiriman</h1>
            <p>Pilih sopir dan jadwalkan pengiriman untuk Pesanan <?php echo htmlspecialchars($pesanan_id); ?></p>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($order_data): ?>
                <div class="order-details-box">
                    <h3><i class="fas fa-box-open"></i> Detail Pesanan</h3>
                    <p><strong>Tanggal Pesan:</strong> <?php echo date('d M Y, H:i', strtotime($order_data['tanggal_pesan'])); ?></p>
                    <p><strong>Pelanggan:</strong> <?php echo htmlspecialchars($order_data['nama_pelanggan']); ?> (<?php echo htmlspecialchars($order_data['telepon_pelanggan']); ?>)</p>
                    <p><strong>Alamat:</strong> <?php echo nl2br(htmlspecialchars($order_data['alamat_pengiriman'])); ?></p>
                    <p><strong>Total:</strong> <span style="font-weight: bold; color: #27ae60;">Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?></span></p>
                </div>

                <form action="assign_delivery.php?pesanan_id=<?php echo htmlspecialchars($pesanan_id); ?>" method="POST">
                    <div class="form-group">
                        <label for="sopir_id"><i class="fas fa-id-card"></i> Pilih Sopir Pengirim</label>
                        <select id="sopir_id" name="sopir_id" required>
                            <option value="" disabled selected>-- Pilih salah satu sopir --</option>
                            <?php if (empty($sopir_list)): ?>
                                <option value="" disabled>Tidak ada sopir yang terdaftar</option>
                            <?php else: ?>
                                <?php foreach ($sopir_list as $sopir): ?>
                                    <option value="<?php echo htmlspecialchars($sopir['id']); ?>"><?php echo htmlspecialchars($sopir['nama_lengkap']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_kirim"><i class="fas fa-calendar-alt"></i> Tetapkan Tanggal Kirim</label>
                        <input type="date" id="tanggal_kirim" name="tanggal_kirim" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <!-- --- DITAMBAHKAN ---: Input untuk Nomor Kendaraan -->
                    <div class="form-group">
                        <label for="no_kendaraan"><i class="fas fa-car-side"></i> Nomor Kendaraan</label>
                        <input type="text" id="no_kendaraan" name="no_kendaraan" 
                               placeholder="Contoh: B 1234 ABC" 
                               value="<?php echo htmlspecialchars($_POST['no_kendaraan'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="button-group">
                        <a href="manage_deliveries.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                        <button type="submit" name="assign_driver" class="btn btn-primary"><i class="fas fa-check-circle"></i> Tugaskan</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // Script tidak perlu diubah
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
    </script>
</body>
</html>

<?php
close_db_connection($conn);
?>