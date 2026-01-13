<?php
require_once 'config/config.php';
require_once 'core/Auth.php';
Session::checkLogin();

$db = new Database();
$conn = $db->getConnection();

$message = '';

$currencies = array(
    'USD' => 'US Dollar ($)',
    'EUR' => 'Euro (€)',
    'GBP' => 'British Pound (£)',
    'INR' => 'Indian Rupee (₹)',
    'AED' => 'UAE Dirham (د.إ)',
    'SAR' => 'Saudi Riyal (﷼)',
    'CAD' => 'Canadian Dollar (C$)',
    'AUD' => 'Australian Dollar (A$)',
    'JPY' => 'Japanese Yen (¥)',
    'CNY' => 'Chinese Yuan (¥)',
    'PKR' => 'Pakistani Rupee (₨)',
    'BDT' => 'Bangladeshi Taka (৳)',
    'MYR' => 'Malaysian Ringgit (RM)',
    'SGD' => 'Singapore Dollar (S$)',
    'ZAR' => 'South African Rand (R)',
    'NGN' => 'Nigerian Naira (₦)',
    'KES' => 'Kenyan Shilling (KSh)',
    'GHS' => 'Ghanaian Cedi (₵)',
    'EGP' => 'Egyptian Pound (E£)',
    'BRL' => 'Brazilian Real (R$)',
    'MXN' => 'Mexican Peso (MX$)'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_company'])) {
    try {
        $stmt = $conn->prepare("UPDATE companies SET company_name = ?, tax_number = ?, address = ?, phone = ?, email = ?, currency = ? WHERE id = ?");
        $stmt->execute(array(
            $_POST['company_name'],
            $_POST['tax_number'],
            $_POST['address'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['currency'],
            $_SESSION['company_id']
        ));
        $_SESSION['company_currency'] = $_POST['currency'];
        $message = "Company profile updated successfully!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle FBR Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fbr'])) {
    try {
        $posId = $_POST['pos_id'];
        $authToken = $_POST['auth_token'];
        $baseUrl = $_POST['base_url'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $environment = $_POST['environment'];

        // Check if settings exist
        $check = $conn->prepare("SELECT id FROM fbr_settings WHERE company_id = ?");
        $check->execute([$_SESSION['company_id']]);
        
        if ($check->fetch()) {
            $stmt = $conn->prepare("UPDATE fbr_settings SET pos_id = ?, auth_token = ?, base_url = ?, is_active = ?, environment = ? WHERE company_id = ?");
            $stmt->execute([$posId, $authToken, $baseUrl, $isActive, $environment, $_SESSION['company_id']]);
        } else {
            $stmt = $conn->prepare("INSERT INTO fbr_settings (company_id, pos_id, auth_token, base_url, is_active, environment) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['company_id'], $posId, $authToken, $baseUrl, $isActive, $environment]);
        }
        $message = "FBR Settings updated successfully!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

$fbrStmt = $conn->prepare("SELECT * FROM fbr_settings WHERE company_id = ?");
$fbrStmt->execute([$_SESSION['company_id']]);
$fbrSettings = $fbrStmt->fetch(PDO::FETCH_ASSOC);
if (!$fbrSettings) {
    // Default values
    $fbrSettings = [
        'pos_id' => '',
        'auth_token' => '',
        'base_url' => 'https://esp.fbr.gov.pk:8243/FBR/v1/api/Live/PostData',
        'is_active' => 0,
        'environment' => 'PRODUCTION'
    ];
}

$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute(array($_SESSION['company_id']));
$company = $stmt->fetch(PDO::FETCH_ASSOC);

require_once 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h2 class="fw-bold">Organization Setup</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Organization</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Company Profile</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-4 shadow-sm border-0">
                <h5 class="card-title fw-bold border-bottom pb-3 mb-4"><i class="fas fa-building me-2"></i>Company
                    Profile</h5>

                <form method="POST">
                    <input type="hidden" name="update_company" value="1">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control"
                            value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tax Number</label>
                        <input type="text" name="tax_number" class="form-control"
                            value="<?php echo htmlspecialchars($company['tax_number'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control"
                            value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-select" required>
                            <?php
                            $currentCurrency = $company['currency'] ?? 'USD';
                            foreach ($currencies as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($currentCurrency == $code) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">This currency will be used across all transactions.</small>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Update
                            Profile</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>

    <!-- FBR Configuration Section -->
    <div class="row justify-content-center mt-4">
        <div class="col-md-8">
            <div class="card p-4 shadow-sm border-0">
                <h5 class="card-title fw-bold border-bottom pb-3 mb-4">
                    <i class="fas fa-network-wired me-2"></i>FBR Integration
                </h5>
                
                <form method="POST">
                    <input type="hidden" name="update_fbr" value="1">
                    
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="is_active" id="fbr_active" 
                            <?php echo $fbrSettings['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="fbr_active">Enable FBR Integration</label>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">POS ID</label>
                            <input type="number" name="pos_id" class="form-control" 
                                value="<?php echo htmlspecialchars($fbrSettings['pos_id'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Environment</label>
                            <select name="environment" class="form-select">
                                <option value="PRODUCTION" <?php echo $fbrSettings['environment'] == 'PRODUCTION' ? 'selected' : ''; ?>>Production</option>
                                <option value="TEST" <?php echo $fbrSettings['environment'] == 'TEST' ? 'selected' : ''; ?>>Test / Sandbox</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Auth Token (Bearer)</label>
                        <div class="input-group">
                            <input type="password" name="auth_token" class="form-control" id="auth_token"
                                value="<?php echo htmlspecialchars($fbrSettings['auth_token'] ?? ''); ?>" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleToken()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Base URL</label>
                        <input type="url" name="base_url" class="form-control" 
                            value="<?php echo htmlspecialchars($fbrSettings['base_url'] ?? ''); ?>" required>
                        <small class="text-muted">Default: https://esp.fbr.gov.pk:8243/FBR/v1/api/Live/PostData</small>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-success px-4"><i class="fas fa-save me-2"></i>Save FBR Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function toggleToken() {
        var x = document.getElementById("auth_token");
        var icon = document.getElementById("toggleIcon");
        if (x.type === "password") {
            x.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            x.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }
    </script>
</div>

<?php require_once 'templates/footer.php'; ?>