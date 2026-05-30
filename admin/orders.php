<?php
/**
 * POS Kiosk System - Order History (Professional Updated)
 */
include('../config/security.php');
restrictToAdmin(); 
include('../config/database.php');

$current_page = basename($_SERVER['PHP_SELF']);

// --- DELETE LOGIC ---
if (isset($_GET['delete_id'])) {
    $id_to_delete = intval($_GET['delete_id']);
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM order_items WHERE order_id = $id_to_delete");
        $conn->query("DELETE FROM orders WHERE id = $id_to_delete");
        $conn->commit();
        header("Location: orders.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error deleting order: " . $e->getMessage());
    }
}

// --- FILTER & SEARCH ---
$filter = $_GET['filter'] ?? 'All';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$query = "SELECT * FROM orders WHERE 1=1";
if ($filter == 'Today') {
    $query .= " AND DATE(created_at) = CURDATE()";
} elseif ($filter == 'Week') {
    $query .= " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
}

if (!empty($search)) {
    $query .= " AND (id LIKE '%$search%' OR customer_name LIKE '%$search%')";
}
$query .= " ORDER BY created_at DESC";
$orders = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History | POS Admin</title>
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
        body { background: var(--bg-dark); color: var(--text-main); display: flex; min-height: 100vh; }

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
        
        .header { margin-bottom: 2rem; }
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 15px; }
        
        .search-box { 
            background: var(--card-bg); border: 1px solid var(--border-color); 
            padding: 12px 18px; border-radius: 15px; display: flex; align-items: center; gap: 12px;
            width: 100%; max-width: 350px; backdrop-filter: blur(10px);
        }
        .search-box input { background: transparent; border: none; color: white; outline: none; width: 100%; }
        .search-box i { color: var(--text-muted); }

        .filter-tabs { display: flex; gap: 10px; }
        .tab { 
            padding: 10px 20px; border-radius: 12px; background: var(--card-bg); 
            color: var(--text-muted); text-decoration: none; font-size: 0.85rem; 
            font-weight: 600; border: 1px solid var(--border-color); transition: 0.3s;
        }
        .tab.active { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }

        /* TABLE */
        .table-card { background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border-color); padding: 1.5rem; overflow-x: auto; backdrop-filter: blur(12px); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border-color); }
        td { padding: 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.02); }

        /* RECEIPT PREVIEW */
        .receipt-container { 
            background: #ffffff; color: #000 !important; padding: 30px; 
            border-radius: 8px; max-width: 400px; margin: 10px auto; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.4); font-family: 'Courier New', Courier, monospace;
        }
        .receipt-container * { color: #000 !important; border-color: #000 !important; }

        .btn-view { background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); border: 1px solid var(--accent-blue); padding: 8px 16px; border-radius: 10px; cursor: pointer; font-weight: 700; transition: 0.2s; }
        .btn-view:hover { background: var(--accent-blue); color: white; }
        .btn-delete { color: var(--accent-red); text-decoration: none; font-size: 1.1rem; margin-left: 15px; transition: 0.2s; }
        .btn-delete:hover { opacity: 0.7; }

        @media print {
            body * { visibility: hidden; }
            .printing-area, .printing-area * { visibility: visible; }
            .printing-area { position: fixed; left: 50%; top: 0; transform: translateX(-50%); width: 100%; }
            .no-print { display: none !important; }
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
        <div class="header no-print">
            <h1 style="font-weight: 800; font-size: 1.8rem;">Order History</h1>
            <p style="color: var(--text-muted);">Track sales performance and manage digital receipts.</p>
        </div>

        <div class="controls no-print">
            <div class="filter-tabs">
                <a href="orders.php?filter=All" class="tab <?= $filter == 'All' ? 'active' : '' ?>">All Records</a>
                <a href="orders.php?filter=Today" class="tab <?= $filter == 'Today' ? 'active' : '' ?>">Today Only</a>
            </div>
            <form action="" method="GET" class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Order ID or Name..." value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            <div class="no-print" style="background: rgba(239, 68, 68, 0.1); color: var(--accent-red); padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.2); font-weight: 600;">
                <i class="fas fa-trash-alt"></i> Order record has been permanently deleted.
            </div>
        <?php endif; ?>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Amount</th>
                        <th style="text-align: right;">Management</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $orders->fetch_assoc()): $oid = $row['id']; ?>
                    <tr>
                        <td style="font-weight: 800; color: var(--accent-blue);">#<?= str_pad($oid, 5, '0', STR_PAD_LEFT) ?></td>
                        <td style="font-weight: 600;"><?= htmlspecialchars($row['customer_name'] ?: 'Guest / Walk-in') ?></td>
                        <td style="color: var(--text-muted);"><?= date('M d, Y • h:i A', strtotime($row['created_at'])) ?></td>
                        <td style="font-weight: 800; color: var(--accent-green);">₱<?= number_format($row['total'], 2) ?></td>
                        <td style="text-align: right;">
                            <button class="btn-view no-print" onclick="toggleDetails(<?= $oid ?>)">Details</button>
                            <a href="orders.php?delete_id=<?= $oid ?>" class="btn-delete no-print" onclick="return confirm('Attention: Delete this order permanently?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <tr id="details-<?= $oid ?>" style="display: none;" class="detail-row no-print">
                        <td colspan="5" style="background: rgba(0,0,0,0.2); padding: 30px;">
                            <div id="print-area-<?= $oid ?>" class="receipt-container">
                                <div style="text-align: center; border-bottom: 2px dashed #000; padding-bottom: 15px; margin-bottom: 15px;">
                                    <h3 style="margin: 0; font-size: 1.3rem;">POS SYSTEM</h3>
                                    <p style="font-size: 0.9rem;">Official Receipt</p>
                                    <p style="font-size: 0.75rem;">ID: #<?= $oid ?> | <?= date('m/d/Y h:i A', strtotime($row['created_at'])) ?></p>
                                </div>
                                
                                <table style="width: 100%; font-size: 0.85rem; border: none;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid #000;">
                                            <th style="text-align: left; padding: 5px 0;">Item</th>
                                            <th style="text-align: center;">Qty</th>
                                            <th style="text-align: right;">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $items = $conn->query("SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE order_id = $oid");
                                        while($item = $items->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td style="padding: 5px 0;"><?= htmlspecialchars($item['name'] ?? 'Item Deleted') ?></td>
                                            <td style="text-align: center;"><?= $item['quantity'] ?></td>
                                            <td style="text-align: right;">₱<?= number_format($item['price'], 2) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>

                                <div style="margin-top: 15px; border-top: 2px dashed #000; padding-top: 15px; text-align: right;">
                                    <span style="font-weight: 800; font-size: 1.1rem;">TOTAL: ₱<?= number_format($row['total'], 2) ?></span>
                                </div>
                                <p style="text-align: center; font-size: 0.75rem; margin-top: 20px;">Thank you for your purchase!</p>
                                
                                <div style="text-align: center; margin-top: 20px;" class="no-print">
                                    <button onclick="printReceipt(<?= $oid ?>)" style="background: #000; color: #fff; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: 800; width: 100%;">
                                        <i class="fas fa-print"></i> Print Receipt
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($orders->num_rows == 0): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 50px; color: var(--text-muted);">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
    function toggleDetails(id) {
        const row = document.getElementById('details-' + id);
        const isVisible = row.style.display === 'table-row';
        document.querySelectorAll('.detail-row').forEach(r => r.style.display = 'none');
        row.style.display = isVisible ? 'none' : 'table-row';
    }

    function printReceipt(id) {
        const content = document.getElementById('print-area-' + id);
        content.classList.add('printing-area');
        window.print();
        setTimeout(() => content.classList.remove('printing-area'), 500);
    }
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