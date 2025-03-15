<?php
// Güvenlik kontrolü
define('SECURITY', true);
if(!defined('SECURITY')) die('İzin Yok..!');
require_once '../../config.php';
require_once '../../function.php';

// Veritabanı bağlantısı
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// Create MySQL connection
$db = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);

// Enable error mode
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$category_sef = isset($_GET['category_sef']) ? $_GET['category_sef'] : 'all';
$masa_id = isset($_GET['masa_id']) ? $_GET['masa_id'] : '';

if (isset($category_sef) && $category_sef != '' && $category_sef != 'all') {
    // Find selected category
    $selectedCategoryQ = $db->prepare('SELECT * FROM categories WHERE (JSON_EXTRACT(category_slug,"$.tr") LIKE ? || JSON_EXTRACT(category_slug,"$.en") LIKE ?)');
    $selectedCategoryQ->execute(["%" . $category_sef . "%", "%" . $category_sef . "%"]);
    $selectedCategory = $selectedCategoryQ->fetch(PDO::FETCH_ASSOC);

    if ($selectedCategory) {
        // List products in selected category
        $productsQ = $db->prepare('SELECT * FROM products LEFT JOIN categories ON products.product_category = categories.category_id WHERE products.product_is_active=? AND categories.category_id=? ORDER BY products.product_order ASC');
        $productsQ->execute([1, $selectedCategory['category_id']]);
        $products = $productsQ->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // List all products if no matching category
        $productsQ = $db->prepare('SELECT * FROM products LEFT JOIN categories ON products.product_category = categories.category_id WHERE products.product_is_active=? ORDER BY products.product_order ASC');
        $productsQ->execute([1]);
        $products = $productsQ->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // List all products
    $productsQ = $db->prepare('SELECT * FROM products LEFT JOIN categories ON products.product_category = categories.category_id WHERE products.product_is_active=? ORDER BY products.product_order ASC');
    $productsQ->execute([1]);
    $products = $productsQ->fetchAll(PDO::FETCH_ASSOC);
}

// Define site_upload_url function
function sites_upload_url() {
    return 'https://ateslipilicler.com/uploads';
}

// Output products HTML
foreach ($products as $product) {
    $productName = json_decode($product['product_name'], true)['tr'] ?? '';
    $productNameSafe = htmlspecialchars(strtoupper(trim($productName)), ENT_QUOTES, 'UTF-8');
    $productId = intval($product['product_id']);
    $productPrice = floatval($product['product_price']);
    
    // ÖNEMLİ: Ürün kartlarını data-* özellikleri ile çıktıla ve onclick olay işleyicisini ekle
    ?>
    <div class="product-card" 
         onclick="POS.orders.addOrder(<?= $productId ?>, '<?= $productNameSafe ?>', <?= $productPrice ?>, <?= $masa_id ?>)" 
         data-product-id="<?= $productId ?>" 
         data-product-name="<?= $productNameSafe ?>" 
         data-product-price="<?= $productPrice ?>">
        <img src="<?= sites_upload_url() . '/products/' . $product['product_image'] ?>" alt="<?= $productNameSafe ?>" class="product-image">
        <div class="product-details">
            <div class="product-name"><?= $productNameSafe ?></div>
            <div class="product-price"><?= number_format($productPrice, 2) ?>₺</div>
        </div>
    </div>
    <?php
}

// Eğer hiç ürün yoksa bir mesaj göster
if (count($products) === 0) {
    echo '<div class="alert alert-info w-100 text-center"><i class="fas fa-info-circle me-2"></i>Bu kategoride ürün bulunamadı.</div>';
}
?>