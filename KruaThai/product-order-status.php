<?php
/**
 * Somdul Table - Product Order Status Page
 * File: product-order-status.php
 * Description: Order confirmation and tracking for product purchases
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order = null;
$order_items = [];
$error_message = "";

// Get order ID from URL
$order_id = $_GET['order'] ?? '';

if (empty($order_id)) {
    header('Location: products.php');
    exit;
}

try {
    // Database connection with fallback
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}

try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT po.*, u.email as user_email, u.first_name, u.last_name 
        FROM product_orders po 
        LEFT JOIN users u ON po.user_id = u.id 
        WHERE po.id = ? AND po.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $error_message = "Order not found or you don't have permission to view this order.";
    } else {
        // Fetch order items
        $stmt = $pdo->prepare("
            SELECT poi.*, p.image_url, p.category 
            FROM product_order_items poi
            LEFT JOIN products p ON poi.product_id = p.id
            WHERE poi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Error loading order details: " . $e->getMessage();
}

// Helper functions
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('F j, Y \a\t g:i A', strtotime($datetime));
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'status-pending';
        case 'paid': return 'status-paid';
        case 'processing': return 'status-processing';
        case 'shipped': return 'status-shipped';
        case 'delivered': return 'status-delivered';
        case 'cancelled': return 'status-cancelled';
        default: return 'status-pending';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'pending': return 'Payment Pending';
        case 'paid': return 'Payment Confirmed';
        case 'processing': return 'Processing Order';
        case 'shipped': return 'Shipped';
        case 'delivered': return 'Delivered';
        case 'cancelled': return 'Cancelled';
        default: return ucfirst($status);
    }
}

function getEstimatedDelivery($order) {
    if (!empty($order['estimated_delivery'])) {
        return formatDate($order['estimated_delivery']);
    }
    
    // Calculate estimated delivery (5-7 business days from order date)
    $order_date = new DateTime($order['created_at']);
    $estimated = clone $order_date;
    $estimated->add(new DateInterval('P7D')); // Add 7 days
    
    return formatDate($estimated->format('Y-m-d'));
}

// Include the header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status - <?= $order ? '#' . htmlspecialchars($order['order_number']) : 'Not Found' ?> | Somdul Table</title>
    <meta name="description" content="Track your Somdul Table product order and delivery status">
    
    <style>
        body.has-header {
            margin-top: 110px;
        }
        
        .order-status-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .order-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .order-header h1 {
            color: var(--brown);
            margin-bottom: 0.5rem;
        }
        
        .order-number {
            font-size: 1.1rem;
            color: var(--text-gray);
            margin-bottom: 1rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff7e6;
            color: #d97706;
            border: 1px solid #fbbf24;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #34d399;
        }
        
        .status-processing {
            background: #dbeafe;
            color: #2563eb;
            border: 1px solid #60a5fa;
        }
        
        .status-shipped {
            background: #e0e7ff;
            color: #7c3aed;
            border: 1px solid #a78bfa;
        }
        
        .status-delivered {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #34d399;
        }
        
        .status-cancelled {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #f87171;
        }
        
        .order-progress {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 2rem 0;
        }
        
        .progress-line {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            height: 2px;
            background: var(--cream);
            z-index: 1;
        }
        
        .progress-line-filled {
            height: 100%;
            background: var(--brown);
            transition: width 0.5s ease;
        }
        
        .progress-step {
            background: var(--white);
            border: 3px solid var(--cream);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 2;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .progress-step.completed {
            background: var(--brown);
            border-color: var(--brown);
            color: var(--white);
        }
        
        .progress-step.current {
            background: var(--curry);
            border-color: var(--curry);
            color: var(--white);
            animation: pulse 2s infinite;
        }
        
        .progress-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
        }
        
        .progress-label {
            flex: 1;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-gray);
            font-weight: 500;
        }
        
        .progress-label.completed {
            color: var(--brown);
            font-weight: 600;
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .detail-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }
        
        .detail-card h3 {
            color: var(--brown);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .detail-label {
            color: var(--text-gray);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--text-dark);
            font-weight: 600;
        }
        
        .order-items {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
        }
        
        .item-card {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--cream);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            background: var(--cream);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brown);
            font-size: 2rem;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--brown);
            margin-bottom: 0.25rem;
        }
        
        .item-meta {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .item-pricing {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tracking-info {
            background: var(--sage);
            color: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .tracking-number {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--brown);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: #a8855f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--brown);
            border: 2px solid var(--brown);
        }
        
        .btn-secondary:hover {
            background: var(--brown);
            color: var(--white);
        }
        
        .error-state {
            background: var(--white);
            padding: 3rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            text-align: center;
            color: var(--text-gray);
        }
        
        .error-state h2 {
            color: var(--brown);
            margin-bottom: 1rem;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .progress-steps {
                margin: 1rem 0;
            }
            
            .progress-step {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            
            .progress-labels {
                font-size: 0.75rem;
            }
            
            .item-card {
                flex-direction: column;
            }
            
            .item-image {
                width: 60px;
                height: 60px;
                align-self: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body class="has-header">
    <div class="order-status-container">
        <?php if ($error_message): ?>
            <!-- Error State -->
            <div class="error-state">
                <h2>Order Not Found</h2>
                <p><?= htmlspecialchars($error_message) ?></p>
                <div style="margin-top: 2rem;">
                    <a href="product.php" class="btn btn-primary">Browse Products</a>
                    <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Order Header -->
            <div class="order-header">
                <h1>Order Confirmation</h1>
                <div class="order-number">Order #<?= htmlspecialchars($order['order_number']) ?></div>
                <div class="status-badge <?= getStatusBadgeClass($order['status']) ?>">
                    <?= getStatusText($order['status']) ?>
                </div>
                <p style="margin-top: 1rem; color: var(--text-gray);">
                    Placed on <?= formatDateTime($order['created_at']) ?>
                </p>
            </div>

            <!-- Order Progress -->
            <div class="order-progress">
                <h3 style="color: var(--brown); margin-bottom: 1rem; text-align: center;">📦 Order Progress</h3>
                
                <div class="progress-steps">
                    <div class="progress-line">
                        <div class="progress-line-filled" style="width: <?= 
                            $order['status'] === 'pending' ? '0%' :
                            ($order['status'] === 'paid' ? '25%' :
                            ($order['status'] === 'processing' ? '50%' :
                            ($order['status'] === 'shipped' ? '75%' : '100%')))
                        ?>"></div>
                    </div>
                    
                    <div class="progress-step <?= in_array($order['status'], ['paid', 'processing', 'shipped', 'delivered']) ? 'completed' : 'current' ?>">1</div>
                    <div class="progress-step <?= in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : ($order['status'] === 'paid' ? 'current' : '') ?>">2</div>
                    <div class="progress-step <?= in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : ($order['status'] === 'processing' ? 'current' : '') ?>">3</div>
                    <div class="progress-step <?= $order['status'] === 'delivered' ? 'completed' : ($order['status'] === 'shipped' ? 'current' : '') ?>">4</div>
                </div>
                
                <div class="progress-labels">
                    <div class="progress-label <?= in_array($order['status'], ['paid', 'processing', 'shipped', 'delivered']) ? 'completed' : '' ?>">Order Confirmed</div>
                    <div class="progress-label <?= in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : '' ?>">Processing</div>
                    <div class="progress-label <?= in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : '' ?>">Shipped</div>
                    <div class="progress-label <?= $order['status'] === 'delivered' ? 'completed' : '' ?>">Delivered</div>
                </div>
                
                <?php if (!empty($order['estimated_delivery'])): ?>
                    <div style="text-align: center; margin-top: 1rem; color: var(--text-gray);">
                        <strong>Estimated Delivery:</strong> <?= getEstimatedDelivery($order) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tracking Information -->
            <?php if ($order['status'] === 'shipped' && !empty($order['tracking_number'])): ?>
                <div class="tracking-info">
                    <h3>📦 Package Shipped!</h3>
                    <div class="tracking-number">Tracking: <?= htmlspecialchars($order['tracking_number']) ?></div>
                    <p>Carrier: <?= htmlspecialchars($order['shipping_carrier'] ?? 'USPS') ?></p>
                    <?php if (!empty($order['tracking_number'])): ?>
                        <a href="https://tools.usps.com/go/TrackConfirmAction?tLabels=<?= urlencode($order['tracking_number']) ?>" 
                           target="_blank" class="btn btn-primary" style="margin-top: 1rem;">
                            Track Package
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Order Details Grid -->
            <div class="order-details-grid">
                <!-- Shipping Details -->
                <div class="detail-card">
                    <h3>🚚 Shipping Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Address:</span>
                        <span class="detail-value"><?= htmlspecialchars($order['shipping_address_line1']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">City:</span>
                        <span class="detail-value"><?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ZIP Code:</span>
                        <span class="detail-value"><?= htmlspecialchars($order['shipping_zip']) ?></span>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="detail-card">
                    <h3>💳 Payment Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value"><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Status:</span>
                        <span class="detail-value"><?= ucfirst($order['payment_status']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Order Total:</span>
                        <span class="detail-value" style="color: var(--brown); font-size: 1.1rem;"><?= formatPrice($order['total_amount']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="order-items">
                <h3 style="color: var(--brown); margin-bottom: 1rem;">📋 Order Items</h3>
                
                <?php foreach ($order_items as $item): ?>
                    <div class="item-card">
                        <div class="item-image">
                            <?php if (!empty($item['image_url']) && file_exists($item['image_url'])): ?>
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="Product" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-sm);">
                            <?php else: ?>
                                🍜
                            <?php endif; ?>
                        </div>
                        
                        <div class="item-details">
                            <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            <div class="item-meta">Quantity: <?= $item['quantity'] ?> • <?= formatPrice($item['unit_price']) ?> each</div>
                            
                            <div class="item-pricing">
                                <span style="color: var(--text-gray);">Item Total:</span>
                                <span style="color: var(--brown); font-weight: 600;"><?= formatPrice($item['total_price']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Order Summary -->
                <div style="border-top: 2px solid var(--cream); padding-top: 1rem; margin-top: 1rem;">
                    <div class="detail-row">
                        <span class="detail-label">Subtotal:</span>
                        <span class="detail-value"><?= formatPrice($order['subtotal']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Shipping:</span>
                        <span class="detail-value"><?= formatPrice($order['shipping_cost']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tax:</span>
                        <span class="detail-value"><?= formatPrice($order['tax_amount']) ?></span>
                    </div>
                    <div class="detail-row" style="border-top: 1px solid var(--cream); padding-top: 0.5rem; margin-top: 0.5rem;">
                        <span class="detail-label" style="font-size: 1.1rem; font-weight: 700; color: var(--brown);">Total:</span>
                        <span class="detail-value" style="font-size: 1.2rem; font-weight: 700; color: var(--brown);"><?= formatPrice($order['total_amount']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="products.php" class="btn btn-secondary">Continue Shopping</a>
                <a href="dashboard.php" class="btn btn-primary">View All Orders</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh page if order is being processed
        document.addEventListener('DOMContentLoaded', function() {
            const orderStatus = '<?= $order['status'] ?? '' ?>';
            
            if (orderStatus === 'processing') {
                // Refresh every 30 seconds for processing orders
                setTimeout(function() {
                    location.reload();
                }, 30000);
            }
            
            // Smooth animations
            const progressSteps = document.querySelectorAll('.progress-step');
            progressSteps.forEach((step, index) => {
                setTimeout(() => {
                    step.style.opacity = '1';
                    step.style.transform = 'scale(1)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>