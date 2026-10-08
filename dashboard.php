<?php
/**
 * dashboard.php — Admin Dashboard
 * Arsalan Model School and College Management System
 */
require_once 'database.php';
require_login();
require_role(['admin']);
$user = current_user();

// ── Stats ──────────────────────────────────────────────
$total_students  = db_count("SELECT COUNT(*) FROM students WHERE status='active'");
$total_teachers  = db_count("SELECT COUNT(*) FROM teachers WHERE status='active'");
$total_classes   = db_count("SELECT COUNT(*) FROM classes WHERE status='active'");
$total_subjects  = db_count("SELECT COUNT(*) FROM subjects WHERE status='active'");
$total_fees      = db_fetch_one("SELECT COALESCE(SUM(amount),0) AS total FROM fees WHERE status='paid'");
$fees_collected  = number_format((float)($total_fees['total'] ?? 0), 2);

// Today's attendance
$today = date('Y-m-d');
$att_present = db_count("SELECT COUNT(*) FROM student_attendance WHERE attendance_date=? AND status='Present'", 's', [$today]);
$att_absent  = db_count("SELECT COUNT(*) FROM student_attendance WHERE attendance_date=? AND status='Absent'",  's', [$today]);

// Recent students
$recent_students = db_fetch_all(
    "SELECT s.student_id, s.first_name, s.last_name, c.class_name, s.status, s.created_at
     FROM students s
     LEFT JOIN classes c ON s.class_id = c.id
     ORDER BY s.created_at DESC LIMIT 5"
);

// Recent fee payments
$recent_fees = db_fetch_all(
    "SELECT f.id, CONCAT(s.first_name,' ',s.last_name) AS student_name,
            s.student_id, f.amount, f.payment_date, f.payment_method, f.status
     FROM fees f
     JOIN students s ON f.student_id = s.id
     ORDER BY f.created_at DESC LIMIT 5"
);

$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= APP_NAME ?></title>
    <meta name="description" content="Admin Dashboard for Arsalan Model School and College Management System">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-wrapper">

    <!-- SIDEBAR OVERLAY (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ════════ SIDEBAR ════════ -->
    <aside class="sidebar" id="appSidebar">
        <a class="sidebar-brand" href="dashboard.php">
            <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="brand-text">
                <h2>Arsalan Model</h2>
                <span>School &amp; College</span>
            </div>
        </a>

        <nav class="sidebar-nav">
            <ul style="list-style:none;padding:0;margin:0;">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                        Dashboard
                    </a>
                </li>

                <li class="nav-section-title">Academics</li>

                <!-- Students -->
                <li class="nav-item">
                    <a href="#" class="nav-link nav-parent">
                        <span class="nav-icon"><i class="fas fa-user-graduate"></i></span>
                        Students
                        <i class="fas fa-chevron-down chevron"></i>
                    </a>
                    <ul class="nav-submenu" style="list-style:none;padding:0;margin:0;">
                        <li><a href="students/add_student.php"     class="nav-link"><i class="fas fa-plus me-1"></i> Add Student</a></li>
                        <li><a href="students/student_records.php" class="nav-link"><i class="fas fa-list me-1"></i> Student Records</a></li>
                    </ul>
                </li>

                <!-- Teachers -->
                <li class="nav-item">
                    <a href="#" class="nav-link nav-parent">
                        <span class="nav-icon"><i class="fas fa-chalkboard-teacher"></i></span>
                        Teachers
                        <i class="fas fa-chevron-down chevron"></i>
                    </a>
                    <ul class="nav-submenu" style="list-style:none;padding:0;margin:0;">
                        <li><a href="teachers/add_teacher.php"     class="nav-link"><i class="fas fa-plus me-1"></i> Add Teacher</a></li>
                        <li><a href="teachers/teacher_records.php" class="nav-link"><i class="fas fa-list me-1"></i> Teacher Records</a></li>
                    </ul>
                </li>

                <!-- Classes -->
                <li class="nav-item">
                    <a href="classes/classes.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-school"></i></span>
                        Classes
                    </a>
                </li>

                <!-- Subjects -->
                <li class="nav-item">
                    <a href="subjects/subjects.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-book"></i></span>
                        Subjects
                    </a>
                </li>

                <li class="nav-section-title">Finance &amp; Attendance</li>

                <!-- Fees -->
                <li class="nav-item">
                    <a href="#" class="nav-link nav-parent">
                        <span class="nav-icon"><i class="fas fa-money-bill-wave"></i></span>
                        Fees
                        <i class="fas fa-chevron-down chevron"></i>
                    </a>
                    <ul class="nav-submenu" style="list-style:none;padding:0;margin:0;">
                        <li><a href="fees/add_fee.php"     class="nav-link"><i class="fas fa-plus me-1"></i> Add Fee</a></li>
                        <li><a href="fees/fee_records.php" class="nav-link"><i class="fas fa-list me-1"></i> Fee Records</a></li>
                    </ul>
                </li>

                <!-- Attendance -->
                <li class="nav-item">
                    <a href="#" class="nav-link nav-parent">
                        <span class="nav-icon"><i class="fas fa-calendar-check"></i></span>
                        Attendance
                        <i class="fas fa-chevron-down chevron"></i>
                    </a>
                    <ul class="nav-submenu" style="list-style:none;padding:0;margin:0;">
                        <li><a href="attendance/student_attendance.php"  class="nav-link"><i class="fas fa-user-check me-1"></i> Student Attendance</a></li>
                        <li><a href="attendance/teacher_attendance.php"  class="nav-link"><i class="fas fa-user-tie me-1"></i> Teacher Attendance</a></li>
                        <li><a href="attendance/attendance_records.php"  class="nav-link"><i class="fas fa-clipboard-list me-1"></i> Attendance Records</a></li>
                    </ul>
                </li>

                <?php if (($user['role'] ?? '') === 'admin'): ?>
                <li class="nav-item">
                    <a href="report.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
                        Reports
                    </a>
                </li>
                <li class="nav-section-title">Administration</li>
                <li class="nav-item">
                    <a href="admin/users.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-users-cog"></i></span>
                        User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin/roles.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-user-shield"></i></span>
                        Roles &amp; Permissions
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-section-title">Account</li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-user-circle"></i></span>
                        Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-cog"></i></span>
                        Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link" data-confirm="Are you sure you want to logout?">
                        <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                <div>
                    <div class="user-name"><?= h($user['username']) ?></div>
                    <div class="user-role"><?= h($user['role']) ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- ════════ MAIN CONTENT ════════ -->
    <div class="main-content">

        <!-- TOP BAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <span class="bar"></span><span class="bar"></span><span class="bar"></span>
                </button>
                <div>
                    <div class="page-title"><?= h($page_title) ?></div>
                    <div class="breadcrumb-ams">
                        <a href="dashboard.php"><i class="fas fa-home"></i></a>
                        <span>/</span><span>Dashboard</span>
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-date">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?= date('D, d M Y') ?>
                </div>
                <a href="logout.php" class="btn-topbar-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <!-- PAGE BODY -->
        <main class="page-body">
            <div id="alertContainer"><?php render_flash(); ?></div>

            <!-- ── STATS GRID ── -->
            <div class="stats-grid">
                <a href="students/student_records.php" class="stat-card blue">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $total_students ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </a>
                <a href="teachers/teacher_records.php" class="stat-card green">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $total_teachers ?></div>
                        <div class="stat-label">Total Teachers</div>
                    </div>
                </a>
                <a href="classes/classes.php" class="stat-card orange">
                    <div class="stat-icon"><i class="fas fa-school"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $total_classes ?></div>
                        <div class="stat-label">Total Classes</div>
                    </div>
                </a>
                <a href="subjects/subjects.php" class="stat-card purple">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $total_subjects ?></div>
                        <div class="stat-label">Total Subjects</div>
                    </div>
                </a>
                <a href="fees/fee_records.php" class="stat-card teal">
                    <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
                    <div class="stat-info">
                        <div class="stat-value" style="font-size:1.4rem;">₹<?= $fees_collected ?></div>
                        <div class="stat-label">Fees Collected</div>
                    </div>
                </a>
            </div>

            <!-- ── BOTTOM ROW ── -->
            <div class="row g-4">

                <!-- Recent Students -->
                <div class="col-lg-7">
                    <div class="card-ams">
                        <div class="card-header-ams">
                            <h5><i class="fas fa-user-graduate text-primary"></i> Recent Students</h5>
                            <a href="students/student_records.php" class="btn-ams btn-primary-ams btn-sm-ams">View All</a>
                        </div>
                        <div class="table-responsive-ams">
                            <table class="table-ams">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Class</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($recent_students)): ?>
                                    <tr><td colspan="4" class="no-data"><i class="fas fa-inbox"></i>No students yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_students as $s): ?>
                                    <tr>
                                        <td><span class="fw-600 text-accent"><?= h($s['student_id']) ?></span></td>
                                        <td><?= h($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                        <td><?= h($s['class_name'] ?? '—') ?></td>
                                        <td>
                                            <span class="badge-ams badge-<?= $s['status'] ?>">
                                                <i class="fas fa-circle" style="font-size:.4rem;"></i>
                                                <?= ucfirst(h($s['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Today's Attendance & Recent Fees -->
                <div class="col-lg-5">
                    <!-- Attendance Summary -->
                    <div class="card-ams mb-4">
                        <div class="card-header-ams">
                            <h5><i class="fas fa-calendar-check text-success"></i> Today's Attendance</h5>
                            <small class="text-muted"><?= date('d M Y') ?></small>
                        </div>
                        <div class="card-body-ams">
                            <div class="summary-row">
                                <div class="summary-item summary-present">
                                    <div class="s-value"><?= $att_present ?></div>
                                    <div class="s-label text-success"><i class="fas fa-check-circle me-1"></i>Present</div>
                                </div>
                                <div class="summary-item summary-absent">
                                    <div class="s-value"><?= $att_absent ?></div>
                                    <div class="s-label text-danger"><i class="fas fa-times-circle me-1"></i>Absent</div>
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <a href="attendance/student_attendance.php" class="btn-ams btn-primary-ams btn-sm-ams">
                                    <i class="fas fa-plus me-1"></i> Mark Attendance
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Fee Payments -->
                    <div class="card-ams">
                        <div class="card-header-ams">
                            <h5><i class="fas fa-money-bill-wave text-info"></i> Recent Fee Payments</h5>
                            <a href="fees/fee_records.php" class="btn-ams btn-primary-ams btn-sm-ams">View All</a>
                        </div>
                        <div class="table-responsive-ams">
                            <table class="table-ams">
                                <thead>
                                    <tr><th>Student</th><th>Amount</th><th>Method</th></tr>
                                </thead>
                                <tbody>
                                <?php if (empty($recent_fees)): ?>
                                    <tr><td colspan="3" class="no-data"><i class="fas fa-inbox"></i>No payments yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_fees as $f): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-600" style="font-size:.82rem;"><?= h($f['student_name']) ?></div>
                                            <small class="text-muted"><?= h($f['student_id']) ?></small>
                                        </td>
                                        <td class="fw-700 text-success">₹<?= number_format((float)$f['amount'], 2) ?></td>
                                        <td><span class="badge-ams badge-active"><?= h($f['payment_method']) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="main-footer">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> — Management System &nbsp;|&nbsp;
            Developed with <i class="fas fa-heart text-danger"></i> for Excellence in Education
        </footer>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>
