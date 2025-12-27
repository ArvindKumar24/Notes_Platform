<?php
require_once("../../config/config.php");

if (empty($_SESSION["user_id"])) {
    header("Location: public/login.php");
    exit;
}

$userId = $_SESSION["user_id"];
$role   = $_SESSION["role"];
$name   = $_SESSION["name"];

// Detect type (note, past_paper, assessment)
$type = "note";
if (isset($_GET["type"])) {
    if ($_GET["type"] === "question_paper") {
        $type = "past_paper";
    } elseif ($_GET["type"] === "assessment") {
        $type = "assessment";
    }
}

$message = "";

// Fetch categories dynamically
$categories = [];
try {
    $catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "⚠️ Error loading categories: " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title       = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $categoryId  = !empty($_POST["category_id"]) ? (int) $_POST["category_id"] : null;

    if (!empty($_FILES["file"]["name"])) {
        $allowedExts = ["pdf", "docx"];
        $fileExt = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowedExts)) {
            // Clean file name
            $baseName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES["file"]["name"]));
            $newFileName = time() . "_" . $baseName;

            $targetDir   = __DIR__ . "/../../uploads/";
            $targetFile  = $targetDir . $newFileName;

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO notes (user_id, category_id, title, description, file_path, type) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $categoryId, $title, $description, $newFileName, $type]);
                    $message = "✅ File uploaded successfully!";
                } catch (Exception $e) {
                    $message = "❌ Database error: " . $e->getMessage();
                }
            } else {
                $message = "❌ Failed to move uploaded file.";
            }
        } else {
            $message = "❌ Only PDF and DOCX files are allowed.";
        }
    } else {
        $message = "❌ Please select a file.";
    }
}
?>

<?php include("../includes/header.php"); ?>

<section class="upload-form">
  <h2>Upload <?php echo ucfirst($type); ?></h2>
  <?php if ($message): ?>
    <p><?php echo htmlspecialchars($message); ?></p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <label>Title</label>
    <input type="text" name="title" required>

    <label>Description</label>
    <textarea name="description"></textarea>

    <label>Category</label>
    <select name="category_id" required>
      <option value="">-- Select Category --</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?php echo $cat['id']; ?>">
          <?php echo htmlspecialchars($cat['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Upload File (PDF/DOCX only)</label>
    <input type="file" name="file" required>

    <button type="submit">Upload</button>
  </form>
</section>

<?php include("../includes/footer.php"); ?>
