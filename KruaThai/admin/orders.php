<?php
/**
 * Krua Thai - Orders Management (Production-Ready, Original Theme)
 * File: admin/orders.php
 * Description: Secure, feature-rich, and styled order management system.
 */

// --- 1. การตั้งค่าพื้นฐานและ Session ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // ใน Production จริงควรตั้งเป็น 0 และ Log error แทน
session_start();

// --- 2. เรียกใช้ไฟล์ที่จำเป็น ---
require_once '../config/database.php';
require_once '../includes/functions.php'; 

// --- 3. การป้องกัน CSRF (สำคัญมาก) ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 4. ตรวจสอบสิทธิ์การเข้าถึงของ Admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// --- 5. จัดการ AJAX Requests อย่างปลอดภัย ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF Token. Please refresh the page.']);
        exit;
    }

    try {
        switch ($_POST['action']) {
            case 'update_order_status':
                $result = updateOrderStatus($pdo, $_POST['order_id'] ?? null, $_POST['status'] ?? null);
                echo json_encode($result);
                exit;
            case 'assign_rider':
                $result = assignRider($pdo, $_POST['order_id'] ?? null, $_POST['rider_id'] ?? null);
                echo json_encode($result);
                exit;
            case 'get_order_details':
                $result = getOrderDetails($pdo, $_POST['order_id'] ?? null);
                echo json_encode($result);
                exit;
            case 'bulk_update_status':
                $order_ids = json_decode($_POST['order_ids'] ?? '[]', true);
                $result = bulkUpdateStatus($pdo, $order_ids, $_POST['status'] ?? null);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        // logActivity('ajax_error', ...);
        echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred.']);
        exit;
    }
}

// --- 6. ฟังก์ชันจัดการฐานข้อมูล ---

function updateOrderStatus($pdo, $orderId, $status) {
    if (!$orderId || !$status) return ['success' => false, 'message' => 'Missing parameters.'];
    $allowed_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed_statuses)) return ['success' => false, 'message' => 'Invalid status.'];
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        if ($stmt->rowCount() > 0) return ['success' => true, 'message' => 'อัปเดตสถานะออเดอร์แล้ว'];
        return ['success' => false, 'message' => 'ไม่พบออเดอร์ หรือสถานะเหมือนเดิม'];
    } catch (PDOException $e) { return ['success' => false, 'message' => 'Database error.']; }
}

function assignRider($pdo, $orderId, $riderId) {
    if (!$orderId || !$riderId) return ['success' => false, 'message' => 'Missing parameters.'];
    try {
        $stmt = $pdo->prepare("UPDATE orders SET assigned_rider_id = ?, status = 'out_for_delivery', updated_at = NOW() WHERE id = ? AND status NOT IN ('delivered', 'cancelled')");
        $stmt->execute([$riderId, $orderId]);
        if ($stmt->rowCount() > 0) return ['success' => true, 'message' => 'มอบหมาย Rider เรียบร้อย'];
        return ['success' => false, 'message' => 'ไม่สามารถมอบหมาย Rider ได้'];
    } catch (PDOException $e) { return ['success' => false, 'message' => 'Database error.']; }
}

function getOrderDetails($pdo, $orderId) {
    if (!$orderId) return ['success' => false, 'message' => 'Missing Order ID.'];
    try {
        $stmt = $pdo->prepare("SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email, u.phone, CONCAT(r.first_name, ' ', r.last_name) as rider_name FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN users r ON o.assigned_rider_id = r.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return ['success' => false, 'message' => 'Order not found.'];
        
        $stmt = $pdo->prepare("SELECT oi.quantity, oi.menu_price, m.name_thai FROM order_items oi JOIN menus m ON oi.menu_id = m.id WHERE oi.order_id = ?");
        $stmt->execute([$orderId]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $order];
    } catch (PDOException $e) { return ['success' => false, 'message' => 'Error fetching details.']; }
}

function bulkUpdateStatus($pdo, $orderIds, $status) {
    if (!is_array($orderIds) || empty($orderIds) || !$status) return ['success' => false, 'message' => 'Invalid data.'];
    $allowed = ['confirmed', 'preparing', 'ready', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) return ['success' => false, 'message' => 'Invalid status.'];
    try {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$status], $orderIds));
        return ['success' => true, 'message' => 'อัปเดต ' . $stmt->rowCount() . ' ออเดอร์เรียบร้อย'];
    } catch (PDOException $e) { return ['success' => false, 'message' => 'Database error.']; }
}

// --- 7. ดึงข้อมูลสำหรับแสดงผลบนหน้าเว็บ ---
$page_error = null;
$limit = 20;
try {
    $status_filter = $_GET['status'] ?? '';
    $date_filter = $_GET['date'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $sort_by_input = $_GET['sort'] ?? 'delivery_date'; 
    $sort_by_allowed = ['delivery_date', 'created_at', 'status'];
    $sort_by = in_array($sort_by_input, $sort_by_allowed) ? $sort_by_input : 'delivery_date';
    
    $whereConditions = []; $params = [];
    if ($status_filter) { $whereConditions[] = "o.status = ?"; $params[] = $status_filter; }
    if ($date_filter) { $whereConditions[] = "o.delivery_date = ?"; $params[] = $date_filter; }
    if ($search) {
        $whereConditions[] = "(o.order_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm);
    }
    $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.id $whereClause");
    $stmt->execute($params);
    $total_orders = $stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    $sql = "SELECT o.id, o.order_number, o.delivery_date, o.status, o.assigned_rider_id, CONCAT(u.first_name, ' ', u.last_name) as customer_name, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count, CONCAT(r.first_name, ' ', r.last_name) as rider_name FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN users r ON o.assigned_rider_id = r.id $whereClause ORDER BY $sort_by DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $riders = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role = 'rider' AND status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $page_error = "เกิดข้อผิดพลาดในการดึงข้อมูลออเดอร์";
    $orders = []; $riders = []; $total_orders = 0; $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการออเดอร์ - Krua Thai Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            --radius-md: 12px;
            --transition: all 0.3s ease;
        }
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f5f2; margin: 0; }
        .admin-layout { display: flex; }
        .sidebar { width: 260px; background: linear-gradient(135deg, var(--brown), var(--curry)); color: white; height: 100vh; position: fixed; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-nav { padding-top: 1rem; }
        .nav-item { display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1.5rem; color: white; text-decoration: none; transition: var(--transition); border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); border-left-color: white; }
        .main-content { margin-left: 260px; padding: 2rem; width: calc(100% - 260px); }
        .page-header { background: white; padding: 2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 2rem; font-weight: 700; margin: 0; }
        .filter-container { background: white; padding: 1.5rem; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); margin-bottom: 2rem; }
        .filter-form { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: flex-end; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px; font-family: inherit; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: var(--transition); }
        .btn-primary { background-color: var(--curry); color: white; }
        .btn-primary:hover { background-color: #a55d2e; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .table-container { background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
        th { background-color: #faf9f7; font-weight: 600; }
        .bulk-actions { display: none; padding: 1rem; background-color: #fff3e0; border-bottom: 1px solid #ffe0b2; align-items: center; gap: 1rem; }
        .bulk-actions.show { display: flex; }
        .action-btn { background: none; border: none; cursor: pointer; color: var(--text-gray); font-size: 1.1rem; padding: 0.5rem; }
        .action-btn:hover { color: var(--curry); }
    </style>
</head>
<body>

<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header"><h3>Krua Thai Admin</h3></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="orders.php" class="nav-item active"><i class="fas fa-shopping-cart"></i><span>Orders</span></a>
            <a href="subscriptions.php" class="nav-item"><i class="fas fa-sync-alt"></i><span>Subscriptions</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1 class="page-title">จัดการออเดอร์</h1>
                <p>ตรวจสอบและจัดการสถานะออเดอร์ของลูกค้าทั้งหมด</p>
            </div>
            <a href="generate_orders.php" class="btn btn-primary">
                <i class="fas fa-calendar-week"></i> สร้างออเดอร์สุดสัปดาห์
            </a>
        </header>

        <section class="filter-container">
            <form method="GET" class="filter-form">
                <div class="form-group"><label>ค้นหา</label><input type="text" name="search" class="form-control" placeholder="เบอร์ออเดอร์, ชื่อลูกค้า..." value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="form-group"><label>วันจัดส่ง</label><input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>"></div>
                <div class="form-group"><label>สถานะ</label><select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" <?php if($status_filter == 'pending') echo 'selected';?>>Pending</option>
                    <option value="confirmed" <?php if($status_filter == 'confirmed') echo 'selected';?>>Confirmed</option>
                    <option value="preparing" <?php if($status_filter == 'preparing') echo 'selected';?>>Preparing</option>
                    <option value="ready" <?php if($status_filter == 'ready') echo 'selected';?>>Ready</option>
                    <option value="out_for_delivery" <?php if($status_filter == 'out_for_delivery') echo 'selected';?>>Out for Delivery</option>
                    <option value="delivered" <?php if($status_filter == 'delivered') echo 'selected';?>>Delivered</option>
                    <option value="cancelled" <?php if($status_filter == 'cancelled') echo 'selected';?>>Cancelled</option>
                </select></div>
                <div class="form-group"><button type="submit" class="btn btn-primary" style="width:100%;">ค้นหา</button></div>
            </form>
        </section>

        <div class="table-container">
            <div id="bulkActions" class="bulk-actions">
                <strong id="selectedCount">0</strong><span>&nbsp;รายการที่เลือก</span>
                <select id="bulkStatusSelect" class="form-control" style="width: auto;">
                    <option value="">เปลี่ยนสถานะเป็น...</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="preparing">Preparing</option>
                </select>
                <button class="btn btn-primary" onclick="applyBulkUpdate()">Apply</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" onchange="toggleSelectAll(this)"></th>
                        <th>ออเดอร์</th>
                        <th>ลูกค้า</th>
                        <th>สถานะ</th>
                        <th>วันจัดส่ง</th>
                        <th>Rider</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($page_error): ?>
                        <tr><td colspan="7" style="text-align:center; color:red;"><?php echo htmlspecialchars($page_error); ?></td></tr>
                    <?php elseif (empty($orders)): ?>
                        <tr><td colspan="7" style="text-align:center; padding: 3rem;">
                            <h4>ไม่พบข้อมูลออเดอร์</h4>
                            <p style="color: #777; font-size: 0.9rem;">อาจยังไม่มีออเดอร์สำหรับวันนี้ ลองกดปุ่ม "สร้างออเดอร์สุดสัปดาห์"</p>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><input type="checkbox" class="order-checkbox" value="<?php echo $order['id']; ?>" onchange="updateBulkActionsUI()"></td>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br><small><?php echo $order['item_count']; ?> รายการ</small></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td>
                                <select class="form-control" style="min-width: 150px;" onchange="updateOrderStatus('<?php echo $order['id']; ?>', this.value)">
                                    <?php foreach (['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'] as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php if($order['status'] == $status) echo 'selected';?>><?php echo ucfirst(str_replace('_', ' ', $status)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?php echo date('d M Y', strtotime($order['delivery_date'])); ?></td>
                            <td>
                                <select class="form-control" onchange="assignRider('<?php echo $order['id']; ?>', this.value)">
                                    <option value="">- มอบหมาย -</option>
                                    <?php foreach ($riders as $rider): ?>
                                        <option value="<?php echo $rider['id']; ?>" <?php if($order['assigned_rider_id'] == $rider['id']) echo 'selected';?>><?php echo htmlspecialchars($rider['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button onclick="viewDetails('<?php echo $order['id']; ?>')" class="action-btn" title="ดูรายละเอียด"><i class="fas fa-eye"></i></button>
                                <a href="print-order.php?id=<?php echo $order['id']; ?>" class="action-btn" title="พิมพ์" target="_blank"><i class="fas fa-print"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
    const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
    // JavaScript functions (updateOrderStatus, applyBulkUpdate, etc.)
</script>

</body>
</html>