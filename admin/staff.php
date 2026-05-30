<?php
session_start();
include('../config/database.php');

// Secure admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$message = "";
$msg_type = ""; 

// ADD NEW STAFF
if (isset($_POST['add_staff'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "Error: Username na ginamit na!";
        $msg_type = "error";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, 'staff')");
        $stmt->bind_param("sss", $name, $username, $hashed_password);
        
        if($stmt->execute()) {
            $message = "Staff member successfully registered!";
            $msg_type = "success";
        } else {
            $message = "Something went wrong. Please try again.";
            $msg_type = "error";
        }
        $stmt->close();
    }
}

// EDIT STAFF
if (isset($_POST['edit_staff'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, username=?, password=? WHERE id=? AND role='staff'");
        $stmt->bind_param("sssi", $name, $username, $password, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, username=? WHERE id=? AND role='staff'");
        $stmt->bind_param("ssi", $name, $username, $id);
    }
    
    if($stmt->execute()){
        $message = "Staff profile updated!";
        $msg_type = "success";
    }
    $stmt->close();
}

// DELETE STAFF
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if($id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='staff'");
        $stmt->bind_param("i", $id);
        if($stmt->execute()){
            $message = "Account removed successfully.";
            $msg_type = "success";
        }
        $stmt->close();
    }
}

$staffs = $conn->query("SELECT * FROM users WHERE role='staff' ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management | POS Admin</title>
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
        body { background: var(--bg-dark); color: var(--text-main); display: flex; min-height: 100vh; }

        /* --- SIDEBAR --- */
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
        .main { flex: 1; margin-left: var(--sidebar-width); padding: 3rem; width: calc(100% - var(--sidebar-width)); }
        
        .header-section { margin-bottom: 2.5rem; }
        .header-section h1 { font-size: 2rem; font-weight: 800; }
        
        /* Alert Box */
        .alert { 
            padding: 16px 20px; border-radius: 14px; margin-bottom: 25px; font-weight: 600; 
            display: flex; align-items: center; gap: 12px; border: 1px solid transparent; 
        }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--accent-green); border-color: rgba(16, 185, 129, 0.2); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: var(--accent-red); border-color: rgba(239, 68, 68, 0.2); }

        /* Form Card */
        .glass-card { background: var(--card-bg); border: 1px solid var(--border-color); backdrop-filter: blur(12px); border-radius: 24px; padding: 2rem; margin-bottom: 2.5rem; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: flex-end; }
        
        label { font-size: 0.7rem; font-weight: 800; color: var(--accent-blue); text-transform: uppercase; margin-bottom: 8px; display: block; }
        
        input { 
            background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color); color: white; 
            padding: 12px 16px; border-radius: 12px; outline: none; width: 100%; transition: 0.3s;
        }
        input:focus { border-color: var(--accent-blue); background: rgba(15, 23, 42, 0.9); }

        .btn-register { background: var(--accent-green); color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-register:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }

        /* Table Design */
        .table-wrapper { background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border-color); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(255,255,255,0.02); color: var(--text-muted); padding: 18px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border-color); }
        
        .td-input { padding: 8px 12px; font-size: 0.9rem; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid transparent; }
        .td-input:focus { border-color: var(--accent-blue); }

        .btn-save { background: var(--accent-blue); color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 700; }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: var(--accent-red); border: 1px solid rgba(239, 68, 68, 0.2); padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.85rem; transition: 0.2s; }
        .btn-delete:hover { background: var(--accent-red); color: white; }

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
    <div class="header-section">
        <h1>Staff Management</h1>
        <p style="color: var(--text-muted);">Manage employee credentials and system access.</p>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?= $msg_type ?>">
            <?= ($msg_type == 'success' ? '✅' : '⚠️') ?> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="glass-card">
        <h3 style="margin-bottom: 1.5rem; color: var(--accent-green);">➕ Register New Staff</h3>
        <form method="POST">
            <div class="form-grid">
                <div>
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Juan Dela Cruz">
                </div>
                <div>
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="juan_pos">
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button name="add_staff" type="submit" class="btn-register">Create Account</button>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
            <h3 style="font-size: 1.1rem;">Active Staff Directory</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Username</th>
                    <th>Update Password</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($staffs->num_rows > 0): ?>
                    <?php while($row = $staffs->fetch_assoc()): ?>
                    <tr>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <td>
                                <input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>" class="td-input" required>
                            </td>
                            <td>
                                <input type="text" name="username" value="<?= htmlspecialchars($row['username']) ?>" class="td-input" required>
                            </td>
                            <td>
                                <input type="password" name="password" placeholder="Leave blank to keep current" class="td-input">
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <button type="submit" name="edit_staff" class="btn-save">Save</button>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('Remove this staff account?')">Delete</a>
                                </div>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--text-muted);">No staff members found.</td></tr>
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