# Forgot Password Feature - Setup Instructions

## What's New

✅ **3 new files created:**
1. `public/forgot_password.php` - User enters email to request password reset
2. `public/reset_password.php` - User creates new password after clicking email link
3. `add_reset_token.sql` - Database migration to add reset token columns

---

## Step 1: Update Database

Run this SQL to add the reset token columns:

**Option A: Via phpMyAdmin**
1. Go to http://localhost/phpmyadmin
2. Select `Notes_website` database
3. Click "SQL" tab
4. Copy and paste this SQL:

```sql
ALTER TABLE users ADD reset_token VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD reset_token_expires DATETIME DEFAULT NULL;
```

5. Click "Go"

**Option B: Via MySQL Command Line**
```bash
mysql -u root -p Notes_website < add_reset_token.sql
```

**Option C: Direct MySQL**
```bash
mysql -u root -p
USE Notes_website;
ALTER TABLE users ADD reset_token VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD reset_token_expires DATETIME DEFAULT NULL;
```

---

## Step 2: Verify Database Changes

Check the users table has new columns:

```sql
DESCRIBE users;
```

You should see:
- `reset_token` (VARCHAR 255)
- `reset_token_expires` (DATETIME)

---

## How It Works

### User Flow:

1. **User clicks "Forgot your password?" on login page**
   - Goes to: `public/forgot_password.php`

2. **User enters email address**
   - System generates unique reset token
   - Token stored in database with 1-hour expiry
   - Reset link emailed to user

3. **User clicks link in email**
   - Link: `http://localhost/Notes_Platform/public/reset_password.php?token=ABC123XYZ...`
   - Opens: `public/reset_password.php`

4. **User creates new password**
   - Password must meet requirements (8+ chars, uppercase, lowercase, number, special char)
   - Password confirmed
   - New password hashed and stored in database
   - Reset token cleared

5. **User logs in with new password**
   - Login as normal

---

## Testing the Feature

### Test Case 1: Valid Password Reset

```
1. Go to: http://localhost/Notes_Platform/public/login.php
2. Click "Forgot your password?"
3. Enter registered email (e.g., john@example.com)
4. Click "Send Reset Link"
5. Check your email inbox
6. Click the reset link
7. Enter new password:
   - New Password: NewPass@123
   - Confirm: NewPass@123
8. Click "Reset Password"
9. Success! Go back to login
10. Login with new password
```

**Expected Results:**
- ✅ Email received with reset link
- ✅ Link is valid and works
- ✅ Password form appears
- ✅ Can reset password
- ✅ Can login with new password

### Test Case 2: Expired Token

```
1. Generate reset token
2. Wait (token expires after 1 hour)
3. Click email link
```

**Expected Results:**
- ❌ Shows: "Reset link has expired or is invalid"

### Test Case 3: Invalid Email

```
1. Go to forgot password page
2. Enter: nonexistent@example.com
3. Click "Send Reset Link"
```

**Expected Results:**
- ✅ Shows: "If an account exists with this email..." (security message - doesn't reveal if email exists)

### Test Case 4: Weak Password

```
1. Click reset link
2. Enter weak password: "abc123"
3. Click "Reset Password"
```

**Expected Results:**
- ❌ Shows validation error: "Password must be at least 8 characters long"
- ❌ And: "Password must contain at least one uppercase letter"
- ❌ And other requirements

### Test Case 5: Mismatched Passwords

```
1. Click reset link
2. Password: NewPass@123
3. Confirm: Different@456
4. Click "Reset Password"
```

**Expected Results:**
- ❌ Shows: "Passwords do not match"

---

## Email Template

When user requests password reset, they receive:

**Subject:** Password Reset Request

**Body:**
```
Hi [User Name],

Reset your password here:
[Click to Reset Password]
(Link with unique token)

If you didn't request this, ignore it.
```

---

## Security Features

✅ **Unique Tokens** - Each reset is unique and unpredictable
✅ **Time Expiry** - Tokens expire after 1 hour
✅ **Email Verification** - Reset link sent to registered email only
✅ **Password Hashing** - New password hashed with bcrypt
✅ **Security Message** - Don't reveal if email exists/not exists
✅ **Token Cleanup** - Token removed after successful reset

---

## Files Modified

| File | Changes |
|------|---------|
| `public/login.php` | Already has "Forgot password?" link (no changes needed) |
| `public/forgot_password.php` | **NEW** - Request password reset |
| `public/reset_password.php` | **NEW** - Reset password form |
| `config/smtp_config.php` | Already configured for email sending |
| `public/includes/EmailSender.php` | Already has `sendPasswordResetEmail()` method |
| `add_reset_token.sql` | **NEW** - Database migration |

---

## Troubleshooting

### Issue: "Invalid reset link"
- **Cause**: Token doesn't exist or is invalid
- **Fix**: Request a new reset email

### Issue: "Reset link has expired"
- **Cause**: Token is older than 1 hour
- **Fix**: Request a new reset email

### Issue: Password reset email not received
- **Check**:
  1. Check spam folder
  2. Verify `APP_ENV = 'production'` in `config/smtp_config.php`
  3. Verify SMTP credentials are correct
  4. Check `logs/email_log.txt` for errors

### Issue: Token not found in database
- **Check**:
  1. Verify database migration was applied
  2. Run: `DESCRIBE users;` to confirm `reset_token` column exists
  3. Check database error logs

---

## Next Steps (Optional)

You can also implement:
- Password reset request rate limiting (prevent spam)
- Email confirmation before password change
- Admin ability to reset user passwords
- Password reset history logging
- Email notifications for suspicious activity

