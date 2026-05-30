<?php
// Siguraduhin na ang session ay nagsimula para makuha ang user_id
session_start();

// 1. I-include ang mga kailangang files
include('../config/database.php');

// Header para kilalanin ng browser na JSON ang isasagot natin
header('Content-Type: application/json');

// 2. Kunin ang JSON data mula sa POS frontend (fetch API)
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Walang data na natanggap.']);
    exit();
}

// 3. I-assign ang mga variables base sa pinasa ng POS
$customer_name = isset($data['customer_name']) ? trim($data['customer_name']) : 'Guest';
$cart_items    = $data['cart_data']; // Ito ang array ng mga biniling items
$staff_id      = $_SESSION['user_id'] ?? 1; // Default sa 1 kung testing phase pa lang

// Kalkulahin ang total amount sa server-side para iwas sa "price tampering"
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += (float)$item['price'] * (int)$item['qty'];
}

// --- SIMULA NG DATABASE TRANSACTION ---
$conn->begin_transaction();

try {
    // 4. INSERT SA ORDERS TABLE
    $stmt_order = $conn->prepare("INSERT INTO orders (customer_name, total, staff_id) VALUES (?, ?, ?)");
    $stmt_order->bind_param("sdi", $customer_name, $total_amount, $staff_id);
    
    if (!$stmt_order->execute()) {
        throw new Exception("Hindi ma-save ang order: " . $conn->error);
    }
    
    $order_id = $conn->insert_id;

    // 5. I-PREPARE ANG MGA STATEMENTS PARA SA ITEMS AT STOCK
    // Insert sa order_items table
    $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)");
    
    // Update stock sa products table (Base sa column name mo na 'total_stock')
    $stmt_update_stock = $conn->prepare("UPDATE products SET total_stock = total_stock - ? WHERE id = ? AND total_stock >= ?");

    foreach ($cart_items as $item) {
        $p_id  = (int)$item['id'];
        $qty   = (int)$item['qty'];
        $price = (float)$item['price'];
        $size  = $item['size'];

        // A. I-save ang bawat item sa order_items
        $stmt_items->bind_param("iiids", $order_id, $p_id, $qty, $price, $size);
        if (!$stmt_items->execute()) {
            throw new Exception("Failed to save item ID: $p_id");
        }

        // B. Bawasan ang stock sa products table
        // Ang huling parameter ($qty) ay para sa "stock >= ?" check para iwas negative stock
        $stmt_update_stock->bind_param("iii", $qty, $p_id, $qty);
        $stmt_update_stock->execute();

        // Kung walang nabagong row, ibig sabihin kulang ang stock
        if ($stmt_update_stock->affected_rows === 0) {
            throw new Exception("Kulang ang stock para sa item: " . $item['name']);
        }
    }

    // 6. COMMIT (Save permanently kung walang error)
    $conn->commit();
    
    echo json_encode([
        'status' => 'success', 
        'order_id' => $order_id,
        'message' => 'Order processed successfully!'
    ]);

} catch (Exception $e) {
    // 7. ROLLBACK (Bawiin lahat kung may kahit isang error)
    $conn->rollback();
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?>