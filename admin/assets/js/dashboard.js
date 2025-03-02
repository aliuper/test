/**
 * Dashboard Script
 */

// Grafik renkleri
const chartColors = {
    primary: '#ff6b6b',
    primaryLight: 'rgba(255, 107, 107, 0.2)',
    secondary: '#3d5af1',
    secondaryLight: 'rgba(61, 90, 241, 0.2)',
    success: '#28a745',
    successLight: 'rgba(40, 167, 69, 0.2)',
    info: '#17a2b8',
    infoLight: 'rgba(23, 162, 184, 0.2)',
    warning: '#ffc107',
    warningLight: 'rgba(255, 193, 7, 0.2)',
    danger: '#dc3545',
    dangerLight: 'rgba(220, 53, 69, 0.2)',
    dark: '#343a40',
    darkLight: 'rgba(52, 58, 64, 0.2)'
};

// Satış grafiği
let salesChart;
let tableStatusChart;

/**
 * Satış grafiğini oluştur
 */
function initSalesChart() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Günlük Satış',
                data: [],
                backgroundColor: chartColors.primaryLight,
                borderColor: chartColors.primary,
                borderWidth: 2,
                pointBackgroundColor: chartColors.primary,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    titleFont: {
                        size: 14
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' ₺';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value + ' ₺';
                        }
                    }
                }
            }
        }
    });
    
    // İlk veri yüklemesi
    fetchSalesData(7);
}

/**
 * Satış grafiği için veri çek
 * 
 * @param {number} days Gün sayısı
 */
function fetchSalesData(days = 7) {
    // Yükleniyor göster
    document.getElementById('salesChartLoading').style.display = 'block';
    
    fetch('ajax/get-sales-chart-data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.getElementById('csrf_token').value
        },
        body: JSON.stringify({
            days: days
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Grafiği güncelle
            salesChart.data.labels = data.labels;
            salesChart.data.datasets[0].data = data.values;
            salesChart.update();
        } else {
            console.error('Veri çekilemedi:', data.message);
            showToast('Veri çekilemedi: ' + data.message, 'error');
        }
        
        // Yükleniyor gizle
        document.getElementById('salesChartLoading').style.display = 'none';
    })
    .catch(error => {
        console.error('Veri çekme hatası:', error);
        showToast('Veri çekilemedi.', 'error');
        
        // Yükleniyor gizle
        document.getElementById('salesChartLoading').style.display = 'none';
    });
}

/**
 * Masa durumları grafiğini oluştur
 */
function initTableStatusChart() {
    const ctx = document.getElementById('tableStatusChart').getContext('2d');
    
    // Masa durumları
    tableStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Boş', 'Dolu', 'Rezerve', 'Bakımda'],
            datasets: [{
                data: [0, 0, 0, 0],
                backgroundColor: [
                    chartColors.success,
                    chartColors.danger,
                    chartColors.warning,
                    chartColors.secondary
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    });
    
    // Masa durumları verilerini çek
    fetchTableStatusData();
}

/**
 * Masa durumları için veri çek
 */
function fetchTableStatusData() {
    // Yükleniyor göster
    document.getElementById('tableChartLoading').style.display = 'block';
    
    fetch('ajax/get-table-status-data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.getElementById('csrf_token').value
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Grafiği güncelle
            tableStatusChart.data.datasets[0].data = [
                data.available,
                data.occupied,
                data.reserved,
                data.maintenance
            ];
            tableStatusChart.update();
        } else {
            console.error('Veri çekilemedi:', data.message);
            showToast('Veri çekilemedi: ' + data.message, 'error');
        }
        
        // Yükleniyor gizle
        document.getElementById('tableChartLoading').style.display = 'none';
    })
    .catch(error => {
        console.error('Veri çekme hatası:', error);
        showToast('Veri çekilemedi.', 'error');
        
        // Yükleniyor gizle
        document.getElementById('tableChartLoading').style.display = 'none';
    });
}

// Sayfa yüklendiğinde çalış
document.addEventListener('DOMContentLoaded', function() {
    // Satış grafiği
    if (document.getElementById('salesChart')) {
        initSalesChart();
    }
    
    // Masa durumları grafiği
    if (document.getElementById('tableStatusChart')) {
        initTableStatusChart();
    }
    
    // Tarih aralığı seçimi
    document.querySelectorAll('.dropdown-item[data-range]').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            const days = parseInt(this.dataset.range);
            
            // Dropdown buton metnini güncelle
            document.getElementById('dropdownMenuButton').textContent = this.textContent;
            
            // Verileri çek
            fetchSalesData(days);
        });
    });
    
    // 5 dakikada bir verileri güncelle
    setInterval(function() {
        if (salesChart) {
            const days = document.querySelector('.dropdown-item[data-range].active')?.dataset.range || 7;
            fetchSalesData(days);
        }
        
        if (tableStatusChart) {
            fetchTableStatusData();
        }
    }, 300000); // 5 dakika
});