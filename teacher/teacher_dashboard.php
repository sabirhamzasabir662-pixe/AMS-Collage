<?php
/**
 * teacher/teacher_dashboard.php — Teacher Portal Dashboard
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/teacher_helper.php';

require_login();
require_role(['teacher']);

$user = current_user();
$teacher = get_teacher_record($user['id']);

$page_title  = 'Teacher Dashboard';
$active_menu = 'teacher_dashboard';
$breadcrumb  = ['Teacher Portal', 'Dashboard'];
$depth       = '../';

// Fetch assigned classes with student count
$assigned_classes = get_teacher_assigned_classes($user['id']);
$total_classes    = count($assigned_classes);
$total_students   = array_sum(array_column($assigned_classes, 'student_count'));

// Attendance stats for today across assigned classes
$today = date('Y-m-d');
$assigned_class_ids = array_column($assigned_classes, 'id');

$today_marked = 0;
$today_present = 0;
if (!empty($assigned_class_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($assigned_class_ids), '?'));
    $types = 's' . str_repeat('i', count($assigned_class_ids));
    $params = array_merge([$today], $assigned_class_ids);

    $att_row = db_fetch_one(
        "SELECT COUNT(*) AS total_marked,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_count
         FROM student_attendance
         WHERE attendance_date = ? AND class_id IN ($in_placeholders)",
        $types,
        $params
    );
    $today_marked = (int)($att_row['total_marked'] ?? 0);
    $today_present = (int)($att_row['present_count'] ?? 0);
}

include '../layout.php';
?>

<!-- Welcome Banner -->
<div class="card-ams mb-4" style="background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #fff;">
    <div class="card-body-ams p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="badge bg-white text-primary fw-bold text-uppercase px-3 py-1 mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <i class="fas fa-id-badge me-1"></i> Teacher Portal
                </span>
                <h2 class="h3 fw-bold mb-1">
                    Welcome back, <?= h($teacher ? ($teacher['first_name'] . ' ' . $teacher['last_name']) : $user['username']) ?>!
                </h2>
                <p class="mb-0 opacity-75">
                    <i class="fas fa-graduation-cap me-1"></i>
                    <?= h($teacher['qualification'] ?? 'Faculty Member') ?> &bull; 
                    Teacher ID: <span class="fw-semibold font-monospace"><?= h($teacher['teacher_id'] ?? 'N/A') ?></span>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="attendance.php" class="btn btn-light fw-semibold text-primary px-3 shadow-sm">
                    <i class="fas fa-calendar-check me-1"></i> Mark Attendance
                </a>
                <a href="marks.php" class="btn btn-outline-light fw-semibold px-3">
                    <i class="fas fa-file-signature me-1"></i> Enter Marks
                </a>
            </div>
        </div>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="card-ams border-start border-primary border-4 h-100">
            <div class="card-body-ams d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-uppercase text-muted fw-bold small">Assigned Classes</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($total_classes) ?></h3>
                    <small class="text-muted">Active classes assigned to you</small>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 1.4rem;">
                    <i class="fas fa-chalkboard"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-4">
        <div class="card-ams border-start border-success border-4 h-100">
            <div class="card-body-ams d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-uppercase text-muted fw-bold small">Enrolled Students</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($total_students) ?></h3>
                    <small class="text-muted">Total active students in your classes</small>
                </div>
                <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 1.4rem;">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-12 col-lg-4">
        <div class="card-ams border-start border-info border-4 h-100">
            <div class="card-body-ams d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="text-uppercase text-muted fw-bold small">Today's Attendance</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($today_marked) ?> <span class="fs-6 fw-normal text-muted">marked</span></h3>
                    <small class="text-muted"><?= number_format($today_present) ?> present today</small>
                </div>
                <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 1.4rem;">
                    <i class="fas fa-clipboard-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assigned Classes Section -->
<div class="card-ams">
    <div class="card-header-ams d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-school text-primary me-2"></i> My Assigned Classes</h5>
        <span class="badge bg-primary rounded-pill"><?= $total_classes ?> Assigned</span>
    </div>
    <div class="card-body-ams p-3">
        <?php if (empty($assigned_classes)): ?>
            <div class="alert alert-warning mb-0 text-center py-4">
                <i class="fas fa-exclamation-circle fa-2x mb-2 text-warning"></i>
                <p class="mb-0 fw-semibold">No classes are currently assigned to your account.</p>
                <small class="text-muted">Please contact the school administrator to configure your class assignments.</small>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($assigned_classes as $cls): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 border shadow-sm rounded-3">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="fw-bold text-dark mb-0"><?= h($cls['class_name']) ?></h5>
                                    <span class="badge bg-secondary-subtle text-dark border">Section: <?= h($cls['section']) ?></span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary px-2 py-1">
                                    <i class="fas fa-users me-1"></i> <?= (int)$cls['student_count'] ?> Students
                                </span>
                            </div>

                            <p class="small text-muted mb-3">
                                <i class="fas fa-door-open me-1"></i> Room: <?= h($cls['room_number'] ?: 'Not Assigned') ?>
                            </p>

                            <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                                <a href="my_classes.php?class_id=<?= (int)$cls['id'] ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                                    <i class="fas fa-list me-1"></i> Roster
                                </a>
                                <a href="attendance.php?class_id=<?= (int)$cls['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                    <i class="fas fa-calendar-check me-1"></i> Attendance
                                </a>
                                <a href="marks.php?class_id=<?= (int)$cls['id'] ?>" class="btn btn-outline-success btn-sm flex-fill">
                                    <i class="fas fa-file-signature me-1"></i> Marks
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include '../layout_end.php';
?>
