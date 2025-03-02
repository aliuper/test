/**
 * Admin Panel JavaScript
 */

// Ürün resmi önizleme
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
            document.getElementById(previewId).style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Onay kutusu
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Aktif tab hatırlama
function rememberTab() {
    // Aktif tabı localStorage'a kaydet
    $('.nav-tabs a').on('click', function() {
        localStorage.setItem('activeTab', $(this).attr('href'));
    });
    
    // Sayfa yüklendiğinde aktif tabı kontrol et ve aktifleştir
    var activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
        $('.nav-tabs a[href="' + activeTab + '"]').tab('show');
    }
}

// DataTable'ı etkinleştir
function initDataTable(tableId, options = {}) {
    if (typeof $.fn.DataTable !== 'undefined') {
        const defaultOptions = {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Turkish.json'
            },
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tümü"]]
        };
        
        const mergedOptions = {...defaultOptions, ...options};
        
        $(tableId).DataTable(mergedOptions);
    }
}

// Sıralama için drag-and-drop
function initSortable(listId, updateUrl) {
    if (typeof Sortable !== 'undefined') {
        var el = document.getElementById(listId);
        if (el) {
            var sortable = Sortable.create(el, {
                animation: 150,
                ghostClass: 'bg-light',
                onEnd: function(evt) {
                    const itemIds = Array.from(evt.from.children).map(item => item.dataset.id);
                    
                    // AJAX ile sıralama güncelleme
                    fetch(updateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            items: itemIds
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Başarılı
                            showToast('Sıralama başarıyla güncellendi.', 'success');
                        } else {
                            // Hata
                            showToast('Sıralama güncellenirken bir hata oluştu.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Bir hata oluştu.', 'error');
                    });
                }
            });
        }
    }
}

// Toast bildirimi göster
function showToast(message, type = 'info') {
    const toastId = 'toast-' + Math.random().toString(36).substr(2, 9);
    const toastClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
    
    const toast = `
    <div id="${toastId}" class="toast ${toastClass} text-white" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Bildirim</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Kapat"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
    </div>
    `;
    
    if (!document.getElementById('toastContainer')) {
        const toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    document.getElementById('toastContainer').innerHTML += toast;
    
    const toastElement = document.getElementById(toastId);
    const bsToast = new bootstrap.Toast(toastElement, {
        delay: 5000
    });
    
    bsToast.show();
    
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}

// Sipariş durumu güncelleme
function updateOrderStatus(orderId, status, callback) {
    fetch('ajax/update-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            order_id: orderId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sipariş durumu güncellendi: ' + data.status_text, 'success');
            if (typeof callback === 'function') {
                callback(data);
            }
        } else {
            showToast('Sipariş durumu güncellenirken bir hata oluştu.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Bir hata oluştu.', 'error');
    });
}

// Ürün durumu güncelleme
function updateProductStatus(productId, status, callback) {
    fetch('ajax/update-product-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            product_id: productId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Ürün durumu güncellendi.', 'success');
            if (typeof callback === 'function') {
                callback(data);
            }
        } else {
            showToast('Ürün durumu güncellenirken bir hata oluştu.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Bir hata oluştu.', 'error');
    });
}

// Sayfa yüklendiğinde çalışacak fonksiyonlar
document.addEventListener('DOMContentLoaded', function() {
    // Aktif tab hatırlaması
    rememberTab();
    
    // DataTable başlatma
    if (document.querySelector('.data-table')) {
        initDataTable('.data-table');
    }
    
    // Form doğrulama
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
});

// CSRF token yenileme (AJAX kullanımı için)
function refreshCsrfToken() {
    return fetch('ajax/refresh-csrf-token.php')
        .then(response => response.json())
        .then(data => {
            // Sayfadaki tüm CSRF token inputlarını güncelle
            document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                input.value = data.token;
            });
            return data.token;
        })
        .catch(error => {
            console.error('CSRF token yenilenirken hata oluştu:', error);
            return null;
        });
}

// AJAX form gönderimi
function submitFormAjax(formId, successCallback, errorCallback) {
    const form = document.getElementById(formId);
    
    if (!form) {
        console.error('Form bulunamadı: ' + formId);
        return;
    }
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        
        // Form verilerini al
        const formData = new FormData(form);
        
        // AJAX isteği gönder
        fetch(form.action, {
            method: form.method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof successCallback === 'function') {
                    successCallback(data);
                } else {
                    showToast(data.message || 'İşlem başarılı.', 'success');
                }
            } else {
                if (typeof errorCallback === 'function') {
                    errorCallback(data);
                } else {
                    showToast(data.message || 'Bir hata oluştu.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Bir hata oluştu.', 'error');
            
            if (typeof errorCallback === 'function') {
                errorCallback({success: false, message: 'Bir hata oluştu.'});
            }
        });
    });
}