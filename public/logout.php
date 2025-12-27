<?php
require_once("../config/config.php");

$_SESSION = [];
session_unset();
session_destroy();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Optional: delete any custom cookies (if using "remember me")
setcookie("remember_me", "", time() - 3600, "/");

header("Location: login.php");
exit;
?>
