<?php
require_once("../config/config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = "";
$errorType = "danger";

// Fetch current user data
$userStmt = $pdo->prepare("SELECT name, email, role, profile_picture FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    if ($action === "update_info") {
        $name = trim($_POST["name"] ?? '');
        $email = trim($_POST["email"] ?? '');
        
        if (empty($name)) {
            $message = "Name cannot be empty.";
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $message = "Name must be between 2 and 100 characters.";
        } elseif (!preg_match("/^[a-zA-Z\\s\\.\\-']+$/", $name)) {
            $message = "Name can only contain letters, spaces, hyphens, and apostrophes.";
        } elseif (empty($email)) {
            $message = "Email cannot be empty.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$email, $userId]);
            
            if ($checkStmt->fetch()) {
                $message = "This email is already in use by another account.";
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                if ($updateStmt->execute([$name, $email, $userId])) {
                    $_SESSION["name"] = $name;
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $message = "Profile information updated successfully!";
                    $errorType = "success";
                } else {
                    $message = "Failed to update profile. Please try again.";
                }
            }
        }
    } elseif ($action === "update_picture") {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 100 * 1024 * 1024;

            if (!in_array($file['type'], $allowedTypes)) {
                $message = "Only JPG, PNG, GIF, and WebP images are allowed.";
            } elseif ($file['size'] > $maxSize) {
                $message = "Image size must be less than 1GB.";
            } else {
                if (!empty($user['profile_picture']) && file_exists("../" . $user['profile_picture'])) {
                    unlink("../" . $user['profile_picture']);
                }

                $uploadDir = '../uploads/profiles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'profile_' . $userId . '_' . uniqid() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $profilePicturePath = 'uploads/profiles/' . $fileName;
                    $updateStmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    if ($updateStmt->execute([$profilePicturePath, $userId])) {
                        $user['profile_picture'] = $profilePicturePath;
                        $message = "Profile picture updated successfully!";
                        $errorType = "success";
                    } else {
                        $message = "Failed to update profile picture in database.";
                    }
                } else {
                    $message = "Failed to upload profile picture.";
                }
            }
        } else {
            $message = "No file selected or error occurred.";
        }
    } elseif ($action === "change_password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";
        
        $passStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $passStmt->execute([$userId]);
        $passData = $passStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($currentPassword, $passData['password'])) {
            $message = "Current password is incorrect.";
        } elseif (strlen($newPassword) < 8) {
            $message = "Password must be at least 8 characters long.";
        } elseif (!preg_match("/[A-Z]/", $newPassword)) {
            $message = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match("/[a-z]/", $newPassword)) {
            $message = "Password must contain at least one lowercase letter.";
        } elseif (!preg_match("/[0-9]/", $newPassword)) {
            $message = "Password must contain at least one number.";
        } elseif (!preg_match("/[!@#$%^&*(),.?\\\":{}|<>]/", $newPassword)) {
            $message = "Password must contain at least one special character.";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "New passwords do not match.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $updatePassStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($updatePassStmt->execute([$hashedPassword, $userId])) {
                $message = "Password changed successfully!";
                $errorType = "success";
            } else {
                $message = "Failed to change password. Please try again.";
            }
        }
    }
}

$page_title = "Edit Profile - Notes Platform";
include("includes/header.php");
?>

<!-- Back Button -->
<div class="mb-3">
    <a href="<?php echo $_SESSION['role'] === 'student' ? 'student_dashboard.php' : ($_SESSION['role'] === 'teacher' ? 'teacher_dashboard.php' : 'admin/dashboard.php'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-person-check me-1"></i>Edit Profile
            </div>
            <div class="card-body p-4">
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $errorType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="picture-tab" data-bs-toggle="tab" data-bs-target="#picture-pane" type="button" role="tab">Profile Picture</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-pane" type="button" role="tab">Profile Info</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-pane" type="button" role="tab">Change Password</button>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- Profile Picture Tab -->
                    <div class="tab-pane fade show active" id="picture-pane">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_picture">

                            <div class="text-center mb-4">
                                <?php 
                                $currentProfilePic = !empty($user['profile_picture']) ? "../".$user['profile_picture'] : null;

                                if ($currentProfilePic && file_exists($currentProfilePic)) {
                                    echo '<img src="'.htmlspecialchars($currentProfilePic).'" class="rounded-circle" style="width:150px;height:150px;object-fit:cover;border:3px solid #007bff;">';
                                } else {
                                    echo '<div class="rounded-circle d-inline-flex align-items-center justify-content-center" 
                                        style="width:150px;height:150px;background:#f0f0f0;border:3px solid #ccc;">
                                        <i class="bi bi-person-fill" style="font-size:3rem;color:#999;"></i>
                                    </div>';
                                }
                                ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Upload New Picture</label>
                                <input type="file" name="profile_picture" class="form-control" accept="image/*">
                                <small class="text-muted">Max size 1GB (JPG, PNG, GIF, WebP)</small>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Update Picture</button>
                            </div>
                        </form>
                    </div>

                    <!-- Profile Info Tab -->
                    <div class="tab-pane fade" id="info-pane">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_info">

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="student_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Tab -->
                    <div class="tab-pane fade" id="password-pane">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                                <a href="student_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>
