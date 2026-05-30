<?php
/**
 * POS Kiosk System - Admin Dashboard (Professional Updated)
 */
include('../config/security.php');
restrictToAdmin(); 
include('../config/database.php');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 1. Critical Stock
    $critical_stock_query = $conn->query("SELECT name, total_stock FROM products WHERE total_stock <= 10 ORDER BY total_stock ASC LIMIT 5");
    $critical_count = $critical_stock_query->num_rows;

    // 2. Summary Counters
    $products = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc();
    $staff = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='staff'")->fetch_assoc();
    $orders = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc();
    $sales = $conn->query("SELECT SUM(total) as total FROM orders")->fetch_assoc();

    // 3. Today's Performance
    $today = date('Y-m-d');
    $daily_stats = $conn->query("SELECT SUM(total) as daily_total FROM orders WHERE DATE(created_at) = '$today'")->fetch_assoc();
    $daily_sales = $daily_stats['daily_total'] ?? 0;

    // 4. Recent Transactions
    $recent_orders = $conn->query("SELECT o.*, u.username as staff_name 
        FROM orders o 
        LEFT JOIN users u ON o.staff_id = u.id 
        ORDER BY o.created_at DESC LIMIT 5");

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    die("A system error occurred.");
}

// Para sa Active State ng Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | POS System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">    
    <style>
        :root {
            --bg-dark: #070b14;
            --sidebar-bg: #111827;
            --card-bg: rgba(30, 41, 59, 0.45);
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --sidebar-width: 260px;
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-dark); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; }

        /* --- UNIFIED SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            padding: 2rem 1.2rem;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform 0.3s var(--ease);
        }

        .sidebar h2 { 
            color: var(--accent-blue); 
            margin-bottom: 2.5rem; 
            font-weight: 800; 
            font-size: 1.4rem;
            text-align: center;
            letter-spacing: -1px;
        }

        .sidebar nav { flex: 1; }

        .sidebar nav a { 
            display: flex; 
            align-items: center; 
            gap: 12px;
            padding: 14px 18px; 
            color: var(--text-muted); 
            text-decoration: none; 
            border-radius: 14px; 
            margin-bottom: 8px; 
            font-weight: 600; 
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .sidebar nav a i { width: 20px; text-align: center; }

        .sidebar nav a:hover { 
            background: rgba(59, 130, 246, 0.05); 
            color: var(--accent-blue); 
        }

        .sidebar nav a.active { 
            background: var(--accent-blue); 
            color: white; 
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.2); 
        }

        .logout-btn {
            margin-top: auto;
            padding: 14px 18px;
            color: var(--accent-red);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            border-radius: 14px;
            background: rgba(239, 68, 68, 0.05);
            transition: 0.3s;
        }

        .logout-btn:hover { background: var(--accent-red); color: white; }

        /* --- MAIN CONTENT --- */
        .main { 
            flex: 1; 
            margin-left: var(--sidebar-width); 
            padding: 2.5rem; 
            width: calc(100% - var(--sidebar-width));
        }

        .header { margin-bottom: 2.5rem; }
        .header h1 { font-size: 2rem; font-weight: 800; }

        /* STATS CARDS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card {
            background: var(--card-bg); padding: 1.8rem; border-radius: 24px;
            border: 1px solid var(--border-color); backdrop-filter: blur(12px);
            transition: 0.3s var(--ease);
        }
        .card:hover { transform: translateY(-5px); border-color: var(--accent-blue); }
        .card-label { color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .card-value { font-size: 2rem; font-weight: 800; letter-spacing: -1px; }

        /* ALERTS & TABLES */
        .warning-box { background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); padding: 1.5rem; border-radius: 20px; margin-bottom: 2rem; }
        .table-wrapper { background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border-color); padding: 1.5rem; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 0.8rem; border-bottom: 1px solid var(--border-color); text-transform: uppercase; }
        td { padding: 15px; font-size: 0.9rem; border-bottom: 1px solid rgba(255,255,255,0.02); }

        /* MOBILE TOGGLE */
       .mobile-toggle {
    display: none; /* Hidden sa Desktop */
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1100;
    background: var(--accent-blue);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 8px;
    cursor: pointer;
}

@media (max-width: 768px) {
    .mobile-toggle { display: block; } /* Labas sa Mobile */

    .sidebar {
        left: -100%; /* Tago ang sidebar sa simula */
        transition: 0.4s ease-in-out;
    }

    .sidebar.active {
        left: 0; /* Lalabas kapag pinindot ang toggle */
    }
    .logout-btn {
        margin-top: auto; /* Itutulak nito ang logout sa pinakailalim */
        margin-bottom: 60px;
        padding: 15px;
    }

    .main {
        margin-left: 0 !important; /* Full width ang main content */
        padding: 1.5rem;
    }
}

        /* --- WELCOME CARD (from index.php) --- */
        .welcome-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            backdrop-filter: blur(12px);
            margin-bottom: 2.5rem;
        }

        .welcome-card h2 {
            font-size: 1.8rem;
            font-weight: 800;
}
    </style>
</head>
<body>

    <button class="mobile-toggle no-print" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar">
    <h2>🍹 POS ADMIN</h2>
    <nav>
        <a href="dashboard.php" class="<?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="products.php" class="<?= ($current_page == 'products.php') ? 'active' : '' ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="staff.php" class="<?= ($current_page == 'staff.php') ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Staff
        </a>
        <a href="inventory.php" class="<?= ($current_page == 'inventory.php') ? 'active' : '' ?>">
            <i class="fas fa-archive"></i> Inventory
        </a>
        <a href="orders.php" class="<?= ($current_page == 'orders.php') ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i> Orders
        </a>
        <a href="reports.php" class="<?= ($current_page == 'reports.php') ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> Reports
        </a>
    </nav>
    <a href="../auth/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</aside>

    <main class="main">
        <div class="header">
            <h1>Dashboard Overview</h1>
            <p style="color: var(--text-muted);">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>.</p>
        </div>

        <div class="stats-grid">
            <div class="card">
                <span class="card-label">Total Revenue</span>
                <p class="card-value">₱<?= number_format($sales['total'] ?? 0, 2) ?></p>
            </div>
            <div class="card" style="border-left: 4px solid var(--accent-green);">
                <span class="card-label" style="color: var(--accent-green);">Today's Sales</span>
                <p class="card-value">₱<?= number_format($daily_sales, 2) ?></p>
            </div>
            <div class="card">
                <span class="card-label">Total Products</span>
                <p class="card-value"><?= $products['total'] ?></p>
            </div>
            <div class="card">
                <span class="card-label">Active Staff</span>
                <p class="card-value"><?= $staff['total'] ?></p>
            </div>
        </div>

        <?php if($critical_count > 0): ?>
        <div class="warning-box">
            <h3 style="color: var(--accent-red); margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-triangle"></i> Low Stock Alert
            </h3>
            <div style="display: grid; gap: 8px;">
                <?php while($item = $critical_stock_query->fetch_assoc()): ?>
                    <div style="display: flex; justify-content: space-between; background: rgba(255,255,255,0.03); padding: 12px 18px; border-radius: 12px;">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span style="color: var(--accent-red); font-weight: 800;"><?= $item['total_stock'] ?> left</span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="table-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-weight: 700;">Recent Transactions</h3>
                <a href="orders.php" style="color: var(--accent-blue); text-decoration: none; font-size: 0.85rem; font-weight: 700;">View All Activity →</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Staff</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $recent_orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td style="font-weight: 600;"><?= htmlspecialchars($row['customer_name'] ?: 'Guest') ?></td>
                        <td style="color: var(--accent-green); font-weight: 700;">₱<?= number_format($row['total'], 2) ?></td>
                        <td><?= htmlspecialchars($row['staff_name']) ?></td>
                        <td style="color: var(--text-muted);"><?= date('M d, g:i A', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

// Para kusa ring magsara kapag pinindot ang labas ng sidebar
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    
    const toggleBtn = document.querySelector('.mobile-toggle');
    
    if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
        sidebar.classList.remove('active');
    }
});
    </script>
</body>
</html>