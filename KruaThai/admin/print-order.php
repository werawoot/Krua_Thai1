<?php
/**
 * Krua Thai - Print Order
 * File: admin/print-order.php
 * Description: Dedicated print page for orders with clean layout
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
    
    // Calculate totals
    $total_amount = 0;
    $total_quantity = 0;
    foreach ($order_items as $item) {
        $total_amount += ($item['menu_price'] * $item['quantity']);
        $total_quantity += $item['quantity'];
    }
    
} catch (Exception $e) {
    die("Error loading order: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order <?= htmlspecialchars($order['order_number']) ?> - Krua Thai</title>
    <style>
        /* Print-optimized styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
            background: white;
            margin: 0;
            padding: 20px;
        }

        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }

        /* Header */
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #cf723a;
            margin-bottom: 5px;
        }

        .tagline {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .order-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .order-number {
            font-size: 18px;
            color: #cf723a;
            font-weight: bold;
        }

        /* Order Info Section */
        .order-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .info-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .info-item {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }

        .info-label {
            font-weight: bold;
            color: #555;
            min-width: 100px;
        }

        .info-value {
            color: #333;
            flex: 1;
            text-align: right;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-preparing { background: #fff3cd; color: #856404; }
        .status-ready { background: #e2e3f1; color: #383d41; }
        .status-out_for_delivery { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        /* Items Table */
        .items-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .items-table th {
            background: #f8f9fa;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            color: #555;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .menu-name {
            font-weight: bold;
            color: #333;
        }

        .menu-thai {
            font-size: 12px;
            color: #666;
            font-style: italic;
        }

        .price {
            font-weight: bold;
            color: #cf723a;
        }

        .quantity {
            text-align: center;
            font-weight: bold;
        }

        .total-row {
            background: #f1f3f4;
            font-weight: bold;
        }

        .total-row td {
            border-top: 2px solid #333;
            padding: 15px 8px;
        }

        /* Special Instructions */
        .special-section {
            background: #fff9c4;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #cf723a;
        }

        .special-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        /* Footer */
        .print-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }

        .qr-section {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        /* Print styles */
        @media print {
            body {
                margin: 0;
                padding: 15px;
                font-size: 12px;
            }
            
            .print-container {
                max-width: none;
                margin: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            .items-table th,
            .items-table td {
                padding: 8px 6px;
            }
            
            .order-info {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
        }

        @media screen {
            .print-actions {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
            }
            
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 10px 20px;
                margin: 0 5px;
                border: none;
                border-radius: 5px;
                font-size: 14px;
                font-weight: bold;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            
            .btn-print {
                background: #cf723a;
                color: white;
            }
            
            .btn-print:hover {
                background: #b8632f;
                transform: translateY(-2px);
            }
            
            .btn-back {
                background: #6c757d;
                color: white;
            }
            
            .btn-back:hover {
                background: #5a6268;
                transform: translateY(-2px);
            }
        }
    </style>
</head>
<body>
    <!-- Print Actions (Screen Only) -->
    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i>
            Print Order
        </button>
        <a href="orders.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i>
            Back to Orders
        </a>
    </div>

    <div class="print-container">
        <!-- Header -->
        <div class="print-header">
            <div class="logo">Krua Thai</div>
            <div class="tagline">Authentic Thai Meals, Made Healthy</div>
            <div class="order-title">Order Details</div>
            <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
        </div>

        <!-- Order Information -->
        <div class="order-info">
            <!-- Customer Information -->
            <div class="info-section">
                <div class="info-title">
                    <i class="fas fa-user"></i>
                    Customer Information
                </div>
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
            </div>

            <!-- Order Details -->
            <div class="info-section">
                <div class="info-title">
                    <i class="fas fa-clipboard-list"></i>
                    Order Details
                </div>
                <div class="info-item">
                    <span class="info-label">Order #:</span>
                    <span class="info-value"><?= htmlspecialchars($order['order_number']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?= $order['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Kitchen:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?= $order['kitchen_status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $order['kitchen_status'])) ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Created:</span>
                    <span class="info-value"><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Updated:</span>
                    <span class="info-value"><?= date('M d, Y H:i', strtotime($order['updated_at'])) ?></span>
                </div>
                <?php if ($order['rider_name']): ?>
                <div class="info-item">
                    <span class="info-label">Rider:</span>
                    <span class="info-value"><?= htmlspecialchars($order['rider_name']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delivery Information -->
        <div class="info-section" style="margin-bottom: 30px;">
            <div class="info-title">
                <i class="fas fa-truck"></i>
                Delivery Information
            </div>
            <div class="info-item">
                <span class="info-label">Date:</span>
                <span class="info-value"><?= date('M d, Y', strtotime($order['delivery_date'])) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Time Slot:</span>
                <span class="info-value"><?= htmlspecialchars($order['delivery_time_slot']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Address:</span>
                <span class="info-value"><?= htmlspecialchars($order['delivery_address']) ?></span>
            </div>
            <?php if ($order['delivery_instructions']): ?>
            <div class="info-item">
                <span class="info-label">Instructions:</span>
                <span class="info-value"><?= htmlspecialchars($order['delivery_instructions']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Order Items -->
        <div class="items-section">
            <div class="section-title">
                <i class="fas fa-utensils"></i>
                Order Items (<?= count($order_items) ?> items)
            </div>
            
            <?php if (!empty($order_items)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Menu Item</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Special Requests</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td>
                            <div class="menu-name"><?= htmlspecialchars($item['menu_name']) ?></div>
                            <?php if ($item['menu_name_current'] && $item['menu_name_current'] !== $item['menu_name']): ?>
                                <div class="menu-thai">Current: <?= htmlspecialchars($item['menu_name_current']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="price">฿<?= number_format($item['menu_price'], 2) ?></td>
                        <td class="quantity"><?= $item['quantity'] ?></td>
                        <td>
                            <span class="status-badge status-<?= $item['item_status'] ?>">
                                <?= ucfirst($item['item_status']) ?>
                            </span>
                        </td>
                        <td class="price">฿<?= number_format($item['menu_price'] * $item['quantity'], 2) ?></td>
                        <td><?= htmlspecialchars($item['special_requests'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Total Row -->
                    <tr class="total-row">
                        <td><strong>TOTAL</strong></td>
                        <td></td>
                        <td class="quantity"><strong><?= $total_quantity ?></strong></td>
                        <td></td>
                        <td class="price"><strong>฿<?= number_format($total_amount, 2) ?></strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-utensils" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                <h3>No items in this order</h3>
            </div>
            <?php endif; ?>
        </div>

        <!-- Special Instructions -->
        <?php if ($order['special_notes'] || $order['delivery_instructions']): ?>
        <div class="special-section">
            <div class="special-title">
                <i class="fas fa-sticky-note"></i>
                Special Instructions
            </div>
            <?php if ($order['special_notes']): ?>
            <div><strong>Order Notes:</strong> <?= htmlspecialchars($order['special_notes']) ?></div>
            <?php endif; ?>
            <?php if ($order['delivery_instructions']): ?>
            <div><strong>Delivery Instructions:</strong> <?= htmlspecialchars($order['delivery_instructions']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- QR Code Section (for delivery confirmation) -->
        <?php if ($order['qr_code'] ?? false): ?>
        <div class="qr-section">
            <div class="special-title">Delivery Confirmation</div>
            <p>Scan QR code to confirm delivery</p>
            <div style="margin: 10px 0;">
                <!-- QR Code would be generated here -->
                <div style="width: 100px; height: 100px; border: 2px dashed #ccc; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                    QR CODE
                </div>
            </div>
            <p style="font-size: 12px; color: #666;">Order ID: <?= htmlspecialchars($order['id']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="print-footer">
            <p><strong>Krua Thai</strong> - Authentic Thai Meals, Made Healthy</p>
            <p>📞 02-123-4567 | 📧 hello@kruathai.com | 🌐 www.kruathai.com</p>
            <p>Printed on: <?= date('M d, Y H:i:s') ?></p>
        </div>
    </div>

    <script>
        // Auto-print when loaded with print parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('auto_print') === '1') {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        });

        // Print function
        function printOrder() {
            window.print();
        }

        // Handle print events
        window.addEventListener('beforeprint', function() {
            document.title = 'Order <?= htmlspecialchars($order['order_number']) ?> - Krua Thai';
        });

        window.addEventListener('afterprint', function() {
            // Close window if opened as popup
            if (window.opener && !window.opener.closed) {
                window.close();
            }
        });

        console.log('Print Order page loaded for order: <?= htmlspecialchars($order['order_number']) ?>');
    </script>
</body>
</html>