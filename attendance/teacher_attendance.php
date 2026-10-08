<?php
/**
 * attendance/teacher_attendance.php
 */
require_once '../database.php';
require_login();
require_permission('attendance.mark');
$user = current_user();

$page_title  = 'Teacher Attendance';
$active_menu = 'attendance_teacher';
$breadcrumb  = ['Attendance', 'Teacher Attendance'];
$depth       = '../';

$selected_date = trim($_GET['att_date'] ?? $_POST['att_date'] ?? date('Y-m-d'));
$teachers_list = [];
$save_errors   = [];

// Load teachers with existing attendance status
$teachers_list = db_fetch_all(
    "SELECT t.id, t.teacher_id, t.first_name, t.last_name,
            COALESCE((SELECT ta.status FROM teacher_attendance ta
                      WHERE ta.teacher_id=t.id AND ta.attendance_date=? LIMIT 1), '') AS att_status
     FROM teachers t
     WHERE t.status='active'
     ORDER BY t.first_name, t.last_name",
    's', [$selected_date]
);

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $att_date = $_POST['att_date'] ?? date('Y-m-d');
    $statuses = $_POST['att_status'] ?? [];

    if (empty($statuses)) {
        $save_errors[] = 'No attendance data to save.';
    } else {
        foreach ($statuses as $tch_id => $status) {
            $tch_id = (int)$tch_id;
            if (!in_array($status, ['Present','Absent'], true)) continue;
            db_execute(
                "INSERT INTO teacher_attendance (teacher_id,attendance_date,status) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE status=?",
                'isss',
                [$tch_id, $att_date, $status, $status]
            );
        }
        set_flash('success', 'Teacher attendance saved for ' . date('d M Y', strtotime($att_date)) . '!');
        redirect('../attendance/teacher_attendance.php?att_date=' . urlencode($att_date));
    }
}

include '../layout.php';
?>

<div class="row g-4">
    <!-- Filter -->
    <div class="col-lg-3">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5><i class="fas fa-filter text-primary"></i> Select Date</h5>
            </div>
            <div class="card-body-ams">
                <form method="GET" action="teacher_attendance.php">
                    <div class="form-group">
                        <label class="form-label-ams">Attendance Date</label>
                        <input type="date" name="att_date" class="form-control-ams"
                               value="<?= h($selected_date) ?>" required>
                    </div>
                    <button type="submit" class="btn-ams btn-primary-ams w-100">
                        <i class="fas fa-users-cog"></i> Load Teachers
                    </button>
                </form>

                <?php if (!empty($teachers_list)): ?>
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
                <h5><i class="fas fa-user-tie text-primary"></i>
                    Teacher Attendance — <?= date('d M Y', strtotime($selected_date)) ?>
                </h5>
            </div>
            <div class="card-body-ams">

                <?php if ($save_errors): ?>
                <div class="alert alert-danger mb-3">
                    <?php foreach ($save_errors as $e): ?><?= h($e) ?><br><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (empty($teachers_list)): ?>
                <div class="no-data">
                    <i class="fas fa-chalkboard-teacher"></i>
                    No active teachers found
                </div>
                <?php else: ?>
                <form method="POST" action="teacher_attendance.php?att_date=<?= urlencode($selected_date) ?>">
                    <input type="hidden" name="att_date" value="<?= h($selected_date) ?>">
                    <input type="hidden" name="save_attendance" value="1">

                    <div class="table-responsive-ams">
                        <table class="table-ams">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Teacher ID</th>
                                    <th>Teacher Name</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $i=1; foreach ($teachers_list as $t): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><span class="fw-600 text-accent"><?= h($t['teacher_id']) ?></span></td>
                                <td class="fw-600"><?= h($t['first_name'].' '.$t['last_name']) ?></td>
                                <td>
                                    <div class="att-radio-group">
                                        <label class="att-radio-label present-label">
                                            <input type="radio" class="att-present-radio"
                                                   name="att_status[<?= $t['id'] ?>]"
                                                   value="Present"
                                                   <?= ($t['att_status']==='Present'||$t['att_status']==='') ? 'checked' : '' ?>>
                                            <i class="fas fa-check"></i> Present
                                        </label>
                                        <label class="att-radio-label absent-label">
                                            <input type="radio" class="att-absent-radio"
                                                   name="att_status[<?= $t['id'] ?>]"
                                                   value="Absent"
                                                   <?= $t['att_status']==='Absent' ? 'checked' : '' ?>>
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
                            <i class="fas fa-save"></i> Save Attendance (<?= count($teachers_list) ?> teachers)
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../layout_end.php'; ?>
