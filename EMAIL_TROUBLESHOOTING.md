# Email Troubleshooting Guide

## Issues Fixed

### 1. **Missing SMTP Config Import**
- **Problem**: `register.php` and `login.php` were not loading `smtp_config.php`, causing SMTP constants to be undefined
- **Fix**: Added `require_once __DIR__ . '/../config/smtp_config.php';` to both files

### 2. **No Error Logging**
- **Problem**: Email sending silently failed with no debugging information
- **Fix**: Added try-catch blocks and error logging to `register.php`

### 3. **Environment Mode Set to Production**
- **Status**: Changed to `'development'` mode in `smtp_config.php` for testing
- **Action**: When you're ready for production, change it back to `'production'`

---

## How It Works Now

### Development Mode (Current Setting)
- Emails are NOT sent via SMTP
- Instead, emails are logged to: `logs/email_log.txt`
- Each entry is JSON format with timestamp, recipient, subject, and body
- No Gmail SMTP connection needed

### Production Mode (For Later)
- Real emails sent via Gmail SMTP
- Requires valid SMTP credentials in `config/smtp_config.php`
- Gmail App Passwords required (not regular password)

---

## Testing Email Sending

### Check Email Log File
```
logs/email_log.txt
```

Each registration/login attempt will append a JSON entry showing:
```json
{
  "time": "2025-11-26T10:30:45+00:00",
  "to": "user@example.com",
  "subject": "Welcome to Notes Platform",
  "body": "Hi User,..."
}
```

### Verify in PHP Error Log
```
tail -f /path/to/php_error_log
```

Look for log entries like:
```
Registration email sent to user@example.com: {"success":true,"message":"Logged (development mode)"}
```

---

## When Ready for Production

### Step 1: Generate Gmail App Password
1. Go to https://myaccount.google.com
2. Security → App Passwords
3. Select Mail and Windows Computer
4. Copy the 16-character password

### Step 2: Update SMTP Config
Edit `config/smtp_config.php`:
```php
define('APP_ENV', 'production');  // Enable production mode
define('SMTP_PASSWORD', 'YOUR_16_CHAR_APP_PASSWORD'); // Use generated password
define('BASE_URL', 'https://yourdomain.com'); // Update with actual domain
```

### Step 3: Verify Settings
- SMTP_HOST: `smtp.gmail.com`
- SMTP_PORT: `587`
- SMTP_SECURE: `tls`
- SMTP_USERNAME: Your Gmail address
- SMTP_PASSWORD: 16-character app password

---

## Common Issues

### Issue: "Invalid email" error
- **Cause**: Email format validation failed
- **Fix**: Use valid email format (user@domain.com)

### Issue: "SMTP error" in production mode
- **Possible Causes**:
  1. Invalid app password
  2. Gmail account has 2FA disabled
  3. Firewall blocking port 587
  4. `allow_url_fopen` disabled in php.ini

### Issue: Log file not created
- **Fix**: Ensure `logs/` directory exists and is writable
  ```bash
  mkdir -p /path/to/logs
  chmod 755 /path/to/logs
  ```

---

## Files Modified
- `public/register.php` - Added SMTP config import and error logging
- `public/login.php` - Added SMTP config import
- `config/smtp_config.php` - Changed to development mode
- `public/includes/EmailSender.php` - Already properly configured

