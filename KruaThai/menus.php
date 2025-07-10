<?php
/**
 * Krua Thai - Public Menus Page
 * File: menus.php
 * Description: Browse, filter, and search all available menus
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

try {
    // Fetch categories
    $categories = [];
    $stmt = $pdo->prepare("
        SELECT id, name, name_thai 
        FROM menu_categories 
        WHERE is_active = 1 
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter/Search logic
    $category_id = $_GET['category_id'] ?? '';
    $search = $_GET['search'] ?? '';
    $diet = $_GET['diet'] ?? '';
    $spice = $_GET['spice'] ?? '';
    $max_price = $_GET['max_price'] ?? '';

    // Build WHERE clause
    $where_conditions = ["m.is_available = 1"];
    $params = [];

    if ($category_id) {
        $where_conditions[] = "m.category_id = ?";
        $params[] = $category_id;
    }

    if ($search) {
        $where_conditions[] = "(m.name LIKE ? OR m.name_thai LIKE ? OR m.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if ($diet) {
        $where_conditions[] = "JSON_CONTAINS(m.dietary_tags, ?)";
        $params[] = '"' . $diet . '"';
    }

    if ($spice) {
        $where_conditions[] = "m.spice_level = ?";
        $params[] = $spice;
    }

    if ($max_price) {
        $where_conditions[] = "m.base_price <= ?";
        $params[] = $max_price;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // Get menus
    $sql = "
        SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories c ON m.category_id = c.id 
        $where_clause 
        ORDER BY m.is_featured DESC, m.updated_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all available dietary tags for filter
    $stmt = $pdo->prepare("
        SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(dietary_tags, CONCAT('$[', idx, ']'))) as tag
        FROM menus m
        JOIN (
            SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
        ) as indexes
        WHERE JSON_EXTRACT(dietary_tags, CONCAT('$[', idx, ']')) IS NOT NULL
        AND m.is_available = 1
        ORDER BY tag
    ");
    $stmt->execute();
    $available_diets = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    error_log("Menus page error: " . $e->getMessage());
    $menus = [];
    $categories = [];
    $available_diets = [];
}

// Get price range for filter
$max_menu_price = 500; // Default fallback
try {
    $stmt = $pdo->prepare("SELECT MAX(base_price) FROM menus WHERE is_available = 1");
    $stmt->execute();
    $max_menu_price = $stmt->fetchColumn() ?: 500;
} catch (Exception $e) {
    // Use fallback
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Krua Thai | Authentic Thai Food for Health</title>
    <meta name="description" content="Browse our healthy Thai food menu from Krua Thai with complete nutritional information and pricing">
    
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --cream: #ece8e1;
            --sage: #adb89d;
            --brown: #bd9379;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-medium: 0 8px 24px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--white);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            background: var(--white);
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
        }

        .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 50%;
            color: var(--white);
            font-size: 1.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--curry);
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
            min-height: calc(100vh - 200px);
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Filters */
        .filters-section {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 3rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
        }

        .filters-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .filter-input,
        .filter-select {
            padding: 0.8rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
            background: var(--white);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 10px rgba(207, 114, 58, 0.2);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        /* Results Header */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem 0;
            border-bottom: 2px solid var(--border-light);
        }

        .results-count {
            font-size: 1.1rem;
            color: var(--text-gray);
        }

        .sort-select {
            padding: 0.6rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: 25px;
            background: var(--white);
            font-family: inherit;
        }

        /* Menu Grid */
        .menus-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .menu-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            position: relative;
        }

        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .menu-image {
            position: relative;
            height: 200px;
            background: linear-gradient(135deg, var(--cream), #e8dcc0);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-gray);
            font-size: 1rem;
            font-weight: 600;
        }

        .menu-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .menu-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.95);
            color: var(--curry);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .featured-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--curry);
            color: var(--white);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .menu-content {
            padding: 1.5rem;
        }

        .menu-category {
            font-size: 0.8rem;
            color: var(--brown);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .menu-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.8rem;
            line-height: 1.3;
        }

        .menu-title-en {
            font-size: 0.9rem;
            color: var(--text-gray);
            font-weight: 500;
            margin-bottom: 0.8rem;
        }

        .menu-description {
            color: var(--text-gray);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.2rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .menu-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1.2rem;
        }

        .tag {
            background: var(--cream);
            color: var(--brown);
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .spice-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .spice-mild { background: #e8f5e8; color: #2e7d32; }
        .spice-medium { background: #fff8e1; color: #f57f17; }
        .spice-hot { background: #ffebee; color: #d32f2f; }
        .spice-extra_hot { background: #ffebee; color: #b71c1c; }

        .menu-nutrition {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
        }

        .nutrition-item {
            text-align: center;
            flex: 1;
            min-width: 60px;
        }

        .nutrition-value {
            font-weight: 700;
            color: var(--curry);
            font-size: 0.9rem;
        }

        .nutrition-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 0.2rem;
        }

        .menu-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .menu-price {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--curry);
        }

        .menu-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            margin: 2rem;
            box-shadow: var(--shadow-medium);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-gray);
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .filter-actions {
                flex-direction: column;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .menus-grid {
                grid-template-columns: 1fr;
            }

            .menu-nutrition {
                gap: 0.5rem;
            }

            .nutrition-item {
                min-width: 50px;
            }

            .modal-content {
                margin: 1rem;
            }
        }

        /* Loading animation */
        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--text-gray);
        }

        .loading i {
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <nav class="container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <span>Krua Thai</span>
            </a>
            
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="menus.php">All Menus</a>
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php">My Account</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Sign Up</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Healthy Thai Food Menu</h1>
                <p class="page-subtitle">
                    Browse our authentic Thai dishes adapted for health-conscious eating 
                    with complete nutritional information
                </p>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <h2 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Search & Filter Menu
                </h2>
                
                <form method="GET" action="" id="filterForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Search Menu</label>
                            <input type="text" 
                                   name="search" 
                                   class="filter-input" 
                                   placeholder="Dish name, description..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select name="category_id" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name'] ?: $cat['name_thai']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Diet Type</label>
                            <select name="diet" class="filter-select">
                                <option value="">All Types</option>
                                <?php foreach ($available_diets as $diet_option): ?>
                                    <option value="<?php echo htmlspecialchars($diet_option); ?>" 
                                            <?php echo ($diet == $diet_option) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($diet_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Spice Level</label>
                            <select name="spice" class="filter-select">
                                <option value="">All Levels</option>
                                <option value="mild" <?php echo ($spice == 'mild') ? 'selected' : ''; ?>>Mild</option>
                                <option value="medium" <?php echo ($spice == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="hot" <?php echo ($spice == 'hot') ? 'selected' : ''; ?>>Hot</option>
                                <option value="extra_hot" <?php echo ($spice == 'extra_hot') ? 'selected' : ''; ?>>Extra Hot</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Max Price ($)</label>
                            <input type="number" 
                                   name="max_price" 
                                   class="filter-input" 
                                   placeholder="e.g. 15" 
                                   min="0" 
                                   max="<?php echo $max_menu_price; ?>"
                                   value="<?php echo htmlspecialchars($max_price); ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                        <a href="menus.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i>
                            Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Header -->
            <div class="results-header">
                <div class="results-count">
                    Found <strong><?php echo count($menus); ?></strong> dishes
                    <?php if ($search || $category_id || $diet || $spice || $max_price): ?>
                        from your search
                    <?php endif; ?>
                </div>
            </div>

            <!-- Menus Grid -->
            <div class="menus-grid">
                <?php if (empty($menus)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-utensils"></i>
                        <h3>No dishes found</h3>
                        <p>Try changing your search terms or filter criteria</p>
                        <a href="menus.php" class="btn btn-primary" style="margin-top: 1rem;">
                            View All Menu
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($menus as $menu): ?>
                        <?php
                        $dietary_tags = json_decode($menu['dietary_tags'] ?? '[]', true);
                        if (!is_array($dietary_tags)) $dietary_tags = [];
                        
                        $spice_labels = [
                            'mild' => 'Mild',
                            'medium' => 'Medium',
                            'hot' => 'Hot',
                            'extra_hot' => 'Extra Hot'
                        ];
                        
                        $spice_icons = [
                            'mild' => '🌶️',
                            'medium' => '🌶️🌶️',
                            'hot' => '🌶️🌶️🌶️',
                            'extra_hot' => '🌶️🌶️🌶️🌶️'
                        ];
                        ?>
                        <div class="menu-card">
                            <?php if ($menu['is_featured']): ?>
                                <div class="featured-badge">Featured</div>
                            <?php endif; ?>
                            
                            <div class="menu-image">
                                <?php if ($menu['main_image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($menu['main_image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($menu['name']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div style="text-align: center;">
                                        <i class="fas fa-utensils" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                        <br><?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($menu['category_name']): ?>
                                    <div class="menu-badge">
                                        <?php echo htmlspecialchars($menu['category_name'] ?: $menu['category_name_thai']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="menu-content">
                                <?php if ($menu['category_name']): ?>
                                    <div class="menu-category">
                                        <?php echo htmlspecialchars($menu['category_name'] ?: $menu['category_name_thai']); ?>
                                    </div>
                              <?php endif; ?>
                                
                                <h3 class="menu-title">
                                    <?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?>
                                </h3>
                                
                                <?php if ($menu['name_thai'] && $menu['name']): ?>
                                    <div class="menu-title-en">
                                        <?php echo htmlspecialchars($menu['name_thai']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="menu-description">
                                    <?php echo htmlspecialchars($menu['description'] ?: 'Healthy Thai cuisine'); ?>
                                </p>
                                
                                <!-- Tags -->
                                <div class="menu-tags">
                                    <!-- Dietary Tags -->
                                    <?php foreach (array_slice($dietary_tags, 0, 2) as $tag): ?>
                                        <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                    
                                    <!-- Spice Level -->
                                    <?php if ($menu['spice_level']): ?>
                                        <span class="spice-tag spice-<?php echo $menu['spice_level']; ?>">
                                            <?php echo $spice_icons[$menu['spice_level']] ?? '🌶️'; ?>
                                            <?php echo $spice_labels[$menu['spice_level']] ?? 'Medium'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Nutrition Info -->
                                <div class="menu-nutrition">
                                    <?php if ($menu['calories_per_serving']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?php echo number_format($menu['calories_per_serving']); ?></div>
                                            <div class="nutrition-label">Calories</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($menu['protein_g']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?php echo number_format($menu['protein_g'], 1); ?>g</div>
                                            <div class="nutrition-label">Protein</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($menu['carbs_g']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?php echo number_format($menu['carbs_g'], 1); ?>g</div>
                                            <div class="nutrition-label">Carbs</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($menu['fat_g']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?php echo number_format($menu['fat_g'], 1); ?>g</div>
                                            <div class="nutrition-label">Fat</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Footer -->
                                <div class="menu-footer">
                                    <div class="menu-price">
                                        $<?php echo number_format($menu['base_price'], 2); ?>
                                    </div>
                                    
                                    <div class="menu-actions">
                                        <button type="button" 
                                                class="btn btn-secondary btn-sm"
                                                onclick="showMenuModal('<?php echo $menu['id']; ?>')">
                                            <i class="fas fa-info-circle"></i>
                                            Details
                                        </button>
                                        
                                        <?php if ($is_logged_in): ?>
                                            <a href="meal-selection.php?single=<?php echo $menu['id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-cart-plus"></i>
                                                Order Now
                                            </a>
                                        <?php else: ?>
                                            <a href="register.php?menu=<?php echo $menu['id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-user-plus"></i>
                                                Sign Up
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Call-to-Action Section -->
            <?php if (!empty($menus)): ?>
                <div class="cta-section" style="text-align: center; padding: 3rem 0; background: var(--cream); border-radius: var(--radius-lg); margin-top: 3rem;">
                    <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1rem;">
                        Ready to start your healthy eating journey?
                    </h2>
                    <p style="font-size: 1.1rem; color: var(--text-gray); margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                        Choose the meal plan that's right for you and start taking care of your health with authentic Thai cuisine
                    </p>
                    <a href="index.php#plans" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2.5rem;">
                        <i class="fas fa-leaf"></i>
                        View All Plans
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Menu Detail Modal -->
    <div id="menuModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Menu Details</h3>
                <button class="modal-close" onclick="closeMenuModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: var(--text-dark); color: var(--white); padding: 2rem 0; text-align: center; margin-top: 4rem;">
        <div class="container">
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div class="logo-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <span style="font-size: 1.5rem; font-weight: 700;">Krua Thai</span>
            </div>
            <p style="color: var(--text-gray); margin-bottom: 0.5rem;">
                Healthy Thai food delivered to your door
            </p>
            <p style="color: var(--text-gray); font-size: 0.9rem;">
                © 2025 Krua Thai. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
        // Menu Modal Functions
        async function showMenuModal(menuId) {
            const modal = document.getElementById('menuModal');
            const modalBody = document.getElementById('modalBody');
            
            // Show modal with loading state
            modal.classList.add('show');
            modalBody.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading details...</p>
                </div>
            `;
            
            try {
                // Fetch menu details
                const response = await fetch(`ajax/get_menu_details.php?id=${menuId}`);
                const data = await response.json();
                
                if (data.success) {
                    modalBody.innerHTML = buildMenuModalContent(data.menu);
                } else {
                    modalBody.innerHTML = `
                        <div class="text-center" style="padding: 2rem; color: var(--text-gray);">
                            <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <h4>Unable to load data</h4>
                            <p>Please try again</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading menu details:', error);
                modalBody.innerHTML = `
                    <div class="text-center" style="padding: 2rem; color: var(--text-gray);">
                        <i class="fas fa-wifi" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <h4>Connection Error</h4>
                        <p>Check your internet connection</p>
                    </div>
                `;
            }
        }

        function buildMenuModalContent(menu) {
            const dietaryTags = menu.dietary_tags ? JSON.parse(menu.dietary_tags) : [];
            const healthBenefits = menu.health_benefits ? JSON.parse(menu.health_benefits) : [];
            
            const spiceLabels = {
                'mild': 'Mild',
                'medium': 'Medium', 
                'hot': 'Hot',
                'extra_hot': 'Extra Hot'
            };
            
            return `
                <div style="text-align: center; margin-bottom: 2rem;">
                    ${menu.main_image_url ? 
                        `<img src="${menu.main_image_url}" alt="${menu.name}" style="width: 100%; max-width: 400px; height: 200px; object-fit: cover; border-radius: 12px; margin-bottom: 1rem;">` :
                        `<div style="width: 100%; height: 200px; background: var(--cream); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; color: var(--text-gray);">
                            <i class="fas fa-utensils" style="font-size: 3rem;"></i>
                        </div>`
                    }
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem;">
                        ${menu.name || menu.name_thai}
                    </h2>
                    ${menu.name && menu.name_thai ? `<p style="color: var(--text-gray); margin-bottom: 1rem;">${menu.name_thai}</p>` : ''}
                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--curry);">
                        $${parseFloat(menu.base_price).toFixed(2)}
                    </div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--text-dark);">Description</h4>
                    <p style="color: var(--text-gray); line-height: 1.6;">
                        ${menu.description || 'Healthy Thai cuisine'}
                    </p>
                </div>

                ${menu.ingredients ? `
                    <div style="margin-bottom: 2rem;">
                        <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--text-dark);">Ingredients</h4>
                        <p style="color: var(--text-gray); line-height: 1.6;">
                            ${menu.ingredients}
                        </p>
                    </div>
                ` : ''}

                <div style="margin-bottom: 2rem;">
                    <h4 style="font-weight: 600; margin-bottom: 1rem; color: var(--text-dark);">Nutritional Information</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 1rem; background: var(--cream); padding: 1.5rem; border-radius: 12px;">
                        ${menu.calories_per_serving ? `
                            <div style="text-align: center;">
                                <div style="font-weight: 700; font-size: 1.2rem; color: var(--curry);">${menu.calories_per_serving}</div>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">Calories</div>
                            </div>
                        ` : ''}
                        ${menu.protein_g ? `
                            <div style="text-align: center;">
                                <div style="font-weight: 700; font-size: 1.2rem; color: var(--curry);">${parseFloat(menu.protein_g).toFixed(1)}g</div>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">Protein</div>
                            </div>
                        ` : ''}
                        ${menu.carbs_g ? `
                            <div style="text-align: center;">
                                <div style="font-weight: 700; font-size: 1.2rem; color: var(--curry);">${parseFloat(menu.carbs_g).toFixed(1)}g</div>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">Carbohydrates</div>
                            </div>
                        ` : ''}
                        ${menu.fat_g ? `
                            <div style="text-align: center;">
                                <div style="font-weight: 700; font-size: 1.2rem; color: var(--curry);">${parseFloat(menu.fat_g).toFixed(1)}g</div>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">Fat</div>
                            </div>
                        ` : ''}
                        ${menu.fiber_g ? `
                            <div style="text-align: center;">
                                <div style="font-weight: 700; font-size: 1.2rem; color: var(--curry);">${parseFloat(menu.fiber_g).toFixed(1)}g</div>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">Fiber</div>
                            </div>
                        ` : ''}
                        ${menu.sodium_mg ? `
                            <div style="text-align: center;">
                                <div style="font-weight: 700; font-size: 1.2rem; color: var(--curry);">${parseFloat(menu.sodium_mg).toFixed(0)}mg</div>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">Sodium</div>
                            </div>
                        ` : ''}
                    </div>
                </div>

                ${dietaryTags.length > 0 ? `
                    <div style="margin-bottom: 2rem;">
                        <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--text-dark);">Diet Types</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            ${dietaryTags.map(tag => `
                                <span style="background: var(--cream); color: var(--brown); padding: 0.4rem 0.8rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">
                                    ${tag}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}

                ${menu.spice_level ? `
                    <div style="margin-bottom: 2rem;">
                        <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--text-dark);">Spice Level</h4>
                        <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--cream); padding: 0.6rem 1rem; border-radius: 25px;">
                            <span style="font-size: 1.1rem;">🌶️</span>
                            <span style="font-weight: 600; color: var(--curry);">
                                ${spiceLabels[menu.spice_level] || 'Medium'}
                            </span>
                        </div>
                    </div>
                ` : ''}

                ${healthBenefits.length > 0 ? `
                    <div style="margin-bottom: 2rem;">
                        <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--text-dark);">Health Benefits</h4>
                        <ul style="color: var(--text-gray); line-height: 1.8; padding-left: 1.5rem;">
                            ${healthBenefits.map(benefit => `<li>${benefit}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}

                <div style="text-align: center; margin-top: 2rem;">
                    ${menu.is_logged_in ? `
                        <a href="meal-selection.php?single=${menu.id}" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">
                            <i class="fas fa-cart-plus"></i>
                            Order This Dish
                        </a>
                    ` : `
                        <a href="register.php?menu=${menu.id}" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">
                            <i class="fas fa-user-plus"></i>
                            Sign Up to Order
                        </a>
                    `}
                </div>
            `;
        }

        function closeMenuModal() {
            document.getElementById('menuModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('menuModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMenuModal();
            }
        });

        // Filter form auto-submit on change
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('filterForm');
            const selectElements = filterForm.querySelectorAll('select');
            
            selectElements.forEach(select => {
                select.addEventListener('change', function() {
                    // Auto-submit form when filters change
                    filterForm.submit();
                });
            });
        });

        // Search input debounce
        let searchTimeout;
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('filterForm').submit();
                }, 1000); // Submit after 1 second of no typing
            });
        }
    </script>
</body>
</html>