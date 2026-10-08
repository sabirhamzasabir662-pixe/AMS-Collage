<?php
/**
 * teachers/add_teacher.php
 */
require_once '../database.php';
require_login();
require_permission('teachers.add');
$user = current_user();

$page_title  = 'Add Teacher';
$active_menu = 'teachers_add';
$breadcrumb  = ['Teachers', 'Add Teacher'];
$depth       = '../';
$can_manage_salary = has_permission('teachers.salary');

$subjects = db_fetch_all("SELECT id, subject_name FROM subjects WHERE status='active' ORDER BY subject_name");
$next_id  = generate_id('TCH', 'teachers', 'teacher_id');
$errors   = [];
$form     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'teacher_id'    => trim($_POST['teacher_id']    ?? ''),
        'first_name'    => trim($_POST['first_name']    ?? ''),
        'last_name'     => trim($_POST['last_name']     ?? ''),
        'gender'        => $_POST['gender']             ?? '',
        'date_of_birth' => $_POST['date_of_birth']      ?? '',
        'phone'         => trim($_POST['phone']         ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'address'       => trim($_POST['address']       ?? ''),
        'joining_date'  => $_POST['joining_date']       ?? date('Y-m-d'),
        'subject_id'    => (int)($_POST['subject_id']   ?? 0) ?: null,
        'qualification' => trim($_POST['qualification'] ?? ''),
        'experience'    => (int)($_POST['experience']   ?? 0),
        'salary'        => $can_manage_salary ? (float)($_POST['salary'] ?? 0) : 0.0,
        'status'        => $_POST['status']             ?? 'active',
    ];

    if (!$form['teacher_id'])  $errors[] = 'Teacher ID is required.';
    if (!$form['first_name'])  $errors[] = 'First name is required.';
    if (!$form['last_name'])   $errors[] = 'Last name is required.';
    if (!$form['gender'])      $errors[] = 'Gender is required.';
    if (!$form['phone'])       $errors[] = 'Phone is required.';
    if ($form['email'] && !filter_var($form['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';

    $existing = db_count("SELECT COUNT(*) FROM teachers WHERE teacher_id=?", 's', [$form['teacher_id']]);
    if ($existing) $errors[] = 'Teacher ID already exists.';

    $photo_filename = null;
    if (!empty($_FILES['photo']['name'])) {
        $photo_filename = upload_photo($_FILES['photo'], TEACHER_PHOTO_DIR);
        if (!$photo_filename) $errors[] = 'Invalid photo. JPG/PNG only, max 2MB.';
    }

    if (empty($errors)) {
        db_execute(
            "INSERT INTO teachers (teacher_id,first_name,last_name,gender,date_of_birth,phone,email,address,joining_date,subject_id,qualification,experience,salary,photo,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            'sssssssssisidss',
            [
                $form['teacher_id'], $form['first_name'], $form['last_name'], $form['gender'],
                $form['date_of_birth'] ?: null,
                $form['phone'], $form['email'], $form['address'],
                $form['joining_date'],
                $form['subject_id'],
                $form['qualification'],
                $form['experience'], $form['salary'],
                $photo_filename, $form['status']
            ]
        );
        set_flash('success', 'Teacher "' . $form['first_name'] . ' ' . $form['last_name'] . '" added successfully!');
        redirect('../teachers/teacher_records.php');
    }
}

include '../layout.php';
?>

<div class="card-ams">
    <div class="card-header-ams">
        <h5><i class="fas fa-chalkboard-teacher text-success"></i> Add New Teacher</h5>
        <a href="teacher_records.php" class="btn-ams btn-secondary-ams btn-sm-ams">
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

        <form method="POST" action="add_teacher.php" enctype="multipart/form-data" data-validate>

            <div class="form-section-title"><i class="fas fa-id-card"></i> Personal Information</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Teacher ID <span class="req">*</span></label>
                    <input type="text" name="teacher_id" class="form-control-ams" required
                           value="<?= h($form['teacher_id'] ?? $next_id) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">First Name <span class="req">*</span></label>
                    <input type="text" name="first_name" class="form-control-ams" required
                           value="<?= h($form['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name" class="form-control-ams" required
                           value="<?= h($form['last_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Gender <span class="req">*</span></label>
                    <select name="gender" class="form-control-ams" required>
                        <option value="">-- Select --</option>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($form['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control-ams"
                           value="<?= h($form['date_of_birth'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Status</label>
                    <div class="radio-group">
                        <label class="radio-pill">
                            <input type="radio" name="status" value="active"
                                   <?= ($form['status']??'active')==='active'?'checked':'' ?>>
                            <i class="fas fa-check-circle text-success"></i> Active
                        </label>
                        <label class="radio-pill">
                            <input type="radio" name="status" value="inactive"
                                   <?= ($form['status']??'')==='inactive'?'checked':'' ?>>
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
                           value="<?= h($form['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Email</label>
                    <input type="email" name="email" class="form-control-ams"
                           value="<?= h($form['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Address</label>
                    <textarea name="address" class="form-control-ams"><?= h($form['address'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section-title"><i class="fas fa-briefcase"></i> Professional Details</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control-ams"
                           value="<?= h($form['joining_date'] ?? date('Y-m-d')) ?>" data-default-today>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Subject</label>
                    <select name="subject_id" class="form-control-ams">
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= ($form['subject_id']??0)==$sub['id']?'selected':'' ?>>
                            <?= h($sub['subject_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Qualification</label>
                    <input type="text" name="qualification" class="form-control-ams"
                           value="<?= h($form['qualification'] ?? '') ?>" placeholder="e.g. M.Ed, B.Sc">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Experience (Years)</label>
                    <input type="number" name="experience" class="form-control-ams" min="0"
                           value="<?= h($form['experience'] ?? 0) ?>">
                </div>
                <?php if ($can_manage_salary): ?>
                <div class="form-group">
                    <label class="form-label-ams">Monthly Salary (₹)</label>
                    <input type="number" name="salary" class="form-control-ams" min="0" step="0.01"
                           value="<?= h($form['salary'] ?? 0) ?>">
                </div>
                <?php endif; ?>
            </div>

            <div class="form-section-title"><i class="fas fa-camera"></i> Photo</div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label-ams">Upload Photo (JPG/PNG, max 2MB)</label>
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
                <button type="submit" class="btn-ams btn-primary-ams">
                    <i class="fas fa-save"></i> Save Teacher
                </button>
                <button type="reset" class="btn-ams btn-secondary-ams">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <a href="teacher_records.php" class="btn-ams btn-secondary-ams">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../layout_end.php'; ?>
