<?php
/**
 * Template 2 - Klasik Tasarım Menü İçeriği
 */
?>

<!-- Kategori Navigasyonu -->
<div class="container">
    <div class="category-nav">
        <ul class="nav nav-pills nav-fill">
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

<div class="container">
    <?php foreach ($categories as $category): ?>
        <?php if (isset($products[$category['id']]) && !empty($products[$category['id']])): ?>
            <div id="category-<?= $category['id'] ?>" class="mb-5">
                <h2 class="category-title"><?= h($category['name']) ?></h2>
                
                <div class="product-list">
                    <?php foreach ($products[$category['id']] as $product): ?>
                        <div class="product-item">
                            <div class="product-image-container">
                                <?php
                                $imageUrl = !empty($product['image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $product['image']) 
                                    ? ASSETS_URL . '/uploads/products/' . $product['image'] 
                                    : ASSETS_URL . '/img/no-image.jpg';
                                ?>
                                <img src="<?= $imageUrl ?>" class="product-image" alt="<?= h($product['name']) ?>">
                            </div>
                            
                            <div class="product-details">
                                <h3 class="product-title"><?= h($product['name']) ?></h3>
                                
                                <?php if (!empty($product['description'])): ?>
                                    <p class="product-description"><?= h($product['description']) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['allergens'])): ?>
                                    <div class="product-allergens mb-2">
                                        <small>
                                            <i class="fas fa-exclamation-circle me-1"></i> Alerjenler: 
                                            <?php 
                                            $allergens = explode(',', $product['allergens']);
                                            foreach ($allergens as $index => $allergen): 
                                                echo '<span class="badge">' . h(trim($allergen)) . '</span>';
                                            endforeach; 
                                            ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-action">
                                    <div class="product-price-container">
                                        <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                            <span class="product-discount"><?= formatCurrency($product['price']) ?></span>
                                            <span class="product-price"><?= formatCurrency($product['discount_price']) ?></span>
                                        <?php else: ?>
                                            <span class="product-price"><?= formatCurrency($product['price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <button class="btn add-to-cart-btn quick-add-to-cart" data-product-id="<?= $product['id'] ?>">
                                            <i class="fas fa-plus me-1"></i> Ekle
                                        </button>
                                        <button class="btn btn-outline-secondary ms-2 view-product-detail" data-product-id="<?= $product['id'] ?>">
                                            <i class="fas fa-info-circle"></i>
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