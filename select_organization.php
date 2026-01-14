<?php
require_once 'config/config.php';
require_once 'core/Auth.php';


if (!Session::get('user_id')) {
    header("Location: login.php");
    exit;
}

$auth = new Auth();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'])) {
    if ($auth->switchOrganization(Session::get('user_id'), $_POST['company_id'])) {
        header("Location: index.php");
        exit;
    } else {
        $error = "Failed to switch organization.";
    }
}

$orgs = $auth->getAvailableOrganizations(Session::get('user_id'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Select Organization - Retail POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .org-card {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            background: white;
        }

        .org-item {
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #eee;
        }

        .org-item:hover {
            background-color: #f8f9fa;
            border-color: var(--bs-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>
    <div class="org-card">
        <h3 class="text-center fw-bold mb-4">Select Organization</h3>
        <p class="text-center text-muted mb-4">You have access to multiple organizations. Please select one to continue.
        </p>

        <?php if (Session::get('is_super_admin') == 1): ?>
            <div class="text-center mb-3">
                <a href="org_create.php" class="btn btn-sm btn-success shadow-sm">
                    <i class="fas fa-plus-circle me-1"></i>Create New Organization
                </a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <?php foreach ($orgs as $org): ?>
                <div class="col-12">
                    <form method="POST">
                        <input type="hidden" name="company_id" value="<?php echo $org['id']; ?>">
                        <button type="submit"
                            class="org-item w-100 p-3 rounded d-flex align-items-center justify-content-between btn text-start">
                            <div>
                                <h5 class="fw-bold mb-1">
                                    <?php echo htmlspecialchars($org['company_name']); ?>
                                </h5>
                                <small class="text-muted">Role ID:
                                    <?php echo $org['role_id']; ?>
                                </small>
                            </div>
                            <i class="fas fa-chevron-right text-primary"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4 border-top pt-3">
            <a href="logout.php" class="text-decoration-none text-danger"><i class="fas fa-sign-out-alt me-1"></i>
                Logout</a>
        </div>
    </div>
</body>

</html>