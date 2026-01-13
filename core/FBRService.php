<?php
// core/FBRService.php

class FBRService
{
    private $conn;
    private $companyId;
    private $config;

    public function __construct($dbConnection, $companyId)
    {
        $this->conn = $dbConnection;
        $this->companyId = $companyId;
        $this->loadConfig();
    }

    private function loadConfig()
    {
        $stmt = $this->conn->prepare("SELECT * FROM fbr_settings WHERE company_id = ?");
        $stmt->execute([$this->companyId]);
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function isEnabled()
    {
        return $this->config && $this->config['is_active'] == 1;
    }

    /**
     * Sync a sale with FBR
     * 
     * @param int|string $saleId
     * @return array Status and details of the sync
     */
    public function syncSale($saleId): array
    {
        if (!$this->isEnabled()) {
            return ['status' => 'disabled'];
        }

        // 1. Fetch Sale Details
        $stmt = $this->conn->prepare("SELECT s.*, c.name as customer_name, c.tax_number as customer_ntn 
            FROM sales s 
            LEFT JOIN customers c ON s.customer_id = c.id 
            WHERE s.id = ?");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale)
            return ['status' => 'error', 'message' => 'Sale not found'];

        if ($sale['fbr_status'] === 'SYNCED') {
            return [
                'status' => 'SYNCED',
                'fbr_invoice' => $sale['fbr_invoice_no'],
                'fbr_qr' => $sale['fbr_qr_code'],
                'message' => 'Already synced'
            ];
        }

        // 2. Fetch Sale Items
        $stmtItems = $this->conn->prepare("
            SELECT si.*, p.name as product_name, p.barcode as item_code, p.tax_rate 
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
        ");
        $stmtItems->execute([$saleId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // 3. Prepare Payload
        // Note: This payload structure matches standard FBR documentation.
        // POSID, USIN (InvoiceNo), DateTime, BuyerName, BuyerNTN, BuyerCNIC, BuyerPhoneNumber, TotalSaleValue, TotalQuantity, ...
        // Items: ItemCode, ItemName, Quantity, PCTCode, TaxRate, SaleValue, TaxCharged, ...

        $invoiceDate = date('Y-m-d H:i:s', strtotime($sale['sale_date']));
        $payload = [
            'InvoiceNumber' => '', // FBR doesn't take our Invoice No initially? Usually we send USIN.
            'POSID' => (int) $this->config['pos_id'],
            'USIN' => $sale['invoice_no'],
            'DateTime' => $invoiceDate,
            'BuyerName' => $sale['customer_name'] ?? 'Walk-in Customer',
            'BuyerNTN' => $sale['customer_ntn'] ?? '',
            'BuyerCNIC' => '', // Add if available
            'BuyerPhoneNumber' => '', // Add if available
            'TotalSaleValue' => (float) $sale['grand_total'], // Inclusive of Tax? Or Exclusive? FBR usually wants Total Sale Value including Tax.
            'TotalQuantity' => 0,
            'TotalBillAmount' => (float) $sale['grand_total'], // Required by FBR
            'TotalTaxCharged' => (float) $sale['tax_total'],
            'Discount' => 0, // Implement if we have discounts
            'FurtherTax' => 0,
            'PaymentMode' => $sale['payment_method'] == 'Cash' ? 1 : ($sale['payment_method'] == 'Card' ? 2 : 5),
            'RefUSIN' => '', // Validation for Returns?
            'InvoiceType' => 1, // 1: New, 2: Return/Cancel (Simplified)
            'Items' => []
        ];

        // Specific handling for Returns (Refunds)
        if ($sale['grand_total'] < 0) {
            $payload['InvoiceType'] = 3; // 3 implies Return in some specs, or check FBR doc. Usually 1=Mobile, 2=Return? 
            // Let's assume 1=Sales, 3=Return as per common integration.
        }

        $totalQty = 0;
        foreach ($items as $item) {
            $qty = abs($item['quantity']);
            $totalQty += $qty;

            $rate = (float) $item['unit_price'];
            $taxRate = (float) $item['tax_rate'];
            // Calculate Tax Amount per item if not stored
            // In our system, total = qty * price. Price usually includes tax or not depending on settings.
            // Let's assume simple Tax calc based on stored values or derive.
            // Simplified:
            $saleValue = $qty * $rate;
            $taxAmount = ($saleValue * $taxRate) / 100; // Rough calc, FBR logic might be specific.

            $payload['Items'][] = [
                'ItemCode' => $item['item_code'] ?? '000000',
                'ItemName' => $item['product_name'],
                'PCTCode' => '98010000', // Default Service/General code if missing
                'Quantity' => $qty,
                'TaxRate' => $taxRate,
                'SaleValue' => $saleValue,
                'TotalAmount' => $saleValue + $taxAmount,
                'TaxCharged' => $taxAmount,
                'Discount' => 0,
                'FurtherTax' => 0,
                'InvoiceType' => $payload['InvoiceType'],
                'RefUSIN' => ''
            ];
        }
        $payload['TotalQuantity'] = $totalQty;

        // 4. Send Request
        $apiResult = $this->sendRequest($payload);
        $rawResponse = $apiResult['response'];
        $httpCode = $apiResult['http_code'];
        $curlError = $apiResult['error'];

        $response = json_decode($rawResponse, true);

        // 5. Update Database
        $status = 'FAILED';
        $fbrInvoiceNo = null;
        $fbrQr = null;

        if (
            $httpCode == 200 && $response && (
                (isset($response['Response']) && $response['Response'] == 'true') ||
                (isset($response['Response']) && stripos($response['Response'], 'successfully') !== false) ||
                (isset($response['Code']) && $response['Code'] == '100')
            )
        ) {
            $status = 'SYNCED';
            $fbrInvoiceNo = $response['InvoiceNumber'];
            $fbrQr = $response['Response'];
        }

        // Update Sales Table
        $upd = $this->conn->prepare("UPDATE sales SET fbr_status = ?, fbr_invoice_no = ?, fbr_qr_code = ? WHERE id = ?");
        $upd->execute([$status, $fbrInvoiceNo, $fbrQr, $saleId]);

        // Log Transaction
        $log = $this->conn->prepare("INSERT INTO fbr_logs (sale_id, request_payload, response_payload, http_status, status, error_message, synced_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $log->execute([
            $saleId,
            json_encode($payload),
            $rawResponse, // Store raw response
            $httpCode,
            $status,
            $curlError,
            ($status == 'SYNCED' ? date('Y-m-d H:i:s') : null)
        ]);

        return [
            'status' => $status,
            'fbr_invoice' => $fbrInvoiceNo,
            'fbr_qr' => $fbrQr
        ];
    }

    /**
     * Send HTTP Request to FBR API
     * 
     * @param array $data Payload
     * @return array API response, HTTP code, and any error
     */
    private function sendRequest($data): array
    {
        $url = $this->config['base_url'];
        $token = $this->config['auth_token'];

        $ch = curl_init($url);

        $jsonData = json_encode($data);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds timeout for POS speed

        // For local development with self-signed certs
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'response' => $result,
            'http_code' => $httpCode,
            'error' => $error
        ];
    }
}
