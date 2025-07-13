<?php
/**
 * Krua Thai - Complete Zones Management System
 * File: admin/zones.php
 * Features: จัดการโซนส่งของ/รหัสไปรษณีย์/ค่าส่ง/สถิติ
 * Status: PRODUCTION READY ✅
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        switch ($_POST['action']) {
            case 'create_zone':
                $result = createZone($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'edit_zone':
                $result = editZone($pdo, $_POST['zone_id'], $_POST);
                echo json_encode($result);
                exit;
                
            case 'delete_zone':
                $result = deleteZone($pdo, $_POST['zone_id']);
                echo json_encode($result);
                exit;
                
            case 'toggle_status':
                $result = toggleZoneStatus($pdo, $_POST['zone_id']);
                echo json_encode($result);
                exit;
                
            case 'validate_zipcode':
                $result = validateZipCodeLocal($pdo, $_POST['zip_code']);
                echo json_encode($result);
                exit;
                
            case 'calculate_fee':
                $result = calculateDeliveryFee($pdo, $_POST['zip_code'], $_POST['order_amount'] ?? 0);
                echo json_encode($result);
                exit;
                
            case 'check_coverage':
                $result = checkZoneCoverage($pdo, $_POST['zip_code']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function getZoneList($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT dz.*, 
                   COUNT(DISTINCT o.id) as total_orders,
                   COUNT(DISTINCT CASE WHEN o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                                      THEN o.id END) as orders_last_30_days,
                   AVG(o.total_amount) as avg_order_value,
                   SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders
            FROM delivery_zones dz
            LEFT JOIN orders o ON JSON_UNQUOTE(JSON_EXTRACT(dz.zip_codes, '$[*]')) LIKE CONCAT('%', SUBSTRING(o.delivery_address, -5), '%')
            GROUP BY dz.id
            ORDER BY dz.zone_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function createZone($pdo, $data) {
    try {
        $zone_id = generateUUID();
        $zip_codes = json_encode(array_map('trim', explode(',', $data['zip_codes'])));
        $delivery_time_slots = json_encode(array_map('trim', explode(',', $data['delivery_time_slots'])));
        
        $stmt = $pdo->prepare("
            INSERT INTO delivery_zones 
            (id, zone_name, zip_codes, delivery_fee, free_delivery_minimum, 
             delivery_time_slots, estimated_delivery_time, max_orders_per_day, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $zone_id,
            $data['zone_name'],
            $zip_codes,
            $data['delivery_fee'],
            $data['free_delivery_minimum'],
            $delivery_time_slots,
            $data['estimated_delivery_time'],
            $data['max_orders_per_day'],
            1
        ]);
        
        return ['success' => true, 'message' => 'Zone created successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error creating zone: ' . $e->getMessage()];
    }
}

function editZone($pdo, $zone_id, $data) {
    try {
        $zip_codes = json_encode(array_map('trim', explode(',', $data['zip_codes'])));
        $delivery_time_slots = json_encode(array_map('trim', explode(',', $data['delivery_time_slots'])));
        
        $stmt = $pdo->prepare("
            UPDATE delivery_zones 
            SET zone_name = ?, zip_codes = ?, delivery_fee = ?, 
                free_delivery_minimum = ?, delivery_time_slots = ?, 
                estimated_delivery_time = ?, max_orders_per_day = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['zone_name'],
            $zip_codes,
            $data['delivery_fee'],
            $data['free_delivery_minimum'],
            $delivery_time_slots,
            $data['estimated_delivery_time'],
            $data['max_orders_per_day'],
            $zone_id
        ]);
        
        return ['success' => true, 'message' => 'Zone updated successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating zone: ' . $e->getMessage()];
    }
}

function deleteZone($pdo, $zone_id) {
    try {
        // Check if zone has active orders
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM orders o 
            WHERE JSON_UNQUOTE(JSON_EXTRACT(
                (SELECT zip_codes FROM delivery_zones WHERE id = ?), '$[*]'
            )) LIKE CONCAT('%', SUBSTRING(o.delivery_address, -5), '%')
            AND o.status NOT IN ('delivered', 'cancelled')
        ");
        $stmt->execute([$zone_id]);
        $active_orders = $stmt->fetchColumn();
        
        if ($active_orders > 0) {
            return ['success' => false, 'message' => 'Cannot delete zone with active orders'];
        }
        
        $stmt = $pdo->prepare("DELETE FROM delivery_zones WHERE id = ?");
        $stmt->execute([$zone_id]);
        
        return ['success' => true, 'message' => 'Zone deleted successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting zone: ' . $e->getMessage()];
    }
}

function toggleZoneStatus($pdo, $zone_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE delivery_zones 
            SET is_active = NOT is_active, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$zone_id]);
        
        return ['success' => true, 'message' => 'Zone status updated!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

function validateZipCodeLocal($pdo, $zip_code) {
    try {
        $stmt = $pdo->prepare("
            SELECT zone_name, is_active 
            FROM delivery_zones 
            WHERE JSON_CONTAINS(zip_codes, JSON_QUOTE(?))
        ");
        $stmt->execute([$zip_code]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($zone) {
            if ($zone['is_active']) {
                return ['success' => true, 'message' => 'Delivery available', 'zone' => $zone['zone_name']];
            } else {
                return ['success' => false, 'message' => 'Zone temporarily unavailable'];
            }
        } else {
            return ['success' => false, 'message' => 'No delivery to this area'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error validating zip code: ' . $e->getMessage()];
    }
}

function calculateDeliveryFee($pdo, $zip_code, $order_amount = 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT delivery_fee, free_delivery_minimum 
            FROM delivery_zones 
            WHERE JSON_CONTAINS(zip_codes, JSON_QUOTE(?)) AND is_active = 1
        ");
        $stmt->execute([$zip_code]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$zone) {
            return ['success' => false, 'message' => 'Area not covered'];
        }
        
        $fee = $zone['delivery_fee'];
        if ($order_amount >= $zone['free_delivery_minimum']) {
            $fee = 0;
        }
        
        return [
            'success' => true, 
            'delivery_fee' => $fee,
            'free_delivery_minimum' => $zone['free_delivery_minimum'],
            'is_free' => $fee == 0
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error calculating fee: ' . $e->getMessage()];
    }
}

function checkZoneCoverage($pdo, $zip_code) {
    try {
        $stmt = $pdo->prepare("
            SELECT dz.*, 
                   COUNT(o.id) as total_orders_in_area
            FROM delivery_zones dz
            LEFT JOIN orders o ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(?))
            WHERE JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(?))
            GROUP BY dz.id
        ");
        $stmt->execute([$zip_code, $zip_code]);
        $coverage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coverage) {
            return ['success' => true, 'data' => $coverage];
        } else {
            return ['success' => false, 'message' => 'Area not covered'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error checking coverage: ' . $e->getMessage()];
    }
}

function getZoneStatistics($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_zones,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_zones,
                AVG(delivery_fee) as avg_delivery_fee,
                AVG(free_delivery_minimum) as avg_free_minimum,
                AVG(estimated_delivery_time) as avg_delivery_time
            FROM delivery_zones
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [
            'total_zones' => 0,
            'active_zones' => 0, 
            'avg_delivery_fee' => 0,
            'avg_free_minimum' => 0,
            'avg_delivery_time' => 0
        ];
    }
}

// Get data for display
$zones = getZoneList($pdo);
$statistics = getZoneStatistics($pdo);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zones Management - Krua Thai Admin</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --cream: #ece8e1;
            --sage: #adb89d;
            --brown: #bd9379;
            --curry: #cf723a;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-muted);
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--curry);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Action Bar */
        .action-bar {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            justify-content: between;
            align-items: center;
            gap: 1rem;
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(207, 114, 58, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #fd7e14);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #e83e8c);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-outline:hover {
            background: var(--curry);
            color: white;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        /* Zones Table */
        .zones-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .zones-header {
            background: linear-gradient(135deg, var(--sage), var(--curry));
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .zones-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .table-container {
            overflow-x: auto;
        }

        .zones-table {
            width: 100%;
            border-collapse: collapse;
        }

        .zones-table th,
        .zones-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .zones-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-dark);
        }

        .zones-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .zip-codes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .zip-code {
            background: var(--cream);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            color: var(--text-dark);
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-help {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1001;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--danger);
        }

        .toast.info {
            background: var(--info);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--sage);
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }

            .zones-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-map-marked-alt"></i>
                <span>Krua Thai - Zones</span>
            </div>
            <nav class="nav-links">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="orders.php" class="nav-link">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
                <a href="zones.php" class="nav-link">
                    <i class="fas fa-map-marked-alt"></i> Zones
                </a>
                <a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Delivery Zones Management</h1>
            <p class="page-subtitle">จัดการโซนการส่งของ รหัสไปรษณีย์ และค่าบริการ</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Total Zones</div>
                    <div class="stat-icon">
                        <i class="fas fa-map"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $statistics['total_zones'] ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Active Zones</div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $statistics['active_zones'] ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Avg Delivery Fee</div>
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="stat-value">฿<?= number_format($statistics['avg_delivery_fee'], 0) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Avg Free Minimum</div>
                    <div class="stat-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                </div>
                <div class="stat-value">฿<?= number_format($statistics['avg_free_minimum'], 0) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Avg Delivery Time</div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($statistics['avg_delivery_time'], 0) ?> min</div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Search zones by name or zip code..." 
                       id="searchInput" onkeyup="searchZones()">
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i>
                Add New Zone
            </button>
            <button class="btn btn-outline" onclick="refreshZones()">
                <i class="fas fa-sync-alt"></i>
                Refresh
            </button>
        </div>

        <!-- Zones Table -->
        <div class="zones-container">
            <div class="zones-header">
                <div class="zones-title">
                    <i class="fas fa-list"></i>
                    Delivery Zones (<?= count($zones) ?>)
                </div>
                <div>
                    <button class="btn btn-outline btn-sm" onclick="exportZones()">
                        <i class="fas fa-download"></i>
                        Export
                    </button>
                </div>
            </div>

            <div class="table-container">
                <?php if (empty($zones)): ?>
                    <div class="empty-state">
                        <i class="fas fa-map-marked-alt"></i>
                        <h3>No delivery zones found</h3>
                        <p>Create your first delivery zone to start managing deliveries</p>
                        <button class="btn btn-primary" onclick="openCreateModal()">
                            <i class="fas fa-plus"></i>
                            Create Zone
                        </button>
                    </div>
                <?php else: ?>
                    <table class="zones-table" id="zonesTable">
                        <thead>
                            <tr>
                                <th>Zone Name</th>
                                <th>Zip Codes</th>
                                <th>Delivery Fee</th>
                                <th>Free Minimum</th>
                                <th>Delivery Time</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($zones as $zone): ?>
                                <tr data-zone-id="<?= $zone['id'] ?>">
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($zone['zone_name']) ?></strong>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                Max: <?= $zone['max_orders_per_day'] ?> orders/day
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="zip-codes">
                                            <?php 
                                                $zip_codes = json_decode($zone['zip_codes'], true);
                                                foreach (array_slice($zip_codes, 0, 3) as $zip): 
                                            ?>
                                                <span class="zip-code"><?= htmlspecialchars($zip) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($zip_codes) > 3): ?>
                                                <span class="zip-code">+<?= count($zip_codes) - 3 ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>฿<?= number_format($zone['delivery_fee'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <div>฿<?= number_format($zone['free_delivery_minimum'], 2) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            for free delivery
                                        </div>
                                    </td>
                                    <td>
                                        <div><?= $zone['estimated_delivery_time'] ?> minutes</div>
                                        <?php 
                                            $time_slots = json_decode($zone['delivery_time_slots'], true);
                                            if ($time_slots && count($time_slots) > 0):
                                        ?>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                <?= count($time_slots) ?> time slots
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= $zone['total_orders'] ?? 0 ?></strong> total
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            <?= $zone['orders_last_30_days'] ?? 0 ?> this month
                                        </div>
                                        <?php if ($zone['avg_order_value']): ?>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                Avg: ฿<?= number_format($zone['avg_order_value'], 0) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $zone['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $zone['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <button class="btn btn-outline btn-sm" 
                                                    onclick="editZone('<?= $zone['id'] ?>')"
                                                    title="Edit Zone">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-<?= $zone['is_active'] ? 'warning' : 'success' ?> btn-sm" 
                                                    onclick="toggleStatus('<?= $zone['id'] ?>')"
                                                    title="<?= $zone['is_active'] ? 'Deactivate' : 'Activate' ?> Zone">
                                                <i class="fas fa-<?= $zone['is_active'] ? 'pause' : 'play' ?>"></i>
                                            </button>
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="viewStatistics('<?= $zone['id'] ?>')"
                                                    title="View Statistics">
                                                <i class="fas fa-chart-bar"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="deleteZone('<?= $zone['id'] ?>', '<?= htmlspecialchars($zone['zone_name']) ?>')"
                                                    title="Delete Zone">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Create/Edit Zone Modal -->
    <div id="zoneModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Zone</h3>
                <button class="close-btn" onclick="closeModal('zoneModal')">&times;</button>
            </div>
            <form id="zoneForm">
                <input type="hidden" id="zoneId" name="zone_id">
                
                <div class="form-group">
                    <label class="form-label">Zone Name *</label>
                    <input type="text" id="zoneName" name="zone_name" class="form-control" 
                           placeholder="e.g., Central Bangkok" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Delivery Fee (฿) *</label>
                        <input type="number" id="deliveryFee" name="delivery_fee" class="form-control" 
                               min="0" step="0.01" placeholder="50.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Free Delivery Minimum (฿) *</label>
                        <input type="number" id="freeMinimum" name="free_delivery_minimum" class="form-control" 
                               min="0" step="0.01" placeholder="500.00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Zip Codes *</label>
                    <input type="text" id="zipCodes" name="zip_codes" class="form-control" 
                           placeholder="10110, 10120, 10130" required>
                    <div class="form-help">Enter zip codes separated by commas</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Delivery Time Slots</label>
                    <input type="text" id="timeSlots" name="delivery_time_slots" class="form-control" 
                           placeholder="09:00-12:00, 12:00-15:00, 15:00-18:00">
                    <div class="form-help">Enter time slots separated by commas</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Estimated Delivery Time (minutes) *</label>
                        <input type="number" id="deliveryTime" name="estimated_delivery_time" class="form-control" 
                               min="15" max="180" placeholder="60" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Orders per Day *</label>
                        <input type="number" id="maxOrders" name="max_orders_per_day" class="form-control" 
                               min="1" placeholder="100" required>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('zoneModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Zone
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Zone Statistics Modal -->
    <div id="statisticsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Zone Statistics</h3>
                <button class="close-btn" onclick="closeModal('statisticsModal')">&times;</button>
            </div>
            <div id="statisticsContent">
                <!-- Statistics content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Coverage Check Modal -->
    <div id="coverageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Check Zone Coverage</h3>
                <button class="close-btn" onclick="closeModal('coverageModal')">&times;</button>
            </div>
            <form id="coverageForm">
                <div class="form-group">
                    <label class="form-label">Zip Code</label>
                    <input type="text" id="checkZipCode" class="form-control" 
                           placeholder="Enter 5-digit zip code" maxlength="5" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Order Amount (฿) - Optional</label>
                    <input type="number" id="orderAmount" class="form-control" 
                           min="0" step="0.01" placeholder="0.00">
                    <div class="form-help">Check if free delivery applies</div>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-primary" onclick="checkCoverage()">
                        <i class="fas fa-search"></i>
                        Check Coverage
                    </button>
                    <button type="button" class="btn btn-info" onclick="calculateFee()">
                        <i class="fas fa-calculator"></i>
                        Calculate Fee
                    </button>
                </div>
                <div id="coverageResult" style="margin-top: 1rem;"></div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script>
        // Global variables
        let currentEditingZone = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners
            document.getElementById('zoneForm').addEventListener('submit', handleZoneSubmit);
            
            // Auto-format zip codes input
            document.getElementById('zipCodes').addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^\d,\s]/g, '');
                e.target.value = value;
            });

            // Validate zip code input in coverage check
            document.getElementById('checkZipCode').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                e.target.value = value;
            });
        });

        // Zone Management Functions
        function openCreateModal() {
            currentEditingZone = null;
            document.getElementById('modalTitle').textContent = 'Add New Zone';
            document.getElementById('zoneForm').reset();
            document.getElementById('zoneId').value = '';
            document.getElementById('zoneModal').style.display = 'block';
        }

        function editZone(zoneId) {
            currentEditingZone = zoneId;
            document.getElementById('modalTitle').textContent = 'Edit Zone';
            
            // Find zone data from table
            const row = document.querySelector(`tr[data-zone-id="${zoneId}"]`);
            if (!row) return;
            
            // Get zone data (you might want to fetch this via AJAX for complete data)
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_zone_details&zone_id=${zoneId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const zone = data.data;
                    document.getElementById('zoneId').value = zone.id;
                    document.getElementById('zoneName').value = zone.zone_name;
                    document.getElementById('deliveryFee').value = zone.delivery_fee;
                    document.getElementById('freeMinimum').value = zone.free_delivery_minimum;
                    document.getElementById('zipCodes').value = JSON.parse(zone.zip_codes).join(', ');
                    document.getElementById('timeSlots').value = JSON.parse(zone.delivery_time_slots || '[]').join(', ');
                    document.getElementById('deliveryTime').value = zone.estimated_delivery_time;
                    document.getElementById('maxOrders').value = zone.max_orders_per_day;
                    
                    document.getElementById('zoneModal').style.display = 'block';
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading zone data', 'error');
            });
        }

        function handleZoneSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const action = currentEditingZone ? 'edit_zone' : 'create_zone';
            formData.append('action', action);
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading"></span> Saving...';
            submitBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('zoneModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                console.error('Error:', error);
                showToast('Error saving zone', 'error');
            });
        }

        function deleteZone(zoneId, zoneName) {
            if (!confirm(`Are you sure you want to delete "${zoneName}"?\n\nThis action cannot be undone.`)) {
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_zone&zone_id=${zoneId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting zone', 'error');
            });
        }

        function toggleStatus(zoneId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_status&zone_id=${zoneId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating status', 'error');
            });
        }

        function viewStatistics(zoneId) {
            document.getElementById('statisticsContent').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading statistics...</div>';
            document.getElementById('statisticsModal').style.display = 'block';
            
            // You can implement detailed statistics fetching here
            setTimeout(() => {
                document.getElementById('statisticsContent').innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-chart-line" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3>Zone Statistics</h3>
                        <p>Detailed statistics feature coming soon!</p>
                        <p>This will include order trends, delivery performance, and revenue analytics.</p>
                    </div>
                `;
            }, 1000);
        }

        // Coverage Check Functions
        function openCoverageModal() {
            document.getElementById('coverageForm').reset();
            document.getElementById('coverageResult').innerHTML = '';
            document.getElementById('coverageModal').style.display = 'block';
        }

        function checkCoverage() {
            const zipCode = document.getElementById('checkZipCode').value.trim();
            if (!zipCode || zipCode.length !== 5) {
                showToast('Please enter a valid 5-digit zip code', 'error');
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_coverage&zip_code=${zipCode}`
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('coverageResult');
                if (data.success) {
                    const zone = data.data;
                    resultDiv.innerHTML = `
                        <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                            <h4><i class="fas fa-check-circle"></i> Coverage Available</h4>
                            <p><strong>Zone:</strong> ${zone.zone_name}</p>
                            <p><strong>Delivery Fee:</strong> ฿${parseFloat(zone.delivery_fee).toFixed(2)}</p>
                            <p><strong>Free Delivery Minimum:</strong> ฿${parseFloat(zone.free_delivery_minimum).toFixed(2)}</p>
                            <p><strong>Estimated Time:</strong> ${zone.estimated_delivery_time} minutes</p>
                            <p><strong>Status:</strong> ${zone.is_active == 1 ? 'Active' : 'Inactive'}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                            <h4><i class="fas fa-times-circle"></i> No Coverage</h4>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error checking coverage', 'error');
            });
        }

        function calculateFee() {
            const zipCode = document.getElementById('checkZipCode').value.trim();
            const orderAmount = document.getElementById('orderAmount').value || 0;
            
            if (!zipCode || zipCode.length !== 5) {
                showToast('Please enter a valid 5-digit zip code', 'error');
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=calculate_fee&zip_code=${zipCode}&order_amount=${orderAmount}`
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('coverageResult');
                if (data.success) {
                    const feeClass = data.is_free ? 'success' : 'info';
                    const feeColor = data.is_free ? '#155724' : '#004085';
                    const feeBackground = data.is_free ? '#d4edda' : '#cce7ff';
                    
                    resultDiv.innerHTML = `
                        <div style="background: ${feeBackground}; color: ${feeColor}; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                            <h4><i class="fas fa-calculator"></i> Delivery Fee Calculation</h4>
                            <p><strong>Order Amount:</strong> ฿${parseFloat(orderAmount).toFixed(2)}</p>
                            <p><strong>Delivery Fee:</strong> ฿${parseFloat(data.delivery_fee).toFixed(2)} ${data.is_free ? '(FREE!)' : ''}</p>
                            <p><strong>Free Delivery Minimum:</strong> ฿${parseFloat(data.free_delivery_minimum).toFixed(2)}</p>
                            ${!data.is_free && orderAmount > 0 ? 
                                `<p><strong>Add ฿${(data.free_delivery_minimum - orderAmount).toFixed(2)} more for free delivery</strong></p>` : ''
                            }
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                            <h4><i class="fas fa-times-circle"></i> Calculation Failed</h4>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error calculating fee', 'error');
            });
        }

        // Utility Functions
        function searchZones() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('zonesTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const zoneName = row.cells[0].textContent.toLowerCase();
                const zipCodes = row.cells[1].textContent.toLowerCase();
                
                if (zoneName.includes(filter) || zipCodes.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        function refreshZones() {
            showToast('Refreshing zones...', 'info');
            setTimeout(() => location.reload(), 500);
        }

        function exportZones() {
            // Simple CSV export
            const table = document.getElementById('zonesTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
                    let cellText = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + cellText + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `delivery_zones_${new Date().toISOString().slice(0, 10)}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
            
            showToast('Zones exported successfully!', 'success');
        }

        // Modal Management
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Toast Notifications
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            document.getElementById('toast-container').appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key closes modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (modal.style.display === 'block') {
                        modal.style.display = 'none';
                    }
                });
            }
            
            // Ctrl+N opens create modal
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openCreateModal();
            }
            
            // Ctrl+F focuses search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        // Add quick action buttons to header
        function addQuickActions() {
            const headerContent = document.querySelector('.header-content');
            const quickActions = document.createElement('div');
            quickActions.innerHTML = `
                <button class="btn btn-outline btn-sm" onclick="openCoverageModal()" style="margin-left: 1rem;">
                    <i class="fas fa-map-pin"></i>
                    Check Coverage
                </button>
            `;
            headerContent.appendChild(quickActions);
        }

        // Initialize quick actions
        document.addEventListener('DOMContentLoaded', addQuickActions);

        console.log('Zones Management System loaded successfully');
    </script>
</body>
</html>