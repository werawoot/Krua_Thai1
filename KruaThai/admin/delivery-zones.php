<?php
/**
 * Krua Thai - Delivery Zones Management
 * File: admin/delivery-zones.php
 * Description: Modern delivery zones management with real database connection
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php"); 
    exit();
}

// Handle AJAX requests for delivery zones operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_zone':
                $result = createDeliveryZone($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'update_zone':
                $result = updateDeliveryZone($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'delete_zone':
                $result = deleteDeliveryZone($pdo, $_POST['zone_id']);
                echo json_encode($result);
                exit;
                
            case 'toggle_status':
                $result = toggleZoneStatus($pdo, $_POST['zone_id']);
                echo json_encode($result);
                exit;
                
            case 'check_coverage':
                $result = checkDeliveryCoverage($pdo, $_POST['zip_code']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Functions for delivery zones operations
function createDeliveryZone($pdo, $data) {
    try {
        $zoneName = trim($data['zone_name']);
        $zipCodes = array_map('trim', explode(',', $data['zip_codes']));
        $deliveryFee = floatval($data['delivery_fee']);
        $freeDeliveryMinimum = !empty($data['free_delivery_minimum']) ? floatval($data['free_delivery_minimum']) : null;
        $estimatedTime = intval($data['estimated_delivery_time']);
        $maxOrders = intval($data['max_orders_per_day']);
        $deliveryTimeSlots = isset($data['delivery_time_slots']) ? $data['delivery_time_slots'] : [];
        
        // Validate required fields
        if (empty($zoneName) || empty($zipCodes)) {
            return ['success' => false, 'message' => 'Zone name and zip codes are required'];
        }
        
        // Check for duplicate zip codes
        foreach ($zipCodes as $zipCode) {
            $stmt = $pdo->prepare("
                SELECT zone_name FROM delivery_zones 
                WHERE JSON_CONTAINS(zip_codes, ?) AND is_active = 1
            ");
            $stmt->execute([json_encode($zipCode)]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                return ['success' => false, 'message' => "Zip code {$zipCode} already covered by zone: {$existing['zone_name']}"];
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO delivery_zones (
                id, zone_name, zip_codes, delivery_fee, free_delivery_minimum, 
                delivery_time_slots, estimated_delivery_time, max_orders_per_day, 
                is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        
        $stmt->execute([
            generate_uuid(),
            $zoneName,
            json_encode($zipCodes),
            $deliveryFee,
            $freeDeliveryMinimum,
            json_encode($deliveryTimeSlots),
            $estimatedTime,
            $maxOrders
        ]);
        
        return ['success' => true, 'message' => 'Delivery zone created successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error creating delivery zone: ' . $e->getMessage()];
    }
}

function updateDeliveryZone($pdo, $data) {
    try {
        $zoneId = $data['zone_id'];
        $zoneName = trim($data['zone_name']);
        $zipCodes = array_map('trim', explode(',', $data['zip_codes']));
        $deliveryFee = floatval($data['delivery_fee']);
        $freeDeliveryMinimum = !empty($data['free_delivery_minimum']) ? floatval($data['free_delivery_minimum']) : null;
        $estimatedTime = intval($data['estimated_delivery_time']);
        $maxOrders = intval($data['max_orders_per_day']);
        $deliveryTimeSlots = isset($data['delivery_time_slots']) ? $data['delivery_time_slots'] : [];
        
        // Check for duplicate zip codes (excluding current zone)
        foreach ($zipCodes as $zipCode) {
            $stmt = $pdo->prepare("
                SELECT zone_name FROM delivery_zones 
                WHERE JSON_CONTAINS(zip_codes, ?) AND is_active = 1 AND id != ?
            ");
            $stmt->execute([json_encode($zipCode), $zoneId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                return ['success' => false, 'message' => "Zip code {$zipCode} already covered by zone: {$existing['zone_name']}"];
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE delivery_zones SET
                zone_name = ?, zip_codes = ?, delivery_fee = ?, 
                free_delivery_minimum = ?, delivery_time_slots = ?, 
                estimated_delivery_time = ?, max_orders_per_day = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $zoneName,
            json_encode($zipCodes),
            $deliveryFee,
            $freeDeliveryMinimum,
            json_encode($deliveryTimeSlots),
            $estimatedTime,
            $maxOrders,
            $zoneId
        ]);
        
        return ['success' => true, 'message' => 'Delivery zone updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating delivery zone: ' . $e->getMessage()];
    }
}

function deleteDeliveryZone($pdo, $zoneId) {
    try {
        // Check if zone has active orders
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE u.zip_code IN (
                SELECT zip_code FROM delivery_zones dz, JSON_TABLE(dz.zip_codes, '$[*]' COLUMNS (zip_code VARCHAR(10) PATH '$')) jt
                WHERE dz.id = ? AND dz.is_active = 1
            ) AND o.status NOT IN ('delivered', 'cancelled')
        ");
        $stmt->execute([$zoneId]);
        $activeOrders = $stmt->fetchColumn();
        
        if ($activeOrders > 0) {
            return ['success' => false, 'message' => "Cannot delete zone with {$activeOrders} active orders"];
        }
        
        $stmt = $pdo->prepare("DELETE FROM delivery_zones WHERE id = ?");
        $stmt->execute([$zoneId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Delivery zone deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Delivery zone not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting delivery zone: ' . $e->getMessage()];
    }
}

function toggleZoneStatus($pdo, $zoneId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE delivery_zones 
            SET is_active = !is_active, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$zoneId]);
        
        $stmt = $pdo->prepare("SELECT is_active FROM delivery_zones WHERE id = ?");
        $stmt->execute([$zoneId]);
        $isActive = $stmt->fetchColumn();
        
        $status = $isActive ? 'activated' : 'deactivated';
        return ['success' => true, 'message' => "Delivery zone {$status} successfully", 'is_active' => $isActive];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error toggling zone status: ' . $e->getMessage()];
    }
}

function checkDeliveryCoverage($pdo, $zipCode) {
    try {
        $stmt = $pdo->prepare("
            SELECT zone_name, delivery_fee, free_delivery_minimum, estimated_delivery_time
            FROM delivery_zones 
            WHERE JSON_CONTAINS(zip_codes, ?) AND is_active = 1
        ");
        $stmt->execute([json_encode($zipCode)]);
        $zone = $stmt->fetch();
        
        if ($zone) {
            return [
                'success' => true, 
                'covered' => true,
                'zone' => $zone,
                'message' => "Zip code {$zipCode} is covered by {$zone['zone_name']}"
            ];
        } else {
            return [
                'success' => true, 
                'covered' => false,
                'message' => "Zip code {$zipCode} is not covered by any delivery zone"
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error checking coverage: ' . $e->getMessage()];
    }
}

// Create default delivery zones if table is empty
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM delivery_zones");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $defaultZones = [
            [
                'id' => generate_uuid(),
                'zone_name' => 'Central Bangkok',
                'zip_codes' => ['10110', '10120', '10130', '10140', '10330', '10400'],
                'delivery_fee' => 0.00,
                'free_delivery_minimum' => 300.00,
                'estimated_delivery_time' => 45,
                'max_orders_per_day' => 150,
                'delivery_time_slots' => ['09:00-12:00', '12:00-15:00', '15:00-18:00', '18:00-21:00']
            ],
            [
                'id' => generate_uuid(),
                'zone_name' => 'Greater Bangkok',
                'zip_codes' => ['10150', '10160', '10170', '10200', '10210', '10220', '10230'],
                'delivery_fee' => 50.00,
                'free_delivery_minimum' => 500.00,
                'estimated_delivery_time' => 60,
                'max_orders_per_day' => 100,
                'delivery_time_slots' => ['12:00-15:00', '15:00-18:00', '18:00-21:00']
            ],
            [
                'id' => generate_uuid(),
                'zone_name' => 'Bangkok Suburbs',
                'zip_codes' => ['10240', '10250', '10260', '10270', '10280', '10290'],
                'delivery_fee' => 80.00,
                'free_delivery_minimum' => 800.00,
                'estimated_delivery_time' => 90,
                'max_orders_per_day' => 50,
                'delivery_time_slots' => ['15:00-18:00', '18:00-21:00']
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO delivery_zones (
                id, zone_name, zip_codes, delivery_fee, free_delivery_minimum,
                delivery_time_slots, estimated_delivery_time, max_orders_per_day,
                is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        
        foreach ($defaultZones as $zone) {
            $stmt->execute([
                $zone['id'],
                $zone['zone_name'],
                json_encode($zone['zip_codes']),
                $zone['delivery_fee'],
                $zone['free_delivery_minimum'],
                json_encode($zone['delivery_time_slots']),
                $zone['estimated_delivery_time'],
                $zone['max_orders_per_day']
            ]);
        }
    }
} catch (Exception $e) {
    // Handle error silently
}

// Fetch all delivery zones
try {
    $stmt = $pdo->prepare("
        SELECT *, 
               JSON_LENGTH(zip_codes) as zip_count,
               JSON_LENGTH(delivery_time_slots) as time_slot_count
        FROM delivery_zones 
        ORDER BY is_active DESC, zone_name ASC
    ");
    $stmt->execute();
    $deliveryZones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $totalZones = count($deliveryZones);
    $activeZones = count(array_filter($deliveryZones, fn($z) => $z['is_active']));
    $totalZipCodes = array_sum(array_column($deliveryZones, 'zip_count'));
    $avgDeliveryTime = $totalZones > 0 ? round(array_sum(array_column($deliveryZones, 'estimated_delivery_time')) / $totalZones) : 0;
    
} catch (Exception $e) {
    $deliveryZones = [];
    $totalZones = $activeZones = $totalZipCodes = $avgDeliveryTime = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Zones - Krua Thai Admin</title>
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
            font-size: 2.5rem;
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

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        /* Stats Cards */
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

        /* Coverage Check */
        .coverage-check {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .coverage-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .coverage-form {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .coverage-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: var(--radius-sm);
            display: none;
        }

        .coverage-result.covered {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid #27ae60;
            color: #27ae60;
        }

        .coverage-result.not-covered {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        /* Zones Grid */
        .zones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .zone-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
        }

        .zone-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .zone-card.inactive {
            opacity: 0.6;
        }

        .zone-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .zone-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .zone-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .zone-status.active {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .zone-status.inactive {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .zone-actions {
            display: flex;
            gap: 0.5rem;
        }

        .zone-body {
            padding: 1.5rem;
        }

        .zone-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .zone-metric {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
        }

        .zone-metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.25rem;
        }

        .zone-metric-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .zip-codes {
            margin-bottom: 1rem;
        }

        .zip-codes-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .zip-codes-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .zip-code-tag {
            background: var(--cream);
            color: var(--text-dark);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-family: 'Courier New', monospace;
        }

        .time-slots {
            margin-bottom: 1rem;
        }

        .time-slots-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .time-slots-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .time-slot-tag {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
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
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
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

        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-control.is-invalid {
            border-color: #e74c3c;
        }

        .invalid-feedback {
            color: #e74c3c;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-item input {
            margin: 0;
        }

        .checkbox-item label {
            margin: 0;
            font-size: 0.85rem;
            cursor: pointer;
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

        .toast-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .toast-title {
            font-weight: 600;
            color: var(--text-dark);
        }

        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-gray);
        }

        .toast-body {
            color: var(--text-gray);
            font-size: 0.9rem;
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

            .zones-grid {
                grid-template-columns: 1fr;
            }

            .zone-info {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .coverage-form {
                flex-direction: column;
                align-items: stretch;
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
        .position-relative { position: relative; }

        .logo {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 0.5rem;
}

.logo-image {
    max-width: 80px;        /* ปรับขนาดตามต้องการ */
    max-height: 80px;
    width: auto;
    height: auto;
    object-fit: contain;    /* รักษาสัดส่วน */
    filter: brightness(1.1) contrast(1.2); /* เพิ่มความสวย */
    transition: transform 0.3s ease;
}

.logo-image:hover {
    transform: scale(1.05); /* ขยายเล็กน้อยเมื่อ hover */
}

/* สำหรับ Responsive */
@media (max-width: 768px) {
    .logo-image {
        max-width: 60px;
        max-height: 60px;
    }
}
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
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
                    <a href="inventory.php" class="nav-item">
                        <i class="nav-icon fas fa-boxes"></i>
                        <span>Inventory</span>
                    </a>
                    <a href="delivery-zones.php" class="nav-item active">
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
                </div>
                  <a href="#" class="nav-item" onclick="logout()" style="color: rgba(255, 255, 255, 0.9);">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">Delivery Zones</h1>
                        <p class="page-subtitle">Manage delivery zones and coverage areas</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="refreshZones()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" onclick="openZoneModal()">
                            <i class="fas fa-plus"></i>
                            Add Zone
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $totalZones ?></div>
                    <div class="stat-label">Total Zones</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $activeZones ?></div>
                    <div class="stat-label">Active Zones</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-mail-bulk"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $totalZipCodes ?></div>
                    <div class="stat-label">Zip Codes Covered</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $avgDeliveryTime ?> min</div>
                    <div class="stat-label">Avg Delivery Time</div>
                </div>
            </div>

            <!-- Coverage Check -->
            <div class="coverage-check">
                <h3 class="coverage-title">
                    <i class="fas fa-search"></i>
                    Check Delivery Coverage
                </h3>
                <form class="coverage-form" onsubmit="checkCoverage(event)">
                    <div class="form-group">
                        <label class="form-label">Zip Code</label>
                        <input type="text" id="coverageZipCode" class="form-control" placeholder="Enter zip code (e.g. 10110)" required>
                    </div>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-search"></i>
                        Check Coverage
                    </button>
                </form>
                <div id="coverageResult" class="coverage-result"></div>
            </div>

            <!-- Delivery Zones Grid -->
            <div class="zones-grid">
                <?php foreach ($deliveryZones as $zone): ?>
                    <?php 
                        $zipCodes = json_decode($zone['zip_codes'], true) ?: [];
                        $timeSlots = json_decode($zone['delivery_time_slots'], true) ?: [];
                        $isActive = $zone['is_active'];
                    ?>
                    <div class="zone-card <?= !$isActive ? 'inactive' : '' ?>" data-zone-id="<?= $zone['id'] ?>">
                        <div class="zone-header">
                            <div>
                                <h3 class="zone-title"><?= htmlspecialchars($zone['zone_name']) ?></h3>
                                <span class="zone-status <?= $isActive ? 'active' : 'inactive' ?>">
                                    <i class="fas fa-circle"></i>
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            <div class="zone-actions">
                                <button class="btn btn-icon btn-info" onclick="editZone('<?= $zone['id'] ?>')" title="Edit Zone">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-icon <?= $isActive ? 'btn-warning' : 'btn-success' ?>" 
                                        onclick="toggleZoneStatus('<?= $zone['id'] ?>')" 
                                        title="<?= $isActive ? 'Deactivate' : 'Activate' ?> Zone">
                                    <i class="fas fa-power-off"></i>
                                </button>
                                <button class="btn btn-icon btn-danger" onclick="deleteZone('<?= $zone['id'] ?>')" title="Delete Zone">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="zone-body">
                            <div class="zone-info">
                                <div class="zone-metric">
                                    <div class="zone-metric-value">₿<?= number_format($zone['delivery_fee'], 0) ?></div>
                                    <div class="zone-metric-label">Delivery Fee</div>
                                </div>
                                <div class="zone-metric">
                                    <div class="zone-metric-value"><?= $zone['estimated_delivery_time'] ?>min</div>
                                    <div class="zone-metric-label">Est. Time</div>
                                </div>
                                <div class="zone-metric">
                                    <div class="zone-metric-value"><?= count($zipCodes) ?></div>
                                    <div class="zone-metric-label">Zip Codes</div>
                                </div>
                                <div class="zone-metric">
                                    <div class="zone-metric-value"><?= $zone['max_orders_per_day'] ?></div>
                                    <div class="zone-metric-label">Max Orders/Day</div>
                                </div>
                            </div>
                            
                            <?php if ($zone['free_delivery_minimum']): ?>
                            <div class="mb-2">
                                <strong>Free Delivery:</strong> Orders ≥ ₿<?= number_format($zone['free_delivery_minimum'], 0) ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="zip-codes">
                                <div class="zip-codes-title">Covered Zip Codes</div>
                                <div class="zip-codes-list">
                                    <?php foreach ($zipCodes as $zipCode): ?>
                                        <span class="zip-code-tag"><?= htmlspecialchars($zipCode) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($timeSlots)): ?>
                            <div class="time-slots">
                                <div class="time-slots-title">Available Time Slots</div>
                                <div class="time-slots-list">
                                    <?php foreach ($timeSlots as $slot): ?>
                                        <span class="time-slot-tag"><?= htmlspecialchars($slot) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($deliveryZones)): ?>
                <div class="text-center" style="grid-column: 1 / -1; padding: 3rem;">
                    <i class="fas fa-map-marked-alt" style="font-size: 4rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--text-gray);">No delivery zones found</h3>
                    <p style="color: var(--text-gray); margin-bottom: 2rem;">Create your first delivery zone to start managing deliveries</p>
                    <button class="btn btn-primary" onclick="openZoneModal()">
                        <i class="fas fa-plus"></i>
                        Add First Zone
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Zone Modal -->
    <div id="zoneModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Delivery Zone</h3>
                <button class="modal-close" onclick="closeZoneModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="zoneForm" onsubmit="saveZone(event)">
                <div class="modal-body">
                    <input type="hidden" id="zoneId" name="zone_id">
                    
                    <div class="form-group">
                        <label class="form-label">Zone Name *</label>
                        <input type="text" id="zoneName" name="zone_name" class="form-control" placeholder="e.g. Central Bangkok" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Delivery Fee (THB)</label>
                            <input type="number" id="deliveryFee" name="delivery_fee" class="form-control" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Free Delivery Minimum (THB)</label>
                            <input type="number" id="freeDeliveryMinimum" name="free_delivery_minimum" class="form-control" min="0" step="0.01" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Estimated Delivery Time (minutes)</label>
                            <input type="number" id="estimatedTime" name="estimated_delivery_time" class="form-control" min="15" max="180" value="60">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Orders per Day</label>
                            <input type="number" id="maxOrders" name="max_orders_per_day" class="form-control" min="1" value="100">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Zip Codes *</label>
                        <input type="text" id="zipCodes" name="zip_codes" class="form-control" placeholder="10110, 10120, 10130" required>
                        <small class="text-muted">Separate multiple zip codes with commas</small>
                        <div class="invalid-feedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Available Time Slots</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="slot1" name="delivery_time_slots[]" value="09:00-12:00">
                                <label for="slot1">09:00-12:00</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="slot2" name="delivery_time_slots[]" value="12:00-15:00">
                                <label for="slot2">12:00-15:00</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="slot3" name="delivery_time_slots[]" value="15:00-18:00">
                                <label for="slot3">15:00-18:00</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="slot4" name="delivery_time_slots[]" value="18:00-21:00">
                                <label for="slot4">18:00-21:00</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeZoneModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Zone
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let currentEditingZone = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh every 30 seconds
            setInterval(refreshZones, 30000);
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    refreshZones();
                } else if (e.ctrlKey && e.key === 'n') {
                    e.preventDefault();
                    openZoneModal();
                } else if (e.key === 'Escape') {
                    closeZoneModal();
                }
            });
        });

        // Zone management functions
        function openZoneModal(zoneId = null) {
            const modal = document.getElementById('zoneModal');
            const modalTitle = document.getElementById('modalTitle');
            const form = document.getElementById('zoneForm');
            
            // Reset form
            form.reset();
            clearValidationErrors();
            
            if (zoneId) {
                modalTitle.textContent = 'Edit Delivery Zone';
                currentEditingZone = zoneId;
                loadZoneData(zoneId);
            } else {
                modalTitle.textContent = 'Add Delivery Zone';
                currentEditingZone = null;
                // Set default values
                document.getElementById('estimatedTime').value = 60;
                document.getElementById('maxOrders').value = 100;
            }
            
            modal.classList.add('show');
        }

        function closeZoneModal() {
            const modal = document.getElementById('zoneModal');
            modal.classList.remove('show');
            currentEditingZone = null;
        }

        function loadZoneData(zoneId) {
            const zoneCard = document.querySelector(`[data-zone-id="${zoneId}"]`);
            if (!zoneCard) return;
            
            // Extract data from zone card (you might want to fetch from server instead)
            const zoneName = zoneCard.querySelector('.zone-title').textContent;
            const zipCodes = Array.from(zoneCard.querySelectorAll('.zip-code-tag')).map(tag => tag.textContent).join(', ');
            
            document.getElementById('zoneId').value = zoneId;
            document.getElementById('zoneName').value = zoneName;
            document.getElementById('zipCodes').value = zipCodes;
            
            // You might want to fetch additional data via AJAX for editing
            fetchZoneDetails(zoneId);
        }

        function fetchZoneDetails(zoneId) {
            // This would typically fetch zone details from the server
            // For now, we'll simulate with the data we have
            showToast('Zone details loaded for editing', 'success');
        }

        function saveZone(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const action = currentEditingZone ? 'update_zone' : 'create_zone';
            formData.append('action', action);
            
            // Collect selected time slots
            const timeSlots = Array.from(document.querySelectorAll('input[name="delivery_time_slots[]"]:checked'))
                                  .map(input => input.value);
            formData.delete('delivery_time_slots[]');
            timeSlots.forEach(slot => formData.append('delivery_time_slots[]', slot));
            
            // Show loading state
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></i> Saving...';
            submitBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeZoneModal();
                    refreshZones();
                } else {
                    showToast(data.message, 'error');
                    if (data.field) {
                        showFieldError(data.field, data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Error saving zone:', error);
                showToast('Error saving zone. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        function editZone(zoneId) {
            openZoneModal(zoneId);
        }

        function toggleZoneStatus(zoneId) {
            if (!confirm('Are you sure you want to change the status of this zone?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('zone_id', zoneId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshZones();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error toggling zone status:', error);
                showToast('Error updating zone status. Please try again.', 'error');
            });
        }

        function deleteZone(zoneId) {
            if (!confirm('Are you sure you want to delete this delivery zone? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_zone');
            formData.append('zone_id', zoneId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshZones();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting zone:', error);
                showToast('Error deleting zone. Please try again.', 'error');
            });
        }

        function checkCoverage(event) {
            event.preventDefault();
            
            const zipCode = document.getElementById('coverageZipCode').value.trim();
            if (!zipCode) {
                showToast('Please enter a zip code', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'check_coverage');
            formData.append('zip_code', zipCode);
            
            const resultDiv = document.getElementById('coverageResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<i class="spinner"></i> Checking coverage...';
            resultDiv.className = 'coverage-result';
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.covered) {
                        resultDiv.className = 'coverage-result covered';
                        resultDiv.innerHTML = `
                            <i class="fas fa-check-circle"></i>
                            <strong>Coverage Available!</strong><br>
                            ${data.message}<br>
                            <small>Delivery Fee: ₿${data.zone.delivery_fee} | Est. Time: ${data.zone.estimated_delivery_time} mins</small>
                        `;
                    } else {
                        resultDiv.className = 'coverage-result not-covered';
                        resultDiv.innerHTML = `
                            <i class="fas fa-times-circle"></i>
                            <strong>No Coverage</strong><br>
                            ${data.message}
                        `;
                    }
                } else {
                    resultDiv.className = 'coverage-result not-covered';
                    resultDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${data.message}`;
                }
            })
            .catch(error => {
                console.error('Error checking coverage:', error);
                resultDiv.className = 'coverage-result not-covered';
                resultDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error checking coverage. Please try again.';
            });
        }

        function refreshZones() {
            showToast('Refreshing delivery zones...', 'success');
            window.location.reload();
        }

        // Utility functions
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            toast.innerHTML = `
                <div class="toast-header">
                    <div class="toast-title">${type === 'success' ? 'Success' : 'Error'}</div>
                    <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="toast-body">${message}</div>
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

        function showFieldError(fieldName, message) {
            const field = document.getElementById(fieldName) || document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.classList.add('is-invalid');
                const feedback = field.parentElement.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = message;
                }
            }
        }

        function clearValidationErrors() {
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            document.querySelectorAll('.invalid-feedback').forEach(feedback => {
                feedback.textContent = '';
            });
        }

        // Form validation
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                const feedback = e.target.parentElement.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = '';
                }
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('zoneModal');
            if (e.target === modal) {
                closeZoneModal();
            }
        });

        // Auto-format zip codes input
        document.getElementById('zipCodes').addEventListener('input', function(e) {
            let value = e.target.value;
            // Remove non-numeric characters except commas and spaces
            value = value.replace(/[^\d,\s]/g, '');
            // Clean up spacing around commas
            value = value.replace(/\s*,\s*/g, ', ');
            // Remove duplicate commas
            value = value.replace(/,+/g, ',');
            // Remove leading/trailing commas
            value = value.replace(/^,|,$/g, '');
            e.target.value = value;
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }

        // Add mobile menu button for small screens
        if (window.innerWidth <= 768) {
            const headerActions = document.querySelector('.header-actions');
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.className = 'btn btn-secondary d-md-none';
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.onclick = toggleSidebar;
            headerActions.insertBefore(mobileMenuBtn, headerActions.firstChild);
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('show');
            }
        });

        // Initialize tooltips (if you have a tooltip library)
        document.querySelectorAll('[title]').forEach(element => {
            element.setAttribute('data-toggle', 'tooltip');
        });

        // Performance optimization: Lazy load zone cards
        const observerOptions = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all zone cards
        document.querySelectorAll('.zone-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            observer.observe(card);
        });

        // Print functionality
        function printZones() {
            window.print();
        }

        // Add print styles
        const printStyles = `
            @media print {
                .sidebar, .header-actions, .zone-actions, .modal { display: none !important; }
                .main-content { margin-left: 0 !important; }
                .zone-card { break-inside: avoid; margin-bottom: 1rem; }
                .page-header { margin-bottom: 1rem; }
                .stats-grid { display: none; }
                body { background: white !important; }
            }
        `;
        const style = document.createElement('style');
        style.textContent = printStyles;
        document.head.appendChild(style);

        // Export zones data
        function exportZones() {
            const zones = [];
            document.querySelectorAll('.zone-card').forEach(card => {
                const zoneId = card.dataset.zoneId;
                const zoneName = card.querySelector('.zone-title').textContent;
                const zipCodes = Array.from(card.querySelectorAll('.zip-code-tag')).map(tag => tag.textContent);
                const status = card.querySelector('.zone-status').textContent.trim();
                
                zones.push({
                    id: zoneId,
                    name: zoneName,
                    zipCodes: zipCodes,
                    status: status
                });
            });
            
            const dataStr = JSON.stringify(zones, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'delivery-zones.json';
            link.click();
            URL.revokeObjectURL(url);
            
            showToast('Zones data exported successfully', 'success');
        }

        // Search functionality
        function filterZones(searchTerm) {
            const zones = document.querySelectorAll('.zone-card');
            zones.forEach(zone => {
                const zoneName = zone.querySelector('.zone-title').textContent.toLowerCase();
                const zipCodes = Array.from(zone.querySelectorAll('.zip-code-tag'))
                                      .map(tag => tag.textContent.toLowerCase())
                                      .join(' ');
                
                if (zoneName.includes(searchTerm.toLowerCase()) || zipCodes.includes(searchTerm.toLowerCase())) {
                    zone.style.display = 'block';
                } else {
                    zone.style.display = 'none';
                }
            });
        }

        // Add search input to header
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search zones...';
        searchInput.className = 'form-control';
        searchInput.style.width = '200px';
        searchInput.style.marginRight = '1rem';
        searchInput.addEventListener('input', (e) => filterZones(e.target.value));

        const headerActions = document.querySelector('.header-actions');
        headerActions.insertBefore(searchInput, headerActions.firstChild);

        console.log('Delivery Zones Management initialized successfully');
    </script>
</body>
</html>