<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN & INISIALISASI ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header("Location: ../public/login.php");
    exit;
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
// --- AKHIR KEAMANAN & INISIALISASI ---

// Variabel untuk pesan dan data
$pupuks = [];
$message = '';
$message_type = '';
$total_harga_keranjang = 0;

// Ambil pesan dari session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// --- LOGIKA FORM ACTIONS (Menambah, Menghapus, Mengosongkan Keranjang) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'add_to_cart') {
            $pupuk_id = filter_var($_POST['pupuk_id'], FILTER_VALIDATE_INT);
            $jumlah = filter_var($_POST['jumlah'], FILTER_VALIDATE_INT);
            if (!$pupuk_id || !$jumlah || $jumlah <= 0) throw new Exception("Input tidak valid.");
            
            // DIUBAH: Tambahkan 'kemasan' pada query
            $stmt_pupuk = $conn->prepare("SELECT nama_pupuk, harga_per_unit, stok, kemasan FROM pupuk WHERE id = ?");
            $stmt_pupuk->bind_param("i", $pupuk_id);
            $stmt_pupuk->execute();
            $result_pupuk = $stmt_pupuk->get_result();
            if ($result_pupuk->num_rows != 1) throw new Exception("Pupuk tidak ditemukan.");
            
            $pupuk_item = $result_pupuk->fetch_assoc();
            $current_in_cart = $_SESSION['cart'][$pupuk_id]['jumlah'] ?? 0;
            if (($current_in_cart + $jumlah) > $pupuk_item['stok']) throw new Exception("Jumlah melebihi stok yang tersedia (".$pupuk_item['stok']." unit).");

            // DIUBAH: Simpan 'kemasan' ke dalam session keranjang
            $_SESSION['cart'][$pupuk_id] = [
                'nama_pupuk' => $pupuk_item['nama_pupuk'],
                'harga_per_unit' => $pupuk_item['harga_per_unit'],
                'kemasan' => $pupuk_item['kemasan'], // BARU
                'jumlah' => $current_in_cart + $jumlah
            ];
            $_SESSION['message'] = $pupuk_item['nama_pupuk'] . " berhasil ditambahkan.";
            $_SESSION['message_type'] = "success";

        } elseif ($_POST['action'] == 'remove_from_cart') {
            $pupuk_id_to_remove = filter_var($_POST['pupuk_id_remove'], FILTER_VALIDATE_INT);
            if (!$pupuk_id_to_remove || !isset($_SESSION['cart'][$pupuk_id_to_remove])) throw new Exception("Item tidak valid.");
            unset($_SESSION['cart'][$pupuk_id_to_remove]);
            $_SESSION['message'] = "Item berhasil dihapus.";
            $_SESSION['message_type'] = "info";

        } elseif ($_POST['action'] == 'clear_cart') {
            $_SESSION['cart'] = [];
            $_SESSION['message'] = "Keranjang berhasil dikosongkan.";
            $_SESSION['message_type'] = "info";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    header("Location: order_pupuk.php");
    exit;
}

// Ambil data pupuk yang tersedia
try {
    // DIUBAH: Tambahkan 'kemasan' pada query
    $stmt = $conn->prepare("SELECT id, nama_pupuk, jenis_pupuk, deskripsi, harga_per_unit, kemasan, stok FROM pupuk WHERE stok > 0 ORDER BY nama_pupuk ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $pupuks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $message = "Gagal mengambil data produk."; $message_type = "error";
}

// Hitung total keranjang
foreach ($_SESSION['cart'] as $item) {
    $total_harga_keranjang += $item['harga_per_unit'] * $item['jumlah'];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Pupuk - Dashboard Pelanggan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Gaya CSS Anda tetap sama, tidak perlu diubah */
        /* ... (Salin semua gaya CSS Anda di sini) ... */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; scroll-behavior: smooth; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; overflow-x: hidden; }
        body.body-no-scroll { overflow: hidden; }

        /* General Dashboard Layout */
        .sidebar { width: 280px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(12px); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); height: 100vh; position: fixed; left: 0; top: 0; transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94); z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1); background: linear-gradient(135deg, #ffc107, #ff8f00); color: white; }
        .sidebar-header h3 { font-size: 1.4rem; margin-bottom: 5px; }
        .sidebar-header p { font-size: 0.9rem; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: #333; text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent; margin: 5px 0; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: linear-gradient(to right, #ffc107, #ffb300); color: white; border-left-color: #ff8f00; transform: translateX(5px); }
        .sidebar-menu a i { width: 20px; margin-right: 15px; }
        .logout-btn { position: absolute; bottom: 20px; left: 20px; right: 20px; }
        .logout-btn a { background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white !important; border-radius: 8px; justify-content: center; border:none; padding: 12px; }
        .main-content { margin-left: 280px; flex-grow: 1; padding: 40px; min-height: 100vh; }

        /* Style khusus halaman Order Pupuk */
        .page-header { margin-bottom: 30px; }
        .page-header h2 { font-size: 2.5rem; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .page-header p { font-size: 1.1rem; color: rgba(255,255,255,0.8); }

        .content-grid { display: grid; grid-template-columns: 1fr; gap: 30px; }
        .product-list-card, .cart-card { background: rgba(255, 255, 255, 0.97); backdrop-filter: blur(10px); border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); }
        .content-grid h3 { font-size: 1.5rem; margin-bottom: 20px; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .content-grid h3 i { margin-right: 10px; color: #667eea; }

        /* Product Cards */
        .pupuk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .pupuk-card { background: #fff; border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; }
        .pupuk-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .pupuk-card h4 { margin-top: 0; color: #343a40; font-size: 1.2rem; }
        .pupuk-card p { font-size: 0.9rem; color: #6c757d; margin: 4px 0; line-height: 1.5; }
        .pupuk-card .price { font-weight: bold; color: #28a745; font-size: 1.2rem; margin: 10px 0; }
        .pupuk-card .stok { font-size: 0.85rem; color: #888; background: #f1f3f5; padding: 3px 8px; border-radius: 5px; display: inline-block; margin-top:auto;}
        .add-to-cart-form { margin-top: 15px; display: flex; align-items: center; gap: 8px; }
        .add-to-cart-form input[type="number"] { width: 60px; padding: 8px; border: 1px solid #ced4da; border-radius: 6px; text-align: center; }
        .add-to-cart-form button { background: #667eea; color: white; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; flex-grow: 1; transition: background-color 0.2s; }
        .add-to-cart-form button:hover { background-color: #5a67d8; }

        /* Cart Summary Card */
        .cart-card ul { list-style: none; padding: 0; }
        .cart-card ul li { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed #e0e0e0; font-size: 0.95rem; }
        .cart-card ul li span { flex-grow: 1; }
        .cart-card .total { font-weight: bold; margin-top: 15px; font-size: 1.2em; text-align: right; color: #333; }
        .cart-actions { display: flex; gap: 10px; margin-top: 20px; }
        .cart-actions button, .cart-actions a { padding: 10px 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: all 0.2s; flex-grow: 1; }
        .cart-actions .btn-checkout { background: #28a745; color: white; border:none; }
        .cart-actions .btn-clear { background: #dc3545; color: white; border:none; cursor: pointer;}
        .remove-item-btn { background: #fa5252; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; margin-left: 10px; font-size: 0.8rem; line-height: 24px; text-align:center;}
        .empty-cart-msg { text-align: center; color: #888; padding: 20px; }

        /* Notification Toast */
        .toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 1001; bottom: 30px; left: 50%; transform: translateX(-50%); font-size: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.3); opacity: 0; transition: visibility 0.5s, opacity 0.5s, transform 0.5s; }
        .toast.show { visibility: visible; opacity: 1; transform: translate(-50%, -10px); }
        .toast.success { background-color: #28a745; }
        .toast.error { background-color: #dc3545; }
        .toast.info { background-color: #17a2b8; }

        /* Responsive & Mobile */
        .mobile-toggle { display: none; position: fixed; top: 15px; left: 15px; width: 45px; height: 45px; border-radius: 50%; z-index: 1001; }
        .overlay { display: none; }

        @media (min-width: 992px) {
            .content-grid { grid-template-columns: 2fr 1fr; } /* Layout 2 kolom di desktop */
            .cart-card { position: sticky; top: 40px; } /* Cart sticky di desktop */
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-toggle, .overlay { display: block; }
            .overlay.active { display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
            .page-header h2 { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <button class="mobile-toggle" style="background-color: #ffc107; color: white; border: none; cursor: pointer;">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar" id="sidebar">
        <!-- Sidebar content (tidak ada perubahan) -->
        <div class="sidebar-header">
            <h3>Dashboard Pelanggan</h3>
            <p>Selamat Datang, <br><?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
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
    
    <main class="main-content">
        <div class="page-header">
            <h2>Pesan Pupuk</h2>
            <p>Pilih pupuk terbaik untuk kebutuhan Anda dan tambahkan ke keranjang.</p>
        </div>

        <div class="content-grid">
            <!-- Product Listing -->
            <div class="product-list-card">
                <h3><i class="fas fa-seedling"></i> Daftar Pupuk Tersedia</h3>
                <?php if (empty($pupuks)): ?>
                    <p class="empty-cart-msg">Maaf, saat ini belum ada pupuk yang tersedia.</p>
                <?php else: ?>
                    <div class="pupuk-grid">
                        <?php foreach ($pupuks as $pupuk): ?>
                            <div class="pupuk-card">
                                <div>
                                    <h4><?php echo htmlspecialchars($pupuk['nama_pupuk']); ?></h4>
                                    <p><strong>Jenis:</strong> <?php echo htmlspecialchars($pupuk['jenis_pupuk']); ?></p>
                                    <p><?php echo nl2br(htmlspecialchars($pupuk['deskripsi'])); ?></p>
                                    <!-- DIUBAH: Tampilkan Harga per Kemasan -->
                                    <p class="price">Rp <?php echo number_format($pupuk['harga_per_unit'], 0, ',', '.'); ?> 
                                        <?php if(!empty($pupuk['kemasan'])): ?>
                                            <span style="font-size: 0.8em; color: #6c757d;">/ <?php echo htmlspecialchars($pupuk['kemasan']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div style="margin-top:auto;">
                                    <form action="order_pupuk.php" method="POST" class="add-to-cart-form">
                                        <input type="hidden" name="pupuk_id" value="<?php echo $pupuk['id']; ?>">
                                        <input type="number" name="jumlah" min="1" max="<?php echo $pupuk['stok']; ?>" value="1" required aria-label="Jumlah">
                                        <button type="submit" name="action" value="add_to_cart"><i class="fas fa-cart-plus"></i></button>
                                    </form>
                                    <p class="stok">Stok: <?php echo htmlspecialchars($pupuk['stok']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Shopping Cart -->
            <aside class="cart-card" id="cart-summary">
                <h3><i class="fas fa-shopping-basket"></i> Keranjang Belanja</h3>
                <?php if (empty($_SESSION['cart'])): ?>
                    <p class="empty-cart-msg">Keranjang Anda kosong.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($_SESSION['cart'] as $id => $item): ?>
                            <li>
                                <span>
                                    <strong><?php echo htmlspecialchars($item['nama_pupuk']); ?></strong>
                                    <!-- DIUBAH: Tampilkan detail jumlah, harga, dan kemasan -->
                                    <br>
                                    <small>
                                        <?php echo htmlspecialchars($item['jumlah']); ?> x Rp <?php echo number_format($item['harga_per_unit'], 0, ',', '.'); ?>
                                        <?php if(!empty($item['kemasan'])): ?>
                                            (<?php echo htmlspecialchars($item['kemasan']); ?>)
                                        <?php endif; ?>
                                    </small>
                                </span>
                                <form action="order_pupuk.php" method="POST" style="display: inline-block;">
                                    <input type="hidden" name="pupuk_id_remove" value="<?php echo htmlspecialchars($id); ?>">
                                    <button type="submit" name="action" value="remove_from_cart" class="remove-item-btn" title="Hapus item">×</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 15px 0;">
                    <p class="total">Total: Rp <?php echo number_format($total_harga_keranjang, 0, ',', '.'); ?></p>
                    <div class="cart-actions">
                        <form action="order_pupuk.php" method="POST" style="flex-grow:1;">
                            <button type="submit" name="action" value="clear_cart" class="btn-clear" onclick="return confirm('Kosongkan keranjang?');">Kosongkan</button>
                        </form>
                        <a href="checkout.php" class="btn-checkout">Checkout</a>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
        
        <!-- Toast Notification element -->
        <div id="toast" class="toast"></div>

    </main>

    <script>
        // Script Anda tetap sama, tidak perlu diubah
        // ... (Salin semua script JS Anda di sini) ...
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const toggleButton = document.querySelector('.mobile-toggle');
        function toggleSidebar() { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); document.body.classList.toggle('body-no-scroll'); }
        function closeSidebar() { sidebar.classList.remove('active'); overlay.classList.remove('active'); document.body.classList.remove('body-no-scroll'); }
        toggleButton.addEventListener('click', (e) => { e.stopPropagation(); toggleSidebar(); });
        overlay.addEventListener('click', closeSidebar);
        window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

        document.addEventListener('DOMContentLoaded', function() {
            const message = <?php echo json_encode($message); ?>;
            const messageType = <?php echo json_encode($message_type); ?>;

            if (message) {
                const toast = document.getElementById('toast');
                toast.textContent = message;
                toast.className = 'toast show ' + messageType;
                setTimeout(function() { 
                    toast.className = toast.className.replace('show', ''); 
                }, 3000);
            }
        });
    </script>
</body>
</html>
<?php
if(isset($conn)) { $conn->close(); }
?>