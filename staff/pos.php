<?php
/**
 * POS Kiosk System - Universal Responsive Terminal
 */
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

// 1. Get Categories
$categories_res = $conn->query("SELECT DISTINCT category FROM products WHERE total_stock > 0 ORDER BY category ASC");

// 2. Group Products Logic
$products_res = $conn->query("SELECT * FROM products WHERE total_stock > 0 ORDER BY category ASC, name ASC");
$grouped_products = [];
while($row = $products_res->fetch_assoc()) {
    $cleanName = trim(preg_replace('/\s*\([ML]\)\s*/i', '', $row['name']));
    if (!isset($grouped_products[$cleanName])) {
        $grouped_products[$cleanName] = [
            'id' => $row['id'],
            'name' => $cleanName,
            'category' => $row['category'],
            'total_stock' => $row['total_stock'],
            'price_m' => 0,
            'price_l' => 0,
            'has_variants' => false
        ];
    }
    if (stripos($row['name'], '(L)') !== false) {
        $grouped_products[$cleanName]['price_l'] = ($row['price_l'] > 0) ? $row['price_l'] : $row['price'];
        $grouped_products[$cleanName]['has_variants'] = true;
    } else {
        $grouped_products[$cleanName]['price_m'] = ($row['price_m'] > 0) ? $row['price_m'] : $row['price'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS Terminal Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0a0f1d; --card-bg: #161d2f; --accent-blue: #3b82f6;
            --accent-green: #10b981; --accent-red: #ef4444; --text-main: #f8fafc;
            --text-muted: #94a3b8; --border: rgba(255, 255, 255, 0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-dark); color: var(--text-main); height: 100vh; overflow: hidden; }
        
        .pos-container { display: grid; grid-template-columns: 1fr 400px; height: 100vh; width: 100vw; }

        /* MENU SECTION */
        .menu-section { padding: 20px; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; }
        .header-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .search-box { width: 100%; padding: 12px 18px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; color: white; outline: none; transition: 0.3s; }
        .search-box:focus { border-color: var(--accent-blue); box-shadow: 0 0 10px rgba(59, 130, 246, 0.3); }
        
        .categories { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 5px; }
        .filter-btn { padding: 10px 18px; background: var(--card-bg); border: 1px solid var(--border); color: var(--text-muted); border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: 0.2s; }
        .filter-btn.active { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }

        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .product-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 15px; display: flex; flex-direction: column; gap: 10px; transition: 0.2s; position: relative; }
        .product-card:hover { border-color: var(--accent-blue); }
        .cat-tag { font-size: 0.65rem; color: var(--accent-blue); font-weight: 800; text-transform: uppercase; }
        .product-name { font-weight: 700; font-size: 1rem; color: white; min-height: 2.4rem; line-height: 1.2; }
        
        /* QUICK ADD BUTTONS */
        .price-row { display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25); padding: 8px 12px; border-radius: 12px; margin-top: 2px; }
        .price-info b { color: var(--accent-green); font-size: 0.9rem; }
        .add-btn { background: var(--accent-blue); border: none; color: white; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 0.75rem; z-index: 10; position: relative; transition: 0.2s; }
        .add-btn:active { transform: scale(0.9); }
        .add-btn:hover { background: #2563eb; }

        /* =========================================
           RE-FIXED CART SECTION - BOTTOM SHEET STYLE
           ========================================= */

       /* =========================================
   CART SECTION - PERFECT RESPONSIVE
   ========================================= */

/* --- [1] SHARED STYLES --- */
.cart-list { flex: 1; overflow-y: auto; margin: 15px 0; }
.checkout-box { background: rgba(15, 23, 42, 0.5); padding: 18px; border-radius: 20px; border: 1px solid var(--border); }
.summary-line { display: flex; justify-content: space-between; margin-bottom: 10px; color: var(--text-muted); font-size: 0.9rem; }
.grand-total { font-size: 2.2rem; font-weight: 800; color: var(--accent-green); text-align: right; margin-bottom: 15px; }
.checkout-btn {
    width: 100%;
    padding: 16px;
    background: #10b981; /* Accent Green */
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 800;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 15px;
    text-transform: uppercase;
}
.checkout-btn:active {
    transform: scale(0.98);
}
.checkout-btn:hover { filter: brightness(1.1); }

/* --- [2] DESKTOP VIEW (Fixed Sidebar) --- */
@media (min-width: 992px) {
    .drag-handle, .cart-overlay { display: none !important; } /* Tago sa PC */
    
    .cart-section {
        width: 400px;
        height: 100vh;
        background: var(--card-bg);
        display: flex;
        flex-direction: column;
        padding: 25px;
        position: sticky;
        top: 0;
        border-left: 1px solid var(--border);
    }
    .cart-content-wrapper { display: flex; flex-direction: column; height: 100%; }
}

/* --- [3] MOBILE VIEW (Modern Slide-up Sheet) --- */
@media (max-width: 991px) {
    .desktop-only { display: none !important; }

    .cart-section {
        position: fixed;
        left: 0; right: 0; bottom: 0;
        width: 100%;
        height: 80vh; 
        background: #111827; 
        z-index: 3000;
        border-radius: 25px 25px 0 0;
        box-shadow: 0 -10px 40px rgba(0,0,0,0.6);
        /* EFFECT: Nakalubog, summary lang ang kita sa baba */
        transform: translateY(calc(80vh - 70px)); 
        transition: transform 0.5s cubic-bezier(0.32, 0.72, 0, 1);
        display: flex;
        flex-direction: column;
    }

    /* Pag hinila pataas */
    .cart-section.active { transform: translateY(0); }

    .drag-handle {
        height: 70px;
        min-height: 70px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0 20px;
        background: #1f2937;
        border-radius: 25px 25px 0 0;
        cursor: pointer;
    }

    .handle-bar { width: 40px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 10px; margin-bottom: 10px; }
    .mobile-summary { width: 100%; display: flex; justify-content: space-between; font-weight: 700; color: white; }

    .cart-content-wrapper { padding: 0 20px 20px 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; }
    
    .cart-overlay.active {
        display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 2999;
    }
    .pos-container {
        grid-template-columns: 1fr; /* Single column sa mobile */
    }

    /* ETO ANG FIX: Nagdagdag ng padding sa baba para hindi matakpan ang products */
    .menu-section, .products-section { 
        /* Dagdagan natin ang padding sa baba para may "tulak" pataas yung mga items */
        padding-bottom: 150px !important; 
        height: 100vh;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch; /* Para swabe ang scroll sa iPhone/Android */
    }

    /* Adjust product grid para sa mobile screens */
   .product-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr) !important; /* Laging dalawa pabalagbag */
        gap: 10px !important;
        padding: 10px;
    }
    .product-card {
        padding: 10px !important;
    }
}
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
    </style>
</head>
<body>

<div class="pos-container">
    <main class="menu-section">
        <div class="header-top">
            <div>
                <h1 style="font-size: 1.5rem; letter-spacing: -0.5px;">STAFF TERMINAL</h1>
                <p style="color: var(--text-muted); font-size: 0.75rem;" id="live-clock">Loading date & time...</p>
            </div>
            <a href="../auth/logout.php" style="color: var(--accent-red); text-decoration: none; font-weight: 700; font-size: 0.75rem; padding: 8px 16px; border: 1px solid var(--accent-red); border-radius: 10px;">LOGOUT</a>
        </div>

        <input type="text" id="pSearch" class="search-box" placeholder="Search menu items..." onkeyup="applyFilters()">

        <div class="categories">
            <button class="filter-btn active" onclick="filterCat('all', this)">All Items</button>
            <?php $categories_res->data_seek(0); while($cat = $categories_res->fetch_assoc()): ?>
                <button class="filter-btn" onclick="filterCat('<?= htmlspecialchars($cat['category']) ?>', this)"><?= htmlspecialchars($cat['category']) ?></button>
            <?php endwhile; ?>
        </div>

        <div class="product-grid" id="pGrid">
            <?php foreach($grouped_products as $p): 
                $encodedProduct = base64_encode(json_encode($p)); 
            ?>
                <div class="product-card" data-cat="<?= htmlspecialchars($p['category']) ?>" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
                    <div>
                        <span class="cat-tag"><?= htmlspecialchars($p['category']) ?></span>
                        <h3 class="product-name"><?= htmlspecialchars($p['name']) ?></h3>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <div class="price-row">
                            <div class="price-info">
                                <small style="display:block; color:var(--text-muted); font-size: 0.6rem;">MEDIUM</small>
                                <b>₱<?= number_format($p['price_m'], 2) ?></b>
                            </div>
                            <button type="button" class="add-btn" onclick="prepareAddToCart('<?= $encodedProduct ?>', 'M')">ADD +</button>
                        </div>

                        <?php if ($p['has_variants']): ?>
                        <div class="price-row">
                            <div class="price-info">
                                <small style="display:block; color:var(--text-muted); font-size: 0.6rem;">LARGE</small>
                                <b>₱<?= number_format($p['price_l'], 2) ?></b>
                            </div>
                            <button type="button" class="add-btn" onclick="prepareAddToCart('<?= $encodedProduct ?>', 'L')" style="background: #8b5cf6;">ADD +</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.65rem;">Stock: <?= $p['total_stock'] ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

   <div class="cart-overlay" id="cartOverlay" onclick="toggleMobileCart()"></div>

<aside class="cart-section" id="cartSidebar">
    <div class="drag-handle" onclick="toggleMobileCart()">
    <div class="handle-bar"></div>
    <div class="mobile-summary">
        <span>🛒 <b id="mobile-qty-count">0 Items</b></span>
        
        <span id="mobile-total-price" style="color: var(--accent-green);">₱0.00</span>
    </div>
</div>

    <div class="cart-content-wrapper">
        <h2 class="desktop-only" style="font-weight: 800; font-size: 1.2rem; margin-bottom:15px;">Current Order</h2>
        <input type="text" id="customer-name" class="search-box" placeholder="Customer Name" style="width:100%; margin-bottom:10px;">

        <div class="cart-list" id="cart-display">
            <div class="empty-msg" style="text-align:center; opacity:0.3; margin-top:50px;">
                <p style="font-size:3rem;">🛒</p>
                <p>Empty Cart</p>
            </div>
        </div>

        <div class="checkout-box">
            <div class="summary-line">
                <span>Qty: <b id="total-qty">0</b></span>
                <span>Total</span>
            </div>
            <div id="grand-total" class="grand-total">₱0.00</div>
<button id="checkout-btn" class="checkout-btn" onclick="processCheckout()">
    CHECKOUT
</button>        </div>
    </div>
</aside>
<button id="pay-button" style="padding: 10px 20px; background-color: #0047ba; color: white; border: none; border-radius: 5px; cursor: pointer;">
    Pay via GCash / Maya
</button>
    
</div>

<script>
let cart = [];

// 1. Live Clock
function updateClock() {
    const now = new Date();
    const options = { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    document.getElementById('live-clock').innerText = now.toLocaleString('en-PH', options).replace(/,/g, ' •');
}
setInterval(updateClock, 1000);
updateClock();

// 2. Add to Cart Helper (Decodes Base64)
function prepareAddToCart(encodedData, size) {
    try {
        const product = JSON.parse(atob(encodedData));
        addToCart(product, size);
    } catch (e) {
        console.error("Error decoding product:", e);
    }
}

function addToCart(product, size) {
    if (product.total_stock <= 0) {
        alert("Out of stock!");
        return;
    }
    
    const cartKey = product.name + size;
    let item = cart.find(i => (i.name + i.size) === cartKey);

    if (item) {
        if (item.qty < product.total_stock) {
            item.qty++;
        } else {
            alert("Insufficient stock!");
        }
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            size: size,
            price: (size === 'M') ? parseFloat(product.price_m) : parseFloat(product.price_l),
            qty: 1,
            total_stock: product.total_stock
        });
    }
    renderCart();
}

function renderCart() {
    const disp = document.getElementById('cart-display');
    if (cart.length === 0) {
        disp.innerHTML = '<div style="text-align: center; color: var(--text-muted); margin-top: 50px;"><p style="font-size: 2rem; opacity: 0.3;">🛒</p><p>Empty Cart</p></div>';
        document.getElementById('grand-total').innerText = '₱0.00';
        document.getElementById('total-qty').innerText = '0';
        return;
    }

    let total = 0, totalQty = 0;
    disp.innerHTML = cart.map((item, index) => {
        const rowTotal = item.price * item.qty;
        total += rowTotal;
        totalQty += item.qty;
        return `
        <div class="cart-row">
            <div style="display:flex; justify-content:space-between; align-items: flex-start;">
                <div>
                    <span style="font-size:0.6rem; background:var(--accent-blue); padding: 2px 6px; border-radius:4px; font-weight:800; color:white;">${item.size === 'M' ? 'MEDIUM' : 'LARGE'}</span>
                    <div style="font-weight:700; margin-top:4px; color:white;">${item.name}</div>
                    <div style="color:var(--accent-green); font-size:0.85rem; font-weight: 700;">₱${item.price.toFixed(2)}</div>
                </div>
                <button onclick="removeFromCart(${index})" style="background:none; border:none; color:var(--accent-red); font-size:1.2rem; cursor:pointer;">&times;</button>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                <div class="qty-ctrl">
                    <button onclick="updateQty(${index}, -1)">-</button>
                    <span style="font-weight:800; color:white;">${item.qty}</span>
                    <button onclick="updateQty(${index}, 1)">+</button>
                </div>
                <b style="font-size: 1rem; color:#fff;">₱${rowTotal.toFixed(2)}</b>
            </div>
        </div>`;
    }).join('');

    document.getElementById('grand-total').innerText = '₱' + total.toLocaleString(undefined, {minimumFractionDigits:2});
    document.getElementById('total-qty').innerText = totalQty;
}

function updateQty(index, delta) {
    let item = cart[index];
    if (item.qty + delta <= 0) removeFromCart(index);
    else if (item.qty + delta > item.total_stock) alert("Max stock reached!");
    else item.qty += delta;
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function filterCat(cat, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
}

function applyFilters() {
    const s = document.getElementById('pSearch').value.toLowerCase();
    const activeFilter = document.querySelector('.filter-btn.active').innerText;
    document.querySelectorAll('.product-card').forEach(card => {
        const matchCat = (activeFilter === 'All Items' || card.dataset.cat === activeFilter);
        const matchSearch = card.dataset.name.includes(s);
        card.style.display = (matchCat && matchSearch) ? "flex" : "none";
    });
}

async function processCheckout() {
    const customerInput = document.getElementById('customer-name');
    const customer = customerInput.value.trim();
    
    // 1. Validation: Dapat may laman ang cart (assume 'cart' is your global array)
    if (typeof cart === 'undefined' || cart.length === 0) {
        alert("Your cart is empty. Please add items first.");
        return;
    }

    // 2. Validation: Dapat may pangalan ng customer
    if (!customer) {
        alert("Please enter a customer name.");
        customerInput.focus(); // I-focus ang input para madaling makita sa mobile
        return;
    }

    const btn = document.getElementById('checkout-btn');
    const originalText = btn.innerText;

    // 3. UI Feedback: Disable button para iwas double click
    btn.disabled = true;
    btn.innerText = "Processing...";

    const fd = new FormData();
    fd.append('cart_data', JSON.stringify(cart));
    fd.append('customer_name', customer);

    try {
        // Siguraduhin na ang 'checkout.php' ay nasa tamang folder path
        const res = await fetch('checkout.php', { 
            method: 'POST', 
            body: fd 
        });

        // 4. Error Checking: I-check kung valid JSON ang binalik ng server
        const text = await res.text(); // Kunin muna ang raw text para i-debug kung may error
        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonErr) {
            console.error("Server Error Response:", text);
            throw new Error("Invalid server response.");
        }

        if (data.success) {
            // Pag success, punta agad sa receipt
            window.location.href = 'receipt.php?id=' + data.order_id;
        } else {
            alert(data.message || "Checkout failed.");
            btn.disabled = false;
            btn.innerText = originalText;
        }
    } catch (e) {
        console.error("Checkout Error:", e);
        alert("Connection Error: " + e.message);
        btn.disabled = false;
        btn.innerText = originalText;
    }
}
function syncMobileCart() {
    // 1. Kunin ang data mula sa main cart (yung gumagana na)
    const mainQty = document.getElementById('total-qty');
    const mainTotal = document.getElementById('grand-total');

    // 2. Kunin ang mga elements sa mobile handle (yung nabilugan mo)
    const mobileQtyDisplay = document.getElementById('mobile-qty-count');
    const mobileTotalDisplay = document.getElementById('mobile-total-price');

    // 3. I-sync ang data (Gayahin kung anong nasa main cart)
    if (mainQty && mobileQtyDisplay) {
        mobileQtyDisplay.innerText = mainQty.innerText + " Items";
    }
    
    if (mainTotal && mobileTotalDisplay) {
        mobileTotalDisplay.innerText = mainTotal.innerText;
    }
}

// Gagawa tayo ng "Observer" para bantayan ang bawat click mo sa "ADD+" button
document.addEventListener('click', function(e) {
    // Kapag nag-click ka ng kahit anong button (Add, Plus, Minus, Remove)
    if (e.target.classList.contains('add-btn') || 
        e.target.classList.contains('qty-btn') || 
        e.target.classList.contains('remove-item')) {
        
        // Mag-antay ng 100ms para matapos muna yung luma mong script, bago natin i-sync
        setTimeout(syncMobileCart, 100);
    }
});

// Tawagin din natin ito sa simula para siguradong hindi 0 ang simula
window.onload = syncMobileCart;

// --- TOGGLE FUNCTION ---
function toggleMobileCart() {
    const cart = document.getElementById('cartSidebar');
    const overlay = document.getElementById('cartOverlay');
    if (cart && overlay) {
        cart.classList.toggle('active');
        overlay.classList.toggle('active');
    }
}

</script>
</body>
</html>