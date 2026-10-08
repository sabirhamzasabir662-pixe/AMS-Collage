<?php
/**
 * classes/classes.php — Manage Classes
 */
require_once '../database.php';
require_login();
require_permission('classes.view');
$user = current_user();

$page_title  = 'Classes';
$active_menu = 'classes';
$breadcrumb  = ['Classes'];
$depth       = '../';

$errors  = [];
$success = '';
$edit    = null;

// Handle Add / Edit / Delete
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'add' || $action === 'edit') {
        if ($action === 'add') {
            require_permission('classes.add');
        } else {
            require_permission('classes.edit');
        }

        $form = [
            'class_name'       => trim($_POST['class_name']       ?? ''),
            'section'          => trim($_POST['section']          ?? 'A'),
            'class_teacher_id' => (int)($_POST['class_teacher_id'] ?? 0) ?: null,
            'room_number'      => trim($_POST['room_number']      ?? ''),
            'status'           => $_POST['status']                ?? 'active',
        ];

        if (!$form['class_name']) $errors[] = 'Class name is required.';

        if (empty($errors)) {
            if ($action === 'add') {
                db_execute(
                    "INSERT INTO classes (class_name,section,class_teacher_id,room_number,status) VALUES (?,?,?,?,?)",
                    'ssiss',
                    [$form['class_name'], $form['section'], $form['class_teacher_id'], $form['room_number'], $form['status']]
                );
                set_flash('success', 'Class "' . $form['class_name'] . '" added successfully!');
            } else {
                $eid = (int)($_POST['edit_id'] ?? 0);
                db_execute(
                    "UPDATE classes SET class_name=?,section=?,class_teacher_id=?,room_number=?,status=? WHERE id=?",
                    'ssissi',
                    [$form['class_name'], $form['section'], $form['class_teacher_id'], $form['room_number'], $form['status'], $eid]
                );
                set_flash('success', 'Class updated successfully!');
            }
            redirect('../classes/classes.php');
        }
    }

} elseif ($action === 'delete') {
    require_permission('classes.delete');
    $del_id = (int)($_GET['id'] ?? 0);
    if ($del_id) {
        // Check if any students are in this class
        $cnt = db_count("SELECT COUNT(*) FROM students WHERE class_id=?", 'i', [$del_id]);
        if ($cnt > 0) {
            set_flash('error', "Cannot delete: $cnt student(s) are assigned to this class.");
        } else {
            db_execute("DELETE FROM classes WHERE id=?", 'i', [$del_id]);
            set_flash('success', 'Class deleted successfully.');
        }
    }
    redirect('../classes/classes.php');

} elseif ($action === 'edit' && isset($_GET['id'])) {
    require_permission('classes.edit');
    $edit = db_fetch_one("SELECT * FROM classes WHERE id=?", 'i', [(int)$_GET['id']]);
}

$classes  = db_fetch_all(
    "SELECT c.*, t.first_name, t.last_name,
            (SELECT COUNT(*) FROM students s WHERE s.class_id=c.id) AS student_count
     FROM classes c LEFT JOIN teachers t ON c.class_teacher_id=t.id
     ORDER BY c.class_name, c.section"
);
$teachers = db_fetch_all("SELECT id, teacher_id, first_name, last_name FROM teachers WHERE status='active' ORDER BY first_name");

$search = trim($_GET['search'] ?? '');

include '../layout.php';
?>

<div class="row g-4">
    <!-- ADD / EDIT FORM -->
    <div class="col-lg-4">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5><i class="fas fa-<?= $edit?'edit':'plus-circle' ?> text-<?= $edit?'warning':'primary' ?>"></i>
                    <?= $edit ? 'Edit Class' : 'Add New Class' ?>
                </h5>
                <?php if ($edit): ?>
                <a href="classes.php" class="btn-ams btn-secondary-ams btn-sm-ams">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body-ams">
                <?php if ($errors): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="classes.php" data-validate>
                    <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
                    <?php if ($edit): ?>
                    <input type="hidden" name="edit_id" value="<?= $edit['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label-ams">Class Name <span class="req">*</span></label>
                        <input type="text" name="class_name" class="form-control-ams" required
                               value="<?= h($edit['class_name'] ?? '') ?>" placeholder="e.g. Class 1, Grade 10">
                    </div>
                    <div class="form-group">
                        <label class="form-label-ams">Section</label>
                        <input type="text" name="section" class="form-control-ams" maxlength="10"
                               value="<?= h($edit['section'] ?? 'A') ?>" placeholder="A">
                    </div>
                    <div class="form-group">
                        <label class="form-label-ams">Class Teacher</label>
                        <select name="class_teacher_id" class="form-control-ams">
                            <option value="">-- None --</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"
                                <?= ($edit['class_teacher_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
                                <?= h($t['first_name'].' '.$t['last_name'].' ('.$t['teacher_id'].')') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label-ams">Room Number</label>
                        <input type="text" name="room_number" class="form-control-ams"
                               value="<?= h($edit['room_number'] ?? '') ?>" placeholder="e.g. 101">
                    </div>
                    <div class="form-group">
                        <label class="form-label-ams">Status</label>
                        <div class="radio-group">
                            <label class="radio-pill">
                                <input type="radio" name="status" value="active"
                                       <?= ($edit['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
                                <i class="fas fa-check-circle text-success"></i> Active
                            </label>
                            <label class="radio-pill">
                                <input type="radio" name="status" value="inactive"
                                       <?= ($edit['status'] ?? '') === 'inactive' ? 'checked' : '' ?>>
                                <i class="fas fa-times-circle text-secondary"></i> Inactive
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn-ams btn-primary-ams w-100">
                        <i class="fas fa-save"></i> <?= $edit ? 'Update Class' : 'Add Class' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- CLASSES TABLE -->
    <div class="col-lg-8">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5><i class="fas fa-school text-orange"></i> All Classes
                    <span class="badge bg-primary ms-2"><?= count($classes) ?></span>
                </h5>
            </div>
            <div class="card-body-ams">
                <form method="GET" class="search-bar mb-3">
                    <div class="search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" value="<?= h($search) ?>"
                               placeholder="Search classes..." data-search-table="classesTable">
                    </div>
                </form>

                <div class="table-responsive-ams">
                    <table class="table-ams" id="classesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Class Name</th>
                                <th>Section</th>
                                <th>Teacher</th>
                                <th>Room</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($classes)): ?>
                            <tr><td colspan="8"><div class="no-data"><i class="fas fa-school"></i>No classes yet</div></td></tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($classes as $c): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="fw-600"><?= h($c['class_name']) ?></td>
                                <td><?= h($c['section']) ?></td>
                                <td><?= $c['first_name'] ? h($c['first_name'].' '.$c['last_name']) : '<span class="text-muted">—</span>' ?></td>
                                <td><?= h($c['room_number'] ?: '—') ?></td>
                                <td>
                                    <span class="badge-ams badge-active"><?= $c['student_count'] ?></span>
                                </td>
                                <td>
                                    <span class="badge-ams badge-<?= $c['status'] ?>">
                                        <?= ucfirst(h($c['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="classes.php?action=edit&id=<?= $c['id'] ?>"
                                           class="btn-ams btn-warning-ams btn-sm-ams" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="classes.php?action=delete&id=<?= $c['id'] ?>"
                                           class="btn-ams btn-danger-ams btn-sm-ams"
                                           data-confirm="Delete class '<?= h($c['class_name']) ?>'?"
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
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
</div>

<?php include '../layout_end.php'; ?>
