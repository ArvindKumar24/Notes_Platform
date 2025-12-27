<?php
require_once("../config/config.php");
require_once __DIR__ . "/includes/EmailSender.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";
$message_type = "danger";

// Upload Logic
if (isset($_POST['upload'])) {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $user_id = (int)$_SESSION['user_id'];
        $uploader_role = $_SESSION['role'];
        $type = "note";

        if (empty($title)) {
            $message = "Title is required.";
        } elseif (strlen($title) < 3) {
            $message = "Title must be at least 3 characters long.";
        } elseif ($category_id <= 0) {
            $message = "Please select a valid category.";
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
            $message = "File upload error. Please try again.";
        } else {

            $file = $_FILES['file'];
            $allowed = ['pdf', 'docx'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $message = "Only PDF and DOCX files are allowed.";
            } elseif ($file['size'] > 100 * 1024 * 1024) {
                $message = "File size must not exceed 1GB.";
            } else {

                // Create safe filename
                $newFileName = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                $targetPath = "../uploads/" . $newFileName;

                if (!is_dir("../uploads")) {
                    @mkdir("../uploads", 0777, true);
                }

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {

                    $relativePath = "uploads/" . $newFileName;

                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO notes (user_id, category_id, title, description, file_path, type, uploaded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $stmt->execute([$user_id, $category_id, $title, $description, $relativePath, $type]);

                    $message = "Note uploaded successfully!";
                    $message_type = "success";

                    /* ========================================================
                       EMAIL NOTIFICATIONS
                    ======================================================== */

                    $mailer = new EmailSender();

                    // Fetch uploader info
                    $userInfo = $pdo->prepare("SELECT name, email, role FROM users WHERE id = ?");
                    $userInfo->execute([$user_id]);
                    $user = $userInfo->fetch();

                    $uploaderName = $user['name'];
                    $uploaderEmail = $user['email'];

                    // 1. Notify ALL users that new notes were uploaded
                    $allEmails = $pdo->query("SELECT email FROM users")->fetchAll(PDO::FETCH_COLUMN);
                    $mailer->sendNewNotesNotification($allEmails, $title, $uploaderName);

                    // 2. If uploader is a TEACHER → send teacher upload confirmation
                    if ($uploader_role === "teacher") {
                        $mailer->sendTeacherUploadNotification(
                            $uploaderEmail,
                            $uploaderName,
                            $title,
                            $description
                        );
                    }
                } else {
                    $message = "Failed to upload file. Please try again.";
                }
            }
        }
    } catch (PDOException $e) {
        $message = "Database error occurred.";
        error_log("Upload error (DB): " . $e->getMessage());
    } catch (Exception $e) {
        $message = "Unexpected error occurred.";
        error_log("Upload error: " . $e->getMessage());
    }
}

$cats = $pdo->query("SELECT * FROM categories")->fetchAll();
?>

<?php include("includes/header.php"); ?>

<div class="container mt-4">

    <a href="<?php echo $_SESSION['role'] === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'; ?>" 
       class="btn btn-outline-secondary btn-sm mb-3">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">

            <div class="card shadow-sm">
                <div class="card-body p-4">

                    <h2 class="h4 mb-3">📤 Upload Notes</h2>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">

                        <label class="form-label">Title</label>
                        <input type="text" class="form-control mb-3" name="title">

                        <label class="form-label">Description</label>
                        <textarea class="form-control mb-3" name="description" rows="3"></textarea>

                        <label class="form-label">Category</label>
                        <select class="form-select mb-3" name="category_id">
                            <option value="">Select category</option>
                            <?php foreach ($cats as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label">Upload File</label>
                        <input type="file" class="form-control mb-3" name="file" accept=".pdf,.docx">

                        <button type="submit" name="upload" class="btn btn-danger w-100">
                            <i class="bi bi-upload"></i> Upload Note
                        </button>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>
