<?php
/**
 * fees/print_receipt.php — Official Printable Fee Receipt
 * Arsalan Model School and College Management System
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/fees_helper.php';
require_login();
$user = current_user();

if (($user['role'] ?? '') !== 'parent') {
    require_permission('fees.print');
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    set_flash('error', 'Invalid fee record ID.');
    if (($user['role'] ?? '') === 'parent') {
        redirect('../parent/fees.php');
    } else {
        redirect('fee_records.php');
    }
}

// Fetch fee record with student and class details using prepared statement
$fee = fee_db_fetch_one(
    "SELECT f.*, 
            s.student_id AS stu_code, s.first_name, s.last_name, s.father_name, s.phone, s.email, s.admission_date,
            c.class_name, c.section, c.room_number
     FROM fees f
     JOIN students s ON f.student_id = s.id
     LEFT JOIN classes c ON f.class_id = c.id
     WHERE f.id = ?",
    [$id],
    'i'
);

if (!$fee) {
    set_flash('error', 'Fee record not found.');
    if (($user['role'] ?? '') === 'parent') {
        redirect('../parent/fees.php');
    } else {
        redirect('fee_records.php');
    }
}

// Scoping: If user is a parent, strictly verify this fee record belongs to their child
if (($user['role'] ?? '') === 'parent') {
    $is_own = db_count("SELECT COUNT(*) FROM students WHERE id = ? AND parent_user_id = ?", 'ii', [(int)$fee['student_id'], (int)$user['id']]);
    if (!$is_own) {
        redirect('../unauthorized.php');
    }
}

$receipt_no = 'AMS-FEE-' . str_pad((string)$fee['id'], 5, '0', STR_PAD_LEFT);
$page_title = 'Fee Receipt ' . $receipt_no;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($receipt_no) ?> - Fee Receipt - Arsalan Model School &amp; College</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f0f2f5;
            color: #1a202c;
            margin: 0;
            padding: 0;
        }

        .screen-topbar {
            background: #1a3a52;
            color: #fff;
            padding: 14px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        .receipt-wrapper {
            max-width: 800px;
            margin: 30px auto 50px auto;
            padding: 0 15px;
        }

        .receipt-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        /* Top decorative accent bar */
        .receipt-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #1a3a52 0%, #2a5f8f 50%, #4a90e2 100%);
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 24px;
            margin-bottom: 28px;
        }

        .school-logo-icon {
            width: 60px;
            height: 60px;
            background: #1a3a52;
            color: #ffffff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(26,58,82,0.25);
        }

        .school-name {
            font-size: 24px;
            font-weight: 800;
            color: #1a3a52;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }

        .school-sub {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin: 0 0 10px 0;
        }

        .receipt-badge-title {
            display: inline-block;
            background: #f1f5f9;
            color: #1e293b;
            font-size: 13px;
            font-weight: 700;
            padding: 6px 18px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid #cbd5e1;
        }

        .meta-box {
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 14px 18px;
            margin-bottom: 24px;
        }

        .meta-item {
            font-size: 13px;
        }
        .meta-label {
            color: #64748b;
            font-weight: 600;
        }
        .meta-value {
            color: #0f172a;
            font-weight: 700;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }

        .details-table th, .details-table td {
            padding: 12px 16px;
            font-size: 14px;
        }

        .details-table thead th {
            background-color: #1a3a52;
            color: #ffffff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .details-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }

        .details-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .amount-highlight {
            font-size: 22px;
            font-weight: 800;
            color: #1a3a52;
        }

        .stamp-box {
            display: inline-block;
            border: 2px solid #22c55e;
            color: #15803d;
            font-weight: 800;
            padding: 6px 18px;
            border-radius: 6px;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 1px;
            transform: rotate(-4deg);
        }

        .stamp-box.pending {
            border-color: #f59e0b;
            color: #b45309;
        }

        .signatures-row {
            margin-top: 50px;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .sig-box {
            text-align: center;
            width: 200px;
        }

        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-bottom: 6px;
        }

        .sig-text {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }

        .receipt-footer {
            margin-top: 30px;
            border-top: 1px solid #f1f5f9;
            padding-top: 15px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        /* ═══════════ PRINT STYLES ═══════════ */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 12pt;
            }

            .screen-topbar,
            .no-print,
            .sidebar,
            .navbar,
            nav,
            header,
            footer,
            .topbar,
            .btn {
                display: none !important;
            }

            .receipt-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .receipt-card {
                border: 2px solid #000000 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 25px 30px !important;
            }

            .receipt-card::before {
                display: none !important;
            }

            .school-name {
                color: #000000 !important;
            }

            .details-table thead th {
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                border: 1px solid #000000 !important;
            }

            .details-table td, .details-table th {
                border: 1px solid #cbd5e1 !important;
            }

            .meta-box {
                border: 1px solid #000000 !important;
                background: none !important;
            }

            .stamp-box {
                border: 2px solid #000000 !important;
                color: #000000 !important;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN-ONLY ACTION BAR -->
    <div class="screen-topbar no-print">
        <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-receipt-cutoff fs-4 text-warning"></i>
                <span class="fw-bold fs-5">Official Fee Receipt</span>
                <span class="badge bg-light text-dark ms-2"><?= h($receipt_no) ?></span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" onclick="window.print()" class="btn btn-warning fw-semibold shadow-sm">
                    <i class="bi bi-printer me-1"></i> Print Receipt
                </button>
                <a href="fee_records.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-1"></i> Back to Fee Records
                </a>
                <a href="add_fee.php" class="btn btn-outline-light">
                    <i class="bi bi-plus-circle me-1"></i> Add Another Fee
                </a>
            </div>
        </div>
    </div>

    <div class="receipt-wrapper">
        <div class="receipt-card">

            <!-- RECEIPT HEADER -->
            <div class="receipt-header">
                <div class="school-logo-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h1 class="school-name">Arsalan Model School &amp; College</h1>
                <p class="school-sub">Recognized &bull; Affiliated &bull; Quality Education &bull; Management System</p>
                <div class="receipt-badge-title">
                    Fee Payment Receipt (Official Voucher)
                </div>
            </div>

            <!-- RECEIPT META (2 Columns) -->
            <div class="meta-box">
                <div class="row g-2">
                    <div class="col-6 col-md-3 meta-item">
                        <div class="meta-label">Receipt / Voucher #</div>
                        <div class="meta-value text-primary"><?= h($receipt_no) ?></div>
                    </div>
                    <div class="col-6 col-md-3 meta-item">
                        <div class="meta-label">Fee Month &amp; Year</div>
                        <div class="meta-value"><?= h($fee['fee_month']) ?></div>
                    </div>
                    <div class="col-6 col-md-3 meta-item">
                        <div class="meta-label">Payment Date</div>
                        <div class="meta-value">
                            <?= !empty($fee['payment_date']) ? date('d M Y', strtotime($fee['payment_date'])) : '—' ?>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 meta-item">
                        <div class="meta-label">Payment Method</div>
                        <div class="meta-value"><?= h($fee['payment_method']) ?></div>
                    </div>
                </div>
            </div>

            <!-- STUDENT & CLASS DETAILS -->
            <div class="table-responsive">
                <table class="details-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Student ID</th>
                            <th style="width: 40%;">Student Full Name</th>
                            <th style="width: 35%;">Class &amp; Section</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong class="text-primary fs-6"><?= h($fee['stu_code']) ?></strong>
                            </td>
                            <td>
                                <strong><?= h($fee['first_name'] . ' ' . $fee['last_name']) ?></strong>
                                <?php if (!empty($fee['father_name'])): ?>
                                    <div class="small text-muted">S/O, D/O: <?= h($fee['father_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= h($fee['class_name'] ?? 'Unassigned') ?></strong>
                                <?= !empty($fee['section']) ? '— Section ' . h($fee['section']) : '' ?>
                                <?php if (!empty($fee['room_number'])): ?>
                                    <span class="small text-muted">(Room <?= h($fee['room_number']) ?>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAYMENT BREAKDOWN TABLE -->
            <div class="table-responsive">
                <table class="details-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">#</th>
                            <th style="width: 55%;">Description</th>
                            <th style="width: 35%; text-align: right;">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>
                                <strong>Monthly Tuition / Academic Fee (<?= h($fee['fee_month']) ?>)</strong>
                                <?php if (!empty($fee['remarks'])): ?>
                                    <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i> <?= h($fee['remarks']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 700;">
                                Rs. <?= number_format((float)$fee['amount'], 2) ?>
                            </td>
                        </tr>
                        <tr style="background: #f8fafc; font-weight: 700;">
                            <td colspan="2" style="text-align: right; text-transform: uppercase; font-size: 13px;">
                                Total Amount <?= ($fee['status'] === 'paid') ? 'Paid' : 'Due' ?>:
                            </td>
                            <td style="text-align: right;">
                                <span class="amount-highlight">Rs. <?= number_format((float)$fee['amount'], 2) ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAYMENT STATUS & STAMP -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 my-3 p-3 bg-light rounded border">
                <div>
                    <div class="small text-muted mb-1">Status of Payment:</div>
                    <?php if ($fee['status'] === 'paid'): ?>
                        <div class="stamp-box">
                            <i class="bi bi-patch-check-fill me-1"></i> PAID &bull; VERIFIED
                        </div>
                    <?php else: ?>
                        <div class="stamp-box pending">
                            <i class="bi bi-clock-history me-1"></i> PENDING PAYMENT
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-end">
                    <div class="small text-muted">Transaction Reference:</div>
                    <div class="fw-bold font-monospace">REF-<?= strtoupper(substr(md5((string)$fee['id'] . $fee['created_at']), 0, 10)) ?></div>
                    <div class="small text-muted">Created: <?= date('d M Y, h:i A', strtotime($fee['created_at'])) ?></div>
                </div>
            </div>

            <!-- SIGNATURES SECTION -->
            <div class="signatures-row">
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-text">Cashier / Accounts</div>
                </div>

                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-text">Principal / Authorized Sign</div>
                </div>
            </div>

            <!-- FOOTER NOTICE -->
            <div class="receipt-footer">
                This is a computer-generated official receipt issued by Arsalan Model School and College Management System.
                <br>
                For fee queries or verification, please contact the accounts office.
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
