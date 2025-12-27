<?php
require_once("../config/config.php");
require_once __DIR__ . '/../config/smtp_config.php';

echo "<h1>Email System Diagnostics</h1>";
echo "<hr>";

// 1. Check SMTP Configuration
echo "<h2>1. SMTP Configuration</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><td><strong>Setting</strong></td><td><strong>Value</strong></td><td><strong>Status</strong></td></tr>";

$checks = [
    'APP_ENV' => [APP_ENV, APP_ENV === 'production' ? '✅ Production' : '⚠️ Development'],
    'SMTP_HOST' => [SMTP_HOST, !empty(SMTP_HOST) ? '✅' : '❌'],
    'SMTP_PORT' => [SMTP_PORT, !empty(SMTP_PORT) ? '✅' : '❌'],
    'SMTP_USERNAME' => [substr(SMTP_USERNAME, 0, 10) . '***', !empty(SMTP_USERNAME) ? '✅' : '❌'],
    'SMTP_PASSWORD' => ['***' . substr(SMTP_PASSWORD, -4), !empty(SMTP_PASSWORD) ? '✅' : '❌'],
    'SMTP_SECURE' => [SMTP_SECURE, !empty(SMTP_SECURE) ? '✅' : '❌'],
    'SMTP_FROM_EMAIL' => [SMTP_FROM_EMAIL, !empty(SMTP_FROM_EMAIL) ? '✅' : '❌'],
    'BASE_URL' => [BASE_URL, !empty(BASE_URL) ? '✅' : '❌'],
];

foreach ($checks as $name => $details) {
    echo "<tr><td>$name</td><td>" . $details[0] . "</td><td>" . $details[1] . "</td></tr>";
}

echo "</table>";
echo "<hr>";

// 2. Check Database
echo "<h2>2. Database Users Table</h2>";
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo "<p><strong>Columns:</strong> " . implode(", ", $columns) . "</p>";
    
    if (in_array('reset_token', $columns) && in_array('reset_token_expires', $columns)) {
        echo "<p>✅ Reset token columns exist</p>";
    } else {
        echo "<p>❌ Reset token columns MISSING - Run this SQL:</p>";
        echo "<code>ALTER TABLE users ADD reset_token VARCHAR(255) DEFAULT NULL;<br>";
        echo "ALTER TABLE users ADD reset_token_expires DATETIME DEFAULT NULL;</code>";
    }
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 3. Check Email Log
echo "<h2>3. Email Log File</h2>";
$logPath = EMAIL_LOG_PATH;
if (file_exists($logPath)) {
    echo "<p>✅ Log file exists: $logPath</p>";
    $logSize = filesize($logPath);
    echo "<p>File size: " . formatBytes($logSize) . "</p>";
    
    $lastLines = shell_exec("tail -5 " . escapeshellarg($logPath));
    echo "<p><strong>Last entries:</strong></p>";
    echo "<pre>" . htmlspecialchars($lastLines) . "</pre>";
} else {
    echo "<p>❌ Log file not found: $logPath</p>";
    $logDir = dirname($logPath);
    if (is_writable($logDir)) {
        echo "<p>✅ Log directory is writable</p>";
    } else {
        echo "<p>❌ Log directory is NOT writable</p>";
    }
}

echo "<hr>";

// 4. Check PHPMailer
echo "<h2>4. PHPMailer Installation</h2>";
$files = [
    __DIR__ . '/../PHPMailer/src/PHPMailer.php' => 'PHPMailer.php',
    __DIR__ . '/../PHPMailer/src/SMTP.php' => 'SMTP.php',
    __DIR__ . '/../PHPMailer/src/Exception.php' => 'Exception.php',
];

foreach ($files as $path => $name) {
    echo "<p>" . (file_exists($path) ? "✅" : "❌") . " $name: " . $path . "</p>";
}

echo "<hr>";

// 5. Test Email Send
echo "<h2>5. Send Test Email</h2>";
echo "<form method='POST'>";
echo "<input type='email' name='test_email' placeholder='Your email' required>";
echo "<button type='submit' name='send_test'>Send Test Email</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    require_once __DIR__ . '/includes/EmailSender.php';
    
    $testEmail = $_POST['test_email'];
    $mailer = new EmailSender();
    
    echo "<h3>Testing Email Send...</h3>";
    $result = $mailer->sendWelcomeEmail($testEmail, 'Test User');
    
    echo "<pre>";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    echo "<p>Check your email: <strong>$testEmail</strong></p>";
    echo "<p>Also check log file: " . EMAIL_LOG_PATH . "</p>";
}

function formatBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<style>
body { font-family: Arial; margin: 20px; }
h1 { color: #dc2626; }
h2 { color: #333; margin-top: 20px; }
table { width: 100%; border-collapse: collapse; }
td { padding: 10px; border: 1px solid #ddd; }
code { background: #f0f0f0; padding: 5px 10px; border-radius: 3px; display: block; margin: 10px 0; }
form { margin: 10px 0; }
input, button { padding: 8px; margin: 5px; }
button { background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #b91c1c; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>
