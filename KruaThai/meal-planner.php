<?php
session_start();
require_once 'config/database.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// เลือก subscription ปัจจุบัน (active หรือ paused)
$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status IN ('active', 'paused') ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    echo "<p style='text-align:center;padding:3rem;'>ยังไม่มี Subscription กรุณาสมัครก่อน <a href='subscribe.php'>สมัครสมาชิก</a></p>";
    exit;
}

// ดึงเมนูทั้งหมด
$menus = $pdo->query("SELECT id, name FROM menus WHERE is_available = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// วันในสัปดาห์
$week_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

// เช็คถ้ามีการ submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($week_days as $day) {
        $field = strtolower($day) . '_menu';
        if (!empty($_POST[$field])) {
            // date ในสัปดาห์ถัดไป (หรือปรับสูตรได้)
            $date = date('Y-m-d', strtotime("next $day"));
            $menu_id = $_POST[$field];
            // check ว่ามี record เดิมหรือยัง
            $check = $pdo->prepare("SELECT id FROM subscription_menus WHERE subscription_id=? AND delivery_date=?");
            $check->execute([$subscription['id'], $date]);
            if ($check->fetch()) {
                // update
                $stmt = $pdo->prepare("UPDATE subscription_menus SET menu_id=? WHERE subscription_id=? AND delivery_date=?");
                $stmt->execute([$menu_id, $subscription['id'], $date]);
            } else {
                // insert
                $stmt = $pdo->prepare("INSERT INTO subscription_menus (id, subscription_id, menu_id, delivery_date) VALUES (UUID(),?,?,?)");
                $stmt->execute([$subscription['id'], $menu_id, $date]);
            }
        }
    }
    $msg = "บันทึกแผนอาหารสำเร็จ!";
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Meal Planner - Krua Thai</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f7f6f2; }
        .container { max-width: 650px; margin: 2.5rem auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px #0002; padding: 2rem; }
        h2 { text-align: center; color: #3d4028; }
        form { margin: 2rem 0; }
        .day-row { display: flex; align-items: center; margin-bottom: 1.2rem; }
        .day-label { width: 120px; font-weight: 600; color: #adb89d; }
        select { padding: 0.7rem 1rem; border-radius: 7px; border: 1px solid #ece8e1; font-size: 1rem; width: 100%; }
        button { margin-top: 2rem; background: #adb89d; color: #fff; border: none; border-radius: 8px; padding: 1rem 2.2rem; font-size: 1.1rem; cursor: pointer; transition: 0.2s; }
        button:hover { background: #3d4028; }
        .msg { text-align: center; color: #388e3c; font-weight: 600; margin-bottom: 1.5rem; }
        .dashboard-link { display:block; text-align:center; color:#3d4028; margin-top:1.5rem; }
    </style>
</head>
<body>
<div class="container">
    <h2>Meal Planner (เลือกเมนูแต่ละวัน)</h2>
    <?php if (!empty($msg)) echo "<div class='msg'>{$msg}</div>"; ?>
    <form method="post">
        <?php foreach ($week_days as $day): ?>
            <div class="day-row">
                <div class="day-label"><?= $day ?></div>
                <select name="<?= strtolower($day) ?>_menu" required>
                    <option value="">-- เลือกเมนู --</option>
                    <?php foreach ($menus as $menu): ?>
                        <option value="<?= $menu['id'] ?>"><?= htmlspecialchars($menu['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
        <button type="submit">บันทึกแผนอาหาร</button>
    </form>
    <a href="subscription-status.php" class="dashboard-link">← กลับ Subscription Status</a>
</div>
</body>
</html>
