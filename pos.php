<?php
require_once 'config/config.php';
require_once 'core/Session.php';
require_once 'core/Database.php';
Session::checkLogin();

// Fetch Company Currency
$db = new Database();
$conn = $db->getConnection();
$companyId = $_SESSION['company_id'] ?? 1;
$stmt = $conn->prepare("SELECT currency FROM companies WHERE id = ?");
$stmt->execute([$companyId]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);
$currencyCode = $res['currency'] ?? 'USD';

// Currency Logic
$currencySymbols = [
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
    'PKR' => 'Rs',
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
];
$currencySymbol = $currencySymbols[$currencyCode] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>POS Terminal - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        const CURRENCY_SYMBOL = "<?php echo $currencySymbol; ?>";
    </script>
    <style>
        :root {
            /* Premium Palette */
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --secondary-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: 1px solid rgba(255, 255, 255, 0.6);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --accent-danger: #ef4444;
            --accent-warning: #f59e0b;
        }

        body {
            height: 100vh;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background-color: #f1f5f9;
            /* Clean light background */
            background-image:
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.05) 0, transparent 50%),
                radial-gradient(at 100% 0%, rgba(16, 185, 129, 0.05) 0, transparent 50%);
        }

        /* Glassmorphism Refined for Light Mode */
        .glass-panel {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            border-radius: 20px;
        }

        /* Top Bar */
        .top-bar {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1050;
            margin: 15px 20px 0 20px;
        }

        /* Search Input - Floating Spotlight */
        .search-container {
            flex: 1;
            max-width: 600px;
            margin-right: 30px;
        }

        .search-input-group {
            display: flex;
            width: 100%;
            background: white;
            border-radius: 50px;
            padding: 5px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            align-items: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .search-input-group:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.25);
        }

        .search-input-group input {
            border: none;
            padding: 12px;
            flex-grow: 1;
            outline: none;
            background: transparent;
            font-size: 1rem;
            color: var(--text-dark);
        }

        .search-input-group i {
            color: #6366f1;
            font-size: 1.1rem;
        }

        /* Header Buttons */
        .btn-custom {
            border: none;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.85);
            color: var(--text-dark);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-custom.active-mode {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .btn-custom i {
            font-size: 1rem;
        }

        /* Specialized Header Button Colors */
        .btn-return-manual.active {
            background: var(--accent-warning);
            color: white;
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.5);
        }

        /* Main Container */
        .main-container {
            flex: 1;
            display: flex;
            padding: 20px;
            gap: 25px;
            overflow: hidden;
        }

        /* Left Panel (Cart) */
        .left-panel {
            flex: 2.2;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .table-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 0.5fr;
            padding: 15px 25px;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .items-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        /* Custom Scrollbar */
        .items-list::-webkit-scrollbar {
            width: 6px;
        }

        .items-list::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .cart-row {
            padding: 18px 25px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 0.5fr;
            align-items: center;
            margin-bottom: 8px;
            background: white;
            border-radius: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .cart-row:hover {
            background: rgba(255, 255, 255, 0.7);
            transform: scale(1.005);
        }

        .cart-row.selected {
            background: white;
            border: 1px solid #c7d2fe;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }

        .item-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
            margin-bottom: 2px;
        }

        .item-price {
            font-weight: 500;
            color: var(--text-muted);
        }

        .qty-input {
            width: 50px;
            text-align: center;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            padding: 5px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .discount-badge {
            background: #ecfdf5;
            color: #059669;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .discount-badge:hover {
            background: #d1fae5;
        }

        .item-total {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 1.05rem;
        }

        .delete-btn {
            color: #cbd5e1;
            transition: 0.2s;
            display: flex;
            justify-content: center;
        }

        .delete-btn:hover {
            color: var(--accent-danger);
            transform: scale(1.1);
        }

        /* Right Panel */
        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 25px;
            min-width: 380px;
        }

        .summary-container {
            margin-bottom: 25px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed rgba(0, 0, 0, 0.1);
        }

        .total-label {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: 1px;
        }

        .total-amount {
            font-size: 2rem;
            font-weight: 800;
            color: #4f46e5;
            text-shadow: 0 2px 10px rgba(79, 70, 229, 0.2);
        }

        /* Payment Inputs */
        .payment-display {
            background: rgba(0, 0, 0, 0.03);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
        }

        .pd-item {
            text-align: center;
            flex: 1;
        }

        .pd-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .pd-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .pd-value.due {
            color: var(--accent-danger);
        }

        /* Action Grid */
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .pay-btn {
            background: white;
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 15px;
            transition: all 0.2s;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
        }

        .pay-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .pay-btn i {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: #f3f4f6;
            color: #6366f1;
        }

        .pay-btn.main-action {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.5);
        }

        .pay-btn.main-action:hover {
            box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.6);
        }

        .pay-btn.main-action i {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* Search Results */
        #searchResults {
            margin-top: 10px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            max-height: 450px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            z-index: 1100;
        }

        .search-result-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover,
        .search-result-item.active {
            background: #f8fafc;
        }

        /* Modals */
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 20px 30px;
            border-radius: 20px 20px 0 0;
        }

        .modal-body {
            padding: 30px;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e2e8f0;
        }

        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        /* Note Badge */
        .note-badge {
            background: #fffbeb;
            border: 1px dashed #fbbf24;
            color: #b45309;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Return Mode Indicator */
        .return-mode-active .main-container {
            position: relative;
        }

        .return-mode-active .main-container::before {
            content: '';
            position: absolute;
            inset: 0;
            border: 4px solid var(--accent-warning);
            border-radius: 25px;
            pointer-events: none;
            z-index: 50;
        }

        /* Custom Notifications (Toasts) */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .custom-toast {
            min-width: 300px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toastSlideIn 0.3s ease-out forwards;
            position: relative;
            overflow: hidden;
        }

        .custom-toast::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 3px;
            width: 100%;
            background: currentColor;
            opacity: 0.3;
            animation: toastTimer 3s linear forwards;
        }

        @keyframes toastSlideIn {
            from {
                transform: translateX(120%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes toastTimer {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }

        .toast-success {
            color: #059669;
            border-left: 5px solid #10b981;
        }

        .toast-error {
            color: #dc2626;
            border-left: 5px solid #ef4444;
        }

        .toast-warning {
            color: #d97706;
            border-left: 5px solid #f59e0b;
        }

        .toast-info {
            color: #2563eb;
            border-left: 5px solid #3b82f6;
        }
    </style>
</head>

<body>

    <!-- Header -->
    <div class="top-bar glass-panel">
        <!-- Search -->
        <div class="search-container position-relative">
            <div class="search-input-group">
                <i class="fas fa-search me-3"></i>
                <input type="text" id="search" placeholder="Search products (F1)..." autocomplete="off">
            </div>
            <div id="searchResults" style="display:none; position:absolute; width:100%; z-index:100;"></div>
        </div>

        <!-- Header Actions -->
        <div class="d-flex gap-2">
            <button class="btn-custom" onclick="addComment()">
                <i class="fas fa-sticky-note text-secondary"></i> Note
            </button>
            <button class="btn-custom" onclick="openTransactionReturn()">
                <i class="fas fa-receipt text-primary"></i> Return
            </button>
            <button id="btnManualReturn" class="btn-custom btn-return-manual" onclick="toggleManualReturn()">
                <i class="fas fa-undo"></i> Manual
            </button>
            <button class="btn-custom" onclick="showRecallModal()">
                <i class="fas fa-clock text-info"></i> Recall
            </button>
            <button class="btn-custom" onclick="suspendTransaction()">
                <i class="fas fa-pause text-warning"></i> Pause
            </button>
            <button class="btn-custom" onclick="clearCart()">
                <i class="fas fa-trash text-danger"></i> Void Transaction
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">

        <!-- Left Panel: Items -->
        <div class="left-panel glass-panel">
            <div class="table-header">
                <div>Item</div>
                <div>Price</div>
                <div class="text-center">Qty</div>
                <div class="text-center">Disc</div>
                <div class="text-end">Total</div>
                <div></div>
            </div>
            <div class="items-list" id="cartList"></div>

            <div id="emptyCartMessage"
                class="d-none h-100 d-flex flex-column align-items-center justify-content-center text-muted opacity-50">
                <i class="fas fa-shopping-basket fa-4x mb-3"></i>
                <p class="fs-5">Start by scanning or searching items</p>
            </div>
        </div>

        <!-- Right Panel: Summary & Pay -->
        <div class="right-panel glass-panel">

            <div class="note-badge" id="orderNoteDisplay"></div>

            <div class="summary-container">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="subtotalDisplay">0.00</span>
                </div>
                <div class="summary-row">
                    <span>Discount</span>
                    <span id="discountDisplay" class="text-danger">0.00</span>
                </div>
                <div class="summary-row">
                    <span>Tax</span>
                    <span id="taxDisplay">0.00</span>
                </div>

                <div class="summary-total">
                    <span class="total-label">PAYABLE</span>
                    <span class="total-amount" id="totalDisplay">0.00</span>
                </div>
            </div>

            <div class="payment-display">
                <div class="pd-item" style="border-right:1px solid rgba(0,0,0,0.1)">
                    <div class="pd-label">Paid</div>
                    <div class="pd-value text-success" id="paidDisplay">0.00</div>
                </div>
                <div class="pd-item">
                    <div class="pd-label">Balance</div>
                    <div class="pd-value due" id="dueDisplay">0.00</div>
                </div>
            </div>

            <div class="payment-grid">
                <button class="pay-btn main-action" onclick="checkout('Cash')">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>CASH PAY</span>
                    <span class="ms-auto opacity-75 small fw-bold">F10</span>
                </button>
                <button class="pay-btn" onclick="checkout('Credit Card')">
                    <i class="fas fa-credit-card"></i> Card
                </button>
                <div class="d-flex gap-2">
                    <button class="pay-btn flex-1 w-100" onclick="setQtyForSelected()">
                        <i class="fas fa-calculator"></i> Qty
                    </button>
                    <button class="pay-btn flex-1 w-100" onclick="openDrawer()">
                        <i class="fas fa-inbox"></i> Drawer
                    </button>
                </div>
                <button class="pay-btn" style="color: var(--text-muted);" data-bs-toggle="collapse"
                    data-bs-target="#moreOptions">
                    <i class="fas fa-chevron-down bg-transparent"></i> More Options
                </button>
                <div class="collapse" id="moreOptions">
                    <div class="d-grid gap-2 mt-2">
                        <button class="pay-btn" onclick="checkout('Cheque')">
                            <i class="fas fa-money-check"></i> Cheque
                        </button>
                        <button class="pay-btn" onclick="checkout('Gift Card')">
                            <i class="fas fa-gift"></i> Gift Card
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Modals -->
    <!-- Recall Modal -->
    <div class="modal fade" id="recallModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Recall Suspended Sale</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group list-group-flush" id="suspendedList"></div>
                    <div id="noSuspendedMsg" class="text-center text-muted p-4" style="display:none;">
                        <i class="fas fa-history fa-2x mb-2 text-muted opacity-25"></i>
                        <p>No suspended sales found</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white" style="background: var(--primary-gradient) !important;">
                    <h5 class="modal-title fw-bold"><i class="fas fa-wallet me-2"></i>Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4 text-center">
                        <span class="text-muted text-uppercase fw-bold small">Amount Due</span>
                        <h2 class="display-4 fw-bold text-dark" id="modalDueDisplay">0.00</h2>
                        <span class="badge bg-light text-primary border px-3 py-2 rounded-pill"
                            id="paymentMethodLabel">Cash</span>
                    </div>

                    <label class="form-label fw-bold text-secondary">Tendered Amount</label>
                    <div class="input-group input-group-lg mb-3">
                        <span
                            class="input-group-text bg-light border-end-0 text-muted"><?php echo $currencySymbol; ?></span>
                        <input type="number" class="form-control border-start-0 ps-0 fw-bold fs-3" id="paymentAmount"
                            step="0.01" onkeydown="if(event.key === 'Enter') confirmPayment()">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-5 py-2 fw-bold rounded-pill"
                        onclick="confirmPayment()" style="background: var(--primary-gradient); border:none;">
                        Complete Payment <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Return Transaction Modal -->
    <div class="modal fade" id="returnModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-primary"><i class="fas fa-undo me-2"></i>Return from Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">

                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white"><i class="fas fa-hashtag text-muted"></i></span>
                            <input type="text" id="returnInvoiceNo" class="form-control" placeholder="INV-000000">
                            <button class="btn btn-primary" onclick="fetchOriginalSale()">Load Items</button>
                        </div>
                        <div id="returnFeedback" class="form-text mt-2 text-danger fw-bold"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom React-like Dialog Modal -->
    <div class="modal fade" id="customDialogModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
        style="z-index: 10000;">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content glass-panel border-0"
                style="background: rgba(255,255,255,0.95); box-shadow: 0 25px 50px rgba(0,0,0,0.3);">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <i id="cdIcon" class="fas fa-question-circle fa-3x text-primary"
                            style="background: -webkit-linear-gradient(135deg, #6366f1, #4f46e5); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                    </div>
                    <h5 id="cdTitle" class="fw-bold mb-2">Confirm?</h5>
                    <p id="cdMessage" class="text-muted mb-4 small">Are you sure?</p>

                    <div id="cdInputContainer" class="mb-4" style="display:none;">
                        <input type="text" id="cdInput" class="form-control text-center fw-bold text-dark"
                            style="font-size: 1.2rem;">
                    </div>

                    <div class="d-grid gap-2 d-flex justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold text-secondary"
                            style="min-width: 100px;" id="cdBtnCancel">Cancel</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold"
                            style="min-width: 100px; background: var(--primary-gradient); border:none;"
                            id="cdBtnOk">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Toast Container -->
    <div id="toastContainer"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /**
         * Global State & Variables
         */
        let cart = [];
        let payments = [];
        let saleComment = '';

        let selectedIndex = -1;
        let searchActiveIndex = -1;

        let isReturnMode = false;

        let currentPaymentMethod = '';
        let pendingGrandTotal = 0;
        let pendingSubtotal = 0;
        let pendingTaxTotal = 0;

        let returnModalObj = null;
        let paymentModalObj = null;

        const searchInput = document.getElementById('search');
        const resultsBox = document.getElementById('searchResults');

        // Mock data loading
        const hasCartItems = () => cart.length > 0;

        window.onload = () => {
            searchInput.focus();
            renderCart();
            setupShortcuts();
        };

        function setupShortcuts() {
            document.addEventListener('keydown', function (e) {
                if (e.key === 'F10') {
                    e.preventDefault();
                    checkout('Cash');
                }
                if (e.key === 'F1') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        }

        function formatPrice(amount) {
            return CURRENCY_SYMBOL + parseFloat(amount).toFixed(2);
        }

        // --- Custom Notifications ---
        function showNotification(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `custom-toast toast-${type}`;

            let icon = 'info-circle';
            if (type === 'success') icon = 'check-circle';
            if (type === 'error') icon = 'exclamation-circle';
            if (type === 'warning') icon = 'exclamation-triangle';

            toast.innerHTML = `
                <i class="fas fa-${icon} fs-5"></i>
                <div class="fw-bold">${message}</div>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'toastSlideIn 0.3s ease-out reverse forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // --- Search ---
        let searchTimeout = null;
        searchInput.addEventListener('input', function (e) {
            let q = e.target.value;
            clearTimeout(searchTimeout);
            searchActiveIndex = -1;

            if (q.length > 0) {
                searchTimeout = setTimeout(() => {
                    resultsBox.innerHTML = '<div class="p-3 text-center text-muted small"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</div>';
                    resultsBox.style.display = 'block';

                    fetch('pos_api.php?action=search&q=' + encodeURIComponent(q))
                        .then(res => {
                            if (!res.ok) throw new Error('Search failed');
                            return res.json();
                        })
                        .then(data => {
                            resultsBox.innerHTML = '';
                            if (data.length > 0) {
                                resultsBox.style.display = 'block';
                                data.forEach((p, idx) => {
                                    let div = document.createElement('div');
                                    div.className = 'search-result-item';
                                    div.innerHTML = `
                                        <div class='d-flex justify-content-between align-items-center'>
                                            <div>
                                                <div class='fw-bold text-dark'>${p.name}</div>
                                                <small class='text-muted'>SKU: ${p.sku} | Barcode: ${p.barcode || 'N/A'}</small>
                                            </div>
                                            <div class='text-end'>
                                                <div class='fw-bold text-primary'>${formatPrice(p.price)}</div>
                                                <small class='text-muted'>${p.stock_quantity} in stock</small>
                                            </div>
                                        </div>`;
                                    div.onclick = () => addToCart(p);
                                    resultsBox.appendChild(div);
                                });
                            } else {
                                resultsBox.innerHTML = '<div class="p-3 text-center text-muted small">No products found</div>';
                            }
                        })
                        .catch(err => {
                            resultsBox.innerHTML = `<div class="p-3 text-center text-danger small">Error: ${err.message}</div>`;
                        });
                }, 300);
            } else {
                resultsBox.style.display = 'none';
                resultsBox.innerHTML = '';
            }
        });

        searchInput.addEventListener('keydown', function (e) {
            const items = document.querySelectorAll('.search-result-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                searchActiveIndex = Math.min(searchActiveIndex + 1, items.length - 1);
                updateActiveSearchItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                searchActiveIndex = Math.max(searchActiveIndex - 1, -1);
                updateActiveSearchItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (searchActiveIndex >= 0 && items[searchActiveIndex]) {
                    items[searchActiveIndex].click();
                } else if (searchInput.value.length > 0 && items.length > 0) {
                    items[0].click();
                }
            }
        });

        function updateActiveSearchItem(items) {
            items.forEach(i => i.classList.remove('active'));
            if (searchActiveIndex >= 0 && items[searchActiveIndex]) {
                items[searchActiveIndex].classList.add('active');
                items[searchActiveIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        // --- Cart Operations ---
        function addToCart(product) {
            let qty = isReturnMode ? -1 : 1;

            let existing = cart.find(i => i.id == product.id && i.discount_percent == 0);
            if (existing) {
                existing.qty += qty;
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    qty: qty,
                    stock: product.stock_quantity || 0,
                    cost_price: parseFloat(product.cost_price) || 0,
                    discount_percent: 0,
                    tax_rate: parseFloat(product.tax_rate) || 0,
                    is_tax_inclusive: product.is_tax_inclusive == 1
                });
            }
            // Reset
            searchInput.value = '';
            resultsBox.style.display = 'none';
            searchActiveIndex = -1;

            selectedIndex = cart.length > 0 ? (existing ? cart.indexOf(existing) : cart.length - 1) : -1;
            renderCart();
            searchInput.focus();
        }

        // --- Custom Dialog System ---
        class CustomDialog {
            static show(options) {
                return new Promise((resolve) => {
                    const modalEl = document.getElementById('customDialogModal');
                    let modal = bootstrap.Modal.getInstance(modalEl);
                    if (!modal) {
                        modal = new bootstrap.Modal(modalEl);
                    }

                    const titleEl = document.getElementById('cdTitle');
                    const msgEl = document.getElementById('cdMessage');
                    const iconEl = document.getElementById('cdIcon');
                    const inputContainer = document.getElementById('cdInputContainer');
                    const inputEl = document.getElementById('cdInput');
                    const btnOk = document.getElementById('cdBtnOk');
                    const btnCancel = document.getElementById('cdBtnCancel');

                    // Reset content
                    titleEl.innerText = options.title || 'Confirm';
                    msgEl.innerText = options.message || '';
                    inputContainer.style.display = options.type === 'prompt' ? 'block' : 'none';
                    inputEl.value = options.defaultValue || '';

                    // Icon styling
                    iconEl.className = options.icon || 'fas fa-question-circle fa-3x text-primary';
                    if (options.iconColor) {
                        iconEl.style.webkitTextFillColor = options.iconColor;
                    }
                    else {
                        iconEl.style.webkitTextFillColor = '';
                    }

                    let resolved = false;

                    const cleanup = () => {
                        modalEl.removeEventListener('hidden.bs.modal', onHidden);
                        btnOk.onclick = null;
                        btnCancel.onclick = null;
                        inputEl.onkeydown = null;
                    };

                    const onHidden = () => {
                        if (!resolved) {
                            resolved = true;
                            resolve(null); // Treat dismissal as cancel
                            cleanup();
                        }
                    };

                    const close = () => {
                        modal.hide();
                    };

                    btnOk.onclick = () => {
                        if (!resolved) {
                            resolved = true;
                            close();
                            if (options.type === 'prompt') resolve(inputEl.value);
                            else resolve(true);
                            cleanup();
                        }
                    };

                    btnCancel.onclick = () => {
                        if (!resolved) {
                            resolved = true;
                            close();
                            resolve(null);
                            cleanup();
                        }
                    };

                    modalEl.addEventListener('hidden.bs.modal', onHidden);

                    if (options.type === 'prompt') {
                        inputEl.onkeydown = (e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                btnOk.click();
                            }
                        }
                        modalEl.addEventListener('shown.bs.modal', () => {
                            inputEl.focus();
                            inputEl.select();
                        }, { once: true });
                    }

                    modal.show();
                });
            }

            static async confirm(message, title = "Confirmation") {
                const res = await this.show({
                    type: 'confirm',
                    title: title,
                    message: message,
                    icon: 'fas fa-exclamation-triangle fa-3x text-warning',
                    iconColor: '#f59e0b'
                });
                return res === true; // Ensure explicit true
            }

            static async prompt(message, defaultValue = "", title = "Input Required") {
                return await this.show({
                    type: 'prompt',
                    title: title,
                    message: message,
                    defaultValue: defaultValue,
                    icon: 'fas fa-pen fa-3x text-info',
                    iconColor: '#3b82f6'
                });
            }
        }

        // --- Modified JS Logic ---

        function renderCart() {
            const list = document.getElementById('cartList');
            const emptyMsg = document.getElementById('emptyCartMessage');
            list.innerHTML = '';

            if (cart.length === 0) {
                list.style.display = 'none';
                emptyMsg.classList.remove('d-none');
            } else {
                list.style.display = 'block';
                emptyMsg.classList.add('d-none');
            }

            let subtotal = 0;
            let totalDiscount = 0;
            let totalTax = 0;
            let grandTotal = 0;

            cart.forEach((item, index) => {
                let gross = item.price * item.qty;
                let disc = gross * (item.discount_percent / 100);
                let net = gross - disc;
                let tax = 0;
                let rate = item.tax_rate / 100;
                if (item.is_tax_inclusive) {
                    tax = net - (net / (1 + rate));
                } else {
                    tax = net * rate;
                }

                subtotal += gross;
                totalDiscount += disc;
                totalTax += tax;
                if (item.is_tax_inclusive) grandTotal += net;
                else grandTotal += (net + tax);

                let row = document.createElement('div');
                row.className = `cart-row ${index === selectedIndex ? 'selected' : ''}`;
                row.onclick = () => {
                    selectedIndex = index;
                    renderCart();
                };

                let qtyDisplay = `<input type='text' class='qty-input' value='${item.qty}' readonly onclick='updateQty(${index})'>`;

                let discBadge = item.discount_percent > 0
                    ? `<span class='discount-badge bg-success text-white' onclick='addDiscount(${index})'>-${item.discount_percent}%</span>`
                    : `<span class='discount-badge text-muted bg-light' onclick='addDiscount(${index})'><i class='fas fa-tag small'></i></span>`;

                row.innerHTML = `
                    <div>
                        <div class='item-name'>${item.name}</div>
                        <div class='item-price'>@ ${formatPrice(item.price)}</div>
                    </div>
                    <div class='item-price'>${formatPrice(item.price * item.qty)}</div>
                    <div class='text-center'>${qtyDisplay}</div>
                    <div class='text-center'>${discBadge}</div>
                    <div class='text-end item-total'>${formatPrice(net)}</div>
                    <div class='delete-btn' onclick='removeFromCart(${index}); event.stopPropagation();'><i class='fas fa-times-circle fs-5'></i></div>
                `;
                list.appendChild(row);
            });

            document.getElementById('subtotalDisplay').innerText = formatPrice(subtotal);
            document.getElementById('discountDisplay').innerText = formatPrice(-totalDiscount);
            document.getElementById('taxDisplay').innerText = formatPrice(totalTax);
            document.getElementById('totalDisplay').innerText = formatPrice(grandTotal);

            let totalPaid = payments.reduce((sum, p) => sum + p.amount, 0);
            let due = grandTotal - totalPaid;

            document.getElementById('paidDisplay').innerText = formatPrice(totalPaid);
            let dueEl = document.getElementById('dueDisplay');
            dueEl.innerText = formatPrice(due);
            // Visual feedback for refund/due status
            if (due < 0) { dueEl.className = 'pd-value text-info'; }
            else if (due === 0) { dueEl.className = 'pd-value text-success'; }
            else { dueEl.className = 'pd-value due'; }

            const noteEl = document.getElementById('orderNoteDisplay');
            if (saleComment) {
                noteEl.style.display = 'flex';
                noteEl.innerHTML = `<span><i class='fas fa-comment-alt me-2 opacity-50'></i>${saleComment}</span> <i class='fas fa-times ms-2' style='cursor:pointer' onclick='addComment("")'></i>`;
            } else {
                noteEl.style.display = 'none';
            }
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            if (selectedIndex >= cart.length) selectedIndex = cart.length - 1;
            renderCart();
        }

        async function clearCart() {
            if (cart.length === 0) return showNotification('Cart is empty');

            const confirmed = await CustomDialog.confirm("Are you sure you want to void this transaction?", "Void Transaction");
            if (confirmed) {
                cart = [];
                payments = [];
                saleComment = '';
                selectedIndex = -1;
                closeReturnMode();
                renderCart();
            }
        }

        // --- Mods ---
        async function updateQty(index) {
            let newQty = await CustomDialog.prompt("Enter new quantity:", cart[index].qty, "Update Quantity");
            if (newQty !== null && !isNaN(newQty)) {
                cart[index].qty = parseFloat(newQty);
                renderCart();
            }
        }

        async function setQtyForSelected() {
            if (selectedIndex >= 0) updateQty(selectedIndex);
            else showNotification("No item selected");
        }

        async function addDiscount(index) {
            let disc = await CustomDialog.prompt("Enter Discount Percentage (%):", cart[index].discount_percent, "Add Discount");
            if (disc !== null && !isNaN(disc)) {
                cart[index].discount_percent = parseFloat(disc);
                renderCart();
            }
        }

        async function addComment(val) {
            let c = val;
            if (c === undefined) {
                c = await CustomDialog.prompt("Enter transaction note:", saleComment, "Sale Note");
            }
            if (c !== null) {
                saleComment = c;
                renderCart();
            }
        }

        // --- Suspend/Recall ---
        function suspendTransaction() {
            if (cart.length === 0) return showNotification('Cart is empty');

            let suspended = JSON.parse(localStorage.getItem('suspended_sales') || '[]');
            suspended.push({
                data: cart,
                note: saleComment,
                date: new Date().toLocaleTimeString(),
                total: document.getElementById('totalDisplay').innerText
            });
            localStorage.setItem('suspended_sales', JSON.stringify(suspended));

            cart = []; payments = []; saleComment = ''; selectedIndex = -1;
            renderCart();
            showNotification("Transaction suspended");
        }

        function showRecallModal() {
            const list = document.getElementById('suspendedList');
            const msg = document.getElementById('noSuspendedMsg');
            list.innerHTML = '';
            let suspended = JSON.parse(localStorage.getItem('suspended_sales') || '[]');

            if (suspended.length === 0) {
                msg.style.display = 'block';
            } else {
                msg.style.display = 'none';
                suspended.forEach((item, idx) => {
                    let a = document.createElement('a');
                    a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3';
                    a.innerHTML = `
                        <div>
                            <div class="fw-bold">${item.date}</div>
                            <small class="text-muted">${item.data.length} items ${item.note ? '• ' + item.note : ''}</small>
                        </div>
                        <span class="badge bg-light text-dark rounded-pill border">${item.total}</span>
                    `;
                    a.onclick = () => recallSale(idx);
                    list.appendChild(a);
                });
            }
            new bootstrap.Modal(document.getElementById('recallModal')).show();
        }

        async function recallSale(index) {
            let suspended = JSON.parse(localStorage.getItem('suspended_sales') || '[]');

            if (cart.length > 0) {
                const confirmed = await CustomDialog.confirm("Overwrite current cart with recalled item?", "Recall Sale");
                if (!confirmed) return;
            }

            let sale = suspended[index];
            cart = sale.data;
            saleComment = sale.note || '';
            payments = [];

            suspended.splice(index, 1);
            localStorage.setItem('suspended_sales', JSON.stringify(suspended));

            bootstrap.Modal.getInstance(document.getElementById('recallModal')).hide();
            renderCart();
        }

        // --- Returns ---
        function openTransactionReturn() {
            if (!returnModalObj) {
                returnModalObj = new bootstrap.Modal(document.getElementById('returnModal'));
            }
            returnModalObj.show();
            setTimeout(() => document.getElementById('returnInvoiceNo').focus(), 500);
        }

        function toggleManualReturn() {
            isReturnMode = !isReturnMode;
            const btn = document.getElementById('btnManualReturn');

            if (isReturnMode) {
                document.body.classList.add('return-mode-active');
                btn.classList.add('active');
            } else {
                document.body.classList.remove('return-mode-active');
                btn.classList.remove('active');
            }
            searchInput.focus();
        }

        function closeReturnMode() {
            if (isReturnMode) {
                isReturnMode = false;
                document.body.classList.remove('return-mode-active');
                document.getElementById('btnManualReturn').classList.remove('active');
            }
        }

        function fetchOriginalSale() {
            const inv = document.getElementById('returnInvoiceNo').value.trim();
            const feedback = document.getElementById('returnFeedback');
            feedback.innerText = '';

            if (!inv) {
                feedback.innerText = 'Please enter an invoice number.';
                return;
            }

            fetch('pos_api.php?action=get_sale&invoice=' + encodeURIComponent(inv))
                .then(res => {
                    if (!res.ok) throw new Error('Invoice not found');
                    return res.json();
                })
                .then(async data => {
                    if (cart.length > 0) {
                        const confirmed = await CustomDialog.confirm("This will clear your current cart items. Continue?", "Import Return");
                        if (!confirmed) return;
                    }

                    cart = [];
                    data.items.forEach(item => {
                        cart.push({
                            id: item.product_id,
                            name: item.name,
                            price: parseFloat(item.unit_price),
                            qty: -Math.abs(item.quantity),
                            stock: item.stock_quantity || 0,
                            discount_percent: 0,
                            tax_rate: parseFloat(item.tax_rate) || 0,
                            is_tax_inclusive: item.is_tax_inclusive == 1
                        });
                    });
                    // Auto-enable manual return mode for visual consistency
                    if (!isReturnMode) toggleManualReturn();

                    renderCart();
                    if (returnModalObj) returnModalObj.hide();
                })
                .catch(err => {
                    feedback.innerText = err.message;
                });
        }


        // --- Payment ---
        function openDrawer() {
            // Placeholder: In a real app, this calls an API to fire the print spooler
            showNotification("Drawer Open Signal Sent");
        }

        function checkout(method) {
            if (cart.length === 0) return showNotification("Cart is empty");

            let grandTotal = 0;
            let subtotal = 0;
            let totalTax = 0;

            cart.forEach(item => {
                let gross = item.price * item.qty;
                let disc = gross * (item.discount_percent / 100);
                let net = gross - disc;
                let rate = item.tax_rate / 100;
                let tax = item.is_tax_inclusive ? (net - (net / (1 + rate))) : (net * rate);

                subtotal += gross;
                totalTax += tax;
                grandTotal += item.is_tax_inclusive ? net : (net + tax);
            });

            let totalPaid = payments.reduce((sum, p) => sum + p.amount, 0);
            let due = grandTotal - totalPaid;

            if (Math.abs(due) <= 0.001) {
                return showNotification("Fully Paid");
            }

            // Persistence
            currentPaymentMethod = method;
            pendingGrandTotal = grandTotal;
            pendingSubtotal = subtotal;
            pendingTaxTotal = totalTax;

            if (due < 0) {
                if (method === 'Cash') currentPaymentMethod = 'Return Cash';
                else if (!method.startsWith('Return') && !method.startsWith('Refund')) currentPaymentMethod = 'Refund ' + method;
            }

            // Update Modal UI
            document.getElementById('paymentMethodLabel').innerText = currentPaymentMethod;
            document.getElementById('modalDueDisplay').innerText = formatPrice(due);
            const amtInput = document.getElementById('paymentAmount');
            amtInput.value = due.toFixed(2);

            if (!paymentModalObj) {
                paymentModalObj = new bootstrap.Modal(document.getElementById('paymentModal'));
            }
            paymentModalObj.show();

            setTimeout(() => {
                amtInput.focus();
                amtInput.select();
            }, 500);
        }

        function confirmPayment() {
            let amount = parseFloat(document.getElementById('paymentAmount').value);

            if (isNaN(amount)) return showNotification("Invalid Amount");

            if (pendingGrandTotal > 0 && amount <= 0) return showNotification("Enter positive amount.");
            if (pendingGrandTotal < 0 && amount >= 0) return showNotification("Confirm negative amount for refund.");

            if (paymentModalObj) paymentModalObj.hide();

            payments.push({ method: currentPaymentMethod, amount: amount });

            let totalPaid = payments.reduce((sum, p) => sum + p.amount, 0);
            let remaining = pendingGrandTotal - totalPaid;

            renderCart();

            if (Math.abs(remaining) <= 0.01) {
                submitSale(pendingGrandTotal, pendingSubtotal, pendingTaxTotal);
            }
        }

        function submitSale(grandTotal, subtotalRaw, taxTotal) {
            let finalItems = cart.map(i => {
                let effectivePrice = i.price * (1 - (i.discount_percent / 100));
                return {
                    id: i.id,
                    qty: i.qty,
                    price: effectivePrice,
                    cost_price: i.cost_price || 0
                };
            });

            let payload = {
                items: finalItems,
                subtotal: subtotalRaw,
                tax_total: taxTotal,
                grand_total: grandTotal,
                payment_method: "Split",
                payments: payments,
                notes: saleComment
            };

            fetch('pos_api.php?action=submit_sale', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.open('print_receipt.php?id=' + data.sale_id, '_blank', 'width=350,height=600');
                        cart = [];
                        payments = [];
                        saleComment = '';
                        selectedIndex = -1;
                        closeReturnMode();
                        renderCart();
                    } else {
                        showNotification("Error: " + data.error);
                    }
                });
        }
    </script>
</body>

</html>