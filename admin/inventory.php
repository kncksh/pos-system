<?php
/**
 * POS Kiosk System - Inventory Management (Professional Updated)
 */
include('../config/security.php');
restrictToAdmin(); 
include('../config/database.php');

$admin_id = $_SESSION['user_id'] ?? 0; 
$current_page = basename($_SERVER['PHP_SELF']);

// --- SEARCH & FILTER LOGIC ---
$category_filter = $_GET['category'] ?? 'All';
$search_query = $_GET['search'] ?? '';

$sql = "SELECT * FROM products WHERE 1=1";

if ($category_filter !== 'All') {
    $cat = $conn->real_escape_string($category_filter);
    $sql .= " AND REPLACE(category, ' ', '') = REPLACE('$cat', ' ', '')";
}

if (!empty($search_query)) {
    $search = $conn->real_escape_string($search_query);
    $sql .= " AND (name LIKE '%$search%' OR id LIKE '%$search%')";
}

$sql .= " ORDER BY total_stock ASC"; 
$products = $conn->query($sql);

// --- UPDATE STOCK WITH TRANSACTION ---
if (isset($_POST['update_stock'])) {
    $id = intval($_POST['id']);
    $new_stock = intval($_POST['total_stock']);

    $conn->begin_transaction();
    try {
        $res = $conn->query("SELECT total_stock, name FROM products WHERE id = $id");
        if ($row = $res->fetch_assoc()) {
            $old_stock = $row['total_stock'];
            $diff = $new_stock - $old_stock;

            if ($diff != 0) {
                $stmt = $conn->prepare("UPDATE products SET total_stock = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_stock, $id);
                $stmt->execute();

                $remarks = "Manual Update: From $old_stock to $new_stock";
                $log_stmt = $conn->prepare("INSERT INTO stock_history (product_id, user_id, quantity_added, remarks) VALUES (?, ?, ?, ?)");
                $log_stmt->bind_param("iiis", $id, $admin_id, $diff, $remarks);
                $log_stmt->execute();
            }
            $conn->commit();
            header("Location: inventory.php?category=$category_filter&success=1");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Tracking | POS Admin</title>
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
            --accent-orange: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --sidebar-width: 260px;
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-dark); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; }

        /* --- UNIFIED SIDEBAR (Same as Dashboard) --- */
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

        .inventory-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        
        .search-box { 
            background: var(--card-bg); border: 1px solid var(--border-color); 
            padding: 12px 18px; border-radius: 15px; display: flex; align-items: center; gap: 12px;
            width: 100%; max-width: 350px; backdrop-filter: blur(10px);
        }
        .search-box input { background: transparent; border: none; color: white; outline: none; width: 100%; font-size: 0.9rem; }
        .search-box i { color: var(--text-muted); }

        /* FILTERS */
        .filter-tabs { display: flex; gap: 12px; margin-bottom: 2rem; overflow-x: auto; padding-bottom: 10px; }
        .tab { 
            padding: 10px 20px; border-radius: 12px; background: var(--card-bg); 
            color: var(--text-muted); text-decoration: none; font-size: 0.85rem; 
            font-weight: 600; border: 1px solid var(--border-color); transition: 0.3s;
        }
        .tab:hover { border-color: var(--accent-blue); color: var(--accent-blue); }
        .tab.active { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }

        /* TABLE */
        .table-card { 
            background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border-color); 
            padding: 1.5rem; overflow-x: auto; backdrop-filter: blur(12px); 
        }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border-color); }
        td { padding: 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.02); }

        /* STOCK INPUTS & BADGES */
        .stock-input { 
            background: rgba(0,0,0,0.4); border: 1px solid var(--border-color); 
            color: white; padding: 10px; border-radius: 10px; width: 85px; 
            text-align: center; font-weight: 800; outline: none; transition: 0.2s;
        }
        .stock-input:focus { border-color: var(--accent-blue); background: rgba(59, 130, 246, 0.05); }

        .badge { padding: 6px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-low { background: rgba(245, 158, 11, 0.1); color: var(--accent-orange); }
        .status-out { background: rgba(239, 68, 68, 0.1); color: var(--accent-red); }
        .status-ok { background: rgba(16, 185, 129, 0.1); color: var(--accent-green); }

        .save-btn { 
            background: var(--accent-blue); color: white; border: none; 
            padding: 10px 20px; border-radius: 10px; cursor: pointer; 
            font-weight: 700; font-size: 0.8rem; transition: 0.3s;
        }
        .save-btn:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3); }

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
        <div class="inventory-header">
            <div>
                <h1 style="font-weight: 800; font-size: 1.8rem;">Inventory Tracking</h1>
                <p style="color: var(--text-muted);">Manage stock levels and monitor product health.</p>
            </div>
            <form action="" method="GET" class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Find a product..." value="<?= htmlspecialchars($search_query) ?>">
                <?php if($category_filter != 'All'): ?>
                    <input type="hidden" name="category" value="<?= $category_filter ?>">
                <?php endif; ?>
            </form>
        </div>

        <div class="filter-tabs">
            <?php 
            $categories = ['All', 'Milk Tea', 'Noodles', 'Pizza', 'Snacks'];
            foreach($categories as $cat): 
                $active = ($category_filter == $cat) ? 'active' : '';
            ?>
                <a href="inventory.php?category=<?= urlencode($cat) ?>" class="tab <?= $active ?>"><?= $cat ?></a>
            <?php endforeach; ?>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); color: var(--accent-green); padding: 15px; border-radius: 15px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 600;">
                <i class="fas fa-check-circle"></i> Stock successfully updated and logged.
            </div>
        <?php endif; ?>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Stock Level</th>
                        <th>Health Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $products->fetch_assoc()): 
                        $stock = $row['total_stock'];
                        if($stock <= 0) {
                            $status_class = "status-out"; $status_text = "Out of Stock";
                        } elseif($stock <= 10) {
                            $status_class = "status-low"; $status_text = "Low Stock";
                        } else {
                            $status_class = "status-ok"; $status_text = "Healthy";
                        }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($row['name']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">SKU: #<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></div>
                        </td>
                        <td><span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;"><?= $row['category'] ?></span></td>
                        <td>
                            <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="number" name="total_stock" class="stock-input" value="<?= $stock ?>">
                        </td>
                        <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                        <td>
                            <button type="submit" name="update_stock" class="save-btn">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($products->num_rows == 0): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 50px; color: var(--text-muted);">No products found in this category.</td></tr>
                    <?php endif; ?>
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