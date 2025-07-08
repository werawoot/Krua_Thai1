<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$email = 'your-email@example.com'; // ใส่ email ที่ต้องการ verify

$query = "UPDATE users SET email_verified = 1, status = 'active', email_verification_token = NULL WHERE email = :email";
$stmt = $db->prepare($query);
$stmt->bindParam(':email', $email);

if ($stmt->execute()) {
    echo "✅ Email verified successfully! You can now login.";
} else {
    echo "❌ Verification failed.";
}
?>