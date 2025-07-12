<?php
session_start();
// Menyertakan file konfigurasi yang sudah memiliki fungsi close_db_connection()
require_once '../config/database.php';

// --- KEAMANAN AKSES HANYA UNTUK SOPIR ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sopir') {
    header("Location: ../public/login.php");
    exit;
}

// --- FUNGSI UNTUK MENUTUP KONEKSI DATABASE ---
// FUNGSI INI SUDAH DIHAPUS DARI SINI KARENA SUDAH ADA DI 'config/database.php'

// --- VALIDASI & PENGAMBILAN DATA PENGIRIMAN ---
$pengiriman_id = filter_input(INPUT_GET, 'pengiriman_id', FILTER_VALIDATE_INT);
if (!$pengiriman_id) {
    $_SESSION['message'] = "ID Pengiriman tidak valid atau tidak ditemukan.";
    $_SESSION['message_type'] = 'error';
    header("Location: my_deliveries.php");
    exit;
}

// Ambil data pengiriman yang akan diperbarui, pastikan milik sopir yang login
$stmt_get = $conn->prepare("
    SELECT peng.*, p.id_pelanggan, u.nama_lengkap AS nama_pelanggan
    FROM pengiriman peng
    JOIN pesanan p ON peng.id_pesanan = p.id
    JOIN users u ON p.id_pelanggan = u.id
    WHERE peng.id = ? AND peng.id_sopir = ?
");
$stmt_get->bind_param("ii", $pengiriman_id, $_SESSION['user_id']);
$stmt_get->execute();
$result_get = $stmt_get->get_result();

if ($result_get->num_rows === 0) {
    $_SESSION['message'] = "Data pengiriman tidak ditemukan atau Anda tidak memiliki akses.";
    $_SESSION['message_type'] = 'error';
    header("Location: my_deliveries.php");
    exit;
}
$pengiriman = $result_get->fetch_assoc();
$current_image_name = $pengiriman['gambar_bukti_sampai'];
$stmt_get->close();

// Inisialisasi variabel pesan
$message = '';
$message_type = '';

// --- PROSES POST REQUEST (SAAT FORM DISUBMIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $status = $_POST['status_pengiriman'] ?? '';
    $tanggal_kirim = !empty($_POST['tanggal_kirim']) ? $_POST['tanggal_kirim'] : null;
    $tanggal_selesai = !empty($_POST['tanggal_selesai']) ? $_POST['tanggal_selesai'] : null;
    $catatan_sopir = trim($_POST['catatan_sopir'] ?? '');
    $new_image_name = $current_image_name;

    // Ambil dan validasi koordinat
    $lat = filter_input(INPUT_POST, 'koordinat_lat', FILTER_VALIDATE_FLOAT, ['options' => ['default' => null]]);
    $lng = filter_input(INPUT_POST, 'koordinat_long', FILTER_VALIDATE_FLOAT, ['options' => ['default' => null]]);

    // Logika otomatisasi tanggal berdasarkan status
    if ($status === 'dalam perjalanan' && is_null($tanggal_kirim)) {
        $tanggal_kirim = date('Y-m-d');
    }
    if ($status === 'sudah sampai') {
        if (is_null($tanggal_kirim)) $tanggal_kirim = date('Y-m-d');
        if (is_null($tanggal_selesai)) $tanggal_selesai = date('Y-m-d');
    }

    // Validasi dasar
    if (empty($status)) {
        $message = "Status pengiriman wajib dipilih.";
        $message_type = 'error';
    } else {
        // Proses upload gambar jika status 'sudah sampai'
        if ($status === 'sudah sampai' && isset($_FILES['gambar_bukti']) && $_FILES['gambar_bukti']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../uploads/bukti_pengiriman/";
            
            // Buat direktori jika belum ada (tanpa @)
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) {
                    $message = "Gagal membuat direktori upload.";
                    $message_type = 'error';
                }
            }

            if ($message_type !== 'error') {
                $file_info = $_FILES['gambar_bukti'];
                $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_ext, $allowed_ext)) {
                    $message = "Tipe file tidak valid. Hanya JPG, JPEG, PNG, GIF yang diizinkan.";
                    $message_type = 'error';
                } elseif ($file_info['size'] > 2 * 1024 * 1024) { // Maks 2MB
                    $message = "Ukuran file terlalu besar. Maksimal 2MB.";
                    $message_type = 'error';
                } else {
                    $unique_name = 'bukti_' . uniqid() . '.' . $file_ext;
                    $target_path = $upload_dir . $unique_name;

                    if (move_uploaded_file($file_info['tmp_name'], $target_path)) {
                        // Hapus gambar lama jika ada (tanpa @)
                        if (!empty($current_image_name) && file_exists($upload_dir . $current_image_name)) {
                            unlink($upload_dir . $current_image_name);
                        }
                        $new_image_name = $unique_name;
                    } else {
                        $message = "Gagal mengunggah file gambar.";
                        $message_type = 'error';
                    }
                }
            }
        }

        // Jika tidak ada error dari validasi & upload, update ke database
        if ($message_type !== 'error') {
            $stmt_update = $conn->prepare("
                UPDATE pengiriman 
                SET status_pengiriman = ?, tanggal_kirim = ?, tanggal_selesai = ?,
                    koordinat_sopir_lat = ?, koordinat_sopir_long = ?, catatan_sopir = ?, gambar_bukti_sampai = ?, updated_at = NOW()
                WHERE id = ? AND id_sopir = ?
            ");
            $stmt_update->bind_param("sssddssii", $status, $tanggal_kirim, $tanggal_selesai, $lat, $lng, $catatan_sopir, $new_image_name, $pengiriman_id, $_SESSION['user_id']);

            if ($stmt_update->execute()) {
                $_SESSION['message'] = "Status pengiriman berhasil diperbarui.";
                $_SESSION['message_type'] = 'success';
                $stmt_update->close();
                header("Location: my_deliveries.php");
                exit;
            } else {
                $message = "Terjadi kesalahan saat menyimpan data: " . $stmt_update->error;
                $message_type = 'error';
            }
            $stmt_update->close();
        }
    }

    // Jika terjadi error, isi ulang data form dengan data yang baru diinput
    $pengiriman = array_merge($pengiriman, $_POST);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Pengiriman #<?php echo htmlspecialchars($pengiriman['id_pesanan']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* CSS GABUNGAN UNTUK LAYOUT UTAMA & HALAMAN FORM */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        /* --- Sidebar & Mobile Toggle Styles --- */
        .sidebar { width: 280px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.2); padding: 0; position: fixed; height: 100vh; overflow-y: auto; transition: all 0.3s ease; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; }
        .sidebar-header { padding: 30px 25px; background: linear-gradient(135deg, #28a745, #20c997); color: white; text-align: center; }
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
        /* --- Main Content --- */
        .main-content { flex: 1; margin-left: 280px; padding: 30px; transition: margin-left 0.3s ease; }
        /* --- Styles spesifik untuk Halaman Form --- */
        .form-container { width: 100%; background-color: rgba(255,255,255,0.95); padding: 25px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); }
        .form-container h2 { text-align: center; margin-bottom: 25px; color: #333; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        .button-group { display: flex; gap: 10px; margin-top: 20px; }
        .btn, .btn-back { padding: 12px 20px; border-radius: 5px; text-decoration: none; color: white !important; border: none; cursor: pointer; font-size: 1em; text-align: center; }
        .btn { background-color: #007bff;} .btn:hover { background-color: #0056b3;}
        .btn-back { background-color: #6c757d;} .btn-back:hover { background-color: #545b62;}
        .btn-location { background-color: #28a745; margin-bottom: 10px; display: inline-block; } .btn-location:hover { background-color: #218838; }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-box { background-color: #eaf6ff; border-left: 4px solid #007bff; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        #map { height: 350px; width: 100%; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; z-index: 1; }
        .coordinates-input { display: flex; gap: 10px; }
        #location-status { font-size: 0.9em; padding: 8px; border-radius: 4px; text-align: center; margin-top: 10px; }
        .location-loading { background-color: #fff3cd; } .location-success { background-color: #d4edda; } .location-error { background-color: #f8d7da; }
        .current-image-preview img { max-width: 150px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px; }
        #image_upload_group { display: none; padding: 15px; background-color: #f9f9f9; border: 1px dashed #ccc; border-radius: 4px; margin-top:10px; }
        /* --- Mobile Responsiveness --- */
        .mobile-toggle { display: none; position: fixed; top: 15px; left: 15px; z-index: 1001; background: #28a745; color: white; border: none; padding: 10px 12px; border-radius: 8px; cursor: pointer; font-size: 1.2rem; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        .overlay.active { display: block; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; }
            .mobile-toggle { display: block; }
            .form-container { padding: 15px; margin-top: 50px; }
            .form-container h2 { font-size: 1.5rem; }
            .coordinates-input { flex-direction: column; }
            #map { height: 250px; }
            .button-group { flex-direction: column; }
            .btn, .btn-back { width: 100%; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="overlay" onclick="toggleSidebar()"></div>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-truck"></i> Dashboard Sopir</h3>
                <div class="user-info"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></div>
            </div>
            <div class="sidebar-menu">
                <div class="menu-section">Menu Utama</div>
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard Utama</a>
                <a href="my_deliveries.php" class="active"><i class="fas fa-shipping-fast"></i> Daftar Pengiriman Saya</a>
                <div class="menu-section logout-btn-wrapper">Lainnya</div>
                <div class="logout-btn"><a href="../public/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="form-container">
                <h2>Update Pengiriman #<?php echo htmlspecialchars($pengiriman['id_pesanan']); ?></h2>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <div class="info-box"><p><strong>Pelanggan:</strong> <?php echo htmlspecialchars($pengiriman['nama_pelanggan']); ?></p></div>
                
                <form action="update_status_pengiriman.php?pengiriman_id=<?php echo $pengiriman_id; ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="status_pengiriman">Status Pengiriman</label>
                        <select id="status_pengiriman" name="status_pengiriman" required onchange="toggleFormElements()">
                            <option value="">Pilih Status</option>
                            <option value="dalam perjalanan" <?php echo ($pengiriman['status_pengiriman'] == 'dalam perjalanan') ? 'selected' : ''; ?>>Dalam Perjalanan</option>
                            <option value="sudah sampai" <?php echo ($pengiriman['status_pengiriman'] == 'sudah sampai') ? 'selected' : ''; ?>>Sudah Sampai</option>
                            <option value="bermasalah" <?php echo ($pengiriman['status_pengiriman'] == 'bermasalah') ? 'selected' : ''; ?>>Bermasalah</option>
                            <option value="dibatalkan" <?php echo ($pengiriman['status_pengiriman'] == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>

                    <div class="form-group" id="image_upload_group">
                        <label for="gambar_bukti">Upload Bukti Sampai (Opsional, Maks 2MB)</label>
                        <input type="file" id="gambar_bukti" name="gambar_bukti" accept="image/jpeg, image/png, image/gif">
                        <?php if (!empty($pengiriman['gambar_bukti_sampai'])): ?>
                            <div class="current-image-preview">
                                <p>Bukti saat ini:</p>
                                <a href="../uploads/bukti_pengiriman/<?php echo htmlspecialchars($pengiriman['gambar_bukti_sampai']); ?>" target="_blank">
                                    <img src="../uploads/bukti_pengiriman/<?php echo htmlspecialchars($pengiriman['gambar_bukti_sampai']); ?>" alt="Bukti Saat Ini">
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_kirim">Tanggal Kirim</label>
                        <input type="date" id="tanggal_kirim" name="tanggal_kirim" value="<?php echo htmlspecialchars(!empty($pengiriman['tanggal_kirim']) ? date('Y-m-d', strtotime($pengiriman['tanggal_kirim'])) : ''); ?>">
                    </div>

                    <div class="form-group" id="tanggal_selesai_group">
                        <label for="tanggal_selesai">Tanggal Sampai</label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="<?php echo htmlspecialchars(!empty($pengiriman['tanggal_selesai']) ? date('Y-m-d', strtotime($pengiriman['tanggal_selesai'])) : ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Lokasi Terkini Sopir (Opsional)</label>
                        <button type="button" id="btn-current-location" class="btn btn-location"><i class="fas fa-map-marker-alt"></i> Dapatkan Lokasi Saya</button>
                        <div id="map"></div>
                        <div class="coordinates-input">
                            <input type="number" step="any" id="koordinat_lat" name="koordinat_lat" placeholder="Latitude" value="<?php echo htmlspecialchars($pengiriman['koordinat_sopir_lat'] ?? ''); ?>">
                            <input type="number" step="any" id="koordinat_long" name="koordinat_long" placeholder="Longitude" value="<?php echo htmlspecialchars($pengiriman['koordinat_sopir_long'] ?? ''); ?>">
                        </div>
                        <div id="location-status"></div>
                    </div>

                    <div class="form-group">
                        <label for="catatan_sopir">Catatan Sopir (Opsional)</label>
                        <textarea id="catatan_sopir" name="catatan_sopir" rows="4" placeholder="Contoh: Barang diterima oleh Bpk. Adi, kondisi hujan."><?php echo htmlspecialchars($pengiriman['catatan_sopir'] ?? ''); ?></textarea>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        <a href="my_deliveries.php" class="btn-back"><i class="fas fa-times"></i> Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // --- Skrip untuk Peta & Form Dinamis ---
        let map, marker;
        const initialLat = parseFloat(<?php echo json_encode(!empty($pengiriman['koordinat_sopir_lat']) ? $pengiriman['koordinat_sopir_lat'] : -6.2088); ?>) || -6.2088;
        const initialLng = parseFloat(<?php echo json_encode(!empty($pengiriman['koordinat_sopir_long']) ? $pengiriman['koordinat_sopir_long'] : 106.8456); ?>) || 106.8456;
        const latInput = document.getElementById('koordinat_lat');
        const lngInput = document.getElementById('koordinat_long');
        const locationStatusDiv = document.getElementById('location-status');

        function initMap(lat, lng) {
            if (map) map.remove();
            map = L.map('map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            addOrUpdateMarker(lat, lng);
            map.on('click', (e) => {
                const newPos = e.latlng;
                latInput.value = newPos.lat.toFixed(6);
                lngInput.value = newPos.lng.toFixed(6);
                addOrUpdateMarker(newPos.lat, newPos.lng);
            });
        }
        function addOrUpdateMarker(lat, lng) {
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', (e) => {
                    const pos = marker.getLatLng();
                    latInput.value = pos.lat.toFixed(6);
                    lngInput.value = pos.lng.toFixed(6);
                });
            }
            map.setView([lat, lng]);
        }
        function updateLocationStatus(msg, type) {
            locationStatusDiv.textContent = msg;
            locationStatusDiv.className = ''; // Reset class
            locationStatusDiv.classList.add(`location-${type}`);
        }
        document.getElementById('btn-current-location').addEventListener('click', () => {
            if (!navigator.geolocation) {
                updateLocationStatus('Geolocation tidak didukung oleh browser Anda.', 'error');
                return;
            }
            updateLocationStatus('Mendapatkan lokasi...', 'loading');
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude: lat, longitude: lng } = position.coords;
                    latInput.value = lat.toFixed(6);
                    lngInput.value = lng.toFixed(6);
                    addOrUpdateMarker(lat, lng);
                    updateLocationStatus('Lokasi GPS berhasil didapatkan!', 'success');
                },
                () => {
                    updateLocationStatus('Gagal mendapatkan lokasi. Pastikan izin GPS aktif dan coba lagi.', 'error');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
        
        function toggleFormElements() {
            const status = document.getElementById('status_pengiriman').value;
            const today = new Date().toISOString().split('T')[0];
            const tglKirimInput = document.getElementById('tanggal_kirim');
            const tglSelesaiInput = document.getElementById('tanggal_selesai');

            // Tampilkan/sembunyikan upload gambar dan tanggal selesai
            document.getElementById('image_upload_group').style.display = (status === 'sudah sampai') ? 'block' : 'none';
            document.getElementById('tanggal_selesai_group').style.display = (status === 'sudah sampai') ? 'block' : 'none';
            
            // Otomatisasi tanggal
            if (status === 'dalam perjalanan' && !tglKirimInput.value) {
                tglKirimInput.value = today;
            }
            if (status === 'sudah sampai') {
                if (!tglKirimInput.value) tglKirimInput.value = today;
                if (!tglSelesaiInput.value) tglSelesaiInput.value = today;
            }
        }
        window.addEventListener('DOMContentLoaded', () => {
            initMap(initialLat, initialLng);
            toggleFormElements();
        });

        // --- Skrip untuk Sidebar Mobile ---
        function toggleSidebar() { if (window.innerWidth <= 768) { document.getElementById('sidebar').classList.toggle('mobile-open'); document.querySelector('.overlay').classList.toggle('active'); } }
        document.addEventListener('click', (event) => { const sidebar = document.getElementById('sidebar'), toggle = document.querySelector('.mobile-toggle'); if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !toggle.contains(event.target) && sidebar.classList.contains('mobile-open')) toggleSidebar(); });
        window.addEventListener('resize', () => { if (window.innerWidth > 768) { document.getElementById('sidebar').classList.remove('mobile-open'); document.querySelector('.overlay').classList.remove('active'); } });
    </script>
</body>
</html>
<?php 
// Panggilan ini sekarang aman karena fungsinya di-load dari database.php
close_db_connection($conn); 
?>