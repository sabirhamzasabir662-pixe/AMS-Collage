<?php
/**
 * parent/fees.php — Child Fee History & Ledger
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/parent_helper.php';

require_login();
require_role(['parent']);
require_permission('fees.view');

$user = current_user();
$student = get_parent_student($user['id']);

$page_title  = 'Fee Records';
$active_menu = 'parent_fees';
$breadcrumb  = ['Parent Portal', 'Fee Records'];
$depth       = '../';

$fee_list = [];
$total_invoiced = 0;
$total_paid = 0;
$total_pending = 0;

if ($student) {
    $stu_id = (int)$student['id'];

    $fee_list = db_fetch_all(
        "SELECT f.*, c.class_name 
         FROM fees f 
         LEFT JOIN classes c ON f.class_id = c.id 
         WHERE f.student_id = ? 
         ORDER BY f.id DESC",
        'i',
        [$stu_id]
    );

    foreach ($fee_list as $f) {
        $amt = (float)$f['amount'];
        $total_invoiced += $amt;
        if ($f['status'] === 'paid') {
            $total_paid += $amt;
        } else {
            $total_pending += $amt;
        }
    }
}

include '../layout.php';
?>

<?php if (!$student): ?>
    <div class="card-ams p-5 text-center mb-4">
        <div class="mb-3 text-warning">
            <i class="fas fa-exclamation-triangle fa-3x"></i>
        </div>
        <h4 class="fw-bold text-navy">No Student Linked</h4>
        <p class="text-muted">No student record is linked to this parent login.</p>
    </div>
<?php else: ?>

    <!-- Header Summary -->
    <div class="card-ams mb-4">
        <div class="card-body-ams d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-navy mb-1">
                    <i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Fee Invoices &amp; Payment Ledger
                </h4>
                <div class="text-muted small">
                    Student: <strong class="text-dark"><?= h($student['first_name'] . ' ' . $student['last_name']) ?></strong> 
                    &bull; Roll No: <strong><?= h($student['student_id']) ?></strong> 
                    &bull; Class: <strong><?= h($student['class_name'] ?? 'Unassigned') ?><?= !empty($student['section']) ? ' (' . h($student['section']) . ')' : '' ?></strong>
                </div>
            </div>
            <div>
                <?php if ($total_pending > 0): ?>
                    <span class="badge bg-danger-subtle text-danger px-3 py-2 fs-6 border border-danger-subtle">
                        <i class="fas fa-exclamation-circle me-1"></i> Pending Dues: Rs. <?= number_format($total_pending) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-success-subtle text-success px-3 py-2 fs-6 border border-success-subtle">
                        <i class="fas fa-check-circle me-1"></i> All Dues Cleared
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Fee Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Total Invoiced</div>
                <h3 class="fw-bold mb-0 text-navy">Rs. <?= number_format($total_invoiced) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Total Paid</div>
                <h3 class="fw-bold mb-0 text-success">Rs. <?= number_format($total_paid) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Current Outstanding</div>
                <h3 class="fw-bold mb-0 <?= $total_pending > 0 ? 'text-danger' : 'text-success' ?>">
                    Rs. <?= number_format($total_pending) ?>
                </h3>
            </div>
        </div>
    </div>

    <!-- Fee Ledger Table -->
    <div class="card-ams">
        <div class="card-header-ams d-flex justify-content-between align-items-center">
            <span class="fw-bold text-navy">
                <i class="fas fa-receipt me-2 text-primary"></i>Fee Transaction History
            </span>
            <span class="text-muted small">
                Showing all recorded fee vouchers
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Fee Month</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                        <th>Payment Method</th>
                        <th>Remarks</th>
                        <th class="text-end" style="width: 130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fee_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-receipt fa-2x mb-2 text-secondary"></i><br>
                                No fee invoices recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $idx = 1; foreach ($fee_list as $row): ?>
                            <tr>
                                <td class="text-muted"><?= $idx++ ?></td>
                                <td>
                                    <strong><?= h($row['fee_month']) ?></strong>
                                </td>
                                <td>
                                    <span class="fw-bold text-navy">Rs. <?= number_format($row['amount'], 2) ?></span>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'paid'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-clock me-1"></i>Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($row['payment_date']) ? date('M d, Y', strtotime($row['payment_date'])) : '<span class="text-muted">&mdash;</span>' ?>
                                </td>
                                <td>
                                    <?= !empty($row['payment_method']) ? '<span class="badge bg-light text-dark border">' . h($row['payment_method']) . '</span>' : '<span class="text-muted">&mdash;</span>' ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= !empty($row['remarks']) ? h($row['remarks']) : '&mdash;' ?></small>
                                </td>
                                <td class="text-end">
                                    <?php if ($row['status'] === 'paid'): ?>
                                        <a href="../fees/print_receipt.php?id=<?= (int)$row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-print me-1"></i> Receipt
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php
include '../layout_end.php';
?>
