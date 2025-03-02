<?php
/**
 * Güvenlik fonksiyonları
 */

// Gerekli dosyaları dahil et
require_once 'config.php';

/**
 * Güvenlik başlıklarını ayarlar
 * 
 * @return void
 */
function setSecurityHeaders() {
    // XSS koruması
    header("X-XSS-Protection: 1; mode=block");
    
    // Clickjacking koruması
    header("X-Frame-Options: SAMEORIGIN");
    
    // MIME türü belirleme koruması
    header("X-Content-Type-Options: nosniff");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Content Security Policy
    $cspHeader = "Content-Security-Policy: ";
    $cspHeader .= "default-src 'self'; ";
    $cspHeader .= "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; ";
    $cspHeader .= "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; ";
    $cspHeader .= "img-src 'self' data:; ";
    $cspHeader .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; ";
    $cspHeader .= "connect-src 'self'; ";
    $cspHeader .= "frame-src 'self'; ";
    $cspHeader .= "object-src 'none';";
    
    header($cspHeader);
    
    // HSTS (yalnızca HTTPS kullanılıyorsa aktif edilmeli)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

/**
 * Form verilerini temizler (XSS koruması)
 * 
 * @param mixed $data Temizlenecek veri
 * @return mixed Temizlenmiş veri
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
    } else {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * SQL Injection koruması için verilen dizideki tüm değerleri temizler
 * 
 * @param array $data Temizlenecek dizi
 * @return array Temizlenmiş dizi
 */
function sanitizeArray($data) {
    $sanitized = [];
    
    foreach ($data as $key => $value) {
        $sanitizedKey = filter_var($key, FILTER_SANITIZE_STRING);
        
        if (is_array($value)) {
            $sanitized[$sanitizedKey] = sanitizeArray($value);
        } else {
            $sanitized[$sanitizedKey] = filter_var($value, FILTER_SANITIZE_STRING);
        }
    }
    
    return $sanitized;
}

/**
 * Rate limiting kontrolü yapar
 * 
 * @param string $key Rate limiting için benzersiz anahtar (örn. IP+işlem)
 * @param int $limit İzin verilen maksimum işlem sayısı
 * @param int $interval Süre aralığı (saniye)
 * @return bool İşlem yapılabilir ise true, limit aşıldı ise false
 */
function checkRateLimit($key, $limit = 5, $interval = 60) {
    $time = time();
    $rateLimitKey = 'rate_limit_' . md5($key);
    
    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = [
            'count' => 1,
            'first_time' => $time,
            'last_time' => $time
        ];
        return true;
    }
    
    $rateLimit = &$_SESSION[$rateLimitKey];
    
    // Süre aşıldı mı kontrol et
    if ($time - $rateLimit['first_time'] > $interval) {
        // Süre aşıldıysa sıfırla
        $rateLimit = [
            'count' => 1,
            'first_time' => $time,
            'last_time' => $time
        ];
        return true;
    }
    
    // Limit aşıldı mı kontrol et
    if ($rateLimit['count'] >= $limit) {
        return false;
    }
    
    // İşlem sayısını artır
    $rateLimit['count']++;
    $rateLimit['last_time'] = $time;
    
    return true;
}

/**
 * IP adresini alır
 * 
 * @return string IP adresi
 */
function getIPAddress() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
            return $_SERVER[$key];
        }
    }
    
    return '0.0.0.0';
}

/**
 * Güvenlik loglarına kayıt ekler
 * 
 * @param string $action İşlem
 * @param string $description Açıklama
 * @param int $userId Kullanıcı ID
 * @param int $level Log seviyesi (1: Bilgi, 2: Uyarı, 3: Kritik)
 * @return bool Başarılı ise true, değilse false
 */
function logSecurityEvent($action, $description, $userId = 0, $level = 1) {
    $ip = getIPAddress();
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    
    try {
        $pdo = getDbConnection();
        $sql = "INSERT INTO security_logs (user_id, action, description, ip_address, user_agent, level) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([$userId, $action, $description, $ip, $userAgent, $level]);
    } catch (PDOException $e) {
        error_log('Güvenlik Log Hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Brute force koruması için başarısız giriş denemelerini kontrol eder
 * 
 * @param string $username Kullanıcı adı
 * @param bool $reset Başarılı giriş sonrası sıfırlamak için
 * @return bool|int Kalan deneme hakkı veya kilitli ise false
 */
function checkLoginAttempts($username, $reset = false) {
    $ip = getIPAddress();
    $key = 'login_' . md5($username . '_' . $ip);
    
    // Başarılı girişte deneme sayısını sıfırla
    if ($reset) {
        unset($_SESSION[$key]);
        return true;
    }
    
    // İlk deneme
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 1,
            'time' => time()
        ];
        return 5; // Toplam 5 deneme hakkı
    }
    
    $loginData = &$_SESSION[$key];
    
    // Kilitlenme süresi kontrolü (15 dakika)
    if (isset($loginData['locked_until'])) {
        if (time() < $loginData['locked_until']) {
            return false; // Hala kilitli
        } else {
            // Kilit süresini geçti, sıfırla
            $loginData = [
                'attempts' => 1,
                'time' => time()
            ];
            return 5;
        }
    }
    
    // Deneme sayısını artır
    $loginData['attempts']++;
    $loginData['time'] = time();
    
    // 5 başarısız denemeden sonra kilitle
    if ($loginData['attempts'] > 5) {
        $loginData['locked_until'] = time() + 900; // 15 dakika kilitle
        
        // Güvenlik loglarına kaydet
        logSecurityEvent('login_blocked', "Çok sayıda başarısız giriş denemesi nedeniyle $username kullanıcısı için erişim engellendi.", 0, 2);
        
        return false;
    }
    
    return 6 - $loginData['attempts']; // Kalan deneme hakkı
}

/**
 * Zararlı dosya türlerini kontrol eder
 * 
 * @param string $filename Dosya adı
 * @return bool Güvenli ise true, değilse false
 */
function isSecureFileType($filename) {
    $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'phps',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'dll',
        'jsp', 'asp', 'aspx', 'vb', 'vbs', 'js', 'htaccess'
    ];
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    return !in_array($extension, $dangerousExtensions);
}

/**
 * Kullanıcı parolasının güçlü olup olmadığını kontrol eder
 * 
 * @param string $password Parola
 * @return bool Güçlü ise true, değilse false
 */
function isStrongPassword($password) {
    // En az 8 karakter
    if (strlen($password) < 8) {
        return false;
    }
    
    // Büyük harf, küçük harf, rakam ve özel karakter içermeli
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    
    return true;
}

// Güvenlik başlıklarını ayarla
setSecurityHeaders();