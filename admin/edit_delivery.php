<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa login dan peran admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$pengiriman_id = filter_var($_GET['pengiriman_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$pengiriman_id) {
    $_SESSION['message'] = "ID Pengiriman tidak valid.";
    $_SESSION['message_type'] = 'error';
    header("Location: manage_deliveries.php");
    exit;
}

// --- LOGIKA FORM (PROSES UPDATE) ---
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_sopir = filter_var($_POST['id_sopir'], FILTER_VALIDATE_INT);
    $no_kendaraan = trim($_POST['no_kendaraan']); // --- DITAMBAHKAN ---
    $status_pengiriman = $_POST['status_pengiriman'] ?? '';
    $tanggal_kirim = empty($_POST['tanggal_kirim']) ? null : $_POST['tanggal_kirim'];
    $tanggal_selesai = empty($_POST['tanggal_selesai']) ? null : $_POST['tanggal_selesai'];
    $koordinat_lat = filter_var($_POST['koordinat_lat'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $koordinat_long = filter_var($_POST['koordinat_long'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $catatan_sopir = trim($_POST['catatan_sopir']) ?? null;

    // --- DIUBAH --- Validasi diperbarui untuk menyertakan 'no_kendaraan'
    if (empty($status_pengiriman) || $id_sopir === false || empty($no_kendaraan)) {
        $message = "Sopir, Nomor Kendaraan, dan Status Pengiriman harus diisi.";
        $message_type = 'error';
    } else {
        // --- DIUBAH --- Query UPDATE diperbarui dengan kolom 'no_kendaraan'
        $stmt_update = $conn->prepare("UPDATE pengiriman SET id_sopir = ?, no_kendaraan = ?, status_pengiriman = ?, tanggal_kirim = ?, tanggal_selesai = ?, koordinat_sopir_lat = ?, koordinat_sopir_long = ?, catatan_sopir = ? WHERE id = ?");
        // --- DIUBAH --- bind_param diperbarui (isssddsi -> issssddsi) dan $no_kendaraan ditambahkan
        $stmt_update->bind_param("issssddsi", $id_sopir, $no_kendaraan, $status_pengiriman, $tanggal_kirim, $tanggal_selesai, $koordinat_lat, $koordinat_long, $catatan_sopir, $pengiriman_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['message'] = "Data pengiriman berhasil diperbarui.";
            $_SESSION['message_type'] = 'success';
            header("Location: manage_deliveries.php");
            exit;
        } else {
            $message = "Gagal memperbarui data: " . $stmt_update->error;
            $message_type = 'error';
        }
        $stmt_update->close();
    }
}

// --- AMBIL DATA UNTUK DITAMPILKAN ---
// --- DIUBAH --- Menambahkan `no_kendaraan` ke SELECT, `peng.*` sudah mencakup ini jika DB diubah.
$stmt_data = $conn->prepare("SELECT peng.*, u_sopir.nama_lengkap AS nama_sopir FROM pengiriman peng LEFT JOIN users u_sopir ON peng.id_sopir = u_sopir.id WHERE peng.id = ?");
$stmt_data->bind_param("i", $pengiriman_id);
$stmt_data->execute();
$pengiriman = $stmt_data->get_result()->fetch_assoc();
$stmt_data->close();

if (!$pengiriman) {
    $_SESSION['message'] = "Data pengiriman tidak ditemukan.";
    $_SESSION['message_type'] = 'error';
    header("Location: manage_deliveries.php");
    exit;
}

// Ambil daftar sopir (tidak berubah)
$drivers = $conn->query("SELECT id, nama_lengkap FROM users WHERE role = 'sopir' ORDER BY nama_lengkap ASC")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengiriman #<?php echo $pengiriman_id; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* CSS tetap sama */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; transition: transform 0.3s ease; }
        .main-content { margin-left: 280px; padding: 40px; }
        .page-header { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; }
        .form-container { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 10px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 15px; border: 2px solid #e9ecef; border-radius: 12px; background: #f8f9fa; }
        .button-group { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #e9ecef; padding-top: 25px; }
        .btn { padding: 15px 30px; border: none; border-radius: 12px; font-weight: 600; text-decoration: none; cursor: pointer; }
        #map { height: 350px; width: 100%; border-radius: 12px; margin-top:10px; }
        /* CSS yang lebih spesifik untuk halaman ini, agar tidak ter-override */
        .sidebar-header h3 { font-size: 1.4rem; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link.active { background: #3498db; font-weight: 600; }
        .nav-link:hover:not(.active) { background: rgba(52,152,219,0.2); }
        .nav-link i { margin-right: 15px; }
        .page-header h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; display:flex; align-items:center; }
        .page-header h1 i { margin-right:15px; color:#e67e22; }
        .btn i { margin-right: 8px; }
        .btn-primary { background: #e67e22; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; }
        .message.error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="sidebar">
        <!-- Sidebar HTML tetap sama -->
        <div class="sidebar-header"><h3><i class="fas fa-user-shield"></i> Admin Panel</h3></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link"><i class="fas fa-users-cog"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link active"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-edit"></i> Edit Pengiriman</h1>
            <p>Memperbarui data untuk <?php echo $pengiriman_id; ?> Pesanan <?php echo htmlspecialchars($pengiriman['id_pesanan']); ?></p>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="edit_delivery.php?pengiriman_id=<?php echo $pengiriman_id; ?>" method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_sopir">Sopir Ditugaskan</label>
                        <select id="id_sopir" name="id_sopir" required>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>" <?php echo ($driver['id'] == $pengiriman['id_sopir']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($driver['nama_lengkap']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- --- DITAMBAHKAN ---: Input untuk Nomor Kendaraan -->
                    <div class="form-group">
                        <label for="no_kendaraan">Nomor Kendaraan</label>
                        <input type="text" id="no_kendaraan" name="no_kendaraan" 
                               value="<?php echo htmlspecialchars($_POST['no_kendaraan'] ?? $pengiriman['no_kendaraan']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_pengiriman">Status Pengiriman</label>
                        <select id="status_pengiriman" name="status_pengiriman" required>
                            <option value="menunggu penugasan" <?php if($pengiriman['status_pengiriman'] == 'menunggu penugasan') echo 'selected'; ?>>Menunggu Penugasan</option>
                            <option value="dalam perjalanan" <?php if($pengiriman['status_pengiriman'] == 'dalam perjalanan') echo 'selected'; ?>>Dalam Perjalanan</option>
                            <option value="sudah sampai" <?php if($pengiriman['status_pengiriman'] == 'sudah sampai') echo 'selected'; ?>>Sudah Sampai</option>
                            <option value="bermasalah" <?php if($pengiriman['status_pengiriman'] == 'bermasalah') echo 'selected'; ?>>Bermasalah</option>
                            <option value="dibatalkan" <?php if($pengiriman['status_pengiriman'] == 'dibatalkan') echo 'selected'; ?>>Dibatalkan</option>
                            <option value="selesai" <?php if($pengiriman['status_pengiriman'] == 'selesai') echo 'selected'; ?>>Selesai</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_kirim">Tanggal Kirim</label>
                        <input type="date" id="tanggal_kirim" name="tanggal_kirim" value="<?php echo htmlspecialchars($pengiriman['tanggal_kirim'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="tanggal_selesai">Tanggal Selesai</label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="<?php echo htmlspecialchars($pengiriman['tanggal_selesai'] ?? ''); ?>">
                    </div>

                    <!-- Pindah Tanggal Selesai di atas agar grid lebih rapi -->

                    <div class="form-group full-width">
                        <label for="catatan_sopir">Catatan (Opsional)</label>
                        <textarea id="catatan_sopir" name="catatan_sopir" rows="4"><?php echo htmlspecialchars($pengiriman['catatan_sopir'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Pilih Lokasi di Peta (Geser pin jika perlu)</label>
                         <div id="map"></div>
                         <input type="hidden" id="koordinat_lat" name="koordinat_lat" value="<?php echo htmlspecialchars($pengiriman['koordinat_sopir_lat'] ?? ''); ?>">
                         <input type="hidden" id="koordinat_long" name="koordinat_long" value="<?php echo htmlspecialchars($pengiriman['koordinat_sopir_long'] ?? ''); ?>">
                    </div>
                </div>

                <div class="button-group">
                    <a href="manage_deliveries.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Javascript untuk map tidak perlu diubah
    document.addEventListener('DOMContentLoaded', function() {
        // ... (kode JS Anda untuk Leaflet map tetap sama)
    });
    </script>
</body>
</html>
<?php
if(isset($conn)) {
    close_db_connection($conn);
}
?>