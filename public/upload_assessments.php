<?php
require_once("../config/config.php");
require_once("includes/EmailSender.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$message = "";
$message_type = "danger";

// Predefined courses
$courses = [
    'BscIT',
    'BMS', 
    'Bcom',
    'BA Psychology',
    'MscIT',
    'BCA',
    
];

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $title = trim($_POST["title"] ?? '');
        $description = trim($_POST["description"] ?? '');
        $category_id = isset($_POST["category_id"]) ? (int)$_POST["category_id"] : 0;
        $course = trim($_POST["course"] ?? '');

        // Validation
        if (empty($title)) {
            $message = "Title is required.";
        } elseif (strlen($title) < 3) {
            $message = "Title must be at least 3 characters.";
        } elseif (strlen($title) > 255) {
            $message = "Title must not exceed 255 characters.";
        } elseif ($category_id <= 0) {
            $message = "Please select a valid category.";
        } elseif (empty($course)) {
            $message = "Please select the course this assessment belongs to.";
        } elseif (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== 0) {
            $message = "File upload error. Please try again.";
        } else {
            $allowed = ["pdf", "docx"];
            $filename = $_FILES["file"]["name"];
            $filesize = $_FILES["file"]["size"];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $message = "Only PDF and DOCX files are allowed.";
            } elseif ($filesize > 100 * 1024 * 1024) {
                $message = "File size must not exceed 100 MB.";
            } else {
                $new_filename = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                $destination = "../uploads/" . $new_filename;

                if (!is_dir("../uploads")) @mkdir("../uploads", 0777, true);

                if (move_uploaded_file($_FILES["file"]["tmp_name"], $destination)) {

                    // Insert into notes with course and status = 'approved'
                    $stmt = $pdo->prepare("
                        INSERT INTO notes
                            (title, description, category_id, course, type, file_path, user_id, uploaded_at, status)
                        VALUES (?, ?, ?, ?, 'assessment', ?, ?, NOW(), 'approved')
                    ");
                    $stmt->execute([$title, $description, $category_id, $course, $new_filename, $_SESSION["user_id"]]);

                    $message = "Assessment uploaded successfully!";
                    $message_type = "success";

                    // Email notifications
                    try {
                        $mailer = new EmailSender();
                        $uploaderId = (int)$_SESSION["user_id"];

                        $uploaderRow = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                        $uploaderRow->execute([$uploaderId]);
                        $uploader = $uploaderRow->fetch(PDO::FETCH_ASSOC);

                        if ($uploader) {
                            $mailer->sendTeacherUploadNotification(
                                $uploader["email"], 
                                $uploader["name"], 
                                $title, 
                                "assessment"
                            );

                            // Notify students enrolled in this course
                            $studentsStmt = $pdo->prepare("
                                SELECT email, name FROM users 
                                WHERE role = 'student' AND course = ?
                            ");
                            $studentsStmt->execute([$course]);
                            $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($students as $student) {
                                $mailer->sendNewAssessmentNotification(
                                    $student['email'],
                                    $uploader["name"],
                                    $title
                                );
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Email error: " . $e->getMessage());
                    }

                } else {
                    $message = "Could not save file.";
                }
            }
        }
    } catch (PDOException $e) {
        $message = "Database error.";
        error_log("upload_assessments.php DB error: " . $e->getMessage());
    }
}

$page_title = "Upload Assessment";
include("includes/header.php");
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Upload Assessment</h1>
                <p class="text-muted mb-4">Share quizzes, tests, and assignments with students.</p>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Course selection -->
                    <div class="mb-3">
                        <label class="form-label">
                            Course <span class="text-danger">*</span>
                            <small class="text-muted">(Only students enrolled in this course will see this assessment)</small>
                        </label>
                        <select name="course" class="form-select" required>
                            <option value="">Select course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Assessment File <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.docx" required>
                        <div class="form-text">PDF or DOCX only. Max 100 MB.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn" style="background:#14B8A6;color:white;">
                            <i class="bi bi-upload me-1"></i>Upload Assessment
                        </button>
                        <a href="teacher_dashboard.php" class="btn btn-outline-secondary">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>