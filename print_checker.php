<?php
/**
 * Fiş Yazdırma Kontrol Sayfası
 * Bu sayfa, kasa bilgisayarında çalışır ve yazdırma kuyruğunu düzenli aralıklarla kontrol eder
 */

define('SECURITY', true);
if (!defined('SECURITY')) die('İzin Yok..!');
if (count($_POST) === 0) {
    $_SESSION['_csrf_token_admin'] = md5(time() . rand(0, 999999));
}
require_once '../../config.php';
require_once '../../function.php';
require_once '../includes/settings.php';
if (!isAuth()) header('Location: ../login.php');
$defaultLang = $setting['setting_default_lang'];

// Database connection
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// Get system settings
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT * FROM system_settings");
    $stmt->execute();
    $settings = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Get refresh interval
    $refreshInterval = isset($settings['refresh_interval']) ? intval($settings['refresh_interval']) : 30;
    
    // Get printer name
    $printerName = isset($settings['printer_name']) ? $settings['printer_name'] : 'POS-80';
    
    // Get company info
    $companyName = isset($settings['company_name']) ? $settings['company_name'] : 'Ateşli Piliçler';
    $companyAddress = isset($settings['company_address']) ? $settings['company_address'] : '';
    $companyPhone = isset($settings['company_phone']) ? $settings['company_phone'] : '0532 548 31 35';
    
    // Count pending print jobs
    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM print_queue WHERE printed = 0");
    $countStmt->execute();
    $pendingCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (PDOException $e) {
    $error = 'Veritabanı hatası: ' . $e->getMessage();
    $refreshInterval = 30;
    $printerName = 'POS-80';
    $companyName = 'Ateşli Piliçler';
    $companyAddress = '';
    $companyPhone = '0532 548 31 35';
    $pendingCount = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiş Yazdırma İzleme - <?= $companyName ?></title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Toastify for notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="pos.css">
    <style>
        body {
            padding: 0;
            margin: 0;
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
        }
        
        .active-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .active-indicator.active {
            background-color: #2ecc71;
            box-shadow: 0 0 5px #2ecc71;
            animation: pulse 2s infinite;
        }
        
        .active-indicator.inactive {
            background-color: #e74c3c;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(46, 204, 113, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0);
            }
        }
        
        .printer-icon {
            font-size: 4rem;
            color: #3498db;
            animation: printer 2s infinite;
        }
        
        @keyframes printer {
            0% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
            100% {
                transform: translateY(0);
            }
        }
        
        .settings-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .queue-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .queue-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }
        
        .queue-item:hover {
            background-color: #f8f9fa;
        }
        
        .queue-item:last-child {
            border-bottom: none;
        }
        
        .empty-queue {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
            font-style: italic;
        }
        
        .activity-log {
            max-height: 200px;
            overflow-y: auto;
            font-size: 0.8rem;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .log-entry {
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #ddd;
        }
        
        .log-time {
            color: #7f8c8d;
            margin-right: 5px;
        }
        
        .log-message {
            color: #2c3e50;
        }
        
        .log-success {
            color: #2ecc71;
        }
        
        .log-error {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="mb-0">
                        <i class="fas fa-print me-2 text-primary"></i>
                        Fiş Yazdırma İzleme
                    </h1>
                    <div class="status-indicator">
                        <span class="active-indicator <?= isset($error) ? 'inactive' : 'active' ?>"></span>
                        <span id="status-text"><?= isset($error) ? 'Bağlantı Hatası' : 'Aktif' ?></span>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $error ?>
                </div>
                <?php endif; ?>
                
                <div class="settings-section">
                    <div class="row">
                        <div class="col-md-6 text-center">
                            <i class="fas fa-print printer-icon mb-3"></i>
                            <h4><?= $printerName ?></h4>
                            <p class="text-muted">Yazıcı Durumu: <span id="printer-status">Bağlı</span></p>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-cog me-2"></i>Ayarlar</h5>
                            <div class="mb-3">
                                <label for="refresh-interval" class="form-label">Yenileme Sıklığı</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="refresh-interval" value="<?= $refreshInterval ?>" min="5" max="300">
                                    <span class="input-group-text">saniye</span>
                                    <button class="btn btn-primary" id="save-interval"><i class="fas fa-save"></i></button>
                                </div>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="auto-print" checked>
                                <label class="form-check-label" for="auto-print">Otomatik Yazdır</label>
                            </div>
                            <button class="btn btn-success" id="refresh-now">
                                <i class="fas fa-sync-alt me-2"></i>Şimdi Yenile
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="queue-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>
                            Yazdırma Kuyruğu 
                            <span class="badge bg-primary" id="queue-count"><?= $pendingCount ?></span>
                        </h5>
                        <button class="btn btn-sm btn-outline-danger" id="clear-queue" <?= $pendingCount == 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-trash me-1"></i>Tümünü Temizle
                        </button>
                    </div>
                    
                    <div id="queue-container">
                        <div class="empty-queue" <?= $pendingCount > 0 ? 'style="display:none"' : '' ?>>
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p>Yazdırma kuyruğunda bekleyen iş yok.</p>
                        </div>
                        <!-- Queue items will be loaded here -->
                    </div>
                    
                    <div class="activity-log mt-4">
                        <h6><i class="fas fa-history me-1"></i>Aktivite Günlüğü</h6>
                        <div id="log-container">
                            <div class="log-entry">
                                <span class="log-time"><?= date('H:i:s') ?></span>
                                <span class="log-message">Yazdırma izleme başlatıldı.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Receipt Modal for preview -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receiptModalLabel">Fiş Önizleme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="receipt-preview">
                    <!-- Receipt content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" id="print-receipt">Yazdır</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS and other libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js"></script>
    <script src="pos.js"></script>
    
    <script>
        // Global variables
        let refreshInterval = <?= $refreshInterval ?>;
        let refreshTimer = null;
        let autoPrint = true;
        let currentQueueId = null;
        
        // Sound effects
        const sounds = {
            notification: new Howl({ src: ['sounds/notification.mp3'], volume: 0.7 }),
            print: new Howl({ src: ['sounds/print.mp3'], volume: 0.5 }),
            error: new Howl({ src: ['sounds/error.mp3'], volume: 0.5 }),
            success: new Howl({ src: ['sounds/success.mp3'], volume: 0.5 })
        };
        
        // Document ready
        $(document).ready(function() {
            // Load queue on start
            loadPrintQueue();
            
            // Start refresh timer
            startRefreshTimer();
            
            // Event listeners
            $('#refresh-now').click(function() {
                loadPrintQueue();
                addLogEntry('Manuel yenileme başlatıldı.');
                sounds.success.play();
            });
            
            $('#save-interval').click(function() {
                const newInterval = parseInt($('#refresh-interval').val());
                if (newInterval >= 5 && newInterval <= 300) {
                    refreshInterval = newInterval;
                    startRefreshTimer();
                    
                    // Save to server
                    $.ajax({
                        url: 'save_setting.php',
                        method: 'POST',
                        data: {
                            setting_key: 'refresh_interval',
                            setting_value: refreshInterval
                        },
                        success: function(response) {
                            if (response.success) {
                                showToast('Yenileme sıklığı kaydedildi: ' + refreshInterval + ' saniye', 'success');
                                addLogEntry('Yenileme sıklığı ' + refreshInterval + ' saniye olarak ayarlandı.', 'success');
                            } else {
                                showToast('Ayar kaydedilemedi: ' + response.message, 'error');
                                addLogEntry('Ayar kaydedilemedi: ' + response.message, 'error');
                            }
                        },
                        error: function() {
                            showToast('Sunucu ile iletişim hatası', 'error');
                            addLogEntry('Sunucu ile iletişim hatası', 'error');
                            sounds.error.play();
                        }
                    });
                } else {
                    showToast('Lütfen 5-300 arası bir değer girin', 'error');
                    sounds.error.play();
                }
            });
            
            $('#auto-print').change(function() {
                autoPrint = $(this).prop('checked');
                addLogEntry('Otomatik yazdırma ' + (autoPrint ? 'etkinleştirildi' : 'devre dışı bırakıldı'));
            });
            
            $('#clear-queue').click(function() {
                clearPrintQueue();
            });
            
            // Print receipt when button clicked
            $('#print-receipt').click(function() {
                if (currentQueueId) {
                    printReceipt(currentQueueId);
                }
            });
        });
        
        // Start refresh timer
        function startRefreshTimer() {
            // Clear existing timer
            if (refreshTimer) {
                clearInterval(refreshTimer);
            }
            
            // Start new timer
            refreshTimer = setInterval(function() {
                loadPrintQueue();
            }, refreshInterval * 1000);
            
            addLogEntry('Yenileme zamanlayıcısı başlatıldı: ' + refreshInterval + ' saniye');
        }
        
        // Load print queue
        function loadPrintQueue() {
            $.ajax({
                url: 'check_print_queue.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        updateQueueDisplay(response.print_queue);
                        
                        // Update queue count
                        $('#queue-count').text(response.count);
                        
                        // Enable/disable clear queue button
                        if (response.count > 0) {
                            $('#clear-queue').prop('disabled', false);
                        } else {
                            $('#clear-queue').prop('disabled', true);
                        }
                        
                        // If auto print is enabled and there are items in the queue, print the first one
                        if (autoPrint && response.print_queue.length > 0) {
                            printReceipt(response.print_queue[0].id);
                        }
                    } else {
                        showToast('Kuyruk yüklenirken hata: ' + response.message, 'error');
                        addLogEntry('Kuyruk yüklenirken hata: ' + response.message, 'error');
                        sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    addLogEntry('Sunucu ile iletişim hatası', 'error');
                    sounds.error.play();
                    
                    // Update status indicator
                    $('.active-indicator').removeClass('active').addClass('inactive');
                    $('#status-text').text('Bağlantı Hatası');
                }
            });
        }
        
        // Update queue display
        function updateQueueDisplay(queue) {
            const container = $('#queue-container');
            const emptyQueue = $('.empty-queue');
            
            // Update status indicator
            $('.active-indicator').removeClass('inactive').addClass('active');
            $('#status-text').text('Aktif');
            
            if (queue.length > 0) {
                // Hide empty message
                emptyQueue.hide();
                
                // Generate queue items HTML
                let html = '';
                queue.forEach(function(item) {
                    const receiptData = JSON.parse(item.receipt_data);
                    const createdTime = new Date(item.created_at);
                    const now = new Date();
                    const diffMs = now - createdTime;
                    const diffMins = Math.floor(diffMs / 60000);
                    let timeAgo;
                    
                    if (diffMins > 0) {
                        timeAgo = diffMins + ' dakika önce';
                    } else {
                        timeAgo = 'Yeni eklendi';
                    }
                    
                    html += `
                        <div class="queue-item" data-id="${item.id}">
                            <div>
                                <h6 class="mb-0">${item.masa_adi}</h6>
                                <p class="mb-0 text-muted small">
                                    ${item.receipt_type === 'full' ? 'Tam Fiş' : (item.receipt_type === 'split' ? 'Bölünmüş Fiş' : 'Kişisel Fiş')} - 
                                    ${receiptData.totalAmount} - 
                                    ${timeAgo}
                                </p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-primary preview-btn" data-id="${item.id}">
                                    <i class="fas fa-eye me-1"></i>Önizle
                                </button>
                                <button class="btn btn-sm btn-success print-btn" data-id="${item.id}">
                                    <i class="fas fa-print me-1"></i>Yazdır
                                </button>
                                <button class="btn btn-sm btn-danger remove-btn" data-id="${item.id}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                // Update container
                container.html(html);
                
                // Add event listeners to buttons
                $('.preview-btn').click(function() {
                    const queueId = $(this).data('id');
                    previewReceipt(queueId);
                });
                
                $('.print-btn').click(function() {
                    const queueId = $(this).data('id');
                    printReceipt(queueId);
                });
                
                $('.remove-btn').click(function() {
                    const queueId = $(this).data('id');
                    removeFromQueue(queueId);
                });
                
                // Play notification sound for new items
                if ($('.queue-item').length > 0) {
                    // Only play if there wasn't already a notification
                    const lastNotification = localStorage.getItem('last_notification');
                    const currentTime = Date.now();
                    
                    if (!lastNotification || (currentTime - parseInt(lastNotification)) > 10000) {
                        sounds.notification.play();
                        localStorage.setItem('last_notification', currentTime);
                    }
                }
            } else {
                // Show empty message
                container.html('');
                emptyQueue.show();
            }
        }
        
        // Preview receipt
        function previewReceipt(queueId) {
            $.ajax({
                url: 'get_receipt.php',
                method: 'POST',
                data: { queue_id: queueId },
                success: function(response) {
                    if (response.success) {
                        // Parse receipt data
                        const receiptData = JSON.parse(response.receipt_data);
                        
                        // Generate receipt HTML
                        let receiptHtml = generateReceiptHtml(receiptData, response.receipt_type);
                        
                        // Update modal content
                        $('#receipt-preview').html(receiptHtml);
                        
                        // Set current queue ID
                        currentQueueId = queueId;
                        
                        // Show modal
                        $('#receiptModal').modal('show');
                        
                        addLogEntry(`Fiş önizleme gösteriliyor: ${response.masa_adi}`);
                    } else {
                        showToast('Fiş önizleme hatası: ' + response.message, 'error');
                        addLogEntry('Fiş önizleme hatası: ' + response.message, 'error');
                        sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    addLogEntry('Sunucu ile iletişim hatası', 'error');
                    sounds.error.play();
                }
            });
        }
        
        // Print receipt
        function printReceipt(queueId) {
            $.ajax({
                url: 'print_receipt.php',
                method: 'POST',
                data: { queue_id: queueId },
                success: function(response) {
                    if (response.success) {
                        showToast('Fiş yazdırıldı: ' + response.masa_adi, 'success');
                        addLogEntry('Fiş yazdırıldı: ' + response.masa_adi, 'success');
                        sounds.print.play();
                        
                        // Close modal if open
                        $('#receiptModal').modal('hide');
                        
                        // Reload queue
                        loadPrintQueue();
                        
                        // Attempt to send to system printer
                        const receiptData = JSON.parse(response.receipt_data);
                        const receiptHtml = generateReceiptHtml(receiptData, response.receipt_type);
                        
                        // Create a hidden iframe for printing
                        const iframe = document.createElement('iframe');
                        iframe.style.display = 'none';
                        document.body.appendChild(iframe);
                        
                        iframe.contentDocument.write(`
                            <html>
                                <head>
                                    <title>Print Receipt</title>
                                    <style>
                                        @media print {
                                            @page {
                                                size: 80mm auto;
                                                margin: 0;
                                            }
                                            body {
                                                margin: 0;
                                                font-family: 'Arial', sans-serif;
                                                font-size: 10pt;
                                            }
                                        }
                                    </style>
                                </head>
                                <body>${receiptHtml}</body>
                            </html>
                        `);
                        
                        iframe.contentDocument.close();
                        
                        // Print after a short delay to let the content load
                        setTimeout(function() {
                            iframe.contentWindow.print();
                            
                            // Remove the iframe after printing
                            setTimeout(function() {
                                document.body.removeChild(iframe);
                            }, 1000);
                        }, 500);
                    } else {
                        showToast('Fiş yazdırma hatası: ' + response.message, 'error');
                        addLogEntry('Fiş yazdırma hatası: ' + response.message, 'error');
                        sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    addLogEntry('Sunucu ile iletişim hatası', 'error');
                    sounds.error.play();
                }
            });
        }
        
        // Remove from queue
        function removeFromQueue(queueId) {
            $.ajax({
                url: 'remove_from_queue.php',
                method: 'POST',
                data: { queue_id: queueId },
                success: function(response) {
                    if (response.success) {
                        showToast('Fiş kuyruktan kaldırıldı', 'success');
                        addLogEntry('Fiş kuyruktan kaldırıldı', 'success');
                        
                        // Reload queue
                        loadPrintQueue();
                    } else {
                        showToast('Fiş kaldırma hatası: ' + response.message, 'error');
                        addLogEntry('Fiş kaldırma hatası: ' + response.message, 'error');
                        sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    addLogEntry('Sunucu ile iletişim hatası', 'error');
                    sounds.error.play();
                }
            });
        }
        
        // Clear print queue
        function clearPrintQueue() {
            $.ajax({
                url: 'clear_print_queue.php',
                method: 'POST',
                success: function(response) {
                    if (response.success) {
                        showToast('Yazdırma kuyruğu temizlendi', 'success');
                        addLogEntry('Yazdırma kuyruğu temizlendi: ' + response.count + ' öğe silindi', 'success');
                        
                        // Reload queue
                        loadPrintQueue();
                    } else {
                        showToast('Kuyruk temizleme hatası: ' + response.message, 'error');
                        addLogEntry('Kuyruk temizleme hatası: ' + response.message, 'error');
                        sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    addLogEntry('Sunucu ile iletişim hatası', 'error');
                    sounds.error.play();
                }
            });
        }
        
        // Generate receipt HTML
        function generateReceiptHtml(receiptData, receiptType) {
            let html = '';
            
            if (receiptType === 'full') {
                // Full receipt
                html = `
                    <div class="receipt-container mb-3">
                        <div class="receipt-header text-center">
                            <img src="../../../uploads/logo.png" alt="Logo" class="img-fluid mb-2" style="max-width: 80px;">
                            <h5 class="mb-0"><?= $companyName ?></h5>
                            <p class="small text-muted mb-0">Lezzetin Adresi</p>
                        </div>
                        
                        <div class="receipt-info d-flex justify-content-between border-top border-bottom py-2 my-2">
                            <div>
                                <div><small>Tarih: ${receiptData.dateTime ? receiptData.dateTime.split(' ')[0] : ''}</small></div>
                                <div><small>Saat: ${receiptData.dateTime ? receiptData.dateTime.split(' ')[1] : ''}</small></div>
                            </div>
                            <div class="text-end">
                                <div><small>Fiş No: #${receiptData.receiptNumber}</small></div>
                                <div><small>Masa: ${receiptData.tableName}</small></div>
                            </div>
                        </div>
                        
                        <table class="table table-sm receipt-table">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th class="text-center">Adet</th>
                                    <th class="text-end">Fiyat</th>
                                    <th class="text-end">Tutar</th>
                                </tr>
                            </thead>
                            <tbody>`;
                
                // Add items
                if (receiptData.items && receiptData.items.length > 0) {
                    receiptData.items.forEach(function(item) {
                        const price = parseFloat(item.price.replace("₺", ""));
                        const quantity = parseInt(item.quantity);
                        const total = price * quantity;
                        
                        html += `
                            <tr>
                                <td>${item.name}</td>
                                <td class="text-center">${quantity}</td>
                                <td class="text-end">${price.toFixed(2)}₺</td>
                                <td class="text-end">${total.toFixed(2)}₺</td>
                            </tr>`;
                    });
                }
                
                html += `
                            </tbody>
                        </table>
                        
                        <div class="receipt-totals border-top pt-2">
                            <div class="d-flex justify-content-between">
                                <span>Ara Toplam:</span>
                                <span>${receiptData.subtotal}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>KDV (${receiptData.taxRate}%):</span>
                                <span>${receiptData.taxAmount}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold fs-5 mt-2">
                                <span>TOPLAM:</span>
                                <span>${receiptData.totalAmount}</span>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3 border-top pt-3">
                            <div class="small">${receiptData.paymentMethod ? 'Ödeme: ' + receiptData.paymentMethod : ''}</div>
                            <p class="small mb-0">Teşekkür ederiz, yine bekleriz!</p>
                            <p class="small mb-0">Tel: <?= $companyPhone ?></p>
                        </div>
                    </div>
                `;
            } else if (receiptType === 'split') {
                // Split receipt
                html = `
                    <div class="receipt-container mb-3">
                        <div class="receipt-header text-center">
                            <img src="../../../uploads/logo.png" alt="Logo" class="img-fluid mb-2" style="max-width: 80px;">
                            <h5 class="mb-0"><?= $companyName ?></h5>
                            <p class="small text-muted mb-0">Lezzetin Adresi</p>
                            <div class="badge bg-primary mb-2">Hesap Bölüşümü</div>
                        </div>
                        
                        <div class="receipt-info d-flex justify-content-between border-top border-bottom py-2 my-2">
                            <div>
                                <div><small>Tarih: ${receiptData.dateTime ? receiptData.dateTime.split(' ')[0] : ''}</small></div>
                                <div><small>Saat: ${receiptData.dateTime ? receiptData.dateTime.split(' ')[1] : ''}</small></div>
                            </div>
                            <div class="text-end">
                                <div><small>Fiş No: #${receiptData.receiptNumber || Math.floor(Math.random() * 1000000)}</small></div>
                                <div><small>Masa: ${receiptData.tableName}</small></div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info text-center p-2">
                            Bu fiş, hesabın ${receiptData.peopleCount} kişi arasında eşit bölünmesi sonucunda düzenlenmiştir.
                        </div>
                        
                        <div class="card p-3 text-center">
                            <h5 class="mb-0">KİŞİ BAŞI ÖDENECEK:</h5>
                            <h3 class="mb-0">${receiptData.perPersonAmount}</h3>
                        </div>
                        
                        <div class="text-center mt-3 border-top pt-3">
                            <p class="small mb-0">Teşekkür ederiz, yine bekleriz!</p>
                            <p class="small mb-0">Tel: <?= $companyPhone ?></p>
                        </div>
                    </div>
                `;
            }
            
            return html;
        }
        
        // Show toast notification
        function showToast(message, type) {
            const colors = {
                success: '#2ecc71',
                error: '#e74c3c',
                warning: '#f39c12',
                info: '#3498db'
            };
            
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: colors[type] || colors.info,
                stopOnFocus: true
            }).showToast();
        }
        
        // Add log entry
        function addLogEntry(message, type = '') {
            const now = new Date();
            const timeStr = now.toTimeString().split(' ')[0];
            
            const logContainer = $('#log-container');
            const typeClass = type ? `log-${type}` : '';
            
            logContainer.prepend(`
                <div class="log-entry">
                    <span class="log-time">${timeStr}</span>
                    <span class="log-message ${typeClass}">${message}</span>
                </div>
            `);
            
            // Limit log entries to 50
            const entries = logContainer.find('.log-entry');
            if (entries.length > 50) {
                entries.slice(50).remove();
            }
        }
    </script>
</body>
</html>