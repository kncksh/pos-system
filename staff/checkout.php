<?php
session_start();
include('../config/database.php');

// Siguraduhin na JSON ang output para mabasa ng fetch sa pos.php
header('Content-Type: application/json');

try {
    // 1. Validation ng basic requirements
    if (!isset($_POST['cart_data'])) {
        throw new Exception("Cart data is missing.");
    }

    $cart = json_decode($_POST['cart_data'], true);
    if (empty($cart)) {
        throw new Exception("Cart is empty.");
    }

    $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : 'Guest';
    
    // Siguraduhin na ang session key (user_id) ay naka-set mula sa login_process.php
    $staff_id = $_SESSION['user_id'] ?? 0;

    // SIMULAN ANG TRANSACTION
    // Ibig sabihin: Lahat ng SQL sa baba dapat mag-success. Kung isa lang ang pumalya, 
    // i-cacancel (rollback) natin lahat para hindi maging "ghost order".
    $conn->begin_transaction();

    $total_amount = 0;
    foreach ($cart as $item) {
        $total_amount += $item['price'] * $item['qty'];
    }

    // 2. INSERT SA ORDERS TABLE
    // Note: Siguraduhin na ang table mo ay may 'customer_name' column.
    $stmt_order = $conn->prepare("INSERT INTO orders (total, staff_id, customer_name, created_at) VALUES (?, ?, ?, NOW())");
    
    // "ds s" -> d (double/total), i (integer/staff_id), s (string/customer_name)
    // TANDAAN: Check mo sa DB kung decimal ang total at int ang staff_id.
    $stmt_order->bind_param("dis", $total_amount, $staff_id, $customer_name);
    
    if (!$stmt_order->execute()) {
        throw new Exception("Failed to create order: " . $stmt_order->error);
    }
    
    $order_id = $conn->insert_id;

    // 3. I-PREPARE ANG STATEMENTS PARA SA ITEMS AT STOCK UPDATE
    $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmt_update_total_stock = $conn->prepare("UPDATE products SET total_stock = total_stock - ? WHERE id = ?");

    foreach ($cart as $item) {
        $p_id = $item['id'];
        $qty = $item['qty'];
        $price = $item['price'];

        // A. I-check muna kung may sapat pang stock (Double Check Security)
        $total_stock_check = $conn->prepare("SELECT total_stock FROM products WHERE id = ?");
        $total_stock_check->bind_param("i", $p_id);
        $total_stock_check->execute();
        $current_total_stock = $total_stock_check->get_result()->fetch_assoc()['total_stock'];

        if ($current_total_stock < $qty) {
            throw new Exception("Insufficient stock for item: " . $item['name']);
        }

        // B. I-save sa order_items
        $stmt_items->bind_param("iiid", $order_id, $p_id, $qty, $price);
        if (!$stmt_items->execute()) {
            throw new Exception("Failed to save order item: " . $p_id);
        }

        // C. BAWASAN ang stock sa products table
        $stmt_update_total_stock->bind_param("ii", $qty, $p_id);
        if (!$stmt_update_total_stock->execute()) {
            throw new Exception("Failed to update stock for item ID: " . $p_id);
        }
    }

    // 4. I-FINALIZE (COMMIT)
    // Kung nakarating dito nang walang error, i-save na lahat sa DB.
    $conn->commit();

    // Success response
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Order processed successfully!'
    ]);

} catch (Exception $e) {
    // Kapag may kahit isang error sa itaas, i-cancel lahat ng database changes
    if (isset($conn)) $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>