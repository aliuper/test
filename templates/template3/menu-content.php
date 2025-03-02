<?php
/**
 * Template 3 - Minimalist Tasarım Menü İçeriği
 */
?>

<!-- Mobil Kategori Navigasyonu (Sadece mobil görünümde) -->
<div class="mobile-category-nav">
    <div class="container">
        <div class="overflow-auto">
            <?php foreach ($categories as $index => $category): ?>
                <a class="nav-link <?= $index === 0 ? 'active' : '' ?>" href="#category-<?= $category['id'] ?>">
                    <?= h($category['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Sidebar Kategori Menüsü (Masaüstü görünümünde) -->
    <div class="col-lg-3">
        <div class="sidebar">
            <h5 class="mb-3">Kategoriler</h5>
            <div class="nav flex-column nav-pills">
                <?php foreach ($categories as $index => $category): ?>
                    <a class="nav-link <?= $index === 0 ? 'active' : '' ?>" href="#category-<?= $category['id'] ?>">
                        <?= h($category['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (isset($tableInfo)): ?>
            <div class="mt-4">
                <h5 class="mb-3">Masa Bilgisi</h5>
                <div class="d-flex align-items-center">
                    <i class="fas fa-chair me-2 fs-4"></i>
                    <div>
                        <div class="fw-bold"><?= h($tableInfo['name']) ?></div>
                        <?php if (!empty($tableInfo['location'])): ?>
                            <small class="text-muted"><?= h($tableInfo['location']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Ürünler -->
    <div class="col-lg-9">
        <?php foreach ($categories as $category): ?>
            <?php if (isset($products[$category['id']]) && !empty($products[$category['id']])): ?>
                <div id="category-<?= $category['id'] ?>" class="mb-5">
                    <h2 class="category-title"><?= h($category['name']) ?></h2>
                    
                    <div class="row">
                        <?php foreach ($products[$category['id']] as $product): ?>
                            <div class="col-md-6 col-lg-6 mb-4">
                                <div class="product-card">
                                    <div class="product-image-container">
                                        <?php
                                        $imageUrl = !empty($product['image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $product['image']) 
                                            ? ASSETS_URL . '/uploads/products/' . $product['image'] 
                                            : ASSETS_URL . '/img/no-image.jpg';
                                        ?>
                                        <img src="<?= $imageUrl ?>" class="product-image" alt="<?= h($product['name']) ?>">
                                        
                                        <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                            <div class="product-discount-badge">
                                                %<?= round((1 - $product['discount_price'] / $product['price']) * 100) ?> İndirim
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-body">
                                        <h3 class="product-title"><?= h($product['name']) ?></h3>
                                        
                                        <?php if (!empty($product['description'])): ?>
                                            <p class="product-description"><?= h($product['description']) ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="product-meta">
                                            <div>
                                                <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                                    <span class="product-discount"><?= formatCurrency($product['price']) ?></span>
                                                    <span class="product-price"><?= formatCurrency($product['discount_price']) ?></span>
                                                <?php else: ?>
                                                    <span class="product-price"><?= formatCurrency($product['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($product['preparation_time'])): ?>
                                                <div class="product-prep-time">
                                                    <i class="far fa-clock"></i> <?= $product['preparation_time'] ?> dk
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="product-footer">
                                            <?php if (!empty($product['allergens'])): ?>
                                                <div class="product-allergens">
                                                    <i class="fas fa-exclamation-circle"></i> Alerjenler
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div>
                                                <button class="btn btn-outline-secondary me-2 view-product-detail" data-product-id="<?= $product['id'] ?>">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <button class="btn add-to-cart-btn quick-add-to-cart" data-product-id="<?= $product['id'] ?>">
                                                    <i class="fas fa-cart-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- Sepet Butonu -->
<a href="<?= BASE_URL ?>/cart.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="cart-fab">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-badge" style="display: none;">0</span>
</a><?php
/**
 * Template 3 - Minimalist Tasarım Menü İçeriği
 */
?>

<!-- Mobil Kategori Navigasyonu (Sadece mobil görünümde) -->
<div class="mobile-category-nav">
    <div class="container">
        <div class="overflow-auto">
            <?php foreach ($categories as $index => $category): ?>
                <a class="nav-link <?= $index === 0 ? 'active' : '' ?>" href="#category-<?= $category['id'] ?>">
                    <?= h($category['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Sidebar Kategori Menüsü (Masaüstü görünümünde) -->
    <div class="col-lg-3">
        <div class="sidebar">
            <h5 class="mb-3">Kategoriler</h5>
            <div class="nav flex-column nav-pills">
                <?php foreach ($categories as $index => $category): ?>
                    <a class="nav-link <?= $index === 0 ? 'active' : '' ?>" href="#category-<?= $category['id'] ?>">
                        <?= h($category['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (isset($tableInfo)): ?>
            <div class="mt-4">
                <h5 class="mb-3">Masa Bilgisi</h5>
                <div class="d-flex align-items-center">
                    <i class="fas fa-chair me-2 fs-4"></i>
                    <div>
                        <div class="fw-bold"><?= h($tableInfo['name']) ?></div>
                        <?php if (!empty($tableInfo['location'])): ?>
                            <small class="text-muted"><?= h($tableInfo['location']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Ürünler -->
    <div class="col-lg-9">
        <?php foreach ($categories as $category): ?>
            <?php if (isset($products[$category['id']]) && !empty($products[$category['id']])): ?>
                <div id="category-<?= $category['id'] ?>" class="mb-5">
                    <h2 class="category-title"><?= h($category['name']) ?></h2>
                    
                    <div class="row">
                        <?php foreach ($products[$category['id']] as $product): ?>
                            <div class="col-md-6 col-lg-6 mb-4">
                                <div class="product-card">
                                    <div class="product-image-container">
                                        <?php
                                        $imageUrl = !empty($product['image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $product['image']) 
                                            ? ASSETS_URL . '/uploads/products/' . $product['image'] 
                                            : ASSETS_URL . '/img/no-image.jpg';
                                        ?>
                                        <img src="<?= $imageUrl ?>" class="product-image" alt="<?= h($product['name']) ?>">
                                        
                                        <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                            <div class="product-discount-badge">
                                                %<?= round((1 - $product['discount_price'] / $product['price']) * 100) ?> İndirim
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-body">
                                        <h3 class="product-title"><?= h($product['name']) ?></h3>
                                        
                                        <?php if (!empty($product['description'])): ?>
                                            <p class="product-description"><?= h($product['description']) ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="product-meta">
                                            <div>
                                                <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                                    <span class="product-discount"><?= formatCurrency($product['price']) ?></span>
                                                    <span class="product-price"><?= formatCurrency($product['discount_price']) ?></span>
                                                <?php else: ?>
                                                    <span class="product-price"><?= formatCurrency($product['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($product['preparation_time'])): ?>
                                                <div class="product-prep-time">
                                                    <i class="far fa-clock"></i> <?= $product['preparation_time'] ?> dk
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="product-footer">
                                            <?php if (!empty($product['allergens'])): ?>
                                                <div class="product-allergens">
                                                    <i class="fas fa-exclamation-circle"></i> Alerjenler
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div>
                                                <button class="btn btn-outline-secondary me-2 view-product-detail" data-product-id="<?= $product['id'] ?>">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <button class="btn add-to-cart-btn quick-add-to-cart" data-product-id="<?= $product['id'] ?>">
                                                    <i class="fas fa-cart-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- Sepet Butonu -->
<a href="<?= BASE_URL ?>/cart.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="cart-fab">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-badge" style="display: none;">0</span>
</a>