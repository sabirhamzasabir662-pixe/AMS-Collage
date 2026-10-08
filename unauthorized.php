<?php
/**
 * unauthorized.php — 403 Forbidden / Not Authorized Page
 * Arsalan Model School and College Management System
 */
require_once 'database.php';
require_login();
$user = current_user();
$home_url = get_role_dashboard($user['role'] ?? 'admin', '', (int)($user['id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background-color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Arial, sans-serif; color: #f8fafc; }
        .error-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 40px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }
        .icon-circle {
            width: 90px;
            height: 90px;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body>
<div class="error-card">
    <div class="icon-circle">
        <i class="fa fa-shield-alt"></i>
    </div>
    <span class="badge bg-danger mb-3 px-3 py-2 text-uppercase">403 — Access Denied</span>
    <h3 class="fw-bold mb-2">Restricted Access</h3>
    <p class="text-secondary mb-4">
        You do not have the required permissions to access this page. Your current account role is <span class="badge bg-secondary"><?= h($user['role'] ?? 'guest') ?></span>.
    </p>
    <div class="d-flex justify-content-center gap-2">
        <a href="<?= h($home_url) ?>" class="btn btn-primary px-4">
            <i class="fa fa-home me-1"></i>Go to My Dashboard
        </a>
        <a href="logout.php" class="btn btn-outline-light px-3">
            <i class="fa fa-sign-out-alt me-1"></i>Logout
        </a>
    </div>
</div>
</body>
</html>
