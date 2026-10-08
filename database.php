<?php
/**
 * Database Configuration & Connection
 * Arsalan Model School and College Management System
 * Uses MySQLi with OOP and prepared statements
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'arsalan_model_school');
define('DB_CHARSET', 'utf8mb4');

// Application constants
define('APP_NAME', 'Arsalan Model School and College');
define('APP_SHORT', 'AMS');
if (!defined('BASE_URL')) {
    $folder = 'ArsalanModelSchool';
    if (!empty($_SERVER['REQUEST_URI']) && preg_match('#^/([^/]+)/#', $_SERVER['REQUEST_URI'], $matches)) {
        $folder = $matches[1];
    }
    define('BASE_URL', '/' . $folder . '/');
}
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('STUDENT_PHOTO_DIR', __DIR__ . '/uploads/students/');
define('TEACHER_PHOTO_DIR', __DIR__ . '/uploads/teachers/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

/**
 * Get database connection (singleton)
 */
function get_db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset(DB_CHARSET);
        } catch (mysqli_sql_exception $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('<div style="font-family:sans-serif;color:#c0392b;padding:30px;text-align:center;">
                <h2>&#9888; Database Connection Error</h2>
                <p>Could not connect to MySQL. Please ensure XAMPP is running and run <strong>setup.sql</strong> first.</p>
                <p><small>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</small></p>
                </div>');
        }
    }
    return $conn;
}

/**
 * Normalize arguments: accepts either ($types, $params) or ($params, $types).
 * Auto-infers types if omitted and params exist.
 */
function normalize_db_args(string|array $arg2 = '', string|array $arg3 = []): array {
    if (is_array($arg2)) {
        $params = $arg2;
        $types  = is_string($arg3) ? $arg3 : '';
    } else {
        $types  = (string)$arg2;
        $params = is_array($arg3) ? $arg3 : [];
    }

    if ($types === '' && !empty($params)) {
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
    }
    return [$types, $params];
}

/**
 * Execute a prepared statement and return the stmt object
 */
function db_query(string $sql, $types = '', $params = []): mysqli_stmt {
    [$actual_types, $actual_params] = normalize_db_args($types, $params);
    $conn = get_db();
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        throw new RuntimeException('Database error. Please try again.');
    }
    if ($actual_types !== '' && count($actual_params) > 0) {
        $stmt->bind_param($actual_types, ...$actual_params);
    }
    $stmt->execute();
    return $stmt;
}

/** Fetch all rows */
function db_fetch_all(string $sql, $types = '', $params = []): array {
    $stmt = db_query($sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Fetch single row */
function db_fetch_one(string $sql, $types = '', $params = []): ?array {
    $stmt = db_query($sql, $types, $params);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Execute INSERT/UPDATE/DELETE */
function db_execute(string $sql, $types = '', $params = []): int {
    $stmt = db_query($sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/** Last insert ID */
function db_last_id(): int {
    return (int)get_db()->insert_id;
}

/** Count helper */
function db_count(string $sql, $types = '', $params = []): int {
    $stmt = db_query($sql, $types, $params);
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (int)($row[0] ?? 0);
}

/** HTML-safe output */
function h(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Redirect helper */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/** Generate padded sequential ID */
function generate_id(string $prefix, string $table, string $column): string {
    $plen = strlen($prefix) + 2;
    $row = db_fetch_one("SELECT MAX(CAST(SUBSTRING(`$column`, $plen) AS UNSIGNED)) AS m FROM `$table`");
    $next = (int)($row['m'] ?? 0) + 1;
    return $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

/** Upload photo, returns filename or null */
function upload_photo(array $file, string $dir, ?string $old_file = null): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > MAX_FILE_SIZE) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) return null;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif'], true)) return null;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = uniqid('', true) . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        if ($old_file && file_exists($dir . $old_file)) unlink($dir . $old_file);
        return $filename;
    }
    return null;
}

/** Flash messages */
function set_flash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = compact('type', 'msg');
}
function get_flash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}
function render_flash(): void {
    $f = get_flash();
    if (!$f) return;
    $cls = match($f['type']) { 'success'=>'alert-success','error'=>'alert-danger', default=>'alert-info' };
    echo '<div class="alert '.$cls.' alert-dismissible fade show" role="alert">'.h($f['msg']).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/** Auth guard */
function require_login(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        $depth = file_exists('dashboard.php') ? '' : '../';
        redirect($depth . 'login.php');
    }
}

/** Current user */
function current_user(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) return null;
    return ['id'=>$_SESSION['user_id'],'username'=>$_SESSION['username'],'role'=>$_SESSION['role']];
}

/**
 * Resolves landing/dashboard URL for a given role or custom role.
 */
function get_role_dashboard(string $role, string $depth = '', ?int $user_id = null): string {
    $norm_role = strtolower(trim($role));
    if ($norm_role === 'teacher') return $depth . 'teacher/teacher_dashboard.php';
    if ($norm_role === 'parent')  return $depth . 'parent/parent_dashboard.php';
    if ($norm_role === 'finance') return $depth . 'finance/finance_dashboard.php';
    if ($norm_role === 'admin')   return $depth . 'dashboard.php';

    // Custom role resolution:
    // Determine the first accessible landing page based on granted permissions
    $uid = $user_id ?? ($_SESSION['user_id'] ?? null);
    if ($uid) {
        if (has_permission('students.view', $uid) || has_permission('students.add', $uid)) {
            return $depth . 'students/student_records.php';
        }
        if (has_permission('teachers.view', $uid) || has_permission('teachers.add', $uid)) {
            return $depth . 'teachers/teacher_records.php';
        }
        if (has_permission('classes.view', $uid) || has_permission('classes.add', $uid)) {
            return $depth . 'classes/classes.php';
        }
        if (has_permission('subjects.view', $uid) || has_permission('subjects.add', $uid)) {
            return $depth . 'subjects/subjects.php';
        }
        if (has_permission('attendance.view', $uid) || has_permission('attendance.mark', $uid)) {
            return $depth . 'attendance/attendance_records.php';
        }
        if (has_permission('fees.view', $uid) || has_permission('fees.add', $uid)) {
            return $depth . 'fees/fee_records.php';
        }
        if (has_permission('users.manage', $uid)) {
            return $depth . 'admin/users.php';
        }
        if (has_permission('activity_log.view', $uid)) {
            return $depth . 'admin/activity_log.php';
        }
    }

    // Default safe fallback if custom role has no specific module permissions
    return $depth . 'profile.php';
}

/**
 * Role-based access control guard.
 * Checks $_SESSION['role'] against an array of allowed roles and redirects if not authorized.
 *
 * @param array|string $allowed_roles
 * @return void
 */
function require_role(array|string $allowed_roles): void {
    require_login();
    $allowed = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    $current_role = $_SESSION['role'] ?? '';

    if (!in_array($current_role, $allowed, true)) {
        if (function_exists('set_flash')) {
            set_flash('error', 'You are not authorized to access that page.');
        }

        $depth = file_exists('dashboard.php') ? '' : '../';
        $unauth_page = $depth . 'unauthorized.php';

        if (file_exists($unauth_page)) {
            header('Location: ' . $unauth_page);
        } else {
            $dest = get_role_dashboard($current_role, $depth);
            header('Location: ' . $dest);
        }
        exit;
    }
}

/**
 * Log user action into the activity_log table via prepared statement.
 *
 * @param int|null $user_id
 * @param string $role
 * @param string $action
 * @param string|null $target_type
 * @param int|null $target_id
 * @param string|null $details
 * @return int Inserted ID
 */
function log_activity(?int $user_id, string $role, string $action, ?string $target_type = null, ?int $target_id = null, ?string $details = null): int {
    $sql = "INSERT INTO activity_log (user_id, role, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?, ?)";
    db_execute($sql, 'isssis', [$user_id, $role, $action, $target_type, $target_id, $details]);
    return db_last_id();
}

/**
 * Fetch all permissions for a given user as an array of 'module.action' strings.
 */
function load_user_permissions(int $user_id): array {
    $sql = "SELECT CONCAT(p.module, '.', p.action) AS perm
            FROM users u
            JOIN role_permissions rp ON u.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ?";
    $rows = db_fetch_all($sql, 'i', [$user_id]);
    return array_column($rows, 'perm');
}

/**
 * Check if user has a granular permission.
 *
 * @param string $permission Format: 'module.action'
 * @param int|null $user_id Optional user_id (defaults to current session user)
 * @return bool
 */
function has_permission(string $permission, ?int $user_id = null): bool {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) session_start();

    $current_uid = $user_id ?? ($_SESSION['user_id'] ?? null);
    if (!$current_uid) return false;

    // Admin role failsafe: system admin always has full permissions
    if ($user_id === null) {
        $current_role = strtolower($_SESSION['role'] ?? '');
        if ($current_role === 'admin') {
            return true;
        }
        // Check cached session permissions if evaluating current user
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            return in_array($permission, $_SESSION['permissions'], true);
        }
    }

    // Parse module.action
    if (!str_contains($permission, '.')) return false;
    [$module, $action] = explode('.', $permission, 2);

    $sql = "SELECT COUNT(*) AS allowed
            FROM users u
            JOIN role_permissions rp ON u.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.module = ? AND p.action = ?
            LIMIT 1";
    $row = db_fetch_one($sql, 'iss', [$current_uid, $module, $action]);
    if (!empty($row['allowed'])) return true;

    // Check if user has admin role in DB
    $u = db_fetch_one("SELECT role FROM users WHERE id = ?", 'i', [$current_uid]);
    if ($u && strtolower($u['role']) === 'admin') return true;

    return false;
}

/**
 * Granular permission guard.
 * Redirects to unauthorized.php (or user's dashboard) if permission is missing.
 *
 * @param string $permission Format: 'module.action'
 * @return void
 */
function require_permission(string $permission): void {
    require_login();
    if (!has_permission($permission)) {
        if (function_exists('set_flash')) {
            set_flash('error', "You do not have permission to perform this action ({$permission}).");
        }
        $depth = file_exists('dashboard.php') ? '' : '../';
        $unauth_page = $depth . 'unauthorized.php';

        if (file_exists($unauth_page)) {
            header('Location: ' . $unauth_page);
        } else {
            $dest = get_role_dashboard($_SESSION['role'] ?? '', $depth);
            header('Location: ' . $dest);
        }
        exit;
    }
}


