<?php
/**
 * parent/parent_dashboard.php — Parent Portal Dashboard
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/parent_helper.php';

require_login();
require_role(['parent']);

$user = current_user();
$student = get_parent_student($user['id']);

$page_title  = 'Parent Dashboard';
$active_menu = 'parent_dashboard';
$breadcrumb  = ['Parent Portal', 'Dashboard'];
$depth       = '../';

// Compute metrics if student linked
$attendance_stats = ['total' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0];
$marks_stats      = ['total_marks' => 0, 'obtained' => 0, 'avg_pct' => 0, 'count' => 0];
$fee_stats        = ['invoiced' => 0, 'paid' => 0, 'pending' => 0];

$recent_attendance = [];
$recent_marks      = [];
$recent_fees       = [];

if ($student) {
    $stu_id = (int)$student['id'];

    // 1. Attendance Metrics
    $att_rows = db_fetch_all(
        "SELECT status, COUNT(*) as cnt FROM student_attendance WHERE student_id = ? GROUP BY status",
        'i',
        [$stu_id]
    );
    foreach ($att_rows as $r) {
        if ($r['status'] === 'Present') $attendance_stats['present'] = (int)$r['cnt'];
        if ($r['status'] === 'Absent')  $attendance_stats['absent']  = (int)$r['cnt'];
    }
    $attendance_stats['total'] = $attendance_stats['present'] + $attendance_stats['absent'];
    if ($attendance_stats['total'] > 0) {
        $attendance_stats['percentage'] = round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 1);
    }

    $recent_attendance = db_fetch_all(
        "SELECT attendance_date, status FROM student_attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 5",
        'i',
        [$stu_id]
    );

    // 2. Marks Metrics
    $marks_agg = db_fetch_one(
        "SELECT COUNT(*) as count, COALESCE(SUM(marks_obtained), 0) as total_obtained, COALESCE(SUM(total_marks), 0) as total_possible 
         FROM marks WHERE student_id = ?",
        'i',
        [$stu_id]
    );
    if ($marks_agg && (float)$marks_agg['total_possible'] > 0) {
        $marks_stats['count'] = (int)$marks_agg['count'];
        $marks_stats['obtained'] = (float)$marks_agg['total_obtained'];
        $marks_stats['total_marks'] = (float)$marks_agg['total_possible'];
        $marks_stats['avg_pct'] = round(($marks_stats['obtained'] / $marks_stats['total_marks']) * 100, 1);
    }

    $recent_marks = db_fetch_all(
        "SELECT m.*, sub.subject_name, sub.subject_code 
         FROM marks m 
         JOIN subjects sub ON m.subject_id = sub.id 
         WHERE m.student_id = ? 
         ORDER BY m.created_at DESC LIMIT 5",
        'i',
        [$stu_id]
    );

    // 3. Fee Metrics
    $fee_rows = db_fetch_all(
        "SELECT status, SUM(amount) as total FROM fees WHERE student_id = ? GROUP BY status",
        'i',
        [$stu_id]
    );
    foreach ($fee_rows as $f) {
        if ($f['status'] === 'paid')    $fee_stats['paid']    = (float)$f['total'];
        if ($f['status'] === 'pending') $fee_stats['pending'] = (float)$f['total'];
    }
    $fee_stats['invoiced'] = $fee_stats['paid'] + $fee_stats['pending'];

    $recent_fees = db_fetch_all(
        "SELECT * FROM fees WHERE student_id = ? ORDER BY id DESC LIMIT 5",
        'i',
        [$stu_id]
    );
}

include '../layout.php';
?>

<?php if (!$student): ?>
    <div class="card-ams p-5 text-center mb-4">
        <div class="mb-3 text-warning">
            <i class="fas fa-exclamation-triangle fa-3x"></i>
        </div>
        <h4 class="fw-bold text-navy">No Student Linked</h4>
        <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
            This parent account is not currently linked to an active student record. 
            Please contact the school administration to map your account to your child's student profile.
        </p>
        <a href="<?= $depth ?>profile.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-user-circle me-1"></i> View Account Profile
        </a>
    </div>
<?php else: ?>

    <!-- Student Header Banner -->
    <div class="card-ams mb-4" style="background: linear-gradient(135deg, #0f5132 0%, #198754 100%); color: #fff;">
        <div class="card-body-ams p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center fw-bold fs-3 shadow-sm" style="width: 65px; height: 65px;">
                        <?php if (!empty($student['photo']) && file_exists('../uploads/students/' . $student['photo'])): ?>
                            <img src="../uploads/students/<?= h($student['photo']) ?>" alt="Photo" class="rounded-circle w-100 h-100 object-fit-cover">
                        <?php else: ?>
                            <i class="fas fa-user-graduate"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="badge bg-white text-success fw-bold text-uppercase px-3 py-1 mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="fas fa-shield-alt me-1"></i> Parent Portal
                        </span>
                        <h2 class="h3 fw-bold mb-1">
                            <?= h($student['first_name'] . ' ' . $student['last_name']) ?>
                        </h2>
                        <div class="d-flex flex-wrap gap-2 text-white-50 small">
                            <span><i class="fas fa-id-card me-1"></i> Roll No: <strong><?= h($student['student_id']) ?></strong></span>
                            <span>&bull;</span>
                            <span><i class="fas fa-chalkboard me-1"></i> Class: <strong><?= h($student['class_name'] ?? 'Unassigned') ?><?= !empty($student['section']) ? ' (' . h($student['section']) . ')' : '' ?></strong></span>
                            <span>&bull;</span>
                            <span><i class="fas fa-user-tie me-1"></i> Guardian: <strong><?= h($student['father_name']) ?></strong></span>
                        </div>
                    </div>
                </div>
                <div>
                    <span class="badge bg-light text-dark px-3 py-2 fs-6">
                        Status: <span class="text-success fw-bold text-capitalize"><?= h($student['status']) ?></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- Attendance Card -->
        <div class="col-md-4">
            <div class="card-ams h-100 p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Attendance Rate</div>
                        <h3 class="fw-bold mb-0 text-navy"><?= $attendance_stats['percentage'] ?>%</h3>
                    </div>
                    <div class="rounded p-2 bg-success-subtle text-success">
                        <i class="fas fa-calendar-check fa-lg"></i>
                    </div>
                </div>
                <div class="progress mb-2" style="height: 6px;">
                    <div class="progress-bar bg-success" style="width: <?= min(100, $attendance_stats['percentage']) ?>%;"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Present: <strong><?= $attendance_stats['present'] ?></strong></span>
                    <span>Absent: <strong><?= $attendance_stats['absent'] ?></strong></span>
                    <span>Total: <strong><?= $attendance_stats['total'] ?></strong></span>
                </div>
                <div class="mt-3 pt-2 border-top">
                    <a href="attendance.php" class="text-decoration-none small text-primary fw-semibold">
                        View Detailed Log <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Academic Marks Card -->
        <div class="col-md-4">
            <div class="card-ams h-100 p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Academic Score</div>
                        <h3 class="fw-bold mb-0 text-navy">
                            <?= $marks_stats['count'] > 0 ? $marks_stats['avg_pct'] . '%' : 'N/A' ?>
                        </h3>
                    </div>
                    <div class="rounded p-2 bg-primary-subtle text-primary">
                        <i class="fas fa-award fa-lg"></i>
                    </div>
                </div>
                <div class="progress mb-2" style="height: 6px;">
                    <div class="progress-bar bg-primary" style="width: <?= min(100, $marks_stats['avg_pct']) ?>%;"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Evaluated Subjects: <strong><?= $marks_stats['count'] ?></strong></span>
                    <span>Total Marks: <strong><?= $marks_stats['obtained'] ?> / <?= $marks_stats['total_marks'] ?></strong></span>
                </div>
                <div class="mt-3 pt-2 border-top">
                    <a href="results.php" class="text-decoration-none small text-primary fw-semibold">
                        View Term Results <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Fees Card -->
        <div class="col-md-4">
            <div class="card-ams h-100 p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Fee Status</div>
                        <h3 class="fw-bold mb-0 <?= $fee_stats['pending'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $fee_stats['pending'] > 0 ? 'Rs. ' . number_format($fee_stats['pending']) . ' Due' : 'Cleared' ?>
                        </h3>
                    </div>
                    <div class="rounded p-2 <?= $fee_stats['pending'] > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>">
                        <i class="fas fa-receipt fa-lg"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>Total Invoiced: <strong>Rs. <?= number_format($fee_stats['invoiced']) ?></strong></span>
                    <span>Paid: <strong class="text-success">Rs. <?= number_format($fee_stats['paid']) ?></strong></span>
                </div>
                <div class="mt-auto pt-2 border-top">
                    <a href="fees.php" class="text-decoration-none small text-primary fw-semibold">
                        View Fee Ledger <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Shortcuts -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="attendance.php" class="card-ams p-3 text-center text-decoration-none d-block hover-lift">
                <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                <div class="fw-bold text-dark">Attendance</div>
                <small class="text-muted">Monthly presence</small>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="results.php" class="card-ams p-3 text-center text-decoration-none d-block hover-lift">
                <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                <div class="fw-bold text-dark">Exam Results</div>
                <small class="text-muted">Marks &amp; grades</small>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="fees.php" class="card-ams p-3 text-center text-decoration-none d-block hover-lift">
                <i class="fas fa-file-invoice-dollar fa-2x text-warning mb-2"></i>
                <div class="fw-bold text-dark">Fee Records</div>
                <small class="text-muted">Invoices &amp; receipts</small>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="profile.php" class="card-ams p-3 text-center text-decoration-none d-block hover-lift">
                <i class="fas fa-id-card fa-2x text-info mb-2"></i>
                <div class="fw-bold text-dark">Child Profile</div>
                <small class="text-muted">Enrolled details</small>
            </a>
        </div>
    </div>

    <!-- Recent Activity Tables -->
    <div class="row g-3">
        <!-- Recent Attendance -->
        <div class="col-md-6">
            <div class="card-ams h-100">
                <div class="card-header-ams d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-navy"><i class="fas fa-calendar-day me-2 text-success"></i>Recent Attendance</span>
                    <a href="attendance.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_attendance)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No recent attendance recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_attendance as $att): ?>
                                    <tr>
                                        <td><strong><?= date('M d, Y', strtotime($att['attendance_date'])) ?></strong></td>
                                        <td class="text-muted"><?= date('l', strtotime($att['attendance_date'])) ?></td>
                                        <td>
                                            <?php if ($att['status'] === 'Present'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Present</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Absent</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Marks -->
        <div class="col-md-6">
            <div class="card-ams h-100">
                <div class="card-header-ams d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-navy"><i class="fas fa-award me-2 text-primary"></i>Recent Exam Marks</span>
                    <a href="results.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Term</th>
                                <th>Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_marks)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No marks entered yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_marks as $m): 
                                    $pct = round(($m['marks_obtained'] / $m['total_marks']) * 100, 1);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= h($m['subject_name']) ?></div>
                                            <small class="text-muted"><?= h($m['subject_code']) ?></small>
                                        </td>
                                        <td><span class="badge bg-secondary-subtle text-dark"><?= h($m['term']) ?></span></td>
                                        <td>
                                            <strong><?= number_format($m['marks_obtained'], 1) ?></strong> / <?= number_format($m['total_marks'], 0) ?>
                                            <span class="badge bg-primary-subtle text-primary ms-1"><?= $pct ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php
include '../layout_end.php';
?>
