<?php
/**
 * teacher/students.php — Read-only Student Records Scoped to Teacher's Assigned Classes
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/teacher_helper.php';

require_login();
require_role(['teacher']);
require_permission('students.view');

$user = current_user();

$page_title  = 'Students';
$active_menu = 'teacher_students';
$breadcrumb  = ['Teacher Portal', 'Students'];
$depth       = '../';

// Fetch assigned classes
$assigned_classes = get_teacher_assigned_classes($user['id']);
$assigned_class_ids = array_column($assigned_classes, 'id');

$filter_class = (int)($_GET['class_id'] ?? 0);
$search       = trim($_GET['search'] ?? '');
$students     = [];

if (!empty($assigned_class_ids)) {
    // If specific class filter chosen, verify teacher is assigned to it
    if ($filter_class > 0) {
        if (!is_teacher_assigned_to_class($user['id'], $filter_class)) {
            set_flash('error', 'Access Denied: You cannot view students outside your assigned classes.');
            redirect('students.php');
        }
        $target_classes = [$filter_class];
    } else {
        $target_classes = $assigned_class_ids;
    }

    $in_placeholders = implode(',', array_fill(0, count($target_classes), '?'));
    $types = str_repeat('i', count($target_classes));
    $params = $target_classes;

    $where = "WHERE s.class_id IN ($in_placeholders) AND s.status = 'active'";

    if ($search !== '') {
        $where .= " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.father_name LIKE ? OR s.phone LIKE ?)";
        $types .= 'sssss';
        $like = '%' . $search . '%';
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    $sql = "SELECT s.id, s.student_id, s.first_name, s.last_name, s.father_name, s.gender,
                   s.date_of_birth, s.phone, s.email, s.address, s.photo, s.admission_date, s.status,
                   c.class_name, c.section, c.room_number
            FROM students s
            JOIN classes c ON s.class_id = c.id
            $where
            ORDER BY c.class_name ASC, c.section ASC, s.first_name ASC, s.last_name ASC";
    $students = db_fetch_all($sql, $types, $params);
}

include '../layout.php';
?>

<!-- Search & Filter Card -->
<div class="card-ams mb-4">
    <div class="card-body-ams p-3">
        <form method="GET" action="students.php" class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control-ams border-start-0" 
                           placeholder="Search by student name, ID, or father's name..." value="<?= h($search) ?>">
                </div>
            </div>

            <div class="col-md-4">
                <select name="class_id" class="form-control-ams" onchange="this.form.submit()">
                    <option value="">-- All My Assigned Classes --</option>
                    <?php foreach ($assigned_classes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filter_class === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= h($c['class_name'] . ' - ' . $c['section']) ?> (<?= (int)$c['student_count'] ?> students)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn-ams btn-primary-ams flex-fill">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <?php if ($search !== '' || $filter_class > 0): ?>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="fas fa-undo"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Student List Card -->
<div class="card-ams">
    <div class="card-header-ams d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0 text-dark">
                <i class="fas fa-user-graduate text-primary me-2"></i> Student Directory
            </h5>
            <small class="text-muted">Showing <?= count($students) ?> student(s) enrolled across your assigned classes</small>
        </div>
        <span class="badge bg-secondary-subtle text-dark border">
            <i class="fas fa-lock me-1"></i> Read-Only View
        </span>
    </div>

    <div class="card-body-ams p-0">
        <?php if (empty($students)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-user-slash fa-3x mb-3 text-secondary"></i>
                <h5>No Students Found</h5>
                <p class="mb-0">No active students matching your search criteria were found in your assigned classes.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Class &amp; Section</th>
                            <th>Father's Name</th>
                            <th>Gender / DOB</th>
                            <th>Contact Info</th>
                            <th style="width: 90px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($students as $st): ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?= $i++ ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark font-monospace border">
                                    <?= h($st['student_id']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($st['photo']) && file_exists(__DIR__ . '/../uploads/students/' . $st['photo'])): ?>
                                        <img src="../uploads/students/<?= h($st['photo']) ?>" alt="Photo" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                            <?= strtoupper(substr($st['first_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-dark"><?= h($st['first_name'] . ' ' . $st['last_name']) ?></div>
                                        <small class="text-muted"><?= h($st['email'] ?: 'No email') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary">
                                    <?= h($st['class_name']) ?> (<?= h($st['section']) ?>)
                                </span>
                            </td>
                            <td><?= h($st['father_name']) ?></td>
                            <td>
                                <div class="small">
                                    <span class="badge <?= $st['gender']==='Female' ? 'bg-danger-subtle text-danger' : 'bg-info-subtle text-info' ?> me-1">
                                        <?= h($st['gender']) ?>
                                    </span>
                                    <span class="text-muted"><?= $st['date_of_birth'] ? date('d M Y', strtotime($st['date_of_birth'])) : 'N/A' ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="small">
                                    <div><i class="fas fa-phone-alt text-muted me-1"></i><?= h($st['phone'] ?: 'N/A') ?></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-primary btn-sm view-profile-btn"
                                        data-bs-toggle="modal" data-bs-target="#studentModal"
                                        data-name="<?= h($st['first_name'] . ' ' . $st['last_name']) ?>"
                                        data-id="<?= h($st['student_id']) ?>"
                                        data-class="<?= h($st['class_name'] . ' - Section ' . $st['section']) ?>"
                                        data-father="<?= h($st['father_name']) ?>"
                                        data-gender="<?= h($st['gender']) ?>"
                                        data-dob="<?= h($st['date_of_birth'] ? date('d M Y', strtotime($st['date_of_birth'])) : 'N/A') ?>"
                                        data-phone="<?= h($st['phone'] ?: 'N/A') ?>"
                                        data-email="<?= h($st['email'] ?: 'N/A') ?>"
                                        data-address="<?= h($st['address'] ?: 'N/A') ?>"
                                        data-admission="<?= h($st['admission_date'] ? date('d M Y', strtotime($st['admission_date'])) : 'N/A') ?>">
                                    <i class="fas fa-eye me-1"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Read-Only Student Details Modal -->
<div class="modal fade" id="studentModal" tabindex="-1" aria-labelledby="studentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="studentModalLabel"><i class="fas fa-id-card me-2"></i> Student Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center fw-bold mb-2" style="width: 64px; height: 64px; font-size: 1.5rem;" id="modalAvatar">
                        S
                    </div>
                    <h5 class="fw-bold mb-0" id="modalStudentName">Student Name</h5>
                    <span class="badge bg-secondary-subtle text-dark border font-monospace mt-1" id="modalStudentId">STD-000</span>
                </div>

                <ul class="list-group list-group-flush border-top border-bottom my-3">
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><i class="fas fa-school me-2 text-primary"></i> Class:</span>
                        <strong id="modalClass">—</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><i class="fas fa-user-friends me-2 text-primary"></i> Father's Name:</span>
                        <span id="modalFather">—</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><i class="fas fa-venus-mars me-2 text-primary"></i> Gender:</span>
                        <span id="modalGender">—</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><i class="fas fa-birthday-cake me-2 text-primary"></i> Date of Birth:</span>
                        <span id="modalDob">—</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><i class="fas fa-phone me-2 text-primary"></i> Phone:</span>
                        <span id="modalPhone">—</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><i class="fas fa-envelope me-2 text-primary"></i> Email:</span>
                        <span id="modalEmail">—</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><i class="fas fa-calendar-alt me-2 text-primary"></i> Admission Date:</span>
                        <span id="modalAdmission">—</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><i class="fas fa-map-marker-alt me-2 text-primary"></i> Address:</span>
                        <span id="modalAddress">—</span>
                    </li>
                </ul>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.view-profile-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            document.getElementById('modalStudentName').textContent = name;
            document.getElementById('modalAvatar').textContent = name.charAt(0).toUpperCase();
            document.getElementById('modalStudentId').textContent = this.getAttribute('data-id');
            document.getElementById('modalClass').textContent = this.getAttribute('data-class');
            document.getElementById('modalFather').textContent = this.getAttribute('data-father');
            document.getElementById('modalGender').textContent = this.getAttribute('data-gender');
            document.getElementById('modalDob').textContent = this.getAttribute('data-dob');
            document.getElementById('modalPhone').textContent = this.getAttribute('data-phone');
            document.getElementById('modalEmail').textContent = this.getAttribute('data-email');
            document.getElementById('modalAddress').textContent = this.getAttribute('data-address');
            document.getElementById('modalAdmission').textContent = this.getAttribute('data-admission');
        });
    });
});
</script>
HTML;

include '../layout_end.php';
?>
