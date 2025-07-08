<?php
/**
 * Krua Thai - Menu Categories Management
 * File: admin/categories.php
 * Description: Complete category management system with drag-and-drop sorting and image upload
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

$errors = [];
$success_message = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_category':
                $result = addCategory($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'edit_category':
                $result = editCategory($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'delete_category':
                $result = deleteCategory($pdo, $_POST['category_id']);
                echo json_encode($result);
                exit;
                
            case 'toggle_status':
                $result = toggleCategoryStatus($pdo, $_POST['category_id']);
                echo json_encode($result);
                exit;
                
            case 'update_sort_order':
                $result = updateSortOrder($pdo, $_POST['categories']);
                echo json_encode($result);
                exit;
                
            case 'get_category':
                $result = getCategory($pdo, $_POST['category_id']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function addCategory($pdo, $data) {
    try {
        $name = sanitizeInput($data['name'] ?? '');
        $name_thai = sanitizeInput($data['name_thai'] ?? '');
        $description = sanitizeInput($data['description'] ?? '');
        
        if (empty($name)) {
            return ['success' => false, 'message' => 'Category name is required'];
        }
        
        // Check for duplicate name
        $stmt = $pdo->prepare("SELECT id FROM menu_categories WHERE name = ? OR name_thai = ?");
        $stmt->execute([$name, $name_thai]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'A category with this name already exists'];
        }
        
        // Get next sort order
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM menu_categories");
        $stmt->execute();
        $next_order = $stmt->fetchColumn();
        
        // Insert category
        $category_id = generateUUID();
        $stmt = $pdo->prepare("
            INSERT INTO menu_categories (id, name, name_thai, description, sort_order, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$category_id, $name, $name_thai ?: null, $description ?: null, $next_order]);
        
        logActivity('category_created', $_SESSION['user_id'], getRealIPAddress(), [
            'category_id' => $category_id,
            'category_name' => $name
        ]);
        
        return ['success' => true, 'message' => 'Category created successfully', 'category_id' => $category_id];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error creating category: ' . $e->getMessage()];
    }
}

function editCategory($pdo, $data) {
    try {
        $category_id = $data['category_id'] ?? '';
        $name = sanitizeInput($data['name'] ?? '');
        $name_thai = sanitizeInput($data['name_thai'] ?? '');
        $description = sanitizeInput($data['description'] ?? '');
        
        if (empty($name)) {
            return ['success' => false, 'message' => 'Category name is required'];
        }
        
        // Check for duplicate name (excluding current category)
        $stmt = $pdo->prepare("SELECT id FROM menu_categories WHERE (name = ? OR name_thai = ?) AND id != ?");
        $stmt->execute([$name, $name_thai, $category_id]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'A category with this name already exists'];
        }
        
        // Update category
        $stmt = $pdo->prepare("
            UPDATE menu_categories 
            SET name = ?, name_thai = ?, description = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$name, $name_thai ?: null, $description ?: null, $category_id]);
        
        if ($stmt->rowCount() > 0) {
            logActivity('category_updated', $_SESSION['user_id'], getRealIPAddress(), [
                'category_id' => $category_id,
                'category_name' => $name
            ]);
            return ['success' => true, 'message' => 'Category updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Category not found or no changes made'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating category: ' . $e->getMessage()];
    }
}

function deleteCategory($pdo, $category_id) {
    try {
        // Check if category has menus
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $menu_count = $stmt->fetchColumn();
        
        if ($menu_count > 0) {
            return ['success' => false, 'message' => "Cannot delete category with {$menu_count} menu items. Please move or delete the menu items first."];
        }
        
        // Delete category
        $stmt = $pdo->prepare("DELETE FROM menu_categories WHERE id = ?");
        $stmt->execute([$category_id]);
        
        if ($stmt->rowCount() > 0) {
            logActivity('category_deleted', $_SESSION['user_id'], getRealIPAddress(), [
                'category_id' => $category_id
            ]);
            return ['success' => true, 'message' => 'Category deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Category not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting category: ' . $e->getMessage()];
    }
}

function toggleCategoryStatus($pdo, $category_id) {
    try {
        $stmt = $pdo->prepare("UPDATE menu_categories SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$category_id]);
        
        if ($stmt->rowCount() > 0) {
            // Get new status
            $stmt = $pdo->prepare("SELECT is_active FROM menu_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $new_status = $stmt->fetchColumn();
            
            return ['success' => true, 'message' => 'Category status updated', 'new_status' => $new_status];
        } else {
            return ['success' => false, 'message' => 'Category not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

function updateSortOrder($pdo, $categories) {
    try {
        $pdo->beginTransaction();
        
        foreach ($categories as $index => $category_id) {
            $stmt = $pdo->prepare("UPDATE menu_categories SET sort_order = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$index + 1, $category_id]);
        }
        
        $pdo->commit();
        
        logActivity('categories_reordered', $_SESSION['user_id'], getRealIPAddress(), [
            'category_count' => count($categories)
        ]);
        
        return ['success' => true, 'message' => 'Category order updated successfully'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error updating sort order: ' . $e->getMessage()];
    }
}

function getCategory($pdo, $category_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM menu_categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            return ['success' => true, 'data' => $category];
        } else {
            return ['success' => false, 'message' => 'Category not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching category: ' . $e->getMessage()];
    }
}

// Get all categories
try {
    $categories_sql = "
        SELECT c.*, 
               COUNT(m.id) as menu_count,
               COUNT(CASE WHEN m.is_available = 1 THEN 1 END) as available_menus
        FROM menu_categories c
        LEFT JOIN menus m ON c.id = m.category_id
        GROUP BY c.id, c.name, c.name_thai, c.description, c.image_url, c.sort_order, c.is_active, c.created_at, c.updated_at
        ORDER BY c.sort_order ASC, c.name ASC
    ";
    $stmt = $pdo->prepare($categories_sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_categories,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_categories,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_categories
        FROM menu_categories
    ";
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $categories = [];
    $stats = ['total_categories' => 0, 'active_categories' => 0, 'inactive_categories' => 0];
    error_log("Categories page error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Categories - Krua Thai Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
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
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
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

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-medium);
        }

        .sidebar-header {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .logo-image {
            max-width: 80px;
            max-height: 80px;
            width: auto;
            height: auto;
            object-fit: contain;
            filter: brightness(1.1) contrast(1.2);
            transition: transform 0.3s ease;
        }

        .logo-image:hover {
            transform: scale(1.05);
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .sidebar-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            cursor: pointer;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--white);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: var(--white);
            font-weight: 600;
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
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

        /* Categories Container */
        .categories-container {
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

        /* Category List */
        .category-list {
            padding: 1rem;
        }

        .category-item {
            background: var(--white);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
            cursor: grab;
            position: relative;
        }

        .category-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            border-color: var(--curry);
        }

        .category-item:active {
            cursor: grabbing;
        }

        .category-item.sortable-drag {
            opacity: 0.5;
            transform: rotate(5deg);
        }

        .category-item.sortable-ghost {
            opacity: 0.3;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .category-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .category-info .thai-name {
            font-size: 1rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .category-info .description {
            font-size: 0.9rem;
            color: var(--text-gray);
            line-height: 1.4;
        }

        .category-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .category-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .category-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--text-gray);
        }

        .category-stats span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .drag-handle {
            position: absolute;
            left: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            font-size: 1.2rem;
            cursor: grab;
        }

        .drag-handle:hover {
            color: var(--curry);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-inactive {
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
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
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

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .required {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-control.error {
            border-color: var(--danger);
            background-color: #fff5f5;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .form-help {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 0.25rem;
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

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 2rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 2rem;
            right: 2rem;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .toast {
            background: var(--white);
            border-left: 4px solid var(--curry);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-medium);
            padding: 1rem;
            margin-bottom: 0.5rem;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        .toast.warning {
            border-left-color: var(--warning);
        }

        .toast-icon {
            font-size: 1.2rem;
            margin-top: 0.2rem;
        }

        .toast.success .toast-icon {
            color: var(--success);
        }

        .toast.error .toast-icon {
            color: var(--danger);
        }

        .toast.warning .toast-icon {
            color: var(--warning);
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-dark);
        }

        .toast-message {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--text-gray);
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0;
            margin-left: auto;
        }

        .toast-close:hover {
            color: var(--text-dark);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 100%;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .category-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .modal-content {
                width: 95%;
                margin: 1rem;
            }

            .toast-container {
                top: 1rem;
                right: 1rem;
                left: 1rem;
            }

            .toast {
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .category-actions {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .btn-sm {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
            }

            .category-stats {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
  <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../assets/image/LOGO_White Trans.png" 
                         alt="Krua Thai Logo" 
                         class="logo-image"
                         loading="lazy">
                </div>
                <div class="sidebar-title">Krua Thai</div>
                <div class="sidebar-subtitle">Admin Panel</div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="orders.php" class="nav-item">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                    <a href="menus.php" class="nav-item active">
                        <i class="nav-icon fas fa-utensils"></i>
                        <span>Menus</span>
                    </a>
                    <a href="subscriptions.php" class="nav-item">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span>Subscriptions</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="users.php" class="nav-item">
                        <i class="nav-icon fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="inventory.php" class="nav-item">
                        <i class="nav-icon fas fa-boxes"></i>
                        <span>Inventory</span>
                    </a>
                    <a href="delivery-zones.php" class="nav-item">
                        <i class="nav-icon fas fa-map-marked-alt"></i>
                        <span>Delivery Zones</span>
                    </a>
                    <a href="reviews.php" class="nav-item">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="complaints.php" class="nav-item">
                        <i class="nav-icon fas fa-exclamation-triangle"></i>
                        <span>Complaints</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Financial</div>
                    <a href="payments.php" class="nav-item">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-item">
                        <i class="nav-icon fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="logout.php" class="nav-item" onclick="logout()" style="color: rgba(255, 255, 255, 0.9);">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">Menu Categories</h1>
                        <p class="page-subtitle">Manage and organize your menu categories with drag-and-drop sorting</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="refreshCategories()">
                            <i class="fas fa-refresh"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i>
                            Add Category
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-tags"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_categories']) ?></div>
                    <div class="stat-label">Total Categories</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['active_categories']) ?></div>
                    <div class="stat-label">Active Categories</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['inactive_categories']) ?></div>
                    <div class="stat-label">Inactive Categories</div>
                </div>
            </div>

            <!-- Categories Container -->
            <div class="categories-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-grip-vertical" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Categories (Drag to Reorder)
                    </div>
                    <div class="table-actions">
                        <span class="form-help">Drag and drop to change the order</span>
                    </div>
                </div>

                <div class="category-list" id="categoryList">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item" data-category-id="<?= htmlspecialchars($category['id']) ?>">
                                <div class="drag-handle">
                                    <i class="fas fa-grip-vertical"></i>
                                </div>
                                
                                <div class="category-header">
                                    <div class="category-info">
                                        <h3><?= htmlspecialchars($category['name']) ?></h3>
                                        <?php if ($category['name_thai']): ?>
                                            <div class="thai-name"><?= htmlspecialchars($category['name_thai']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($category['description']): ?>
                                            <div class="description"><?= htmlspecialchars($category['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="category-actions">
                                        <label class="toggle-switch">
                                            <input type="checkbox" 
                                                   <?= $category['is_active'] ? 'checked' : '' ?>
                                                   onchange="toggleCategoryStatus('<?= $category['id'] ?>')">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        
                                        <button class="btn btn-info btn-sm btn-icon" 
                                                onclick="editCategory('<?= $category['id'] ?>')"
                                                title="Edit Category">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <button class="btn btn-danger btn-sm btn-icon" 
                                                onclick="deleteCategory('<?= $category['id'] ?>', '<?= htmlspecialchars($category['name']) ?>')"
                                                title="Delete Category">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="category-meta">
                                    <div class="category-stats">
                                        <span>
                                            <i class="fas fa-utensils"></i>
                                            <?= number_format($category['menu_count']) ?> menus
                                        </span>
                                        <span>
                                            <i class="fas fa-check-circle"></i>
                                            <?= number_format($category['available_menus']) ?> available
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar"></i>
                                            Created: <?= date('M j, Y', strtotime($category['created_at'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="status-badge <?= $category['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <h3>No Categories Found</h3>
                            <p>Start by adding your first menu category to organize your dishes.</p>
                            <button class="btn btn-primary" onclick="openAddModal()">
                                <i class="fas fa-plus"></i>
                                Add First Category
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Category Modal -->
    <div class="modal" id="categoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Category</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="categoryForm">
                <div class="modal-body">
                    <input type="hidden" id="categoryId" name="category_id">
                    
                    <div class="form-group">
                        <label class="form-label" for="categoryName">
                            Category Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="categoryName" 
                               name="name" 
                               class="form-control" 
                               placeholder="e.g., Thai Curries"
                               required>
                        <div class="form-help">Enter the category name in English</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="categoryNameThai">
                            Thai Name
                        </label>
                        <input type="text" 
                               id="categoryNameThai" 
                               name="name_thai" 
                               class="form-control" 
                               placeholder="e.g., แกงไทย">
                        <div class="form-help">Optional: Enter the category name in Thai</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="categoryDescription">
                            Description
                        </label>
                        <textarea id="categoryDescription" 
                                  name="description" 
                                  class="form-control" 
                                  placeholder="Brief description of this category..."
                                  rows="3"></textarea>
                        <div class="form-help">Optional: Describe what types of dishes belong in this category</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        <span id="submitText">Add Category</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let sortable;
        let isEditing = false;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeSortable();
            initializeFormValidation();
        });

        // Initialize sortable functionality
        function initializeSortable() {
            const categoryList = document.getElementById('categoryList');
            if (categoryList && categoryList.children.length > 0) {
                sortable = Sortable.create(categoryList, {
                    animation: 150,
                    handle: '.drag-handle',
                    onEnd: function(evt) {
                        updateSortOrder();
                    }
                });
            }
        }

        // Update sort order
        function updateSortOrder() {
            const categories = [];
            const categoryItems = document.querySelectorAll('.category-item');
            
            categoryItems.forEach(item => {
                categories.push(item.dataset.categoryId);
            });

            fetch('categories.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_sort_order',
                    categories: JSON.stringify(categories)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Order Updated', 'Category order has been updated successfully.');
                } else {
                    showToast('error', 'Update Failed', data.message || 'Failed to update category order.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An error occurred while updating the order.');
            });
        }

        // Open add modal
        function openAddModal() {
            isEditing = false;
            document.getElementById('modalTitle').textContent = 'Add New Category';
            document.getElementById('submitText').textContent = 'Add Category';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryModal').classList.add('show');
        }

        // Edit category
        function editCategory(categoryId) {
            isEditing = true;
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('submitText').textContent = 'Update Category';
            
            // Fetch category data
            fetch('categories.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_category',
                    category_id: categoryId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const category = data.data;
                    document.getElementById('categoryId').value = category.id;
                    document.getElementById('categoryName').value = category.name;
                    document.getElementById('categoryNameThai').value = category.name_thai || '';
                    document.getElementById('categoryDescription').value = category.description || '';
                    document.getElementById('categoryModal').classList.add('show');
                } else {
                    showToast('error', 'Error', data.message || 'Failed to fetch category data.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An error occurred while fetching category data.');
            });
        }

        // Delete category
        function deleteCategory(categoryId, categoryName) {
            if (!confirm(`Are you sure you want to delete "${categoryName}"? This action cannot be undone.`)) {
                return;
            }

            fetch('categories.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'delete_category',
                    category_id: categoryId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Category Deleted', data.message);
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('error', 'Delete Failed', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An error occurred while deleting the category.');
            });
        }

        // Toggle category status
        function toggleCategoryStatus(categoryId) {
            fetch('categories.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'toggle_status',
                    category_id: categoryId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Status Updated', 'Category status has been updated.');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('error', 'Update Failed', data.message);
                    // Revert toggle
                    const checkbox = event.target;
                    checkbox.checked = !checkbox.checked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An error occurred while updating the status.');
                // Revert toggle
                const checkbox = event.target;
                checkbox.checked = !checkbox.checked;
            });
        }

        // Close modal
        function closeModal() {
            document.getElementById('categoryModal').classList.remove('show');
            document.getElementById('categoryForm').reset();
        }

        // Refresh categories
        function refreshCategories() {
            location.reload();
        }

        // Initialize form validation
        function initializeFormValidation() {
            const form = document.getElementById('categoryForm');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const action = isEditing ? 'edit_category' : 'add_category';
                formData.append('action', action);

                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;

                fetch('categories.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', isEditing ? 'Category Updated' : 'Category Added', data.message);
                        closeModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast('error', 'Operation Failed', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'Error', 'An error occurred while processing the request.');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        }

        // Show toast notification
        function showToast(type, title, message) {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-times-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.id = toastId;
            toast.innerHTML = `
                <i class="toast-icon ${icons[type] || icons.info}"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="removeToast('${toastId}')">&times;</button>
            `;

            toastContainer.appendChild(toast);

            // Auto remove after 5 seconds
            setTimeout(() => {
                removeToast(toastId);
            }, 5000);
        }

        // Remove toast
        function removeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('categoryModal');
            if (e.target === modal) {
                closeModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modal
            if (e.key === 'Escape') {
                closeModal();
            }
            
            // Ctrl+R to refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshCategories();
            }
        });
    </script>
</body>
</html>