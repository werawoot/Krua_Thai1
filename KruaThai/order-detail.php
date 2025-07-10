<?php
// Krua Thai - Order Detail Page
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=order-history.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? '';

if (!$order_id) {
    header('Location: order-history.php');
    exit;
}

// Get subscription/order info
try {
    // Get subscription/order
    $stmt = $pdo->prepare("
        SELECT s.*, p.name_thai AS plan_name, p.meals_per_week, p.final_price
        FROM subscriptions s
        LEFT JOIN subscription_plans p ON s.plan_id = p.id
        WHERE s.id = ? AND s.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('ไม่พบข้อมูลออเดอร์');
    }

    // Get meal items
    $stmt = $pdo->prepare("
        SELECT sm.*, m.name_thai, m.name, m.main_image_url, m.calories_per_serving, m.protein_g
        FROM subscription_menus sm
        LEFT JOIN menus m ON sm.menu_id = m.id
        WHERE sm.subscription_id = ?
        ORDER BY sm.delivery_date ASC
    ");
    $stmt->execute([$order_id]);
    $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: order-history.php');
    exit;
}

function formatStatus($status) {
    switch(strtolower($status)) {
        case 'active': return '<span class="badge active">ใช้งานอยู่</span>';
        case 'paused': return '<span class="badge paused">พัก</span>';
        case 'cancelled': return '<span class="badge cancelled">ยกเลิก</span>';
        case 'paid': return '<span class="badge paid">ชำระแล้ว</span>';
        case 'pending': return '<span class="badge pending">รอชำระเงิน</span>';
        default: return '<span class="badge">'.$status.'</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดออเดอร์ | Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #ece8e1; color: #2c3e50; }
        .container { max-width: 950px; margin: 40px auto; background: #fff; border-radius: 18px; box-shadow: 0 8px 28px rgba(207,114,58,0.11); padding: 2.5rem;}
        h1 { font-size: 2rem; font-weight: 800; margin-bottom: 1rem;}
        .badge { padding: 0.5em 1.1em; border-radius: 30px; font-size: 0.96em; font-weight: 700;}
        .paid { background: #e9fbe9; color: #24b75d;}
        .pending { background: #fff7e2; color: #ff9900;}
        .cancelled { background: #fdeaea; color: #de2e2e;}
        .active { background: #cf723a; color: #fff;}
        .paused { background: #adb89d; color: #fff;}
        .order-info-table { width: 100%; margin-bottom: 2.5rem;}
        .order-info-table th, .order-info-table td { padding: 0.5rem 0.7rem; text-align: left; font-weight: 500;}
        .order-info-table th { color: #cf723a; width: 160px;}
        .meals-list { margin: 2rem 0; }
        .meal-card { display: flex; gap: 1rem; background: #ece8e1; border-radius: 14px; margin-bottom: 1.1rem; align-items: center; }
        .meal-img { width: 80px; height: 80px; border-radius: 13px; object-fit: cover; background: #fff;}
        .meal-details { flex: 1;}
        .meal-title { font-weight: 700; color: #cf723a;}
        .meal-meta { color: #999; font-size: 0.97em;}
        .back-link { display: inline-block; margin-top: 1.5rem; background: #adb89d; color: #fff; border-radius: 25px; padding: 0.6em 1.5em; text-decoration: none; }
        .back-link:hover { background: #cf723a;}
        @media (max-width:700px){.container{padding:1rem;} .meal-img{width:50px;height:50px;}}
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-file-alt"></i> รายละเอียดออเดอร์</h1>
        <table class="order-info-table">
            <tr>
                <th>รหัสออเดอร์</th>
                <td><?php echo htmlspecialchars($order['id']); ?></td>
            </tr>
            <tr>
                <th>แพ็กเกจ</th>
                <td><?php echo htmlspecialchars($order['plan_name'] ?: '-'); ?></td>
            </tr>
            <tr>
                <th>จำนวนมื้อ</th>
                <td><?php echo (int)($order['meals_per_week']); ?> มื้อ</td>
            </tr>
            <tr>
                <th>ราคารวม</th>
                <td>฿<?php echo number_format($order['final_price'] ?: 0); ?></td>
            </tr>
            <tr>
                <th>สถานะ</th>
                <td><?php echo formatStatus($order['status']); ?></td>
            </tr>
            <tr>
                <th>วันที่สั่งซื้อ</th>
                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
            </tr>
            <tr>
                <th>วันส่งอาหาร</th>
                <td>
                    <?php echo $order['delivery_date'] ? date('d/m/Y', strtotime($order['delivery_date'])) : '-'; ?>
                </td>
            </tr>
            <tr>
                <th>ช่องทางชำระเงิน</th>
                <td>
                    <?php echo htmlspecialchars($order['payment_method'] ?? '—'); ?>
                    <?php if ($order['payment_status']): ?>
                        <span class="badge <?php echo strtolower($order['payment_status']); ?>">
                            <?php echo $order['payment_status']=='success' ? 'สำเร็จ' : ucfirst($order['payment_status']); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h2 style="font-size:1.2rem;color:#2c3e50;margin:1.5rem 0 1rem;font-weight:700;">
            เมนูอาหารในออเดอร์นี้
        </h2>
        <div class="meals-list">
            <?php if (empty($meals)): ?>
                <p style="color:#999;">ยังไม่มีการเลือกเมนูในออเดอร์นี้</p>
            <?php else: ?>
                <?php foreach ($meals as $meal): ?>
                    <div class="meal-card">
                        <img src="<?php echo $meal['main_image_url'] ?: 'assets/images/food-placeholder.jpg'; ?>"
                             class="meal-img"
                             alt="<?php echo htmlspecialchars($meal['name_thai'] ?: $meal['name']); ?>">
                        <div class="meal-details">
                            <div class="meal-title">
                                <?php echo htmlspecialchars($meal['name_thai'] ?: $meal['name']); ?>
                            </div>
                            <div class="meal-meta">
                                <?php if ($meal['delivery_date']): ?>
                                    <span><i class="fas fa-calendar"></i> ส่งวันที่ <?php echo date('d/m/Y', strtotime($meal['delivery_date'])); ?></span>
                                <?php endif; ?>
                                <?php if ($meal['calories_per_serving']): ?>
                                    <span style="margin-left:15px;"><i class="fas fa-fire"></i> <?php echo $meal['calories_per_serving']; ?> kcal</span>
                                <?php endif; ?>
                                <?php if ($meal['protein_g']): ?>
                                    <span style="margin-left:15px;"><i class="fas fa-egg"></i> <?php echo number_format($meal['protein_g'],1); ?>g โปรตีน</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="order-history.php" class="back-link"><i class="fas fa-chevron-left"></i> กลับสู่ประวัติ</a>
    </div>
</body>
</html>
