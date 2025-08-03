<?php
/**
 * Krua Thai - Enhanced Order Confirmation Page
 * File: order-confirmation.php
 * Optimized for guest-checkout.php integration
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Enhanced Security & Data Validation
$order_id = $_GET['order'] ?? '';
$has_session_data = isset($_SESSION['order_success']);
$order_data = null;
$errors = [];

// Try to get order data from session first (fresh order)
if ($has_session_data) {
    $order_data = $_SESSION['order_success'];
    // Keep session for 5 minutes in case of refresh
    if (!isset($_SESSION['order_success_timestamp'])) {
        $_SESSION['order_success_timestamp'] = time();
    } elseif (time() - $_SESSION['order_success_timestamp'] > 300) {
        unset($_SESSION['order_success'], $_SESSION['order_success_timestamp']);
        $has_session_data = false;
    }
}

// If no session data, try to fetch from database
if (!$has_session_data && !empty($order_id)) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Fetch order details from database
        $stmt = $pdo->prepare("
            SELECT o.*, u.first_name, u.last_name, u.email, u.phone, u.delivery_address,
                   p.transaction_id, p.payment_method, p.amount as payment_amount
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN payments p ON p.user_id = u.id 
            WHERE o.id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY o.created_at DESC LIMIT 1
        ");
        $stmt->execute([$order_id]);
        $order_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order_record) {
            // Fetch order items
            $stmt = $pdo->prepare("
                SELECT oi.*, m.name as menu_name, m.description, m.prep_time, 
                       m.spice_level, m.main_image_url
                FROM order_items oi
                LEFT JOIN menus m ON oi.menu_id = m.id
                WHERE oi.order_id = ?
                ORDER BY oi.created_at
            ");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Reconstruct order data in session format
            $order_data = [
                'order_number' => $order_record['order_number'],
                'total' => $order_record['total_amount'],
                'email' => $order_record['email'],
                'delivery_date' => $order_record['delivery_date'],
                'source' => 'database',
                'customer_name' => trim($order_record['first_name'] . ' ' . $order_record['last_name']),
                'phone' => $order_record['phone'],
                'delivery_address' => $order_record['delivery_address'],
                'payment_method' => $order_record['payment_method'],
                'transaction_id' => $order_record['transaction_id'],
                'order_status' => $order_record['status'],
                'created_at' => $order_record['created_at'],
                'items' => []
            ];
            
            // Convert order items to expected format
            foreach ($order_items as $item) {
                $order_data['items'][] = [
                    'id' => $item['menu_id'],
                    'name' => $item['menu_name'] ?: 'Thai Meal Kit',
                    'base_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_price' => $item['total_price'],
                    'description' => $item['special_requests'] ?: ($item['description'] ?? ''),
                    'prep_time' => $item['prep_time'],
                    'spice_level' => $item['spice_level'],
                    'image_url' => $item['main_image_url']
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Order confirmation database error: " . $e->getMessage());
        $errors[] = "Unable to load order details from database.";
    }
}

// If still no data, redirect with error
if (!$order_data) {
    $_SESSION['error_message'] = "Order not found or session expired. Please check your email for confirmation details.";
    header('Location: meal-kit.php');
    exit;
}

// Extract order information with fallbacks
$order_number = $order_data['order_number'] ?? 'N/A';
$order_total = $order_data['total'] ?? 0;
$order_items = $order_data['items'] ?? [];
$customer_email = $order_data['email'] ?? '';
$customer_name = $order_data['customer_name'] ?? 'Valued Customer';
$delivery_date = $order_data['delivery_date'] ?? '';
$checkout_source = $order_data['source'] ?? 'guest';
$payment_method = $order_data['payment_method'] ?? '';
$transaction_id = $order_data['transaction_id'] ?? '';
$order_status = $order_data['order_status'] ?? 'pending';
$phone = $order_data['phone'] ?? '';
$delivery_address = $order_data['delivery_address'] ?? '';

// Enhanced helper functions
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

function formatDeliveryDate($date) {
    if (empty($date)) return 'To be scheduled';
    
    $timestamp = strtotime($date);
    $today = strtotime('today');
    $tomorrow = strtotime('tomorrow');
    
    if ($timestamp == $today) {
        return 'Today (' . date('M j', $timestamp) . ')';
    } elseif ($timestamp == $tomorrow) {
        return 'Tomorrow (' . date('M j', $timestamp) . ')';
    } else {
        return date('l, F j, Y', $timestamp);
    }
}

function getPaymentMethodDisplay($method) {
    $methods = [
        'credit_card' => '💳 Credit/Debit Card',
        'apple_pay' => '🍎 Apple Pay',
        'google_pay' => '🅖 Google Pay',
        'paypal' => '🅿️ PayPal',
        'bank_transfer' => '🏦 Bank Transfer'
    ];
    return $methods[$method] ?? '💳 ' . ucfirst(str_replace('_', ' ', $method));
}

function getStatusBadge($status) {
    $statuses = [
        'pending' => ['⏳', '#f39c12', 'Order Received'],
        'confirmed' => ['✅', '#27ae60', 'Confirmed'],
        'preparing' => ['👨‍🍳', '#3498db', 'Being Prepared'],
        'ready' => ['📦', '#9b59b6', 'Ready for Delivery'],
        'out_for_delivery' => ['🚚', '#e67e22', 'Out for Delivery'],
        'delivered' => ['🎉', '#27ae60', 'Delivered'],
        'cancelled' => ['❌', '#e74c3c', 'Cancelled']
    ];
    
    $info = $statuses[$status] ?? ['📋', '#7f8c8d', ucfirst($status)];
    return [
        'icon' => $info[0],
        'color' => $info[1],
        'text' => $info[2]
    ];
}

// Calculate order summary
$item_count = array_sum(array_column($order_items, 'quantity'));
$subtotal = array_sum(array_map(function($item) {
    return $item['base_price'] * $item['quantity'];
}, $order_items));

// Delivery fee calculation (matching guest-checkout.php logic)
$delivery_fee = $subtotal >= 25 ? 0 : 3.99;
$tax_rate = 0.0825; // 8.25%
$tax_amount = $subtotal * $tax_rate;
$calculated_total = $subtotal + $delivery_fee + $tax_amount;

// Use calculated total if order total seems incorrect
if (abs($order_total - $calculated_total) > 0.01) {
    $order_total = $calculated_total;
}

$status_info = getStatusBadge($order_status);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - <?php echo htmlspecialchars($order_number); ?> | Krua Thai</title>
    <meta name="description" content="Your Thai meal kit order has been confirmed and will be delivered fresh to your door!">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- BaticaSans Font -->
    <link rel="preconnect" href="https://ydpschool.com">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2');
            font-weight: 400;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2');
            font-weight: 700;
            font-display: swap;
        }

        :root {
            --curry: #cf723a;
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --success: #27ae60;
            --warning: #f39c12;
            --info: #3498db;
            --shadow: 0 8px 32px rgba(189, 147, 121, 0.15);
            --shadow-lg: 0 12px 40px rgba(189, 147, 121, 0.2);
            --radius: 16px;
            --radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, #f8f9fa 0%, var(--cream) 100%);
            min-height: 100vh;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--curry);
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .print-btn {
            background: transparent;
            border: 2px solid var(--curry);
            color: var(--curry);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
            transition: var(--transition);
        }

        .print-btn:hover {
            background: var(--curry);
            color: var(--white);
        }

        /* Main Content */
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Success Header */
        .success-header {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 3rem 2rem;
            box-shadow: var(--shadow-lg);
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .success-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--curry), var(--sage));
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--success), #2ecc71);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 3rem;
            color: var(--white);
            animation: successPulse 2s infinite;
            box-shadow: 0 8px 20px rgba(39, 174, 96, 0.3);
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .success-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .order-number {
            font-size: 1.1rem;
            color: var(--curry);
            font-weight: 600;
            margin-bottom: 1rem;
            font-family: 'Courier New', monospace;
        }

        .success-message {
            font-size: 1.1rem;
            color: var(--text-gray);
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 1rem;
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid;
        }

        /* Order Summary Cards */
        .order-summary {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .order-details, .order-totals {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-grid {
            display: grid;
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-gray);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 140px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
            text-align: right;
            flex: 1;
            word-break: break-word;
        }

        /* Order Items */
        .order-items {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .item {
            display: flex;
            gap: 1rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid #f1f3f4;
            align-items: center;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 70px;
            height: 70px;
            background: linear-gradient(45deg, var(--curry), var(--brown));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .item-content {
            flex: 1;
        }

        .item-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .item-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .item-description {
            font-size: 0.9rem;
            color: var(--text-gray);
            font-style: italic;
        }

        .item-price-section {
            text-align: right;
            min-width: 100px;
        }

        .item-quantity {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.25rem;
        }

        .item-price {
            font-weight: 700;
            color: var(--curry);
            font-size: 1.2rem;
        }

        /* Price Breakdown */
        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .price-row:last-child {
            border-bottom: none;
            border-top: 2px solid var(--curry);
            padding-top: 1rem;
            margin-top: 0.5rem;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--curry);
        }

        .price-label {
            font-weight: 500;
        }

        .price-value {
            font-weight: 600;
        }

        .free-delivery {
            color: var(--success) !important;
            font-weight: 700;
        }

        /* Next Steps */
        .next-steps {
            background: var(--cream);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .step-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: var(--transition);
        }

        .step-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--curry);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .step-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-dark);
        }

        .step-description {
            color: var(--text-gray);
            line-height: 1.5;
        }

        /* Action Buttons */
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin: 2rem 0;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 180px;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        /* Support Info */
        .support-section {
            background: linear-gradient(135deg, var(--sage), #9db89a);
            color: var(--white);
            padding: 2rem;
            border-radius: var(--radius);
            text-align: center;
            margin-top: 2rem;
        }

        .support-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .support-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .support-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .order-summary {
                grid-template-columns: 1fr;
            }

            .success-header {
                padding: 2rem 1.5rem;
            }

            .success-title {
                font-size: 2rem;
            }

            .actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }

            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .detail-value {
                text-align: left;
            }

            .item {
                flex-direction: column;
                text-align: center;
            }

            .item-price-section {
                text-align: center;
            }

            .nav-actions {
                display: none;
            }
        }

        /* Print Styles */
        @media print {
            .navbar, .actions, .support-section, .next-steps, .print-btn {
                display: none !important;
            }
            
            body {
                background: white !important;
            }
            
            .success-header, .order-details, .order-totals, .order-items {
                box-shadow: none !important;
                border: 1px solid #ddd;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Krua Thai" style="height: 40px; width: auto;">
                <span class="logo-text">Krua Thai</span>
            </a>
            <div class="nav-actions">
                <button onclick="window.print()" class="print-btn">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Success Header -->
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="success-title">Order Confirmed!</h1>
            <div class="order-number">Order #<?php echo htmlspecialchars($order_number); ?></div>
            <div class="status-badge" style="border-color: <?php echo $status_info['color']; ?>; color: <?php echo $status_info['color']; ?>;">
                <span><?php echo $status_info['icon']; ?></span>
                <span><?php echo $status_info['text']; ?></span>
            </div>
            <p class="success-message">
                Thank you<?php echo $customer_name !== 'Valued Customer' ? ', ' . htmlspecialchars($customer_name) : ''; ?>! 
                Your delicious Thai meal kit<?php echo $item_count > 1 ? 's' : ''; ?> 
                <?php echo $item_count > 1 ? 'are' : 'is'; ?> being prepared and will be delivered fresh to your door.
            </p>
        </div>

        <!-- Order Summary -->
        <div class="order-summary">
            <div class="order-details">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Order Details
                </h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-envelope"></i>
                            Email
                        </span>
                        <span class="detail-value"><?php echo htmlspecialchars($customer_email); ?></span>
                    </div>
                    <?php if ($phone): ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-phone"></i>
                            Phone
                        </span>
                        <span class="detail-value"><?php echo htmlspecialchars($phone); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-calendar"></i>
                            Delivery
                        </span>
                        <span class="detail-value"><?php echo formatDeliveryDate($delivery_date); ?></span>
                    </div>
                    <?php if ($delivery_address): ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-map-marker-alt"></i>
                            Address
                        </span>
                        <span class="detail-value"><?php echo htmlspecialchars($delivery_address); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-credit-card"></i>
                            Payment
                        </span>
                        <span class="detail-value"><?php echo getPaymentMethodDisplay($payment_method); ?></span>
                    </div>
                    <?php if ($transaction_id): ?>
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="fas fa-receipt"></i>
                            Transaction
                        </span>
                        <span class="detail-value" style="font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($transaction_id); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
<div class="order-totals">
                <h3 class="card-title">
                    <i class="fas fa-calculator"></i>
                    Order Summary
                </h3>
                <div class="price-row">
                    <span class="price-label">Subtotal (<?php echo $item_count; ?> item<?php echo $item_count > 1 ? 's' : ''; ?>)</span>
                    <span class="price-value"><?php echo formatPrice($subtotal); ?></span>
                </div>
                <div class="price-row">
                    <span class="price-label">Delivery Fee</span>
                    <span class="price-value <?php echo $delivery_fee == 0 ? 'free-delivery' : ''; ?>">
                        <?php echo $delivery_fee == 0 ? 'FREE' : formatPrice($delivery_fee); ?>
                    </span>
                </div>
                <?php if ($subtotal < 25 && $delivery_fee > 0): ?>
                <div style="font-size: 0.85rem; color: var(--text-gray); font-style: italic; margin: 0.5rem 0;">
                    💡 Add <?php echo formatPrice(25 - $subtotal); ?> more for free delivery next time!
                </div>
                <?php endif; ?>
                <div class="price-row">
                    <span class="price-label">Tax (8.25%)</span>
                    <span class="price-value"><?php echo formatPrice($tax_amount); ?></span>
                </div>
                <div class="price-row">
                    <span class="price-label">Total</span>
                    <span class="price-value"><?php echo formatPrice($order_total); ?></span>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <?php if (!empty($order_items)): ?>
        <div class="order-items">
            <h3 class="card-title">
                <i class="fas fa-utensils"></i>
                Your Order (<?php echo $item_count; ?> item<?php echo $item_count > 1 ? 's' : ''; ?>)
            </h3>
            <?php foreach ($order_items as $item): ?>
            <div class="item">
                <div class="item-image">
                    <?php if (isset($item['image_url']) && !empty($item['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                    <?php else: ?>
                        <i class="fas fa-bowl-food"></i>
                    <?php endif; ?>
                </div>
                <div class="item-content">
                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <div class="item-meta">
                        <?php if (isset($item['prep_time']) && $item['prep_time']): ?>
                            <span><i class="fas fa-clock"></i> <?php echo $item['prep_time']; ?> mins</span>
                        <?php endif; ?>
                        <?php if (isset($item['spice_level']) && $item['spice_level']): ?>
                            <span><i class="fas fa-pepper-hot"></i> <?php echo htmlspecialchars($item['spice_level']); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-users"></i> Serves 2-3</span>
                    </div>
                    <?php if (isset($item['description']) && !empty($item['description'])): ?>
                        <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="item-price-section">
                    <?php if ($item['quantity'] > 1): ?>
                        <div class="item-quantity">Qty: <?php echo $item['quantity']; ?> × <?php echo formatPrice($item['base_price']); ?></div>
                    <?php endif; ?>
                    <div class="item-price">
                        <?php echo formatPrice($item['base_price'] * $item['quantity']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Next Steps -->
        <div class="next-steps">
            <h3 class="card-title">
                <i class="fas fa-route"></i>
                What Happens Next?
            </h3>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-number">1</div>
                        <div class="step-title">Email Confirmation</div>
                    </div>
                    <div class="step-description">
                        You'll receive a detailed confirmation email with your order information and tracking details within 5 minutes.
                    </div>
                </div>
                
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <div class="step-title">Kitchen Preparation</div>
                    </div>
                    <div class="step-description">
                        Our Thai chefs will prepare your fresh ingredients and authentic curry pastes with traditional techniques.
                    </div>
                </div>
                
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-number">3</div>
                        <div class="step-title">Quality & Packaging</div>
                    </div>
                    <div class="step-description">
                        Everything is carefully quality-checked and packaged with ice packs to maintain freshness during delivery.
                    </div>
                </div>
                
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-number">4</div>
                        <div class="step-title">Fresh Delivery</div>
                    </div>
                    <div class="step-description">
                        Your meal kit will be delivered on <?php echo formatDeliveryDate($delivery_date); ?>. We'll notify you when it's on the way!
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="actions">
            <a href="meal-kit.php" class="btn btn-secondary">
                <i class="fas fa-shopping-cart"></i>
                Order More Kits
            </a>
            <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="register.php?email=<?php echo urlencode($customer_email); ?>&ref=order_confirmation" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Create Account
            </a>
            <?php else: ?>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i>
                View Dashboard
            </a>
            <?php endif; ?>
        </div>

        <!-- Support Information -->
        <div class="support-section">
            <h3 class="support-title">
                <i class="fas fa-headset"></i>
                Need Help? We're Here for You!
            </h3>
            <div class="support-content">
                <div class="support-item">
                    <i class="fas fa-envelope"></i>
                    <span>support@kruathai.com</span>
                </div>
                <div class="support-item">
                    <i class="fas fa-phone"></i>
                    <span>(555) 123-THAI</span>
                </div>
                <div class="support-item">
                    <i class="fas fa-clock"></i>
                    <span>Daily 8 AM - 8 PM</span>
                </div>
                <div class="support-item">
                    <i class="fas fa-comments"></i>
                    <span>Live Chat Available</span>
                </div>
            </div>
            <div style="margin-top: 1rem; font-size: 0.9rem; opacity: 0.9;">
                Questions about your order? Recipe help? We're here to make your Thai cooking experience amazing!
            </div>
        </div>

        <!-- Additional Information -->
        <div style="background: var(--white); border-radius: var(--radius); padding: 2rem; margin-top: 2rem; text-align: center;">
            <h4 style="color: var(--curry); margin-bottom: 1rem; font-size: 1.2rem;">
                <i class="fas fa-gift"></i>
                What's Included in Your Kit
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div style="padding: 1rem; background: var(--cream); border-radius: 8px;">
                    <i class="fas fa-seedling" style="color: var(--sage); font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                    <div style="font-weight: 600; margin-bottom: 0.25rem;">Fresh Ingredients</div>
                    <div style="font-size: 0.9rem; color: var(--text-gray);">Premium vegetables, herbs, and proteins</div>
                </div>
                <div style="padding: 1rem; background: var(--cream); border-radius: 8px;">
                    <i class="fas fa-mortar-pestle" style="color: var(--curry); font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                    <div style="font-weight: 600; margin-bottom: 0.25rem;">Authentic Pastes</div>
                    <div style="font-size: 0.9rem; color: var(--text-gray);">House-made curry pastes & seasonings</div>
                </div>
                <div style="padding: 1rem; background: var(--cream); border-radius: 8px;">
                    <i class="fas fa-book-open" style="color: var(--brown); font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                    <div style="font-weight: 600; margin-bottom: 0.25rem;">Recipe Cards</div>
                    <div style="font-size: 0.9rem; color: var(--text-gray);">Step-by-step cooking instructions</div>
                </div>
                <div style="padding: 1rem; background: var(--cream); border-radius: 8px;">
                    <i class="fas fa-snowflake" style="color: var(--info); font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                    <div style="font-weight: 600; margin-bottom: 0.25rem;">Cold Chain</div>
                    <div style="font-size: 0.9rem; color: var(--text-gray);">Insulated packaging with ice packs</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced order confirmation functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Order confirmation page loaded - Enhanced version');
            
            // Auto-scroll to top smoothly
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Enhanced page protection
            let confirmationViewed = false;
            let pageLoadTime = Date.now();
            
            // Prevent accidental navigation
            function setupNavigationProtection() {
                history.pushState(null, null, location.href);
                
                window.addEventListener('beforeunload', function(e) {
                    if (!confirmationViewed && (Date.now() - pageLoadTime) < 10000) {
                        e.preventDefault();
                        e.returnValue = 'Are you sure you want to leave? Your order details will not be accessible again.';
                        return e.returnValue;
                    }
                });
                
                window.addEventListener('popstate', function() {
                    if (!confirmationViewed) {
                        if (confirm('Are you sure you want to leave this page? You can always check your email for order details.')) {
                            confirmationViewed = true;
                            window.location.href = 'meal-kit.php';
                        } else {
                            history.pushState(null, null, location.href);
                        }
                    } else {
                        history.go(-1);
                    }
                });
            }
            
            setupNavigationProtection();
            
            // Mark as viewed after user interaction or 8 seconds
            setTimeout(() => {
                confirmationViewed = true;
                console.log('✅ Order confirmation marked as viewed');
            }, 8000);
            
            document.addEventListener('click', () => {
                confirmationViewed = true;
            });

            // Enhanced analytics tracking
            if (typeof gtag !== 'undefined') {
                // E-commerce tracking
                gtag('event', 'purchase', {
                    'transaction_id': '<?php echo htmlspecialchars($order_number); ?>',
                    'value': <?php echo $order_total; ?>,
                    'currency': 'USD',
                    'items': <?php echo json_encode(array_map(function($item) {
                        return [
                            'item_id' => $item['id'] ?? 'unknown',
                            'item_name' => $item['name'] ?? 'Thai Meal Kit',
                            'category' => 'meal_kit',
                            'quantity' => $item['quantity'] ?? 1,
                            'price' => $item['base_price'] ?? 0
                        ];
                    }, $order_items)); ?>
                });
                
                // Page view with custom parameters
                gtag('event', 'page_view', {
                    'page_title': 'Order Confirmation',
                    'page_location': window.location.href,
                    'custom_map': {
                        'order_value': <?php echo $order_total; ?>,
                        'order_source': '<?php echo $checkout_source; ?>',
                        'payment_method': '<?php echo $payment_method; ?>'
                    }
                });
                
                // Conversion tracking
                gtag('event', 'conversion', {
                    'send_to': 'conversion_id',
                    'value': <?php echo $order_total; ?>,
                    'currency': 'USD',
                    'order_id': '<?php echo htmlspecialchars($order_number); ?>'
                });
            }

            // Enhanced print functionality
            window.printOrder = function() {
                // Track print action
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'print_receipt', {
                        'order_number': '<?php echo htmlspecialchars($order_number); ?>',
                        'order_value': <?php echo $order_total; ?>
                    });
                }
                
                // Add print timestamp
                const printInfo = document.createElement('div');
                printInfo.innerHTML = `
                    <div style="position: fixed; bottom: 10px; right: 10px; font-size: 10px; color: #666; display: none;" class="print-only">
                        Printed: ${new Date().toLocaleString()}<br>
                        Order: <?php echo htmlspecialchars($order_number); ?>
                    </div>
                `;
                document.body.appendChild(printInfo);
                
                // Show print info only when printing
                const printStyles = document.createElement('style');
                printStyles.innerHTML = `
                    @media print {
                        .print-only { display: block !important; }
                        @page { margin: 0.5in; size: portrait; }
                        body { font-size: 11pt; }
                    }
                `;
                document.head.appendChild(printStyles);
                
                setTimeout(() => {
                    window.print();
                }, 100);
            };

            // Copy order number with enhanced feedback
            window.copyOrderNumber = function() {
                const orderNumber = '<?php echo htmlspecialchars($order_number); ?>';
                
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(orderNumber).then(() => {
                        showNotification('Order number copied to clipboard! 📋', 'success');
                        
                        // Track copy action
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'copy_order_number', {
                                'order_number': orderNumber
                            });
                        }
                    }).catch(err => {
                        console.error('Failed to copy:', err);
                        fallbackCopy(orderNumber);
                    });
                } else {
                    fallbackCopy(orderNumber);
                }
            };
            
            function fallbackCopy(text) {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    showNotification('Order number copied! 📋', 'success');
                } catch (err) {
                    showNotification('Unable to copy. Please manually copy: <?php echo htmlspecialchars($order_number); ?>', 'warning');
                }
                
                document.body.removeChild(textArea);
            }

            // Add click handler to order number for easy copying
            const orderNumberEl = document.querySelector('.order-number');
            if (orderNumberEl) {
                orderNumberEl.style.cursor = 'pointer';
                orderNumberEl.title = 'Click to copy order number';
                orderNumberEl.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.copyOrderNumber();
                });
                
                // Add visual hover effect
                orderNumberEl.addEventListener('mouseenter', function() {
                    this.style.background = 'rgba(207, 114, 58, 0.1)';
                    this.style.padding = '0.25rem 0.5rem';
                    this.style.borderRadius = '4px';
                    this.style.transition = 'all 0.2s ease';
                });
                
                orderNumberEl.addEventListener('mouseleave', function() {
                    this.style.background = 'transparent';
                    this.style.padding = '0';
                });
            }

            // Enhanced notification system
            window.showNotification = function(message, type = 'info', duration = 4000) {
                const colors = {
                    'success': '#27ae60',
                    'error': '#e74c3c',
                    'warning': '#f39c12',
                    'info': '#3498db'
                };
                
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.innerHTML = `
                    <div style="
                        position: fixed; 
                        top: 20px; 
                        right: 20px; 
                        background: ${colors[type]}; 
                        color: white; 
                        padding: 1rem 1.5rem; 
                        border-radius: 8px; 
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
                        z-index: 10000;
                        font-weight: 600;
                        max-width: 300px;
                        animation: slideInRight 0.3s ease-out;
                        cursor: pointer;
                    " onclick="this.parentElement.remove()">
                        ${message}
                        <div style="font-size: 0.8rem; opacity: 0.8; margin-top: 0.25rem;">Click to dismiss</div>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.style.animation = 'slideOutRight 0.3s ease-in';
                        setTimeout(() => {
                            if (notification.parentElement) {
                                notification.remove();
                            }
                        }, 300);
                    }
                }, duration);
                
                // Auto-remove on click
                notification.addEventListener('click', () => {
                    notification.remove();
                });
            };

            // Add CSS animations for notifications
            if (!document.getElementById('notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOutRight {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }

            // Social sharing functionality
            window.shareOrder = function() {
                const shareData = {
                    title: 'I just ordered from Krua Thai! 🍛',
                    text: 'Delicious authentic Thai meal kits delivered fresh to my door.',
                    url: 'https://kruathai.com'
                };
                
                if (navigator.share && navigator.canShare && navigator.canShare(shareData)) {
                    navigator.share(shareData).then(() => {
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'share', {
                                'method': 'web_share_api',
                                'content_type': 'order_confirmation'
                            });
                        }
                        showNotification('Thanks for sharing! 🎉', 'success');
                    }).catch(err => {
                        console.log('Share cancelled or failed:', err);
                    });
                } else {
                    // Fallback - copy link to clipboard
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText('https://kruathai.com').then(() => {
                            showNotification('Link copied to clipboard! Share with friends 🔗', 'info');
                        });
                    } else {
                        showNotification('Visit kruathai.com to order your own Thai meal kits! 🍛', 'info');
                    }
                }
            };

            // Auto-suggest account creation for guests (with improved timing)
            <?php if (!isset($_SESSION['user_id'])): ?>
            let accountPromptShown = false;
            
            // Show account creation prompt after user engagement
            function maybeShowAccountPrompt() {
                if (!accountPromptShown && confirmationViewed) {
                    accountPromptShown = true;
                    
                    setTimeout(() => {
                        if (confirm('🎉 Create an account to easily track orders and reorder your favorites?\n\nBenefits:\n• Order history & tracking\n• Faster checkout\n• Exclusive member offers\n• Recipe collection')) {
                            window.location.href = 'register.php?email=<?php echo urlencode($customer_email); ?>&ref=order_confirmation&welcome=1';
                        }
                    }, 500);
                }
            }
            
            // Trigger account prompt after 12 seconds or on scroll
            setTimeout(maybeShowAccountPrompt, 12000);
            
            let scrolled = false;
            window.addEventListener('scroll', () => {
                if (!scrolled && window.scrollY > 200) {
                    scrolled = true;
                    setTimeout(maybeShowAccountPrompt, 2000);
                }
            });
            <?php endif; ?>

            // Performance and error tracking
            if (typeof gtag !== 'undefined') {
                // Page load performance
                const loadTime = performance.now();
                gtag('event', 'timing_complete', {
                    'name': 'order_confirmation_load',
                    'value': Math.round(loadTime)
                });
                
                // Track user engagement
                let engagementTimer = Date.now();
                window.addEventListener('beforeunload', () => {
                    const timeOnPage = Date.now() - engagementTimer;
                    gtag('event', 'user_engagement', {
                        'engagement_time_msec': timeOnPage
                    });
                });
            }

            // Enhanced error handling
            window.addEventListener('error', function(e) {
                console.error('Order confirmation error:', e.error);
                
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'exception', {
                        'description': e.error ? e.error.toString() : 'Unknown error',
                        'fatal': false,
                        'page': 'order_confirmation'
                    });
                }
                
                // Show user-friendly error message
                showNotification('Something went wrong, but your order is safe! Check your email for details.', 'warning', 6000);
            });

            // Initialize page interactions
            function initializeInteractions() {
                // Add smooth scrolling to action buttons
                document.querySelectorAll('.btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        // Track button clicks
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'click', {
                                'element_type': 'button',
                                'element_text': this.textContent.trim(),
                                'page': 'order_confirmation'
                            });
                        }
                    });
                });
                
                // Add hover effects to cards
                document.querySelectorAll('.step-card').forEach(card => {
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-4px)';
                        this.style.boxShadow = '0 12px 24px rgba(0,0,0,0.15)';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 8px 20px rgba(0,0,0,0.12)';
                    });
                });
            }
            
            initializeInteractions();
            
            // Show welcome message
            setTimeout(() => {
                showNotification('Order confirmed! 🎉 Check your email for details.', 'success', 5000);
            }, 1000);

            console.log('✅ Enhanced order confirmation features loaded successfully');
        });

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            // Allow escape key to dismiss notifications
            if (e.key === 'Escape') {
                document.querySelectorAll('.notification').forEach(notification => {
                    notification.remove();
                });
            }
            
            // Copy order number with Ctrl+C when focused
            if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                const selection = window.getSelection().toString();
                if (!selection && document.activeElement.classList.contains('order-number')) {
                    e.preventDefault();
                    window.copyOrderNumber();
                }
            }
        });
    </script>

    <!-- Enhanced Structured Data for SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Order",
        "orderNumber": "<?php echo htmlspecialchars($order_number); ?>",
        "orderStatus": "https://schema.org/OrderProcessing",
        "orderDate": "<?php echo date('c'); ?>",
        "customer": {
            "@type": "Person",
            "email": "<?php echo htmlspecialchars($customer_email); ?>"
            <?php if ($customer_name !== 'Valued Customer'): ?>
            ,"name": "<?php echo htmlspecialchars($customer_name); ?>"
            <?php endif; ?>
        },
        "seller": {
            "@type": "Organization",
            "name": "Krua Thai",
            "url": "https://kruathai.com",
            "telephone": "(555) 123-THAI",
            "email": "support@kruathai.com"
        },
        "orderedItem": [
            <?php foreach ($order_items as $index => $item): ?>
            {
                "@type": "OrderItem",
                "orderQuantity": <?php echo $item['quantity']; ?>,
                "orderedItem": {
                    "@type": "Product",
                    "name": "<?php echo htmlspecialchars($item['name']); ?>",
                    "category": "Food/Meal Kit",
                    "offers": {
                        "@type": "Offer",
                        "price": "<?php echo $item['base_price']; ?>",
                        "priceCurrency": "USD",
                        "availability": "https://schema.org/InStock"
                    }
                }
            }<?php echo $index < count($order_items) - 1 ? ',' : ''; ?>
            <?php endforeach; ?>
        ],
        "totalPaymentDue": {
            "@type": "PriceSpecification",
            "price": "<?php echo $order_total; ?>",
            "priceCurrency": "USD"
        },
        "paymentMethod": "<?php echo htmlspecialchars($payment_method); ?>",
        "orderDelivery": {
            "@type": "ParcelDelivery",
            "expectedArrivalFrom": "<?php echo date('c', strtotime($delivery_date)); ?>",
            "deliveryAddress": {
                "@type": "PostalAddress",
                "addressLocality": "<?php echo htmlspecialchars($delivery_address); ?>"
            }
        }
    }
    </script>

    <!-- Preload critical resources for better performance -->
    <link rel="preload" href="https://ydpschool.com/fonts/BaticaSans-Bold.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="./assets/image/LOGO_BG.png" as="image">
</body>
</html>