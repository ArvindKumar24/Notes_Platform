<?php
require_once("../config/config.php");
require_once __DIR__ . '/includes/EmailSender.php';

$message = "";
$error = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"] ?? '');

    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        // Check user exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            // Generate token
            $reset_token = bin2hex(random_bytes(32));
            $token_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save to DB
            $query = $pdo->prepare("
                UPDATE users SET reset_token = ?, reset_token_expires = ? 
                WHERE id = ?
            ");
            $query->execute([$reset_token, $token_expires, $user["id"]]);

            // Send Email
            try {
                $mailer = new EmailSender();
                $mailer->sendPasswordResetEmail($email, $user["name"], $reset_token);

            } catch (Exception $e) {
                error_log("Email error: " . $e->getMessage());
            }
        }

        // Always show success message for security
        $success = true;
        $message = "If an account exists with this email, you will receive a password reset link shortly.";
    }
}

$page_title = "Forgot Password - Notes Platform";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Simple clean version -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">

            <h2 class="mb-3 fw-bold">Reset Password</h2>
            <p class="text-muted">Enter your email to get a reset link</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control mb-3" required>

                <button class="btn" style="background: #14B8A6; color: white; width: 100%;">Send Reset Link</button>
            </form>

            <div class="mt-3">
                <a href="login.php" class="fw-bold" style="color: #14B8A6;">Back to Login</a>
            </div>

        </div>
    </div>
</div>

</body>
</html>
