<?php
require_once("../../config/config.php");

// only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// get counts - updated to match actual database structure
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalNotes = $pdo->query("SELECT COUNT(*) FROM notes WHERE type='note'")->fetchColumn();
$totalPapers = $pdo->query("SELECT COUNT(*) FROM notes WHERE type='question_paper'")->fetchColumn();
$totalAssessments = $pdo->query("SELECT COUNT(*) FROM notes WHERE type='assessment'")->fetchColumn();
$pendingApprovals = $pdo->query("SELECT COUNT(*) FROM notes WHERE status='pending'")->fetchColumn();

$page_title = "Admin Dashboard";
include("./header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #007bff;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.pending .stat-number { color: #ffc107; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.success .stat-number { color: #28a745; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.danger .stat-number { color: #dc3545; }
        
        .navigation-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        .nav-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .nav-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            text-decoration: none;
            color: #333;
        }
        .nav-card h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        .nav-card p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Admin Dashboard</h2>
    <p>Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>!</p>

    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Total Users</h3>
            <div class="stat-number"><?= $totalUsers ?></div>
        </div>
        <div class="stat-card success">
            <h3>Total Notes</h3>
            <div class="stat-number"><?= $totalNotes ?></div>
        </div>
        <div class="stat-card">
            <h3>Question Papers</h3>
            <div class="stat-number"><?= $totalPapers ?></div>
        </div>
        <div class="stat-card">
            <h3>Assessments</h3>
            <div class="stat-number"><?= $totalAssessments ?></div>
        </div>
        <div class="stat-card pending">
            <h3>Pending Approvals</h3>
            <div class="stat-number"><?= $pendingApprovals ?></div>
        </div>
    </div>

    <h3>Quick Navigation</h3>
    <div class="navigation-links">
        <a href="manage_users.php" class="nav-card" style="border-top: 4px solid #28a745;">
            <h3>👥 Manage Users</h3>
            <p>View, edit, add users and manage roles</p>
        </a>
        <a href="manage_notes.php" class="nav-card">
            <h3>📚 Manage Notes</h3>
            <p>Approve, reject, or delete uploaded content</p>
        </a>
        <a href="manage_categories.php" class="nav-card">
            <h3>📂 Manage Categories</h3>
            <p>Add, edit, or remove content categories</p>
        </a>
        
    </div>

    <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h4>Recent Activity</h4>
        <?php
        // Get recent uploads
        $recentUploads = $pdo->query("
            SELECT n.title, n.type, n.uploaded_at, u.name as uploader, n.status 
            FROM notes n 
            LEFT JOIN users u ON n.user_id = u.id 
            ORDER BY n.uploaded_at DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if ($recentUploads): ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($recentUploads as $upload): ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                        <strong><?= htmlspecialchars($upload['title']) ?></strong> 
                        (<?= ucfirst(str_replace('_', ' ', $upload['type'])) ?>)
                        <br>
                        <small>
                            Uploaded by <?= htmlspecialchars($upload['uploader']) ?> 
                            on <?= $upload['uploaded_at'] ?>
                            - Status: 
                            <span style="color: 
                                <?= $upload['status'] == 'approved' ? 'green' : 
                                   ($upload['status'] == 'rejected' ? 'red' : 'orange') ?>">
                                <?= ucfirst($upload['status']) ?>
                            </span>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No recent uploads.</p>
        <?php endif; ?>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
</body>
</html>