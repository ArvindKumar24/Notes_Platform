<?php
require_once("../../config/config.php");

// If already admin, go to dashboard
if (!empty($_SESSION["user_id"]) && ($_SESSION["role"] ?? '') === 'admin') {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $fixedEmail = "notesshare@edu.in";
    $fixedPassword = "NotesShare";

    if ($email === $fixedEmail && $password === $fixedPassword) {
        $_SESSION["user_id"] = "admin"; // synthetic id
        $_SESSION["role"] = "admin";
        $_SESSION["name"] = "Admin";
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid admin credentials.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Notes Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="bg-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                                <span class="text-white fw-bold">N</span>
                            </div>
                            <h1 class="h4 mb-0">Admin Login</h1>
                            <p class="text-muted">Restricted area</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" placeholder="notesshare@edu.in" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" placeholder="NotesShare" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-danger">Sign In</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

