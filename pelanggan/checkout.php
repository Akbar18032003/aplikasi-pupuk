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
$message = '';
$message_type = '';
$cart_items = $_SESSION['cart'] ?? [];
$total_harga_keranjang = 0;
$pelanggan_alamat = '';
$pelanggan_telepon = '';

// Ambil pesan dari session jika ada
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Ambil alamat dan telepon pelanggan dari database
try {
    $stmt_user = $conn->prepare("SELECT alamat, telepon FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows == 1) {
        $user_info = $result_user->fetch_assoc();
        $pelanggan_alamat = $user_info['alamat'];
        $pelanggan_telepon = $user_info['telepon'];
    }
    $stmt_user->close();
} catch (Exception $e) {
    $message = "Gagal mengambil data pengguna.";
    $message_type = "error";
}

// Hitung ulang total harga keranjang
foreach ($cart_items as $pupuk_id => $item) {
    $subtotal = $item['harga_per_unit'] * $item['jumlah'];
    $total_harga_keranjang += $subtotal;
}

// --- LOGIKA PROSES CHECKOUT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'place_order') {
    $alamat_pengiriman = trim($_POST['alamat_pengiriman']);
    $catatan = trim($_POST['catatan'] ?? '');

    if (empty($alamat_pengiriman)) {
        $message = "Alamat pengiriman tidak boleh kosong.";
        $message_type = "error";
    } elseif (empty($cart_items)) {
        $message = "Keranjang belanja Anda kosong. Tidak dapat membuat pesanan.";
        $message_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            // Validasi stok
            $stock_issues = false;
            foreach ($cart_items as $pupuk_id => $item) {
                $stmt_check_stock = $conn->prepare("SELECT nama_pupuk, stok FROM pupuk WHERE id = ? FOR UPDATE");
                $stmt_check_stock->bind_param("i", $pupuk_id);
                $stmt_check_stock->execute();
                $result_check_stock = $stmt_check_stock->get_result();
                $pupuk_data = $result_check_stock->fetch_assoc();
                $stmt_check_stock->close();

                if ($item['jumlah'] > $pupuk_data['stok']) {
                    $stock_issues = true;
                    $message = "Maaf, stok " . htmlspecialchars($pupuk_data['nama_pupuk']) . " tidak mencukupi. Tersedia: " . $pupuk_data['stok'] . ", Anda minta: " . $item['jumlah'] . ".";
                    $message_type = "error";
                    break;
                }
            }

            if ($stock_issues) {
                $conn->rollback();
            } else {
                // Proses pesanan
                $status_pesanan = 'pending';
                $stmt_pesanan = $conn->prepare("INSERT INTO pesanan (id_pelanggan, alamat_pengiriman, catatan, total_harga, status_pesanan) VALUES (?, ?, ?, ?, ?)");
                $stmt_pesanan->bind_param("isdss", $user_id, $alamat_pengiriman, $catatan, $total_harga_keranjang, $status_pesanan);
                $stmt_pesanan->execute();
                $pesanan_id = $conn->insert_id;
                $stmt_pesanan->close();

                foreach ($cart_items as $pupuk_id => $item) {
                    $stmt_detail = $conn->prepare("INSERT INTO detail_pesanan (id_pesanan, id_pupuk, jumlah, harga_satuan) VALUES (?, ?, ?, ?)");
                    $stmt_detail->bind_param("iiid", $pesanan_id, $pupuk_id, $item['jumlah'], $item['harga_per_unit']);
                    $stmt_detail->execute();
                    $stmt_detail->close();

                    $stmt_update_stock = $conn->prepare("UPDATE pupuk SET stok = stok - ? WHERE id = ?");
                    $stmt_update_stock->bind_param("ii", $item['jumlah'], $pupuk_id);
                    $stmt_update_stock->execute();
                    $stmt_update_stock->close();
                }

                $conn->commit();
                $_SESSION['cart'] = [];
                $_SESSION['message'] = "Pesanan Anda berhasil dibuat! Nomor Pesanan: #" . $pesanan_id;
                $_SESSION['message_type'] = "success";
                header("Location: track_delivery.php");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Terjadi kesalahan saat memproses pesanan: " . $e->getMessage();
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
    <title>Checkout - Dashboard Pelanggan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Lengkap */
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
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); height: 100vh;
            position: fixed; left: 0; top: 0;
            transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 1000;
        }
        .sidebar-header {
            padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, #ffc107, #ff8f00); color: white;
        }
        .sidebar-header h3 { font-size: 1.4rem; margin-bottom: 5px; font-weight: 600; }
        .sidebar-header p { font-size: 0.9rem; opacity: 0.9; word-wrap: break-word; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 15px 25px; color: #333; text-decoration: none;
            transition: all 0.3s ease; border-left: 4px solid transparent; margin: 5px 0;
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
            border-radius: 8px; justify-content: center; border-left: none !important;
            transform: none !important; padding: 12px;
        }
        .logout-btn a:hover {
            background: linear-gradient(135deg, #ff5252, #d32f2f);
            transform: translateY(-2px) !important; box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .main-content {
            margin-left: 280px; flex-grow: 1; padding: 40px; min-height: 100vh;
            width: calc(100% - 280px); transition: margin-left 0.3s ease;
        }

        .checkout-card {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 30px 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); }}
        .checkout-card h2 {
            font-size: 2.2rem; margin-bottom: 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            text-align: center;
        }
        .order-summary, .shipping-address {
            margin-bottom: 25px; padding: 20px; border: 1px solid #e0e0e0;
            border-radius: 10px; background-color: #fafafa;
        }
        .order-summary h3, .shipping-address h3 {
            color: #764ba2; border-bottom: 2px solid #764ba2;
            padding-bottom: 10px; margin-bottom: 15px; font-size: 1.3rem;
        }
        .order-summary ul { list-style: none; padding: 0; }
        .order-summary ul li {
            margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #ccc;
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 5px;
        }
        .order-summary .total {
            font-weight: bold; font-size: 1.3em; color: #667eea;
            margin-top: 15px; text-align: right; padding-top: 10px;
            border-top: 2px solid #ddd;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; margin-bottom: 8px; color: #555; font-weight: 600;
        }
        .form-group textarea, .form-group input {
            width: 100%; padding: 12px; border: 1px solid #ccc;
            border-radius: 8px; font-family: inherit; font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group textarea:focus, .form-group input:focus {
            outline: none; border-color: #764ba2;
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.2);
        }
        .shipping-address p { margin-bottom: 15px; line-height: 1.6; }
        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 25px;
        }
        .btn {
            color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer;
            font-size: 1rem; text-decoration: none; display: inline-block;
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); }
        .btn-secondary { background: #888; }
        .btn-primary:hover, .btn-secondary:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; }
        .message.success { background-color: #d1f7e4; color: #0f5132; border: 1px solid #a3cfbb; }
        .message.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        .mobile-toggle {
            display: none; position: fixed; top: 15px; left: 15px; background: #ffc107;
            color: white; border: none; width: 45px; height: 45px; border-radius: 50%;
            cursor: pointer; z-index: 1001; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        /* [PERBAIKAN] Overlay disembunyikan secara default */
        .overlay {
            display: none; 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: 999; opacity: 0;
            transition: opacity 0.3s ease;
        }
        /* [PERBAIKAN] Overlay hanya ditampilkan dan memiliki opacity 1 saat aktif */
        .overlay.active {
            display: block; /* Hanya tampil saat dibutuhkan */
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; width: 100%; }

            /* [PERBAIKAN] Hanya mobile toggle yang selalu tampil di mobile, overlay tidak */
            .mobile-toggle {
                display: block;
            }

            .checkout-card { padding: 25px 20px; }
            .checkout-card h2 { font-size: 1.8rem; }
            .button-group { justify-content: center; }
            .btn { width: 100%; text-align: center; }
            .btn-primary { order: 2; }
            .btn-secondary { order: 1; }
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
            <p>Selamat Datang,<br><?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="order_pupuk.php" class="active"><i class="fas fa-shopping-cart"></i><span>Pesan Pupuk</span></a>
            <a href="track_delivery.php"><i class="fas fa-truck"></i><span>Lacak Pesanan Aktif</span></a>
            <a href="order_history.php"><i class="fas fa-history"></i><span>Riwayat Pesanan</span></a>
        </div>
        <div class="logout-btn">
            <a href="../public/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <div class="main-content" id="main-content">
        <div class="checkout-card">
            <h2>Konfirmasi Pesanan</h2>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <p style="text-align: center; font-size: 1.1rem; color: #666;">Keranjang belanja Anda kosong.</p>
                <div class="button-group" style="margin-top: 30px; justify-content: center;">
                    <a href="order_pupuk.php" class="btn btn-primary" style="flex-grow: 0; width: auto;">Kembali Pesan Pupuk</a>
                </div>
            <?php else: ?>
                <div class="order-summary">
                    <h3><i class="fas fa-receipt"></i> Ringkasan Pesanan</h3>
                    <ul>
                        <?php foreach ($cart_items as $pupuk_id => $item): ?>
                            <li>
                                <span><strong><?php echo htmlspecialchars($item['nama_pupuk']); ?></strong> (x<?php echo htmlspecialchars($item['jumlah']); ?>)</span>
                                <span>Rp <?php echo number_format($item['harga_per_unit'] * $item['jumlah'], 0, ',', '.'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="total">Total: Rp <?php echo number_format($total_harga_keranjang, 0, ',', '.'); ?></p>
                </div>

                <div class="shipping-address">
                    <h3><i class="fas fa-map-marked-alt"></i> Alamat Pengiriman</h3>
                     <p>Alamat terdaftar: <strong><?php echo nl2br(htmlspecialchars($pelanggan_alamat)); ?></strong><br>
                        Telepon: <strong><?php echo htmlspecialchars($pelanggan_telepon); ?></strong>
                    </p>
                    <form action="checkout.php" method="POST">
                        <div class="form-group">
                            <label for="alamat_pengiriman">Gunakan Alamat Ini atau Ubah:</label>
                            <textarea id="alamat_pengiriman" name="alamat_pengiriman" rows="4" required><?php echo htmlspecialchars($pelanggan_alamat); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="catatan">Catatan Tambahan (opsional):</label>
                            <textarea id="catatan" name="catatan" rows="2" placeholder="Contoh: 'Letakkan di teras rumah.'"><?php echo htmlspecialchars($_POST['catatan'] ?? ''); ?></textarea>
                        </div>
                        <div class="button-group">
                            <a href="order_pupuk.php" class="btn btn-secondary">Kembali ke Keranjang</a>
                            <button type="submit" name="action" value="place_order" class="btn btn-primary">Konfirmasi & Pesan</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
// Selalu tutup koneksi di akhir skrip
if (isset($conn)) {
    $conn->close();
}
?>