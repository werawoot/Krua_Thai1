<?php
// api/subscription_actions.php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'create':
            // รับค่าจากฟอร์มสมัครสมาชิก
            $user_id = $_POST['user_id'] ?? null;
            $plan_id = $_POST['plan_id'] ?? null;
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            $delivery_days = $_POST['delivery_days'] ?? '["monday"]';
            $total_amount = $_POST['total_amount'] ?? 0;
            // validate ข้อมูลเบื้องต้น
            if (!$user_id || !$plan_id) {
                echo json_encode(['success'=>false, 'message'=>'Missing data']);
                exit;
            }
            // สร้าง subscription ใหม่
            $id = uniqid();
            $stmt = $pdo->prepare("INSERT INTO subscriptions (id, user_id, plan_id, status, start_date, billing_cycle, total_amount, delivery_days, created_at) VALUES (?, ?, ?, 'pending_payment', ?, 'weekly', ?, ?, NOW())");
            $ok = $stmt->execute([$id, $user_id, $plan_id, $start_date, $total_amount, $delivery_days]);
            echo json_encode(['success'=>$ok, 'subscription_id'=>$id]);
            exit;
        
        case 'update_status':
            // เปลี่ยนสถานะ (pause/cancel/renew)
            $sub_id = $_POST['sub_id'] ?? null;
            $status = $_POST['status'] ?? null;
            if (!$sub_id || !$status) {
                echo json_encode(['success'=>false, 'message'=>'Missing data']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE subscriptions SET status = ? WHERE id = ?");
            $ok = $stmt->execute([$status, $sub_id]);
            echo json_encode(['success'=>$ok]);
            exit;

        case 'payment_status':
            // อัปเดตสถานะ payment
            $sub_id = $_POST['sub_id'] ?? null;
            $pay_status = $_POST['payment_status'] ?? null;
            if (!$sub_id || !$pay_status) {
                echo json_encode(['success'=>false, 'message'=>'Missing data']);
                exit;
            }
            // หา payment ล่าสุดของ subscription นี้
            $stmt = $pdo->prepare("UPDATE payments SET status=? WHERE subscription_id=? ORDER BY payment_date DESC LIMIT 1");
            $ok = $stmt->execute([$pay_status, $sub_id]);
            echo json_encode(['success'=>$ok]);
            exit;

        default:
            echo json_encode(['success'=>false, 'message'=>'Unknown action']);
            exit;
    }
}

// กรณี GET: ดึงข้อมูล subscription (optional, ถ้าอยากใช้)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? null;
    $subs = [];
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success'=>true, 'subscriptions'=>$subs]);
    exit;
}

// หากไม่ได้ระบุ action
echo json_encode(['success'=>false, 'message'=>'Invalid request']);
exit;
