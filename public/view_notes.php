<?php
require_once("../config/config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Get filters
$type_filter = $_GET['type'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT n.*, u.name AS uploader, c.name AS category_name 
          FROM notes n 
          LEFT JOIN users u ON n.user_id = u.id 
          LEFT JOIN categories c ON n.category_id = c.id 
          WHERE 1=1";

$params = [];

// Apply type filter
if ($type_filter && in_array($type_filter, ['note', 'past_paper', 'assessment'])) {
    $query .= " AND n.type = ?";
    $params[] = $type_filter;
}

// Apply category filter
if ($category_filter && is_numeric($category_filter)) {
    $query .= " AND n.category_id = ?";
    $params[] = $category_filter;
}

// Apply search filter
if ($search) {
    $query .= " AND (n.title LIKE ? OR n.description LIKE ? OR c.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY n.uploaded_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$page_title = "Browse Notes - Notes Share";
include("includes/header.php");

?>

<!-- Back Button -->
<div class="mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Home
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h4 mb-3">Browse Study Materials</h2>
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Search by title, description, or category..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <option value="">All Types</option>
                        <option value="note" <?php echo $type_filter=='note' ? 'selected' : ''; ?>>Notes</option>
                        <option value="past_paper" <?php echo $type_filter=='past_paper' ? 'selected' : ''; ?>>Past Papers</option>
                        <option value="assessment" <?php echo $type_filter=='assessment' ? 'selected' : ''; ?>>Assessments</option>
                    </select>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="view_notes.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (count($notes) > 0): ?>
    <div class="row g-3">
        <?php foreach ($notes as $note): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h3 class="h6 mb-0"><?php echo htmlspecialchars($note['title']); ?></h3>
                            <span class="badge text-bg-primary">
                                <?php echo ucfirst(str_replace('_', ' ', $note['type'])); ?>
                            </span>
                        </div>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($note['description'] ?: 'No description'); ?></p>
                        <ul class="list-unstyled small text-muted mb-3">
                            <li><strong>Category:</strong> <?php echo htmlspecialchars($note['category_name']); ?></li>
                            <li><strong>Uploaded by:</strong> <?php echo htmlspecialchars($note['uploader']); ?></li>
                            <li><strong>Uploaded:</strong> <?php echo date('M j, Y', strtotime($note['uploaded_at'])); ?></li>
                            <li><strong>Downloads:</strong> <?php echo $note['downloads_count']; ?></li>
                        </ul>
                        <div class="mt-auto">
                            <a href="download.php?id=<?php echo $note['id']; ?>" class="btn btn-outline-danger w-100">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card text-center shadow-sm">
        <div class="card-body">
            <h3 class="h5">No notes found</h3>
            <p class="text-muted mb-3">Try adjusting your search filters or upload the first note!</p>
            <a href="upload_notes.php" class="btn btn-danger">Upload First Note</a>
        </div>
    </div>
<?php endif; ?>

<div class="text-center my-4">
    <a href="dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
</div>

<?php include("includes/footer.php"); ?>