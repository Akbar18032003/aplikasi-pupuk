<?php
session_start(); // Mulai sesi

// Jika sudah login, redirect ke index.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once '../config/database.php'; // Sertakan file koneksi database

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validasi input
    if (empty($username) || empty($password)) {
        $error_message = "Username dan password tidak boleh kosong.";
    } else {
        // Gunakan Prepared Statements untuk mencegah SQL Injection
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username); // "s" menunjukkan parameter adalah string
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            // Verifikasi password yang di-hash
            if (password_verify($password, $user['password'])) {
                // Login berhasil, simpan data user ke session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirect ke halaman index.php yang akan mengarahkan ke dashboard masing-masing peran
                header("Location: index.php");
                exit;
            } else {
                $error_message = "Password salah.";
            }
        } else {
            $error_message = "Username tidak ditemukan.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Enam Saudara</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box; /* Penggunaan box-sizing ini sudah sangat bagus */
        }

        html, body {
            min-height: 100vh; /* Memastikan html juga mengambil tinggi penuh */
            height: 100%; /* Penting untuk beberapa browser mobile */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom,rgb(13, 64, 165),rgba(105, 7, 154, 0.81));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            width: 150px;
            height: auto;
            display: block;
            margin: 0 auto 16px;
        }

        .login-title {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #666;
            font-size: 14px;
            text-align: center;
            margin-bottom: 30px;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #c33;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: #555;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
            z-index: 1; /* sudah benar, untuk memastikan di atas input */
        }

        .form-group input {
            /* Mencegah zoom otomatis pada input di iOS */
            -webkit-appearance: none; 
            appearance: none;
            
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px; /* font-size 16px mencegah zoom otomatis di Safari mobile */
            background: #f8f9fa;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input:focus ~ .input-wrapper i { /* Menggunakan sibling selector (~) untuk lebih baik */
            color: #667eea;
        }

        .login-button {
            -webkit-appearance: none; 
            appearance: none;

            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            
            /* Mencegah highlight biru saat diklik di mobile */
            -webkit-tap-highlight-color: transparent;
        }

        .login-button:hover {
            /* Hover tidak selalu berfungsi di touch screen, jadi jangan bergantung padanya */
            /* Untuk desktop, ini tetap berfungsi */
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .login-button:active {
            transform: translateY(0); /* Efek 'ditekan' akan berfungsi baik di mobile maupun desktop */
        }

        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .login-button:hover::before {
            left: 100%;
        }

        .register-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e1e5e9;
        }

        .register-link p {
            color: #666;
            font-size: 14px;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .register-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* Loading state */
        .login-button.loading {
            pointer-events: none;
            color: transparent;
        }

        .login-button.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* === Media Query === */
        /* Aturan ini berlaku untuk layar dengan lebar MAKSIMAL 480px (Handphone) */
        @media (max-width: 480px) {
            body {
                /* Hapus padding body agar container bisa mengambil lebar penuh */
                padding: 0;
                /* Alih-alih center, posisikan container ke atas di mobile agar lebih alami */
                align-items: flex-start; 
                padding-top: 20px;
            }

            .login-container {
                /* Gunakan padding 20px atas-bawah dan 24px kiri-kanan */
                padding: 30px 24px;
                /* Menghapus margin agar menempel di tepi jika diperlukan */
                margin: 10px;
                /* Radius border lebih kecil agar tidak aneh di layar sempit */
                border-radius: 16px;
            }
            
            .login-title {
                font-size: 24px;
            }
            
            .login-logo {
                width: 120px;
            }

            .login-subtitle {
                font-size: 13px; /* Sedikit lebih kecil agar tidak terlalu makan tempat */
            }
        }

        /* Animation */
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

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="../includes/img/logo.png" alt="Logo Perusahaan" class="login-logo">
            <h2 class="login-title">Selamat Datang</h2>
            <p class="login-subtitle">PT Usaha Enam Saudara</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" required autocomplete="username" placeholder="Masukkan username Anda">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Masukkan password Anda">
                </div>
            </div>

            <button type="submit" class="login-button" id="loginBtn">
                <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                Masuk
            </button>
        </form>

        <div class="register-link">
            <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
        </div>
    </div>

    <script>
        // Form submission with loading animation
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.innerHTML = 'Memproses...';
        });

        // Auto focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Enhanced form validation
        const form = document.getElementById('loginForm');
        const inputs = form.querySelectorAll('input');

        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.style.borderColor = '#e74c3c';
                } else {
                    this.style.borderColor = '#27ae60';
                }
            });

            input.addEventListener('input', function() {
                if (this.style.borderColor === 'rgb(231, 76, 60)') {
                    this.style.borderColor = '#e1e5e9';
                }
            });
        });
    </script>
</body>
</html>

<?php
if (isset($conn)) {
    close_db_connection($conn); // Tutup koneksi database
}
?>