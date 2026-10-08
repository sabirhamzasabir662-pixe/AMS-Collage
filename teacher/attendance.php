<?php
/**
 * teacher/attendance.php — Teacher Mark Attendance Screen
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/teacher_helper.php';

require_login();
require_role(['teacher']);
require_permission('attendance.mark');

$user = current_user();

$page_title  = 'Mark Attendance';
$active_menu = 'teacher_attendance';
$breadcrumb  = ['Teacher Portal', 'Mark Attendance'];
$depth       = '../';

// Fetch only classes assigned to this teacher
$assigned_classes = get_teacher_assigned_classes($user['id']);

$selected_date  = trim($_GET['att_date'] ?? $_POST['att_date'] ?? date('Y-m-d'));
$selected_class = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$students_list  = [];
$class_info     = null;

// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// If class is selected, verify teacher assignment
if ($selected_class > 0) {
    if (!is_teacher_assigned_to_class($user['id'], $selected_class)) {
        set_flash('error', 'Access Denied: You are not authorized to mark attendance for this class.');
        redirect('attendance.php');
    }

    $class_info = db_fetch_one("SELECT id, class_name, section FROM classes WHERE id = ? LIMIT 1", 'i', [$selected_class]);

    if ($class_info) {
        $students_list = db_fetch_all(
            "SELECT s.id, s.student_id, s.first_name, s.last_name, s.gender,
                    COALESCE((SELECT sa.status FROM student_attendance sa 
                              WHERE sa.student_id = s.id AND sa.attendance_date = ? LIMIT 1), 'Present') AS att_status
             FROM students s
             WHERE s.class_id = ? AND s.status = 'active'
             ORDER BY s.first_name ASC, s.last_name ASC",
            'si',
            [$selected_date, $selected_class]
        );
    }
}

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $post_class_id = (int)($_POST['class_id'] ?? 0);
    $post_date     = trim($_POST['att_date'] ?? '');
    $statuses      = $_POST['att_status'] ?? [];

    // STRICT SERVER-SIDE CHECK
    if (!is_teacher_assigned_to_class($user['id'], $post_class_id)) {
        set_flash('error', 'Security Violation: You are not assigned to this class.');
        redirect('attendance.php');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post_date)) {
        $post_date = date('Y-m-d');
    }

    if (empty($statuses) || !is_array($statuses)) {
        set_flash('error', 'No attendance records submitted to save.');
        redirect("attendance.php?class_id={$post_class_id}&att_date=" . urlencode($post_date));
    }

    $saved_count = 0;
    foreach ($statuses as $stu_id => $status) {
        $stu_id = (int)$stu_id;
        if (!in_array($status, ['Present', 'Absent'], true)) continue;

        // Verify student belongs to this assigned class
        $stu = db_fetch_one("SELECT id, class_id FROM students WHERE id = ? AND class_id = ? LIMIT 1", 'ii', [$stu_id, $post_class_id]);
        if (!$stu) continue;

        db_execute(
            "INSERT INTO student_attendance (student_id, class_id, attendance_date, status)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = ?",
            'iisss',
            [$stu_id, $post_class_id, $post_date, $status, $status]
        );
        $saved_count++;
    }

    // Fetch class name for audit log
    $target_cls = db_fetch_one("SELECT class_name, section FROM classes WHERE id = ? LIMIT 1", 'i', [$post_class_id]);
    $cls_label = $target_cls ? ($target_cls['class_name'] . ' ' . $target_cls['section']) : "Class #{$post_class_id}";

    log_activity(
        (int)$user['id'],
        'teacher',
        'Marked attendance',
        'class',
        $post_class_id,
        "Marked attendance for {$cls_label} ({$saved_count} students) on {$post_date}."
    );

    set_flash('success', "Attendance successfully saved for {$cls_label} on " . date('d M Y', strtotime($post_date)) . " ({$saved_count} students marked).");
    redirect("attendance.php?class_id={$post_class_id}&att_date=" . urlencode($post_date));
}

include '../layout.php';
?>

<div class="row g-4">
    <!-- Left Filter Panel -->
    <div class="col-lg-3">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5 class="mb-0"><i class="fas fa-filter text-primary me-2"></i> Select Class &amp; Date</h5>
            </div>
            <div class="card-body-ams p-3">
                <form method="GET" action="attendance.php" id="loadAttendanceForm">
                    <div class="mb-3">
                        <label class="form-label-ams fw-semibold">Attendance Date</label>
                        <input type="date" name="att_date" class="form-control-ams"
                               value="<?= h($selected_date) ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-ams fw-semibold">Assigned Class</label>
                        <select name="class_id" class="form-control-ams" required>
                            <option value="">-- Choose Class --</option>
                            <?php foreach ($assigned_classes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $selected_class === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['class_name'] . ' - ' . $c['section']) ?> (<?= (int)$c['student_count'] ?> students)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" style="font-size: 0.75rem;">Only classes assigned to you are listed.</small>
                    </div>

                    <button type="submit" class="btn-ams btn-primary-ams w-100">
                        <i class="fas fa-users me-1"></i> Load Students
                    </button>
                </form>

                <?php if (!empty($assigned_classes)): ?>
                <div class="mt-4 pt-3 border-top">
                    <span class="small fw-bold text-muted text-uppercase d-block mb-2">Quick Navigation</span>
                    <div class="d-grid gap-1">
                        <?php foreach ($assigned_classes as $ac): ?>
                        <a href="attendance.php?class_id=<?= (int)$ac['id'] ?>&att_date=<?= urlencode($selected_date) ?>" 
                           class="btn btn-sm btn-outline-secondary text-start <?= $selected_class === (int)$ac['id'] ? 'active' : '' ?>">
                            <i class="fas fa-school me-1 text-primary"></i> <?= h($ac['class_name'] . ' - ' . $ac['section']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Attendance Panel -->
    <div class="col-lg-9">
        <?php if (!$selected_class): ?>
            <div class="card-ams">
                <div class="card-body-ams text-center py-5 text-muted">
                    <i class="fas fa-calendar-check fa-3x mb-3 text-secondary"></i>
                    <h5>No Class Selected</h5>
                    <p class="mb-0">Please pick one of your assigned classes and a date to take attendance.</p>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="attendance.php">
                <input type="hidden" name="class_id" value="<?= (int)$selected_class ?>">
                <input type="hidden" name="att_date" value="<?= h($selected_date) ?>">

                <div class="card-ams">
                    <div class="card-header-ams d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-clipboard-user text-primary me-2"></i>
                                Attendance: <?= h($class_info['class_name']) ?> (Section <?= h($class_info['section']) ?>)
                            </h5>
                            <small class="text-muted">
                                Date: <strong class="text-dark"><?= date('l, d M Y', strtotime($selected_date)) ?></strong>
                                &bull; Enrolled: <?= count($students_list) ?> student(s)
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm" id="btnMarkAllPresent">
                                <i class="fas fa-check-double me-1"></i> All Present
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="btnMarkAllAbsent">
                                <i class="fas fa-times me-1"></i> All Absent
                            </button>
                        </div>
                    </div>

                    <div class="card-body-ams p-0">
                        <?php if (empty($students_list)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-user-slash fa-2x mb-2 text-secondary"></i>
                                <p class="mb-0 fw-semibold">No active students enrolled in this class.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">#</th>
                                            <th>Student ID</th>
                                            <th>Student Name</th>
                                            <th style="width: 250px;" class="text-center">Attendance Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; foreach ($students_list as $s): ?>
                                        <tr>
                                            <td class="text-center text-muted fw-bold"><?= $i++ ?></td>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-dark font-monospace border">
                                                    <?= h($s['student_id']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= h($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group" aria-label="Attendance status">
                                                    <input type="radio" class="btn-check att-present" 
                                                           name="att_status[<?= (int)$s['id'] ?>]" 
                                                           id="present_<?= (int)$s['id'] ?>" 
                                                           value="Present" 
                                                           <?= $s['att_status'] !== 'Absent' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-success btn-sm px-3" for="present_<?= (int)$s['id'] ?>">
                                                        <i class="fas fa-check me-1"></i> Present
                                                    </label>

                                                    <input type="radio" class="btn-check att-absent" 
                                                           name="att_status[<?= (int)$s['id'] ?>]" 
                                                           id="absent_<?= (int)$s['id'] ?>" 
                                                           value="Absent"
                                                           <?= $s['att_status'] === 'Absent' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-danger btn-sm px-3" for="absent_<?= (int)$s['id'] ?>">
                                                        <i class="fas fa-times me-1"></i> Absent
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                                <span class="small text-muted">Review attendance marks before saving.</span>
                                <button type="submit" name="save_attendance" value="1" class="btn-ams btn-primary-ams px-4">
                                    <i class="fas fa-save me-1"></i> Save Attendance
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$extra_js = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnAllPresent = document.getElementById('btnMarkAllPresent');
    const btnAllAbsent = document.getElementById('btnMarkAllAbsent');

    if (btnAllPresent) {
        btnAllPresent.addEventListener('click', () => {
            document.querySelectorAll('.att-present').forEach(radio => radio.checked = true);
        });
    }

    if (btnAllAbsent) {
        btnAllAbsent.addEventListener('click', () => {
            document.querySelectorAll('.att-absent').forEach(radio => radio.checked = true);
        });
    }
});
</script>
HTML;

include '../layout_end.php';
?>
