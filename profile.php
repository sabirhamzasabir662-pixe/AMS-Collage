<?php
/**
 * profile.php
 * Arsalan Model School and College Management System
 * Admin Profile Management (View & Edit Name, Email, and Password)
 */

require_once __DIR__ . '/database.php';
require_login();

$user_session = current_user();
$user_id = (int)($user_session['id'] ?? 0);

if ($user_id <= 0) {
    redirect('logout.php');
}

$page_title  = 'Profile';
$active_menu = 'profile';
$breadcrumb  = ['Profile'];
$depth       = '';

$success_msg = '';
$error_msg   = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            $error_msg = 'Name cannot be empty.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Please enter a valid email address.';
        } else {
            // Update admin name and email using prepared statement
            db_execute(
                "UPDATE users SET name = ?, email = ? WHERE id = ?",
                [$name, $email, $user_id],
                'ssi'
            );
            $success_msg = 'Profile information updated successfully!';
        }
    } elseif ($action === 'change_password') {
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
            // Fetch current password hash from DB
            $user_row = db_fetch_one("SELECT password FROM users WHERE id = ? LIMIT 1", [$user_id], 'i');
            if (!$user_row || !password_verify($current_password, $user_row['password'])) {
                $error_msg = 'Current password is incorrect. Please try again.';
            } else {
                // Hash new password and update
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                db_execute("UPDATE users SET password = ? WHERE id = ?", [$new_hash, $user_id], 'si');
                $success_msg = 'Password changed successfully! You can now use your new password.';
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
                <li class="breadcrumb-item"><a href="<?= get_role_dashboard($admin['role'] ?? 'admin') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Profile</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <h2 class="h3 fw-bold text-dark mb-0">
                <i class="bi bi-person-circle text-primary me-2"></i> Profile
            </h2>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                Role: <?= h(strtoupper($admin['role'])); ?>
            </span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                Status: <?= h(ucfirst($admin['status'])); ?>
            </span>
        </div>
    </div>
</div>

<!-- Success / Error Feedback Alerts -->
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
    <!-- Left Column: Profile Card & Account Information Form -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold text-dark mb-0">
                    <i class="bi bi-person-vcard text-primary me-2"></i> Account Details
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 64px; height: 64px; font-size: 1.75rem;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><?= h($admin['name'] ?: $admin['username']); ?></h5>
                        <p class="text-muted small mb-0">
                            Username: <span class="font-monospace text-dark fw-semibold"><?= h($admin['username']); ?></span>
                        </p>
                        <p class="text-muted small mb-0">
                            Account ID: #<?= (int)$admin['id']; ?> &bull; Joined <?= date('M Y', strtotime($admin['created_at'])); ?>
                        </p>
                    </div>
                </div>

                <form method="POST" action="profile.php" novalidate>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label for="adminUsername" class="form-label fw-semibold">Username</label>
                        <input type="text" 
                               class="form-control bg-light" 
                               id="adminUsername" 
                               value="<?= h($admin['username']); ?>" 
                               readonly 
                               disabled 
                               title="Username cannot be changed">
                        <div class="form-text">Your system login identifier (read-only).</div>
                    </div>

                    <div class="mb-3">
                        <label for="adminName" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="adminName" 
                               name="name" 
                               value="<?= h($admin['name'] ?? ''); ?>" 
                               placeholder="e.g. System Administrator" 
                               maxlength="100" 
                               required>
                    </div>

                    <div class="mb-4">
                        <label for="adminEmail" class="form-label fw-semibold">Email Address</label>
                        <input type="email" 
                               class="form-control" 
                               id="adminEmail" 
                               name="email" 
                               value="<?= h($admin['email'] ?? ''); ?>" 
                               placeholder="e.g. admin@arsalan.edu.pk" 
                               maxlength="100">
                        <div class="form-text">Used for notifications and account correspondence.</div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Change Password Form -->
    <div class="col-lg-6" id="password-section">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title fw-bold text-dark mb-0">
                    <i class="bi bi-shield-lock text-warning me-2"></i> Change Password
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info py-2 px-3 small mb-3 border-0 bg-info-subtle text-info-emphasis">
                    <i class="bi bi-info-circle me-1"></i> You must confirm your current password to set a new one. Password must be at least 6 characters.
                </div>

                <form method="POST" action="profile.php" novalidate>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="currentPassword" class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="currentPassword" 
                                   name="current_password" 
                                   required 
                                   placeholder="Enter your existing password">
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
                                   placeholder="Repeat your new password">
                            <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="confirmPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning text-dark px-4 fw-semibold shadow-sm">
                            <i class="bi bi-key me-1"></i> Update Password
                        </button>
                    </div>
                </form>
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
