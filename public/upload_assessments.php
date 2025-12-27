<?php
require_once("../config/config.php");
require_once("includes/EmailSender.php"); // ADD THIS

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

        // Validation
        if (empty($title)) {
            $message = "Title is required.";
        } elseif (strlen($title) < 3) {
            $message = "Title must be at least 3 characters long.";
        } elseif (strlen($title) > 255) {
            $message = "Title must not exceed 255 characters.";
        } elseif ($category_id <= 0) {
            $message = "Please select a valid category.";
        } elseif (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== 0) {
            $message = "File upload error. Please try again.";
        } else {
            $allowed = ["pdf", "docx"];
            $filename = $_FILES["file"]["name"];
            $filesize = $_FILES["file"]["size"];
            $max_size = 100 * 1024 * 1024;

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $message = "Only PDF and DOCX files are allowed.";
            } elseif ($filesize > $max_size) {
                $message = "File size must not exceed 1GB.";
            } elseif ($filesize <= 0) {
                $message = "File is empty.";
            } else {

                // Generate safe filename
                $new_filename = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                $destination = "../uploads/" . $new_filename;

                if (!is_dir("../uploads")) {
                    @mkdir("../uploads", 0777, true);
                }

                if (move_uploaded_file($_FILES["file"]["tmp_name"], $destination)) {

                    // Save in database
                    $stmt = $pdo->prepare("
                        INSERT INTO notes 
                        (title, description, category_id, type, file_path, user_id, uploaded_at) 
                        VALUES (?, ?, ?, 'assessment', ?, ?, NOW())
                    ");
                    $stmt->execute([$title, $description, $category_id, $new_filename, $_SESSION["user_id"]]);

                    $message = "Assessment uploaded successfully!";
                    $message_type = "success";

                    // -----------------------------------------
                    // EMAIL NOTIFICATION SECTION START
                    // -----------------------------------------

                        $mailer = new EmailSender();

                        $uploaderId = $_SESSION["user_id"];

                        // Fetch uploader details
                        $uploaderQuery = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                        $uploaderQuery->execute([$uploaderId]);
                        $uploader = $uploaderQuery->fetch(PDO::FETCH_ASSOC);

                        $uploaderName = $uploader["name"];
                        $uploaderEmail = $uploader["email"];

                        /* 1️⃣ Notify ONLY the uploader */
                        $mailer->sendTeacherUploadNotification(
                            $uploaderEmail,
                            $uploaderName,
                            $title,
                            "assessment"
                        );

                        /* 2️⃣ Notify all other teachers + all students */
                        $othersQuery = $pdo->prepare("
                            SELECT email FROM users 
                            WHERE id != ? AND role IN ('teacher','student')
                        ");
                        $othersQuery->execute([$uploaderId]);

                        $otherEmails = $othersQuery->fetchAll(PDO::FETCH_COLUMN);

                        foreach ($otherEmails as $email) {
                            $mailer->sendNewAssessmentNotification(
                                $email,
                                $uploaderName,
                                $title
                            );
                        }
                    // -----------------------------------------
                    // EMAIL NOTIFICATION SECTION END
                    // -----------------------------------------

                } else {
                    $message = "Could not upload file. Please try again.";
                }
            }
        }
    } catch (PDOException $e) {
        $message = "Database error: Please contact support.";
        error_log("Database error in upload_assessments.php: " . $e->getMessage());
    } catch (Exception $e) {
        $message = "An unexpected error occurred.";
        error_log("Error in upload_assessments.php: " . $e->getMessage());
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = "Upload Assessments - Notes Platform";
include("includes/header.php");
?>

<!-- Back Button -->
<div class="mb-3">
    <a href="<?php echo $_SESSION['role'] === 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Upload Assessment</h1>
                <p class="text-muted mb-4">Share quizzes, tests, and assignments with your students.</p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Midterm Assessment" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Add helpful details for your students"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Assessment File</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.docx" required>
                        <div class="form-text">Accepts PDF or DOCX files up to 1GB.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-upload me-1"></i>Upload Assessment
                        </button>
                        <a href="teacher_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tips Card -->
    <div class="col-lg-4">
        <div class="card bg-light border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Tips for Great Assessments</h2>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Use clear titles so students understand the topic</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Add instructions in the description if needed</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Prefer PDF format to keep formatting intact</li>
                    <li><i class="bi bi-check-circle text-success me-2"></i>Assign the correct category for easy discovery</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>
