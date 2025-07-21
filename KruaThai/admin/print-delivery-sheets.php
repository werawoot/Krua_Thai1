<?php
/**
 * Krua Thai - Print Delivery Sheets
 * File: admin/print-delivery-sheets.php
 * Description: Professional print layout for delivery sheets grouped by zones and riders
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Get delivery date from query parameter or use today
$deliveryDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$groupBy = isset($_GET['group']) ? $_GET['group'] : 'zone'; // zone, rider, or all

try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Zone configuration
$zoneConfigs = [
    'A' => ['name' => 'Zone A (0-8 miles)', 'color' => '#27ae60', 'description' => 'Fullerton, Brea area'],
    'B' => ['name' => 'Zone B (8-15 miles)', 'color' => '#f39c12', 'description' => 'Buena Park, Anaheim area'],
    'C' => ['name' => 'Zone C (15-25 miles)', 'color' => '#e67e22', 'description' => 'Garden Grove, Westminster area'],
    'D' => ['name' => 'Zone D (25+ miles)', 'color' => '#e74c3c', 'description' => 'Santa Ana, Huntington Beach area']
];

// ZIP code to zone mapping
$zipToZone = [
    // Zone A: 0-8 miles
    '92831' => 'A', '92832' => 'A', '92833' => 'A', '92834' => 'A', '92835' => 'A',
    '92821' => 'A', '92823' => 'A',
    // Zone B: 8-15 miles
    '90620' => 'B', '90621' => 'B', '92801' => 'B', '92802' => 'B', 
    '92804' => 'B', '92805' => 'B',
    // Zone C: 15-25 miles
    '92840' => 'C', '92841' => 'C', '92843' => 'C', '92683' => 'C',
    // Zone D: 25+ miles
    '92703' => 'D', '92648' => 'D', '92647' => 'D'
];

// Function to determine zone from zip code
function getZoneFromZip($zipCode, $zipToZone) {
    $zip5 = substr($zipCode, 0, 5);
    return isset($zipToZone[$zip5]) ? $zipToZone[$zip5] : 'Unknown';
}

// Fetch delivery data
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.total_items, o.status, o.assigned_rider_id,
               o.delivery_date, o.delivery_time_slot, o.delivery_instructions,
               o.created_at, o.subscription_id,
               u.id as user_id, u.first_name, u.last_name, u.phone, u.zip_code, 
               u.delivery_address, u.city, u.state, u.delivery_instructions as user_instructions,
               r.first_name as rider_first_name, r.last_name as rider_last_name,
               GROUP_CONCAT(
                   CONCAT(oi.menu_name, ' (', oi.quantity, ')') 
                   ORDER BY oi.menu_name SEPARATOR ', '
               ) as menu_items,
               SUM(oi.quantity) as total_quantity,
               SUM(oi.menu_price * oi.quantity) as total_amount
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN users r ON o.assigned_rider_id = r.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE DATE(o.delivery_date) = ? AND o.status != 'cancelled'
        GROUP BY o.id
        ORDER BY u.zip_code, u.last_name, u.first_name
    ");
    $stmt->execute([$deliveryDate]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process orders and group them
    $deliveryData = [];
    $zoneSummary = [];
    $riderSummary = [];
    $totalStats = [
        'orders' => 0,
        'boxes' => 0,
        'amount' => 0,
        'assigned' => 0,
        'unassigned' => 0
    ];

    foreach ($orders as $order) {
        $zone = getZoneFromZip($order['zip_code'], $zipToZone);
        $riderId = $order['assigned_rider_id'] ?: 'unassigned';
        $riderName = $order['assigned_rider_id'] ? 
                    ($order['rider_first_name'] . ' ' . $order['rider_last_name']) : 'Unassigned';

        // Add zone info to order
        $order['zone'] = $zone;
        $order['zone_config'] = $zoneConfigs[$zone] ?? ['name' => 'Unknown Zone', 'color' => '#95a5a6'];
        $order['rider_name'] = $riderName;

        // Group by selected criteria
        if ($groupBy === 'zone') {
            $deliveryData[$zone][] = $order;
        } elseif ($groupBy === 'rider') {
            $deliveryData[$riderName][] = $order;
        } else {
            $deliveryData['all'][] = $order;
        }

        // Update summaries
        if (!isset($zoneSummary[$zone])) {
            $zoneSummary[$zone] = ['orders' => 0, 'boxes' => 0, 'amount' => 0];
        }
        $zoneSummary[$zone]['orders']++;
        $zoneSummary[$zone]['boxes'] += $order['total_items'];
        $zoneSummary[$zone]['amount'] += $order['total_amount'];

        if (!isset($riderSummary[$riderName])) {
            $riderSummary[$riderName] = ['orders' => 0, 'boxes' => 0, 'amount' => 0];
        }
        $riderSummary[$riderName]['orders']++;
        $riderSummary[$riderName]['boxes'] += $order['total_items'];
        $riderSummary[$riderName]['amount'] += $order['total_amount'];

        // Update total stats
        $totalStats['orders']++;
        $totalStats['boxes'] += $order['total_items'];
        $totalStats['amount'] += $order['total_amount'];
        if ($order['assigned_rider_id']) {
            $totalStats['assigned']++;
        } else {
            $totalStats['unassigned']++;
        }
    }

} catch (Exception $e) {
    die("Error loading delivery data: " . $e->getMessage());
}

// Determine page title based on grouping
$pageTitle = '';
switch ($groupBy) {
    case 'zone':
        $pageTitle = 'Delivery Sheets by Zone';
        break;
    case 'rider':
        $pageTitle = 'Delivery Sheets by Rider';
        break;
    default:
        $pageTitle = 'Complete Delivery Sheet';
}

$formattedDate = date('l, F j, Y', strtotime($deliveryDate));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo $formattedDate; ?></title>
    <style>
        /* Print-optimized styles */
      @media print {
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;  /* ใช้ print-color-adjust แทน color-adjust */
    }
            body {
                margin: 0;
                padding: 10mm;
                font-size: 12px;
                line-height: 1.3;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            .page-break-inside {
                page-break-inside: avoid;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            th, td {
                border: 1px solid #000;
                padding: 4px 6px;
                text-align: left;
                vertical-align: top;
            }
            
            th {
                background-color: #f0f0f0 !important;
                font-weight: bold;
            }
        }

        /* Screen styles */
        @media screen {
            body {
                margin: 0;
                padding: 20px;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f5f5f5;
            }
            
            .print-container {
                max-width: 210mm;
                margin: 0 auto;
                background: white;
                padding: 20mm;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
        }

        /* Common styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #cf723a;
            padding-bottom: 20px;
        }

        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #cf723a;
            margin: 10px 0 5px 0;
        }

        .subtitle {
            font-size: 16px;
            color: #666;
            font-style: italic;
        }

        .delivery-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #cf723a;
        }

        .delivery-info h2 {
            margin: 0 0 10px 0;
            color: #cf723a;
            font-size: 18px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
        }

        .info-label {
            font-weight: bold;
            color: #555;
        }

        .info-value {
            color: #333;
        }

        .zone-section, .rider-section {
            margin-bottom: 40px;
            break-inside: avoid;
        }

        .zone-header, .rider-header {
            background: linear-gradient(135deg, #cf723a, #bd9379);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .zone-title, .rider-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
        }

        .zone-stats, .rider-stats {
            font-size: 14px;
            opacity: 0.9;
        }

        .delivery-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .delivery-table th {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            color: #555;
        }

        .delivery-table td {
            border: 1px solid #ddd;
            padding: 6px;
            vertical-align: top;
        }

        .delivery-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .delivery-table tbody tr:hover {
            background: #e3f2fd;
        }

        .order-number {
            font-weight: bold;
            color: #cf723a;
        }

        .customer-name {
            font-weight: 600;
            color: #333;
        }

        .menu-items {
            font-size: 10px;
            line-height: 1.2;
            max-width: 200px;
        }

        .status-badge {
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-preparing {
            background: #fff3cd;
            color: #856404;
        }

        .status-ready {
            background: #cce5ff;
            color: #004085;
        }

        .status-out-for-delivery {
            background: #e2d3f4;
            color: #6a1b9a;
        }

        .zone-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .summary-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border: 1px solid #ddd;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }

        .summary-item {
            text-align: center;
        }

        .summary-number {
            font-size: 24px;
            font-weight: bold;
            color: #cf723a;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #cf723a;
            color: white;
        }

        .btn-primary:hover {
            background: #bd9379;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .special-instructions {
            font-style: italic;
            color: #e67e22;
            font-size: 10px;
        }

        .contact-info {
            font-size: 10px;
            color: #666;
        }

        .signature-box {
            border: 1px dashed #ccc;
            height: 40px;
            text-align: center;
            line-height: 40px;
            color: #999;
            font-size: 10px;
            margin-top: 5px;
        }

        .notes-section {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        .notes-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #cf723a;
        }

        .notes-lines {
            border-bottom: 1px solid #ddd;
            height: 20px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- Print Controls (visible only on screen) -->
    <div class="print-controls no-print">
        <button class="btn btn-primary" onclick="window.print()">
            🖨️ Print Sheets
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            ❌ Close
        </button>
        <br><br>
        <select onchange="changeGrouping(this.value)" style="width: 100%; margin-bottom: 10px;">
            <option value="zone" <?php echo $groupBy === 'zone' ? 'selected' : ''; ?>>Group by Zone</option>
            <option value="rider" <?php echo $groupBy === 'rider' ? 'selected' : ''; ?>>Group by Rider</option>
            <option value="all" <?php echo $groupBy === 'all' ? 'selected' : ''; ?>>All Orders</option>
        </select>
    </div>

    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">🍛 Krua Thai</div>
            <div class="subtitle">Authentic Thai Meals, Made Healthy</div>
        </div>

        <!-- Delivery Information -->
        <div class="delivery-info">
            <h2><?php echo $pageTitle; ?></h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Delivery Date:</span>
                    <span class="info-value"><?php echo $formattedDate; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Orders:</span>
                    <span class="info-value"><?php echo $totalStats['orders']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Boxes:</span>
                    <span class="info-value"><?php echo $totalStats['boxes']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Amount:</span>
                    <span class="info-value">$<?php echo number_format($totalStats['amount'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Assigned:</span>
                    <span class="info-value"><?php echo $totalStats['assigned']; ?> orders</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Unassigned:</span>
                    <span class="info-value"><?php echo $totalStats['unassigned']; ?> orders</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Printed:</span>
                    <span class="info-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">By:</span>
                    <span class="info-value"><?php echo $_SESSION['first_name'] ?? 'Admin'; ?></span>
                </div>
            </div>
        </div>

        <!-- Delivery Sheets -->
        <?php if (empty($deliveryData)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <h3>No deliveries scheduled for <?php echo $formattedDate; ?></h3>
                <p>There are no orders to deliver on this date.</p>
            </div>
        <?php else: ?>
            <?php foreach ($deliveryData as $groupName => $groupOrders): ?>
                <?php if (!empty($groupOrders)): ?>
                    <div class="<?php echo $groupBy === 'zone' ? 'zone-section' : ($groupBy === 'rider' ? 'rider-section' : 'all-section'); ?> page-break-inside">
                        
                        <!-- Group Header -->
                        <?php if ($groupBy === 'zone'): ?>
                            <div class="zone-header" style="background: <?php echo $zoneConfigs[$groupName]['color'] ?? '#cf723a'; ?>;">
                                <div>
                                    <h3 class="zone-title">
                                        <span class="zone-indicator" style="background: white;"></span>
                                        <?php echo $zoneConfigs[$groupName]['name'] ?? "Zone $groupName"; ?>
                                    </h3>
                                    <div style="font-size: 12px; opacity: 0.9;">
                                        <?php echo $zoneConfigs[$groupName]['description'] ?? ''; ?>
                                    </div>
                                </div>
                                <div class="zone-stats">
                                    <div><?php echo $zoneSummary[$groupName]['orders']; ?> orders</div>
                                    <div><?php echo $zoneSummary[$groupName]['boxes']; ?> boxes</div>
                                    <div>$<?php echo number_format($zoneSummary[$groupName]['amount'], 0); ?></div>
                                </div>
                            </div>
                        <?php elseif ($groupBy === 'rider'): ?>
                            <div class="rider-header">
                                <div>
                                    <h3 class="rider-title">🚴‍♂️ <?php echo $groupName; ?></h3>
                                </div>
                                <div class="rider-stats">
                                    <div><?php echo $riderSummary[$groupName]['orders']; ?> orders</div>
                                    <div><?php echo $riderSummary[$groupName]['boxes']; ?> boxes</div>
                                    <div>$<?php echo number_format($riderSummary[$groupName]['amount'], 0); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Orders Table -->
                        <table class="delivery-table">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 12%;">Order #</th>
                                    <th style="width: 18%;">Customer</th>
                                    <th style="width: 12%;">Contact</th>
                                    <th style="width: 20%;">Address</th>
                                    <th style="width: 8%;">Time</th>
                                    <th style="width: 20%;">Items</th>
                                    <th style="width: 5%;">✓</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupOrders as $index => $order): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: bold;">
                                            <?php echo $index + 1; ?>
                                        </td>
                                        <td>
                                            <span class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></span>
                                            <?php if ($groupBy !== 'zone'): ?>
                                                <br><small style="color: <?php echo $order['zone_config']['color']; ?>;">
                                                    Zone <?php echo $order['zone']; ?>
                                                </small>
                                            <?php endif; ?>
                                            <br><span class="status-badge status-<?php echo str_replace(['_', ' '], '-', strtolower($order['status'])); ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="customer-name">
                                                <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                            </span>
                                            <?php if ($groupBy !== 'rider' && $order['rider_name'] !== 'Unassigned'): ?>
                                                <br><small style="color: #666;">🚴‍♂️ <?php echo htmlspecialchars($order['rider_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="contact-info">
                                            <?php if ($order['phone']): ?>
                                                📞 <?php echo htmlspecialchars($order['phone']); ?><br>
                                            <?php endif; ?>
                                            <small><?php echo htmlspecialchars($order['zip_code']); ?></small>
                                        </td>
                                        <td style="font-size: 10px;">
                                            <?php echo htmlspecialchars($order['delivery_address']); ?>
                                            <?php if ($order['city']): ?>
                                                <br><?php echo htmlspecialchars($order['city']); ?>
                                            <?php endif; ?>
                                            <?php if ($order['delivery_instructions'] || $order['user_instructions']): ?>
                                                <br><span class="special-instructions">
                                                    ℹ️ <?php echo htmlspecialchars($order['delivery_instructions'] ?: $order['user_instructions']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php echo $order['delivery_time_slot'] ?: '12:00-15:00'; ?>
                                        </td>
                                        <td class="menu-items">
                                            <?php echo htmlspecialchars($order['menu_items']); ?>
                                            <br><strong><?php echo $order['total_items']; ?> boxes</strong>
                                            <br><small>$<?php echo number_format($order['total_amount'], 2); ?></small>
                                        </td>
                                        <td>
                                            <div class="signature-box">
                                                SIGN
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Group Summary -->
                        <?php if ($groupBy !== 'all'): ?>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid <?php echo $groupBy === 'zone' ? ($zoneConfigs[$groupName]['color'] ?? '#cf723a') : '#cf723a'; ?>;">
                                <strong><?php echo $groupName; ?> Summary:</strong>
                                <?php $summary = $groupBy === 'zone' ? $zoneSummary[$groupName] : $riderSummary[$groupName]; ?>
                                <?php echo $summary['orders']; ?> orders, 
                                <?php echo $summary['boxes']; ?> boxes, 
                                $<?php echo number_format($summary['amount'], 2); ?> total
                            </div>
                        <?php endif; ?>

                        <?php if ($index < count($deliveryData) - 1): ?>
                            <div class="page-break"></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Overall Summary -->
        <div class="summary-section">
            <div class="notes-title">📊 Daily Summary</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-number"><?php echo $totalStats['orders']; ?></div>
                    <div class="summary-label">Total Orders</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo $totalStats['boxes']; ?></div>
                    <div class="summary-label">Total Boxes</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">$<?php echo number_format($totalStats['amount'], 0); ?></div>
                    <div class="summary-label">Total Revenue</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo $totalStats['assigned']; ?></div>
                    <div class="summary-label">Assigned</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo $totalStats['unassigned']; ?></div>
                    <div class="summary-label">Unassigned</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo count(array_unique(array_column($orders, 'assigned_rider_id'))); ?></div>
                    <div class="summary-label">Active Riders</div>
                </div>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="notes-section">
            <div class="notes-title">📝 Delivery Notes</div>
            <div class="notes-lines"></div>
            <div class="notes-lines"></div>
            <div class="notes-lines"></div>
            <div class="notes-lines"></div>
            <div class="notes-lines"></div>
            
            <div style="margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                <div>
                    <div class="notes-title">👨‍💼 Manager Signature</div>
                    <div style="border-bottom: 1px solid #333; height: 40px; margin-top: 20px;"></div>
                    <div style="font-size: 10px; color: #666; margin-top: 5px;">Date: _______________</div>
                </div>
                <div>
                    <div class="notes-title">🚚 Dispatcher Signature</div>
                    <div style="border-bottom: 1px solid #333; height: 40px; margin-top: 20px;"></div>
                    <div style="font-size: 10px; color: #666; margin-top: 5px;">Time: _______________</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 10px; color: #666;">
            <p>🍛 Krua Thai - Authentic Thai Meals, Made Healthy</p>
            <p>For questions contact: admin@kruathai.com | (555) 123-4567</p>
            <p>Delivery sheets generated on <?php echo date('Y-m-d H:i:s'); ?> by <?php echo $_SESSION['first_name'] ?? 'Admin'; ?></p>
        </div>
    </div>

    <script>
        function changeGrouping(value) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('group', value);
            window.location.href = currentUrl.toString();
        }

        // Auto-print functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+P or Cmd+P for print
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
                
                // ESC to close
                if (e.key === 'Escape') {
                    window.close();
                }
            });

            // Add print media queries optimization
            const style = document.createElement('style');
            style.textContent = `
                @media print {
                    .delivery-table {
                        font-size: 10px !important;
                    }
                    
                    .delivery-table th,
                    .delivery-table td {
                        padding: 3px !important;
                    }
                    
                    /* Ensure zone colors are visible in print */
                 .zone-header {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;  /* ใช้ print-color-adjust แทน color-adjust */
}
                    
                    /* Optimize table layout for printing */
                    .delivery-table tr {
                        break-inside: avoid;
                    }
                    
                    /* Adjust margins for better printing */
                    body {
                        margin: 5mm !important;
                        padding: 0 !important;
                    }
                    
                    .print-container {
                        padding: 5mm !important;
                        margin: 0 !important;
                        box-shadow: none !important;
                    }
                }
            `;
            document.head.appendChild(style);
            
            console.log('Krua Thai Delivery Sheets loaded successfully');
            console.log('Total orders: <?php echo $totalStats['orders']; ?>');
            console.log('Total boxes: <?php echo $totalStats['boxes']; ?>');
            console.log('Grouping: <?php echo $groupBy; ?>');
        });

        // Print optimization functions
        function optimizeForPrint() {
            // Hide all interactive elements during print
            const controls = document.querySelectorAll('.no-print');
            controls.forEach(control => {
                control.style.display = 'none';
            });
        }

        function restoreAfterPrint() {
            // Restore interactive elements after print
            const controls = document.querySelectorAll('.no-print');
            controls.forEach(control => {
                control.style.display = 'block';
            });
        }

        // Add print event listeners
        window.addEventListener('beforeprint', optimizeForPrint);
        window.addEventListener('afterprint', restoreAfterPrint);

        // Add automatic page orientation detection
        function detectOptimalOrientation() {
            const orderCount = <?php echo $totalStats['orders']; ?>;
            const hasLongAddresses = <?php echo json_encode(array_reduce($orders, function($carry, $order) {
                return $carry || strlen($order['delivery_address']) > 50;
            }, false)); ?>;
            
            if (orderCount > 15 || hasLongAddresses) {
                // Suggest landscape mode for many orders or long addresses
                console.log('Recommendation: Use landscape orientation for better layout');
            }
        }

        detectOptimalOrientation();
    </script>
</body>
</html>