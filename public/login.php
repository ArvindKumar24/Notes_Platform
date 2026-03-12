<?php
require_once("../config/config.php");
require_once __DIR__ . '/includes/EmailSender.php';
require_once __DIR__ . '/../config/smtp_config.php';

// If already logged in, redirect
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

    // Admin login check
    if ($email === "notesshare@edu.in" && $password === "NotesShare") {

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
include("includes/header.php");
?>

<style>
.hero-section {
    background: linear-gradient(135deg, #14B8A6 0%, #0d9488 100%);
}

.card-custom {
    border: none;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    border-radius: 15px;
}

.btn-custom {
    background: linear-gradient(45deg, #14B8A6, #38BDF8);
    border: none;
    border-radius: 8px;
    padding: 12px 30px;
    font-weight: 600;
}

.btn-custom:hover {
    background: linear-gradient(45deg, #0d9488, #14B8A6);
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(20, 184, 166, 0.3);
}

.form-control:focus {
    border-color: #14B8A6;
    box-shadow: 0 0 0 0.2rem rgba(20, 184, 166, 0.25);
}
</style>

<!-- Login Section -->
<section class="py-5 bg-light min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-custom">
                    <div class="card-body p-5">

                        <div class="text-center mb-4">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                 style="width: 60px; height: 60px; background: #14B8A6;">
                                <i class="bi bi-journal-bookmark-fill text-white fs-4"></i>
                            </div>
                            <h2 class="fw-bold">Welcome Back</h2>
                            <p class="text-muted">Login to your account</p>
                        </div>

                        <?php if (!empty($_SESSION["success"])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php 
                                    echo $_SESSION["success"]; 
                                    unset($_SESSION["success"]); 
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email Address<span class="text-danger">*</span></label>
                                <input type="email"
                                       name="email"
                                       class="form-control"
                                       required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="Enter your email">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Password<span class="text-danger">*</span></label>
                                <input type="password"
                                       name="password"
                                       class="form-control"
                                       required
                                       placeholder="Enter your password">
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-custom text-white btn-lg" >
                                    Login
                                </button>
                            </div>

                            <div class="text-center">
                                <a href="forgot_password.php"
                                   class="text-decoration-none"
                                   style="color: #050505;">
                                   Forgot your password?
                                </a>
                            </div>

                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-0">
                                Don't have an account?
                                <a href="register.php"
                                   class="text-decoration-none fw-bold"
                                   style="color: #090909;">
                                   Create one here
                                </a>
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include("includes/footer.php"); ?>