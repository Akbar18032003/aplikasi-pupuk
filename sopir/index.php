<?php
session_start();
// Pastikan user sudah login dan memiliki peran 'sopir'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sopir') {
    header("Location: ../public/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Sopir</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS YANG SUDAH DISELARASKAN DENGAN HALAMAN LAIN */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; color: #333; overflow-x: hidden;
        }
        .dashboard-wrapper { display: flex; min-height: 100vh; }

        /* --- Sidebar Styles (Identik dengan my_deliveries.php) --- */
        .sidebar {
            width: 280px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2); padding: 0; position: fixed;
            height: 100vh; overflow-y: auto; transition: transform 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000;
        }
        .sidebar-header {
            padding: 30px 25px; background: linear-gradient(135deg, #28a745, #20c997);
            color: white; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-header h3 { font-size: 1.4rem; font-weight: 600; margin-bottom: 8px; }
        .sidebar-header .user-info { font-size: 0.9rem; opacity: 0.9; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 18px 25px; color: #555;
            text-decoration: none; transition: all 0.3s ease; border-left: 4px solid transparent;
            font-weight: 500; gap: 15px;
        }
        /* Style untuk menu yang aktif */
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.1), transparent);
            color: #28a745; border-left-color: #28a745; transform: translateX(5px);
        }
        .sidebar-menu a i { width: 20px; text-align: center; font-size: 1.1rem; }
        .sidebar-menu .menu-section { padding: 15px 25px 8px; font-size: 0.8rem; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: 1px; }
        .logout-btn-wrapper { margin-top: 20px; }
        .logout-btn { border-top: 1px solid rgba(0, 0, 0, 0.1); padding-top: 10px; }
        .logout-btn a { color: #dc3545 !important; }
        .logout-btn a:hover { background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), transparent); border-left-color: #dc3545; }

        /* --- Main Content (Dashboard) --- */
        .main-content {
            flex: 1; margin-left: 280px; padding: 40px;
            transition: margin-left 0.3s ease-in-out;
        }
        .welcome-card {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            border-radius: 20px; padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center; margin-bottom: 30px;
        }
        .welcome-card h1 { font-size: 2.5rem; color: #333; margin-bottom: 15px; font-weight: 700; }
        .welcome-card .subtitle { font-size: 1.1rem; color: #666; margin-bottom: 30px; line-height: 1.6; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-top: 30px; }
        .stat-card {
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
            border-radius: 16px; padding: 30px; text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); }
        .stat-card i { font-size: 3rem; color: #28a745; margin-bottom: 15px; }
        .stat-card h3 { font-size: 1.3rem; color: #333; margin-bottom: 10px; font-weight: 600; }
        .stat-card p { color: #666; font-size: 0.95rem; line-height: 1.5; }

        /* --- Mobile Responsiveness (Identik dengan my_deliveries.php) --- */
        .mobile-toggle { display: none; position: fixed; top: 15px; left: 15px; z-index: 1001; background: #28a745; color: white; border: none; padding: 10px 12px; border-radius: 8px; cursor: pointer; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        .overlay.active { display: block; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 15px; }
            .mobile-toggle { display: block; }
            .welcome-card { padding: 30px 20px; margin-top: 60px; }
            .welcome-card h1 { font-size: 2rem; }
            .welcome-card .subtitle { font-size: 1rem; }
            .stats-grid { grid-template-columns: 1fr; gap: 20px; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="overlay" onclick="toggleSidebar()"></div>

    <div class="dashboard-wrapper">
        <!-- Sidebar yang sudah disesuaikan -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-truck"></i> Dashboard Sopir</h3>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-section">Menu Utama</div>
                <!-- Menu Dashboard Utama menjadi 'active' di halaman ini -->
                <a href="index.php" class="active">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard Utama
                </a>
                <a href="my_deliveries.php">
                    <i class="fas fa-shipping-fast"></i>
                    Daftar Pengiriman Saya
                </a>
                
                <div class="menu-section logout-btn-wrapper">Lainnya</div>
                <div class="logout-btn">
                    <a href="../public/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content (Konten Asli index.php tidak diubah) -->
        <div class="main-content">
            <div class="welcome-card">
                <h1>Selamat Datang!</h1>
                <p class="subtitle">
                    Halo <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>, 
                    selamat datang di dashboard sopir. Kelola pengiriman Anda dengan mudah dan efisien.
                </p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-shipping-fast"></i>
                    <h3>Pengiriman Aktif</h3>
                    <p>Lihat dan kelola semua pengiriman yang sedang dalam proses.</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-route"></i>
                    <h3>Rute Perjalanan</h3>
                    <p>Optimalisasi rute untuk efisiensi waktu dan bahan bakar.</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Statistik Pengiriman</h3>
                    <p>Monitor performa dan pencapaian pengiriman Anda.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript yang sudah diselaraskan -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.overlay');
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
            }
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !toggle.contains(event.target) && sidebar.classList.contains('mobile-open')) {
                toggleSidebar();
            }
        });

        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.overlay');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            }
        });
    </script>
</body>
</html>