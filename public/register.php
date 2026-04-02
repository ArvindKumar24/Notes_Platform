<?php
require_once("../config/config.php");
require_once __DIR__ . '/includes/EmailSender.php';
require_once __DIR__ . '/../config/smtp_config.php';

$errors = [];
$success = "";

// Predefined courses
$courses = [
    'BscIT',
    'BMS', 
    'Bcom',
    'BA Psychology',
    'MscIT',
    'BCA',
    
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';
    $role = $_POST["role"] ?? "student";
    $course = trim($_POST["course"] ?? '');

    // Name validation
    if (empty($name)) {
        $errors['name'] = "Full name is required.";
    } elseif (strlen($name) < 2) {
        $errors['name'] = "Name must be at least 2 characters long.";
    } elseif (strlen($name) > 100) {
        $errors['name'] = "Name must not exceed 100 characters.";
    } elseif (!preg_match("/^[a-zA-Z\s\.\-']+$/", $name)) {
        $errors['name'] = "Name can only contain letters, spaces, hyphens, and apostrophes.";
    }

    // Email validation
    if (empty($email)) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    } elseif (strlen($email) > 150) {
        $errors['email'] = "Email must not exceed 150 characters.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = "This email is already registered.";
        }
    }

    // Password validation
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } else {
        if (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters long.";
        }
        if (!preg_match("/[A-Z]/", $password)) {
            $errors['password'] = "Password must contain at least one uppercase letter.";
        }
        if (!preg_match("/[a-z]/", $password)) {
            $errors['password'] = "Password must contain at least one lowercase letter.";
        }
        if (!preg_match("/[0-9]/", $password)) {
            $errors['password'] = "Password must contain at least one number.";
        }
        if (!preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
            $errors['password'] = "Password must contain at least one special character.";
        }
    }

    // Confirm password validation
    if (empty($confirm_password)) {
        $errors['confirm_password'] = "Please confirm your password.";
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // Role validation
    if (!in_array($role, ['student', 'teacher'])) {
        $errors['role'] = "Please select a valid role.";
    }

    // Course validation for students
    if ($role === 'student' && empty($course)) {
        $errors['course'] = "Please select your course.";
    }

    // Profile Picture Upload (keep existing code)
    $profilePicturePath = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024;

            if (!in_array($file['type'], $allowedTypes)) {
                $errors['profile_picture'] = "Only JPG, PNG, GIF, and WebP images are allowed.";
            } elseif ($file['size'] > $maxSize) {
                $errors['profile_picture'] = "Image size must be less than 5MB.";
            } else {
                $uploadDir = __DIR__ . '/profile_pictures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'profile_' . uniqid() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $profilePicturePath = $fileName;
                } else {
                    $errors['profile_picture'] = "Failed to upload profile picture.";
                }
            }
        }
    }

    // If no errors → Register user
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, course, profile_picture) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password, $role, $course, $profilePicturePath]);

            // Send email after successful registration
            try {
                $mailer = new EmailSender();
                $emailResult = $mailer->sendWelcomeEmail($email, $name, $password);
                error_log('Registration email sent to ' . $email);
            } catch (Exception $e) {
                error_log('Registration email error: ' . $e->getMessage());
            }

            $_SESSION["success"] = "Registration successful! You can now login.";
            header("Location: login.php");
            exit;

        } catch (PDOException $e) {
            $errors['general'] = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = "Register - Notes Platform";
include("includes/header.php");
?>

<style>
body {
    background: linear-gradient(135deg, #eef2ff, #f8fafc);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Container */
.register-container {
    max-width: 600px;
    margin: 0 auto;
}

/* Card */
.register-card {
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 15px 35px rgba(0,0,0,0.08);
    border: none;
    transition: 0.3s ease;
}

.register-card:hover {
    transform: translateY(-4px);
}

/* Header */
.register-header {
    text-align: center;
    margin-bottom: 2rem;
}

.register-header h3 {
    font-weight: 700;
    color: #1e293b;
}

.register-header p {
    font-size: 14px;
}

/* Inputs */
.form-control, .form-select {
    border-radius: 12px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    transition: 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}

/* Button */
.btn-primary {
    border-radius: 12px;
    padding: 12px;
    font-weight: 600;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border: none;
    transition: 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #4338ca);
}

/* Password strength */
.password-strength {
    height: 6px;
    border-radius: 5px;
    margin-top: 6px;
    transition: all 0.3s ease;
}

.strength-weak { background: #ef4444; width: 25%; }
.strength-fair { background: #f59e0b; width: 50%; }
.strength-good { background: #3b82f6; width: 75%; }
.strength-strong { background: #10b981; width: 100%; }

/* Password toggle */
.password-toggle {
    cursor: pointer;
    position: absolute;
    right: 12px;
    top: 60%;
    transform: translateY(-50%);
    color: #64748b;
}

/* Profile Image */
.profile-picture-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #6366f1;
    display: none;
    margin: 15px auto;
}

.profile-picture-preview.show {
    display: block;
}

/* File Upload */
.file-input-label {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 14px;
    background: #f8fafc;
    border: 2px dashed #c7d2fe;
    border-radius: 12px;
    cursor: pointer;
    transition: 0.3s ease;
}

.file-input-label:hover {
    background: #eef2ff;
    border-color: #6366f1;
}

.file-input-label i {
    margin-right: 8px;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    opacity: 0;
}

/* Alerts */
.alert {
    border-radius: 10px;
    font-size: 14px;
}

/* Links */
a {
    color: #6366f1;
}

a:hover {
    text-decoration: underline;
}
</style>

<div class="register-container py-5">
    <div class="register-card p-5">
        <div class="register-header">
            <h3>Create Account</h3>
            <p class="text-muted mb-0">Join our learning community</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>

        <form method="POST" id="registrationForm" enctype="multipart/form-data">
            <!-- Profile Picture Field -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Profile Picture (Optional)</label>
                <div class="file-input-wrapper">
                    <label for="profile_picture" class="file-input-label">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <span>Click to upload profile picture</span>
                    </label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this)">
                </div>
                <img id="profilePreview" class="profile-picture-preview" alt="Profile preview">
                <small class="text-muted">Max size: 5MB (JPG, PNG, GIF, WebP)</small>
            </div>

            <!-- Name Field -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Full Name<span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                       name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback d-block"><?php echo $errors['name']; ?></div>
                <?php endif; ?>
            </div>

            <!-- Email Field -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address<span class="text-danger">*</span></label>
                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                       name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold">Password<span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                               name="password" required minlength="8">
                        <span class="password-toggle" onclick="togglePassword(this)">
                            <i class="bi bi-eye"></i>
                        </span>
                        <div id="passwordStrength" class="password-strength"></div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold">Confirm Password<span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                               name="confirm_password" required minlength="8">
                        <span class="password-toggle" onclick="togglePassword(this)">
                            <i class="bi bi-eye"></i>
                        </span>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['confirm_password']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Role Field -->
            <div class="mb-3">
                <label class="form-label fw-semibold">I am a:<span class="text-danger">*</span></label>
                <select class="form-select" name="role" id="role" required onchange="toggleCourseField()">
                    <option value="">Select your role</option>
                    <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo ($_POST['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                </select>
            </div>

            <!-- Course Field (for students only) -->
            <div class="mb-3" id="courseField" style="display: none;">
                <label class="form-label fw-semibold">Course<span class="text-danger">*</span></label>
                <select class="form-select <?php echo isset($errors['course']) ? 'is-invalid' : ''; ?>" name="course">
                    <option value="">Select your course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo ($_POST['course'] ?? '') === $c ? 'selected' : ''; ?>>
                            <?php echo $c; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['course'])): ?>
                    <div class="invalid-feedback d-block"><?php echo $errors['course']; ?></div>
                <?php endif; ?>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
            </div>
        </form>

        <div class="text-center">
            <p class="text-muted mb-0">
                Already have an account?
                <a href="login.php" class="text-decoration-none fw-bold">Login here</a>
            </p>
        </div>
    </div>
</div>

<script>
function toggleCourseField() {
    const role = document.getElementById('role').value;
    const courseField = document.getElementById('courseField');
    courseField.style.display = role === 'student' ? 'block' : 'none';
}

function togglePassword(icon) {
    const input = icon.parentElement.querySelector('input');
    if (input.type === 'password') {
        input.type = 'text';
        icon.querySelector('i').className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.querySelector('i').className = 'bi bi-eye';
    }
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('profilePreview');
            preview.src = e.target.result;
            preview.classList.add('show');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Password strength checker
document.querySelector('input[name="password"]').addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[!@#$%^&*()]/.test(password)) strength++;
    
    const strengthBar = document.getElementById('passwordStrength');
    strengthBar.style.display = password.length > 0 ? 'block' : 'none';
    strengthBar.className = 'password-strength';
    
    if (strength <= 2) strengthBar.classList.add('strength-weak');
    else if (strength === 3) strengthBar.classList.add('strength-fair');
    else if (strength === 4) strengthBar.classList.add('strength-good');
    else if (strength >= 5) strengthBar.classList.add('strength-strong');
});

// Call on page load
document.addEventListener('DOMContentLoaded', toggleCourseField);
</script>

<?php include("includes/footer.php"); ?>