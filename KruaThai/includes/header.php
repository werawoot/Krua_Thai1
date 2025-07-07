<?php
// includes/header.php - Header template for all pages

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_name = '';
$user_role = '';

if ($is_logged_in) {
    $user_name = $_SESSION['user_name'] ?? '';
    $user_role = $_SESSION['user_role'] ?? 'customer';
}

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Set default page title if not defined
if (!isset($page_title)) {
    $page_title = 'Krua Thai - Healthy Thai Meals';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Krua Thai - อาหารไทยเพื่อสุขภาพ บริการส่งถึงบ้าน">
    <meta name="keywords" content="อาหารไทย, สุขภาพ, ส่งถึงบ้าน, subscription, healthy food">
    <meta name="author" content="Krua Thai">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="อาหารไทยเพื่อสุขภาพ บริการส่งถึงบ้าน">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:image" content="<?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/assets/images/og-image.jpg">
    
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Variables -->
    <style>
        :root {
            --olive: #86602800;
            --brown: #866028;
            --cream: #ece8e1;
            --light-cream: #f8f6f0;
            --matcha: #adbe89;
            --gray: #666;
        }
    </style>
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <!-- Logo Section -->
                <div class="logo-section">
                    <a href="/index.php" class="brand-link">
                        <img src="/assets/images/logo.png" alt="Krua Thai Logo" class="logo" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div class="logo-placeholder" style="display: none;">🍽️</div>
                        <span class="brand-text">Krua Thai</span>
                    </a>
                </div>

                <!-- Main Navigation -->
                <nav class="main-nav">
                    <ul class="nav-links">
                        <li>
                            <a href="/index.php" class="nav-link <?php echo $current_page === 'index' ? 'active' : ''; ?>">
                                หน้าหลัก
                            </a>
                        </li>
                        <li>
                            <a href="/menus.php" class="nav-link <?php echo $current_page === 'menus' ? 'active' : ''; ?>">
                                เมนูอาหาร
                            </a>
                        </li>
                        <li>
                            <a href="/subscription-plans.php" class="nav-link <?php echo $current_page === 'subscription-plans' ? 'active' : ''; ?>">
                                แพ็กเกจ
                            </a>
                        </li>
                        <?php if ($is_logged_in): ?>
                            <li>
                                <a href="/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                                    แดชบอร์ด
                                </a>
                            </li>
                            <?php if ($user_role === 'admin'): ?>
                                <li>
                                    <a href="/admin_users.php" class="nav-link <?php echo $current_page === 'admin_users' ? 'active' : ''; ?>">
                                        จัดการผู้ใช้
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <li>
                            <a href="/about.php" class="nav-link <?php echo $current_page === 'about' ? 'active' : ''; ?>">
                                เกี่ยวกับเรา
                            </a>
                        </li>
                        <li>
                            <a href="/contact.php" class="nav-link <?php echo $current_page === 'contact' ? 'active' : ''; ?>">
                                ติดต่อเรา
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- User Menu -->
                <div class="user-menu">
                    <?php if ($is_logged_in): ?>
                        <!-- Logged In User -->
                        <div class="user-dropdown">
                            <a href="/dashboard.php" class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user_name, 0, 1)) ?: '👤'; ?>
                                </div>
                                <span class="user-name"><?php echo htmlspecialchars($user_name ?: 'ผู้ใช้'); ?></span>
                            </a>
                            <div class="dropdown-menu">
                                <a href="/dashboard.php" class="dropdown-item">
                                    <span class="dropdown-icon">📊</span>
                                    แดชบอร์ด
                                </a>
                                <a href="/edit_profile.php" class="dropdown-item">
                                    <span class="dropdown-icon">👤</span>
                                    แก้ไขโปรไฟล์
                                </a>
                                <a href="/orders.php" class="dropdown-item">
                                    <span class="dropdown-icon">📋</span>
                                    ออเดอร์ของฉัน
                                </a>
                                <a href="/subscription-manage.php" class="dropdown-item">
                                    <span class="dropdown-icon">📦</span>
                                    จัดการแพ็กเกจ
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($user_role === 'admin'): ?>
                                    <a href="/admin_users.php" class="dropdown-item">
                                        <span class="dropdown-icon">👑</span>
                                        จัดการผู้ใช้
                                    </a>
                                    <div class="dropdown-divider"></div>
                                <?php endif; ?>
                                <a href="/logout.php" class="dropdown-item logout">
                                    <span class="dropdown-icon">🚪</span>
                                    ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Not Logged In -->
                        <div class="auth-buttons">
                            <a href="/login.php" class="btn-login">เข้าสู่ระบบ</a>
                            <a href="/register.php" class="btn-register">สมัครสมาชิก</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="เปิด/ปิดเมนู">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div class="mobile-nav" id="mobileNav">
            <div class="mobile-nav-content">
                <div class="mobile-nav-header">
                    <span class="mobile-nav-title">เมนู</span>
                    <button class="mobile-nav-close" id="mobileNavClose">&times;</button>
                </div>
                <ul class="mobile-nav-links">
                    <li><a href="/index.php" class="mobile-nav-link">หน้าหลัก</a></li>
                    <li><a href="/menus.php" class="mobile-nav-link">เมนูอาหาร</a></li>
                    <li><a href="/subscription-plans.php" class="mobile-nav-link">แพ็กเกจ</a></li>
                    <?php if ($is_logged_in): ?>
                        <li><a href="/dashboard.php" class="mobile-nav-link">แดชบอร์ด</a></li>
                        <li><a href="/edit_profile.php" class="mobile-nav-link">แก้ไขโปรไฟล์</a></li>
                        <li><a href="/orders.php" class="mobile-nav-link">ออเดอร์ของฉัน</a></li>
                        <?php if ($user_role === 'admin'): ?>
                            <li><a href="/admin_users.php" class="mobile-nav-link">จัดการผู้ใช้</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li><a href="/about.php" class="mobile-nav-link">เกี่ยวกับเรา</a></li>
                    <li><a href="/contact.php" class="mobile-nav-link">ติดต่อเรา</a></li>
                </ul>
                <?php if ($is_logged_in): ?>
                    <div class="mobile-nav-user">
                        <div class="mobile-user-info">
                            <div class="mobile-user-avatar">
                                <?php echo strtoupper(substr($user_name, 0, 1)) ?: '👤'; ?>
                            </div>
                            <span class="mobile-user-name"><?php echo htmlspecialchars($user_name ?: 'ผู้ใช้'); ?></span>
                        </div>
                        <a href="/logout.php" class="mobile-logout-btn">ออกจากระบบ</a>
                    </div>
                <?php else: ?>
                    <div class="mobile-nav-auth">
                        <a href="/login.php" class="mobile-auth-btn login">เข้าสู่ระบบ</a>
                        <a href="/register.php" class="mobile-auth-btn register">สมัครสมาชิก</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <main class="main-content">

<style>
/* Header Specific Styles */
.brand-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
}

.logo-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

/* User Dropdown */
.user-dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    padding: 1rem 0;
    min-width: 220px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1000;
    margin-top: 0.5rem;
}

.user-dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    color: var(--gray);
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    background: none;
    width: 100%;
    font-size: 0.95rem;
}

.dropdown-item:hover {
    background: var(--light-cream);
    color: var(--olive);
}

.dropdown-item.logout:hover {
    background: #ffe6e6;
    color: #dc3545;
}

.dropdown-icon {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

.dropdown-divider {
    height: 1px;
    background: var(--cream);
    margin: 0.5rem 0;
}

/* Mobile Menu Styles */
.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 4px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
}

.mobile-menu-toggle span {
    width: 25px;
    height: 3px;
    background: white;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.mobile-nav {
    position: fixed;
    top: 0;
    right: -100%;
    width: 100%;
    max-width: 350px;
    height: 100vh;
    background: white;
    z-index: 2000;
    transition: right 0.3s ease;
    box-shadow: -5px 0 20px rgba(0, 0, 0, 0.1);
}

.mobile-nav.active {
    right: 0;
}

.mobile-nav-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 0;
}

.mobile-nav-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--olive), var(--matcha));
    color: white;
}

.mobile-nav-title {
    font-size: 1.3rem;
    font-weight: 600;
}

.mobile-nav-close {
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.mobile-nav-links {
    list-style: none;
    padding: 1rem 0;
    margin: 0;
    flex: 1;
}

.mobile-nav-links li {
    margin: 0;
}

.mobile-nav-link {
    display: block;
    padding: 1rem 1.5rem;
    color: var(--gray);
    text-decoration: none;
    border-bottom: 1px solid var(--light-cream);
    transition: all 0.3s ease;
}

.mobile-nav-link:hover {
    background: var(--light-cream);
    color: var(--olive);
}

.mobile-nav-user {
    padding: 1.5rem;
    background: var(--light-cream);
    border-top: 1px solid var(--cream);
}

.mobile-user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.mobile-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--brown);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.mobile-user-name {
    color: var(--olive);
    font-weight: 600;
}

.mobile-logout-btn {
    display: block;
    width: 100%;
    padding: 0.75rem;
    background: #dc3545;
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
}

.mobile-nav-auth {
    padding: 1.5rem;
    background: var(--light-cream);
    border-top: 1px solid var(--cream);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.mobile-auth-btn {
    padding: 0.75rem;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.mobile-auth-btn.login {
    background: var(--brown);
    color: white;
}

.mobile-auth-btn.register {
    background: white;
    color: var(--brown);
    border: 2px solid var(--brown);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .main-nav {
        display: none;
    }
    
    .mobile-menu-toggle {
        display: flex;
    }
    
    .auth-buttons {
        display: none;
    }
    
    .user-dropdown .user-info {
        padding: 0.5rem;
    }
    
    .user-name {
        display: none;
    }
}

@media (max-width: 480px) {
    .header-content {
        padding: 0.75rem 0;
    }
    
    .brand-text {
        font-size: 1.2rem;
    }
    
    .logo {
        width: 40px;
        height: 40px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileNav = document.getElementById('mobileNav');
    const mobileNavClose = document.getElementById('mobileNavClose');
    
    // Open mobile menu
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileNav.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }
    
    // Close mobile menu
    if (mobileNavClose) {
        mobileNavClose.addEventListener('click', function() {
            mobileNav.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    // Close mobile menu when clicking on links
    document.querySelectorAll('.mobile-nav-link').forEach(link => {
        link.addEventListener('click', function() {
            mobileNav.classList.remove('active');
            document.body.style.overflow = '';
        });
    });
    
    // Close mobile menu when clicking outside
    mobileNav.addEventListener('click', function(e) {
        if (e.target === mobileNav) {
            mobileNav.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    // Handle dropdown on mobile
    document.querySelectorAll('.user-dropdown').forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                const menu = this.querySelector('.dropdown-menu');
                menu.style.opacity = menu.style.opacity === '1' ? '0' : '1';
                menu.style.visibility = menu.style.visibility === 'visible' ? 'hidden' : 'visible';
            }
        });
    });
});
</script>