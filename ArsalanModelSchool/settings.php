<?php
/**
 * settings.php
 * Arsalan Model School and College Management System
 * Admin Settings & Security Management
 */

require_once __DIR__ . '/database.php';
require_login();

$user_session = current_user();
$user_id = (int)($user_session['id'] ?? 0);

if ($user_id <= 0) {
    redirect('logout.php');
}

$page_title  = 'System Settings';
$active_menu = 'settings';
$depth       = '';

$success_msg = '';
$error_msg   = '';

// Handle Password Change Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $error_msg = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error_msg = 'New password and confirmation do not match.';
        } elseif (strlen($new_password) < 6) {
            $error_msg = 'New password must be at least 6 characters long.';
        } else {
            // Verify current password against DB
            $user_row = db_fetch_one("SELECT password FROM users WHERE id = ? LIMIT 1", [$user_id], 'i');
            if (!$user_row || !password_verify($current_password, $user_row['password'])) {
                $error_msg = 'Current password is incorrect. Please try again.';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                db_execute("UPDATE users SET password = ? WHERE id = ?", [$new_hash, $user_id], 'si');
                $success_msg = 'Password updated successfully! Your account credentials have been changed.';
            }
        }
    }
}

// Fetch current user details
$admin = db_fetch_one(
    "SELECT id, username, name, email, role, status, created_at FROM users WHERE id = ? LIMIT 1",
    [$user_id],
    'i'
);

if (!$admin) {
    redirect('logout.php');
}

require_once __DIR__ . '/layout.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Settings</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <h2 class="h3 fw-bold text-dark mb-0">
                <i class="bi bi-gear-fill text-secondary me-2"></i> Account &amp; System Settings
            </h2>
            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle px-2 py-1">
                Security Center
            </span>
        </div>
    </div>
    <div>
        <a href="profile.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person-gear me-1"></i> Edit Full Profile
        </a>
    </div>
</div>

<!-- Alerts -->
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill flex-shrink-0 me-2 fs-5"></i>
        <div><?= h($success_msg); ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2 fs-5"></i>
        <div><?= h($error_msg); ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Change Password Card -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold text-dark mb-0">
                    <i class="bi bi-shield-lock text-warning me-2"></i> Change Password
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    To keep your administrator account secure, update your password regularly. You will need to enter your existing password to authenticate this change.
                </p>

                <form method="POST" action="settings.php" novalidate>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="currentPassword" class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="currentPassword" 
                                   name="current_password" 
                                   required 
                                   placeholder="Enter current password">
                            <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="currentPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="newPassword" class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="newPassword" 
                                   name="new_password" 
                                   minlength="6" 
                                   required 
                                   placeholder="At least 6 characters">
                            <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="newPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Choose a strong password with letters, numbers, and symbols.</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirmPassword" class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="confirmPassword" 
                                   name="confirm_password" 
                                   minlength="6" 
                                   required 
                                   placeholder="Repeat new password">
                            <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="confirmPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="dashboard.php" class="btn btn-light border px-3">Cancel</a>
                        <button type="submit" class="btn btn-warning text-dark px-4 fw-semibold shadow-sm">
                            <i class="bi bi-key me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Security & Information Summary Card -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold text-dark mb-0">
                    <i class="bi bi-info-circle text-info me-2"></i> Account Overview
                </h5>
            </div>
            <div class="card-body p-4">
                <ul class="list-unstyled mb-0">
                    <li class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Username:</span>
                        <span class="font-monospace fw-semibold text-dark"><?= h($admin['username']); ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Admin Name:</span>
                        <span class="fw-semibold text-dark"><?= h($admin['name'] ?: 'Not configured'); ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Email:</span>
                        <span class="text-dark"><?= h($admin['email'] ?: 'Not configured'); ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Role:</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= h(strtoupper($admin['role'])); ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-2">
                        <span class="text-muted">Account Status:</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><?= h(ucfirst($admin['status'])); ?></span>
                    </li>
                </ul>

                <div class="mt-4 pt-2 border-top">
                    <a href="profile.php" class="btn btn-outline-secondary w-100 btn-sm">
                        <i class="bi bi-pencil-square me-1"></i> Update Name &amp; Email in Profile
                    </a>
                </div>
            </div>
        </div>

        <div class="card bg-primary-subtle border-0 rounded-3 p-3">
            <div class="d-flex align-items-start">
                <i class="bi bi-shield-lock-fill text-primary fs-3 me-3"></i>
                <div>
                    <h6 class="fw-bold text-primary mb-1">Security Best Practices</h6>
                    <p class="small text-muted mb-0">
                        Never share your administrator login credentials with anyone. If you suspect unauthorized access, change your password immediately.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-pwd-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });
});
</script>
HTML;

require_once __DIR__ . '/layout_end.php';
?>
