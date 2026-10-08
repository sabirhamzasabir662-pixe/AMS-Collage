<?php
/**
 * students/edit_student.php
 * Arsalan Model School and College Management System
 * Phase 3: Students Module
 */

require_once __DIR__ . '/../database.php';
require_login();

$page_title  = 'Edit Student';
$active_menu = 'students';
$depth       = '../';

// Retrieve student by ID from GET/POST parameter
$student_primary_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($student_primary_id <= 0) {
    set_flash('error', 'Invalid student ID specified.');
    redirect('student_records.php');
}

// Fetch existing student record using prepared statement
$student = db_fetch_one("SELECT * FROM students WHERE id = ? LIMIT 1", [$student_primary_id], 'i');

if (!$student) {
    set_flash('error', 'The requested student record does not exist.');
    redirect('student_records.php');
}

// Load active classes for dropdown in natural grade sequence
$classes = db_fetch_all("SELECT id, class_name, section FROM classes WHERE status = 'active' ORDER BY id ASC");

$errors = [];
$form = [
    'student_id'     => $student['student_id'],
    'first_name'     => $student['first_name'],
    'last_name'      => $student['last_name'],
    'father_name'    => $student['father_name'],
    'gender'         => $student['gender'],
    'date_of_birth'  => $student['date_of_birth'] ?? '',
    'phone'          => $student['phone'],
    'email'          => $student['email'] ?? '',
    'address'        => $student['address'] ?? '',
    'class_id'       => (int)$student['class_id'],
    'admission_date' => $student['admission_date'],
    'status'         => $student['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['student_id']     = trim($_POST['student_id'] ?? '');
    $form['first_name']     = trim($_POST['first_name'] ?? '');
    $form['last_name']      = trim($_POST['last_name'] ?? '');
    $form['father_name']    = trim($_POST['father_name'] ?? '');
    $form['gender']         = trim($_POST['gender'] ?? '');
    $form['date_of_birth']  = trim($_POST['date_of_birth'] ?? '');
    $form['phone']          = trim($_POST['phone'] ?? '');
    $form['email']          = trim($_POST['email'] ?? '');
    $form['address']        = trim($_POST['address'] ?? '');
    $form['class_id']       = (int)($_POST['class_id'] ?? 0);
    $form['admission_date'] = trim($_POST['admission_date'] ?? '');
    $form['status']         = trim($_POST['status'] ?? 'active');

    // Validations
    if ($form['student_id'] === '') {
        $errors[] = 'Student ID is required.';
    }
    if ($form['first_name'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($form['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }
    if ($form['father_name'] === '') {
        $errors[] = "Father's name is required.";
    }
    if (!in_array($form['gender'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }
    if ($form['phone'] === '') {
        $errors[] = 'Phone number is required.';
    }
    if ($form['class_id'] <= 0) {
        $errors[] = 'Please select a valid class.';
    }
    if ($form['admission_date'] === '') {
        $errors[] = 'Admission date is required.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The email address is invalid.';
    }
    if (!in_array($form['status'], ['active', 'inactive'], true)) {
        $form['status'] = 'active';
    }

    // Check unique student_id excluding current record
    if ($form['student_id'] !== '') {
        $chk = db_fetch_one("SELECT id FROM students WHERE student_id = ? AND id != ? LIMIT 1", [$form['student_id'], $student_primary_id], 'si');
        if ($chk) {
            $errors[] = "Student ID '{$form['student_id']}' is already assigned to another student.";
        }
    }

    // Handle Photo Upload (optional replacement)
    $photo_filename = $student['photo']; // Keep existing photo by default
    $upload_dir = __DIR__ . '/../uploads/students/';

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading replacement photo (code: ' . $file['error'] . ').';
        } else {
            // Check size (<= 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Replacement photo must be under 2MB.';
            }

            // Check extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $errors[] = 'Only JPG, JPEG, and PNG images are allowed.';
            }

            // Verify MIME
            $image_info = @getimagesize($file['tmp_name']);
            if (!$image_info || !in_array($image_info['mime'], ['image/jpeg', 'image/png'], true)) {
                $errors[] = 'The uploaded file is not a valid JPEG or PNG image.';
            }

            if (empty($errors)) {
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_photo_filename = 'student_' . uniqid('', true) . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . $new_photo_filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    // Delete old photo file if it exists
                    if (!empty($student['photo']) && file_exists($upload_dir . $student['photo'])) {
                        @unlink($upload_dir . $student['photo']);
                    }
                    $photo_filename = $new_photo_filename;
                } else {
                    $errors[] = 'Failed to save uploaded photo file.';
                }
            }
        }
    }

    // Save changes via UPDATE prepared statement
    if (empty($errors)) {
        $dob = ($form['date_of_birth'] !== '') ? $form['date_of_birth'] : null;

        $update_sql = "UPDATE students SET 
            student_id = ?,
            first_name = ?,
            last_name = ?,
            father_name = ?,
            gender = ?,
            date_of_birth = ?,
            phone = ?,
            email = ?,
            address = ?,
            class_id = ?,
            admission_date = ?,
            photo = ?,
            status = ?
            WHERE id = ?";

        $params = [
            $form['student_id'],
            $form['first_name'],
            $form['last_name'],
            $form['father_name'],
            $form['gender'],
            $dob,
            $form['phone'],
            $form['email'],
            $form['address'],
            $form['class_id'],
            $form['admission_date'],
            $photo_filename,
            $form['status'],
            $student_primary_id,
        ];

        db_execute($update_sql, $params, 'sssssssssisssi');

        set_flash('success', "Student record for '{$form['first_name']} {$form['last_name']}' updated successfully!");
        redirect('student_records.php');
    }
}

require_once __DIR__ . '/../layout.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="student_records.php" class="text-decoration-none">Students</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Student</li>
            </ol>
        </nav>
        <h2 class="h3 fw-bold text-dark mb-0">
            <i class="bi bi-pencil-square text-primary me-2"></i> Edit Student: <?= h($student['first_name'] . ' ' . $student['last_name']); ?>
        </h2>
    </div>
    <div>
        <a href="student_records.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Student Records
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i> Please correct the following errors:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Edit Student Form Card -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0">Edit Information</h5>
        <span class="badge bg-light text-dark border font-monospace">Record ID #<?= (int)$student['id']; ?></span>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="edit_student.php?id=<?= (int)$student['id']; ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id" value="<?= (int)$student['id']; ?>">

            <div class="row g-3">
                <!-- Academic Info -->
                <div class="col-12">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-mortarboard me-1"></i> Academic Identification
                    </h6>
                </div>

                <div class="col-md-4">
                    <label for="student_id" class="form-label fw-semibold">Student ID <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                        <input type="text" 
                               class="form-control" 
                               id="student_id" 
                               name="student_id" 
                               value="<?= h($form['student_id']); ?>" 
                               required>
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="class_id" class="form-label fw-semibold">Class / Section <span class="text-danger">*</span></label>
                    <select class="form-select" id="class_id" name="class_id" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int)$c['id']; ?>" <?= ((string)$form['class_id'] === (string)$c['id']) ? 'selected' : ''; ?>>
                                <?= h($c['class_name']); ?> - <?= h($c['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="admission_date" class="form-label fw-semibold">Admission Date <span class="text-danger">*</span></label>
                    <input type="date" 
                           class="form-control" 
                           id="admission_date" 
                           name="admission_date" 
                           value="<?= h($form['admission_date']); ?>" 
                           required>
                </div>

                <!-- Personal Info -->
                <div class="col-12 mt-4">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-person-badge me-1"></i> Personal Profile
                    </h6>
                </div>

                <div class="col-md-4">
                    <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="first_name" 
                           name="first_name" 
                           value="<?= h($form['first_name']); ?>" 
                           required>
                </div>

                <div class="col-md-4">
                    <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="last_name" 
                           name="last_name" 
                           value="<?= h($form['last_name']); ?>" 
                           required>
                </div>

                <div class="col-md-4">
                    <label for="father_name" class="form-label fw-semibold">Father's Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="father_name" 
                           name="father_name" 
                           value="<?= h($form['father_name']); ?>" 
                           required>
                </div>

                <div class="col-md-4">
                    <label for="gender" class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="Male" <?= ($form['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?= ($form['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?= ($form['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="date_of_birth" class="form-label fw-semibold">Date of Birth</label>
                    <input type="date" 
                           class="form-control" 
                           id="date_of_birth" 
                           name="date_of_birth" 
                           value="<?= h($form['date_of_birth']); ?>">
                </div>

                <div class="col-md-4">
                    <label for="status" class="form-label fw-semibold">Enrollment Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="active" <?= ($form['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= ($form['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <!-- Contact & Address -->
                <div class="col-12 mt-4">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-telephone me-1"></i> Contact Information &amp; Address
                    </h6>
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-phone"></i></span>
                        <input type="text" 
                               class="form-control" 
                               id="phone" 
                               name="phone" 
                               value="<?= h($form['phone']); ?>" 
                               required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?= h($form['email']); ?>">
                    </div>
                </div>

                <div class="col-12">
                    <label for="address" class="form-label fw-semibold">Residential Address</label>
                    <textarea class="form-control" 
                              id="address" 
                              name="address" 
                              rows="2"><?= h($form['address']); ?></textarea>
                </div>

                <!-- Photo Management -->
                <div class="col-12 mt-4">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-camera me-1"></i> Photo Management
                    </h6>
                </div>

                <div class="col-md-8">
                    <label for="photo" class="form-label fw-semibold">Replace Photo (Optional)</label>
                    <input class="form-control" 
                           type="file" 
                           id="photo" 
                           name="photo" 
                           accept=".jpg,.jpeg,.png">
                    <div class="form-text">Leave blank to keep current photo. Formats: JPG, PNG under 2MB.</div>
                </div>

                <div class="col-md-4 text-center">
                    <div class="border rounded p-2 bg-light d-inline-block">
                        <div class="small fw-semibold text-muted mb-1">Current / New Photo</div>
                        <?php if (!empty($student['photo']) && file_exists(__DIR__ . '/../uploads/students/' . $student['photo'])): ?>
                            <img id="photoPreview" 
                                 src="../uploads/students/<?= h($student['photo']); ?>" 
                                 alt="<?= h($student['first_name']); ?>" 
                                 class="img-fluid rounded" 
                                 style="max-height: 100px; max-width: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div id="photoPreviewPlaceholder" class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center fw-bold" 
                                 style="width: 80px; height: 80px; font-size: 1.5rem;">
                                <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                            </div>
                            <img id="photoPreview" src="" alt="Preview" class="img-fluid rounded d-none" style="max-height: 100px; max-width: 100px; object-fit: cover;">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submit buttons -->
                <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                        <i class="bi bi-save me-1"></i> Update Student
                    </button>
                    <a href="student_records.php" class="btn btn-light border px-4 py-2">
                        Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

<?php
$extra_js = <<<'HTML'
<script>
document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPreviewPlaceholder');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(evt) {
            preview.src = evt.target.result;
            preview.classList.remove('d-none');
            if (placeholder) placeholder.classList.add('d-none');
        };
        reader.readAsDataURL(file);
    }
});
</script>
HTML;

require_once __DIR__ . '/../layout_end.php';
?>
