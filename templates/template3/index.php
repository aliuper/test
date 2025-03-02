<?php
/**
 * QR Menü - Minimalist Tasarım Şablonu
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . $siteTitle : $siteTitle ?></title>
    
    <!-- Meta Bilgileri -->
    <meta name="description" content="<?= $siteDescription ?>">
    <?php if (isset($pageMetaDescription)): ?>
        <meta name="description" content="<?= $pageMetaDescription ?>">
    <?php endif; ?>
    <?php if (isset($pageMetaKeywords)): ?>
        <meta name="keywords" content="<?= $pageMetaKeywords ?>">
    <?php endif; ?>
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= isset($pageTitle) ? $pageTitle . ' - ' . $siteTitle : $siteTitle ?>">
    <meta property="og:description" content="<?= isset($pageMetaDescription) ? $pageMetaDescription : $siteDescription ?>">
    <meta property="og:url" content="<?= $currentUrl ?>">
    <?php if (isset($ogImage)): ?>
        <meta property="og:image" content="<?= $ogImage ?>">
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" href="<?= ASSETS_URL ?>/img/favicon.png" type="image/png">
    
    <!-- CSS Dosyaları -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/template3.css">
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
    
    <style>
        :root {
            --primary-color: #222831;
            --primary-dark: #1a1f25;
            --secondary-color: #393e46;
            --accent-color: #00adb5;
            --accent-dark: #00858c;
            --text-color: #333;
            --light-color: #eeeeee;
            --dark-color: #222831;
            --background-color: #ffffff;
            --card-bg-color: #fff;
            --font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            font-family: var(--font-family);
            color: var(--text-color);
            background-color: var(--background-color);
            padding-bottom: 70px;
            line-height: 1.6;
        }
        
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-bottom: 30px;
        }
        
        .header .restaurant-logo {
            max-height: 70px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.3rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .header p {
            margin-bottom: 0;
            opacity: 0.85;
            font-size: 0.95rem;
        }
        
        .sidebar {
            position: sticky;
            top: 20px;
            padding: 20px;
            background-color: var(--light-color);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .sidebar .nav-link {
            color: var(--text-color);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(0, 173, 181, 0.1);
            color: var(--accent-color);
        }
        
        .sidebar .nav-link.active {
            background-color: var(--accent-color);
            color: white;
        }
        
        .category-title {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 25px;
            margin-top: 30px;
            color: var(--primary-color);
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .category-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: var(--accent-color);
            margin-left: 15px;
        }
        
        .product-card {
            background-color: var(--card-bg-color);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: none;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .product-image-container {
            position: relative;
            overflow: hidden;
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .product-discount-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--accent-color);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .product-body {
            padding: 20px;
        }
        
        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .product-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 38px;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .product-price {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.15rem;
        }
        
        .product-discount {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9rem;
            margin-right: 8px;
        }
        
        .product-prep-time {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .product-prep-time i {
            margin-right: 5px;
        }
        
        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .product-allergens {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .add-to-cart-btn {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .add-to-cart-btn:hover {
            background-color: var(--accent-dark);
        }
        
        .mobile-category-nav {
            position: sticky;
            top: 0;
            background-color: white;
            z-index: 100;
            padding: 10px 0;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            display: none;
        }
        
        .mobile-category-nav .nav-link {
            display: inline-block;
            color: var(--text-color);
            padding: 8px 15px;
            border-radius: 20px;
            margin-right: 10px;
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
        }
        
        .mobile-category-nav .nav-link:hover {
            color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .mobile-category-nav .nav-link.active {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .cart-fab {
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .cart-fab:hover {
            background-color: var(--accent-dark);
            transform: scale(1.05);
        }
        
        .cart-fab .fas {
            font-size: 1.5rem;
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--primary-color);
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--primary-color);
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            z-index: 999;
        }
        
        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .bottom-nav-item.active {
            color: white;
        }
        
        .bottom-nav-item i {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        
        .bottom-nav-item span {
            font-size: 0.8rem;
        }
        
        .table-info {
            display: inline-block;
            padding: 5px 15px;
            background-color: var(--light-color);
            color: var(--primary-color);
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .product-detail-modal .modal-content {
            border-radius: 10px;
            border: none;
            overflow: hidden;
        }
        
        .product-detail-modal .modal-header {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .product-detail-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .product-detail-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 20px 0 15px;
            color: var(--primary-color);
        }
        
        .product-detail-description {
            margin-bottom: 20px;
            color: #6c757d;
        }
        
        .product-detail-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .product-detail-discount {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 1.1rem;
            margin-right: 10px;
        }
        
        .product-detail-meta {
            display: flex;
            justify-content: space-between;
            background-color: var(--light-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .meta-icon {
            font-size: 1.5rem;
            color: var(--accent-color);
            margin-bottom: 5px;
        }
        
        .meta-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .meta-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .product-detail-quantity {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .quantity-btn {
            background-color: var(--light-color);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--primary-color);
        }
        
        .quantity-btn:hover {
            background-color: var(--accent-color);
            color: white;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            font-weight: 600;
            margin: 0 10px;
            border: 1px solid var(--light-color);
            border-radius: 8px;
            padding: 8px;
        }
        
        .product-detail-note {
            margin-bottom: 20px;
        }
        
        .product-detail-note textarea {
            border: 1px solid var(--light-color);
            border-radius: 8px;
            padding: 10px;
            resize: none;
        }
        
        .add-to-cart-modal-btn {
            width: 100%;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            background-color: var(--accent-color);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .add-to-cart-modal-btn:hover {
            background-color: var(--accent-dark);
        }
        
        .allergen-badge {
            display: inline-block;
            background-color: var(--light-color);
            color: var(--text-color);
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 15px;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                display: none;
            }
            
            .mobile-category-nav {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1.5rem 0;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .header .restaurant-logo {
                max-height: 60px;
            }
            
            .product-detail-image {
                height: 200px;
            }
            
            .product-detail-meta {
                flex-wrap: wrap;
            }
            
            .meta-item {
                width: 50%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container text-center">
            <?php if (!empty($restaurantLogo)): ?>
                <img src="<?= ASSETS_URL ?>/uploads/<?= $restaurantLogo ?>" alt="<?= h($siteTitle) ?>" class="restaurant-logo">
            <?php else: ?>
                <h1 class="mb-2"><?= h($siteTitle) ?></h1>
            <?php endif; ?>
            
            <?php if (!empty($restaurantSlogan)): ?>
                <p><?= h($restaurantSlogan) ?></p>
            <?php endif; ?>
            
            <?php if (isset($tableInfo)): ?>
                <div class="table-info">
                    <i class="fas fa-chair me-1"></i> <?= h($tableInfo['name']) ?>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- İçerik -->
    <main class="container">
        <?= $content ?>
    </main>
    
    <!-- Alt Navigasyon -->
    <nav class="bottom-nav">
        <a href="<?= BASE_URL ?>/menu.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="bottom-nav-item <?= $currentPage === 'menu' ? 'active' : '' ?>">
            <i class="fas fa-utensils"></i>
            <span>Menü</span>
        </a>
        
        <a href="<?= BASE_URL ?>/cart.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="bottom-nav-item <?= $currentPage === 'cart' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Sepet</span>
        </a>
        
        <a href="<?= BASE_URL ?>/order-status.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="bottom-nav-item <?= $currentPage === 'order-status' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Siparişlerim</span>
        </a>
        
        <a href="<?= BASE_URL ?>/call-waiter.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="bottom-nav-item <?= $currentPage === 'call-waiter' ? 'active' : '' ?>">
            <i class="fas fa-bell"></i>
            <span>Garson Çağır</span>
        </a>
    </nav>
    
    <!-- JS Dosyaları -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ASSETS_URL ?>/js/menu.js"></script>
    
    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Kategori navigasyonunu yatay olarak kaydırma
            const categoryNav = document.querySelector('.mobile-category-nav');
            if (categoryNav) {
                // Aktif kategoriyi görünür yap
                const activeLink = categoryNav.querySelector('.nav-link.active');
                if (activeLink) {
                    activeLink.scrollIntoView({ behavior: 'smooth', inline: 'center' });
                }
            }
            
            // Sepet sayısını güncelle
            updateCartBadge();
            
            // Ürün detay modalları
            document.querySelectorAll('.view-product-detail').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.productId;
                    const productDetailUrl = '<?= BASE_URL ?>/ajax/product-detail.php?id=' + productId;
                    
                    fetch(productDetailUrl)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const product = data.product;
                                
                                // Modal içeriğini doldur
                                document.getElementById('productDetailTitle').textContent = product.name;
                                document.getElementById('productDetailDescription').textContent = product.description;
                                
                                // Fiyat
                                const priceHtml = product.discount_price 
                                    ? '<span class="product-detail-discount">' + formatCurrency(product.price) + '</span>' + formatCurrency(product.discount_price)
                                    : formatCurrency(product.price);
                                document.getElementById('productDetailPrice').innerHTML = priceHtml;
                                
                                // Resim
                                const imageUrl = product.image 
                                    ? '<?= ASSETS_URL ?>/uploads/products/' + product.image
                                    : '<?= ASSETS_URL ?>/img/no-image.jpg';
                                document.getElementById('productDetailImage').src = imageUrl;
                                
                                // Alerjenler
                                const allergensContainer = document.getElementById('productDetailAllergens');
                                allergensContainer.innerHTML = '';
                                
                                if (product.allergens) {
                                    const allergens = product.allergens.split(',');
                                    allergens.forEach(function(allergen) {
                                        const badge = document.createElement('span');
                                        badge.className = 'allergen-badge';
                                        badge.textContent = allergen.trim();
                                        allergensContainer.appendChild(badge);
                                    });
                                } else {
                                    allergensContainer.innerHTML = '<span class="text-muted">Alerjen bilgisi bulunmuyor</span>';
                                }
                                
                                // Hazırlama süresi
                                const prepTimeContainer = document.getElementById('productDetailPrepTime');
                                if (product.preparation_time) {
                                    prepTimeContainer.textContent = product.preparation_time + ' dakika';
                                } else {
                                    prepTimeContainer.textContent = 'Belirtilmemiş';
                                }
                                
                                // Kalori
                                const caloriesContainer = document.getElementById('productDetailCalories');
                                if (product.calories) {
                                    caloriesContainer.textContent = product.calories + ' kcal';
                                } else {
                                    caloriesContainer.textContent = 'Belirtilmemiş';
                                }
                                
                                // Ürün ID'sini buton data özelliğine ekle
                                document.getElementById('addToCartModalBtn').dataset.productId = product.id;
                                
                                // Modalı göster
                                new bootstrap.Modal(document.getElementById('productDetailModal')).show();
                            } else {
                                alert('Ürün detayları alınamadı: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Bir hata oluştu.');
                        });
                });
            });
            
            // Miktar artırma/azaltma
            document.getElementById('decreaseQuantity').addEventListener('click', function() {
                const input = document.getElementById('productQuantity');
                const currentValue = parseInt(input.value, 10);
                if (currentValue > 1) {
                    input.value = currentValue - 1;
                }
            });
            
            document.getElementById('increaseQuantity').addEventListener('click', function() {
                const input = document.getElementById('productQuantity');
                const currentValue = parseInt(input.value, 10);
                input.value = currentValue + 1;
            });
            
            // Sepete ekle butonu
            document.getElementById('addToCartModalBtn').addEventListener('click', function() {
                const productId = this.dataset.productId;
                const quantity = document.getElementById('productQuantity').value;
                const note = document.getElementById('productNote').value;
                
                addToCart(productId, quantity, note);
            });
            
            // Quick add butonları
            document.querySelectorAll('.quick-add-to-cart').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const productId = this.dataset.productId;
                    addToCart(productId, 1, '');
                });
            });
        });
        
        // Sepete ekle fonksiyonu
        function addToCart(productId, quantity, note) {
            const addToCartUrl = '<?= BASE_URL ?>/ajax/add-to-cart.php';
            
            fetch(addToCartUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity,
                    note: note,
                    table_id: <?= isset($tableId) ? $tableId : 'null' ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sepet sayısını güncelle
                    updateCartBadge();
                    
                    // Modalı kapat
                    const detailModal = document.getElementById('productDetailModal');
                    if (detailModal) {
                        const bsModal = bootstrap.Modal.getInstance(detailModal);
                        if (bsModal) {
                            bsModal.hide();
                        }
                    }
                    
                    // Başarı mesajı göster
                    showToast('Ürün sepete eklendi!', 'success');
                } else {
                    alert('Ürün sepete eklenemedi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu.');
            });
        }
        
        // Sepet sayısını güncelle
        function updateCartBadge() {
            const getCartCountUrl = '<?= BASE_URL ?>/ajax/get-cart-count.php';
            
            fetch(getCartCountUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    table_id: <?= isset($tableId) ? $tableId : 'null' ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = data.count;
                        
                        if (data.count > 0) {
                            cartBadge.style.display = 'flex';
                        } else {
                            cartBadge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        // Para birimi formatla
        function formatCurrency(amount) {
            return parseFloat(amount).toFixed(2) + ' ₺';
        }
        
        // Toast mesajı göster
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-' + (type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info');
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button>
                </div>
            `;
            
            // Toast container oluştur
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container position-fixed bottom-0 start-50 translate-middle-x p-3';
                document.body.appendChild(toastContainer);
            }
            
            toastContainer.appendChild(toast);
            
            const bootstrapToast = new bootstrap.Toast(toast, {
                delay: 3000
            });
            bootstrapToast.show();
            
            toast.addEventListener('hidden.bs.toast', function() {
                toast.remove();
            });
        }
    </script>
</body>
</html>