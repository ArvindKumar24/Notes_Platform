<?php
require_once("../../config/config.php");


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}


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
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .admin-header h1 {
            font-size: 2rem;
            color: #1e293b;
            margin: 0;
        }

        .welcome-text {
            color: #64748b;
            margin: 0.5rem 0 0 0;
        }

       
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            padding: 1.75rem;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            border-left: 4px solid #14B8A6;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
            position: relative;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .stat-card h3 {
            margin: 0 0 1rem 0;
            font-size: 0.875rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #14B8A6;
            line-height: 1;
        }

        .stat-card.pending { border-left-color: #F59E0B; }
        .stat-card.pending .stat-number { color: #F59E0B; }

        .stat-card.success { border-left-color: #14B8A6; }
        .stat-card.success .stat-number { color: #14B8A6; }

        .stat-card.danger { border-left-color: #F59E0B; }
        .stat-card.danger .stat-number { color: #F59E0B; }

       
        .section-header {
            display: flex;
            align-items: center;
            margin: 2.5rem 0 1.5rem 0;
            padding-bottom: 1rem;
            border-bottom: 2px solid #14B8A6;
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #1e293b;
            font-weight: 600;
        }

       
        .navigation-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0 2rem 0;
        }

        .nav-card {
            background: white;
            padding: 1.75rem;
            border-radius: 10px;
            border: 2px solid transparent;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: #14B8A6;
            transition: height 0.3s ease;
        }

        .nav-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(20, 184, 166, 0.15);
            border-color: #14B8A6;
            text-decoration: none;
            color: #333;
        }

        .nav-card:hover::before {
            height: 100%;
            opacity: 0.1;
            z-index: -1;
        }

        .nav-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.125rem;
            color: #1e293b;
            font-weight: 600;
        }

        .nav-card p {
            margin: 0;
            color: #64748b;
            font-size: 0.875rem;
            line-height: 1.5;
        }

       
        .activity-section {
            margin-top: 2.5rem;
            padding: 1.75rem;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .activity-section h4 {
            margin: 0 0 1.5rem 0;
            font-size: 1.125rem;
            color: #1e293b;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .activity-section h4::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 20px;
            background: #14B8A6;
            border-radius: 2px;
            margin-right: 0.75rem;
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-info {
            flex: 1;
        }

        .activity-info strong {
            display: block;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .activity-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .activity-badge.approved {
            background: #dcfce7;
            color: #166534;
        }

        .activity-badge.pending {
            background: #fef3c7;
            color: #b45309;
        }

        .activity-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .activity-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

       
        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 1rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-number {
                font-size: 1.75rem;
            }

            .navigation-links {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-header">
        <div>
            <h1>Admin Dashboard</h1>
            <p class="welcome-text">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>!</p>
        </div>
    </div>

    <!-- Statistics Section -->
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

    <!-- Quick Navigation Section -->
    <div class="section-header">
        <h3>Quick Navigation</h3>
    </div>
    <div class="navigation-links">
        <a href="manage_users.php" class="nav-card">
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

    <!-- Recent Activity Section -->
    <div class="activity-section">
        <h4>Recent Activity</h4>
        <?php
        
        $recentUploads = $pdo->query("
            SELECT n.title, n.type, n.uploaded_at, u.name as uploader, n.status 
            FROM notes n 
            LEFT JOIN users u ON n.user_id = u.id 
            ORDER BY n.uploaded_at DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if ($recentUploads): ?>
            <ul class="activity-list">
                <?php foreach ($recentUploads as $upload): ?>
                    <li class="activity-item">
                        <div class="activity-info">
                            <strong><?= htmlspecialchars($upload['title']) ?></strong>
                            <div class="activity-meta">
                                Uploaded by <strong><?= htmlspecialchars($upload['uploader']) ?></strong>
                                <span class="activity-type"><?= ucfirst(str_replace('_', ' ', $upload['type'])) ?></span>
                                <br>
                                <small><?= $upload['uploaded_at'] ?></small>
                            </div>
                        </div>
                        <span class="activity-badge <?= strtolower($upload['status']) ?>">
                            <?= ucfirst($upload['status']) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: #64748b; margin: 0;">No recent uploads.</p>
        <?php endif; ?>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
</body>
</html>