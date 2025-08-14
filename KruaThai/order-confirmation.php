<?php
session_start();

// ตรวจสอบว่ามี order success data
if (!isset($_SESSION['order_success'])) {
    header("Location: home2.php");
    exit;
}

$order_data = $_SESSION['order_success'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmed - Krua Thai</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 20px;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        h1 { color: #28a745; }
    </style>
</head>
<body>
    <div class="success-box">
        <h1>✅ Order Confirmed!</h1>
        <p><strong>Order Number:</strong> <?php echo $order_data['order_number']; ?></p>
        <p><strong>Total:</strong> $<?php echo number_format($order_data['total'], 2); ?></p>
        <p><strong>Delivery Date:</strong> <?php echo date('l, M j', strtotime($order_data['delivery_date'])); ?></p>
        <p><strong>Email:</strong> <?php echo $order_data['email']; ?></p>
        <p>A confirmation email has been sent to your email address.</p>
        <br>
        <a href="home2.php" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">Back to Home</a>
    </div>
</body>
</html>