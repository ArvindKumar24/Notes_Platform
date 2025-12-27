<?php
require_once("../../config/config.php");

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$page_title = "Manage Categories - Admin";

// Handle add category
if (isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
    }
}

// Handle delete category
if (isset($_POST['delete_category'])) {
    $id = $_POST['category_id'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
}

// Handle edit category
if (isset($_POST['edit_category'])) {
    $id = $_POST['category_id'];
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
    }
}

// Fetch all categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

include("./header.php");
?>
<div class="manage-container">
    <div class="container">
        <div class="manage-header">
            <h2>📂 Manage Categories</h2>
            <div class="mt-3">
                <a href="download_categories_report.php" class="btn btn-success">
                    <i class="bi bi-download me-1"></i> Download Categories Report
                </a>
            </div>
        </div>

        <!-- Rest of your existing code -->
<!-- Back Button -->
<div style="margin-bottom: 1.5rem; margin-top: 1rem;">
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<style>
.manage-container {
    background: #f8fafc;
    padding: 2rem 0;
    min-height: 100vh;
}

.manage-header {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.manage-header h2 {
    margin: 0;
    font-size: 1.8rem;
}

.section-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.section-card h3 {
    color: #1e293b;
    font-size: 1.3rem;
    margin-top: 0;
    margin-bottom: 1.5rem;
    font-weight: 700;
}

.form-group {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.form-group input {
    flex: 1;
    min-width: 200px;
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: all 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.form-group button {
    padding: 0.75rem 1.5rem;
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.form-group button:hover {
    background: #b91c1c;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(220, 38, 38, 0.2);
}

.categories-table-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table thead {
    background: #f1f5f9;
    border-bottom: 2px solid #e2e8f0;
}

table th {
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    color: #334155;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    color: #475569;
}

table tbody tr:hover {
    background: #f8fafc;
}

.category-id {
    font-weight: 600;
    color: #dc2626;
}

.category-name {
    color: #1e293b;
    font-weight: 500;
}

.actions-cell {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.edit-form {
    display: flex;
    gap: 0.5rem;
    flex: 1;
    min-width: 250px;
}

.edit-form input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.edit-form input:focus {
    outline: none;
    border-color: #dc2626;
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.1);
}

.action-btn {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s;
    white-space: nowrap;
}

.btn-update {
    background: #3b82f6;
    color: white;
}

.btn-update:hover {
    background: #2563eb;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #64748b;
}

.empty-state-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .manage-header h2 {
        font-size: 1.5rem;
    }

    .form-group {
        flex-direction: column;
    }

    .form-group input {
        min-width: 100%;
    }

    .edit-form {
        flex-direction: column;
    }

    .actions-cell {
        flex-direction: column;
    }

    .action-btn {
        width: 100%;
    }

    table th, table td {
        padding: 0.75rem;
        font-size: 0.9rem;
    }
}
</style>

<div class="manage-container">
    <div class="container">
        <div class="manage-header">
            <h2>📂 Manage Categories</h2>
        </div>

        <div class="section-card">
            <h3>➕ Add New Category</h3>
            <form method="POST" class="form-group">
                <input type="text" name="name" placeholder="Enter category name..." required>
                <button type="submit" name="add_category">Add Category</button>
            </form>
        </div>

        <h3 style="color: #1e293b; font-size: 1.3rem; font-weight: 700; margin-bottom: 1.5rem;">📋 Existing Categories</h3>

        <?php if (count($categories) > 0): ?>
        <div class="categories-table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="category-id">#<?= $cat['id'] ?></td>
                        <td class="category-name"><?= htmlspecialchars($cat['name']) ?></td>
                        <td class="actions-cell">
                            <!-- Edit Form -->
                            <form method="POST" class="edit-form">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required>
                                <button type="submit" name="edit_category" class="action-btn btn-update">✓ Update</button>
                            </form>

                            <!-- Delete Form -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category? This action cannot be undone.');">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <button type="submit" name="delete_category" class="action-btn btn-delete">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="section-card">
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p><strong>No categories yet</strong></p>
                <p style="font-size: 0.9rem;">Start by adding your first category above.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
