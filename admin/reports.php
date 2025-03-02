<?php
/**
 * Admin Panel - Raporlar Sayfası
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Giriş yapılmamışsa login sayfasına yönlendir
requireLogin();

// Sadece süper admin erişebilir
if (!isSuperAdmin()) {
    redirect(ADMIN_URL . '/unauthorized.php');
}

// Sayfa başlığı
$pageTitle = 'Raporlar ve Analitik';

// Filtreleri al
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? sanitizeInput($_GET['report_type']) : 'sales';

// Rapor verilerini getir
switch ($reportType) {
    case 'sales':
        // Günlük satış raporu
        $salesData = dbQuery("
            SELECT 
                DATE(created_at) as date, 
                COUNT(*) as order_count,
                SUM(total_amount) as total_sales
            FROM orders 
            WHERE 
                created_at BETWEEN ? AND ? 
                AND status NOT IN ('cancelled')
            GROUP BY DATE(created_at) 
            ORDER BY date
        ", [$startDate, $endDate . ' 23:59:59']);
        break;
        
    case 'products':
        // En çok satan ürünler
        $productData = dbQuery("
            SELECT 
                p.id, 
                p.name, 
                p.price,
                p.category_id,
                c.name as category_name,
                COUNT(oi.id) as order_count,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.unit_price * oi.quantity) as total_sales
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            JOIN categories c ON p.category_id = c.id
            WHERE 
                o.created_at BETWEEN ? AND ? 
                AND o.status NOT IN ('cancelled')
            GROUP BY p.id
            ORDER BY total_quantity DESC
            LIMIT 20
        ", [$startDate, $endDate . ' 23:59:59']);
        break;
        
    case 'categories':
        // Kategori bazlı satışlar
        $categoryData = dbQuery("
            SELECT 
                c.id, 
                c.name, 
                COUNT(oi.id) as order_count,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.unit_price * oi.quantity) as total_sales
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            JOIN categories c ON p.category_id = c.id
            WHERE 
                o.created_at BETWEEN ? AND ? 
                AND o.status NOT IN ('cancelled')
            GROUP BY c.id
            ORDER BY total_sales DESC
        ", [$startDate, $endDate . ' 23:59:59']);
        break;
        
    case 'tables':
        // Masa bazlı satışlar
        $tableData = dbQuery("
            SELECT 
                t.id, 
                t.name, 
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_sales
            FROM orders o
            JOIN tables t ON o.table_id = t.id
            WHERE 
                o.created_at BETWEEN ? AND ? 
                AND o.status NOT IN ('cancelled')
            GROUP BY t.id
            ORDER BY total_sales DESC
        ", [$startDate, $endDate . ' 23:59:59']);
        break;
        
    case 'times':
        // Saat bazlı satışlar
        $timeData = dbQuery("
            SELECT 
                HOUR(created_at) as hour, 
                COUNT(*) as order_count,
                SUM(total_amount) as total_sales
            FROM orders 
            WHERE 
                created_at BETWEEN ? AND ? 
                AND status NOT IN ('cancelled')
            GROUP BY HOUR(created_at) 
            ORDER BY hour
        ", [$startDate, $endDate . ' 23:59:59']);
        break;
        
    default:
        // Varsayılan olarak günlük satış raporu
        $reportType = 'sales';
        $salesData = dbQuery("
            SELECT 
                DATE(created_at) as date, 
                COUNT(*) as order_count,
                SUM(total_amount) as total_sales
            FROM orders 
            WHERE 
                created_at BETWEEN ? AND ? 
                AND status NOT IN ('cancelled')
            GROUP BY DATE(created_at) 
            ORDER BY date
        ", [$startDate, $endDate . ' 23:59:59']);
        break;
}

// Özet istatistikler
$totalOrders = dbQuerySingle("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE 
        created_at BETWEEN ? AND ? 
        AND status NOT IN ('cancelled')
", [$startDate, $endDate . ' 23:59:59'])['count'] ?? 0;

$totalSales = dbQuerySingle("
    SELECT SUM(total_amount) as total 
    FROM orders 
    WHERE 
        created_at BETWEEN ? AND ? 
        AND status NOT IN ('cancelled')
", [$startDate, $endDate . ' 23:59:59'])['total'] ?? 0;

$averageOrder = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

$topProduct = dbQuerySingle("
    SELECT 
        p.name, 
        SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE 
        o.created_at BETWEEN ? AND ? 
        AND o.status NOT IN ('cancelled')
    GROUP BY p.id
    ORDER BY total_quantity DESC
    LIMIT 1
", [$startDate, $endDate . ' 23:59:59']);

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
<style>
    .report-card {
        transition: all 0.3s ease;
    }
    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .stat-card {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    .stat-card .card-body {
        position: relative;
        padding: 20px;
        z-index: 1;
    }
    .stat-card .stat-icon {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 3rem;
        opacity: 0.1;
        z-index: 0;
    }
    .stat-title {
        font-size: 1rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0;
    }
    .chart-container {
        position: relative;
        height: 400px;
    }
    .table-container {
        max-height: 500px;
        overflow-y: auto;
    }
    .report-tabs .nav-link {
        border-radius: 0;
        font-weight: 600;
    }
    .report-tabs .nav-link.active {
        background-color: var(--primary-color);
        color: white;
    }
</style>
';

// Ekstra JS
$extraJs = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.tr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
<script>
    // Tarih seçicileri
    $(".datepicker").datepicker({
        format: "yyyy-mm-dd",
        language: "tr",
        autoclose: true,
        todayHighlight: true
    });
    
    // Rapor türü değiştiğinde formu otomatik gönder
    $("#reportType").on("change", function() {
        $("#reportForm").submit();
    });
    
    // Grafik oluşturma (rapor tipine göre)
    let reportChart;
';

// Rapor tipine göre JS kodu ekle
switch ($reportType) {
    case 'sales':
        $extraJs .= '
    // Satış grafiği
    const salesCtx = document.getElementById("reportChart").getContext("2d");
    reportChart = new Chart(salesCtx, {
        type: "line",
        data: {
            labels: ' . json_encode(array_column($salesData, 'date')) . ',
            datasets: [{
                label: "Toplam Satış",
                data: ' . json_encode(array_column($salesData, 'total_sales')) . ',
                backgroundColor: "rgba(255, 107, 107, 0.2)",
                borderColor: "#ff6b6b",
                borderWidth: 2,
                pointBackgroundColor: "#ff6b6b",
                pointBorderColor: "#fff",
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.3,
                fill: true,
                yAxisID: "y"
            }, {
                label: "Sipariş Sayısı",
                data: ' . json_encode(array_column($salesData, 'order_count')) . ',
                backgroundColor: "rgba(61, 90, 241, 0.2)",
                borderColor: "#3d5af1",
                borderWidth: 2,
                pointBackgroundColor: "#3d5af1",
                pointBorderColor: "#fff",
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.3,
                fill: true,
                yAxisID: "y1"
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: "Tarih"
                    }
                },
                y: {
                    type: "linear",
                    display: true,
                    position: "left",
                    title: {
                        display: true,
                        text: "Toplam Satış (₺)"
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺";
                        }
                    }
                },
                y1: {
                    type: "linear",
                    display: true,
                    position: "right",
                    title: {
                        display: true,
                        text: "Sipariş Sayısı"
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || "";
                            if (label) {
                                label += ": ";
                            }
                            if (context.dataset.yAxisID === "y") {
                                return label + context.parsed.y.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺";
                            } else {
                                return label + context.parsed.y;
                            }
                        }
                    }
                }
            }
        }
    });
        ';
        break;
        
    case 'products':
        $extraJs .= '
    // Ürün grafiği
    const productsCtx = document.getElementById("reportChart").getContext("2d");
    reportChart = new Chart(productsCtx, {
        type: "bar",
        data: {
            labels: ' . json_encode(array_map(function($item) { return mb_substr($item['name'], 0, 20) . (mb_strlen($item['name']) > 20 ? '...' : ''); }, $productData)) . ',
            datasets: [{
                label: "Toplam Satış",
                data: ' . json_encode(array_column($productData, 'total_sales')) . ',
                backgroundColor: "#ff6b6b",
                borderColor: "#ff6b6b",
                borderWidth: 1,
                yAxisID: "y"
            }, {
                label: "Adet",
                data: ' . json_encode(array_column($productData, 'total_quantity')) . ',
                backgroundColor: "#3d5af1",
                borderColor: "#3d5af1",
                borderWidth: 1,
                yAxisID: "y1"
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: "Ürünler"
                    }
                },
                y: {
                    type: "linear",
                    display: true,
                    position: "left",
                    title: {
                        display: true,
                        text: "Toplam Satış (₺)"
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺";
                        }
                    }
                },
                y1: {
                    type: "linear",
                    display: true,
                    position: "right",
                    title: {
                        display: true,
                        text: "Satılan Adet"
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || "";
                            if (label) {
                                label += ": ";
                            }
                            if (context.dataset.yAxisID === "y") {
                                return label + context.parsed.y.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺";
                            } else {
                                return label + context.parsed.y;
                            }
                        }
                    }
                }
            }
        }
    });
        ';
        break;
        
    case 'categories':
        $extraJs .= '
    // Kategori grafiği
    const categoriesCtx = document.getElementById("reportChart").getContext("2d");
    reportChart = new Chart(categoriesCtx, {
        type: "doughnut",
        data: {
            labels: ' . json_encode(array_column($categoryData, 'name')) . ',
            datasets: [{
                data: ' . json_encode(array_column($categoryData, 'total_sales')) . ',
                backgroundColor: [
                    "#ff6b6b", "#3d5af1", "#00adb5", "#ffc107", "#6c5ce7",
                    "#fd79a8", "#00b894", "#fdcb6e", "#e84393", "#55efc4",
                    "#ff7675", "#74b9ff", "#a29bfe", "#fab1a0", "#81ecec"
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "right"
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || "";
                            let value = context.raw;
                            let total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(2) + "%";
                            return label + ": " + value.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺ (" + percentage + ")";
                        }
                    }
                }
            }
        }
    });
        ';
        break;
        
    case 'tables':
        $extraJs .= '
    // Masa grafiği
    const tablesCtx = document.getElementById("reportChart").getContext("2d");
    reportChart = new Chart(tablesCtx, {
        type: "pie",
        data: {
            labels: ' . json_encode(array_column($tableData, 'name')) . ',
            datasets: [{
                data: ' . json_encode(array_column($tableData, 'total_sales')) . ',
                backgroundColor: [
                    "#ff6b6b", "#3d5af1", "#00adb5", "#ffc107", "#6c5ce7",
                    "#fd79a8", "#00b894", "#fdcb6e", "#e84393", "#55efc4",
                    "#ff7675", "#74b9ff", "#a29bfe", "#fab1a0", "#81ecec"
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "right"
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || "";
                            let value = context.raw;
                            let total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(2) + "%";
                            return label + ": " + value.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺ (" + percentage + ")";
                        }
                    }
                }
            }
        }
    });
        ';
        break;
        
    case 'times':
        $extraJs .= '
    // Saat grafiği
    const timesCtx = document.getElementById("reportChart").getContext("2d");
    reportChart = new Chart(timesCtx, {
        type: "bar",
        data: {
            labels: ' . json_encode(array_map(function($item) { return $item['hour'] . ':00'; }, $timeData)) . ',
            datasets: [{
                label: "Toplam Satış",
                data: ' . json_encode(array_column($timeData, 'total_sales')) . ',
                backgroundColor: "#ff6b6b",
                borderColor: "#ff6b6b",
                borderWidth: 1,
                yAxisID: "y"
            }, {
                label: "Sipariş Sayısı",
                data: ' . json_encode(array_column($timeData, 'order_count')) . ',
                backgroundColor: "#3d5af1",
                borderColor: "#3d5af1",
                borderWidth: 1,
                yAxisID: "y1"
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: "Saat"
                    }
                },
                y: {
                    type: "linear",
                    display: true,
                    position: "left",
                    title: {
                        display: true,
                        text: "Toplam Satış (₺)"
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺";
                        }
                    }
                },
                y1: {
                    type: "linear",
                    display: true,
                    position: "right",
                    title: {
                        display: true,
                        text: "Sipariş Sayısı"
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || "";
                            if (label) {
                                label += ": ";
                            }
                            if (context.dataset.yAxisID === "y") {
                                return label + context.parsed.y.toLocaleString("tr-TR", { minimumFractionDigits: 2 }) + " ₺";
                            } else {
                                return label + context.parsed.y;
                            }
                        }
                    }
                }
            }
        }
    });
        ';
        break;
}

$extraJs .= '
</script>
';
?>

<div class="container-fluid">
    <!-- Filtre Formu -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Rapor Filtreleri</h5>
        </div>
        <div class="card-body">
            <form id="reportForm" method="get" action="<?= $_SERVER['PHP_SELF'] ?>" class="row g-3">
                <div class="col-md-3">
                    <label for="reportType" class="form-label">Rapor Türü</label>
                    <select class="form-select" id="reportType" name="report_type">
                        <option value="sales" <?= $reportType === 'sales' ? 'selected' : '' ?>>Günlük Satış Raporu</option>
                        <option value="products" <?= $reportType === 'products' ? 'selected' : '' ?>>Ürün Bazlı Satışlar</option>
                        <option value="categories" <?= $reportType === 'categories' ? 'selected' : '' ?>>Kategori Bazlı Satışlar</option>
                        <option value="tables" <?= $reportType === 'tables' ? 'selected' : '' ?>>Masa Bazlı Satışlar</option>
                        <option value="times" <?= $reportType === 'times' ? 'selected' : '' ?>>Saat Bazlı Satışlar</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="startDate" class="form-label">Başlangıç Tarihi</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                        <input type="text" class="form-control datepicker" id="startDate" name="start_date" value="<?= $startDate ?>" placeholder="YYYY-MM-DD">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label for="endDate" class="form-label">Bitiş Tarihi</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                        <input type="text" class="form-control datepicker" id="endDate" name="end_date" value="<?= $endDate ?>" placeholder="YYYY-MM-DD">
                    </div>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-2"></i> Filtrele
                    </button>
                    
                    <button type="button" class="btn btn-success export-report" data-type="excel">
                        <i class="fas fa-file-excel me-2"></i> Excel
                    </button>
                    
                    <button type="button" class="btn btn-danger ms-2 export-report" data-type="pdf">
                        <i class="fas fa-file-pdf me-2"></i> PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Özet İstatistikler -->
    <div class="row mb-4">
        <!-- Toplam Sipariş -->
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-white border-start border-5 border-primary">
                <div class="card-body">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h6 class="stat-title">Toplam Sipariş</h6>
                    <h3 class="stat-value"><?= number_format($totalOrders) ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Toplam Satış -->
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-white border-start border-5 border-success">
                <div class="card-body">
                    <div class="stat-icon text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h6 class="stat-title">Toplam Satış</h6>
                    <h3 class="stat-value"><?= formatCurrency($totalSales) ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Ortalama Sipariş Tutarı -->
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-white border-start border-5 border-info">
                <div class="card-body">
                    <div class="stat-icon text-info">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h6 class="stat-title">Ortalama Sipariş</h6>
                    <h3 class="stat-value"><?= formatCurrency($averageOrder) ?></h3>
                </div>
            </div>
        </div>
        
        <!-- En Çok Satan Ürün -->
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-white border-start border-5 border-warning">
                <div class="card-body">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h6 class="stat-title">En Çok Satan Ürün</h6>
                    <h3 class="stat-value"><?= !empty($topProduct) ? h($topProduct['name']) : '-' ?></h3>
                    <?php if (!empty($topProduct)): ?>
                        <small class="text-muted"><?= number_format($topProduct['total_quantity']) ?> adet</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Grafik ve Tablo -->
    <div class="row">
        <!-- Grafik -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($reportType === 'sales'): ?>
                            Günlük Satış Grafiği
                        <?php elseif ($reportType === 'products'): ?>
                            En Çok Satan Ürünler
                        <?php elseif ($reportType === 'categories'): ?>
                            Kategori Bazlı Satışlar
                        <?php elseif ($reportType === 'tables'): ?>
                            Masa Bazlı Satışlar
                        <?php elseif ($reportType === 'times'): ?>
                            Saat Bazlı Satışlar
                        <?php endif; ?>
                    </h5>
                    <div>
                        <span class="badge bg-primary">
                            <?= date('d.m.Y', strtotime($startDate)) ?> - <?= date('d.m.Y', strtotime($endDate)) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="reportChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tablo -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Rapor Detayları</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary export-table">
                        <i class="fas fa-download me-1"></i> İndir
                    </button>
                </div>
                <div class="card-body table-container">
                    <?php if ($reportType === 'sales'): ?>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Sipariş</th>
                                    <th>Satış</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($salesData)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Bu tarih aralığında veri bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($salesData as $item): ?>
                                        <tr>
                                            <td><?= date('d.m.Y', strtotime($item['date'])) ?></td>
                                            <td><?= number_format($item['order_count']) ?></td>
                                            <td><?= formatCurrency($item['total_sales']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Toplam</th>
                                    <th><?= number_format($totalOrders) ?></th>
                                    <th><?= formatCurrency($totalSales) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    <?php elseif ($reportType === 'products'): ?>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th>Adet</th>
                                    <th>Satış</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($productData)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Bu tarih aralığında veri bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($productData as $item): ?>
                                        <tr>
                                            <td><?= h($item['name']) ?></td>
                                            <td><?= number_format($item['total_quantity']) ?></td>
                                            <td><?= formatCurrency($item['total_sales']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php elseif ($reportType === 'categories'): ?>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Adet</th>
                                    <th>Satış</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categoryData)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Bu tarih aralığında veri bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categoryData as $item): ?>
                                        <tr>
                                            <td><?= h($item['name']) ?></td>
                                            <td><?= number_format($item['total_quantity']) ?></td>
                                            <td><?= formatCurrency($item['total_sales']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php elseif ($reportType === 'tables'): ?>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Masa</th>
                                    <th>Sipariş</th>
                                    <th>Satış</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tableData)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Bu tarih aralığında veri bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tableData as $item): ?>
                                        <tr>
                                            <td><?= h($item['name']) ?></td>
                                            <td><?= number_format($item['order_count']) ?></td>
                                            <td><?= formatCurrency($item['total_sales']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php elseif ($reportType === 'times'): ?>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Saat</th>
                                    <th>Sipariş</th>
                                    <th>Satış</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($timeData)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Bu tarih aralığında veri bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($timeData as $item): ?>
                                        <tr>
                                            <td><?= $item['hour'] ?>:00</td>
                                            <td><?= number_format($item['order_count']) ?></td>
                                            <td><?= formatCurrency($item['total_sales']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>