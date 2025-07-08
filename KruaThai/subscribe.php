<?php
/**
 * Krua Thai - Subscribe Page
 * File: subscribe.php
 * Description: เลือกแพ็กเกจอาหารก่อนเลือกเมนู (Step 1)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    $redirect_url = 'subscribe.php';
    if (isset($_GET['menu'])) $redirect_url .= '?menu=' . urlencode($_GET['menu']);
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

$highlight_menu_id = $_GET['menu'] ?? '';

// ดึงแพ็กเกจจากฐานข้อมูล (หรือ mock data หากไม่มี DB)
try {
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $plans = [
        [
            'id' => 4, 'name_thai' => 'แพ็กเกจเริ่มต้น', 'meals_per_week' => 4, 'final_price' => 899, 'description' => 'เหมาะสำหรับผู้เริ่มต้น'
        ],
        [
            'id' => 8, 'name_thai' => 'กินดีทุกวัน', 'meals_per_week' => 8, 'final_price' => 1599, 'description' => 'เหมาะสำหรับดูแลสุขภาพประจำ'
        ],
        [
            'id' => 12, 'name_thai' => 'สุขภาพทั้งครอบครัว', 'meals_per_week' => 12, 'final_price' => 2199, 'description' => 'เหมาะสำหรับครอบครัว'
        ],
        [
            'id' => 15, 'name_thai' => 'พรีเมียมโปร', 'meals_per_week' => 15, 'final_price' => 2699, 'description' => 'สำหรับสาย Healthy จัดเต็ม'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เลือกแพ็กเกจอาหาร | Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #ece8e1; color: #2c3e50; }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .progress-bar {
            width:100%; margin:30px 0 32px 0; display:flex; gap:8px; align-items:center; justify-content:center;
        }
        .progress-step {
            border-radius:20px; padding:6px 18px; font-weight:700; font-size:1rem;
            background: #ece8e1; color: #cf723a; border: 2px solid #ece8e1; transition:.2s;
        }
        .progress-step.active, .progress-step.completed { background: #cf723a; color:#fff; border-color: #cf723a; }
        .progress-arrow { color:#adb89d; font-size:1.4rem; }
        .title { font-size: 2.2rem; font-weight: 700; margin-bottom: 1rem; text-align:center;}
        .subtitle { font-size: 1.1rem; color: #7f8c8d; margin-bottom: 2.5rem; text-align:center;}
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; }
        .plan-card {
            background: #fff; border-radius: 20px; box-shadow: 0 4px 16px rgba(207,114,58,0.10);
            padding: 2rem; text-align: center; transition: box-shadow 0.2s, transform 0.2s, border-color .2s;
            border: 2px solid #ece8e1; position: relative;
        }
        .plan-card:hover, .plan-card.selected { box-shadow: 0 8px 28px rgba(207,114,58,0.16); border-color: #cf723a; }
        .plan-card.selected:after {
            content:'✔'; position:absolute; top:16px; right:18px; color:#28a745; font-size:2rem;
        }
        .plan-name { font-size: 1.4rem; font-weight: 700; margin-bottom: 0.3rem; color: #cf723a; }
        .plan-info { color: #7f8c8d; font-size: 1rem; margin-bottom: 0.5rem; }
        .plan-price { font-size: 2.2rem; font-weight: 800; color: #cf723a; margin-bottom: 1.2rem; }
        .plan-desc { font-size: 1rem; color: #2c3e50; margin-bottom: 1.5rem; }
        .btn {
            padding: 0.9rem 2rem; border-radius: 50px; background: #cf723a; color: #fff;
            font-weight: 700; border: none; cursor: pointer; font-size: 1.1rem; transition: background 0.2s;
            text-decoration: none; display: inline-block;
        }
        .btn:hover { background: #bd9379; }
        @media (max-width: 600px) { .plans-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-step active"><i class="fas fa-check-circle"></i> 1 เลือกแพ็กเกจ</div>
            <span class="progress-arrow">→</span>
            <div class="progress-step">2 เลือกเมนู</div>
            <span class="progress-arrow">→</span>
            <div class="progress-step">3 ชำระเงิน</div>
            <span class="progress-arrow">→</span>
            <div class="progress-step">4 เสร็จสิ้น</div>
        </div>

        <div class="title">เลือกแพ็กเกจอาหาร</div>
        <div class="subtitle">
            กรุณาเลือกแพ็กเกจที่เหมาะกับไลฟ์สไตล์ของคุณ<br>
            จากนั้นจะไปเลือกเมนูตามจำนวนที่กำหนด
        </div>
        <div class="plans-grid">
            <?php foreach($plans as $plan): 
                // ถ้า highlight menu id ส่ง query ไปหน้า meal-selection ต่อ
                $query = "plan=" . urlencode($plan['id']);
                if ($highlight_menu_id) $query .= "&menu=" . urlencode($highlight_menu_id);
            ?>
            <div class="plan-card<?php echo ($plan['meals_per_week'] == 8) ? ' selected' : ''; ?>">
                <div class="plan-name"><?php echo htmlspecialchars($plan['name_thai']); ?></div>
                <div class="plan-info"><?php echo $plan['meals_per_week']; ?> มื้อต่อสัปดาห์</div>
                <div class="plan-price">฿<?php echo number_format($plan['final_price'], 0); ?> /สัปดาห์</div>
                <div class="plan-desc"><?php echo htmlspecialchars($plan['description']); ?></div>
                <a href="meal-selection.php?<?php echo $query; ?>" class="btn">
                    เลือกแพ็กเกจนี้ <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
