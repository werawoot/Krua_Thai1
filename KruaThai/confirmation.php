<?php
// Krua Thai - Confirmation Page
session_start();

$order_success = isset($_GET['success']) && $_GET['success'] == '1';
// ใน production: สามารถดึงเลขออเดอร์ หรือสรุปจาก session/database ได้

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ขอบคุณสำหรับการสั่งซื้อ | Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #ece8e1; color: #2c3e50; }
        .container { max-width: 600px; margin: 70px auto; padding: 2rem; background: #fff; border-radius: 18px; box-shadow: 0 6px 24px rgba(207,114,58,0.11);}
        .center { text-align: center; }
        .success-icon { font-size: 3.5rem; color: #28a745; margin-bottom: 1rem;}
        .title { font-size: 2rem; font-weight: 800; margin-bottom: 0.7rem;}
        .desc { font-size: 1.1rem; color: #7f8c8d; margin-bottom: 2.2rem;}
        .btn { padding: 1rem 2.2rem; border-radius: 40px; background: #cf723a; color: #fff; font-size: 1.1rem; font-weight: 700; border: none; cursor: pointer; transition: background 0.2s;}
        .btn:hover { background: #bd9379; }
        .actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;}
        @media (max-width:600px){.container{padding:1rem;}}
    </style>
</head>
<body>
<div class="container center">
    <?php if ($order_success): ?>
        <div class="success-icon"><i class="fas fa-check-circle"></i></div>
        <div class="title">สั่งซื้อสำเร็จ!</div>
        <div class="desc">
            ขอบคุณที่ใช้บริการ Krua Thai<br>
            เราได้รับออเดอร์ของคุณเรียบร้อยแล้ว ทีมงานจะจัดส่งอาหารถึงบ้านคุณตามรอบที่เลือกไว้<br>
            <br>
            <!-- หากอยากแสดงเลขออเดอร์, เพิ่มตรงนี้ -->
        </div>
        <div class="actions">
            <a href="index.php" class="btn"><i class="fas fa-home"></i> กลับหน้าหลัก</a>
            <a href="dashboard.php" class="btn" style="background:#adb89d;"><i class="fas fa-box"></i> ดูสถานะออเดอร์</a>
        </div>
    <?php else: ?>
        <div class="success-icon" style="color: #dc3545;"><i class="fas fa-times-circle"></i></div>
        <div class="title">ไม่สำเร็จ</div>
        <div class="desc">เกิดข้อผิดพลาด ไม่พบออเดอร์หรือยังไม่ได้ชำระเงิน กรุณาลองใหม่</div>
        <div class="actions">
            <a href="index.php" class="btn">กลับหน้าหลัก</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
