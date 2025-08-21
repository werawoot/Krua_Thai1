<?php
/**
 * Somdul Table - Products Page
 * File: products.php
 * Description: Browse and purchase individual products (meal kits, sauces, etc.)
 * CLEAN VERSION: Uses header.php consistently like menus.php
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

// Include cart functions if available
if (file_exists('includes/cart_functions.php')) {
    require_once 'includes/cart_functions.php';
}

// Database connection with fallback
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    // Fallback connection for MAMP/XAMPP
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        // Second fallback
        $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Get filter from URL
$category_filter = $_GET['category'] ?? 'all';

// Calculate cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += intval($item['quantity'] ?? 1);
    }
}

// Fetch products from database
try {
    if ($category_filter === 'all') {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE is_active = 1 ORDER BY category, name");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE is_active = 1 AND category = ? ORDER BY name");
        $stmt->execute([$category_filter]);
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback products if database table doesn't exist yet
    $products = [
        [
            'id' => 'pad-thai-kit-pro',
            'name' => 'Premium Pad Thai Kit',
            'name_thai' => 'ชุดผัดไทยพรีเมียม',
            'description' => 'Complete authentic Pad Thai kit with premium ingredients, rice noodles, tamarind sauce, and traditional garnishes. Serves 2-3 people.',
            'price' => 24.99,
            'image_url' => './assets/image/pad-thai-kit-pro.jpg',
            'category' => 'meal-kit',
            'stock_quantity' => 50,
            'can_ship_nationwide' => 1
        ],
        [
            'id' => 'tom-yum-paste-authentic',
            'name' => 'Authentic Tom Yum Paste',
            'name_thai' => 'น้ำพริกต้มยำแท้',
            'description' => 'Traditional Tom Yum paste made with fresh lemongrass, galangal, and kaffir lime leaves. Perfect for making restaurant-quality Tom Yum soup at home.',
            'price' => 12.99,
            'image_url' => './assets/image/tom-yum-paste.jpg',
            'category' => 'sauce',
            'stock_quantity' => 100,
            'can_ship_nationwide' => 1
        ],
        [
            'id' => 'thai-curry-kit-trio',
            'name' => 'Thai Curry Kit Trio',
            'name_thai' => 'ชุดแกง 3 รส',
            'description' => 'Three authentic curry pastes: Red, Green, and Yellow. Includes coconut milk powder and cooking instructions.',
            'price' => 34.99,
            'image_url' => './assets/image/curry-kit-trio.jpg',
            'category' => 'meal-kit',
            'stock_quantity' => 30,
            'can_ship_nationwide' => 1
        ],
        [
            'id' => 'fish-sauce-premium',
            'name' => 'Premium Fish Sauce',
            'name_thai' => 'น้ำปลาเกรดพรีเมียม',
            'description' => 'Artisanal fish sauce aged for 2 years. The secret ingredient that makes Thai food authentic.',
            'price' => 18.99,
            'image_url' => './assets/image/fish-sauce-premium.jpg',
            'category' => 'sauce',
            'stock_quantity' => 75,
            'can_ship_nationwide' => 1
        ],
        [
            'id' => 'thai-chili-oil-spicy',
            'name' => 'Spicy Thai Chili Oil',
            'name_thai' => 'น้ำมันพริกไทย',
            'description' => 'Handcrafted chili oil with Thai bird\'s eye chilies, garlic, and aromatics. Perfect for noodles and rice dishes.',
            'price' => 15.99,
            'image_url' => './assets/image/chili-oil.jpg',
            'category' => 'sauce',
            'stock_quantity' => 60,
            'can_ship_nationwide' => 1
        ],
        [
            'id' => 'som-tam-kit-fresh',
            'name' => 'Fresh Som Tam Kit',
            'name_thai' => 'ชุดส้มตำสด',
            'description' => 'Everything needed for authentic papaya salad: dressing mix, dried shrimp, peanuts, and instructions. Add fresh papaya!',
            'price' => 19.99,
            'image_url' => './assets/image/som-tam-kit.jpg',
            'category' => 'meal-kit',
            'stock_quantity' => 40,
            'can_ship_nationwide' => 1
        ]
    ];
}

// Helper function to format category names
function formatCategoryName($category) {
    switch ($category) {
        case 'meal-kit': return 'Meal Kits';
        case 'sauce': return 'Sauces & Condiments';
        case 'ingredient': return 'Ingredients';
        case 'accessory': return 'Accessories';
        default: return ucfirst($category);
    }
}

// Helper function to format price
function formatPrice($price) {
    return '$' . number_format($price, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thai Products - Authentic Ingredients & Meal Kits | Somdul Table</title>
    <meta name="description" content="Shop authentic Thai meal kits, sauces, and ingredients. Delivered nationwide. Cook restaurant-quality Thai food at home.">
    
    <style>
        /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding-top: 2rem;
            min-height: calc(100vh - 200px);
        }

        /* Products Hero Section */
        .products-hero {
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%);
            color: var(--white);
            padding: 4rem 2rem;
            text-align: center;
        }
        
        .products-hero h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--white);
        }
        
        .products-hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .products-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }
        
        /* Floating Cart */
        .floating-cart {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            animation: slideInUp 0.3s ease-out, float 3s ease-in-out infinite;
        }
        
        .floating-cart.hidden {
            display: none;
        }
        
        .floating-cart-link {
            text-decoration: none;
            display: block;
        }
        
        .floating-cart-icon {
            position: relative;
            width: 60px;
            height: 60px;
            background: var(--brown);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
            border: 3px solid var(--white);
        }
        
        .floating-cart-icon:hover {
            background: var(--curry);
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 12px 30px rgba(189, 147, 121, 0.4);
            animation: none; /* Stop floating animation on hover */
        }
        
        .floating-cart-counter {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--curry);
            color: var(--white);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            border: 2px solid var(--white);
            min-width: 28px;
            animation: pulse 0.6s ease-out;
        }
        
        .floating-cart-counter.hidden {
            display: none;
        }
        
        /* Category Filters */
        .products-filters {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--brown);
            background: transparent;
            color: var(--brown);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: var(--brown);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .product-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
            border: 1px solid var(--cream);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brown);
            font-size: 3rem;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-content {
            padding: 1.5rem;
        }
        
        .product-category {
            font-size: 0.85rem;
            color: var(--curry);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .product-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .product-name-thai {
            font-size: 0.9rem;
            color: var(--sage);
            font-style: italic;
            margin-bottom: 1rem;
        }
        
        .product-description {
            color: var(--text-gray);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        
        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brown);
        }
        
        .product-stock {
            font-size: 0.85rem;
            color: var(--sage);
        }
        
        .product-actions {
            margin-top: 1rem;
        }
        
        .btn-add-cart {
            width: 100%;
            background: var(--brown);
            color: var(--white);
            border: none;
            padding: 1rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            font-size: 1rem;
        }
        
        .btn-add-cart:hover {
            background: #a8855f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .btn-add-cart:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-add-cart.loading {
            background: var(--sage);
            pointer-events: none;
        }
        
        /* Shipping Banner */
        .shipping-banner {
            background: var(--sage);
            color: var(--white);
            text-align: center;
            padding: 1rem;
            margin: 2rem 0;
            border-radius: var(--radius-md);
            font-weight: 600;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }
        
        .empty-state h3 {
            color: var(--brown);
            margin-bottom: 1rem;
        }
        
        /* Success notification */
        .toast-notification {
            position: fixed;
            top: 140px;
            right: 2rem;
            background: var(--sage);
            color: var(--white);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease-out;
            max-width: 350px;
        }
        
        .toast-notification.show {
            transform: translateX(0);
        }
        
        .toast-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .toast-icon {
            font-size: 1.2rem;
        }
        
        .toast-text {
            font-weight: 600;
        }
        
        /* Animations */
        @keyframes slideInUp {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px) scale(1.1);
            }
            60% {
                transform: translateY(-10px) scale(1.05);
            }
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .products-hero h1 {
                font-size: 2rem;
            }
            
            .products-container {
                padding: 2rem 1rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .floating-cart {
                bottom: 20px;
                right: 20px;
            }
            
            .floating-cart-icon {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
            
            .floating-cart-counter {
                width: 24px;
                height: 24px;
                font-size: 0.7rem;
                top: -6px;
                right: -6px;
            }
            
            .toast-notification {
                right: 1rem;
                left: 1rem;
                max-width: none;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->

    <!-- Floating Cart Icon -->
    <div class="floating-cart" id="floatingCart">
        <a href="cart.php" class="floating-cart-link">
            <div class="floating-cart-icon">
                🛒
                <span class="floating-cart-counter <?= $cart_count == 0 ? 'hidden' : '' ?>" id="floatingCartCounter">
                    <?= $cart_count ?>
                </span>
            </div>
        </a>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Products Hero Section -->
        <section class="products-hero">
            <h1>Thai Products & Meal Kits</h1>
            <p>Authentic Thai ingredients and meal kits delivered nationwide. Bring the flavors of Thailand to your kitchen.</p>
        </section>

        <!-- Main Products Container -->
        <div class="products-container">
            <!-- Shipping Banner -->
            <div class="shipping-banner">
                🚚 Free shipping on orders over $50 • Delivered nationwide • 5-7 business days
            </div>

            <!-- Category Filters -->
            <div class="products-filters">
                <a href="products.php?category=all" class="filter-btn <?= $category_filter === 'all' ? 'active' : '' ?>">
                    All Products
                </a>
                <a href="products.php?category=meal-kit" class="filter-btn <?= $category_filter === 'meal-kit' ? 'active' : '' ?>">
                    Meal Kits
                </a>
                <a href="products.php?category=sauce" class="filter-btn <?= $category_filter === 'sauce' ? 'active' : '' ?>">
                    Sauces & Condiments
                </a>
                <a href="products.php?category=ingredient" class="filter-btn <?= $category_filter === 'ingredient' ? 'active' : '' ?>">
                    Ingredients
                </a>
            </div>

            <!-- Products Grid -->
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <h3>No products found</h3>
                    <p>We're currently updating our product catalog. Please check back soon!</p>
                    <a href="products.php?category=all" class="btn btn-primary" style="margin-top: 1rem;">View All Products</a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" data-product-id="<?= htmlspecialchars($product['id']) ?>">
                            <div class="product-image">
                                <?php if (!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <?php else: ?>
                                    🍜
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-content">
                                <div class="product-category">
                                    <?= formatCategoryName($product['category']) ?>
                                </div>
                                
                                <h3 class="product-name">
                                    <?= htmlspecialchars($product['name']) ?>
                                </h3>
                                
                                <?php if (!empty($product['name_thai'])): ?>
                                    <div class="product-name-thai">
                                        <?= htmlspecialchars($product['name_thai']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="product-description">
                                    <?= htmlspecialchars($product['description']) ?>
                                </p>
                                
                                <div class="product-footer">
                                    <div>
                                        <div class="product-price">
                                            <?= formatPrice($product['price']) ?>
                                        </div>
                                        <?php if (isset($product['stock_quantity']) && $product['stock_quantity'] > 0): ?>
                                            <div class="product-stock">
                                                <?= $product['stock_quantity'] ?> in stock
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="product-actions">
                                    <button class="btn-add-cart" onclick="addToCart('<?= htmlspecialchars($product['id']) ?>')" 
                                            data-product-id="<?= htmlspecialchars($product['id']) ?>"
                                            data-product-name="<?= htmlspecialchars($product['name']) ?>">
                                        🛒 Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Call to Action -->
            <div style="text-align: center; margin-top: 3rem; padding: 2rem; background: var(--cream); border-radius: var(--radius-lg);">
                <h3 style="color: var(--brown); margin-bottom: 1rem;">Can't find what you're looking for?</h3>
                <p style="color: var(--text-gray); margin-bottom: 1.5rem;">Contact us for custom orders or special requests.</p>
                <a href="contact.php" class="btn btn-primary">Contact Us</a>
            </div>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotification">
        <div class="toast-content">
            <span class="toast-icon">✅</span>
            <span class="toast-text" id="toastText">Product added to cart!</span>
        </div>
    </div>

    <script>
        // Cart management and AJAX functionality
        let cartCount = <?= $cart_count ?>;
        
        // Add to cart function
        async function addToCart(productId) {
            const button = document.querySelector(`[data-product-id="${productId}"]`);
            const productName = button.getAttribute('data-product-name');
            const originalText = button.innerHTML;
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            button.classList.add('loading');
            button.disabled = true;
            
            try {
                const response = await fetch('ajax/add_product_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 1,
                        type: 'product'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update cart count
                    cartCount = data.cart_count;
                    updateCartDisplay();
                    
                    // Add bounce effect to floating cart
                    const floatingCart = document.getElementById('floatingCart');
                    if (floatingCart) {
                        floatingCart.style.animation = 'bounce 0.6s ease-out';
                        setTimeout(() => {
                            floatingCart.style.animation = 'slideInUp 0.3s ease-out, float 3s ease-in-out infinite';
                        }, 600);
                    }
                    
                    // Reset button immediately (no "Added!" text)
                    button.innerHTML = originalText;
                    button.classList.remove('loading');
                    button.disabled = false;
                    
                    // Show toast notification
                    showToast(`${productName} added to cart!`);
                    
                } else {
                    throw new Error(data.message || 'Failed to add to cart');
                }
                
            } catch (error) {
                console.error('Error adding to cart:', error);
                
                // Show error state
                button.innerHTML = '❌ Error';
                button.classList.remove('loading');
                
                // Show error toast
                showToast(error.message || 'Error adding to cart', 'error');
                
                // Reset button
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            }
        }
        
        // Update cart display
        function updateCartDisplay() {
            const floatingCartCounter = document.getElementById('floatingCartCounter');
            const floatingCart = document.getElementById('floatingCart');
            
            if (floatingCartCounter) {
                floatingCartCounter.textContent = cartCount;
            }
            
            // Show/hide floating cart
            if (cartCount > 0) {
                floatingCart.classList.remove('hidden');
                if (floatingCartCounter) {
                    floatingCartCounter.classList.remove('hidden');
                    // Add pulse animation
                    floatingCartCounter.style.animation = 'pulse 0.6s ease-out';
                    setTimeout(() => {
                        floatingCartCounter.style.animation = '';
                    }, 600);
                }
            } else {
                if (floatingCartCounter) {
                    floatingCartCounter.classList.add('hidden');
                }
                // Keep floating cart visible but with 0 counter
                floatingCart.classList.remove('hidden');
            }
            
            // Update navbar cart counter if exists
            const navCartCounter = document.querySelector('.cart-counter');
            if (navCartCounter) {
                navCartCounter.textContent = cartCount;
                navCartCounter.style.display = cartCount > 0 ? 'inline-block' : 'none';
            }
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toastNotification');
            const toastText = document.getElementById('toastText');
            const toastIcon = toast.querySelector('.toast-icon');
            
            toastText.textContent = message;
            
            if (type === 'error') {
                toastIcon.textContent = '❌';
                toast.style.background = 'var(--error-color, #e74c3c)';
            } else {
                toastIcon.textContent = '✅';
                toast.style.background = 'var(--sage)';
            }
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Products page loaded');
            updateCartDisplay();
            
            // Add smooth hover effects
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Close toast notification when clicked
            const toast = document.getElementById('toastNotification');
            toast.addEventListener('click', function() {
                this.classList.remove('show');
            });
        });
        
        // Handle escape key to close toast
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const toast = document.getElementById('toastNotification');
                toast.classList.remove('show');
            }
        });
    </script>
</body>
</html>