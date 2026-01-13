<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();
$message = '';

// 1. Fetch products for dropdown
// We alias 'name' to 'product_name' to ensure compatibility with JS
$products = $conn->query("SELECT id, name AS product_name, price, stock_quantity FROM products WHERE is_active = 1 AND stock_quantity > 0")->fetchAll(PDO::FETCH_ASSOC);

// 2. Handle Sale Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sale'])) {
    try {
        $conn->beginTransaction();

        $customer_name = !empty($_POST['customer_name']) ? $_POST['customer_name'] : 'Walk-in Customer';
        $total_amount = $_POST['total_amount'];
        $user_id = $_SESSION['user_id'];
        $store_id = $_SESSION['store_id'] ?? null;
        
        // Decode the cart items sent from JavaScript
        $cart_items = json_decode($_POST['cart_data'], true);

        if (empty($cart_items)) {
            throw new Exception("Cart is empty.");
        }

        // A. Insert into sales table
        $stmt = $conn->prepare("INSERT INTO sales (customer_name, total_amount, user_id, store_id) VALUES (:cust, :total, :uid, :sid)");
        $stmt->execute([
            ':cust'  => $customer_name,
            ':total' => $total_amount,
            ':uid'   => $user_id,
            ':sid'   => $store_id
        ]);
        $sale_id = $conn->lastInsertId();

        // B. Insert items and update stock
        $itemStmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (:sid, :pid, :qty, :price, :sub)");
        $stockStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :pid");

        foreach ($cart_items as $item) {
            $subtotal = $item['price'] * $item['qty'];
            
            // Record the item
            $itemStmt->execute([
                ':sid'   => $sale_id,
                ':pid'   => $item['id'],
                ':qty'   => $item['qty'],
                ':price' => $item['price'],
                ':sub'   => $subtotal
            ]);

            // Reduce stock
            $stockStmt->execute([
                ':qty' => $item['qty'],
                ':pid' => $item['id']
            ]);
        }

        $conn->commit();
        $message = "Sale #$sale_id completed successfully!";
        
        // Refresh products to show updated stock
        $products = $conn->query("SELECT id, name AS product_name, price, stock_quantity FROM products WHERE is_active = 1 AND stock_quantity > 0")->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $conn->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="fw-bold"><i class="fas fa-cash-register me-2"></i>POS Terminal</h2>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- LEFT: Product Selection -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Item Selection</h5>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small text-muted">Product</label>
                            <select class="form-select form-select-lg" id="product_select">
                                <option value="">-- Search / Select Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" 
                                            data-price="<?php echo $p['price']; ?>" 
                                            data-name="<?php echo htmlspecialchars($p['product_name']); ?>"
                                            data-stock="<?php echo $p['stock_quantity']; ?>">
                                        <?php echo htmlspecialchars($p['product_name']); ?> (Qty: <?php echo $p['stock_quantity']; ?>) - $<?php echo number_format($p['price'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Qty</label>
                            <input type="number" id="product_qty" class="form-control form-control-lg" value="1" min="1">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-primary btn-lg w-100" id="add_item_btn">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>

                    <table class="table table-hover mt-4 align-middle" id="sales_table">
                        <thead class="table-dark">
                            <tr>
                                <th>Item Name</th>
                                <th width="15%">Price</th>
                                <th width="15%">Qty</th>
                                <th width="15%">Total</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Added via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT: Summary & Checkout -->
        <div class="col-md-4">
            <form method="POST" id="checkout_form" class="card shadow-sm border-0 bg-light">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Order Summary</h5>
                    <input type="hidden" name="create_sale" value="1">
                    
                    <!-- Hidden field to store JSON cart data -->
                    <input type="hidden" name="cart_data" id="cart_data_input">
                    <input type="hidden" name="total_amount" id="total_amount_input" value="0">

                    <div class="mb-3">
                        <label class="form-label small text-muted">Customer Name</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="Walk-in Customer">
                    </div>

                    <div class="bg-white p-3 rounded mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold" id="subtotal_display">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-success h4 mb-0">
                            <span class="fw-bold">Total:</span>
                            <span class="fw-bold" id="grand_total_display">$0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100 py-3 fw-bold" id="checkout_btn" disabled>
                        <i class="fas fa-check-circle me-2"></i> COMPLETE SALE
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let cart = [];

document.getElementById('add_item_btn').addEventListener('click', function() {
    const select = document.getElementById('product_select');
    const selectedOption = select.options[select.selectedIndex];
    
    if (!selectedOption.value) return;

    const productId = selectedOption.value;
    const name = selectedOption.dataset.name;
    const price = parseFloat(selectedOption.dataset.price);
    const qty = parseInt(document.getElementById('product_qty').value);
    const stock = parseInt(selectedOption.dataset.stock);

    if (qty > stock) {
        alert("Insufficient stock! Only " + stock + " available.");
        return;
    }

    // Check if item exists in cart
    const existingIndex = cart.findIndex(item => item.id === productId);
    if (existingIndex > -1) {
        cart[existingIndex].qty += qty;
    } else {
        cart.push({ id: productId, name, price, qty });
    }

    updateUI();
    select.value = "";
    document.getElementById('product_qty').value = 1;
});

function updateUI() {
    const tbody = document.querySelector('#sales_table tbody');
    tbody.innerHTML = "";
    let total = 0;

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        tbody.innerHTML += `
            <tr>
                <td class="fw-bold">${item.name}</td>
                <td>$${item.price.toFixed(2)}</td>
                <td>${item.qty}</td>
                <td class="fw-bold">$${itemTotal.toFixed(2)}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    document.getElementById('subtotal_display').innerText = '$' + total.toFixed(2);
    document.getElementById('grand_total_display').innerText = '$' + total.toFixed(2);
    
    // Set hidden fields for form submission
    document.getElementById('total_amount_input').value = total;
    document.getElementById('cart_data_input').value = JSON.stringify(cart);
    
    document.getElementById('checkout_btn').disabled = cart.length === 0;
}

function removeItem(index) {
    cart.splice(index, 1);
    updateUI();
}

// Ensure the form is processed correctly
document.getElementById('checkout_form').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        e.preventDefault();
        alert("Please add items to the cart first.");
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>