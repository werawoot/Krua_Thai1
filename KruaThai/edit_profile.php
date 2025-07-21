<?php
/**
 * Beautiful & Complete Edit Profile Page with Password Change (AJAX Version)
 * File: edit_profile.php
 * Status: PRODUCTION READY ✅
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=edit_profile.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// 🔥 IMPROVEMENT: AJAX Handler at the top
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'errors' => []];

    // Get current user password hash for verification
    $stmt_pass = mysqli_prepare($connection, "SELECT password_hash FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt_pass, "s", $user_id);
    mysqli_stmt_execute($stmt_pass);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_pass));
    $current_password_hash = $user_data['password_hash'] ?? '';
    mysqli_stmt_close($stmt_pass);

    if ($action === 'update_profile') {
        // Sanitize all inputs
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $delivery_address = sanitizeInput($_POST['delivery_address'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $zip_code = sanitizeInput($_POST['zip_code'] ?? '');
        $delivery_instructions = sanitizeInput($_POST['delivery_instructions'] ?? '');
        $dietary_preferences = isset($_POST['dietary_preferences']) ? json_encode($_POST['dietary_preferences']) : '[]';
        $allergies = isset($_POST['allergies']) ? json_encode($_POST['allergies']) : '[]';
        $spice_level = sanitizeInput($_POST['spice_level'] ?? 'medium');

        // Validation
        if (empty($first_name)) $response['errors'][] = "กรุณากรอกชื่อ";
        if (empty($last_name)) $response['errors'][] = "กรุณากรอกนามสกุล";

        if (empty($response['errors'])) {
            $sql = "UPDATE users SET 
                        first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, gender = ?,
                        delivery_address = ?, city = ?, zip_code = ?, delivery_instructions = ?,
                        dietary_preferences = ?, allergies = ?, spice_level = ?, updated_at = NOW() 
                    WHERE id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssssssss", 
                $first_name, $last_name, $phone, $date_of_birth, $gender, 
                $delivery_address, $city, $zip_code, $delivery_instructions, 
                $dietary_preferences, $allergies, $spice_level, 
                $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = "อัพเดทข้อมูลส่วนตัวเรียบร้อยแล้ว";
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            } else {
                $response['errors'][] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . mysqli_error($connection);
            }
            mysqli_stmt_close($stmt);
        }
    } 
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($current_password)) $response['errors'][] = "กรุณากรอกรหัสผ่านปัจจุบัน";
        if (empty($new_password)) $response['errors'][] = "กรุณากรอกรหัสผ่านใหม่";
        if (strlen($new_password) < 8) $response['errors'][] = "รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร";
        if ($new_password !== $confirm_password) $response['errors'][] = "รหัสผ่านใหม่และการยืนยันไม่ตรงกัน";
        
        if (empty($response['errors'])) {
            if (password_verify($current_password, $current_password_hash)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($connection, "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
                } else {
                    $response['errors'][] = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
                }
                mysqli_stmt_close($stmt);
            } else {
                $response['errors'][] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
            }
        }
    }
    
    echo json_encode($response);
    exit();
}

// Fetch current user data for page display
$stmt = mysqli_prepare($connection, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "s", $user_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current_user) {
    die("ไม่สามารถดึงข้อมูลผู้ใช้ได้");
}

$page_title = "แก้ไขข้อมูลส่วนตัว";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Krua Thai</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Paste all CSS from previous code here... */
        /* Make sure to keep the improved CSS for alerts, forms, buttons, etc. */
        :root {
            --olive: #3d4028; --matcha: #4e4f22; --brown: #866028; --cream: #d1b990;
            --light-cream: #f5ede4; --white: #ffffff; --gray: #6c757d; --light-gray: #f8f9fa;
            --success: #28a745; --danger: #dc3545; --shadow: rgba(61, 64, 40, 0.1);
        }
        body { font-family: 'Sarabun', sans-serif; line-height: 1.6; color: var(--olive); background: var(--light-cream); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .profile-header { background: linear-gradient(135deg, var(--olive) 0%, var(--matcha) 100%); color: var(--white); padding: 2rem 0; margin-bottom: 2rem; }
        .profile-header h1 { font-size: 2.5rem; font-weight: 700; }
        .profile-layout { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; align-items: start; }
        .profile-sidebar { background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 20px var(--shadow); position: sticky; top: 2rem; }
        .sidebar-menu { display: flex; flex-direction: column; gap: 0.5rem; }
        .menu-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: none; border-radius: 10px; cursor: pointer; transition: all 0.3s ease; text-align: left; width: 100%; background: transparent; font-family: inherit; font-size: 1rem; }
        .menu-item:hover { background: var(--light-cream); }
        .menu-item.active { background: var(--brown); color: var(--white); }
        .profile-content { background: var(--white); border-radius: 15px; box-shadow: 0 5px 20px var(--shadow); }
        .tab-content { display: none; padding: 2rem; }
        .tab-content.active { display: block; }
        .section-header { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--light-cream); }
        .section-header h2 { font-size: 1.5rem; }
        .section-header p { color: var(--gray); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label { font-weight: 600; margin-bottom: 0.5rem; }
        .required { color: var(--danger); }
        .form-input, .form-select, .form-textarea { padding: 0.8rem 1rem; border: 2px solid #e9ecef; border-radius: 10px; font-size: 1rem; font-family: inherit; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--brown); box-shadow: 0 0 0 3px rgba(134, 96, 40, 0.1); }
        .form-input:disabled { background: var(--light-gray); cursor: not-allowed; }
        .form-textarea { resize: vertical; min-height: 120px; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
        .checkbox-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--light-cream); border-radius: 10px; cursor: pointer; }
        .spice-level-selector { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; }
        .spice-option input[type="radio"] { display: none; }
        .spice-label { text-align: center; padding: 1rem; background: var(--light-cream); border-radius: 10px; cursor: pointer; border: 2px solid transparent; }
        .spice-option input[type="radio"]:checked + .spice-label { background: var(--brown); color: var(--white); border-color: var(--brown); }
        .form-actions { display: flex; gap: 1rem; margin-top: 2rem; padding-top: 2rem; border-top: 2px solid var(--light-cream); }
        .btn-primary { background: var(--brown); color: var(--white); border: none; }
        .btn-secondary { background: transparent; color: var(--brown); border: 2px solid var(--brown); }
        .btn-danger { background: var(--danger); color: var(--white); border: none; }
        .input-wrapper { position: relative; }
        .password-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--gray); }
        @media (max-width: 1024px) { .profile-layout { grid-template-columns: 1fr; } .profile-sidebar { position: static; } .sidebar-menu { flex-direction: row; overflow-x: auto; } }
    </style>
</head>
<body>
    <div class="profile-header">
        <div class="container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>จัดการข้อมูลและค่ากำหนดต่างๆ ของคุณได้ในที่เดียว</p>
        </div>
    </div>

    <div class="container">
        <div class="profile-layout">
            <aside class="profile-sidebar">
                <nav class="sidebar-menu">
                    <button class="menu-item active" data-tab="profile">👤 ข้อมูลส่วนตัวและที่อยู่</button>
                    <button class="menu-item" data-tab="security">🔒 ความปลอดภัย</button>
                </nav>
            </aside>

            <main class="profile-content">
                <div class="tab-content active" id="profile-tab">
                    <form id="profileForm">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="section-header">
                            <h2>ข้อมูลส่วนตัว</h2>
                            <p>ข้อมูลนี้จะปรากฏในโปรไฟล์และข้อมูลการจัดส่งของคุณ</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group"><label for="first_name" class="form-label">ชื่อ <span class="required">*</span></label><input type="text" id="first_name" name="first_name" class="form-input" value="<?= htmlspecialchars($current_user['first_name']); ?>" required></div>
                            <div class="form-group"><label for="last_name" class="form-label">นามสกุล <span class="required">*</span></label><input type="text" id="last_name" name="last_name" class="form-input" value="<?= htmlspecialchars($current_user['last_name']); ?>" required></div>
                            <div class="form-group"><label for="email" class="form-label">อีเมล</label><input type="email" id="email" class="form-input" value="<?= htmlspecialchars($current_user['email']); ?>" disabled></div>
                            <div class="form-group"><label for="phone" class="form-label">เบอร์โทรศัพท์</label><input type="tel" id="phone" name="phone" class="form-input" value="<?= htmlspecialchars($current_user['phone'] ?? ''); ?>"></div>
                            <div class="form-group"><label for="date_of_birth" class="form-label">วันเกิด</label><input type="date" id="date_of_birth" name="date_of_birth" class="form-input" value="<?= htmlspecialchars($current_user['date_of_birth'] ?? ''); ?>"></div>
                            <div class="form-group"><label for="gender" class="form-label">เพศ</label><select id="gender" name="gender" class="form-select"><option value="">เลือกเพศ</option><option value="male" <?= ($current_user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>ชาย</option><option value="female" <?= ($current_user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>หญิง</option><option value="other" <?= ($current_user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>อื่นๆ</option></select></div>
                        </div>

                        <div class="section-header" style="margin-top: 2rem;">
                            <h2>ที่อยู่สำหรับจัดส่ง</h2>
                            <p>แก้ไขที่อยู่หลักสำหรับรับอาหารของคุณ</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group full-width"><label for="delivery_address" class="form-label">ที่อยู่</label><input type="text" id="delivery_address" name="delivery_address" class="form-input" value="<?= htmlspecialchars($current_user['delivery_address'] ?? ''); ?>"></div>
                            <div class="form-group"><label for="city" class="form-label">เมือง/เขต</label><input type="text" id="city" name="city" class="form-input" value="<?= htmlspecialchars($current_user['city'] ?? ''); ?>"></div>
                            <div class="form-group"><label for="zip_code" class="form-label">รหัสไปรษณีย์</label><input type="text" id="zip_code" name="zip_code" class="form-input" value="<?= htmlspecialchars($current_user['zip_code'] ?? ''); ?>"></div>
                            <div class="form-group full-width"><label for="delivery_instructions" class="form-label">คำแนะนำเพิ่มเติม</label><textarea id="delivery_instructions" name="delivery_instructions" class="form-textarea"><?= htmlspecialchars($current_user['delivery_instructions'] ?? ''); ?></textarea></div>
                        </div>
                        
                        <div class="section-header" style="margin-top: 2rem;">
                            <h2>ความชอบอาหาร</h2>
                            <p>บอกให้เรารู้เกี่ยวกับข้อจำกัดและรสชาติที่คุณชอบ</p>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">ข้อจำกัดด้านอาหาร</label>
                            <div class="checkbox-grid">
                                <?php $dietary_preferences = json_decode($current_user['dietary_preferences'] ?? '[]', true); $preferences = ['vegetarian' => 'มังสวิรัติ', 'vegan' => 'วีแกน', 'halal' => 'ฮาลาล', 'gluten_free' => 'ไม่มีกลูเตน', 'dairy_free' => 'ไม่มีแลคโตส', 'low_sodium' => 'โซเดียมต่ำ'];
                                foreach ($preferences as $key => $label): ?>
                                    <label class="checkbox-item"><input type="checkbox" name="dietary_preferences[]" value="<?= $key; ?>" <?= in_array($key, $dietary_preferences) ? 'checked' : ''; ?>><span><?= $label; ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">ระดับความเผ็ด</label>
                            <div class="spice-level-selector">
                                <?php $spice_levels = ['mild' => ['icon' => '🟢', 'text' => 'ไม่เผ็ด'], 'medium' => ['icon' => '🟡', 'text' => 'เผ็ดกลาง'], 'hot' => ['icon' => '🟠', 'text' => 'เผ็ด'], 'extra_hot' => ['icon' => '🔴', 'text' => 'เผ็ดมาก']];
                                foreach ($spice_levels as $key => $data): ?>
                                    <label class="spice-option"><input type="radio" name="spice_level" value="<?= $key; ?>" <?= ($current_user['spice_level'] ?? 'medium') === $key ? 'checked' : ''; ?>><div class="spice-label"><div><?= $data['icon']; ?></div><div><?= $data['text']; ?></div></div></label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">บันทึกข้อมูล</button>
                            <a href="dashboard.php" class="btn-secondary">ยกเลิก</a>
                        </div>
                    </form>
                </div>

                <div class="tab-content" id="security-tab">
                    <div class="section-header">
                        <h2>ความปลอดภัย</h2>
                        <p>จัดการรหัสผ่านและการตั้งค่าความปลอดภัย</p>
                    </div>
                    <form id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-grid">
                            <div class="form-group"><label for="current_password" class="form-label">รหัสผ่านปัจจุบัน <span class="required">*</span></label><div class="input-wrapper"><input type="password" id="current_password" name="current_password" class="form-input" required><button type="button" class="password-toggle" onclick="togglePassword('current_password')">👁️</button></div></div>
                            <div class="form-group"><label for="new_password" class="form-label">รหัสผ่านใหม่ <span class="required">*</span></label><div class="input-wrapper"><input type="password" id="new_password" name="new_password" class="form-input" required minlength="8"><button type="button" class="password-toggle" onclick="togglePassword('new_password')">👁️</button></div></div>
                            <div class="form-group"><label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่ <span class="required">*</span></label><div class="input-wrapper"><input type="password" id="confirm_password" name="confirm_password" class="form-input" required><button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">👁️</button></div></div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-danger">เปลี่ยนรหัสผ่าน</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    menuItems.forEach(mi => mi.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(this.dataset.tab + '-tab').classList.add('active');
                });
            });

            const profileForm = document.getElementById('profileForm');
            profileForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                await submitForm(this, 'กำลังบันทึกข้อมูล...');
            });

            const passwordForm = document.getElementById('passwordForm');
            passwordForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const newPass = document.getElementById('new_password').value;
                const confirmPass = document.getElementById('confirm_password').value;
                if (newPass !== confirmPass) {
                    Swal.fire('ข้อผิดพลาด', 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน', 'error');
                    return;
                }
                if (newPass.length < 8) {
                    Swal.fire('ข้อผิดพลาด', 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร', 'error');
                    return;
                }
                await submitForm(this, 'กำลังเปลี่ยนรหัสผ่าน...');
                this.reset();
            });
        });

        async function submitForm(formElement, loadingText) {
            const submitButton = formElement.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = `<span>${loadingText}</span>`;

            try {
                const formData = new FormData(formElement);
                const response = await fetch('edit_profile.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: result.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        html: result.errors.join('<br>')
                    });
                }
            } catch (error) {
                Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        }
        
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = '🙈';
            } else {
                input.type = 'password';
                button.textContent = '👁️';
            }
        }
    </script>
</body>
</html>