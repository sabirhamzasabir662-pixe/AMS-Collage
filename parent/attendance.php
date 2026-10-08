<?php
/**
 * parent/attendance.php — Child Attendance History
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/parent_helper.php';

require_login();
require_role(['parent']);
require_permission('attendance.view');

$user = current_user();
$student = get_parent_student($user['id']);

$page_title  = 'Child Attendance';
$active_menu = 'parent_attendance';
$breadcrumb  = ['Parent Portal', 'Attendance'];
$depth       = '../';

$month_filter = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');

$attendance_list = [];
$total_present = 0;
$total_absent  = 0;
$total_days    = 0;
$pct           = 0;

if ($student) {
    $stu_id = (int)$student['id'];

    // Query attendance records for the selected month or all
    if ($month_filter && preg_match('/^\d{4}-\d{2}$/', $month_filter)) {
        $attendance_list = db_fetch_all(
            "SELECT * FROM student_attendance 
             WHERE student_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ? 
             ORDER BY attendance_date DESC",
            'is',
            [$stu_id, $month_filter]
        );
    } else {
        $attendance_list = db_fetch_all(
            "SELECT * FROM student_attendance 
             WHERE student_id = ? 
             ORDER BY attendance_date DESC",
            'i',
            [$stu_id]
        );
    }

    foreach ($attendance_list as $row) {
        if ($row['status'] === 'Present') $total_present++;
        if ($row['status'] === 'Absent')  $total_absent++;
    }
    $total_days = count($attendance_list);
    if ($total_days > 0) {
        $pct = round(($total_present / $total_days) * 100, 1);
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

    <!-- Student Header Summary -->
    <div class="card-ams mb-4">
        <div class="card-body-ams d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-navy mb-1">
                    <i class="fas fa-calendar-check me-2 text-success"></i>Attendance Record
                </h4>
                <div class="text-muted small">
                    Student: <strong class="text-dark"><?= h($student['first_name'] . ' ' . $student['last_name']) ?></strong> 
                    &bull; Roll No: <strong><?= h($student['student_id']) ?></strong> 
                    &bull; Class: <strong><?= h($student['class_name'] ?? 'Unassigned') ?><?= !empty($student['section']) ? ' (' . h($student['section']) . ')' : '' ?></strong>
                </div>
            </div>
            <!-- Month Filter Form -->
            <form method="GET" class="d-flex align-items-center gap-2">
                <label for="month" class="form-label mb-0 small text-muted fw-semibold">Month:</label>
                <input type="month" name="month" id="month" class="form-control form-control-sm" value="<?= h($month_filter) ?>">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="attendance.php?month=" class="btn btn-outline-secondary btn-sm">Show All</a>
            </form>
        </div>
    </div>

    <!-- Attendance Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Total Sessions</div>
                <h3 class="fw-bold mb-0 text-navy"><?= $total_days ?></h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Present Days</div>
                <h3 class="fw-bold mb-0 text-success"><?= $total_present ?></h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Absent Days</div>
                <h3 class="fw-bold mb-0 text-danger"><?= $total_absent ?></h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Attendance Rate</div>
                <h3 class="fw-bold mb-0 text-primary"><?= $pct ?>%</h3>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="card-ams">
        <div class="card-header-ams d-flex justify-content-between align-items-center">
            <span class="fw-bold text-navy">
                <i class="fas fa-list me-2 text-primary"></i>Daily Attendance Breakdown
            </span>
            <span class="badge bg-secondary-subtle text-dark border">
                <?= $month_filter ? date('F Y', strtotime($month_filter . '-01')) : 'All Time' ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">#</th>
                        <th>Date</th>
                        <th>Day of Week</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance_list)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                <i class="fas fa-calendar-times fa-2x mb-2 text-secondary"></i><br>
                                No attendance records found for the selected period.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $idx = 1; foreach ($attendance_list as $row): ?>
                            <tr>
                                <td class="text-muted"><?= $idx++ ?></td>
                                <td>
                                    <strong><?= date('M d, Y', strtotime($row['attendance_date'])) ?></strong>
                                </td>
                                <td class="text-muted">
                                    <?= date('l', strtotime($row['attendance_date'])) ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Present'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Present</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Absent</span>
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
