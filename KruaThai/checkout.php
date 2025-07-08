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
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: var(--white);
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: inherit;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--curry);
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
        }

        .nav-link:hover {
            background: var(--cream);
            color: var(--curry);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 20px 4rem;
        }

        /* Progress Bar */
        .progress-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-soft);
        }

        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius-xl);
            font-weight: 600;
            font-size: 0.95rem;
            background: var(--cream);
            color: var(--text-gray);
            border: 2px solid var(--cream);
            transition: var(--transition);
            white-space: nowrap;
        }

        .progress-step.active {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }

        .progress-step.completed {
            background: var(--success);
            color: var(--white);
            border-color: var(--success);
        }

        .progress-arrow {
            color: var(--sage);
            font-size: 1.2rem;
            font-weight: 600;
        }

        .title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            color: var(--text-dark);
        }

        .title i {
            color: var(--curry);
            margin-right: 0.5rem;
        }

        .section {
            background: var(--white);
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-soft);
            padding: 2rem;
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }

        .section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--curry), var(--brown), var(--sage));
        }

        .label {
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .plan-title {
            font-size: 1.2rem;
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .plan-price {
            color: var(--curry);
            font-size: 1.4rem;
            font-weight: 700;
        }

        .meal-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .meal-list li {
            border-bottom: 1px solid var(--border-light);
            padding: 1rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .meal-list li:hover {
            background: var(--cream);
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: var(--radius-md);
        }

        .meal-list li:last-child {
            border-bottom: none;
        }

        .total {
            font-size: 1.5rem;
            color: var(--curry);
            font-weight: 700;
            margin: 2rem 0;
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--cream), #f5f3f0);
            border-radius: var(--radius-lg);
        }

        .address-input, .input {
            width: 100%;
            padding: 1rem 1.2rem;
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-light);
            margin-bottom: 1.2rem;
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
            background: var(--white);
        }

        .input:focus, .address-input:focus {
            border-color: var(--curry);
            outline: none;
            box-shadow: 0 0 15px rgba(207, 114, 58, 0.2);
        }

        .btn {
            width: 100%;
            padding: 1.2rem 2rem;
            border-radius: var(--radius-xl);
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }

        .btn:hover {
            background: linear-gradient(135deg, var(--brown), var(--sage));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(207, 114, 58, 0.4);
        }

        .payment-methods label {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.1rem;
            cursor: pointer;
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            transition: var(--transition);
            background: var(--white);
            font-weight: 600;
        }

        .payment-methods label:hover {
            border-color: var(--sage);
            background: var(--cream);
        }

        .payment-methods input:checked + i {
            color: var(--curry);
        }

        .payment-methods input {
            accent-color: var(--curry);
        }

        .payment-methods i {
            font-size: 1.3rem;
            color: var(--curry);
        }

        .error {
            background: linear-gradient(135deg, #ffebee, #fce4ec);
            color: var(--danger);
            border: 2px solid #ffcdd2;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .error ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .error li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .error li:before {
            content: "⚠️";
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem 15px 3rem;
            }

            .section {
                padding: 1.5rem;
            }

            .progress-bar {
                gap: 0.5rem;
            }

            .progress-step {
                font-size: 0.8rem;
                padding: 0.6rem 1rem;
            }

            .progress-arrow {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .header-container {
                padding: 1rem;
            }

            .logo-text {
                font-size: 1.5rem;
            }

            .title {
                font-size: 1.8rem;
            }

            .progress-step {
                font-size: 0.7rem;
                padding: 0.5rem 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-text">Krua Thai</div>
            </a>
            <nav class="header-nav">
                <a href="menu.php" class="nav-link">เมนู</a>
                <a href="about.php" class="nav-link">เกี่ยวกับเรา</a>
                <a href="contact.php" class="nav-link">ติดต่อ</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="nav-link">แดชบอร์ด</a>
                    <a href="logout.php" class="nav-link">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">เข้าสู่ระบบ</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>เลือกแพ็กเกจ</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>เลือกเมนู</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step active">
                    <i class="fas fa-credit-card"></i>
                    <span>ชำระเงิน</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-check-double"></i>
                    <span>เสร็จสิ้น</span>
                </div>
            </div>
        </div>

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
            <div class="label"><i class="fas fa-box"></i> แพ็กเกจที่เลือก</div>
            <div class="plan-title"><?php echo htmlspecialchars($plan['name_thai'] ?? $plan['name']); ?> (<?php echo $plan['meals_per_week']; ?> มื้อต่อสัปดาห์)</div>
            <div class="plan-price">฿<?php echo number_format($plan['final_price'], 0); ?> /สัปดาห์</div>
        </div>

        <!-- Meals Summary -->
        <div class="section meals-summary">
            <div class="label"><i class="fas fa-utensils"></i> เมนูที่เลือก</div>
            <ul class="meal-list">
                <?php foreach ($selected_meals as $meal_id): ?>
                    <?php $meal = $meal_details[$meal_id] ?? null; if (!$meal) continue; ?>
                    <li>
                        <span><?php echo htmlspecialchars($meal['name_thai'] ?? $meal['name']); ?></span>
                        <span style="color: var(--curry); font-weight: 600;">(฿<?php echo number_format($meal['base_price'],0); ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Address/Shipping + Payment -->
        <form method="POST">
            <div class="section">
                <div class="label"><i class="fas fa-map-marker-alt"></i> ที่อยู่จัดส่ง</div>
                <input type="text" class="address-input" name="delivery_address" required
                       value="<?php echo htmlspecialchars($user['delivery_address'] ?? ''); ?>" placeholder="กรอกที่อยู่/โลเคชัน">
                <input type="text" class="address-input" name="city" required
                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="จังหวัด/เมือง">
                <input type="text" class="address-input" name="zip_code" required maxlength="5" pattern="[0-9]{5}"
                       value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>" placeholder="รหัสไปรษณีย์">
                <textarea name="delivery_instructions" class="address-input" rows="2" placeholder="คำแนะนำเพิ่มเติม (ถ้ามี)"><?php echo htmlspecialchars($user['delivery_instructions'] ?? ''); ?></textarea>
            </div>

            <div class="section">
                <div class="label"><i class="fas fa-calendar-alt"></i> เลือกวันจัดส่ง (เลือกได้หลายวัน)</div>
                <div style="display: flex; gap: 0.7rem; flex-wrap: wrap;">
                    <?php
                    $days = ['monday'=>'จันทร์','tuesday'=>'อังคาร','wednesday'=>'พุธ','thursday'=>'พฤหัสบดี','friday'=>'ศุกร์','saturday'=>'เสาร์','sunday'=>'อาทิตย์'];
                    foreach($days as $val=>$label): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.8rem; background: var(--cream); border-radius: var(--radius-md); cursor: pointer; border: 2px solid transparent; font-weight: 600;">
                            <input type="checkbox" name="delivery_days[]" value="<?php echo $val; ?>" <?php if(isset($order['delivery_days']) && in_array($val,$order['delivery_days'])) echo 'checked'; ?> style="accent-color: var(--curry);">
                            <?php echo $label; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="label" style="margin-top:1rem;"><i class="fas fa-clock"></i> ช่วงเวลาที่สะดวก</div>
                <select name="preferred_time" class="address-input" required>
                    <option value="morning">เช้า (8:00-12:00)</option>
                    <option value="afternoon" selected>บ่าย (12:00-16:00)</option>
                    <option value="evening">เย็น (16:00-20:00)</option>
                    <option value="flexible">ยืดหยุ่น (8:00-20:00)</option>
                </select>
            </div>

            <!-- Payment Method -->
            <div class="section">
                <div class="label"><i class="fas fa-credit-card"></i> เลือกวิธีชำระเงิน</div>
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
                <button class="btn" type="submit" name="submit_order"><i class="fas fa-lock"></i> ยืนยันและชำระเงิน</button>
            </div>
        </form>
    </div>
</body>
</html>