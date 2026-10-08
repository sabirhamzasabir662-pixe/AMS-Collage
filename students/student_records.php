<?php
/**
 * students/student_records.php
 * Arsalan Model School and College Management System
 * Phase 3: Students Module
 */

require_once __DIR__ . '/../database.php';
require_login();
require_permission('students.view');

$page_title  = 'Student Records';
$active_menu = 'students';
$depth       = '../';

// Search Query Filter
$search = trim($_GET['search'] ?? '');

// Pagination Setup
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Count Query using Prepared Statements
$count_sql = "SELECT COUNT(*) AS total 
              FROM students s 
              LEFT JOIN classes c ON s.class_id = c.id";
$count_params = [];
$count_types  = "";

if ($search !== '') {
    $count_sql .= " WHERE (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.email LIKE ?)";
    $like = '%' . $search . '%';
    $count_params = [$like, $like, $like, $like, $like];
    $count_types  = "sssss";
}

$count_row = db_fetch_one($count_sql, $count_types, $count_params);
$total_students = (int)($count_row['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_students / $per_page));
if ($page > $total_pages && $total_students > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Fetch Students Data using Prepared Statements
$data_sql = "SELECT s.*, c.class_name, c.section 
             FROM students s 
             LEFT JOIN classes c ON s.class_id = c.id";
$data_params = [];
$data_types  = "";

if ($search !== '') {
    $data_sql .= " WHERE (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.email LIKE ?)";
    $like = '%' . $search . '%';
    $data_params = [$like, $like, $like, $like, $like];
    $data_types  = "sssss";
}

$data_sql .= " ORDER BY s.id DESC LIMIT ? OFFSET ?";
$data_params[] = $per_page;
$data_params[] = $offset;
$data_types   .= "ii";

$students = db_fetch_all($data_sql, $data_types, $data_params);

// Total active students count for overall badge
$active_total_row = db_fetch_one("SELECT COUNT(*) AS total FROM students WHERE status = 'active'");
$total_active = (int)($active_total_row['total'] ?? 0);

require_once __DIR__ . '/../layout.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= $dashboard_link ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Students</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <h2 class="h3 fw-bold text-dark mb-0">
                <i class="bi bi-people-fill text-primary me-2"></i> Student Directory
            </h2>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                Total: <?= number_format($total_students); ?> <?= ($search !== '') ? 'Found' : 'Enrolled'; ?>
            </span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                Active: <?= number_format($total_active); ?>
            </span>
        </div>
    </div>
    <?php if (has_permission('students.add')): ?>
    <div>
        <a href="add_student.php" class="btn btn-primary shadow-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Enroll New Student
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['new_parent_credential'])): 
    $cred = $_SESSION['new_parent_credential'];
    unset($_SESSION['new_parent_credential']);
?>
<!-- Parent Account Credentials One-Time Display Banner -->
<div class="alert alert-success alert-dismissible fade show border-2 shadow-sm mb-4" role="alert" style="border-left: 5px solid #198754 !important;">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-shield-check fs-2 text-success me-3"></i>
        <div>
            <h5 class="alert-heading mb-0 fw-bold">Parent Portal Account Generated &amp; Linked!</h5>
            <small class="text-muted">A parent account has been created and linked to this student. Note or copy the temporary password below — it is displayed only once.</small>
        </div>
    </div>
    <div class="bg-white p-3 rounded-3 border mt-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <span class="text-muted small d-block">Child / Student:</span>
                <strong class="text-dark"><?= h($cred['student_name']) ?> <span class="badge bg-light text-muted border font-monospace"><?= h($cred['student_id']) ?></span></strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Parent Username:</span>
                <span class="badge bg-light text-primary border font-monospace fs-6 px-2 py-1"><?= h($cred['username']) ?></span>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Temporary Password:</span>
                <span class="badge bg-warning bg-opacity-25 text-dark border border-warning font-monospace fs-6 px-2 py-1"><?= h($cred['password']) ?></span>
            </div>
            <div class="col-md-2 text-md-end">
                <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btnCopyCreds"
                        onclick="navigator.clipboard.writeText('Student: <?= h(addslashes($cred['student_name'])) ?> (<?= h($cred['student_id']) ?>)\nUsername: <?= h($cred['username']) ?>\nPassword: <?= h($cred['password']) ?>'); this.innerHTML='<i class=\'bi bi-check2 me-1\'></i> Copied!';">
                    <i class="bi bi-clipboard me-1"></i> Copy Login
                </button>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="student_records.php" class="row g-2 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control border-start-0 ps-0" 
                           name="search" 
                           placeholder="Search by student ID, full name, or email..." 
                           value="<?= h($search); ?>" 
                           autofocus>
                    <button class="btn btn-primary" type="submit">
                        Search
                    </button>
                    <?php if ($search !== ''): ?>
                        <a href="student_records.php" class="btn btn-outline-secondary" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 col-lg-6 text-md-end text-muted small">
                Showing <strong><?= count($students); ?></strong> of <strong><?= $total_students; ?></strong> records
                <?php if ($search !== ''): ?>
                    matching "<em><?= h($search); ?></em>"
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Students Records Table Card -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.03em;">
                <tr>
                    <th class="ps-3 py-3" style="width: 70px;">Photo</th>
                    <th class="py-3">Student ID</th>
                    <th class="py-3">Name</th>
                    <th class="py-3">Father's Name</th>
                    <th class="py-3">Class</th>
                    <th class="py-3">Phone</th>
                    <th class="py-3 text-center">Status</th>
                    <th class="pe-3 py-3 text-end" style="min-width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                            <h6 class="fw-semibold">No students found</h6>
                            <p class="small mb-3">
                                <?= ($search !== '') ? 'Try refining your search query.' : 'Get started by enrolling your first student.'; ?>
                            </p>
                            <?php if ($search !== ''): ?>
                                <a href="student_records.php" class="btn btn-sm btn-outline-primary">View All Students</a>
                            <?php else: ?>
                                <a href="add_student.php" class="btn btn-sm btn-primary">Enroll Student</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $stu): ?>
                        <tr>
                            <!-- Photo Thumbnail -->
                            <td class="ps-3">
                                <?php if (!empty($stu['photo']) && file_exists(__DIR__ . '/../uploads/students/' . $stu['photo'])): ?>
                                    <img src="../uploads/students/<?= h($stu['photo']); ?>" 
                                         alt="<?= h($stu['first_name']); ?>" 
                                         class="rounded-circle shadow-sm" 
                                         style="width: 42px; height: 42px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                         style="width: 42px; height: 42px; font-size: 0.9rem;">
                                        <?= strtoupper(substr($stu['first_name'], 0, 1) . substr($stu['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Student ID -->
                            <td>
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                    <?= h($stu['student_id']); ?>
                                </span>
                            </td>

                            <!-- Full Name & Email -->
                            <td>
                                <div class="fw-bold text-dark">
                                    <?= h($stu['first_name'] . ' ' . $stu['last_name']); ?>
                                </div>
                                <?php if (!empty($stu['email'])): ?>
                                    <div class="text-muted small"><?= h($stu['email']); ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Father's Name -->
                            <td class="text-secondary">
                                <?= h($stu['father_name']); ?>
                            </td>

                            <!-- Class & Section -->
                            <td>
                                <?php if (!empty($stu['class_name'])): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                        <?= h($stu['class_name']); ?><?= !empty($stu['section']) ? ' - ' . h($stu['section']) : ''; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Not Assigned</span>
                                <?php endif; ?>
                            </td>

                            <!-- Phone -->
                            <td class="text-secondary small">
                                <i class="bi bi-telephone text-muted me-1"></i><?= h($stu['phone']); ?>
                            </td>

                            <!-- Status -->
                            <td class="text-center">
                                <?php if ($stu['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check-circle me-1"></i> Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                        <i class="bi bi-dash-circle me-1"></i> Inactive
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <!-- View Button (triggers modal) -->
                                    <button type="button" 
                                            class="btn btn-outline-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewStudentModal<?= (int)$stu['id']; ?>" 
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <!-- Edit Button -->
                                    <?php if (has_permission('students.edit')): ?>
                                    <a href="edit_student.php?id=<?= (int)$stu['id']; ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Edit Student">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>

                                    <!-- Delete Button with Confirmation -->
                                    <?php if (has_permission('students.delete')): ?>
                                    <a href="delete_student.php?id=<?= (int)$stu['id']; ?>" 
                                       class="btn btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete student \'<?= h(addslashes($stu['first_name'] . ' ' . $stu['last_name'])); ?>\' (<?= h($stu['student_id']); ?>)? This action cannot be undone.');" 
                                       title="Delete Student">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>

                                <!-- View Student Modal -->
                                <div class="modal fade text-start" id="viewStudentModal<?= (int)$stu['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?= (int)$stu['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-light border-bottom">
                                                <h5 class="modal-content-title modal-title fw-bold text-dark" id="viewModalLabel<?= (int)$stu['id']; ?>">
                                                    <i class="bi bi-person-badge text-primary me-2"></i> Student Details
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="text-center mb-4">
                                                    <?php if (!empty($stu['photo']) && file_exists(__DIR__ . '/../uploads/students/' . $stu['photo'])): ?>
                                                        <img src="../uploads/students/<?= h($stu['photo']); ?>" 
                                                             alt="<?= h($stu['first_name']); ?>" 
                                                             class="rounded-circle shadow border p-1" 
                                                             style="width: 100px; height: 100px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                                             style="width: 100px; height: 100px; font-size: 2rem;">
                                                            <?= strtoupper(substr($stu['first_name'], 0, 1) . substr($stu['last_name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <h5 class="fw-bold mt-2 mb-0"><?= h($stu['first_name'] . ' ' . $stu['last_name']); ?></h5>
                                                    <span class="badge bg-light text-dark border font-monospace"><?= h($stu['student_id']); ?></span>
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm table-borderless mb-0">
                                                        <tbody>
                                                            <tr>
                                                                <th class="text-muted w-40">Father's Name:</th>
                                                                <td class="fw-semibold text-dark"><?= h($stu['father_name']); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Class &amp; Section:</th>
                                                                <td><?= h($stu['class_name'] ?? 'N/A'); ?><?= !empty($stu['section']) ? ' - ' . h($stu['section']) : ''; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Gender:</th>
                                                                <td><?= h($stu['gender']); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Date of Birth:</th>
                                                                <td><?= !empty($stu['date_of_birth']) ? date('M d, Y', strtotime($stu['date_of_birth'])) : 'Not recorded'; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Admission Date:</th>
                                                                <td><?= !empty($stu['admission_date']) ? date('M d, Y', strtotime($stu['admission_date'])) : 'Not recorded'; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Phone Number:</th>
                                                                <td><?= h($stu['phone']); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Email Address:</th>
                                                                <td><?= !empty($stu['email']) ? h($stu['email']) : 'Not provided'; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Residential Address:</th>
                                                                <td><?= !empty($stu['address']) ? nl2br(h($stu['address'])) : 'Not provided'; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Status:</th>
                                                                <td>
                                                                    <?php if ($stu['status'] === 'active'): ?>
                                                                        <span class="badge bg-success">Active</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">Inactive</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <a href="edit_student.php?id=<?= (int)$stu['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil me-1"></i> Edit Student
                                                </a>
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                Showing page <strong><?= $page; ?></strong> of <strong><?= $total_pages; ?></strong> (<?= $total_students; ?> total students)
            </div>
            <nav aria-label="Student records pagination">
                <ul class="pagination pagination-sm mb-0">
                    <!-- Prev Page -->
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?= $page - 1; ?><?= ($search !== '') ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                            &laquo;
                        </a>
                    </li>

                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?= $i; ?><?= ($search !== '') ? '&search=' . urlencode($search) : ''; ?>">
                                <?= $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <!-- Next Page -->
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?= $page + 1; ?><?= ($search !== '') ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                            &raquo;
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layout_end.php';
?>
