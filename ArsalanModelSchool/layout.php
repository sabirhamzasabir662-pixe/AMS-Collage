<?php
/**
 * layout.php — Shared Header, Navigation Bar & Sidebar
 * Arsalan Model School and College Management System
 */

require_once __DIR__ . '/database.php';
require_login();

$user = current_user();

if (!isset($page_title))  $page_title  = 'Arsalan Model School & College';
if (!isset($active_menu)) $active_menu = '';
if (!isset($depth))       $depth       = '../';

$flash_success = get_flash('success');
$flash_error   = get_flash('error') ?? get_flash('danger');
$flash_warning = get_flash('warning');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title); ?> - Arsalan Model School &amp; College</title>
    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm px-3">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center fw-bold" href="<?= $depth; ?>dashboard.php">
                <i class="bi bi-mortarboard-fill text-primary me-2 fs-4"></i>
                <span>Arsalan Model School &amp; College</span>
            </a>
            <div class="d-flex align-items-center ms-auto">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-3 d-none d-md-inline-block">
                    Management System Active
                </span>
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="fw-semibold"><?= h($user['username'] ?? 'User'); ?></span>
                        <span class="badge bg-secondary ms-2 text-uppercase" style="font-size: 0.65rem;"><?= h($user['role'] ?? 'admin'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header">Signed in as <?= h($user['username'] ?? ''); ?></h6></li>
                        <li>
                            <a class="dropdown-item" href="<?= $depth; ?>profile.php">
                                <i class="bi bi-person-gear me-2 text-muted"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= $depth; ?>settings.php">
                                <i class="bi bi-gear me-2 text-muted"></i> Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= $depth; ?>logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">

            <!-- Sidebar Navigation -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-white sidebar py-3 border-end min-vh-100">
                <div class="position-sticky">
                    <div class="px-3 pb-3 mb-2 border-bottom">
                        <small class="text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.05em;">Navigation Menu</small>
                    </div>

                    <ul class="nav flex-column nav-pills px-2">
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= ($active_menu === 'dashboard') ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>dashboard.php">
                                <i class="bi bi-speedometer2 me-2 <?= ($active_menu === 'dashboard') ? '' : 'text-primary'; ?>"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= (str_starts_with($active_menu, 'students')) ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>students/student_records.php">
                                <i class="bi bi-people me-2 <?= (str_starts_with($active_menu, 'students')) ? '' : 'text-primary'; ?>"></i>
                                <span>Students</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= (str_starts_with($active_menu, 'teachers')) ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>teachers/teacher_records.php">
                                <i class="bi bi-person-badge me-2 <?= (str_starts_with($active_menu, 'teachers')) ? '' : 'text-success'; ?>"></i>
                                <span>Teachers</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= ($active_menu === 'classes') ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>classes/classes.php">
                                <i class="bi bi-building me-2 text-info"></i>
                                <span>Classes</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= ($active_menu === 'subjects') ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>subjects/subjects.php">
                                <i class="bi bi-book me-2 text-warning"></i>
                                <span>Subjects</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= ($active_menu === 'fees') ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>fees/fee_records.php">
                                <i class="bi bi-cash-stack me-2 text-success"></i>
                                <span>Fees</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= ($active_menu === 'attendance') ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>attendance/attendance_records.php">
                                <i class="bi bi-calendar-check me-2 text-danger"></i>
                                <span>Attendance</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= ($active_menu === 'report') ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>report.php">
                                <i class="bi bi-bar-chart-line me-2 text-primary"></i>
                                <span>Report</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= ($active_menu === 'profile') ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>profile.php">
                                <i class="bi bi-person-gear me-2 text-secondary"></i>
                                <span>Profile</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link <?= ($active_menu === 'settings') ? 'active' : 'text-dark'; ?> d-flex align-items-center" href="<?= $depth; ?>settings.php">
                                <i class="bi bi-gear me-2 text-secondary"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                        <li class="nav-item my-2 border-top pt-2">
                            <a class="nav-link text-danger d-flex align-items-center" href="<?= $depth; ?>logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>

                    <div class="card bg-light border-0 mx-2 mt-4 p-3 text-center">
                        <i class="bi bi-shield-check text-success fs-3 mb-1"></i>
                        <small class="fw-bold text-dark">Database Connected</small>
                        <small class="text-muted" style="font-size: 0.75rem;">arsalan_model_school</small>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

                <!-- Flash Messages -->
                <?php if ($flash_success): ?>
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill flex-shrink-0 me-2 fs-5"></i>
                        <div><?= h($flash_success); ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($flash_error): ?>
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2 fs-5"></i>
                        <div><?= h($flash_error); ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($flash_warning): ?>
                    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-circle-fill flex-shrink-0 me-2 fs-5"></i>
                        <div><?= h($flash_warning); ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
