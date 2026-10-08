<?php
/**
 * teacher/my_classes.php — My Assigned Classes & Student Rosters
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/teacher_helper.php';

require_login();
require_role(['teacher']);
require_permission('classes.view');

$user = current_user();

$page_title  = 'My Classes';
$active_menu = 'teacher_classes';
$breadcrumb  = ['Teacher Portal', 'My Classes'];
$depth       = '../';

// Fetch all assigned classes
$assigned_classes = get_teacher_assigned_classes($user['id']);

// Selected class
$selected_class_id = (int)($_GET['class_id'] ?? ($assigned_classes[0]['id'] ?? 0));
$selected_class = null;
$students_roster = [];

if ($selected_class_id > 0) {
    // STRICT SECURITY CHECK: verify class belongs to this teacher
    if (!is_teacher_assigned_to_class($user['id'], $selected_class_id)) {
        set_flash('error', 'Access Denied: You are not assigned to that class.');
        redirect('my_classes.php');
    }

    $selected_class = db_fetch_one(
        "SELECT id, class_name, section, room_number FROM classes WHERE id = ? LIMIT 1",
        'i',
        [$selected_class_id]
    );

    if ($selected_class) {
        $students_roster = db_fetch_all(
            "SELECT id, student_id, first_name, last_name, father_name, gender, date_of_birth, phone, email, photo, status
             FROM students
             WHERE class_id = ? AND status = 'active'
             ORDER BY first_name ASC, last_name ASC",
            'i',
            [$selected_class_id]
        );
    }
}

include '../layout.php';
?>

<div class="row g-4">
    <!-- Left Column: Assigned Classes List -->
    <div class="col-lg-4">
        <div class="card-ams">
            <div class="card-header-ams d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chalkboard text-primary me-2"></i> Assigned Classes</h5>
                <span class="badge bg-primary"><?= count($assigned_classes) ?></span>
            </div>
            <div class="card-body-ams p-2">
                <?php if (empty($assigned_classes)): ?>
                    <div class="p-3 text-center text-muted">
                        <i class="fas fa-info-circle mb-1"></i> No classes assigned to you.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($assigned_classes as $cls): ?>
                        <?php $is_active = ($cls['id'] === $selected_class_id); ?>
                        <a href="my_classes.php?class_id=<?= (int)$cls['id'] ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center rounded-2 mb-1 <?= $is_active ? 'active' : '' ?>">
                            <div>
                                <div class="fw-bold"><?= h($cls['class_name']) ?> — Section <?= h($cls['section']) ?></div>
                                <small class="<?= $is_active ? 'text-white-50' : 'text-muted' ?>">
                                    <i class="fas fa-door-open me-1"></i> Room: <?= h($cls['room_number'] ?: 'N/A') ?>
                                </small>
                            </div>
                            <span class="badge <?= $is_active ? 'bg-light text-primary' : 'bg-primary-subtle text-primary' ?> rounded-pill">
                                <?= (int)$cls['student_count'] ?> Students
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Student Roster for Selected Class -->
    <div class="col-lg-8">
        <?php if (!$selected_class): ?>
            <div class="card-ams">
                <div class="card-body-ams text-center py-5 text-muted">
                    <i class="fas fa-chalkboard-teacher fa-3x mb-3 text-secondary"></i>
                    <h5>No Class Selected</h5>
                    <p class="mb-0">Please select an assigned class from the left panel to view its student roster.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card-ams">
                <div class="card-header-ams d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0 text-dark">
                            <i class="fas fa-users text-primary me-2"></i>
                            Class Roster: <?= h($selected_class['class_name']) ?> (Section <?= h($selected_class['section']) ?>)
                        </h5>
                        <small class="text-muted">Total Enrolled: <?= count($students_roster) ?> student(s)</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="attendance.php?class_id=<?= (int)$selected_class['id'] ?>" class="btn-ams btn-primary-ams btn-sm">
                            <i class="fas fa-calendar-check me-1"></i> Mark Attendance
                        </a>
                        <a href="marks.php?class_id=<?= (int)$selected_class['id'] ?>" class="btn-ams btn-success-ams btn-sm text-white" style="background:#16a34a;border-color:#16a34a;">
                            <i class="fas fa-file-signature me-1"></i> Enter Marks
                        </a>
                    </div>
                </div>

                <div class="card-body-ams p-0">
                    <?php if (empty($students_roster)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-user-slash fa-2x mb-2 text-secondary"></i>
                            <p class="mb-0 fw-semibold">No active students found in this class.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Father's Name</th>
                                        <th>Gender</th>
                                        <th>Contact Phone</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($students_roster as $s): ?>
                                    <tr>
                                        <td class="text-center text-muted fw-bold"><?= $i++ ?></td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-dark font-monospace border">
                                                <?= h($s['student_id']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                    <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= h($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                                    <small class="text-muted"><?= h($s['email'] ?: 'No email') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= h($s['father_name']) ?></td>
                                        <td>
                                            <span class="badge <?= $s['gender']==='Female' ? 'bg-danger-subtle text-danger' : 'bg-info-subtle text-info' ?>">
                                                <?= h($s['gender'] ?: 'N/A') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone-alt text-muted me-1 small"></i>
                                            <?= h($s['phone'] ?: 'N/A') ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="fas fa-check-circle me-1"></i> <?= h(ucfirst($s['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include '../layout_end.php';
?>
