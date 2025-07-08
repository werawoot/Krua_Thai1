<?php
/**
 * Krua Thai - User Profile Page
 * File: profile.php
 * Description: Show & edit user profile (for customers)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=profile.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);

    // Validation (เพิ่มเองได้)
    if ($first_name && $last_name && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
        $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);
        $msg = "บันทึกข้อมูลสำเร็จ";
        // (Optional) อัปเดต session
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;
    } else {
        $msg = "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    if ($new !== $confirm) {
        $msg = "รหัสผ่านใหม่ไม่ตรงกัน";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($old, $user['password'])) {
            $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
            $msg = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
        } else {
            $msg = "รหัสผ่านเดิมไม่ถูกต้อง";
        }
    }
}

// Load user profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: logout.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>โปรไฟล์ผู้ใช้ | Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #ece8e1;
            color: #2c3e50;
            margin: 0;
            padding: 0;
        }
        .profile-container {
            max-width: 460px;
            margin: 40px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 6px 28px rgba(207, 114, 58, .07);
            padding: 2.2rem 2.5rem;
        }
        h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #cf723a;
        }
        label {
            display: block;
            margin-bottom: 0.4rem;
            color: #bd9379;
            font-weight: 600;
        }
        input[type=text], input[type=email], input[type=password], input[type=tel] {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1.4rem;
            border-radius: 8px;
            border: 1.5px solid #adb89d;
            font-size: 1.07rem;
        }
        .btn {
            background: linear-gradient(90deg,#cf723a,#bd9379);
            color: #fff;
            border: none;
            border-radius: 25px;
            padding: 0.8rem 2.2rem;
            font-size: 1.05rem;
            font-weight: 700;
            margin-top: 0.8rem;
            cursor: pointer;
            box-shadow: 0 2px 10px #adb89d22;
            transition: .17s;
        }
        .btn:hover {
            background: #adb89d;
        }
        .section {
            margin-bottom: 2.5rem;
        }
        .msg {
            text-align: center;
            background: #fcf2ea;
            color: #cf723a;
            padding: 0.7rem;
            margin-bottom: 1rem;
            border-radius: 9px;
        }
        .logout-link {
            display: inline-block;
            text-align: center;
            margin: 1.5rem auto 0;
            color: #cf723a;
            text-decoration: none;
        }
        .logout-link:hover { text-decoration: underline; }
        @media (max-width: 600px) {
            .profile-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <h2><i class="fas fa-user-circle"></i> โปรไฟล์ของฉัน</h2>
        <?php if($msg): ?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif; ?>

        <!-- Profile Update Form -->
        <form method="post" class="section" autocomplete="off">
            <label>ชื่อ</label>
            <input type="text" name="first_name" value="<?=htmlspecialchars($user['first_name'])?>" required>
            <label>นามสกุล</label>
            <input type="text" name="last_name" value="<?=htmlspecialchars($user['last_name'])?>" required>
            <label>Email</label>
            <input type="email" name="email" value="<?=htmlspecialchars($user['email'])?>" required>
            <label>เบอร์โทรศัพท์</label>
            <input type="tel" name="phone" value="<?=htmlspecialchars($user['phone'])?>">
            <button class="btn" name="update_profile" type="submit"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
        </form>

        <!-- Password Change Form -->
        <form method="post" class="section" autocomplete="off">
            <label>เปลี่ยนรหัสผ่าน</label>
            <input type="password" name="old_password" placeholder="รหัสผ่านเดิม" required>
            <input type="password" name="new_password" placeholder="รหัสผ่านใหม่" required>
            <input type="password" name="confirm_password" placeholder="ยืนยันรหัสผ่านใหม่" required>
            <button class="btn" name="change_password" type="submit"><i class="fas fa-lock"></i> เปลี่ยนรหัสผ่าน</button>
        </form>

        <a class="logout-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>
</body>
</html>
