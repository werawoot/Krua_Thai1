<?php
/**
 * Krua Thai - Simple ZIP Code Management System
 * File: admin/delivery-zones.php
 * Features: Simple add/remove ZIP codes for delivery zones
 * Status: SIMPLIFIED ✅
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// ======================================================================
// ZIP CODE MANAGEMENT FUNCTIONS
// ======================================================================

function addZipCodeToZone($pdo, $zoneId, $zipCode) {
    try {
        // Validate zip code format (5 digits)
        if (!preg_match('/^\d{5}$/', $zipCode)) {
            return ['success' => false, 'message' => 'ZIP code must be exactly 5 digits'];
        }

        // Check if ZIP code already exists in any zone
        $stmt = $pdo->prepare("
            SELECT zone_name 
            FROM delivery_zones 
            WHERE JSON_CONTAINS(zip_codes, JSON_QUOTE(?))
        ");
        $stmt->execute([$zipCode]);
        $existingZone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingZone) {
            return ['success' => false, 'message' => "ZIP code {$zipCode} already exists in zone: {$existingZone['zone_name']}"];
        }

        // Get current zip codes
        $stmt = $pdo->prepare("SELECT zip_codes FROM delivery_zones WHERE id = ?");
        $stmt->execute([$zoneId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['success' => false, 'message' => 'Zone not found'];
        }

        $currentZipCodes = json_decode($result['zip_codes'], true) ?? [];
        
        // Check if already exists in this zone
        if (in_array($zipCode, $currentZipCodes)) {
            return ['success' => false, 'message' => 'ZIP code already exists in this zone'];
        }

        // Add new zip code
        $currentZipCodes[] = $zipCode;
        sort($currentZipCodes); // Keep sorted
        
        // Update database
        $stmt = $pdo->prepare("
            UPDATE delivery_zones 
            SET zip_codes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([json_encode($currentZipCodes), $zoneId]);

        return ['success' => true, 'message' => "ZIP code {$zipCode} added successfully"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function removeZipCodeFromZone($pdo, $zoneId, $zipCode) {
    try {
        // Get current zip codes
        $stmt = $pdo->prepare("SELECT zone_name, zip_codes FROM delivery_zones WHERE id = ?");
        $stmt->execute([$zoneId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['success' => false, 'message' => 'Zone not found'];
        }

        $currentZipCodes = json_decode($result['zip_codes'], true) ?? [];
        
        // Check if zip code exists
        if (!in_array($zipCode, $currentZipCodes)) {
            return ['success' => false, 'message' => 'ZIP code not found in this zone'];
        }

        // Check if this is the last zip code (don't allow empty zones)
        if (count($currentZipCodes) <= 1) {
            return ['success' => false, 'message' => 'Cannot remove the last ZIP code from a zone'];
        }

        // Remove zip code
        $currentZipCodes = array_values(array_filter($currentZipCodes, function($code) use ($zipCode) {
            return $code !== $zipCode;
        }));
        
        // Update database
        $stmt = $pdo->prepare("
            UPDATE delivery_zones 
            SET zip_codes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([json_encode($currentZipCodes), $zoneId]);

        return ['success' => true, 'message' => "ZIP code {$zipCode} removed successfully"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function createSimpleZone($pdo, $zoneName, $initialZipCodes) {
    try {
        // Validate zone name
        if (empty(trim($zoneName))) {
            return ['success' => false, 'message' => 'Zone name is required'];
        }

        // Check if zone name already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM delivery_zones WHERE zone_name = ?");
        $stmt->execute([trim($zoneName)]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Zone name already exists'];
        }

        // Validate and clean zip codes
        $zipCodes = [];
        if (!empty($initialZipCodes)) {
            $codes = preg_split('/[\s,]+/', $initialZipCodes);
            foreach ($codes as $code) {
                $code = trim($code);
                if (!empty($code)) {
                    if (!preg_match('/^\d{5}$/', $code)) {
                        return ['success' => false, 'message' => "Invalid ZIP code format: {$code}"];
                    }
                    $zipCodes[] = $code;
                }
            }
        }

        if (empty($zipCodes)) {
            return ['success' => false, 'message' => 'At least one valid ZIP code is required'];
        }

        // Remove duplicates and sort
        $zipCodes = array_values(array_unique($zipCodes));
        sort($zipCodes);

        // Check if any zip codes already exist
        foreach ($zipCodes as $zipCode) {
            $stmt = $pdo->prepare("
                SELECT zone_name 
                FROM delivery_zones 
                WHERE JSON_CONTAINS(zip_codes, JSON_QUOTE(?))
            ");
            $stmt->execute([$zipCode]);
            $existingZone = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingZone) {
                return ['success' => false, 'message' => "ZIP code {$zipCode} already exists in zone: {$existingZone['zone_name']}"];
            }
        }

        // Create new zone with minimal data
        $stmt = $pdo->prepare("
            INSERT INTO delivery_zones (
                id, zone_name, zip_codes, delivery_fee, 
                free_delivery_minimum, estimated_delivery_time, 
                max_orders_per_day, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            generateUUID(),
            trim($zoneName),
            json_encode($zipCodes),
            5.99, // Default delivery fee
            50.00, // Default free delivery minimum
            45, // Default delivery time (45 minutes)
            100, // Default max orders per day
            1 // Active by default
        ]);

        return ['success' => true, 'message' => "Zone '{$zoneName}' created with " . count($zipCodes) . " ZIP codes"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteZone($pdo, $zoneId) {
    try {
        // Check if zone has active orders
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_orders,
                   (SELECT zone_name FROM delivery_zones WHERE id = ?) as zone_name
            FROM orders o 
            WHERE JSON_CONTAINS(
                (SELECT zip_codes FROM delivery_zones WHERE id = ?), 
                JSON_QUOTE(SUBSTRING(o.delivery_address, -5))
            )
            AND o.status NOT IN ('delivered', 'cancelled')
        ");
        $stmt->execute([$zoneId, $zoneId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['active_orders'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete zone with active orders'];
        }

        // Delete the zone
        $stmt = $pdo->prepare("DELETE FROM delivery_zones WHERE id = ?");
        $stmt->execute([$zoneId]);

        return ['success' => true, 'message' => "Zone deleted successfully"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Generate UUID function (if not already defined)
if (!function_exists('generateUUID')) {
    function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Note: sanitizeInput() function is already defined in includes/functions.php

// AJAX Request Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {
            case 'add_zip_code':
                $result = addZipCodeToZone($pdo, $_POST['zone_id'], $_POST['zip_code']);
                break;
            case 'remove_zip_code':
                $result = removeZipCodeFromZone($pdo, $_POST['zone_id'], $_POST['zip_code']);
                break;
            case 'create_zone':
                $result = createSimpleZone($pdo, $_POST['zone_name'], $_POST['zip_codes']);
                break;
            case 'delete_zone':
                $result = deleteZone($pdo, $_POST['zone_id']);
                break;
            default:
                $result = ['success' => false, 'message' => 'Invalid action'];
        }
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch all zones for display
try {
    $stmt = $pdo->prepare("SELECT * FROM delivery_zones ORDER BY zone_name ASC");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $zones = [];
    $error_message = "Could not fetch delivery zones: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZIP Code Management - Krua Thai Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --cream: #ece8e1; --sage: #adb89d; --brown: #bd9379; --curry: #cf723a; --white: #ffffff;
            --text-dark: #2c3e50; --text-gray: #7f8c8d; --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05); --shadow-medium: 0 8px 24px rgba(0,0,0,0.1);
            --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #27ae60; --warning: #f39c12; --danger: #e74c3c;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Sarabun', sans-serif; 
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%); 
            color: var(--text-dark); 
            line-height: 1.6; 
        }
        .admin-layout { display: flex; min-height: 100vh; }
        
        /* Sidebar Styles */
        .sidebar { 
            width: 280px; 
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%); 
            color: var(--white); 
            position: fixed; 
            height: 100vh; 
            overflow-y: auto; 
            z-index: 1000; 
            box-shadow: var(--shadow-medium);
        }
        .sidebar-header { 
            padding: 2rem; 
            text-align: center; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
        }
        .logo-image { 
            max-width: 80px; 
            max-height: 80px;
            object-fit: contain;
            filter: brightness(1.1) contrast(1.2);
        }
        .sidebar-title { 
            font-size: 1.5rem; 
            font-weight: 700; 
            margin-top: 0.5rem; 
        }
        .sidebar-subtitle { 
            font-size: 0.9rem; 
            opacity: 0.8; 
        }
        .sidebar-nav { padding: 1rem 0; }
        .nav-section { margin-bottom: 1.5rem; }
        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            opacity: 0.7;
        }
        .nav-item { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            padding: 0.75rem 1.5rem; 
            color: rgba(255, 255, 255, 0.9); 
            text-decoration: none; 
            border-left: 3px solid transparent; 
            transition: var(--transition);
        }
        .nav-item:hover, .nav-item.active { 
            background: rgba(255, 255, 255, 0.1); 
            border-left-color: var(--white); 
            color: var(--white);
        }
        .nav-item.active { font-weight: 600; }
        .nav-icon { width: 24px; text-align: center; }
        
        /* Submenu Styles */
        .nav-item-with-submenu {
            position: relative;
        }
        .nav-item-with-submenu .nav-toggle {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }
        .nav-item-with-submenu.expanded .nav-toggle {
            transform: rotate(180deg);
        }
        .nav-submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0, 0, 0, 0.1);
        }
        .nav-submenu.expanded {
            max-height: 300px;
        }
        .nav-subitem {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.5rem 0.5rem 3rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        .nav-subitem:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            text-decoration: none;
        }
        .nav-subitem.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content { 
            margin-left: 280px; 
            flex: 1; 
            padding: 2rem; 
        }
        
        /* Page Header */
        .page-header { 
            background: var(--white); 
            padding: 2rem; 
            border-radius: var(--radius-lg); 
            box-shadow: var(--shadow-soft); 
            margin-bottom: 2rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
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
            font-weight: 600; 
            cursor: pointer; 
            transition: var(--transition);
            text-decoration: none;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-medium); }
        .btn-primary { background: linear-gradient(135deg, var(--curry), #e67e22); color: var(--white); }
        .btn-success { background: linear-gradient(135deg, var(--success), #229954); color: var(--white); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #c0392b); color: var(--white); }
        .btn-secondary { background: #f8f9fa; border: 1px solid var(--border-light); color: var(--text-dark); }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.9rem; }
        
        /* Zone Cards */
        .zones-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); 
            gap: 2rem; 
        }
        .zone-card { 
            background: var(--white); 
            border-radius: var(--radius-md); 
            padding: 2rem; 
            box-shadow: var(--shadow-soft); 
            border-left: 5px solid var(--curry); 
            transition: var(--transition);
        }
        .zone-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }
        .zone-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1.5rem; 
        }
        .zone-title { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: var(--curry);
        }
        .zone-count {
            background: var(--sage);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        /* ZIP Code Management */
        .zip-section { margin-bottom: 2rem; }
        .zip-list { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 0.5rem; 
            margin-bottom: 1rem; 
        }
        .zip-code-tag { 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            background: var(--cream); 
            padding: 0.5rem 0.75rem; 
            border-radius: 20px; 
            font-family: 'Courier New', monospace;
            font-weight: 600;
            border: 1px solid var(--border-light);
        }
        .zip-remove { 
            background: var(--danger); 
            color: var(--white); 
            border: none; 
            border-radius: 50%; 
            width: 20px; 
            height: 20px; 
            font-size: 0.8rem; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .zip-add-form { 
            display: flex; 
            gap: 0.5rem; 
            align-items: center; 
        }
        .zip-input { 
            width: 120px; 
            padding: 0.5rem; 
            border: 1px solid var(--border-light); 
            border-radius: var(--radius-sm); 
            font-family: 'Courier New', monospace;
            text-align: center;
        }
        
        /* Zone Actions */
        .zone-actions { 
            display: flex; 
            gap: 0.5rem; 
            justify-content: flex-end; 
            margin-top: 1.5rem; 
            padding-top: 1.5rem; 
            border-top: 1px solid var(--border-light); 
        }
        
        /* Modal */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0,0,0,0.6); 
            z-index: 2000; 
            align-items: center; 
            justify-content: center; 
            backdrop-filter: blur(4px); 
        }
        .modal.show { display: flex; }
        .modal-content { 
            background: var(--white); 
            border-radius: var(--radius-md); 
            padding: 2rem; 
            max-width: 500px; 
            width: 90%; 
            box-shadow: var(--shadow-medium);
        }
        .modal-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1.5rem; 
        }
        .modal-title { 
            font-size: 1.5rem; 
            font-weight: 600; 
            color: var(--curry);
        }
        .modal-close { 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
            color: var(--text-gray); 
        }
        .form-group { margin-bottom: 1rem; }
        .form-label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 600; 
            color: var(--text-dark);
        }
        .form-control { 
            width: 100%; 
            padding: 0.75rem; 
            border: 1px solid var(--border-light); 
            border-radius: var(--radius-sm); 
            font-family: inherit; 
        }
        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
            .sidebar { transform: translateX(-100%); }
            .zones-grid { grid-template-columns: 1fr; }
            .page-header { 
                flex-direction: column; 
                align-items: flex-start; 
                gap: 1rem; 
            }
        }
        
        /* Utilities */
        .text-center { text-align: center; }
        .text-muted { color: var(--text-gray); }
        .mb-1 { margin-bottom: 0.5rem; }
        .fw-bold { font-weight: 600; }
    </style>
</head>
<body>
    <div class="admin-layout">
         <!-- Enhanced Sidebar -->
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
                <!-- Main Section -->
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
                
            
                <!-- Management Section -->
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
                     <!-- Delivery Section with Submenu -->
                <div class="nav-section">
                    
                    <div class="nav-item-with-submenu" id="deliveryMenu">
                        <div class="nav-item" onclick="toggleDeliveryMenu()">
                            <i class="nav-icon fas fa-truck"></i>
                            <span>Delivery</span>
                            <i class="nav-toggle fas fa-chevron-down"></i>
                        </div>
                        <div class="nav-submenu" id="deliverySubmenu">
                            <a href="delivery-management.php" class="nav-subitem ">
                                <i class="nav-subitem-icon fas fa-route"></i>
                                <span>Route Optimizer</span>
                            </a>
                            <a href="delivery-zones.php" class="nav-subitem  active">
                                <i class="nav-subitem-icon fas fa-map"></i>
                                <span>Delivery Zones</span>
                            </a>
                            <a href="assign-riders.php" class="nav-subitem">
                                <i class="nav-subitem-icon fas fa-user-check"></i>
                                <span>Assign Riders</span>
                            </a>
                
                        </div>
                    </div>
                </div>
                
                    <a href="reviews.php" class="nav-item">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="complaints.php" class="nav-item">
                        <i class="nav-icon fas fa-exclamation-triangle"></i>
                        <span>Complaints</span>
                    </a>
                </div>
                
                <!-- Financial Section -->
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
                
                <!-- System Section -->
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-item">
                        <i class="nav-icon fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="../logout.php" class="nav-item">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-map-marked-alt" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        ZIP Code Management
                    </h1>
                    <p class="page-subtitle">Manage delivery zones by adding or removing ZIP codes</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="showCreateZoneModal()">
                        <i class="fas fa-plus"></i> Create New Zone
                    </button>
                </div>
            </header>
            
            <?php if (empty($zones)): ?>
                <div class="empty-state">
                    <i class="fas fa-map-marked-alt"></i>
                    <h2>No Delivery Zones Found</h2>
                    <p>Create your first delivery zone to start managing ZIP codes for delivery coverage.</p>
                    <button class="btn btn-primary" onclick="showCreateZoneModal()">
                        <i class="fas fa-plus"></i> Create New Zone
                    </button>
                </div>
            <?php else: ?>
                <div class="zones-grid">
                    <?php foreach ($zones as $zone): 
                        $zipCodes = json_decode($zone['zip_codes'] ?? '[]', true) ?? [];
                    ?>
                        <div class="zone-card" data-zone-id="<?= $zone['id'] ?>">
                            <div class="zone-header">
                                <h3 class="zone-title"><?= htmlspecialchars($zone['zone_name']) ?></h3>
                                <span class="zone-count"><?= count($zipCodes) ?> ZIP codes</span>
                            </div>
                            
                            <div class="zip-section">
                                <h4 style="margin-bottom: 1rem; color: var(--text-dark);">
                                    <i class="fas fa-map-pin"></i> ZIP Codes
                                </h4>
                                
                                <?php if (empty($zipCodes)): ?>
                                    <p class="text-muted">No ZIP codes added yet.</p>
                                <?php else: ?>
                                    <div class="zip-list">
                                        <?php foreach ($zipCodes as $zipCode): ?>
                                            <div class="zip-code-tag">
                                                <span><?= htmlspecialchars($zipCode) ?></span>
                                                <button class="zip-remove" 
                                                        onclick="removeZipCode('<?= $zone['id'] ?>', '<?= $zipCode ?>')"
                                                        title="Remove ZIP code">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="zip-add-form">
                                    <input type="text" 
                                           class="zip-input" 
                                           placeholder="12345" 
                                           maxlength="5"
                                           pattern="\d{5}"
                                           id="zipInput-<?= $zone['id'] ?>"
                                           onkeypress="handleZipKeyPress(event, '<?= $zone['id'] ?>')">
                                    <button class="btn btn-success btn-sm" 
                                            onclick="addZipCode('<?= $zone['id'] ?>')">
                                        <i class="fas fa-plus"></i> Add ZIP
                                    </button>
                                </div>
                            </div>
                            
                            <div class="zone-actions">
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deleteZone('<?= $zone['id'] ?>', '<?= htmlspecialchars(addslashes($zone['zone_name'])) ?>')">
                                    <i class="fas fa-trash"></i> Delete Zone
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Create Zone Modal -->
    <div class="modal" id="createZoneModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Create New Delivery Zone</h2>
                <button type="button" class="modal-close" onclick="closeModal('createZoneModal')">&times;</button>
            </div>
            <form id="createZoneForm">
                <div class="form-group">
                    <label class="form-label">Zone Name *</label>
                    <input type="text" 
                           class="form-control" 
                           name="zone_name" 
                           placeholder="e.g., Downtown, North Side, etc."
                           required>
                </div>
                <div class="form-group">
                    <label class="form-label">Initial ZIP Codes *</label>
                    <textarea class="form-control" 
                              name="zip_codes" 
                              rows="4" 
                              placeholder="Enter ZIP codes separated by commas, spaces, or new lines&#10;e.g., 90210, 90211, 90212"
                              required></textarea>
                    <small class="text-muted">Enter at least one 5-digit ZIP code</small>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createZoneModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Zone
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Modal Management
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function showCreateZoneModal() {
            document.getElementById('createZoneForm').reset();
            showModal('createZoneModal');
        }

        // ZIP Code Management Functions
        async function addZipCode(zoneId) {
            const input = document.getElementById(`zipInput-${zoneId}`);
            const zipCode = input.value.trim();
            
            if (!zipCode) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing ZIP Code',
                    text: 'Please enter a ZIP code to add.',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            if (!/^\d{5}$/.test(zipCode)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Format',
                    text: 'ZIP code must be exactly 5 digits.',
                    confirmButtonText: 'OK'
                });
                input.focus();
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'add_zip_code');
                formData.append('zone_id', zoneId);
                formData.append('zip_code', zipCode);

                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message,
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Unable to add ZIP code. Please try again.',
                    confirmButtonText: 'OK'
                });
                console.error('Error:', error);
            }
        }

        async function removeZipCode(zoneId, zipCode) {
            const { isConfirmed } = await Swal.fire({
                title: 'Remove ZIP Code?',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <p><strong>ZIP Code:</strong> ${zipCode}</p>
                        <hr style="margin: 1rem 0;">
                        <p style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong></p>
                        <ul style="color: #666; margin-left: 1rem;">
                            <li>This ZIP code will no longer be available for delivery</li>
                            <li>Existing orders in this area will not be affected</li>
                            <li>Customers in this area won't be able to place new orders</li>
                        </ul>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Yes, Remove',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d'
            });

            if (!isConfirmed) return;

            try {
                const formData = new FormData();
                formData.append('action', 'remove_zip_code');
                formData.append('zone_id', zoneId);
                formData.append('zip_code', zipCode);

                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Removed!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message,
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Unable to remove ZIP code. Please try again.',
                    confirmButtonText: 'OK'
                });
                console.error('Error:', error);
            }
        }

        async function deleteZone(zoneId, zoneName) {
            const { isConfirmed } = await Swal.fire({
                title: 'Delete Entire Zone?',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <p><strong>Zone:</strong> ${zoneName}</p>
                        <hr style="margin: 1rem 0;">
                        <p style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> <strong>Danger Zone:</strong></p>
                        <ul style="color: #666; margin-left: 1rem;">
                            <li>This will permanently delete the entire zone</li>
                            <li>All ZIP codes in this zone will be removed</li>
                            <li>This action cannot be undone</li>
                            <li>Zone cannot be deleted if it has active orders</li>
                        </ul>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Yes, Delete Zone',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d'
            });

            if (!isConfirmed) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete_zone');
                formData.append('zone_id', zoneId);

                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Zone Deleted!',
                        text: result.message,
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cannot Delete Zone',
                        text: result.message,
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Unable to delete zone. Please try again.',
                    confirmButtonText: 'OK'
                });
                console.error('Error:', error);
            }
        }

        // Form Handling
        document.getElementById('createZoneForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_zone');
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    closeModal('createZoneModal');
                    await Swal.fire({
                        icon: 'success',
                        title: 'Zone Created!',
                        text: result.message,
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Creating Zone',
                        text: result.message,
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Unable to create zone. Please try again.',
                    confirmButtonText: 'OK'
                });
                console.error('Error:', error);
            }
        });

        // Utility Functions
        function handleZipKeyPress(event, zoneId) {
            // Only allow digits
            if (!/\d/.test(event.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(event.key)) {
                event.preventDefault();
                return;
            }
            
            // Submit on Enter
            if (event.key === 'Enter') {
                event.preventDefault();
                addZipCode(zoneId);
            }
        }

        // Input validation for ZIP codes
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('zip-input')) {
                // Remove non-digits
                e.target.value = e.target.value.replace(/\D/g, '');
                
                // Limit to 5 digits
                if (e.target.value.length > 5) {
                    e.target.value = e.target.value.slice(0, 5);
                }
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close any open modals
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                showCreateZoneModal();
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🗺️ Krua Thai ZIP Code Management System initialized');
            console.log('⌨️ Keyboard shortcuts: Ctrl+N (New Zone), Esc (Close Modal)');
            console.log('📍 Features: Add/Remove ZIP codes, Create/Delete zones');
            
            // Auto-focus first ZIP input if zones exist
            const firstZipInput = document.querySelector('.zip-input');
            if (firstZipInput) {
                firstZipInput.focus();
            }
            
            // Initialize delivery menu as expanded
            const deliveryMenu = document.getElementById('deliveryMenu');
            const deliverySubmenu = document.getElementById('deliverySubmenu');
            if (deliveryMenu && deliverySubmenu) {
                deliveryMenu.classList.add('expanded');
                deliverySubmenu.classList.add('expanded');
            }
        });

        // Toggle delivery submenu
        function toggleDeliveryMenu() {
            const deliveryMenu = document.getElementById('deliveryMenu');
            const deliverySubmenu = document.getElementById('deliverySubmenu');
            
            if (deliveryMenu && deliverySubmenu) {
                deliveryMenu.classList.toggle('expanded');
                deliverySubmenu.classList.toggle('expanded');
            }
        }

        // Add visual feedback for ZIP code actions
        function showZipCodeFeedback(element, type) {
            const originalColor = element.style.backgroundColor;
            element.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
            element.style.transition = 'background-color 0.3s ease';
            
            setTimeout(() => {
                element.style.backgroundColor = originalColor;
            }, 1000);
        }

        // Validate ZIP code in real-time
        function validateZipCode(zipCode) {
            if (!/^\d{5}$/.test(zipCode)) {
                return { valid: false, message: 'ZIP code must be exactly 5 digits' };
            }
            
            // Basic US ZIP code validation (you can customize for your region)
            const firstDigit = zipCode.charAt(0);
            if (firstDigit === '0' && zipCode === '00000') {
                return { valid: false, message: 'Invalid ZIP code' };
            }
            
            return { valid: true, message: 'Valid ZIP code' };
        }
    </script>
</body>
</html>