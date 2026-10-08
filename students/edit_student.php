<?php
/**
 * students/edit_student.php
 */
require_once '../database.php';
require_login();
require_permission('students.edit');
$user = current_user();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) { set_flash('error','Invalid student ID.'); redirect('../students/student_records.php'); }

$student = db_fetch_one(
    "SELECT s.*, c.class_name FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=?",
    'i', [$id]
);
if (!$student) { set_flash('error','Student not found.'); redirect('../students/student_records.php'); }

$classes = db_fetch_all("SELECT id, class_name, section FROM classes WHERE status='active' ORDER BY class_name");
$errors  = [];

$page_title  = 'Edit Student';
$active_menu = 'students_list';
$breadcrumb  = ['Students', 'Edit'];
$depth       = '../';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'first_name'    => trim($_POST['first_name']    ?? ''),
        'last_name'     => trim($_POST['last_name']     ?? ''),
        'father_name'   => trim($_POST['father_name']   ?? ''),
        'gender'        => $_POST['gender']             ?? '',
        'date_of_birth' => $_POST['date_of_birth']      ?? '',
        'phone'         => trim($_POST['phone']         ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'address'       => trim($_POST['address']       ?? ''),
        'class_id'      => (int)($_POST['class_id']     ?? 0),
        'admission_date'=> $_POST['admission_date']     ?? '',
        'status'        => $_POST['status']             ?? 'active',
    ];

    if (!$form['first_name'])  $errors[] = 'First name is required.';
    if (!$form['last_name'])   $errors[] = 'Last name is required.';
    if (!$form['father_name']) $errors[] = 'Father name is required.';
    if (!$form['phone'])       $errors[] = 'Phone is required.';
    if (!$form['class_id'])    $errors[] = 'Class is required.';
    if ($form['email'] && !filter_var($form['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';

    $photo_filename = $student['photo'];
    if (!empty($_FILES['photo']['name'])) {
        $new_photo = upload_photo($_FILES['photo'], STUDENT_PHOTO_DIR, $student['photo']);
        if (!$new_photo) $errors[] = 'Invalid photo. Only JPG/PNG, max 2MB.';
        else $photo_filename = $new_photo;
    }

    if (empty($errors)) {
        db_execute(
            "UPDATE students SET first_name=?,last_name=?,father_name=?,gender=?,date_of_birth=?,
             phone=?,email=?,address=?,class_id=?,admission_date=?,photo=?,status=? WHERE id=?",
            'ssssssssisssi',
            [
                $form['first_name'], $form['last_name'], $form['father_name'], $form['gender'],
                $form['date_of_birth'] ?: null,
                $form['phone'], $form['email'], $form['address'],
                $form['class_id'], $form['admission_date'],
                $photo_filename, $form['status'], $id
            ]
        );
        set_flash('success', 'Student updated successfully!');
        redirect('../students/student_records.php');
    }
    // Re-populate form on error
    $student = array_merge($student, $form);
}

include '../layout.php';
?>

<div class="card-ams">
    <div class="card-header-ams">
        <h5><i class="fas fa-edit text-warning"></i> Edit Student — <?= h($student['student_id']) ?></h5>
        <a href="student_records.php" class="btn-ams btn-secondary-ams btn-sm-ams">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body-ams">

        <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="edit_student.php?id=<?= $id ?>" enctype="multipart/form-data" data-validate>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="form-section-title"><i class="fas fa-id-card"></i> Personal Information</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Student ID</label>
                    <input type="text" class="form-control-ams" value="<?= h($student['student_id']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">First Name <span class="req">*</span></label>
                    <input type="text" name="first_name" class="form-control-ams" required
                           value="<?= h($student['first_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name" class="form-control-ams" required
                           value="<?= h($student['last_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Father's Name <span class="req">*</span></label>
                    <input type="text" name="father_name" class="form-control-ams" required
                           value="<?= h($student['father_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Gender <span class="req">*</span></label>
                    <select name="gender" class="form-control-ams" required>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= $student['gender']===$g?'selected':'' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control-ams"
                           value="<?= h($student['date_of_birth']) ?>">
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-phone"></i> Contact</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Phone <span class="req">*</span></label>
                    <input type="text" name="phone" class="form-control-ams" required
                           value="<?= h($student['phone']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Email</label>
                    <input type="email" name="email" class="form-control-ams"
                           value="<?= h($student['email']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Address</label>
                    <textarea name="address" class="form-control-ams"><?= h($student['address']) ?></textarea>
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-school"></i> Academic</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Class <span class="req">*</span></label>
                    <select name="class_id" class="form-control-ams" required>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $student['class_id']==$c['id']?'selected':'' ?>>
                            <?= h($c['class_name'].' - '.$c['section']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Admission Date</label>
                    <input type="date" name="admission_date" class="form-control-ams"
                           value="<?= h($student['admission_date']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Status</label>
                    <div class="radio-group">
                        <label class="radio-pill">
                            <input type="radio" name="status" value="active" <?= $student['status']==='active'?'checked':'' ?>>
                            <i class="fas fa-check-circle text-success"></i> Active
                        </label>
                        <label class="radio-pill">
                            <input type="radio" name="status" value="inactive" <?= $student['status']==='inactive'?'checked':'' ?>>
                            <i class="fas fa-times-circle text-secondary"></i> Inactive
                        </label>
                    </div>
                </div>
            </div>

            <!-- Photo -->
            <div class="form-section-title"><i class="fas fa-camera"></i> Photo</div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label-ams">Current Photo</label><br>
                    <?php if ($student['photo'] && file_exists(STUDENT_PHOTO_DIR . $student['photo'])): ?>
                        <img src="<?= $depth ?>uploads/students/<?= h($student['photo']) ?>"
                             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #4a90e2;" alt="Photo">
                    <?php else: ?>
                        <div class="record-photo-placeholder" style="width:80px;height:80px;font-size:2rem;">
                            <?= strtoupper(substr($student['first_name'],0,1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Replace Photo (optional)</label>
                    <div class="file-upload-area">
                        <img id="photoPreview" src="#" alt="Preview" class="photo-preview" style="display:none;">
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/gif"
                               data-photo-input="photoPreview">
                        <div>
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0" style="font-size:.85rem;">Click to upload new photo</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mt-4 flex-wrap">
                <button type="submit" class="btn-ams btn-success-ams">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="delete_student.php?id=<?= $id ?>"
                   class="btn-ams btn-danger-ams"
                   data-confirm="Delete this student permanently?">
                    <i class="fas fa-trash"></i> Delete Student
                </a>
                <a href="student_records.php" class="btn-ams btn-secondary-ams">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../layout_end.php'; ?>
