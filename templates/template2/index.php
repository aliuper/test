<?php
/**
 * QR Menü - Klasik Tasarım Şablonu
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
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/template2.css">
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
    
    <style>
        :root {
            --primary-color: #4b6584;
            --primary-dark: #3a4e66;
            --secondary-color: #a5b1c2;
            --text-color: #333;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --background-color: #f5f5f5;
            --card-bg-color: #fff;
            --accent-color: #d1d8e0;
            --font-family: 'Georgia', serif;
        }
        
        body {
            font-family: var(--font-family);
            color: var(--text-color);
            background-color: var(--background-color);
            padding-bottom: 70px;
        }
        
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-bottom: 30px;
            border-bottom: 5px solid var(--accent-color);
            position: relative;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 5px;
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .header .restaurant-logo {
            max-height: 100px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
            font-family: 'Times New Roman', Times, serif;
            letter-spacing: 1px;
        }
        
        .header p {
            margin-bottom: 0;
            font-style: italic;
            opacity: 0.9;
        }
        
        .category-nav {
            background: var(--card-bg-color);
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 0;
            border: 1px solid #e0e0e0;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
        }
        
        .category-nav .nav-link {
            color: var(--text-color);
            font-weight: 500;
            padding: 10px 15px;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .category-nav .nav-link:hover {
            color: var(--primary-color);
            background-color: rgba(75, 101, 132, 0.05);
        }
        
        .category-nav .nav-link.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            font-weight: 600;
        }
        
        .category-title {
            font-family: 'Times New Roman', Times, serif;
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 30px;
            margin-top: 40px;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.8rem;
            text-align: center;
            background-color: var(--card-bg-color);
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
        }
        
        .category-title::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            width: 100px;
            height: 3px;
            background-color: var(--primary-color);
            transform: translateX(-50%);
        }
        
        .product-list {
            margin-bottom: 40px;
        }
        
        .product-item {
            background-color: var(--card-bg-color);
            margin-bottom: 20px;
            border-radius: 0;
            border: 1px solid #e0e0e0;
            padding: 20px;
            display: flex;
            position: relative;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
        }
        
        .product-image-container {
            flex: 0 0 120px;
            margin-right: 20px;
        }
        
        .product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--primary-color);
        }
        
        .product-description {
            color: #666;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-style: italic;
        }
        
        .product-price {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-color);
            display: block;
            margin-bottom: 10px;
        }
        
        .product-discount {
            text-decoration: line-through;
            color: #999;
            font-size: 1rem;
            margin-right: 10px;
        }
        
        .product-action {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .add-to-cart-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 0;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .add-to-cart-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .product-allergens {
            font-size: 0.85rem;
            color: #888;
        }
        
        .product-allergens .badge {
            background-color: var(--accent-color);
            color: var(--text-color);
            font-weight: 500;
            margin-right: 5px;
        }
        
        .cart-fab {
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .cart-fab:hover {
            background-color: var(--primary-dark);
        }
        
        .cart-fab .fas {
            font-size: 1.5rem;
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--dark-color);
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
            background-color: white;
            box-shadow: 0 -3px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            z-index: 999;
            border-top: 2px solid var(--accent-color);
        }
        
        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #6c757d;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .bottom-nav-item.active {
            color: var(--primary-color);
        }
        
        .bottom-nav-item i {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        
        .bottom-nav-item span {
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .table-badge {
            display: inline-block;
            padding: 8px 15px;
            background-color: var(--accent-color);
            color: var(--primary-color);
            font-weight: 700;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .product-detail-modal .modal-content {
            border-radius: 0;
            border: 2px solid var(--primary-color);
        }
        
        .product-detail-modal .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
        }
        
        .product-detail-modal .modal-title {
            font-family: 'Times New Roman', Times, serif;
            font-weight: 700;
        }
        
        .product-detail-modal .btn-close {
            color: white;
        }
        
        .product-detail-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        
        .product-detail-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-family: 'Times New Roman', Times, serif;
        }
        
        .product-detail-description {
            margin-bottom: 20px;
            color: #666;
            font-style: italic;
        }
        
        .product-detail-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .product-detail-discount {
            text-decoration: line-through;
            color: #999;
            font-size: 1.1rem;
            margin-right: 10px;
        }
        
        .product-detail-quantity {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .quantity-btn {
            background-color: var(--accent-color);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover {
            background-color: var(--secondary-color);
        }
        
        .quantity-input {
            width: 50px;
            text-align: center;
            font-weight: 600;
            margin: 0 10px;
            border: 1px solid var(--accent-color);
            padding: 5px;
        }
        
        .add-to-cart-modal-btn {
            width: 100%;
            padding: 12px;
            font-weight: 600;
            border-radius: 0;
            background-color: var(--primary-color);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .add-to-cart-modal-btn:hover {
            background-color: var(--primary-dark);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                padding: 1.5rem 0;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .header .restaurant-logo {
                max-height: 80px;
            }
            
            .product-item {
                flex-direction: column;
            }
            
            .product-image-container {
                flex: initial;
                margin-right: 0;
                margin-bottom: 15px;
                text-align: center;
            }
            
            .product-image {
                width: 100%;
                max-width: 200px;
                height: auto;
                aspect-ratio: 1/1;
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
                <div class="mt-3">
                    <span class="table-badge">
                        <i class="fas fa-chair me-1"></i> <?= h($tableInfo['name']) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- İçerik -->
    <main>
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
        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // Kategori navigasyonunu yatay olarak kaydırma
            const categoryNav = document.querySelector('.category-nav-scroll');
            if (categoryNav) {
                // Aktif kategoriyi görünür yap
                const activeLink = categoryNav.querySelector('.nav-link.active');
                if (activeLink) {
                    activeLink.scrollIntoView({ behavior: 'smooth', inline: 'center' });
                }
            }
            
            // Sepet sayısını güncelle
            updateCartBadge();
            
            // Ürün detay butonları
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
                                        badge.className = 'badge me-1 mb-1';
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