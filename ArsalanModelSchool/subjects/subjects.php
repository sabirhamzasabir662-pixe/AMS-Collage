<?php
/**
 * subjects/subjects.php
 * Arsalan Model School and College Management System
 * Phase 5: Subjects Module
 */

require_once __DIR__ . '/../database.php';
require_login();

// ─── AJAX HANDLER ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = trim($_POST['ajax_action']);

    try {
        if ($action === 'get_subject') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid subject ID.']);
                exit;
            }
            $sub = db_fetch_one("SELECT * FROM subjects WHERE id = ? LIMIT 1", [$id], 'i');
            if (!$sub) {
                echo json_encode(['success' => false, 'message' => 'Subject not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'subject' => $sub]);
            exit;
        }

        if ($action === 'save_subject') {
            $id           = (int)($_POST['subject_id'] ?? 0);
            $subject_name = trim($_POST['subject_name'] ?? '');
            $subject_code = strtoupper(trim($_POST['subject_code'] ?? ''));
            $class_id     = (int)($_POST['class_id'] ?? 0);
            $subject_teacher_name = trim($_POST['subject_teacher_name'] ?? '');
            $status       = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

            if ($subject_name === '') {
                echo json_encode(['success' => false, 'message' => 'Subject name is required.']);
                exit;
            }
            if ($subject_code === '') {
                echo json_encode(['success' => false, 'message' => 'Subject code is required (e.g. ENG-101).']);
                exit;
            }
            if ($class_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select an assigned class.']);
                exit;
            }

            // Verify unique subject_code
            if ($id > 0) {
                $dup = db_fetch_one(
                    "SELECT id FROM subjects WHERE subject_code = ? AND id != ? LIMIT 1",
                    [$subject_code, $id],
                    'si'
                );
            } else {
                $dup = db_fetch_one(
                    "SELECT id FROM subjects WHERE subject_code = ? LIMIT 1",
                    [$subject_code],
                    's'
                );
            }

            if ($dup) {
                echo json_encode(['success' => false, 'message' => "Subject code '{$subject_code}' is already assigned to another subject."]);
                exit;
            }

            $teacher_param = (!empty($subject_teacher_name)) ? $subject_teacher_name : null;

            if ($id > 0) {
                // UPDATE
                $sql = "UPDATE subjects SET subject_name = ?, subject_code = ?, class_id = ?, subject_teacher_name = ?, status = ? WHERE id = ?";
                db_execute($sql, [$subject_name, $subject_code, $class_id, $teacher_param, $status, $id], 'ssiisi');
                $message = "Subject '{$subject_name}' updated successfully!";
                $subject_primary_id = $id;
            } else {
                // INSERT
                $sql = "INSERT INTO subjects (subject_name, subject_code, class_id, subject_teacher_name, status) VALUES (?, ?, ?, ?, ?)";
                $res = db_execute($sql, [$subject_name, $subject_code, $class_id, $teacher_param, $status], 'ssiis');
                $message = "Subject '{$subject_name}' added successfully!";
                $subject_primary_id = $res['insert_id'];
            }

            // Fetch the updated row with class and teacher names
            $updated_row = db_fetch_one(
                "SELECT s.*, c.class_name, c.section, t.teacher_id as faculty_code, t.first_name, t.last_name 
                 FROM subjects s 
                 LEFT JOIN classes c ON s.class_id = c.id 
                 LEFT JOIN teachers t ON s.teacher_id = t.id 
                 WHERE s.id = ? LIMIT 1",
                [$subject_primary_id],
                'i'
            );

            echo json_encode([
                'success' => true,
                'message' => $message,
                'is_edit' => ($id > 0),
                'subject' => $updated_row,
            ]);
            exit;
        }

        if ($action === 'delete_subject') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid subject ID.']);
                exit;
            }

            db_execute("DELETE FROM subjects WHERE id = ?", [$id], 'i');
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Subject deleted successfully!']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        exit;
    }
}

// ─── PAGE DATA ─────────────────────────────────────────────────────────────────
$page_title  = 'Subjects Management';
$active_menu = 'subjects';
$depth       = '../';

// Load active classes for dropdown
$classes = db_fetch_all("SELECT id, class_name, section FROM classes WHERE status = 'active' ORDER BY id ASC");

// Load active teachers for dropdown
$teachers = db_fetch_all("SELECT id, teacher_id, first_name, last_name FROM teachers WHERE status = 'active' ORDER BY first_name ASC");

// Load all subjects with linked class and teacher details
$subjects = db_fetch_all(
    "SELECT s.*, c.class_name, c.section, t.teacher_id as faculty_code, t.first_name, t.last_name, s.subject_teacher_name 
     FROM subjects s 
     LEFT JOIN classes c ON s.class_id = c.id 
     LEFT JOIN teachers t ON s.teacher_id = t.id 
     ORDER BY s.id ASC"
);

$total_subjects = count($subjects);
$active_subjects = count(array_filter($subjects, fn($s) => $s['status'] === 'active'));

require_once __DIR__ . '/../layout.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Subjects</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <h2 class="h3 fw-bold text-dark mb-0">
                <i class="bi bi-book text-warning me-2"></i> Academic Curriculum
            </h2>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" id="totalSubjectsBadge">
                Total: <?= number_format($total_subjects); ?> Subjects
            </span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" id="activeSubjectsBadge">
                Active: <?= number_format($active_subjects); ?>
            </span>
        </div>
    </div>
    <div>
        <button type="button" class="btn btn-warning text-dark fw-semibold shadow-sm" id="btnOpenAddSubjectModal">
            <i class="bi bi-plus-circle-fill me-1"></i> Add Subject
        </button>
    </div>
</div>

<!-- Dynamic Toast / Alert Container for AJAX Responses -->
<div id="ajaxAlertContainer"></div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control border-start-0 ps-0" 
                           id="subjectSearchInput" 
                           placeholder="Filter by subject name, code, class, or teacher..." 
                           autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" id="btnClearSearch" title="Clear filter">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6 col-lg-7 text-md-end text-muted small">
                Showing <strong id="visibleCount"><?= $total_subjects; ?></strong> of <strong><?= $total_subjects; ?></strong> subjects
            </div>
        </div>
    </div>
</div>

<!-- Subjects Table Card -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="subjectsTable">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.03em;">
                <tr>
                    <th class="ps-3 py-3" style="width: 70px;">ID</th>
                    <th class="py-3">Subject Name</th>
                    <th class="py-3">Code</th>
                    <th class="py-3">Assigned Class</th>
                    <th class="py-3">Assigned Teacher</th>
                    <th class="py-3 text-center" style="width: 100px;">Status</th>
                    <th class="pe-3 py-3 text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="subjectsTableBody">
                <?php if (empty($subjects)): ?>
                    <tr id="noRecordsRow">
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-book fs-1 d-block mb-2 text-secondary"></i>
                            <h6 class="fw-semibold">No subjects registered yet</h6>
                            <p class="small mb-3">Add subjects to construct the school course curriculum.</p>
                            <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold" onclick="openSubjectModal()">
                                Add Subject
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $s): ?>
                        <tr id="subjectRow<?= (int)$s['id']; ?>">
                            <td class="ps-3">
                                <span class="badge bg-light text-dark border font-monospace">
                                    <?= (int)$s['id']; ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-dark subject-name-cell"><?= h($s['subject_name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace subject-code-cell">
                                    <?= h($s['subject_code']); ?>
                                </span>
                            </td>
                            <td class="class-cell">
                                <?php if (!empty($s['class_name'])): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">
                                        <?= h($s['class_name']); ?><?= !empty($s['section']) ? ' - ' . h($s['section']) : ''; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="teacher-cell">
                                <?php if (!empty($s['subject_teacher_name'])): ?>
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1">
                                        <i class="bi bi-person-check me-1"></i>
                                        <?= h($s['subject_teacher_name']); ?>
                                    </span>
                                <?php elseif (!empty($s['first_name'])): ?>
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1">
                                        <i class="bi bi-person-check me-1"></i>
                                        <?= h($s['first_name'] . ' ' . $s['last_name']); ?>
                                        <small class="text-muted">(<?= h($s['faculty_code']); ?>)</small>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">
                                        <i class="bi bi-dash me-1"></i> Not Assigned
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center status-cell">
                                <?php if ($s['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check-circle me-1"></i> Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                        <i class="bi bi-dash-circle me-1"></i> Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="editSubject(<?= (int)$s['id']; ?>)" 
                                            title="Edit Subject">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="deleteSubject(<?= (int)$s['id']; ?>, '<?= h(addslashes($s['subject_name'])); ?>')" 
                                            title="Delete Subject">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1" aria-labelledby="subjectModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-dark" id="subjectModalTitle">
                    <i class="bi bi-book text-warning me-2"></i> Add New Subject
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="subjectForm" novalidate>
                <input type="hidden" id="modalSubjectId" name="subject_id" value="0">
                <input type="hidden" name="ajax_action" value="save_subject">

                <div class="modal-body p-4">
                    <!-- Error Alert -->
                    <div class="alert alert-danger d-none" id="modalErrorAlert" role="alert"></div>

                    <div class="mb-3">
                        <label for="modalSubjectName" class="form-label fw-semibold">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="modalSubjectName" 
                               name="subject_name" 
                               placeholder="e.g. Mathematics, Physics, English Compulsory" 
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="modalSubjectCode" class="form-label fw-semibold">Subject Code <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control font-monospace" 
                               id="modalSubjectCode" 
                               name="subject_code" 
                               placeholder="e.g. MTH-101, ENG-101" 
                               required>
                        <div class="form-text">Unique curriculum code identifier.</div>
                    </div>

                    <div class="mb-3">
                        <label for="modalClassId" class="form-label fw-semibold">Assigned Class <span class="text-danger">*</span></label>
                        <select class="form-select" id="modalClassId" name="class_id" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= (int)$c['id']; ?>">
                                    <?= h($c['class_name']); ?> - <?= h($c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modalSubjectTeacherName" class="form-label fw-semibold">Assigned Subject Teacher</label>
                        <input type="text" class="form-control" id="modalSubjectTeacherName" name="subject_teacher_name" placeholder="Enter teacher name (optional)" maxlength="100">
                    </div>

                    <div class="mb-2">
                        <label for="modalStatus" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="modalStatus" name="status" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-semibold" id="btnSaveSubject">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpinner"></span>
                        <i class="bi bi-check2-circle me-1" id="saveIcon"></i> Save Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'HTML'
<script>
let subjectModalInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    subjectModalInstance = new bootstrap.Modal(document.getElementById('subjectModal'));

    document.getElementById('btnOpenAddSubjectModal').addEventListener('click', () => {
        openSubjectModal();
    });

    document.getElementById('subjectForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveSubjectAjax();
    });

    document.getElementById('subjectSearchInput').addEventListener('input', function() {
        filterSubjectsTable(this.value.toLowerCase().trim());
    });

    document.getElementById('btnClearSearch').addEventListener('click', function() {
        document.getElementById('subjectSearchInput').value = '';
        filterSubjectsTable('');
    });
});

function openSubjectModal(subjectData = null) {
    const errorAlert = document.getElementById('modalErrorAlert');
    errorAlert.classList.add('d-none');
    errorAlert.textContent = '';

    const titleEl = document.getElementById('subjectModalTitle');
    const idInput = document.getElementById('modalSubjectId');
    const nameInput = document.getElementById('modalSubjectName');
    const codeInput = document.getElementById('modalSubjectCode');
    const classInput = document.getElementById('modalClassId');
    const teacherInput = document.getElementById('modalSubjectTeacherName');
    const statusInput = document.getElementById('modalStatus');

    if (subjectData) {
        titleEl.innerHTML = '<i class="bi bi-pencil-square text-warning me-2"></i> Edit Subject';
        idInput.value = subjectData.id;
        nameInput.value = subjectData.subject_name || '';
        codeInput.value = subjectData.subject_code || '';
        classInput.value = subjectData.class_id || '';
        teacherInput.value = subjectData.subject_teacher_name || '';
        statusInput.value = subjectData.status || 'active';
    } else {
        titleEl.innerHTML = '<i class="bi bi-book text-warning me-2"></i> Add New Subject';
        idInput.value = '0';
        nameInput.value = '';
        codeInput.value = '';
        classInput.value = '';
        teacherInput.value = '';
        statusInput.value = 'active';
    }

    subjectModalInstance.show();
    setTimeout(() => nameInput.focus(), 400);
}

function editSubject(id) {
    const formData = new FormData();
    formData.append('ajax_action', 'get_subject');
    formData.append('id', id);

    fetch('subjects.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.subject) {
            openSubjectModal(data.subject);
        } else {
            showToast(data.message || 'Could not load subject data.', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error while retrieving subject details.', 'danger');
    });
}

function saveSubjectAjax() {
    const form = document.getElementById('subjectForm');
    const errorAlert = document.getElementById('modalErrorAlert');
    const spinner = document.getElementById('saveSpinner');
    const icon = document.getElementById('saveIcon');
    const btn = document.getElementById('btnSaveSubject');

    errorAlert.classList.add('d-none');
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    btn.disabled = true;

    const formData = new FormData(form);

    fetch('subjects.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
        btn.disabled = false;

        if (data.success) {
            subjectModalInstance.hide();
            showToast(data.message, 'success');
            renderOrUpdateSubjectRow(data.subject, data.is_edit);
        } else {
            errorAlert.textContent = data.message || 'Validation error.';
            errorAlert.classList.remove('d-none');
        }
    })
    .catch(err => {
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
        btn.disabled = false;
        errorAlert.textContent = 'Server/Network error while saving subject.';
        errorAlert.classList.remove('d-none');
    });
}

function renderOrUpdateSubjectRow(s, isEdit) {
    const tbody = document.getElementById('subjectsTableBody');
    const noRow = document.getElementById('noRecordsRow');
    if (noRow) noRow.remove();

    const classBadge = s.class_name 
        ? `<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">${escapeHtml(s.class_name)}${s.section ? ' - ' + escapeHtml(s.section) : ''}</span>`
        : `<span class="text-muted small">Not Assigned</span>`;

    const teacherName = s.subject_teacher_name ? `${escapeHtml(s.subject_teacher_name)}` : '';
    const teacherBadge = s.subject_teacher_name 
        ? `<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1"><i class="bi bi-person-check me-1"></i>${teacherName}</span>`
        : (s.first_name ? `<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1"><i class="bi bi-person-check me-1"></i>${escapeHtml(s.first_name)} ${escapeHtml(s.last_name)} <small class="text-muted">(${escapeHtml(s.faculty_code)})</small></span>` : `<span class="badge bg-light text-muted border"><i class="bi bi-dash me-1"></i> Not Assigned</span>`);

    const statusBadge = s.status === 'active' 
        ? `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i> Active</span>`
        : `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="bi bi-dash-circle me-1"></i> Inactive</span>`;

    const rowHtml = `
        <td class="ps-3">
            <span class="badge bg-light text-dark border font-monospace">${s.id}</span>
        </td>
        <td>
            <strong class="text-dark subject-name-cell">${escapeHtml(s.subject_name)}</strong>
        </td>
        <td>
            <span class="badge bg-light text-dark border font-monospace subject-code-cell">
                ${escapeHtml(s.subject_code)}
            </span>
        </td>
        <td class="class-cell">${classBadge}</td>
        <td class="teacher-cell">${teacherBadge}</td>
        <td class="text-center status-cell">${statusBadge}</td>
        <td class="pe-3 text-end">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary" onclick="editSubject(${s.id})" title="Edit Subject">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="deleteSubject(${s.id}, '${escapeJs(s.subject_name)}')" title="Delete Subject">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </td>
    `;

    if (isEdit) {
        const existingRow = document.getElementById(`subjectRow${s.id}`);
        if (existingRow) {
            existingRow.innerHTML = rowHtml;
            highlightRow(existingRow);
        }
    } else {
        const newRow = document.createElement('tr');
        newRow.id = `subjectRow${s.id}`;
        newRow.innerHTML = rowHtml;
        tbody.prepend(newRow);
        highlightRow(newRow);
        updateSubjectCounts(1);
    }
}

function deleteSubject(id, name) {
    if (!confirm(`Are you sure you want to delete subject '${name}'? This action cannot be undone.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('ajax_action', 'delete_subject');
    formData.append('id', id);

    fetch('subjects.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`subjectRow${id}`);
            if (row) {
                row.classList.add('table-danger');
                setTimeout(() => {
                    row.remove();
                    updateSubjectCounts(-1);
                }, 300);
            }
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Could not delete subject.', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error during subject deletion.', 'danger');
    });
}

function filterSubjectsTable(query) {
    const rows = document.querySelectorAll('#subjectsTableBody tr');
    let visible = 0;
    rows.forEach(r => {
        if (r.id === 'noRecordsRow') return;
        const text = (r.textContent || '').toLowerCase();
        if (text.includes(query)) {
            r.style.display = '';
            visible++;
        } else {
            r.style.display = 'none';
        }
    });
    document.getElementById('visibleCount').textContent = visible;
}

function updateSubjectCounts(delta) {
    const totalEl = document.getElementById('totalSubjectsBadge');
    const cur = parseInt(totalEl.textContent.replace(/\D/g, '')) || 0;
    const n = Math.max(0, cur + delta);
    totalEl.textContent = `Total: ${n} Subjects`;
    document.getElementById('visibleCount').textContent = n;
}

function highlightRow(row) {
    row.classList.add('table-warning');
    setTimeout(() => row.classList.remove('table-warning'), 1500);
}

function showToast(message, type = 'success') {
    const container = document.getElementById('ajaxAlertContainer');
    const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show d-flex align-items-center shadow-sm mb-3" role="alert">
            <i class="bi ${icon} flex-shrink-0 me-2 fs-5"></i>
            <div>${escapeHtml(message)}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    container.innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'");
}
</script>
HTML;

require_once __DIR__ . '/../layout_end.php';
?>
