<?php
/**
 * Kimlik doğrulama ve yetkilendirme fonksiyonları
 */

// Gerekli dosyaları dahil et
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

/**
 * Kullanıcı girişi yapar
 * 
 * @param string $username Kullanıcı adı
 * @param string $password Şifre
 * @return bool|int Başarılı ise kullanıcı ID'si, değilse false
 */
function loginUser($username, $password) {
    // Kullanıcı adına göre kullanıcıyı bul
    $user = dbQuerySingle("SELECT * FROM users WHERE username = ? AND status = 1", [$username]);
    
    if (!$user) {
        return false;
    }
    
    // Şifre kontrolü
    if (!password_verify($password, $user['password'])) {
        return false;
    }
    
    // Session bilgilerini kaydet
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['auth_time'] = time();
    
    // Son giriş tarihini güncelle
    dbExecute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    
    return $user['id'];
}

/**
 * Kullanıcı çıkışı yapar
 * 
 * @return void
 */
function logoutUser() {
    // Session'ı temizle
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Kullanıcının giriş yapıp yapmadığını kontrol eder
 * 
 * @return bool Giriş yapmışsa true, yapmamışsa false
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Giriş yapmadıysa giriş sayfasına yönlendirir
 * 
 * @param string $redirectUrl Yönlendirilecek URL
 * @return void
 */
function requireLogin($redirectUrl = null) {
    if (!isLoggedIn()) {
        $url = $redirectUrl ? $redirectUrl : ADMIN_URL . '/login.php';
        redirect($url);
    }
}

/**
 * Kullanıcının rolünü kontrol eder
 * 
 * @param string|array $roles İzin verilen rol veya roller
 * @return bool İzin varsa true, yoksa false
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role'], $roles);
}

/**
 * Kullanıcının rolünü kontrol eder, yoksa hata sayfasına yönlendirir
 * 
 * @param string|array $roles İzin verilen rol veya roller
 * @param string $redirectUrl Yönlendirilecek URL
 * @return void
 */
function requireRole($roles, $redirectUrl = null) {
    if (!hasRole($roles)) {
        $url = $redirectUrl ? $redirectUrl : ADMIN_URL . '/unauthorized.php';
        redirect($url);
    }
}

/**
 * Şifre hasher
 * 
 * @param string $password Ham şifre
 * @return string Hashlenmiş şifre
 */
function hashPassword($password) {
    return password_hash($password . SALT, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Şu anki kullanıcı bilgilerini döndürür
 * 
 * @return array|bool Kullanıcı bilgileri veya false
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return false;
    }
    
    return dbQuerySingle("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

/**
 * Kullanıcının Süper Admin olup olmadığını kontrol eder
 * 
 * @return bool Süper Admin ise true, değilse false
 */
function isSuperAdmin() {
    return hasRole(ROLE_SUPER_ADMIN);
}

/**
 * Kullanıcının Garson olup olmadığını kontrol eder
 * 
 * @return bool Garson ise true, değilse false
 */
function isWaiter() {
    return hasRole(ROLE_WAITER);
}

/**
 * Kullanıcının Mutfak personeli olup olmadığını kontrol eder
 * 
 * @return bool Mutfak personeli ise true, değilse false
 */
function isKitchen() {
    return hasRole(ROLE_KITCHEN);
}