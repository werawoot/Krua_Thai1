<?php
/**
 * Krua Thai - Edit Order
 * File: admin/edit-order.php
 * Description: Complete order editing with real-time updates
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Path ที่ถูกต้องสำหรับ admin folder
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

// Get order ID from URL
$order_id = $_GET['id'] ?? '';
if (empty($order_id)) {
    header("Location: orders.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update order
        if (isset($_POST['update_order'])) {
            $delivery_date = $_POST['delivery_date'] ?? '';
            $delivery_time_slot = $_POST['delivery_time_slot'] ?? '';
            $delivery_address = $_POST['delivery_address'] ?? '';
            $delivery_instructions = $_POST['delivery_instructions'] ?? '';
            $status = $_POST['status'] ?? '';
            $kitchen_status = $_POST['kitchen_status'] ?? '';
            $assigned_rider_id = $_POST['assigned_rider_id'] ?? null;
            $special_notes = $_POST['special_notes'] ?? '';
            
            if (empty($assigned_rider_id)) $assigned_rider_id = null;
            
            $stmt = $pdo->prepare("
                UPDATE orders SET 
                    delivery_date = ?, 
                    delivery_time_slot = ?, 
                    delivery_address = ?, 
                    delivery_instructions = ?, 
                    status = ?, 
                    kitchen_status = ?, 
                    assigned_rider_id = ?, 
                    special_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $delivery_date, $delivery_time_slot, $delivery_address, 
                $delivery_instructions, $status, $kitchen_status, 
                $assigned_rider_id, $special_notes, $order_id
            ]);
            
            $success_message = "Order updated successfully!";
        }
        
        // Add new item
        if (isset($_POST['add_item'])) {
            $menu_id = $_POST['new_menu_id'] ?? '';
            $quantity = (int)($_POST['new_quantity'] ?? 1);
            
            if (!empty($menu_id)) {
                // Get menu details
                $stmt = $pdo->prepare("SELECT name, base_price FROM menus WHERE id = ?");
                $stmt->execute([$menu_id]);
                $menu = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($menu) {
                    $stmt = $pdo->prepare("
                        INSERT INTO order_items 
                        (id, order_id, menu_id, menu_name, menu_price, quantity, item_status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                    ");
                    $stmt->execute([
                        generateUUID(), $order_id, $menu_id, 
                        $menu['name'], $menu['base_price'], $quantity
                    ]);
                    
                    // Update total items count
                    $stmt = $pdo->prepare("
                        UPDATE orders SET 
                            total_items = (SELECT COUNT(*) FROM order_items WHERE order_id = ?),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$order_id, $order_id]);
                    
                    $success_message = "Item added successfully!";
                }
            }
        }
        
        // Update item
        if (isset($_POST['update_item'])) {
            $item_id = $_POST['item_id'] ?? '';
            $quantity = (int)($_POST['quantity'] ?? 1);
            $item_status = $_POST['item_status'] ?? '';
            $special_requests = $_POST['special_requests'] ?? '';
            
            $stmt = $pdo->prepare("
                UPDATE order_items SET 
                    quantity = ?, 
                    item_status = ?, 
                    special_requests = ?,
                    updated_at = NOW()
                WHERE id = ? AND order_id = ?
            ");
            $stmt->execute([$quantity, $item_status, $special_requests, $item_id, $order_id]);
            
            $success_message = "Item updated successfully!";
        }
        
        // Delete item
        if (isset($_POST['delete_item'])) {
            $item_id = $_POST['item_id'] ?? '';
            
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
            $stmt->execute([$item_id, $order_id]);
            
            // Update total items count
            $stmt = $pdo->prepare("
                UPDATE orders SET 
                    total_items = (SELECT COUNT(*) FROM order_items WHERE order_id = ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$order_id, $order_id]);
            
            $success_message = "Item deleted successfully!";
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get order details
try {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               u.phone as customer_phone,
               CONCAT(r.first_name, ' ', r.last_name) as rider_name
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN users r ON o.assigned_rider_id = r.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: orders.php");
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name as menu_name_current, m.base_price as current_price
        FROM order_items oi
        LEFT JOIN menus m ON oi.menu_id = m.id
        WHERE oi.order_id = ?
        ORDER BY oi.created_at ASC
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available menus for adding
    $stmt = $pdo->prepare("
        SELECT id, name, name_thai, base_price
        FROM menus 
        WHERE is_available = 1 
        ORDER BY name ASC
    ");
    $stmt->execute();
    $available_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available riders
    $stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name
        FROM users 
        WHERE role = 'rider' AND status = 'active'
        ORDER BY first_name ASC
    ");
    $stmt->execute();
    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error loading order: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order <?= htmlspecialchars($order['order_number']) ?> - Krua Thai Admin</title>
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
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
        }

        .order-number {
            color: var(--curry);
            font-size: 1.2rem;
            margin-top: 0.5rem;
        }

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
        }

        .btn-success {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: var(--white);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: var(--white);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .grid {
            display: grid;
            gap: 2rem;
        }

        .grid-2 {
            grid-template-columns: 1fr 1fr;
        }

        .card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-confirmed {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-preparing {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-ready {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .status-out_for_delivery {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-delivered {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .items-table th,
        .items-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .items-table th {
            background: var(--cream);
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .items-table tbody tr:hover {
            background: #fafafa;
        }

        .item-actions {
            display: flex;
            gap: 0.5rem;
        }

        .add-item-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            margin-top: 1rem;
            border: 2px dashed var(--border-light);
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .customer-info {
            display: grid;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .info-label {
            font-weight: 600;
            color: var(--text-gray);
        }

        .info-value {
            color: var(--text-dark);
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-edit" style="color: var(--curry); margin-right: 0.5rem;"></i>
                    Edit Order
                </h1>
                <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
            </div>
            <div>
                <a href="orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Orders
                </a>
                <button onclick="printOrder()" class="btn btn-primary">
                    <i class="fas fa-print"></i>
                    Print Order
                </button>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-2">
            <!-- Order Details -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        Order Details
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" name="delivery_date" class="form-control" 
                                   value="<?= htmlspecialchars($order['delivery_date']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Delivery Time Slot</label>
                            <select name="delivery_time_slot" class="form-control" required>
                                <option value="09:00-12:00" <?= $order['delivery_time_slot'] === '09:00-12:00' ? 'selected' : '' ?>>Morning (09:00-12:00)</option>
                                <option value="12:00-15:00" <?= $order['delivery_time_slot'] === '12:00-15:00' ? 'selected' : '' ?>>Afternoon (12:00-15:00)</option>
                                <option value="15:00-18:00" <?= $order['delivery_time_slot'] === '15:00-18:00' ? 'selected' : '' ?>>Evening (15:00-18:00)</option>
                                <option value="18:00-21:00" <?= $order['delivery_time_slot'] === '18:00-21:00' ? 'selected' : '' ?>>Night (18:00-21:00)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Delivery Address</label>
                            <textarea name="delivery_address" class="form-control" rows="3" required><?= htmlspecialchars($order['delivery_address']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Delivery Instructions</label>
                            <textarea name="delivery_instructions" class="form-control" rows="2"><?= htmlspecialchars($order['delivery_instructions'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Order Status</label>
                            <select name="status" class="form-control" required>
                                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Ready</option>
                                <option value="out_for_delivery" <?= $order['status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                                <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kitchen Status</label>
                            <select name="kitchen_status" class="form-control" required>
                                <option value="not_started" <?= $order['kitchen_status'] === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                                <option value="in_progress" <?= $order['kitchen_status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="completed" <?= $order['kitchen_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Assigned Rider</label>
                            <select name="assigned_rider_id" class="form-control">
                                <option value="">Select Rider</option>
                                <?php foreach ($riders as $rider): ?>
                                    <option value="<?= $rider['id'] ?>" <?= $order['assigned_rider_id'] === $rider['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rider['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Special Notes</label>
                            <textarea name="special_notes" class="form-control" rows="3"><?= htmlspecialchars($order['special_notes'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" name="update_order" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Order
                        </button>
                    </form>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-user"></i>
                        Customer Information
                    </h2>
                </div>
                <div class="card-body">
                    <div class="customer-info">
                        <div class="info-item">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?= htmlspecialchars($order['customer_email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?= htmlspecialchars($order['customer_phone']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Order Number:</span>
                            <span class="info-value"><?= htmlspecialchars($order['order_number']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Current Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Items:</span>
                            <span class="info-value"><?= $order['total_items'] ?> items</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Created:</span>
                            <span class="info-value"><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Updated:</span>
                            <span class="info-value"><?= date('M d, Y H:i', strtotime($order['updated_at'])) ?></span>
                        </div>
                        <?php if ($order['rider_name']): ?>
                        <div class="info-item">
                            <span class="info-label">Assigned Rider:</span>
                            <span class="info-value"><?= htmlspecialchars($order['rider_name']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-utensils"></i>
                    Order Items (<?= count($order_items) ?>)
                </h2>
            </div>
            <div class="card-body">
                <?php if (!empty($order_items)): ?>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Special Requests</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <form method="POST" style="display: contents;">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($item['menu_name']) ?></strong>
                                    <?php if ($item['menu_name_current'] && $item['menu_name_current'] !== $item['menu_name']): ?>
                                        <br><small style="color: var(--text-gray);">Current: <?= htmlspecialchars($item['menu_name_current']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>฿<?= number_format($item['menu_price'], 2) ?></td>
                                <td>
                                    <input type="number" name="quantity" value="<?= $item['quantity'] ?>" 
                                           min="1" max="10" class="form-control" style="width: 80px;">
                                </td>
                                <td>
                                    <select name="item_status" class="form-control">
                                        <option value="pending" <?= $item['item_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="preparing" <?= $item['item_status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                        <option value="ready" <?= $item['item_status'] === 'ready' ? 'selected' : '' ?>>Ready</option>
                                        <option value="served" <?= $item['item_status'] === 'served' ? 'selected' : '' ?>>Served</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="special_requests" 
                                           value="<?= htmlspecialchars($item['special_requests'] ?? '') ?>" 
                                           class="form-control" placeholder="Special requests...">
                                </td>
                                <td>
                                    <div class="item-actions">
                                        <button type="submit" name="update_item" class="btn btn-success btn-sm">
                                            <i class="fas fa-save"></i>
                                        </button>
                                        <button type="submit" name="delete_item" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Delete this item?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                    <i class="fas fa-utensils" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <h3>No items in this order</h3>
                    <p>Add items using the form below</p>
                </div>
                <?php endif; ?>

                <!-- Add New Item Form -->
                <div class="add-item-form">
                    <h3 style="margin-bottom: 1rem;">
                        <i class="fas fa-plus"></i>
                        Add New Item
                    </h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Select Menu</label>
                                <select name="new_menu_id" class="form-control" required>
                                    <option value="">Choose a menu...</option>
                                    <?php foreach ($available_menus as $menu): ?>
                                        <option value="<?= $menu['id'] ?>" data-price="<?= $menu['base_price'] ?>">
                                            <?= htmlspecialchars($menu['name']) ?>
                                            <?php if ($menu['name_thai']): ?>
                                                (<?= htmlspecialchars($menu['name_thai']) ?>)
                                            <?php endif; ?>
                                            - ฿<?= number_format($menu['base_price'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="new_quantity" class="form-control" 
                                       value="1" min="1" max="10" required>
                            </div>
                            <div class="form-group">
                                <button type="submit" name="add_item" class="btn btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Add Item
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Order History/Timeline -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-history"></i>
                    Order Timeline
                </h2>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Order Created</h4>
                            <p><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></p>
                            <small>Order <?= htmlspecialchars($order['order_number']) ?> was created</small>
                        </div>
                    </div>

                    <?php if ($order['status'] !== 'pending'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Status Updated</h4>
                            <p><?= date('M d, Y H:i', strtotime($order['updated_at'])) ?></p>
                            <small>Status changed to: <span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst(str_replace('_', ' ', $order['status'])) ?></span></small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($order['assigned_rider_id']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Rider Assigned</h4>
                            <p><?= date('M d, Y H:i', strtotime($order['updated_at'])) ?></p>
                            <small>Assigned to: <?= htmlspecialchars($order['rider_name']) ?></small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($order['delivered_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker completed"></div>
                        <div class="timeline-content">
                            <h4>Order Delivered</h4>
                            <p><?= date('M d, Y H:i', strtotime($order['delivered_at'])) ?></p>
                            <small>Order successfully delivered to customer</small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Timeline Styles */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-light);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }

        .timeline-marker {
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--curry);
            border: 3px solid var(--white);
            box-shadow: 0 0 0 3px var(--border-light);
        }

        .timeline-marker.completed {
            background: var(--sage);
        }

        .timeline-content h4 {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .timeline-content p {
            margin-bottom: 0.25rem;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .timeline-content small {
            color: var(--text-gray);
        }
    </style>

    <script>
        // Auto-save functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add change listeners for auto-save
            const statusSelect = document.querySelector('select[name="status"]');
            const kitchenStatusSelect = document.querySelector('select[name="kitchen_status"]');
            
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    if (confirm('Update order status?')) {
                        this.form.submit();
                    }
                });
            }
        });

    // 🔧 แทนที่ฟังก์ชัน printOrder() ใน edit-order.php
// หาบรรทัด 734 และแทนที่ด้วยโค้ดนี้

// 🔧 หาและแทนที่ฟังก์ชัน printOrder() ในไฟล์ admin/edit-order.php
// ประมาณบรรทัด 734-850

function printOrder() {
    const orderNumber = '<?= htmlspecialchars($order['order_number']) ?>';
    const printWindow = window.open('', '_blank');
    
    // ✅ แยก PHP code ออกจาก JavaScript template literal
    let itemsHtml = '';
    <?php foreach ($order_items as $item): ?>
    itemsHtml += '<tr>' +
        '<td><?= htmlspecialchars($item['menu_name']) ?></td>' +
        '<td>฿<?= number_format($item['menu_price'], 2) ?></td>' +
        '<td><?= $item['quantity'] ?></td>' +
        '<td>฿<?= number_format($item['menu_price'] * $item['quantity'], 2) ?></td>' +
        '</tr>';
    <?php endforeach; ?>
    
    // ✅ ใช้ string concatenation แทน template literal
    const htmlContent = '<!DOCTYPE html>' +
        '<html>' +
        '<head>' +
            '<title>Order ' + orderNumber + '</title>' +
            '<style>' +
                'body { font-family: Arial, sans-serif; margin: 20px; }' +
                '.header { text-align: center; margin-bottom: 30px; }' +
                '.order-info { margin-bottom: 20px; }' +
                '.items-table { width: 100%; border-collapse: collapse; }' +
                '.items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }' +
                '.items-table th { background: #f5f5f5; }' +
                '@media print { .no-print { display: none; } }' +
            '</style>' +
        '</head>' +
        '<body>' +
            '<div class="header">' +
                '<h1>Krua Thai</h1>' +
                '<h2>Order: ' + orderNumber + '</h2>' +
            '</div>' +
            
            '<div class="order-info">' +
                '<p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>' +
                '<p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>' +
                '<p><strong>Delivery Date:</strong> <?= date('M d, Y', strtotime($order['delivery_date'])) ?></p>' +
                '<p><strong>Time Slot:</strong> <?= htmlspecialchars($order['delivery_time_slot']) ?></p>' +
                '<p><strong>Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>' +
                <?php if ($order['delivery_instructions']): ?>
                '<p><strong>Instructions:</strong> <?= htmlspecialchars($order['delivery_instructions']) ?></p>' +
                <?php endif; ?>
            '</div>' +
            
            '<table class="items-table">' +
                '<thead>' +
                    '<tr>' +
                        '<th>Menu</th>' +
                        '<th>Price</th>' +
                        '<th>Quantity</th>' +
                        '<th>Total</th>' +
                    '</tr>' +
                '</thead>' +
                '<tbody>' +
                    itemsHtml +
                '</tbody>' +
            '</table>' +
            
            '<div style="margin-top: 20px;">' +
                '<p><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $order['status'])) ?></p>' +
                '<p><strong>Kitchen Status:</strong> <?= ucfirst(str_replace('_', ' ', $order['kitchen_status'])) ?></p>' +
                <?php if ($order['special_notes']): ?>
                '<p><strong>Special Notes:</strong> <?= htmlspecialchars($order['special_notes']) ?></p>' +
                <?php endif; ?>
            '</div>' +
            
            '<script>' +
                'window.onload = function() {' +
                    'window.print();' +
                    'window.onafterprint = function() { window.close(); }' +
                '}' +
            '</script>' +
        '</body>' +
        '</html>';
    
    printWindow.document.write(htmlContent);
    printWindow.document.close();
}

        // Confirm before deleting items
        document.addEventListener('click', function(e) {
            if (e.target.closest('button[name="delete_item"]')) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            }
        });

        // Auto-calculate total when adding items
        document.addEventListener('change', function(e) {
            if (e.target.name === 'new_menu_id') {
                const selectedOption = e.target.options[e.target.selectedIndex];
                const price = selectedOption.dataset.price;
                if (price) {
                    console.log('Selected menu price:', price);
                }
            }
        });

        // Show success/error messages
        <?php if ($success_message): ?>
        setTimeout(function() {
            document.querySelector('.alert-success')?.remove();
        }, 5000);
        <?php endif; ?>

        <?php if ($error_message): ?>
        setTimeout(function() {
            document.querySelector('.alert-error')?.remove();
        }, 10000);
        <?php endif; ?>

        console.log('Edit Order page loaded successfully');
    </script>
</body>
</html>