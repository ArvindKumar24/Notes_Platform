<?php
require_once("../config/config.php");
require_once __DIR__ . '/includes/EmailSender.php';
require_once __DIR__ . '/../config/smtp_config.php';

// ❌ Removed broken email sending here
// $mailer = new EmailSender();
// $mailer->sendPasswordResetEmail($email, $name, $resetToken);

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] === "admin") {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Fixed admin credential login - create admin user in database instead
    if ($email === "notesshare@edu.in" && $password === "NotesShare") {
        // Check if admin user exists in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role='admin' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["name"] = $user["name"];
            header("Location: admin/dashboard.php");
            exit;
        } else {
            $error = "Admin account not found in database.";
        }
    } else {
        // Regular user login
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["name"] = $user["name"];
            
            if ($user["role"] === "admin") {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}

$page_title = "Login - Notes Platform";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }
        
        .card-custom {
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        
        .btn-custom {
            background: linear-gradient(45deg, #dc2626, #ef4444);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-custom:hover {
            background: linear-gradient(45deg, #b91c1c, #dc2626);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        
        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
        }
        
        .navbar-brand-custom {
            font-weight: 700;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="bg-danger rounded d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                    <span class="text-white fw-bold">N</span>
                </div>
                <span class="navbar-brand-custom text-dark">Notes Platform</span>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-dark fw-medium" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark fw-medium" href="view_notes.php">Notes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark fw-medium" href="upload_notes.php">Upload</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Form Section -->
    <section class="py-5 bg-light min-vh-100 d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card card-custom">
                        <div class="card-body p-5">
                            <!-- Logo & Header -->
                            <div class="text-center mb-4">
                                <div class="bg-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                    <i class="bi bi-journal-bookmark-fill text-white fs-4"></i>
                                </div>
                                <h2 class="card-title text-dark fw-bold">Welcome Back</h2>
                                <p class="text-muted">Login to your account</p>
                            </div>

                            <?php if (!empty($_SESSION["success"])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo $_SESSION["success"]; unset($_SESSION["success"]); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="email" class="form-label fw-semibold">Email Address</label>
                                    <input type="email" class="form-control" 
                                           id="email" name="email" required 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           placeholder="Enter your email">
                                </div>

                                <div class="mb-4">
                                    <label for="password" class="form-label fw-semibold">Password</label>
                                    <input type="password" class="form-control" 
                                           id="password" name="password" required 
                                           placeholder="Enter your password">
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-custom text-white btn-lg">Login</button>
                                </div>

                                <div class="text-center">
                                    <a href="forgot_password.php" class="text-danger text-decoration-none">Forgot your password?</a>
                                </div>
                            </form>

                            <hr class="my-4">

                            <div class="text-center">
                                <p class="text-muted mb-0">
                                    Don't have an account? 
                                    <a href="register.php" class="text-danger text-decoration-none fw-bold">Create one here</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> Notes Sharing Platform. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>