<?php
/**
 * layout.php — Shared sidebar/topbar layout helper
 * Include with: include_once '../layout.php';
 * Requires: $page_title (string), $active_menu (string)
 */
if (!isset($page_title)) $page_title = 'Page';
if (!isset($active_menu)) $active_menu = '';
if (!isset($user)) $user = current_user();

// Depth from root
$depth = isset($depth) ? $depth : '../';
$css   = $depth . 'css/style.css';
$js    = $depth . 'js/script.js';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $css ?>">
</head>
<body>
<div class="app-wrapper">
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
$dashboard_link = get_role_dashboard($user['role'] ?? 'admin', $depth);
?>
<!-- ═══════════ SIDEBAR ═══════════ -->
<aside class="sidebar" id="appSidebar">
    <a class="sidebar-brand" href="<?= $dashboard_link ?>">
        <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="brand-text">
            <h2>Arsalan Model</h2>
            <span>School &amp; College</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <?php if (($user['role'] ?? '') === 'teacher'): ?>
        <ul style="list-style:none;padding:0;margin:0;">
            <li class="nav-item">
                <a href="<?= $depth ?>teacher/teacher_dashboard.php" class="nav-link <?= $active_menu==='teacher_dashboard'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
                </a>
            </li>
            <li class="nav-section-title">Teaching</li>
            <li class="nav-item">
                <a href="<?= $depth ?>teacher/my_classes.php" class="nav-link <?= $active_menu==='teacher_classes'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-chalkboard"></i></span> My Classes
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>teacher/attendance.php" class="nav-link <?= $active_menu==='teacher_attendance'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-calendar-check"></i></span> Mark Attendance
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>teacher/marks.php" class="nav-link <?= $active_menu==='teacher_marks'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-file-signature"></i></span> Enter Marks
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>teacher/students.php" class="nav-link <?= $active_menu==='teacher_students'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-user-graduate"></i></span> Students
                </a>
            </li>

            <li class="nav-section-title">Account</li>
            <li class="nav-item">
                <a href="<?= $depth ?>profile.php" class="nav-link <?= $active_menu==='profile'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>settings.php" class="nav-link <?= $active_menu==='settings'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span> Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>logout.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span> Logout
                </a>
            </li>
        </ul>
        <?php elseif (($user['role'] ?? '') === 'parent'): ?>
        <ul style="list-style:none;padding:0;margin:0;">
            <li class="nav-item">
                <a href="<?= $depth ?>parent/parent_dashboard.php" class="nav-link <?= $active_menu==='parent_dashboard'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
                </a>
            </li>
            <li class="nav-section-title">Child Portal</li>
            <li class="nav-item">
                <a href="<?= $depth ?>parent/attendance.php" class="nav-link <?= $active_menu==='parent_attendance'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-calendar-check"></i></span> Attendance
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>parent/results.php" class="nav-link <?= $active_menu==='parent_results'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-chart-line"></i></span> Exam Results
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>parent/fees.php" class="nav-link <?= $active_menu==='parent_fees'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-receipt"></i></span> Fee Records
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>parent/profile.php" class="nav-link <?= $active_menu==='parent_profile'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-user-graduate"></i></span> Child Profile
                </a>
            </li>
            <li class="nav-section-title">Account</li>
            <li class="nav-item">
                <a href="<?= $depth ?>profile.php" class="nav-link <?= $active_menu==='profile'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>settings.php" class="nav-link <?= $active_menu==='settings'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span> Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>logout.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span> Logout
                </a>
            </li>
        </ul>
        <?php elseif (($user['role'] ?? '') === 'finance'): ?>
        <ul style="list-style:none;padding:0;margin:0;">
            <li class="nav-item">
                <a href="<?= $depth ?>finance/finance_dashboard.php" class="nav-link <?= $active_menu==='finance_dashboard'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
                </a>
            </li>
            <li class="nav-section-title">Finance</li>
            <li class="nav-item">
                <a href="<?= $depth ?>fees/fee_records.php" class="nav-link <?= $active_menu==='fees_list'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-receipt"></i></span> Fee Records
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>fees/add_fee.php" class="nav-link <?= $active_menu==='fees_add'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-plus me-1"></i></span> Collect Fee
                </a>
            </li>
            <?php if (has_permission('teachers.salary')): ?>
            <li class="nav-item">
                <a href="<?= $depth ?>finance/teacher_salary.php" class="nav-link <?= $active_menu==='teacher_salary'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-money-bill-wave"></i></span> Teacher Salary
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-section-title">Account</li>
            <li class="nav-item">
                <a href="<?= $depth ?>profile.php" class="nav-link <?= $active_menu==='profile'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>settings.php" class="nav-link <?= $active_menu==='settings'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span> Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>logout.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span> Logout
                </a>
            </li>
        </ul>
        <?php else: 
            $is_admin = (($user['role'] ?? '') === 'admin');
            $has_students   = $is_admin || has_permission('students.view') || has_permission('students.add');
            $has_teachers   = $is_admin || has_permission('teachers.view') || has_permission('teachers.add') || has_permission('teachers.salary');
            $has_classes    = $is_admin || has_permission('classes.view');
            $has_subjects   = $is_admin || has_permission('subjects.view');
            $has_academics  = $has_students || $has_teachers || $has_classes || $has_subjects;

            $has_fees       = $is_admin || has_permission('fees.view') || has_permission('fees.add');
            $has_attendance = $is_admin || has_permission('attendance.view') || has_permission('attendance.mark');
            $has_fin_att    = $has_fees || $has_attendance;

            $has_reports    = $is_admin;
            $has_users      = $is_admin || has_permission('users.manage');
            $has_activity   = $is_admin || has_permission('activity_log.view');
            $has_admin_sec  = $has_reports || $has_users || $has_activity;
        ?>
        <ul style="list-style:none;padding:0;margin:0;">
            <?php if ($is_admin): ?>
            <li class="nav-item">
                <a href="<?= $depth ?>dashboard.php" class="nav-link <?= $active_menu==='dashboard'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
                </a>
            </li>
            <?php endif; ?>

            <?php if ($has_academics): ?>
            <li class="nav-section-title">Academics</li>

            <!-- Students -->
            <?php if ($has_students): ?>
            <li class="nav-item">
                <a href="#" class="nav-link nav-parent <?= str_starts_with($active_menu,'students')?'open':'' ?>">
                    <span class="nav-icon"><i class="fas fa-user-graduate"></i></span>
                    Students <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="nav-submenu <?= str_starts_with($active_menu,'students')?'open':'' ?>" style="list-style:none;padding:0;margin:0;">
                    <?php if ($is_admin || has_permission('students.add')): ?>
                    <li><a href="<?= $depth ?>students/add_student.php"     class="nav-link <?= $active_menu==='students_add'?'active':'' ?>"><i class="fas fa-plus me-1"></i> Add Student</a></li>
                    <?php endif; ?>
                    <?php if ($is_admin || has_permission('students.view')): ?>
                    <li><a href="<?= $depth ?>students/student_records.php" class="nav-link <?= $active_menu==='students_list'?'active':'' ?>"><i class="fas fa-list me-1"></i> Student Records</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Teachers -->
            <?php if ($has_teachers): ?>
            <li class="nav-item">
                <a href="#" class="nav-link nav-parent <?= (str_starts_with($active_menu,'teachers') || $active_menu==='teacher_salary')?'open':'' ?>">
                    <span class="nav-icon"><i class="fas fa-chalkboard-teacher"></i></span>
                    Teachers <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="nav-submenu <?= (str_starts_with($active_menu,'teachers') || $active_menu==='teacher_salary')?'open':'' ?>" style="list-style:none;padding:0;margin:0;">
                    <?php if ($is_admin || has_permission('teachers.add')): ?>
                    <li><a href="<?= $depth ?>teachers/add_teacher.php"     class="nav-link <?= $active_menu==='teachers_add'?'active':'' ?>"><i class="fas fa-plus me-1"></i> Add Teacher</a></li>
                    <?php endif; ?>
                    <?php if ($is_admin || has_permission('teachers.view')): ?>
                    <li><a href="<?= $depth ?>teachers/teacher_records.php" class="nav-link <?= $active_menu==='teachers_list'?'active':'' ?>"><i class="fas fa-list me-1"></i> Teacher Records</a></li>
                    <?php endif; ?>
                    <?php if ($is_admin || has_permission('teachers.salary')): ?>
                    <li><a href="<?= $depth ?>finance/teacher_salary.php" class="nav-link <?= $active_menu==='teacher_salary'?'active':'' ?>"><i class="fas fa-money-bill-wave me-1"></i> Teacher Salary</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($has_classes): ?>
            <li class="nav-item">
                <a href="<?= $depth ?>classes/classes.php" class="nav-link <?= $active_menu==='classes'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-school"></i></span> Classes
                </a>
            </li>
            <?php endif; ?>

            <?php if ($has_subjects): ?>
            <li class="nav-item">
                <a href="<?= $depth ?>subjects/subjects.php" class="nav-link <?= $active_menu==='subjects'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-book"></i></span> Subjects
                </a>
            </li>
            <?php endif; ?>
            <?php endif; // has_academics ?>

            <?php if ($has_fin_att): ?>
            <li class="nav-section-title">Finance &amp; Attendance</li>

            <!-- Fees -->
            <?php if ($has_fees): ?>
            <li class="nav-item">
                <a href="#" class="nav-link nav-parent <?= str_starts_with($active_menu,'fees')?'open':'' ?>">
                    <span class="nav-icon"><i class="fas fa-money-bill-wave"></i></span>
                    Fees <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="nav-submenu <?= str_starts_with($active_menu,'fees')?'open':'' ?>" style="list-style:none;padding:0;margin:0;">
                    <?php if ($is_admin || has_permission('fees.add')): ?>
                    <li><a href="<?= $depth ?>fees/add_fee.php"     class="nav-link <?= $active_menu==='fees_add'?'active':'' ?>"><i class="fas fa-plus me-1"></i> Add Fee</a></li>
                    <?php endif; ?>
                    <?php if ($is_admin || has_permission('fees.view')): ?>
                    <li><a href="<?= $depth ?>fees/fee_records.php" class="nav-link <?= $active_menu==='fees_list'?'active':'' ?>"><i class="fas fa-list me-1"></i> Fee Records</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Attendance -->
            <?php if ($has_attendance): ?>
            <li class="nav-item">
                <a href="#" class="nav-link nav-parent <?= str_starts_with($active_menu,'attendance')?'open':'' ?>">
                    <span class="nav-icon"><i class="fas fa-calendar-check"></i></span>
                    Attendance <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="nav-submenu <?= str_starts_with($active_menu,'attendance')?'open':'' ?>" style="list-style:none;padding:0;margin:0;">
                    <?php if ($is_admin || has_permission('attendance.mark')): ?>
                    <li><a href="<?= $depth ?>attendance/student_attendance.php"  class="nav-link <?= $active_menu==='attendance_student'?'active':'' ?>"><i class="fas fa-user-check me-1"></i> Student Attendance</a></li>
                    <li><a href="<?= $depth ?>attendance/teacher_attendance.php"  class="nav-link <?= $active_menu==='attendance_teacher'?'active':'' ?>"><i class="fas fa-user-tie me-1"></i> Teacher Attendance</a></li>
                    <?php endif; ?>
                    <?php if ($is_admin || has_permission('attendance.view')): ?>
                    <li><a href="<?= $depth ?>attendance/attendance_records.php"  class="nav-link <?= $active_menu==='attendance_records'?'active':'' ?>"><i class="fas fa-clipboard-list me-1"></i> Attendance Records</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php endif; // has_fin_att ?>

            <?php if ($has_admin_sec): ?>
            <li class="nav-section-title">Administration</li>
            <?php if ($has_reports): ?>
            <li class="nav-item">
                <a href="<?= $depth ?>report.php" class="nav-link <?= $active_menu==='report'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports
                </a>
            </li>
            <?php endif; ?>
            <?php if ($has_users): ?>
            <li class="nav-item">
                <a href="<?= $depth ?>admin/users.php" class="nav-link <?= $active_menu==='admin_users'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-users-cog"></i></span> User Management
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>admin/roles.php" class="nav-link <?= $active_menu==='admin_roles'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-user-shield"></i></span> Roles &amp; Permissions
                </a>
            </li>
            <?php endif; ?>
            <?php endif; // has_admin_sec ?>

            <li class="nav-section-title">Account</li>
            <li class="nav-item">
                <a href="<?= $depth ?>profile.php" class="nav-link <?= $active_menu==='profile'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>settings.php" class="nav-link <?= $active_menu==='settings'?'active':'' ?>">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span> Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $depth ?>logout.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span> Logout
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($user['username'] ?? 'A', 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= h($user['username'] ?? 'Admin') ?></div>
                <div class="user-role"><?= h($user['role'] ?? 'admin') ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- ═══════════ MAIN CONTENT ═══════════ -->
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
                    <a href="<?= $dashboard_link ?>"><i class="fas fa-home"></i></a>
                    <?php if (isset($breadcrumb)): foreach ($breadcrumb as $bc): ?>
                        <span>/</span><span><?= h($bc) ?></span>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date"><i class="fas fa-calendar-alt me-1"></i><?= date('D, d M Y') ?></div>
            <a href="<?= $depth ?>logout.php" class="btn-topbar-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <!-- PAGE BODY STARTS -->
    <main class="page-body">
    <div id="alertContainer"><?php render_flash(); ?></div>
