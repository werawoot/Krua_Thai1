<?php
// payment.php
session_start();

// Mock data for amount
$total_amount = $_SESSION['subscription_total'] ?? 990; // หรือรับค่าจากขั้นตอนก่อนหน้า

$status = $_GET['status'] ?? 'pending'; // สถานะ: pending, success, fail

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mock payment process: random result
    $payment_methods = ['credit_card', 'google_pay', 'apple_pay', 'paypal'];
    $selected = $_POST['payment_method'];
    if (in_array($selected, $payment_methods)) {
        // Mock result: 80% success, 20% fail
        $success = (rand(1, 10) > 2);
        header('Location: payment.php?status=' . ($success ? 'success' : 'fail'));
        exit();
    }
}

function show_status_message($status) {
    switch ($status) {
        case 'success':
            return '<div class="alert success">✅ Payment successful! Thank you for your order.</div>';
        case 'fail':
            return '<div class="alert error">❌ Payment failed. Please try again or use a different method.</div>';
        default:
            return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment - Krua Thai</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f9f9f6; margin: 0; padding: 0;}
        .container { max-width: 420px; margin: 60px auto; background: #fff; border-radius: 16px; box-shadow: 0 6px 24px #0001; padding: 2rem;}
        h2 { text-align: center; color: #3d4028; margin-bottom: 1.5rem;}
        .amount { font-size: 1.8rem; color: #cf723a; text-align: center; margin-bottom: 1.5rem; font-weight: bold;}
        .form-group { margin-bottom: 1.2rem;}
        label { display: block; margin-bottom: 0.4rem; font-weight: 500;}
        .payment-methods { display: flex; flex-direction: column; gap: 0.7rem; }
        .payment-option { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 1px solid #adb89d22; border-radius: 8px; background: #ece8e1; cursor: pointer;}
        .payment-option input[type="radio"] { accent-color: #cf723a;}
        .payment-icon { font-size: 1.3rem; }
        .btn-pay { width: 100%; background: #cf723a; color: #fff; border: none; padding: 1rem; font-size: 1.15rem; border-radius: 8px; cursor: pointer; margin-top: 1rem; font-weight: 600;}
        .btn-pay:hover { background: #bd9379; }
        .alert { text-align: center; margin-bottom: 1.2rem; border-radius: 8px; padding: 1rem;}
        .alert.success { background: #e8f5e9; color: #388e3c;}
        .alert.error { background: #ffebee; color: #c62828;}
        .back-link { display: block; text-align: center; margin-top: 2rem; color: #718096; text-decoration: underline;}
    </style>
</head>
<body>
    <div class="container">
        <h2>Payment</h2>
        <div class="amount">Total: ฿<?= number_format($total_amount); ?></div>

        <?= show_status_message($status); ?>

        <?php if ($status === 'pending'): ?>
        <form method="post" autocomplete="off">
            <div class="form-group">
                <label>Select Payment Method:</label>
                <div class="payment-methods">
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="credit_card" required>
                        <span class="payment-icon">💳</span> Credit Card
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="google_pay" required>
                        <span class="payment-icon">🟩</span> Google Pay
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="apple_pay" required>
                        <span class="payment-icon">🍏</span> Apple Pay
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="paypal" required>
                        <span class="payment-icon">🅿️</span> PayPal
                    </label>
                </div>
            </div>
            <button type="submit" class="btn-pay">Pay Now</button>
        </form>
        <?php elseif ($status === 'success'): ?>
            <a href="dashboard.php" class="back-link">Go to Dashboard</a>
        <?php elseif ($status === 'fail'): ?>
            <a href="payment.php" class="back-link">Try Again</a>
        <?php endif; ?>
    </div>
</body>
</html>
