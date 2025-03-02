<?php
/**
 * Genel yardımcı fonksiyonlar
 */

// Gerekli dosyaları dahil et
require_once 'config.php';
require_once 'db.php';

/**
 * Güvenli çıktı oluşturur (XSS koruması)
 * 
 * @param string $text Temizlenecek metin
 * @return string Temizlenmiş metin
 */
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Verilen URL'ye yönlendirir
 * 
 * @param string $url Yönlendirilecek URL
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Başarı mesajı oluşturur
 * 
 * @param string $message Mesaj
 * @return string HTML çıktısı
 */
function successMessage($message) {
    return '<div class="alert alert-success">' . h($message) . '</div>';
}

/**
 * Hata mesajı oluşturur
 * 
 * @param string $message Mesaj
 * @return string HTML çıktısı
 */
function errorMessage($message) {
    return '<div class="alert alert-danger">' . h($message) . '</div>';
}

/**
 * Bilgi mesajı oluşturur
 * 
 * @param string $message Mesaj
 * @return string HTML çıktısı
 */
function infoMessage($message) {
    return '<div class="alert alert-info">' . h($message) . '</div>';
}

/**
 * Session mesajı kaydeder
 * 
 * @param string $type Mesaj tipi (success, error, info)
 * @param string $message Mesaj
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * Session mesajlarını gösterir ve siler
 * 
 * @return string HTML çıktısı
 */
function displayFlashMessages() {
    $output = '';
    
    if (isset($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $type => $messages) {
            foreach ($messages as $message) {
                if ($type == 'success') {
                    $output .= successMessage($message);
                } else if ($type == 'error') {
                    $output .= errorMessage($message);
                } else if ($type == 'info') {
                    $output .= infoMessage($message);
                }
            }
        }
        
        unset($_SESSION['flash_messages']);
    }
    
    return $output;
}

/**
 * CSRF token oluşturur ve session'a kaydeder
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    
    return $token;
}

/**
 * CSRF token kontrolü yapar
 * 
 * @param string $token Kontrol edilecek token
 * @return bool Geçerli ise true, değilse false
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if ($_SESSION['csrf_token'] !== $token) {
        return false;
    }
    
    if (time() - $_SESSION['csrf_token_time'] > TOKEN_TIMEOUT) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return true;
}

/**
 * Rastgele güvenli şifre oluşturur
 * 
 * @param int $length Şifre uzunluğu
 * @return string Oluşturulan şifre
 */
function generateRandomPassword($length = 12) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * Para birimini formatlar
 * 
 * @param float $amount Miktar
 * @return string Formatlanmış para birimi
 */
function formatCurrency($amount) {
    return number_format($amount, 2, ',', '.') . ' ₺';
}

/**
 * Tarihi formatlar
 * 
 * @param string $date Tarih
 * @param string $format Format
 * @return string Formatlanmış tarih
 */
function formatDate($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

/**
 * QR kod oluşturur ve kaydeder
 * 
 * @param string $data QR kod içeriği
 * @param string $filename Dosya adı
 * @return bool|string Başarılı ise dosya yolu, değilse false
 */
function generateQRCode($data, $filename) {
    // Bu fonksiyon için phpqrcode kütüphanesi gerekli
    // Kurulum: composer require phpqrcode/phpqrcode
    
    if (!file_exists(QR_CODES_DIR)) {
        mkdir(QR_CODES_DIR, 0755, true);
    }
    
    $filePath = QR_CODES_DIR . '/' . $filename . '.png';
    
    // QR kod oluşturma kodu burada
    // Örnek: QRcode::png($data, $filePath, QR_ERROR_CORRECTION, QR_SIZE, QR_MARGIN);
    
    if (file_exists($filePath)) {
        return $filePath;
    }
    
    return false;
}

/**
 * Dosya yükler
 * 
 * @param array $file $_FILES dizisi
 * @param string $targetDir Hedef dizin
 * @param array $allowedTypes İzin verilen dosya tipleri
 * @param int $maxSize Maksimum dosya boyutu (byte)
 * @return array Sonuç dizisi
 */
function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => '',
        'filepath' => ''
    ];
    
    // Hata kontrolü
    if (!isset($file['error']) || is_array($file['error'])) {
        $result['message'] = 'Geçersiz dosya!';
        return $result;
    }
    
    // Dosya yükleme hatası kontrolü
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $result['message'] = 'Dosya boyutu çok büyük!';
            return $result;
        case UPLOAD_ERR_PARTIAL:
            $result['message'] = 'Dosya kısmen yüklendi!';
            return $result;
        case UPLOAD_ERR_NO_FILE:
            $result['message'] = 'Dosya yüklenmedi!';
            return $result;
        case UPLOAD_ERR_NO_TMP_DIR:
            $result['message'] = 'Geçici klasör bulunamadı!';
            return $result;
        case UPLOAD_ERR_CANT_WRITE:
            $result['message'] = 'Dosya diske yazılamadı!';
            return $result;
        case UPLOAD_ERR_EXTENSION:
            $result['message'] = 'Dosya yükleme uzantı tarafından durduruldu!';
            return $result;
        default:
            $result['message'] = 'Bilinmeyen hata!';
            return $result;
    }
    
    // Dosya boyutu kontrolü
    if ($file['size'] > $maxSize) {
        $result['message'] = 'Dosya boyutu çok büyük!';
        return $result;
    }
    
    // MIME türü kontrolü
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $fileContents = file_get_contents($file['tmp_name']);
    $mimeType = $finfo->buffer($fileContents);
    
    $allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    $validMimeType = false;
    foreach ($allowedTypes as $ext) {
        if (isset($allowedMimeTypes[$ext]) && $allowedMimeTypes[$ext] === $mimeType) {
            $validMimeType = true;
            break;
        }
    }
    
    if (!$validMimeType) {
        $result['message'] = 'Geçersiz dosya türü!';
        return $result;
    }
    
    // Dizin kontrolü
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            $result['message'] = 'Hedef dizin oluşturulamadı!';
            return $result;
        }
    }
    
    // Rastgele dosya adı oluştur
    $filename = md5(uniqid() . time()) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $targetPath = $targetDir . '/' . $filename;
    
    // Dosyayı taşı
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $result['message'] = 'Dosya yüklenemedi!';
        return $result;
    }
    
    $result['success'] = true;
    $result['message'] = 'Dosya başarıyla yüklendi!';
    $result['filename'] = $filename;
    $result['filepath'] = $targetPath;
    
    return $result;
}

/**
 * SEO dostu URL oluşturur
 * 
 * @param string $string URL yapılacak metin
 * @return string SEO dostu URL
 */
function createSlug($string) {
    $turkishChars = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
    $englishChars = ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'];
    
    $string = str_replace($turkishChars, $englishChars, $string);
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', ' ', $string);
    $string = trim($string);
    $string = str_replace(' ', '-', $string);
    
    return $string;
}

/**
 * Sayfalama bağlantıları oluşturur
 * 
 * @param int $totalItems Toplam öğe sayısı
 * @param int $itemsPerPage Sayfa başına öğe sayısı
 * @param int $currentPage Mevcut sayfa
 * @param string $url Sayfalama URL'si
 * @return string HTML çıktısı
 */
function generatePagination($totalItems, $itemsPerPage, $currentPage, $url) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    if ($totalPages <= 1) {
        return '';
    }
    
    $output = '<nav aria-label="Sayfalama"><ul class="pagination">';
    
    // Önceki sayfa
    if ($currentPage > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($currentPage - 1) . '">&laquo; Önceki</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><span class="page-link">&laquo; Önceki</span></li>';
    }
    
    // Sayfa numaraları
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=1">1</a></li>';
        if ($startPage > 2) {
            $output .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $output .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $output .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Sonraki sayfa
    if ($currentPage < $totalPages) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($currentPage + 1) . '">Sonraki &raquo;</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><span class="page-link">Sonraki &raquo;</span></li>';
    }
    
    $output .= '</ul></nav>';
    
    return $output;
}