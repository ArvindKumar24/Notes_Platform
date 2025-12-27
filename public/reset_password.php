<?php
require_once("../config/config.php");

$token = $_GET['token'] ?? '';
$error = "";
$success = false;

// Validate token
if (empty($token)) {
    $error = "Invalid reset link.";
} else {
    $stmt = $pdo->prepare("
        SELECT id, email FROM users 
        WHERE reset_token = ? 
        AND reset_token_expires > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "Reset link has expired or is invalid.";
    }
}

// Handle form
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    }

    if (empty($error)) {

        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $update = $pdo->prepare("
            UPDATE users 
            SET password = ?, reset_token = NULL, reset_token_expires = NULL
            WHERE id = ?
        ");
        $update->execute([$hashed, $user["id"]]);

        $success = true;
    }
}

$page_title = "Create New Password - Notes Platform";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">

            <h2 class="fw-bold mb-3">Create New Password</h2>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    Password reset successful!
                    <a href="login.php" class="fw-bold text-success">Login</a>
                </div>
            <?php else: ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (empty($error)): ?>
                    <form method="POST">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control mb-3" required>

                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control mb-3" required>

                        <button class="btn btn-danger w-100">Reset Password</button>
                    </form>
                <?php endif; ?>

            <?php endif; ?>

            <div class="mt-3">
                <a href="login.php" class="fw-bold text-danger">Back to Login</a>
            </div>

        </div>
    </div>
</div>

</body>
</html>
