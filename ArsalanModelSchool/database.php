<?php
/**
 * Arsalan Model School and College Management System
 * Phase 1: Foundation
 * 
 * database.php — MySQLi Database Connection & Core Auth Helpers
 * Host: localhost | User: root | Password: (blank) | Database: arsalan_model_school
 */

// 1. Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'arsalan_model_school');
define('DB_PORT', 3306);

/**
 * Returns a shared MySQLi connection instance (Singleton pattern).
 *
 * @return mysqli
 */
function get_db(): mysqli {
    static $conn = null;

    if ($conn === null) {
        // Disable mysqli automatic exception throwing to handle connection gracefully
        mysqli_report(MYSQLI_REPORT_OFF);

        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

        if ($conn->connect_error) {
            die(
                '<div style="font-family:Segoe UI,sans-serif;max-width:600px;margin:50px auto;padding:24px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:8px;">' .
                '<h3 style="margin-top:0;">Database Connection Failed</h3>' .
                '<p><strong>Error (' . $conn->connect_errno . '):</strong> ' . htmlspecialchars($conn->connect_error) . '</p>' .
                '<p>Please ensure MySQL is running in XAMPP and that the database <code>arsalan_model_school</code> has been created by importing <code>setup.sql</code>.</p>' .
                '</div>'
            );
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}

/**
 * Automatically infers MySQLi bind type characters from an array of values.
 *
 * @param array $params
 * @return string
 */
function db_infer_types(array $params): string {
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    return $types;
}

/**
 * Prepares and executes a parameterized SQL query using prepared statements.
 * Prevents SQL Injection by binding all parameters.
 *
 * @param string $sql SQL query string with ? placeholders
 * @param array  $params Indexed array of parameters to bind
 * @param string $types Optional type definition string (e.g. 'ssi', 'id')
 * @return mysqli_stmt
 */
function db_query(string $sql, array $params = [], string $types = ''): mysqli_stmt {
    $conn = get_db();
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Query Preparation Failed: ' . htmlspecialchars($conn->error) . ' | Query: ' . htmlspecialchars($sql));
    }

    if (!empty($params)) {
        if (empty($types)) {
            $types = db_infer_types($params);
        }
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        die('Query Execution Failed: ' . htmlspecialchars($stmt->error) . ' | Query: ' . htmlspecialchars($sql));
    }

    return $stmt;
}

/**
 * Executes a prepared query and fetches all matching rows as an associative array.
 *
 * @param string $sql
 * @param array  $params
 * @param string $types
 * @return array
 */
function db_fetch_all(string $sql, array $params = [], string $types = ''): array {
    $stmt = db_query($sql, $params, $types);
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();
    return $rows;
}

/**
 * Executes a prepared query and fetches a single row as an associative array.
 * Returns null if no record matches.
 *
 * @param string $sql
 * @param array  $params
 * @param string $types
 * @return array|null
 */
function db_fetch_one(string $sql, array $params = [], string $types = ''): ?array {
    $stmt = db_query($sql, $params, $types);
    $result = $stmt->get_result();
    $row = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $row;
}

/**
 * Executes an INSERT, UPDATE, or DELETE statement.
 *
 * @param string $sql
 * @param array  $params
 * @param string $types
 * @return array ['affected_rows' => int, 'insert_id' => int]
 */
function db_execute(string $sql, array $params = [], string $types = ''): array {
    $stmt = db_query($sql, $params, $types);
    $conn = get_db();
    $response = [
        'affected_rows' => $stmt->affected_rows,
        'insert_id'     => $conn->insert_id,
    ];
    $stmt->close();
    return $response;
}

// 3. Authentication & Session Helpers

/**
 * Checks if the current visitor is authenticated.
 *
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Returns logged-in user information stored in session.
 *
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? 'admin',
    ];
}

/**
 * Guard function: redirects unauthenticated users to login.php.
 * Must be called at the top of every protected page.
 *
 * @param string $login_path Custom path to login.php if needed
 * @return void
 */
function require_login(string $login_path = ''): void {
    if (!is_logged_in()) {
        if ($login_path === '') {
            $login_path = file_exists('login.php') ? 'login.php' : '../login.php';
        }
        header('Location: ' . $login_path);
        exit;
    }
}

/**
 * Safely redirects the browser to another URL and terminates script execution.
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Escapes HTML output to protect against Cross-Site Scripting (XSS).
 *
 * @param mixed $string
 * @return string
 */
function h($string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sets a flash message in session.
 *
 * @param string $type 'success', 'danger', 'warning', 'info'
 * @param string $message
 * @return void
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'][$type] = $message;
}

/**
 * Gets and clears a flash message from session.
 *
 * @param string $type
 * @return string|null
 */
function get_flash(string $type): ?string {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

/**
 * Checks if a flash message exists.
 *
 * @param string $type
 * @return bool
 */
function has_flash(string $type): bool {
    return !empty($_SESSION['flash'][$type]);
}

/**
 * Automatically queries highest existing student ID and returns the next ID (e.g. STU-005).
 *
 * @return string
 */
function generate_next_student_id(): string {
    $rows = db_fetch_all("SELECT student_id FROM students");
    $max_num = 0;
    foreach ($rows as $row) {
        if (preg_match('/(\d+)/', $row['student_id'], $m)) {
            $num = (int)$m[1];
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
    }
    return sprintf('STU-%03d', $max_num + 1);
}

/**
 * Automatically queries highest existing teacher ID and returns the next ID (e.g. TCH-004).
 *
 * @return string
 */
function generate_next_teacher_id(): string {
    $rows = db_fetch_all("SELECT teacher_id FROM teachers");
    $max_num = 0;
    foreach ($rows as $row) {
        if (preg_match('/(\d+)/', $row['teacher_id'], $m)) {
            $num = (int)$m[1];
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
    }
    return sprintf('TCH-%03d', $max_num + 1);
}
