<?php
/**
 * Krua Thai - Generate Weekend Orders Script (Production-Ready, Original Theme)
 * File: admin/generate_orders.php
 * Description: Automatically finds the next Sat/Sun and creates all orders for the upcoming weekend.
 */

// --- 1. การตั้งค่าพื้นฐาน ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // ใน Production จริงควรตั้งเป็น 0
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

// --- 2. ตรวจสอบสิทธิ์ Admin ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// --- 3. ฟังก์ชันหลักในการสร้างออเดอร์สำหรับสุดสัปดาห์ ---
function generateOrdersForUpcomingWeekend($pdo) {
    
    // คำนวณหาวันเสาร์และอาทิตย์ถัดไป
    $upcomingSaturday = date('Y-m-d', strtotime('next saturday'));
    $upcomingSunday = date('Y-m-d', strtotime('next sunday'));
    $weekendDates = [$upcomingSaturday, $upcomingSunday];

    $generated_count = 0;
    $skipped_count = 0;

    try {
        $pdo->beginTransaction();

        // ดึงรายการทั้งหมดที่ต้องจัดส่งในวันเสาร์และอาทิตย์ที่จะถึงนี้
        $placeholders = implode(',', array_fill(0, count($weekendDates), '?'));
        $stmt = $pdo->prepare("
            SELECT 
                s.id as subscription_id, s.user_id,
                u.delivery_address, u.delivery_instructions,
                sm.menu_id, sm.quantity, sm.delivery_date,
                m.base_price, m.name as menu_name
            FROM subscription_menus sm
            JOIN subscriptions s ON sm.subscription_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN menus m ON sm.menu_id = m.id
            WHERE s.status = 'active' AND sm.delivery_date IN ($placeholders)
        ");
        $stmt->execute($weekendDates);
        $all_scheduled_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($all_scheduled_items)) {
            return ['success' => true, 'message' => "ไม่พบรายการที่ต้องจัดส่งสำหรับสุดสัปดาห์นี้ (" . implode(' และ ', $weekendDates) . ")"];
        }

        // จัดกลุ่มรายการอาหารตามวันและ Subscription
        $grouped_by_day_and_sub = [];
        foreach ($all_scheduled_items as $item) {
            $delivery_date = $item['delivery_date'];
            $subscription_id = $item['subscription_id'];
            
            if (!isset($grouped_by_day_and_sub[$delivery_date][$subscription_id])) {
                $grouped_by_day_and_sub[$delivery_date][$subscription_id] = [
                    'details' => [
                        'user_id' => $item['user_id'],
                        'delivery_address' => $item['delivery_address'],
                        'delivery_instructions' => $item['delivery_instructions'],
                    ],
                    'items' => []
                ];
            }
            $grouped_by_day_and_sub[$delivery_date][$subscription_id]['items'][] = $item;
        }
        
        // วนลูปสร้าง Order
        foreach ($grouped_by_day_and_sub as $delivery_date => $subscriptions) {
            foreach ($subscriptions as $subscription_id => $data) {
                // ตรวจสอบว่าเคยสร้าง Order ของวันนี้ไปแล้วหรือยัง
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE subscription_id = ? AND delivery_date = ?");
                $check_stmt->execute([$subscription_id, $delivery_date]);
                if ($check_stmt->fetchColumn() > 0) {
                    $skipped_count++;
                    continue; 
                }

                // สร้าง Order ใหม่
                $order_id = generateUUID();
                $order_number = 'ORD-' . date('Ymd', strtotime($delivery_date)) . '-' . substr(str_shuffle("0123456789"), 0, 4);
                
                $order_stmt = $pdo->prepare("
                    INSERT INTO orders (id, order_number, subscription_id, user_id, delivery_date, delivery_address, delivery_instructions, total_items, status, kitchen_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'not_started')
                ");
                $order_stmt->execute([
                    $order_id, $order_number, $subscription_id, $data['details']['user_id'],
                    $delivery_date, $data['details']['delivery_address'], $data['details']['delivery_instructions'], count($data['items'])
                ]);

                // เพิ่ม Order Items
                $item_stmt = $pdo->prepare("INSERT INTO order_items (id, order_id, menu_id, menu_name, menu_price, quantity) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($data['items'] as $item) {
                    $item_stmt->execute([generateUUID(), $order_id, $item['menu_id'], $item['menu_name'], $item['base_price'], $item['quantity']]);
                }
                $generated_count++;
            }
        }

        $pdo->commit();
        $message = "ดำเนินการเสร็จสิ้น! สร้างออเดอร์ใหม่ $generated_count รายการ, ข้าม $skipped_count รายการ (เนื่องจากมีอยู่แล้ว)";
        return ['success' => true, 'message' => $message];

    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดร้ายแรง: ' . $e->getMessage()];
    }
}

// --- 4. รันฟังก์ชันและแสดงผล ---
$result = generateOrdersForUpcomingWeekend($pdo);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ผลการสร้างออเดอร์สุดสัปดาห์ - Krua Thai Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --curry: #cf723a; --white: #ffffff; --success: #27ae60; --danger: #e74c3c; }
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f5f2; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin:0; }
        .result-container {
            background: var(--white); padding: 3rem; border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1); text-align: center; max-width: 500px;
        }
        .icon { font-size: 4rem; margin-bottom: 1.5rem; }
        .icon.success { color: var(--success); }
        .icon.error { color: var(--danger); }
        .result-title { font-size: 1.8rem; font-weight: 700; margin-bottom: 1rem; }
        .result-message { font-size: 1.1rem; color: #555; margin-bottom: 2rem; }
        .btn {
            display: inline-block; padding: 0.8rem 2rem; border-radius: 8px;
            background-color: var(--curry); color: white; text-decoration: none;
            font-weight: 600; transition: transform 0.2s;
        }
        .btn:hover { transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="result-container">
        <?php if ($result['success']): ?>
            <div class="icon success"><i class="fas fa-check-circle"></i></div>
            <h1 class="result-title">ดำเนินการเรียบร้อย</h1>
        <?php else: ?>
            <div class="icon error"><i class="fas fa-times-circle"></i></div>
            <h1 class="result-title">เกิดข้อผิดพลาด</h1>
        <?php endif; ?>

        <p class="result-message"><?php echo htmlspecialchars($result['message']); ?></p>
        <a href="orders.php" class="btn">กลับไปที่หน้าจัดการออเดอร์</a>
    </div>
</body>
</html>