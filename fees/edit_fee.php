<?php
/**
 * fees/edit_fee.php — Edit Fee Record
 * Arsalan Model School and College Management System
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/fees_helper.php';
require_login();
require_permission('fees.edit');
$user = current_user();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    set_flash('error', 'Invalid fee record ID.');
    redirect('fee_records.php');
}

// Fetch existing fee record using prepared statement
$fee = fee_db_fetch_one(
    "SELECT f.*, s.student_id AS stu_code, s.first_name, s.last_name, c.class_name, c.section
     FROM fees f
     JOIN students s ON f.student_id = s.id
     LEFT JOIN classes c ON f.class_id = c.id
     WHERE f.id = ?",
    [$id],
    'i'
);

if (!$fee) {
    set_flash('error', 'Fee record not found.');
    redirect('fee_records.php');
}

$page_title  = 'Edit Fee Record #' . $fee['id'];
$active_menu = 'fees_list';
$breadcrumb  = ['Fees', 'Edit Fee'];
$depth       = '../';

// Fetch active students (or the current student if inactive)
$students = fee_db_fetch_all(
    "SELECT s.id, s.student_id, s.first_name, s.last_name, c.id AS class_id, c.class_name, c.section
     FROM students s
     LEFT JOIN classes c ON s.class_id = c.id
     WHERE s.status = 'active' OR s.id = ?
     ORDER BY s.student_id ASC, s.first_name ASC",
    [$fee['student_id']],
    'i'
);

$months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

$current_year = (int)date('Y');
$years        = range($current_year - 2, $current_year + 3);

// Parse existing fee_month e.g. "September 2026"
$existing_month = '';
$existing_year  = $current_year;
if (!empty($fee['fee_month'])) {
    $parts = explode(' ', trim($fee['fee_month']));
    if (isset($parts[0])) $existing_month = $parts[0];
    if (isset($parts[1]) && is_numeric($parts[1])) $existing_year = (int)$parts[1];
}

$errors = [];
$form   = [
    'student_id'     => $fee['student_id'],
    'class_id'       => $fee['class_id'],
    'fee_month'      => $existing_month ?: date('F'),
    'fee_year'       => $existing_year,
    'amount'         => $fee['amount'],
    'payment_date'   => $fee['payment_date'] ?? '',
    'payment_method' => $fee['payment_method'] ?? 'Cash',
    'remarks'        => $fee['remarks'] ?? '',
    'status'         => $fee['status'] ?? 'paid',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['student_id']     = (int)($_POST['student_id'] ?? 0);
    $form['class_id']       = (int)($_POST['class_id'] ?? 0);
    $month_input            = trim($_POST['fee_month'] ?? '');
    $year_input             = trim($_POST['fee_year'] ?? '');
    $form['fee_month']      = $month_input;
    $form['fee_year']       = $year_input;
    $form['amount']         = trim($_POST['amount'] ?? '');
    $form['payment_date']   = trim($_POST['payment_date'] ?? '');
    $form['payment_method'] = trim($_POST['payment_method'] ?? 'Cash');
    $form['remarks']        = trim($_POST['remarks'] ?? '');
    $form['status']         = trim($_POST['status'] ?? 'paid');

    if (!$form['student_id']) {
        $errors[] = 'Please select a student.';
    }

    if ($form['student_id']) {
        $student_info = fee_db_fetch_one("SELECT id, class_id FROM students WHERE id = ?", [$form['student_id']], 'i');
        if (!$student_info) {
            $errors[] = 'Selected student does not exist.';
        } else {
            $form['class_id'] = (int)$student_info['class_id'];
        }
    }

    if ($month_input === '' || !in_array($month_input, $months, true)) {
        $errors[] = 'Please select a valid fee month.';
    }

    if ($year_input === '' || !is_numeric($year_input)) {
        $errors[] = 'Please enter or select a valid fee year.';
    }

    $combined_fee_month = $month_input . ' ' . $year_input;

    if ($form['amount'] === '' || !is_numeric($form['amount']) || (float)$form['amount'] <= 0) {
        $errors[] = 'Amount must be a positive number.';
    }

    if (!in_array($form['payment_method'], ['Cash', 'Bank', 'Online'], true)) {
        $form['payment_method'] = 'Cash';
    }

    if (!in_array($form['status'], ['paid', 'pending'], true)) {
        $form['status'] = 'paid';
    }

    $payment_date = null;
    if ($form['status'] === 'paid') {
        $payment_date = !empty($form['payment_date']) ? $form['payment_date'] : date('Y-m-d');
    } else {
        $payment_date = !empty($form['payment_date']) ? $form['payment_date'] : null;
    }

    if (empty($errors)) {
        fee_db_execute(
            "UPDATE fees
             SET student_id = ?, class_id = ?, fee_month = ?, amount = ?, payment_date = ?, payment_method = ?, remarks = ?, status = ?
             WHERE id = ?",
            [
                $form['student_id'],
                $form['class_id'],
                $combined_fee_month,
                (float)$form['amount'],
                $payment_date,
                $form['payment_method'],
                $form['remarks'],
                $form['status'],
                $id
            ],
            'iisdssssi'
        );

        set_flash('success', 'Fee record updated successfully!');
        redirect('fee_records.php');
    }
}

include '../layout.php';
?>

<!-- Link Bootstrap Icons if not in layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-primary fw-bold">
            <i class="bi bi-pencil-square me-2 text-warning"></i> Edit Fee Record #<?= (int)$fee['id'] ?>
        </h5>
        <div class="d-flex gap-2">
            <a href="print_receipt.php?id=<?= (int)$fee['id'] ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Print Receipt
            </a>
            <a href="fee_records.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Records
            </a>
        </div>
    </div>
    <div class="card-body p-4">

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="edit_fee.php?id=<?= $id ?>" id="feeForm">
            <input type="hidden" name="id" value="<?= $id ?>">

            <!-- ── SECTION 1: STUDENT SELECTION ── -->
            <h6 class="text-uppercase text-secondary fw-bold mb-3 pb-2 border-bottom">
                <i class="bi bi-person-fill me-1 text-primary"></i> Student Selection
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <label class="form-label fw-semibold" for="feeStudentSelect">
                        Select Student <span class="text-danger">*</span>
                    </label>
                    <select name="student_id" class="form-select" required id="feeStudentSelect" onchange="fillStudentInfo(this)">
                        <option value="">-- Select Student (ID - Full Name - Class - Section) --</option>
                        <?php foreach ($students as $s): ?>
                        <?php
                            $student_display = $s['student_id'] . ' — ' . $s['first_name'] . ' ' . $s['last_name'] . ' — ' . ($s['class_name'] ?? 'No Class') . ($s['section'] ? ' (Sec ' . $s['section'] . ')' : '');
                            $class_display   = ($s['class_name'] ?? 'No Class') . ($s['section'] ? ' - ' . $s['section'] : '');
                        ?>
                        <option value="<?= $s['id'] ?>"
                                data-student-code="<?= h($s['student_id']) ?>"
                                data-class-id="<?= (int)$s['class_id'] ?>"
                                data-class-name="<?= h($class_display) ?>"
                                <?= ($form['student_id'] == $s['id']) ? 'selected' : '' ?>>
                            <?= h($student_display) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Student ID (Auto-filled)</label>
                    <input type="text" id="studentIdDisplay" class="form-control bg-light" readonly
                           placeholder="Select student above" style="cursor: not-allowed;">
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold">Class &amp; Section (Auto-filled)</label>
                    <input type="text" id="classDisplay" class="form-control bg-light" readonly
                           placeholder="Select student above" style="cursor: not-allowed;">
                    <input type="hidden" name="class_id" id="classIdHidden" value="<?= h($form['class_id']) ?>">
                </div>
            </div>

            <!-- ── SECTION 2: FEE DETAILS ── -->
            <h6 class="text-uppercase text-secondary fw-bold mb-3 pb-2 border-bottom">
                <i class="bi bi-receipt me-1 text-primary"></i> Fee Payment Details
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fee Month <span class="text-danger">*</span></label>
                    <select name="fee_month" class="form-select" required id="feeMonthSelect">
                        <option value="">-- Select Month --</option>
                        <?php foreach ($months as $m): ?>
                        <option value="<?= $m ?>" <?= ($form['fee_month'] === $m) ? 'selected' : '' ?>>
                            <?= $m ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fee Year <span class="text-danger">*</span></label>
                    <select name="fee_year" class="form-select" required id="feeYearSelect">
                        <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= ((string)$form['fee_year'] === (string)$y) ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Amount (Rs.) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" required
                           min="1" step="0.01"
                           value="<?= h($form['amount']) ?>" placeholder="e.g. 3500">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Payment Date</label>
                    <input type="date" name="payment_date" id="paymentDateInput" class="form-control"
                           value="<?= h($form['payment_date']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="Cash"   <?= ($form['payment_method'] === 'Cash') ? 'selected' : '' ?>>Cash</option>
                        <option value="Bank"   <?= ($form['payment_method'] === 'Bank') ? 'selected' : '' ?>>Bank</option>
                        <option value="Online" <?= ($form['payment_method'] === 'Online') ? 'selected' : '' ?>>Online</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required id="feeStatusSelect">
                        <option value="paid"    <?= ($form['status'] === 'paid') ? 'selected' : '' ?>>Paid</option>
                        <option value="pending" <?= ($form['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold">Remarks (Optional)</label>
                    <textarea name="remarks" class="form-control" rows="2"
                              placeholder="e.g. Monthly Tuition fee, Admission fee, Challan number, Easypaisa TID..."><?= h($form['remarks']) ?></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 mt-4 flex-wrap">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i> Update Fee Record
                </button>
                <a href="fee_records.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function fillStudentInfo(sel) {
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('studentIdDisplay').value = opt.dataset.studentCode || '';
        document.getElementById('classDisplay').value     = opt.dataset.className || '';
        document.getElementById('classIdHidden').value    = opt.dataset.classId || '';
    } else {
        document.getElementById('studentIdDisplay').value = '';
        document.getElementById('classDisplay').value     = '';
        document.getElementById('classIdHidden').value    = '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('feeStudentSelect');
    if (sel && sel.value) {
        fillStudentInfo(sel);
    }
});
</script>

<?php include '../layout_end.php'; ?>
