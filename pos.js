/**
 * Ateşli Piliçler POS Sistemi
 * Ana JavaScript Dosyası
 * 
 * @version 2.0
 */

// Ana POS nesnesi - tüm fonksiyonları içerir
const POS = {
    // Ayarlar
    config: {
        soundEnabled: true,
        refreshInterval: 30, // saniye
        autoRefreshEnabled: true,
        refreshTimer: null,
        currentMasaId: null,
        currentTableName: '',
        currentTableStatus: 0,
        apiEndpoint: '',
        companyInfo: {
            name: 'Ateşli Piliçler',
            address: 'Adres bilgisi',
            phone: '0532 548 31 35',
            taxId: '1234567890'
        }
    },

    // Ses efektleri
    sounds: {
        buttonClick: null,
        addItem: null,
        removeItem: null,
        payment: null,
        print: null,
        error: null,
        init: function () {
            if (typeof Howl !== 'undefined') {
                this.buttonClick = new Howl({ src: ['sounds/click.mp3'], volume: 0.5 });
                this.addItem = new Howl({ src: ['sounds/add-item.mp3'], volume: 0.5 });
                this.removeItem = new Howl({ src: ['sounds/remove-item.mp3'], volume: 0.5 });
                this.payment = new Howl({ src: ['sounds/payment.mp3'], volume: 0.5 });
                this.print = new Howl({ src: ['sounds/print.mp3'], volume: 0.5 });
                this.error = new Howl({ src: ['sounds/error.mp3'], volume: 0.5 });
            }
        },
        play: function (sound) {
            if (POS.config.soundEnabled && this[sound]) {
                this[sound].play();
            }
        }
    },

    // Yardımcı fonksiyonlar
    utils: {
        formatCurrency: function (amount) {
            return parseFloat(amount).toFixed(2) + '₺';
        },

        formatDate: function (dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR');
        },

        formatTime: function (dateString) {
            const date = new Date(dateString);
            return date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        },

        getRandomReceiptNumber: function () {
            return Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
        },

        // Zaman farkını insan dostu formatta döndür
        getTimeDiff: function (startDate) {
            if (!startDate) return '';

            const now = new Date();
            const start = new Date(startDate);
            const diffMs = now - start;

            // Milisaniyeden dönüşüm
            const diffSec = Math.floor(diffMs / 1000);
            const diffMin = Math.floor(diffSec / 60);
            const diffHour = Math.floor(diffMin / 60);

            if (diffHour > 0) {
                return `${diffHour} saat ${diffMin % 60} dk.`;
            } else if (diffMin > 0) {
                return `${diffMin} dakika`;
            } else {
                return `Yeni açıldı`;
            }
        },

        // HTML karakterleri temizleme - XSS güvenliği
        escapeHTML: function (str) {
            if (!str) return '';
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        },

        // AJAX istekleri için yardımcı fonksiyon
        ajax: function (url, method, data, successCallback, errorCallback) {
            $.ajax({
                url: url,
                method: method || 'GET',
                data: data || {},
                dataType: 'json',
                success: function (response) {
                    if (successCallback) successCallback(response);
                },
                error: function (xhr, status, error) {
                    console.error("AJAX Error:", error);
                    if (errorCallback) errorCallback(xhr, status, error);
                    POS.sounds.play('error');

                    // Kullanıcıya hata mesajını göster
                    Toastify({
                        text: "İşlem sırasında bir hata oluştu: " + error,
                        duration: 3000,
                        backgroundColor: "#e74c3c",
                    }).showToast();
                }
            });
        }
    },

    // Tablo/Masa işlemleri
    tables: {
        loadTables: function () {
            POS.utils.ajax('get_tables.php', 'GET', {}, function (response) {
                if (response.success) {
                    POS.tables.renderTables(response.tables);
                    POS.tables.updateTableTimers();
                }
            });
        },

        renderTables: function (tables) {
            const regularTablesContainer = document.getElementById('regular-tables-container');
            const takeawayTablesContainer = document.getElementById('takeaway-tables-container');

            if (!regularTablesContainer || !takeawayTablesContainer) return;

            // Düzenli masalar ve paket masaları ayır
            const regularTables = tables.filter(table => ![11, 12, 13, 14, 15].includes(parseInt(table.table_id)));
            const takeawayTables = tables.filter(table => [11, 12, 13, 14, 15].includes(parseInt(table.table_id)));

            // Düzenli masaları render et
            let regularHtml = '';
            regularTables.forEach(function (table) {
                const isBusy = table.durum == 1;
                regularHtml += `
                    <div class="table-card ${isBusy ? 'busy' : ''}" 
                         onclick="POS.tables.openTableModal(${table.table_id}, '${table.table_name}', ${table.durum})"
                         data-status="${table.durum}" 
                         data-type="regular"
                         data-opened="${table.opened_at || ''}">
                        <i class="fas fa-utensils table-icon ${isBusy ? 'busy' : ''}"></i>
                        <div class="table-name">${table.table_name}</div>
                        ${isBusy ? `<div class="table-badge">Dolu</div>` : ''}
                        ${isBusy && table.opened_at ? `<div class="table-timer" data-time="${table.opened_at}">
                            ${POS.utils.getTimeDiff(table.opened_at)}
                        </div>` : ''}
                    </div>
                `;
            });
            regularTablesContainer.innerHTML = regularHtml;

            // Paket masalarını render et
            let takeawayHtml = '';
            takeawayTables.forEach(function (table) {
                const isBusy = table.durum == 1;
                takeawayHtml += `
                    <div class="table-card ${isBusy ? 'busy' : ''}" 
                         onclick="POS.tables.openTableModal(${table.table_id}, '${table.table_name}', ${table.durum})"
                         data-status="${table.durum}" 
                         data-type="takeaway"
                         data-opened="${table.opened_at || ''}">
                        <i class="fas fa-shopping-bag table-icon ${isBusy ? 'busy' : ''}"></i>
                        <div class="table-name">${table.table_name}</div>
                        ${isBusy ? `<div class="table-badge">Hazırlanıyor</div>` : ''}
                        ${isBusy && table.opened_at ? `<div class="table-timer" data-time="${table.opened_at}">
                            ${POS.utils.getTimeDiff(table.opened_at)}
                        </div>` : ''}
                    </div>
                `;
            });
            takeawayTablesContainer.innerHTML = takeawayHtml;
        },

        updateTableTimers: function () {
            // Masalardaki süreleri güncelle
            const timers = document.querySelectorAll('.table-timer');

            timers.forEach(function (timer) {
                const time = timer.getAttribute('data-time');
                if (time) {
                    timer.textContent = POS.utils.getTimeDiff(time);
                }
            });
        },

        filterTables: function (filter) {
            POS.sounds.play('buttonClick');

            // Aktif sınıfını güncelle
            document.querySelectorAll('.category-btn').forEach(btn => {
                if (btn.getAttribute('data-filter') === filter) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            // Lokal depolamaya kaydet
            localStorage.setItem('pos_default_category', filter);

            const tables = document.querySelectorAll('.table-card');

            tables.forEach(table => {
                const status = table.getAttribute('data-status');
                const type = table.getAttribute('data-type');

                switch (filter) {
                    case 'all':
                        table.style.display = 'block';
                        break;
                    case 'busy':
                        table.style.display = status === '1' ? 'block' : 'none';
                        break;
                    case 'free':
                        table.style.display = status === '0' ? 'block' : 'none';
                        break;
                    case 'regular':
                        table.style.display = type === 'regular' ? 'block' : 'none';
                        break;
                    case 'takeaway':
                        table.style.display = type === 'takeaway' ? 'block' : 'none';
                        break;
                }
            });

            // Grup görünürlüğünü güncelle
            POS.tables.updateGroupVisibility();
        },

        updateGroupVisibility: function () {
            const regularGroup = document.getElementById('regular-tables');
            const takeawayGroup = document.getElementById('takeaway-tables');

            if (!regularGroup || !takeawayGroup) return;

            // Her gruptaki görünür masaları kontrol et
            const regularVisible = Array.from(regularGroup.querySelectorAll('.table-card')).some(table => {
                return table.style.display !== 'none';
            });

            const takeawayVisible = Array.from(takeawayGroup.querySelectorAll('.table-card')).some(table => {
                return table.style.display !== 'none';
            });

            // Grupları buna göre göster/gizle
            regularGroup.style.display = regularVisible ? 'block' : 'none';
            takeawayGroup.style.display = takeawayVisible ? 'block' : 'none';
        },

        openTableModal: function (tableId, tableName, status) {
            POS.sounds.play('buttonClick');

            // Mevcut masa bilgilerini ayarla
            POS.config.currentMasaId = tableId;
            POS.config.currentTableName = tableName;
            POS.config.currentTableStatus = status;

            // Modal içeriğini güncelle
            const modalTitle = document.getElementById('tableModalLabel');
            const modalIcon = document.getElementById('modal-table-icon');
            const modalName = document.getElementById('modal-table-name');
            const modalStatus = document.getElementById('modal-table-status');
            const openBtn = document.getElementById('openTableBtn');
            const viewBtn = document.getElementById('viewOrderBtn');
            const closeBtn = document.getElementById('closeTableBtn');

            if (!modalTitle || !modalIcon || !modalName || !modalStatus) return;

            modalTitle.innerHTML = `<i class="fas fa-utensils me-2"></i>${tableName} İşlemleri`;
            modalName.textContent = tableName;

            // Masa tipine göre simgeyi ayarla
            const isTakeaway = [11, 12, 13, 14, 15].includes(tableId);
            modalIcon.className = `fas fa-${isTakeaway ? 'shopping-bag' : 'utensils'} fa-3x mb-3`;

            // Duruma göre düğme görünürlüğü ve durum rozetini ayarla
            if (status == 1) {
                modalStatus.className = 'badge bg-danger mb-3';
                modalStatus.textContent = isTakeaway ? 'Hazırlanıyor' : 'Dolu';
                openBtn.style.display = 'none';
                viewBtn.style.display = 'block';
                closeBtn.style.display = 'block';
            } else {
                modalStatus.className = 'badge bg-success mb-3';
                modalStatus.textContent = 'Boş';
                openBtn.style.display = 'block';
                viewBtn.style.display = 'none';
                closeBtn.style.display = 'none';
            }

            // Modalı göster
            $('#tableModal').modal('show');
        },

        startAdisyon: function (tableId, tableName) {
            POS.sounds.play('buttonClick');

            // Modalı gizle
            $('#tableModal').modal('hide');

            // Eğer masa zaten açık değilse, veritabanındaki masa durumunu güncelle
            if (POS.config.currentTableStatus == 0) {
                POS.utils.ajax('update_table_status.php', 'POST', { masa_id: tableId }, function (response) {
                    if (response.success) {
                        console.log("Masa durumu güncellendi.");
                    }
                });
            }

            // POS menüsüne yönlendir
            setTimeout(function () {
                window.location.href = `posmenu.php?masa_id=${tableId}`;
            }, 300);
        },

        closeTable: function (tableId) {
            POS.sounds.play('buttonClick');

            // Kapatmadan önce onay al
            Swal.fire({
                title: 'Emin misiniz?',
                text: `${POS.config.currentTableName} masası kapatılacak. Tüm siparişler silinecek.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, Kapat',
                cancelButtonText: 'İptal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Modalı gizle
                    $('#tableModal').modal('hide');

                    // Masayı kapat
                    POS.utils.ajax('masa_kapat.php', 'POST', { masaId: tableId }, function (response) {
                        if (response.success) {
                            POS.sounds.play('payment');

                            Toastify({
                                text: `${POS.config.currentTableName} başarıyla kapatıldı`,
                                duration: 3000,
                                backgroundColor: "#2ecc71",
                            }).showToast();

                            // Kısa bir gecikmeden sonra sayfayı yenile
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                        }
                    });
                }
            });
        }
    },

    // Ayarlar
    settings: {
        loadSettings: function () {
            // localStorage'dan ayarları yükle
            const savedSound = localStorage.getItem('pos_sound_enabled');
            if (savedSound !== null) {
                POS.config.soundEnabled = savedSound === 'true';

                // Ses düğmesini güncelle
                const soundToggle = document.getElementById('sound-toggle');
                if (soundToggle) {
                    if (POS.config.soundEnabled) {
                        soundToggle.innerHTML = '<i class="fas fa-volume-up"></i>';
                        soundToggle.classList.remove('off');
                    } else {
                        soundToggle.innerHTML = '<i class="fas fa-volume-mute"></i>';
                        soundToggle.classList.add('off');
                    }
                }
            }

            // Yenileme aralığı ayarı
            const savedRefreshInterval = localStorage.getItem('pos_refresh_interval');
            if (savedRefreshInterval !== null) {
                POS.config.refreshInterval = parseInt(savedRefreshInterval);
            }

            // Otomatik yenileme ayarı
            const savedAutoRefresh = localStorage.getItem('pos_auto_refresh');
            if (savedAutoRefresh !== null) {
                POS.config.autoRefreshEnabled = savedAutoRefresh === 'true';

                // Otomatik yenilemeyi başlat/durdur
                if (POS.config.autoRefreshEnabled) {
                    POS.settings.startAutoRefresh();
                } else {
                    POS.settings.stopAutoRefresh();
                }
            }

            // Ayar formundaki kontrolleri güncelle
            const soundEffectsToggle = document.getElementById('soundEffectsToggle');
            if (soundEffectsToggle) {
                soundEffectsToggle.checked = POS.config.soundEnabled;
            }

            const autoRefreshToggle = document.getElementById('autoRefreshToggle');
            if (autoRefreshToggle) {
                autoRefreshToggle.checked = POS.config.autoRefreshEnabled;
            }

            const refreshIntervalSelect = document.getElementById('refreshIntervalSelect');
            if (refreshIntervalSelect) {
                refreshIntervalSelect.value = POS.config.refreshInterval;
            }

            // Varsayılan kategori ayarı
            const savedDefaultCategory = localStorage.getItem('pos_default_category');
            if (savedDefaultCategory) {
                const defaultCategorySelect = document.getElementById('defaultCategory');
                if (defaultCategorySelect) {
                    defaultCategorySelect.value = savedDefaultCategory;
                }

                // Varsayılan kategori filtresini uygula
                const categoryBtn = document.querySelector(`.category-btn[data-filter="${savedDefaultCategory}"]`);
                if (categoryBtn) {
                    document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
                    categoryBtn.classList.add('active');
                    POS.tables.filterTables(savedDefaultCategory);
                }
            }
        },

        saveSettings: function () {
            POS.sounds.play('buttonClick');

            // Form değerlerini al
            const soundEffects = document.getElementById('soundEffectsToggle').checked;
            const autoRefresh = document.getElementById('autoRefreshToggle').checked;
            const refreshInterval = parseInt(document.getElementById('refreshIntervalSelect').value);
            const defaultCategory = document.getElementById('defaultCategory').value;

            // localStorage'a kaydet
            localStorage.setItem('pos_sound_enabled', soundEffects);
            localStorage.setItem('pos_auto_refresh', autoRefresh);
            localStorage.setItem('pos_refresh_interval', refreshInterval);
            localStorage.setItem('pos_default_category', defaultCategory);

            // Global değişkenleri güncelle
            POS.config.soundEnabled = soundEffects;
            POS.config.autoRefreshEnabled = autoRefresh;
            POS.config.refreshInterval = refreshInterval;

            // Ses düğmesini güncelle
            const soundToggle = document.getElementById('sound-toggle');
            if (soundToggle) {
                if (POS.config.soundEnabled) {
                    soundToggle.innerHTML = '<i class="fas fa-volume-up"></i>';
                    soundToggle.classList.remove('off');
                } else {
                    soundToggle.innerHTML = '<i class="fas fa-volume-mute"></i>';
                    soundToggle.classList.add('off');
                }
            }

            // Otomatik yenilemeyi güncelle
            if (POS.config.autoRefreshEnabled) {
                POS.settings.startAutoRefresh();
            } else {
                POS.settings.stopAutoRefresh();
            }

            // Varsayılan kategoriyi uygula
            const categoryBtn = document.querySelector(`.category-btn[data-filter="${defaultCategory}"]`);
            if (categoryBtn) {
                document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
                categoryBtn.classList.add('active');
                POS.tables.filterTables(defaultCategory);
            }

            // Modalı gizle
            $('#settingsModal').modal('hide');

            // Başarı mesajı göster
            Toastify({
                text: "Ayarlar başarıyla kaydedildi",
                duration: 3000,
                backgroundColor: "#2ecc71",
            }).showToast();
        },

        toggleSound: function () {
            POS.config.soundEnabled = !POS.config.soundEnabled;

            const soundToggle = document.getElementById('sound-toggle');
            if (soundToggle) {
                if (POS.config.soundEnabled) {
                    soundToggle.innerHTML = '<i class="fas fa-volume-up"></i>';
                    soundToggle.classList.remove('off');
                    POS.sounds.play('buttonClick');
                } else {
                    soundToggle.innerHTML = '<i class="fas fa-volume-mute"></i>';
                    soundToggle.classList.add('off');
                }
            }

            // localStorage'a kaydet
            localStorage.setItem('pos_sound_enabled', POS.config.soundEnabled);

            Toastify({
                text: POS.config.soundEnabled ? "Ses efektleri açıldı" : "Ses efektleri kapatıldı",
                duration: 2000,
                gravity: "top",
                position: "right",
                backgroundColor: POS.config.soundEnabled ? "#2ecc71" : "#e74c3c",
            }).showToast();
        },

        startAutoRefresh: function () {
            // Önce mevcut zamanlayıcıyı temizle
            POS.settings.stopAutoRefresh();

            if (POS.config.autoRefreshEnabled) {
                POS.config.refreshTimer = setInterval(function () {
                    POS.tables.loadTables();
                }, POS.config.refreshInterval * 1000);
            }
        },

        stopAutoRefresh: function () {
            if (POS.config.refreshTimer) {
                clearInterval(POS.config.refreshTimer);
                POS.config.refreshTimer = null;
            }
        }
    },

    // Ürünler ve sipariş işlemleri
    orders: {
        addOrder: function (productId, productName, productPrice, masaId) {
            POS.sounds.play('addItem');

            var orderList = document.getElementById("order-list-container");
            if (!orderList) return;

            var existingItem = Array.from(orderList.getElementsByClassName("summary-item")).find(function (item) {
                return item.getAttribute('data-product-id') == productId;
            });

            if (existingItem) {
                var quantityElement = existingItem.querySelector('.quantity-value');
                var quantity = parseInt(quantityElement.innerText.trim());
                var newQuantity = quantity + 1;
                quantityElement.innerText = newQuantity;
                POS.orders.updateTotalPrice();

                // Veritabanında miktarı güncelle
                POS.utils.ajax('update_quantity.php', 'POST', {
                    masaId: masaId,
                    productId: productId,
                    quantity: newQuantity
                }, function (response) {
                    if (response.success) {
                        Toastify({
                            text: productName + " miktarı artırıldı",
                            duration: 3000,
                            backgroundColor: "#2ecc71",
                        }).showToast();
                    }
                });

                return;
            }

            // Yeni ürün ekle
            POS.utils.ajax('save_order.php', 'POST', {
                productId: productId,
                productName: productName,
                productPrice: productPrice,
                masaId: masaId
            }, function (response) {
                if (response.success) {
                    // Sipariş listesine yeni satır ekle
                    var newItem = document.createElement("div");
                    newItem.className = "summary-item";
                    newItem.setAttribute('data-product-id', productId);

                    newItem.innerHTML = `
                        <div class="item-details">
                            <div class="item-name">${productName}</div>
                            <div class="item-price">${productPrice.toFixed(2)}₺</div>
                        </div>
                        <div class="item-quantity">
                            <button class="quantity-btn" onclick="POS.orders.updateQuantity(${productId}, 'decrease', ${masaId})">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="quantity-value">1</span>
                            <button class="quantity-btn" onclick="POS.orders.updateQuantity(${productId}, 'increase', ${masaId})">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    `;

                    orderList.appendChild(newItem);
                    POS.orders.updateTotalPrice();

                    Toastify({
                        text: productName + " sepete eklendi",
                        duration: 3000,
                        backgroundColor: "#3498db",
                    }).showToast();
                }
            });
        },

        updateQuantity: function (productId, action, masaId) {
            POS.sounds.play('buttonClick');

            var item = document.querySelector(`.summary-item[data-product-id="${productId}"]`);
            if (!item) return;

            var quantityElement = item.querySelector('.quantity-value');
            var currentQuantity = parseInt(quantityElement.innerText);

            if (action === 'decrease') {
                if (currentQuantity === 1) {
                    // Miktar 1 ise, ürünü kaldır
                    POS.orders.removeOrder(productId, masaId);
                    return;
                }

                var newQuantity = currentQuantity - 1;
                quantityElement.innerText = newQuantity;
            } else if (action === 'increase') {
                var newQuantity = currentQuantity + 1;
                quantityElement.innerText = newQuantity;
            }

            // Veritabanında miktarı güncelle
            POS.utils.ajax('update_quantity.php', 'POST', {
                masaId: masaId,
                productId: productId,
                quantity: newQuantity
            }, function (response) {
                if (response.success) {
                    POS.orders.updateTotalPrice();
                }
            });
        },

        removeOrder: function (productId, masaId) {
            POS.sounds.play('removeItem');

            Swal.fire({
                title: 'Ürünü Sil',
                text: 'Bu ürünü sepetten çıkarmak istediğinize emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    POS.utils.ajax('remove_order.php', 'POST', {
                        productId: productId,
                        masaId: masaId
                    }, function (response) {
                        if (response.success) {
                            // DOM'dan öğeyi kaldır
                            var item = document.querySelector(`.summary-item[data-product-id="${productId}"]`);
                            if (item) {
                                item.remove();
                            }

                            // Toplam fiyatı güncelle
                            POS.orders.updateTotalPrice();

                            Toastify({
                                text: "Ürün sepetten çıkarıldı",
                                duration: 3000,
                                backgroundColor: "#e74c3c",
                            }).showToast();
                        }
                    });
                }
            });
        },

        clearOrder: function (masaId) {
            POS.sounds.play('buttonClick');

            // Onay al
            Swal.fire({
                title: 'Tüm Siparişleri Sil',
                text: 'Tüm sipariş öğeleri silinecek! Emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, Temizle',
                cancelButtonText: 'İptal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    POS.sounds.play('removeItem');

                    // Veritabanından temizle
                    POS.utils.ajax('clear_orders.php', 'POST', {
                        masaId: masaId
                    }, function (response) {
                        if (response.success) {
                            // UI'dan temizle
                            var orderList = document.getElementById('order-list-container');
                            if (orderList) {
                                orderList.innerHTML = '';
                            }

                            // Toplam fiyatı güncelle
                            POS.orders.updateTotalPrice();

                            Toastify({
                                text: "Tüm siparişler temizlendi",
                                duration: 3000,
                                backgroundColor: "#e74c3c",
                            }).showToast();
                        }
                    });
                }
            });
        },

        updateTotalPrice: function () {
            var totalPrice = 0;
            var orderItems = document.querySelectorAll(".summary-item");

            orderItems.forEach(function (item) {
                var priceElement = item.querySelector(".item-price");
                var price = parseFloat(priceElement.innerText.replace("₺", "").trim());
                var quantityElement = item.querySelector(".quantity-value");
                var quantity = parseInt(quantityElement.innerText.trim());
                totalPrice += price * quantity;
            });

            // KDV hesapla (8%)
            var tax = totalPrice * 0.08;
            var subtotal = totalPrice - tax;

            // Görüntülenen toplamları güncelle
            var subtotalElement = document.getElementById("subtotal");
            var taxElement = document.getElementById("tax");
            var totalElement = document.getElementById("total-price");

            if (subtotalElement) subtotalElement.innerText = subtotal.toFixed(2) + "₺";
            if (taxElement) taxElement.innerText = tax.toFixed(2) + "₺";
            if (totalElement) totalElement.innerText = totalPrice.toFixed(2) + "₺";

            return totalPrice;
        }
    },

    // Ödeme işlemleri
    payment: {
        odemeYap: function (masaId, odemeYontemi) {
            POS.sounds.play('payment');

            // Toplam tutarı güncelle
            var totalAmount = POS.orders.updateTotalPrice();
            var formattedAmount = totalAmount.toFixed(2) + "₺";
            var masaAdi = "Masa " + masaId;

            POS.utils.ajax('kaydet.php', 'POST', {
                masaAdi: masaAdi,
                toplamFiyat: formattedAmount,
                odemeYontemi: odemeYontemi
            }, function (response) {
                if (response.success) {
                    Toastify({
                        text: "Ödeme başarıyla alındı: " + formattedAmount,
                        duration: 3000,
                        backgroundColor: "#2ecc71",
                    }).showToast();

                    // Onay modalını göster
                    $('#confirmModal').modal('show');
                }
            });
        },

        fisYazdir: function (masaId, dateTime) {
            POS.sounds.play('print');

            // Sipariş verilerini al
            var totalAmount = POS.orders.updateTotalPrice().toFixed(2) + "₺";
            var orderItems = Array.from(document.querySelectorAll(".summary-item")).map(function (item) {
                var productName = item.querySelector(".item-name").innerText;
                var price = item.querySelector(".item-price").innerText;
                var quantity = item.querySelector(".quantity-value").innerText;
                var productId = item.getAttribute('data-product-id');

                return {
                    product_id: productId,
                    name: productName,
                    price: price,
                    quantity: quantity
                };
            });

            // Fiş verilerini hazırla
            var receiptData = {
                receiptNumber: POS.utils.getRandomReceiptNumber(),
                tableId: masaId,
                tableName: "Masa " + masaId,
                dateTime: dateTime,
                items: orderItems,
                totalAmount: totalAmount,
                taxRate: 8,
                taxAmount: (POS.orders.updateTotalPrice() * 0.08).toFixed(2) + "₺",
                subtotal: (POS.orders.updateTotalPrice() * 0.92).toFixed(2) + "₺",
                paymentMethod: "Belirsiz"
            };

            // Fiş yazdırma isteğini veritabanına ekle
            POS.utils.ajax('add_print_queue.php', 'POST', {
                masa_id: masaId,
                masa_adi: "Masa " + masaId,
                receipt_type: 'full',
                receipt_data: JSON.stringify(receiptData)
            }, function (response) {
                if (response.success) {
                    Toastify({
                        text: "Fiş yazdırma isteği gönderildi",
                        duration: 3000,
                        backgroundColor: "#f1c40f",
                    }).showToast();

                    // Lokal tarayıcıda yazdırma önizlemesi göster
                    POS.payment.showPrintPreview(receiptData);
                }
            });
        },

        showPrintPreview: function (receiptData) {
            // Fiş içeriğini oluştur
            var printContent = `
            <style type="text/css" media="print">
                @page {
                    size: 80mm auto;
                    margin: 0;
                }
                body {
                    margin: 0;
                    font-family: 'Arial', sans-serif;
                    padding: 5mm;
                }
                .receipt {
                    width: 70mm;
                    max-width: 100%;
                    margin: 0 auto;
                }
                .receipt-header {
                    text-align: center;
                    margin-bottom: 5mm;
                }
                .receipt-logo {
                    max-width: 30mm;
                    margin-bottom: 2mm;
                }
                .receipt-title {
                    font-size: 14pt;
                    font-weight: bold;
                    margin-bottom: 1mm;
                }
                .receipt-subtitle {
                    font-size: 10pt;
                    color: #666;
                    margin-bottom: 1mm;
                }
                .receipt-info {
                    display: flex;
                    flex-direction: column;
                    margin-bottom: 5mm;
                    font-size: 9pt;
                    padding: 2mm 0;
                    border-top: 1px dashed #000;
                    border-bottom: 1px dashed #000;
                }
                .receipt-info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 1mm;
                }
                .receipt-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 5mm;
                    font-size: 9pt;
                }
                .receipt-table th {
                    text-align: left;
                    padding: 1mm 0;
                    border-bottom: 1px solid #000;
                }
                .receipt-table td {
                    padding: 1mm 0;
                }
                .receipt-table .quantity {
                    text-align: center;
                    width: 15%;
                }
                .receipt-table .price {
                    text-align: right;
                    width: 25%;
                }
                .receipt-table .total {
                    text-align: right;
                    width: 25%;
                }
                .receipt-totals {
                    margin-bottom: 5mm;
                    font-size: 9pt;
                }
                .receipt-total-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 1mm;
                }
                .receipt-grand-total {
                    font-weight: bold;
                    font-size: 12pt;
                    display: flex;
                    justify-content: space-between;
                    margin-top: 2mm;
                    padding-top: 2mm;
                    border-top: 1px solid #000;
                }
                .receipt-footer {
                    text-align: center;
                    margin-top: 5mm;
                    font-size: 9pt;
                    padding-top: 2mm;
                    border-top: 1px dashed #000;
                }
                .receipt-qrcode {
                    text-align: center;
                    margin: 5mm 0;
                }
                .receipt-barcode {
                    text-align: center;
                    margin: 3mm 0;
                    font-family: 'Courier', monospace;
                    font-size: 10pt;
                }
                @media print {
                    .no-print {
                        display: none;
                    }
                }
            </style>
            <div class="receipt">
                <div class="receipt-header">
                    <img src="https://ateslipilicler.com/admin/pos/fislogo.png" alt="Logo" class="receipt-logo">
                    <div class="receipt-title">Ateşli Piliçlerrrrrrrrrrrr</div>
                    <div class="receipt-subtitle">Lezzetin Ateşli Hali</div>
                </div>
                
                <div class="receipt-info">
                    <div class="receipt-info-row">
                        <div>Tarih:</div>
                        <div>${receiptData.dateTime.split(' ')[0]}</div>
                    </div>
                    <div class="receipt-info-row">
                        <div>Saat:</div>
                        <div>${receiptData.dateTime.split(' ')[1]}</div>
                    </div>
                    <div class="receipt-info-row">
                        <div>Fiş No:</div>
                        <div>#${receiptData.receiptNumber}</div>
                    </div>
                    <div class="receipt-info-row">
                        <div>Masa:</div>
                        <div>${receiptData.tableName}</div>
                    </div>
                </div>
                
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Ürün</th>
                            <th class="quantity">Adet</th>
                            
                            <th class="total">Fiyat</th>
                        </tr>
                    </thead>
                    <tbody>`;

            // Ürünleri ekle
            receiptData.items.forEach(function (item) {
                const price = parseFloat(item.price.replace("₺", ""));
                const quantity = parseInt(item.quantity);
                const total = price * quantity;

                printContent += `
                    <tr>
                        <td>${item.name}</td>
                        <td class="quantity">${quantity}</td>
                       
                        <td class="total">${total.toFixed(2)}₺</td>
                    </tr>`;
            });

            // Alt toplamları ekle
            printContent += `
                </tbody>
            </table>
            
            <div class="receipt-totals">
                <div class="receipt-total-row">
                    <div>Ara Toplam:</div>
                    <div>${receiptData.subtotal}</div>
                </div>
                <div class="receipt-total-row">
                    <div>KDV (${receiptData.taxRate}%):</div>
                    <div>${receiptData.taxAmount}</div>
                </div>
                <div class="receipt-grand-total">
                    <div>TOPLAM:</div>
                    <div>${receiptData.totalAmount}</div>
                </div>
            </div>
            
            <div class="receipt-barcode">
                *${receiptData.tableId}${receiptData.receiptNumber}*
            </div>
            
            <div class="receipt-footer">
                <p>Teşekkür ederiz, yine bekleriz!</p>
                <p>Tel: 0532 548 31 35</p>
            </div>
            
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">Yazdır</button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Kapat</button>
            </div>
        </div>`;

            // Yeni pencerede göster
            var printWindow = window.open('', '_blank', 'width=400,height=600');
            printWindow.document.write(printContent);
            printWindow.document.close();
        },

        showSplitBillModal: function () {
            POS.sounds.play('buttonClick');

            // Formları sıfırla
            var evenSplitForm = document.getElementById('even-split-form');
            if (evenSplitForm) evenSplitForm.style.display = 'none';

            // Seçimi sıfırla
            var splitOptions = document.querySelectorAll('.split-option');
            splitOptions.forEach(option => {
                option.classList.remove('selected');
            });

            // Modal göster
            $('#splitBillModal').modal('show');
        },

        selectSplitOption: function (option) {
            POS.sounds.play('buttonClick');

            // Seçimi güncelle
            var splitOptions = document.querySelectorAll('.split-option');
            splitOptions.forEach(opt => {
                if (opt.querySelector('.split-title').innerText.includes(option === 'even' ? 'Eşit' : 'Kişisel')) {
                    opt.classList.add('selected');
                } else {
                    opt.classList.remove('selected');
                }
            });

            // İlgili formu göster
            if (option === 'even') {
                document.getElementById('even-split-form').style.display = 'block';
            }
        },

        calculateEvenSplit: function () {
            POS.sounds.play('buttonClick');

            var peopleCount = parseInt(document.getElementById('split-people-count').value);

            if (peopleCount < 2) {
                Toastify({
                    text: "Lütfen en az 2 kişi girin",
                    duration: 3000,
                    backgroundColor: "#e74c3c",
                }).showToast();
                return;
            }

            // Toplam tutarı al
            var totalAmount = POS.orders.updateTotalPrice();

            // Kişi başı tutarı hesapla
            var perPersonAmount = totalAmount / peopleCount;

            // Sonucu görüntüle
            var resultDiv = document.getElementById('even-split-result');
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <h5>Hesap Bölünme Sonucu</h5>
                    <p>Toplam Hesap: ${totalAmount.toFixed(2)}₺</p>
                    <p>Kişi Sayısı: ${peopleCount}</p>
                    <p class="lead">Kişi Başı: <strong>${perPersonAmount.toFixed(2)}₺</strong></p>
                </div>
            `;
        },

        printSplitReceipts: function (masaId) {
            POS.sounds.play('print');

            // Kişi sayısını al
            var peopleCount = parseInt(document.getElementById('split-people-count').value);

            if (peopleCount < 2) {
                Toastify({
                    text: "Lütfen en az 2 kişi girin",
                    duration: 3000,
                    backgroundColor: "#e74c3c",
                }).showToast();
                return;
            }

            // Toplam tutarı al
            var totalAmount = POS.orders.updateTotalPrice();

            // Kişi başı tutarı hesapla
            var perPersonAmount = totalAmount / peopleCount;

            // Sipariş öğelerini al
            var orderItems = Array.from(document.querySelectorAll(".summary-item")).map(function (item) {
                var productName = item.querySelector(".item-name").innerText;
                var price = item.querySelector(".item-price").innerText;
                var quantity = item.querySelector(".quantity-value").innerText;
                var productId = item.getAttribute('data-product-id');

                return {
                    product_id: productId,
                    name: productName,
                    price: price,
                    quantity: quantity
                };
            });

            // Tüm fişleri tek bir istek olarak gönder
            var receiptData = {
                splitType: 'even',
                peopleCount: peopleCount,
                perPersonAmount: perPersonAmount.toFixed(2) + "₺",
                tableId: masaId,
                tableName: "Masa " + masaId,
                dateTime: new Date().toISOString().slice(0, 19).replace('T', ' '),
                items: orderItems,
                totalAmount: totalAmount.toFixed(2) + "₺",
                taxRate: 8,
                taxAmount: (totalAmount * 0.08).toFixed(2) + "₺",
                subtotal: (totalAmount * 0.92).toFixed(2) + "₺"
            };

            // Fiş yazdırma isteğini veritabanına ekle
            POS.utils.ajax('add_print_queue.php', 'POST', {
                masa_id: masaId,
                masa_adi: "Masa " + masaId,
                receipt_type: 'split',
                receipt_data: JSON.stringify(receiptData)
            }, function (response) {
                if (response.success) {
                    Toastify({
                        text: peopleCount + " kişi için fiş yazdırma isteği gönderildi",
                        duration: 3000,
                        backgroundColor: "#f1c40f",
                    }).showToast();

                    // Modalı kapat
                    $('#splitBillModal').modal('hide');

                    // İlk fiş için önizleme göster
                    POS.payment.showSplitReceiptPreview(receiptData, 1);
                }
            });
        },

        printIndividualReceipts: function (masaId) {
            POS.sounds.play('print');

            // Get all persons with assigned items
            let personData = {
                '': [], // Shared items
                'person1': [],
                'person2': [],
                'person3': [],
                'person4': []
            };

            // Collect items for each person
            document.querySelectorAll(".summary-item").forEach(function (item) {
                const productName = item.querySelector(".item-name").innerText.split(' - ')[0];
                const price = parseFloat(item.querySelector(".item-price").innerText.replace('₺', ''));
                const quantity = parseInt(item.querySelector(".quantity-value").innerText);
                const productId = item.getAttribute('data-product-id');
                const person = item.getAttribute('data-person') || '';

                personData[person].push({
                    product_id: productId,
                    name: productName,
                    price: price.toFixed(2) + '₺',
                    quantity: quantity
                });
            });

            // Print a receipt for each person who has items
            Object.keys(personData).forEach(function (person) {
                if (personData[person].length > 0) {
                    const personName = person === '' ? 'Ortak' : 'Kişi ' + person.charAt(person.length - 1);

                    // Calculate total for this person
                    let personTotal = 0;
                    personData[person].forEach(function (item) {
                        personTotal += parseFloat(item.price) * item.quantity;
                    });

                    // Create receipt data
                    const receiptData = {
                        receiptNumber: POS.utils.getRandomReceiptNumber(),
                        tableId: masaId,
                        tableName: "Masa " + masaId,
                        dateTime: new Date().toISOString().slice(0, 19).replace('T', ' '),
                        items: personData[person],
                        totalAmount: personTotal.toFixed(2) + "₺",
                        taxRate: 8,
                        taxAmount: (personTotal * 0.08).toFixed(2) + "₺",
                        subtotal: (personTotal * 0.92).toFixed(2) + "₺",
                        paymentMethod: "Belirsiz",
                        personName: personName,
                        splitType: 'individual'
                    };

                    // Add print request to queue
                    POS.utils.ajax('add_print_queue.php', 'POST', {
                        masa_id: masaId,
                        masa_adi: "Masa " + masaId + " (" + personName + ")",
                        receipt_type: 'personal',
                        receipt_data: JSON.stringify(receiptData)
                    }, function (response) {
                        if (response.success) {
                            Toastify({
                                text: personName + " için fiş yazdırma isteği gönderildi",
                                duration: 3000,
                                style: { background: "#f1c40f" }
                            }).showToast();
                        }
                    });
                }
            });

            // Close modal after sending all print requests
            setTimeout(function () {
                $('#splitBillModal').modal('hide');
            }, 1000);
        },

        showSplitReceiptPreview: function (receiptData, personNumber) {
            // Fiş içeriğini oluştur
            var printContent = `
            <style type="text/css" media="print">
                @page {
                    size: 80mm auto;
                    margin: 0;
                }
                body {
                    margin: 0;
                    font-family: 'Arial', sans-serif;
                    padding: 5mm;
                }
                .receipt {
                    width: 70mm;
                    max-width: 100%;
                    margin: 0 auto;
                }
                .receipt-header {
                    text-align: center;
                    margin-bottom: 5mm;
                }
                .receipt-logo {
                    max-width: 30mm;
                    margin-bottom: 2mm;
                }
                .receipt-title {
                    font-size: 14pt;
                    font-weight: bold;
                    margin-bottom: 1mm;
                }
                .receipt-subtitle {
                    font-size: 10pt;
                    color: #666;
                    margin-bottom: 1mm;
                }
                .receipt-person {
                    font-size: 12pt;
                    font-weight: bold;
                    margin: 2mm 0;
                    padding: 2mm;
                    background: #f1f1f1;
                    border-radius: 2mm;
                }
                .receipt-info {
                    display: flex;
                    flex-direction: column;
                    margin-bottom: 5mm;
                    font-size: 9pt;
                    padding: 2mm 0;
                    border-top: 1px dashed #000;
                    border-bottom: 1px dashed #000;
                }
                .receipt-info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 1mm;
                }
                .receipt-note {
                    font-size: 9pt;
                    margin: 3mm 0;
                    padding: 2mm;
                    border: 1px dashed #000;
                    border-radius: 2mm;
                    text-align: center;
                    font-style: italic;
                }
                .receipt-amount {
                    font-size: 14pt;
                    font-weight: bold;
                    text-align: center;
                    margin: 3mm 0;
                    padding: 3mm;
                    border: 1px solid #000;
                    border-radius: 2mm;
                }
                .receipt-footer {
                    text-align: center;
                    margin-top: 5mm;
                    font-size: 9pt;
                    padding-top: 2mm;
                    border-top: 1px dashed #000;
                }
                @media print {
                    .no-print {
                        display: none;
                    }
                }
            </style>
            <div class="receipt">
                <div class="receipt-header">
                    <img src="https://ateslipilicler.com/admin/pos/fislogo.png" alt="Logo" class="receipt-logo">
                    <div class="receipt-title">Ateşli PiliçlershowwSplitReceiptPreview</div>
                    <div class="receipt-subtitle">Lezzetin Ateşli Hali</div>
                    <div class="receipt-person">Kişi ${personNumber} - Hesap Bölüşümü</div>
                </div>
                
                <div class="receipt-info">
                    <div class="receipt-info-row">
                        <div>Tarih:</div>
                        <div>${receiptData.dateTime.split(' ')[0]}</div>
                    </div>
                    <div class="receipt-info-row">
                        <div>Saat:</div>
                        <div>${receiptData.dateTime.split(' ')[1]}</div>
                    </div>
                    <div class="receipt-info-row">
                        <div>Fiş No:</div>
                        <div>#${POS.utils.getRandomReceiptNumber()}</div>
                    </div>
                    <div class="receipt-info-row">
                        <div>Masa:</div>
                        <div>${receiptData.tableName}</div>
                    </div>
                </div>
                
                <div class="receipt-note">
                    Bu fiş, hesabın ${receiptData.peopleCount} kişi arasında eşit bölünmesi sonucunda düzenlenmiştir.
                </div>
                
                <div class="receipt-amount">
                    ÖDENECEK: ${receiptData.perPersonAmount}
                </div>
                
                <div class="receipt-footer">
                    <p>Teşekkür ederiz, yine bekleriz!</p>
                    <p>Tel: 0532 548 31 35</p>
                </div>
                
                <div class="no-print" style="margin-top: 20px; text-align: center;">
                    <p>Kişi ${personNumber}/${receiptData.peopleCount} için fiş önizlemesi</p>
                    <button onclick="window.print()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">Yazdır</button>
                    <button onclick="window.close()" style="padding: 10px 20px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Kapat</button>
                </div>
            </div>`;

            // Yeni pencerede göster
            var printWindow = window.open('', '_blank', 'width=400,height=600');
            printWindow.document.write(printContent);
            printWindow.document.close();
        }
    },

    // Ana başlatma fonksiyonu
    init: function () {
        console.log('POS Sistemi başlatılıyor...');

        // Ses efektlerini yükle
        POS.sounds.init();

        // Ayarları yükle
        POS.settings.loadSettings();

        // Olay dinleyicileri kur
        this.setupEventListeners();

        // Sayfa türüne göre özel başlatma
        if (document.getElementById('tables-container')) {
            // Masalar sayfası
            POS.tables.loadTables();
        } else if (document.getElementById('order-list-container')) {
            // POS menü sayfası
            POS.orders.updateTotalPrice();
        } else if (document.getElementById('print-checker-container')) {
            // Yazdırma kontrol sayfası
            POS.print.startPrintChecker();
        }

        // Hoşgeldiniz bildirimi
        Toastify({
            text: "POS Sistemi başlatıldı! Hoş geldiniz.",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "linear-gradient(to right, #e74c3c, #e67e22)",
            stopOnFocus: true
        }).showToast();
    },

    setupEventListeners: function () {
        // Sayfa yüklendikten sonra olay dinleyicileri ekle
        document.addEventListener('DOMContentLoaded', function () {
            // Sayfa türüne göre farklı olay dinleyicileri ekle
            if (document.getElementById('tables-container')) {
                // Masalar sayfası

                // Yenileme düğmesi
                const refreshBtn = document.getElementById('refresh-btn');
                if (refreshBtn) {
                    refreshBtn.addEventListener('click', function () {
                        POS.sounds.play('buttonClick');
                        window.location.reload();
                    });
                }

                // Ayarlar düğmesi
                const settingsBtn = document.getElementById('settings-btn');
                if (settingsBtn) {
                    settingsBtn.addEventListener('click', function () {
                        POS.sounds.play('buttonClick');
                        $('#settingsModal').modal('show');
                    });
                }

                // Ses düğmesi
                const soundToggle = document.getElementById('sound-toggle');
                if (soundToggle) {
                    soundToggle.addEventListener('click', POS.settings.toggleSound);
                }

                // Ayarları kaydetme düğmesi
                const saveSettingsBtn = document.getElementById('saveSettingsBtn');
                if (saveSettingsBtn) {
                    saveSettingsBtn.addEventListener('click', POS.settings.saveSettings);
                }

                // Kategori filtreleme düğmeleri
                const categoryBtns = document.querySelectorAll('.category-btn');
                categoryBtns.forEach(btn => {
                    btn.addEventListener('click', function () {
                        const filter = this.getAttribute('data-filter');
                        POS.tables.filterTables(filter);
                    });
                });

                // Masa işlem butonları
                const openTableBtn = document.getElementById('openTableBtn');
                if (openTableBtn) {
                    openTableBtn.addEventListener('click', function () {
                        POS.tables.startAdisyon(POS.config.currentMasaId, POS.config.currentTableName);
                    });
                }

                const viewOrderBtn = document.getElementById('viewOrderBtn');
                if (viewOrderBtn) {
                    viewOrderBtn.addEventListener('click', function () {
                        POS.tables.startAdisyon(POS.config.currentMasaId, POS.config.currentTableName);
                    });
                }

                const closeTableBtn = document.getElementById('closeTableBtn');
                if (closeTableBtn) {
                    closeTableBtn.addEventListener('click', function () {
                        POS.tables.closeTable(POS.config.currentMasaId);
                    });
                }

                // WhatsApp desteği
                const whatsappSupport = document.querySelector('.whatsapp-support');
                if (whatsappSupport) {
                    whatsappSupport.addEventListener('click', function () {
                        POS.sounds.play('buttonClick');
                        window.open('https://wa.me/905325483135', '_blank');
                    });
                }
            } else if (document.getElementById('order-list-container')) {
                // POS menü sayfası

                // Hesap bölme modal butonları
                const splitOptions = document.querySelectorAll('.split-option');
                splitOptions.forEach(option => {
                    option.addEventListener('click', function () {
                        const splitType = this.getAttribute('data-split-type');
                        POS.payment.selectSplitOption(splitType);
                    });
                });

                const calculateSplitBtn = document.getElementById('calculate-split-btn');
                if (calculateSplitBtn) {
                    calculateSplitBtn.addEventListener('click', POS.payment.calculateEvenSplit);
                }

                const splitBillBtn = document.getElementById('split-print-receipts');
                if (splitBillBtn) {
                    splitBillBtn.addEventListener('click', function () {
                        const masaId = document.getElementById('masa_id').value;
                        POS.payment.printSplitReceipts(masaId);
                    });
                }

                // Ödeme onay modalı
                const confirmPaymentBtn = document.getElementById('confirmPaymentBtn');
                if (confirmPaymentBtn) {
                    confirmPaymentBtn.addEventListener('click', function () {
                        const masaId = document.getElementById('masa_id').value;
                        POS.tables.closeTable(masaId);
                    });
                }

                // WhatsApp desteği
                const whatsappSupport = document.querySelector('.whatsapp-support');
                if (whatsappSupport) {
                    whatsappSupport.addEventListener('click', function () {
                        POS.sounds.play('buttonClick');
                        window.open('https://wa.me/905325483135', '_blank');
                    });
                }

                // Ürün arama
                const productSearch = document.getElementById('product-search');
                if (productSearch) {
                    productSearch.addEventListener('input', function () {
                        const searchTerm = this.value.toLowerCase();
                        const products = document.querySelectorAll('.product-card');

                        products.forEach(product => {
                            const productName = product.querySelector('.product-name').innerText.toLowerCase();
                            if (productName.includes(searchTerm)) {
                                product.style.display = 'block';
                            } else {
                                product.style.display = 'none';
                            }
                        });
                    });
                }
            }
        });
    },

    // Yazdırma kontrolü
    print: {
        startPrintChecker: function () {
            // Yazdırma kuyruğunu düzenli aralıklarla kontrol et
            setInterval(function () {
                POS.print.checkPrintQueue();
            }, 5000); // 5 saniyede bir kontrol et

            // İlk kontrolü hemen yap
            POS.print.checkPrintQueue();
        },

        checkPrintQueue: function () {
            // Yazdırma kuyruğundaki öğeleri kontrol et
            POS.utils.ajax('check_print_queue.php', 'GET', {}, function (response) {
                if (response.success) {
                    const printQueue = response.print_queue;

                    // Durum göstergesini güncelle
                    const printStatus = document.getElementById('print-status');
                    if (printStatus) {
                        if (printQueue.length > 0) {
                            printStatus.className = 'print-status active';
                            printStatus.innerHTML = `<i class="fas fa-print"></i> ${printQueue.length} adet yazdırma işlemi bekleniyor...`;
                        } else {
                            printStatus.className = 'print-status idle';
                            printStatus.innerHTML = '<i class="fas fa-check-circle"></i> Yazdırma kuyruğu boş';
                        }
                    }

                    // Kuyruk listesini güncelle
                    const queueList = document.getElementById('print-queue-list');
                    if (queueList) {
                        if (printQueue.length > 0) {
                            let html = '';
                            printQueue.forEach(function (item) {
                                const receiptData = JSON.parse(item.receipt_data);
                                const createdAt = new Date(item.created_at);
                                const timeAgo = POS.utils.getTimeDiff(item.created_at);

                                html += `
                                    <div class="print-queue-item">
                                        <div class="print-queue-info">
                                            <div><strong>${item.masa_adi}</strong> - ${item.receipt_type === 'full' ? 'Tam Fiş' : (item.receipt_type === 'split' ? 'Bölünmüş Fiş' : 'Kişisel Fiş')}</div>
                                            <div>Sipariş tutarı: ${receiptData.totalAmount} - ${timeAgo} önce</div>
                                        </div>
                                        <div class="print-queue-actions">
                                            <button class="btn btn-sm btn-warning" onclick="POS.print.printReceipt(${item.id})">
                                                <i class="fas fa-print"></i> Yazdır
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="POS.print.removeFromQueue(${item.id})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            });
                            queueList.innerHTML = html;
                        } else {
                            queueList.innerHTML = '<div class="print-queue-empty">Yazdırma kuyruğunda hiç öğe yok</div>';
                        }
                    }

                    // İlk yazdırma işlemini otomatik olarak başlat
                    if (printQueue.length > 0) {
                        POS.print.printReceipt(printQueue[0].id);
                    }
                }
            });
        },

        printReceipt: function (queueId) {
            // Fişi yazdır ve kuyruktan kaldır
            POS.utils.ajax('print_receipt.php', 'POST', { queue_id: queueId }, function (response) {
                if (response.success) {
                    // Yazdırma önizlemesi göster
                    const receiptData = JSON.parse(response.receipt_data);

                    // Fiş türüne göre farklı önizleme fonksiyonları çağır
                    if (receiptData.receipt_type === 'split') {
                        // Bölünmüş fiş için
                        for (let i = 1; i <= receiptData.peopleCount; i++) {
                            setTimeout(() => {
                                POS.payment.showSplitReceiptPreview(receiptData, i);
                            }, i * 500);
                        }
                    } else {
                        // Tam fiş için
                        POS.payment.showPrintPreview(receiptData);
                    }

                    // Yazdırma durumunu güncelle
                    POS.print.checkPrintQueue();
                }
            });
        },

        removeFromQueue: function (queueId) {
            // Yazdırma kuyruğundan kaldır
            POS.utils.ajax('remove_from_queue.php', 'POST', { queue_id: queueId }, function (response) {
                if (response.success) {
                    Toastify({
                        text: "Yazdırma işlemi kuyruktan kaldırıldı",
                        duration: 3000,
                        backgroundColor: "#e74c3c",
                    }).showToast();

                    // Kuyruk listesini güncelle
                    POS.print.checkPrintQueue();
                }
            });
        }
    }
};

// Sayfa yüklendiğinde POS sistemini başlat
document.addEventListener('DOMContentLoaded', function () {
    POS.init();
});