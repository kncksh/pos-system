<?php
session_start();
include('../config/database.php');

// Kunin ang ID mula sa URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // 1. Hanapin ang produkto sa database para makuha ang detalye
    $result = $conn->query("SELECT id, name, price, stock FROM products WHERE id = $id");
    $product = $result->fetch_assoc();

    if ($product) {
        // 2. I-initialize ang cart kung wala pa
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // 3. I-check kung nasa cart na ang item
        if (isset($_SESSION['cart'][$id])) {
            // Kung may stock pa, dagdagan ang quantity
            if ($_SESSION['cart'][$id]['qty'] < $product['stock']) {
                $_SESSION['cart'][$id]['qty'] += 1;
            }
        } else {
            // Kung wala pa, i-add bilang bagong entry
            $_SESSION['cart'][$id] = [
                'id'    => $product['id'],
                'name'  => $product['name'],
                'price' => $product['price'],
                'qty'   => 1
            ];
        }
    }
}

// 4. I-redirect pabalik sa POS page para makita ang update
header("Location: pos.php");
exit();