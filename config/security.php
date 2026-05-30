<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check kung naka-login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php?error=please_login");
    exit();
}

// Check kung Admin (para sa admin pages)
function restrictToAdmin() {
    if ($_SESSION['role'] !== 'admin') {
        // Kung hindi admin, itatapon sa staff dashboard o login
        header("Location: ../staff/pos.php?error=unauthorized"); 
        exit();
    }
}

// Check kung Staff (para sa POS/Staff pages)
function restrictToStaff() {
    if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
        header("Location: ../auth/login.php");
        exit();
    }
}
// Function para linisin ang input laban sa XSS (Cross-Site Scripting)
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>