<?php
// SMTP configuration

define('APP_ENV', 'production');  // set to "development" for logging-only mode, "production" for real emails

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'akav0786@gmail.com');
define('SMTP_PASSWORD', 'dmjd nvbi jvvu nipy');  // Use Gmail App Password (16 characters)
define('SMTP_SECURE', 'tls'); // tls or ssl

define('SMTP_FROM_EMAIL', 'akav0786@gmail.com');
define('SMTP_FROM_NAME', 'Notes Platform');

// Base URL for links (reset password etc.)
// define('BASE_URL', 'http://localhost/Notes_Platform');  // Update to your actual domain when deployed

// Log file path (still logs for backup/debugging)
define('EMAIL_LOG_PATH', __DIR__ . '/../logs/email_log.txt');
