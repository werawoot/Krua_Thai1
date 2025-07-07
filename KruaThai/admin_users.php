<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=admin_users");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current user's role
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = mysqli_prepare($connection, $role_query);
mysqli_stmt_bind_param($stmt, "s", $user_id);
mysqli_stmt_execute($stmt);
$current_user_role = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['role'];
mysqli_stmt_close($stmt);

// Check admin permissions
if ($current_user_role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_user_id = $_POST['user_id'] ?? '';
    
    if ($action === 'update_status') {
        $new_status = sanitizeInput($_POST['status'] ?? '');
        $valid_statuses = ['active', 'inactive', 'suspended', 'pending_verification'];
        
        if (in_array($new_status, $valid_statuses)) {
            $update_stmt = mysqli_prepare($connection, 
                "UPDATE users SET status = ? WHERE id = ? AND id != ?");
            mysqli_stmt_bind_param($update_stmt, "sss", $new_status, $target_user_id, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "อัพเดทสถานะผู้ใช้เรียบร้อยแล้ว";
                logActivity($user_id, 'admin_update_user_status', "Updated user $target_user_id status to $new_status");
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการอัพเดทสถานะ";
            }
            mysqli_stmt_close($update_stmt);
        }
    }
    elseif ($action === 'update_role') {
        $new_role = sanitizeInput($_POST['role'] ?? '');
        $valid_roles = ['customer', 'admin', 'kitchen', 'rider', 'support'];
        
        if (in_array($new_role, $valid_roles) && $target_user_id !== $user_id) {
            $update_stmt = mysqli_prepare($connection, 
                "UPDATE users SET role = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "ss", $new_role, $target_user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "อัพเดทบทบาทผู้ใช้เรียบร้อยแล้ว";
                logActivity($user_id, 'admin_update_user_role', "Updated user $target_user_id role to $new_role");
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการอัพเดทบทบาท";
            }
            mysqli_stmt_close($update_stmt);
        }
    }
    elseif ($action === 'delete_user') {
        if ($target_user_id !== $user_id) {
            // Soft delete - just update status
            $delete_stmt = mysqli_prepare($connection, 
                "UPDATE users SET status = 'inactive', email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()) WHERE id = ?");
            mysqli_stmt_bind_param($delete_stmt, "s", $target_user_id);
            
            if (mysqli_stmt_execute($delete_stmt)) {
                $success_message = "ลบผู้ใช้เรียบร้อยแล้ว";
                logActivity($user_id, 'admin_delete_user', "Soft deleted user $target_user_id");
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการลบผู้ใช้";
            }
            mysqli_stmt_close($delete_stmt);
        }
    }
    elseif ($action === 'reset_password') {
        // Generate temporary password
        $temp_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        $reset_stmt = mysqli_prepare($connection, 
            "UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
        mysqli_stmt_bind_param($reset_stmt, "ss", $hashed_password, $target_user_id);
        
        if (mysqli_stmt_execute($reset_stmt)) {
            // Get user email for notification
            $email_stmt = mysqli_prepare($connection, "SELECT email, first_name FROM users WHERE id = ?");
            mysqli_stmt_bind_param($email_stmt, "s", $target_user_id);
            mysqli_stmt_execute($email_stmt);
            $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($email_stmt));
            
            if ($user_data) {
                // Send temporary password email
                $email_subject = "รหัสผ่านชั่วคราว - Krua Thai";
                $email_body = generateTempPasswordEmail($user_data['first_name'], $temp_password);
                sendEmail($user_data['email'], $email_subject, $email_body);
                
                $success_message = "รีเซ็ตรหัสผ่านเรียบร้อย ส่งรหัสผ่านชั่วคราวให้ผู้ใช้ทางอีเมลแล้ว";
            }
            
            logActivity($user_id, 'admin_reset_password', "Reset password for user $target_user_id");
            mysqli_stmt_close($email_stmt);
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน";
        }
        mysqli_stmt_close($reset_stmt);
    }
}

// Get filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$role_filter = sanitizeInput($_GET['role'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $param_types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM users WHERE $where_clause";
$count_stmt = mysqli_prepare($connection, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total_users = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total_users / $per_page);
mysqli_stmt_close($count_stmt);

// Get users
$users_query = "SELECT id, first_name, last_name, email, phone, role, status, 
                       email_verified, last_login, created_at,
                       (SELECT COUNT(*) FROM subscriptions WHERE user_id = users.id AND status = 'active') as active_subscriptions
                FROM users 
                WHERE $where_clause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";

$users_stmt = mysqli_prepare($connection, $users_query);
$limit_params = $params;
$limit_params[] = $per_page;
$limit_params[] = $offset;
$limit_param_types = $param_types . "ii";
mysqli_stmt_bind_param($users_stmt, $limit_param_types, ...$limit_params);
mysqli_stmt_execute($users_stmt);
$users = mysqli_fetch_all(mysqli_stmt_get_result($users_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($users_stmt);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today,
    SUM(CASE WHEN DATE(last_login) = CURDATE() THEN 1 ELSE 0 END) as active_today
FROM users";
$stats_result = mysqli_query($connection, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$page_title = "จัดการผู้ใช้งาน";
include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/admin.css">

<div class="admin-container">
    <div class="admin-header">
        <div class="container">
            <div class="header-content">
                <div class="breadcrumb">
                    <a href="dashboard.php">แดชบอร์ด</a>
                    <span>›</span>
                    <span>จัดการผู้ใช้งาน</span>
                </div>
                <h1>จัดการผู้ใช้งาน</h1>
                <p>ดูและจัดการข้อมูลผู้ใช้งานทั้งหมดในระบบ</p>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">ผู้ใช้ทั้งหมด</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['active_users']); ?></div>
                    <div class="stat-label">ผู้ใช้ที่ใช้งาน</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['customers']); ?></div>
                    <div class="stat-label">ลูกค้า</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['new_today']); ?></div>
                    <div class="stat-label">สมัครวันนี้</div>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <div class="alert-icon">✅</div>
                <div class="alert-content">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters and Search -->
        <div class="users-controls">
            <div class="search-section">
                <form method="GET" class="search-form">
                    <div class="search-group">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="ค้นหาชื่อ, อีเมล..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="search-input"
                        >
                        <button type="submit" class="search-btn">
                            🔍 ค้นหา
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <select name="role" class="filter-select">
                            <option value="">ทุกบทบาท</option>
                            <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>ลูกค้า</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>ผู้ดูแลระบบ</option>
                            <option value="kitchen" <?php echo $role_filter === 'kitchen' ? 'selected' : ''; ?>>ครัว</option>
                            <option value="rider" <?php echo $role_filter === 'rider' ? 'selected' : ''; ?>>ไรเดอร์</option>
                            <option value="support" <?php echo $role_filter === 'support' ? 'selected' : ''; ?>>ฝ่ายสนับสนุน</option>
                        </select>
                        
                        <select name="status" class="filter-select">
                            <option value="">ทุกสถานะ</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>ใช้งาน</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>ไม่ใช้งาน</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>ถูกระงับ</option>
                            <option value="pending_verification" <?php echo $status_filter === 'pending_verification' ? 'selected' : ''; ?>>รอยืนยัน</option>
                        </select>
                        
                        <button type="submit" class="filter-btn">กรอง</button>
                        
                        <?php if (!empty($search) || !empty($role_filter) || !empty($status_filter)): ?>
                            <a href="admin_users.php" class="clear-btn">ล้างตัวกรอง</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-table-container">
            <div class="table-header">
                <h2>รายการผู้ใช้งาน</h2>
                <div class="table-info">
                    แสดง <?php echo count($users); ?> จาก <?php echo number_format($total_users); ?> ผู้ใช้
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ข้อมูลผู้ใช้</th>
                            <th>บทบาท</th>
                            <th>สถานะ</th>
                            <th>การเข้าสู่ระบบ</th>
                            <th>สมาชิกตั้งแต่</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr class="user-row">
                                <td class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <div class="user-name">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </div>
                                        <div class="user-email">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                            <?php if ($user['email_verified']): ?>
                                                <span class="verified-badge">✓</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($user['phone'])): ?>
                                            <div class="user-phone"><?php echo htmlspecialchars($user['phone']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($user['active_subscriptions'] > 0): ?>
                                            <div class="user-subscription">📦 มีการสมัครใช้งาน</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php 
                                        $role_names = [
                                            'customer' => '🛒 ลูกค้า',
                                            'admin' => '👑 แอดมิน',
                                            'kitchen' => '👨‍🍳 ครัว',
                                            'rider' => '🏍️ ไรเดอร์',
                                            'support' => '🎧 ซัพพอร์ต'
                                        ];
                                        echo $role_names[$user['role']] ?? $user['role'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php 
                                        $status_names = [
                                            'active' => '✅ ใช้งาน',
                                            'inactive' => '💤 ไม่ใช้งาน',
                                            'suspended' => '🚫 ถูกระงับ',
                                            'pending_verification' => '⏳ รอยืนยัน'
                                        ];
                                        echo $status_names[$user['status']] ?? $user['status'];
                                        ?>
                                    </span>
                                </td>
                                <td class="login-info">
                                    <?php if ($user['last_login']): ?>
                                        <div class="last-login">
                                            <?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="never-login">ไม่เคยเข้าสู่ระบบ</div>
                                    <?php endif; ?>
                                </td>
                                <td class="created-date">
                                    <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="actions">
                                    <?php if ($user['id'] !== $user_id): ?>
                                        <div class="action-buttons">
                                            <button class="action-btn edit-btn" onclick="openEditModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>', '<?php echo $user['role']; ?>', '<?php echo $user['status']; ?>')">
                                                ✏️ แก้ไข
                                            </button>
                                            <button class="action-btn reset-btn" onclick="resetPassword('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                🔑 รีเซ็ต
                                            </button>
                                            <button class="action-btn delete-btn" onclick="deleteUser('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                🗑️ ลบ
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="current-user">ผู้ใช้ปัจจุบัน</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn">
                            ← ก่อนหน้า
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn">
                            ถัดไป →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>แก้ไขข้อมูลผู้ใช้</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="">
            <input type="hidden" name="user_id" value="">
            
            <div class="form-group">
                <label>ชื่อผู้ใช้:</label>
                <div id="editUserName" class="user-display"></div>
            </div>
            
            <div class="form-group">
                <label for="editRole">บทบาท:</label>
                <select name="role" id="editRole" class="form-select">
                    <option value="customer">ลูกค้า</option>
                    <option value="admin">ผู้ดูแลระบบ</option>
                    <option value="kitchen">ครัว</option>
                    <option value="rider">ไรเดอร์</option>
                    <option value="support">ฝ่ายสนับสนุน</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="editStatus">สถานะ:</label>
                <select name="status" id="editStatus" class="form-select">
                    <option value="active">ใช้งาน</option>
                    <option value="inactive">ไม่ใช้งาน</option>
                    <option value="suspended">ถูกระงับ</option>
                    <option value="pending_verification">รอยืนยัน</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal()">ยกเลิก</button>
                <button type="button" class="btn-primary" onclick="updateRole()">อัพเดทบทบาท</button>
                <button type="button" class="btn-primary" onclick="updateStatus()">อัพเดทสถานะ</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(userId, userName, role, status) {
    const modal = document.getElementById('editModal');
    const form = modal.querySelector('form');
    
    form.querySelector('input[name="user_id"]').value = userId;
    document.getElementById('editUserName').textContent = userName;
    document.getElementById('editRole').value = role;
    document.getElementById('editStatus').value = status;
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function updateRole() {
    const form = document.querySelector('#editModal form');
    form.querySelector('input[name="action"]').value = 'update_role';
    form.submit();
}

function updateStatus() {
    const form = document.querySelector('#editModal form');
    form.querySelector('input[name="action"]').value = 'update_status';
    form.submit();
}

function resetPassword(userId, userName) {
    if (confirm(`ยืนยันการรีเซ็ตรหัสผ่านสำหรับ "${userName}"?\n\nระบบจะส่งรหัสผ่านชั่วคราวไปยังอีเมลของผู้ใช้`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'reset_password';
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        
        form.appendChild(actionInput);
        form.appendChild(userIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteUser(userId, userName) {
    if (confirm(`ยืนยันการลบผู้ใช้ "${userName}"?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_user';
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        
        form.appendChild(actionInput);
        form.appendChild(userIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Auto-refresh every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>

<?php
// Temporary password email template
function generateTempPasswordEmail($firstName, $tempPassword) {
    $logoUrl = "https://" . $_SERVER['HTTP_HOST'] . "/assets/images/logo.png";
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>รหัสผ่านชั่วคราว - Krua Thai</title>
        <style>
            body { font-family: "Sarabun", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f6f0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #ffc107, #fd7e14); padding: 2rem; text-align: center; color: white; }
            .logo { max-width: 120px; margin-bottom: 1rem; }
            .content { padding: 2rem; }
            .temp-password { background: #fff3cd; border: 2px solid #ffc107; padding: 1.5rem; margin: 1.5rem 0; border-radius: 10px; text-align: center; }
            .password-code { font-family: monospace; font-size: 1.5rem; font-weight: bold; color: #856404; background: white; padding: 1rem; border-radius: 5px; margin: 1rem 0; letter-spacing: 2px; }
            .footer { background: #f8f6f0; padding: 1.5rem; text-align: center; color: #666; font-size: 0.9rem; }
            .warning { background: #f8d7da; border-left: 4px solid #dc3545; padding: 1rem; margin: 1rem 0; border-radius: 5px; color: #721c24; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="' . $logoUrl . '" alt="Krua Thai" class="logo">
                <h1>รหัสผ่านชั่วคราว</h1>
                <p>ผู้ดูแลระบบได้รีเซ็ตรหัสผ่านของคุณ</p>
            </div>
            <div class="content">
                <p>สวัสดี คุณ' . htmlspecialchars($firstName) . ',</p>
                <p>ผู้ดูแลระบบได้ทำการรีเซ็ตรหัสผ่านของบัญชี Krua Thai ของคุณ</p>
                
                <div class="temp-password">
                    <h3>รหัสผ่านชั่วคราวของคุณ:</h3>
                    <div class="password-code">' . htmlspecialchars($tempPassword) . '</div>
                    <p><strong>กรุณาเปลี่ยนรหัสผ่านใหม่ทันทีหลังจากเข้าสู่ระบบ</strong></p>
                </div>
                
                <div class="warning">
                    <strong>เพื่อความปลอดภัย:</strong>
                    <ul>
                        <li>เข้าสู่ระบบด้วยรหัสผ่านชั่วคราวนี้</li>
                        <li>เปลี่ยนรหัสผ่านใหม่ในหน้าแก้ไขโปรไฟล์ทันที</li>
                        <li>อย่าแชร์รหัสผ่านนี้กับผู้อื่น</li>
                        <li>ลบอีเมลนี้หลังจากเปลี่ยนรหัสผ่านแล้ว</li>
                    </ul>
                </div>
                
                <p>หากคุณไม่ได้ขอให้รีเซ็ตรหัสผ่าน กรุณาติดต่อฝ่ายสนับสนุนทันที</p>
                
                <div style="text-align: center; margin: 2rem 0;">
                    <a href="https://' . $_SERVER['HTTP_HOST'] . '/login.php" style="background: linear-gradient(45deg, #866028, #a67c00); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 10px; font-weight: 600;">เข้าสู่ระบบ</a>
                </div>
            </div>
            <div class="footer">
                <p>ด้วยความห่วงใย<br><strong>ทีมงาน Krua Thai</strong></p>
                <p>หากมีปัญหา กรุณาติดต่อ: <a href="mailto:support@kruathai.com">support@kruathai.com</a></p>
            </div>
        </div>
    </body>
    </html>';
}
?>