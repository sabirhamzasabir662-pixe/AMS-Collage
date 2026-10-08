<?php
/**
 * classes/classes.php
 * Arsalan Model School and College Management System
 * Phase 5: Classes Module
 */

require_once __DIR__ . '/../database.php';
require_login();

// ─── AJAX HANDLER ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = trim($_POST['ajax_action']);

    try {
        if ($action === 'get_class') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid class ID.']);
                exit;
            }
            $cls = db_fetch_one("SELECT * FROM classes WHERE id = ? LIMIT 1", [$id], 'i');
            if (!$cls) {
                echo json_encode(['success' => false, 'message' => 'Class not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'class' => $cls]);
            exit;
        }

        if ($action === 'save_class') {
            $id                 = (int)($_POST['class_id'] ?? 0);
            $class_name         = trim($_POST['class_name'] ?? '');
            $section            = strtoupper(trim($_POST['section'] ?? 'A'));
            $class_teacher_name = trim($_POST['class_teacher_name'] ?? '');
            $teacher_name_param = ($class_teacher_name !== '') ? $class_teacher_name : null;
            $room_number        = trim($_POST['room_number'] ?? '');
            $status             = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

            if ($class_name === '') {
                echo json_encode(['success' => false, 'message' => 'Class name is required.']);
                exit;
            }
            if ($section === '') {
                echo json_encode(['success' => false, 'message' => 'Section is required.']);
                exit;
            }

            // Verify unique class_name + section combo
            if ($id > 0) {
                $dup = db_fetch_one(
                    "SELECT id FROM classes WHERE class_name = ? AND section = ? AND id != ? LIMIT 1",
                    [$class_name, $section, $id],
                    'ssi'
                );
            } else {
                $dup = db_fetch_one(
                    "SELECT id FROM classes WHERE class_name = ? AND section = ? LIMIT 1",
                    [$class_name, $section],
                    'ss'
                );
            }

            if ($dup) {
                echo json_encode(['success' => false, 'message' => "The class '{$class_name}' with Section '{$section}' already exists."]);
                exit;
            }

            if ($id > 0) {
                // UPDATE
                $sql = "UPDATE classes SET class_name = ?, section = ?, class_teacher_name = ?, room_number = ?, status = ? WHERE id = ?";
                db_execute($sql, [$class_name, $section, $teacher_name_param, $room_number, $status, $id], 'sssssi');
                $message = "Class '{$class_name} - {$section}' updated successfully!";
                $class_id = $id;
            } else {
                // INSERT
                $sql = "INSERT INTO classes (class_name, section, class_teacher_name, room_number, status) VALUES (?, ?, ?, ?, ?)";
                $res = db_execute($sql, [$class_name, $section, $teacher_name_param, $room_number, $status], 'sssss');
                $message = "Class '{$class_name} - {$section}' added successfully!";
                $class_id = $res['insert_id'];
            }

            // Fetch the updated row
            $updated_row = db_fetch_one(
                "SELECT c.*, 
                        (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count
                 FROM classes c 
                 WHERE c.id = ? LIMIT 1",
                [$class_id],
                'i'
            );

            echo json_encode([
                'success' => true,
                'message' => $message,
                'is_edit' => ($id > 0),
                'class'   => $updated_row,
            ]);
            exit;
        }

        if ($action === 'delete_class') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid class ID.']);
                exit;
            }

            // Check if students or subjects are attached
            $students_count = (int)(db_fetch_one("SELECT COUNT(*) AS cnt FROM students WHERE class_id = ?", [$id], 'i')['cnt'] ?? 0);
            if ($students_count > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot delete class: {$students_count} student(s) are currently enrolled in it. Reassign or remove students first."
                ]);
                exit;
            }

            $subjects_count = (int)(db_fetch_one("SELECT COUNT(*) AS cnt FROM subjects WHERE class_id = ?", [$id], 'i')['cnt'] ?? 0);
            if ($subjects_count > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot delete class: {$subjects_count} subject(s) are linked to this class. Remove or reassign subjects first."
                ]);
                exit;
            }

            db_execute("DELETE FROM classes WHERE id = ?", [$id], 'i');
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Class deleted successfully!']);
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
$page_title  = 'Classes Management';
$active_menu = 'classes';
$depth       = '../';

// Load all classes with student count
$classes = db_fetch_all(
    "SELECT c.*,
            (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count
     FROM classes c 
     ORDER BY c.id ASC"
);

$total_classes = count($classes);
$active_classes = count(array_filter($classes, fn($c) => $c['status'] === 'active'));

require_once __DIR__ . '/../layout.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Classes</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <h2 class="h3 fw-bold text-dark mb-0">
                <i class="bi bi-building text-info me-2"></i> Classes &amp; Sections
            </h2>
            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1" id="totalClassesBadge">
                Total: <?= number_format($total_classes); ?> Classes
            </span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" id="activeClassesBadge">
                Active: <?= number_format($active_classes); ?>
            </span>
        </div>
    </div>
    <div>
        <button type="button" class="btn btn-info text-white shadow-sm" id="btnOpenAddModal">
            <i class="bi bi-plus-circle-fill me-1"></i> Add Class
        </button>
    </div>
</div>

<!-- Dynamic Toast / Alert Container for AJAX Responses -->
<div id="ajaxAlertContainer"></div>

<!-- Quick Filter & Search Card -->
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
                           id="classSearchInput" 
                           placeholder="Filter by class name, section, room, or teacher..." 
                           autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" id="btnClearSearch" title="Clear filter">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6 col-lg-7 text-md-end text-muted small">
                Showing <strong id="visibleCount"><?= $total_classes; ?></strong> of <strong><?= $total_classes; ?></strong> classes
            </div>
        </div>
    </div>
</div>

<!-- Classes Table Card -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="classesTable">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.03em;">
                <tr>
                    <th class="ps-3 py-3" style="width: 70px;">ID</th>
                    <th class="py-3">Class Name</th>
                    <th class="py-3 text-center" style="width: 100px;">Section</th>
                    <th class="py-3">Class Teacher</th>
                    <th class="py-3">Room</th>
                    <th class="py-3 text-center" style="width: 110px;">Students</th>
                    <th class="py-3 text-center" style="width: 100px;">Status</th>
                    <th class="pe-3 py-3 text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="classesTableBody">
                <?php if (empty($classes)): ?>
                    <tr id="noRecordsRow">
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-building fs-1 d-block mb-2 text-secondary"></i>
                            <h6 class="fw-semibold">No classes found</h6>
                            <p class="small mb-3">Get started by creating your first school class.</p>
                            <button type="button" class="btn btn-sm btn-info text-white" onclick="openClassModal()">
                                Add Class
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classes as $c): ?>
                        <tr id="classRow<?= (int)$c['id']; ?>" data-class-name="<?= h(strtolower($c['class_name'])); ?>" data-section="<?= h(strtolower($c['section'])); ?>" data-room="<?= h(strtolower($c['room_number'])); ?>" data-teacher="<?= h(strtolower($c['class_teacher_name'] ?? '')); ?>">
                            <td class="ps-3">
                                <span class="badge bg-light text-dark border font-monospace">
                                    <?= (int)$c['id']; ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-dark class-name-cell"><?= h($c['class_name']); ?></strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border px-2 py-1 section-cell">
                                    Section <?= h($c['section']); ?>
                                </span>
                            </td>
                            <td class="teacher-cell">
                                <?php if (!empty($c['class_teacher_name'])): ?>
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1">
                                        <i class="bi bi-person me-1"></i>
                                        <?= htmlspecialchars($c['class_teacher_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">
                                        <i class="bi bi-dash me-1"></i> Not Assigned
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary small room-cell">
                                <i class="bi bi-geo-alt text-muted me-1"></i><?= h($c['room_number'] !== '' ? $c['room_number'] : 'N/A'); ?>
                            </td>
                            <td class="text-center small">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?= (int)($c['student_count'] ?? 0); ?>
                                </span>
                            </td>
                            <td class="text-center status-cell">
                                <?php if ($c['status'] === 'active'): ?>
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
                                            onclick="editClass(<?= (int)$c['id']; ?>)" 
                                            title="Edit Class">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="deleteClass(<?= (int)$c['id']; ?>, '<?= h(addslashes($c['class_name'] . ' - ' . $c['section'])); ?>')" 
                                            title="Delete Class">
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

<!-- Add / Edit Class Modal -->
<div class="modal fade" id="classModal" tabindex="-1" aria-labelledby="classModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-dark" id="classModalTitle">
                    <i class="bi bi-building text-info me-2"></i> Add New Class
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="classForm" novalidate>
                <input type="hidden" id="modalClassId" name="class_id" value="0">
                <input type="hidden" name="ajax_action" value="save_class">

                <div class="modal-body p-4">
                    <!-- Modal Error Alert -->
                    <div class="alert alert-danger d-none" id="modalErrorAlert" role="alert"></div>

                    <div class="mb-3">
                        <label for="modalClassName" class="form-label fw-semibold">Class Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="modalClassName" 
                               name="class_name" 
                               placeholder="e.g. Class 9, Nursery, 1st Year" 
                               required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="modalSection" class="form-label fw-semibold">Section <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="modalSection" 
                                   name="section" 
                                   placeholder="e.g. A, B, C, D" 
                                   maxlength="10" 
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label for="modalRoomNumber" class="form-label fw-semibold">Room Number</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="modalRoomNumber" 
                                   name="room_number" 
                                   placeholder="e.g. 501, N-1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modalClassTeacherName" class="form-label fw-semibold">Class Teacher (Name)</label>
                        <input type="text" 
                               class="form-control" 
                               id="modalClassTeacherName" 
                               name="class_teacher_name" 
                               placeholder="e.g. Sir Tariq / Ma'am Ayesha" 
                               maxlength="100">
                        <div class="form-text">Type the teacher name directly (free text).</div>
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
                    <button type="submit" class="btn btn-info text-white fw-semibold" id="btnSaveClass">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpinner"></span>
                        <i class="bi bi-check2-circle me-1" id="saveIcon"></i> Save Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'HTML'
<script>
let classModalInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    classModalInstance = new bootstrap.Modal(document.getElementById('classModal'));

    // Open Add Modal
    document.getElementById('btnOpenAddModal').addEventListener('click', () => {
        openClassModal();
    });

    // Form submission via AJAX
    document.getElementById('classForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveClassAjax();
    });

    // Search filter
    document.getElementById('classSearchInput').addEventListener('input', function() {
        filterClassesTable(this.value.toLowerCase().trim());
    });

    document.getElementById('btnClearSearch').addEventListener('click', function() {
        document.getElementById('classSearchInput').value = '';
        filterClassesTable('');
    });
});

function openClassModal(classData = null) {
    const errorAlert = document.getElementById('modalErrorAlert');
    errorAlert.classList.add('d-none');
    errorAlert.textContent = '';

    const titleEl = document.getElementById('classModalTitle');
    const idInput = document.getElementById('modalClassId');
    const nameInput = document.getElementById('modalClassName');
    const sectionInput = document.getElementById('modalSection');
    const teacherNameInput = document.getElementById('modalClassTeacherName');
    const roomInput = document.getElementById('modalRoomNumber');
    const statusInput = document.getElementById('modalStatus');

    if (classData) {
        titleEl.innerHTML = '<i class="bi bi-pencil-square text-info me-2"></i> Edit Class';
        idInput.value = classData.id;
        nameInput.value = classData.class_name || '';
        sectionInput.value = classData.section || '';
        teacherNameInput.value = classData.class_teacher_name || '';
        roomInput.value = classData.room_number || '';
        statusInput.value = classData.status || 'active';
    } else {
        titleEl.innerHTML = '<i class="bi bi-building text-info me-2"></i> Add New Class';
        idInput.value = '0';
        nameInput.value = '';
        sectionInput.value = 'A';
        teacherNameInput.value = '';
        roomInput.value = '';
        statusInput.value = 'active';
    }

    classModalInstance.show();
    setTimeout(() => nameInput.focus(), 400);
}

function editClass(id) {
    const formData = new FormData();
    formData.append('ajax_action', 'get_class');
    formData.append('id', id);

    fetch('classes.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.class) {
            openClassModal(data.class);
        } else {
            showToast(data.message || 'Could not load class data.', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error while retrieving class data.', 'danger');
    });
}

function saveClassAjax() {
    const form = document.getElementById('classForm');
    const errorAlert = document.getElementById('modalErrorAlert');
    const spinner = document.getElementById('saveSpinner');
    const icon = document.getElementById('saveIcon');
    const btn = document.getElementById('btnSaveClass');

    errorAlert.classList.add('d-none');
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    btn.disabled = true;

    const formData = new FormData(form);

    fetch('classes.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
        btn.disabled = false;

        if (data.success) {
            classModalInstance.hide();
            showToast(data.message, 'success');
            renderOrUpdateClassRow(data.class, data.is_edit);
        } else {
            errorAlert.textContent = data.message || 'Validation error.';
            errorAlert.classList.remove('d-none');
        }
    })
    .catch(err => {
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
        btn.disabled = false;
        errorAlert.textContent = 'Server/Network error while saving class.';
        errorAlert.classList.remove('d-none');
    });
}

function renderOrUpdateClassRow(c, isEdit) {
    const tbody = document.getElementById('classesTableBody');
    const noRow = document.getElementById('noRecordsRow');
    if (noRow) noRow.remove();

    const teacherBadge = (c.class_teacher_name && c.class_teacher_name.trim() !== '')
        ? `<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1"><i class="bi bi-person me-1"></i>${escapeHtml(c.class_teacher_name)}</span>` 
        : `<span class="badge bg-light text-muted border"><i class="bi bi-dash me-1"></i> Not Assigned</span>`;

    const statusBadge = c.status === 'active' 
        ? `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i> Active</span>`
        : `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="bi bi-dash-circle me-1"></i> Inactive</span>`;

    const rowHtml = `
        <td class="ps-3">
            <span class="badge bg-light text-dark border font-monospace">${c.id}</span>
        </td>
        <td>
            <strong class="text-dark class-name-cell">${escapeHtml(c.class_name)}</strong>
        </td>
        <td class="text-center">
            <span class="badge bg-secondary-subtle text-secondary-emphasis border px-2 py-1 section-cell">
                Section ${escapeHtml(c.section)}
            </span>
        </td>
        <td class="teacher-cell">${teacherBadge}</td>
        <td class="text-secondary small room-cell">
            <i class="bi bi-geo-alt text-muted me-1"></i>${escapeHtml(c.room_number || 'N/A')}
        </td>
        <td class="text-center small">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                ${c.student_count || 0}
            </span>
        </td>
        <td class="text-center status-cell">${statusBadge}</td>
        <td class="pe-3 text-end">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary" onclick="editClass(${c.id})" title="Edit Class">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="deleteClass(${c.id}, '${escapeJs(c.class_name)} - ${escapeJs(c.section)}')" title="Delete Class">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </td>
    `;

    if (isEdit) {
        const existingRow = document.getElementById(`classRow${c.id}`);
        if (existingRow) {
            existingRow.innerHTML = rowHtml;
            existingRow.dataset.className = (c.class_name || '').toLowerCase();
            existingRow.dataset.section = (c.section || '').toLowerCase();
            existingRow.dataset.room = (c.room_number || '').toLowerCase();
            existingRow.dataset.teacher = (c.class_teacher_name || '').toLowerCase();
            highlightRow(existingRow);
        }
    } else {
        const newRow = document.createElement('tr');
        newRow.id = `classRow${c.id}`;
        newRow.dataset.className = (c.class_name || '').toLowerCase();
        newRow.dataset.section = (c.section || '').toLowerCase();
        newRow.dataset.room = (c.room_number || '').toLowerCase();
        newRow.dataset.teacher = (c.class_teacher_name || '').toLowerCase();
        newRow.innerHTML = rowHtml;
        tbody.prepend(newRow);
        highlightRow(newRow);
        updateClassCounts(1);
    }
}

function deleteClass(id, name) {
    if (!confirm(`Are you sure you want to delete class '${name}'? This action cannot be undone.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('ajax_action', 'delete_class');
    formData.append('id', id);

    fetch('classes.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`classRow${id}`);
            if (row) {
                row.classList.add('table-danger');
                setTimeout(() => {
                    row.remove();
                    updateClassCounts(-1);
                }, 300);
            }
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Could not delete class.', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error during deletion.', 'danger');
    });
}

function filterClassesTable(query) {
    const rows = document.querySelectorAll('#classesTableBody tr');
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

function updateClassCounts(delta) {
    const totalEl = document.getElementById('totalClassesBadge');
    const cur = parseInt(totalEl.textContent.replace(/\D/g, '')) || 0;
    const n = Math.max(0, cur + delta);
    totalEl.textContent = `Total: ${n} Classes`;
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
