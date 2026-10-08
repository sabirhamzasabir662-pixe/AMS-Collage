<?php
/**
 * finance_dashboard.php — Finance Dashboard Placeholder
 * Arsalan Model School and College Management System
 */
require_once '../database.php';
require_login();
require_role(['admin', 'finance']);
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background-color: #f4f6f9; min-height: 100vh; font-family: 'Segoe UI', Arial, sans-serif; }
        .dashboard-container { max-width: 900px; margin: 40px auto; padding: 20px; }
        .hero-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            padding: 40px;
            text-align: center;
        }
        .role-badge {
            background: linear-gradient(135deg, #d97706, #b45309);
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: rgba(217, 119, 6, 0.1);
            color: #d97706;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 24px;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0 fw-bold text-navy"><i class="fa fa-graduation-cap me-2 text-warning"></i><?= APP_NAME ?></h5>
            <small class="text-muted">Finance & Accounts Portal</small>
        </div>
        <div>
            <span class="me-3 text-secondary"><i class="fa fa-user-circle me-1"></i><?= h($user['username']) ?></span>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm"><i class="fa fa-sign-out-alt me-1"></i>Logout</a>
        </div>
    </div>

    <div class="hero-card">
        <div class="icon-circle">
            <i class="fa fa-file-invoice-dollar"></i>
        </div>
        <span class="role-badge"><i class="fa fa-coins me-1"></i>Finance Department</span>
        <h2 class="fw-bold text-dark mb-2">Finance Dashboard — Coming Soon</h2>
        <p class="text-muted mb-4" style="max-width: 580px; margin-left: auto; margin-right: auto;">
            Welcome, <strong><?= h($user['username']) ?></strong>! The dedicated finance dashboard for handling fee collection, student invoices, fee structures, receipts, and financial reports is being finalized.
        </p>

        <div class="alert alert-warning d-inline-block text-start px-4 py-3 mb-4" style="max-width: 520px;">
            <div class="d-flex">
                <i class="fa fa-chart-line fa-2x me-3 text-warning"></i>
                <div>
                    <strong>Account Status: Active</strong><br>
                    <small class="text-muted">Role: <code>finance</code> &bull; Authenticated User ID: #<?= h((string)$user['id']) ?></small>
                </div>
            </div>
        </div>

        <div>
            <a href="../fees/fee_records.php" class="btn btn-success px-4 me-2"><i class="fa fa-receipt me-1"></i>Fee Records</a>
            <a href="../fees/add_fee.php" class="btn btn-outline-primary px-4 me-2"><i class="fa fa-plus me-1"></i>Collect Fee</a>
            <a href="../logout.php" class="btn btn-secondary px-4"><i class="fa fa-sign-out-alt me-1"></i>Sign Out</a>
        </div>
    </div>
</div>
</body>
</html>
