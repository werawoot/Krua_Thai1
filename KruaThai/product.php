<?php
/**
 * Somdul Table - Products Page
 * File: products.php
 * Description: Browse and purchase individual products (meal kits, sauces, etc.)
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

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
            'name_thai' => 'ชุดแกงไทย 3 รส',
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

// Include the header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thai Products - Authentic Ingredients & Meal Kits | Somdul Table</title>
    <meta name="description" content="Shop authentic Thai meal kits, sauces, and ingredients. Delivered nationwide. Cook restaurant-quality Thai food at home.">
    
    <style>
        /* Product Page Specific Styles */
        body.has-header {
            margin-top: 110px;
        }
        
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
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-add-cart {
            flex: 1;
            background: var(--brown);
            color: var(--white);
            border: none;
            padding: 0.8rem 1rem;
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
        }
        
        .btn-add-cart:hover {
            background: #a8855f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .btn-quick-buy {
            background: var(--curry);
            color: var(--white);
            border: none;
            padding: 0.8rem 1rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
        }
        
        .btn-quick-buy:hover {
            background: #b8621f;
            transform: translateY(-2px);
        }
        
        .shipping-banner {
            background: var(--sage);
            color: var(--white);
            text-align: center;
            padding: 1rem;
            margin: 2rem 0;
            border-radius: var(--radius-md);
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }
        
        .empty-state h3 {
            color: var(--brown);
            margin-bottom: 1rem;
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
            
            .product-actions {
                flex-direction: column;
            }
            
            .btn-quick-buy {
                min-width: auto;
            }
        }
    </style>
</head>

<body class="has-header">
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
                    <div class="product-card">
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
                                <?php if ($is_logged_in): ?>
                                    <a href="product-checkout.php?product=<?= urlencode($product['id']) ?>&action=add_to_cart" 
                                       class="btn-add-cart">
                                        🛒 Add to Cart
                                    </a>
                                    <a href="product-checkout.php?product=<?= urlencode($product['id']) ?>&action=buy_now" 
                                       class="btn-quick-buy">
                                        Buy Now
                                    </a>
                                <?php else: ?>
                                    <a href="login.php?redirect=<?= urlencode('product-checkout.php?product=' . $product['id']) ?>" 
                                       class="btn-add-cart">
                                        🛒 Add to Cart
                                    </a>
                                    <a href="guest-product-checkout.php?product=<?= urlencode($product['id']) ?>" 
                                       class="btn-quick-buy">
                                        Buy as Guest
                                    </a>
                                <?php endif; ?>
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

    <script>
        // Simple product page interactions
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Track button clicks for analytics (if needed)
            const buttons = document.querySelectorAll('.btn-add-cart, .btn-quick-buy');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Optional: Add analytics tracking here
                    console.log('Product interaction:', this.href);
                });
            });
        });
    </script>
</body>
</html>