<?php
/**
 * parent/profile.php — Child Profile View for Parent
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/parent_helper.php';

require_login();
require_role(['parent']);
require_permission('students.view');

$user = current_user();
$student = get_parent_student($user['id']);

$page_title  = 'Child Profile';
$active_menu = 'parent_profile';
$breadcrumb  = ['Parent Portal', 'Child Profile'];
$depth       = '../';

include '../layout.php';
?>

<?php if (!$student): ?>
    <div class="card-ams p-5 text-center mb-4">
        <div class="mb-3 text-warning">
            <i class="fas fa-exclamation-triangle fa-3x"></i>
        </div>
        <h4 class="fw-bold text-navy">No Student Linked</h4>
        <p class="text-muted">No student record is linked to this parent login.</p>
    </div>
<?php else: ?>

    <div class="row g-4">
        <!-- Left Column: Identity Card -->
        <div class="col-md-4">
            <div class="card-ams p-4 text-center">
                <div class="position-relative d-inline-block mb-3">
                    <div class="rounded-circle bg-light border shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; overflow: hidden;">
                        <?php if (!empty($student['photo']) && file_exists('../uploads/students/' . $student['photo'])): ?>
                            <img src="../uploads/students/<?= h($student['photo']) ?>" alt="Photo" class="w-100 h-100 object-fit-cover">
                        <?php else: ?>
                            <i class="fas fa-user-graduate fa-4x text-secondary"></i>
                        <?php endif; ?>
                    </div>
                    <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-<?= $student['status'] === 'active' ? 'success' : 'secondary' ?> border border-white">
                        <?= ucfirst($student['status']) ?>
                    </span>
                </div>

                <h4 class="fw-bold text-navy mb-1"><?= h($student['first_name'] . ' ' . $student['last_name']) ?></h4>
                <div class="text-muted small mb-3">Student Roll No: <strong><?= h($student['student_id']) ?></strong></div>

                <div class="badge bg-primary-subtle text-primary fs-6 px-3 py-2 mb-3">
                    <?= h($student['class_name'] ?? 'Unassigned') ?><?= !empty($student['section']) ? ' &bull; Section ' . h($student['section']) : '' ?>
                </div>

                <div class="border-top pt-3 text-start small">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Admission Date:</span>
                        <strong><?= !empty($student['admission_date']) ? date('M d, Y', strtotime($student['admission_date'])) : 'N/A' ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Guardian Account:</span>
                        <strong><?= h($user['username']) ?></strong>
                    </div>
                </div>
            </div>

            <!-- Helpful Notice -->
            <div class="card-ams p-3 mt-3 bg-light border">
                <div class="d-flex gap-2">
                    <i class="fas fa-info-circle text-primary mt-1"></i>
                    <small class="text-muted">
                        Official student records can only be updated by the School Administration. 
                        If any information above is incorrect, please submit a request to the school registrar.
                    </small>
                </div>
            </div>
        </div>

        <!-- Right Column: Detailed Particulars -->
        <div class="col-md-8">
            <div class="card-ams">
                <div class="card-header-ams">
                    <span class="fw-bold text-navy">
                        <i class="fas fa-id-card me-2 text-primary"></i>Enrolled Student Details
                    </span>
                    <span class="badge bg-secondary-subtle text-dark border">Official Record</span>
                </div>
                <div class="card-body-ams p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Full Name</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border"><?= h($student['first_name'] . ' ' . $student['last_name']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Father's / Guardian's Name</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border"><?= h($student['father_name'] ?: 'N/A') ?></div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Gender</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border"><?= h($student['gender'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Date of Birth</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border">
                                <?= !empty($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : 'N/A' ?>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Assigned Class</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border"><?= h($student['class_name'] ?? 'Unassigned') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Section</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border"><?= h($student['section'] ?: 'Default') ?></div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Emergency Contact Phone</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border">
                                <i class="fas fa-phone me-1 text-muted"></i><?= h($student['phone'] ?: 'N/A') ?>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Registered Email</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border">
                                <i class="fas fa-envelope me-1 text-muted"></i><?= h($student['email'] ?: 'N/A') ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Residential Address</label>
                            <div class="fw-semibold text-dark p-2 bg-light rounded border">
                                <i class="fas fa-map-marker-alt me-1 text-muted"></i><?= h($student['address'] ?: 'N/A') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php
include '../layout_end.php';
?>
