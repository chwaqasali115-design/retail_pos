<?php
require_once 'config/config.php';
require_once 'core/Auth.php';

Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Fetch products for the dropdown
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.sku, p.barcode, p.sell_price, p.description, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.company_id = ? 
    ORDER BY p.name ASC
");
$stmt->execute([$_SESSION['company_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-barcode me-2"></i>Barcode Generator</h2>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Generate Barcode</h5>
                </div>
                <div class="card-body">
                    <form id="barcodeForm">
                        <div class="mb-3">
                            <label for="productSelect" class="form-label">Select Product</label>
                            <select class="form-select" id="productSelect" required>
                                <option value="">-- Select a Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['sku']); ?>"
                                        data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                        data-price="<?php echo isset($p['sell_price']) ? $p['sell_price'] : ''; ?>"
                                        data-cat="<?php echo htmlspecialchars($p['category_name'] ?? ''); ?>"
                                        data-desc="<?php echo htmlspecialchars($p['description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($p['name']); ?> (
                                        <?php echo htmlspecialchars($p['sku']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customSku" class="form-label">Or Enter Custom SKU/Code</label>
                            <input type="text" class="form-control" id="customSku" placeholder="e.g. 12345678">
                        </div>

                        <div class="mb-3">
                            <label for="barcodeType" class="form-label">Barcode Type</label>
                            <select class="form-select" id="barcodeType">
                                <option value="CODE128">CODE128 (Standard)</option>
                                <option value="EAN13">EAN-13</option>
                                <option value="UPC">UPC</option>
                                <option value="QR">QR Code</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tagSize" class="form-label">Tag Size (mm)</label>
                                <select class="form-select" id="tagSize">
                                    <option value="50x25">50mm x 25mm (Standard)</option>
                                    <option value="40x30">40mm x 30mm</option>
                                    <option value="38x25">38mm x 25mm (Small)</option>
                                    <option value="75x50">75mm x 50mm (Large)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="printQty" class="form-label">Quantity to Print</label>
                                <input type="number" class="form-control" id="printQty" value="1" min="1" max="100">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-magic me-2"></i>Generate
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm border-0 text-center" style="min-height: 400px;">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Preview</h5>
                    <button class="btn btn-sm btn-secondary" onclick="printBarcodes()" id="printBtn" disabled>
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center" id="previewArea">
                    <p class="text-muted">Select a product and click Generate to see preview.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JsBarcode Library -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<!-- QRCode Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
    document.getElementById('productSelect').addEventListener('change', function () {
        if (this.value) {
            document.getElementById('customSku').value = this.value;
        }
    });

    document.getElementById('barcodeForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const code = document.getElementById('customSku').value;
        const type = document.getElementById('barcodeType').value;
        const qty = parseInt(document.getElementById('printQty').value);
        const size = document.getElementById('tagSize').value; // e.g. "50x25"
        
        // Parse size
        const [w_mm, h_mm] = size.split('x').map(Number);
        
        // Convert mm to px roughly for screen preview (1mm approx 3.78px)
        const scale = 3.78;
        const w_px = w_mm * scale;
        const h_px = h_mm * scale;

        // Get Extra Info
        const select = document.getElementById('productSelect');
        const selectedOption = select.options[select.selectedIndex];
        
        let productName = '';
        let productPrice = '';
        let productCat = '';
        if(selectedOption.value && selectedOption.value === code) {
             productName = selectedOption.getAttribute('data-name') || '';
             const desc = selectedOption.getAttribute('data-desc');
            //  if(desc) productName += ' ' + desc; // Too long for small tags, maybe limit chars
             if(desc) productName += ' ' + desc.substring(0, 20) + (desc.length > 20 ? '...' : '');

             const p = selectedOption.getAttribute('data-price');
             if(p) productPrice = 'Rs ' + parseFloat(p).toFixed(2);
             
             productCat = selectedOption.getAttribute('data-cat') || '';
        }

        if (!code) {
            alert('Please select a product or enter a code');
            return;
        }

        const preview = document.getElementById('previewArea');
        preview.innerHTML = ''; // Clear previous

        // Shared wrapper style
        // We use flex col for content
        const wrapperStyle = `
            width: ${w_mm}mm; 
            height: ${h_mm}mm; 
            border: 1px dotted #ccc; 
            background: white; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center;
            overflow: hidden;
            padding: 2px;
            box-sizing: border-box;
            page-break-inside: avoid;
        `;
        
        const container = document.createElement('div');
        // Changed to vertical column for Zebra/Roll preview
        container.className = 'd-flex flex-column align-items-center gap-3 printable-area';

        // Adjust font sizes based on tag height
        const fontSizeHeader = h_mm < 30 ? '8px' : '10px';
        const fontSizeFooter = h_mm < 30 ? '7px' : '9px';

        for (let i = 0; i < qty; i++) {
            const wrapper = document.createElement('div');
            wrapper.className = 'label-wrapper'; // Add class for print targeting
            wrapper.style.cssText = wrapperStyle;
            
            // 1. Header: Name
            if(productName) {
                const title = document.createElement('div');
                title.style.fontSize = fontSizeHeader;
                title.style.fontWeight = 'bold';
                title.style.textAlign = 'center';
                title.style.width = '100%';
                title.style.whiteSpace = 'nowrap';
                title.style.overflow = 'hidden';
                title.style.textOverflow = 'ellipsis';
                title.innerText = productName;
                wrapper.appendChild(title);
            }

            // 2. Barcode / QR
            const codeContainer = document.createElement('div');
            codeContainer.style.flex = '1'; // Take remaining space
            codeContainer.style.display = 'flex';
            codeContainer.style.alignItems = 'center';
            codeContainer.style.justifyContent = 'center';
            codeContainer.style.width = '100%';
            codeContainer.style.overflow = 'hidden';
            
            if (type === 'QR') {
                const qrDiv = document.createElement('div');
                // Calculate QR size: use smaller of w or h minus padding
                const qrSize = Math.min(w_px, h_px) * 0.6; 
                new QRCode(qrDiv, {
                    text: code,
                    width: qrSize,
                    height: qrSize,
                    correctLevel : QRCode.CorrectLevel.L
                });
                codeContainer.appendChild(qrDiv);
            } else {
                const img = document.createElement('img');
                try {
                    // Dynamic JsBarcode settings
                    // Bar width: 1 for small tags, 2 for large
                    const barWidth = w_mm < 40 ? 1 : 1.5;
                    const barHeight = h_mm * 0.4 * 3.78; // 40% of height in px
                    
                    JsBarcode(img, code, {
                        format: type,
                        lineColor: "#000",
                        width: barWidth,
                        height: barHeight,
                        displayValue: true,
                        fontSize: 10,
                        margin: 0
                    });
                     // Check if img width exceeds container
                     // If so, we might need to scale it down via CSS
                     img.style.maxWidth = '100%';
                     img.style.height = 'auto';
                     
                    codeContainer.appendChild(img);
                } catch (err) {
                    codeContainer.innerHTML = '<span class="text-danger small">Err</span>';
                }
            }
            wrapper.appendChild(codeContainer);

            // 3. Footer: Price & Cat
            if(productPrice || productCat) {
                const info = document.createElement('div');
                info.style.width = '100%';
                info.style.fontSize = fontSizeFooter;
                info.style.display = 'flex';
                info.style.justifyContent = 'space-between';
                info.style.marginTop = '2px';
                info.innerHTML = `<strong>${productPrice}</strong> <span>${productCat}</span>`;
                wrapper.appendChild(info);
            }
            
            container.appendChild(wrapper);
        }
        preview.appendChild(container);

        document.getElementById('printBtn').disabled = false;
        // Store size for print
        window.currentTagSize = {w: w_mm, h: h_mm};
    });

    function printBarcodes() {
        const printContent = document.getElementById('previewArea').innerHTML;
        const size = window.currentTagSize || {w: 50, h: 25};
        
        const win = window.open('', '', 'height=600,width=800');
        win.document.write('<html><head><title>Print Barcodes</title>');
        win.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
        win.document.write('<style>');
        // Print Styles for Zebra
        win.document.write(`
            @media print { 
                @page { size: ${size.w}mm ${size.h}mm; margin: 0; } 
                body { margin: 0; }
                .label-wrapper { 
                    page-break-after: always; 
                    margin: 0 auto;
                    border: none !important; /* Remove border for actual print */
                }
                .label-wrapper:last-child { page-break-after: auto; }
            }
        `);
        win.document.write('</style>');
        win.document.write('</head><body >');
        // Changed to block/column for print structure
        win.document.write('<div class="d-flex flex-column align-items-center">');
        win.document.write(printContent);
        win.document.write('</div>');
        win.document.write('</body></html>');
        win.document.close();
        // Give time for images to render? JsBarcode is sync usually
        setTimeout(() => {
            win.print();
        }, 500);
    }
</script>

<?php require_once 'templates/footer.php'; ?>