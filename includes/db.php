<?php
/**
 * Veritabanı bağlantısı ve işlemleri
 */

// Konfigürasyon dosyasını dahil et
require_once 'config.php';

/**
 * Veritabanı bağlantısı oluşturur
 * 
 * @return PDO
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Hata günlüğüne kaydet
            error_log('Veritabanı Bağlantı Hatası: ' . $e->getMessage());
            
            // Hata sayfasına yönlendir
            header('Location: ' . BASE_URL . '/error.php?type=db');
            exit;
        }
    }
    
    return $pdo;
}

/**
 * Veritabanı sorgusu çalıştırır ve sonucu döndürür
 * 
 * @param string $sql SQL sorgusu
 * @param array $params Parametre dizisi
 * @return array|false
 */
function dbQuery($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Sorgu Hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Tek bir satır döndüren sorgu çalıştırır
 * 
 * @param string $sql SQL sorgusu
 * @param array $params Parametre dizisi
 * @return array|false
 */
function dbQuerySingle($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Sorgu Hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * INSERT, UPDATE veya DELETE sorgusu çalıştırır
 * 
 * @param string $sql SQL sorgusu
 * @param array $params Parametre dizisi
 * @return int|false Etkilenen satır sayısı veya false
 */
function dbExecute($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('Sorgu Hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * INSERT sorgusu çalıştırır ve eklenen kaydın ID'sini döndürür
 * 
 * @param string $sql SQL sorgusu
 * @param array $params Parametre dizisi
 * @return int|false Son eklenen ID veya false
 */
function dbInsert($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Sorgu Hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Tüm site ayarlarını veritabanından yükler
 * 
 * @return array Ayarlar dizisi
 */
function loadSettings() {
    global $settings;
    
    if (empty($settings)) {
        $result = dbQuery("SELECT setting_key, setting_value, setting_group FROM settings");
        
        if ($result) {
            foreach ($result as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    
    return $settings;
}

/**
 * Belirli bir ayarın değerini döndürür
 * 
 * @param string $key Ayar anahtarı
 * @param mixed $default Varsayılan değer
 * @return mixed Ayar değeri
 */
function getSetting($key, $default = null) {
    global $settings;
    
    if (empty($settings)) {
        loadSettings();
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Site ayarlarını yükle
loadSettings();