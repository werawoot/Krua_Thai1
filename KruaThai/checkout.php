<?php
// Krua Thai - Checkout (Simple Weekend Version - Based on Working Code)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check login status
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection (PDO)
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

// Helper function to get plan name with fallback
function getPlanName($plan) {
    if (isset($plan['name_english']) && !empty($plan['name_english'])) {
        return $plan['name_english'];
    } elseif (isset($plan['name_thai']) && !empty($plan['name_thai'])) {
        return $plan['name_thai'];
    } else {
        return 'Selected Package';
    }
}

// Helper function to get menu name with fallback
function getMenuName($menu) {
    if (isset($menu['name']) && !empty($menu['name'])) {
        return $menu['name'];
    } elseif (isset($menu['name_thai']) && !empty($menu['name_thai'])) {
        return $menu['name_thai'];
    } else {
        return 'Menu Item';
    }
}

// Generate weekend dates (Saturday & Sunday only) - SIMPLE VERSION
function getWeekendDates() {
    $dates = [];
    $currentDate = new DateTime();
    $currentDate->setTimezone(new DateTimeZone('Asia/Bangkok'));
    
    // Find next 4 weekends (8 days total)
    for ($week = 0; $week < 4; $week++) {
        // Calculate this week's Saturday
        $saturday = clone $currentDate;
        $saturday->modify('this week monday')->modify('+' . (5 + $week * 7) . ' days');
        
        // Calculate this week's Sunday  
        $sunday = clone $saturday;
        $sunday->modify('+1 day');
        
        // Only include future dates
        $today = new DateTime();
        $today->setTimezone(new DateTimeZone('Asia/Bangkok'));
        
        if ($saturday >= $today) {
            $dates['sat_' . $week] = $saturday->format('l, M j') . ' (Saturday)';
        }
        
        if ($sunday >= $today) {
            $dates['sun_' . $week] = $sunday->format('l, M j') . ' (Sunday)';
        }
    }
    
    return $dates;
}

// ---------------------------------------------

// 1. Get selection data from SESSION
$order = $_SESSION['checkout_data'] ?? null;
if (!$order || empty($order['plan']) || empty($order['selected_meals'])) {
    $_SESSION['flash_message'] = "Please select a package and meals first";
    $_SESSION['flash_type'] = 'error';
    header('Location: subscribe.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$plan = $order['plan'];
$selected_meals = $order['selected_meals'] ?? [];
$meal_details = $order['meal_details'] ?? [];

// FIXED: If meal_details is empty, fetch from database
if (empty($meal_details) && !empty($selected_meals)) {
    try {
        $placeholders = str_repeat('?,', count($selected_meals) - 1) . '?';
        $stmt = $db->prepare("SELECT id, name, name_thai, base_price FROM menus WHERE id IN ($placeholders)");
        $stmt->execute($selected_meals);
        $fetched_meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to associative array by ID
        foreach ($fetched_meals as $meal) {
            $meal_details[$meal['id']] = $meal;
        }
    } catch (Exception $e) {
        error_log("Error fetching meal details: " . $e->getMessage());
    }
}

$total_price = $plan['final_price'];
$success = false;
$errors = [];
$flash_message = '';

// Get weekend dates
$weekend_dates = getWeekendDates();

// 2. Process Form Submission - KEEP EXACTLY SAME AS WORKING VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $delivery_days = $_POST['delivery_days'] ?? [];
    $preferred_time = $_POST['preferred_time'] ?? 'afternoon';
    $delivery_instructions = trim($_POST['delivery_instructions'] ?? '');

    // Basic validation - SAME AS WORKING VERSION
    if (empty($delivery_address)) $errors[] = "Please enter delivery address";
    if (empty($city)) $errors[] = "Please enter city/state";
    if (empty($zip_code)) $errors[] = "Please enter ZIP code";
    if (empty($payment_method)) $errors[] = "Please select payment method";
    if (empty($delivery_days)) $errors[] = "Please select at least 1 delivery day";

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
            ) VALUES (?, ?, ?, ?, ?, ?, 'USD', ?, 'completed', NOW(), ?, ?, ?, NOW(), NOW())");
            $description = "Subscription " . getPlanName($plan);
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
            $_SESSION['flash_message'] = "Order placed successfully! Thank you for choosing Krua Thai";
            $_SESSION['flash_type'] = 'success';
            header("Location: subscription-status.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get user profile for form
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Order | Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            font-family: 'Inter', sans-serif;
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

        /* Progress Bar - FIXED: One line layout */
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
            justify-content: space-between;
            gap: 1rem;
            max-width: 800px;
            margin: 0 auto;
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
            flex: 1;
            justify-content: center;
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
            flex-shrink: 0;
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

        /* Weekend delivery - SAME STYLE AS WORKING VERSION */
        .delivery-day-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem;
            background: var(--cream);
            border-radius: var(--radius-md);
            cursor: pointer;
            border: 2px solid transparent;
            font-weight: 600;
            transition: var(--transition);
        }

        .delivery-day-checkbox:hover {
            border-color: var(--curry);
            background: var(--white);
        }

        .delivery-day-checkbox input:checked {
            accent-color: var(--curry);
        }

        .delivery-days-container {
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
        }

        .weekend-info {
            background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
            border: 1px solid var(--sage);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-size: 0.9rem;
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
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .progress-step {
                font-size: 0.8rem;
                padding: 0.6rem 1rem;
                flex: none;
                min-width: 120px;
            }

            .progress-arrow {
                font-size: 1rem;
            }

            .delivery-days-container {
                flex-direction: column;
            }

            .delivery-day-checkbox {
                justify-content: flex-start;
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
                min-width: 100px;
            }

            .meal-list li {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
                <a href="menu.php" class="nav-link">Menu</a>
                <a href="about.php" class="nav-link">About Us</a>
                <a href="contact.php" class="nav-link">Contact</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                    <a href="logout.php" class="nav-link">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Progress Bar - FIXED: One line layout -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Choose Package</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Select Menu</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step active">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-check-double"></i>
                    <span>Complete</span>
                </div>
            </div>
        </div>

        <div class="title"><i class="fas fa-wallet"></i> Review and Pay</div>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <ul>
                    <?php foreach ($errors as $err) echo "<li>" . htmlspecialchars($err) . "</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Plan Summary -->
        <div class="section plan-summary">
            <div class="label"><i class="fas fa-box"></i> Selected Package</div>
            <div class="plan-title"><?php echo htmlspecialchars(getPlanName($plan)); ?> (<?php echo $plan['meals_per_week']; ?> meals per week)</div>
            <div class="plan-price">$<?php echo number_format($plan['final_price']/100, 2); ?> /week</div>
        </div>

        <!-- Meals Summary -->
        <div class="section meals-summary">
            <div class="label"><i class="fas fa-utensils"></i> Selected Meals</div>
            <ul class="meal-list">
                <?php foreach ($selected_meals as $meal_id): ?>
                    <?php $meal = $meal_details[$meal_id] ?? null; if (!$meal) continue; ?>
                    <li>
                        <span><?php echo htmlspecialchars(getMenuName($meal)); ?></span>
                        <span style="color: var(--curry); font-weight: 600;">($<?php echo number_format($meal['base_price']/100, 2); ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Address/Shipping + Payment -->
        <form method="POST">
            <div class="section">
                <div class="label"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
                <input type="text" class="address-input" name="delivery_address" required
                       value="<?php echo htmlspecialchars($user['delivery_address'] ?? ''); ?>" placeholder="Enter your address">
                <input type="text" class="address-input" name="city" required
                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="City, State">
                <input type="text" class="address-input" name="zip_code" required maxlength="5" pattern="[0-9]{5}"
                       value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>" placeholder="ZIP Code">
                <textarea name="delivery_instructions" class="address-input" rows="2" placeholder="Special delivery instructions (optional)"><?php echo htmlspecialchars($user['delivery_instructions'] ?? ''); ?></textarea>
            </div>

            <div class="section">
                <div class="label"><i class="fas fa-calendar-weekend"></i> Select Weekend Delivery Days</div>
                
                <div class="weekend-info">
                    <i class="fas fa-info-circle" style="color: var(--sage); margin-right: 0.5rem;"></i>
                    <strong>Weekend Delivery Only:</strong> We deliver on Saturdays and Sundays only. Choose from the next 4 weekends.
                </div>
                
                <div class="delivery-days-container">
                    <?php foreach($weekend_dates as $val => $label): ?>
                        <label class="delivery-day-checkbox">
                            <input type="checkbox" name="delivery_days[]" value="<?php echo $val; ?>">
                            <?php echo $label; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="label" style="margin-top:1rem;"><i class="fas fa-clock"></i> Preferred Delivery Time</div>
                <select name="preferred_time" class="address-input" required>
                    <option value="morning">Morning (8:00 AM - 12:00 PM)</option>
                    <option value="afternoon" selected>Afternoon (12:00 PM - 4:00 PM)</option>
                    <option value="evening">Evening (4:00 PM - 8:00 PM)</option>
                    <option value="flexible">Flexible (8:00 AM - 8:00 PM)</option>
                </select>
            </div>

            <!-- Payment Method -->
            <div class="section">
                <div class="label"><i class="fas fa-credit-card"></i> Choose Payment Method</div>
                <div class="payment-methods">
                    <label>
                        <input type="radio" name="payment_method" value="credit" required> <i class="fas fa-credit-card"></i> Credit/Debit Card
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
                    <label>
                        <input type="radio" name="payment_method" value="promptpay"> <i class="fas fa-university"></i> Bank Transfer
                    </label>
                </div>
                <div class="total">Total: $<?php echo number_format($plan['final_price']/100, 2); ?></div>
                <button class="btn" type="submit" name="submit_order"><i class="fas fa-lock"></i> Confirm and Pay</button>
            </div>
        </form>
    </div>

    <!-- NO COMPLEX JAVASCRIPT - Keep it simple like working version -->
</body>
</html>