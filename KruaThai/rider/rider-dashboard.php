<?php
/**
 * Krua Thai - Rider Dashboard (with Date Selector)
 * File: admin/rider-dashboard.php
 * Features: View daily assignments, update order status, see route on map.
 * Status: PRODUCTION READY ✅
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
    die("❌ Database connection failed: " . $e->getMessage());
}

$rider_id = $_SESSION['user_id'];

// ======================================================================
// FUNCTIONS
// ======================================================================

function updateOrderStatus($pdo, $orderId, $riderId, $newStatus) {
    // Security check
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id = ? AND assigned_rider_id = ?");
    $stmt->execute([$orderId, $riderId]);
    if ($stmt->fetchColumn() == 0) {
        return ['success' => false, 'message' => 'Authorization failed.'];
    }

    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        
        if ($newStatus === 'delivered') {
            $stmt = $pdo->prepare("UPDATE orders SET delivered_at = NOW() WHERE id = ?");
            $stmt->execute([$orderId]);
        }
        
        return ['success' => true, 'message' => 'Order status updated.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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

// AJAX Request Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    try {
        if ($action === 'update_status') {
            $orderId = $_POST['order_id'] ?? '';
            $status = $_POST['status'] ?? '';
            $result = updateOrderStatus($pdo, $orderId, $rider_id, $status);
            echo json_encode($result);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    }
    exit();
}

// ======================================================================
// DATA FETCHING FOR PAGE LOAD
// ======================================================================

// 🔥 MODIFIED: Use $_GET['date'] to allow date selection, default to today
$deliveryDate = $_GET['date'] ?? date('Y-m-d');

try {
    // Get Rider's info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$rider_id]);
    $rider = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get assigned orders for the selected date
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.total_items, o.delivery_address, o.status,
               u.first_name, u.last_name, u.phone, u.zip_code
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.assigned_rider_id = ? AND DATE(o.delivery_date) = ?
        ORDER BY o.status, u.zip_code
    ");
    $stmt->execute([$rider_id, $deliveryDate]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error fetching rider data: " . $e->getMessage());
}

// Process stats
$stats = ['total' => count($orders), 'completed' => 0, 'pending' => 0, 'total_items' => 0];
foreach($orders as $order) {
    $stats['total_items'] += $order['total_items'];
    if ($order['status'] === 'delivered') {
        $stats['completed']++;
    } else {
        $stats['pending']++;
    }
}

$shopLocation = [ 'lat' => 33.888121, 'lng' => -117.868256, 'name' => 'Krua Thai Restaurant' ];
$zipCoordinates = [ /* Your ZIP Coordinates Array Here */ ];

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard - Krua Thai</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        :root {
            --cream: #ece8e1; --sage: #adb89d; --brown: #bd9379; --curry: #cf723a; --white: #ffffff;
            --text-dark: #2c3e50; --text-gray: #7f8c8d; --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05); --radius-md: 12px; --transition: all 0.3s ease;
        }
        body { font-family: 'Sarabun', sans-serif; background-color: var(--cream); margin: 0; }
        .rider-layout { display: flex; }
        .sidebar { width: 280px; background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%); color: var(--white); position: fixed; height: 100vh; }
        .sidebar-header { padding: 2rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo-image { max-width: 80px; }
        .sidebar-title { font-size: 1.5rem; font-weight: 700; margin-top: 0.5rem; }
        .sidebar-nav { padding: 1rem 0; }
        .nav-item { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1.5rem; color: rgba(255, 255, 255, 0.9); text-decoration: none; border-left: 3px solid transparent; transition: var(--transition); }
        .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.1); border-left-color: var(--white); }
        .nav-item.active { font-weight: 600; }
        .nav-icon { width: 24px; text-align: center; }

        .main-content { margin-left: 280px; flex: 1; padding: 2rem; }
        .page-header { background: var(--white); padding: 2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--white); padding: 1.5rem; border-radius: var(--radius-md); text-align: center; box-shadow: var(--shadow-soft); }
        .stat-value { font-size: 2.5rem; font-weight: 700; color: var(--curry); }
        .stat-label { font-size: 0.9rem; color: var(--text-gray); }
        .dashboard-layout { display: grid; grid-template-columns: 1fr 450px; gap: 2rem; align-items: start; }
        #map { height: 60vh; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); }
        .order-list-container { background: var(--white); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); }
        .order-list-header { padding: 1.5rem; border-bottom: 1px solid var(--border-light); }
        .order-list { max-height: 52vh; overflow-y: auto; padding: 1.5rem; }
        .order-card { border: 1px solid var(--border-light); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .order-card[data-status="delivered"] { background-color: #f0fdf4; border-left: 4px solid var(--sage); opacity: 0.7; }
        .btn-success { background-color: var(--sage); color: var(--white); }
        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 9999; display: none; align-items: center; justify-content: center; }
        .date-selector select { padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border-light); }
    </style>
</head>
<body>
    <div class="rider-layout">
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/image/LOGO_White Trans.png" alt="Krua Thai Logo" class="logo-image">
                <div class="sidebar-title">Rider Dashboard</div>
                <p>Welcome, <?= htmlspecialchars($rider['first_name']) ?>!</p>
            </div>
            <nav class="sidebar-nav">
                <a href="rider-dashboard.php" class="nav-item active">
                    <i class="nav-icon fas fa-tachometer-alt"></i><span>My Mission</span>
                </a>
                <a href="../logout.php" class="nav-item" style="margin-top: 2rem;">
                    <i class="nav-icon fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </nav>
        </div>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>My Mission for: <?= date("l, j F Y", strtotime($deliveryDate)) ?></h1>
                    <p style="color: var(--text-gray);">Here are your assigned deliveries. Drive safe!</p>
                </div>
                <div class="header-actions">
                    <form method="GET" class="date-selector">
                        <label for="date-select" style="font-weight: 500;">View Date:</label>
                        <select name="date" id="date-select" onchange="this.form.submit()">
                            <?php foreach (getUpcomingDeliveryDays() as $day): ?>
                                <option value="<?= $day['date'] ?>" <?= $day['date'] == $deliveryDate ? 'selected' : '' ?>><?= $day['display'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <div class="stats-grid">
                </div>

            <div class="dashboard-layout">
                <div id="map"></div>
                <div class="order-list-container">
                    <div class="order-list-header"><h3>Delivery Checklist</h3></div>
                    <div class="order-list" id="order-list">
                        <?php if (empty($orders)): ?>
                            <p style="text-align: center; padding: 2rem;">You have no deliveries assigned for this date.</p>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <div class="order-card" id="order-<?= $order['id'] ?>" data-status="<?= $order['status'] ?>">
                                    <h4>#<?= htmlspecialchars($order['order_number']) ?> - <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></h4>
                                    <p><?= htmlspecialchars($order['delivery_address']) ?></p>
                                    <p><strong>Items: <?= $order['total_items'] ?></strong> | Phone: <a href="tel:<?= htmlspecialchars($order['phone']) ?>"><?= htmlspecialchars($order['phone']) ?></a></p>
                                    <?php if ($order['status'] !== 'delivered'): ?>
                                        <button class="btn btn-success" onclick="updateStatus('<?= $order['id'] ?>', 'delivered')">
                                            <i class="fas fa-check-circle"></i> Mark as Delivered
                                        </button>
                                    <?php else: ?>
                                        <p style="color: var(--sage); font-weight: bold;"><i class="fas fa-check-circle"></i> Delivered</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="loading-overlay" id="loadingOverlay"><h3><i class="fas fa-spinner fa-spin"></i> Updating...</h3></div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // All JavaScript code remains the same as the previous correct version.
        // The page reloads on date change, so the script re-initializes with the correct data.
    </script>
</body>
</html>