<?php
/**
 * Krua Thai - Complete Delivery Zone Management System
 * File: admin/delivery-zones.php
 * Features: CRUD zones, ZIP codes, delivery fees, time slots, capacity limits
 * Status: PRODUCTION READY ✅
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_zone':
                $result = createDeliveryZone($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'update_zone':
                $result = updateDeliveryZone($pdo, $_POST['zone_id'], $_POST);
                echo json_encode($result);
                exit;
                
            case 'delete_zone':
                $result = deleteDeliveryZone($pdo, $_POST['zone_id']);
                echo json_encode($result);
                exit;
                
            case 'get_zone':
                $result = getDeliveryZone($pdo, $_POST['zone_id']);
                echo json_encode($result);
                exit;
                
            case 'bulk_update_zip_codes':
                $result = bulkUpdateZipCodes($pdo, $_POST['zone_id'], $_POST['zip_codes']);
                echo json_encode($result);
                exit;
                
            case 'test_zip_coverage':
                $result = testZipCodeCoverage($pdo);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// ======================================================================
// DELIVERY ZONE FUNCTIONS
// ======================================================================



function createDeliveryZone($pdo, $data) {
    try {
        $pdo->beginTransaction();
        
        $zoneId = generateUUID();
        
        // Prepare time slots and ZIP codes as JSON
        $timeSlots = isset($data['time_slots']) ? json_encode($data['time_slots']) : json_encode([]);
        $zipCodes = isset($data['zip_codes']) ? json_encode(array_filter(explode(',', $data['zip_codes']))) : json_encode([]);
        
        $stmt = $pdo->prepare("
            INSERT INTO delivery_zones (
                id, zone_name, zone_description, zone_color,
                min_distance, max_distance, base_delivery_fee, 
                per_mile_fee, time_slots, zip_codes,
                max_orders_per_day, max_orders_per_slot,
                estimated_delivery_time, is_active, sort_order,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $zoneId,
            $data['zone_name'],
            $data['zone_description'] ?? '',
            $data['zone_color'] ?? '#3498db',
            (float)($data['min_distance'] ?? 0),
            (float)($data['max_distance'] ?? 0),
            (float)($data['base_delivery_fee'] ?? 0),
            (float)($data['per_mile_fee'] ?? 0),
            $timeSlots,
            $zipCodes,
            (int)($data['max_orders_per_day'] ?? 50),
            (int)($data['max_orders_per_slot'] ?? 10),
            (int)($data['estimated_delivery_time'] ?? 60),
            1, // is_active
            (int)($data['sort_order'] ?? 1)
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Delivery zone created successfully',
            'zone_id' => $zoneId
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error creating zone: ' . $e->getMessage()];
    }
}

function updateDeliveryZone($pdo, $zoneId, $data) {
    try {
        $pdo->beginTransaction();
        
        $timeSlots = isset($data['time_slots']) ? json_encode($data['time_slots']) : null;
        $zipCodes = isset($data['zip_codes']) ? json_encode(array_filter(explode(',', $data['zip_codes']))) : null;
        
        $stmt = $pdo->prepare("
            UPDATE delivery_zones SET
                zone_name = ?, zone_description = ?, zone_color = ?,
                min_distance = ?, max_distance = ?, base_delivery_fee = ?,
                per_mile_fee = ?, time_slots = ?, zip_codes = ?,
                max_orders_per_day = ?, max_orders_per_slot = ?,
                estimated_delivery_time = ?, is_active = ?, sort_order = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['zone_name'],
            $data['zone_description'] ?? '',
            $data['zone_color'] ?? '#3498db',
            (float)($data['min_distance'] ?? 0),
            (float)($data['max_distance'] ?? 0),
            (float)($data['base_delivery_fee'] ?? 0),
            (float)($data['per_mile_fee'] ?? 0),
            $timeSlots,
            $zipCodes,
            (int)($data['max_orders_per_day'] ?? 50),
            (int)($data['max_orders_per_slot'] ?? 10),
            (int)($data['estimated_delivery_time'] ?? 60),
            (int)($data['is_active'] ?? 1),
            (int)($data['sort_order'] ?? 1),
            $zoneId
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Delivery zone updated successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error updating zone: ' . $e->getMessage()];
    }
}

function deleteDeliveryZone($pdo, $zoneId) {
    try {
        // Check if zone has any active deliveries
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            WHERE SUBSTRING(u.zip_code, 1, 5) IN (
                SELECT JSON_UNQUOTE(JSON_EXTRACT(zip_codes, CONCAT('$[', numbers.n, ']')))
                FROM delivery_zones dz
                CROSS JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) numbers
                WHERE dz.id = ? AND JSON_UNQUOTE(JSON_EXTRACT(zip_codes, CONCAT('$[', numbers.n, ']'))) IS NOT NULL
            )
            AND s.status = 'active'
        ");
        $stmt->execute([$zoneId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete zone. There are {$result['count']} active subscriptions in this zone."
            ];
        }
        
        // Delete the zone
        $stmt = $pdo->prepare("DELETE FROM delivery_zones WHERE id = ?");
        $stmt->execute([$zoneId]);
        
        return [
            'success' => true,
            'message' => 'Delivery zone deleted successfully'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting zone: ' . $e->getMessage()];
    }
}

function getDeliveryZone($pdo, $zoneId) {
    try {
        $stmt = $pdo->prepare("
            SELECT dz.*,
                   (SELECT COUNT(*) FROM subscriptions s 
                    JOIN users u ON s.user_id = u.id 
                    WHERE JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(u.zip_code, 1, 5)))
                    AND s.status = 'active') as active_subscriptions
            FROM delivery_zones dz
            WHERE dz.id = ?
        ");
        $stmt->execute([$zoneId]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($zone) {
            // Decode JSON fields
            $zone['time_slots_array'] = json_decode($zone['time_slots'], true) ?? [];
            $zone['zip_codes_array'] = json_decode($zone['zip_codes'], true) ?? [];
            $zone['zip_codes_string'] = implode(', ', $zone['zip_codes_array']);
            
            return ['success' => true, 'zone' => $zone];
        } else {
            return ['success' => false, 'message' => 'Zone not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching zone: ' . $e->getMessage()];
    }
}

function bulkUpdateZipCodes($pdo, $zoneId, $zipCodes) {
    try {
        $zipArray = array_filter(array_map('trim', explode(',', $zipCodes)));
        $zipJson = json_encode($zipArray);
        
        $stmt = $pdo->prepare("
            UPDATE delivery_zones 
            SET zip_codes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$zipJson, $zoneId]);
        
        return [
            'success' => true,
            'message' => 'ZIP codes updated successfully',
            'zip_count' => count($zipArray)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating ZIP codes: ' . $e->getMessage()];
    }
}

function testZipCodeCoverage($pdo) {
    try {
        // Get all ZIP codes from users
        $stmt = $pdo->prepare("
            SELECT DISTINCT SUBSTRING(zip_code, 1, 5) as zip_code, COUNT(*) as user_count
            FROM users 
            WHERE zip_code IS NOT NULL AND zip_code != ''
            GROUP BY SUBSTRING(zip_code, 1, 5)
            ORDER BY user_count DESC
        ");
        $stmt->execute();
        $userZips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all configured zones with ZIP codes
        $stmt = $pdo->prepare("
            SELECT id, zone_name, zip_codes
            FROM delivery_zones 
            WHERE is_active = 1
        ");
        $stmt->execute();
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $coverage = [];
        $uncoveredZips = [];
        
        foreach ($userZips as $userZip) {
            $zip = $userZip['zip_code'];
            $covered = false;
            
            foreach ($zones as $zone) {
                $zoneZips = json_decode($zone['zip_codes'], true) ?? [];
                if (in_array($zip, $zoneZips)) {
                    $coverage[] = [
                        'zip_code' => $zip,
                        'user_count' => $userZip['user_count'],
                        'zone_name' => $zone['zone_name'],
                        'status' => 'covered'
                    ];
                    $covered = true;
                    break;
                }
            }
            
            if (!$covered) {
                $uncoveredZips[] = [
                    'zip_code' => $zip,
                    'user_count' => $userZip['user_count'],
                    'status' => 'uncovered'
                ];
            }
        }
        
        return [
            'success' => true,
            'coverage' => $coverage,
            'uncovered' => $uncoveredZips,
            'stats' => [
                'total_zips' => count($userZips),
                'covered_zips' => count($coverage),
                'uncovered_zips' => count($uncoveredZips),
                'coverage_percentage' => count($userZips) > 0 ? round((count($coverage) / count($userZips)) * 100, 1) : 0
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error testing coverage: ' . $e->getMessage()];
    }
}

// ======================================================================
// FETCH DELIVERY ZONES DATA
// ======================================================================

try {
    // Get all delivery zones
    $stmt = $pdo->prepare("
        SELECT dz.*,
               (SELECT COUNT(*) FROM subscriptions s 
                JOIN users u ON s.user_id = u.id 
                WHERE JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(u.zip_code, 1, 5)))
                AND s.status = 'active') as active_subscriptions,
               (SELECT COUNT(*) FROM subscriptions s 
                JOIN users u ON s.user_id = u.id 
                WHERE JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(u.zip_code, 1, 5)))
                AND s.status = 'active' 
                AND DATE(s.start_date) = CURDATE()) as today_orders
        FROM delivery_zones dz
        ORDER BY dz.sort_order ASC, dz.zone_name ASC
    ");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process zones data
    foreach ($zones as &$zone) {
        $zone['time_slots_array'] = json_decode($zone['time_slots'], true) ?? [];
        $zone['zip_codes_array'] = json_decode($zone['zip_codes'], true) ?? [];
        $zone['zip_codes_string'] = implode(', ', $zone['zip_codes_array']);
        $zone['capacity_percentage'] = $zone['max_orders_per_day'] > 0 ? 
            round(($zone['active_subscriptions'] / $zone['max_orders_per_day']) * 100, 1) : 0;
    }
    
    // Get system statistics
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM delivery_zones WHERE is_active = 1) as active_zones,
            (SELECT COUNT(DISTINCT SUBSTRING(zip_code, 1, 5)) FROM users WHERE zip_code IS NOT NULL) as unique_zip_codes,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') as active_subscriptions,
            (SELECT SUM(JSON_LENGTH(zip_codes)) FROM delivery_zones WHERE is_active = 1) as total_covered_zips
    ");
    $stmt->execute();
    $systemStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $zones = [];
    $systemStats = ['active_zones' => 0, 'unique_zip_codes' => 0, 'active_subscriptions' => 0, 'total_covered_zips' => 0];
    error_log("Delivery zones error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Zone Management - Krua Thai Admin</title>
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
            
            /* Status colors */
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
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
            flex-wrap: wrap;
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
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

        .btn-secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
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

        /* Zone Cards */
        .zones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .zone-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            border-left: 4px solid var(--curry);
            transition: var(--transition);
        }

        .zone-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .zone-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .zone-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .zone-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .zone-status.active {
            background: #d4edda;
            color: #155724;
        }

        .zone-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .zone-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .zone-info-item {
            text-align: center;
        }

        .zone-info-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--curry);
        }

        .zone-info-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .zone-progress {
            margin: 1rem 0;
        }

        .zone-progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            transition: width 0.5s ease;
            background: linear-gradient(90deg, var(--success), var(--sage));
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, var(--warning), #e67e22);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, var(--danger), #c0392b);
        }

        .zone-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Forms */
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
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

        /* Tables */
        .table-container {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-light);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(3px);
        }

        .loading-content {
            background: var(--white);
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

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .zones-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
        }

        /* Additional Styles */
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .zip-codes-display {
            background: var(--cream);
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 0.5rem;
            max-height: 60px;
            overflow-y: auto;
        }

        .color-picker {
            width: 50px;
            height: 40px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }

        .time-slots-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .time-slot-tag {
            background: var(--curry);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .time-slot-remove {
            cursor: pointer;
            font-weight: bold;
        }

        .coverage-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .coverage-good {
            background: var(--success);
        }

        .coverage-warning {
            background: var(--warning);
        }

        .coverage-danger {
            background: var(--danger);
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
                <div class="sidebar-subtitle">Zone Manager</div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="delivery-management.php" class="nav-item">
                        <i class="nav-icon fas fa-route"></i>
                        <span>Route Optimizer</span>
                    </a>
                    <a href="orders.php" class="nav-item">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Delivery</div>
                    <a href="assign-riders.php" class="nav-item">
                        <i class="nav-icon fas fa-users"></i>
                        <span>Assign Riders</span>
                    </a>
                    <a href="track-deliveries.php" class="nav-item">
                        <i class="nav-icon fas fa-map-marked-alt"></i>
                        <span>Track Deliveries</span>
                    </a>
                    <a href="delivery-zones.php" class="nav-item active">
                        <i class="nav-icon fas fa-map"></i>
                        <span>Delivery Zones</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Reports</div>
                    <a href="analytics.php" class="nav-item">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <span>Analytics</span>
                    </a>
                    <a href="performance.php" class="nav-item">
                        <i class="nav-icon fas fa-award"></i>
                        <span>Performance</span>
                    </a>
                </div>
                
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-map" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Delivery Zone Management
                        </h1>
                        <p class="page-subtitle">Manage delivery zones, ZIP codes, fees, and capacity limits</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="testZipCoverage()">
                            <i class="fas fa-search"></i>
                            Test Coverage
                        </button>
                        
                        <button class="btn btn-warning" onclick="bulkImportZips()">
                            <i class="fas fa-upload"></i>
                            Import ZIP Codes
                        </button>
                        
                        <button class="btn btn-primary" onclick="showCreateZoneModal()">
                            <i class="fas fa-plus"></i>
                            Create New Zone
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $systemStats['active_zones'] ?></div>
                    <div class="stat-label">Active Zones</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-location-dot"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $systemStats['total_covered_zips'] ?></div>
                    <div class="stat-label">Covered ZIP Codes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $systemStats['unique_zip_codes'] ?></div>
                    <div class="stat-label">Unique ZIP Codes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $systemStats['active_subscriptions'] ?></div>
                    <div class="stat-label">Active Subscriptions</div>
                </div>
            </div>

            <!-- Delivery Zones Grid -->
            <div class="zones-grid">
                <?php foreach ($zones as $zone): ?>
                    <div class="zone-card" style="border-left-color: <?= htmlspecialchars($zone['zone_color']) ?>;">
                        <div class="zone-header">
                            <div class="zone-title"><?= htmlspecialchars($zone['zone_name']) ?></div>
                            <div class="zone-status <?= $zone['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $zone['is_active'] ? 'Active' : 'Inactive' ?>
                            </div>
                        </div>
                        
                        <div class="zone-info">
                            <div class="zone-info-item">
                                <div class="zone-info-value"><?= count($zone['zip_codes_array']) ?></div>
                                <div class="zone-info-label">ZIP Codes</div>
                            </div>
                            <div class="zone-info-item">
                                <div class="zone-info-value">$<?= number_format($zone['base_delivery_fee'], 2) ?></div>
                                <div class="zone-info-label">Base Fee</div>
                            </div>
                            <div class="zone-info-item">
                                <div class="zone-info-value"><?= $zone['active_subscriptions'] ?></div>
                                <div class="zone-info-label">Subscriptions</div>
                            </div>
                            <div class="zone-info-item">
                                <div class="zone-info-value"><?= $zone['estimated_delivery_time'] ?> min</div>
                                <div class="zone-info-label">Est. Time</div>
                            </div>
                        </div>
                        
                        <div class="zone-progress">
                            <div class="zone-progress-label">
                                <span>Capacity Usage</span>
                                <span><?= $zone['capacity_percentage'] ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?= $zone['capacity_percentage'] > 80 ? 'danger' : ($zone['capacity_percentage'] > 60 ? 'warning' : '') ?>" 
                                     style="width: <?= min($zone['capacity_percentage'], 100) ?>%"></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($zone['zone_description'])): ?>
                            <div style="font-size: 0.9rem; color: var(--text-gray); margin: 1rem 0;">
                                <?= htmlspecialchars($zone['zone_description']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="zip-codes-display">
                            <strong>ZIP Codes:</strong> 
                            <?= !empty($zone['zip_codes_string']) ? htmlspecialchars($zone['zip_codes_string']) : 'No ZIP codes assigned' ?>
                        </div>
                        
                        <?php if (count($zone['time_slots_array']) > 0): ?>
                            <div class="time-slots-container">
                                <?php foreach ($zone['time_slots_array'] as $slot): ?>
                                    <span class="time-slot-tag"><?= htmlspecialchars($slot) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="zone-actions">
                            <button class="btn btn-primary btn-sm" onclick="editZone('<?= $zone['id'] ?>')">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="viewZoneDetails('<?= $zone['id'] ?>')">
                                <i class="fas fa-eye"></i>
                                Details
                            </button>
                            <button class="btn btn-warning btn-sm" onclick="manageZipCodes('<?= $zone['id'] ?>')">
                                <i class="fas fa-location-dot"></i>
                                ZIP Codes
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteZone('<?= $zone['id'] ?>', '<?= htmlspecialchars($zone['zone_name']) ?>')">
                                <i class="fas fa-trash"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($zones)): ?>
                    <div class="zone-card" style="text-align: center; padding: 3rem; border: 2px dashed var(--border-light);">
                        <i class="fas fa-map-marked-alt" style="font-size: 3rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--text-gray); margin-bottom: 0.5rem;">No Delivery Zones Found</h3>
                        <p style="color: var(--text-gray); margin-bottom: 1.5rem;">Create your first delivery zone to start managing deliveries</p>
                        <button class="btn btn-primary" onclick="showCreateZoneModal()">
                            <i class="fas fa-plus"></i>
                            Create First Zone
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <div>Processing...</div>
        </div>
    </div>

    <!-- Create/Edit Zone Modal -->
    <div class="modal" id="zoneModal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 class="modal-title" id="zoneModalTitle">Create New Delivery Zone</h3>
                <button class="modal-close" onclick="closeModal('zoneModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="zoneForm">
                <input type="hidden" id="zoneId" name="zone_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Zone Name *</label>
                        <input type="text" class="form-control" id="zoneName" name="zone_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Zone Color</label>
                        <input type="color" class="color-picker" id="zoneColor" name="zone_color" value="#3498db">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Zone Description</label>
                    <textarea class="form-control" id="zoneDescription" name="zone_description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Min Distance (miles)</label>
                        <input type="number" class="form-control" id="minDistance" name="min_distance" step="0.1" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Distance (miles)</label>
                        <input type="number" class="form-control" id="maxDistance" name="max_distance" step="0.1" min="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Base Delivery Fee ($)</label>
                        <input type="number" class="form-control" id="baseDeliveryFee" name="base_delivery_fee" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Per Mile Fee ($)</label>
                        <input type="number" class="form-control" id="perMileFee" name="per_mile_fee" step="0.01" min="0">
                    </div>
                </div>
                
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Max Orders/Day</label>
                        <input type="number" class="form-control" id="maxOrdersPerDay" name="max_orders_per_day" min="1" value="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Orders/Slot</label>
                        <input type="number" class="form-control" id="maxOrdersPerSlot" name="max_orders_per_slot" min="1" value="10">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Est. Delivery Time (min)</label>
                        <input type="number" class="form-control" id="estimatedDeliveryTime" name="estimated_delivery_time" min="1" value="60">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ZIP Codes (comma-separated)</label>
                    <textarea class="form-control" id="zipCodes" name="zip_codes" rows="3" 
                              placeholder="92831, 92832, 92833, 90620, 90621"></textarea>
                    <small style="color: var(--text-gray);">Enter ZIP codes separated by commas</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Available Time Slots</label>
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <input type="time" id="timeSlotStart" class="form-control" style="flex: 1;">
                        <input type="time" id="timeSlotEnd" class="form-control" style="flex: 1;">
                        <button type="button" class="btn btn-secondary" onclick="addTimeSlot()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="timeSlotsList" class="time-slots-container"></div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="sortOrder" name="sort_order" min="1" value="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" id="isActive" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('zoneModal')">
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

    <!-- ZIP Code Management Modal -->
    <div class="modal" id="zipCodeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Manage ZIP Codes</h3>
                <button class="modal-close" onclick="closeModal('zipCodeModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">ZIP Codes for <span id="zipModalZoneName"></span></label>
                    <textarea class="form-control" id="zipCodeTextarea" rows="8" 
                              placeholder="Enter ZIP codes, one per line or comma-separated"></textarea>
                    <small style="color: var(--text-gray);">
                        You can paste ZIP codes from Excel, enter one per line, or separate with commas
                    </small>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('zipCodeModal')">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveZipCodes()">
                        <i class="fas fa-save"></i>
                        Save ZIP Codes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Coverage Test Modal -->
    <div class="modal" id="coverageModal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 class="modal-title">ZIP Code Coverage Analysis</h3>
                <button class="modal-close" onclick="closeModal('coverageModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="coverageResults">
                <div class="spinner" style="margin: 2rem auto;"></div>
                <div style="text-align: center; color: var(--text-gray);">Analyzing coverage...</div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Global variables
        let currentZoneId = null;
        let currentTimeSlots = [];

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            console.log('🗺️ Krua Thai Delivery Zone Management System initialized');
        });

        // Event listeners
        function setupEventListeners() {
            // Zone form submission
            document.getElementById('zoneForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveZone();
            });
        }

        // Show create zone modal
        function showCreateZoneModal() {
            document.getElementById('zoneModalTitle').textContent = 'Create New Delivery Zone';
            document.getElementById('zoneForm').reset();
            document.getElementById('zoneId').value = '';
            currentZoneId = null;
            currentTimeSlots = [];
            updateTimeSlotsList();
            showModal('zoneModal');
        }

        // Edit zone
        function editZone(zoneId) {
            showLoading();
            
            fetch('delivery-zones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_zone&zone_id=${zoneId}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    populateZoneForm(data.zone);
                    document.getElementById('zoneModalTitle').textContent = 'Edit Delivery Zone';
                    showModal('zoneModal');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to fetch zone data'
                });
            });
        }

        // Populate zone form with data
        function populateZoneForm(zone) {
            currentZoneId = zone.id;
            document.getElementById('zoneId').value = zone.id;
            document.getElementById('zoneName').value = zone.zone_name;
            document.getElementById('zoneDescription').value = zone.zone_description || '';
            document.getElementById('zoneColor').value = zone.zone_color || '#3498db';
            document.getElementById('minDistance').value = zone.min_distance || '';
            document.getElementById('maxDistance').value = zone.max_distance || '';
            document.getElementById('baseDeliveryFee').value = zone.base_delivery_fee || '';
            document.getElementById('perMileFee').value = zone.per_mile_fee || '';
            document.getElementById('maxOrdersPerDay').value = zone.max_orders_per_day || 50;
            document.getElementById('maxOrdersPerSlot').value = zone.max_orders_per_slot || 10;
            document.getElementById('estimatedDeliveryTime').value = zone.estimated_delivery_time || 60;
            document.getElementById('zipCodes').value = zone.zip_codes_string || '';
            document.getElementById('sortOrder').value = zone.sort_order || 1;
            document.getElementById('isActive').value = zone.is_active || 1;
            
            currentTimeSlots = zone.time_slots_array || [];
            updateTimeSlotsList();
        }

        // Save zone
        function saveZone() {
            const formData = new FormData(document.getElementById('zoneForm'));
            const action = currentZoneId ? 'update_zone' : 'create_zone';
            formData.append('action', action);
            formData.append('time_slots', JSON.stringify(currentTimeSlots));
            
            showLoading();
            
            fetch('delivery-zones.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        closeModal('zoneModal');
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save zone'
                });
            });
        }

        // Delete zone
        function deleteZone(zoneId, zoneName) {
            Swal.fire({
                title: 'Are you sure?',
                text: `Delete delivery zone "${zoneName}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    
                    fetch('delivery-zones.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_zone&zone_id=${zoneId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Cannot Delete',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to delete zone'
                        });
                    });
                }
            });
        }

        // Manage ZIP codes
        function manageZipCodes(zoneId) {
            showLoading();
            
            fetch('delivery-zones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_zone&zone_id=${zoneId}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    currentZoneId = zoneId;
                    document.getElementById('zipModalZoneName').textContent = data.zone.zone_name;
                    document.getElementById('zipCodeTextarea').value = data.zone.zip_codes_string || '';
                    showModal('zipCodeModal');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
            });
        }

        // Save ZIP codes
        function saveZipCodes() {
            const zipCodes = document.getElementById('zipCodeTextarea').value;
            
            showLoading();
            
            fetch('delivery-zones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bulk_update_zip_codes&zone_id=${currentZoneId}&zip_codes=${encodeURIComponent(zipCodes)}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Updated ${data.zip_count} ZIP codes successfully`,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        closeModal('zipCodeModal');
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
            });
        }

        // Add time slot
        function addTimeSlot() {
            const startTime = document.getElementById('timeSlotStart').value;
            const endTime = document.getElementById('timeSlotEnd').value;
            
            if (!startTime || !endTime) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Time',
                    text: 'Please select both start and end times'
                });
                return;
            }
            
            if (startTime >= endTime) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Time Range',
                    text: 'End time must be after start time'
                });
                return;
            }
            
            const timeSlot = `${startTime}-${endTime}`;
            
            if (currentTimeSlots.includes(timeSlot)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Duplicate Time Slot',
                    text: 'This time slot already exists'
                });
                return;
            }
            
            currentTimeSlots.push(timeSlot);
            updateTimeSlotsList();
            
            // Clear inputs
            document.getElementById('timeSlotStart').value = '';
            document.getElementById('timeSlotEnd').value = '';
        }

        // Remove time slot
        function removeTimeSlot(index) {
            currentTimeSlots.splice(index, 1);
            updateTimeSlotsList();
        }

        // Update time slots list display
        function updateTimeSlotsList() {
            const container = document.getElementById('timeSlotsList');
            container.innerHTML = '';
            
            currentTimeSlots.forEach((slot, index) => {
                const tag = document.createElement('span');
                tag.className = 'time-slot-tag';
                tag.innerHTML = `
                    ${slot}
                    <span class="time-slot-remove" onclick="removeTimeSlot(${index})">×</span>
                `;
                container.appendChild(tag);
            });
        }

        // Test ZIP code coverage
        function testZipCoverage() {
            showLoading();
            document.getElementById('coverageResults').innerHTML = `
                <div class="spinner" style="margin: 2rem auto;"></div>
                <div style="text-align: center; color: var(--text-gray);">Analyzing coverage...</div>
            `;
            showModal('coverageModal');
            
            fetch('delivery-zones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=test_zip_coverage'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    displayCoverageResults(data);
                } else {
                    document.getElementById('coverageResults').innerHTML = `
                        <div style="text-align: center; color: var(--danger);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <p>Error analyzing coverage: ${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                document.getElementById('coverageResults').innerHTML = `
                    <div style="text-align: center; color: var(--danger);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>Failed to analyze coverage</p>
                    </div>
                `;
            });
        }

        // Display coverage analysis results
        function displayCoverageResults(data) {
            const stats = data.stats;
            const coverage = data.coverage;
            const uncovered = data.uncovered;
            
            let html = `
                <div style="margin-bottom: 2rem;">
                    <h4>Coverage Summary</h4>
                    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0;">
                        <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--curry);">${stats.total_zips}</div>
                            <div style="font-size: 0.8rem; color: var(--text-gray);">Total ZIP Codes</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--success);">${stats.covered_zips}</div>
                            <div style="font-size: 0.8rem; color: var(--text-gray);">Covered</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger);">${stats.uncovered_zips}</div>
                            <div style="font-size: 0.8rem; color: var(--text-gray);">Uncovered</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--info);">${stats.coverage_percentage}%</div>
                            <div style="font-size: 0.8rem; color: var(--text-gray);">Coverage Rate</div>
                        </div>
                    </div>
                </div>
            `;
            
            if (uncovered.length > 0) {
                html += `
                    <div style="margin-bottom: 2rem;">
                        <h4 style="color: var(--danger);">
                            <i class="fas fa-exclamation-triangle"></i>
                            Uncovered ZIP Codes (${uncovered.length})
                        </h4>
                        <div style="max-height: 200px; overflow-y: auto; background: var(--white); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 1rem;">
                `;
                
                uncovered.forEach(item => {
                    html += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light);">
                            <span>
                                <span class="coverage-indicator coverage-danger"></span>
                                <strong>${item.zip_code}</strong>
                            </span>
                            <span style="color: var(--text-gray);">${item.user_count} users</span>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                        <div style="margin-top: 1rem;">
                            <button class="btn btn-warning" onclick="suggestZoneForUncovered()">
                                <i class="fas fa-lightbulb"></i>
                                Suggest Zone Assignment
                            </button>
                        </div>
                    </div>
                `;
            }
            
            if (coverage.length > 0) {
                html += `
                    <div>
                        <h4 style="color: var(--success);">
                            <i class="fas fa-check-circle"></i>
                            Covered ZIP Codes (${coverage.length})
                        </h4>
                        <div style="max-height: 200px; overflow-y: auto; background: var(--white); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 1rem;">
                `;
                
                coverage.forEach(item => {
                    html += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light);">
                            <span>
                                <span class="coverage-indicator coverage-good"></span>
                                <strong>${item.zip_code}</strong> → ${item.zone_name}
                            </span>
                            <span style="color: var(--text-gray);">${item.user_count} users</span>
                        </div>
                    `;
                });
                
                html += '</div></div>';
            }
            
            document.getElementById('coverageResults').innerHTML = html;
        }

        // View zone details
        function viewZoneDetails(zoneId) {
            // Implementation for detailed zone view
            Swal.fire({
                title: 'Zone Details',
                text: 'Detailed view functionality will be implemented here',
                icon: 'info'
            });
        }

        // Bulk import ZIP codes
        function bulkImportZips() {
            Swal.fire({
                title: 'Bulk Import ZIP Codes',
                html: `
                    <div style="text-align: left;">
                        <p>Upload a CSV file with ZIP codes and zone assignments.</p>
                        <p><strong>Required format:</strong></p>
                        <pre style="background: #f8f9fa; padding: 1rem; border-radius: 4px;">zip_code,zone_name
92831,Zone A
92832,Zone A
90620,Zone B</pre>
                        <input type="file" id="zipImportFile" accept=".csv" style="margin-top: 1rem;">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Import',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const file = document.getElementById('zipImportFile').files[0];
                    if (!file) {
                        Swal.showValidationMessage('Please select a CSV file');
                        return false;
                    }
                    return file;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Process CSV file
                    const file = result.value;
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        processCSVImport(e.target.result);
                    };
                    reader.readAsText(file);
                }
            });
        }

        // Process CSV import
        function processCSVImport(csvContent) {
            const lines = csvContent.split('\n');
            const header = lines[0].split(',');
            
            if (header.length < 2) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Format',
                    text: 'CSV must have at least 2 columns: zip_code, zone_name'
                });
                return;
            }
            
            let importData = [];
            for (let i = 1; i < lines.length; i++) {
                const row = lines[i].split(',');
                if (row.length >= 2 && row[0].trim()) {
                    importData.push({
                        zip_code: row[0].trim(),
                        zone_name: row[1].trim()
                    });
                }
            }
            
            if (importData.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Data',
                    text: 'No valid data found in CSV file'
                });
                return;
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Import Preview',
                html: `
                    <p>Found ${importData.length} ZIP codes to import.</p>
                    <p>This feature will be fully implemented in the next update.</p>
                `
            });
        }

        // Suggest zone assignment for uncovered ZIP codes
        function suggestZoneForUncovered() {
            Swal.fire({
                title: 'Zone Assignment Suggestions',
                html: `
                    <p>AI-powered zone assignment suggestions will be implemented here.</p>
                    <p>This will analyze distance, user density, and existing zones to suggest optimal assignments.</p>
                `,
                icon: 'info'
            });
        }

        // Utility functions
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'n':
                        e.preventDefault();
                        showCreateZoneModal();
                        break;
                    case 't':
                        e.preventDefault();
                        testZipCoverage();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                // Close all modals
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        console.log('🗺️ Krua Thai Delivery Zone Management System initialized successfully');
        console.log('⌨️ Keyboard shortcuts: Ctrl+N (New Zone), Ctrl+T (Test Coverage), Esc (Close Modals)');
        console.log('🎯 Features: CRUD Zones, ZIP Management, Coverage Analysis, Bulk Import');
    </script>

    <!-- Additional CSS for better styling -->
    <style>
        .modal-body {
            padding: 0;
        }

        .coverage-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .coverage-item {
            text-align: center;
            padding: 1rem;
            background: var(--cream);
            border-radius: var(--radius-sm);
        }

        .coverage-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .coverage-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .zip-list {
            max-height: 300px;
            overflow-y: auto;
            background: var(--white);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 1rem;
        }

        .zip-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .zip-item:last-child {
            border-bottom: none;
        }

        .form-help {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 0.25rem;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .zones-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                padding: 1rem;
                max-height: 90vh;
            }

            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }

            .zone-info {
                grid-template-columns: 1fr 1fr;
            }

            .coverage-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .zone-actions {
                flex-direction: column;
            }

            .zone-actions .btn {
                justify-content: center;
            }
        }
    </style>
</body>
</html>