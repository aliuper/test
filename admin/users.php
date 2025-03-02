<?php
/**
 * Admin Panel - Kullanıcı Yönetimi
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Giriş yapılmamışsa login sayfasına yönlendir
requireLogin();

// Sadece süper admin erişebilir
if (!isSuperAdmin()) {
    redirect(ADMIN_URL . '/unauthorized.php');
}

// Sayfa başlığı
$pageTitle = 'Kullanıcı Yönetimi';

// Kullanıcı ekle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password']; // Şifreyi sanitize etme
        $role = sanitizeInput($_POST['role']);
        $fullName = sanitizeInput($_POST['full_name']);
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Zorunlu alanları kontrol et
        if (empty($username) || empty($email) || empty($password) || empty($role) || empty($fullName)) {
            setFlashMessage('error', 'Tüm zorunlu alanları doldurun.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('error', 'Geçerli bir e-posta adresi girin.');
        } elseif (!isStrongPassword($password)) {
            setFlashMessage('error', 'Şifre en az 8 karakter uzunluğunda olmalı ve büyük harf, küçük harf, rakam ve özel karakter içermelidir.');
        } else {
            // Kullanıcı adı ve e-posta benzersiz olmalı
            $existingUser = dbQuerySingle("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $email]);
            
            if ($existingUser) {
                if ($existingUser['username'] === $username) {
                    setFlashMessage('error', 'Bu kullanıcı adı zaten kullanılıyor.');
                } else {
                    setFlashMessage('error', 'Bu e-posta adresi zaten kullanılıyor.');
                }
            } else {
                // Şifreyi hashle
                $hashedPassword = hashPassword($password);
                
                // Kullanıcıyı ekle
                $result = dbInsert("
                    INSERT INTO users (username, password, email, role, full_name, phone, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ", [$username, $hashedPassword, $email, $role, $fullName, $phone, $status]);
                
                if ($result) {
                    setFlashMessage('success', 'Kullanıcı başarıyla eklendi.');
                    
                    // Olay kaydı oluştur
                    $userId = $_SESSION['user_id'];
                    dbInsert("
                        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ", [$userId, 'create', 'user', $result, "Yeni kullanıcı eklendi: $username"]);
                    
                    // Yönlendir
                    redirect(ADMIN_URL . '/users.php');
                } else {
                    setFlashMessage('error', 'Kullanıcı eklenirken bir hata oluştu.');
                }
            }
        }
    }
}

// Kullanıcı sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'], $_POST['user_id'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $userId = (int)$_POST['user_id'];
        
        // Kendi hesabını silmeyi engelle
        if ($userId === (int)$_SESSION['user_id']) {
            setFlashMessage('error', 'Kendi hesabınızı silemezsiniz.');
        } else {
            // Kullanıcıyı getir
            $user = dbQuerySingle("SELECT * FROM users WHERE id = ?", [$userId]);
            
            if (!$user) {
                setFlashMessage('error', 'Kullanıcı bulunamadı.');
            } else {
                // Kullanıcıyı sil
                $result = dbExecute("DELETE FROM users WHERE id = ?", [$userId]);
                
                if ($result) {
                    setFlashMessage('success', 'Kullanıcı başarıyla silindi.');
                    
                    // Olay kaydı oluştur
                    $currentUserId = $_SESSION['user_id'];
                    dbInsert("
                        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ", [$currentUserId, 'delete', 'user', $userId, "Kullanıcı silindi: {$user['username']}"]);
                } else {
                    setFlashMessage('error', 'Kullanıcı silinirken bir hata oluştu.');
                }
            }
        }
    }
    
    // Yönlendir
    redirect(ADMIN_URL . '/users.php');
}

// Kullanıcı durumunu güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['user_id'], $_POST['status'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $userId = (int)$_POST['user_id'];
        $status = (int)$_POST['status'] ? 1 : 0;
        
        // Kendi hesabını devre dışı bırakmayı engelle
        if ($userId === (int)$_SESSION['user_id'] && $status === 0) {
            setFlashMessage('error', 'Kendi hesabınızı devre dışı bırakamazsınız.');
        } else {
            // Kullanıcıyı getir
            $user = dbQuerySingle("SELECT * FROM users WHERE id = ?", [$userId]);
            
            if (!$user) {
                setFlashMessage('error', 'Kullanıcı bulunamadı.');
            } else {
                // Durumu güncelle
                $result = dbExecute("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);
                
                if ($result) {
                    $statusText = $status ? 'etkinleştirildi' : 'devre dışı bırakıldı';
                    setFlashMessage('success', 'Kullanıcı durumu başarıyla güncellendi.');
                    
                    // Olay kaydı oluştur
                    $currentUserId = $_SESSION['user_id'];
                    dbInsert("
                        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ", [$currentUserId, 'update_status', 'user', $userId, "Kullanıcı durumu $statusText: {$user['username']}"]);
                } else {
                    setFlashMessage('error', 'Kullanıcı durumu güncellenirken bir hata oluştu.');
                }
            }
        }
    }
    
    // Yönlendir
    redirect(ADMIN_URL . '/users.php');
}

// Kullanıcıları getir
$users = dbQuery("SELECT * FROM users ORDER BY id");

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css">
<style>
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: #fff;
    }
    .user-role {
        font-size: 0.85rem;
        text-transform: capitalize;
    }
    .actions-column {
        width: 120px;
    }
    .user-status-badge {
        width: 100px;
    }
</style>
';

// Ekstra JS
$extraJs = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        // DataTable başlat
        $("#users-table").DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Turkish.json"
            },
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [0, 6] }
            ]
        });
        
        // Silme onayı
        $(".delete-user").on("click", function(e) {
            e.preventDefault();
            
            const userName = $(this).data("user-name");
            
            if (confirm(userName + " kullanıcısını silmek istediğinize emin misiniz? Bu işlem geri alınamaz!")) {
                $(this).closest("form").submit();
            }
        });
        
        // Şifre göster/gizle
        $("#togglePassword").on("click", function() {
            const passwordInput = $("#password");
            const icon = $(this).find("i");
            
            if (passwordInput.attr("type") === "password") {
                passwordInput.attr("type", "text");
                icon.removeClass("fa-eye").addClass("fa-eye-slash");
            } else {
                passwordInput.attr("type", "password");
                icon.removeClass("fa-eye-slash").addClass("fa-eye");
            }
        });
        
        // Şifre oluşturma
        $("#generatePassword").on("click", function() {
            const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()";
            let password = "";
            
            // En az 8 karakter, 1 büyük harf, 1 küçük harf, 1 rakam, 1 özel karakter
            password += chars.charAt(Math.floor(Math.random() * 26)); // Büyük harf
            password += chars.charAt(Math.floor(Math.random() * 26) + 26); // Küçük harf
            password += chars.charAt(Math.floor(Math.random() * 10) + 52); // Rakam
            password += chars.charAt(Math.floor(Math.random() * 10) + 62); // Özel karakter
            
            // Geri kalan karakterler
            for (let i = 4; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            // Karakterleri karıştır
            password = password.split("").sort(() => 0.5 - Math.random()).join("");
            
            // Şifreyi input alanına yerleştir
            $("#password").val(password).attr("type", "text");
            $("#togglePassword").find("i").removeClass("fa-eye").addClass("fa-eye-slash");
        });
    });
</script>
';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <!-- Kullanıcı Ekle -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Yeni Kullanıcı Ekle</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="generatePassword" title="Güçlü şifre oluştur">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                            <div class="form-text">Şifre en az 8 karakter uzunluğunda olmalı ve büyük harf, küçük harf, rakam ve özel karakter içermelidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="super_admin">Süper Admin</option>
                                <option value="waiter">Garson</option>
                                <option value="kitchen">Mutfak</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="status" name="status" checked>
                            <label class="form-check-label" for="status">Aktif</label>
                        </div>
                        
                        <button type="submit" name="add_user" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Kullanıcı Ekle
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Kullanıcı Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Kullanıcı Listesi</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="users-table" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>Kullanıcı Adı</th>
                                    <th>Ad Soyad</th>
                                    <th>E-posta</th>
                                    <th>Rol</th>
                                    <th>Durum</th>
                                    <th class="actions-column">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            // Avatar arka plan rengi
                                            $colors = ['#4b6584', '#a5b1c2', '#778ca3', '#0fb9b1', '#20bf6b', '#3867d6', '#8854d0', '#fed330', '#fc5c65', '#eb3b5a', '#2d98da', '#3dc1d3'];
                                            $colorIndex = crc32($user['username']) % count($colors);
                                            $avatarColor = $colors[$colorIndex];
                                            $initials = strtoupper(substr($user['full_name'], 0, 1));
                                            ?>
                                            <div class="user-avatar" style="background-color: <?= $avatarColor ?>;">
                                                <?= $initials ?>
                                            </div>
                                        </td>
                                        <td><?= h($user['username']) ?></td>
                                        <td><?= h($user['full_name']) ?></td>
                                        <td><?= h($user['email']) ?></td>
                                        <td>
                                            <?php
                                            $roleName = '';
                                            $roleClass = '';
                                            
                                            switch ($user['role']) {
                                                case 'super_admin':
                                                    $roleName = 'Süper Admin';
                                                    $roleClass = 'bg-danger';
                                                    break;
                                                case 'waiter':
                                                    $roleName = 'Garson';
                                                    $roleClass = 'bg-primary';
                                                    break;
                                                case 'kitchen':
                                                    $roleName = 'Mutfak';
                                                    $roleClass = 'bg-success';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $roleClass ?> user-role"><?= $roleName ?></span>
                                        </td>
                                        <td>
                                            <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $user['status'] ? 0 : 1 ?>">
                                                
                                                <button type="submit" name="update_status" class="btn btn-sm user-status-badge <?= $user['status'] ? 'btn-success' : 'btn-danger' ?>" <?= $user['id'] === $_SESSION['user_id'] && $user['status'] ? 'disabled' : '' ?>>
                                                    <i class="fas <?= $user['status'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                                                    <?= $user['status'] ? 'Aktif' : 'Pasif' ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <a href="<?= ADMIN_URL ?>/user-edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary me-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                
                                                <button type="button" class="btn btn-sm btn-danger delete-user" data-user-name="<?= h($user['username']) ?>" <?= $user['id'] === $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>