<?php
// subscription-status.php
session_start();
require_once 'config/database.php';

// เช็คว่าเข้าสู่ระบบหรือยัง
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// --- ฟังก์ชัน Update สถานะ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = $_POST['id'];
    $action = $_POST['action'];
    $status_map = [
        'pause' => 'paused',
        'cancel' => 'cancelled',
        'renew' => 'active'
    ];
    if (isset($status_map[$action])) {
        $new_status = $status_map[$action];
        $stmt = $pdo->prepare("UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $id, $user_id]);
        header("Location: subscription-status.php");
        exit();
    }
}

// --- ดึงข้อมูล Subscription ---
$stmt = $pdo->prepare("
    SELECT s.*, sp.name AS plan_name, sp.meals_per_week, sp.final_price, sp.plan_type
    FROM subscriptions s 
    JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$user_id]);
$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusText($status) {
    $map = [
        'active' => 'ใช้งาน',
        'paused' => 'พักชั่วคราว',
        'cancelled' => 'ยกเลิก',
        'expired' => 'หมดอายุ',
        'pending_payment' => 'รอชำระเงิน'
    ];
    return $map[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สถานะการสมัครสมาชิก - Krua Thai</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f7f6f2; margin: 0; }
        .container { max-width: 900px; margin: 3rem auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 32px #0001; padding: 2rem; }
        h2 { color: #3d4028; margin-bottom: 2rem; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        th, td { padding: 1rem; border-bottom: 1px solid #ece8e1; }
        th { background: #ece8e1; color: #3d4028; }
        td { color: #374151; }
        .status { font-weight: 600; padding: 0.4rem 1.2rem; border-radius: 12px; display: inline-block;}
        .status.active { background: #e8f5e9; color: #388e3c;}
        .status.paused { background: #fff3cd; color: #856404;}
        .status.cancelled { background: #ffebee; color: #c62828;}
        .status.expired { background: #e0e0e0; color: #757575;}
        .status.pending_payment { background: #e3f2fd; color: #0277bd;}
        .btn-action { border: none; padding: 0.6rem 1.1rem; border-radius: 8px; font-size: 1rem; margin-right: 0.5rem; cursor: pointer;}
        .btn-pause { background: #ffe082; color: #8d6e63;}
        .btn-cancel { background: #ffcdd2; color: #c62828;}
        .btn-renew { background: #c8e6c9; color: #388e3c;}
        .btn-action:disabled { opacity: 0.6; cursor: not-allowed;}
        .empty { text-align: center; padding: 3rem; color: #adb89d;}
    </style>
</head>
<body>
<div class="container">
    <h2>สถานะการสมัครสมาชิก</h2>
    <?php if (empty($subs)): ?>
        <div class="empty">ยังไม่มีแพ็กเกจ กรุณา <a href="subscribe.php">สมัครสมาชิก</a></div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>แพ็กเกจ</th>
                    <th>ประเภท</th>
                    <th>จำนวนมื้อต่อสัปดาห์</th>
                    <th>ราคา</th>
                    <th>สถานะ</th>
                    <th>วันเริ่ม</th>
                    <th>วันหมดอายุ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subs as $sub): ?>
                <tr>
                    <td><?= htmlspecialchars($sub['plan_name']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($sub['plan_type'])) ?></td>
                    <td><?= $sub['meals_per_week'] ?></td>
                    <td>฿<?= number_format($sub['final_price']) ?>/<?= $sub['plan_type'] === 'weekly' ? 'สัปดาห์' : 'เดือน' ?></td>
                    <td>
                        <span class="status <?= $sub['status'] ?>"><?= getStatusText($sub['status']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($sub['start_date']) ?></td>
                    <td><?= htmlspecialchars($sub['end_date'] ?? '-') ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                            <?php if ($sub['status'] === 'active'): ?>
                                <button name="action" value="pause" class="btn-action btn-pause">พัก</button>
                                <button name="action" value="cancel" class="btn-action btn-cancel">ยกเลิก</button>
                            <?php elseif ($sub['status'] === 'paused'): ?>
                                <button name="action" value="renew" class="btn-action btn-renew">เริ่มใหม่</button>
                            <?php else: ?>
                                <button disabled class="btn-action">---</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <a href="dashboard.php">← กลับแดชบอร์ด</a>
</div>
</body>
</html>
