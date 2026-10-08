<?php
/**
 * subjects/subjects.php — Manage Subjects
 */
require_once '../database.php';
require_login();
require_permission('subjects.view');
$user = current_user();

$page_title  = 'Subjects';
$active_menu = 'subjects';
$breadcrumb  = ['Subjects'];
$depth       = '../';

$errors = [];
$edit   = null;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        if ($action === 'add') {
            require_permission('subjects.add');
        } else {
            require_permission('subjects.edit');
        }

        $form = [
            'subject_name' => trim($_POST['subject_name'] ?? ''),
            'subject_code' => trim($_POST['subject_code'] ?? ''),
            'class_id'     => (int)($_POST['class_id']    ?? 0),
            'teacher_id'   => (int)($_POST['teacher_id']  ?? 0) ?: null,
            'status'       => $_POST['status']            ?? 'active',
        ];

        if (!$form['subject_name']) $errors[] = 'Subject name is required.';
        if (!$form['subject_code']) $errors[] = 'Subject code is required.';
        if (!$form['class_id'])     $errors[] = 'Class is required.';

        // Check unique code (exclude self on edit)
        $eid = (int)($_POST['edit_id'] ?? 0);
        $q   = $action === 'edit'
            ? "SELECT COUNT(*) FROM subjects WHERE subject_code=? AND id!=?"
            : "SELECT COUNT(*) FROM subjects WHERE subject_code=?";
        $p   = $action === 'edit'
            ? ['si', [$form['subject_code'], $eid]]
            : ['s',  [$form['subject_code']]];
        if (db_count($q, ...$p)) $errors[] = 'Subject code already exists.';

        if (empty($errors)) {
            if ($action === 'add') {
                db_execute(
                    "INSERT INTO subjects (subject_name,subject_code,class_id,teacher_id,status) VALUES (?,?,?,?,?)",
                    'ssiis',
                    [$form['subject_name'], $form['subject_code'], $form['class_id'], $form['teacher_id'], $form['status']]
                );
                set_flash('success', 'Subject added successfully!');
            } else {
                db_execute(
                    "UPDATE subjects SET subject_name=?,subject_code=?,class_id=?,teacher_id=?,status=? WHERE id=?",
                    'ssiisi',
                    [$form['subject_name'], $form['subject_code'], $form['class_id'], $form['teacher_id'], $form['status'], $eid]
                );
                set_flash('success', 'Subject updated successfully!');
            }
            redirect('../subjects/subjects.php');
        }
    }

} elseif ($action === 'delete') {
    require_permission('subjects.delete');
    $del_id = (int)($_GET['id'] ?? 0);
    if ($del_id) {
        db_execute("DELETE FROM subjects WHERE id=?", 'i', [$del_id]);
        set_flash('success', 'Subject deleted.');
    }
    redirect('../subjects/subjects.php');

} elseif ($action === 'edit' && isset($_GET['id'])) {
    require_permission('subjects.edit');
    $edit = db_fetch_one("SELECT * FROM subjects WHERE id=?", 'i', [(int)$_GET['id']]);
}

$subjects = db_fetch_all(
    "SELECT sub.*, c.class_name, t.first_name, t.last_name
     FROM subjects sub
     LEFT JOIN classes  c ON sub.class_id  = c.id
     LEFT JOIN teachers t ON sub.teacher_id = t.id
     ORDER BY sub.subject_name"
);
$classes  = db_fetch_all("SELECT id, class_name, section FROM classes WHERE status='active' ORDER BY class_name");
$teachers = db_fetch_all("SELECT id, first_name, last_name FROM teachers WHERE status='active' ORDER BY first_name");

include '../layout.php';
?>

<div class="row g-4">
    <!-- FORM -->
    <div class="col-lg-4">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5><i class="fas fa-<?= $edit?'edit':'plus-circle' ?> text-<?= $edit?'warning':'primary' ?>"></i>
                    <?= $edit ? 'Edit Subject' : 'Add New Subject' ?>
                </h5>
                <?php if ($edit): ?>
                <a href="subjects.php" class="btn-ams btn-secondary-ams btn-sm-ams">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="card-body-ams">
                <?php if ($errors): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="subjects.php" data-validate>
                    <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
                    <?php if ($edit): ?>
                    <input type="hidden" name="edit_id" value="<?= $edit['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label-ams">Subject Name <span class="req">*</span></label>
                        <input type="text" name="subject_name" class="form-control-ams" required
                               value="<?= h($edit['subject_name'] ?? '') ?>" placeholder="e.g. Mathematics">
                    </div>
                    <div class="form-group">
                        <label class="form-label-ams">Subject Code <span class="req">*</span></label>
                        <input type="text" name="subject_code" class="form-control-ams" required
                               value="<?= h($edit['subject_code'] ?? '') ?>" placeholder="e.g. MATH-01">
                    </div>
                    <div class="form-group">
                        <label class="form-label-ams">Class <span class="req">*</span></label>
                        <select name="class_id" class="form-control-ams" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                <?= ($edit['class_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                <?= h($c['class_name'].' - '.$c['section']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label-ams">Teacher</label>
                        <select name="teacher_id" class="form-control-ams">
                            <option value="">-- None --</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"
                                <?= ($edit['teacher_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
                                <?= h($t['first_name'].' '.$t['last_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
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
                        <i class="fas fa-save"></i> <?= $edit ? 'Update Subject' : 'Add Subject' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="col-lg-8">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5><i class="fas fa-book text-purple"></i> All Subjects
                    <span class="badge bg-primary ms-2"><?= count($subjects) ?></span>
                </h5>
            </div>
            <div class="card-body-ams">
                <div class="search-bar mb-3">
                    <div class="search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search subjects..."
                               data-search-table="subjectsTable">
                    </div>
                </div>

                <div class="table-responsive-ams">
                    <table class="table-ams" id="subjectsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th>Class</th>
                                <th>Teacher</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr><td colspan="7"><div class="no-data"><i class="fas fa-book"></i>No subjects yet</div></td></tr>
                        <?php else: ?>
                            <?php $i=1; foreach ($subjects as $s): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><span class="fw-600 text-accent"><?= h($s['subject_code']) ?></span></td>
                                <td class="fw-600"><?= h($s['subject_name']) ?></td>
                                <td><?= h($s['class_name'] ?? '—') ?></td>
                                <td><?= $s['first_name'] ? h($s['first_name'].' '.$s['last_name']) : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <span class="badge-ams badge-<?= $s['status'] ?>">
                                        <?= ucfirst(h($s['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="subjects.php?action=edit&id=<?= $s['id'] ?>"
                                           class="btn-ams btn-warning-ams btn-sm-ams" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="subjects.php?action=delete&id=<?= $s['id'] ?>"
                                           class="btn-ams btn-danger-ams btn-sm-ams"
                                           data-confirm="Delete subject '<?= h($s['subject_name']) ?>'?"
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
