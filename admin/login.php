<?php
/**
 * Admin Panel Giriş Sayfası
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Zaten giriş yapmışsa yönlendir
if (isLoggedIn()) {
    redirect(ADMIN_URL . '/dashboard.php');
}

$error = '';
$username = '';

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password']; // Şifreyi sanitize etme
        
        // Brute force koruması
        $loginAttempts = checkLoginAttempts($username);
        
        if ($loginAttempts === false) {
            $error = 'Çok fazla başarısız giriş denemesi. Hesabınız 15 dakika boyunca kilitlendi.';
        } elseif (empty($username) || empty($password)) {
            $error = 'Kullanıcı adı ve şifre gerekli.';
        } else {
            $userId = loginUser($username, $password);
            
            if ($userId) {
                // Başarılı giriş, deneme sayısını sıfırla
                checkLoginAttempts($username, true);
                
                // Güvenlik loguna kaydet
                logSecurityEvent('login', "Başarılı giriş: $username", $userId, 1);
                
                // Rol bazlı yönlendirme
                switch ($_SESSION['role']) {
                    case ROLE_SUPER_ADMIN:
                        redirect(ADMIN_URL . '/dashboard.php');
                        break;
                    case ROLE_WAITER:
                        redirect(ADMIN_URL . '/orders.php');
                        break;
                    case ROLE_KITCHEN:
                        redirect(ADMIN_URL . '/kitchen.php');
                        break;
                    default:
                        redirect(ADMIN_URL . '/dashboard.php');
                }
            } else {
                // Başarısız giriş
                $kalan = checkLoginAttempts($username);
                $error = "Geçersiz kullanıcı adı veya şifre. Kalan deneme hakkınız: $kalan";
                
                // Güvenlik loguna kaydet
                logSecurityEvent('login_failed', "Başarısız giriş denemesi: $username", 0, 2);
            }
        }
    }
}

// CSRF token oluştur
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Restoran Menü Sistemi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= ADMIN_ASSETS_URL ?>/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-logo img {
            max-width: 150px;
        }
        .form-control {
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 15px;
        }
        .btn-login {
            background-color: #ff6b6b;
            border: none;
            border-radius: 5px;
            padding: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background-color: #ff5252;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="<?= ADMIN_ASSETS_URL ?>/img/logo.png" alt="Restoran Menü Sistemi">
            <h2>Restoran Menü Sistemi</h2>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label">Kullanıcı Adı</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="<?= h($username) ?>" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">Şifre</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Giriş Yap
                </button>
            </div>
        </form>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Şifre görünürlüğü toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>