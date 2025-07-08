<?php
// Krua Thai - Checkout
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// เช็คการ login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// เชื่อมต่อฐานข้อมูล (PDO)
require_once 'config/database.php';
$db = (new Database())->getConnection();

// --------- Utility: UUIDv4 Generator ---------
function uuidv4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
// ---------------------------------------------

// 1. รับข้อมูลการเลือกจาก SESSION
$order = $_SESSION['checkout_data'] ?? null;
if (!$order || empty($order['plan']) || empty($order['selected_meals'])) {
    $_SESSION['flash_message'] = "กรุณาเลือกแพ็กเกจและเมนูก่อน";
    $_SESSION['flash_type'] = 'error';
    header('Location: subscribe.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$plan = $order['plan'];
$selected_meals = $order['selected_meals'] ?? [];
$meal_details = $order['meal_details'] ?? [];
$total_price = $plan['final_price'];
$success = false;
$errors = [];
$flash_message = '';

// 2. Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $delivery_days = $_POST['delivery_days'] ?? [];
    $preferred_time = $_POST['preferred_time'] ?? 'afternoon';
    $delivery_instructions = trim($_POST['delivery_instructions'] ?? '');

    // Basic validation
    if (empty($delivery_address)) $errors[] = "กรุณากรอกที่อยู่จัดส่ง";
    if (empty($city)) $errors[] = "กรุณากรอกจังหวัด/เมือง";
    if (empty($zip_code)) $errors[] = "กรุณากรอกรหัสไปรษณีย์";
    if (empty($payment_method)) $errors[] = "กรุณาเลือกวิธีการชำระเงิน";
    if (empty($delivery_days)) $errors[] = "กรุณาเลือกวันส่งอย่างน้อย 1 วัน";

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            $subscription_id = uuidv4();
            $payment_id = uuidv4();
            $transaction_id = 'TXN-' . date('Ymd-His') . '-' . substr($subscription_id, 0, 6);

            $start_date = date('Y-m-d', strtotime('+1 day'));
            $billing_cycle = $plan['plan_type'] === 'monthly' ? 'monthly' : 'weekly';
            $next_billing_date = $billing_cycle === 'monthly'
                ? date('Y-m-d', strtotime('+1 month', strtotime($start_date)))
                : date('Y-m-d', strtotime('+1 week', strtotime($start_date)));

            // 3. Insert subscription
            $stmt = $db->prepare("INSERT INTO subscriptions (
                id, user_id, plan_id, status, start_date, next_billing_date,
                billing_cycle, total_amount, delivery_days, preferred_delivery_time,
                special_instructions, auto_renew, created_at, updated_at
            ) VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
            $stmt->execute([
                $subscription_id,
                $user_id,
                $plan['id'],
                $start_date,
                $next_billing_date,
                $billing_cycle,
                $total_price,
                json_encode($delivery_days),
                $preferred_time,
                $delivery_instructions
            ]);

            // 4. Insert payment
            $payment_map = [
                'credit' => 'credit_card',
                'promptpay' => 'bank_transfer',
                'paypal' => 'paypal',
                'apple_pay' => 'apple_pay',
                'google_pay' => 'google_pay'
            ];
            $db_payment_method = $payment_map[$payment_method] ?? 'credit_card';

            $stmt = $db->prepare("INSERT INTO payments (
                id, subscription_id, user_id, payment_method, transaction_id,
                amount, currency, net_amount, status, payment_date,
                billing_period_start, billing_period_end, description, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'THB', ?, 'completed', NOW(), ?, ?, ?, NOW(), NOW())");
            $description = "Subscription " . ($plan['name_thai'] ?? $plan['name']);
            $billing_end = $next_billing_date;
            $stmt->execute([
                $payment_id,
                $subscription_id,
                $user_id,
                $db_payment_method,
                $transaction_id,
                $total_price,
                $total_price,
                $start_date,
                $billing_end,
                $description
            ]);

            // 5. Add selected menus
            if (!empty($selected_meals)) {
                $stmt_menu = $db->prepare("INSERT INTO subscription_menus
                    (id, subscription_id, menu_id, delivery_date, quantity, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 1, 'scheduled', NOW(), NOW())");
                foreach ($selected_meals as $meal_id) {
                    $menu_uuid = uuidv4();
                    $stmt_menu->execute([$menu_uuid, $subscription_id, $meal_id, $start_date]);
                }
            }

            // 6. Update user address (optionally)
            $stmt = $db->prepare("UPDATE users SET delivery_address=?, city=?, zip_code=?, delivery_instructions=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$delivery_address, $city, $zip_code, $delivery_instructions, $user_id]);

            $db->commit();
            $success = true;
            unset($_SESSION['checkout_data']);
            $_SESSION['flash_message'] = "สั่งซื้อสำเร็จ! ขอบคุณที่ใช้บริการ Krua Thai";
            $_SESSION['flash_type'] = 'success';
            header("Location: subscription-status.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ดึง user profile สำหรับฟอร์ม
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ยืนยันสั่งซื้อ | Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #ece8e1; color: #2c3e50; }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .title { font-size: 2.2rem; font-weight: 700; margin-bottom: 1.2rem; }
        .section { background: #fff; border-radius: 18px; margin-bottom: 2rem; box-shadow: 0 4px 16px rgba(207,114,58,0.08); padding: 2rem; }
        .label { font-weight: 600; color: #cf723a; margin-bottom: 0.5rem; }
        .plan-title { font-size: 1.1rem; color: #2c3e50; }
        .plan-price { color: #cf723a; font-size: 1.4rem; font-weight: 700; }
        .meal-list { list-style: none; margin: 0; padding: 0; }
        .meal-list li { border-bottom: 1px solid #ece8e1; padding: 0.7rem 0; }
        .total { font-size: 1.3rem; color: #cf723a; font-weight: 700; margin-top: 1rem; }
        .address-input, .input { width: 100%; padding: 0.9rem 1rem; border-radius: 12px; border: 2px solid #e8e8e8; margin-bottom: 1.2rem; }
        .input:focus { border-color: #cf723a; outline: none; }
        .btn { padding: 1rem 2.2rem; border-radius: 40px; background: #cf723a; color: #fff; font-size: 1.1rem; font-weight: 700; border: none; cursor: pointer; transition: background 0.2s;}
        .btn:hover { background: #bd9379; }
        .payment-methods label { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 1.1rem; cursor: pointer; }
        .payment-methods input { accent-color: #cf723a; }
        @media (max-width: 700px) { .section { padding: 1rem; } }
        .error { background: #fbeee6; color: #d8000c; border: 1px solid #ffd2b5; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="title"><i class="fas fa-wallet"></i> สรุปและชำระเงิน</div>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $err) echo "<li>" . htmlspecialchars($err) . "</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Plan Summary -->
    <div class="section plan-summary">
        <div class="label">แพ็กเกจที่เลือก</div>
        <div class="plan-title"><?php echo htmlspecialchars($plan['name_thai'] ?? $plan['name']); ?> (<?php echo $plan['meals_per_week']; ?> มื้อต่อสัปดาห์)</div>
        <div class="plan-price">฿<?php echo number_format($plan['final_price'], 0); ?> /สัปดาห์</div>
    </div>

    <!-- Meals Summary -->
    <div class="section meals-summary">
        <div class="label">เมนูที่เลือก</div>
        <ul class="meal-list">
            <?php foreach ($selected_meals as $meal_id): ?>
                <?php $meal = $meal_details[$meal_id] ?? null; if (!$meal) continue; ?>
                <li>
                    <?php echo htmlspecialchars($meal['name_thai'] ?? $meal['name']); ?>
                    <span style="color:#7f8c8d; font-size: 0.95em;">(<?php echo number_format($meal['base_price'],0); ?>฿)</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Address/Shipping + Payment -->
    <form method="POST">
        <div class="section">
            <div class="label">ที่อยู่จัดส่ง</div>
            <input type="text" class="address-input" name="delivery_address" required
                   value="<?php echo htmlspecialchars($user['delivery_address'] ?? ''); ?>" placeholder="กรอกที่อยู่/โลเคชัน">
            <input type="text" class="address-input" name="city" required
                   value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="จังหวัด/เมือง">
            <input type="text" class="address-input" name="zip_code" required maxlength="5" pattern="[0-9]{5}"
                   value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>" placeholder="รหัสไปรษณีย์">
            <textarea name="delivery_instructions" class="address-input" rows="2" placeholder="คำแนะนำเพิ่มเติม (ถ้ามี)"><?php echo htmlspecialchars($user['delivery_instructions'] ?? ''); ?></textarea>
        </div>

        <div class="section">
            <div class="label">เลือกวันจัดส่ง (เลือกได้หลายวัน)</div>
            <div style="display: flex; gap: 0.7rem; flex-wrap: wrap;">
                <?php
                $days = ['monday'=>'จันทร์','tuesday'=>'อังคาร','wednesday'=>'พุธ','thursday'=>'พฤหัสบดี','friday'=>'ศุกร์','saturday'=>'เสาร์','sunday'=>'อาทิตย์'];
                foreach($days as $val=>$label): ?>
                    <label>
                        <input type="checkbox" name="delivery_days[]" value="<?php echo $val; ?>" <?php if(isset($order['delivery_days']) && in_array($val,$order['delivery_days'])) echo 'checked'; ?>>
                        <?php echo $label; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="label" style="margin-top:1rem;">ช่วงเวลาที่สะดวก</div>
            <select name="preferred_time" class="address-input" required>
                <option value="morning">เช้า (8:00-12:00)</option>
                <option value="afternoon" selected>บ่าย (12:00-16:00)</option>
                <option value="evening">เย็น (16:00-20:00)</option>
                <option value="flexible">ยืดหยุ่น (8:00-20:00)</option>
            </select>
        </div>

        <!-- Payment Method -->
        <div class="section">
            <div class="label">เลือกวิธีชำระเงิน</div>
            <div class="payment-methods">
                <label>
                    <input type="radio" name="payment_method" value="credit" required> <i class="fas fa-credit-card"></i> บัตรเครดิต/เดบิต
                </label>
                <label>
                    <input type="radio" name="payment_method" value="promptpay"> <i class="fas fa-qrcode"></i> PromptPay
                </label>
                <label>
                    <input type="radio" name="payment_method" value="paypal"> <i class="fab fa-paypal"></i> PayPal
                </label>
                <label>
                    <input type="radio" name="payment_method" value="apple_pay"> <i class="fab fa-apple-pay"></i> Apple Pay
                </label>
                <label>
                    <input type="radio" name="payment_method" value="google_pay"> <i class="fab fa-google-pay"></i> Google Pay
                </label>
            </div>
            <div class="total">ยอดรวม: ฿<?php echo number_format($plan['final_price'], 0); ?></div>
            <button class="btn" type="submit" name="submit_order">ยืนยันและชำระเงิน</button>
        </div>
    </form>
</div>
</body>
</html>
