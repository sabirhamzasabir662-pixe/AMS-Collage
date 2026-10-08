<?php
/**
 * teachers/add_teacher.php
 * Arsalan Model School and College Management System
 * Phase 4: Teachers Module
 */

require_once __DIR__ . '/../database.php';
require_login();

$page_title  = 'Add New Teacher';
$active_menu = 'teachers';
$depth       = '../';

// Load active subjects for dropdown (with class info if available)
$subjects = db_fetch_all(
    "SELECT s.id, s.subject_name, s.subject_code, c.class_name, c.section 
     FROM subjects s 
     LEFT JOIN classes c ON s.class_id = c.id 
     WHERE s.status = 'active' 
     ORDER BY s.subject_name ASC"
);

// Auto-generate next teacher ID (e.g. TCH-004)
$auto_teacher_id = generate_next_teacher_id();

$errors = [];
$form = [
    'teacher_id'    => $auto_teacher_id,
    'first_name'    => '',
    'last_name'     => '',
    'gender'        => 'Male',
    'date_of_birth' => '',
    'phone'         => '',
    'email'         => '',
    'address'       => '',
    'joining_date'  => date('Y-m-d'),
    'subject_id'    => '',
    'qualification' => '',
    'experience'    => '0',
    'salary'        => '',
    'status'        => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['teacher_id']    = trim($_POST['teacher_id'] ?? '');
    $form['first_name']    = trim($_POST['first_name'] ?? '');
    $form['last_name']     = trim($_POST['last_name'] ?? '');
    $form['gender']        = trim($_POST['gender'] ?? '');
    $form['date_of_birth'] = trim($_POST['date_of_birth'] ?? '');
    $form['phone']         = trim($_POST['phone'] ?? '');
    $form['email']         = trim($_POST['email'] ?? '');
    $form['address']       = trim($_POST['address'] ?? '');
    $form['joining_date']  = trim($_POST['joining_date'] ?? date('Y-m-d'));
    $form['subject_id']    = (int)($_POST['subject_id'] ?? 0);
    $form['qualification'] = trim($_POST['qualification'] ?? '');
    $form['experience']    = trim($_POST['experience'] ?? '0');
    $form['salary']        = trim($_POST['salary'] ?? '');
    $form['status']        = trim($_POST['status'] ?? 'active');

    // Server-side validation
    if ($form['teacher_id'] === '') {
        $errors[] = 'Teacher ID is required.';
    }
    if ($form['first_name'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($form['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }
    if (!in_array($form['gender'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }
    if ($form['phone'] === '') {
        $errors[] = 'Phone number is required.';
    }
    if ($form['joining_date'] === '') {
        $errors[] = 'Joining date is required.';
    }
    if ($form['qualification'] === '') {
        $errors[] = 'Qualification is required.';
    }
    if (!is_numeric($form['experience']) || (int)$form['experience'] < 0) {
        $errors[] = 'Experience must be a positive number (in years).';
    }
    if ($form['salary'] === '' || !is_numeric($form['salary']) || (float)$form['salary'] < 0) {
        $errors[] = 'Please enter a valid positive salary amount.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The email address is invalid.';
    }
    if (!in_array($form['status'], ['active', 'inactive'], true)) {
        $form['status'] = 'active';
    }

    // Check unique teacher_id
    if ($form['teacher_id'] !== '') {
        $chk = db_fetch_one("SELECT id FROM teachers WHERE teacher_id = ? LIMIT 1", [$form['teacher_id']], 's');
        if ($chk) {
            $errors[] = "Teacher ID '{$form['teacher_id']}' is already in use. Please choose another.";
        }
    }

    // Handle Photo Upload
    $photo_filename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading photo (code: ' . $file['error'] . ').';
        } else {
            // Check file size (max 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Teacher photo must be under 2MB.';
            }

            // Check file extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $errors[] = 'Only JPG, JPEG, and PNG image files are allowed.';
            }

            // Verify MIME type
            $image_info = @getimagesize($file['tmp_name']);
            if (!$image_info || !in_array($image_info['mime'], ['image/jpeg', 'image/png'], true)) {
                $errors[] = 'The uploaded file is not a valid JPEG or PNG image.';
            }

            if (empty($errors)) {
                $upload_dir = __DIR__ . '/../uploads/teachers/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $photo_filename = 'teacher_' . uniqid('', true) . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . $photo_filename;

                if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                    $errors[] = 'Failed to move uploaded photo to storage directory.';
                    $photo_filename = null;
                }
            }
        }
    }

    // Insert into database if validation passes
    if (empty($errors)) {
        $dob = ($form['date_of_birth'] !== '') ? $form['date_of_birth'] : null;
        $subject_id_val = ($form['subject_id'] > 0) ? $form['subject_id'] : null;
        $exp = (int)$form['experience'];
        $sal = (float)$form['salary'];

        $insert_sql = "INSERT INTO teachers 
            (teacher_id, first_name, last_name, gender, date_of_birth, phone, email, address, joining_date, subject_id, qualification, experience, salary, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $form['teacher_id'],
            $form['first_name'],
            $form['last_name'],
            $form['gender'],
            $dob,
            $form['phone'],
            $form['email'],
            $form['address'],
            $form['joining_date'],
            $subject_id_val,
            $form['qualification'],
            $exp,
            $sal,
            $photo_filename,
            $form['status'],
        ];

        db_execute($insert_sql, $params, 'sssssssssisidss');

        set_flash('success', "Teacher '{$form['first_name']} {$form['last_name']}' ({$form['teacher_id']}) added successfully!");
        redirect('teacher_records.php');
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
                <li class="breadcrumb-item"><a href="teacher_records.php" class="text-decoration-none">Teachers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Teacher</li>
            </ol>
        </nav>
        <h2 class="h3 fw-bold text-dark mb-0">
            <i class="bi bi-person-plus-fill text-success me-2"></i> Register New Teacher
        </h2>
    </div>
    <div>
        <a href="teacher_records.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Teacher Records
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

<!-- Teacher Registration Form Card -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="fw-bold text-dark mb-0">Teacher Information</h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="add_teacher.php" enctype="multipart/form-data" novalidate>
            
            <div class="row g-3">
                <!-- Section 1: Professional Identification -->
                <div class="col-12">
                    <h6 class="text-success fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-award me-1"></i> Professional Identification &amp; Assignment
                    </h6>
                </div>

                <div class="col-md-4">
                    <label for="teacher_id" class="form-label fw-semibold">Teacher ID <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                        <input type="text" 
                               class="form-control" 
                               id="teacher_id" 
                               name="teacher_id" 
                               value="<?= h($form['teacher_id']); ?>" 
                               required>
                    </div>
                    <div class="form-text">Auto-generated sequential faculty identifier.</div>
                </div>

                <div class="col-md-4">
                    <label for="subject_id" class="form-label fw-semibold">Primary Teaching Subject</label>
                    <select class="form-select" id="subject_id" name="subject_id">
                        <option value="">-- No Subject Assigned / General --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int)$s['id']; ?>" <?= ((string)$form['subject_id'] === (string)$s['id']) ? 'selected' : ''; ?>>
                                <?= h($s['subject_name']); ?> (<?= h($s['subject_code']); ?>)
                                <?php if (!empty($s['class_name'])): ?>
                                    - <?= h($s['class_name']); ?><?= !empty($s['section']) ? ' ' . h($s['section']) : ''; ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Select subject from the curriculum directory.</div>
                </div>

                <div class="col-md-4">
                    <label for="joining_date" class="form-label fw-semibold">Joining Date <span class="text-danger">*</span></label>
                    <input type="date" 
                           class="form-control" 
                           id="joining_date" 
                           name="joining_date" 
                           value="<?= h($form['joining_date']); ?>" 
                           required>
                </div>

                <!-- Section 2: Personal Profile -->
                <div class="col-12 mt-4">
                    <h6 class="text-success fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-person-badge me-1"></i> Personal Profile
                    </h6>
                </div>

                <div class="col-md-4">
                    <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="first_name" 
                           name="first_name" 
                           placeholder="e.g. Tariq" 
                           value="<?= h($form['first_name']); ?>" 
                           required>
                </div>

                <div class="col-md-4">
                    <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="last_name" 
                           name="last_name" 
                           placeholder="e.g. Mehmood" 
                           value="<?= h($form['last_name']); ?>" 
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
                    <label for="qualification" class="form-label fw-semibold">Qualification <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="qualification" 
                           name="qualification" 
                           placeholder="e.g. M.Sc Mathematics, B.Ed" 
                           value="<?= h($form['qualification']); ?>" 
                           required>
                </div>

                <div class="col-md-4">
                    <label for="status" class="form-label fw-semibold">Faculty Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="active" <?= ($form['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= ($form['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <!-- Section 3: Compensation & Experience -->
                <div class="col-12 mt-4">
                    <h6 class="text-success fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-briefcase me-1"></i> Experience &amp; Compensation
                    </h6>
                </div>

                <div class="col-md-6">
                    <label for="experience" class="form-label fw-semibold">Teaching Experience (Years) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-calendar-range"></i></span>
                        <input type="number" 
                               class="form-control" 
                               id="experience" 
                               name="experience" 
                               min="0" 
                               max="60" 
                               placeholder="e.g. 5" 
                               value="<?= h($form['experience']); ?>" 
                               required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="salary" class="form-label fw-semibold">Monthly Salary (PKR) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">Rs.</span>
                        <input type="number" 
                               class="form-control" 
                               id="salary" 
                               name="salary" 
                               min="0" 
                               step="0.01" 
                               placeholder="e.g. 45000.00" 
                               value="<?= h($form['salary']); ?>" 
                               required>
                    </div>
                </div>

                <!-- Section 4: Contact & Residence -->
                <div class="col-12 mt-4">
                    <h6 class="text-success fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-telephone me-1"></i> Contact &amp; Residence
                    </h6>
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                        <input type="text" 
                               class="form-control" 
                               id="phone" 
                               name="phone" 
                               placeholder="e.g. 0300-1234567" 
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
                               placeholder="teacher@arsalan.edu.pk" 
                               value="<?= h($form['email']); ?>">
                    </div>
                </div>

                <div class="col-12">
                    <label for="address" class="form-label fw-semibold">Residential Address</label>
                    <textarea class="form-control" 
                              id="address" 
                              name="address" 
                              rows="2" 
                              placeholder="Street, City, District"><?= h($form['address']); ?></textarea>
                </div>

                <!-- Section 5: Photo Upload -->
                <div class="col-12 mt-4">
                    <h6 class="text-success fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-camera me-1"></i> Teacher Photograph
                    </h6>
                </div>

                <div class="col-md-8">
                    <label for="photo" class="form-label fw-semibold">Upload Photo</label>
                    <input class="form-control" 
                           type="file" 
                           id="photo" 
                           name="photo" 
                           accept=".jpg,.jpeg,.png">
                    <div class="form-text">Accepted formats: JPG, JPEG, PNG. Max size: 2MB.</div>
                </div>

                <div class="col-md-4 text-center">
                    <div class="border rounded p-2 bg-light d-inline-block">
                        <div id="photoPreviewPlaceholder" class="text-muted small py-4 px-3">
                            <i class="bi bi-person-bounding-box fs-1 d-block text-secondary mb-1"></i>
                            <span>Photo Preview</span>
                        </div>
                        <img id="photoPreview" src="" alt="Preview" class="img-fluid rounded d-none" style="max-height: 120px; max-width: 120px; object-fit: cover;">
                    </div>
                </div>

                <!-- Form Submit Buttons -->
                <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-success px-4 py-2 fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Save &amp; Register Teacher
                    </button>
                    <a href="teacher_records.php" class="btn btn-light border px-4 py-2">
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
            placeholder.classList.add('d-none');
        };
        reader.readAsDataURL(file);
    } else {
        preview.src = '';
        preview.classList.add('d-none');
        placeholder.classList.remove('d-none');
    }
});
</script>
HTML;

require_once __DIR__ . '/../layout_end.php';
?>
