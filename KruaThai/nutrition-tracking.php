<?php
// nutrition-tracking.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$db = (new Database())->getConnection();

// 1. ดึง Nutrition Goals (เป้าหมาย)
$goal = [];
$stmt = $db->prepare("SELECT * FROM nutrition_goals WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
if ($stmt->rowCount()) $goal = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. ดึงสถิติแต่ละวัน (ย้อนหลัง 7 วัน)
$stmt = $db->prepare("SELECT * FROM daily_nutrition_tracking WHERE user_id = ? ORDER BY tracking_date DESC LIMIT 7");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ติดตามโภชนาการ | Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f8f9fa; color: #2c3e50; }
        .container { max-width: 700px; margin: 2rem auto; background: #fff; padding: 2rem; border-radius: 18px; box-shadow: 0 6px 24px #0001; }
        h1 { font-size: 2rem; color: #cf723a; }
        .goal-bar { margin-bottom: 1rem; }
        .bar-bg { background: #ece8e1; border-radius: 20px; overflow: hidden; height: 18px; }
        .bar { height: 100%; border-radius: 20px; text-align: right; color: #fff; font-weight: bold; padding-right: 7px; }
        .bar-cal { background: #ffae00; }
        .bar-pro { background: #4fc46a; }
        .bar-carb { background: #45aaf2; }
        .bar-fat { background: #fa8231; }
        .row-head th { background: #faf5ed; }
        table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
        th, td { padding: 0.5rem; text-align: center; }
        th { color: #cf723a; }
        tr:nth-child(even) { background: #f6f8fa; }
        .small { font-size: 0.98em; color: #7f8c8d; }
    </style>
</head>
<body>
<div class="container">
    <h1>ติดตามโภชนาการของฉัน</h1>
    <?php if ($goal): ?>
        <div>
            <div class="goal-bar">
                <div class="small">พลังงาน (เป้าหมาย <?=number_format($goal['target_calories'])?> kcal)</div>
                <div class="bar-bg">
                    <div class="bar bar-cal" style="width:<?=min(100, $rows[0]['total_calories'] / ($goal['target_calories'] ?: 1) * 100)?>%">
                        <?=number_format($rows[0]['total_calories'])?> kcal
                    </div>
                </div>
            </div>
            <div class="goal-bar">
                <div class="small">โปรตีน (เป้าหมาย <?=number_format($goal['target_protein_g'])?>g)</div>
                <div class="bar-bg">
                    <div class="bar bar-pro" style="width:<?=min(100, $rows[0]['total_protein_g'] / ($goal['target_protein_g'] ?: 1) * 100)?>%">
                        <?=number_format($rows[0]['total_protein_g'],1)?> g
                    </div>
                </div>
            </div>
            <div class="goal-bar">
                <div class="small">คาร์บ (เป้าหมาย <?=number_format($goal['target_carbs_g'])?>g)</div>
                <div class="bar-bg">
                    <div class="bar bar-carb" style="width:<?=min(100, $rows[0]['total_carbs_g'] / ($goal['target_carbs_g'] ?: 1) * 100)?>%">
                        <?=number_format($rows[0]['total_carbs_g'],1)?> g
                    </div>
                </div>
            </div>
            <div class="goal-bar">
                <div class="small">ไขมัน (เป้าหมาย <?=number_format($goal['target_fat_g'])?>g)</div>
                <div class="bar-bg">
                    <div class="bar bar-fat" style="width:<?=min(100, $rows[0]['total_fat_g'] / ($goal['target_fat_g'] ?: 1) * 100)?>%">
                        <?=number_format($rows[0]['total_fat_g'],1)?> g
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:2rem;">ย้อนหลัง 7 วัน</h2>
    <table border="1">
        <tr class="row-head">
            <th>วันที่</th>
            <th>พลังงาน (kcal)</th>
            <th>โปรตีน (g)</th>
            <th>คาร์บ (g)</th>
            <th>ไขมัน (g)</th>
            <th>ไฟเบอร์ (g)</th>
            <th>โซเดียม (mg)</th>
            <th>%เป้าหมาย</th>
        </tr>
        <?php foreach($rows as $r): ?>
        <tr>
            <td><?=htmlspecialchars($r['tracking_date'])?></td>
            <td><?=number_format($r['total_calories'])?></td>
            <td><?=number_format($r['total_protein_g'],1)?></td>
            <td><?=number_format($r['total_carbs_g'],1)?></td>
            <td><?=number_format($r['total_fat_g'],1)?></td>
            <td><?=number_format($r['total_fiber_g'],1)?></td>
            <td><?=number_format($r['total_sodium_mg'])?></td>
            <td><?=number_format($r['goal_achievement_percentage'],0)?>%</td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
