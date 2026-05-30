<?php
/**
 * POS Kiosk System - Login Page
 * Final Version
 */
session_start();
require_once '../config/database.php';

// 1. Optimized Redirect Logic
if (isset($_SESSION['role'])) {
    $redirect = ($_SESSION['role'] === 'admin') ? "../admin/dashboard.php" : "../staff/pos.php";
    header("Location: $redirect");
    exit();
}

$error = "";

// 2. Secure Authentication Process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Trim para iwas sa accidental spaces
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Prepared Statement to prevent SQL Injection
    $query = "SELECT id, username, name, password, role FROM users WHERE username = ? LIMIT 1";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Verify Password
            if (password_verify($password, $user['password'])) {
                // Regenerate session ID for security (prevents session fixation)
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                $redirect = ($user['role'] === 'admin') ? "../admin/dashboard.php" : "../staff/pos.php";
                header("Location: $redirect");
                exit();
            } else {
                $error = "Maling password. Subukan muli.";
            }
        } else {
            $error = "Hindi mahanap ang username.";
        }
        $stmt->close();
    } else {
        $error = "System Error: Please contact admin.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login | POS System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #070b14;
            --card-bg: rgba(15, 23, 42, 0.85);
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
        }

        * { 
            margin: 0; padding: 0; box-sizing: border-box; 
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            min-height: 100dvh;
            background: radial-gradient(circle at top right, #1e293b, #070b14);
            color: var(--text-main);
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-container {
            background: var(--card-bg);
            padding: clamp(25px, 8vw, 40px) clamp(20px, 5vw, 30px);
            border-radius: 28px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-section h1 {
            font-size: clamp(1.8rem, 5vw, 2.2rem);
            font-weight: 800;
            background: linear-gradient(to right, #fff, var(--text-muted));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .error-banner {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            padding: 14px;
            border-radius: 14px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            margin-bottom: 25px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 600;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        input {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            color: white;
            padding: 16px;
            border-radius: 14px;
            outline: none;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 16px; /* Para iwas zoom sa iOS */
        }

        input:focus {
            border-color: var(--accent-blue);
            background: rgba(15, 23, 42, 0.9);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .login-btn {
            background: var(--accent-blue);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 700;
            width: 100%;
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 10px;
        }

        .login-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .login-btn:active { transform: translateY(0); }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .footer-text a { 
            color: var(--accent-blue); 
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-height: 580px) {
            body { align-items: flex-start; padding-top: 20px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <main class="form-container">
        <div class="logo-section">
            <h1>🍹 POS Login</h1>
            <p>Access the terminal system</p>
        </div>

        <?php if($error !== ""): ?>
            <div class="error-banner" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" name="login" class="login-btn">
                Sign In to System
            </button>
        </form>

        <div class="footer-text">
            Forgot password? <br>
            <a href="#">Contact System Administrator</a>
        </div>
    </main>
</div>

</body>
</html>