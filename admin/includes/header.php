<?php
/**
 * Admin Panel Header
 */

// Kimlik doğrulama kontrolü
requireLogin();

// Kullanıcı bilgilerini al
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Admin Panel') ?> - Restoran Menü Sistemi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= ADMIN_ASSETS_URL ?>/css/admin.css">
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="active">
            <div class="sidebar-header">
                <img src="<?= ADMIN_ASSETS_URL ?>/img/logo.png" alt="Logo" class="logo-large">
                <img src="<?= ADMIN_ASSETS_URL ?>/img/logo-small.png" alt="Logo" class="logo-small">
            </div>
            
            <ul class="list-unstyled components">
                <?php if (isSuperAdmin() || isWaiter() || isKitchen()): ?>
                    <li class="<?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>">
                        <a href="<?= ADMIN_URL ?>/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Pano</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if (isSuperAdmin() || isWaiter()): ?>
                    <li class="<?= (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'active' : '' ?>">
                        <a href="<?= ADMIN_URL ?>/orders.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Siparişler</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if (isSuperAdmin() || isKitchen()): ?>
                    <li class="<?= (basename($_SERVER['PHP_SELF']) == 'kitchen.php') ? 'active' : '' ?>">
                        <a href="<?= ADMIN_URL ?>/kitchen.php">
                            <i class="fas fa-utensils"></i>
                            <span>Mutfak</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if (isSuperAdmin()): ?>
                    <li class="<?= (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : '' ?>">
                        <a href="<?= ADMIN_URL ?>/products.php">
                            <i class="fas fa-hamburger"></i>
                            <span>Ürünler</span>
                        </a>
                    </li>
                    
                    <li class="<?= (basename($_SERVER['PHP_SELF']) == 'categories.php') ? 'active' : '' ?>">
                        <a href="<?= ADMIN_URL ?>/categories.php">
                            <i class="fas fa-tags"></i>
                            <span>Kategoriler</span>
                        </a>
                    </li>
                    
                    <li class="<?= (basename($_SERVER['PHP_SELF']) == 'tables.php') ? 'active' : '' ?>">
                        <a href="<?= ADMIN_URL ?>/tables.php">
                            <i class="fas fa-chair"></i>
                            <span>Masalar</span>
                        </a>
                    </li>
                    
                    <li class="<?= (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : '' ?>">
                        <a href="<?= ADMIN_URL ?>/users.php">
                            <i class="fas fa-users"></i>
                            <span>Kullanıcılar</span>
                        </a>
                    </li>
                    
                    <li class="<?= (in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'seo.php', 'qr-designs.php'])) ? 'active' : '' ?>">
                        <a href="#settingsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                            <i class="fas fa-cog"></i>
                            <span>Ayarlar</span>
                        </a>
                        <ul class="collapse list-unstyled" id="settingsSubmenu">
                            <li class="<?= (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : '' ?>">
                                <a href="<?= ADMIN_URL ?>/settings.php">
                                    <i class="fas fa-sliders-h"></i>
                                    <span>Genel Ayarlar</span>
                                </a>
                            </li>
                            <li class="<?= (basename($_SERVER['PHP_SELF']) == 'seo.php') ? 'active' : '' ?>">
                                <a href="<?= ADMIN_URL ?>/seo.php">
                                    <i class="fas fa-search"></i>
                                    <span>SEO Ayarları</span>
                                </a>
                            </li>
                            <li class="<?= (basename($_SERVER['PHP_SELF']) == 'qr-designs.php') ? 'active' : '' ?>">
                                <a href="<?= ADMIN_URL ?>/qr-designs.php">
                                    <i class="fas fa-qrcode"></i>
                                    <span>QR Tasarımları</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <!-- Page Content -->
        <div id="content">
            <!-- Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="nav navbar-nav ms-auto">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                                   data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i>
                                    <?= h($currentUser['full_name']) ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li>
                                        <a class="dropdown-item" href="<?= ADMIN_URL ?>/profile.php">
                                            <i class="fas fa-user-cog me-2"></i>Profil
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?= ADMIN_URL ?>/logout.php">
                                            <i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Page Content -->
            <div class="container-fluid content-container">
                <div class="row mb-4">
                    <div class="col">
                        <h1 class="page-title"><?= h($pageTitle ?? 'Admin Panel') ?></h1>
                    </div>
                </div>
                
                <?= displayFlashMessages() ?>