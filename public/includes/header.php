<?php 
// Detect if we're in admin or upload subdirectory
$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$is_admin = strpos($script_path, '/admin/') !== false || strpos($script_path, '\\admin\\') !== false;
$is_upload = strpos($script_path, '/upload/') !== false || strpos($script_path, '\\upload\\') !== false;
$prefix = ($is_admin || $is_upload) ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Notes Share'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $prefix; ?>assets/CSS/styles.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $prefix; ?>index.php">
                <span class="me-2">📘</span> Notes Share
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link"  style="color: white;"href="<?php echo $prefix; ?>index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"style="color: white;" href="<?php echo $prefix; ?>view_notes.php">Browse Notes</a>
                    </li>
                    <?php if (!empty($_SESSION["user_id"]) && $_SESSION["role"] === "admin"): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $prefix; ?>admin/dashboard.php">Admin Panel</a>
                        </li>
                    <?php endif; ?>
                </ul>



                <ul class="navbar-nav mb-2 mb-lg-0">
                    <?php if (!empty($_SESSION["user_id"])): ?>
                        <li class="nav-item">
                            <a class="btn btn-sm me-2" style="background: #14B8A6; color: white;" href="<?php echo $prefix; ?>dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="btn btn-outline-light btn-sm" style="background: #000b0a; color: white;" href="<?php echo $prefix; ?>logout.php">Logout</a>
                        </li>

                        
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-outline-light btn-sm me-2" style="background: #000b0a; color: white;" href="<?php echo $prefix; ?>login.php">Login</a>
                        </li>
                        <li class="nav-item">    
                            <a class="btn btn-sm" style="background: #000b0a; color: white;" href="<?php echo $prefix; ?>register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-4"> 