<?php
/**
 * login.php — Authentication Page
 * Arsalan Model School and College Management System
 */
require_once 'database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . get_role_dashboard($_SESSION['role'] ?? 'admin'));
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $user = db_fetch_one(
            "SELECT id, username, password, role, role_id, status FROM users WHERE username = ? LIMIT 1",
            's', [$username]
        );

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact the administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['username']    = $user['username'];
                $_SESSION['role']        = $user['role'];
                $_SESSION['role_id']     = $user['role_id'];
                $_SESSION['permissions'] = load_user_permissions($user['id']);
                if (!empty($_POST['remember'])) {
                    setcookie('ams_user', $user['username'], time() + 86400 * 30, '/');
                }
                $target = get_role_dashboard($user['role']);
                header('Location: ' . $target);
                exit;
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
$cookie_user = $_COOKIE['ams_user'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Arsalan Model School and College</title>
    <meta name="description" content="Login to Arsalan Model School and College Management System">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body.login-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d2137 0%, #1a3a52 45%, #1e4d72 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 20px;
        }
        .login-wrapper {
            width: 100%;
            max-width: 460px;
        }
        .login-card {
            background: rgba(255,255,255,0.97);
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
            overflow: hidden;
            animation: slideUp .5s ease;
        }
        @keyframes slideUp {
            from { opacity:0; transform:translateY(30px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .login-header {
            background: linear-gradient(135deg, #1a3a52, #2a5f8f);
            padding: 40px 30px 30px;
            text-align: center;
            color: #fff;
        }
        .login-header .school-icon {
            width: 80px; height: 80px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 36px;
            border: 3px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
        }
        .login-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .login-header p { font-size: .85rem; opacity: .8; margin: 0; }
        .login-body { padding: 35px 35px 30px; }
        .form-label { font-weight: 600; color: #1a3a52; font-size: .9rem; }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: .95rem;
            transition: border-color .3s, box-shadow .3s;
        }
        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 3px rgba(74,144,226,0.15);
        }
        .input-group .form-control { border-right: 0; border-radius: 10px 0 0 10px; }
        .input-group .btn-toggle-pw {
            border: 2px solid #e0e0e0;
            border-left: 0;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
            color: #6c757d;
            transition: all .3s;
            padding: 0 14px;
            cursor: pointer;
        }
        .input-group .btn-toggle-pw:hover { background: #4a90e2; color: #fff; border-color: #4a90e2; }
        .input-group:focus-within .btn-toggle-pw { border-color: #4a90e2; }
        .btn-login {
            background: linear-gradient(135deg, #4a90e2, #2a5f8f);
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            width: 100%;
            letter-spacing: .5px;
            transition: all .3s;
            margin-top: 10px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #357abd, #1a3a52);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74,144,226,.4);
        }
        .btn-login:active { transform: translateY(0); }
        .login-footer {
            text-align: center;
            padding: 15px 35px 25px;
            border-top: 1px solid #f0f0f0;
            color: #888;
            font-size: .8rem;
        }
        .alert-danger { border-radius: 10px; border-left: 4px solid #dc3545; }
        .decor-circle {
            position: fixed; border-radius: 50%; opacity: .06; pointer-events: none;
            background: #fff; animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%,100% { transform: translateY(0) scale(1); }
            50%      { transform: translateY(-20px) scale(1.05); }
        }
    </style>
</head>
<body class="login-page">
    <!-- Decorative circles -->
    <div class="decor-circle" style="width:300px;height:300px;top:-80px;left:-80px;animation-delay:0s;"></div>
    <div class="decor-circle" style="width:200px;height:200px;bottom:50px;right:-50px;animation-delay:2s;"></div>
    <div class="decor-circle" style="width:150px;height:150px;bottom:200px;left:100px;animation-delay:4s;"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="school-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1>ARSALAN MODEL SCHOOL</h1>
                <p>&amp; College Management System</p>
            </div>

            <!-- Body -->
            <div class="login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= h($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="username">
                            <i class="fas fa-user me-1 text-primary"></i> Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= h($username ?: $cookie_user) ?>"
                               placeholder="Enter your username" required autocomplete="username">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">
                            <i class="fas fa-lock me-1 text-primary"></i> Password
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="btn-toggle-pw" id="togglePassword" title="Show/Hide Password">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                        <label class="form-check-label text-muted" for="remember" style="font-size:.875rem;">
                            Remember me for 30 days
                        </label>
                    </div>

                    <button type="submit" class="btn-login" id="btnLogin">
                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <i class="fas fa-shield-alt me-1 text-primary"></i>
                Secure Login &nbsp;|&nbsp; &copy; <?= date('Y') ?> Arsalan Model School &amp; College
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide password
        document.getElementById('togglePassword').addEventListener('click', function() {
            const pw = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.classList.replace('fa-eye','fa-eye-slash');
            } else {
                pw.type = 'password';
                icon.classList.replace('fa-eye-slash','fa-eye');
            }
        });
        // Animate button on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnLogin');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Signing In...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
