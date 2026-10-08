<?php
/**
 * teachers/teacher_records.php
 * Arsalan Model School and College Management System
 * Phase 4: Teachers Module
 */

require_once __DIR__ . '/../database.php';
require_login();

$page_title  = 'Teacher Records';
$active_menu = 'teachers';
$depth       = '../';

// Search Query Filter
$search = trim($_GET['search'] ?? '');

// Pagination Setup
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Count Query using Prepared Statements
$count_sql = "SELECT COUNT(*) AS total 
              FROM teachers t 
              LEFT JOIN subjects s ON t.subject_id = s.id";
$count_params = [];
$count_types  = "";

if ($search !== '') {
    $count_sql .= " WHERE (t.teacher_id LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR s.subject_name LIKE ? OR s.subject_code LIKE ?)";
    $like = '%' . $search . '%';
    $count_params = [$like, $like, $like, $like, $like, $like];
    $count_types  = "ssssss";
}

$count_row = db_fetch_one($count_sql, $count_params, $count_types);
$total_teachers = (int)($count_row['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_teachers / $per_page));
if ($page > $total_pages && $total_teachers > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Fetch Teachers Data using Prepared Statements
$data_sql = "SELECT t.*, s.subject_name, s.subject_code, c.class_name, c.section 
             FROM teachers t 
             LEFT JOIN subjects s ON t.subject_id = s.id 
             LEFT JOIN classes c ON s.class_id = c.id";
$data_params = [];
$data_types  = "";

if ($search !== '') {
    $data_sql .= " WHERE (t.teacher_id LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR s.subject_name LIKE ? OR s.subject_code LIKE ?)";
    $like = '%' . $search . '%';
    $data_params = [$like, $like, $like, $like, $like, $like];
    $data_types  = "ssssss";
}

$data_sql .= " ORDER BY t.id DESC LIMIT ? OFFSET ?";
$data_params[] = $per_page;
$data_params[] = $offset;
$data_types   .= "ii";

$teachers = db_fetch_all($data_sql, $data_params, $data_types);

// Total active teachers count
$active_total_row = db_fetch_one("SELECT COUNT(*) AS total FROM teachers WHERE status = 'active'");
$total_active = (int)($active_total_row['total'] ?? 0);

require_once __DIR__ . '/../layout.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Teachers</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <h2 class="h3 fw-bold text-dark mb-0">
                <i class="bi bi-person-badge-fill text-success me-2"></i> Faculty Directory
            </h2>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                Total: <?= number_format($total_teachers); ?> <?= ($search !== '') ? 'Found' : 'Registered'; ?>
            </span>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                Active: <?= number_format($total_active); ?>
            </span>
        </div>
    </div>
    <div>
        <a href="add_teacher.php" class="btn btn-success shadow-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Add New Teacher
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="teacher_records.php" class="row g-2 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control border-start-0 ps-0" 
                           name="search" 
                           placeholder="Search by teacher ID, name, or subject..." 
                           value="<?= h($search); ?>" 
                           autofocus>
                    <button class="btn btn-success" type="submit">
                        Search
                    </button>
                    <?php if ($search !== ''): ?>
                        <a href="teacher_records.php" class="btn btn-outline-secondary" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 col-lg-6 text-md-end text-muted small">
                Showing <strong><?= count($teachers); ?></strong> of <strong><?= $total_teachers; ?></strong> faculty members
                <?php if ($search !== ''): ?>
                    matching "<em><?= h($search); ?></em>"
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Teachers Records Table Card -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.03em;">
                <tr>
                    <th class="ps-3 py-3" style="width: 70px;">Photo</th>
                    <th class="py-3">Teacher ID</th>
                    <th class="py-3">Name</th>
                    <th class="py-3">Subject</th>
                    <th class="py-3">Phone</th>
                    <th class="py-3">Salary</th>
                    <th class="py-3 text-center">Status</th>
                    <th class="pe-3 py-3 text-end" style="min-width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teachers)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-person-badge fs-1 d-block mb-2 text-secondary"></i>
                            <h6 class="fw-semibold">No teachers found</h6>
                            <p class="small mb-3">
                                <?= ($search !== '') ? 'Try refining your search query.' : 'Get started by adding your first faculty member.'; ?>
                            </p>
                            <?php if ($search !== ''): ?>
                                <a href="teacher_records.php" class="btn btn-sm btn-outline-success">View All Teachers</a>
                            <?php else: ?>
                                <a href="add_teacher.php" class="btn btn-sm btn-success">Add Teacher</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $tch): ?>
                        <tr>
                            <!-- Photo Thumbnail -->
                            <td class="ps-3">
                                <?php if (!empty($tch['photo']) && file_exists(__DIR__ . '/../uploads/teachers/' . $tch['photo'])): ?>
                                    <img src="../uploads/teachers/<?= h($tch['photo']); ?>" 
                                         alt="<?= h($tch['first_name']); ?>" 
                                         class="rounded-circle shadow-sm" 
                                         style="width: 42px; height: 42px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                         style="width: 42px; height: 42px; font-size: 0.9rem;">
                                        <?= strtoupper(substr($tch['first_name'], 0, 1) . substr($tch['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Teacher ID -->
                            <td>
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                    <?= h($tch['teacher_id']); ?>
                                </span>
                            </td>

                            <!-- Full Name & Email -->
                            <td>
                                <div class="fw-bold text-dark">
                                    <?= h($tch['first_name'] . ' ' . $tch['last_name']); ?>
                                </div>
                                <div class="text-muted small">
                                    <?= !empty($tch['qualification']) ? h($tch['qualification']) : h($tch['email']); ?>
                                </div>
                            </td>

                            <!-- Primary Subject -->
                            <td>
                                <?php if (!empty($tch['subject_name'])): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= h($tch['subject_name']); ?>
                                        <small>(<?= h($tch['subject_code']); ?>)</small>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">General / None</span>
                                <?php endif; ?>
                            </td>

                            <!-- Phone -->
                            <td class="text-secondary small">
                                <i class="bi bi-telephone text-muted me-1"></i><?= h($tch['phone']); ?>
                            </td>

                            <!-- Salary -->
                            <td class="fw-semibold text-dark">
                                Rs. <?= number_format((float)$tch['salary'], 2); ?>
                            </td>

                            <!-- Status -->
                            <td class="text-center">
                                <?php if ($tch['status'] === 'active'): ?>
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
                                            data-bs-target="#viewTeacherModal<?= (int)$tch['id']; ?>" 
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <!-- Edit Button -->
                                    <a href="edit_teacher.php?id=<?= (int)$tch['id']; ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Edit Teacher">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <!-- Delete Button with Confirmation -->
                                    <a href="delete_teacher.php?id=<?= (int)$tch['id']; ?>" 
                                       class="btn btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete teacher \'<?= h(addslashes($tch['first_name'] . ' ' . $tch['last_name'])); ?>\' (<?= h($tch['teacher_id']); ?>)? This action cannot be undone.');" 
                                       title="Delete Teacher">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>

                                <!-- View Teacher Modal -->
                                <div class="modal fade text-start" id="viewTeacherModal<?= (int)$tch['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?= (int)$tch['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-light border-bottom">
                                                <h5 class="modal-title fw-bold text-dark" id="viewModalLabel<?= (int)$tch['id']; ?>">
                                                    <i class="bi bi-person-badge text-success me-2"></i> Faculty Profile
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="text-center mb-4">
                                                    <?php if (!empty($tch['photo']) && file_exists(__DIR__ . '/../uploads/teachers/' . $tch['photo'])): ?>
                                                        <img src="../uploads/teachers/<?= h($tch['photo']); ?>" 
                                                             alt="<?= h($tch['first_name']); ?>" 
                                                             class="rounded-circle shadow border p-1" 
                                                             style="width: 100px; height: 100px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center fw-bold shadow-sm" 
                                                             style="width: 100px; height: 100px; font-size: 2rem;">
                                                            <?= strtoupper(substr($tch['first_name'], 0, 1) . substr($tch['last_name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <h5 class="fw-bold mt-2 mb-0"><?= h($tch['first_name'] . ' ' . $tch['last_name']); ?></h5>
                                                    <span class="badge bg-light text-dark border font-monospace"><?= h($tch['teacher_id']); ?></span>
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm table-borderless mb-0">
                                                        <tbody>
                                                            <tr>
                                                                <th class="text-muted w-40">Primary Subject:</th>
                                                                <td class="fw-semibold text-dark">
                                                                    <?= !empty($tch['subject_name']) ? h($tch['subject_name']) . ' (' . h($tch['subject_code']) . ')' : 'None'; ?>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Qualification:</th>
                                                                <td><?= h($tch['qualification']); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Experience:</th>
                                                                <td><?= (int)$tch['experience']; ?> years</td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Monthly Salary:</th>
                                                                <td class="fw-bold text-success">Rs. <?= number_format((float)$tch['salary'], 2); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Gender:</th>
                                                                <td><?= h($tch['gender']); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Joining Date:</th>
                                                                <td><?= !empty($tch['joining_date']) ? date('M d, Y', strtotime($tch['joining_date'])) : 'N/A'; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Phone Number:</th>
                                                                <td><?= h($tch['phone']); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Email Address:</th>
                                                                <td><?= !empty($tch['email']) ? h($tch['email']) : 'Not provided'; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Address:</th>
                                                                <td><?= !empty($tch['address']) ? nl2br(h($tch['address'])) : 'Not provided'; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-muted">Status:</th>
                                                                <td>
                                                                    <?php if ($tch['status'] === 'active'): ?>
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
                                                <a href="edit_teacher.php?id=<?= (int)$tch['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil me-1"></i> Edit Teacher
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
                Showing page <strong><?= $page; ?></strong> of <strong><?= $total_pages; ?></strong> (<?= $total_teachers; ?> total teachers)
            </div>
            <nav aria-label="Teacher records pagination">
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
