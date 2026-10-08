<?php
/**
 * finance/teacher_salary.php — Manage Teacher Salaries
 * Arsalan Model School and College Management System
 * Protected by granular permission: teachers.salary
 */

require_once __DIR__ . '/../database.php';
require_login();
require_permission('teachers.salary');

$user = current_user();
$page_title  = 'Teacher Salaries';
$active_menu = 'teacher_salary';
$breadcrumb  = ['Finance', 'Teacher Salaries'];
$depth       = '../';

// Handle Salary Update POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_salary') {
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $new_salary_raw = trim($_POST['salary'] ?? '');

    if ($teacher_id <= 0) {
        set_flash('error', 'Invalid teacher selected.');
        redirect('teacher_salary.php');
    }

    if ($new_salary_raw === '' || !is_numeric($new_salary_raw)) {
        set_flash('error', 'Please enter a valid salary amount.');
        redirect('teacher_salary.php');
    }

    $new_salary = (float)$new_salary_raw;
    if ($new_salary < 0) {
        set_flash('error', 'Salary cannot be a negative amount.');
        redirect('teacher_salary.php');
    }

    // Fetch existing teacher record to log previous vs new and confirm existence
    $teacher = db_fetch_one(
        "SELECT t.id, t.teacher_id, t.first_name, t.last_name, t.salary 
         FROM teachers t 
         WHERE t.id = ?",
        'i',
        [$teacher_id]
    );

    if (!$teacher) {
        set_flash('error', 'Teacher record not found.');
        redirect('teacher_salary.php');
    }

    $old_salary = (float)$teacher['salary'];
    $full_name = trim($teacher['first_name'] . ' ' . $teacher['last_name']);
    $formatted_new = number_format($new_salary);

    // Update salary using prepared statement
    db_execute(
        "UPDATE teachers SET salary = ? WHERE id = ?",
        'di',
        [$new_salary, $teacher_id]
    );

    // Activity log entry (matching prompt requirement)
    $log_detail = "Updated salary for Teacher {$teacher['teacher_id']} ({$full_name}) to Rs. {$formatted_new}";
    log_activity(
        $user['id'],
        $user['role'],
        'update_salary',
        'teachers',
        $teacher_id,
        $log_detail
    );

    set_flash('success', "Salary updated for Teacher {$teacher['teacher_id']} ({$full_name}) to Rs. {$formatted_new}");
    redirect('teacher_salary.php');
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where = " WHERE 1=1";
$params = [];
$types = "";

if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= " AND (t.teacher_id LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR s.subject_name LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= "sssss";
}

if ($status_filter === 'active' || $status_filter === 'inactive') {
    $where .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Total Stats (Always across all teachers)
$stats_row = db_fetch_one("
    SELECT 
        COUNT(*) AS total_teachers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_teachers,
        SUM(CASE WHEN status = 'active' THEN salary ELSE 0 END) AS monthly_payroll,
        AVG(CASE WHEN status = 'active' THEN salary ELSE NULL END) AS avg_salary
    FROM teachers
");

$total_teachers  = (int)($stats_row['total_teachers'] ?? 0);
$active_teachers = (int)($stats_row['active_teachers'] ?? 0);
$monthly_payroll = (float)($stats_row['monthly_payroll'] ?? 0);
$avg_salary      = (float)($stats_row['avg_salary'] ?? 0);

// Query filtered teacher rows
$sql = "SELECT t.id, t.teacher_id, t.first_name, t.last_name, t.salary, t.status, 
               COALESCE(s.subject_name, 'Unassigned') AS subject_name,
               s.subject_code
        FROM teachers t
        LEFT JOIN subjects s ON t.subject_id = s.id
        {$where}
        ORDER BY t.first_name ASC, t.last_name ASC";

$teachers = db_fetch_all($sql, $types, $params);

include __DIR__ . '/../layout.php';
?>

<div class="container-fluid px-0">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= $depth ?>finance/finance_dashboard.php" class="text-decoration-none">Finance</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Teacher Salaries</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold text-dark mb-0">
                <i class="fas fa-money-bill-wave text-success me-2"></i> Faculty Salary Management
            </h2>
            <small class="text-muted">Dedicated finance portal to review and manage monthly compensation for teachers.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">
                <i class="fas fa-lock me-1"></i> Authorized: Finance Payroll
            </span>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($flash = get_flash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= h($flash) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($flash_err = get_flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= h($flash_err) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- KPI Summary Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-3 me-3">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Faculty</div>
                        <h4 class="mb-0 fw-bold"><?= $total_teachers ?></h4>
                        <small class="text-muted"><?= $active_teachers ?> active status</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100 border-start border-success border-4">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 text-success p-3 me-3">
                        <i class="fas fa-coins fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Active Monthly Payroll</div>
                        <h4 class="mb-0 fw-bold text-success">Rs. <?= number_format($monthly_payroll) ?></h4>
                        <small class="text-muted">Total recurring budget</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-info bg-opacity-10 text-info p-3 me-3">
                        <i class="fas fa-chart-bar fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Average Salary</div>
                        <h4 class="mb-0 fw-bold text-dark">Rs. <?= number_format($avg_salary) ?></h4>
                        <small class="text-muted">Per active faculty</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-warning bg-opacity-10 text-warning p-3 me-3">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Access Scope</div>
                        <h5 class="mb-0 fw-bold text-dark">Salary Only</h5>
                        <small class="text-muted">Restricted view mode</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="teacher_salary.php" class="row g-2 align-items-center">
                <div class="col-md-6 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0 ps-0" 
                               name="search" 
                               placeholder="Search by Teacher ID, Name, or Subject..." 
                               value="<?= h($search) ?>" 
                               autofocus>
                    </div>
                </div>
                <div class="col-md-3 col-lg-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                    </select>
                </div>
                <div class="col-md-3 col-lg-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <?php if ($search !== '' || $status_filter !== ''): ?>
                        <a href="teacher_salary.php" class="btn btn-outline-secondary" title="Clear Filters">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Teachers Salary Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-list-alt text-primary me-2"></i> Faculty Salary Registry
            </h5>
            <span class="text-muted small">
                Showing <strong><?= count($teachers) ?></strong> faculty records
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.03em;">
                    <tr>
                        <th class="ps-3 py-3" style="width: 130px;">Teacher ID</th>
                        <th class="py-3">Teacher Name</th>
                        <th class="py-3">Subject</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3" style="width: 200px;">Current Salary</th>
                        <th class="pe-3 py-3 text-end" style="width: 170px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($teachers)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-user-slash fa-3x d-block mb-3 text-secondary"></i>
                            <h6 class="fw-semibold">No teachers found</h6>
                            <p class="small mb-0">No faculty records match the specified search or filter criteria.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $tch): ?>
                        <?php 
                        $tid = (int)$tch['id'];
                        $full_name = trim($tch['first_name'] . ' ' . $tch['last_name']);
                        $current_sal = (float)$tch['salary'];
                        ?>
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                    <?= h($tch['teacher_id']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= h($full_name) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                    <?= h($tch['subject_name']) ?>
                                    <?php if (!empty($tch['subject_code'])): ?>
                                        <small class="text-muted">(<?= h($tch['subject_code']) ?>)</small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($tch['status'] === 'active'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fas fa-check-circle me-1"></i> Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                        <i class="fas fa-minus-circle me-1"></i> Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fs-6 fw-bold text-success">
                                    Rs. <?= number_format($current_sal, 2) ?>
                                </div>
                            </td>
                            <td class="pe-3 text-end">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-success px-3"
                                        onclick="openSalaryModal(<?= $tid ?>, '<?= h(addslashes($tch['teacher_id'])) ?>', '<?= h(addslashes($full_name)) ?>', <?= $current_sal ?>)">
                                    <i class="fas fa-edit me-1"></i> Update Salary
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Update Teacher Salary -->
<div class="modal fade" id="updateSalaryModal" tabindex="-1" aria-labelledby="updateSalaryLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="teacher_salary.php" class="modal-content shadow">
            <input type="hidden" name="action" value="update_salary">
            <input type="hidden" name="teacher_id" id="modalTeacherId">
            
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="updateSalaryLabel">
                    <i class="fas fa-coins me-2"></i> Update Teacher Salary
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="d-flex align-items-center bg-light p-3 rounded-3 mb-3 border">
                    <div class="rounded-circle bg-success text-white p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="fas fa-user-tie fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark" id="modalTeacherNameDisplay">Teacher Name</h6>
                        <span class="badge bg-white text-dark border font-monospace mt-1" id="modalTeacherIdDisplay">TCH-000</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Current Monthly Salary</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted">Rs.</span>
                        <input type="text" class="form-control bg-light fw-bold text-muted" id="modalCurrentSalaryDisplay" readonly>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-bold text-dark" for="modalSalaryInput">
                        New Monthly Salary (Rs.) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-success text-white fw-bold">Rs.</span>
                        <input type="number" 
                               step="0.01" 
                               min="0" 
                               name="salary" 
                               id="modalSalaryInput" 
                               class="form-control form-control-lg fw-bold text-success" 
                               required 
                               placeholder="e.g. 45000">
                    </div>
                    <div class="form-text text-muted">Enter the approved monthly salary for this teacher.</div>
                </div>
            </div>
            
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4 fw-semibold">
                    <i class="fas fa-check me-1"></i> Save Salary
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openSalaryModal(id, code, name, salary) {
    document.getElementById('modalTeacherId').value = id;
    document.getElementById('modalTeacherIdDisplay').textContent = code;
    document.getElementById('modalTeacherNameDisplay').textContent = name;
    document.getElementById('modalCurrentSalaryDisplay').value = Number(salary).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    const input = document.getElementById('modalSalaryInput');
    input.value = salary;
    
    const modal = new bootstrap.Modal(document.getElementById('updateSalaryModal'));
    modal.show();
    setTimeout(() => {
        input.focus();
        input.select();
    }, 400);
}
</script>

<?php include __DIR__ . '/../layout_end.php'; ?>
