<?php
/**
 * Krua Thai - Payment Status & History Page
 * File: payment_status.php
 * Description: Displays the logged-in user's payment history.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1); // ใน Production จริงควรตั้งเป็น 0
session_start();

// --- 1. การตั้งค่าและตรวจสอบการล็อกอิน ---
require_once 'config/database.php';
require_once 'includes/functions.php'; // ถ้ามีฟังก์ชันส่วนกลาง

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=payment_status.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_error = null;
$payments = [];

// --- 2. ดึงข้อมูลประวัติการชำระเงิน ---
try {
    $stmt = $pdo->prepare(
        "SELECT 
            p.transaction_id, 
            p.amount, 
            p.currency, 
            p.status, 
            p.payment_date, 
            p.description,
            p.payment_method
         FROM payments p
         WHERE p.user_id = ?
         ORDER BY p.payment_date DESC"
    );
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // logError('payment_status.php', $e->getMessage());
    $page_error = "เกิดข้อผิดพลาดในการดึงข้อมูลการชำระเงิน";
}

// --- Helper Function สำหรับแสดงผล ---
function getStatusInfo($status) {
    $map = [
        'completed' => ['text' => 'สำเร็จ', 'class' => 'completed', 'icon' => 'fa-check-circle'],
        'pending'   => ['text' => 'รอตรวจสอบ', 'class' => 'pending', 'icon' => 'fa-clock'],
        'failed'    => ['text' => 'ล้มเหลว', 'class' => 'failed', 'icon' => 'fa-times-circle'],
        'refunded'  => ['text' => 'คืนเงินแล้ว', 'class' => 'refunded', 'icon' => 'fa-undo'],
        'partial_refund' => ['text' => 'คืนเงินบางส่วน', 'class' => 'refunded', 'icon' => 'fa-undo-alt']
    ];
    return $map[strtolower($status)] ?? ['text' => ucfirst($status), 'class' => 'unknown', 'icon' => 'fa-question-circle'];
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการชำระเงิน - Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --cream: #ece8e1; --sage: #adb89d; --brown: #bd9379; --curry: #cf723a;
            --white: #ffffff; --text-dark: #2c3e50; --text-gray: #7f8c8d;
            --border-light: #e8e8e8; --shadow-soft: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-medium: 0 8px 24px rgba(0,0,0,0.1); --radius-lg: 16px;
            --transition: all 0.3s ease; --success: #27ae60; --danger: #e74c3c;
            --warning: #f39c12; --info: #3498db;
        }
        body { font-family: 'Sarabun', sans-serif; background: #f8f5f2; color: var(--text-dark); margin: 0; }
        .container { max-width: 1100px; margin: 0 auto; padding: 2rem; }
        .page-header { text-align: center; margin-bottom: 2rem; }
        .page-title { font-size: 2.5rem; font-weight: 700; color: var(--text-dark); }
        .page-title i { color: var(--curry); }
        .table-container { background: var(--white); border-radius: var(--radius-lg); box-shadow: var(--shadow-medium); overflow: hidden; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1.2rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-light); }
        th { background: #fafafa; font-weight: 600; text-transform: uppercase; font-size: 0.9rem; color: var(--text-gray); }
        tbody tr:hover { background-color: #fcfcfc; }
        .status-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
        .status-badge.completed { background: #eaf9f0; color: #27ae60; }
        .status-badge.pending { background: #fef5e7; color: #f39c12; }
        .status-badge.failed { background: #fbeae9; color: #e74c3c; }
        .status-badge.refunded { background: #eaf2f8; color: #3498db; }
        .payment-method { display: flex; align-items: center; gap: 0.5rem; }
        .price { font-weight: 700; color: var(--text-dark); }
        .empty-state { text-align: center; padding: 4rem 2rem; }
        .empty-state i { font-size: 4rem; color: var(--sage); opacity: 0.5; margin-bottom: 1.5rem; }
        .empty-state h3 { font-size: 1.5rem; margin-bottom: 1rem; }
        .btn { display: inline-block; padding: 0.8rem 2rem; border-radius: 8px; background-color: var(--curry); color: white; text-decoration: none; font-weight: 600; transition: transform 0.2s; }
        .btn:hover { transform: scale(1.05); }
    </style>
</head>
<body>
    <?php // include 'includes/header.php'; // เพิ่ม Header ของเว็บคุณที่นี่ ?>

    <div class="container">
        <header class="page-header">
            <h1 class="page-title"><i class="fas fa-receipt"></i> ประวัติการชำระเงิน</h1>
            <p class="page-subtitle">รายการชำระเงินทั้งหมดของคุณสำหรับบริการ Krua Thai</p>
        </header>

        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>รหัสธุรกรรม</th>
                            <th>รายละเอียด</th>
                            <th>ช่องทาง</th>
                            <th>จำนวนเงิน</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($page_error): ?>
                            <tr><td colspan="6" style="text-align:center; color:red;"><?php echo htmlspecialchars($page_error); ?></td></tr>
                        <?php elseif (empty($payments)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        <h3>ยังไม่มีประวัติการชำระเงิน</h3>
                                        <p>เมื่อคุณเริ่มสมัครแพ็กเกจ รายการชำระเงินจะแสดงที่นี่</p>
                                        <a href="subscribe.php" class="btn">เลือกแพ็กเกจเลย</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment):
                                $status_info = getStatusInfo($payment['status']);
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?></td>
                                <td><code><?php echo htmlspecialchars($payment['transaction_id']); ?></code></td>
                                <td><?php echo htmlspecialchars($payment['description']); ?></td>
                                <td>
                                    <span class="payment-method">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                    </span>
                                </td>
                                <td><strong class="price">฿<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td>
                                    <div class="status-badge <?php echo $status_info['class']; ?>">
                                        <i class="fas <?php echo $status_info['icon']; ?>"></i>
                                        <span><?php echo $status_info['text']; ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php // include 'includes/footer.php'; // เพิ่ม Footer ของเว็บคุณที่นี่ ?>
</body>
</html>