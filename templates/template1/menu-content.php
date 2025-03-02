<?php
/**
 * Template 1 - Modern Tasarım Menü İçeriği
 */
?>

<!-- Kategori Navigasyonu -->
<div class="container">
    <div class="category-nav">
        <div class="category-nav-scroll">
            <ul class="nav flex-nowrap overflow-auto">
                <?php foreach ($categories as $index => $category): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $index === 0 ? 'active' : '' ?>" href="#category-<?= $category['id'] ?>">
                            <?= h($category['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="container">
    <?php foreach ($categories as $category): ?>
        <?php if (isset($products[$category['id']]) && !empty($products[$category['id']])): ?>
            <div id="category-<?= $category['id'] ?>" class="mb-5">
                <h2 class="category-title"><?= h($category['name']) ?></h2>
                
                <div class="row">
                    <?php foreach ($products[$category['id']] as $product): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card product-card" data-product-id="<?= $product['id'] ?>">
                                <div class="product-image-container">
                                    <?php
                                    $imageUrl = !empty($product['image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $product['image']) 
                                        ? ASSETS_URL . '/uploads/products/' . $product['image'] 
                                        : ASSETS_URL . '/img/no-image.jpg';
                                    ?>
                                    <img src="<?= $imageUrl ?>" class="card-img-top product-image" alt="<?= h($product['name']) ?>">
                                    
                                    <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                        <span class="badge bg-danger position-absolute top-0 end-0 mt-2 me-2">
                                            %<?= round((1 - $product['discount_price'] / $product['price']) * 100) ?> İndirim
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <h5 class="product-title"><?= h($product['name']) ?></h5>
                                    
                                    <?php if (!empty($product['description'])): ?>
                                        <p class="product-description"><?= h($product['description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <div class="product-price">
                                            <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                                <span class="product-discount"><?= formatCurrency($product['price']) ?></span>
                                                <?= formatCurrency($product['discount_price']) ?>
                                            <?php else: ?>
                                                <?= formatCurrency($product['price']) ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button class="btn add-to-cart-btn" data-product-id="<?= $product['id'] ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
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

<!-- Sepet Butonu -->
<a href="<?= BASE_URL ?>/cart.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="cart-fab">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-badge" style="display: none;">0</span>
</a>