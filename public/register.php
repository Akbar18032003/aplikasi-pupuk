<?php
session_start();
require_once '../config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'];
    $alamat = $_POST['alamat'];
    $telepon = $_POST['telepon'];
    $role = 'pelanggan'; // Secara default mendaftar sebagai pelanggan

    // Validasi input
    if (empty($username) || empty($password) || empty($nama_lengkap) || empty($email) || empty($alamat) || empty($telepon)) {
        $message = "Semua kolom harus diisi!";
    } else {
        // Hash password sebelum disimpan
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Cek apakah username atau email sudah ada
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "Username atau email sudah terdaftar.";
        } else {
            // Masukkan data pengguna baru ke database
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, nama_lengkap, alamat, telepon) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $hashed_password, $email, $role, $nama_lengkap, $alamat, $telepon);

            if ($stmt->execute()) {
                $message = "Registrasi berhasil! Silakan <a href='login.php'>login</a>.";
                // Opsional: Langsung login user setelah registrasi
                // $_SESSION['user_id'] = $conn->insert_id;
                // $_SESSION['username'] = $username;
                // $_SESSION['role'] = $role;
                // header("Location: index.php");
                // exit;
            } else {
                $message = "Registrasi gagal: " . $stmt->error;
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
    <title>Daftar Akun Baru</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
            animation: backgroundShift 20s ease-in-out infinite;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: translateX(0) translateY(0); }
            25% { transform: translateX(10px) translateY(-10px); }
            50% { transform: translateX(-5px) translateY(10px); }
            75% { transform: translateX(-10px) translateY(-5px); }
        }

        .register-container {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 
                0 25px 45px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            border-radius: 20px 20px 0 0;
        }

        h2 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px 15px 50px;
            background: rgba(255, 255, 255, 0.79);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(60, 15, 15, 0.4);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(94, 40, 40, 0.6);
            z-index: 10;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            padding-top: 15px;
        }

        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        button:hover::before {
            left: 100%;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        button:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            color: rgba(255, 255, 255, 0.8);
        }

        .login-link a {
            color: #4ecdc4;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .login-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #4ecdc4;
            transition: width 0.3s ease;
        }

        .login-link a:hover::after {
            width: 100%;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            animation: messageSlide 0.5s ease-out;
        }

        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4caf50;
        }

        .message.error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.3);
            color: #f44336;
        }

        .message a {
            color: inherit;
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .register-container {
                padding: 25px;
                margin: 10px;
            }

            h2 {
                font-size: 24px;
            }

            .form-group input,
            .form-group textarea {
                padding: 12px 15px 12px 45px;
                font-size: 14px;
            }

            .input-icon {
                left: 15px;
            }
        }

        /* Floating particles effect */
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            animation: float 6s infinite ease-in-out;
        }

        .particle:nth-child(1) { top: 20%; left: 20%; animation-delay: 0s; }
        .particle:nth-child(2) { top: 60%; left: 80%; animation-delay: 2s; }
        .particle:nth-child(3) { top: 80%; left: 40%; animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 1; }
            50% { transform: translateY(-20px) rotate(180deg); opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    
    <div class="register-container">
        <h2><i class="fas fa-user-plus"></i> Daftar Akun Pelanggan</h2>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo (strpos($message, 'berhasil') !== false) ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <div class="input-wrapper">
                    <div class="input-icon"><i class="fas fa-user"></i></div>
                    <input type="text" id="username" name="username" placeholder="Masukkan username" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <div class="input-wrapper">
                    <div class="input-icon"><i class="fas fa-lock"></i></div>
                    <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                </div>
            </div>

            <div class="form-group">
                <label for="nama_lengkap"><i class="fas fa-id-card"></i> Nama Lengkap</label>
                <div class="input-wrapper">
                    <div class="input-icon"><i class="fas fa-id-card"></i></div>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <div class="input-wrapper">
                    <div class="input-icon"><i class="fas fa-envelope"></i></div>
                    <input type="email" id="email" name="email" placeholder="Masukkan email" required>
                </div>
            </div>

            <div class="form-group">
                <label for="alamat"><i class="fas fa-map-marker-alt"></i> Alamat</label>
                <div class="input-wrapper">
                    <div class="input-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <textarea id="alamat" name="alamat" rows="3" placeholder="Masukkan alamat lengkap" required></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="telepon"><i class="fas fa-phone"></i> Telepon</label>
                <div class="input-wrapper">
                    <div class="input-icon"><i class="fas fa-phone"></i></div>
                    <input type="tel" id="telepon" name="telepon" placeholder="Masukkan nomor telepon" required>
                </div>
            </div>

            <button type="submit">
                <i class="fas fa-user-plus"></i> Daftar Sekarang
            </button>
        </form>
        
        <div class="login-link">
            Sudah punya akun? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login di sini</a>
        </div>
    </div>
</body>
</html>

<?php
close_db_connection($conn);
?>