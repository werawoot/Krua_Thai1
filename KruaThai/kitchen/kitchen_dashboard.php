<?php
/**
 * Kitchen Dashboard - SOMDUL TABLE Brand
 * File: kitchen_dashboard.php
 * Role: kitchen, admin only
 * Status: PRODUCTION READY ✅
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Role-based access control
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has kitchen or admin role
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['kitchen', 'admin'])) {
    die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 10px; margin: 20px; font-family: Arial;">
        <h3>🚫 Access Denied</h3>
        <p>You do not have permission to access the kitchen dashboard.</p>
        <p>Required roles: Kitchen Staff or Admin</p>
        <a href="dashboard.php" style="background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>
    </div>');
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Handle status updates
if ($_POST && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    try {
        $update_query = "UPDATE orders SET kitchen_status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($connection, $update_query);
        mysqli_stmt_bind_param($stmt, "ss", $new_status, $order_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = "Order status updated successfully";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Failed to update order status";
            $_SESSION['flash_type'] = "error";
        }
        
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit();
}

// Get filter parameters
$selected_date = $_GET['date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? 'all';
$export_type = $_GET['export'] ?? '';

// Kitchen data arrays
$orders_by_date = [];
$meal_summary = [];
$total_orders = 0;
$total_meals = 0;

try {
    // Get orders for selected date with detailed information
    $orders_query = "
        SELECT 
            o.id as order_id,
            o.order_number,
            o.delivery_date,
            o.delivery_time_slot,
            o.delivery_address,
            o.delivery_instructions,
            o.special_notes,
            o.status as order_status,
            o.kitchen_status,
            o.total_items,
            o.estimated_prep_time,
            o.created_at,
            u.first_name,
            u.last_name,
            u.phone,
            u.dietary_preferences,
            u.allergies,
            u.spice_level,
            s.special_instructions as subscription_instructions
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN subscriptions s ON o.subscription_id = s.id
        WHERE o.delivery_date = ?
        " . ($status_filter !== 'all' ? "AND o.kitchen_status = ?" : "") . "
        ORDER BY o.delivery_time_slot ASC, o.created_at ASC
    ";
    
    $stmt = mysqli_prepare($connection, $orders_query);
    
    if ($status_filter !== 'all') {
        mysqli_stmt_bind_param($stmt, "ss", $selected_date, $status_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "s", $selected_date);
    }
    
    mysqli_stmt_execute($stmt);
    $orders_result = mysqli_stmt_get_result($stmt);
    
    while ($order = mysqli_fetch_assoc($orders_result)) {
        $orders_by_date[] = $order;
        $total_orders++;
    }
    
    // Get order items (meals) for each order
    foreach ($orders_by_date as &$order) {
        $items_query = "
            SELECT 
                oi.id as item_id,
                oi.menu_name,
                oi.quantity,
                oi.customizations,
                oi.special_requests,
                oi.item_status,
                oi.preparation_notes,
                m.ingredients,
                m.cooking_method,
                m.preparation_time,
                m.spice_level as menu_spice_level,
                mc.name as category_name
            FROM order_items oi
            LEFT JOIN menus m ON oi.menu_id = m.id
            LEFT JOIN menu_categories mc ON m.category_id = mc.id
            WHERE oi.order_id = ?
            ORDER BY mc.sort_order ASC, m.name ASC
        ";
        
        $items_stmt = mysqli_prepare($connection, $items_query);
        mysqli_stmt_bind_param($items_stmt, "s", $order['order_id']);
        mysqli_stmt_execute($items_stmt);
        $items_result = mysqli_stmt_get_result($items_stmt);
        
        $order['items'] = [];
        while ($item = mysqli_fetch_assoc($items_result)) {
            $order['items'][] = $item;
            $total_meals += $item['quantity'];
            
            // Build meal summary for aggregation
            $meal_key = $item['menu_name'];
            if (!isset($meal_summary[$meal_key])) {
                $meal_summary[$meal_key] = [
                    'name' => $item['menu_name'],
                    'category' => $item['category_name'],
                    'total_quantity' => 0,
                    'prep_time' => $item['preparation_time'],
                    'spice_level' => $item['menu_spice_level'],
                    'customizations' => [],
                    'special_requests' => [],
                    'ingredients' => $item['ingredients'],
                    'cooking_method' => $item['cooking_method']
                ];
            }
            
            $meal_summary[$meal_key]['total_quantity'] += $item['quantity'];
            
            // Collect customizations
            if (!empty($item['customizations'])) {
                $customs = json_decode($item['customizations'], true);
                if ($customs) {
                    foreach ($customs as $custom) {
                        $meal_summary[$meal_key]['customizations'][] = $custom;
                    }
                }
            }
            
            // Collect special requests
            if (!empty($item['special_requests'])) {
                $meal_summary[$meal_key]['special_requests'][] = $item['special_requests'];
            }
        }
        
        mysqli_stmt_close($items_stmt);
    }
    
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Handle export requests
if ($export_type === 'csv') {
    exportToCSV($meal_summary, $selected_date);
    exit();
} elseif ($export_type === 'print') {
    generatePrintSheet($orders_by_date, $meal_summary, $selected_date);
    exit();
}

// Export functions
function exportToCSV($meal_summary, $date) {
    $filename = "kitchen_summary_" . $date . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Menu Name', 'Category', 'Total Quantity', 'Prep Time (min)', 
        'Spice Level', 'Customizations', 'Special Requests'
    ]);
    
    foreach ($meal_summary as $meal) {
        fputcsv($output, [
            $meal['name'],
            $meal['category'],
            $meal['total_quantity'],
            $meal['prep_time'],
            $meal['spice_level'],
            implode('; ', array_unique($meal['customizations'])),
            implode('; ', array_unique($meal['special_requests']))
        ]);
    }
    
    fclose($output);
}

function generatePrintSheet($orders, $meal_summary, $date) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Kitchen Production Sheet - <?php echo $date; ?></title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
            
            @media print { .no-print { display: none; } }
            
            body { 
                font-family: 'Batica Sans', 'Inter', Arial, sans-serif; 
                font-size: 12px; 
                color: #333;
                line-height: 1.4;
                margin: 0;
                padding: 20px;
            }
            
            .header { 
                text-align: center; 
                margin-bottom: 30px; 
                border-bottom: 3px solid #bd9379; 
                padding-bottom: 20px; 
            }
            
            .header h1 {
                color: #bd9379;
                font-size: 28px;
                font-weight: 800;
                margin: 0 0 10px 0;
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            
            .header h2 {
                color: #333;
                font-size: 18px;
                font-weight: 600;
                margin: 10px 0;
            }
            
            .summary { 
                margin-bottom: 40px; 
            }
            
            .summary h3 {
                background: linear-gradient(135deg, #bd9379, #ece8e1);
                color: #333;
                padding: 15px;
                margin: 0 0 20px 0;
                font-size: 16px;
                font-weight: 700;
                border-radius: 8px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 15px 0;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            th, td { 
                border: 1px solid #ddd; 
                padding: 12px 8px; 
                text-align: left; 
                vertical-align: top;
            }
            
            th { 
                background: #bd9379;
                color: white;
                font-weight: 700;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .quantity-cell {
                text-align: center;
                font-size: 20px;
                font-weight: 800;
                color: #bd9379;
                background: #ece8e1;
            }
            
            .order-detail { 
                margin: 20px 0; 
                padding: 15px; 
                border-left: 4px solid #bd9379;
                background: #f9f9f9;
                page-break-inside: avoid;
            }
            
            .order-detail h4 {
                color: #bd9379;
                font-size: 14px;
                font-weight: 700;
                margin: 0 0 10px 0;
                text-transform: uppercase;
            }
            
            .order-info {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }
            
            .order-info p {
                margin: 5px 0;
                font-size: 11px;
            }
            
            .order-info strong {
                color: #bd9379;
                font-weight: 600;
            }
            
            .items-list {
                background: white;
                padding: 10px;
                border-radius: 5px;
                border: 1px solid #ece8e1;
            }
            
            .items-list h5 {
                color: #bd9379;
                font-size: 12px;
                font-weight: 700;
                margin: 0 0 10px 0;
                text-transform: uppercase;
            }
            
            .items-list ul {
                margin: 0;
                padding: 0 0 0 15px;
            }
            
            .items-list li {
                margin-bottom: 8px;
                font-size: 11px;
                line-height: 1.3;
            }
            
            .item-name {
                font-weight: 700;
                color: #333;
            }
            
            .item-note {
                font-style: italic;
                color: #cf723a;
                font-size: 10px;
                margin-top: 2px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>SOMDUL TABLE</h1>
            <h2>🍳 Kitchen Production Sheet</h2>
            <p><strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($date)); ?></p>
            <p><strong>Total Orders:</strong> <?php echo count($orders); ?> | <strong>Total Meals:</strong> <?php echo array_sum(array_column($meal_summary, 'total_quantity')); ?></p>
            <p><strong>Generated:</strong> <?php echo date('m/d/Y H:i:s'); ?></p>
        </div>

        <div class="summary">
            <h3>📋 Meal Production Summary</h3>
            <table>
                <thead>
                    <tr>
                        <th>Menu Item</th>
                        <th>Quantity</th>
                        <th>Prep Time</th>
                        <th>Spice Level</th>
                        <th>Special Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Sort by quantity (highest first)
                    uasort($meal_summary, function($a, $b) {
                        return $b['total_quantity'] - $a['total_quantity'];
                    });
                    
                    foreach ($meal_summary as $meal): 
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($meal['name']); ?></strong><br>
                            <small style="color: #666;"><?php echo htmlspecialchars($meal['category']); ?></small>
                        </td>
                        <td class="quantity-cell">
                            <?php echo $meal['total_quantity']; ?>
                        </td>
                        <td><?php echo $meal['prep_time']; ?> min</td>
                        <td><?php echo ucfirst($meal['spice_level']); ?></td>
                        <td>
                            <?php if (!empty($meal['customizations'])): ?>
                                <strong>Customizations:</strong> <?php echo implode(', ', array_unique($meal['customizations'])); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($meal['special_requests'])): ?>
                                <strong>Special Requests:</strong> <?php echo implode(', ', array_unique($meal['special_requests'])); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="orders">
            <h3 style="background: linear-gradient(135deg, #bd9379, #ece8e1); color: #333; padding: 15px; margin: 0 0 20px 0; font-size: 16px; font-weight: 700; border-radius: 8px; text-transform: uppercase;">📦 Order Details</h3>
            <?php foreach ($orders as $order): ?>
            <div class="order-detail">
                <h4>📋 Order: <?php echo htmlspecialchars($order['order_number']); ?></h4>
                
                <div class="order-info">
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                    <p><strong>Delivery Time:</strong> <?php echo htmlspecialchars($order['delivery_time_slot']); ?></p>
                    <p><strong>Spice Level:</strong> <?php echo ucfirst($order['spice_level'] ?? 'medium'); ?></p>
                </div>
                
                <?php if (!empty($order['special_notes']) || !empty($order['subscription_instructions'])): ?>
                <p style="background: #fff3cd; padding: 8px; border-radius: 4px; border-left: 3px solid #ffc107; margin: 10px 0; font-size: 11px;">
                    <strong>⚠️ Special Notes:</strong><br>
                    <?php if (!empty($order['special_notes'])): ?>
                        <?php echo htmlspecialchars($order['special_notes']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($order['subscription_instructions'])): ?>
                        <?php echo htmlspecialchars($order['subscription_instructions']); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                
                <?php if (!empty($order['dietary_preferences']) || !empty($order['allergies'])): ?>
                <p style="background: #d1ecf1; padding: 8px; border-radius: 4px; border-left: 3px solid #17a2b8; margin: 10px 0; font-size: 11px;">
                    <?php if (!empty($order['dietary_preferences'])): ?>
                        <strong>🥗 Dietary Preferences:</strong>
                        <?php 
                        $prefs = json_decode($order['dietary_preferences'], true);
                        echo $prefs ? implode(', ', $prefs) : $order['dietary_preferences'];
                        ?><br>
                    <?php endif; ?>
                    <?php if (!empty($order['allergies'])): ?>
                        <strong>⚠️ Allergies:</strong>
                        <?php 
                        $allergies = json_decode($order['allergies'], true);
                        echo $allergies ? implode(', ', $allergies) : $order['allergies'];
                        ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                
                <div class="items-list">
                    <h5>🍽️ Meal Items</h5>
                    <ul>
                        <?php foreach ($order['items'] as $item): ?>
                        <li>
                            <span class="item-name"><?php echo htmlspecialchars($item['menu_name']); ?></span> 
                            <strong>x <?php echo $item['quantity']; ?></strong>
                            <?php if (!empty($item['special_requests'])): ?>
                                <div class="item-note">Note: <?php echo htmlspecialchars($item['special_requests']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['customizations'])): ?>
                                <div class="item-note">
                                    Customizations: <?php 
                                    $customs = json_decode($item['customizations'], true);
                                    echo $customs ? implode(', ', $customs) : $item['customizations'];
                                    ?>
                                </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()" style="background: #bd9379; color: white; padding: 15px 30px; border: none; border-radius: 25px; cursor: pointer; font-size: 14px; font-weight: 600; margin-right: 10px;">🖨️ Print</button>
            <button onclick="window.close()" style="background: #6c757d; color: white; padding: 15px 30px; border: none; border-radius: 25px; cursor: pointer; font-size: 14px; font-weight: 600;">❌ Close</button>
        </div>
    </body>
    </html>
    <?php
}

$page_title = "Kitchen Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SOMDUL TABLE</title>
    
    <!-- SOMDUL TABLE Brand Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* SOMDUL TABLE Brand Colors */
            --rice: #bd9379;
            --family: #ece8e1;
            --herb: #adb89d;
            --thai-curry: #cf723a;
            --white: #ffffff;
            --gray: #6c757d;
            --light-gray: #f8f9fa;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --dark: #2c3e50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Batica Sans", "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--white);
            color: #333;
            line-height: 1.6;
            font-weight: 400;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 2px 20px rgba(189, 147, 121, 0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid var(--family);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--rice);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--rice), var(--thai-curry));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--family);
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            font-weight: 500;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 6rem 2rem 2rem;
        }

        /* Controls */
        .dashboard-controls {
            background: var(--white);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(189, 147, 121, 0.1);
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
            border: 1px solid var(--family);
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .control-group label {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            padding: 1rem 1.5rem;
            border: 2px solid var(--family);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            color: #333;
            font-family: inherit;
            font-weight: 500;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--rice);
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: var(--rice);
            color: var(--white);
        }

        .btn-primary:hover {
            background: #a67c5a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(189, 147, 121, 0.3);
        }

        .btn-success {
            background: var(--herb);
            color: var(--white);
        }

        .btn-success:hover {
            background: #9aa386;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(173, 184, 157, 0.3);
        }

        .btn-info {
            background: var(--thai-curry);
            color: var(--white);
        }

        .btn-info:hover {
            background: #b8631f;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(207, 114, 58, 0.3);
        }

        .btn-secondary {
            background: var(--family);
            color: #333;
            border: 2px solid var(--family);
        }

        .btn-secondary:hover {
            background: #333;
            color: var(--white);
        }

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(189, 147, 121, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--family);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--rice), var(--thai-curry));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(189, 147, 121, 0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--rice);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            color: #333;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .orders-section, .meal-summary {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(189, 147, 121, 0.1);
            overflow: hidden;
            border: 1px solid var(--family);
        }

        .section-header {
            background: linear-gradient(135deg, var(--rice), var(--thai-curry));
            color: var(--white);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .orders-list, .summary-list {
            max-height: 70vh;
            overflow-y: auto;
        }

        .order-card {
            border-bottom: 1px solid var(--family);
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            background: var(--family);
        }

        .order-card:last-child {
            border-bottom: none;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .order-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--rice);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-not_started {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }

        .status-in_progress {
            background: #cce7ff;
            color: #0c5460;
            border: 2px solid #17a2b8;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            padding: 1rem;
            background: rgba(189, 147, 121, 0.05);
            border-radius: 12px;
            border: 1px solid var(--family);
        }

        .info-item strong {
            color: var(--rice);
            font-weight: 600;
            display: block;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .items-list {
            margin-top: 1.5rem;
        }

        .items-list strong {
            color: var(--rice);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .item {
            background: var(--white);
            padding: 1rem;
            border-radius: 12px;
            margin: 0.8rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--family);
            transition: all 0.3s ease;
        }

        .item:hover {
            border-color: var(--rice);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(189, 147, 121, 0.1);
        }

        .item-name {
            font-weight: 600;
            color: #333;
        }

        .item-quantity {
            background: var(--rice);
            color: var(--white);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            min-width: 45px;
            text-align: center;
        }

        .summary-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--family);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .summary-item:hover {
            background: var(--family);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .meal-name {
            font-weight: 600;
            color: #333;
            flex: 1;
        }

        .meal-quantity {
            background: var(--rice);
            color: var(--white);
            padding: 0.8rem 1.5rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.1rem;
            min-width: 60px;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin: 1rem 0;
            border-left: 4px solid;
            font-weight: 500;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }

        .kitchen-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            padding-top: 1.5rem;
            border-top: 2px solid var(--family);
        }

        .kitchen-actions .btn {
            font-size: 0.8rem;
            padding: 0.8rem 1.5rem;
        }

        .flash-message {
            position: fixed;
            top: 90px;
            right: 20px;
            background: var(--white);
            color: #333;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            max-width: 400px;
            border-left: 4px solid var(--success);
            animation: slideInRight 0.5s ease-out;
        }

        .flash-message.error {
            border-left-color: var(--danger);
            color: #721c24;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .dashboard-controls {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 1rem;
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
            }
            
            .container {
                padding: 5rem 1rem 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .order-info {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }
            
            .main-content {
                gap: 1.5rem;
            }
        }

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: var(--white);
            }
            
            .container {
                max-width: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header no-print">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">ST</div>
                <span>SOMDUL TABLE Kitchen</span>
            </div>
            <div class="user-info">
                👨‍🍳 <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Kitchen Staff'); ?>
                <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 1rem;">← Back</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Controls -->
        <div class="dashboard-controls no-print">
            <div class="control-group">
                <label for="date">📅 Delivery Date</label>
                <input type="date" id="date" name="date" class="form-control" 
                       value="<?php echo htmlspecialchars($selected_date); ?>"
                       onchange="filterOrders()">
            </div>
            
            <div class="control-group">
                <label for="status">🔄 Kitchen Status</label>
                <select id="status" name="status" class="form-control" onchange="filterOrders()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                    <option value="not_started" <?php echo $status_filter === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            
            <div class="control-group">
                <label>&nbsp;</label>
                <button onclick="refreshData()" class="btn btn-primary">
                    🔄 Refresh
                </button>
            </div>
            
            <div class="control-group">
                <label>&nbsp;</label>
                <a href="?date=<?php echo $selected_date; ?>&status=<?php echo $status_filter; ?>&export=print" 
                   target="_blank" class="btn btn-success">
                    🖨️ Print Sheet
                </a>
            </div>
            
            <div class="control-group">
                <label>&nbsp;</label>
                <a href="?date=<?php echo $selected_date; ?>&status=<?php echo $status_filter; ?>&export=csv" 
                   class="btn btn-info">
                    📊 Export CSV
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_meals; ?></div>
                <div class="stat-label">Total Meals</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($meal_summary); ?></div>
                <div class="stat-label">Menu Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo date('M j', strtotime($selected_date)); ?></div>
                <div class="stat-label">Selected Date</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Orders List -->
            <div class="orders-section">
                <div class="section-header">
                    <div class="section-title">📋 Order List</div>
                    <div style="font-size: 0.9rem;"><?php echo count($orders_by_date); ?> Orders</div>
                </div>
                
                <div class="orders-list">
                    <?php if (empty($orders_by_date)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>No Orders</h3>
                        <p>No orders found for the selected date</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($orders_by_date as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></div>
                                <div class="order-status status-<?php echo $order['kitchen_status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress', 
                                        'completed' => 'Completed'
                                    ];
                                    echo $status_text[$order['kitchen_status']] ?? $order['kitchen_status'];
                                    ?>
                                </div>
                            </div>
                            
                            <div class="order-info">
                                <div class="info-item">
                                    <strong>👤 Customer</strong>
                                    <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                </div>
                                <div class="info-item">
                                    <strong>📞 Phone</strong>
                                    <?php echo htmlspecialchars($order['phone'] ?? 'Not specified'); ?>
                                </div>
                                <div class="info-item">
                                    <strong>🕐 Delivery Time</strong>
                                    <?php echo htmlspecialchars($order['delivery_time_slot'] ?? 'Not specified'); ?>
                                </div>
                                <div class="info-item">
                                    <strong>🌶️ Spice Level</strong>
                                    <?php echo ucfirst($order['spice_level'] ?? 'medium'); ?>
                                </div>
                            </div>

                            <?php if (!empty($order['special_notes']) || !empty($order['subscription_instructions'])): ?>
                            <div class="alert alert-warning">
                                <strong>⚠️ Special Notes:</strong><br>
                                <?php if (!empty($order['special_notes'])): ?>
                                    <?php echo htmlspecialchars($order['special_notes']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($order['subscription_instructions'])): ?>
                                    <?php echo htmlspecialchars($order['subscription_instructions']); ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($order['dietary_preferences']) || !empty($order['allergies'])): ?>
                            <div class="alert alert-info">
                                <?php if (!empty($order['dietary_preferences'])): ?>
                                    <strong>🥗 Dietary Preferences:</strong>
                                    <?php 
                                    $prefs = json_decode($order['dietary_preferences'], true);
                                    echo $prefs ? implode(', ', $prefs) : $order['dietary_preferences'];
                                    ?><br>
                                <?php endif; ?>
                                <?php if (!empty($order['allergies'])): ?>
                                    <strong>⚠️ Allergies:</strong>
                                    <?php 
                                    $allergies = json_decode($order['allergies'], true);
                                    echo $allergies ? implode(', ', $allergies) : $order['allergies'];
                                    ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="items-list">
                                <strong>🍽️ Meal Items:</strong>
                                <?php foreach ($order['items'] as $item): ?>
                                <div class="item">
                                    <div>
                                        <div class="item-name"><?php echo htmlspecialchars($item['menu_name']); ?></div>
                                        <?php if (!empty($item['special_requests'])): ?>
                                        <div style="font-size: 0.8rem; color: var(--thai-curry); font-style: italic;">
                                            Note: <?php echo htmlspecialchars($item['special_requests']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['customizations'])): ?>
                                        <div style="font-size: 0.8rem; color: var(--herb);">
                                            Customizations: <?php 
                                            $customs = json_decode($item['customizations'], true);
                                            echo $customs ? implode(', ', $customs) : $item['customizations'];
                                            ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-quantity"><?php echo $item['quantity']; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Kitchen Action Buttons -->
                            <div class="kitchen-actions">
                                <?php if ($order['kitchen_status'] === 'not_started'): ?>
                                <button onclick="updateKitchenStatus('<?php echo $order['order_id']; ?>', 'in_progress')" 
                                        class="btn btn-primary">
                                    ▶️ Start Cooking
                                </button>
                                <?php elseif ($order['kitchen_status'] === 'in_progress'): ?>
                                <button onclick="updateKitchenStatus('<?php echo $order['order_id']; ?>', 'completed')" 
                                        class="btn btn-success">
                                    ✅ Mark Complete
                                </button>
                                <?php else: ?>
                                <span style="color: var(--success); font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                                    ✅ Completed
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Meal Summary -->
            <div class="meal-summary">
                <div class="section-header">
                    <div class="section-title">📊 Meal Summary</div>
                    <div style="font-size: 0.9rem;"><?php echo count($meal_summary); ?> Types</div>
                </div>
                
                <div class="summary-list">
                    <?php if (empty($meal_summary)): ?>
                    <div class="empty-state">
                        <div class="icon">📊</div>
                        <h4>No Data</h4>
                        <p>No meals for selected date</p>
                    </div>
                    <?php else: ?>
                        <?php 
                        // Sort meals by quantity (highest first)
                        uasort($meal_summary, function($a, $b) {
                            return $b['total_quantity'] - $a['total_quantity'];
                        });
                        ?>
                        <?php foreach ($meal_summary as $meal): ?>
                        <div class="summary-item">
                            <div class="meal-info" style="flex: 1;">
                                <div class="meal-name"><?php echo htmlspecialchars($meal['name']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--gray);">
                                    <?php echo htmlspecialchars($meal['category']); ?> • 
                                    <?php echo $meal['prep_time']; ?> min • 
                                    <?php echo ucfirst($meal['spice_level']); ?>
                                </div>
                                <?php if (!empty($meal['customizations'])): ?>
                                <div style="font-size: 0.75rem; color: var(--herb); margin-top: 0.2rem;">
                                    Customizations: <?php echo implode(', ', array_unique($meal['customizations'])); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($meal['special_requests'])): ?>
                                <div style="font-size: 0.75rem; color: var(--thai-curry); margin-top: 0.2rem;">
                                    Special Requests: <?php echo implode(', ', array_unique($meal['special_requests'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="meal-quantity"><?php echo $meal['total_quantity']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function filterOrders() {
            const date = document.getElementById('date').value;
            const status = document.getElementById('status').value;
            
            const url = new URL(window.location);
            url.searchParams.set('date', date);
            url.searchParams.set('status', status);
            
            window.location = url.toString();
        }

        function refreshData() {
            window.location.reload();
        }

        function updateKitchenStatus(orderId, newStatus) {
            if (!confirm('Are you sure you want to change the status of this order?')) {
                return;
            }

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const orderIdInput = document.createElement('input');
            orderIdInput.type = 'hidden';
            orderIdInput.name = 'order_id';
            orderIdInput.value = orderId;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = newStatus;
            
            form.appendChild(orderIdInput);
            form.appendChild(statusInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshData();
            }
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                const printUrl = '?date=<?php echo $selected_date; ?>&status=<?php echo $status_filter; ?>&export=print';
                window.open(printUrl, '_blank');
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('SOMDUL TABLE Kitchen Dashboard loaded');
            console.log('Orders:', <?php echo count($orders_by_date); ?>);
            console.log('Meals:', <?php echo $total_meals; ?>);
            
            // Focus on date input if no orders
            <?php if (empty($orders_by_date)): ?>
                document.getElementById('date').focus();
            <?php endif; ?>
        });
    </script>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="flash-message <?php echo $_SESSION['flash_type'] === 'error' ? 'error' : ''; ?>" id="flash-message">
        <strong><?php echo $_SESSION['flash_type'] === 'success' ? '✅' : '❌'; ?> </strong>
        <?php 
        echo htmlspecialchars($_SESSION['flash_message']); 
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        ?>
    </div>
    <script>
        setTimeout(function() {
            const msg = document.getElementById('flash-message');
            if (msg) {
                msg.style.opacity = '0';
                msg.style.transform = 'translateX(100%)';
                setTimeout(() => msg.remove(), 300);
            }
        }, 5000);
    </script>
    <?php endif; ?>
</body>
</html>
