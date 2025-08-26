<?php
/**
 * Order Management Dashboard - Somdul Table
 * File: manage_order.php
 * Role: admin only
 * Status: PRODUCTION READY ✅
 * Focus: Manage subscription orders and status updates
 * Language: English
 * Timezone: America/New_York
 * 
 * 🎯 FEATURES:
 * - Simple order management interface
 * - Bulk confirm orders to "in the kitchen" status
 * - Individual order cancellation with reasons
 * - Undo/rollback functionality for safety
 * - Date selection for delivery management
 * 
 * 🛡️ SAFE DATABASE UPDATES:
 * - Automatically checks if required columns exist
 * - Creates missing columns safely without affecting existing data
 * - Works with existing database structure
 * - Graceful fallback if database permissions are insufficient
 * 
 * 📝 DATABASE CHANGES (Applied automatically):
 * - Adds 'user_status' column to subscription_menus (if missing)
 * - Adds 'cancel_reason' column to subscription_menus (if missing)
 * - Adds 'cancelled_at' column to subscription_menus (if missing)  
 * - Adds 'cancelled_by' column to subscription_menus (if missing)
 */

// Start output buffering to prevent header issues
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session FIRST
session_start();

// Set timezone to US Eastern
date_default_timezone_set('America/New_York');

// Role-based access control - Admin only
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if user has admin role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 10px; margin: 20px; font-family: Arial;">
        <h3>🚫 Access Denied</h3>
        <p>You do not have permission to access the order management dashboard.</p>
        <p>Required role: Admin</p>
        <a href="../admin/dashboard.php" style="background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>
    </div>');
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Database connection with PDO
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Get upcoming delivery days (Wednesday & Saturday) for next 4 weeks
function getUpcomingDeliveryDays($weeks = 4) {
    $deliveryDays = [];
    $today = new DateTime();
    $today->setTimezone(new DateTimeZone('America/New_York'));
    
    for ($week = 0; $week < $weeks; $week++) {
        // Find Wednesday of the week
        $wednesday = clone $today;
        $wednesday->modify("+" . $week . " weeks");
        $wednesday->modify("wednesday this week");
        
        // Find Saturday of the week
        $saturday = clone $today;
        $saturday->modify("+" . $week . " weeks");
        $saturday->modify("saturday this week");
        
        // Add only future dates
        if ($wednesday >= $today) {
            $deliveryDays[] = [
                'date' => $wednesday->format('Y-m-d'),
                'display' => 'Wednesday ' . $wednesday->format('m/d/Y')
            ];
        }
        
        if ($saturday >= $today) {
            $deliveryDays[] = [
                'date' => $saturday->format('Y-m-d'),
                'display' => 'Saturday ' . $saturday->format('m/d/Y')
            ];
        }
    }
    
    return $deliveryDays;
}

// Check if selected date is valid delivery date
function isValidDeliveryDate($date) {
    $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 3=Wednesday, 6=Saturday
    return in_array($dayOfWeek, [3, 6]); // Only Wednesday(3) and Saturday(6)
}

// Check if required columns exist and create them if necessary
function checkAndCreateColumns($pdo) {
    try {
        // Check subscription_menus columns
        $check_user_status = $pdo->query("SHOW COLUMNS FROM subscription_menus LIKE 'user_status'");
        if ($check_user_status->rowCount() === 0) {
            $pdo->exec("ALTER TABLE subscription_menus ADD COLUMN user_status ENUM('order received', 'in the kitchen', 'delivering', 'completed', 'cancelled') DEFAULT 'order received' AFTER status");
        }
        
        $check_cancel_reason = $pdo->query("SHOW COLUMNS FROM subscription_menus LIKE 'cancel_reason'");
        if ($check_cancel_reason->rowCount() === 0) {
            $pdo->exec("ALTER TABLE subscription_menus ADD COLUMN cancel_reason TEXT NULL AFTER user_status");
        }
        
        $check_cancelled_at = $pdo->query("SHOW COLUMNS FROM subscription_menus LIKE 'cancelled_at'");
        if ($check_cancelled_at->rowCount() === 0) {
            $pdo->exec("ALTER TABLE subscription_menus ADD COLUMN cancelled_at TIMESTAMP NULL AFTER cancel_reason");
        }
        
        $check_cancelled_by = $pdo->query("SHOW COLUMNS FROM subscription_menus LIKE 'cancelled_by'");
        if ($check_cancelled_by->rowCount() === 0) {
            $pdo->exec("ALTER TABLE subscription_menus ADD COLUMN cancelled_by INT NULL AFTER cancelled_at");
        }
        
        // Check subscription table columns
        $check_subscription_user_status = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'user_status'");
        if ($check_subscription_user_status->rowCount() === 0) {
            $pdo->exec("ALTER TABLE subscriptions ADD COLUMN user_status ENUM('order received', 'in the kitchen', 'delivering', 'completed', 'cancelled') DEFAULT 'order received'");
        }
        
        $check_subscription_cancel_reason = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'cancellation_reason'");
        if ($check_subscription_cancel_reason->rowCount() === 0) {
            $pdo->exec("ALTER TABLE subscriptions ADD COLUMN cancellation_reason TEXT NULL");
        }
        
        $check_subscription_cancelled_at = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'cancelled_at'");
        if ($check_subscription_cancelled_at->rowCount() === 0) {
            $pdo->exec("ALTER TABLE subscriptions ADD COLUMN cancelled_at TIMESTAMP NULL");
        }
        
        $check_subscription_cancelled_by = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'cancelled_by'");
        if ($check_subscription_cancelled_by->rowCount() === 0) {
            $pdo->exec("ALTER TABLE subscriptions ADD COLUMN cancelled_by INT NULL");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Column creation error: " . $e->getMessage());
        return false;
    }
}

// Check and create columns automatically (safe operation)
$columns_ready = checkAndCreateColumns($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    ob_end_clean(); // Clear any previous output
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (!$columns_ready) {
            throw new Exception('Database schema is not ready. Please check database permissions.');
        }
        
        switch ($action) {
            case 'confirm_all_orders':
                $selected_date = $_POST['date'] ?? '';
                
                if (empty($selected_date)) {
                    throw new Exception('Date is required');
                }
                
                // Store previous states for undo functionality (from both tables)
                $prev_states_query = "
                    SELECT sm.id, 
                           COALESCE(sm.user_status, 'order received') as user_status, 
                           sm.subscription_id, s.user_id, 
                           u.first_name, u.last_name,
                           s.status as subscription_status,
                           COALESCE(s.user_status, 'order received') as subscription_user_status
                    FROM subscription_menus sm
                    JOIN subscriptions s ON sm.subscription_id = s.id
                    JOIN users u ON s.user_id = u.id
                    WHERE sm.delivery_date = ?
                    AND sm.status = 'scheduled'
                    AND s.status = 'active'
                ";
                
                $stmt = $pdo->prepare($prev_states_query);
                $stmt->execute([$selected_date]);
                $previous_states = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($previous_states)) {
                    throw new Exception('No orders found for the selected date');
                }
                
                // Store undo information in session
                $_SESSION['undo_data'] = [
                    'type' => 'confirm_all',
                    'date' => $selected_date,
                    'timestamp' => time(),
                    'states' => $previous_states
                ];
                
                // Start transaction for both table updates
                $pdo->beginTransaction();
                
                try {
                    // Update subscription_menus to "in the kitchen"
                    $update_menus_query = "
                        UPDATE subscription_menus sm
                        JOIN subscriptions s ON sm.subscription_id = s.id
                        SET sm.user_status = 'in the kitchen',
                            sm.updated_at = CURRENT_TIMESTAMP
                        WHERE sm.delivery_date = ?
                        AND sm.status = 'scheduled'
                        AND s.status = 'active'
                        AND COALESCE(sm.user_status, 'order received') = 'order received'
                    ";
                    
                    $stmt = $pdo->prepare($update_menus_query);
                    $stmt->execute([$selected_date]);
                    $menu_count = $stmt->rowCount();
                    
                    // Get distinct subscription IDs that were affected
                    $get_subscription_ids_query = "
                        SELECT DISTINCT sm.subscription_id
                        FROM subscription_menus sm
                        JOIN subscriptions s ON sm.subscription_id = s.id
                        WHERE sm.delivery_date = ?
                        AND sm.status = 'scheduled'
                        AND s.status = 'active'
                        AND sm.user_status = 'in the kitchen'
                    ";
                    
                    $stmt = $pdo->prepare($get_subscription_ids_query);
                    $stmt->execute([$selected_date]);
                    $subscription_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $subscription_count = 0;
                    
                    // Update each affected subscription
                    if (!empty($subscription_ids)) {
                        $update_subscriptions_query = "
                            UPDATE subscriptions
                            SET user_status = 'in the kitchen',
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                            AND COALESCE(user_status, 'order received') = 'order received'
                        ";
                        
                        $stmt = $pdo->prepare($update_subscriptions_query);
                        foreach ($subscription_ids as $subscription_id) {
                            $stmt->execute([$subscription_id]);
                            $subscription_count += $stmt->rowCount();
                        }
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $response['success'] = true;
                    $response['message'] = "Successfully confirmed {$menu_count} menu items and {$subscription_count} subscriptions to 'In the Kitchen' status";
                    $response['count'] = $menu_count + $subscription_count;
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $pdo->rollback();
                    throw $e;
                }
                break;
                
            case 'cancel_order':
                $subscription_id = $_POST['subscription_id'] ?? '';
                $cancel_reason = $_POST['cancel_reason'] ?? '';
                $delivery_date = $_POST['date'] ?? '';
                
                if (empty($subscription_id) || empty($cancel_reason) || empty($delivery_date)) {
                    throw new Exception('Subscription ID, reason, and date are required');
                }
                
                // Get current state for undo (from both tables)
                $current_state_query = "
                    SELECT sm.id, 
                           COALESCE(sm.user_status, 'order received') as user_status, 
                           sm.subscription_id, s.user_id,
                           u.first_name, u.last_name,
                           s.status as subscription_status,
                           COALESCE(s.user_status, 'order received') as subscription_user_status,
                           s.cancellation_reason as subscription_cancellation_reason,
                           s.cancelled_at as subscription_cancelled_at,
                           s.cancelled_by as subscription_cancelled_by
                    FROM subscription_menus sm
                    JOIN subscriptions s ON sm.subscription_id = s.id
                    JOIN users u ON s.user_id = u.id
                    WHERE sm.subscription_id = ? AND sm.delivery_date = ?
                ";
                
                $stmt = $pdo->prepare($current_state_query);
                $stmt->execute([$subscription_id, $delivery_date]);
                $current_state = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($current_state)) {
                    throw new Exception('Order not found');
                }
                
                // Store undo information (including subscription data)
                $_SESSION['undo_data'] = [
                    'type' => 'cancel_order',
                    'subscription_id' => $subscription_id,
                    'date' => $delivery_date,
                    'timestamp' => time(),
                    'states' => $current_state,
                    'cancel_reason' => $cancel_reason
                ];
                
                // Start transaction for both table updates
                $pdo->beginTransaction();
                
                try {
                    // Cancel the subscription menus
                    $cancel_menus_query = "
                        UPDATE subscription_menus sm
                        SET sm.user_status = 'cancelled',
                            sm.cancel_reason = ?,
                            sm.cancelled_at = CURRENT_TIMESTAMP,
                            sm.cancelled_by = ?,
                            sm.updated_at = CURRENT_TIMESTAMP
                        WHERE sm.subscription_id = ? AND sm.delivery_date = ?
                    ";
                    
                    $stmt = $pdo->prepare($cancel_menus_query);
                    $stmt->execute([$cancel_reason, $_SESSION['user_id'], $subscription_id, $delivery_date]);
                    $menu_count = $stmt->rowCount();
                    
                    // Cancel the subscription itself
                    $cancel_subscription_query = "
                        UPDATE subscriptions
                        SET status = 'cancelled',
                            user_status = 'cancelled',
                            cancellation_reason = ?,
                            cancelled_at = CURRENT_TIMESTAMP,
                            cancelled_by = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ";
                    
                    $stmt = $pdo->prepare($cancel_subscription_query);
                    $stmt->execute([$cancel_reason, $_SESSION['user_id'], $subscription_id]);
                    $subscription_count = $stmt->rowCount();
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $response['success'] = true;
                    $response['message'] = "Successfully cancelled order and subscription for {$current_state[0]['first_name']} {$current_state[0]['last_name']} (Updated {$menu_count} menu items and {$subscription_count} subscription)";
                    $response['count'] = $menu_count + $subscription_count;
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $pdo->rollback();
                    throw $e;
                }
                break;
                
            case 'undo_action':
                if (!isset($_SESSION['undo_data'])) {
                    throw new Exception('No action to undo');
                }
                
                $undo_data = $_SESSION['undo_data'];
                
                // Removed timer restriction - users can undo anytime
                
                if ($undo_data['type'] === 'confirm_all') {
                    // Start transaction for both table restores
                    $pdo->beginTransaction();
                    
                    try {
                        // Restore previous states for subscription_menus
                        foreach ($undo_data['states'] as $state) {
                            $restore_menus_query = "
                                UPDATE subscription_menus 
                                SET user_status = ?, updated_at = CURRENT_TIMESTAMP
                                WHERE id = ?
                            ";
                            $stmt = $pdo->prepare($restore_menus_query);
                            $stmt->execute([$state['user_status'], $state['id']]);
                        }
                        
                        // Restore previous states for subscriptions
                        $subscription_ids = array_unique(array_column($undo_data['states'], 'subscription_id'));
                        foreach ($subscription_ids as $subscription_id) {
                            // Find the original subscription status for this subscription
                            $original_status = null;
                            foreach ($undo_data['states'] as $state) {
                                if ($state['subscription_id'] == $subscription_id) {
                                    $original_status = $state['subscription_user_status'];
                                    break;
                                }
                            }
                            
                            if ($original_status) {
                                $restore_subscription_query = "
                                    UPDATE subscriptions 
                                    SET user_status = ?, updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ?
                                ";
                                $stmt = $pdo->prepare($restore_subscription_query);
                                $stmt->execute([$original_status, $subscription_id]);
                            }
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        $response['message'] = 'Successfully undid bulk confirmation of ' . count($undo_data['states']) . ' menu items and ' . count($subscription_ids) . ' subscriptions';
                        
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $pdo->rollback();
                        throw $e;
                    }
                    
                } elseif ($undo_data['type'] === 'cancel_order') {
                    // Start transaction for both table restores
                    $pdo->beginTransaction();
                    
                    try {
                        // Restore subscription_menus states
                        foreach ($undo_data['states'] as $state) {
                            $restore_menus_query = "
                                UPDATE subscription_menus 
                                SET user_status = ?, 
                                    cancel_reason = NULL,
                                    cancelled_at = NULL,
                                    cancelled_by = NULL,
                                    updated_at = CURRENT_TIMESTAMP
                                WHERE id = ?
                            ";
                            $stmt = $pdo->prepare($restore_menus_query);
                            $stmt->execute([$state['user_status'], $state['id']]);
                        }
                        
                        // Restore subscription state
                        $restore_subscription_query = "
                            UPDATE subscriptions
                            SET status = ?,
                                user_status = ?,
                                cancellation_reason = ?,
                                cancelled_at = ?,
                                cancelled_by = ?,
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                        ";
                        
                        $first_state = $undo_data['states'][0];
                        $stmt = $pdo->prepare($restore_subscription_query);
                        $stmt->execute([
                            $first_state['subscription_status'],
                            $first_state['subscription_user_status'],
                            $first_state['subscription_cancellation_reason'],
                            $first_state['subscription_cancelled_at'],
                            $first_state['subscription_cancelled_by'],
                            $undo_data['subscription_id']
                        ]);
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        $response['message'] = "Successfully undid cancellation for {$undo_data['states'][0]['first_name']} {$undo_data['states'][0]['last_name']} (Restored subscription and menu items)";
                        
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $pdo->rollback();
                        throw $e;
                    }
                }
                
                // Clear undo data after successful undo
                unset($_SESSION['undo_data']);
                
                $response['success'] = true;
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Order Management Error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
}

// Get filter parameters
$available_delivery_days = getUpcomingDeliveryDays();
$selected_date = $_GET['date'] ?? ($available_delivery_days[0]['date'] ?? date('Y-m-d'));
$status_filter = $_GET['status'] ?? 'all';

// Check if it's Wednesday or Saturday
if (!isValidDeliveryDate($selected_date)) {
    $selected_date = $available_delivery_days[0]['date'] ?? date('Y-m-d');
}

// Order management data arrays
$orders = [];
$total_orders = 0;
$status_counts = [
    'order received' => 0,
    'in the kitchen' => 0,
    'delivering' => 0,
    'completed' => 0,
    'cancelled' => 0
];
$error_message = '';

try {
    // Get all orders for selected date with status counts (with fallback for missing columns)
    $orders_query = "
        SELECT 
            s.id as subscription_id,
            s.user_id,
            s.preferred_delivery_time,
            s.special_instructions as subscription_notes,
            u.first_name,
            u.last_name,
            u.phone,
            u.delivery_address,
            u.city,
            sp.name as plan_name,
            sp.meals_per_week,
            COUNT(sm.id) as total_meals_today,
            GROUP_CONCAT(DISTINCT COALESCE(sm.user_status, 'order received')) as order_statuses,
            MIN(COALESCE(sm.user_status, 'order received')) as primary_status,
            MAX(COALESCE(sm.cancel_reason, '')) as cancel_reason,
            MAX(sm.cancelled_at) as cancelled_at
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN subscription_plans sp ON s.plan_id = sp.id
        JOIN subscription_menus sm ON s.id = sm.subscription_id
        WHERE sm.delivery_date = ?
        AND s.status = 'active'
        AND sm.status = 'scheduled'
    ";
    
    // Add status filter if specified (handle missing column gracefully)
    if ($status_filter !== 'all') {
        if ($columns_ready) {
            $orders_query .= " AND COALESCE(sm.user_status, 'order received') = ?";
        } else {
            // If columns don't exist, show all orders when filtering
            $orders_query .= " AND 'order received' = ?";
        }
    }
    
    $orders_query .= "
        GROUP BY s.id, s.user_id, u.first_name, u.last_name, u.phone, 
                 u.delivery_address, u.city, sp.name, sp.meals_per_week,
                 s.preferred_delivery_time, s.special_instructions
        ORDER BY s.preferred_delivery_time ASC, u.last_name ASC
    ";
    
    $stmt = $pdo->prepare($orders_query);
    if ($status_filter !== 'all') {
        $stmt->execute([$selected_date, $status_filter]);
    } else {
        $stmt->execute([$selected_date]);
    }
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_orders = count($orders);
    
    // Get status counts (with fallback)
    if ($columns_ready) {
        $status_count_query = "
            SELECT COALESCE(sm.user_status, 'order received') as user_status, COUNT(*) as count
            FROM subscription_menus sm
            JOIN subscriptions s ON sm.subscription_id = s.id
            WHERE sm.delivery_date = ?
            AND s.status = 'active'
            AND sm.status = 'scheduled'
            GROUP BY COALESCE(sm.user_status, 'order received')
        ";
        
        $stmt = $pdo->prepare($status_count_query);
        $stmt->execute([$selected_date]);
        $status_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($status_results as $status_result) {
            if (isset($status_counts[$status_result['user_status']])) {
                $status_counts[$status_result['user_status']] = $status_result['count'];
            }
        }
    } else {
        // If columns don't exist, assume all orders are "order received"
        $status_counts['order received'] = $total_orders;
    }
    
} catch (Exception $e) {
    error_log("Order Management Error: " . $e->getMessage());
    $error_message = "Error loading order data: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Somdul Table</title>
    <link href="https://ydpschool.com/fonts/BaticaSans.css" rel="stylesheet" onerror="console.log('BaticaSans font failed to load')">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" onerror="console.log('Font Awesome failed to load')">
    
    <style>
        :root {
            /* Somdul Table Brand Colors */
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #6c757d;
            --border-light: #e9ecef;
            --shadow-soft: 0 2px 10px rgba(0,0,0,0.05);
            --shadow-medium: 0 4px 20px rgba(0,0,0,0.1);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
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
            font-family: 'BaticaSans', 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f1f3f4 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-medium);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-title h1 {
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
            color: white;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .dashboard-controls {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .control-label {
            font-weight: 500;
            color: var(--curry);
            font-size: 0.9rem;
        }

        .control-input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .control-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            font-family: inherit;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: white;
        }

        .btn-success:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }

        .btn-danger:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #f39c12);
            color: white;
        }

        .btn-warning:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--cream);
            color: var(--curry);
        }

        .btn-outline:hover:not(:disabled) {
            background: var(--cream);
            border-color: var(--curry);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-gray);
            font-weight: 500;
        }

        .stat-card.total { 
            --accent-color: var(--curry); 
        }
        .stat-card.received { 
            --accent-color: #3498db; 
        }
        .stat-card.kitchen { 
            --accent-color: var(--warning); 
        }
        .stat-card.delivering { 
            --accent-color: var(--info); 
        }
        .stat-card.completed { 
            --accent-color: var(--success); 
        }
        .stat-card.cancelled { 
            --accent-color: var(--danger); 
        }

        .stat-card .stat-icon,
        .stat-card .stat-number {
            color: var(--accent-color);
        }

        .actions-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
        }

        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .actions-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--curry);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .actions-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .orders-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, var(--cream), #f8f6f3);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--curry);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .order-item {
            border-bottom: 1px solid #f3f4f6;
            padding: 1.5rem 2rem;
            transition: var(--transition);
        }

        .order-item:hover {
            background: var(--cream);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .customer-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 0.5rem;
        }

        .customer-details {
            font-size: 0.9rem;
            color: var(--text-gray);
            line-height: 1.4;
        }

        .customer-details div {
            margin-bottom: 0.25rem;
        }

        .order-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge.order.received {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .status-badge.in.the.kitchen {
            background: linear-gradient(135deg, var(--warning), #f39c12);
            color: white;
        }

        .status-badge.delivering {
            background: linear-gradient(135deg, var(--info), #138496);
            color: white;
        }

        .status-badge.completed {
            background: linear-gradient(135deg, var(--success), #218838);
            color: white;
        }

        .status-badge.cancelled {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }

        .delivery-time {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--cream);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--curry);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-info {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fed7aa;
        }

        /* Cancel Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--cream), #f8f6f3);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--curry);
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Loading state */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .header {
                padding: 1rem;
            }
            
            .header-title h1 {
                font-size: 1.5rem;
            }
            
            .dashboard-controls {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .actions-buttons {
                flex-direction: column;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-actions {
                justify-content: flex-start;
            }
        }

        /* Undo Section Styles */
        .undo-section {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #fdd835;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            display: none;
        }

        .undo-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .undo-message {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #856404;
            font-weight: 500;
        }

        .undo-timer {
            font-size: 0.9rem;
            color: #856404;
            font-weight: 600;
        }

        /* Include calendar styles from kitchen dashboard */
        .calendar-dropdown {
            position: relative;
            width: 100%;
        }

        .calendar-trigger {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
            font-family: inherit;
            font-size: 1rem;
        }

        .calendar-trigger:hover,
        .calendar-trigger.active {
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .calendar-trigger-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .calendar-trigger-icon {
            color: var(--curry);
            font-size: 1.1rem;
        }

        .calendar-trigger-text {
            font-weight: 500;
            color: var(--text-dark);
        }

        .calendar-trigger-arrow {
            color: var(--text-gray);
            transition: transform 0.3s ease;
        }

        .calendar-trigger.active .calendar-trigger-arrow {
            transform: rotate(180deg);
        }

        .calendar-dropdown-panel {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid var(--curry);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            min-width: 320px;
        }

        .calendar-dropdown-panel.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(135deg, var(--cream), #f8f6f3);
            border-bottom: 1px solid var(--border-light);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
        }

        .calendar-nav-btn {
            background: none;
            border: none;
            color: var(--curry);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .calendar-nav-btn:hover {
            background: var(--curry);
            color: white;
            transform: scale(1.1);
        }

        .calendar-title {
            font-weight: 600;
            color: var(--curry);
            font-size: 1.1rem;
            min-width: 140px;
            text-align: center;
        }

        .calendar-grid {
            padding: 1rem;
        }

        .calendar-days-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            color: var(--text-gray);
            font-size: 0.8rem;
            padding: 0.5rem;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.25rem;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            min-height: 40px;
            font-size: 0.9rem;
            font-weight: 500;
            background: #f8f9fa;
        }

        .calendar-day.other-month {
            color: #ccc;
            cursor: not-allowed;
            background: transparent;
        }

        .calendar-day.current-month {
            color: var(--text-dark);
            background: white;
            border-color: var(--border-light);
        }

        .calendar-day.delivery-day {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: white;
            border-color: var(--sage);
            font-weight: 600;
            cursor: pointer;
        }

        .calendar-day.delivery-day:hover {
            background: linear-gradient(135deg, #27ae60, var(--sage));
            transform: scale(1.05);
            box-shadow: var(--shadow-soft);
        }

        .calendar-day.selected {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: white;
            border-color: var(--curry);
            transform: scale(1.05);
            box-shadow: var(--shadow-soft);
        }

        .calendar-day.today {
            border: 2px solid var(--curry);
            font-weight: 700;
        }

        .calendar-day.disabled {
            color: #ddd;
            cursor: not-allowed;
            background: #f8f9fa;
        }

        .calendar-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-light);
            background: var(--cream);
            border-radius: 0 0 var(--radius-md) var(--radius-md);
        }

        .calendar-legend {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            justify-content: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--text-gray);
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .delivery-dot {
            background: linear-gradient(135deg, var(--sage), #27ae60);
        }

        .selected-dot {
            background: linear-gradient(135deg, var(--curry), var(--brown));
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <h1>
                    <i class="fas fa-clipboard-list"></i>
                    Order Management
                </h1>
                <div class="delivery-indicator" style="background: linear-gradient(135deg, var(--sage), #27ae60); color: white; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; font-weight: 500;">
                    <i class="fas fa-calendar-alt"></i>
                    Wed & Sat Delivery
                </div>
            </div>
            <div class="header-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo !empty($selected_date) ? date('l, M j, Y', strtotime($selected_date)) : 'Date not selected'; ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span><?php echo $total_orders; ?> Orders</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-user-shield"></i>
                    <span><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                </div>
                <a href="../admin/dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>
    </div>

    <div class="main-container">
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($columns_ready && empty($error_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Order management system is ready! Database schema has been automatically updated.
            </div>
        <?php endif; ?>

        <!-- Undo Section (Hidden by default) -->
        <div class="undo-section" id="undoSection">
            <div class="undo-content">
                <div class="undo-message">
                    <i class="fas fa-undo"></i>
                    <span id="undoMessage">Action completed successfully</span>
                </div>
                <div>
                    <button class="btn btn-warning" onclick="undoLastAction()">
                        <i class="fas fa-undo"></i> Undo Action
                    </button>
                </div>
            </div>
        </div>

        <!-- Dashboard Controls -->
        <div class="dashboard-controls">
            <div class="control-group">
                <label class="control-label">Select Delivery Date (Wed & Sat Only)</label>
                
                <!-- Full Calendar Grid -->
                <div class="calendar-dropdown">
                    <div class="calendar-trigger" onclick="toggleCalendar()">
                        <div class="calendar-trigger-content">
                            <i class="fas fa-calendar-alt calendar-trigger-icon"></i>
                            <span class="calendar-trigger-text" id="selectedDateText">
                                <?php echo !empty($selected_date) ? date('D, M j, Y', strtotime($selected_date)) : 'Select Date'; ?>
                            </span>
                        </div>
                        <i class="fas fa-chevron-down calendar-trigger-arrow"></i>
                    </div>
                    
                    <div class="calendar-dropdown-panel" id="calendarPanel">
                        <!-- Calendar Header with Navigation -->
                        <div class="calendar-header">
                            <button type="button" class="calendar-nav-btn" onclick="changeMonth(-1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="calendar-title" id="calendarTitle">
                                January 2025
                            </div>
                            <button type="button" class="calendar-nav-btn" onclick="changeMonth(1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <!-- Days of Week Header -->
                        <div class="calendar-grid">
                            <div class="calendar-days-header">
                                <div class="calendar-day-header">S</div>
                                <div class="calendar-day-header">M</div>
                                <div class="calendar-day-header">T</div>
                                <div class="calendar-day-header">W</div>
                                <div class="calendar-day-header">T</div>
                                <div class="calendar-day-header">F</div>
                                <div class="calendar-day-header">S</div>
                            </div>
                            
                            <!-- Calendar Days Grid -->
                            <div class="calendar-days" id="calendarDays">
                                <!-- JavaScript will generate calendar days here -->
                            </div>
                        </div>
                        
                        <!-- Legend -->
                        <div class="calendar-footer">
                            <div class="calendar-legend">
                                <div class="legend-item">
                                    <span class="legend-dot delivery-dot"></span>
                                    <span>Delivery Days (Wed & Sat)</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-dot selected-dot"></span>
                                    <span>Selected Date</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="control-group">
                <label class="control-label">Status Filter</label>
                <select class="control-input" onchange="filterByStatus(this.value)">
                    <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Orders</option>
                    <option value="order received" <?php echo ($status_filter === 'order received') ? 'selected' : ''; ?>>Order Received</option>
                    <option value="in the kitchen" <?php echo ($status_filter === 'in the kitchen') ? 'selected' : ''; ?>>In the Kitchen</option>
                    <option value="delivering" <?php echo ($status_filter === 'delivering') ? 'selected' : ''; ?>>Delivering</option>
                    <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="control-group">
                <label class="control-label">Quick Actions</label>
                <button class="btn btn-primary" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Status Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-number"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            
            <div class="stat-card received">
                <div class="stat-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['order received']; ?></div>
                <div class="stat-label">Order Received</div>
            </div>
            
            <div class="stat-card kitchen">
                <div class="stat-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['in the kitchen']; ?></div>
                <div class="stat-label">In the Kitchen</div>
            </div>
            
            <div class="stat-card delivering">
                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['delivering']; ?></div>
                <div class="stat-label">Delivering</div>
            </div>
            
            <div class="stat-card completed">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <div class="stat-card cancelled">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['cancelled']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <!-- Bulk Actions Section -->
        <div class="actions-section">
            <div class="actions-header">
                <h2 class="actions-title">
                    <i class="fas fa-tasks"></i>
                    Bulk Actions
                </h2>
                <div class="actions-buttons">
                    <button class="btn btn-success" onclick="confirmAllOrders()" id="confirmAllBtn" 
                            <?php echo ($status_counts['order received'] === 0) ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-double"></i>
                        Confirm All Orders (<?php echo $status_counts['order received']; ?>)
                    </button>
                </div>
            </div>
            <p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">
                <i class="fas fa-info-circle"></i>
                Use "Confirm All Orders" to move all "Order Received" orders to "In the Kitchen" status.
                <?php if ($columns_ready): ?>
                    <br><small><i class="fas fa-database"></i> Database schema automatically updated for order management.</small>
                <?php else: ?>
                    <br><small><i class="fas fa-exclamation-triangle"></i> Some features may be limited until database schema update completes.</small>
                <?php endif; ?>
            </p>
        </div>

        <!-- Orders List -->
        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-list-ul"></i>
                    Orders for <?php echo !empty($selected_date) ? date('l, M j', strtotime($selected_date)) : 'Date not selected'; ?>
                </h2>
            </div>
            
            <div class="order-list">
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>No Orders Found</h3>
                        <p>No orders are scheduled for the selected date and status filter</p>
                        <small>Try selecting a different date or changing the status filter</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-item">
                            <div class="order-header">
                                <div class="customer-info">
                                    <h3><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></h3>
                                    <div class="customer-details">
                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['phone'] ?? 'No phone provided'); ?></div>
                                        <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(($order['delivery_address'] ?? 'No address') . ', ' . ($order['city'] ?? '')); ?></div>
                                        <div><i class="fas fa-tag"></i> Plan: <?php echo htmlspecialchars($order['plan_name'] ?? 'Standard Plan'); ?></div>
                                        <div><i class="fas fa-utensils"></i> Total Meals: <?php echo $order['total_meals_today']; ?></div>
                                        <?php if (!empty($order['subscription_notes'])): ?>
                                            <div><i class="fas fa-sticky-note"></i> Notes: <?php echo htmlspecialchars($order['subscription_notes']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($order['cancel_reason'])): ?>
                                            <div style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Cancel Reason: <?php echo htmlspecialchars($order['cancel_reason']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="order-actions">
                                    <div class="delivery-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo htmlspecialchars($order['preferred_delivery_time'] ?? '3:00 PM - 6:00 PM'); ?>
                                    </div>
                                    <div class="status-badge <?php echo str_replace(' ', '.', $order['primary_status'] ?: 'order received'); ?>">
                                        <?php echo ucfirst($order['primary_status'] ?: 'order received'); ?>
                                    </div>
                                    <?php if (($order['primary_status'] ?: 'order received') !== 'cancelled'): ?>
                                        <button class="btn btn-danger cancel-order-btn" 
                                                data-subscription-id="<?php echo $order['subscription_id']; ?>"
                                                data-customer-name="<?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>"
                                                id="cancelBtn_<?php echo $order['subscription_id']; ?>">
                                            <i class="fas fa-times"></i>
                                            Cancel Order
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Cancel Order
                </h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel the order for <strong id="cancelCustomerName"></strong>?</p>
                <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 1.5rem;">This action will set all meals for this customer to "cancelled" status.</p>
                
                <div class="form-group">
                    <label class="form-label" for="cancelReason">
                        Cancellation Reason <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea class="form-control" id="cancelReason" rows="3" 
                              placeholder="Please provide a reason for cancellation (e.g., customer request, out of stock, etc.)" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCancelModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmCancel()" id="confirmCancelBtn">
                    <i class="fas fa-times"></i>
                    Confirm Cancellation
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let selectedDateValue = '<?php echo $selected_date; ?>';
        let currentCancelSubscriptionId = null;
        
        // Calendar functionality (reused from kitchen dashboard)
        let calendarOpen = false;
        let currentCalendarDate = new Date();
        let availableDeliveryDates = <?php echo json_encode($available_delivery_days); ?>;

        function toggleCalendar() {
            const trigger = document.querySelector('.calendar-trigger');
            const panel = document.getElementById('calendarPanel');
            
            calendarOpen = !calendarOpen;
            
            if (calendarOpen) {
                trigger.classList.add('active');
                panel.classList.add('active');
                generateCalendar();
            } else {
                trigger.classList.remove('active');
                panel.classList.remove('active');
            }
        }

        function changeMonth(direction) {
            currentCalendarDate.setMonth(currentCalendarDate.getMonth() + direction);
            generateCalendar();
        }

        function generateCalendar() {
            const year = currentCalendarDate.getFullYear();
            const month = currentCalendarDate.getMonth();
            
            // Update calendar title
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            document.getElementById('calendarTitle').textContent = `${monthNames[month]} ${year}`;
            
            // Get first day of month and number of days
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = firstDay.getDay(); // 0 = Sunday
            
            // Get previous month's last days
            const prevMonth = new Date(year, month, 0);
            const daysInPrevMonth = prevMonth.getDate();
            
            let calendarHTML = '';
            let dayCount = 1;
            let nextMonthDay = 1;
            
            // Generate 6 weeks (42 days) to fill calendar grid
            for (let week = 0; week < 6; week++) {
                for (let day = 0; day < 7; day++) {
                    const cellIndex = week * 7 + day;
                    let dayNumber, cellClass, cellDate, isClickable = false;
                    
                    if (cellIndex < startingDayOfWeek) {
                        // Previous month days
                        dayNumber = daysInPrevMonth - startingDayOfWeek + cellIndex + 1;
                        cellClass = 'calendar-day other-month';
                        cellDate = new Date(year, month - 1, dayNumber);
                    } else if (dayCount <= daysInMonth) {
                        // Current month days
                        dayNumber = dayCount;
                        cellClass = 'calendar-day current-month';
                        cellDate = new Date(year, month, dayNumber);
                        
                        // Check if it's today
                        const today = new Date();
                        if (cellDate.toDateString() === today.toDateString()) {
                            cellClass += ' today';
                        }
                        
                        // Check if it's a delivery day (Wednesday = 3, Saturday = 6)
                        const dayOfWeek = cellDate.getDay();
                        if (dayOfWeek === 3 || dayOfWeek === 6) { // Wednesday or Saturday
                            const dateString = cellDate.getFullYear() + '-' + 
                                String(cellDate.getMonth() + 1).padStart(2, '0') + '-' + 
                                String(cellDate.getDate()).padStart(2, '0');
                            
                            // Check if this delivery date has available orders
                            const hasDelivery = availableDeliveryDates.some(d => d.date === dateString);
                            
                            if (hasDelivery) {
                                cellClass += ' delivery-day';
                                isClickable = true;
                                
                                // Check if selected
                                if (dateString === selectedDateValue) {
                                    cellClass += ' selected';
                                }
                            } else {
                                cellClass += ' disabled';
                            }
                        } else {
                            cellClass += ' disabled';
                        }
                        
                        dayCount++;
                    } else {
                        // Next month days
                        dayNumber = nextMonthDay;
                        cellClass = 'calendar-day other-month';
                        cellDate = new Date(year, month + 1, nextMonthDay);
                        nextMonthDay++;
                    }
                    
                    const clickHandler = isClickable ? 
                        `onclick="selectCalendarDate('${cellDate.getFullYear()}-${String(cellDate.getMonth() + 1).padStart(2, '0')}-${String(cellDate.getDate()).padStart(2, '0')}')"` : '';
                    
                    calendarHTML += `<div class="${cellClass}" ${clickHandler}>${dayNumber}</div>`;
                }
                
                // Stop generating weeks if we've shown all days of current month and next month days
                if (dayCount > daysInMonth && nextMonthDay > 7) break;
            }
            
            document.getElementById('calendarDays').innerHTML = calendarHTML;
        }

        function selectCalendarDate(dateString) {
            // Find the delivery date object
            const deliveryDate = availableDeliveryDates.find(d => d.date === dateString);
            
            if (deliveryDate) {
                // Update display text
                const date = new Date(dateString);
                const displayText = deliveryDate.display;
                document.getElementById('selectedDateText').textContent = displayText;
                
                // Close calendar
                document.querySelector('.calendar-trigger').classList.remove('active');
                document.getElementById('calendarPanel').classList.remove('active');
                calendarOpen = false;
                
                // Navigate to selected date
                filterByDate(dateString);
            }
        }

        // Close calendar when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.calendar-dropdown');
            
            if (calendarOpen && !dropdown.contains(event.target)) {
                document.querySelector('.calendar-trigger').classList.remove('active');
                document.getElementById('calendarPanel').classList.remove('active');
                calendarOpen = false;
            }
        });

        // Navigation functions
        function filterByDate(date) {
            if (date) {
                window.location.href = `?date=${date}&status=<?php echo $status_filter; ?>`;
            }
        }

        function filterByStatus(status) {
            if (status) {
                window.location.href = `?date=<?php echo $selected_date; ?>&status=${status}`;
            }
        }

        function refreshData() {
            window.location.reload();
        }

        // Main action functions
        function confirmAllOrders() {
            console.log('confirmAllOrders called'); // Debug log
            if (confirm('Are you sure you want to confirm all "Order Received" orders and move them to "In the Kitchen" status?')) {
                const btn = document.getElementById('confirmAllBtn');
                const originalText = btn.innerHTML;
                
                // Show loading state
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                btn.disabled = true;
                
                // Make AJAX call
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=confirm_all_orders&date=${encodeURIComponent(selectedDateValue)}`
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Response received:', data); // Debug log
                    if (data.success) {
                        showAlert(data.message, 'success');
                        showUndoSection('Confirmed all orders to "In the Kitchen" status');
                        
                        // Refresh page after short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showAlert(data.message, 'error');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while processing the request', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        }

        function showCancelModal(subscriptionId, customerName) {
            console.log('showCancelModal called with:', subscriptionId, customerName); // Debug log
            
            // Check if required elements exist
            const modal = document.getElementById('cancelModal');
            const customerNameEl = document.getElementById('cancelCustomerName');
            const reasonEl = document.getElementById('cancelReason');
            
            if (!modal) {
                console.error('Cancel modal not found!');
                showAlert('Modal not found. Please refresh the page and try again.', 'error');
                return;
            }
            
            if (!customerNameEl) {
                console.error('Customer name element not found!');
                showAlert('Modal elements not found. Please refresh the page.', 'error');
                return;
            }
            
            if (!reasonEl) {
                console.error('Reason textarea not found!');
                showAlert('Modal form elements not found. Please refresh the page.', 'error');
                return;
            }
            
            // Set modal data
            currentCancelSubscriptionId = subscriptionId;
            customerNameEl.textContent = customerName;
            reasonEl.value = '';
            
            // Show modal
            modal.style.display = 'flex';
            console.log('Modal should now be visible');
            
            // Focus on reason field
            setTimeout(() => {
                reasonEl.focus();
            }, 100);
        }

        function closeCancelModal() {
            console.log('closeCancelModal called'); // Debug log
            currentCancelSubscriptionId = null;
            const modal = document.getElementById('cancelModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function confirmCancel() {
            console.log('confirmCancel called'); // Debug log
            const reason = document.getElementById('cancelReason').value.trim();
            
            if (!reason) {
                showAlert('Please provide a cancellation reason', 'warning');
                return;
            }
            
            if (!currentCancelSubscriptionId) {
                showAlert('No subscription selected for cancellation', 'error');
                return;
            }
            
            const btn = document.getElementById('confirmCancelBtn');
            const originalText = btn.innerHTML;
            
            // Show loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            btn.disabled = true;
            
            // Make AJAX call
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=cancel_order&subscription_id=${encodeURIComponent(currentCancelSubscriptionId)}&cancel_reason=${encodeURIComponent(reason)}&date=${encodeURIComponent(selectedDateValue)}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Cancel response:', data); // Debug log
                if (data.success) {
                    showAlert(data.message, 'success');
                    showUndoSection(`Cancelled order with reason: "${reason}"`);
                    closeCancelModal();
                    
                    // Refresh page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert(data.message, 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while cancelling the order', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        // Undo functionality (No timer restriction)
        function showUndoSection(message) {
            const undoSection = document.getElementById('undoSection');
            const undoMessage = document.getElementById('undoMessage');
            
            undoMessage.textContent = message + '. You can undo this action anytime until you perform another action.';
            undoSection.style.display = 'block';
        }

        function hideUndoSection() {
            const undoSection = document.getElementById('undoSection');
            if (undoSection) {
                undoSection.style.display = 'none';
            }
        }

        function undoLastAction() {
            if (confirm('Are you sure you want to undo the last action? This will restore the previous state.')) {
                // Show loading on undo section
                const undoSection = document.getElementById('undoSection');
                undoSection.style.opacity = '0.7';
                undoSection.style.pointerEvents = 'none';
                
                // Make AJAX call
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=undo_action'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        hideUndoSection();
                        
                        // Refresh page after short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showAlert(data.message, 'error');
                        undoSection.style.opacity = '1';
                        undoSection.style.pointerEvents = 'auto';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while undoing the action', 'error');
                    undoSection.style.opacity = '1';
                    undoSection.style.pointerEvents = 'auto';
                });
            }
        }

        // Utility functions
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            
            const icon = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-triangle',
                warning: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle'
            };
            
            alertDiv.innerHTML = `<i class="${icon[type]}"></i> ${message}`;
            
            // Insert at top of main container
            const mainContainer = document.querySelector('.main-container');
            mainContainer.insertBefore(alertDiv, mainContainer.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case 'r':
                        e.preventDefault();
                        refreshData();
                        break;
                    case 'u':
                        e.preventDefault();
                        if (document.getElementById('undoSection').style.display === 'block') {
                            undoLastAction();
                        }
                        break;
                }
            }
            
            // Close modals on Escape
            if (e.key === 'Escape') {
                if (document.getElementById('cancelModal').style.display === 'flex') {
                    closeCancelModal();
                }
                if (calendarOpen) {
                    toggleCalendar();
                }
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add global error handler to catch JavaScript syntax errors
            window.onerror = function(msg, url, lineNo, columnNo, error) {
                console.error('JavaScript Error:', {
                    message: msg,
                    source: url,
                    line: lineNo,
                    column: columnNo,
                    error: error
                });
                
                // Show user-friendly message for syntax errors
                if (msg.includes('SyntaxError') || msg.includes('Unexpected token')) {
                    showAlert('Page loading error detected. Please refresh the page and contact support if the issue persists.', 'warning');
                }
                return false;
            };
            
            // Add event listeners for cancel buttons (ROBUST METHOD)
            document.querySelectorAll('.cancel-order-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Cancel button clicked!'); // Debug log
                    
                    const subscriptionId = this.getAttribute('data-subscription-id');
                    const customerName = this.getAttribute('data-customer-name');
                    
                    console.log('Data:', {
                        subscriptionId: subscriptionId,
                        customerName: customerName
                    }); // Debug log
                    
                    if (subscriptionId && customerName) {
                        showCancelModal(subscriptionId, customerName);
                    } else {
                        console.error('Missing data attributes:', {
                            subscriptionId: subscriptionId,
                            customerName: customerName
                        });
                        showAlert('Button data missing. Please refresh the page.', 'error');
                    }
                });
            });
            
            // Check if database schema is ready
            const columnsReady = <?php echo $columns_ready ? 'true' : 'false'; ?>;
            
            if (!columnsReady) {
                showAlert('Database schema is being updated. Some features may be limited until the update completes.', 'warning');
                
                // Disable action buttons if schema is not ready
                const confirmBtn = document.getElementById('confirmAllBtn');
                if (confirmBtn) confirmBtn.disabled = true;
                
                document.querySelectorAll('.cancel-order-btn').forEach(btn => {
                    btn.disabled = true;
                });
            }
            
            // Test critical functions exist
            try {
                if (typeof showCancelModal !== 'function') {
                    console.error('showCancelModal function not defined');
                    showAlert('JavaScript loading error. Please refresh the page.', 'error');
                    return;
                }
                
                // Test modal elements exist
                const modal = document.getElementById('cancelModal');
                const customerName = document.getElementById('cancelCustomerName');
                const cancelReason = document.getElementById('cancelReason');
                
                if (!modal || !customerName || !cancelReason) {
                    console.error('Modal elements missing:', {
                        modal: !!modal,
                        customerName: !!customerName,
                        cancelReason: !!cancelReason
                    });
                    showAlert('Page elements missing. Please refresh the page.', 'warning');
                }
                
                console.log('✅ All critical elements found and functions loaded');
                console.log('✅ Added event listeners to', document.querySelectorAll('.cancel-order-btn').length, 'cancel buttons');
                
            } catch (error) {
                console.error('Initialization error:', error);
                showAlert('Page initialization error. Please refresh the page.', 'error');
            }
            
            // Set current calendar date to selected date or current month
            if (selectedDateValue) {
                currentCalendarDate = new Date(selectedDateValue);
            }
            
            // Check if there's an undo action available from PHP session
            <?php if (isset($_SESSION['undo_data'])): ?>
                const undoData = <?php echo json_encode($_SESSION['undo_data']); ?>;
                
                let message = '';
                if (undoData.type === 'confirm_all') {
                    message = `Confirmed all orders to "In the Kitchen" status`;
                } else if (undoData.type === 'cancel_order') {
                    message = `Cancelled order with reason: "${undoData.cancel_reason}"`;
                }
                
                if (message) {
                    const undoSection = document.getElementById('undoSection');
                    const undoMessage = document.getElementById('undoMessage');
                    
                    if (undoSection && undoMessage) {
                        undoMessage.textContent = message + '. You can undo this action anytime until you perform another action.';
                        undoSection.style.display = 'block';
                    }
                }
            <?php endif; ?>
            
            console.log('✅ Order Management Dashboard loaded successfully');
            console.log('📊 Statistics:');
            console.log('- Total orders:', <?php echo $total_orders; ?>);
            console.log('- Selected date:', '<?php echo $selected_date; ?>');
            console.log('- Status filter:', '<?php echo $status_filter; ?>');
            console.log('- Database schema ready:', columnsReady);
            console.log('- JavaScript functions loaded:', typeof showCancelModal === 'function');
        });
    </script>
</body>
</html>