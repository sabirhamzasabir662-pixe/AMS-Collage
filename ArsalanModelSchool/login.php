<?php
/**
 * Arsalan Model School and College Management System
 * Phase 1: Foundation
 * 
 * login.php — Authentication Entry Point
 */

require_once __DIR__ . '/database.php';

// Redirect if already authenticated
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        // Query user with prepared statement (prevents SQL injection)
        $user = db_fetch_one(
            "SELECT id, username, password, role, status FROM users WHERE username = ? LIMIT 1",
            [$username],
            "s"
        );

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account is deactivated. Please contact the administrator.';
            } else {
                // Success: Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']  = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Arsalan Model School and College</title>
    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-body p-4 p-sm-5">
                        
                        <!-- School Header -->
                        <div class="text-center mb-4">
                            <div class="mb-2">
                                <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle" style="width: 60px; height: 60px; font-size: 1.75rem;">
                                    <i class="bi bi-mortarboard-fill"></i>
                                </span>
                            </div>
                            <h4 class="fw-bold mb-1 text-dark">Arsalan Model School</h4>
                            <p class="text-muted small mb-0">&amp; College Management System</p>
                            <span class="badge bg-secondary-subtle text-secondary border mt-2">Phase 1: Foundation</span>
                        </div>

                        <!-- Alert Box -->
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2"></i>
                                <div><?= htmlspecialchars($error); ?></div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Login Form -->
                        <form method="POST" action="login.php" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text"
                                           class="form-control border-start-0 ps-0"
                                           id="username"
                                           name="username"
                                           value="<?= htmlspecialchars($username); ?>"
                                           placeholder="Enter username"
                                           required
                                           autofocus>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password"
                                           class="form-control border-start-0 ps-0"
                                           id="password"
                                           name="password"
                                           placeholder="Enter password"
                                           required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                            </button>
                        </form>

                        <!-- Phase 1 Credentials Hint -->
                        <div class="mt-4 pt-3 border-top text-center text-muted small">
                            <p class="mb-1 fw-semibold text-secondary">Default Admin Credentials:</p>
                            <div>Username: <code class="text-dark">admin</code></div>
                            <div>Password: <code class="text-dark">admin123</code></div>
                        </div>

                    </div>
                </div>

                <div class="text-center mt-3 text-muted small">
                    &copy; <?= date('Y'); ?> Arsalan Model School and College. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
