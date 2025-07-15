<?php
/**
 * Krua Thai - Improved Checkout System
 * Updated for single date selection and better UI
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables with defaults
$order = null;
$plan = null;
$selected_meals = [];
$meal_details = [];
$errors = [];
$success = false;
$weekend_dates = [];
$user = null;

// Early session and authentication checks
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Utility Functions
class CheckoutUtils {
    
    public static function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public static function generateOrderNumber() {
        return 'TXN-' . date('Ymd-His') . '-' . substr(self::generateUUID(), 0, 6);
    }
    
    public static function getPlanName($plan) {
        return $plan['name_thai'] ?? $plan['name'] ?? 'Selected Package';
    }
    
    public static function getMenuName($menu) {
        return $menu['name_thai'] ?? $menu['name'] ?? 'Menu Item';
    }
    
    public static function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
}

// Database Connection Handler
class DatabaseConnection {
    private static $connection = null;
    
    public static function getInstance() {
        if (self::$connection === null) {
            try {
                require_once 'config/database.php';
                self::$connection = (new Database())->getConnection();
            } catch (Exception $e) {
                // Fallback connections
                $configs = [
                    ["mysql:host=localhost;dbname=krua_thai;charset=utf8mb4", "root", "root"],
                    ["mysql:host=localhost:8889;dbname=krua_thai;charset=utf8mb4", "root", "root"]
                ];
                
                foreach ($configs as $config) {
                    try {
                        self::$connection = new PDO($config[0], $config[1], $config[2]);
                        self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        break;
                    } catch (PDOException $e) {
                        continue;
                    }
                }
                
                if (self::$connection === null) {
                    throw new Exception("❌ Database connection failed: " . $e->getMessage());
                }
            }
        }
        return self::$connection;
    }
}

// Weekend Date Generator (Updated for single selection)
class WeekendDateGenerator {
    
    public static function getWeekendDates() {
        $dates = [];
        $currentDate = new DateTime();
        $currentDate->setTimezone(new DateTimeZone('Asia/Bangkok'));
        
        for ($i = 0; $i < 28; $i++) { 
            $checkDate = clone $currentDate;
            $checkDate->modify("+{$i} days");
            
            $dayOfWeek = $checkDate->format('N'); 
            
            if ($dayOfWeek == 6 || $dayOfWeek == 7) { 
                $dayName = ($dayOfWeek == 6) ? 'Saturday' : 'Sunday';
                $key = strtolower(substr($dayName, 0, 3)) . '_' . floor(count($dates) / 2);
                $dates[$key] = [
                    'label' => $checkDate->format('l, M j'),
                    'day' => $dayName,
                    'date' => $checkDate->format('Y-m-d'),
                    'formatted' => $checkDate->format('d/m/Y')
                ];
                
                if (count($dates) >= 8) break; 
            }
        }
        
        return $dates;
    }
    
    public static function convertWeekendKeyToDate($weekendKey) {
        $weekendDates = self::getWeekendDates();
        return isset($weekendDates[$weekendKey]) ? $weekendDates[$weekendKey]['date'] : null;
    }
}

// Checkout Data Manager
class CheckoutDataManager {
    
    public static function createDemoCheckoutData($db) {
        try {
            $stmt = $db->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1");
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($plan) {
                $meal_limit = $plan['meals_per_week'] ?? 3;
                $stmt = $db->query("SELECT id, name, name_thai, base_price FROM menus WHERE is_available = 1 ORDER BY RAND() LIMIT $meal_limit");
                $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($menus)) {
                    return [
                        'plan' => $plan,
                        'selected_meals' => array_column($menus, 'id'),
                        'meal_details' => array_combine(array_column($menus, 'id'), $menus)
                    ];
                }
            }
        } catch (Exception $e) {
            throw new Exception("Error loading demo data: " . $e->getMessage());
        }
        
        return null;
    }
    
    public static function validateCheckoutData($order) {
        $errors = [];
        
        if (!$order || empty($order['plan']) || empty($order['selected_meals'])) {
            $errors[] = "Invalid checkout data";
        }
        
        if (!isset($order['plan']['id']) || !isset($order['plan']['final_price'])) {
            $errors[] = "Invalid plan data";
        }
        
        if (empty($order['selected_meals']) || !is_array($order['selected_meals'])) {
            $errors[] = "No meals selected";
        }
        
        return $errors;
    }
}

// Order Processing Engine (Updated for single delivery day)
class OrderProcessor {
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function validateFormInput($postData) {
        $errors = [];
        
        $required_fields = [
            'delivery_address' => 'Delivery address',
            'city' => 'City/State',
            'zip_code' => 'ZIP code',
            'payment_method' => 'Payment method'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty(trim($postData[$field] ?? ''))) {
                $errors[] = "Please enter {$label}";
            }
        }
        
        // Updated validation for single delivery day
        if (empty($postData['delivery_day'])) {
            $errors[] = "Please select a delivery day";
        }
        
        // ZIP code validation
        $zip_code = trim($postData['zip_code'] ?? '');
        if (!preg_match('/^\d{5}$/', $zip_code)) {
            $errors[] = "ZIP code must be 5 digits";
        }
        
        return $errors;
    }
    
    public function createSubscription($data) {
        $subscription_id = CheckoutUtils::generateUUID();
        
        $stmt = $this->db->prepare("INSERT INTO subscriptions (
            id, user_id, plan_id, status, start_date, next_billing_date,
            billing_cycle, total_amount, delivery_days, preferred_delivery_time,
            special_instructions, auto_renew, created_at, updated_at
        ) VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
        
        $result = $stmt->execute([
            $subscription_id,
            $data['user_id'],
            $data['plan_id'],
            $data['start_date'],
            $data['next_billing_date'],
            $data['billing_cycle'],
            $data['total_amount'],
            $data['delivery_days'],
            $data['preferred_time'],
            $data['delivery_instructions']
        ]);
        
        if (!$result) {
            throw new Exception("Failed to create subscription");
        }
        
        return $subscription_id;
    }
    
    public function createPayment($data) {
        $payment_id = CheckoutUtils::generateUUID();
        $transaction_id = CheckoutUtils::generateOrderNumber();
        
        $payment_map = [
            'credit' => 'credit_card',
            'promptpay' => 'bank_transfer',
            'paypal' => 'paypal',
            'apple_pay' => 'apple_pay',
            'google_pay' => 'google_pay'
        ];
        
        $db_payment_method = $payment_map[$data['payment_method']] ?? 'credit_card';
        
        $stmt = $this->db->prepare("INSERT INTO payments (
            id, subscription_id, user_id, payment_method, transaction_id,
            amount, currency, net_amount, status, payment_date,
            billing_period_start, billing_period_end, description, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'USD', ?, 'completed', NOW(), ?, ?, ?, NOW(), NOW())");
        
        $result = $stmt->execute([
            $payment_id,
            $data['subscription_id'],
            $data['user_id'],
            $db_payment_method,
            $transaction_id,
            $data['amount'],
            $data['amount'],
            $data['start_date'],
            $data['next_billing_date'],
            $data['description']
        ]);
        
        if (!$result) {
            throw new Exception("Failed to create payment");
        }
        
        return ['payment_id' => $payment_id, 'transaction_id' => $transaction_id];
    }
    
    public function createSubscriptionMenus($subscription_id, $selected_meals, $delivery_date) {
        $stmt = $this->db->prepare("INSERT INTO subscription_menus
            (id, subscription_id, menu_id, delivery_date, quantity, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, 'scheduled', NOW(), NOW())");
        
        foreach ($selected_meals as $meal_id) {
            $menu_uuid = CheckoutUtils::generateUUID();
            $result = $stmt->execute([$menu_uuid, $subscription_id, $meal_id, $delivery_date]);
            
            if (!$result) {
                throw new Exception("Failed to create subscription menu");
            }
        }
    }
    
    public function updateUserProfile($user_id, $data) {
        $stmt = $this->db->prepare("UPDATE users 
            SET delivery_address=?, city=?, zip_code=?, delivery_instructions=?, updated_at=NOW() 
            WHERE id=?");
        
        return $stmt->execute([
            $data['delivery_address'],
            $data['city'],
            $data['zip_code'],
            $data['delivery_instructions'],
            $user_id
        ]);
    }
    
    public function processFullOrder($user_id, $order, $postData) {
        $this->db->beginTransaction();
        
        try {
            $plan = $order['plan'];
            $selected_meals = $order['selected_meals'];
            $delivery_day = $postData['delivery_day']; // Single delivery day
            
            // Calculate dates
            $start_date = WeekendDateGenerator::convertWeekendKeyToDate($delivery_day);
            
            if (!$start_date) {
                $start_date = date('Y-m-d', strtotime('+1 day'));
            }
            
            $billing_cycle = ($plan['plan_type'] ?? 'weekly') === 'monthly' ? 'monthly' : 'weekly';
            $next_billing_date = $billing_cycle === 'monthly'
                ? date('Y-m-d', strtotime('+1 month', strtotime($start_date)))
                : date('Y-m-d', strtotime('+1 week', strtotime($start_date)));
            
            // Create subscription
            $subscription_data = [
                'user_id' => $user_id,
                'plan_id' => $plan['id'],
                'start_date' => $start_date,
                'next_billing_date' => $next_billing_date,
                'billing_cycle' => $billing_cycle,
                'total_amount' => $plan['final_price'],
                'delivery_days' => json_encode([$delivery_day]), // Array with single day
                'preferred_time' => $postData['preferred_time'] ?? 'afternoon',
                'delivery_instructions' => CheckoutUtils::sanitizeInput($postData['delivery_instructions'] ?? '')
            ];
            
            $subscription_id = $this->createSubscription($subscription_data);
            
            // Create payment
            $payment_data = [
                'subscription_id' => $subscription_id,
                'user_id' => $user_id,
                'payment_method' => $postData['payment_method'],
                'amount' => $plan['final_price'],
                'start_date' => $start_date,
                'next_billing_date' => $next_billing_date,
                'description' => "Subscription " . CheckoutUtils::getPlanName($plan)
            ];
            
            $payment_result = $this->createPayment($payment_data);
            
            // Create subscription menus for single delivery date
            $this->createSubscriptionMenus($subscription_id, $selected_meals, $start_date);
            
            // Update user profile
            $user_data = [
                'delivery_address' => CheckoutUtils::sanitizeInput($postData['delivery_address']),
                'city' => CheckoutUtils::sanitizeInput($postData['city']),
                'zip_code' => CheckoutUtils::sanitizeInput($postData['zip_code']),
                'delivery_instructions' => CheckoutUtils::sanitizeInput($postData['delivery_instructions'] ?? '')
            ];
            
            $this->updateUserProfile($user_id, $user_data);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'subscription_id' => $subscription_id,
                'transaction_id' => $payment_result['transaction_id']
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}

// Main execution starts here
try {
    // Get database connection
    $db = DatabaseConnection::getInstance();
    
    // Get user data
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get or create checkout data
    $order = $_SESSION['checkout_data'] ?? null;
    
    // Validate existing checkout data or create demo data
    $validation_errors = CheckoutDataManager::validateCheckoutData($order);
    if (!empty($validation_errors)) {
        $order = CheckoutDataManager::createDemoCheckoutData($db);
        if ($order) {
            $_SESSION['checkout_data'] = $order;
        } else {
            throw new Exception("Unable to create checkout data. Please ensure plans and menus are available.");
        }
    }
    
    // Extract order components
    $plan = $order['plan'];
    $selected_meals = $order['selected_meals'] ?? [];
    $meal_details = $order['meal_details'] ?? [];
    $total_price = $plan['final_price'];
    
    // Get weekend dates
    $weekend_dates = WeekendDateGenerator::getWeekendDates();
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_order']) || !empty($_POST['payment_method']))) {
        
        $processor = new OrderProcessor($db);
        
        // Validate form input
        $errors = $processor->validateFormInput($_POST);
        
        if (empty($errors)) {
            try {
                $result = $processor->processFullOrder($user_id, $order, $_POST);
                
                if ($result['success']) {
                    $success = true;
                    unset($_SESSION['checkout_data']);
                    $_SESSION['flash_message'] = "Order placed successfully! Thank you for choosing Krua Thai";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['last_order_id'] = $result['subscription_id'];
                    $_SESSION['prevent_double_submit'] = time();
                    
                    header("Location: subscription-status.php?order=" . $result['subscription_id']);
                    exit;
                }
                
            } catch (Exception $e) {
                $errors[] = "An error occurred: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    die("❌ Application Error: " . $e->getMessage());
}

// Check for successful completion - early return to prevent HTML output
if ($success) {
    exit;
}
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

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .payment-methods label {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            padding: 1.2rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            transition: var(--transition);
            background: var(--white);
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .payment-methods label::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(207, 114, 58, 0.05), rgba(189, 147, 121, 0.05));
            opacity: 0;
            transition: var(--transition);
        }

        .payment-methods label:hover::before {
            opacity: 1;
        }

        .payment-methods label:hover {
            border-color: var(--curry);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.2);
        }

        .payment-methods input:checked + i {
            color: var(--curry);
        }

        .payment-methods input {
            accent-color: var(--curry);
            margin-right: 0.5rem;
        }

        .payment-methods i {
            font-size: 1.3rem;
            color: var(--curry);
            z-index: 1;
        }

        .payment-methods span {
            z-index: 1;
        }

        /* Delivery Day Selection (Improved for single selection) */
        .delivery-days-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .delivery-day-option {
            position: relative;
            cursor: pointer;
        }

        .delivery-day-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .delivery-day-card {
            padding: 1.5rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            transition: var(--transition);
            background: var(--white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .delivery-day-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(207, 114, 58, 0.1), rgba(189, 147, 121, 0.1));
            opacity: 0;
            transition: var(--transition);
        }

        .delivery-day-option:hover .delivery-day-card {
            border-color: var(--curry);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(207, 114, 58, 0.2);
        }

        .delivery-day-option:hover .delivery-day-card::before {
            opacity: 1;
        }

        .delivery-day-option input[type="radio"]:checked + .delivery-day-card {
            border-color: var(--curry);
            background: linear-gradient(135deg, rgba(207, 114, 58, 0.1), rgba(189, 147, 121, 0.1));
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(207, 114, 58, 0.3);
        }

        .delivery-day-option input[type="radio"]:checked + .delivery-day-card::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 15px;
            background: var(--curry);
            color: var(--white);
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .delivery-day-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            z-index: 1;
            position: relative;
        }

        .delivery-day-date {
            color: var(--curry);
            font-weight: 600;
            font-size: 0.9rem;
            z-index: 1;
            position: relative;
        }

        .delivery-day-info {
            color: var(--text-gray);
            font-size: 0.8rem;
            margin-top: 0.3rem;
            z-index: 1;
            position: relative;
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

        .weekend-info {
            background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
            border: 2px solid var(--sage);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: var(--text-dark);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .weekend-info i {
            color: var(--sage);
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .weekend-info-content {
            flex: 1;
        }

        .weekend-info strong {
            color: var(--curry);
        }

        .success-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: var(--success);
            border: 2px solid #c3e6cb;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            text-align: center;
        }

        .success-message h2 {
            margin-bottom: 1rem;
            color: var(--success);
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

            .delivery-days-grid {
                grid-template-columns: 1fr;
            }

            .payment-methods {
                grid-template-columns: 1fr;
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

            .delivery-days-grid {
                grid-template-columns: 1fr;
            }

            .delivery-day-card {
                padding: 1rem;
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
        <!-- Progress Bar -->
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
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Plan Summary -->
        <div class="section plan-summary">
            <div class="label"><i class="fas fa-box"></i> Selected Package</div>
            <div class="plan-title"><?php echo htmlspecialchars(CheckoutUtils::getPlanName($plan)); ?> (<?php echo $plan['meals_per_week']; ?> meals per week)</div>
            <div class="plan-price">$<?php echo number_format($plan['final_price']/100, 2); ?> /week</div>
        </div>

        <!-- Meals Summary -->
        <div class="section meals-summary">
            <div class="label"><i class="fas fa-utensils"></i> Selected Meals</div>
            <ul class="meal-list">
                <?php foreach ($selected_meals as $meal_id): ?>
                    <?php $meal = $meal_details[$meal_id] ?? null; if (!$meal) continue; ?>
                    <li>
                        <span><?php echo htmlspecialchars(CheckoutUtils::getMenuName($meal)); ?></span>
                        <span style="color: var(--curry); font-weight: 600;">($<?php echo number_format($meal['base_price']/100, 2); ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Order Form -->
        <form method="POST" action="" id="checkout-form">
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
                <div class="label"><i class="fas fa-calendar-weekend"></i> Select Your Delivery Day</div>
                
                <div class="weekend-info">
                    <i class="fas fa-info-circle"></i>
                    <div class="weekend-info-content">
                        <strong>Weekend Delivery Only:</strong> Choose one delivery day from the available weekends. We deliver fresh Thai meals on Saturdays and Sundays only.
                    </div>
                </div>
                
                <div class="delivery-days-grid">
                    <?php foreach($weekend_dates as $val => $dateInfo): ?>
                        <div class="delivery-day-option">
                            <input type="radio" name="delivery_day" value="<?php echo htmlspecialchars($val); ?>" id="delivery_<?php echo htmlspecialchars($val); ?>" required>
                            <div class="delivery-day-card">
                                <div class="delivery-day-title"><?php echo htmlspecialchars($dateInfo['day']); ?></div>
                                <div class="delivery-day-date"><?php echo htmlspecialchars($dateInfo['label']); ?></div>
                                <div class="delivery-day-info"><?php echo htmlspecialchars($dateInfo['formatted']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="label" style="margin-top:2rem;"><i class="fas fa-clock"></i> Preferred Delivery Time</div>
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
                        <input type="radio" name="payment_method" value="credit" required>
                        <i class="fas fa-credit-card"></i>
                        <span>Credit/Debit Card</span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="paypal">
                        <i class="fab fa-paypal"></i>
                        <span>PayPal</span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="apple_pay">
                        <i class="fab fa-apple-pay"></i>
                        <span>Apple Pay</span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="google_pay">
                        <i class="fab fa-google-pay"></i>
                        <span>Google Pay</span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="promptpay">
                        <i class="fas fa-university"></i>
                        <span>Bank Transfer</span>
                    </label>
                </div>
                
                <div class="total">Total: $<?php echo number_format($plan['final_price']/100, 2); ?></div>
                
                <button class="btn" type="submit" name="submit_order" value="1" id="main-submit-btn">
                    <i class="fas fa-lock"></i> Confirm and Pay
                </button>
                
                <!-- Hidden fields for form security -->
                <input type="hidden" name="form_token" value="<?php echo hash('sha256', session_id() . time()); ?>">
                <input type="hidden" name="form_submitted" value="1">
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Improved checkout page loaded');
            
            const form = document.getElementById('checkout-form');
            const submitBtn = document.getElementById('main-submit-btn');
            
            if (form && submitBtn) {
                // Enhanced form validation
                function validateForm() {
                    const errors = [];
                    
                    // Required fields validation
                    const requiredFields = [
                        { name: 'delivery_address', label: 'Delivery address' },
                        { name: 'city', label: 'City' },
                        { name: 'zip_code', label: 'ZIP code' }
                    ];
                    
                    requiredFields.forEach(field => {
                        const input = document.querySelector(`input[name="${field.name}"]`);
                        if (!input || !input.value.trim()) {
                            errors.push(`Please enter ${field.label}`);
                        }
                    });
                    
                    // ZIP code format validation
                    const zipCode = document.querySelector('input[name="zip_code"]');
                    if (zipCode && zipCode.value && !/^\d{5}$/.test(zipCode.value.trim())) {
                        errors.push('ZIP code must be 5 digits');
                    }
                    
                    // Payment method validation
                    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                    if (!paymentMethod) {
                        errors.push('Please select a payment method');
                    }
                    
                    // Delivery day validation (single selection)
                    const deliveryDay = document.querySelector('input[name="delivery_day"]:checked');
                    if (!deliveryDay) {
                        errors.push('Please select a delivery day');
                    }
                    
                    return errors;
                }
                
                // Form submission handler
                form.addEventListener('submit', function(e) {
                    console.log('🚀 Form submission started');
                    
                    const errors = validateForm();
                    
                    if (errors.length > 0) {
                        console.log('❌ Validation failed:', errors);
                        alert('Please fix the following issues:\n\n' + errors.join('\n'));
                        e.preventDefault();
                        return false;
                    }
                    
                    // Prevent double submission
                    if (submitBtn.disabled) {
                        console.log('⚠️ Double submission prevented');
                        e.preventDefault();
                        return false;
                    }
                    
                    console.log('✅ Validation passed, submitting...');
                    
                    // Update button state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
                    submitBtn.disabled = true;
                    
                    // Add timestamp to prevent double submission
                    const timestamp = document.createElement('input');
                    timestamp.type = 'hidden';
                    timestamp.name = 'submit_timestamp';
                    timestamp.value = Date.now();
                    form.appendChild(timestamp);
                    
                    return true;
                });
                
                // Delivery day selection enhancement
                const deliveryDayOptions = document.querySelectorAll('.delivery-day-option');
                deliveryDayOptions.forEach(option => {
                    const radio = option.querySelector('input[type="radio"]');
                    const card = option.querySelector('.delivery-day-card');
                    
                    option.addEventListener('click', function() {
                        // Remove selection from all other options
                        deliveryDayOptions.forEach(opt => {
                            opt.querySelector('input[type="radio"]').checked = false;
                        });
                        
                        // Select this option
                        radio.checked = true;
                        
                        // Trigger change event for validation
                        radio.dispatchEvent(new Event('change'));
                        
                        console.log('📅 Delivery day selected:', radio.value);
                    });
                });
                
                // Payment method selection enhancement
                const paymentOptions = document.querySelectorAll('.payment-methods label');
                paymentOptions.forEach(label => {
                    label.addEventListener('click', function() {
                        const radio = this.querySelector('input[type="radio"]');
                        console.log('💳 Payment method selected:', radio.value);
                    });
                });
                
                // Auto-fill demo data for testing (remove in production)
                if (window.location.search.includes('demo=1')) {
                    setTimeout(() => {
                        // Auto-select first payment method
                        const firstPayment = document.querySelector('input[name="payment_method"]');
                        if (firstPayment) firstPayment.checked = true;
                        
                        // Auto-select first delivery day
                        const firstDelivery = document.querySelector('input[name="delivery_day"]');
                        if (firstDelivery) firstDelivery.checked = true;
                        
                        console.log('🧪 Demo data auto-filled');
                    }, 500);
                }
                
                // Real-time validation feedback
                const inputs = form.querySelectorAll('input[required], select[required]');
                inputs.forEach(input => {
                    input.addEventListener('blur', function() {
                        if (this.value.trim()) {
                            this.style.borderColor = 'var(--success)';
                        } else {
                            this.style.borderColor = 'var(--danger)';
                        }
                    });
                    
                    input.addEventListener('input', function() {
                        if (this.style.borderColor === 'rgb(231, 76, 60)') { // var(--danger)
                            this.style.borderColor = 'var(--border-light)';
                        }
                    });
                });
                
                console.log('✅ Enhanced form handlers attached');
                
            } else {
                console.error('❌ Form or submit button not found');
            }
        });
        
        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('❌ JavaScript error:', e.error);
            
            // Re-enable submit button if there's an error
            const submitBtn = document.getElementById('main-submit-btn');
            if (submitBtn && submitBtn.disabled) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-lock"></i> Confirm and Pay';
            }
        });
        
        // Handle page unload during form submission
        let formSubmitting = false;
        document.getElementById('checkout-form')?.addEventListener('submit', () => {
            formSubmitting = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formSubmitting) {
                e.preventDefault();
                e.returnValue = '';
                return 'Your order is being processed. Please wait...';
            }
        });
    </script>
</body>
</html>