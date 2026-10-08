<?php
/**
 * attendance/student_attendance.php
 */
require_once '../database.php';
require_login();
require_permission('attendance.mark');
$user = current_user();

$page_title  = 'Student Attendance';
$active_menu = 'attendance_student';
$breadcrumb  = ['Attendance', 'Student Attendance'];
$depth       = '../';

$classes = db_fetch_all("SELECT id, class_name, section FROM classes WHERE status='active' ORDER BY class_name");

$selected_date  = trim($_GET['att_date'] ?? $_POST['att_date'] ?? date('Y-m-d'));
$selected_class = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$students_list  = [];
$saved          = false;
$save_errors    = [];

// Load students if class is selected
if ($selected_class) {
    $students_list = db_fetch_all(
        "SELECT s.id, s.student_id, s.first_name, s.last_name,
                COALESCE((SELECT sa.status FROM student_attendance sa
                          WHERE sa.student_id=s.id AND sa.attendance_date=? LIMIT 1), '') AS att_status
         FROM students s
         WHERE s.class_id=? AND s.status='active'
         ORDER BY s.first_name, s.last_name",
        'si', [$selected_date, $selected_class]
    );
}

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $att_date  = $selected_date;
    $class_id  = $selected_class;
    $statuses  = $_POST['att_status'] ?? [];

    if (empty($statuses)) {
        $save_errors[] = 'No attendance data to save.';
    } else {
        foreach ($statuses as $stu_id => $status) {
            $stu_id = (int)$stu_id;
            if (!in_array($status, ['Present','Absent'], true)) continue;

            // Get class_id for this student
            $stu = db_fetch_one("SELECT class_id FROM students WHERE id=?", 'i', [$stu_id]);
            if (!$stu) continue;

            // Insert or update
            db_execute(
                "INSERT INTO student_attendance (student_id,class_id,attendance_date,status)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE status=?",
                'iisss',
                [$stu_id, $stu['class_id'], $att_date, $status, $status]
            );
        }
        set_flash('success', 'Attendance saved for ' . date('d M Y', strtotime($att_date)) . '!');
        redirect('../attendance/student_attendance.php?att_date=' . urlencode($att_date) . '&class_id=' . $class_id);
    }
}

include '../layout.php';
?>

<div class="row g-4">
    <!-- Filter Form -->
    <div class="col-lg-3">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5><i class="fas fa-filter text-primary"></i> Select Date & Class</h5>
            </div>
            <div class="card-body-ams">
                <form method="GET" action="student_attendance.php" id="loadStudentsForm">
                    <div class="form-group">
                        <label class="form-label-ams">Attendance Date</label>
                        <input type="date" name="att_date" class="form-control-ams"
                               value="<?= h($selected_date) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label-ams">Class</label>
                        <select name="class_id" class="form-control-ams" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $selected_class==$c['id']?'selected':'' ?>>
                                <?= h($c['class_name'].' - '.$c['section']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-ams btn-primary-ams w-100">
                        <i class="fas fa-users"></i> Load Students
                    </button>
                </form>

                <?php if ($selected_class && !empty($students_list)): ?>
                <hr>
                <div class="d-flex flex-column gap-2 mt-2">
                    <button class="btn-ams btn-success-ams btn-sm-ams" id="markAllPresent">
                        <i class="fas fa-check-double"></i> Mark All Present
                    </button>
                    <button class="btn-ams btn-danger-ams btn-sm-ams" id="markAllAbsent">
                        <i class="fas fa-times"></i> Mark All Absent
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="col-lg-9">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5><i class="fas fa-user-check text-success"></i>
                    Student Attendance — <?= date('d M Y', strtotime($selected_date)) ?>
                </h5>
                <a href="../attendance/attendance_records.php" class="btn-ams btn-info-ams btn-sm-ams">
                    <i class="fas fa-clipboard-list"></i> View Records
                </a>
            </div>
            <div class="card-body-ams">

                <?php if ($save_errors): ?>
                <div class="alert alert-danger mb-3">
                    <?php foreach ($save_errors as $e): ?><?= h($e) ?><br><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!$selected_class): ?>
                <div class="no-data">
                    <i class="fas fa-calendar-check"></i>
                    Select a date and class to load students
                </div>
                <?php elseif (empty($students_list)): ?>
                <div class="no-data">
                    <i class="fas fa-user-graduate"></i>
                    No active students found in this class
                </div>
                <?php else: ?>
                <form method="POST" action="student_attendance.php?att_date=<?= urlencode($selected_date) ?>&class_id=<?= $selected_class ?>">
                    <input type="hidden" name="att_date"  value="<?= h($selected_date) ?>">
                    <input type="hidden" name="class_id"  value="<?= $selected_class ?>">
                    <input type="hidden" name="save_attendance" value="1">

                    <div class="table-responsive-ams">
                        <table class="table-ams">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $i=1; foreach ($students_list as $s): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><span class="fw-600 text-accent"><?= h($s['student_id']) ?></span></td>
                                <td class="fw-600"><?= h($s['first_name'].' '.$s['last_name']) ?></td>
                                <td>
                                    <div class="att-radio-group">
                                        <label class="att-radio-label present-label">
                                            <input type="radio" class="att-present-radio"
                                                   name="att_status[<?= $s['id'] ?>]"
                                                   value="Present"
                                                   <?= ($s['att_status']==='Present'||$s['att_status']==='') ? 'checked' : '' ?>>
                                            <i class="fas fa-check"></i> Present
                                        </label>
                                        <label class="att-radio-label absent-label">
                                            <input type="radio" class="att-absent-radio"
                                                   name="att_status[<?= $s['id'] ?>]"
                                                   value="Absent"
                                                   <?= $s['att_status']==='Absent' ? 'checked' : '' ?>>
                                            <i class="fas fa-times"></i> Absent
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn-ams btn-success-ams">
                            <i class="fas fa-save"></i> Save Attendance (<?= count($students_list) ?> students)
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../layout_end.php'; ?>
