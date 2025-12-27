<?php
require_once("../config/config.php");

if (empty($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

switch ($_SESSION["role"]) {
    case "student":
        header("Location: student_dashboard.php");
        break;
    case "teacher":
        header("Location: teacher_dashboard.php");
        break;
    case "admin":
        header("Location: ../admin/dashboard.php");
        break;
    default:
        header("Location: logout.php");
        break;
}
exit;
?>
