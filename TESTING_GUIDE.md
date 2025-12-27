# Notes Platform - Testing Guide

## Prerequisites

- XAMPP running (Apache + MySQL)
- Database `Notes_website` created
- Schema imported from `Schema.sql`
- PHP 7.4+

---

## Step 1: Database Setup

### 1.1 Start MySQL
```bash
# Open XAMPP Control Panel
# Click "Start" next to MySQL
# Or run command:
mysql -u root -p
```

### 1.2 Create Database
```sql
CREATE DATABASE IF NOT EXISTS Notes_website;
USE Notes_website;
```

### 1.3 Import Schema
```bash
# Via Command Line:
mysql -u root -p Notes_website < Schema.sql

# OR Via phpMyAdmin:
# 1. Go to http://localhost/phpmyadmin
# 2. Select "Notes_website" database
# 3. Click "Import"
# 4. Select Schema.sql file
# 5. Click "Go"
```

### 1.4 Verify Tables
```sql
USE Notes_website;
SHOW TABLES;
```

Expected output:
```
users
notes
assessments
papers
categories
```

---

## Step 2: Configure SMTP for Testing

### 2.1 Set Development Mode (No Email Sending)
Edit `config/smtp_config.php`:
```php
define('APP_ENV', 'development');  // Emails logged, not sent
```

### 2.2 Create Logs Directory
```bash
# PowerShell
mkdir c:\xampp\htdocs\Notes_Platform\logs

# OR Linux/Mac
mkdir -p /var/www/html/Notes_Platform/logs
chmod 755 /var/www/html/Notes_Platform/logs
```

### 2.3 Verify Config
Check file exists:
```
c:\xampp\htdocs\Notes_Platform\config\smtp_config.php
```

---

## Step 3: Test Registration

### 3.1 Access Registration Page
```
URL: http://localhost/Notes_Platform/public/register.php
```

### 3.2 Test Case 1: Valid Registration (Student)
```
Name: John Doe
Email: john@example.com
Password: Test@1234
Confirm Password: Test@1234
Role: Student
Profile Picture: (optional - upload any JPG/PNG under 1GB)
```

**Expected Results:**
- ✅ Form accepts all inputs
- ✅ Redirects to login page
- ✅ Shows "Registration successful!" message
- ✅ Email logged to `logs/email_log.txt`

### 3.3 Test Case 2: Valid Registration (Teacher)
```
Name: Jane Smith
Email: jane@example.com
Password: Teacher@5678
Confirm Password: Teacher@5678
Role: Teacher
```

**Expected Results:**
- ✅ Registration succeeds
- ✅ Can login as teacher later

### 3.4 Test Case 3: Validation - Weak Password
```
Name: Test User
Email: test@example.com
Password: weak
Confirm Password: weak
```

**Expected Results:**
- ❌ Form shows error: "Password must be at least 8 characters long"
- ❌ Form shows error: "Password must contain at least one uppercase letter"
- ❌ Form does not submit

### 3.5 Test Case 4: Validation - Email Already Exists
```
Name: Another User
Email: john@example.com  (already registered)
Password: Valid@9999
Confirm Password: Valid@9999
```

**Expected Results:**
- ❌ Form shows error: "This email is already registered"

### 3.6 Test Case 5: Validation - Mismatched Passwords
```
Name: User Test
Email: mismatch@example.com
Password: Match@1234
Confirm Password: Different@5678
```

**Expected Results:**
- ❌ Form shows error: "Passwords do not match"

### 3.7 Verify Email Logging
Check file: `logs/email_log.txt`

```json
{"time":"2025-11-26T10:30:45+00:00","to":"john@example.com","subject":"Welcome to Notes Platform","body":"Hi John Doe,\nWelcome to our Notes Platform!..."}
```

---

## Step 4: Test Login

### 4.1 Access Login Page
```
URL: http://localhost/Notes_Platform/public/login.php
```

### 4.2 Test Case 1: Valid Student Login
```
Email: john@example.com
Password: Test@1234
```

**Expected Results:**
- ✅ Login succeeds
- ✅ Redirected to `dashboard.php` (student dashboard)
- ✅ Shows welcome message with student's name
- ✅ Session created with `user_id`, `role`, `name`

### 4.3 Test Case 2: Valid Teacher Login
```
Email: jane@example.com
Password: Teacher@5678
```

**Expected Results:**
- ✅ Login succeeds
- ✅ Redirected to `teacher_dashboard.php`
- ✅ Teacher-specific options visible

### 4.4 Test Case 3: Invalid Credentials
```
Email: john@example.com
Password: WrongPassword123
```

**Expected Results:**
- ❌ Shows error: "Invalid email or password"
- ❌ Stays on login page
- ❌ No session created

### 4.5 Test Case 4: Non-existent Email
```
Email: nonexistent@example.com
Password: Any@Password1
```

**Expected Results:**
- ❌ Shows error: "Invalid email or password"

### 4.6 Test Case 5: Admin Login (if admin exists)
```
Email: notesshare@edu.in
Password: NotesShare
```

**Expected Results:**
- ✅ Redirected to `admin/dashboard.php` (if admin user exists in DB)
- ❌ Error message: "Admin account not found in database" (if admin not created)

---

## Step 5: Test Student Dashboard

### 5.1 After Login as Student
```
URL: http://localhost/Notes_Platform/public/dashboard.php
```

### 5.2 Verify Student Functions
- [ ] View student name in profile section
- [ ] View upload notes option
- [ ] View view notes option
- [ ] View view assessments option
- [ ] View download option
- [ ] Access edit profile

### 5.3 Test Upload Notes
```
1. Click "Upload Notes"
2. Fill form:
   - Title: "Physics Chapter 1"
   - Category: (select from dropdown)
   - Description: "Introduction to mechanics"
   - File: Select PDF/DOC under 1GB
3. Click "Upload"
```

**Expected Results:**
- ✅ File uploaded successfully
- ✅ Success message shown
- ✅ Note appears in "View Notes"

### 5.4 Test View Notes
```
1. Click "View Notes"
2. Should see list of all uploaded notes
3. Can download notes
4. Can delete own notes
```

---

## Step 6: Test Teacher Dashboard

### 6.1 After Login as Teacher
```
URL: http://localhost/Notes_Platform/public/teacher_dashboard.php
```

### 6.2 Verify Teacher Functions
- [ ] View teacher name in profile
- [ ] Upload assessments
- [ ] Upload papers
- [ ] View/manage assessments
- [ ] View/manage papers
- [ ] Download files

### 6.3 Test Upload Assessments
```
1. Click "Upload Assessments"
2. Fill form:
   - Title: "Midterm Exam Questions"
   - Description: "Section A & B"
   - File: Upload PDF
3. Submit
```

**Expected Results:**
- ✅ Assessment uploaded
- ✅ Appears in "View Assessments"

---

## Step 7: Test Admin Panel

### 7.1 Create Admin User (Manual)
```sql
USE Notes_website;
INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@example.com', '$2y$10$...', 'admin');
```

### 7.2 Access Admin Login
```
URL: http://localhost/Notes_Platform/public/admin/login.php
```

### 7.3 Test Admin Login
```
Email: admin@example.com
Password: (whatever you set)
```

**Expected Results:**
- ✅ Redirected to `admin/dashboard.php`

### 7.4 Verify Admin Functions
- [ ] Manage Users (view/delete/edit)
- [ ] Manage Notes (view/delete)
- [ ] Manage Categories
- [ ] Download Reports

---

## Step 8: Test File Operations

### 8.1 Profile Picture Upload
```
1. Register with profile picture
2. File: PNG/JPG/GIF under 1GB
3. Verify uploaded to: uploads/profiles/
```

### 8.2 Notes/Assessment Upload
```
1. Upload note/assessment
2. Verify file in: public/uploads/
3. Test download functionality
```

### 8.3 File Size Limit
```
1. Try uploading file > 1GB
2. Should show error: "File size must be less than 1GB"
```

---

## Step 9: Test Session & Security

### 9.1 Session Timeout Test
```
1. Login successfully
2. Wait 30 minutes (or adjust gc_maxlifetime)
3. Try to access dashboard
4. Should redirect to login
```

### 9.2 Direct URL Access (Without Login)
```
1. Logout first
2. Try to access: http://localhost/Notes_Platform/public/dashboard.php
3. Should redirect to login.php
```

### 9.3 HTTPS Cookie Security
```
1. Check config/config.php:
   - session.cookie_secure = isset($_SERVER['HTTPS'])
2. When on HTTPS, cookies should be secure
3. When on HTTP (local), cookies work normally
```

---

## Step 10: Database Verification

### 10.1 Check User Records
```sql
SELECT * FROM users;
```

Expected columns:
- id, name, email, password (hashed), role, profile_picture, created_at, updated_at

### 10.2 Check Uploaded Notes
```sql
SELECT * FROM notes;
```

Expected columns:
- id, user_id, title, category_id, description, file_path, created_at

### 10.3 Check Relationships
```sql
SELECT u.name, n.title FROM users u 
JOIN notes n ON u.id = n.user_id;
```

---

## Step 11: Email Testing

### 11.1 Check Development Mode Log
```
File: logs/email_log.txt
```

Each registration should create a JSON entry:
```json
{
  "time": "2025-11-26T10:35:22+00:00",
  "to": "user@example.com",
  "subject": "Welcome to Notes Platform",
  "body": "Hi User Name,\nWelcome to our Notes Platform!..."
}
```

### 11.2 Switch to Production Mode (Optional)
Edit `config/smtp_config.php`:
```php
define('APP_ENV', 'production');
define('SMTP_PASSWORD', 'YOUR_GMAIL_APP_PASSWORD');
```

Then register user - real email should be sent.

---

## Step 12: Error Handling Tests

### 12.1 Database Connection Failure
```
1. Edit config/config.php - change DB password to wrong value
2. Try to access any page
3. Should show: "Database Connection Failed"
```

### 12.2 Missing Required Fields
```
1. Register form - leave fields empty
2. Should show validation errors
```

### 12.3 Invalid File Types
```
1. Try uploading .exe or .txt file
2. Should reject with type validation error
```

---

## Quick Checklist

```
SETUP:
[ ] Database created and schema imported
[ ] XAMPP running (Apache + MySQL)
[ ] logs/ directory created and writable
[ ] APP_ENV set to 'development'

REGISTRATION:
[ ] Valid registration works
[ ] Validation errors show correctly
[ ] Duplicate email detected
[ ] Email logged to file
[ ] Redirects to login

LOGIN:
[ ] Valid credentials work
[ ] Invalid credentials rejected
[ ] Session created
[ ] Redirect to correct dashboard

STUDENT:
[ ] Can access student dashboard
[ ] Can upload notes
[ ] Can view notes
[ ] Can download files

TEACHER:
[ ] Can access teacher dashboard
[ ] Can upload assessments
[ ] Can upload papers

SECURITY:
[ ] Session timeout works
[ ] Unauthorized access blocked
[ ] Passwords hashed (bcrypt)
[ ] CSRF protection in place

DATABASE:
[ ] Users table populated
[ ] Notes/assessments stored correctly
[ ] Relationships intact
```

---

## Troubleshooting

### Issue: "Database Connection Failed"
- Check MySQL is running
- Verify credentials in `config/config.php`
- Ensure database exists: `SHOW DATABASES;`

### Issue: "Emails not being sent/logged"
- Check `logs/` directory exists
- Check directory permissions: `chmod 755 logs/`
- Check `APP_ENV` is set to 'development'

### Issue: "File upload fails"
- Check `public/uploads/` exists
- Check directory is writable: `chmod 755 public/uploads/`
- Check file size < 1GB
- Check file type is allowed

### Issue: "Session not persisting"
- Check cookies enabled in browser
- Check session save path in php.ini
- Clear browser cookies and try again

---

## Test Data Summary

| Field | Value | Notes |
|-------|-------|-------|
| Student Email | john@example.com | Password: Test@1234 |
| Teacher Email | jane@example.com | Password: Teacher@5678 |
| Database | Notes_website | Charset: utf8 |
| Upload Size | 1GB max | For all files |
| Password Rules | 8+ chars, 1 uppercase, 1 lowercase, 1 number, 1 special | Bcrypt hashed |

