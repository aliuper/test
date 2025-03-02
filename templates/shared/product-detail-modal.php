<?php
/**
 * Ürün Detay Modalı (Tüm şablonlarda ortak)
 */
?>

<!-- Ürün Detay Modalı -->
<div class="modal fade product-detail-modal" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailModalLabel">Ürün Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <img id="productDetailImage" src="" alt="Ürün Görseli" class="product-detail-image img-fluid mb-3">
                    </div>
                    <div class="col-md-6">
                        <h2 id="productDetailTitle" class="product-detail-title"></h2>
                        <p id="productDetailDescription" class="product-detail-description"></p>
                        
                        <div id="productDetailPrice" class="product-detail-price mb-4"></div>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <strong>Hazırlama Süresi:</strong>
                                </div>
                                <div id="productDetailPrepTime"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <strong>Alerjenler:</strong>
                                </div>
                                <div id="productDetailAllergens"></div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="productQuantity" class="form-label">Miktar:</label>
                            <div class="product-detail-quantity">
                                <button type="button" class="quantity-btn" id="decreaseQuantity">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="productQuantity" class="quantity-input" value="1" min="1" max="10">
                                <button type="button" class="quantity-btn" id="increaseQuantity">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="productNote" class="form-label">Özel İstek / Not:</label>
                            <textarea id="productNote" class="form-control" rows="2" placeholder="Özel isteklerinizi belirtebilirsiniz..."></textarea>
                        </div>
                        
                        <button type="button" id="addToCartModalBtn" class="btn add-to-cart-modal-btn" data-product-id="">
                            <i class="fas fa-cart-plus me-2"></i> Sepete Ekle
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>