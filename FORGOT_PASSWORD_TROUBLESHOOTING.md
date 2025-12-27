# Forgot Password Email - Troubleshooting Guide

## Issue: Password Reset Link Not Coming in Email

---

## Quick Diagnosis (3 Steps)

### Step 1: Run Diagnostics Page
```
URL: http://localhost/Notes_Platform/public/diagnostics.php
```

This will show you:
- ✅ SMTP Configuration status
- ✅ Database reset token columns status
- ✅ Email log file status
- ✅ PHPMailer installation status
- ✅ Test email send functionality

---

## Common Issues & Fixes

### Issue 1: Database Columns Missing
**Symptom:** Token not stored in database

**Check:** Open `diagnostics.php` - it will show if `reset_token` columns are missing

**Fix:** Run this SQL in phpMyAdmin:
```sql
ALTER TABLE users ADD reset_token VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD reset_token_expires DATETIME DEFAULT NULL;
```

**Verify:**
```sql
DESCRIBE users;
```

---

### Issue 2: SMTP Configuration Error
**Symptom:** Email not sent, no error message

**Check:** Go to `diagnostics.php` and check SMTP settings

**Fix:** Verify in `config/smtp_config.php`:
```php
define('APP_ENV', 'production');  // Must be production
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'akav0786@gmail.com');
define('SMTP_PASSWORD', 'dmjd nvbi jvvu nipy');  // App password
define('SMTP_SECURE', 'tls');
```

---

### Issue 3: Gmail App Password Issue
**Symptom:** "Could not connect to SMTP host"

**Fix:** 
1. Go to: https://myaccount.google.com/security
2. Enable 2-Factor Authentication (if not enabled)
3. Go to "App passwords"
4. Select **Mail** and **Windows Computer**
5. Copy the 16-character password
6. Update `SMTP_PASSWORD` in `config/smtp_config.php`

**Test:** Go to `diagnostics.php` and send test email

---

### Issue 4: Email Link Not Correct
**Symptom:** Email received but link doesn't work

**Check:** In `public/includes/EmailSender.php`, verify:
```php
$resetLink = BASE_URL . "/public/reset_password.php?token=" . urlencode($token);
```

Should produce URL like:
```
http://localhost/Notes_Platform/public/reset_password.php?token=ABC123XYZ...
```

---

### Issue 5: Log File Not Writable
**Symptom:** Errors but no log file

**Fix:**
```bash
# Create logs directory
mkdir c:\xampp\htdocs\Notes_Platform\logs

# Give write permissions
cd c:\xampp\htdocs\Notes_Platform\logs
attrib -r *
```

---

## Step-by-Step Testing

### Test 1: Check Database Setup
```
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select database: Notes_website
3. Click table: users
4. Check columns - should see:
   - reset_token (VARCHAR 255)
   - reset_token_expires (DATETIME)
```

**If NOT there:**
```sql
ALTER TABLE users ADD reset_token VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD reset_token_expires DATETIME DEFAULT NULL;
```

### Test 2: Run Diagnostics
```
1. Go to: http://localhost/Notes_Platform/public/diagnostics.php
2. Check all green checkmarks (✅)
3. If any red X (❌), follow the fix instructions
```

### Test 3: Send Test Email
```
1. Still on diagnostics.php page
2. Enter your email in the form
3. Click "Send Test Email"
4. Check if you receive the email
5. Check logs: c:\xampp\htdocs\Notes_Platform\logs\email_log.txt
```

### Test 4: Test Forgot Password Flow
```
1. Go to: http://localhost/Notes_Platform/public/login.php
2. Click "Forgot your password?"
3. Enter a registered user's email
4. Click "Send Reset Link"
5. Check email inbox for "Password Reset Request"
6. Click the link in the email
7. Should open reset form with URL like:
   http://localhost/Notes_Platform/public/reset_password.php?token=ABC123...
```

---

## Check Email Log for Errors

### View Log File:
```
File: c:\xampp\htdocs\Notes_Platform\logs\email_log.txt
```

### Log Entry Examples:

**Success:**
```json
{"time":"2025-11-26T10:30:45+00:00","to":"user@gmail.com","subject":"Password Reset Request","body":"SUCCESS: Email sent"}
```

**Error:**
```json
{"time":"2025-11-26T10:30:45+00:00","to":"user@gmail.com","subject":"Password Reset Request","body":"ERROR: Could not connect to SMTP host"}
```

---

## PHP Error Log

Check for errors:
```bash
# View XAMPP PHP error log
type C:\xampp\php\logs\php_error_log

# Or check recent errors
tail -20 C:\xampp\php\logs\php_error_log
```

---

## Manual Testing Script

Create a test file to manually test the email system:

1. Create: `c:\xampp\htdocs\Notes_Platform\test_email.php`

```php
<?php
require_once("config/config.php");
require_once "public/includes/EmailSender.php";

// Test email
$mailer = new EmailSender();
$result = $mailer->sendPasswordResetEmail('your-email@gmail.com', 'Test User', 'ABC123TOKEN');

echo "<pre>";
print_r($result);
echo "</pre>";

// Check log
echo "<h2>Email Log:</h2>";
echo "<pre>";
echo file_get_contents("logs/email_log.txt");
echo "</pre>";
?>
```

2. Open: `http://localhost/Notes_Platform/test_email.php`

---

## Checklist

```
DATABASE:
[ ] reset_token column exists
[ ] reset_token_expires column exists

SMTP CONFIG:
[ ] APP_ENV = 'production'
[ ] SMTP_HOST = 'smtp.gmail.com'
[ ] SMTP_PORT = 587
[ ] SMTP_SECURE = 'tls'
[ ] SMTP_USERNAME = Gmail address
[ ] SMTP_PASSWORD = Gmail app password (16 chars)
[ ] BASE_URL = correct path

FILES:
[ ] forgot_password.php exists
[ ] reset_password.php exists
[ ] EmailSender.php has sendPasswordResetEmail() method
[ ] PHPMailer installed in PHPMailer/src/

PERMISSIONS:
[ ] logs/ directory writable
[ ] logs/email_log.txt writable (or can be created)

EMAIL:
[ ] Gmail app password is valid
[ ] 2FA enabled on Gmail
[ ] No firewall blocking port 587
```

---

## Still Not Working?

### Try these fixes in order:

**1. Clear Logs & Test Again**
```bash
# Delete old log
del c:\xampp\htdocs\Notes_Platform\logs\email_log.txt

# Try forgot password again
```

**2. Try Different Port/Security**
Edit `config/smtp_config.php`:
```php
// Try SSL instead of TLS
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
```

**3. Check Gmail Account**
- Go to: https://myaccount.google.com/security
- Check "Less secure app access" is enabled
- Regenerate App Password
- Try again

**4. Check Firewall/Antivirus**
- Port 587 (or 465) might be blocked
- Check antivirus/firewall settings
- Try disabling temporarily to test

**5. Enable PHP Debug Mode**
Add to `config/smtp_config.php`:
```php
define('APP_ENV', 'development');  // Temporarily log instead of send
```

Then test - emails will be logged to `logs/email_log.txt` instead

---

## Support Information

When asking for help, provide:

1. Screenshot of `diagnostics.php` output
2. Contents of `logs/email_log.txt` (last 5 entries)
3. SMTP settings from `config/smtp_config.php` (password hidden)
4. Error message from browser (if any)
5. PHP error log contents

