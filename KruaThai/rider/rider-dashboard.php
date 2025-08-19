<?php
/**
 * Somdul Table - Enhanced Rider Dashboard
 * File: admin/rider-dashboard.php
 * Features: View daily assignments, update delivery status, add notes, upload photos
 * Status: PRODUCTION READY ✅
 * ENHANCED: Added note and photo functionality + DELIVERED STATUS UPDATE
 * 
 * UPDATED: Now updates subscription status to "delivered" when marking delivery as completed
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if a rider is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rider') {
    header("Location: ../login.php"); 
    exit();
}

// Database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("⚠ Database connection failed: " . $e->getMessage());
}

$rider_id = $_SESSION['user_id'];

// ======================================================================
// ENHANCED FUNCTIONS WITH DELIVERED STATUS UPDATE
// ======================================================================

function updateDeliveryStatus($pdo, $subscriptionId, $riderId, $newStatus, $notes = '', $photoPath = '') {
    // 🔧 DEBUG: Log the received values to understand the issue
    error_log("🔍 DEBUG - updateDeliveryStatus called with:");
    error_log("  - subscriptionId: '" . $subscriptionId . "' (type: " . gettype($subscriptionId) . ")");
    error_log("  - riderId: '" . $riderId . "'");
    error_log("  - newStatus: '" . $newStatus . "'");
    
    // 🔧 FIX: Ensure subscription ID is treated as string (UUID)
    $subscriptionId = (string) $subscriptionId;
    
    // Security check - ensure this subscription is assigned to this rider
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM subscriptions 
        WHERE CAST(id as CHAR) = ? AND assigned_rider_id = ? AND status IN ('active', 'delivered')
    ");
    $stmt->execute([$subscriptionId, $riderId]);
    $count = $stmt->fetchColumn();
    
    error_log("🔍 DEBUG - Security check result: " . $count . " matches found");
    
    if ($count == 0) {
        error_log("❌ SECURITY CHECK FAILED - No subscription found for ID: " . $subscriptionId . ", Rider: " . $riderId);
        return ['success' => false, 'message' => 'Authorization failed - delivery not assigned to you.'];
    }

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // 🔧 FIX: Update or insert delivery status record with proper UUID handling
        $stmt = $pdo->prepare("
            INSERT INTO delivery_status (subscription_id, rider_id, status, notes, photo_path, updated_at, created_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
            status = CASE WHEN VALUES(status) != '' THEN VALUES(status) ELSE status END,
            notes = CASE WHEN VALUES(notes) != '' THEN VALUES(notes) ELSE notes END,
            photo_path = CASE WHEN VALUES(photo_path) != '' THEN VALUES(photo_path) ELSE photo_path END,
            updated_at = NOW()
        ");
        $result = $stmt->execute([$subscriptionId, $riderId, $newStatus, $notes, $photoPath]);
        
        error_log("🔍 DEBUG - delivery_status insert/update result: " . ($result ? "SUCCESS" : "FAILED"));
        
        // ✅ NEW: Update subscription status to "delivered" when delivery is completed
        if ($newStatus === 'completed') {
            $stmt = $pdo->prepare("
                UPDATE subscriptions 
                SET status = 'delivered', delivered_at = NOW(), updated_at = NOW() 
                WHERE CAST(id as CHAR) = ? AND assigned_rider_id = ?
            ");
            $subscriptionResult = $stmt->execute([$subscriptionId, $riderId]);
            
            error_log("✅ Subscription {$subscriptionId} status updated to 'delivered' by rider {$riderId} - Result: " . ($subscriptionResult ? "SUCCESS" : "FAILED"));
        }
        
        // If status is changed back to pending/in_progress, revert subscription to active
        if (in_array($newStatus, ['pending', 'in_progress'])) {
            $stmt = $pdo->prepare("
                UPDATE subscriptions 
                SET status = 'active', delivered_at = NULL, updated_at = NOW() 
                WHERE CAST(id as CHAR) = ? AND assigned_rider_id = ? AND status = 'delivered'
            ");
            $revertResult = $stmt->execute([$subscriptionId, $riderId]);
            
            error_log("🔄 Subscription {$subscriptionId} status reverted to 'active' by rider {$riderId} - Result: " . ($revertResult ? "SUCCESS" : "FAILED"));
        }
        
        $pdo->commit();
        
        $statusMessage = $newStatus === 'completed' ? 
            'Delivery completed successfully! Subscription marked as delivered.' : 
            'Delivery status updated successfully.';
            
        return ['success' => true, 'message' => $statusMessage];
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("❌ Error updating delivery status: " . $e->getMessage());
        error_log("❌ Full error details: " . print_r($e, true));
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function uploadDeliveryPhoto($file, $subscriptionId, $riderId) {
    $uploadDir = '../uploads/delivery_photos/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Maximum 5MB allowed.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'delivery_' . $subscriptionId . '_' . $riderId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => 'uploads/delivery_photos/' . $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file.'];
    }
}

function getUpcomingDeliveryDays($weeks = 4) {
    $deliveryDays = [];
    $today = new DateTime();
    for ($i = 0; $i < ($weeks * 7); $i++) {
        $day = (clone $today)->modify("+$i days");
        $dayOfWeek = $day->format('N');
        if (in_array($dayOfWeek, [3, 6])) { // Wednesday and Saturday
            if (!in_array($day->format('Y-m-d'), array_column($deliveryDays, 'date'))) {
                $deliveryDays[] = ['date' => $day->format('Y-m-d'), 'display' => $day->format('D, M j')];
            }
        }
    }
    return $deliveryDays;
}

// ENHANCED AJAX Request Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'update_delivery_status':
                $subscriptionId = $_POST['subscription_id'] ?? '';
                $status = $_POST['status'] ?? '';
                $notes = $_POST['notes'] ?? '';
                $photoPath = '';
                
                // Handle photo upload if present
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadDeliveryPhoto($_FILES['photo'], $subscriptionId, $rider_id);
                    if ($uploadResult['success']) {
                        $photoPath = $uploadResult['path'];
                    } else {
                        echo json_encode($uploadResult);
                        exit;
                    }
                }
                
                $result = updateDeliveryStatus($pdo, $subscriptionId, $rider_id, $status, $notes, $photoPath);
                echo json_encode($result);
                break;
                
            case 'add_delivery_note':
                $subscriptionId = $_POST['subscription_id'] ?? '';
                $notes = $_POST['notes'] ?? '';
                
                $result = updateDeliveryStatus($pdo, $subscriptionId, $rider_id, '', $notes, '');
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action.']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    }
    exit();
}

// ======================================================================
// DATA FETCHING FOR PAGE LOAD
// ======================================================================

// Use $_GET['date'] to allow date selection, default to today
$deliveryDate = $_GET['date'] ?? date('Y-m-d');

try {
    // Get Rider's info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$rider_id]);
    $rider = $stmt->fetch(PDO::FETCH_ASSOC);

    // 🔧 DEBUG: Check what subscriptions exist for this rider
    error_log("🔍 DEBUG - Checking subscriptions for rider: " . $rider_id);
    $debugStmt = $pdo->prepare("
        SELECT id, user_id, status, assigned_rider_id, delivery_days, start_date,
               CHAR_LENGTH(id) as id_length,
               HEX(id) as id_hex
        FROM subscriptions 
        WHERE assigned_rider_id = ?
    ");
    $debugStmt->execute([$rider_id]);
    $debugSubs = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("🔍 DEBUG - Found " . count($debugSubs) . " subscriptions for rider " . $rider_id);
    foreach ($debugSubs as $index => $sub) {
        error_log("🔍 DEBUG - Subscription $index:");
        error_log("  - id: '" . $sub['id'] . "' (length: " . $sub['id_length'] . ")");
        error_log("  - hex: " . $sub['id_hex']);
        error_log("  - status: " . $sub['status']);
        error_log("  - delivery_days: " . ($sub['delivery_days'] ?? 'NULL'));
    }

    // ENHANCED: Get assigned deliveries with status information
    // Updated to include both 'active' and 'delivered' subscriptions for full visibility
    // 🔧 FIX: Ensure subscription_id is properly cast as CHAR for UUID matching
    $stmt = $pdo->prepare("
        SELECT 
            s.id as subscription_id,
            CAST(s.id as CHAR) as subscription_id_cast,
            CHAR_LENGTH(s.id) as id_length,
            s.user_id,
            s.total_amount,
            s.preferred_delivery_time,
            s.status as subscription_status,
            s.delivered_at,
            s.delivery_days,
            u.first_name, 
            u.last_name, 
            u.phone, 
            u.zip_code,
            u.delivery_address,
            u.city,
            u.state,
            GREATEST(1, ROUND(COALESCE(s.total_amount, 0) / 15)) as total_items,
            ds.status as delivery_status,
            ds.notes as delivery_notes,
            ds.photo_path as delivery_photo,
            ds.updated_at as status_updated_at
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN delivery_status ds ON CAST(s.id as CHAR) = CAST(ds.subscription_id as CHAR) AND ds.rider_id = ?
        WHERE s.assigned_rider_id = ? 
        AND s.status IN ('active', 'delivered')
        AND (
            s.delivery_days LIKE ? 
            OR s.delivery_days LIKE ?
            OR s.delivery_days IS NULL 
            OR s.delivery_days = ''
        )
        ORDER BY 
            CASE s.status WHEN 'delivered' THEN 1 ELSE 0 END,
            COALESCE(ds.status, 'pending'), 
            u.zip_code, 
            u.last_name
    ");
    
    $dayOfWeek = strtolower(date('l', strtotime($deliveryDate)));
    $dayOfWeekParam = "%$dayOfWeek%";
    $dateParam = "%$deliveryDate%";
    
    $stmt->execute([$rider_id, $rider_id, $dayOfWeekParam, $dateParam]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔧 DEBUG: Log what we actually got from the database
    error_log("🔍 DEBUG - Database query returned " . count($deliveries) . " deliveries");
    foreach ($deliveries as $index => $delivery) {
        error_log("🔍 DEBUG - Delivery $index:");
        error_log("  - subscription_id: '" . $delivery['subscription_id'] . "' (type: " . gettype($delivery['subscription_id']) . ", length: " . strlen($delivery['subscription_id']) . ")");
        error_log("  - user_id: '" . $delivery['user_id'] . "'");
        error_log("  - customer: " . $delivery['first_name'] . " " . $delivery['last_name']);
        error_log("  - Raw subscription_id: " . var_export($delivery['subscription_id'], true));
    }

} catch (Exception $e) {
    die("Error fetching rider data: " . $e->getMessage());
}

// Process stats
$stats = [
    'total' => count($deliveries), 
    'completed' => 0, 
    'pending' => 0, 
    'in_progress' => 0,
    'delivered' => 0,
    'total_items' => 0
];

foreach($deliveries as $delivery) {
    $stats['total_items'] += $delivery['total_items'];
    $status = $delivery['delivery_status'] ?? 'pending';
    $subscriptionStatus = $delivery['subscription_status'];
    
    // Count delivered subscriptions separately
    if ($subscriptionStatus === 'delivered') {
        $stats['delivered']++;
    } else {
        switch ($status) {
            case 'completed':
                $stats['completed']++;
                break;
            case 'in_progress':
                $stats['in_progress']++;
                break;
            default:
                $stats['pending']++;
                break;
        }
    }
}

$shopLocation = [ 'lat' => 33.888121, 'lng' => -117.868256, 'name' => 'Somdul Table Restaurant' ];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard - Somdul Table</title>
    <link href="https://ydpschool.com/fonts/" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
        }
        
        :root {
            --cream: #ece8e1; --sage: #adb89d; --brown: #bd9379; --curry: #cf723a; --white: #ffffff;
            --text-dark: #2c3e50; --text-gray: #7f8c8d; --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05); --radius-md: 12px; --transition: all 0.3s ease;
            --success: #28a745; --warning: #ffc107; --danger: #dc3545; --info: #17a2b8; --delivered: #6f42c1;
        }
        
        body { 
            font-family: 'BaticaSans', Arial, sans-serif; 
            background-color: var(--cream); 
            margin: 0; 
            line-height: 1.6;
        }
        
        .rider-layout { display: flex; min-height: 100vh; }
        
        .sidebar { 
            width: 280px; 
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%); 
            color: var(--white); 
            position: fixed; 
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header { 
            padding: 2rem; 
            text-align: center; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
        }
        
        .logo-image { max-width: 80px; margin-bottom: 1rem; }
        .sidebar-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        
        .sidebar-nav { padding: 1rem 0; }
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
        }
        .nav-item.active { font-weight: 600; }
        .nav-icon { width: 24px; text-align: center; }

        .main-content { 
            margin-left: 280px; 
            flex: 1; 
            padding: 2rem; 
            min-height: 100vh;
        }
        
        .page-header { 
            background: var(--white); 
            padding: 2rem; 
            border-radius: var(--radius-md); 
            box-shadow: var(--shadow-soft); 
            margin-bottom: 2rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 2rem; 
        }
        
        .stat-card { 
            background: var(--white); 
            padding: 1.5rem; 
            border-radius: var(--radius-md); 
            text-align: center; 
            box-shadow: var(--shadow-soft);
            border-top: 4px solid var(--curry);
        }
        
        .stat-card.delivered { border-top-color: var(--delivered); }
        
        .stat-value { 
            font-size: 2.5rem; 
            font-weight: 700; 
            color: var(--curry); 
            margin-bottom: 0.5rem;
        }
        
        .stat-card.delivered .stat-value { color: var(--delivered); }
        
        .stat-label { 
            font-size: 0.9rem; 
            color: var(--text-gray);
            text-transform: uppercase;
        }
        
        .delivery-list-container { 
            background: var(--white); 
            border-radius: var(--radius-md); 
            box-shadow: var(--shadow-soft); 
        }
        
        .delivery-list-header { 
            padding: 1.5rem; 
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, var(--sage), var(--brown));
            color: var(--white);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
        }
        
        .delivery-list { 
            padding: 1rem; 
        }
        
        .delivery-card { 
            border: 1px solid var(--border-light); 
            border-radius: 12px; 
            margin-bottom: 1.5rem; 
            overflow: hidden;
            transition: var(--transition);
            background: var(--white);
        }
        
        .delivery-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .delivery-card[data-status="completed"] { 
            background-color: #f0fdf4; 
            border-left: 4px solid var(--success);
        }
        
        .delivery-card[data-status="in_progress"] { 
            background-color: #fffbeb; 
            border-left: 4px solid var(--warning);
        }
        
        .delivery-card[data-status="pending"] { 
            background-color: #fef2f2; 
            border-left: 4px solid var(--danger);
        }
        
        .delivery-card[data-subscription-status="delivered"] { 
            background-color: #f3e8ff; 
            border-left: 4px solid var(--delivered);
        }
        
        .delivery-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            background: #f8f9fa;
        }
        
        .customer-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.5rem;
        }
        
        .delivery-details {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
        }
        
        .delivery-info {
            color: var(--text-gray);
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-completed { background: var(--success); color: white; }
        .status-in_progress { background: var(--warning); color: white; }
        .status-pending { background: var(--danger); color: white; }
        .status-delivered { background: var(--delivered); color: white; }
        
        .subscription-status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }
        
        .sub-status-delivered { 
            background: var(--delivered); 
            color: white; 
        }
        
        .delivery-actions {
            padding: 1.5rem;
            background: #f8f9fa;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        
        .action-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-complete {
            background: var(--success);
            color: white;
        }
        
        .btn-progress {
            background: var(--warning);
            color: white;
        }
        
        .btn-pending {
            background: var(--danger);
            color: white;
        }
        
        .btn-notes {
            background: var(--info);
            color: white;
        }
        
        .btn-photo {
            background: var(--brown);
            color: white;
        }
        
        .btn-delivered-disabled {
            background: #6c757d;
            color: white;
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .action-btn:hover:not(.btn-delivered-disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .delivery-notes {
            margin-top: 1rem;
            padding: 1rem;
            background: #e3f2fd;
            border-radius: 8px;
            border-left: 4px solid var(--info);
        }
        
        .delivery-photo {
            margin-top: 1rem;
            text-align: center;
        }
        
        .delivery-photo img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: var(--shadow-soft);
            cursor: pointer;
        }
        
        .delivery-timestamp {
            background: #e8f5e8;
            padding: 0.8rem;
            border-radius: 8px;
            border-left: 4px solid var(--success);
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .date-selector select { 
            padding: 0.75rem; 
            border-radius: 8px; 
            border: 2px solid var(--sage); 
            font-family: 'BaticaSans', Arial, sans-serif;
            font-size: 0.9rem;
        }
        
        .loading-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(255,255,255,0.9); 
            z-index: 9999; 
            display: none; 
            align-items: center; 
            justify-content: center; 
        }
        
        .loading-content {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: var(--radius-md);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-group textarea,
        .form-group input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-family: 'BaticaSans', Arial, sans-serif;
            font-size: 0.9rem;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-save {
            background: var(--success);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .rider-layout { flex-direction: column; }
            .sidebar { 
                width: 100%; 
                height: auto; 
                position: relative; 
            }
            .main-content { 
                margin-left: 0; 
                padding: 1rem; 
            }
            .page-header { 
                flex-direction: column; 
                gap: 1rem; 
                text-align: center; 
            }
            .stats-grid { 
                grid-template-columns: repeat(2, 1fr); 
            }
            .delivery-actions {
                flex-direction: column;
            }
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="rider-layout">
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/image/LOGO_White Trans.png" alt="Somdul Table Logo" class="logo-image">
                <div class="sidebar-title">Rider Dashboard</div>
                <p>Welcome, <?= htmlspecialchars($rider['first_name']) ?>!</p>
            </div>
            <nav class="sidebar-nav">
                <a href="rider-dashboard.php" class="nav-item active">
                    <i class="nav-icon fas fa-tachometer-alt"></i><span>My Deliveries</span>
                </a>
                <a href="../logout.php" class="nav-item" style="margin-top: 2rem;">
                    <i class="nav-icon fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </nav>
        </div>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>My Deliveries - <?= date("l, j F Y", strtotime($deliveryDate)) ?></h1>
                    <p style="color: var(--text-gray);">Manage your assigned deliveries safely and efficiently!</p>
                </div>
                <div class="header-actions">
                    <form method="GET" class="date-selector">
                        <label for="date-select" style="font-weight: 500;">Select Date:</label>
                        <select name="date" id="date-select" onchange="this.form.submit()">
                            <?php foreach (getUpcomingDeliveryDays() as $day): ?>
                                <option value="<?= $day['date'] ?>" <?= $day['date'] == $deliveryDate ? 'selected' : '' ?>><?= $day['display'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Deliveries</div>
                </div>
                <div class="stat-card delivered">
                    <div class="stat-value"><?= $stats['delivered'] ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['completed'] ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['in_progress'] ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['pending'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_items'] ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>

            <div class="delivery-list-container">
                <div class="delivery-list-header">
                    <h3><i class="fas fa-list-check"></i> Today's Delivery Schedule</h3>
                </div>
                <div class="delivery-list" id="delivery-list">
                    <?php if (empty($deliveries)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-gray);">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h4>No deliveries assigned for this date</h4>
                            <p>Check with your manager or select a different date.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($deliveries as $delivery): ?>
                            <?php 
                            $status = $delivery['delivery_status'] ?? 'pending';
                            $subscriptionStatus = $delivery['subscription_status'];
                            $statusClass = 'status-' . $status;
                            $isDelivered = ($subscriptionStatus === 'delivered');
                            ?>
                            <div class="delivery-card" id="delivery-<?= $delivery['subscription_id'] ?>" 
                                 data-status="<?= $status ?>" 
                                 data-subscription-status="<?= $subscriptionStatus ?>">
                                <div class="delivery-header">
                                    <div class="delivery-details">
                                        <div>
                                            <div class="customer-name">
                                                <?= htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']) ?>
                                                <?php if ($isDelivered): ?>
                                                    <span class="subscription-status-badge sub-status-delivered">
                                                        <i class="fas fa-check-circle"></i> DELIVERED
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="delivery-info">
                                                <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($delivery['delivery_address'] . ', ' . $delivery['city'] . ', ' . $delivery['state'] . ' ' . $delivery['zip_code']) ?></div>
                                                <div><i class="fas fa-phone"></i> <a href="tel:<?= htmlspecialchars($delivery['phone']) ?>"><?= htmlspecialchars($delivery['phone']) ?></a></div>
                                                <div><i class="fas fa-box"></i> <?= $delivery['total_items'] ?> items</div>
                                                <?php if ($delivery['preferred_delivery_time']): ?>
                                                    <div><i class="fas fa-clock"></i> <?= htmlspecialchars($delivery['preferred_delivery_time']) ?></div>
                                                <?php endif; ?>
                                                <?php if ($delivery['status_updated_at']): ?>
                                                    <div><i class="fas fa-history"></i> Updated: <?= date('g:i A', strtotime($delivery['status_updated_at'])) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="status-badge <?= $isDelivered ? 'status-delivered' : $statusClass ?>">
                                                <?= $isDelivered ? 'Delivered' : ucfirst(str_replace('_', ' ', $status)) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="delivery-actions">
                                    <?php if (!$isDelivered): ?>
                                        <!-- 🔧 DEBUG: Show what subscription_id is being passed -->
                                        <div style="background: #ffe6e6; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                            <strong>🔍 ACTION DEBUG:</strong><br>
                                            Passing subscription_id: <code><?= htmlspecialchars($delivery['subscription_id']) ?></code><br>
                                            Length: <?= strlen($delivery['subscription_id']) ?><br>
                                            Raw value: <code><?= var_export($delivery['subscription_id'], true) ?></code>
                                        </div>
                                        
                                        <button class="action-btn btn-complete" 
                                                onclick="updateDeliveryStatus('<?= htmlspecialchars($delivery['subscription_id']) ?>', 'completed')"
                                                data-subscription-id="<?= htmlspecialchars($delivery['subscription_id']) ?>">
                                            <i class="fas fa-check-circle"></i> Mark Complete
                                        </button>
                                        <button class="action-btn btn-progress" 
                                                onclick="updateDeliveryStatus('<?= htmlspecialchars($delivery['subscription_id']) ?>', 'in_progress')"
                                                data-subscription-id="<?= htmlspecialchars($delivery['subscription_id']) ?>">
                                            <i class="fas fa-clock"></i> In Progress
                                        </button>
                                        
                                        <?php if ($status === 'completed'): ?>
                                            <button class="action-btn btn-pending" 
                                                    onclick="updateDeliveryStatus('<?= htmlspecialchars($delivery['subscription_id']) ?>', 'pending')"
                                                    data-subscription-id="<?= htmlspecialchars($delivery['subscription_id']) ?>">
                                                <i class="fas fa-undo"></i> Mark Pending
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="action-btn btn-delivered-disabled" disabled>
                                            <i class="fas fa-check-double"></i> Already Delivered
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="action-btn btn-notes" 
                                            onclick="openNotesModal('<?= htmlspecialchars($delivery['subscription_id']) ?>', '<?= htmlspecialchars($delivery['delivery_notes'] ?? '', ENT_QUOTES) ?>')"
                                            data-subscription-id="<?= htmlspecialchars($delivery['subscription_id']) ?>">
                                        <i class="fas fa-sticky-note"></i> <?= $delivery['delivery_notes'] ? 'Edit Notes' : 'Add Notes' ?>
                                    </button>
                                    
                                    <button class="action-btn btn-photo" 
                                            onclick="openPhotoModal('<?= htmlspecialchars($delivery['subscription_id']) ?>')"
                                            data-subscription-id="<?= htmlspecialchars($delivery['subscription_id']) ?>">
                                        <i class="fas fa-camera"></i> <?= $delivery['delivery_photo'] ? 'Update Photo' : 'Add Photo' ?>
                                    </button>
                                </div>
                                
                                <?php if ($delivery['delivered_at']): ?>
                                    <div class="delivery-timestamp">
                                        <strong><i class="fas fa-check-circle"></i> Delivered Successfully:</strong>
                                        <p style="margin: 0.3rem 0 0 0;"><?= date('F j, Y \a\t g:i A', strtotime($delivery['delivered_at'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($delivery['delivery_notes']): ?>
                                    <div class="delivery-notes">
                                        <strong><i class="fas fa-sticky-note"></i> Delivery Notes:</strong>
                                        <p style="margin: 0.5rem 0 0 0;"><?= nl2br(htmlspecialchars($delivery['delivery_notes'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($delivery['delivery_photo']): ?>
                                    <div class="delivery-photo">
                                        <strong><i class="fas fa-camera"></i> Delivery Photo:</strong><br>
                                        <img src="../<?= htmlspecialchars($delivery['delivery_photo']) ?>" alt="Delivery Photo" onclick="openImageModal('../<?= htmlspecialchars($delivery['delivery_photo']) ?>')">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>Processing...</h3>
        </div>
    </div>

    <!-- Notes Modal -->
    <div id="notesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-sticky-note"></i> Delivery Notes</h3>
                <span class="close" onclick="closeNotesModal()">&times;</span>
            </div>
            <form id="notesForm">
                <input type="hidden" id="notesSubscriptionId" name="subscription_id">
                <div class="form-group">
                    <label for="deliveryNotes">Add notes about this delivery:</label>
                    <textarea id="deliveryNotes" name="notes" placeholder="e.g., Left at front door, handed to neighbor, special instructions followed..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeNotesModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Notes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Photo Modal -->
    <div id="photoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Delivery Photo</h3>
                <span class="close" onclick="closePhotoModal()">&times;</span>
            </div>
            <form id="photoForm" enctype="multipart/form-data">
                <input type="hidden" id="photoSubscriptionId" name="subscription_id">
                <div class="form-group">
                    <label for="deliveryPhoto">Upload photo showing where you placed the delivery:</label>
                    <input type="file" id="deliveryPhoto" name="photo" accept="image/*" capture="environment">
                    <small style="color: var(--text-gray);">Supported formats: JPG, PNG, GIF (Max 5MB)</small>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closePhotoModal()">Cancel</button>
                    <button type="submit" class="btn-save">Upload Photo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <div class="modal-header">
                <h3><i class="fas fa-image"></i> Delivery Photo</h3>
                <span class="close" onclick="closeImageModal()">&times;</span>
            </div>
            <div style="text-align: center;">
                <img id="modalImage" src="" alt="Delivery Photo" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ======================================================================
        // ENHANCED DELIVERY MANAGEMENT FUNCTIONS WITH DELIVERED STATUS
        // ======================================================================
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // Update delivery status
        function updateDeliveryStatus(subscriptionId, newStatus) {
            const statusText = newStatus.replace('_', ' ');
            let confirmText = `Mark this delivery as ${statusText}?`;
            
            if (newStatus === 'completed') {
                confirmText = 'Mark this delivery as completed? This will update the subscription status to "delivered".';
            }
            
            Swal.fire({
                title: 'Update Delivery Status',
                text: confirmText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: getStatusColor(newStatus),
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, mark as ${statusText}`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    performStatusUpdate(subscriptionId, newStatus);
                }
            });
        }
        
        function performStatusUpdate(subscriptionId, status) {
            // 🔧 DEBUG: Log what we're sending
            console.log('🔍 DEBUG - performStatusUpdate called with:');
            console.log('  - subscriptionId:', subscriptionId, '(type:', typeof subscriptionId, ')');
            console.log('  - status:', status);
            
            // 🔧 FIX: Ensure subscription ID is a string
            subscriptionId = String(subscriptionId);
            
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'update_delivery_status');
            formData.append('subscription_id', subscriptionId);
            formData.append('status', status);
            
            // 🔧 DEBUG: Log FormData contents
            console.log('📤 Sending FormData:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value} (type: ${typeof value})`);
            }
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                console.log('📥 Response received:', data);
                
                if (data.success) {
                    let title = 'Status Updated!';
                    if (status === 'completed') {
                        title = 'Delivery Completed! ✅';
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: title,
                        text: data.message,
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        // Refresh the page to show updated status
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('❌ Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to update status. Please try again.'
                });
            });
        }
        
        function getStatusColor(status) {
            switch (status) {
                case 'completed': return '#28a745';
                case 'in_progress': return '#ffc107';
                case 'pending': return '#dc3545';
                default: return '#17a2b8';
            }
        }
        
        // ======================================================================
        // NOTES MODAL FUNCTIONS
        // ======================================================================
        
        function openNotesModal(subscriptionId, currentNotes = '') {
            // 🔧 DEBUG: Log subscription ID
            console.log('🔍 DEBUG - openNotesModal called with subscriptionId:', subscriptionId, '(type:', typeof subscriptionId, ')');
            
            // 🔧 FIX: Ensure subscription ID is a string
            subscriptionId = String(subscriptionId);
            
            document.getElementById('notesSubscriptionId').value = subscriptionId;
            document.getElementById('deliveryNotes').value = currentNotes;
            document.getElementById('notesModal').style.display = 'block';
            document.getElementById('deliveryNotes').focus();
        }
        
        function closeNotesModal() {
            document.getElementById('notesModal').style.display = 'none';
            document.getElementById('notesForm').reset();
        }
        
        // Handle notes form submission
        document.getElementById('notesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_delivery_note');
            
            // 🔧 DEBUG: Log FormData for notes
            console.log('📤 Sending Notes FormData:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value} (type: ${typeof value})`);
            }
            
            showLoading();
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                console.log('📥 Notes Response received:', data);
                
                if (data.success) {
                    closeNotesModal();
                    Swal.fire({
                        icon: 'success',
                        title: 'Notes Saved!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Save Failed',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('❌ Notes Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to save notes. Please try again.'
                });
            });
        });
        
        // ======================================================================
        // PHOTO MODAL FUNCTIONS
        // ======================================================================
        
        function openPhotoModal(subscriptionId) {
            // 🔧 DEBUG: Log subscription ID
            console.log('🔍 DEBUG - openPhotoModal called with subscriptionId:', subscriptionId, '(type:', typeof subscriptionId, ')');
            
            // 🔧 FIX: Ensure subscription ID is a string
            subscriptionId = String(subscriptionId);
            
            document.getElementById('photoSubscriptionId').value = subscriptionId;
            document.getElementById('photoModal').style.display = 'block';
        }
        
        function closePhotoModal() {
            document.getElementById('photoModal').style.display = 'none';
            document.getElementById('photoForm').reset();
        }
        
        // Handle photo form submission
        document.getElementById('photoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('deliveryPhoto');
            const file = fileInput.files[0];
            
            if (!file) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Photo Selected',
                    text: 'Please select a photo to upload.'
                });
                return;
            }
            
            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'Please select a photo smaller than 5MB.'
                });
                return;
            }
            
            // Validate file type
            if (!file.type.startsWith('image/')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Type',
                    text: 'Please select a valid image file (JPG, PNG, GIF).'
                });
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'update_delivery_status');
            formData.append('status', ''); // Don't change status, just add photo
            
            // 🔧 DEBUG: Log FormData for photo upload
            console.log('📤 Sending Photo FormData:');
            for (let [key, value] of formData.entries()) {
                if (key === 'photo') {
                    console.log(`  ${key}: File(${value.name}, ${value.size} bytes) (type: ${typeof value})`);
                } else {
                    console.log(`  ${key}: ${value} (type: ${typeof value})`);
                }
            }
            
            showLoading();
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                console.log('📥 Photo Response received:', data);
                
                if (data.success) {
                    closePhotoModal();
                    Swal.fire({
                        icon: 'success',
                        title: 'Photo Uploaded!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('❌ Photo Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to upload photo. Please try again.'
                });
            });
        });
        
        // ======================================================================
        // IMAGE VIEWER MODAL
        // ======================================================================
        
        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').style.display = 'block';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.getElementById('modalImage').src = '';
        }
        
        // ======================================================================
        // MODAL EVENT HANDLERS
        // ======================================================================
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = ['notesModal', 'photoModal', 'imageModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeNotesModal();
                closePhotoModal();
                closeImageModal();
            }
        });
        
        // ======================================================================
        // INITIALIZATION
        // ======================================================================
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Enhanced Rider Dashboard loaded successfully');
            console.log('📱 Features: Status updates, Notes, Photo uploads, Delivered status tracking');
            
            // Auto-refresh page every 5 minutes to get latest assignments
            setInterval(() => {
                console.log('🔄 Auto-refreshing deliveries...');
                window.location.reload();
            }, 5 * 60 * 1000);
        });
        
        // Service Worker for offline capability (optional enhancement)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../sw.js').then(() => {
                console.log('📱 Service Worker registered for offline support');
            }).catch(err => {
                console.log('⚠ Service Worker registration failed:', err);
            });
        }
    </script>
</body>
</html>