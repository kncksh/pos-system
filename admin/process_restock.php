<?php
include('../config/security.php');
restrictToAdmin();
include('../config/database.php');

if (isset($_POST['submit_restock'])) {
    $product_id = $_POST['product_id'];
    $add_qty = intval($_POST['add_qty']);
    $admin_id = $_SESSION['user_id'];

    if ($add_qty <= 0) {
        header("Location: inventory.php?error=invalid_qty");
        exit();
    }

    $conn->begin_transaction();

    try {
        // 1. Update ang Stock sa Products table
        $update_query = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $update_query->bind_param("ii", $add_qty, $product_id);
        $update_query->execute();

        // 2. I-record sa Stock History (Para sa Audit Trail)
        // Siguraduhin na nagawa mo na yung stock_history table sa database
        $log_query = $conn->prepare("INSERT INTO stock_history (product_id, user_id, quantity_added, remarks) VALUES (?, ?, ?, 'Manual Restock')");
        $log_query->bind_param("iii", $product_id, $admin_id, $add_qty);
        $log_query->execute();

        $conn->commit();
        header("Location: inventory.php?success=restocked");

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: inventory.php?error=failed");
    }
}