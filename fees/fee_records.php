<?php
/**
 * fees/fee_records.php — Fee Records List, Filtering, Summary & Actions
 * Arsalan Model School and College Management System
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/fees_helper.php';
require_login();
require_permission('fees.view');
$user = current_user();

$page_title  = 'Fee Records';
$active_menu = 'fees_list';
$breadcrumb  = ['Fees', 'Fee Records'];
$depth       = '../';

// Filter inputs
$per_page  = 10;
$page      = max(1, (int)($_GET['page'] ?? 1));
$search    = trim($_GET['search'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date   = trim($_GET['to_date'] ?? '');
$status_f  = trim($_GET['status'] ?? '');

$where  = "WHERE 1=1";
$types  = '';
$params = [];

// Server-side prepared statement search on student name & ID
if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
    $types .= 'ssss';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

// Date range filter on payment_date
if ($from_date !== '') {
    $where .= " AND f.payment_date >= ?";
    $types .= 's';
    $params[] = $from_date;
}

if ($to_date !== '') {
    $where .= " AND f.payment_date <= ?";
    $types .= 's';
    $params[] = $to_date;
}

// Optional status filter
if (in_array($status_f, ['paid', 'pending'], true)) {
    $where .= " AND f.status = ?";
    $types .= 's';
    $params[] = $status_f;
}

// ── Summary Totals Query (Prepared Statement) ──
$summary_sql = "SELECT 
    COALESCE(SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END), 0) AS total_collected,
    COALESCE(SUM(CASE WHEN f.status = 'pending' THEN f.amount ELSE 0 END), 0) AS total_pending,
    COUNT(CASE WHEN f.status = 'pending' THEN 1 END) AS pending_count,
    COUNT(*) AS total_count
 FROM fees f
 JOIN students s ON f.student_id = s.id
 $where";

$summary = fee_db_fetch_one($summary_sql, $params, $types);
$total_collected = (float)($summary['total_collected'] ?? 0);
$total_pending   = (float)($summary['total_pending'] ?? 0);
$pending_count   = (int)($summary['pending_count'] ?? 0);
$total_records   = (int)($summary['total_count'] ?? 0);

// Pagination calculations
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// ── Fetch Fee Rows (Prepared Statement) ──
$list_sql = "SELECT f.*, s.student_id AS stu_code, s.first_name, s.last_name, c.class_name, c.section
 FROM fees f
 JOIN students s ON f.student_id = s.id
 LEFT JOIN classes c ON f.class_id = c.id
 $where
 ORDER BY f.id DESC
 LIMIT ? OFFSET ?";

$list_types  = $types . 'ii';
$list_params = array_merge($params, [$per_page, $offset]);
$fees        = fee_db_fetch_all($list_sql, $list_params, $list_types);

// Query string for pagination links
$query_args = $_GET;
unset($query_args['page']);
$base_query = !empty($query_args) ? http_build_query($query_args) . '&' : '';

include '../layout.php';
?>

<!-- Link Bootstrap Icons if not in layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- Alert Container for Flash Messages -->
<div id="alertContainer">
    <?php fee_render_flash(); ?>
</div>

<!-- ═══════════ SUMMARY METRICS AT TOP ═══════════ -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 bg-success-subtle p-3 text-success me-3">
                    <i class="bi bi-check-circle-fill fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Total Amount Collected (Paid)</div>
                    <div class="fs-4 fw-bold text-dark">Rs. <?= number_format($total_collected, 2) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 bg-warning-subtle p-3 text-warning me-3">
                    <i class="bi bi-hourglass-split fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Total Pending Amount</div>
                    <div class="fs-4 fw-bold text-dark">Rs. <?= number_format($total_pending, 2) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-3 bg-primary-subtle p-3 text-primary me-3">
                    <i class="bi bi-clock-history fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Pending Payments Count</div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($pending_count) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ FEE RECORDS TABLE CARD ═══════════ -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0 text-primary fw-bold">
            <i class="bi bi-cash-stack me-2 text-success"></i> Fee Records 
            <span class="badge bg-secondary-subtle text-secondary border ms-2"><?= $total_records ?> Total</span>
        </h5>
        <a href="add_fee.php" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add New Fee
        </a>
    </div>
    <div class="card-body p-3 p-md-4">

        <!-- Filters Form -->
        <form method="GET" action="fee_records.php" class="row g-2 mb-4 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label small fw-semibold mb-1">
                    <i class="bi bi-search me-1"></i> Search Student Name or ID
                </label>
                <input type="text" name="search" class="form-control"
                       placeholder="e.g. Hamza, Bilal, STD-001..."
                       value="<?= h($search) ?>">
            </div>

            <div class="col-lg-2 col-md-3">
                <label class="form-label small fw-semibold mb-1">
                    <i class="bi bi-calendar-event me-1"></i> Payment From
                </label>
                <input type="date" name="from_date" class="form-control"
                       value="<?= h($from_date) ?>">
            </div>

            <div class="col-lg-2 col-md-3">
                <label class="form-label small fw-semibold mb-1">
                    <i class="bi bi-calendar-event me-1"></i> Payment To
                </label>
                <input type="date" name="to_date" class="form-control"
                       value="<?= h($to_date) ?>">
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-semibold mb-1">
                    <i class="bi bi-filter me-1"></i> Status
                </label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="paid"    <?= ($status_f === 'paid') ? 'selected' : '' ?>>Paid</option>
                    <option value="pending" <?= ($status_f === 'pending') ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="fee_records.php" class="btn btn-outline-secondary" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>

        <!-- Records Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="feeRecordsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 11%;">Student ID</th>
                        <th style="width: 17%;">Name</th>
                        <th style="width: 13%;">Class</th>
                        <th style="width: 12%;">Month / Year</th>
                        <th style="width: 11%;">Amount</th>
                        <th style="width: 11%;">Payment Date</th>
                        <th style="width: 8%;">Method</th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 14%; text-align: end;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($fees)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <strong>No fee records found</strong>
                            <p class="small text-muted mb-0">Try adjusting your search filters or add a new fee payment.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $row_num = $offset + 1; foreach ($fees as $f): ?>
                    <tr>
                        <td><?= $row_num++ ?></td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">
                                <?= h($f['stu_code']) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= h($f['first_name'] . ' ' . $f['last_name']) ?></strong>
                        </td>
                        <td>
                            <?= h($f['class_name'] ?? '—') ?>
                            <?= !empty($f['section']) ? '<span class="text-muted small">(' . h($f['section']) . ')</span>' : '' ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= h($f['fee_month']) ?></span>
                        </td>
                        <td>
                            <strong class="<?= ($f['status'] === 'paid') ? 'text-success' : 'text-warning' ?>">
                                Rs. <?= number_format((float)$f['amount'], 2) ?>
                            </strong>
                        </td>
                        <td>
                            <?php if (!empty($f['payment_date'])): ?>
                                <span class="small">
                                    <i class="bi bi-calendar-check text-muted me-1"></i>
                                    <?= date('d M Y', strtotime($f['payment_date'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size: .75rem;">
                                <?= h($f['payment_method']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($f['status'] === 'paid'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle-fill me-1"></i> Paid
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                    <i class="bi bi-clock-fill me-1"></i> Pending
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Fee Actions">
                                <!-- Print Receipt Button -->
                                <a href="print_receipt.php?id=<?= (int)$f['id']; ?>"
                                   class="btn btn-outline-secondary"
                                   title="Print Official Receipt">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <!-- Edit Button -->
                                <a href="edit_fee.php?id=<?= (int)$f['id']; ?>"
                                   class="btn btn-outline-primary"
                                   title="Edit Fee Record">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <!-- Delete Button with Confirmation -->
                                <a href="delete_fee.php?id=<?= (int)$f['id']; ?>"
                                   class="btn btn-outline-danger"
                                   title="Delete Fee Record"
                                   onclick="return confirm('Are you sure you want to delete fee record #<?= (int)$f['id']; ?> for <?= h(addslashes($f['first_name'] . ' ' . $f['last_name'])); ?> (<?= h(addslashes($f['fee_month'])); ?>)? This action cannot be undone.');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══════════ PAGINATION ═══════════ -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top">
            <div class="text-muted small">
                Showing <strong><?= min($offset + 1, $total_records) ?></strong> to <strong><?= min($offset + $per_page, $total_records) ?></strong> of <strong><?= $total_records ?></strong> records
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="fee_records.php?<?= $base_query ?>page=<?= max(1, $page - 1) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                        $start_p = max(1, $page - 2);
                        $end_p   = min($total_pages, $page + 2);
                        if ($start_p > 1) {
                            echo '<li class="page-item"><a class="page-link" href="fee_records.php?' . $base_query . 'page=1">1</a></li>';
                            if ($start_p > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        for ($p = $start_p; $p <= $end_p; $p++):
                    ?>
                    <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="fee_records.php?<?= $base_query ?>page=<?= $p ?>"><?= $p ?></a>
                    </li>
                    <?php
                        endfor;
                        if ($end_p < $total_pages) {
                            if ($end_p < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="fee_records.php?' . $base_query . 'page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                    ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="fee_records.php?<?= $base_query ?>page=<?= min($total_pages, $page + 1) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../layout_end.php'; ?>
