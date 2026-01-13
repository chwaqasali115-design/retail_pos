<?php
// pos_api.php
require_once 'config/config.php';
require_once 'core/Auth.php';
require_once 'core/AccountingHelper.php';
require_once 'core/FBRService.php';

// session_start(); // Already started in Auth.php -> Session.php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Search Customers
if ($action === 'search_customers') {
    $q = $_GET['q'] ?? '';
    $stmt = $conn->prepare("SELECT id, name, phone, loyalty_points FROM customers WHERE (name LIKE :q OR phone LIKE :q) AND company_id = :cid LIMIT 15");
    $stmt->execute([':q' => "%$q%", ':cid' => $_SESSION['company_id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
    exit;
}

if ($action === 'search') {
    try {
        $q = $_GET['q'] ?? '';
        $cid = $_SESSION['company_id'] ?? 1;
        // Optimized search to include stock qty
        $stmt = $conn->prepare("SELECT id, name, price, cost_price, tax_id, tax_rate, sku, barcode, is_tax_inclusive, stock_quantity FROM products WHERE (name LIKE :q OR sku LIKE :q OR barcode LIKE :q) AND company_id = :cid LIMIT 20");
        $stmt->execute([':q' => "%$q%", ':cid' => $cid]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_terminals') {
    $store_id = $_GET['store_id'] ?? 0;

    if (!$store_id) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, name FROM terminals WHERE store_id = ? AND is_active = 1");
    $stmt->execute([$store_id]);
    $terminals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($terminals);
    exit;
}

if ($action === 'get_sale') {
    $invoice = $_GET['invoice'] ?? '';
    if (!$invoice) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice number required']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM sales WHERE invoice_no = ?");
    $stmt->execute([$invoice]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Get Items
    $stmt = $conn->prepare("
        SELECT si.*, p.name, p.sku, p.tax_rate, p.is_tax_inclusive, COALESCE(p.stock_quantity, 0) as stock_quantity 
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id 
        WHERE si.sale_id = ?
    ");
    $stmt->execute([$sale['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sale' => $sale, 'items' => $items]);
    exit;
}

if ($action === 'submit_sale') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['items'])) {
        echo json_encode(['error' => 'Cart is empty']);
        exit;
    }

    try {
        $conn->beginTransaction();
        $acc = new AccountingHelper($conn);

        // 1. Create Sale Header
        $invoiceNo = 'INV-' . strtoupper(uniqid());
        $customerId = !empty($input['customer_id']) ? $input['customer_id'] : null;
        $stmt = $conn->prepare("INSERT INTO sales (store_id, terminal_id, user_id, customer_id, invoice_no, sale_date, subtotal, tax_total, grand_total, payment_method, notes) VALUES (:sid, :tid, :uid, :cust_id, :inv, NOW(), :sub, :tax, :total, :method, :notes)");
        $stmt->execute([
            ':sid' => $_SESSION['store_id'] ?? 1,
            ':tid' => $_SESSION['terminal_id'] ?? null,
            ':uid' => $_SESSION['user_id'],
            ':cust_id' => $customerId,
            ':inv' => $invoiceNo,
            ':sub' => $input['subtotal'],
            ':tax' => $input['tax_total'] ?? 0,
            ':total' => $input['grand_total'],
            ':method' => $input['payment_method'],
            ':notes' => $input['notes'] ?? ''
        ]);
        $saleId = $conn->lastInsertId();

        $totalCost = 0;

        // 2. Process Items
        foreach ($input['items'] as $item) {
            // Get latest cost from DB to be safe, or use what was passed (assuming secured)
            // Using passed 'cost_price' for speed, ideally re-fetch
            // Note: In Search I added cost_price to select
            $cost = $item['cost_price'] ?? 0; // Ensure frontend sends this or we re-fetch

            $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total) VALUES (:sid, :pid, :qty, :price, :tot)");
            $stmt->execute([
                ':sid' => $saleId,
                ':pid' => $item['id'],
                ':qty' => $item['qty'],
                ':price' => $item['price'],
                ':tot' => $item['qty'] * $item['price']
            ]);

            // Deduct Stock from inventory_stock table
            $check = $conn->prepare("SELECT id, quantity FROM inventory_stock WHERE product_id = ? AND warehouse_id = 1");
            $check->execute([$item['id']]);
            if ($row = $check->fetch()) {
                $newQty = $row['quantity'] - $item['qty'];
                $upd = $conn->prepare("UPDATE inventory_stock SET quantity = ? WHERE id = ?");
                $upd->execute([$newQty, $row['id']]);
            } else {
                $ins = $conn->prepare("INSERT INTO inventory_stock (product_id, warehouse_id, quantity) VALUES (?, 1, ?)");
                $ins->execute([$item['id'], -1 * $item['qty']]);
            }

            // ALSO update products.stock_quantity (main stock field shown in inventory page)
            $updateProductStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $updateProductStock->execute([$item['qty'], $item['id']]);

            // Log Transaction (Stock Card)
            $log = $conn->prepare("INSERT INTO inventory_transactions (warehouse_id, product_id, type, quantity, reference_id) VALUES (1, ?, 'Sale', ?, ?)");
            $log->execute([$item['id'], -1 * $item['qty'], $saleId]);

            $totalCost += ($cost * $item['qty']);
        }

        // 3. Payment Record
        if (isset($input['payments']) && is_array($input['payments'])) {
            $payStmt = $conn->prepare("INSERT INTO sale_payments (sale_id, method, amount) VALUES (?, ?, ?)");
            foreach ($input['payments'] as $p) {
                $payStmt->execute([$saleId, $p['method'], $p['amount']]);
            }
        } else {
            // Fallback
            $pay = $conn->prepare("INSERT INTO sale_payments (sale_id, method, amount) VALUES (?, ?, ?)");
            $pay->execute([$saleId, $input['payment_method'], $input['grand_total']]);
        }

        // 4. Accounting (GL Posting)
        // Entity: 1001 (Cash), 4001 (Sales), 5001 (COGS), 1002 (Inventory)

        // Entry 1: Revenue / Cash
        $cashAccountId = $acc->getAccountId(1001);
        $salesAccountId = $acc->getAccountId(4001);

        $glEntries = [];

        // Cash Entry
        if ($input['grand_total'] >= 0) {
            $glEntries[] = ['account_id' => $cashAccountId, 'debit' => $input['grand_total'], 'credit' => 0];
        } else {
            // Return: Credit Cash
            $glEntries[] = ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => abs($input['grand_total'])];
        }

        // Sales Entry
        // FIX: Calculate Sales Revenue as (Grand Total - Tax Total) to ensure GL balances.
        // Debit (Cash) = Credit (Sales) + Credit (Tax)
        // Therefore: Sales = Cash - Tax
        $salesAmount = $input['grand_total'] - ($input['tax_total'] ?? 0);

        if ($salesAmount >= 0) {
            $glEntries[] = ['account_id' => $salesAccountId, 'debit' => 0, 'credit' => $salesAmount];
        } else {
            // Return: Debit Sales (Sales Return)
            $glEntries[] = ['account_id' => $salesAccountId, 'debit' => abs($salesAmount), 'credit' => 0];
        }

        // Tax Entry
        if (($input['tax_total'] ?? 0) != 0) {
            $taxAccountId = $acc->getAccountId(2002);
            if ($input['tax_total'] > 0) {
                $glEntries[] = ['account_id' => $taxAccountId, 'debit' => 0, 'credit' => $input['tax_total']];
            } else {
                // Return: Debit Tax Payable (Reduce Liability)
                $glEntries[] = ['account_id' => $taxAccountId, 'debit' => abs($input['tax_total']), 'credit' => 0];
            }
        }

        $acc->createJournalEntry(
            $_SESSION['company_id'],
            date('Y-m-d'),
            $invoiceNo,
            "Sale/Return Invoice $invoiceNo",
            $glEntries
        );

        // Entry 2: COGS (Inventory reduction)
        if ($totalCost != 0) {
            $cogsAccountId = $acc->getAccountId(5001);
            $inventoryAccountId = $acc->getAccountId(1002);

            $cogsEntries = [];
            if ($totalCost > 0) {
                // Sale: Debit COGS, Credit Inventory
                $cogsEntries[] = ['account_id' => $cogsAccountId, 'debit' => $totalCost, 'credit' => 0];
                $cogsEntries[] = ['account_id' => $inventoryAccountId, 'debit' => 0, 'credit' => $totalCost];
            } else {
                // Return: Credit COGS (Reverse Expense), Debit Inventory (Restock)
                $absCost = abs($totalCost);
                $cogsEntries[] = ['account_id' => $cogsAccountId, 'debit' => 0, 'credit' => $absCost];
                $cogsEntries[] = ['account_id' => $inventoryAccountId, 'debit' => $absCost, 'credit' => 0];
            }

            $acc->createJournalEntry(
                $_SESSION['company_id'],
                date('Y-m-d'),
                $invoiceNo,
                "COGS for $invoiceNo",
                $cogsEntries
            );
        }

        $conn->commit();

        // 5. FBR Integration Code
        $fbrResponse = [];
        try {
            $fbrService = new FBRService($conn, $_SESSION['company_id']);
            if ($fbrService->isEnabled()) {
                $fbrResponse = $fbrService->syncSale($saleId);
            }
        } catch (Exception $e) {
            // Log silent error, don't stop POS flow
            // error_log("FBR Error: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'invoice_no' => $invoiceNo,
            'sale_id' => $saleId,
            'fbr_invoice_no' => $fbrResponse['fbr_invoice'] ?? null,
            'fbr_qr_code' => $fbrResponse['fbr_qr'] ?? null
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
