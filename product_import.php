<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
// session_start() handled in Session.php included via Auth.php
Session::checkLogin();

$message = '';
$error = '';

if (isset($_POST['import'])) {
    if ($_FILES['file']['name']) {
        $filename = explode(".", $_FILES['file']['name']);
        if ($filename[1] == 'csv') {
            $handle = fopen($_FILES['file']['tmp_name'], "r");
            $count = 0;

            $db = new Database();
            $conn = $db->getConnection();

            while ($data = fgetcsv($handle)) {
                // Header row fix: if first row is header, skip it? 
                // Let's assume user provides header, scan for it? Or just row 1 is data?
                // Usually Row 1 is header.
                if ($count === 0) {
                    if (strtolower($data[0]) === 'name') { // Simple check
                        $count++;
                        continue;
                    }
                }

                // Expected: Name, SKU, Barcode, Cost, Sell, Stock, TaxRate
                $name = $data[0] ?? '';
                $sku = $data[1] ?? '';
                $barcode = $data[2] ?? '';
                $cost = floatval($data[3] ?? 0);
                $sell = floatval($data[4] ?? 0);
                $stock = floatval($data[5] ?? 0);
                $tax = floatval($data[6] ?? 0);

                if ($name && $sku) {
                    // Check if SKU exists
                    $check = $conn->prepare("SELECT id FROM products WHERE sku = ? AND company_id = ?");
                    $check->execute([$sku, $_SESSION['company_id']]);
                    if (!$check->fetch()) {
                        $stmt = $conn->prepare("INSERT INTO products (company_id, name, sku, barcode, cost_price, sell_price, stock_quantity, tax_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$_SESSION['company_id'], $name, $sku, $barcode, $cost, $sell, $stock, $tax]);
                        $count++;
                    }
                }
            }
            fclose($handle);
            $message = "Import successful! Processed products.";
        } else {
            $error = "Please upload a CSV file.";
        }
    }
}

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-file-import me-2"></i>Import Products</h2>
        <a href="products.php" class="btn btn-secondary">Back to Products</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <p>Upload a CSV file with the following columns (in order):</p>
            <ol>
                <li>Name</li>
                <li>SKU (Must be unique)</li>
                <li>Barcode</li>
                <li>Cost Price</li>
                <li>Sell Price</li>
                <li>Stock Quantity</li>
                <li>Tax Rate (%)</li>
            </ol>
            <div class="alert alert-info">Row 1 is assumed to be a header row and will be skipped if it starts with
                'Name'.</div>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Select CSV File</label>
                    <input type="file" name="file" class="form-control" accept=".csv" required>
                </div>
                <button type="submit" name="import" class="btn btn-primary">
                    <i class="fas fa-upload me-2"></i>Upload & Import
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>