<?php
session_start();
include('../config/database.php');

// Kunin ang Order ID mula sa URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id === 0) {
    die("Invalid Order ID.");
}

// 1. FETCH ORDER DETAILS
$order_query = $conn->prepare("SELECT o.*, u.name as staff_name FROM orders o 
                               LEFT JOIN users u ON o.staff_id = u.id 
                               WHERE o.id = ?");
$order_query->bind_param("i", $order_id);
$order_query->execute();
$order = $order_query->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

// 2. FETCH ITEMS
$items_query = $conn->prepare("SELECT oi.*, p.name FROM order_items oi 
                                 JOIN products p ON oi.product_id = p.id 
                                 WHERE oi.order_id = ?");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$items = $items_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipt #<?= $order_id ?></title>
    <style>
        /* Thermal Printer Optimization */
        @page { size: auto; margin: 0mm; }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f5f5f5; /* Light gray for screen view only */
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .receipt-card {
            background: #fff;
            width: 320px; /* Standard 80mm thermal width */
            padding: 25px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 4px;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 15px 0; }

        .brand-name { font-size: 1.5rem; font-weight: bold; margin: 0; }
        .info-text { font-size: 0.85rem; margin: 2px 0; color: #333; }

        /* Highlight for Calling Name */
        .calling-name-section {
            border: 2px solid #000;
            padding: 15px 10px;
            margin: 20px 0;
            text-align: center;
        }
        .calling-label { font-size: 0.75rem; letter-spacing: 1px; font-weight: bold; }
        .calling-name { font-size: 2rem; font-weight: 900; display: block; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { font-size: 0.85rem; border-bottom: 1px solid #000; padding-bottom: 5px; }
        td { padding: 8px 0; font-size: 0.9rem; vertical-align: top; }

        .total-row { font-size: 1.3rem; font-weight: bold; }

        /* UI Buttons (Hidden when printing) */
        .action-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            font-family: sans-serif;
            transition: 0.3s;
        }
        .btn-print { background: #10b981; color: white; }
        .btn-back { background: #3b82f6; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt-card { box-shadow: none; width: 100%; }
            .action-buttons { display: none; }
        }
    </style>
</head>
<body>

    <div class="action-buttons">
        <a href="pos.php" class="btn btn-back">← Back to POS</a>
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Receipt</button>
    </div>

    <div class="receipt-card">
        <div class="text-center">
            <h1 class="brand-name">🍹 POS KIOSK</h1>
            <p class="info-text">123 Business Street, Caloocan City</p>
            <p class="info-text">VAT REG TIN: 000-123-456-000</p>
            <p class="info-text">Tel: 0912-345-6789</p>
        </div>

        <div class="divider"></div>

        <div style="font-size: 0.85rem;">
            <div style="display:flex; justify-content:space-between;">
                <span>Order ID:</span> <strong>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span>Date:</span> <span><?= date('M d, Y | h:i A', strtotime($order['created_at'])) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span>Cashier:</span> <span><?= htmlspecialchars($order['staff_name'] ?? 'System') ?></span>
            </div>
        </div>

        <div class="calling-name-section">
            <span class="calling-label">ORDER FOR:</span>
            <span class="calling-name"><?= htmlspecialchars($order['customer_name'] ?? 'GUEST') ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th align="left">Description</th>
                    <th align="center">Qty</th>
                    <th align="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td align="center"><?= $item['quantity'] ?></td>
                    <td align="right">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <table class="total-row">
            <tr>
                <td>TOTAL</td>
                <td align="right">₱<?= number_format($order['total'], 2) ?></td>
            </tr>
        </table>

        <div class="divider"></div>
        
        <div class="text-center" style="margin-top: 20px;">
            <p style="margin: 5px 0; font-weight: bold;">THANK YOU!</p>
            <p style="font-size: 0.75rem; margin: 0;">Please keep this receipt and wait for your name to be called.</p>
           <div style="margin-top: 25px; text-align: center; page-break-inside: avoid;">
    <?php
        // 1. DITO MO ILAGAY ANG LINK MO. Palitan mo ito ng totoong website URL mo.
        // Halimbawa: "https://mypostonline.com/track/" o kaya "https://t.me/mybot?start="
        $base_url = "https://iyong-website.com/track-order.php"; // <--- PALITAN MO ITO
        
        // 2. Binubuo natin ang full link kasama ang Order ID para unique bawat resibo
        $full_link = $base_url . "?id=" . $order_id;
        
        // 3. Ginagawa nating QR-friendly ang link (inaalis ang bawal na characters)
        $encoded_link = urlencode($full_link);
        
        // 4. Ito ang API link para sa QR code
        $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $encoded_link;
    ?>

    <img src="<?= $qr_api_url ?>" alt="Order QR Code" style="border: 1px solid #ccc; padding: 5px;">
    
    <p style="font-size: 0.7rem; margin-top: 5px; color: #555;">Scan to track your order</p>
</div>
        </div>
    </div>

    <script>
        // Trigger print dialog automatically on load
        window.addEventListener('load', () => {
            // Optional: window.print();
        });
    </script>
</body>
</html>