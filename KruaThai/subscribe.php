<?php
/**
 * Somdul Table - Subscribe Page
 * File: subscribe.php
 * Description: Choose meal package before selecting menu (Step 1)
 * FIXED: Early login check to prevent errors when not logged in
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// EARLY LOGIN CHECK - Move this BEFORE any other includes/requires
// Check if user is logged in FIRST, before anything else that might cause errors
if (!isset($_SESSION['user_id'])) {
    $redirect_url = 'subscribe.php';
    if (isset($_GET['menu'])) $redirect_url .= '?menu=' . urlencode($_GET['menu']);
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

// Only proceed with includes and database operations if user is logged in
require_once 'config/database.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

$highlight_menu_id = $_GET['menu'] ?? '';

// Fetch packages from database (or mock data if no DB)
try {
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Updated fallback plans matching new pricing structure
    $plans = [
        [
            'id' => 'essentials-plan-2025', 
            'name' => 'Essentials',
            'name_thai' => 'Essentials',
            'name_english' => 'Essentials', 
            'meals_per_week' => 4, 
            'final_price' => 59.99, 
            'description' => 'Perfect for individuals who want to try authentic Thai cuisine',
            'is_popular' => 0,
            'sort_order' => 1
        ],
        [
            'id' => 'smart-choice-plan-2025', 
            'name' => 'Smart Choice',
            'name_thai' => 'Smart Choice',
            'name_english' => 'Smart Choice', 
            'meals_per_week' => 8, 
            'final_price' => 87.99, 
            'description' => 'Balanced value with the best perceived deal',
            'is_popular' => 1,
            'sort_order' => 2
        ],
        [
            'id' => 'founding-feast-plan-2025', 
            'name' => 'Founding Feast',
            'name_thai' => 'Founding Feast',
            'name_english' => 'Founding Feast', 
            'meals_per_week' => 12, 
            'final_price' => 119.99, 
            'description' => 'Bulk value perfect for families or serious food enthusiasts',
            'is_popular' => 0,
            'sort_order' => 3
        ]
    ];
}

// Helper function to get plan name with fallback
function getPlanName($plan) {
    if (isset($plan['name']) && !empty($plan['name'])) {
        return $plan['name'];
    } elseif (isset($plan['name_english']) && !empty($plan['name_english'])) {
        return $plan['name_english'];
    } elseif (isset($plan['name_thai']) && !empty($plan['name_thai'])) {
        return $plan['name_thai'];
    } else {
        return 'Package ' . ($plan['meals_per_week'] ?? 'Unknown');
    }
}

// Helper function to get plan description with fallback
function getPlanDescription($plan) {
    if (isset($plan['description']) && !empty($plan['description'])) {
        return $plan['description'];
    } else {
        return $plan['meals_per_week'] . ' meals per week';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Choose Your Meal Package | Somdul Table</title>
    <meta name="description" content="Choose your perfect meal package from Somdul Table - Authentic Thai cuisine delivered fresh to your door">
    
    <style>
    /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
    
    /* Main Container */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }

    /* Progress Bar */
    .progress-container {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 3rem;
        box-shadow: var(--shadow-soft);
    }

    .progress-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .progress-step {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.8rem 1.5rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.95rem;
        font-family: 'BaticaSans', sans-serif;
        background: var(--cream);
        color: var(--text-gray);
        border: 2px solid var(--cream);
        transition: var(--transition);
        white-space: nowrap;
    }

    .progress-step.active {
        background: var(--brown);
        color: var(--white);
        border-color: var(--brown);
        box-shadow: 0 4px 12px rgba(189, 147, 121, 0.3);
    }

    .progress-step.completed {
        background: var(--sage);
        color: var(--white);
        border-color: var(--sage);
    }

    .progress-arrow {
        color: var(--sage);
        font-size: 1.2rem;
        font-weight: 600;
    }

    /* Hero Section */
    .hero-section {
        background: var(--white);
        border-radius: 24px;
        padding: 3rem 2rem;
        text-align: center;
        margin-bottom: 3rem;
        box-shadow: var(--shadow-medium);
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--brown), var(--sage), var(--curry));
    }

    .hero-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .hero-subtitle {
        font-size: 1.2rem;
        color: var(--text-gray);
        max-width: 600px;
        margin: 0 auto 2rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .hero-features {
        display: flex;
        justify-content: center;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .hero-feature {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.8rem 1.5rem;
        background: var(--cream);
        border-radius: 16px;
        color: var(--brown);
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    .hero-feature i {
        font-size: 1.1rem;
    }

    /* Plans Grid */
    .plans-section {
        margin-bottom: 3rem;
    }

    .section-title {
        font-size: 2rem;
        font-weight: 700;
        text-align: center;
        margin-bottom: 1rem;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
    }

    .section-subtitle {
        font-size: 1.1rem;
        color: var(--text-gray);
        text-align: center;
        margin-bottom: 2.5rem;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        font-family: 'BaticaSans', sans-serif;
    }

    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
    }

    .plan-card {
        background: var(--white);
        border-radius: 24px;
        box-shadow: var(--shadow-soft);
        padding: 2.5rem 2rem;
        text-align: center;
        transition: var(--transition);
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .plan-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--sage);
        transform: scaleX(0);
        transition: var(--transition);
    }

    .plan-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
        border-color: var(--brown);
    }

    .plan-card:hover::before {
        transform: scaleX(1);
        background: var(--brown);
    }

    .plan-card.selected {
        border-color: var(--brown);
        box-shadow: 0 8px 32px rgba(189, 147, 121, 0.2);
    }

    .plan-card.selected::before {
        transform: scaleX(1);
        background: var(--brown);
    }

    .plan-card.selected::after {
        content: '✓';
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        background: var(--sage);
        color: var(--white);
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .plan-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .plan-info {
        color: var(--text-gray);
        font-size: 1rem;
        margin-bottom: 1rem;
        font-weight: 500;
        font-family: 'BaticaSans', sans-serif;
    }

    .plan-price {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--brown);
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .plan-price .currency {
        font-size: 1.5rem;
        color: var(--curry);
    }

    .plan-period {
        color: var(--text-gray);
        font-size: 0.9rem;
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .plan-desc {
        font-size: 1rem;
        color: var(--text-gray);
        margin-bottom: 2rem;
        line-height: 1.5;
        font-family: 'BaticaSans', sans-serif;
    }

    .plan-features {
        list-style: none;
        margin-bottom: 2rem;
        text-align: left;
    }

    .plan-features li {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.5rem 0;
        color: var(--text-dark);
        font-size: 0.95rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .plan-features i {
        color: var(--sage);
        font-size: 1rem;
        width: 1rem;
    }

    .plan-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem 2rem;
        border-radius: 50px;
        background: var(--brown);
        color: var(--white);
        font-weight: 700;
        font-size: 1.1rem;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: var(--shadow-soft);
        font-family: 'BaticaSans', sans-serif;
    }

    .plan-button:hover {
        background: #a8855f;
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .plan-button:active {
        transform: translateY(0);
    }

    /* Price per meal display */
    .price-per-meal {
        font-size: 0.9rem;
        color: var(--text-gray);
        margin-top: 0.5rem;
        font-weight: 500;
    }

    .price-per-meal .highlight {
        color: var(--curry);
        font-weight: 600;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 1rem;
        }

        .hero-title {
            font-size: 2rem;
        }

        .hero-subtitle {
            font-size: 1rem;
        }

        .hero-features {
            gap: 1rem;
        }

        .hero-feature {
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
        }

        .plans-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .plan-card {
            padding: 2rem 1.5rem;
        }

        .progress-bar {
            gap: 0.5rem;
        }

        .progress-step {
            font-size: 0.8rem;
            padding: 0.6rem 1rem;
        }

        .progress-arrow {
            font-size: 1rem;
        }
    }

    @media (max-width: 480px) {
        .progress-step {
            font-size: 0.7rem;
            padding: 0.5rem 0.8rem;
        }

        .hero-section {
            padding: 2rem 1.5rem;
        }

        .hero-features {
            flex-direction: column;
            align-items: center;
        }
    }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->

    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-step active">
                    <i class="fas fa-check-circle"></i>
                    <span>Choose Package</span>
                </div>
                <span class="progress-arrow">â†'</span>
                <div class="progress-step">
                    <i class="fas fa-utensils"></i>
                    <span>Select Menu</span>
                </div>
                <span class="progress-arrow">â†'</span>
                <div class="progress-step">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment</span>
                </div>
                <span class="progress-arrow">â†'</span>
                <div class="progress-step">
                    <i class="fas fa-check-double"></i>
                    <span>Complete</span>
                </div>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="hero-section">
            <h1 class="hero-title">Choose Your Meal Package</h1>
            <p class="hero-subtitle">
                Select the perfect package for your lifestyle, then choose your meals from our authentic Thai menu.
                Healthy Thai cuisine delivered fresh to your door every day.
            </p>
            <div class="hero-features">
                <div class="hero-feature">
                    <i class="fas fa-leaf"></i>
                    <span>Fresh Ingredients</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-truck"></i>
                    <span>Free Daily Delivery</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-heart"></i>
                    <span>Health Focused</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-star"></i>
                    <span>Authentic Flavors</span>
                </div>
            </div>
        </div>

        <!-- Plans Section -->
        <div class="plans-section">
            <h2 class="section-title">Our Meal Packages</h2>
            <p class="section-subtitle">
                Flexible packages to suit every lifestyle, with the freedom to customize your meals according to your preferences.
            </p>
            
            <div class="plans-grid">
                <?php foreach($plans as $index => $plan): 
                    // If highlight menu id is sent, pass query to meal-selection page
                    $query = "plan=" . urlencode($plan['id']);
                    if ($highlight_menu_id) $query .= "&menu=" . urlencode($highlight_menu_id);
                    
                    $isPopular = (isset($plan['is_popular']) && $plan['is_popular']) || $plan['meals_per_week'] == 8;
                    $pricePerMeal = $plan['final_price'] / $plan['meals_per_week'];
                ?>
                <div class="plan-card<?php echo $isPopular ? ' selected' : ''; ?>">
                    <div class="plan-name"><?php echo htmlspecialchars(getPlanName($plan)); ?></div>
                    <div class="plan-info"><?php echo $plan['meals_per_week']; ?> meals</div>
                    <div class="plan-price">
                        <span class="currency">$</span><?php echo number_format($pricePerMeal, 2); ?>
                    </div>
                    <div class="plan-period">per meal</div>
                    <div class="price-per-meal">
                        <span class="highlight">$<?php echo number_format($plan['final_price'], 2); ?> in total</span>
                    </div>
                    <div class="plan-desc"><?php echo htmlspecialchars(getPlanDescription($plan)); ?></div>
                    
                    <ul class="plan-features">
                        <li>
                            <i class="fas fa-utensils"></i>
                            <span><?php echo $plan['meals_per_week']; ?> healthy Thai meals</span>
                        </li>
                        <li>
                            <i class="fas fa-truck"></i>
                            <span>Free home delivery</span>
                        </li>
                        <li>
                            <i class="fas fa-calendar-alt"></i>
                            <span>Flexible scheduling</span>
                        </li>
                        <li>
                            <i class="fas fa-leaf"></i>
                            <span>Premium quality ingredients</span>
                        </li>
                        <?php if ($isPopular): ?>
                        <li>
                            <i class="fas fa-crown"></i>
                            <span>Most popular package</span>
                        </li>
                        <?php endif; ?>
                        <?php if ($plan['meals_per_week'] >= 12): ?>
                        <li>
                            <i class="fas fa-gift"></i>
                            <span>Free Thai snack or drink gift</span>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <a href="meal-selection.php?<?php echo $query; ?>" class="plan-button">
                        Choose This Package
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
    
    <script>
    // Page-specific JavaScript for subscribe.php
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🥘 Subscribe page loaded');
        
        // Add loading animation to plan cards
        const planCards = document.querySelectorAll('.plan-card');
        planCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Add click tracking for analytics
        planCards.forEach(card => {
            card.addEventListener('click', function(e) {
                if (!e.target.matches('.plan-button, .plan-button *')) {
                    const link = this.querySelector('.plan-button');
                    if (link) {
                        link.click();
                    }
                }
            });
        });
        
        // The mobile menu and promo banner functions are already available from header.php
        // You can use: toggleMobileMenu(), closeMobileMenu(), closePromoBanner()
    });
    </script>
</body>
</html>