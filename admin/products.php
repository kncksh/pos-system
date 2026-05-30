<?php
session_start();
include('../config/database.php');

// 1. Secure admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$message = "";

// --- ADD PRODUCT LOGIC ---
if (isset($_POST['add_product'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $category = $_POST['category'];
    $stock = intval($_POST['stock']);

    if ($category === 'Milk Tea') {
        $p_m = floatval($_POST['price_m']);
        $p_l = floatval($_POST['price_l']);
        
        $stmt = $conn->prepare("INSERT INTO products (name, category, price, total_stock) VALUES (?, ?, ?, ?)");
        
        $m_name = $name . " (M)";
        $stmt->bind_param("ssdi", $m_name, $category, $p_m, $stock);
        $stmt->execute();

        $l_name = $name . " (L)";
        $stmt->bind_param("ssdi", $l_name, $category, $p_l, $stock);
        $stmt->execute();
        $stmt->close();
    } else {
        $price = floatval($_POST['price']);
        $stmt = $conn->prepare("INSERT INTO products (name, category, price, total_stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $name, $category, $price, $stock);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: products.php?success=1");
    exit();
}

// --- UPDATE PRICES LOGIC ---
if (isset($_POST['update_all_prices'])) {
    if (isset($_POST['prices']) && is_array($_POST['prices'])) {
        foreach ($_POST['prices'] as $id => $new_price) {
            $stmt = $conn->prepare("UPDATE products SET price = ? WHERE id = ?");
            $stmt->bind_param("di", $new_price, $id);
            $stmt->execute();
            $stmt->close();
        }
        $message = "Prices updated successfully!";
    }
}

// --- DELETE LOGIC ---
if (isset($_GET['delete_name']) && isset($_GET['cat'])) {
    $name_to_delete = $_GET['delete_name'];
    $category_to_delete = $_GET['cat'];

    $search_name = $name_to_delete . "%";
    $stmt = $conn->prepare("DELETE FROM products WHERE name LIKE ? AND category = ?");
    $stmt->bind_param("ss", $search_name, $category_to_delete);
    $stmt->execute();
    $stmt->close();
    header("Location: products.php?msg=deleted");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | POS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

        .header-section { margin-bottom: 2rem; }
        .header-section h1 { font-size: 1.8rem; font-weight: 800; }
        .header-section p { color: var(--text-muted); }

        /* Form Container */
        .glass-card { 
            background: var(--card-bg); 
            border: 1px solid var(--border-color); 
            backdrop-filter: blur(12px); 
            border-radius: 24px; 
            padding: 2rem; 
            margin-bottom: 2.5rem; 
        }
        
        .form-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; }
        
        input, select { 
            background: rgba(15, 23, 42, 0.8); 
            border: 1px solid var(--border-color); 
            color: white; 
            padding: 14px 18px; 
            border-radius: 12px; 
            outline: none; 
            width: 100%;
            transition: 0.2s;
        }
        input:focus { border-color: var(--accent-blue); background: rgba(15, 23, 42, 1); }

        .btn-primary { 
            background: var(--accent-blue); 
            color: white; 
            border: none; 
            padding: 14px; 
            border-radius: 12px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: 0.3s; 
            margin-top: 15px; 
            width: 100%; 
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3); }

        /* Table Design */
        .table-wrapper { 
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 24px;
            border: 1px solid var(--border-color);
            overflow-x: auto; 
        }
        table { width: 100%; border-collapse: collapse; }
        th { color: var(--text-muted); padding: 15px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border-color); }
        td { padding: 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.02); }

        .price-badge { background: var(--bg-dark); border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 10px; display: flex; align-items: center; gap: 8px; }
        .price-tag { font-size: 0.7rem; font-weight: 800; color: var(--accent-blue); }
        .price-input { width: 60px !important; padding: 4px !important; border: none !important; background: transparent !important; text-align: center; }

        .badge-cat { background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; }
        
        .save-mini { background: var(--accent-blue); border: none; color: white; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 700; }
        .delete-btn { color: var(--accent-red); text-decoration: none; margin-left: 15px; font-size: 1.1rem; transition: 0.2s; }
        .delete-btn:hover { opacity: 0.7; }

        .success-msg { background: rgba(16, 185, 129, 0.1); color: var(--accent-green); padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 600; }

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

<div class="main">
    <div class="header-section">
        <h1>Product Management</h1>
        <p>Add, update pricing, and manage your shop menu items.</p>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="success-msg">✨ New product added to the database successfully!</div>
    <?php endif; ?>

    <?php if(!empty($message)): ?>
        <div class="success-msg">✅ <?= $message ?></div>
    <?php endif; ?>

    <div class="glass-card">
        <h3 style="margin-bottom: 1.2rem; color: var(--accent-green);">+ Add New Item</h3>
        <form method="POST">
            <div class="form-grid">
                <input name="name" placeholder="Product Name (e.g. Wintermelon)" required>
                <select name="category" id="categorySelect" onchange="toggleMilkTea()" required>
                    <option value="">Category</option>
                    <option value="Milk Tea">Milk Tea</option>
                    <option value="Pizza">Pizza</option>
                    <option value="Noodles">Noodles</option>
                    <option value="Sides & Snacks">Sides & Snacks</option>
                </select>
                <input name="stock" type="number" placeholder="Initial Stock" required>
            </div>

            <div id="standardPriceDiv" style="margin-top: 15px;">
                <input name="price" type="number" step="0.01" placeholder="Selling Price (₱)" id="priceInput">
            </div>

            <div id="milkTeaPriceDiv" style="display: none; margin-top: 15px; grid-template-columns: 1fr 1fr; gap: 15px;">
                <input name="price_m" type="number" step="0.01" placeholder="Price Medium (M) ₱">
                <input name="price_l" type="number" step="0.01" placeholder="Price Large (L) ₱">
            </div>

            <button name="add_product" class="btn-primary">Confirm & Save Product</button>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Product Details</th>
                    <th>Category</th>
                    <th style="text-align: center;">Price Settings</th>
                    <th style="text-align: center;">Stock</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $res = $conn->query("SELECT * FROM products ORDER BY category, name ASC");
                $grouped = [];
                while($row = $res->fetch_assoc()) {
                    $clean_name = preg_replace('/ \(M\)| \(L\)/', '', $row['name']);
                    $key = $clean_name . "_" . $row['category'];
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = ['name' => $clean_name, 'category' => $row['category'], 'stock' => $row['total_stock'], 'items' => []];
                    }
                    $grouped[$key]['items'][] = $row;
                }

                foreach($grouped as $product):
                ?>
                <tr>
                    <td><div style="font-weight: 700;"><?= $product['name'] ?></div></td>
                    <td><span class="badge-cat"><?= $product['category'] ?></span></td>
                    <td>
                        <form method="POST" style="display: flex; gap: 8px; justify-content: center;">
                            <?php foreach($product['items'] as $item): 
                                $size = (strpos($item['name'], '(M)') !== false) ? 'M' : ((strpos($item['name'], '(L)') !== false) ? 'L' : '₱');
                            ?>
                                <div class="price-badge">
                                    <span class="price-tag"><?= $size ?></span>
                                    <input type="number" step="0.01" name="prices[<?= $item['id'] ?>]" value="<?= $item['price'] ?>" class="price-input">
                                </div>
                            <?php endforeach; ?>
                    </td>
                    <td style="text-align: center; font-weight: 800; color: var(--accent-green);"><?= $product['stock'] ?></td>
                    <td style="text-align: center;">
                        <button name="update_all_prices" class="save-mini">Save</button>
                        </form>
                        <a href="products.php?delete_name=<?= urlencode($product['name']) ?>&cat=<?= urlencode($product['category']) ?>" 
                           onclick="return confirm('Delete this product and all its sizes?')" class="delete-btn">
                           <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($grouped)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 50px; color: var(--text-muted);">No products found in database.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleMilkTea() {
    const cat = document.getElementById('categorySelect').value;
    const std = document.getElementById('standardPriceDiv');
    const milk = document.getElementById('milkTeaPriceDiv');
    const priceInput = document.getElementById('priceInput');

    if (cat === 'Milk Tea') {
        std.style.display = 'none';
        milk.style.display = 'grid';
        priceInput.removeAttribute('required');
    } else {
        std.style.display = 'block';
        milk.style.display = 'none';
        priceInput.setAttribute('required', 'true');
    }
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