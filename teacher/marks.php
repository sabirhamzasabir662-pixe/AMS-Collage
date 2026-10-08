<?php
/**
 * teacher/marks.php — Enter & Edit Student Marks
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/teacher_helper.php';

require_login();
require_role(['teacher']);
require_permission('marks.enter');

$user = current_user();

$page_title  = 'Enter Marks';
$active_menu = 'teacher_marks';
$breadcrumb  = ['Teacher Portal', 'Enter Marks'];
$depth       = '../';

// Fetch teacher's assigned classes
$assigned_classes = get_teacher_assigned_classes($user['id']);

$selected_class   = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$selected_subject = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
$selected_term    = trim($_GET['term'] ?? $_POST['term'] ?? 'Midterm 2026');

$class_info       = null;
$subjects_list    = [];
$students_marks   = [];

// If class is selected, verify assignment & load subjects
if ($selected_class > 0) {
    if (!is_teacher_assigned_to_class($user['id'], $selected_class)) {
        set_flash('error', 'Access Denied: You are not authorized to manage marks for this class.');
        redirect('marks.php');
    }

    $class_info = db_fetch_one("SELECT id, class_name, section FROM classes WHERE id = ? LIMIT 1", 'i', [$selected_class]);
    $subjects_list = db_fetch_all("SELECT id, subject_name, subject_code FROM subjects WHERE class_id = ? AND status = 'active' ORDER BY subject_name ASC", 'i', [$selected_class]);

    // If subject is selected, load students with marks
    if ($selected_subject > 0 && $selected_term !== '') {
        // Verify subject belongs to this class
        $subj_check = db_fetch_one("SELECT id, subject_name FROM subjects WHERE id = ? AND class_id = ? LIMIT 1", 'ii', [$selected_subject, $selected_class]);
        if (!$subj_check) {
            set_flash('error', 'The selected subject does not belong to this class.');
            redirect("marks.php?class_id={$selected_class}");
        }

        $sql = "SELECT s.id AS stu_id, s.student_id, s.first_name, s.last_name,
                       m.id AS mark_id, m.marks_obtained, m.total_marks, m.remarks, m.entered_by
                FROM students s
                LEFT JOIN marks m ON m.student_id = s.id AND m.subject_id = ? AND m.term = ?
                WHERE s.class_id = ? AND s.status = 'active'
                ORDER BY s.first_name ASC, s.last_name ASC";
        $students_marks = db_fetch_all($sql, 'isi', [$selected_subject, $selected_term, $selected_class]);
    }
}

// Handle Marks Save/Update Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $post_class_id   = (int)($_POST['class_id'] ?? 0);
    $post_subject_id = (int)($_POST['subject_id'] ?? 0);
    $post_term       = trim($_POST['term'] ?? '');
    $marks_data      = $_POST['marks'] ?? [];

    // STRICT SERVER-SIDE CHECK: teacher owns class
    if (!is_teacher_assigned_to_class($user['id'], $post_class_id)) {
        set_flash('error', 'Security Violation: You are not assigned to this class.');
        redirect('marks.php');
    }

    // Verify subject belongs to class
    $subj = db_fetch_one("SELECT id, subject_name FROM subjects WHERE id = ? AND class_id = ? LIMIT 1", 'ii', [$post_subject_id, $post_class_id]);
    if (!$subj) {
        set_flash('error', 'Invalid subject selected for this class.');
        redirect("marks.php?class_id={$post_class_id}");
    }

    if ($post_term === '') {
        set_flash('error', 'Please specify an examination term.');
        redirect("marks.php?class_id={$post_class_id}&subject_id={$post_subject_id}");
    }

    $saved_count = 0;
    foreach ($marks_data as $stu_id => $row) {
        $stu_id = (int)$stu_id;
        $obtained_str = trim($row['obtained'] ?? '');
        $total_val    = (float)($row['total'] ?? 100.00);
        $remarks      = trim($row['remarks'] ?? '');

        // If obtained marks was left blank, skip recording
        if ($obtained_str === '') continue;

        $obtained_val = (float)$obtained_str;
        if ($obtained_val < 0) $obtained_val = 0.00;
        if ($total_val <= 0)   $total_val = 100.00;
        if ($obtained_val > $total_val) $obtained_val = $total_val;

        // Verify student belongs to this assigned class
        $stu = db_fetch_one("SELECT id FROM students WHERE id = ? AND class_id = ? LIMIT 1", 'ii', [$stu_id, $post_class_id]);
        if (!$stu) continue;

        // Check if mark row already exists
        $existing_mark = db_fetch_one(
            "SELECT id, entered_by FROM marks WHERE student_id = ? AND subject_id = ? AND term = ? LIMIT 1",
            'iis',
            [$stu_id, $post_subject_id, $post_term]
        );

        if ($existing_mark) {
            // Update existing marks
            db_execute(
                "UPDATE marks 
                 SET marks_obtained = ?, total_marks = ?, remarks = ?, entered_by = ? 
                 WHERE id = ?",
                'ddssi',
                [$obtained_val, $total_val, $remarks, $user['id'], $existing_mark['id']]
            );
        } else {
            // Insert new marks record
            db_execute(
                "INSERT INTO marks (student_id, subject_id, term, marks_obtained, total_marks, remarks, entered_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                'iisddsi',
                [$stu_id, $post_subject_id, $post_term, $obtained_val, $total_val, $remarks, $user['id']]
            );
        }
        $saved_count++;
    }

    $cls_row = db_fetch_one("SELECT class_name, section FROM classes WHERE id = ? LIMIT 1", 'i', [$post_class_id]);
    $cls_title = $cls_row ? ($cls_row['class_name'] . ' ' . $cls_row['section']) : "Class #{$post_class_id}";

    log_activity(
        (int)$user['id'],
        'teacher',
        'Entered/updated marks',
        'class',
        $post_class_id,
        "Entered marks for {$cls_title}, Subject '{$subj['subject_name']}', Term '{$post_term}' ({$saved_count} student records saved)."
    );

    set_flash('success', "Marks saved successfully for {$cls_title} &bull; {$subj['subject_name']} &bull; {$post_term} ({$saved_count} records).");
    redirect("marks.php?class_id={$post_class_id}&subject_id={$post_subject_id}&term=" . urlencode($post_term));
}

include '../layout.php';
?>

<!-- Filter & Selection Bar -->
<div class="card-ams mb-4">
    <div class="card-header-ams">
        <h5 class="mb-0"><i class="fas fa-filter text-primary me-2"></i> Select Class, Subject &amp; Term</h5>
    </div>
    <div class="card-body-ams p-3">
        <form method="GET" action="marks.php" id="marksFilterForm">
            <div class="row g-3 align-items-end">
                <!-- Class Selection -->
                <div class="col-md-4">
                    <label class="form-label-ams fw-semibold">Assigned Class</label>
                    <select name="class_id" class="form-control-ams" onchange="document.getElementById('marksFilterForm').submit()" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach ($assigned_classes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $selected_class === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= h($c['class_name'] . ' - ' . $c['section']) ?> (<?= (int)$c['student_count'] ?> students)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Subject Selection -->
                <div class="col-md-4">
                    <label class="form-label-ams fw-semibold">Subject</label>
                    <select name="subject_id" class="form-control-ams" <?= empty($subjects_list) ? 'disabled' : '' ?> required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects_list as $sb): ?>
                        <option value="<?= (int)$sb['id'] ?>" <?= $selected_subject === (int)$sb['id'] ? 'selected' : '' ?>>
                            <?= h($sb['subject_name']) ?> (<?= h($sb['subject_code']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Term Selection -->
                <div class="col-md-2">
                    <label class="form-label-ams fw-semibold">Examination Term</label>
                    <input type="text" name="term" class="form-control-ams" value="<?= h($selected_term) ?>" placeholder="e.g. Midterm 2026" required>
                </div>

                <!-- Action Button -->
                <div class="col-md-2">
                    <button type="submit" class="btn-ams btn-primary-ams w-100">
                        <i class="fas fa-search me-1"></i> Load Marks
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Marks Entry Table -->
<?php if ($selected_class > 0 && $selected_subject > 0): ?>
    <?php
    $cur_subject = db_fetch_one("SELECT subject_name, subject_code FROM subjects WHERE id = ? LIMIT 1", 'i', [$selected_subject]);
    ?>
    <form method="POST" action="marks.php">
        <input type="hidden" name="class_id" value="<?= (int)$selected_class ?>">
        <input type="hidden" name="subject_id" value="<?= (int)$selected_subject ?>">
        <input type="hidden" name="term" value="<?= h($selected_term) ?>">

        <div class="card-ams">
            <div class="card-header-ams d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 text-dark">
                        <i class="fas fa-file-signature text-primary me-2"></i>
                        Marks Entry: <?= h($class_info['class_name']) ?> (<?= h($class_info['section']) ?>) &bull; <?= h($cur_subject['subject_name'] ?? '') ?>
                    </h5>
                    <small class="text-muted">Term: <strong><?= h($selected_term) ?></strong> &bull; <?= count($students_marks) ?> student(s) enrolled</small>
                </div>
                <div>
                    <button type="submit" name="save_marks" value="1" class="btn-ams btn-primary-ams btn-sm px-3">
                        <i class="fas fa-save me-1"></i> Save Marks
                    </button>
                </div>
            </div>

            <div class="card-body-ams p-0">
                <?php if (empty($students_marks)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-user-slash fa-2x mb-2 text-secondary"></i>
                        <p class="mb-0 fw-semibold">No active students found in this class.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th style="width: 140px;">Total Marks</th>
                                    <th style="width: 150px;">Marks Obtained</th>
                                    <th style="width: 100px;" class="text-center">Percentage</th>
                                    <th>Remarks / Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($students_marks as $sm): ?>
                                <?php
                                $obt = ($sm['marks_obtained'] !== null) ? (float)$sm['marks_obtained'] : '';
                                $tot = ($sm['total_marks'] !== null) ? (float)$sm['total_marks'] : 100.00;
                                $pct = ($obt !== '' && $tot > 0) ? round(($obt / $tot) * 100, 1) : null;
                                ?>
                                <tr>
                                    <td class="text-center text-muted fw-bold"><?= $i++ ?></td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-dark font-monospace border">
                                            <?= h($sm['student_id']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= h($sm['first_name'] . ' ' . $sm['last_name']) ?></div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="1" max="1000"
                                               name="marks[<?= (int)$sm['stu_id'] ?>][total]" 
                                               class="form-control form-control-sm total-input text-center" 
                                               value="<?= h((string)$tot) ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="1000"
                                               name="marks[<?= (int)$sm['stu_id'] ?>][obtained]" 
                                               class="form-control form-control-sm obtained-input text-center fw-bold" 
                                               value="<?= h((string)$obt) ?>" placeholder="e.g. 85">
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= ($pct !== null && $pct >= 50) ? 'bg-success-subtle text-success' : ($pct !== null ? 'bg-danger-subtle text-danger' : 'bg-light text-muted') ?> pct-badge">
                                            <?= ($pct !== null) ? "{$pct}%" : '—' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="marks[<?= (int)$sm['stu_id'] ?>][remarks]" 
                                               class="form-control form-control-sm" 
                                               value="<?= h($sm['remarks'] ?? '') ?>" placeholder="Optional remarks">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                        <span class="small text-muted">
                            <i class="fas fa-info-circle me-1"></i> Changes will update marks recorded in the database with your Teacher ID.
                        </span>
                        <button type="submit" name="save_marks" value="1" class="btn-ams btn-primary-ams px-4">
                            <i class="fas fa-save me-1"></i> Save All Marks
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
<?php elseif ($selected_class > 0 && empty($subjects_list)): ?>
    <div class="card-ams">
        <div class="card-body-ams text-center py-5 text-muted">
            <i class="fas fa-book-open fa-3x mb-3 text-warning"></i>
            <h5>No Subjects Found</h5>
            <p class="mb-0">There are currently no active subjects configured for this class in the system.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card-ams">
        <div class="card-body-ams text-center py-5 text-muted">
            <i class="fas fa-file-alt fa-3x mb-3 text-secondary"></i>
            <h5>Select a Class &amp; Subject</h5>
            <p class="mb-0">Choose one of your assigned classes and a subject from the selection bar above to record student marks.</p>
        </div>
    </div>
<?php endif; ?>

<?php
$extra_js = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Dynamic percentage badge update on mark entry
    document.querySelectorAll('tr').forEach(row => {
        const obtInput = row.querySelector('.obtained-input');
        const totInput = row.querySelector('.total-input');
        const pctBadge = row.querySelector('.pct-badge');

        if (obtInput && totInput && pctBadge) {
            const updatePct = () => {
                const obt = parseFloat(obtInput.value);
                const tot = parseFloat(totInput.value);
                if (!isNaN(obt) && !isNaN(tot) && tot > 0) {
                    const pct = Math.round((obt / tot) * 1000) / 10;
                    pctBadge.textContent = pct + '%';
                    pctBadge.className = 'badge pct-badge ' + (pct >= 50 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger');
                } else {
                    pctBadge.textContent = '—';
                    pctBadge.className = 'badge pct-badge bg-light text-muted';
                }
            };
            obtInput.addEventListener('input', updatePct);
            totInput.addEventListener('input', updatePct);
        }
    });
});
</script>
HTML;

include '../layout_end.php';
?>
