<?php
/**
 * Krua Thai - Inventory Management
 * File: admin/inventory.php
 * Description: Complete inventory management system with real-time stock tracking
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_item':
                $result = addInventoryItem($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'update_item':
                $result = updateInventoryItem($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'get_item':
                $result = getInventoryItem($pdo, $_POST['id']);
                echo json_encode($result);
                exit;
                
            case 'delete_item':
                $result = deleteInventoryItem($pdo, $_POST['id']);
                echo json_encode($result);
                exit;
                
            case 'update_stock':
                $result = updateStock($pdo, $_POST['id'], $_POST['quantity'], $_POST['type']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function addInventoryItem($pdo, $data) {
    try {
        $id = generateUUID();
        $stmt = $pdo->prepare("
            INSERT INTO inventory 
            (id, ingredient_name, ingredient_name_thai, category, unit_of_measure, 
             current_stock, minimum_stock, maximum_stock, cost_per_unit, supplier_name, 
             supplier_contact, expiry_date, storage_location, storage_temperature) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id, 
            $data['ingredient_name'], 
            $data['ingredient_name_thai'] ?: null, 
            $data['category'] ?: null,
            $data['unit_of_measure'], 
            $data['current_stock'], 
            $data['minimum_stock'],
            $data['maximum_stock'] ?: null, 
            $data['cost_per_unit'] ?: null, 
            $data['supplier_name'] ?: null, 
            $data['supplier_contact'] ?: null,
            $data['expiry_date'] ?: null, 
            $data['storage_location'] ?: null, 
            $data['storage_temperature']
        ]);
        
        return ['success' => true, 'message' => 'Inventory item added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error adding item: ' . $e->getMessage()];
    }
}

function updateInventoryItem($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE inventory SET 
                ingredient_name = ?, ingredient_name_thai = ?, category = ?,
                unit_of_measure = ?, current_stock = ?, minimum_stock = ?,
                maximum_stock = ?, cost_per_unit = ?, supplier_name = ?,
                supplier_contact = ?, expiry_date = ?, storage_location = ?,
                storage_temperature = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['ingredient_name'], 
            $data['ingredient_name_thai'] ?: null, 
            $data['category'] ?: null,
            $data['unit_of_measure'], 
            $data['current_stock'], 
            $data['minimum_stock'],
            $data['maximum_stock'] ?: null, 
            $data['cost_per_unit'] ?: null,
            $data['supplier_name'] ?: null, 
            $data['supplier_contact'] ?: null,
            $data['expiry_date'] ?: null, 
            $data['storage_location'] ?: null,
            $data['storage_temperature'], 
            $data['id']
        ]);
        
        return ['success' => true, 'message' => 'Inventory item updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating item: ' . $e->getMessage()];
    }
}

function getInventoryItem($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            return ['success' => true, 'data' => $item];
        } else {
            return ['success' => false, 'message' => 'Item not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching item: ' . $e->getMessage()];
    }
}

function deleteInventoryItem($pdo, $id) {
    try {
        $stmt = $pdo->prepare("UPDATE inventory SET is_active = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true, 'message' => 'Inventory item deactivated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deactivating item: ' . $e->getMessage()];
    }
}

function updateStock($pdo, $id, $quantity, $type) {
    try {
        $operator = ($type === 'add') ? '+' : '-';
        $stmt = $pdo->prepare("
            UPDATE inventory 
            SET current_stock = current_stock $operator ?, 
                last_restocked_date = CURDATE(),
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$quantity, $id]);
        
        return ['success' => true, 'message' => 'Stock updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating stock: ' . $e->getMessage()];
    }
}

// Use generateUUID from functions.php

// Fetch Data
try {
    // Filters
    $categoryFilter = $_GET['category'] ?? '';
    $stockFilter = $_GET['stock'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Main inventory query
    $whereConditions = ['is_active = 1'];
    $params = [];
    
    if ($categoryFilter) {
        $whereConditions[] = 'category = ?';
        $params[] = $categoryFilter;
    }
    
    if ($stockFilter === 'low') {
        $whereConditions[] = 'current_stock <= minimum_stock';
    } elseif ($stockFilter === 'out') {
        $whereConditions[] = 'current_stock = 0';
    }
    
    if ($search) {
        $whereConditions[] = '(ingredient_name LIKE ? OR ingredient_name_thai LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT * FROM inventory 
        WHERE $whereClause 
        ORDER BY ingredient_name ASC
    ");
    $stmt->execute($params);
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_items,
            SUM(CASE WHEN current_stock <= minimum_stock THEN 1 ELSE 0 END) as low_stock_items,
            SUM(CASE WHEN current_stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(current_stock * COALESCE(cost_per_unit, 0)) as total_value
        FROM inventory WHERE is_active = 1
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Categories
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count 
        FROM inventory 
        WHERE is_active = 1 AND category IS NOT NULL 
        GROUP BY category 
        ORDER BY count DESC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $inventoryItems = [];
    $stats = ['total_items' => 0, 'low_stock_items' => 0, 'out_of_stock' => 0, 'total_value' => 0];
    $categories = [];
    error_log("Inventory error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Krua Thai Admin</title>
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

        /* Page Header */
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

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Filters */
        .filters-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .filters-row {
            display: grid;
            grid-template-columns: 1fr 200px 200px 150px;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        /* Inventory Table */
        .inventory-section {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .table th {
            background: var(--cream);
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        /* Status Badges */
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

        .status-low {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-out {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-good {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        /* Stock Level Indicator */
        .stock-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stock-bar {
            width: 60px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .stock-fill {
            height: 100%;
            transition: var(--transition);
        }

        .stock-fill.good {
            background: #27ae60;
        }

        .stock-fill.warning {
            background: #f39c12;
        }

        .stock-fill.danger {
            background: #e74c3c;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-row.full {
            grid-template-columns: 1fr;
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
            .sidebar {
                width: 260px;
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

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
                grid-template-columns: 1fr;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .mobile-menu-btn {
                display: block !important;
            }
        }

        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--curry);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 50%;
            box-shadow: var(--shadow-medium);
            cursor: pointer;
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
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

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
                    <a href="menus.php" class="nav-item">
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
                    <a href="inventory.php" class="nav-item active">
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
                    <a href="../logout.php" class="nav-item" onclick="logout()" style="color: rgba(255, 255, 255, 0.9);">
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
                        <h1 class="page-title">
                            <i class="fas fa-boxes" style="margin-right: 0.5rem; color: var(--curry);"></i>
                            Inventory Management
                        </h1>
                        <p class="page-subtitle">Monitor and manage ingredient stock levels</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="exportInventory()">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i>
                            Add Item
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_items']) ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['low_stock_items']) ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['out_of_stock']) ?></div>
                    <div class="stat-label">Out of Stock</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?= number_format($stats['total_value'], 0) ?></div>
                    <div class="stat-label">Total Value</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filters-row">
                        <div class="form-group">
                            <label class="form-label">Search Items</label>
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search by name..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                        <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?> (<?= $cat['count'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Stock Level</label>
                            <select name="stock" class="form-control">
                                <option value="">All Stock Levels</option>
                                <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Low Stock</option>
                                <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Inventory Table -->
            <div class="inventory-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-list" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Inventory Items
                    </h3>
                    <div style="display: flex; gap: 1rem;">
                        <span style="color: var(--text-gray); font-size: 0.9rem;">
                            Showing <?= count($inventoryItems) ?> items
                        </span>
                    </div>
                </div>
                
                <div class="table-container">
                    <?php if (!empty($inventoryItems)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Stock Level</th>
                                <th>Unit</th>
                                <th>Cost/Unit</th>
                                <th>Supplier</th>
                                <th>Expiry Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventoryItems as $item): ?>
                            <?php 
                                $stockPercentage = $item['minimum_stock'] > 0 ? 
                                    ($item['current_stock'] / $item['minimum_stock']) * 100 : 100;
                                $stockStatus = $item['current_stock'] == 0 ? 'out' : 
                                    ($item['current_stock'] <= $item['minimum_stock'] ? 'low' : 'good');
                            ?>
                            <tr data-id="<?= $item['id'] ?>">
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($item['ingredient_name']) ?></strong>
                                        <?php if ($item['ingredient_name_thai']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            <?= htmlspecialchars($item['ingredient_name_thai']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="background: var(--cream); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                        <?= htmlspecialchars($item['category'] ?: 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <strong style="color: <?= $stockStatus === 'out' ? '#e74c3c' : ($stockStatus === 'low' ? '#f39c12' : '#27ae60') ?>">
                                            <?= number_format($item['current_stock'], 1) ?>
                                        </strong>
                                        <button class="btn btn-secondary btn-sm" onclick="openStockModal('<?= $item['id'] ?>', '<?= htmlspecialchars($item['ingredient_name']) ?>', <?= $item['current_stock'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-indicator">
                                        <span class="status-badge status-<?= $stockStatus ?>">
                                            <?= ucfirst($stockStatus) ?>
                                        </span>
                                        <div class="stock-bar">
                                            <div class="stock-fill <?= $stockStatus ?>" 
                                                 style="width: <?= min(100, max(0, $stockPercentage)) ?>%"></div>
                                        </div>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray); margin-top: 0.25rem;">
                                        Min: <?= number_format($item['minimum_stock'], 1) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
                                <td>
                                    <?php if ($item['cost_per_unit']): ?>
                                        ₿<?= number_format($item['cost_per_unit'], 2) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['supplier_name']): ?>
                                        <div><?= htmlspecialchars($item['supplier_name']) ?></div>
                                        <?php if ($item['supplier_contact']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            <?= htmlspecialchars($item['supplier_contact']) ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['expiry_date']): ?>
                                        <?php 
                                            $expiryDate = new DateTime($item['expiry_date']);
                                            $today = new DateTime();
                                            $daysUntilExpiry = $today->diff($expiryDate)->days;
                                            $isExpired = $expiryDate < $today;
                                            $isExpiringSoon = $daysUntilExpiry <= 7 && !$isExpired;
                                        ?>
                                        <div style="color: <?= $isExpired ? '#e74c3c' : ($isExpiringSoon ? '#f39c12' : 'inherit') ?>;">
                                            <?= $expiryDate->format('M d, Y') ?>
                                            <?php if ($isExpired): ?>
                                                <div style="font-size: 0.8rem;">Expired</div>
                                            <?php elseif ($isExpiringSoon): ?>
                                                <div style="font-size: 0.8rem;"><?= $daysUntilExpiry ?> days left</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn btn-icon btn-secondary btn-sm" 
                                                onclick="editItem('<?= $item['id'] ?>')" 
                                                title="Edit Item">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-icon btn-danger btn-sm" 
                                                onclick="deleteItem('<?= $item['id'] ?>', '<?= htmlspecialchars($item['ingredient_name']) ?>')" 
                                                title="Delete Item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-gray);">
                        <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3>No inventory items found</h3>
                        <p>Start by adding your first ingredient to the inventory</p>
                        <button class="btn btn-primary" onclick="openAddModal()" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i>
                            Add First Item
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Item Modal -->
    <div class="modal" id="itemModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Item</h3>
                <button class="modal-close" onclick="closeModal('itemModal')">&times;</button>
            </div>
            <form id="itemForm">
                <div class="modal-body">
                    <input type="hidden" id="itemId" name="id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Ingredient Name *</label>
                            <input type="text" name="ingredient_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Thai Name</label>
                            <input type="text" name="ingredient_name_thai" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" 
                                   placeholder="e.g., Vegetables, Spices, Meat">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unit of Measure *</label>
                            <select name="unit_of_measure" class="form-control" required>
                                <option value="">Select Unit</option>
                                <option value="kg">Kilogram (kg)</option>
                                <option value="g">Gram (g)</option>
                                <option value="L">Liter (L)</option>
                                <option value="ml">Milliliter (ml)</option>
                                <option value="pcs">Pieces (pcs)</option>
                                <option value="packs">Packs</option>
                                <option value="bottles">Bottles</option>
                                <option value="cans">Cans</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Current Stock *</label>
                            <input type="number" name="current_stock" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Minimum Stock *</label>
                            <input type="number" name="minimum_stock" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Maximum Stock</label>
                            <input type="number" name="maximum_stock" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cost per Unit</label>
                            <input type="number" name="cost_per_unit" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" name="supplier_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Supplier Contact</label>
                            <input type="text" name="supplier_contact" class="form-control" 
                                   placeholder="Phone or email">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Storage Temperature</label>
                            <select name="storage_temperature" class="form-control">
                                <option value="room_temp">Room Temperature</option>
                                <option value="refrigerated">Refrigerated</option>
                                <option value="frozen">Frozen</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label">Storage Location</label>
                            <input type="text" name="storage_location" class="form-control" 
                                   placeholder="e.g., Pantry A, Fridge 1, Freezer B">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('itemModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Update Modal -->
    <div class="modal" id="stockModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Update Stock</h3>
                <button class="modal-close" onclick="closeModal('stockModal')">&times;</button>
            </div>
            <form id="stockForm">
                <div class="modal-body">
                    <input type="hidden" id="stockItemId">
                    
                    <div class="form-group">
                        <label class="form-label">Item</label>
                        <input type="text" id="stockItemName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Current Stock</label>
                        <input type="number" id="stockCurrentAmount" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Action</label>
                        <select id="stockAction" class="form-control">
                            <option value="add">Add to Stock</option>
                            <option value="remove">Remove from Stock</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="stockQuantity" class="form-control" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('stockModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let isEditing = false;
        let currentEditingItem = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
        });

        // Initialize event listeners
        function initializeEventListeners() {
            // Form submissions
            document.getElementById('itemForm').addEventListener('submit', handleItemSubmit);
            document.getElementById('stockForm').addEventListener('submit', handleStockSubmit);
            
            // Auto-submit filters
            document.querySelectorAll('#filtersForm select').forEach(element => {
                element.addEventListener('change', function() {
                    document.getElementById('filtersForm').submit();
                });
            });
            
            // Search with debounce
            let searchTimeout;
            document.querySelector('input[name="search"]').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('filtersForm').submit();
                }, 500);
            });
        }

        // Open add modal
        function openAddModal() {
            isEditing = false;
            currentEditingItem = null;
            document.getElementById('modalTitle').textContent = 'Add New Item';
            document.getElementById('itemForm').reset();
            document.getElementById('itemId').value = '';
            document.getElementById('itemModal').classList.add('show');
        }

        // Edit item
        function editItem(itemId) {
            isEditing = true;
            currentEditingItem = itemId;
            
            document.getElementById('modalTitle').textContent = 'Edit Item';
            document.getElementById('itemId').value = itemId;
            
            // Fetch item data
            const formData = new FormData();
            formData.append('action', 'get_item');
            formData.append('id', itemId);
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = data.data;
                    populateForm(item);
                    document.getElementById('itemModal').classList.add('show');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error fetching item data', 'error');
            });
        }

        // Populate form with item data
        function populateForm(item) {
            document.querySelector('input[name="ingredient_name"]').value = item.ingredient_name || '';
            document.querySelector('input[name="ingredient_name_thai"]').value = item.ingredient_name_thai || '';
            document.querySelector('input[name="category"]').value = item.category || '';
            document.querySelector('select[name="unit_of_measure"]').value = item.unit_of_measure || '';
            document.querySelector('input[name="current_stock"]').value = item.current_stock || '';
            document.querySelector('input[name="minimum_stock"]').value = item.minimum_stock || '';
            document.querySelector('input[name="maximum_stock"]').value = item.maximum_stock || '';
            document.querySelector('input[name="cost_per_unit"]').value = item.cost_per_unit || '';
            document.querySelector('input[name="supplier_name"]').value = item.supplier_name || '';
            document.querySelector('input[name="supplier_contact"]').value = item.supplier_contact || '';
            document.querySelector('input[name="expiry_date"]').value = item.expiry_date || '';
            document.querySelector('select[name="storage_temperature"]').value = item.storage_temperature || 'room_temp';
            document.querySelector('input[name="storage_location"]').value = item.storage_location || '';
        }

        // Delete item
        function deleteItem(itemId, itemName) {
            if (!confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('id', itemId);
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting item', 'error');
            });
        }

        // Open stock modal
        function openStockModal(itemId, itemName, currentStock) {
            document.getElementById('stockItemId').value = itemId;
            document.getElementById('stockItemName').value = itemName;
            document.getElementById('stockCurrentAmount').value = currentStock;
            document.getElementById('stockQuantity').value = '';
            document.getElementById('stockModal').classList.add('show');
        }

        // Handle item form submission
        function handleItemSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const action = isEditing ? 'update_item' : 'add_item';
            formData.append('action', action);
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('itemModal');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error saving item', 'error');
            });
        }

        // Handle stock form submission
        function handleStockSubmit(e) {
            e.preventDefault();
            
            const itemId = document.getElementById('stockItemId').value;
            const quantity = document.getElementById('stockQuantity').value;
            const action = document.getElementById('stockAction').value;
            
            const formData = new FormData();
            formData.append('action', 'update_stock');
            formData.append('id', itemId);
            formData.append('quantity', quantity);
            formData.append('type', action);
            
            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('stockModal');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating stock', 'error');
            });
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Export inventory
        function exportInventory() {
            showToast('Exporting inventory data...', 'info');
            
            // Create CSV export
            const rows = [['Ingredient', 'Thai Name', 'Category', 'Current Stock', 'Minimum Stock', 'Unit', 'Cost/Unit', 'Supplier']];
            
            document.querySelectorAll('.table tbody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 1) {
                    const ingredient = cells[0].querySelector('strong').textContent.trim();
                    const ingredientThai = cells[0].querySelector('div')?.textContent.trim() || '';
                    const category = cells[1].textContent.trim();
                    const currentStock = cells[2].querySelector('strong').textContent.trim();
                    const unit = cells[4].textContent.trim();
                    const cost = cells[5].textContent.trim();
                    const supplier = cells[6].querySelector('div')?.textContent.trim() || cells[6].textContent.trim();
                    
                    const rowData = [ingredient, ingredientThai, category, currentStock, '', unit, cost, supplier];
                    rows.push(rowData);
                }
            });
            
            const csv = rows.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `inventory_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            toast.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
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

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddModal();
            }
            
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        // Click outside modal to close
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Auto-refresh every 5 minutes
        setInterval(function() {
            // Check for low stock items and show notifications
            const lowStockCount = <?= $stats['low_stock_items'] ?>;
            const outOfStockCount = <?= $stats['out_of_stock'] ?>;
            
            if (lowStockCount > 0 || outOfStockCount > 0) {
                console.log(`Alert: ${lowStockCount} low stock, ${outOfStockCount} out of stock items`);
            }
        }, 300000);

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Inventory page loaded in ${Math.round(loadTime)}ms`);
        });

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Add tooltips to buttons
            document.querySelectorAll('[title]').forEach(element => {
                element.addEventListener('mouseenter', function() {
                    // Simple tooltip implementation can be added here
                });
            });
        });

        // Form validation
        function validateForm(formElement) {
            const requiredFields = formElement.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#e74c3c';
                    isValid = false;
                } else {
                    field.style.borderColor = '';
                }
            });
            
            return isValid;
        }

        // Add form validation to submit handlers
        document.getElementById('itemForm').addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showToast('Please fill in all required fields', 'error');
            }
        });

        // Real-time stock level updates
        function updateStockDisplay() {
            // This could be expanded to update stock levels in real-time
            // via WebSocket or periodic AJAX calls
        }

        // Initialize inventory management system
        console.log('Krua Thai Inventory Management System initialized successfully');
        
        // Show welcome message for first-time users
        if (document.querySelectorAll('.table tbody tr').length === 0) {
            setTimeout(() => {
                showToast('Welcome! Start by adding your first inventory item.', 'info');
            }, 1000);
        }

        // Auto-save draft functionality for forms
        function autoSaveDraft() {
            const formData = new FormData(document.getElementById('itemForm'));
            const draftData = {};
            
            for (let [key, value] of formData.entries()) {
                if (value.trim()) {
                    draftData[key] = value;
                }
            }
            
            if (Object.keys(draftData).length > 0) {
                localStorage.setItem('inventory_draft', JSON.stringify(draftData));
            }
        }

        // Load draft data
        function loadDraft() {
            const draft = localStorage.getItem('inventory_draft');
            if (draft) {
                try {
                    const draftData = JSON.parse(draft);
                    Object.keys(draftData).forEach(key => {
                        const field = document.querySelector(`[name="${key}"]`);
                        if (field) {
                            field.value = draftData[key];
                        }
                    });
                    showToast('Draft data loaded', 'info');
                } catch (e) {
                    console.error('Error loading draft:', e);
                }
            }
        }

        // Clear draft when form is submitted successfully
        function clearDraft() {
            localStorage.removeItem('inventory_draft');
        }

        // Enhanced error handling
        window.addEventListener('error', function(e) {
            console.error('JavaScript error:', e.error);
            showToast('An unexpected error occurred. Please refresh the page.', 'error');
        });

        // Prevent form submission on Enter key in search field
        document.querySelector('input[name="search"]').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filtersForm').submit();
            }
        });

        // Add loading states to buttons
        function setButtonLoading(button, loading = true) {
            if (loading) {
                button.disabled = true;
                const originalText = button.innerHTML;
                button.setAttribute('data-original-text', originalText);
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            } else {
                button.disabled = false;
                const originalText = button.getAttribute('data-original-text');
                if (originalText) {
                    button.innerHTML = originalText;
                }
            }
        }

        // Enhanced AJAX error handling
        function handleAjaxError(error, context = '') {
            console.error(`AJAX Error ${context}:`, error);
            
            if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                showToast('Network error. Please check your connection.', 'error');
            } else {
                showToast(`Error ${context}. Please try again.`, 'error');
            }
        }

        // Data validation helpers
        function isValidNumber(value, min = 0) {
            const num = parseFloat(value);
            return !isNaN(num) && num >= min;
        }

        function isValidDate(dateString) {
            const date = new Date(dateString);
            return date instanceof Date && !isNaN(date);
        }

        // Initialize all components
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🍜 Krua Thai Inventory Management System Ready!');
        });
    </script>
</body>
</html>