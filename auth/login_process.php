<?php
ob_start(); 
session_start();
include('../config/database.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 1. Prepared Statement - Secure laban sa SQL Injection
    $stmt = $conn->prepare("SELECT id, name, username, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // 2. Verify Password
        if (password_verify($password, $user['password'])) {
            
            // 3. Security: Refresh session ID para iwas session hijacking
            session_regenerate_id(true);

            // 4. IMPORTANT: I-set ang session variables na kailangan ng ibang files
            $_SESSION['user_id']  = $user['id'];       // Kailangan ito ng checkout.php
            $_SESSION['username'] = $user['username']; // Para sa "Logged in as" display
            $_SESSION['name']     = $user['name'];
            $_SESSION['role']     = strtolower(trim($user['role']));

            // 5. REDIRECT LOGIC
            if ($_SESSION['role'] === 'admin') {
                header("Location: ../admin/dashboard.php");
            } elseif ($_SESSION['role'] === 'staff') {
                header("Location: ../staff/pos.php");
            } else {
                // Fallback kung sakaling may kakaibang role na nailagay
                header("Location: login.php");
            }
            exit();

        } else {
            $_SESSION['error'] = "Mali ang iyong password. Subukan muli.";
        }
    } else {
        $_SESSION['error'] = "Hindi mahanap ang username na ito.";
    }

    // Pag may error, balik sa login page
    header("Location: login.php");
    exit();
}
ob_end_flush();
?>