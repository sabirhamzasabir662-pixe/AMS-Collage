<?php
/**
 * teachers/edit_teacher.php
 */
require_once '../database.php';
require_login();
require_permission('teachers.edit');
$user = current_user();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) { set_flash('error','Invalid teacher ID.'); redirect('../teachers/teacher_records.php'); }

$teacher = db_fetch_one("SELECT * FROM teachers WHERE id=?", 'i', [$id]);
if (!$teacher) { set_flash('error','Teacher not found.'); redirect('../teachers/teacher_records.php'); }

$subjects = db_fetch_all("SELECT id, subject_name FROM subjects WHERE status='active' ORDER BY subject_name");
$errors   = [];

$page_title  = 'Edit Teacher';
$active_menu = 'teachers_list';
$breadcrumb  = ['Teachers', 'Edit'];
$depth       = '../';
$can_manage_salary = has_permission('teachers.salary');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'first_name'    => trim($_POST['first_name']    ?? ''),
        'last_name'     => trim($_POST['last_name']     ?? ''),
        'gender'        => $_POST['gender']             ?? '',
        'date_of_birth' => $_POST['date_of_birth']      ?? '',
        'phone'         => trim($_POST['phone']         ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'address'       => trim($_POST['address']       ?? ''),
        'joining_date'  => $_POST['joining_date']       ?? '',
        'subject_id'    => (int)($_POST['subject_id']   ?? 0) ?: null,
        'qualification' => trim($_POST['qualification'] ?? ''),
        'experience'    => (int)($_POST['experience']   ?? 0),
        'salary'        => $can_manage_salary ? (float)($_POST['salary'] ?? 0) : (float)$teacher['salary'],
        'status'        => $_POST['status']             ?? 'active',
    ];

    if (!$form['first_name'])  $errors[] = 'First name is required.';
    if (!$form['last_name'])   $errors[] = 'Last name is required.';
    if (!$form['phone'])       $errors[] = 'Phone is required.';
    if ($form['email'] && !filter_var($form['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email.';

    $photo_filename = $teacher['photo'];
    if (!empty($_FILES['photo']['name'])) {
        $new_photo = upload_photo($_FILES['photo'], TEACHER_PHOTO_DIR, $teacher['photo']);
        if (!$new_photo) $errors[] = 'Invalid photo.';
        else $photo_filename = $new_photo;
    }

    if (empty($errors)) {
        db_execute(
            "UPDATE teachers SET first_name=?,last_name=?,gender=?,date_of_birth=?,phone=?,email=?,
             address=?,joining_date=?,subject_id=?,qualification=?,experience=?,salary=?,photo=?,status=?
             WHERE id=?",
            'ssssssssisidsssi',
            [
                $form['first_name'], $form['last_name'], $form['gender'],
                $form['date_of_birth'] ?: null,
                $form['phone'], $form['email'], $form['address'],
                $form['joining_date'],
                $form['subject_id'],
                $form['qualification'], $form['experience'], $form['salary'],
                $photo_filename, $form['status'], $id
            ]
        );
        set_flash('success', 'Teacher updated successfully!');
        redirect('../teachers/teacher_records.php');
    }
    $teacher = array_merge($teacher, $form);
}

include '../layout.php';
?>

<div class="card-ams">
    <div class="card-header-ams">
        <h5><i class="fas fa-edit text-warning"></i> Edit Teacher — <?= h($teacher['teacher_id']) ?></h5>
        <a href="teacher_records.php" class="btn-ams btn-secondary-ams btn-sm-ams">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body-ams">

        <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="edit_teacher.php?id=<?= $id ?>" enctype="multipart/form-data" data-validate>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="form-section-title"><i class="fas fa-id-card"></i> Personal Information</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Teacher ID</label>
                    <input type="text" class="form-control-ams" value="<?= h($teacher['teacher_id']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">First Name <span class="req">*</span></label>
                    <input type="text" name="first_name" class="form-control-ams" required
                           value="<?= h($teacher['first_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name" class="form-control-ams" required
                           value="<?= h($teacher['last_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Gender</label>
                    <select name="gender" class="form-control-ams">
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= $teacher['gender']===$g?'selected':'' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control-ams"
                           value="<?= h($teacher['date_of_birth']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Status</label>
                    <div class="radio-group">
                        <label class="radio-pill">
                            <input type="radio" name="status" value="active"
                                   <?= $teacher['status']==='active'?'checked':'' ?>>
                            <i class="fas fa-check-circle text-success"></i> Active
                        </label>
                        <label class="radio-pill">
                            <input type="radio" name="status" value="inactive"
                                   <?= $teacher['status']==='inactive'?'checked':'' ?>>
                            <i class="fas fa-times-circle text-secondary"></i> Inactive
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-phone"></i> Contact</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Phone <span class="req">*</span></label>
                    <input type="text" name="phone" class="form-control-ams" required
                           value="<?= h($teacher['phone']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Email</label>
                    <input type="email" name="email" class="form-control-ams"
                           value="<?= h($teacher['email']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Address</label>
                    <textarea name="address" class="form-control-ams"><?= h($teacher['address']) ?></textarea>
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-briefcase"></i> Professional</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control-ams"
                           value="<?= h($teacher['joining_date']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Subject</label>
                    <select name="subject_id" class="form-control-ams">
                        <option value="">-- None --</option>
                        <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= $teacher['subject_id']==$sub['id']?'selected':'' ?>>
                            <?= h($sub['subject_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Qualification</label>
                    <input type="text" name="qualification" class="form-control-ams"
                           value="<?= h($teacher['qualification']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Experience (Years)</label>
                    <input type="number" name="experience" class="form-control-ams" min="0"
                           value="<?= h($teacher['experience']) ?>">
                </div>
                <?php if ($can_manage_salary): ?>
                <div class="form-group">
                    <label class="form-label-ams">Salary (₹)</label>
                    <input type="number" name="salary" class="form-control-ams" min="0" step="0.01"
                           value="<?= h($teacher['salary']) ?>">
                </div>
                <?php endif; ?>
            </div>

            <!-- Photo -->
            <div class="form-section-title"><i class="fas fa-camera"></i> Photo</div>
            <div class="form-grid-2">
                <div class="form-group">
                    <?php if ($teacher['photo'] && file_exists(TEACHER_PHOTO_DIR . $teacher['photo'])): ?>
                        <img src="<?= $depth ?>uploads/teachers/<?= h($teacher['photo']) ?>"
                             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #4a90e2;" alt="Photo">
                    <?php else: ?>
                        <div class="record-photo-placeholder" style="width:80px;height:80px;font-size:2rem;">
                            <?= strtoupper(substr($teacher['first_name'],0,1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Replace Photo</label>
                    <div class="file-upload-area">
                        <img id="photoPreview" src="#" alt="Preview" class="photo-preview" style="display:none;">
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/gif"
                               data-photo-input="photoPreview">
                        <div>
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0" style="font-size:.85rem;">Click to upload</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mt-4 flex-wrap">
                <button type="submit" class="btn-ams btn-success-ams">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="delete_teacher.php?id=<?= $id ?>"
                   class="btn-ams btn-danger-ams"
                   data-confirm="Delete this teacher permanently?">
                    <i class="fas fa-trash"></i> Delete
                </a>
                <a href="teacher_records.php" class="btn-ams btn-secondary-ams">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../layout_end.php'; ?>
