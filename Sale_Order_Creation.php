<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Get company currency
$stmt = $conn->prepare("SELECT currency FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$companyData = $stmt->fetch(PDO::FETCH_ASSOC);
$currency = isset($companyData['currency']) ? $companyData['currency'] : 'USD';

// Currency symbols
$currencySymbols = array(
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'INR' => '₹',
    'AED' => 'د.إ',
    'SAR' => '﷼',
    'CAD' => 'C$',
    'AUD' => 'A$',
    'JPY' => '¥',
    'CNY' => '¥',
    'PKR' => '₨',
    'BDT' => '৳',
    'MYR' => 'RM',
    'SGD' => 'S$',
    'ZAR' => 'R',
    'NGN' => '₦',
    'KES' => 'KSh',
    'GHS' => '₵',
    'EGP' => 'E£',
    'BRL' => 'R$',
    'MXN' => 'MX$'
);
$symbol = isset($currencySymbols[$currency]) ? $currencySymbols[$currency] : '$';

// Fetch Customers and Products
$customers = $conn->query("SELECT id, name FROM customers")->fetchAll(PDO::FETCH_ASSOC);
$products = $conn->query("SELECT id, name, price FROM products WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

$order_no = "SO-" . time();
require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-uppercase" style="letter-spacing: 1px;">Sales Order: <span class="text-primary">
                <?php echo $order_no; ?>
            </span></h2>
        <div>
            <a href="sales_orders.php" class="btn btn-outline-secondary me-2">Cancel</a>
            <button type="submit" form="orderForm" class="btn btn-primary px-4">Confirm Order</button>
        </div>
    </div>

    <form id="orderForm" action="process_order.php" method="POST">
        <input type="hidden" name="order_number" value="<?php echo $order_no; ?>">
        <input type="hidden" name="currency" value="<?php echo $currency; ?>">

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-light fw-bold">General Information</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Customer account</label>
                        <select name="customer_id" class="form-select select2" required>
                            <option value="">Select Customer...</option>
                            <?php foreach ($customers as $c): ?>

                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo $c['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Order Date</label>
                        <input type="date" name="order_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Order Status</label>
                        <input type="text" class="form-control bg-light" value="Draft" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Currency</label>
                        <input type="text" class="form-control bg-light" value="<?php echo $currency; ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold">Sales Order Lines</span>
                <button type="button" class="btn btn-sm btn-success" onclick="addRow()">+ Add Line</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="linesTable">
                        <thead class="table-light">
                            <tr class="small text-uppercase">
                                <th width="5%">#</th>
                                <th width="40%">Item Number / Name</th>
                                <th width="15%">Quantity</th>
                                <th width="15%">Unit Price</th>
                                <th width="15%">Net Amount</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="lineBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white text-end">
                <h4 class="fw-bold text-primary">Total: <span id="grandTotal">
                        <?php echo $symbol; ?>0.00
                    </span></h4>
                <input type="hidden" name="total_amount" id="total_amount_input" value="0">
            </div>
        </div>
    </form>
</div>

<script>
    let rowCount = 0;
    const products = <?php echo json_encode($products); ?>;
    const currencySymbol = '<?php echo $symbol; ?>';

    function addRow() {
        rowCount++;
        const tbody = document.getElementById('lineBody');
        const row = document.createElement('tr');
        row.id = 'row_' + rowCount;

        let options = '<option value="">Select Item...</option>';
        products.forEach(function (p) {
            options += '<option value="' + p.id + '" data-price="' + p.price + '">' + p.name + '</option>';
        });

        row.innerHTML = '<td>' + rowCount + '</td>' +
            '<td><select name="items[' + rowCount + '][product_id]" class="form-select border-0 item-select" onchange="updatePrice(this, ' + rowCount + ')" required>' + options + '</select></td>' +
            '<td><input type="number" name="items[' + rowCount + '][qty]" class="form-control border-0 text-center qty-input" value="1" min="1" onchange="calculateLine(' + rowCount + ')"></td>' +
            '<td><input type="number" name="items[' + rowCount + '][price]" class="form-control border-0 price-input" value="0.00" step="0.01" onchange="calculateLine(' + rowCount + ')"></td>' +
            '<td class="bg-light fw-bold text-end"><span id="lineTotal_' + rowCount + '">0.00</span></td>' +
            '<td><button type="button" class="btn btn-link text-danger" onclick="removeRow(' + rowCount + ')"><i class="fas fa-trash"></i></button></td>';

        tbody.appendChild(row);
    }

    function updatePrice(select, rowId) {
        var price = select.options[select.selectedIndex].dataset.price;
        var row = document.getElementById('row_' + rowId);
        row.querySelector('.price-input').value = price;
        calculateLine(rowId);
    }

    function calculateLine(rowId) {
        var row = document.getElementById('row_' + rowId);
        var qty = row.querySelector('.qty-input').value;
        var price = row.querySelector('.price-input').value;
        var total = (qty * price).toFixed(2);

        document.getElementById('lineTotal_' + rowId).innerText = total;
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        var total = 0;
        document.querySelectorAll('[id^="lineTotal_"]').forEach(function (span) {
            total += parseFloat(span.innerText);
        });
        document.getElementById('grandTotal').innerText = currencySymbol + total.toFixed(2);
        document.getElementById('total_amount_input').value = total;
    }

    function removeRow(rowId) {
        document.getElementById('row_' + rowId).remove();
        calculateGrandTotal();
    }

    window.onload = addRow;
</script>

<style>
    .form-control:focus,
    .form-select:focus {
        box-shadow: none;
        border-color: #0d6efd;
    }

    .table input,
    .table select {
        background: transparent;
    }

    .table th {
        font-size: 0.75rem;
        color: #6c757d;
    }
</style>

<?php require_once 'templates/footer.php'; ?>