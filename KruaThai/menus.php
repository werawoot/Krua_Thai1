<?php
/**
 * Somdul Table - Public Menus Page
 * File: menus.php
 * Description: Browse, filter, and search all available menus
 * FIXED: Now uses header.php consistently
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

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

    // Get menus with additional images
    $sql = "
        SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai,
               m.main_image_url, m.additional_images
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
    <title>Menu - Somdul Table | Authentic Thai Restaurant Management</title>
    <meta name="description" content="Browse our healthy Thai food menu from Somdul Table with complete nutritional information and pricing">
    
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

    /* Menu Navigation */
    .menu-nav-container {
        margin: 2rem 0;
        padding: 20px 0;
        background: var(--cream);
        border-top: 1px solid rgba(189, 147, 121, 0.1);
        border-bottom: 1px solid rgba(189, 147, 121, 0.1);
    }

    .menu-nav-wrapper {
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding: 0 1rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .menu-nav-wrapper::-webkit-scrollbar { display: none; }

    .menu-nav-list {
        display: flex;
        gap: 0;
        min-width: max-content;
        align-items: center;
        justify-content: center;
    }

    .menu-nav-item {
        display: flex;
        align-items: center;
        gap: 8px;
        height: 54px;
        padding: 0 16px;
        border-bottom: 2px solid transparent;
        background: transparent;
        cursor: pointer;
        font-family: 'BaticaSans', Arial, sans-serif;
        font-size: 14px;
        font-weight: 600;
        color: #707070;
        transition: all 0.3s ease;
        white-space: nowrap;
        text-decoration: none;
        border-radius: 8px;
    }

    .menu-nav-item:hover {
        color: var(--brown);
        background: rgba(189, 147, 121, 0.1);
        border-bottom-color: var(--brown);
    }

    .menu-nav-item.active {
        color: var(--brown);
        background: var(--white);
        border-bottom-color: var(--brown);
        box-shadow: var(--shadow-soft);
    }

    .menu-nav-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .menu-nav-icon svg {
        width: 100%;
        height: 100%;
        fill: #707070;
        transition: fill 0.3s ease;
    }

    .menu-nav-item:hover .menu-nav-icon svg { fill: var(--brown); }
    .menu-nav-item.active .menu-nav-icon svg { fill: var(--brown); }

    /* Results */
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
        font-family: 'BaticaSans', sans-serif;
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
        border-radius: 16px;
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
        font-family: 'BaticaSans', sans-serif;
        cursor: pointer;
        transition: var(--transition);
    }

    .menu-image:hover { transform: scale(1.02); }
    .menu-image img { width: 100%; height: 100%; object-fit: cover; }

    .menu-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.95);
        color: var(--brown);
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
        font-family: 'BaticaSans', sans-serif;
    }

    .featured-badge {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: var(--brown);
        color: var(--white);
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-content { padding: 1.5rem; }

    .menu-category {
        font-size: 0.8rem;
        color: var(--brown);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 0.8rem;
        line-height: 1.3;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-title-en {
        font-size: 0.9rem;
        color: var(--text-gray);
        font-weight: 500;
        margin-bottom: 0.8rem;
        font-family: 'BaticaSans', sans-serif;
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
        font-family: 'BaticaSans', sans-serif;
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
        font-family: 'BaticaSans', sans-serif;
    }

    .spice-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.6rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
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
        color: var(--brown);
        font-size: 0.9rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .nutrition-label {
        font-size: 0.8rem;
        color: var(--text-gray);
        margin-top: 0.2rem;
        font-family: 'BaticaSans', sans-serif;
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
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Button styles for menu actions (extend header.php button styles) */
    .btn-sm {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-gray);
        grid-column: 1 / -1;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
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
        z-index: 2000;
        align-items: center;
        justify-content: center;
    }

    .modal.show { display: flex; }

    .modal-content {
        background: var(--white);
        border-radius: 16px;
        max-width: 900px;
        width: 90%;
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
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-gray);
    }

    /* Modal Image Gallery */
    .modal-image-gallery {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        height: 400px;
    }

    .modal-main-image-container {
        flex: 5;
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(135deg, var(--cream), #e8dcc0);
    }

    .modal-main-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .modal-main-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-gray);
        font-size: 1.5rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        flex-direction: column;
        gap: 0.5rem;
    }

    .modal-thumbnail-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .modal-thumbnail {
        flex: 1;
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        transition: var(--transition);
        position: relative;
        background: linear-gradient(135deg, #f8f4f0, #e8dcc0);
        border: 2px solid transparent;
    }

    .modal-thumbnail:hover {
        transform: scale(1.02);
        box-shadow: var(--shadow-soft);
    }

    .modal-thumbnail.active {
        border-color: var(--brown);
        transform: scale(1.02);
    }

    .modal-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .modal-thumbnail-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-gray);
        font-size: 1.2rem;
        opacity: 0.5;
    }

    /* CTA Section */
    .cta-section {
        text-align: center;
        padding: 3rem 2rem;
        background: var(--cream);
        border-radius: 16px;
        margin-top: 3rem;
    }

    .cta-section h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .cta-section p {
        font-size: 1.1rem;
        color: var(--text-gray);
        margin-bottom: 2rem;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Footer */
    footer {
        background: var(--text-dark);
        color: var(--white);
        padding: 2rem 0;
        text-align: center;
        margin-top: 4rem;
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

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .container { padding: 0 15px; }
        .menus-grid { grid-template-columns: 1fr; }
        .results-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
        .menu-nav-item { padding: 0 12px; font-size: 13px; }
        .menu-nav-icon { width: 20px; height: 20px; }
        .menu-actions { flex-direction: column; gap: 0.5rem; }
        .menu-footer { flex-direction: column; gap: 1rem; align-items: flex-start; }
        .modal-content { margin: 1rem; max-width: 95%; }
        .cta-section { padding: 2rem 1rem; }
        
        /* Modal image gallery responsive */
        .modal-image-gallery {
            flex-direction: column;
            height: auto;
        }

        .modal-main-image-container {
            height: 280px;
        }

        .modal-thumbnail-container {
            flex-direction: row;
            height: 80px;
        }
    }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="container">

            <!-- Menu Navigation Container -->
            <div class="menu-nav-container">
                <div class="menu-nav-wrapper">
                    <div class="menu-nav-list">
                        <?php if (empty($categories)): ?>
                            <a href="menus.php" class="menu-nav-item <?php echo empty($category_id) ? 'active' : ''; ?>">
                                <span class="menu-nav-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                    </svg>
                                </span>
                                <span class="menu-nav-text">All Items</span>
                            </a>
                        <?php else: ?>
                            <a href="menus.php" class="menu-nav-item <?php echo empty($category_id) ? 'active' : ''; ?>">
                                <span class="menu-nav-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                    </svg>
                                </span>
                                <span class="menu-nav-text">All Items</span>
                            </a>
                            
                            <?php 
                            $category_icons = [
                                'Rice Bowls' => '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>',
                                'Thai Curries' => '<path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
                                'Noodle Dishes' => '<path d="M22 2v20H2V2h20zm-2 2H4v16h16V4zM6 8h12v2H6V8zm0 4h12v2H6v-2zm0 4h8v2H6v-2z"/>',
                                'Stir Fry' => '<path d="M12.5 3.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5S10.17 2 11 2s1.5.67 1.5 1.5zM20 8H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zm0 10H4v-8h16v8zm-8-6c1.38 0 2.5 1.12 2.5 2.5S13.38 17 12 17s-2.5-1.12-2.5-2.5S10.62 12 12 12z"/>',
                                'Rice Dishes' => '<path d="M18 3H6c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H6V5h12v14zM8 7h8v2H8V7zm0 4h8v2H8v-2zm0 4h6v2H8v-2z"/>',
                                'Soups' => '<path d="M4 18h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2zm0-10h16v8H4V8zm8-4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>',
                                'Salads' => '<path d="M7 10c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm8 0c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8 0-1.12.23-2.18.65-3.15C6.53 8.51 8 8 9.64 8c.93 0 1.83.22 2.64.61.81-.39 1.71-.61 2.64-.61 1.64 0 3.11.51 4.35.85.42.97.65 2.03.65 3.15 0 4.41-3.59 8-8 8z"/>',
                                'Desserts' => '<path d="M12 3L8 6.5h8L12 3zm0 18c4.97 0 9-4.03 9-9H3c0 4.97 4.03 9 9 9zm0-16L8.5 8h7L12 5z"/>',
                                'Beverages' => '<path d="M5 4v3h5.5v12h3V7H19V4H5z"/>'
                            ];
                            
                            $default_icon = '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>';
                            
                            foreach ($categories as $category): 
                                $category_name = $category['name'] ?: $category['name_thai'];
                                $icon_path = $category_icons[$category_name] ?? $default_icon;
                                $is_active = ($category_id == $category['id']) ? 'active' : '';
                            ?>
                                <a href="menus.php?category_id=<?php echo $category['id']; ?>" 
                                   class="menu-nav-item <?php echo $is_active; ?>">
                                    <span class="menu-nav-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <?php echo $icon_path; ?>
                                        </svg>
                                    </span>
                                    <span class="menu-nav-text">
                                        <?php echo htmlspecialchars($category_name); ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
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
                    <div class="empty-state">
                        <i style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">🍽️</i>
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
                            
                            <div class="menu-image" onclick="showMenuModal('<?php echo $menu['id']; ?>')" title="Click to view details">
                                <?php if ($menu['main_image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($menu['main_image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($menu['name']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div style="text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;">🍽️</div>
                                        <?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?>
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
                                            Details
                                        </button>
                                        
                                        <?php if ($is_logged_in): ?>
                                            <a href="subscribe.php?menu=<?php echo $menu['id']; ?>" 
                                            class="btn btn-primary btn-sm">
                                                🛒 Order Now
                                            </a>
                                        <?php else: ?>
                                            <a href="register.php?menu=<?php echo $menu['id']; ?>" 
                                            class="btn btn-primary btn-sm">
                                                Sign Up to Order
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
                <div class="cta-section">
                    <h2>Ready to start your healthy eating journey?</h2>
                    <p>
                        Choose the meal plan that's right for you and start taking care of your health with authentic Thai cuisine
                    </p>
                    <a href="index.php#plans" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2.5rem;">
                        🌿 View All Plans
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
                <button class="modal-close" onclick="closeMenuModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">
                    <i>⟲</i>
                    <p>Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 45px; width: auto;">
                <span style="font-size: 1.5rem; font-weight: 700;">Somdul Table</span>
            </div>
            <p style="color: var(--text-gray); margin-bottom: 0.5rem;">
                Healthy Thai food delivered to your door
            </p>
            <p style="color: var(--text-gray); font-size: 0.9rem;">
                © 2025 Somdul Table. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
    // Page-specific JavaScript for menus.php
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🍽️ Menus page loaded');
        
        // Check for show_menu parameter and automatically open modal
        const urlParams = new URLSearchParams(window.location.search);
        const showMenuId = urlParams.get('show_menu');
        
        if (showMenuId) {
            console.log('Auto-opening modal for menu ID:', showMenuId);
            // Wait a brief moment for the page to fully load, then show the modal
            setTimeout(() => {
                showMenuModal(showMenuId);
                // Remove the parameter from the URL without reloading the page
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                urlParams.delete('show_menu');
                const remainingParams = urlParams.toString();
                if (remainingParams) {
                    window.history.replaceState({}, document.title, newUrl + '?' + remainingParams);
                } else {
                    window.history.replaceState({}, document.title, newUrl);
                }
            }, 500);
        }
        
        // The mobile menu and promo banner functions are already available from header.php
        // You can use: toggleMobileMenu(), closeMobileMenu(), closePromoBanner()
    });

    // Modal Image Gallery Functions
    function changeModalImage(menuId, imageIndex, clickedThumbnail) {
        const mainImageElement = document.getElementById(`modal-main-image-${menuId}`);
        const imageUrl = clickedThumbnail.getAttribute('data-image-url');
        
        // Remove active class from all thumbnails in this modal
        const thumbnailContainer = clickedThumbnail.parentElement;
        thumbnailContainer.querySelectorAll('.modal-thumbnail').forEach(thumb => {
            thumb.classList.remove('active');
        });
        
        // Add active class to clicked thumbnail
        clickedThumbnail.classList.add('active');
        
        // Change main image
        if (imageUrl && imageUrl.trim() !== '') {
            if (mainImageElement.tagName === 'IMG') {
                mainImageElement.src = imageUrl;
            } else {
                // Replace placeholder with actual image
                const newImg = document.createElement('img');
                newImg.src = imageUrl;
                newImg.alt = 'Menu Image';
                newImg.className = 'modal-main-image';
                newImg.id = `modal-main-image-${menuId}`;
                newImg.loading = 'lazy';
                
                mainImageElement.parentElement.replaceChild(newImg, mainImageElement);
            }
        }
    }

    // Menu Modal Functions
    async function showMenuModal(menuId) {
        const modal = document.getElementById('menuModal');
        const modalBody = document.getElementById('modalBody');
        
        // Show modal with loading state
        modal.classList.add('show');
        modalBody.innerHTML = `
            <div class="loading">
                <i>⟲</i>
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
                        <i style="font-size: 2rem; margin-bottom: 1rem;">⚠️</i>
                        <h4>Unable to load data</h4>
                        <p>Please try again</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading menu details:', error);
            modalBody.innerHTML = `
                <div class="text-center" style="padding: 2rem; color: var(--text-gray);">
                    <i style="font-size: 2rem; margin-bottom: 1rem;">📶</i>
                    <h4>Connection Error</h4>
                    <p>Check your internet connection</p>
                </div>
            `;
        }
    }

    function buildMenuModalContent(menu) {
        const dietaryTags = menu.dietary_tags ? JSON.parse(menu.dietary_tags) : [];
        const healthBenefits = menu.health_benefits ? JSON.parse(menu.health_benefits) : [];
        
        // Parse additional images
        const additionalImages = menu.additional_images ? JSON.parse(menu.additional_images) : [];
        
        // Create image array with main image first, then additional images
        const allImages = [];
        if (menu.main_image_url) {
            allImages.push(menu.main_image_url);
        }
        
        // Add additional images
        if (additionalImages.length > 0) {
            allImages.push(...additionalImages);
        } else {
            // Mock additional images for demonstration (remove this in production)
            const mockImages = [
                'assets/image/sample1.jpg',
                'assets/image/sample2.jpg',
                'assets/image/sample3.jpg',
                'assets/image/sample4.jpg'
            ];
            allImages.push(...mockImages);
        }
        
        // Fill up to 5 images with placeholders if needed
        while (allImages.length < 5) {
            allImages.push(null);
        }
        allImages.splice(5); // Limit to 5 images
        
        const spiceLabels = {
            'mild': 'Mild',
            'medium': 'Medium', 
            'hot': 'Hot',
            'extra_hot': 'Extra Hot'
        };
        
        return `
            <!-- Image Gallery -->
            <div class="modal-image-gallery">
                <!-- Main Image -->
                <div class="modal-main-image-container">
                    ${allImages[0] ? 
                        `<img src="${allImages[0]}" alt="${menu.name}" class="modal-main-image" id="modal-main-image-${menu.id}" loading="lazy">` :
                        `<div class="modal-main-image-placeholder" id="modal-main-image-${menu.id}">
                            <div style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.5;">🍽️</div>
                            <div>${menu.name || menu.name_thai}</div>
                        </div>`
                    }
                </div>
                
                <!-- Thumbnail Images -->
                <div class="modal-thumbnail-container">
                    ${allImages.slice(1, 5).map((imageUrl, index) => `
                        <div class="modal-thumbnail ${index === 0 ? 'active' : ''}" 
                             onclick="changeModalImage('${menu.id}', ${index + 1}, this)"
                             data-image-url="${imageUrl || ''}">
                            ${imageUrl ? 
                                `<img src="${imageUrl}" alt="${menu.name} - Image ${index + 2}" loading="lazy">` :
                                `<div class="modal-thumbnail-placeholder">📷</div>`
                            }
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Menu Information -->
            <div style="text-align: center; margin-bottom: 2rem;">
                <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--brown); margin-bottom: 0.5rem;">
                    ${menu.name || menu.name_thai}
                </h2>
                ${menu.name && menu.name_thai ? `<p style="color: var(--text-gray); margin-bottom: 1rem;">${menu.name_thai}</p>` : ''}
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--brown);">
                    $${parseFloat(menu.base_price).toFixed(2)}
                </div>
            </div>

            <div style="margin-bottom: 2rem;">
                <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--brown);">Description</h4>
                <p style="color: var(--text-gray); line-height: 1.6;">
                    ${menu.description || 'Healthy Thai cuisine'}
                </p>
            </div>

            ${menu.ingredients ? `
                <div style="margin-bottom: 2rem;">
                    <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--brown);">Ingredients</h4>
                    <p style="color: var(--text-gray); line-height: 1.6;">
                        ${menu.ingredients}
                    </p>
                </div>
            ` : ''}

            <div style="margin-bottom: 2rem;">
                <h4 style="font-weight: 600; margin-bottom: 1rem; color: var(--brown);">Nutritional Information</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 1rem; background: var(--cream); padding: 1.5rem; border-radius: 12px;">
                    ${menu.calories_per_serving ? `
                        <div style="text-align: center;">
                            <div style="font-weight: 700; font-size: 1.2rem; color: var(--brown);">${menu.calories_per_serving}</div>
                            <div style="font-size: 0.9rem; color: var(--text-gray);">Calories</div>
                        </div>
                    ` : ''}
                    ${menu.protein_g ? `
                        <div style="text-align: center;">
                            <div style="font-weight: 700; font-size: 1.2rem; color: var(--brown);">${parseFloat(menu.protein_g).toFixed(1)}g</div>
                            <div style="font-size: 0.9rem; color: var(--text-gray);">Protein</div>
                        </div>
                    ` : ''}
                    ${menu.carbs_g ? `
                        <div style="text-align: center;">
                            <div style="font-weight: 700; font-size: 1.2rem; color: var(--brown);">${parseFloat(menu.carbs_g).toFixed(1)}g</div>
                            <div style="font-size: 0.9rem; color: var(--text-gray);">Carbohydrates</div>
                        </div>
                    ` : ''}
                    ${menu.fat_g ? `
                        <div style="text-align: center;">
                            <div style="font-weight: 700; font-size: 1.2rem; color: var(--brown);">${parseFloat(menu.fat_g).toFixed(1)}g</div>
                            <div style="font-size: 0.9rem; color: var(--text-gray);">Fat</div>
                        </div>
                    ` : ''}
                    ${menu.fiber_g ? `
                        <div style="text-align: center;">
                            <div style="font-weight: 700; font-size: 1.2rem; color: var(--brown);">${parseFloat(menu.fiber_g).toFixed(1)}g</div>
                            <div style="font-size: 0.9rem; color: var(--text-gray);">Fiber</div>
                        </div>
                    ` : ''}
                    ${menu.sodium_mg ? `
                        <div style="text-align: center;">
                            <div style="font-weight: 700; font-size: 1.2rem; color: var(--brown);">${parseFloat(menu.sodium_mg).toFixed(0)}mg</div>
                            <div style="font-size: 0.9rem; color: var(--text-gray);">Sodium</div>
                        </div>
                    ` : ''}
                </div>
            </div>

            ${dietaryTags.length > 0 ? `
                <div style="margin-bottom: 2rem;">
                    <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--brown);">Diet Types</h4>
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
                    <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--brown);">Spice Level</h4>
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--cream); padding: 0.6rem 1rem; border-radius: 25px;">
                        <span style="font-size: 1.1rem;">🌶️</span>
                        <span style="font-weight: 600; color: var(--brown);">
                            ${spiceLabels[menu.spice_level] || 'Medium'}
                        </span>
                    </div>
                </div>
            ` : ''}

            ${healthBenefits.length > 0 ? `
                <div style="margin-bottom: 2rem;">
                    <h4 style="font-weight: 600; margin-bottom: 0.8rem; color: var(--brown);">Health Benefits</h4>
                    <ul style="color: var(--text-gray); line-height: 1.8; padding-left: 1.5rem;">
                        ${healthBenefits.map(benefit => `<li>${benefit}</li>`).join('')}
                    </ul>
                </div>
            ` : ''}

            <div style="text-align: center; margin-top: 2rem;">
                ${menu.is_logged_in ? `
                    <a href="meal-selection.php?single=${menu.id}" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">
                        🛒 Order This Dish
                    </a>
                ` : `
                    <a href="register.php?menu=${menu.id}" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">
                        👤 Sign Up to Order
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
    </script>
</body>
</html>