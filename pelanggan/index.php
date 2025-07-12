<?php
session_start();
// Pastikan user sudah login dan memiliki peran 'pelanggan'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header("Location: ../public/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pelanggan</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            overflow-x: hidden; /* Mencegah scroll horizontal */
        }
        /* Style untuk mencegah scroll saat menu mobile terbuka */
        body.body-no-scroll {
            overflow: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 1000;
        }
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, #ffc107, #ff8f00);
            color: white;
        }
        .sidebar-header h3 {
            font-size: 1.4rem;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            word-wrap: break-word;
        }
        .sidebar-menu {
            padding: 20px 0;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin: 5px 0;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(to right, #ffc107, #ffb300);
            color: white;
            border-left-color: #ff8f00;
            transform: translateX(5px);
        }
        .sidebar-menu a i {
            width: 20px;
            margin-right: 15px;
            font-size: 1.1rem;
        }
        .sidebar-menu a span {
            font-weight: 500;
        }
        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
        }
        .logout-btn a {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
            color: white !important;
            border-radius: 8px;
            justify-content: center;
            border-left: none !important;
            transform: none !important;
            padding: 12px;
        }
        .logout-btn a:hover {
            background: linear-gradient(135deg, #ff5252, #d32f2f);
            transform: translateY(-2px) !important;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex-grow: 1;
            padding: 40px;
            min-height: 100vh;
            width: calc(100% - 280px);
            transition: margin-left 0.3s ease;
        }
        .welcome-card, .stats-grid {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }
        .welcome-card h2 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .welcome-card p {
            font-size: 1.1rem;
            color: #666;
            line-height: 1.6;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
            background: transparent;
            box-shadow: none;
            padding: 0;
            border: none;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        .stat-card i {
            font-size: 3rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #ffc107, #ff8f00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card h4 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #333;
        }
        .stat-card p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Tombol Mobile Toggle dan Overlay */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            background: #ffc107;
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1001;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .overlay.active {
            opacity: 1;
        }

        /* Aturan untuk Mobile (layar <= 768px) */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
                width: 100%;
            }
            .mobile-toggle, .overlay {
                display: block;
            }
            .welcome-card {
                padding: 30px 25px;
            }
            .welcome-card h2 {
                font-size: 2rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Animasi */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }
        .stat-card:nth-child(4) { animation-delay: 0.3s; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>

    <!-- [PERBAIKAN] onclick dihapus dan id ditambahkan -->
    <button class="mobile-toggle" id="mobile-toggle-btn">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Dashboard Pelanggan</h3>
            <p>Selamat Datang,<br><?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
        
        <div class="sidebar-menu">
            <!-- [PERBAIKAN] Menambahkan class 'active' untuk halaman ini -->
            <a href="index.php" class="active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="order_pupuk.php">
                <i class="fas fa-shopping-cart"></i>
                <span>Pesan Pupuk</span>
            </a>
            <a href="track_delivery.php">
                <i class="fas fa-truck"></i>
                <span>Lacak Pesanan Aktif</span>
            </a>
            <a href="order_history.php">
                <i class="fas fa-history"></i>
                <span>Riwayat Pesanan</span>
            </a>
        </div>

        <div class="logout-btn">
            <a href="../public/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="main-content" id="main-content">
        <div class="welcome-card">
            <h2>Selamat Datang!</h2>
            <p>Ini adalah dashboard Pelanggan. Anda bisa memesan pupuk dan melacak pengiriman dengan mudah melalui menu navigasi. Semua fitur telah dirancang untuk memberikan pengalaman terbaik.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-seedling"></i>
                <h4>Pesan Pupuk</h4>
                <p>Temukan berbagai jenis pupuk berkualitas untuk kebutuhan pertanian Anda.</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-map-marker-alt"></i>
                <h4>Lacak Pengiriman</h4>
                <p>Pantau status pengiriman pupuk Anda secara real-time dari gudang ke lokasi.</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-clipboard-list"></i>
                <h4>Riwayat Lengkap</h4>
                <p>Lihat semua transaksi dan detail pesanan yang pernah Anda buat sebelumnya.</p>
            </div>
        </div>
    </div>

    <!-- [PERBAIKAN] Script dibuat lebih robust dan konsisten -->
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