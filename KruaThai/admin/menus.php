<?php
/**
 * Krua Thai - Menu Management
 * File: admin/menus.php
 * Description: Complete menu management system with categories, nutrition tracking, and availability control
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'toggle_availability':
                $result = toggleMenuAvailability($pdo, $_POST['menu_id']);
                echo json_encode($result);
                exit;
                
            case 'toggle_featured':
                $result = toggleMenuFeatured($pdo, $_POST['menu_id']);
                echo json_encode($result);
                exit;
                
            case 'delete_menu':
                $result = deleteMenu($pdo, $_POST['menu_id']);
                echo json_encode($result);
                exit;
                
            case 'bulk_update_category':
                $result = bulkUpdateCategory($pdo, $_POST['menu_ids'], $_POST['category_id']);
                echo json_encode($result);
                exit;
                
            case 'bulk_update_availability':
                $result = bulkUpdateAvailability($pdo, $_POST['menu_ids'], $_POST['availability']);
                echo json_encode($result);
                exit;
                
            case 'get_menu_details':
                $result = getMenuDetails($pdo, $_POST['menu_id']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function toggleMenuAvailability($pdo, $menuId) {
    try {
        $stmt = $pdo->prepare("UPDATE menus SET is_available = NOT is_available, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$menuId]);
        
        if ($stmt->rowCount() > 0) {
            // Get new status
            $stmt = $pdo->prepare("SELECT is_available FROM menus WHERE id = ?");
            $stmt->execute([$menuId]);
            $newStatus = $stmt->fetchColumn();
            
            logActivity('menu_availability_changed', $_SESSION['user_id'], getRealIPAddress(), [
                'menu_id' => $menuId,
                'new_status' => $newStatus ? 'available' : 'unavailable'
            ]);
            
            return ['success' => true, 'message' => 'Menu availability updated', 'new_status' => $newStatus];
        } else {
            return ['success' => false, 'message' => 'Menu not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating availability: ' . $e->getMessage()];
    }
}

function toggleMenuFeatured($pdo, $menuId) {
    try {
        $stmt = $pdo->prepare("UPDATE menus SET is_featured = NOT is_featured, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$menuId]);
        
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT is_featured FROM menus WHERE id = ?");
            $stmt->execute([$menuId]);
            $newStatus = $stmt->fetchColumn();
            
            return ['success' => true, 'message' => 'Featured status updated', 'new_status' => $newStatus];
        } else {
            return ['success' => false, 'message' => 'Menu not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating featured status: ' . $e->getMessage()];
    }
}

function deleteMenu($pdo, $menuId) {
    try {
        // Check if menu is used in any orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE menu_id = ?");
        $stmt->execute([$menuId]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete menu item that has been ordered. Consider making it unavailable instead.'];
        }
        
        // Delete menu
        $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
        $stmt->execute([$menuId]);
        
        if ($stmt->rowCount() > 0) {
            logActivity('menu_deleted', $_SESSION['user_id'], getRealIPAddress(), [
                'menu_id' => $menuId
            ]);
            return ['success' => true, 'message' => 'Menu item deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Menu not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting menu: ' . $e->getMessage()];
    }
}

function bulkUpdateCategory($pdo, $menuIds, $categoryId) {
    try {
        $placeholders = str_repeat('?,', count($menuIds) - 1) . '?';
        $params = array_merge([$categoryId], $menuIds);
        
        $stmt = $pdo->prepare("UPDATE menus SET category_id = ?, updated_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($params);
        
        return ['success' => true, 'message' => $stmt->rowCount() . ' menus updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating menus: ' . $e->getMessage()];
    }
}

function bulkUpdateAvailability($pdo, $menuIds, $availability) {
    try {
        $placeholders = str_repeat('?,', count($menuIds) - 1) . '?';
        $isAvailable = $availability === 'available' ? 1 : 0;
        $params = array_merge([$isAvailable], $menuIds);
        
        $stmt = $pdo->prepare("UPDATE menus SET is_available = ?, updated_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($params);
        
        return ['success' => true, 'message' => $stmt->rowCount() . ' menus updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating menus: ' . $e->getMessage()];
    }
}

function getMenuDetails($pdo, $menuId) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, mc.name as category_name, mc.name_thai as category_name_thai
            FROM menus m 
            LEFT JOIN menu_categories mc ON m.category_id = mc.id
            WHERE m.id = ?
        ");
        $stmt->execute([$menuId]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($menu) {
            // Get ingredients
            $stmt = $pdo->prepare("
                SELECT mi.quantity_needed, mi.is_main_ingredient, mi.preparation_notes,
                       i.ingredient_name, i.ingredient_name_thai, i.unit_of_measure
                FROM menu_ingredients mi
                JOIN inventory i ON mi.inventory_id = i.id
                WHERE mi.menu_id = ?
                ORDER BY mi.is_main_ingredient DESC, i.ingredient_name ASC
            ");
            $stmt->execute([$menuId]);
            $menu['ingredients_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'data' => $menu];
        } else {
            return ['success' => false, 'message' => 'Menu not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching menu details: ' . $e->getMessage()];
    }
}

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$availability_filter = $_GET['availability'] ?? '';
$featured_filter = $_GET['featured'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'asc';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ['1=1'];
$params = [];

if ($category_filter) {
    $where_conditions[] = "m.category_id = ?";
    $params[] = $category_filter;
}

if ($availability_filter !== '') {
    $where_conditions[] = "m.is_available = ?";
    $params[] = $availability_filter === 'available' ? 1 : 0;
}

if ($featured_filter !== '') {
    $where_conditions[] = "m.is_featured = ?";
    $params[] = $featured_filter === 'featured' ? 1 : 0;
}

if ($search) {
    $where_conditions[] = "(m.name LIKE ? OR m.name_thai LIKE ? OR m.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Validate sort parameters
$valid_sorts = ['name', 'base_price', 'calories_per_serving', 'created_at', 'updated_at'];
$sort = in_array($sort, $valid_sorts) ? $sort : 'name';
$order = in_array(strtolower($order), ['asc', 'desc']) ? strtolower($order) : 'asc';

try {
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM menus m 
        LEFT JOIN menu_categories mc ON m.category_id = mc.id
        WHERE $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_menus = $stmt->fetchColumn();
    $total_pages = ceil($total_menus / $limit);

    // Get menus
    $menus_sql = "
        SELECT m.*, mc.name as category_name, mc.name_thai as category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories mc ON m.category_id = mc.id
        WHERE $where_clause
        ORDER BY m.$sort $order 
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($menus_sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get menu statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_menus,
            SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_menus,
            SUM(CASE WHEN is_available = 0 THEN 1 ELSE 0 END) as unavailable_menus,
            SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_menus,
            SUM(CASE WHEN is_seasonal = 1 THEN 1 ELSE 0 END) as seasonal_menus,
            AVG(base_price) as avg_price,
            AVG(calories_per_serving) as avg_calories
        FROM menus m
        WHERE $where_clause
    ";
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get categories for filter
    $categories_sql = "SELECT id, name, name_thai FROM menu_categories WHERE is_active = 1 ORDER BY sort_order, name";
    $stmt = $pdo->prepare($categories_sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get popular menus (based on order count)
    $popular_sql = "
        SELECT m.id, m.name, m.name_thai, COUNT(oi.id) as order_count
        FROM menus m
        LEFT JOIN order_items oi ON m.id = oi.menu_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY m.id, m.name, m.name_thai
        ORDER BY order_count DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($popular_sql);
    $stmt->execute();
    $popular_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $menus = [];
    $stats = ['total_menus' => 0, 'available_menus' => 0, 'unavailable_menus' => 0, 'featured_menus' => 0, 'seasonal_menus' => 0, 'avg_price' => 0, 'avg_calories' => 0];
    $categories = [];
    $popular_menus = [];
    $total_menus = 0;
    $total_pages = 1;
    error_log("Menus page error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - Krua Thai Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2rem;
            transition: var(--transition);
        }

        /* Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-gray);
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-soft);
        }

        .btn-secondary:hover {
            background: var(--cream);
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: var(--white);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: var(--white);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: var(--white);
        }

        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: var(--white);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--curry), var(--brown));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Filter Controls */
        .filter-controls {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        /* Menu Grid Layout */
        .menu-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }

        .menus-container {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Menu Cards */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }

        .menu-card {
            background: var(--white);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .menu-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .menu-card.selected {
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .menu-image {
            height: 200px;
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-gray);
            font-size: 3rem;
            position: relative;
        }

        .menu-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .menu-badges {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .menu-card-body {
            padding: 1.5rem;
        }

        .menu-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .menu-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .menu-title-thai {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .menu-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--curry);
        }

        .menu-description {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .menu-nutrition {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: var(--cream);
            border-radius: var(--radius-sm);
        }

        .nutrition-item {
            font-size: 0.8rem;
            text-align: center;
        }

        .nutrition-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .nutrition-label {
            color: var(--text-gray);
        }

        .menu-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--text-gray);
        }

        .menu-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Badge styles */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-available {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .badge-unavailable {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .badge-featured {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .badge-seasonal {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .badge-category {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .badge-spice {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: var(--transition);
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: var(--transition);
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--curry);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        /* Sidebar Info Panel */
        .info-panel {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .info-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .info-card-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .info-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .info-card-body {
            padding: 1rem;
        }

        /* Popular Menus List */
        .popular-menu-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .popular-menu-item:last-child {
            border-bottom: none;
        }

        .popular-menu-info h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .popular-menu-info p {
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        .popular-menu-count {
            font-weight: 600;
            color: var(--curry);
            font-size: 0.9rem;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--white);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: none;
            align-items: center;
            gap: 1rem;
        }

        .bulk-actions.show {
            display: flex;
        }

        .selected-count {
            font-weight: 600;
            color: var(--curry);
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
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-medium);
            animation: slideIn 0.3s ease;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-gray);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--text-dark);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-light);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Loading States */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3000;
        }

        .toast {
            background: var(--white);
            border-left: 4px solid var(--curry);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-medium);
            padding: 1rem;
            margin-bottom: 0.5rem;
            min-width: 300px;
            transform: translateX(100%);
            transition: var(--transition);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left-color: #27ae60;
        }

        .toast.error {
            border-left-color: #e74c3c;
        }

        /* Responsive Design */
        @media (max-width: 768px) {

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .menu-layout {
                grid-template-columns: 1fr;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Utilities */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .d-none { display: none; }
        .d-block { display: block; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-utensils" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Menu Management
                        </h1>
                        <p class="page-subtitle">Create, edit and manage your delicious Thai menu items</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="refreshMenus()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <a href="add-menu.php" class="btn btn-success">
                            <i class="fas fa-plus"></i>
                            Add New Menu
                        </a>
                        <button class="btn btn-primary" onclick="exportMenus()">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Menu Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-utensils"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_menus']) ?></div>
                    <div class="stat-label">Total Menu Items</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['available_menus']) ?></div>
                    <div class="stat-label">Available Items</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['featured_menus']) ?></div>
                    <div class="stat-label">Featured Items</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?= number_format($stats['avg_price'], 0) ?></div>
                    <div class="stat-label">Average Price</div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="filter-controls">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= $category_filter === $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                        <?php if ($category['name_thai']): ?>
                                            (<?= htmlspecialchars($category['name_thai']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Availability</label>
                            <select name="availability" class="form-control">
                                <option value="">All Items</option>
                                <option value="available" <?= $availability_filter === 'available' ? 'selected' : '' ?>>Available</option>
                                <option value="unavailable" <?= $availability_filter === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Featured</label>
                            <select name="featured" class="form-control">
                                <option value="">All Items</option>
                                <option value="featured" <?= $featured_filter === 'featured' ? 'selected' : '' ?>>Featured</option>
                                <option value="not_featured" <?= $featured_filter === 'not_featured' ? 'selected' : '' ?>>Not Featured</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Menu name, description..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-control">
                                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
                                <option value="base_price" <?= $sort === 'base_price' ? 'selected' : '' ?>>Price</option>
                                <option value="calories_per_serving" <?= $sort === 'calories_per_serving' ? 'selected' : '' ?>>Calories</option>
                                <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Created Date</option>
                                <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Updated Date</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Filter
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <a href="menus.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Main Layout -->
            <div class="menu-layout">
                <!-- Menus Container -->
                <div class="menus-container">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-list" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Menu Items (<?= number_format($total_menus) ?> total)
                        </div>
                        <div>
                            <button class="btn btn-sm btn-secondary" onclick="selectAllMenus()">
                                <i class="fas fa-check-square"></i>
                                Select All
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="showBulkActions()" id="bulkActionsBtn" style="display: none;">
                                <i class="fas fa-tasks"></i>
                                Bulk Actions
                            </button>
                        </div>
                    </div>

                    <!-- Bulk Actions Bar -->
                    <div class="bulk-actions" id="bulkActions">
                        <div class="selected-count">
                            <span id="selectedCount">0</span> menus selected
                        </div>
                        <select id="bulkCategorySelect" class="form-control" style="width: auto;">
                            <option value="">Change Category To...</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="bulkAvailabilitySelect" class="form-control" style="width: auto;">
                            <option value="">Change Availability To...</option>
                            <option value="available">Available</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                        <button class="btn btn-sm btn-success" onclick="applyBulkUpdate()">
                            <i class="fas fa-check"></i>
                            Apply
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>

                    <!-- Menu Grid -->
                    <?php if (!empty($menus)): ?>
                    <div class="menu-grid">
                        <?php foreach ($menus as $menu): ?>
                        <div class="menu-card" data-menu-id="<?= $menu['id'] ?>">
                            <div class="menu-image">
                                <?php if ($menu['main_image_url']): ?>
                                    <img src="../<?= htmlspecialchars($menu['main_image_url']) ?>" alt="<?= htmlspecialchars($menu['name']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-utensils"></i>
                                <?php endif; ?>
                                
                                <div class="menu-badges">
                                    <input type="checkbox" class="menu-checkbox" value="<?= $menu['id'] ?>" onchange="updateBulkActions()" style="position: absolute; top: 0.5rem; right: 0.5rem;">
                                    <?php if ($menu['is_available']): ?>
                                        <span class="badge badge-available">
                                            <i class="fas fa-check"></i>
                                            Available
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-unavailable">
                                            <i class="fas fa-times"></i>
                                            Unavailable
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($menu['is_featured']): ?>
                                        <span class="badge badge-featured">
                                            <i class="fas fa-star"></i>
                                            Featured
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($menu['is_seasonal']): ?>
                                        <span class="badge badge-seasonal">
                                            <i class="fas fa-leaf"></i>
                                            Seasonal
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="menu-card-body">
                                <div class="menu-card-header">
                                    <div>
                                        <h3 class="menu-title"><?= htmlspecialchars($menu['name']) ?></h3>
                                        <?php if ($menu['name_thai']): ?>
                                            <p class="menu-title-thai"><?= htmlspecialchars($menu['name_thai']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="menu-price">₿<?= number_format($menu['base_price'], 0) ?></div>
                                </div>
                                
                                <?php if ($menu['description']): ?>
                                    <p class="menu-description"><?= htmlspecialchars($menu['description']) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($menu['calories_per_serving'] || $menu['protein_g'] || $menu['carbs_g'] || $menu['fat_g']): ?>
                                <div class="menu-nutrition">
                                    <?php if ($menu['calories_per_serving']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?= $menu['calories_per_serving'] ?></div>
                                            <div class="nutrition-label">Calories</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($menu['protein_g']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?= $menu['protein_g'] ?>g</div>
                                            <div class="nutrition-label">Protein</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($menu['carbs_g']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?= $menu['carbs_g'] ?>g</div>
                                            <div class="nutrition-label">Carbs</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($menu['fat_g']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?= $menu['fat_g'] ?>g</div>
                                            <div class="nutrition-label">Fat</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="menu-meta">
                                    <div>
                                        <?php if ($menu['category_name']): ?>
                                            <span class="badge badge-category">
                                                <?= htmlspecialchars($menu['category_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($menu['spice_level']): ?>
                                            <span class="badge badge-spice">
                                                <?php
                                                $spice_icons = [
                                                    'mild' => '🌶️',
                                                    'medium' => '🌶️🌶️',
                                                    'hot' => '🌶️🌶️🌶️',
                                                    'extra_hot' => '🌶️🌶️🌶️🌶️'
                                                ];
                                                echo $spice_icons[$menu['spice_level']] ?? '';
                                                ?>
                                                <?= ucfirst($menu['spice_level']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <small>
                                            <i class="fas fa-clock"></i>
                                            <?= $menu['preparation_time'] ?? 15 ?> min
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="menu-actions">
                                    <button class="btn btn-icon btn-info btn-sm" onclick="viewMenuDetails('<?= $menu['id'] ?>')" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="edit-menu.php?id=<?= $menu['id'] ?>" class="btn btn-icon btn-warning btn-sm" title="Edit Menu">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <label class="toggle-switch" title="Toggle Availability">
                                        <input type="checkbox" <?= $menu['is_available'] ? 'checked' : '' ?> onchange="toggleAvailability('<?= $menu['id'] ?>', this)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <button class="btn btn-icon btn-warning btn-sm" onclick="toggleFeatured('<?= $menu['id'] ?>')" title="Toggle Featured">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <button class="btn btn-icon btn-danger btn-sm" onclick="deleteMenu('<?= $menu['id'] ?>')" title="Delete Menu">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 4rem; color: var(--text-gray);">
                        <i class="fas fa-utensils" style="font-size: 4rem; margin-bottom: 2rem; opacity: 0.3;"></i>
                        <h3>No menu items found</h3>
                        <p>No menu items match your current filters. Try adjusting your search criteria.</p>
                        <a href="add-menu.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i>
                            Add First Menu Item
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Info Panel -->
                <div class="info-panel">
                    <!-- Popular Menus -->
                    <?php if (!empty($popular_menus)): ?>
                    <div class="info-card">
                        <div class="info-card-header">
                            <h3 class="info-card-title">
                                <i class="fas fa-fire" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                Popular This Month
                            </h3>
                        </div>
                        <div class="info-card-body">
                            <?php foreach ($popular_menus as $index => $menu): ?>
                            <div class="popular-menu-item">
                                <div class="popular-menu-info">
                                    <h4>#<?= $index + 1 ?> <?= htmlspecialchars($menu['name']) ?></h4>
                                    <?php if ($menu['name_thai']): ?>
                                        <p><?= htmlspecialchars($menu['name_thai']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="popular-menu-count">
                                    <?= $menu['order_count'] ?> orders
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Stats -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <h3 class="info-card-title">
                                <i class="fas fa-chart-pie" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                Menu Analytics
                            </h3>
                        </div>
                        <div class="info-card-body">
                            <div style="display: grid; gap: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>Available Items</span>
                                    <strong style="color: var(--sage);"><?= $stats['available_menus'] ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>Unavailable Items</span>
                                    <strong style="color: #e74c3c;"><?= $stats['unavailable_menus'] ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>Featured Items</span>
                                    <strong style="color: #f39c12;"><?= $stats['featured_menus'] ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>Seasonal Items</span>
                                    <strong style="color: #9b59b6;"><?= $stats['seasonal_menus'] ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>Avg. Calories</span>
                                    <strong style="color: var(--curry);"><?= number_format($stats['avg_calories']) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <h3 class="info-card-title">
                                <i class="fas fa-bolt" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                Quick Actions
                            </h3>
                        </div>
                        <div class="info-card-body">
                            <div style="display: grid; gap: 0.5rem;">
                                <a href="add-menu.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus"></i>
                                    Add New Menu
                                </a>
                                <a href="categories.php" class="btn btn-info btn-sm">
                                    <i class="fas fa-tags"></i>
                                    Manage Categories
                                </a>
                                <button class="btn btn-warning btn-sm" onclick="bulkMakeAvailable()">
                                    <i class="fas fa-check-circle"></i>
                                    Make All Available
                                </button>
                                <button class="btn btn-secondary btn-sm" onclick="exportMenus()">
                                    <i class="fas fa-download"></i>
                                    Export Menu List
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu Details Modal -->
    <div class="modal" id="menuDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Menu Details</h3>
                <button class="modal-close" onclick="closeModal('menuDetailsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="menuDetailsContent">
                <div class="loading-overlay">
                    <div class="spinner"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('menuDetailsModal')">Close</button>
                <button class="btn btn-warning" onclick="editCurrentMenu()">
                    <i class="fas fa-edit"></i>
                    Edit Menu
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let selectedMenus = new Set();
        let currentMenuId = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Menus page initialized');
            updateBulkActions();
        });

        // Refresh menus
        function refreshMenus() {
            showToast('Refreshing menus...', 'info');
            window.location.reload();
        }

        // Export menus
        function exportMenus() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open(`export-menus.php?${params.toString()}`, '_blank');
            showToast('Export started...', 'info');
        }

        // Toggle menu availability
        function toggleAvailability(menuId, toggleElement) {
            const wasChecked = toggleElement.checked;
            
            // Disable toggle during update
            toggleElement.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_availability&menu_id=${menuId}`
            })
            .then(response => response.json())
            .then(data => {
                toggleElement.disabled = false;
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update badge
                    updateMenuBadge(menuId, 'availability', data.new_status);
                } else {
                    showToast(data.message, 'error');
                    // Revert toggle
                    toggleElement.checked = !wasChecked;
                }
            })
            .catch(error => {
                toggleElement.disabled = false;
                console.error('Error toggling availability:', error);
                showToast('Error updating availability', 'error');
                // Revert toggle
                toggleElement.checked = !wasChecked;
            });
        }

        // Toggle featured status
        function toggleFeatured(menuId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_featured&menu_id=${menuId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    updateMenuBadge(menuId, 'featured', data.new_status);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error toggling featured:', error);
                showToast('Error updating featured status', 'error');
            });
        }

        // Delete menu
        function deleteMenu(menuId) {
            if (!confirm('Are you sure you want to delete this menu item? This action cannot be undone.')) {
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_menu&menu_id=${menuId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Remove menu card from DOM
                    document.querySelector(`[data-menu-id="${menuId}"]`).remove();
                    updateBulkActions();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting menu:', error);
                showToast('Error deleting menu', 'error');
            });
        }

        // View menu details
        function viewMenuDetails(menuId) {
            currentMenuId = menuId;
            document.getElementById('menuDetailsModal').classList.add('show');
            
            // Show loading
            document.getElementById('menuDetailsContent').innerHTML = `
                <div class="loading-overlay">
                    <div class="spinner"></div>
                </div>
            `;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_menu_details&menu_id=${menuId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMenuDetails(data.data);
                } else {
                    document.getElementById('menuDetailsContent').innerHTML = `
                        <div class="text-center" style="padding: 2rem; color: var(--text-gray);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <h3>Error Loading Menu</h3>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching menu details:', error);
                document.getElementById('menuDetailsContent').innerHTML = `
                    <div class="text-center" style="padding: 2rem; color: var(--text-gray);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <h3>Error Loading Menu</h3>
                        <p>Failed to load menu details. Please try again.</p>
                    </div>
                `;
            });
        }

        // Display menu details
        function displayMenuDetails(menu) {
            const createdDate = new Date(menu.created_at).toLocaleString();
            const updatedDate = new Date(menu.updated_at).toLocaleString();
            
            let ingredientsHtml = '';
            if (menu.ingredients_list && menu.ingredients_list.length > 0) {
                ingredientsHtml = menu.ingredients_list.map(ingredient => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light);">
                        <div>
                            <strong>${escapeHtml(ingredient.ingredient_name)}</strong>
                            ${ingredient.ingredient_name_thai ? `<div style="font-size: 0.9rem; color: var(--text-gray);">${escapeHtml(ingredient.ingredient_name_thai)}</div>` : ''}
                            ${ingredient.is_main_ingredient ? '<span class="badge badge-featured" style="font-size: 0.7rem; margin-top: 0.25rem;">Main</span>' : ''}
                        </div>
                        <div style="text-align: right;">
                            <div>${ingredient.quantity_needed} ${ingredient.unit_of_measure}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                ingredientsHtml = '<p style="color: var(--text-gray);">No ingredients data available</p>';
            }
            
            const nutritionHtml = `
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div>
                        <strong>Calories:</strong> ${menu.calories_per_serving || 'N/A'}
                    </div>
                    <div>
                        <strong>Protein:</strong> ${menu.protein_g ? menu.protein_g + 'g' : 'N/A'}
                    </div>
                    <div>
                        <strong>Carbs:</strong> ${menu.carbs_g ? menu.carbs_g + 'g' : 'N/A'}
                    </div>
                    <div>
                        <strong>Fat:</strong> ${menu.fat_g ? menu.fat_g + 'g' : 'N/A'}
                    </div>
                    <div>
                        <strong>Fiber:</strong> ${menu.fiber_g ? menu.fiber_g + 'g' : 'N/A'}
                    </div>
                    <div>
                        <strong>Sodium:</strong> ${menu.sodium_mg ? menu.sodium_mg + 'mg' : 'N/A'}
                    </div>
                </div>
            `;
            
            document.getElementById('menuDetailsContent').innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="margin-bottom: 1rem; color: var(--curry);">Basic Information</h4>
                        <div style="margin-bottom: 1rem;">
                            <strong>Name:</strong><br>
                            ${escapeHtml(menu.name)}
                            ${menu.name_thai ? `<br><span style="color: var(--text-gray);">${escapeHtml(menu.name_thai)}</span>` : ''}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Category:</strong><br>
                            ${menu.category_name ? escapeHtml(menu.category_name) : 'No category'}
                            ${menu.category_name_thai ? `<br><span style="color: var(--text-gray);">${escapeHtml(menu.category_name_thai)}</span>` : ''}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Price:</strong><br>
                            ₿${parseFloat(menu.base_price).toFixed(2)}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Preparation Time:</strong><br>
                            ${menu.preparation_time || 15} minutes
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Spice Level:</strong><br>
                            ${menu.spice_level ? menu.spice_level.charAt(0).toUpperCase() + menu.spice_level.slice(1) : 'Not specified'}
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 1rem; color: var(--curry);">Status & Settings</h4>
                        <div style="margin-bottom: 1rem;">
                            <strong>Availability:</strong><br>
                            <span class="badge ${menu.is_available ? 'badge-available' : 'badge-unavailable'}">
                                ${menu.is_available ? 'Available' : 'Unavailable'}
                            </span>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Featured:</strong><br>
                            <span class="badge ${menu.is_featured ? 'badge-featured' : 'badge-category'}">
                                ${menu.is_featured ? 'Featured' : 'Not Featured'}
                            </span>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Seasonal:</strong><br>
                            <span class="badge ${menu.is_seasonal ? 'badge-seasonal' : 'badge-category'}">
                                ${menu.is_seasonal ? 'Seasonal' : 'Regular'}
                            </span>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Created:</strong><br>
                            ${createdDate}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Last Updated:</strong><br>
                            ${updatedDate}
                        </div>
                    </div>
                </div>
                
                ${menu.description ? `
                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--curry);">Description</h4>
                    <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                        ${escapeHtml(menu.description)}
                    </div>
                </div>
                ` : ''}
                
                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--curry);">Nutrition Information</h4>
                    <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                        ${nutritionHtml}
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--curry);">Ingredients</h4>
                    <div style="border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        ${ingredientsHtml}
                    </div>
                </div>
            `;
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Edit current menu
        function editCurrentMenu() {
            if (currentMenuId) {
                window.location.href = `edit-menu.php?id=${currentMenuId}`;
            }
        }

        // Update menu badge
        function updateMenuBadge(menuId, type, status) {
            const menuCard = document.querySelector(`[data-menu-id="${menuId}"]`);
            if (!menuCard) return;
            
            const badgesContainer = menuCard.querySelector('.menu-badges');
            
            if (type === 'availability') {
                const existingBadge = badgesContainer.querySelector('.badge-available, .badge-unavailable');
                if (existingBadge) {
                    existingBadge.remove();
                }
                
                const newBadge = document.createElement('span');
                newBadge.className = `badge ${status ? 'badge-available' : 'badge-unavailable'}`;
                newBadge.innerHTML = `<i class="fas fa-${status ? 'check' : 'times'}"></i> ${status ? 'Available' : 'Unavailable'}`;
                badgesContainer.appendChild(newBadge);
            } else if (type === 'featured') {
                const existingBadge = badgesContainer.querySelector('.badge-featured');
                if (status && !existingBadge) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge badge-featured';
                    newBadge.innerHTML = '<i class="fas fa-star"></i> Featured';
                    badgesContainer.appendChild(newBadge);
                } else if (!status && existingBadge) {
                    existingBadge.remove();
                }
            }
        }

        // Bulk selection functions
        function selectAllMenus() {
            const checkboxes = document.querySelectorAll('.menu-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                selectedMenus.add(checkbox.value);
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.menu-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const bulkActionsBtn = document.getElementById('bulkActionsBtn');
            const selectedCount = document.getElementById('selectedCount');
            
            // Update selected menus set
            selectedMenus.clear();
            checkboxes.forEach(checkbox => {
                selectedMenus.add(checkbox.value);
            });
            
            // Update UI
            selectedCount.textContent = selectedMenus.size;
            
            if (selectedMenus.size > 0) {
                bulkActions.classList.add('show');
                bulkActionsBtn.style.display = 'inline-flex';
            } else {
                bulkActions.classList.remove('show');
                bulkActionsBtn.style.display = 'none';
            }
        }

        function showBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            bulkActions.classList.add('show');
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.menu-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectedMenus.clear();
            
            updateBulkActions();
        }

        function applyBulkUpdate() {
            const categoryId = document.getElementById('bulkCategorySelect').value;
            const availability = document.getElementById('bulkAvailabilitySelect').value;
            
            if (!categoryId && !availability) {
                showToast('Please select an action to perform', 'error');
                return;
            }
            
            if (selectedMenus.size === 0) {
                showToast('No menus selected', 'error');
                return;
            }
            
            const menuIds = Array.from(selectedMenus);
            let action, params;
            
            if (categoryId) {
                action = 'bulk_update_category';
                params = `action=${action}&menu_ids=${JSON.stringify(menuIds)}&category_id=${categoryId}`;
            } else if (availability) {
                action = 'bulk_update_availability';
                params = `action=${action}&menu_ids=${JSON.stringify(menuIds)}&availability=${availability}`;
            }
            
            if (!confirm(`Update ${selectedMenus.size} menus?`)) {
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => refreshMenus(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error updating menus:', error);
                showToast('Error updating menus', 'error');
            });
        }

        // Quick actions
        function bulkMakeAvailable() {
            if (confirm('Make all menus available?')) {
                // This would select all menus and make them available
                showToast('This feature is coming soon!', 'info');
            }
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            toast.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: var(--text-gray);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                showToast('Logging out...', 'info');
                
                fetch('../auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=logout'
                })
                .then(response => {
                    window.location.href = '../login.php';
                })
                .catch(error => {
                    console.error('Logout error:', error);
                    window.location.href = '../login.php';
                });
            }
        }

        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshMenus();
            }
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                selectAllMenus();
            }
            if (e.key === 'Escape') {
                clearSelection();
                // Close any open modals
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Menus page loaded in ${Math.round(loadTime)}ms`);
        });

        console.log('Krua Thai Menu Management initialized successfully');
    </script>
</body>
</html>