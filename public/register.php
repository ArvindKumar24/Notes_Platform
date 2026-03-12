<?php
require_once("../config/config.php");
require_once __DIR__ . '/includes/EmailSender.php';
require_once __DIR__ . '/../config/smtp_config.php';

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';
    $role = $_POST["role"] ?? "student";

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
            $errors['email'] = "This email is already registered. Please use a different email or login.";
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
        if (strlen($password) > 255) {
            $errors['password'] = "Password must not exceed 255 characters.";
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

    // Profile Picture Upload
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
                $uploadDir = '../uploads/profiles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'profile_' . uniqid() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $profilePicturePath = 'uploads/profiles/' . $fileName;
                } else {
                    $errors['profile_picture'] = "Failed to upload profile picture.";
                }
            }
        } else {
            $errors['profile_picture'] = "Error uploading profile picture.";
        }
    }

    // If no errors → Register user
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, profile_picture) VALUES (?, ?, ?, ?, ?)");

            if ($stmt->execute([$name, $email, $hashed_password, $role, $profilePicturePath])) {

                // Send email after successful registration
                try {
                    $mailer = new EmailSender();
                    $emailResult = $mailer->sendWelcomeEmail($email, $name, $password);
                    error_log('Registration email sent to ' . $email . ': ' . json_encode($emailResult));
                } catch (Exception $e) {
                    error_log('Registration email error: ' . $e->getMessage());
                }

                $_SESSION["success"] = "Registration successful! You can now login to your account.";
                header("Location: login.php");
                exit;

            } else {
                $errors['general'] = "Registration failed. Please try again.";
            }
        } catch (PDOException $e) {
            $errors['general'] = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = "Register - Notes Platform";
include("includes/header.php");
?>

<style>
    .register-container {
        max-width: 600px;
        margin: 0 auto;
    }
    .register-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .register-header {
        text-align: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }
    .register-header h3 {
        color: #212529;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .password-strength {
        height: 4px;
        margin-top: 5px;
        border-radius: 2px;
        transition: all 0.3s ease;
    }
    .strength-weak { background-color: #F59E0B; width: 25%; }
    .strength-fair { background-color: #38BDF8; width: 50%; }
    .strength-good { background-color: #14B8A6; width: 75%; }
    .strength-strong { background-color: #14B8A6; width: 100%; }
    .password-toggle {
        cursor: pointer;
        position: absolute;
        right: 12px;
        top: 61%;
        transform: translateY(-50%);
        color: #6c757d;
    }
    .profile-picture-preview {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #dee2e6;
        display: none;
        margin: 15px auto;
    }
    .profile-picture-preview.show {
        display: block;
    }
    .file-input-wrapper input[type="file"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    .file-input-label {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 12px;
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }
    .file-input-label:hover {
        background: #f0f0f0;
        border-color: #adb5bd;
    }
    .file-input-label i {
        margin-right: 8px;
    }
</style>

<div class="register-container py-5">
    <div class="register-card p-5">
        <!-- Register Header -->
        <div class="register-header">
            <h3>Create Account</h3>
            <p class="text-muted mb-0">Join our learning community</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errors['general']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
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
                <small class="text-muted d-block mt-2">Max size: 5MB (JPG, PNG, GIF, WebP)</small>
                <?php if (isset($errors['profile_picture'])): ?>
                    <div class="alert alert-danger mt-2 mb-0">
                        <?php echo htmlspecialchars($errors['profile_picture']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Name Field -->
            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Full Name<span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                       id="name" name="name" required
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                       placeholder="Enter your full name">
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback d-block">
                        <?php echo htmlspecialchars($errors['name']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Email Field -->
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">Email Address<span class="text-danger">*</span></label>
                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                       id="email" name="email" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="Enter your email">
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback d-block">
                        <?php echo htmlspecialchars($errors['email']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <!-- Password Field -->
                <div class="col-md-6">
                    <div class="mb-3 position-relative">
                        <label for="password" class="form-label fw-semibold">Password<span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                               id="password" name="password" required minlength="8"
                               placeholder="Create password">
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <i class="bi bi-eye"></i>
                        </span>
                        <div id="passwordStrength" class="password-strength"></div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback d-block">
                                <?php echo htmlspecialchars($errors['password']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Confirm Password Field -->
                <div class="col-md-6">
                    <div class="mb-3 position-relative">
                        <label for="confirm_password" class="form-label fw-semibold">Confirm Password<span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                               id="confirm_password" name="confirm_password" required minlength="8"
                               placeholder="Confirm password">
                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="bi bi-eye"></i>
                        </span>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback d-block">
                                <?php echo htmlspecialchars($errors['confirm_password']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Role Field -->
            <div class="mb-4">
                <label for="role" class="form-label fw-semibold">I am a:<span class="text-danger">*</span></label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">Select your role</option>
                    <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo ($_POST['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                </select>
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

<?php include("includes/footer.php"); ?>
    <script>
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

        function checkPasswordStrength(password) {
            let strength = 0;
            const strengthBar = document.getElementById('passwordStrength');
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[!@#$%^&*(),.?\":{}|<>]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength';
            if (password.length === 0) {
                strengthBar.style.display = 'none';
            } else {
                strengthBar.style.display = 'block';
                if (strength <= 2) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength === 3) {
                    strengthBar.classList.add('strength-fair');
                } else if (strength === 4) {
                    strengthBar.classList.add('strength-good');
                } else {
                    strengthBar.classList.add('strength-strong');
                }
            }
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        document.getElementById('password').addEventListener('input', function(e) {
            checkPasswordStrength(e.target.value);
            validatePasswordMatch();
        });

        document.getElementById('confirm_password').addEventListener('input', validatePasswordMatch);

        function validatePasswordMatch() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const msg = confirmPassword.parentElement.querySelector('.match-error');
            
            if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.classList.add('is-invalid');
                if (!msg) {
                    const error = document.createElement('div');
                    error.className = 'invalid-feedback d-block match-error';
                    error.textContent = 'Passwords do not match';
                    confirmPassword.parentElement.appendChild(error);
                }
            } else {
                confirmPassword.classList.remove('is-invalid');
                if (msg) msg.remove();
            }
        }

        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                validatePasswordMatch();
            }
        });

        document.getElementById('name').addEventListener('input', function(e) {
            const name = e.target.value;
            const nameRegex = /^[a-zA-Z\s\.\-']+$/;
            
            if (name && !nameRegex.test(name)) {
                e.target.classList.add('is-invalid');
            } else {
                e.target.classList.remove('is-invalid');
            }
        });
    </script>