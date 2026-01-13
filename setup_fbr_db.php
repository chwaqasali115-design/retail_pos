<?php
require_once 'config/config.php';
require_once 'core/Database.php';

echo "Setting up FBR Integration Database...\n";

$db = new Database();
$conn = $db->getConnection();

try {
    // 1. fbr_settings
    $sqlSettings = "CREATE TABLE IF NOT EXISTS fbr_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        pos_id VARCHAR(50),
        auth_token VARCHAR(255),
        base_url VARCHAR(255) DEFAULT 'https://esp.fbr.gov.pk:8243/FBR/v1/api/Live/PostData',
        is_active BOOLEAN DEFAULT 0,
        environment ENUM('TEST', 'PRODUCTION') DEFAULT 'PRODUCTION',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_company (company_id)
    )";
    $conn->exec($sqlSettings);
    echo "- Table 'fbr_settings' created/checked.\n";

    // 2. fbr_logs
    $sqlLogs = "CREATE TABLE IF NOT EXISTS fbr_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        invoice_no VARCHAR(50),
        request_payload TEXT,
        response_payload TEXT,
        http_status INT,
        status ENUM('PENDING', 'SYNCED', 'FAILED') DEFAULT 'PENDING',
        error_message TEXT,
        synced_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sale (sale_id),
        INDEX idx_status (status)
    )";
    $conn->exec($sqlLogs);
    echo "- Table 'fbr_logs' created/checked.\n";

    // 3. Alter sales table
    // Check if columns exist first to avoid errors
    $columns = $conn->query("DESCRIBE sales")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('fbr_invoice_no', $columns)) {
        $conn->exec("ALTER TABLE sales ADD COLUMN fbr_invoice_no VARCHAR(50) AFTER invoice_no");
        echo "- Column 'fbr_invoice_no' added to 'sales'.\n";
    }

    if (!in_array('fbr_qr_code', $columns)) {
        $conn->exec("ALTER TABLE sales ADD COLUMN fbr_qr_code TEXT AFTER fbr_invoice_no"); // FBR returns a long string, sometimes used for QR
        echo "- Column 'fbr_qr_code' added to 'sales'.\n";
    }

    if (!in_array('fbr_status', $columns)) {
        $conn->exec("ALTER TABLE sales ADD COLUMN fbr_status ENUM('PENDING', 'SYNCED', 'FAILED') DEFAULT 'PENDING' AFTER fbr_qr_code");
        echo "- Column 'fbr_status' added to 'sales'.\n";
    }

    echo "Database setup completed successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
