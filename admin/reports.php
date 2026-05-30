<?php
include('../config/security.php');
restrictToAdmin();
include('../config/database.php');

$current_page = basename($_SERVER['PHP_SELF']);

// Filter logic (Default: Current Month/Year)
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Query para sa Daily Sales Summary
$query = "SELECT DATE(created_at) as sale_date, COUNT(id) as total_orders, SUM(total) as daily_revenue 
          FROM orders 
          WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
          GROUP BY DATE(created_at)
          ORDER BY sale_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $month, $year);
$stmt->execute();
$report_res = $stmt->get_result();

$sales_data = [];
while ($row = $report_res->fetch_assoc()) {
    $sales_data[] = $row;
}

// Stats Query
$total_query = "SELECT SUM(total) as grand_total, COUNT(id) as total_orders_month FROM orders WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?";
$t_stmt = $conn->prepare($total_query);
$t_stmt->bind_param("ss", $month, $year);
$t_stmt->execute();
$stats = $t_stmt->get_result()->fetch_assoc();
$grand_total = $stats['grand_total'] ?? 0;
$total_orders_month = $stats['total_orders_month'] ?? 0;

$month_name = date('F', mktime(0, 0, 0, (int)$month, 10));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports | POS Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-dark); color: var(--text-main); display: flex; min-height: 100vh; }

        /* --- UNIFIED SIDEBAR --- */
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
            transition: 0.3s; 
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
        .main { flex: 1; margin-left: var(--sidebar-width); padding: 2.5rem; width: calc(100% - var(--sidebar-width)); }
        
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        
        .filter-box { 
            background: var(--card-bg); 
            padding: 1.5rem; 
            border-radius: 20px; 
            border: 1px solid var(--border-color); 
            display: flex; 
            gap: 15px; 
            align-items: flex-end; 
            margin-bottom: 2rem; 
        }

        select, .btn-apply { 
            background: rgba(15, 23, 42, 0.8); border: 1px solid var(--border-color); color: white; 
            padding: 10px 15px; border-radius: 10px; outline: none; cursor: pointer;
        }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 2rem; }
        .stat-card { 
            background: var(--card-bg); padding: 1.5rem; border-radius: 24px; border: 1px solid var(--border-color); 
            backdrop-filter: blur(10px);
        }
        .stat-card h3 { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .stat-card p { font-size: 1.8rem; font-weight: 800; }

        /* Chart & Table */
        .glass-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 2rem; margin-bottom: 2rem; }
        .chart-wrapper { height: 350px; width: 100%; margin-top: 1rem; }

        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { color: var(--text-muted); padding: 15px; text-align: left; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.03); }

        .btn-print { background: var(--accent-green); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-print:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }

        /* Print Logic */
        @media print {
            .no-print, .sidebar { display: none !important; }
            body { background: white !important; color: black !important; }
            .main { margin: 0 !important; padding: 1cm !important; width: 100% !important; }
            .stat-card, .glass-card { border: 1px solid #eee !important; background: white !important; color: black !important; box-shadow: none !important; }
            .stat-card p, td { color: black !important; }
            .signatories { display: flex !important; justify-content: space-around; margin-top: 50px; }
            .sig-line { border-top: 1px solid #000; width: 200px; text-align: center; margin-top: 40px; font-weight: bold; padding-top: 5px; }
        }
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
    <div class="header-flex">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800;">Sales Report</h1>
            <p style="color: var(--text-muted);">Data for <strong><?= $month_name ?> <?= $year ?></strong></p>
        </div>
        <button onclick="window.print()" class="btn-print no-print">🖨️ Print Report</button>
    </div>

    <form class="filter-box no-print" method="GET">
        <div style="flex: 1;">
            <label style="font-size: 0.65rem; font-weight: 800; color: var(--accent-blue); text-transform: uppercase;">Month</label><br>
            <select name="month" style="width: 100%;">
                <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= sprintf('%02d', $m) ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div style="flex: 1;">
            <label style="font-size: 0.65rem; font-weight: 800; color: var(--accent-blue); text-transform: uppercase;">Year</label><br>
            <select name="year" style="width: 100%;">
                <?php for($y=2024; $y<=2026; $y++): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn-apply" style="background: var(--accent-blue); font-weight: 700; height: 42px;">Filter</button>
    </form>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Monthly Revenue</h3>
            <p style="color: var(--accent-green);">₱<?= number_format($grand_total, 2) ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Orders</h3>
            <p><?= number_format($total_orders_month) ?></p>
        </div>
        <div class="stat-card">
            <h3>Avg. Order Value</h3>
            <p>₱<?= $total_orders_month > 0 ? number_format($grand_total / $total_orders_month, 2) : '0.00' ?></p>
        </div>
    </div>

    <div class="glass-card">
        <h3 style="color: var(--accent-blue);">Revenue Trend</h3>
        <div class="chart-wrapper">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <div class="glass-card">
        <h3>Daily Performance Breakdown</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transactions</th>
                        <th style="text-align: right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($sales_data)): ?>
                        <tr><td colspan="3" style="text-align:center; padding: 40px;">No data available for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach(array_reverse($sales_data) as $row): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= date('M d, Y', strtotime($row['sale_date'])) ?></td>
                                <td><?= $row['total_orders'] ?> orders</td>
                                <td style="text-align: right; font-weight: 800; color: var(--accent-green);">₱<?= number_format($row['daily_revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="signatories" style="display: none;">
        <div class="sig-block">
            <p>Prepared by:</p>
            <div class="sig-line">Cashier / Admin</div>
        </div>
        <div class="sig-block">
            <p>Certified Correct:</p>
            <div class="sig-line">Store Owner</div>
        </div>
    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const chartData = <?= json_encode($sales_data) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(d => new Date(d.sale_date).toLocaleDateString('en-US', {day:'numeric', month:'short'})),
            datasets: [{
                label: 'Daily Revenue (₱)',
                data: chartData.map(d => d.daily_revenue),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#94a3b8' }
                },
                x: { 
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            }
        }
    });
});
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