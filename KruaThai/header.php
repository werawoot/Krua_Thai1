<?php
/**
 * Somdul Table - Reusable Header Component
 * File: header.php
 * Include this file in every page that needs the navigation and promo banner
 */

// Ensure session is started (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<style>
/* Header Component Styles */

/* BaticaSans Font Import - Local Files */
@font-face {
    font-family: 'BaticaSans';
    src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
        url('./Font/BaticaSans-Regular.woff') format('woff'),
        url('./Font/BaticaSans-Regular.ttf') format('truetype');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'BaticaSans';
    src: url('./Font/BaticaSans-Italic.woff2') format('woff2'),
        url('./Font/BaticaSans-Italic.woff') format('woff'),
        url('./Font/BaticaSans-Italic.ttf') format('truetype');
    font-weight: 400;
    font-style: italic;
    font-display: swap;
}

@font-face {
    font-family: 'BaticaSans';
    src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
        url('./Font/BaticaSans-Regular.woff') format('woff'),
        url('./Font/BaticaSans-Regular.ttf') format('truetype');
    font-weight: 500;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'BaticaSans';
    src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
        url('./Font/BaticaSans-Regular.woff') format('woff'),
        url('./Font/BaticaSans-Regular.ttf') format('truetype');
    font-weight: 700;
    font-style: normal;
    font-display: swap;
}

/* CSS Variables (ensure they're available) */
:root {
    --brown: #bd9379;
    --white: #ffffff;
    --cream: #ece8e1;
    --sage: #adb89d;
    --curry: #cf723a;
    --text-dark: #2c3e50;
    --text-gray: #7f8c8d;
    --border-light: #d4c4b8;
    --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
    --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Mobile Navigation Hamburger Menu */
.mobile-menu-toggle {
    display: none;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    z-index: 1001;
    position: relative;
    transition: var(--transition);
}

.mobile-menu-toggle:hover {
    background: rgba(189, 147, 121, 0.1);
    border-radius: 8px;
}

.mobile-menu-toggle:active {
    transform: scale(0.95);
    background: rgba(189, 147, 121, 0.2);
}

.hamburger {
    width: 24px;
    height: 20px;
    position: relative;
    cursor: pointer;
}

.hamburger span {
    display: block;
    position: absolute;
    height: 3px;
    width: 100%;
    background: var(--brown);
    border-radius: 2px;
    opacity: 1;
    left: 0;
    transform: rotate(0deg);
    transition: 0.25s ease-in-out;
}

.hamburger span:nth-child(1) {
    top: 0px;
}

.hamburger span:nth-child(2),
.hamburger span:nth-child(3) {
    top: 8px;
}

.hamburger span:nth-child(4) {
    top: 16px;
}

.hamburger.open span:nth-child(1) {
    top: 8px;
    width: 0%;
    left: 50%;
}

.hamburger.open span:nth-child(2) {
    transform: rotate(45deg);
}

.hamburger.open span:nth-child(3) {
    transform: rotate(-45deg);
}

.hamburger.open span:nth-child(4) {
    top: 8px;
    width: 0%;
    left: 50%;
}

/* Mobile Navigation Menu */
.mobile-nav-menu {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    background: var(--white);
    z-index: 1100; /* Higher than navbar */
    padding-top: 80px;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
}

.mobile-nav-menu.active {
    transform: translateX(0);
    display: block; /* Ensure it's visible when active */
}

.mobile-nav-links {
    list-style: none;
    padding: 2rem;
    margin: 0;
}

.mobile-nav-links li {
    margin-bottom: 1.5rem;
}

.mobile-nav-links a {
    display: block;
    padding: 1rem 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-dark);
    text-decoration: none;
    border-bottom: 1px solid var(--cream);
    transition: color 0.3s ease;
    font-family: 'BaticaSans', sans-serif;
}

.mobile-nav-links a:hover {
    color: var(--brown);
}

/* Main Navbar */
.navbar {
    position: fixed;
    top: 38px;
    left: 0;
    right: 0;
    background: #ece8e1;
    backdrop-filter: blur(10px);
    z-index: 1000;
    transition: var(--transition);
    box-shadow: var(--shadow-soft);
}

.navbar, .navbar * {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.navbar nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    text-decoration: none;
    color: var(--brown);
}

.logo-icon {
    width: 45px;
    height: 45px;
    background: var(--brown);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.5rem;
    font-family: 'BaticaSans', sans-serif;
    font-weight: 700;
}

.logo-text {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--brown);
    font-family: 'BaticaSans', sans-serif;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 2rem;
    align-items: center;
}

.nav-links a {
    text-decoration: none;
    color: var(--text-gray);
    font-weight: 500;
    font-family: 'BaticaSans', sans-serif;
    transition: var(--transition);
}

.nav-links a:hover {
    color: var(--brown);
}

.nav-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.btn {
    padding: 0.8rem 1.5rem;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-family: 'BaticaSans', sans-serif;
    text-decoration: none;
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--brown);
    color: var(--white);
    box-shadow: var(--shadow-soft);
}

.btn-primary:hover {
    background: #a8855f;
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.btn-secondary {
    background: transparent;
    color: var(--brown);
    border: 2px solid var(--brown);
}

.btn-secondary:hover {
    background: var(--brown);
    color: var(--white);
}

/* Profile Icon Styles */
.profile-link {
    text-decoration: none;
    transition: var(--transition);
}

.profile-icon {
    width: 45px;
    height: 45px;
    background: var(--brown);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    transition: var(--transition);
    box-shadow: var(--shadow-soft);
}

.profile-icon:hover {
    background: #a8855f;
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.profile-icon svg {
    width: 24px;
    height: 24px;
}

/* Promotional Banner Styles */
.promo-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: #cf723a;
    color: var(--white);
    text-align: center;
    padding: 8px 20px;
    font-family: 'BaticaSans', sans-serif;
    font-weight: 700;
    font-size: 14px;
    z-index: 1002;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    animation: glow 2s ease-in-out infinite alternate;
}

.promo-banner-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.promo-icon {
    font-size: 16px;
    animation: bounce 1.5s ease-in-out infinite;
}

.promo-text {
    letter-spacing: 0.5px;
}

/* Desktop text - hide on mobile */
.mobile-text {
    display: none;
}

.desktop-text {
    display: inline;
}

.promo-close {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--white);
    font-size: 18px;
    cursor: pointer;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.promo-close:hover {
    opacity: 1;
}

@keyframes glow {
    from {
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    to {
        box-shadow: 0 2px 20px rgba(207, 114, 58, 0.3);
    }
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-3px);
    }
    60% {
        transform: translateY(-2px);
    }
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .promo-banner {
        padding: 4px 10px;
        font-size: 10px;
        height: 28px;
        display: flex;
        align-items: center;
    }
    
    .navbar {
        top: 28px;
    }
    
    .promo-banner-content {
        flex-direction: row;
        gap: 6px;
        justify-content: center;
    }
    
    .promo-text {
        letter-spacing: 0.3px;
    }
    
    .promo-icon {
        font-size: 12px;
    }
    
    .promo-close {
        right: 8px;
        font-size: 14px;
    }

    /* Mobile text switching */
    .desktop-text {
        display: none;
    }

    .mobile-text {
        display: inline;
    }

    /* Mobile Navigation */
    .mobile-menu-toggle {
        display: block !important; /* Force display on mobile */
        z-index: 1101; /* Above mobile menu */
    }

    .nav-links {
        display: none !important; /* Hide desktop nav */
    }

    .navbar > div {
        padding: 0.8rem 1rem !important;
        display: grid !important;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 1rem;
    }

    .logo {
        order: 2;
        justify-self: center;
    }

    .logo img {
        height: 50px !important;
    }

    .mobile-menu-toggle {
        order: 1;
        justify-self: start;
    }

    .nav-actions {
        order: 3;
        justify-self: end;
        gap: 0.5rem;
    }

    .nav-actions .btn {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .mobile-nav-menu {
        display: block !important; /* Ensure mobile menu is available */
    }
}

@media (max-width: 480px) {
    .promo-banner {
        padding: 2px 5px;
        font-size: 9px;
        height: 24px;
    }

    .promo-banner-content {
        gap: 4px;
    }

    .promo-icon {
        font-size: 10px;
    }

    .promo-close {
        font-size: 12px;
        right: 5px;
    }

    .navbar {
        top: 24px;
    }
}

/* Body margin adjustment for pages using this header */
body.has-header {
    margin-top: 110px; /* Default spacing for desktop */
}

@media (max-width: 768px) {
    body.has-header {
        margin-top: 105px; /* Mobile spacing */
    }
}

@media (max-width: 480px) {
    body.has-header {
        margin-top: 100px; /* Small mobile spacing */
    }
}
</style>

<!-- Promotional Banner -->
<div class="promo-banner" id="promoBanner">
    <div class="promo-banner-content">
        <span class="promo-icon">🪵</span>
        <span class="promo-text">
            <span class="desktop-text">50% OFF First Week + Free Cookies for Life</span>
            <span class="mobile-text">50% OFF + Free Cookies</span>
        </span>
        <span class="promo-icon">🎉</span>
    </div>
    <button class="promo-close" onclick="closePromoBanner()" title="Close">×</button>
</div>

<!-- Navigation -->
<nav class="navbar">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1200px; margin: 0 auto; width: 100%;">
        <!-- Mobile hamburger menu -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleMobileMenu()" type="button" aria-label="Toggle mobile menu">
            <div class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>

        <a href="index.php" class="logo">
            <img src="./assets/image/LOGO_BG2.png" alt="Somdul Table" style="height: 80px; width: auto;">
        </a>

        <ul class="nav-links">
            <li><a href="./menus.php">Menu</a></li>
            <li><a href="./meal-kits.php">Meal-Kits</a></li>
            <li><a href="./index.php#how-it-works">How It Works</a></li>
            <li><a href="./blogs.php">About</a></li>
            <li><a href="./contact.php">Contact</a></li>
        </ul>
        
        <div class="nav-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- User is logged in - show profile icon -->
                <a href="dashboard.php" class="profile-link" title="Go to Dashboard">
                    <div class="profile-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </a>
            <?php else: ?>
                <!-- User is not logged in - show sign in button -->
                <a href="login.php" class="btn btn-secondary">Sign In</a>
            <?php endif; ?>
            <a href="subscribe.php" class="btn btn-primary">Get Started</a>
        </div>
    </div>
</nav>

<!-- Mobile Navigation Menu -->
<div class="mobile-nav-menu" id="mobileNavMenu">
    <ul class="mobile-nav-links">
        <li><a href="./menus.php" onclick="closeMobileMenu()">Menu</a></li>
        <li><a href="./meal-kits.php" onclick="closeMobileMenu()">Meal-Kits</a></li>
        <li><a href="./index.php#how-it-works" onclick="closeMobileMenu()">How It Works</a></li>
        <li><a href="./blogs.php" onclick="closeMobileMenu()">About</a></li>
        <li><a href="./contact.php" onclick="closeMobileMenu()">Contact</a></li>
    </ul>
</div>

<script>
// Header JavaScript Functions

// Global variable to track menu state
window.mobileMenuOpen = false;

// Mobile Navigation Functions
function toggleMobileMenu() {
    try {
        const mobileMenu = document.getElementById('mobileNavMenu');
        const hamburger = document.querySelector('.hamburger');
        const body = document.body;
        
        if (!mobileMenu || !hamburger) {
            console.error('Mobile menu elements not found');
            return;
        }
        
        // Toggle the menu
        mobileMenu.classList.toggle('active');
        hamburger.classList.toggle('open');
        window.mobileMenuOpen = !window.mobileMenuOpen;
        
        // Handle body scroll
        if (mobileMenu.classList.contains('active')) {
            body.style.overflow = 'hidden';
        } else {
            body.style.overflow = 'auto';
        }
        
    } catch (error) {
        console.error('Error in toggleMobileMenu:', error);
    }
}

function closeMobileMenu() {
    try {
        const mobileMenu = document.getElementById('mobileNavMenu');
        const hamburger = document.querySelector('.hamburger');
        const body = document.body;
        
        if (mobileMenu && hamburger) {
            mobileMenu.classList.remove('active');
            hamburger.classList.remove('open');
            body.style.overflow = 'auto';
            window.mobileMenuOpen = false;
        }
    } catch (error) {
        console.error('Error in closeMobileMenu:', error);
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const mobileMenu = document.getElementById('mobileNavMenu');
    const hamburger = document.getElementById('mobileMenuToggle');
    
    if (mobileMenu && hamburger && mobileMenu.classList.contains('active') && 
        !mobileMenu.contains(event.target) && 
        !hamburger.contains(event.target)) {
        closeMobileMenu();
    }
});

// Close mobile menu on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && window.mobileMenuOpen) {
        closeMobileMenu();
    }
});

// Promo Banner Functions
function closePromoBanner() {
    const promoBanner = document.getElementById('promoBanner');
    const navbar = document.querySelector('.navbar');
    
    if (promoBanner && navbar) {
        promoBanner.style.transform = 'translateY(-100%)';
        promoBanner.style.opacity = '0';
        
        setTimeout(() => {
            promoBanner.style.display = 'none';
            navbar.style.top = '0';
        }, 300);
    }
}

// Navbar background on scroll
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        if (window.scrollY > 100) {
            navbar.style.background = '#ece8e1';
        } else {
            navbar.style.background = '#ece8e1';
        }
    }
});

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Add click event listener to mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMobileMenu();
            });
            
            // Also add touch event for mobile
            mobileMenuToggle.addEventListener('touchstart', function(e) {
                e.preventDefault();
                toggleMobileMenu();
            });
        }
        
        // Add click handlers to mobile nav links
        const mobileNavLinks = document.querySelectorAll('.mobile-nav-links a');
        mobileNavLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeMobileMenu();
            });
        });
        
        // Smooth scrolling for navigation links
        const navLinks = document.querySelectorAll('a[href^="#"]');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetSection = document.querySelector(targetId);
                
                if (targetSection) {
                    targetSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
    } catch (error) {
        console.error('Error during header initialization:', error);
    }
});
</script>