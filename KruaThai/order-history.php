<?php
// Krua Thai - Order History Page
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// ต้องล็อกอินก่อน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=order-history.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงประวัติการสั่งซื้อ (Subscription + รายการอาหาร)
try {
    $stmt = $pdo->prepare("
        SELECT s.*, p.name_thai AS plan_name, p.meals_per_week, p.final_price
        FROM subscriptions s
        LEFT JOIN subscription_plans p ON s.plan_id = p.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการสั่งซื้อ | Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #ece8e1; color: #2c3e50; }
        .container { max-width: 950px; margin: 50px auto; background: #fff; border-radius: 16px; box-shadow: 0 6px 24px rgba(207,114,58,0.09); padding: 2rem;}
        h1 { font-size: 2rem; font-weight: 800; margin-bottom: 1.5rem;}
        .history-table { width: 100%; border-collapse: collapse; background: #fff; }
        .history-table th, .history-table td { padding: 1rem; border-bottom: 1px solid #ece8e1; text-align: left;}
        .history-table th { background: #ece8e1; color: #cf723a; font-weight: 700;}
        .status-badge { padding: 0.5em 1.1em; border-radius: 30px; font-size: 0.96em; font-weight: 700;}
        .paid { background: #e9fbe9; color: #24b75d;}
        .pending { background: #fff7e2; color: #ff9900;}
        .cancelled { background: #fdeaea; color: #de2e2e;}
        .active { background: #cf723a; color: #fff;}
        .paused { background: #adb89d; color: #fff;}
        .btn { padding: 0.5em 1.2em; border-radius: 25px; background: #adb89d; color: #fff; text-decoration: none; border: none; transition: background 0.15s;}
        .btn:hover { background: #cf723a;}
        .no-orders { text-align: center; color: #aaa; padding: 3rem 0;}
        @media (max-width:700px){.container{padding:0.8rem;} .history-table th,.history-table td{padding:0.5rem;}}
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-clipboard-list"></i> ประวัติการสั่งซื้อของฉัน</h1>
        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <i class="fas fa-utensils" style="font-size:2.2rem; opacity:0.3;"></i>
                <p>ยังไม่มีประวัติการสั่งซื้อ</p>
                <a href="index.php#plans" class="btn">เลือกแพ็กเกจแรกของคุณ</a>
            </div>
        <?php else: ?>
        <table class="history-table">
            <thead>
                <tr>
                    <th>รหัสออเดอร์</th>
                    <th>แพ็กเกจ</th>
                    <th>จำนวนมื้อ</th>
                    <th>ราคารวม</th>
                    <th>สถานะ</th>
                    <th>วันที่สั่งซื้อ</th>
                    <th>รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                    <td><?php echo htmlspecialchars($order['plan_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($order['meals_per_week']); ?></td>
                    <td>฿<?php echo number_format($order['final_price'] ?: 0); ?></td>
                    <td>
                        <?php
                        $status = strtolower($order['status']);
                        $badgeClass = $status;
                        echo "<span class='status-badge $badgeClass'>";
                        if ($status == 'active') echo 'ใช้งานอยู่';
                        elseif ($status == 'paused') echo 'พัก';
                        elseif ($status == 'cancelled') echo 'ยกเลิก';
                        elseif ($status == 'paid') echo 'ชำระแล้ว';
                        elseif ($status == 'pending') echo 'รอชำระเงิน';
                        else echo ucfirst($status);
                        echo "</span>";
                        ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                    <td>
                        <a href="order-detail.php?id=<?php echo urlencode($order['id']); ?>" class="btn">
                            <i class="fas fa-search"></i> ดู
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>
