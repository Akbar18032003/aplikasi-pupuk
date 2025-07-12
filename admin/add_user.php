<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Jika tidak, redirect ke halaman login
    header("Location: ../public/login.php");
    exit;
}


$message = ''; 
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $alamat = trim($_POST['alamat']);
    $telepon = trim($_POST['telepon']);
    $role = $_POST['role']; 

    if (empty($username) || empty($password) || empty($nama_lengkap) || empty($email) || empty($alamat) || empty($telepon) || empty($role)) {
        $message = "Semua kolom harus diisi!";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format email tidak valid.";
        $message_type = "error";
    } elseif (!in_array($role, ['sopir', 'pelanggan'])) { 
        $message = "Peran tidak valid.";
        $message_type = "error";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "Username atau Email sudah terdaftar.";
            $message_type = "error";
        } else {

            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, alamat, telepon, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $hashed_password, $nama_lengkap, $email, $alamat, $telepon, $role);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Pengguna baru '{$username}' berhasil ditambahkan!";
                $_SESSION['message_type'] = "success";
                header("Location: manage_users.php"); // Redirect ke halaman utama manajemen pengguna
                exit;
            } else {
                $message = "Gagal menambahkan pengguna: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengguna Baru - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- CSS DI BAWAH INI SAMA DENGAN HALAMAN ADMIN LAINNYA -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        /* --- STYLES UNTUK SIDEBAR --- */
        .sidebar {
            position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; z-index: 1000; transition: transform 0.3s ease; box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }
        .sidebar-header {
            padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(0, 0, 0, 0.1);
        }
        .sidebar-header h3 {
            color: #ecf0f1; font-size: 1.4rem; margin-bottom: 5px;
        }
        .sidebar-header p {
            color: #bdc3c7; font-size: 0.9rem;
        }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin: 8px 20px; }
        .nav-link { display: flex; align-items: center; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-radius: 12px; transition: all 0.3s ease; font-size: 0.95rem; }
        .nav-link:hover { background: rgba(52, 152, 219, 0.2); transform: translateX(5px); color: #3498db; }
        .nav-link.active { background: #3498db; color: #ffffff; font-weight: 600; box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3); }
        .nav-link.active:hover { background: #3498db; color: #ffffff; transform: translateX(0); }
        .nav-link i { margin-right: 15px; width: 20px; text-align: center; font-size: 1.1rem; }
        .nav-link.logout { margin-top: 30px; background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.3); }
        .nav-link.logout:hover { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .sidebar-toggle { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background: #2c3e50; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
        
        /* --- STYLES UNTUK KONTEN UTAMA --- */
        .main-content { margin-left: 280px; padding: 40px; transition: margin-left 0.3s ease; }
        .page-header { background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); }
        .page-header h1 { font-size: 2rem; color: #2c3e50; margin-bottom: 8px; font-weight: 600; display: flex; align-items: center; }
        .page-header h1 i { margin-right: 15px; color: #3498db; }
        .page-header p { color: #7f8c8d; font-size: 1rem; }
        .form-container { background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); max-width: 800px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 10px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 15px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 1rem; transition: all 0.3s ease; background: #f8f9fa; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1); }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .button-group { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #e9ecef; padding-top: 25px; }
        .btn { padding: 15px 30px; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; transition: all 0.3s ease; }
        .btn i { margin-right: 8px; }
        .btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary { background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: white; }
        .btn-secondary:hover { transform: translateY(-2px); }
        .message { padding: 20px; margin-bottom: 25px; border-radius: 12px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
        
        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-toggle { display: block; }
            .main-content { margin-left: 0; padding: 20px; padding-top: 80px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-user-shield"></i> Admin Panel</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i>Dashboard</a></div>
            <div class="nav-item"><a href="manage_users.php" class="nav-link active"><i class="fas fa-users"></i>Manajemen Pengguna</a></div>
            <div class="nav-item"><a href="manage_pupuk.php" class="nav-link"><i class="fas fa-seedling"></i>Manajemen Pupuk</a></div>
            <div class="nav-item"><a href="manage_deliveries.php" class="nav-link"><i class="fas fa-truck"></i>Manajemen Pengiriman</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></div>
            <div class="nav-item"><a href="../public/logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i>Logout</a></div>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i> Tambah Pengguna Baru</h1>
            <p>Buat akun baru untuk sopir atau pelanggan dalam sistem.</p>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="add_user.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($_POST['nama_lengkap'] ?? ''); ?>" placeholder="Masukkan nama lengkap" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="contoh@email.com" required>
                    </div>
                     <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Buat username unik" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                    </div>
                     <div class="form-group">
                        <label for="telepon">Nomor Telepon</label>
                        <input type="tel" id="telepon" name="telepon" value="<?php echo htmlspecialchars($_POST['telepon'] ?? ''); ?>" placeholder="08xxxxxxxxxx" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Peran (Role)</label>
                        <select id="role" name="role" required>
                            <option value="" disabled selected>Pilih Peran</option>
                            <option value="pelanggan" <?php echo (isset($_POST['role']) && $_POST['role'] == 'pelanggan') ? 'selected' : ''; ?>>Pelanggan</option>
                            <option value="sopir" <?php echo (isset($_POST['role']) && $_POST['role'] == 'sopir') ? 'selected' : ''; ?>>Sopir</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="alamat">Alamat Lengkap</label>
                        <textarea id="alamat" name="alamat" rows="3" placeholder="Masukkan alamat lengkap" required><?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="button-group">
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Tambah Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
        window.addEventListener('resize', () => { if (window.innerWidth > 768) document.getElementById('sidebar').classList.remove('active'); });
    </script>
</body>
</html>
<?php
close_db_connection($conn);
?>