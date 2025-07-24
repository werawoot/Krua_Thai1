<?php
/**
 * Krua Thai - Enhanced Rider Assignment System with Unassign Feature
 * File: admin/assign-riders.php
 * Features: View assigned/unassigned orders, assign/unassign orders to/from riders.
 * Status: ENHANCED ✅
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
// FUNCTIONS
// ======================================================================

function assignOrdersToRider($pdo, $orderIds, $riderId) {
    if (empty($orderIds) || empty($riderId)) {
        return ['success' => false, 'message' => 'Missing order IDs or rider ID.'];
    }

    try {
        $pdo->beginTransaction();
        
        // Verify orders are available for assignment
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        $checkStmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE id IN ($placeholders) AND assigned_rider_id IS NULL");
        $checkStmt->execute($orderIds);
        $availableOrders = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($availableOrders) !== count($orderIds)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Some orders are no longer available for assignment.'];
        }
        
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET assigned_rider_id = ?, status = 'out_for_delivery', updated_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        
        $params = array_merge([$riderId], $orderIds);
        $stmt->execute($params);

        $pdo->commit();
        
        return ['success' => true, 'message' => count($orderIds) . ' order(s) assigned successfully.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function unassignOrdersFromRider($pdo, $orderIds) {
    if (empty($orderIds)) {
        return ['success' => false, 'message' => 'No order IDs provided.'];
    }

    try {
        $pdo->beginTransaction();
        
        // Verify orders can be unassigned (not delivered or cancelled)
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        $checkStmt = $pdo->prepare("
            SELECT id, order_number, status 
            FROM orders 
            WHERE id IN ($placeholders) 
            AND status NOT IN ('delivered', 'cancelled', 'failed')
            AND assigned_rider_id IS NOT NULL
        ");
        $checkStmt->execute($orderIds);
        $unassignableOrders = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($unassignableOrders)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'No orders can be unassigned (already delivered/cancelled or not assigned).'];
        }
        
        $validOrderIds = array_column($unassignableOrders, 'id');
        $validPlaceholders = str_repeat('?,', count($validOrderIds) - 1) . '?';
        
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET assigned_rider_id = NULL, status = 'ready', updated_at = NOW() 
            WHERE id IN ($validPlaceholders)
        ");
        
        $stmt->execute($validOrderIds);

        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => count($validOrderIds) . ' order(s) unassigned successfully.',
            'unassigned_count' => count($validOrderIds),
            'total_requested' => count($orderIds)
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function getUpcomingDeliveryDays($weeks = 4) {
    $deliveryDays = [];
    $today = new DateTime();
    for ($i = 0; $i < ($weeks * 7); $i++) {
        $day = (clone $today)->modify("+$i days");
        $dayOfWeek = $day->format('N'); // 1=Monday, 6=Saturday, 7=Sunday
        if (in_array($dayOfWeek, [3, 6])) { // Wednesday and Saturday
            if (!in_array($day->format('Y-m-d'), array_column($deliveryDays, 'date'))) {
                $deliveryDays[] = ['date' => $day->format('Y-m-d'), 'display' => $day->format('D, M j')];
            }
        }
    }
    return $deliveryDays;
}

// AJAX Request Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    try {
        if ($action === 'assign_orders') {
            $orderIds = json_decode($_POST['order_ids'] ?? '[]');
            $riderId = $_POST['rider_id'] ?? '';
            $result = assignOrdersToRider($pdo, $orderIds, $riderId);
            echo json_encode($result);
        } elseif ($action === 'unassign_orders') {
            $orderIds = json_decode($_POST['order_ids'] ?? '[]');
            $result = unassignOrdersFromRider($pdo, $orderIds);
            echo json_encode($result);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    }
    exit();
}

// ======================================================================
// DATA FETCHING FOR PAGE LOAD
// ======================================================================

$deliveryDate = $_GET['date'] ?? date('Y-m-d');
$zipCoordinates = [
    '92831' => ['zone' => 'A'], '92832' => ['zone' => 'A'], '92833' => ['zone' => 'A'],
    '92835' => ['zone' => 'A'], '92821' => ['zone' => 'A'], '92823' => ['zone' => 'A'],
    '90620' => ['zone' => 'B'], '90621' => ['zone' => 'B'], '92801' => ['zone' => 'B'],
    '92802' => ['zone' => 'B'], '92804' => ['zone' => 'B'], '92805' => ['zone' => 'B'],
    '92840' => ['zone' => 'C'], '92841' => ['zone' => 'C'], '92843' => ['zone' => 'C'],
    '92683' => ['zone' => 'C'], '92703' => ['zone' => 'D'], '92648' => ['zone' => 'D'],
    '92647' => ['zone' => 'D']
];

try {
    // Get Riders and their current load for the selected date
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, 
               COALESCE((SELECT COUNT(o.id) FROM orders o WHERE o.assigned_rider_id = u.id AND DATE(o.delivery_date) = ?), 0) as current_orders,
               COALESCE((SELECT SUM(o.total_items) FROM orders o WHERE o.assigned_rider_id = u.id AND DATE(o.delivery_date) = ?), 0) as current_load
        FROM users u 
        WHERE u.role = 'rider' AND u.status = 'active' 
        GROUP BY u.id
        ORDER BY u.first_name
    ");
    $stmt->execute([$deliveryDate, $deliveryDate]);
    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Unassigned Orders for the selected date
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.total_items, o.delivery_address, u.zip_code, u.first_name, u.last_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE DATE(o.delivery_date) = ? AND o.assigned_rider_id IS NULL AND o.status IN ('confirmed', 'preparing', 'ready')
        ORDER BY u.zip_code, o.created_at
    ");
    $stmt->execute([$deliveryDate]);
    $unassignedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Assigned Orders for the selected date
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.total_items, o.delivery_address, o.status, o.assigned_rider_id,
               u.zip_code, u.first_name as customer_first_name, u.last_name as customer_last_name,
               r.first_name as rider_first_name, r.last_name as rider_last_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN users r ON o.assigned_rider_id = r.id
        WHERE DATE(o.delivery_date) = ? AND o.assigned_rider_id IS NOT NULL 
        AND o.status NOT IN ('delivered', 'cancelled')
        ORDER BY r.first_name, o.created_at
    ");
    $stmt->execute([$deliveryDate]);
    $assignedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Riders - Krua Thai Admin</title>
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
        
        /* Mobile Menu Button */
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
        
        /* Enhanced Sidebar */
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
            margin-bottom: 0.5rem;
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
        
        /* Navigation Sections */
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
            border-left: 3px solid transparent; 
            transition: var(--transition);
            cursor: pointer;
        }

          /* ⬇️ เพิ่มตรงนี้ หลังจาก CSS navigation ที่มีอยู่แล้ว */
        /* Collapsible Delivery Menu */
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
            gap: 1rem;
            padding: 0.5rem 1.5rem 0.5rem 3rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        .nav-subitem:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
        }
        .nav-subitem.active {
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
            font-weight: 600;
        }
        .nav-item:hover { 
            background: rgba(255, 255, 255, 0.1); 
            border-left-color: var(--white); 
            color: var(--white);
            text-decoration: none;
        }
        .nav-item.active { 
            background: rgba(255, 255, 255, 0.15);
            border-left-color: var(--white); 
            font-weight: 600; 
        }
        .nav-icon { 
            width: 24px; 
            text-align: center; 
            font-size: 1.2rem; 
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
            flex-wrap: wrap;
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
        
        /* Date Selector */
        .date-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--cream);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
        }
        .date-selector select { 
            border: none;
            background: transparent;
            font-family: inherit;
            color: var(--text-dark);
            font-weight: 500;
            cursor: pointer;
        }
        
        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: var(--white);
            padding: 0.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }
        .tab-button {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--text-gray);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .tab-button:hover {
            background: var(--cream);
            color: var(--text-dark);
        }
        .tab-button.active {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }
        
        /* Assignment Layout */
        .assignment-layout { 
            display: grid; 
            grid-template-columns: 400px 1fr; 
            gap: 2rem; 
            align-items: start; 
        }
        .assignment-column { 
            background: var(--white); 
            border-radius: var(--radius-md); 
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
        }
        .column-header { 
            padding: 1.5rem; 
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
        }
        .column-title { 
            font-size: 1.25rem; 
            font-weight: 600; 
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .column-body { 
            padding: 1.5rem; 
            max-height: 70vh; 
            overflow-y: auto; 
        }
        
        /* Cards */
        .rider-card, .order-card { 
            background: #f8f9fa; 
            border: 1px solid var(--border-light); 
            border-radius: var(--radius-sm); 
            padding: 1rem; 
            margin-bottom: 1rem; 
            transition: var(--transition); 
        }
        .rider-card {
            border-left: 4px solid var(--sage);
        }
        .rider-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }
        .order-card { 
            cursor: pointer; 
            border-left: 4px solid transparent;
        }
        .order-card:hover {
            border-left-color: var(--brown);
            background-color: #f0f0f0;
        }
        .order-card.selected { 
            border-left-color: var(--curry); 
            background-color: #fff9f2; 
            box-shadow: var(--shadow-soft);
        }
        .order-card.assigned {
            border-left-color: var(--success);
            background-color: #f8fff8;
        }
        .order-card.assigned.selected {
            border-left-color: var(--danger);
            background-color: #fff0f0;
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
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        .btn-primary { 
            background: linear-gradient(135deg, var(--curry), #e67e22); 
            color: var(--white); 
        }
        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: var(--white);
        }
        .btn-sm { 
            padding: 0.5rem 1rem; 
            font-size: 0.9rem; 
        }
        
        /* Loading Overlay */
        .loading-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0,0,0,0.5); 
            z-index: 9999; 
            display: none; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            backdrop-filter: blur(3px);
        }
        .loading-content {
            background: var(--white);
            color: var(--text-dark);
            padding: 2rem;
            border-radius: var(--radius-md);
            text-align: center;
            box-shadow: var(--shadow-medium);
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Zone Badge */
        .zone-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            margin-left: 0.5rem;
        }
        .zone-badge.zone-A { background: #27ae60; }
        .zone-badge.zone-B { background: #f39c12; }
        .zone-badge.zone-C { background: #e67e22; }
        .zone-badge.zone-D { background: #e74c3c; }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            margin-left: 0.5rem;
        }
        .status-badge.out_for_delivery { background: var(--warning); }
        .status-badge.ready { background: var(--success); }
        .status-badge.preparing { background: var(--curry); }
        
        /* Stats */
        .rider-stats {
            display: flex;
            justify-content: space-between;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: var(--text-gray);
        }
        
        /* Hidden sections */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
            .assignment-layout {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .page-header {
                padding: 1.5rem;
            }
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            .mobile-menu-btn {
                display: block !important;
            }
            .tab-navigation {
                flex-direction: column;
                gap: 0.25rem;
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
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

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
                            <a href="delivery-zones.php" class="nav-subitem">
                                <i class="nav-subitem-icon fas fa-map"></i>
                                <span>Delivery Zones</span>
                            </a>
                            <a href="assign-riders.php" class="nav-subitem active">
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
        <div class="main-content">
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-user-check" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Enhanced Rider Assignment
                        </h1>
                        <p class="page-subtitle">Assign or unassign orders to/from riders with enhanced controls</p>
                    </div>
                    <div class="header-actions">
                        <div class="date-selector">
                            <i class="fas fa-calendar-alt" style="color: var(--curry);"></i>
                            <form method="GET" style="display: inline;">
                                <select name="date" onchange="this.form.submit()">
                                    <?php foreach (getUpcomingDeliveryDays() as $day): ?>
                                        <option value="<?= $day['date'] ?>" <?= $day['date'] == $deliveryDate ? 'selected' : '' ?>>
                                            <?= $day['display'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-button active" onclick="switchTab('unassigned')">
                    <i class="fas fa-box-open"></i>
                    Unassigned Orders (<?= count($unassignedOrders) ?>)
                </button>
                <button class="tab-button" onclick="switchTab('assigned')">
                    <i class="fas fa-truck"></i>
                    Assigned Orders (<?= count($assignedOrders) ?>)
                </button>
            </div>

            <!-- Unassigned Orders Tab -->
            <div id="unassigned-tab" class="tab-content active">
                <div class="assignment-layout">
                    <!-- Riders Column -->
                    <div class="assignment-column">
                        <div class="column-header">
                            <h2 class="column-title">
                                <i class="fas fa-users"></i> 
                                Available Riders (<?= count($riders) ?>)
                            </h2>
                        </div>
                        <div class="column-body">
                            <?php if (empty($riders)): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <p>No active riders found.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($riders as $rider): ?>
                                    <div class="rider-card">
                                        <h4 class="mb-1">
                                            <i class="fas fa-user" style="color: var(--sage); margin-right: 0.5rem;"></i>
                                            <?= htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) ?>
                                        </h4>
                                        <div class="rider-stats">
                                            <span>
                                                <i class="fas fa-shopping-cart"></i>
                                                <?= $rider['current_orders'] ?> orders
                                            </span>
                                            <span>
                                                <i class="fas fa-box"></i>
                                                <?= $rider['current_load'] ?> boxes
                                            </span>
                                        </div>
                                        <button class="btn btn-primary btn-sm" style="width: 100%;" onclick="assignToRider('<?= $rider['id'] ?>')">
                                            <i class="fas fa-user-plus"></i> Assign Selected Orders
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Unassigned Orders Column -->
                    <div class="assignment-column">
                        <div class="column-header">
                            <h2 class="column-title">
                                <i class="fas fa-box-open"></i> 
                                Unassigned Orders (<?= count($unassignedOrders) ?>)
                            </h2>
                        </div>
                        <div class="column-body">
                            <?php if (empty($unassignedOrders)): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; color: var(--sage);"></i>
                                    <h3 style="color: var(--sage);">All Orders Assigned!</h3>
                                    <p>No unassigned orders for this date.</p>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom: 1rem; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm); border-left: 4px solid var(--curry);">
                                    <i class="fas fa-info-circle" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                    <strong>Instructions:</strong> Click on orders to select them, then click "Assign Selected Orders" on a rider card.
                                </div>
                                <?php foreach ($unassignedOrders as $order): 
                                    $zipCode = substr($order['zip_code'], 0, 5);
                                    $zone = $zipCoordinates[$zipCode]['zone'] ?? 'N/A';
                                ?>
                                    <div class="order-card" data-order-id="<?= $order['id'] ?>" onclick="toggleOrderSelection(this)">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                            <strong style="color: var(--curry);">
                                                <i class="fas fa-receipt"></i>
                                                #<?= htmlspecialchars($order['order_number']) ?>
                                            </strong>
                                            <span class="zone-badge zone-<?= $zone ?>">Zone <?= $zone ?></span>
                                        </div>
                                        <div class="mb-1">
                                            <i class="fas fa-user" style="color: var(--brown); margin-right: 0.5rem;"></i>
                                            <strong><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 0.5rem;">
                                            <i class="fas fa-map-marker-alt" style="margin-right: 0.5rem;"></i>
                                            <?= htmlspecialchars($order['delivery_address']) ?>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                                            <span>
                                                <i class="fas fa-box" style="color: var(--sage); margin-right: 0.25rem;"></i>
                                                <?= $order['total_items'] ?> items
                                            </span>
                                            <span class="text-muted">
                                                <i class="fas fa-map-pin" style="margin-right: 0.25rem;"></i>
                                                <?= htmlspecialchars($zipCode) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assigned Orders Tab -->
            <div id="assigned-tab" class="tab-content">
                <div class="assignment-layout">
                    <!-- Unassign Action Column -->
                    <div class="assignment-column">
                        <div class="column-header">
                            <h2 class="column-title">
                                <i class="fas fa-user-times"></i> 
                                Unassign Actions
                            </h2>
                        </div>
                        <div class="column-body">
                            <div style="margin-bottom: 1rem; padding: 1rem; background: #fff3cd; border-radius: var(--radius-sm); border-left: 4px solid var(--warning);">
                                <i class="fas fa-exclamation-triangle" style="color: var(--warning); margin-right: 0.5rem;"></i>
                                <strong>Warning:</strong> Unassigning orders will return them to the unassigned list and change their status back to "Ready".
                            </div>
                            
                            <div style="text-align: center; margin: 2rem 0;">
                                <button class="btn btn-danger" onclick="unassignSelected()" style="width: 100%;">
                                    <i class="fas fa-user-times"></i>
                                    Unassign Selected Orders
                                </button>
                            </div>
                            
                            <div style="margin-top: 1rem; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                                <h4 style="margin-bottom: 0.5rem;">
                                    <i class="fas fa-keyboard" style="color: var(--curry);"></i>
                                    Keyboard Shortcuts
                                </h4>
                                <ul style="list-style: none; font-size: 0.9rem; color: var(--text-gray);">
                                    <li><strong>Ctrl+A:</strong> Select all orders</li>
                                    <li><strong>Ctrl+D:</strong> Deselect all orders</li>
                                    <li><strong>Esc:</strong> Clear selections</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assigned Orders Column -->
                    <div class="assignment-column">
                        <div class="column-header">
                            <h2 class="column-title">
                                <i class="fas fa-truck"></i> 
                                Assigned Orders (<?= count($assignedOrders) ?>)
                            </h2>
                        </div>
                        <div class="column-body">
                            <?php if (empty($assignedOrders)): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <h3>No Assigned Orders</h3>
                                    <p>No orders have been assigned to riders for this date.</p>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom: 1rem; padding: 1rem; background: #d1ecf1; border-radius: var(--radius-sm); border-left: 4px solid var(--curry);">
                                    <i class="fas fa-info-circle" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                    <strong>Instructions:</strong> Click on assigned orders to select them for unassignment.
                                </div>
                                <?php 
                                $currentRider = null;
                                foreach ($assignedOrders as $order): 
                                    $riderName = $order['rider_first_name'] . ' ' . $order['rider_last_name'];
                                    if ($currentRider !== $riderName): 
                                        if ($currentRider !== null): echo '</div>'; endif;
                                        $currentRider = $riderName;
                                ?>
                                    <div style="margin-bottom: 1rem;">
                                        <h4 style="color: var(--brown); margin-bottom: 0.5rem; padding: 0.5rem; background: var(--cream); border-radius: var(--radius-sm);">
                                            <i class="fas fa-user" style="margin-right: 0.5rem;"></i>
                                            Rider: <?= htmlspecialchars($riderName) ?>
                                        </h4>
                                <?php endif; 
                                    $zipCode = substr($order['zip_code'], 0, 5);
                                    $zone = $zipCoordinates[$zipCode]['zone'] ?? 'N/A';
                                ?>
                                    <div class="order-card assigned" data-order-id="<?= $order['id'] ?>" onclick="toggleOrderSelection(this)">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                            <strong style="color: var(--success);">
                                                <i class="fas fa-receipt"></i>
                                                #<?= htmlspecialchars($order['order_number']) ?>
                                            </strong>
                                            <div>
                                                <span class="status-badge <?= $order['status'] ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                                </span>
                                                <span class="zone-badge zone-<?= $zone ?>">Zone <?= $zone ?></span>
                                            </div>
                                        </div>
                                        <div class="mb-1">
                                            <i class="fas fa-user" style="color: var(--brown); margin-right: 0.5rem;"></i>
                                            <strong><?= htmlspecialchars($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></strong>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.9rem; margin-bottom: 0.5rem;">
                                            <i class="fas fa-map-marker-alt" style="margin-right: 0.5rem;"></i>
                                            <?= htmlspecialchars($order['delivery_address']) ?>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                                            <span>
                                                <i class="fas fa-box" style="color: var(--sage); margin-right: 0.25rem;"></i>
                                                <?= $order['total_items'] ?> items
                                            </span>
                                            <span class="text-muted">
                                                <i class="fas fa-map-pin" style="margin-right: 0.25rem;"></i>
                                                <?= htmlspecialchars($zipCode) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; 
                                if ($currentRider !== null): echo '</div>'; endif;
                                ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3 id="loadingTitle">Processing...</h3>
            <p id="loadingMessage" style="color: var(--text-gray); margin-top: 0.5rem;">Please wait</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

          // ⬇️ เพิ่มตรงนี้ก่อน JavaScript อื่นๆ
        function toggleDeliveryMenu() {
            const deliveryMenu = document.getElementById('deliveryMenu');
            const deliverySubmenu = document.getElementById('deliverySubmenu');
            
            deliveryMenu.classList.toggle('expanded');
            deliverySubmenu.classList.toggle('expanded');
        }

        // Auto-expand delivery menu since we're on assign-riders.php
        document.addEventListener('DOMContentLoaded', function() {
            const deliveryMenu = document.getElementById('deliveryMenu');
            const deliverySubmenu = document.getElementById('deliverySubmenu');
            if (deliveryMenu && deliverySubmenu) {
                deliveryMenu.classList.add('expanded');
                deliverySubmenu.classList.add('expanded');
            }
        });
        // Global variables
        let currentTab = 'unassigned';
        
        // Tab switching functionality
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(`${tabName}-tab`).classList.add('active');
            
            // Clear selections when switching tabs
            clearAllSelections();
            
            currentTab = tabName;
        }

        // Toggle sidebar for mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Order selection functionality
        function toggleOrderSelection(cardElement) {
            cardElement.classList.toggle('selected');
            
            // Add selection feedback
            if (cardElement.classList.contains('selected')) {
                cardElement.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    cardElement.style.transform = '';
                }, 150);
            }
        }

        function getSelectedOrderIds() {
            return Array.from(document.querySelectorAll('.order-card.selected')).map(card => card.dataset.orderId);
        }

        function clearAllSelections() {
            document.querySelectorAll('.order-card.selected').forEach(card => {
                card.classList.remove('selected');
            });
        }
        
        // Assignment functionality
        async function assignToRider(riderId) {
            const orderIds = getSelectedOrderIds();
            if (orderIds.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Orders Selected',
                    text: 'Please click on one or more orders to select them first.',
                    confirmButtonText: 'Got it!'
                });
                return;
            }

            const riderName = document.querySelector(`.rider-card button[onclick="assignToRider('${riderId}')"]`)
                .closest('.rider-card').querySelector('h4').textContent.trim();

            const { isConfirmed } = await Swal.fire({
                title: 'Confirm Assignment',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <p><strong>Rider:</strong> ${riderName}</p>
                        <p><strong>Orders:</strong> ${orderIds.length} order(s)</p>
                        <hr style="margin: 1rem 0;">
                        <p style="color: #666;">This will assign the selected orders to this rider and update their status to "Out for Delivery".</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-user-plus"></i> Yes, Assign!',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                confirmButtonColor: '#cf723a',
                cancelButtonColor: '#6c757d'
            });

            if (isConfirmed) {
                await performAssignment(orderIds, riderId);
            }
        }

        // Unassignment functionality
        async function unassignSelected() {
            const orderIds = getSelectedOrderIds();
            if (orderIds.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Orders Selected',
                    text: 'Please click on one or more assigned orders to select them first.',
                    confirmButtonText: 'Got it!'
                });
                return;
            }

            const { isConfirmed } = await Swal.fire({
                title: 'Confirm Unassignment',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <p><strong>Orders to unassign:</strong> ${orderIds.length} order(s)</p>
                        <hr style="margin: 1rem 0;">
                        <p style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong></p>
                        <ul style="color: #666; margin-left: 1rem;">
                            <li>Orders will be returned to the unassigned list</li>
                            <li>Status will change back to "Ready"</li>
                            <li>Riders will lose these assignments</li>
                            <li>This action helps prevent delivery mistakes</li>
                        </ul>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-user-times"></i> Yes, Unassign!',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d'
            });

            if (isConfirmed) {
                await performUnassignment(orderIds);
            }
        }

        // Perform assignment
        async function performAssignment(orderIds, riderId) {
            document.getElementById('loadingTitle').textContent = 'Assigning Orders...';
            document.getElementById('loadingMessage').textContent = 'Please wait while we assign the orders';
            document.getElementById('loadingOverlay').style.display = 'flex';

            const formData = new FormData();
            formData.append('action', 'assign_orders');
            formData.append('rider_id', riderId);
            formData.append('order_ids', JSON.stringify(orderIds));

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    await Swal.fire({ 
                        icon: 'success', 
                        title: 'Assignment Successful!', 
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
                        title: 'Assignment Failed',
                        text: result.message,
                        confirmButtonText: 'Try Again'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'An unexpected error occurred. Please try again.',
                    confirmButtonText: 'OK'
                });
                console.error('Error:', error);
            } finally {
                document.getElementById('loadingOverlay').style.display = 'none';
            }
        }

        // Perform unassignment
        async function performUnassignment(orderIds) {
            document.getElementById('loadingTitle').textContent = 'Unassigning Orders...';
            document.getElementById('loadingMessage').textContent = 'Please wait while we unassign the orders';
            document.getElementById('loadingOverlay').style.display = 'flex';

            const formData = new FormData();
            formData.append('action', 'unassign_orders');
            formData.append('order_ids', JSON.stringify(orderIds));

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    let message = result.message;
                    if (result.unassigned_count && result.total_requested && result.unassigned_count !== result.total_requested) {
                        message += ` (${result.unassigned_count} of ${result.total_requested} orders were eligible for unassignment)`;
                    }
                    
                    await Swal.fire({ 
                        icon: 'success', 
                        title: 'Unassignment Successful!', 
                        text: message, 
                        timer: 3000, 
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                    window.location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Unassignment Failed',
                        text: result.message,
                        confirmButtonText: 'Try Again'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'An unexpected error occurred. Please try again.',
                    confirmButtonText: 'OK'
                });
                console.error('Error:', error);
            } finally {
                document.getElementById('loadingOverlay').style.display = 'none';
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'a':
                        e.preventDefault();
                        // Select all orders in current tab
                        const selector = currentTab === 'unassigned' ? '.order-card:not(.assigned)' : '.order-card.assigned';
                        document.querySelectorAll(selector).forEach(card => {
                            if (!card.classList.contains('selected')) {
                                card.classList.add('selected');
                            }
                        });
                        break;
                    case 'd':
                        e.preventDefault();
                        // Deselect all orders
                        clearAllSelections();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                // Deselect all on Escape
                clearAllSelections();
            }

            // Tab switching with numbers
            if (e.key === '1' && !e.ctrlKey && !e.metaKey) {
                switchTab('unassigned');
            } else if (e.key === '2' && !e.ctrlKey && !e.metaKey) {
                switchTab('assigned');
            }
        });

        // Add selection counter functionality
        function updateSelectionCounter() {
            const selectedCount = document.querySelectorAll('.order-card.selected').length;
            // Update counters if they exist
            document.querySelectorAll('[id$="Counter"]').forEach(counter => {
                counter.textContent = selectedCount > 0 ? `${selectedCount} selected` : '';
            });
        }

        // Monitor selection changes
        document.addEventListener('click', function(e) {
            if (e.target.closest('.order-card')) {
                setTimeout(updateSelectionCounter, 10);
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚚 Krua Thai Enhanced Rider Assignment System initialized');
            console.log('⌨️ Keyboard shortcuts: Ctrl+A (Select All), Ctrl+D (Deselect All), Esc (Clear), 1/2 (Switch Tabs)');
            console.log('✨ New Feature: Unassign orders to prevent delivery mistakes');
            
            // Add selection counters to column headers if orders exist
            setTimeout(() => {
                const unassignedColumn = document.querySelector('#unassigned-tab .assignment-column:last-child .column-header');
                const assignedColumn = document.querySelector('#assigned-tab .assignment-column:last-child .column-header');
                
                if (unassignedColumn && document.querySelectorAll('#unassigned-tab .order-card').length > 0) {
                    const counter = document.createElement('div');
                    counter.id = 'unassignedCounter';
                    counter.style.cssText = 'font-size: 0.9rem; color: var(--text-gray); margin-top: 0.5rem;';
                    unassignedColumn.appendChild(counter);
                }
                
                if (assignedColumn && document.querySelectorAll('#assigned-tab .order-card').length > 0) {
                    const counter = document.createElement('div');
                    counter.id = 'assignedCounter';
                    counter.style.cssText = 'font-size: 0.9rem; color: var(--text-gray); margin-top: 0.5rem;';
                    assignedColumn.appendChild(counter);
                }
            }, 100);
        });
    </script>
</body>
</html>