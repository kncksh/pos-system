<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once(__DIR__ . '/config/database.php');
/**
 * POS Kiosk System - Welcome Page
 * Final Version
 */
session_start();

// 1. Centralized Session Redirect Logic
if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } elseif ($role === 'staff') {
        header("Location: staff/pos.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Welcome | POS Kiosk System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #070b14;
            --card-bg: rgba(15, 23, 42, 0.85);
            --accent-green: #10b981;
            --accent-blue: #3b82f6;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Reset & Base Styles */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            -webkit-tap-highlight-color: transparent;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            min-height: 100dvh; /* iOS Safari fix */
            background: 
                radial-gradient(circle at 0% 0%, rgba(59, 130, 246, 0.15) 0%, transparent 35%),
                radial-gradient(circle at 100% 100%, rgba(16, 185, 129, 0.1) 0%, transparent 35%),
                var(--bg-dark);
            color: var(--text-main);
            padding: 20px;
            overflow-x: hidden;
        }

        /* Entrance Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .welcome-card {
            max-width: 420px;
            width: 100%;
            padding: clamp(30px, 8vw, 50px) clamp(20px, 5vw, 40px);
            background: var(--card-bg);
            border-radius: 32px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .hero-icon-container {
            width: clamp(80px, 15vw, 100px);
            height: clamp(80px, 15vw, 100px);
            background: rgba(59, 130, 246, 0.1);
            border-radius: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 25px auto;
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            border: 1px solid rgba(59, 130, 246, 0.2);
            box-shadow: inset 0 0 20px rgba(59, 130, 246, 0.1);
        }

        h1 {
            font-size: clamp(1.8rem, 6vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 10px;
            background: linear-gradient(to bottom, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .tagline {
            color: var(--accent-green);
            font-weight: 700;
            font-size: clamp(0.7rem, 2vw, 0.85rem);
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 25px;
            opacity: 0.9;
            display: block;
        }

        .description {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 40px;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .btn-get-started {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: var(--accent-blue);
            color: white;
            text-decoration: none;
            width: 100%;
            padding: 18px 32px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition-smooth);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
            border: none;
            cursor: pointer;
        }

        /* Hover & Touch Feedback */
        @media (hover: hover) {
            .btn-get-started:hover {
                background: #2563eb;
                transform: translateY(-3px);
                box-shadow: 0 20px 30px -10px rgba(59, 130, 246, 0.5);
            }
        }

        .btn-get-started:active {
            transform: translateY(1px);
            opacity: 0.95;
            box-shadow: 0 5px 10px -3px rgba(59, 130, 246, 0.4);
        }

        /* Extra Mobile Adjustments */
        @media (max-width: 375px) {
            .welcome-card {
                padding: 30px 20px;
                border-radius: 24px;
            }
            .description { margin-bottom: 25px; }
        }

        @media (max-height: 500px) {
            body { align-items: flex-start; padding-top: 20px; overflow-y: auto; }
            .welcome-card { margin-bottom: 20px; }
        }
    </style>
</head>
<body>

    <main class="welcome-card">
        <div class="hero-icon-container" aria-hidden="true">
            🍹
        </div>
        <header>
            <h1>POS Kiosk</h1>
            <span class="tagline">Milk Tea • Pizza • Noodles</span>
        </header>
        
        <p class="description">
            The smart way to manage your orders. Access the terminal to start selling or manage your store's inventory.
        </p>
        
        <a href="auth/login.php" class="btn-get-started">
            Launch Terminal <span>🚀</span>
        </a>
    </main>

</body>
</html>