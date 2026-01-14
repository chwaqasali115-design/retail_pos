<?php
require_once 'config/config.php';
require_once 'core/Auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth();
    $loginStatus = $auth->login($_POST['username'], $_POST['password']);

    if ($loginStatus === 'SUCCESS') {
        header("Location: index.php");
        exit;
    } elseif ($loginStatus === 'MULTI_ORG') {
        header("Location: select_organization.php");
        exit;
    } elseif ($loginStatus === 'NO_ORG') {
        $error = "No active organization assigned to your account.";
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - Retail POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="public/assets/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            background: white;
        }

        .brand-logo {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="brand-logo"><i class="fas fa-cube"></i> Retail POS</div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required placeholder="admin">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="admin123">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Sign In</button>
        </form>
        <div class="text-center mt-3 text-muted">
            <small>Default: admin / admin123</small>
        </div>
    </div>
</body>

</html>