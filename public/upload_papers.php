<?php
require_once("../config/config.php");
require_once("includes/EmailSender.php");  // CORRECT PATH

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$message = "";
$message_type = "danger";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $title = trim($_POST["title"] ?? '');
        $description = trim($_POST["description"] ?? '');
        $category_id = isset($_POST["category_id"]) ? (int)$_POST["category_id"] : 0;

        // VALIDATION
        if (empty($title)) {
            $message = "Title is required.";
        } elseif (strlen($title) < 3) {
            $message = "Title must be at least 3 characters.";
        } elseif (strlen($title) > 200) {
            $message = "Title cannot exceed 200 characters.";
        } elseif ($category_id <= 0) {
            $message = "Please select a valid category.";
        } elseif (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== 0) {
            $message = "File upload failed.";
        } else {

            // FILE VALIDATION
            $allowed = ["pdf", "docx"];
            $filename = $_FILES["file"]["name"];
            $filesize = $_FILES["file"]["size"];
            $max_size = 100 * 1024 * 1024;  // 100MB

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $message = "Only PDF and DOCX files are allowed.";
            } elseif ($filesize > $max_size) {
                $message = "File size must not exceed 100MB.";
            } elseif ($filesize <= 0) {
                $message = "File is empty.";
            } else {

                // FILE STORAGE
                $new_filename = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                $destination = "../uploads/" . $new_filename;

                if (!is_dir("../uploads")) {
                    @mkdir("../uploads", 0777, true);
                }

                if (move_uploaded_file($_FILES["file"]["tmp_name"], $destination)) {

                    // INSERT INTO DB
                    // type must be 'question_paper' according to your enum
                    $stmt = $pdo->prepare("
                        INSERT INTO notes (user_id, category_id, title, description, file_path, type, uploaded_at) 
                        VALUES (?, ?, ?, ?, ?, 'question_paper', NOW())
                    ");

                    $stmt->execute([
                        $_SESSION["user_id"],
                        $category_id,
                        $title,
                        $description,
                        $new_filename
                    ]);

                    $message = "Past paper uploaded successfully!";
                    $message_type = "success";

                    /* -------------------------------------------------------
                       EMAIL NOTIFICATIONS – protected from breaking upload
                    -------------------------------------------------------- */
                    try {
                        $mailer = new EmailSender();
                        $uploaderId = $_SESSION["user_id"];

                        // Fetch uploader info
                        $uploaderQuery = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                        $uploaderQuery->execute([$uploaderId]);
                        $uploader = $uploaderQuery->fetch(PDO::FETCH_ASSOC);

                        if ($uploader) {
                            $uploaderName = $uploader["name"];
                            $uploaderEmail = $uploader["email"];

                            // Notify uploader
                            $mailer->sendTeacherUploadNotification(
                                $uploaderEmail,
                                $uploaderName,
                                $title,
                                "past paper"
                            );

                            // Notify all teachers + students except uploader
                            $othersQuery = $pdo->prepare("
                                SELECT email FROM users 
                                WHERE id != ? AND role IN ('teacher','student')
                            ");
                            $othersQuery->execute([$uploaderId]);
                            $otherEmails = $othersQuery->fetchAll(PDO::FETCH_COLUMN);

                            foreach ($otherEmails as $email) {
                                $mailer->sendNewQuestionPaperNotification(
                                    $email,
                                    $uploaderName,
                                    $title
                                );

                            }
                        }

                    } catch (Exception $e) {
                        error_log("Email sending failed: " . $e->getMessage());
                    }
                } else {
                    $message = "Failed to move uploaded file.";
                }
            }
        }

    } catch (PDOException $e) {
        $message = "Database error. Please contact support.";
        error_log("DB ERROR upload_papers.php: " . $e->getMessage());
    }
}

// Load categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")
                  ->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Upload Past Papers - Notes Platform";
include("includes/header.php");
?>

<!-- HTML FORM SAME AS YOUR VERSION BELOW -->


</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Upload Past Paper</h1>
                <p class="text-muted mb-4">Share previous exam papers to help students prepare.</p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Title <span style="color: red;">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Final Exam 2023" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category <span style="color: red;">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">File <span style="color: red;">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.docx" required>
                        <div class="form-text">Accepts PDF or DOCX files up to 5MB.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn" style="background: #14B8A6; color: white;">
                            <i class="bi bi-upload me-1"></i>Upload Paper
                        </button>
                        <a href="teacher_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        
                            <a href="<?php echo $_SESSION['role'] === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'; ?>" 
                            class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                            </a>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-light shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Tips for Sharing Papers</h2>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-2">Include exam year in title</li>
                    <li class="mb-2">Add syllabus coverage</li>
                    <li class="mb-2">Prefer PDF for consistency</li>
                    <li>Choose accurate category</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>
