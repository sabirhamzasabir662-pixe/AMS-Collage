<?php
/**
 * report.php
 * Arsalan Model School and College Management System
 * Comprehensive School Summary & Analytics Report
 */

require_once __DIR__ . '/database.php';
require_role(['admin']);

$page_title  = 'Summary Reports';
$active_menu = 'report';
$breadcrumb  = ['Reports'];
$depth       = '';

// ─── 1. DATE RANGE FILTER (For Attendance) ──────────────────────────────────
// Default to current month (first day of current month to today)
$default_start = date('Y-m-01');
$default_end   = date('Y-m-d');

$start_date = trim($_GET['start_date'] ?? '');
$end_date   = trim($_GET['end_date'] ?? '');

// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = $default_start;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = $default_end;
}
if ($start_date > $end_date) {
    // Swap if reversed
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// ─── 2. STUDENTS METRICS ────────────────────────────────────────────────────
// Total students
$students_total = (int)(db_fetch_one("SELECT COUNT(*) AS cnt FROM students")['cnt'] ?? 0);
// Count by status
$students_active = (int)(db_fetch_one("SELECT COUNT(*) AS cnt FROM students WHERE status = 'active'")['cnt'] ?? 0);
$students_inactive = (int)(db_fetch_one("SELECT COUNT(*) AS cnt FROM students WHERE status = 'inactive'")['cnt'] ?? 0);

// Count of students by class & section
$students_by_class = db_fetch_all(
    "SELECT c.id, c.class_name, c.section, c.class_teacher_name,
            COUNT(s.id) AS student_count,
            SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN s.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
     FROM classes c
     LEFT JOIN students s ON s.class_id = c.id
     GROUP BY c.id, c.class_name, c.section, c.class_teacher_name
     ORDER BY c.id ASC"
);

// ─── 3. FEES METRICS ────────────────────────────────────────────────────────
// Total collected (sum of Paid)
$fees_collected = (float)(db_fetch_one(
    "SELECT COALESCE(SUM(amount), 0) AS total FROM fees WHERE status = 'paid'"
)['total'] ?? 0.0);

// Total pending amount
$fees_pending = (float)(db_fetch_one(
    "SELECT COALESCE(SUM(amount), 0) AS total FROM fees WHERE status = 'pending'"
)['total'] ?? 0.0);

// Count of pending payments
$fees_pending_count = (int)(db_fetch_one(
    "SELECT COUNT(*) AS cnt FROM fees WHERE status = 'pending'"
)['cnt'] ?? 0);

// Count of paid payments
$fees_paid_count = (int)(db_fetch_one(
    "SELECT COUNT(*) AS cnt FROM fees WHERE status = 'paid'"
)['cnt'] ?? 0);

// ─── 4. ATTENDANCE METRICS ──────────────────────────────────────────────────
// Overall student attendance percentage for selectable date range
$att_summary = db_fetch_one(
    "SELECT 
        COUNT(*) AS total_records,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
     FROM student_attendance
     WHERE attendance_date BETWEEN ? AND ?",
    [$start_date, $end_date],
    'ss'
);

$att_total   = (int)($att_summary['total_records'] ?? 0);
$att_present = (int)($att_summary['present_count'] ?? 0);
$att_absent  = (int)($att_summary['absent_count'] ?? 0);
$att_percentage = ($att_total > 0) ? round(($att_present / $att_total) * 100, 1) : 0.0;

// Breakdown of attendance by class within selected date range
$att_by_class = db_fetch_all(
    "SELECT c.class_name, c.section,
            COUNT(sa.id) AS total_marked,
            SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) AS present_cnt,
            SUM(CASE WHEN sa.status = 'Absent' THEN 1 ELSE 0 END) AS absent_cnt
     FROM classes c
     JOIN student_attendance sa ON sa.class_id = c.id
     WHERE sa.attendance_date BETWEEN ? AND ?
     GROUP BY c.id, c.class_name, c.section
     ORDER BY c.id ASC",
    [$start_date, $end_date],
    'ss'
);

require_once __DIR__ . '/layout.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Summary Report</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <h2 class="h3 fw-bold text-dark mb-0">
                <i class="bi bi-bar-chart-line text-primary me-2"></i> Comprehensive School Report
            </h2>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                Live Overview
            </span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SECTION 1: TOP KPI CARDS (Matching dashboard.php Style)                   -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <!-- Total Students -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-uppercase text-muted fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Total Students</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($students_total); ?></h3>
                        <small class="text-muted">
                            <span class="text-success fw-semibold"><?= number_format($students_active); ?> active</span> &bull; 
                            <span class="text-secondary"><?= number_format($students_inactive); ?> inactive</span>
                        </small>
                    </div>
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fees Collected -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-success border-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-uppercase text-muted fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Fees Collected (Paid)</span>
                        <h3 class="fw-bold text-success mb-0 mt-1">Rs <?= number_format($fees_collected, 2); ?></h3>
                        <small class="text-muted">
                            <span class="badge bg-success-subtle text-success px-2 py-0"><?= number_format($fees_paid_count); ?> paid receipts</span>
                        </small>
                    </div>
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fees Pending -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-warning border-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-uppercase text-muted fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Pending Fees Amount</span>
                        <h3 class="fw-bold text-warning-emphasis mb-0 mt-1">Rs <?= number_format($fees_pending, 2); ?></h3>
                        <small class="text-muted">
                            <span class="badge bg-warning-subtle text-warning-emphasis px-2 py-0"><?= number_format($fees_pending_count); ?> pending invoices</span>
                        </small>
                    </div>
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Percentage -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-info border-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-uppercase text-muted fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Student Attendance</span>
                        <h3 class="fw-bold text-info-emphasis mb-0 mt-1"><?= $att_percentage; ?>%</h3>
                        <small class="text-muted">
                            <?= number_format($att_present); ?> present / <?= number_format($att_total); ?> marked
                        </small>
                    </div>
                    <div class="rounded-circle bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-calendar-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SECTION 2: ATTENDANCE DATE RANGE FILTER & SUMMARY                         -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="card-title fw-bold text-dark mb-0">
            <i class="bi bi-calendar-range text-info me-2"></i> Attendance Filter &amp; Analysis
        </h5>
        <div class="text-muted small">
            Current Filter: <strong><?= date('d M Y', strtotime($start_date)); ?></strong> to <strong><?= date('d M Y', strtotime($end_date)); ?></strong>
        </div>
    </div>
    <div class="card-body p-3 bg-light-subtle">
        <form method="GET" action="report.php" class="row g-3 align-items-end">
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label for="start_date" class="form-label small fw-semibold text-muted mb-1">From Date</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-calendar-event"></i></span>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= h($start_date); ?>" required>
                </div>
            </div>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label for="end_date" class="form-label small fw-semibold text-muted mb-1">To Date</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-calendar-event-fill"></i></span>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= h($end_date); ?>" required>
                </div>
            </div>
            <div class="col-sm-12 col-md-4 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">
                    <i class="bi bi-funnel me-1"></i> Filter Attendance
                </button>
                <a href="report.php" class="btn btn-sm btn-outline-secondary">
                    Reset (This Month)
                </a>
            </div>
        </form>
    </div>

    <!-- Attendance metrics row -->
    <div class="card-body p-3 border-top">
        <div class="row g-3 text-center">
            <div class="col-md-3 border-end">
                <div class="text-muted small">Total Attendance Logs</div>
                <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($att_total); ?></div>
            </div>
            <div class="col-md-3 border-end">
                <div class="text-muted small">Present Marks</div>
                <div class="fs-4 fw-bold text-success mt-1"><?= number_format($att_present); ?></div>
            </div>
            <div class="col-md-3 border-end">
                <div class="text-muted small">Absent Marks</div>
                <div class="fs-4 fw-bold text-danger mt-1"><?= number_format($att_absent); ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Overall Attendance Rate</div>
                <div class="fs-4 fw-bold text-primary mt-1"><?= $att_percentage; ?>%</div>
            </div>
        </div>

        <?php if (!empty($att_by_class)): ?>
            <div class="table-responsive mt-3 border rounded">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light small text-muted text-uppercase">
                        <tr>
                            <th class="ps-3 py-2">Class &amp; Section</th>
                            <th class="py-2 text-center">Total Marked</th>
                            <th class="py-2 text-center">Present</th>
                            <th class="py-2 text-center">Absent</th>
                            <th class="pe-3 py-2 text-end">Attendance Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($att_by_class as $row): 
                            $class_rate = ($row['total_marked'] > 0) ? round(($row['present_cnt'] / $row['total_marked']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-dark">
                                    <?= h($row['class_name']); ?> - Section <?= h($row['section']); ?>
                                </td>
                                <td class="text-center font-monospace"><?= (int)$row['total_marked']; ?></td>
                                <td class="text-center font-monospace text-success"><?= (int)$row['present_cnt']; ?></td>
                                <td class="text-center font-monospace text-danger"><?= (int)$row['absent_cnt']; ?></td>
                                <td class="pe-3 text-end">
                                    <span class="badge <?= ($class_rate >= 75) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'; ?> border px-2 py-1">
                                        <?= $class_rate; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted small py-3 mt-2 bg-light rounded">
                <i class="bi bi-info-circle me-1"></i> No student attendance records logged for the selected date range.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SECTION 3: STUDENTS BY CLASS & SECTION BREAKDOWN TABLE                    -->
<!-- ========================================================================= -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="card-title fw-bold text-dark mb-0">
            <i class="bi bi-building text-primary me-2"></i> Students Breakdown by Class &amp; Section
        </h5>
        <span class="badge bg-light text-dark border">
            Total Classes: <?= count($students_by_class); ?>
        </span>
    </div>
    <div class="table-responsive" style="max-height: 460px; overflow-y: auto;">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light sticky-top text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.03em;">
                <tr>
                    <th class="ps-3 py-3" style="width: 70px;">ID</th>
                    <th class="py-3">Class Name</th>
                    <th class="py-3 text-center" style="width: 100px;">Section</th>
                    <th class="py-3">Class Teacher</th>
                    <th class="py-3 text-center" style="width: 130px;">Active Students</th>
                    <th class="py-3 text-center" style="width: 140px;">Inactive Students</th>
                    <th class="pe-3 py-3 text-end" style="width: 130px;">Total Enrolled</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students_by_class)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No classes found in the system.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $sum_total = 0;
                    $sum_active = 0;
                    $sum_inactive = 0;
                    foreach ($students_by_class as $c): 
                        $sum_total += (int)$c['student_count'];
                        $sum_active += (int)$c['active_count'];
                        $sum_inactive += (int)$c['inactive_count'];
                    ?>
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-dark border font-monospace"><?= (int)$c['id']; ?></span>
                            </td>
                            <td>
                                <strong class="text-dark"><?= h($c['class_name']); ?></strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border px-2 py-1">
                                    Section <?= h($c['section']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($c['class_teacher_name'])): ?>
                                    <span class="text-dark small">
                                        <i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($c['class_teacher_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    <?= number_format((int)$c['active_count']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                    <?= number_format((int)$c['inactive_count']); ?>
                                </span>
                            </td>
                            <td class="pe-3 text-end">
                                <strong class="text-dark font-monospace"><?= number_format((int)$c['student_count']); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="4" class="ps-3 text-uppercase text-muted small">Total Summary Across All Classes:</td>
                    <td class="text-center text-success"><?= number_format($sum_active); ?></td>
                    <td class="text-center text-secondary"><?= number_format($sum_inactive); ?></td>
                    <td class="pe-3 text-end font-monospace"><?= number_format($sum_total); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/layout_end.php';
?>
