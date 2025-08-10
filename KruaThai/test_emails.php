<?php
/**
 * View Test Emails - Krua Thai
 * File: test_emails.php
 */

$email_dir = __DIR__ . '/test_emails';

if (!is_dir($email_dir)) {
    mkdir($email_dir, 0755, true);
}

$files = glob($email_dir . '/email_*.html');
$emails = [];

foreach ($files as $file) {
    $emails[] = [
        'filename' => basename($file),
        'created' => date('Y-m-d H:i:s', filemtime($file)),
        'size' => filesize($file),
        'url' => 'test_emails/' . basename($file)
    ];
}

// เรียงตามวันที่ใหม่สุด
usort($emails, function($a, $b) {
    return strcmp($b['created'], $a['created']);
});
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Emails - Krua Thai</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .email-list { border-collapse: collapse; width: 100%; }
        .email-list th, .email-list td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .email-list th { background-color: #cf723a; color: white; }
        .view-btn { background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>📧 Test Emails - Krua Thai</h1>
    
    <?php if (empty($emails)): ?>
        <p>No test emails found. Try using the forgot password feature first.</p>
    <?php else: ?>
        <table class="email-list">
            <tr>
                <th>File</th>
                <th>Created</th>
                <th>Size</th>
                <th>Action</th>
            </tr>
            <?php foreach ($emails as $email): ?>
            <tr>
                <td><?php echo htmlspecialchars($email['filename']); ?></td>
                <td><?php echo $email['created']; ?></td>
                <td><?php echo number_format($email['size'] / 1024, 1); ?> KB</td>
                <td><a href="<?php echo $email['url']; ?>" target="_blank" class="view-btn">View Email</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    
    <p><a href="forgot_password.php">← Back to Forgot Password</a></p>
</body>
</html>