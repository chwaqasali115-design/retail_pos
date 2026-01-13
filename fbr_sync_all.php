<?php
// fbr_sync_all.php
require_once 'config/config.php';
require_once 'core/Database.php'; // Avoid Auth for CLI/Cron compatibility
require_once 'core/FBRService.php';

// Auth check for browser
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
$conn = $db->getConnection();

$companyId = 0;

if (isset($_SESSION['company_id'])) {
    $companyId = $_SESSION['company_id'];
} else {
    // CLI Argument Support: php fbr_sync_all.php [company_id]
    if (isset($argv[1])) {
        $companyId = (int) $argv[1];
    }
}

if (!$companyId) {
    // Fallback: If only 1 company exists, use it
    $stmt = $conn->query("SELECT id FROM companies LIMIT 1");
    if ($row = $stmt->fetch()) {
        $companyId = $row['id'];
    } else {
        die("Error: No company found or Company ID not specified. Usage: php fbr_sync_all.php [company_id]");
    }
}

echo "Starting Batch Sync for Company ID: $companyId...<br>\n";

try {
    $fbr = new FBRService($conn, $companyId);

    if (!$fbr->isEnabled()) {
        die("FBR is disabled for this company.");
    }

    // Find Pending Sales
    // Using JOIN to ensure we get sales for the specific company
    $stmt = $conn->prepare("
        SELECT s.id, s.invoice_no 
        FROM sales s
        JOIN stores st ON s.store_id = st.id
        WHERE st.company_id = ? 
        AND (s.fbr_status = 'PENDING' OR s.fbr_status = 'FAILED' OR s.fbr_status IS NULL)
        LIMIT 50
    ");

    $stmt->execute([$companyId]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($sales) . " pending invoices.<br>\n";

    $successCount = 0;
    foreach ($sales as $sale) {
        echo "Syncing Invoice: " . $sale['invoice_no'] . "... ";
        $res = $fbr->syncSale($sale['id']);

        if ($res['status'] == 'SYNCED') {
            echo "<span style='color:green'>SUCCESS</span> (FBR Inv: " . $res['fbr_invoice'] . ")<br>\n";
            $successCount++;
        } else {
            echo "<span style='color:red'>FAILED</span><br>\n";
        }

        // Sleep slightly to avoid rate limits
        usleep(200000); // 0.2s
    }

    echo "<hr>Batch Sync Completed. Synced: $successCount / " . count($sales);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
