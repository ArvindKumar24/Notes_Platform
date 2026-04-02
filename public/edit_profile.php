
<?php
require_once("../config/config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = "";
$errorType = "danger";

// FETCH USER
$userStmt = $pdo->prepare("SELECT name, email, role, profile_picture FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// ================= HANDLE FORM =================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // ---------- UPDATE INFO ----------
    if ($action === "update_info") {

        $name = trim($_POST["name"]);
        $email = trim($_POST["email"]);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?");
            if ($stmt->execute([$name, $email, $userId])) {
                $user['name'] = $name;
                $user['email'] = $email;
                $_SESSION["name"] = $name;
                $message = "Profile updated!";
                $errorType = "success";
            } else {
                $message = "Update failed.";
            }
        }
    }

    // ---------- UPDATE PROFILE PICTURE ----------
    elseif ($action === "update_picture") {

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {

            $file = $_FILES['profile_picture'];
            $maxSize = 10 * 1024 * 1024;

            if ($file['size'] > $maxSize) {
                $message = "Max size is 10MB.";
            } else {

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowed[$mime])) {
                    $message = "Invalid image type.";
                } else {

                    // DELETE OLD IMAGE
                    if (!empty($user['profile_picture'])) {
                        $oldPath = __DIR__ . '/profile_pictures/' . $user['profile_picture'];
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }

                    // UPLOAD DIR
                    $uploadDir = __DIR__ . '/profile_pictures/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $ext = $allowed[$mime];
                    $fileName = "profile_" . $userId . "_" . uniqid() . "." . $ext;

                    $serverPath = $uploadDir . $fileName;
                    $dbPath = $fileName;

                    if (move_uploaded_file($file['tmp_name'], $serverPath)) {

                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        if ($stmt->execute([$dbPath, $userId])) {
                            $user['profile_picture'] = $dbPath;
                            $message = "Profile picture updated!";
                            $errorType = "success";
                        } else {
                            $message = "DB update failed.";
                        }

                    } else {
                        $message = "Upload failed.";
                    }
                }
            }

        } else {
            $message = "No file selected.";
        }
    }

    // ---------- CHANGE PASSWORD ----------
    elseif ($action === "change_password") {

        $current = $_POST["current_password"];
        $new = $_POST["new_password"];
        $confirm = $_POST["confirm_password"];

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $data = $stmt->fetch();

        if (!password_verify($current, $data['password'])) {
            $message = "Wrong current password.";
        } elseif ($new !== $confirm) {
            $message = "Passwords do not match.";
        } else {

            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");

            if ($stmt->execute([$hash, $userId])) {
                $message = "Password changed!";
                $errorType = "success";
            } else {
                $message = "Failed.";
            }
        }
    }
}

include("includes/header.php");
?>

<div class="container mt-4">
<div class="row justify-content-center">
<div class="col-lg-6">

<div class="card shadow-sm">
<div class="card-header bg-success text-white">
Edit Profile
</div>

<div class="card-body">

<?php if ($message): ?>
<div class="alert alert-<?php echo $errorType; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- PROFILE IMAGE -->
<div class="text-center mb-4">
<?php
$default = "https://via.placeholder.com/150?text=User";
$image = $default;

if (!empty($user['profile_picture'])) {
    $serverPath = __DIR__ . '/profile_pictures/' . $user['profile_picture'];

    if (file_exists($serverPath)) {
        // ✅ FINAL FIX HERE
        $image = 'profile_pictures/' . $user['profile_picture'];
    }
}
?>
<img src="<?php echo htmlspecialchars($image); ?>" 
     class="rounded-circle"
     style="width:150px;height:150px;object-fit:cover;border:3px solid #14B8A6;">
</div>

<!-- UPLOAD -->
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="action" value="update_picture">
<input type="file" name="profile_picture" class="form-control mb-2">
<button class="btn btn-success w-100">Upload</button>
</form>

<hr>

<!-- INFO -->
<form method="POST">
<input type="hidden" name="action" value="update_info">
<input type="text" name="name" class="form-control mb-2"
value="<?php echo htmlspecialchars($user['name']); ?>">
<input type="email" name="email" class="form-control mb-2"
value="<?php echo htmlspecialchars($user['email']); ?>">
<button class="btn btn-primary w-100">Save</button>
</form>

<hr>

<!-- PASSWORD -->
<form method="POST">
<input type="hidden" name="action" value="change_password">
<input type="password" name="current_password" placeholder="Current Password" class="form-control mb-2">
<input type="password" name="new_password" placeholder="New Password" class="form-control mb-2">
<input type="password" name="confirm_password" placeholder="Confirm Password" class="form-control mb-2">
<button class="btn btn-danger w-100">Change Password</button>
</form>

</div>
</div>

</div>
</div>
</div>

<?php include("includes/footer.php"); ?>
