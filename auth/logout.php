<?php
session_start();

// 1. Burahin lahat ng session variables
$_SESSION = array();

// 2. Sirain ang session cookie kung mayroon man
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Tuluyan nang sirain ang session
session_destroy();

// 4. REDIRECT SA INDEX.PHP (Nasa labas ng auth folder kaya ../)
header("Location: ../index.php");
exit();
?>