<?php
/**
 * attendance/attendance_records.php
 */
require_once '../database.php';
require_login();
require_permission('attendance.view');
$user = current_user();

$page_title  = 'Attendance Records';
$active_menu = 'attendance_records';
$breadcrumb  = ['Attendance', 'Records'];
$depth       = '../';

$classes = db_fetch_all("SELECT id, class_name, section FROM classes WHERE status='active' ORDER BY class_name");

$tab        = $_GET['tab']        ?? 'students';
$from_date  = $_GET['from_date']  ?? date('Y-m-01');
$to_date    = $_GET['to_date']    ?? date('Y-m-d');
$class_f    = (int)($_GET['class_id'] ?? 0);
$search     = trim($_GET['search'] ?? '');

$per_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));

if ($tab === 'students') {
    $where  = "WHERE sa.attendance_date BETWEEN ? AND ?";
    $types  = 'ss';
    $params = [$from_date, $to_date];

    if ($class_f) {
        $where .= " AND sa.class_id=?"; $types .= 'i'; $params[] = $class_f;
    }
    if ($search !== '') {
        $like   = '%'.$search.'%';
        $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
        $types  .= 'sss'; $params = array_merge($params, [$like,$like,$like]);
    }

    $total = db_count(
        "SELECT COUNT(*) FROM student_attendance sa JOIN students s ON sa.student_id=s.id $where",
        $types, $params
    );
    $total_pages = max(1, (int)ceil($total/$per_page));
    $page = min($page, $total_pages);
    $offset = ($page-1)*$per_page;

    $records = db_fetch_all(
        "SELECT sa.*, s.student_id AS stu_id, s.first_name, s.last_name, c.class_name
         FROM student_attendance sa
         JOIN students s ON sa.student_id=s.id
         LEFT JOIN classes c ON sa.class_id=c.id
         $where ORDER BY sa.attendance_date DESC, s.first_name LIMIT $per_page OFFSET $offset",
        $types, $params
    );

    // Attendance % per student in range
    $stats = db_fetch_all(
        "SELECT s.student_id AS stu_id, s.first_name, s.last_name,
                COUNT(*) AS total_days,
                SUM(sa.status='Present') AS present_days,
                ROUND(SUM(sa.status='Present')*100/COUNT(*),1) AS pct
         FROM student_attendance sa
         JOIN students s ON sa.student_id=s.id
         $where GROUP BY sa.student_id ORDER BY pct ASC LIMIT 10",
        $types, $params
    );

} else {
    // Teachers
    $where  = "WHERE ta.attendance_date BETWEEN ? AND ?";
    $types  = 'ss';
    $params = [$from_date, $to_date];
    if ($search !== '') {
        $like   = '%'.$search.'%';
        $where .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.teacher_id LIKE ?)";
        $types  .= 'sss'; $params = array_merge($params, [$like,$like,$like]);
    }

    $total = db_count(
        "SELECT COUNT(*) FROM teacher_attendance ta JOIN teachers t ON ta.teacher_id=t.id $where",
        $types, $params
    );
    $total_pages = max(1,(int)ceil($total/$per_page));
    $page = min($page,$total_pages);
    $offset = ($page-1)*$per_page;

    $records = db_fetch_all(
        "SELECT ta.*, t.teacher_id AS tch_id, t.first_name, t.last_name
         FROM teacher_attendance ta
         JOIN teachers t ON ta.teacher_id=t.id
         $where ORDER BY ta.attendance_date DESC, t.first_name LIMIT $per_page OFFSET $offset",
        $types, $params
    );

    $stats = [];
}

include '../layout.php';
?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" style="border-bottom:2px solid #dee2e6;">
    <li class="nav-item">
        <a class="nav-link <?= $tab==='students'?'active':'' ?>"
           href="?tab=students&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>">
            <i class="fas fa-user-graduate me-1"></i> Students
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='teachers'?'active':'' ?>"
           href="?tab=teachers&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>">
            <i class="fas fa-chalkboard-teacher me-1"></i> Teachers
        </a>
    </li>
</ul>

<!-- Filters -->
<form method="GET" class="row g-2 mb-4">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <div class="col-md-2">
        <input type="date" name="from_date" class="form-control-ams" value="<?= h($from_date) ?>" title="From Date">
    </div>
    <div class="col-md-2">
        <input type="date" name="to_date" class="form-control-ams" value="<?= h($to_date) ?>" title="To Date">
    </div>
    <?php if ($tab === 'students'): ?>
    <div class="col-md-2">
        <select name="class_id" class="form-control-ams">
            <option value="">All Classes</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_f==$c['id']?'selected':'' ?>>
                <?= h($c['class_name'].' '.$c['section']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-md-3">
        <div class="search-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search name or ID...">
        </div>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn-ams btn-primary-ams btn-sm-ams">Filter</button>
        <a href="?tab=<?= h($tab) ?>" class="btn-ams btn-secondary-ams btn-sm-ams">Reset</a>
    </div>
</form>

<div class="row g-4">
    <!-- Attendance Table -->
    <div class="col-lg-<?= !empty($stats)?'8':'12' ?>">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5>
                    <i class="fas fa-clipboard-list text-primary"></i>
                    <?= $tab==='students'?'Student':'Teacher' ?> Attendance Records
                    <span class="badge bg-primary ms-2"><?= $total ?></span>
                </h5>
            </div>
            <div class="table-responsive-ams">
                <table class="table-ams">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>Name</th>
                            <?php if ($tab==='students'): ?><th>Class</th><?php endif; ?>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="6"><div class="no-data"><i class="fas fa-calendar-times"></i>No attendance records found</div></td></tr>
                    <?php else: ?>
                        <?php $i=$offset+1; foreach ($records as $r): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><span class="fw-600 text-accent"><?= h($r[$tab==='students'?'stu_id':'tch_id']) ?></span></td>
                            <td class="fw-600"><?= h($r['first_name'].' '.$r['last_name']) ?></td>
                            <?php if ($tab==='students'): ?>
                            <td><?= h($r['class_name'] ?? '—') ?></td>
                            <?php endif; ?>
                            <td><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
                            <td>
                                <span class="badge-ams badge-<?= strtolower($r['status']) ?>">
                                    <i class="fas fa-<?= $r['status']==='Present'?'check':'times' ?>-circle" style="font-size:.7rem;"></i>
                                    <?= h($r['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
            <div style="padding:16px;">
                <div class="pagination-ams">
                    <a class="page-btn <?= $page<=1?'disabled':'' ?>"
                       href="?tab=<?= h($tab) ?>&page=<?= $page-1 ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php for ($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
                    <a class="page-btn <?= $p==$page?'active':'' ?>"
                       href="?tab=<?= h($tab) ?>&page=<?= $p ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"
                       href="?tab=<?= h($tab) ?>&page=<?= $page+1 ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Attendance % Summary (students only) -->
    <?php if ($tab==='students' && !empty($stats)): ?>
    <div class="col-lg-4">
        <div class="card-ams">
            <div class="card-header-ams">
                <h5><i class="fas fa-chart-bar text-warning"></i> Low Attendance</h5>
            </div>
            <div class="card-body-ams">
                <p class="text-muted mb-3" style="font-size:.8rem;">Students with lowest attendance in selected range</p>
                <?php foreach ($stats as $s): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem;">
                        <span class="fw-600"><?= h($s['first_name'].' '.$s['last_name']) ?></span>
                        <span class="<?= $s['pct']<75?'text-danger':'text-success' ?> fw-700"><?= $s['pct'] ?>%</span>
                    </div>
                    <div style="background:#f0f0f0;border-radius:4px;height:6px;">
                        <div style="background:<?= $s['pct']<75?'#dc3545':'#28a745' ?>;width:<?= $s['pct'] ?>%;height:6px;border-radius:4px;transition:width .5s;"></div>
                    </div>
                    <small class="text-muted"><?= $s['present_days'] ?>/<?= $s['total_days'] ?> days</small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../layout_end.php'; ?>
