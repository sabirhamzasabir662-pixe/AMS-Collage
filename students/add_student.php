<?php
/**
 * students/add_student.php
 * Arsalan Model School and College Management System
 */
require_once '../database.php';
require_login();
require_permission('students.add');
$user = current_user();

$page_title  = 'Add Student';
$active_menu = 'students_add';
$breadcrumb  = ['Students', 'Add Student'];
$depth       = '../';

// Load classes for dropdown
$classes = db_fetch_all("SELECT id, class_name, section FROM classes WHERE status='active' ORDER BY class_name, section");

// Auto-generate next student ID
$next_id = generate_id('STU', 'students', 'student_id');

$errors = [];
$form   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect & sanitize
    $form = [
        'student_id'     => trim($_POST['student_id']     ?? ''),
        'first_name'     => trim($_POST['first_name']     ?? ''),
        'last_name'      => trim($_POST['last_name']      ?? ''),
        'father_name'    => trim($_POST['father_name']    ?? ''),
        'gender'         => $_POST['gender']              ?? '',
        'date_of_birth'  => $_POST['date_of_birth']       ?? '',
        'phone'          => trim($_POST['phone']          ?? ''),
        'email'          => trim($_POST['email']          ?? ''),
        'address'        => trim($_POST['address']        ?? ''),
        'class_id'       => (int)($_POST['class_id']      ?? 0),
        'admission_date' => $_POST['admission_date']      ?? date('Y-m-d'),
        'status'         => $_POST['status']              ?? 'active',
    ];

    // Validate
    if (!$form['student_id'])     $errors[] = 'Student ID is required.';
    if (!$form['first_name'])     $errors[] = 'First name is required.';
    if (!$form['last_name'])      $errors[] = 'Last name is required.';
    if (!$form['father_name'])    $errors[] = 'Father name is required.';
    if (!$form['gender'])         $errors[] = 'Gender is required.';
    if (!$form['phone'])          $errors[] = 'Phone is required.';
    if (!$form['class_id'])       $errors[] = 'Class is required.';
    if (!$form['admission_date']) $errors[] = 'Admission date is required.';
    if ($form['email'] && !filter_var($form['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';

    // Check unique student_id
    $existing = db_count("SELECT COUNT(*) FROM students WHERE student_id=?", 's', [$form['student_id']]);
    if ($existing) $errors[] = 'Student ID already exists. Choose another.';

    // Handle photo
    $photo_filename = null;
    if (!empty($_FILES['photo']['name'])) {
        $photo_filename = upload_photo($_FILES['photo'], STUDENT_PHOTO_DIR);
        if (!$photo_filename) $errors[] = 'Invalid photo. Only JPG/PNG allowed, max 2MB.';
    }

    if (empty($errors)) {
        db_execute(
            "INSERT INTO students (student_id,first_name,last_name,father_name,gender,date_of_birth,phone,email,address,class_id,admission_date,photo,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            'sssssssssssss',
            [
                $form['student_id'], $form['first_name'], $form['last_name'], $form['father_name'],
                $form['gender'],
                $form['date_of_birth'] ?: null,
                $form['phone'], $form['email'], $form['address'],
                $form['class_id'],
                $form['admission_date'],
                $photo_filename,
                $form['status'],
            ]
        );
        $new_student_db_id = db_last_id();

        // Check if auto-create parent account was requested
        $create_parent = !empty($_POST['create_parent_account']);
        if ($create_parent) {
            $parent_username = $form['student_id'];
            
            // Ensure username uniqueness
            $existing_u = db_fetch_one("SELECT id FROM users WHERE username = ? LIMIT 1", 's', [$parent_username]);
            if ($existing_u) {
                $parent_username = $form['student_id'] . '_p';
            }

            // Generate secure temporary password
            $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $rand_code = '';
            for ($i = 0; $i < 6; $i++) {
                $rand_code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $temp_password = 'AMS-' . $rand_code;
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $parent_role_row = db_fetch_one("SELECT id FROM roles WHERE LOWER(name) = 'parent' LIMIT 1");
            $parent_role_id = $parent_role_row ? (int)$parent_role_row['id'] : 3;

            $parent_name = !empty($form['father_name']) ? $form['father_name'] : ($form['first_name'] . ' ' . $form['last_name'] . ' Parent');
            $parent_email = !empty($form['email']) ? $form['email'] : null;

            db_execute(
                "INSERT INTO users (username, name, email, password, role, role_id, status) VALUES (?, ?, ?, ?, 'parent', ?, 'active')",
                'ssssi',
                [$parent_username, $parent_name, $parent_email, $password_hash, $parent_role_id]
            );
            $new_parent_user_id = db_last_id();

            // Link parent_user_id to student immediately
            db_execute("UPDATE students SET parent_user_id = ? WHERE id = ?", 'ii', [$new_parent_user_id, $new_student_db_id]);

            log_activity(
                (int)$_SESSION['user_id'],
                $_SESSION['role'],
                'Auto-created parent account',
                'user',
                $new_parent_user_id,
                "Auto-created parent login '{$parent_username}' for new student '{$form['student_id']}' ({$form['first_name']} {$form['last_name']})."
            );

            $_SESSION['new_parent_credential'] = [
                'student_id'   => $form['student_id'],
                'student_name' => $form['first_name'] . ' ' . $form['last_name'],
                'username'     => $parent_username,
                'password'     => $temp_password,
            ];
        }

        set_flash('success', 'Student "' . $form['first_name'] . ' ' . $form['last_name'] . '" added successfully!');
        redirect('../students/student_records.php');
    }
}

include '../layout.php';
?>

<div class="card-ams">
    <div class="card-header-ams">
        <h5><i class="fas fa-user-graduate text-primary"></i> Add New Student</h5>
        <a href="student_records.php" class="btn-ams btn-secondary-ams btn-sm-ams">
            <i class="fas fa-arrow-left"></i> Back to Records
        </a>
    </div>
    <div class="card-body-ams">

        <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="add_student.php" enctype="multipart/form-data" data-validate id="addStudentForm">

            <!-- Personal Info -->
            <div class="form-section-title"><i class="fas fa-id-card"></i> Personal Information</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Student ID <span class="req">*</span></label>
                    <input type="text" name="student_id" class="form-control-ams" required
                           value="<?= h($form['student_id'] ?? $next_id) ?>" placeholder="STU-001">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">First Name <span class="req">*</span></label>
                    <input type="text" name="first_name" class="form-control-ams" required
                           value="<?= h($form['first_name'] ?? '') ?>" placeholder="Enter first name">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name" class="form-control-ams" required
                           value="<?= h($form['last_name'] ?? '') ?>" placeholder="Enter last name">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Father's Name <span class="req">*</span></label>
                    <input type="text" name="father_name" class="form-control-ams" required
                           value="<?= h($form['father_name'] ?? '') ?>" placeholder="Enter father's name">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Gender <span class="req">*</span></label>
                    <select name="gender" class="form-control-ams" required>
                        <option value="">-- Select Gender --</option>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($form['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control-ams"
                           value="<?= h($form['date_of_birth'] ?? '') ?>">
                </div>
            </div>

            <!-- Contact Info -->
            <div class="form-section-title"><i class="fas fa-phone"></i> Contact Information</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Phone <span class="req">*</span></label>
                    <input type="text" name="phone" class="form-control-ams" required
                           value="<?= h($form['phone'] ?? '') ?>" placeholder="+92-300-0000000">
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Email</label>
                    <input type="email" name="email" class="form-control-ams"
                           value="<?= h($form['email'] ?? '') ?>" placeholder="student@example.com">
                </div>
                <div class="form-group" style="grid-column: span 1;">
                    <label class="form-label-ams">Address</label>
                    <textarea name="address" class="form-control-ams" rows="2"
                              placeholder="Full address"><?= h($form['address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Academic Info -->
            <div class="form-section-title"><i class="fas fa-school"></i> Academic Information</div>
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label-ams">Class <span class="req">*</span></label>
                    <select name="class_id" class="form-control-ams" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($form['class_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                            <?= h($c['class_name'] . ' - Section ' . $c['section']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Admission Date <span class="req">*</span></label>
                    <input type="date" name="admission_date" class="form-control-ams" required
                           value="<?= h($form['admission_date'] ?? date('Y-m-d')) ?>" data-default-today>
                </div>
                <div class="form-group">
                    <label class="form-label-ams">Status</label>
                    <div class="radio-group">
                        <label class="radio-pill">
                            <input type="radio" name="status" value="active"
                                   <?= ($form['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
                            <i class="fas fa-check-circle text-success"></i> Active
                        </label>
                        <label class="radio-pill">
                            <input type="radio" name="status" value="inactive"
                                   <?= ($form['status'] ?? '') === 'inactive' ? 'checked' : '' ?>>
                            <i class="fas fa-times-circle text-secondary"></i> Inactive
                        </label>
                    </div>
                </div>
            </div>

            <!-- Photo -->
            <div class="form-section-title"><i class="fas fa-camera"></i> Student Photo</div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label-ams">Upload Photo (JPG/PNG, max 2MB)</label>
                    <div class="file-upload-area">
                        <img id="photoPreview" src="#" alt="Preview" class="photo-preview"
                             style="display:none;width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 10px;">
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/gif"
                               data-photo-input="photoPreview">
                        <div>
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0" style="font-size:.85rem;">Click to upload photo</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parent Portal Account (Optional) -->
            <div class="form-section-title"><i class="fas fa-user-friends"></i> Parent Portal Account (Optional)</div>
            <div class="p-3 bg-light rounded-3 mb-4 border">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="createParentAccount" name="create_parent_account" value="1" <?= (!empty($_POST['create_parent_account'])) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold text-dark" for="createParentAccount">
                        Create a parent account for this student?
                    </label>
                </div>
                <p class="text-muted small mb-0">
                    If checked, a parent login is auto-generated with <strong>Username = Student ID</strong> and a secure temporary password, and linked immediately to this student record. Login credentials will be displayed on screen once upon enrollment.
                </p>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-3 mt-4 flex-wrap">
                <button type="submit" class="btn-ams btn-primary-ams">
                    <i class="fas fa-save"></i> Save Student
                </button>
                <button type="reset" class="btn-ams btn-secondary-ams">
                    <i class="fas fa-redo"></i> Reset Form
                </button>
                <a href="student_records.php" class="btn-ams btn-secondary-ams">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../layout_end.php'; ?>
