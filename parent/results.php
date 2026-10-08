<?php
/**
 * parent/results.php — Child Academic Results & Marks
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/parent_helper.php';

require_login();
require_role(['parent']);
require_permission('marks.view');

$user = current_user();
$student = get_parent_student($user['id']);

$page_title  = 'Exam Results';
$active_menu = 'parent_results';
$breadcrumb  = ['Parent Portal', 'Results'];
$depth       = '../';

$term_filter = isset($_GET['term']) ? trim($_GET['term']) : '';

$available_terms = [];
$marks_list = [];

$summary = [
    'total_possible' => 0,
    'total_obtained' => 0,
    'percentage'     => 0,
    'grade'          => 'N/A',
    'count'          => 0
];

function calculate_grade($pct) {
    if ($pct >= 90) return ['grade' => 'A+', 'color' => 'success'];
    if ($pct >= 80) return ['grade' => 'A',  'color' => 'success'];
    if ($pct >= 70) return ['grade' => 'B',  'color' => 'primary'];
    if ($pct >= 60) return ['grade' => 'C',  'color' => 'info'];
    if ($pct >= 50) return ['grade' => 'D',  'color' => 'warning'];
    return ['grade' => 'F', 'color' => 'danger'];
}

if ($student) {
    $stu_id = (int)$student['id'];

    // Distinct terms for filter dropdown
    $term_rows = db_fetch_all("SELECT DISTINCT term FROM marks WHERE student_id = ? ORDER BY term DESC", 'i', [$stu_id]);
    $available_terms = array_column($term_rows, 'term');

    // Default to the latest term if no filter specified and terms exist
    if ($term_filter === '' && !empty($available_terms)) {
        $term_filter = $available_terms[0];
    }

    if ($term_filter !== '' && $term_filter !== 'all') {
        $marks_list = db_fetch_all(
            "SELECT m.*, sub.subject_name, sub.subject_code, u.name AS teacher_name 
             FROM marks m 
             JOIN subjects sub ON m.subject_id = sub.id 
             LEFT JOIN users u ON m.entered_by = u.id 
             WHERE m.student_id = ? AND m.term = ? 
             ORDER BY sub.subject_name ASC",
            'is',
            [$stu_id, $term_filter]
        );
    } else {
        $marks_list = db_fetch_all(
            "SELECT m.*, sub.subject_name, sub.subject_code, u.name AS teacher_name 
             FROM marks m 
             JOIN subjects sub ON m.subject_id = sub.id 
             LEFT JOIN users u ON m.entered_by = u.id 
             WHERE m.student_id = ? 
             ORDER BY m.term DESC, sub.subject_name ASC",
            'i',
            [$stu_id]
        );
    }

    foreach ($marks_list as $m) {
        $summary['total_obtained'] += (float)$m['marks_obtained'];
        $summary['total_possible'] += (float)$m['total_marks'];
        $summary['count']++;
    }

    if ($summary['total_possible'] > 0) {
        $summary['percentage'] = round(($summary['total_obtained'] / $summary['total_possible']) * 100, 1);
        $grade_info = calculate_grade($summary['percentage']);
        $summary['grade'] = $grade_info['grade'];
        $summary['grade_color'] = $grade_info['color'];
    }
}

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

    <!-- Student Header Summary -->
    <div class="card-ams mb-4">
        <div class="card-body-ams d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-navy mb-1">
                    <i class="fas fa-award me-2 text-primary"></i>Academic Performance &amp; Results
                </h4>
                <div class="text-muted small">
                    Student: <strong class="text-dark"><?= h($student['first_name'] . ' ' . $student['last_name']) ?></strong> 
                    &bull; Roll No: <strong><?= h($student['student_id']) ?></strong> 
                    &bull; Class: <strong><?= h($student['class_name'] ?? 'Unassigned') ?><?= !empty($student['section']) ? ' (' . h($student['section']) . ')' : '' ?></strong>
                </div>
            </div>
            <!-- Term Selector Form -->
            <form method="GET" class="d-flex align-items-center gap-2">
                <label for="term" class="form-label mb-0 small text-muted fw-semibold">Term:</label>
                <select name="term" id="term" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= $term_filter === 'all' ? 'selected' : '' ?>>All Terms</option>
                    <?php foreach ($available_terms as $t): ?>
                        <option value="<?= h($t) ?>" <?= $term_filter === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($available_terms)): ?>
                    <span class="text-muted small italic">(No terms recorded yet)</span>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Term Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Subjects Graded</div>
                <h3 class="fw-bold mb-0 text-navy"><?= $summary['count'] ?></h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Marks Obtained</div>
                <h3 class="fw-bold mb-0 text-primary">
                    <?= number_format($summary['total_obtained'], 1) ?> <span class="text-muted fs-6">/ <?= number_format($summary['total_possible'], 0) ?></span>
                </h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Average Percentage</div>
                <h3 class="fw-bold mb-0 text-success"><?= $summary['percentage'] ?>%</h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-ams p-3 text-center">
                <div class="text-muted small fw-semibold text-uppercase">Overall Grade</div>
                <h3 class="fw-bold mb-0 text-<?= $summary['grade_color'] ?? 'secondary' ?>"><?= $summary['grade'] ?></h3>
            </div>
        </div>
    </div>

    <!-- Marks Table -->
    <div class="card-ams">
        <div class="card-header-ams d-flex justify-content-between align-items-center">
            <span class="fw-bold text-navy">
                <i class="fas fa-file-alt me-2 text-primary"></i>Subject Marks Breakdown
            </span>
            <span class="badge bg-primary-subtle text-primary border">
                <?= $term_filter === 'all' ? 'All Terms' : ($term_filter ?: 'No Term Selected') ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Subject</th>
                        <th>Term</th>
                        <th>Marks Obtained</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                        <th>Teacher Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($marks_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-clipboard-list fa-2x mb-2 text-secondary"></i><br>
                                No marks recorded for the selected term.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $idx = 1; foreach ($marks_list as $row): 
                            $pct = ($row['total_marks'] > 0) ? round(($row['marks_obtained'] / $row['total_marks']) * 100, 1) : 0;
                            $g = calculate_grade($pct);
                        ?>
                            <tr>
                                <td class="text-muted"><?= $idx++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= h($row['subject_name']) ?></div>
                                    <small class="text-muted"><?= h($row['subject_code']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-dark"><?= h($row['term']) ?></span>
                                </td>
                                <td>
                                    <strong><?= number_format($row['marks_obtained'], 1) ?></strong> / <?= number_format($row['total_marks'], 0) ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; min-width: 60px;">
                                            <div class="progress-bar bg-<?= $g['color'] ?>" style="width: <?= min(100, $pct) ?>%;"></div>
                                        </div>
                                        <span class="small fw-bold"><?= $pct ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $g['color'] ?>"><?= $g['grade'] ?></span>
                                </td>
                                <td>
                                    <small class="text-muted"><?= !empty($row['remarks']) ? h($row['remarks']) : '&mdash;' ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php
include '../layout_end.php';
?>
