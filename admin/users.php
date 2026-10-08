<?php
/**
 * admin/users.php — User Management & Role Assignment
 * Arsalan Model School and College Management System
 * Phase B1: Role-Based Access Control
 */

require_once __DIR__ . '/../database.php';
require_login();
require_role(['admin']);
require_permission('users.manage');

$user = current_user();
$page_title  = 'User Management';
$active_menu = 'admin_users';
$breadcrumb  = ['Administration', 'User Management'];
$depth       = '../';

$error = '';
$success = '';

// ═══════════════════════════════════════════════════════════════
// POST ACTION HANDLERS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // ─────────────────────────────────────────────────────────────
    // 1. CREATE TEACHER USER ACCOUNT
    // ─────────────────────────────────────────────────────────────
    if ($action === 'create_teacher') {
        $teacher_record_id = (int)($_POST['teacher_id'] ?? 0);
        $username          = trim($_POST['username'] ?? '');
        $password          = trim($_POST['password'] ?? '');
        $selected_classes  = $_POST['classes'] ?? [];

        if ($teacher_record_id <= 0 || $username === '' || $password === '') {
            set_flash('error', 'Please fill in all required fields (Teacher, Username, Password).');
            header('Location: users.php');
            exit;
        }

        if (strlen($password) < 5) {
            set_flash('error', 'Password must be at least 5 characters long.');
            header('Location: users.php');
            exit;
        }

        // Verify teacher exists
        $teacher = db_fetch_one("SELECT * FROM teachers WHERE id = ? LIMIT 1", 'i', [$teacher_record_id]);
        if (!$teacher) {
            set_flash('error', 'Selected teacher was not found in the database.');
            header('Location: users.php');
            exit;
        }

        // Check if username already exists
        $existing = db_fetch_one("SELECT id FROM users WHERE username = ? LIMIT 1", 's', [$username]);
        if ($existing) {
            set_flash('error', "Username '{$username}' is already taken. Please pick another.");
            header('Location: users.php');
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $full_name = trim($teacher['first_name'] . ' ' . $teacher['last_name']);
        $email = $teacher['email'] ?: null;

        // Find teacher role ID
        $teacher_role = db_fetch_one("SELECT id FROM roles WHERE LOWER(name) = 'teacher' LIMIT 1");
        $teacher_role_id = $teacher_role ? (int)$teacher_role['id'] : 2;

        // Insert into users
        db_execute(
            "INSERT INTO users (username, name, email, password, role, role_id, status) VALUES (?, ?, ?, ?, 'teacher', ?, 'active')",
            'ssssi',
            [$username, $full_name, $email, $password_hash, $teacher_role_id]
        );
        $new_user_id = db_last_id();

        // Link teacher record to this user_id
        db_execute("UPDATE teachers SET user_id = ? WHERE id = ?", 'ii', [$new_user_id, $teacher_record_id]);

        // Assign classes
        $assigned_count = 0;
        if (is_array($selected_classes) && count($selected_classes) > 0) {
            foreach ($selected_classes as $cid) {
                $cid = (int)$cid;
                if ($cid > 0) {
                    db_execute(
                        "INSERT IGNORE INTO teacher_class_assignments (teacher_user_id, class_id) VALUES (?, ?)",
                        'ii',
                        [$new_user_id, $cid]
                    );
                    $assigned_count++;
                }
            }
        }

        // Audit Log
        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Created Teacher account',
            'user',
            $new_user_id,
            "Created login '{$username}' for Teacher #{$teacher['teacher_id']} ({$full_name}). Assigned {$assigned_count} class(es)."
        );

        set_flash('success', "Teacher account '{$username}' created successfully with {$assigned_count} assigned class(es).");
        header('Location: users.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. CREATE FINANCE USER ACCOUNT
    // ─────────────────────────────────────────────────────────────
    if ($action === 'create_finance') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($name === '' || $username === '' || $password === '') {
            set_flash('error', 'Please fill in Name, Username, and Password for the Finance account.');
            header('Location: users.php');
            exit;
        }

        if (strlen($password) < 5) {
            set_flash('error', 'Password must be at least 5 characters long.');
            header('Location: users.php');
            exit;
        }

        // Check if username exists
        $existing = db_fetch_one("SELECT id FROM users WHERE username = ? LIMIT 1", 's', [$username]);
        if ($existing) {
            set_flash('error', "Username '{$username}' is already taken. Please pick another.");
            header('Location: users.php');
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $email_val = $email ?: null;

        $fin_role = db_fetch_one("SELECT id FROM roles WHERE LOWER(name) = 'finance' LIMIT 1");
        $fin_role_id = $fin_role ? (int)$fin_role['id'] : 4;

        db_execute(
            "INSERT INTO users (username, name, email, password, role, role_id, status) VALUES (?, ?, ?, ?, 'finance', ?, 'active')",
            'ssssi',
            [$username, $name, $email_val, $password_hash, $fin_role_id]
        );
        $new_user_id = db_last_id();

        // Audit Log
        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Created Finance account',
            'user',
            $new_user_id,
            "Created finance account '{$username}' for {$name}."
        );

        set_flash('success', "Finance account '{$username}' created successfully.");
        header('Location: users.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2B. CREATE USER WITH CUSTOM ROLE
    // ─────────────────────────────────────────────────────────────
    if ($action === 'create_custom_user') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role_id  = (int)($_POST['role_id'] ?? 0);

        if ($name === '' || $username === '' || $password === '' || $role_id <= 0) {
            set_flash('error', 'Please fill in Name, Username, Role, and Password.');
            header('Location: users.php');
            exit;
        }

        if (strlen($password) < 5) {
            set_flash('error', 'Password must be at least 5 characters long.');
            header('Location: users.php');
            exit;
        }

        $role_row = db_fetch_one("SELECT id, name FROM roles WHERE id = ? LIMIT 1", 'i', [$role_id]);
        if (!$role_row) {
            set_flash('error', 'Selected role does not exist.');
            header('Location: users.php');
            exit;
        }

        $existing = db_fetch_one("SELECT id FROM users WHERE username = ? LIMIT 1", 's', [$username]);
        if ($existing) {
            set_flash('error', "Username '{$username}' is already taken. Please pick another.");
            header('Location: users.php');
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $email_val = $email ?: null;
        $role_slug = strtolower(str_replace(' ', '_', $role_row['name']));

        db_execute(
            "INSERT INTO users (username, name, email, password, role, role_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
            'sssssi',
            [$username, $name, $email_val, $password_hash, $role_slug, $role_id]
        );
        $new_user_id = db_last_id();

        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Created user account',
            'user',
            $new_user_id,
            "Created account '{$username}' for {$name} with role '{$role_row['name']}'."
        );

        set_flash('success', "Account '{$username}' with role '{$role_row['name']}' created successfully.");
        header('Location: users.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2C. CREATE PARENT USER ACCOUNT WITH OPTIONAL STUDENT LINK
    // ─────────────────────────────────────────────────────────────
    if ($action === 'create_parent') {
        $name       = trim($_POST['name'] ?? '');
        $username   = trim($_POST['username'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        $student_id = (int)($_POST['student_id'] ?? 0);

        if ($username === '' || $password === '') {
            set_flash('error', 'Please fill in both Username and Password for the Parent account.');
            header('Location: users.php');
            exit;
        }

        if (strlen($password) < 5) {
            set_flash('error', 'Password must be at least 5 characters long.');
            header('Location: users.php');
            exit;
        }

        $existing = db_fetch_one("SELECT id FROM users WHERE username = ? LIMIT 1", 's', [$username]);
        if ($existing) {
            set_flash('error', "Username '{$username}' is already taken. Please choose another.");
            header('Location: users.php');
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $email_val = $email ?: null;

        $parent_role = db_fetch_one("SELECT id FROM roles WHERE LOWER(name) = 'parent' LIMIT 1");
        $parent_role_id = $parent_role ? (int)$parent_role['id'] : 3;

        db_execute(
            "INSERT INTO users (username, name, email, password, role, role_id, status) VALUES (?, ?, ?, ?, 'parent', ?, 'active')",
            'ssssi',
            [$username, $name, $email_val, $password_hash, $parent_role_id]
        );
        $new_user_id = db_last_id();

        $linked_msg = '';
        if ($student_id > 0) {
            $stu = db_fetch_one("SELECT id, student_id, first_name, last_name FROM students WHERE id = ? LIMIT 1", 'i', [$student_id]);
            if ($stu) {
                // Clear any previous parent on this student to enforce 1:1
                db_execute("UPDATE students SET parent_user_id = NULL WHERE id = ?", 'i', [$student_id]);
                db_execute("UPDATE students SET parent_user_id = ? WHERE id = ?", 'ii', [$new_user_id, $student_id]);
                $linked_msg = " and linked to student {$stu['student_id']} ({$stu['first_name']} {$stu['last_name']})";
            }
        }

        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Created Parent account',
            'user',
            $new_user_id,
            "Created parent account '{$username}'{$linked_msg}."
        );

        set_flash('success', "Parent account '{$username}' created successfully{$linked_msg}.");
        header('Location: users.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2D. LINK / UNLINK STUDENT TO PARENT ACCOUNT
    // ─────────────────────────────────────────────────────────────
    if ($action === 'link_student') {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $student_id     = (int)($_POST['student_id'] ?? 0);
        $unlink_only    = (int)($_POST['unlink_only'] ?? 0);

        if ($target_user_id <= 0) {
            set_flash('error', 'Invalid user account specified.');
            header('Location: users.php');
            exit;
        }

        $target_user = db_fetch_one("SELECT id, username, role FROM users WHERE id = ? LIMIT 1", 'i', [$target_user_id]);
        if (!$target_user) {
            set_flash('error', 'User account not found.');
            header('Location: users.php');
            exit;
        }

        if ($unlink_only === 1 || $student_id <= 0) {
            $prev_stu = db_fetch_one("SELECT id, student_id, first_name, last_name FROM students WHERE parent_user_id = ? LIMIT 1", 'i', [$target_user_id]);
            db_execute("UPDATE students SET parent_user_id = NULL WHERE parent_user_id = ?", 'i', [$target_user_id]);

            if ($prev_stu) {
                log_activity(
                    (int)$_SESSION['user_id'],
                    $_SESSION['role'],
                    'Unlinked parent account from student',
                    'user',
                    $target_user_id,
                    "Unlinked parent account '{$target_user['username']}' from student '{$prev_stu['student_id']}' ({$prev_stu['first_name']} {$prev_stu['last_name']})."
                );
                set_flash('success', "Parent account '{$target_user['username']}' unlinked from student '{$prev_stu['student_id']}'.");
            } else {
                set_flash('info', "No student was linked to this account.");
            }
        } else {
            // Verify student exists
            $stu = db_fetch_one("SELECT id, student_id, first_name, last_name FROM students WHERE id = ? LIMIT 1", 'i', [$student_id]);
            if (!$stu) {
                set_flash('error', 'Selected student record not found.');
                header('Location: users.php');
                exit;
            }

            // Enforce 1:1 mapping:
            // 1. If this parent was linked to a different student, log unlink from that student
            $prev_stu = db_fetch_one("SELECT id, student_id, first_name, last_name FROM students WHERE parent_user_id = ? LIMIT 1", 'i', [$target_user_id]);
            if ($prev_stu && (int)$prev_stu['id'] !== $student_id) {
                db_execute("UPDATE students SET parent_user_id = NULL WHERE parent_user_id = ?", 'i', [$target_user_id]);
                log_activity(
                    (int)$_SESSION['user_id'],
                    $_SESSION['role'],
                    'Unlinked parent account from student',
                    'user',
                    $target_user_id,
                    "Unlinked parent account '{$target_user['username']}' from student '{$prev_stu['student_id']}' ({$prev_stu['first_name']} {$prev_stu['last_name']})."
                );
            } else {
                db_execute("UPDATE students SET parent_user_id = NULL WHERE parent_user_id = ?", 'i', [$target_user_id]);
            }

            // 2. Clear any other parent currently linked to this student
            $prev_parent_on_new_stu = db_fetch_one("SELECT u.id, u.username FROM users u JOIN students s ON s.parent_user_id = u.id WHERE s.id = ? LIMIT 1", 'i', [$student_id]);
            if ($prev_parent_on_new_stu && (int)$prev_parent_on_new_stu['id'] !== $target_user_id) {
                db_execute("UPDATE students SET parent_user_id = NULL WHERE id = ?", 'i', [$student_id]);
                log_activity(
                    (int)$_SESSION['user_id'],
                    $_SESSION['role'],
                    'Unlinked parent account from student',
                    'user',
                    (int)$prev_parent_on_new_stu['id'],
                    "Unlinked parent account '{$prev_parent_on_new_stu['username']}' from student '{$stu['student_id']}' ({$stu['first_name']} {$stu['last_name']})."
                );
            } else {
                db_execute("UPDATE students SET parent_user_id = NULL WHERE id = ?", 'i', [$student_id]);
            }

            // Set new link
            db_execute("UPDATE students SET parent_user_id = ? WHERE id = ?", 'ii', [$target_user_id, $student_id]);

            log_activity(
                (int)$_SESSION['user_id'],
                $_SESSION['role'],
                'Linked parent account to student',
                'user',
                $target_user_id,
                "Linked parent account '{$target_user['username']}' to student '{$stu['student_id']}' ({$stu['first_name']} {$stu['last_name']})."
            );

            set_flash('success', "Parent account '{$target_user['username']}' successfully linked to student '{$stu['student_id']} — {$stu['first_name']} {$stu['last_name']}'.");
        }

        header('Location: users.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 3. TOGGLE ACTIVE / INACTIVE STATUS
    // ─────────────────────────────────────────────────────────────
    if ($action === 'toggle_status') {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $new_status     = trim($_POST['new_status'] ?? '');

        if (!in_array($new_status, ['active', 'inactive'], true)) {
            set_flash('error', 'Invalid status specified.');
            header('Location: users.php');
            exit;
        }

        // Prevent self-lockout
        if ($target_user_id === (int)$_SESSION['user_id']) {
            set_flash('error', 'You cannot deactivate your own administrative account.');
            header('Location: users.php');
            exit;
        }

        $target_user = db_fetch_one("SELECT id, username, role, status FROM users WHERE id = ? LIMIT 1", 'i', [$target_user_id]);
        if (!$target_user) {
            set_flash('error', 'User not found.');
            header('Location: users.php');
            exit;
        }

        db_execute("UPDATE users SET status = ? WHERE id = ?", 'si', [$new_status, $target_user_id]);

        $log_action = ($new_status === 'active') ? 'Reactivated user account' : 'Deactivated user account';
        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            $log_action,
            'user',
            $target_user_id,
            "Changed account status of '{$target_user['username']}' ({$target_user['role']}) to {$new_status}."
        );

        set_flash('success', "Account '{$target_user['username']}' status changed to " . ucfirst($new_status) . ".");
        header('Location: users.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 4. RESET PASSWORD
    // ─────────────────────────────────────────────────────────────
    if ($action === 'reset_password') {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $new_password   = trim($_POST['new_password'] ?? '');

        if ($target_user_id <= 0 || $new_password === '') {
            set_flash('error', 'Please provide a valid new password.');
            header('Location: users.php');
            exit;
        }

        if (strlen($new_password) < 5) {
            set_flash('error', 'New password must be at least 5 characters long.');
            header('Location: users.php');
            exit;
        }

        $target_user = db_fetch_one("SELECT id, username, role FROM users WHERE id = ? LIMIT 1", 'i', [$target_user_id]);
        if (!$target_user) {
            set_flash('error', 'User not found.');
            header('Location: users.php');
            exit;
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        db_execute("UPDATE users SET password = ? WHERE id = ?", 'si', [$new_hash, $target_user_id]);

        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Reset user password',
            'user',
            $target_user_id,
            "Admin reset password for username '{$target_user['username']}' ({$target_user['role']})."
        );

        set_flash('success', "Password for user '{$target_user['username']}' has been updated.");
        header('Location: users.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 5. EDIT USER ACCOUNT (NAME, EMAIL, ROLE, & STUDENT LINK)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'edit_user') {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $name           = trim($_POST['name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $role_id        = (int)($_POST['role_id'] ?? 0);
        $student_id     = (int)($_POST['student_id'] ?? 0);
        $unlink_student = (int)($_POST['unlink_student'] ?? 0);

        if ($target_user_id <= 0) {
            set_flash('error', 'Invalid user account specified.');
            header('Location: users.php');
            exit;
        }

        $target_user = db_fetch_one("SELECT id, username, role, role_id FROM users WHERE id = ? LIMIT 1", 'i', [$target_user_id]);
        if (!$target_user) {
            set_flash('error', 'User account not found.');
            header('Location: users.php');
            exit;
        }

        $email_val = $email ?: null;

        // If role_id provided, look up role
        $role_row = null;
        if ($role_id > 0) {
            $role_row = db_fetch_one("SELECT id, name FROM roles WHERE id = ? LIMIT 1", 'i', [$role_id]);
        }

        if ($role_row) {
            $role_slug = strtolower(str_replace(' ', '_', $role_row['name']));
            db_execute(
                "UPDATE users SET name = ?, email = ?, role = ?, role_id = ? WHERE id = ?",
                'sssii',
                [$name, $email_val, $role_slug, $role_id, $target_user_id]
            );
        } else {
            db_execute(
                "UPDATE users SET name = ?, email = ? WHERE id = ?",
                'ssi',
                [$name, $email_val, $target_user_id]
            );
        }

        // Student Linking Logic
        if ($unlink_student === 1) {
            $prev_stu = db_fetch_one("SELECT id, student_id, first_name, last_name FROM students WHERE parent_user_id = ? LIMIT 1", 'i', [$target_user_id]);
            db_execute("UPDATE students SET parent_user_id = NULL WHERE parent_user_id = ?", 'i', [$target_user_id]);
            if ($prev_stu) {
                log_activity(
                    (int)$_SESSION['user_id'],
                    $_SESSION['role'],
                    'Unlinked parent account from student',
                    'user',
                    $target_user_id,
                    "Unlinked parent account '{$target_user['username']}' from student '{$prev_stu['student_id']}' ({$prev_stu['first_name']} {$prev_stu['last_name']})."
                );
            }
        } elseif ($student_id > 0) {
            $stu = db_fetch_one("SELECT id, student_id, first_name, last_name FROM students WHERE id = ? LIMIT 1", 'i', [$student_id]);
            if ($stu) {
                // Clear any other student currently linked to this parent
                $prev_stu = db_fetch_one("SELECT id, student_id, first_name, last_name FROM students WHERE parent_user_id = ? LIMIT 1", 'i', [$target_user_id]);
                if ($prev_stu && (int)$prev_stu['id'] !== $student_id) {
                    db_execute("UPDATE students SET parent_user_id = NULL WHERE parent_user_id = ?", 'i', [$target_user_id]);
                    log_activity(
                        (int)$_SESSION['user_id'],
                        $_SESSION['role'],
                        'Unlinked parent account from student',
                        'user',
                        $target_user_id,
                        "Unlinked parent account '{$target_user['username']}' from student '{$prev_stu['student_id']}' ({$prev_stu['first_name']} {$prev_stu['last_name']})."
                    );
                } else {
                    db_execute("UPDATE students SET parent_user_id = NULL WHERE parent_user_id = ?", 'i', [$target_user_id]);
                }

                // Clear any other parent currently linked to this student
                $prev_parent_on_new_stu = db_fetch_one("SELECT u.id, u.username FROM users u JOIN students s ON s.parent_user_id = u.id WHERE s.id = ? LIMIT 1", 'i', [$student_id]);
                if ($prev_parent_on_new_stu && (int)$prev_parent_on_new_stu['id'] !== $target_user_id) {
                    db_execute("UPDATE students SET parent_user_id = NULL WHERE id = ?", 'i', [$student_id]);
                    log_activity(
                        (int)$_SESSION['user_id'],
                        $_SESSION['role'],
                        'Unlinked parent account from student',
                        'user',
                        (int)$prev_parent_on_new_stu['id'],
                        "Unlinked parent account '{$prev_parent_on_new_stu['username']}' from student '{$stu['student_id']}' ({$stu['first_name']} {$stu['last_name']})."
                    );
                } else {
                    db_execute("UPDATE students SET parent_user_id = NULL WHERE id = ?", 'i', [$student_id]);
                }

                // Set new link
                db_execute("UPDATE students SET parent_user_id = ? WHERE id = ?", 'ii', [$target_user_id, $student_id]);

                log_activity(
                    (int)$_SESSION['user_id'],
                    $_SESSION['role'],
                    'Linked parent account to student',
                    'user',
                    $target_user_id,
                    "Linked parent account '{$target_user['username']}' to student '{$stu['student_id']}' ({$stu['first_name']} {$stu['last_name']})."
                );
            }
        }

        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Updated user account',
            'user',
            $target_user_id,
            "Updated profile information for user '{$target_user['username']}'."
        );

        set_flash('success', "Account '{$target_user['username']}' updated successfully.");
        header('Location: users.php');
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// DATA FETCHING FOR VIEW
// ═══════════════════════════════════════════════════════════════
$role_filter = trim($_GET['role'] ?? '');
$search      = trim($_GET['search'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types  = "";

if ($role_filter !== '') {
    $where_clauses[] = "(u.role = ? OR r.name = ?)";
    $params[] = $role_filter;
    $params[] = $role_filter;
    $types   .= "ss";
}

if ($search !== '') {
    $where_clauses[] = "(u.username LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
    $types .= "sssssss";
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch all roles for dropdowns and filters
$all_roles = db_fetch_all("SELECT id, name, is_system_role FROM roles ORDER BY is_system_role DESC, id ASC");

// Fetch all users with linked person records and role names
$sql = "SELECT u.*,
               r.name AS role_name, r.is_system_role,
               t.id AS teacher_record_id, t.teacher_id, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
               s.id AS student_record_id, s.student_id, CONCAT(s.first_name, ' ', s.last_name) AS student_name,
               c.class_name AS student_class, c.section AS student_section
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN teachers t ON t.user_id = u.id
        LEFT JOIN students s ON s.parent_user_id = u.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE {$where_sql}
        ORDER BY u.id DESC";

$users_list = db_fetch_all($sql, $types, $params);

// Fetch assigned classes for all teachers
$assignments_raw = db_fetch_all(
    "SELECT tca.teacher_user_id, c.id AS class_id, c.class_name, c.section
     FROM teacher_class_assignments tca
     JOIN classes c ON tca.class_id = c.id
     ORDER BY c.id ASC"
);
$teacher_classes = [];
foreach ($assignments_raw as $ar) {
    $teacher_classes[$ar['teacher_user_id']][] = $ar['class_name'] . ' - ' . $ar['section'];
}

// Counts for KPI Cards
$count_all     = db_count("SELECT COUNT(*) FROM users");
$count_admin   = db_count("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$count_teacher = db_count("SELECT COUNT(*) FROM users WHERE role = 'teacher'");
$count_parent  = db_count("SELECT COUNT(*) FROM users WHERE role = 'parent'");
$count_finance = db_count("SELECT COUNT(*) FROM users WHERE role = 'finance'");

// Fetch active teachers for Create Teacher modal (list all teachers, indicate if already linked)
$all_teachers = db_fetch_all(
    "SELECT t.id, t.teacher_id, t.first_name, t.last_name, t.email, t.user_id, u.username AS existing_username
     FROM teachers t
     LEFT JOIN users u ON t.user_id = u.id
     WHERE t.status = 'active'
     ORDER BY t.first_name ASC"
);

// Fetch active classes for class assignment checkboxes
$all_classes = db_fetch_all(
    "SELECT id, class_name, section, room_number
     FROM classes
     WHERE status = 'active'
     ORDER BY id ASC"
);

// Roles that can be linked to students (Parent role or any role with students.view)
$parent_capable_roles = db_fetch_all(
    "SELECT DISTINCT r.id 
     FROM roles r
     LEFT JOIN role_permissions rp ON r.id = rp.role_id
     LEFT JOIN permissions p ON rp.permission_id = p.id
     WHERE (p.module = 'students' AND p.action = 'view') OR LOWER(r.name) = 'parent'"
);
$parent_role_ids = array_map(fn($r) => (int)$r['id'], $parent_capable_roles);

// Active students with classes for parent linking typeahead
$all_students_for_link = db_fetch_all(
    "SELECT s.id, s.student_id, s.first_name, s.last_name, s.parent_user_id,
            c.class_name, c.section, u.username AS linked_parent_username
     FROM students s
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN users u ON s.parent_user_id = u.id
     WHERE s.status = 'active'
     ORDER BY s.student_id ASC, s.first_name ASC"
);

$students_json_list = [];
foreach ($all_students_for_link as $s) {
    $full_name = trim($s['first_name'] . ' ' . $s['last_name']);
    $class_name = $s['class_name'] ?? 'No Class';
    $section = !empty($s['section']) ? 'Section ' . $s['section'] : '';
    $class_full = $class_name . ($section ? ' (' . $section . ')' : '');
    $display_label = $s['student_id'] . ' — ' . $full_name . ' — ' . $class_full;

    $students_json_list[] = [
        'id'              => (int)$s['id'],
        'student_code'    => $s['student_id'],
        'name'            => $full_name,
        'class_full'      => $class_full,
        'display'         => $display_label,
        'parent_user_id'  => (int)($s['parent_user_id'] ?? 0),
        'parent_username' => $s['linked_parent_username'] ?? '',
    ];
}

include_once __DIR__ . '/../layout.php';
?>

<style>
.student-search-container {
    position: relative;
}
.student-suggestions-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1060;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    max-height: 240px;
    overflow-y: auto;
    display: none;
    margin-top: 4px;
}
.student-suggestion-item {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.15s ease-in-out;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.student-suggestion-item:last-child {
    border-bottom: none;
}
.student-suggestion-item:hover,
.student-suggestion-item.active {
    background-color: #f0f7ff;
}
.student-suggestion-item .stu-main {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
}
.student-suggestion-item .stu-code {
    font-weight: 700;
    color: #0284c7;
    margin-right: 6px;
}
.student-suggestion-item .stu-class {
    font-size: 0.8rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 4px;
}
.student-suggestion-item mark {
    background-color: #fef08a;
    padding: 0 2px;
    border-radius: 2px;
}
.student-suggestion-empty {
    padding: 14px;
    text-align: center;
    color: #94a3b8;
    font-size: 0.875rem;
}
</style>

<div class="container-fluid px-0">

    <!-- Top KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-3 me-3">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Accounts</div>
                        <h4 class="mb-0 fw-bold"><?= (int)$count_all ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-info bg-opacity-10 text-info p-3 me-3">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Teachers</div>
                        <h4 class="mb-0 fw-bold"><?= (int)$count_teacher ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 text-success p-3 me-3">
                        <i class="fas fa-user-friends fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Parents</div>
                        <h4 class="mb-0 fw-bold"><?= (int)$count_parent ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-warning bg-opacity-10 text-warning p-3 me-3">
                        <i class="fas fa-user-shield fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Finance &amp; Admin</div>
                        <h4 class="mb-0 fw-bold"><?= (int)($count_finance + $count_admin) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="fas fa-users-cog text-primary me-2"></i>User Management
                </h5>
                <small class="text-muted">Manage credentials, assign classes to teachers, and enforce access control.</small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="roles.php" class="btn btn-outline-dark btn-sm px-3">
                    <i class="fas fa-user-shield me-1"></i> Roles &amp; Permissions
                </a>
                <button type="button" class="btn btn-outline-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createCustomUserModal">
                    <i class="fas fa-user-plus me-1"></i> Add Custom User
                </button>
                <button type="button" class="btn btn-success btn-sm px-3 text-white fw-semibold" data-bs-toggle="modal" data-bs-target="#createParentModal">
                    <i class="fas fa-user-friends me-1"></i> Add Parent Account
                </button>
                <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createTeacherModal">
                    <i class="fas fa-chalkboard-teacher me-1"></i> Add Teacher Account
                </button>
                <button type="button" class="btn btn-warning btn-sm px-3 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#createFinanceModal">
                    <i class="fas fa-coins me-1"></i> Add Finance Account
                </button>
            </div>
        </div>

        <div class="card-body">
            <!-- Filter & Search Bar -->
            <form method="GET" action="users.php" class="row g-2 align-items-center mb-4">
                <div class="col-md-4 col-sm-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search username, name, or student..." value="<?= h($search) ?>">
                    </div>
                </div>
                <div class="col-md-3 col-sm-4">
                    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Roles (<?= (int)$count_all ?>)</option>
                        <?php foreach ($all_roles as $r): ?>
                            <option value="<?= h($r['name']) ?>" <?= strtolower($role_filter) === strtolower($r['name']) ? 'selected' : '' ?>>
                                <?= h($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-2">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
                </div>
                <?php if ($search !== '' || $role_filter !== ''): ?>
                <div class="col-md-2 col-sm-2">
                    <a href="users.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                </div>
                <?php endif; ?>
            </form>

            <!-- Users Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 50px;">#ID</th>
                            <th>Username &amp; Name</th>
                            <th>Role</th>
                            <th>Linked Profile</th>
                            <th>Assigned Classes</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-user-slash fa-2x mb-2 d-block text-secondary"></i>
                                No user accounts found matching your filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $u): ?>
                            <?php
                            $uid = (int)$u['id'];
                            $urole = $u['role'];
                            $ustatus = $u['status'];
                            $role_display = !empty($u['role_name']) ? $u['role_name'] : ucfirst($urole);
                            $role_badge = match(strtolower($urole)) {
                                'admin'   => '<span class="badge bg-dark px-2 py-1"><i class="fas fa-shield-alt me-1"></i>Admin</span>',
                                'teacher' => '<span class="badge bg-primary px-2 py-1"><i class="fas fa-chalkboard-teacher me-1"></i>Teacher</span>',
                                'parent'  => '<span class="badge bg-success px-2 py-1"><i class="fas fa-user-friends me-1"></i>Parent</span>',
                                'finance' => '<span class="badge bg-warning text-dark px-2 py-1"><i class="fas fa-coins me-1"></i>Finance</span>',
                                default   => '<span class="badge text-white px-2 py-1" style="background-color:#6f42c1;"><i class="fas fa-user-tag me-1"></i>' . h($role_display) . '</span>'
                            };

                            $status_badge = ($ustatus === 'active')
                                ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-check-circle me-1"></i>Active</span>'
                                : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fas fa-ban me-1"></i>Inactive</span>';
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold text-muted"><?= $uid ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= h($u['username']) ?></div>
                                    <?php if (!empty($u['name'])): ?>
                                        <small class="text-secondary"><?= h($u['name']) ?></small>
                                    <?php elseif (!empty($u['email'])): ?>
                                        <small class="text-muted"><?= h($u['email']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $role_badge ?></td>
                                <td>
                                    <?php if ($urole === 'teacher'): ?>
                                        <?php if (!empty($u['teacher_name'])): ?>
                                            <span class="text-dark fw-semibold"><i class="fas fa-user-tie text-primary me-1"></i><?= h($u['teacher_name']) ?></span>
                                            <span class="badge bg-light text-muted border font-monospace ms-1"><?= h($u['teacher_id']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Unlinked Teacher</span>
                                        <?php endif; ?>
                                    <?php elseif ($urole === 'parent'): ?>
                                        <?php if (!empty($u['student_name'])): ?>
                                            <div>
                                                <span class="text-dark fw-semibold"><i class="fas fa-child text-success me-1"></i><?= h($u['student_name']) ?></span>
                                                <span class="badge bg-light text-muted border font-monospace ms-1"><?= h($u['student_id']) ?></span>
                                            </div>
                                            <small class="text-muted"><?= h($u['student_class'] . ' - ' . $u['student_section']) ?></small>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-outline-primary btn-xs py-0 px-2" style="font-size: 0.72rem;"
                                                        onclick="openLinkStudentModal(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>', <?= (int)$u['student_record_id'] ?>, '<?= h(addslashes($u['student_id'] . ' — ' . $u['student_name'])) ?>')">
                                                    <i class="fas fa-sync-alt me-1"></i>Change Link
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div><span class="text-danger small fst-italic"><i class="fas fa-exclamation-circle me-1"></i>No Student Linked</span></div>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-outline-success btn-xs py-0 px-2" style="font-size: 0.72rem;"
                                                        onclick="openLinkStudentModal(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>', 0, '')">
                                                    <i class="fas fa-link me-1"></i>Link to Student
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($urole === 'admin'): ?>
                                        <span class="badge bg-light text-dark border">System Administrator</span>
                                    <?php elseif ($urole === 'finance'): ?>
                                        <span class="text-muted">Accounts Office</span>
                                    <?php else: ?>
                                        <?php if (!empty($u['student_name'])): ?>
                                            <div>
                                                <span class="text-dark fw-semibold"><i class="fas fa-child text-primary me-1"></i><?= h($u['student_name']) ?></span>
                                                <span class="badge bg-light text-muted border font-monospace ms-1"><?= h($u['student_id']) ?></span>
                                            </div>
                                            <small class="text-muted"><?= h($u['student_class'] . ' - ' . $u['student_section']) ?></small>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-outline-primary btn-xs py-0 px-2" style="font-size: 0.72rem;"
                                                        onclick="openLinkStudentModal(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>', <?= (int)$u['student_record_id'] ?>, '<?= h(addslashes($u['student_id'] . ' — ' . $u['student_name'])) ?>')">
                                                    <i class="fas fa-sync-alt me-1"></i>Change Link
                                                </button>
                                            </div>
                                        <?php elseif (in_array((int)$u['role_id'], $parent_role_ids, true)): ?>
                                            <span class="text-muted small fst-italic">No student linked</span>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" style="font-size: 0.72rem;"
                                                        onclick="openLinkStudentModal(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>', 0, '')">
                                                    <i class="fas fa-link me-1"></i>Link Student
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($urole === 'teacher'): ?>
                                        <?php
                                        $classes = $teacher_classes[$uid] ?? [];
                                        if (!empty($classes)): ?>
                                            <div class="d-flex flex-wrap gap-1" style="max-width: 250px;">
                                                <?php foreach ($classes as $cname): ?>
                                                    <span class="badge bg-light text-primary border border-primary border-opacity-25" style="font-size: 0.75rem;">
                                                        <?= h($cname) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">No classes assigned</span>
                                        <?php endif; ?>
                                    <?php elseif ($urole === 'admin'): ?>
                                        <span class="badge bg-light text-dark border">Full Access</span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $status_badge ?></td>
                                <td class="small text-muted">
                                    <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Edit Account Button -->
                                        <button type="button" class="btn btn-outline-primary"
                                                title="Edit Account"
                                                onclick="openEditUserModal(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>', '<?= h(addslashes($u['name'] ?? '')) ?>', '<?= h(addslashes($u['email'] ?? '')) ?>', <?= (int)$u['role_id'] ?>, <?= (int)($u['student_record_id'] ?? 0) ?>, '<?= h(addslashes(($u['student_id'] ?? '') . (!empty($u['student_name']) ? ' — ' . $u['student_name'] : ''))) ?>')">
                                            <i class="fas fa-user-edit"></i>
                                        </button>

                                        <!-- Quick Link Student Button for parent/custom roles -->
                                        <?php if ($urole === 'parent' || in_array((int)$u['role_id'], $parent_role_ids, true)): ?>
                                            <button type="button" class="btn btn-outline-info"
                                                    title="<?= !empty($u['student_name']) ? 'Change / Unlink Student' : 'Link Account to Student' ?>"
                                                    onclick="openLinkStudentModal(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>', <?= (int)($u['student_record_id'] ?? 0) ?>, '<?= h(addslashes(($u['student_id'] ?? '') . (!empty($u['student_name']) ? ' — ' . $u['student_name'] : ''))) ?>')">
                                                <i class="fas fa-link"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Reset Password Button -->
                                        <button type="button" class="btn btn-outline-secondary" 
                                                title="Reset Password"
                                                onclick="openResetModal(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>')">
                                            <i class="fas fa-key"></i>
                                        </button>

                                        <!-- Toggle Status Button -->
                                        <?php if ($uid !== (int)$_SESSION['user_id']): ?>
                                            <?php if ($ustatus === 'active'): ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        title="Deactivate Account"
                                                        onclick="confirmToggleStatus(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>', 'inactive')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success" 
                                                        title="Reactivate Account"
                                                        onclick="confirmToggleStatus(<?= $uid ?>, '<?= h(addslashes($u['username'])) ?>', 'active')">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary disabled" title="Current Account" disabled>
                                                <i class="fas fa-user-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: CREATE CUSTOM USER ACCOUNT                               -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="createCustomUserModal" tabindex="-1" aria-labelledby="createCustomUserLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content">
            <input type="hidden" name="action" value="create_custom_user">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="createCustomUserLabel">
                    <i class="fas fa-user-plus me-2 text-primary"></i>Create Custom Role Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Tariq Mehmood" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Role <span class="text-danger">*</span></label>
                    <select name="role_id" class="form-select" required>
                        <option value="">-- Choose Role --</option>
                        <?php foreach ($all_roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"><?= h($r['name']) ?><?= $r['is_system_role'] ? ' (System)' : ' (Custom)' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="e.g. librarian1" required autocomplete="off">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Email Address (Optional)</label>
                    <input type="email" name="email" class="form-control" placeholder="staff@arsalan.edu.pk">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Min 5 characters" minlength="5" required autocomplete="new-password">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fas fa-save me-1"></i>Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: CREATE TEACHER ACCOUNT                                   -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="createTeacherModal" tabindex="-1" aria-labelledby="createTeacherLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="users.php" class="modal-content">
            <input type="hidden" name="action" value="create_teacher">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="createTeacherLabel">
                    <i class="fas fa-chalkboard-teacher me-2"></i>Create Teacher User Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Select Teacher Profile <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="teacherSelect" class="form-select" required onchange="autoFillTeacherUsername(this)">
                            <option value="">-- Choose Teacher from Staff Records --</option>
                            <?php foreach ($all_teachers as $tch): ?>
                                <?php
                                $is_linked = !empty($tch['user_id']);
                                $label = h($tch['teacher_id'] . ' — ' . $tch['first_name'] . ' ' . $tch['last_name']);
                                if ($is_linked) {
                                    $label .= ' (Already linked to ' . h($tch['existing_username']) . ')';
                                }
                                ?>
                                <option value="<?= (int)$tch['id'] ?>" 
                                        data-tid="<?= h(strtolower(str_replace([' ', '-'], '_', $tch['teacher_id']))) ?>"
                                        data-firstname="<?= h(strtolower($tch['first_name'])) ?>"
                                        <?= $is_linked ? 'style="color:#888;"' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Linking a teacher associates marks and attendance records with their login.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" name="username" id="teacherUsernameInput" class="form-control" placeholder="e.g. tch_tariq" required autocomplete="off">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Initial Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="password" id="teacherPasswordInput" class="form-control" placeholder="Min 5 characters" minlength="5" required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold mb-1">
                            <i class="fas fa-school text-primary me-1"></i>Assign Permitted Classes
                        </label>
                        <p class="text-muted small mb-2">Check the classes this teacher will be restricted to managing:</p>
                        
                        <div class="border rounded-3 p-3 bg-light" style="max-height: 220px; overflow-y: auto;">
                            <div class="row g-2">
                                <?php foreach ($all_classes as $cls): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="classes[]" value="<?= (int)$cls['id'] ?>" id="cls_<?= (int)$cls['id'] ?>">
                                            <label class="form-check-label small" for="cls_<?= (int)$cls['id'] ?>">
                                                <strong><?= h($cls['class_name']) ?></strong> - <?= h($cls['section']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i>Create Teacher Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: CREATE FINANCE ACCOUNT                                   -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="createFinanceModal" tabindex="-1" aria-labelledby="createFinanceLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content">
            <input type="hidden" name="action" value="create_finance">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold" id="createFinanceLabel">
                    <i class="fas fa-coins me-2"></i>Create Finance User Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Staff Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Kamran Qureshi" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="e.g. finance_kamran" required autocomplete="off">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Email Address (Optional)</label>
                    <input type="email" name="email" class="form-control" placeholder="kamran@arsalan.edu.pk">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Min 5 characters" minlength="5" required autocomplete="new-password">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning px-4 fw-bold"><i class="fas fa-save me-1"></i>Create Finance Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: RESET PASSWORD                                           -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="resetPasswordLabel">
                    <i class="fas fa-key me-2 text-warning"></i>Reset User Password
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Set a new password for account: <strong id="resetUsernameDisplay" class="text-primary font-monospace"></strong></p>
                <div class="mb-3">
                    <label class="form-label fw-bold">New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" name="new_password" id="resetNewPassword" class="form-control" placeholder="Enter new password (min 5 chars)" minlength="5" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-check me-1"></i>Save New Password</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: CREATE PARENT USER ACCOUNT                               -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="createParentModal" tabindex="-1" aria-labelledby="createParentLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="users.php" class="modal-content" id="createParentForm">
            <input type="hidden" name="action" value="create_parent">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="createParentLabel">
                    <i class="fas fa-user-friends me-2"></i>Create Parent User Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- Student Selection Typeahead -->
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Link to Student <span class="text-muted fw-normal">(Search by Name or Student ID)</span></label>
                        <div class="student-search-container" id="cpSearchContainer">
                            <div class="input-group">
                                <span class="input-group-text bg-white text-success border-end-0">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text"
                                       id="cpStudentSearch"
                                       class="form-control border-start-0 ps-0"
                                       placeholder="Type student name or ID (e.g. STU-001 or Bilal)..."
                                       autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" id="cpClearStudentBtn" title="Clear student" style="display:none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <input type="hidden" name="student_id" id="cpSelectedStudentId" value="0">
                            <div id="cpStudentSuggestions" class="student-suggestions-dropdown"></div>
                        </div>
                        <small class="text-muted">Associates this parent account directly to their child's portal dashboard and records.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Selected Student Info</label>
                        <input type="text" id="cpSelectedStudentDisplay" class="form-control bg-light" readonly placeholder="None selected (optional)">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Parent / Guardian Full Name</label>
                        <input type="text" name="name" id="cpParentName" class="form-control" placeholder="e.g. Muhammad Farooq">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" name="username" id="cpUsernameInput" class="form-control" placeholder="e.g. STU-001 or parent_farooq" required autocomplete="off">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Initial Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                            <input type="text" name="password" id="cpPasswordInput" class="form-control font-monospace" placeholder="Min 5 characters" minlength="5" required autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="generateRandomParentPassword('cpPasswordInput')" title="Generate random password">
                                <i class="fas fa-dice"></i> Generate
                            </button>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold">Email Address (Optional)</label>
                        <input type="email" name="email" id="cpEmailInput" class="form-control" placeholder="parent@example.com">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4 fw-bold"><i class="fas fa-save me-1"></i>Create Parent Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: LINK / CHANGE STUDENT FOR PARENT ACCOUNT                 -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="linkStudentModal" tabindex="-1" aria-labelledby="linkStudentLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content" id="linkStudentForm">
            <input type="hidden" name="action" value="link_student">
            <input type="hidden" name="user_id" id="linkTargetUserId" value="0">
            <input type="hidden" name="unlink_only" id="linkUnlinkOnly" value="0">
            
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="linkStudentLabel">
                    <i class="fas fa-link me-2 text-primary"></i>Link Account to Student
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <span class="text-muted small">Account Username:</span>
                    <h5 class="mb-0 fw-bold text-dark font-monospace" id="linkTargetUsernameDisplay"></h5>
                </div>

                <!-- Current Link Status Box -->
                <div class="alert alert-info py-2 px-3 mb-3" id="linkCurrentStatusBox">
                    <div class="small fw-semibold text-secondary">Current Status:</div>
                    <div id="linkCurrentStatusText" class="fw-bold text-dark"></div>
                </div>

                <!-- Search Student Typeahead -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Search &amp; Select Student <span class="text-danger">*</span></label>
                    <div class="student-search-container" id="lsSearchContainer">
                        <div class="input-group">
                            <span class="input-group-text bg-white text-primary border-end-0">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text"
                                   id="lsStudentSearch"
                                   class="form-control border-start-0 ps-0"
                                   placeholder="Type student name or ID..."
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="lsClearStudentBtn" title="Clear selection" style="display:none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" name="student_id" id="lsSelectedStudentId" value="0">
                        <div id="lsStudentSuggestions" class="student-suggestions-dropdown"></div>
                    </div>
                    <small class="text-muted">Type student ID or name to link to this account (1:1 child mapping).</small>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-semibold small text-muted">Selected Student:</label>
                    <input type="text" id="lsSelectedStudentDisplay" class="form-control form-control-sm bg-light" readonly placeholder="None selected">
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnUnlinkStudent" onclick="submitUnlinkStudent()" style="display:none;">
                        <i class="fas fa-unlink me-1"></i> Unlink Student
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-3 fw-bold" id="btnSaveLink">
                        <i class="fas fa-save me-1"></i> Save Link
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: EDIT USER ACCOUNT                                        -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="users.php" class="modal-content" id="editUserForm">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="editUserId" value="0">
            <input type="hidden" name="unlink_student" id="editUnlinkStudent" value="0">
            
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="editUserLabel">
                    <i class="fas fa-user-edit me-2 text-primary"></i>Edit User Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" id="editUsernameDisplay" class="form-control bg-light font-monospace" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="name" id="editNameInput" class="form-control" placeholder="Full name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email" id="editEmailInput" class="form-control" placeholder="Email address">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Assigned Role</label>
                        <select name="role_id" id="editRoleIdSelect" class="form-select" onchange="onEditRoleChange(this)">
                            <?php foreach ($all_roles as $r): ?>
                                <option value="<?= (int)$r['id'] ?>" data-name="<?= h(strtolower($r['name'])) ?>"><?= h($r['name']) ?><?= $r['is_system_role'] ? ' (System)' : ' (Custom)' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Student Linking Section (Shown when Role is Parent or Parent-Capable) -->
                    <div class="col-md-12" id="editStudentLinkSection">
                        <hr class="my-2">
                        <label class="form-label fw-bold text-success mb-1">
                            <i class="fas fa-child me-1"></i> Link to Student (Parent Account)
                        </label>
                        
                        <div class="alert alert-light border py-2 px-3 mb-2" id="editCurrentStudentBox">
                            <div class="small text-muted">Currently Linked:</div>
                            <div id="editCurrentStudentText" class="fw-bold text-dark">—</div>
                        </div>

                        <div class="student-search-container" id="editSearchContainer">
                            <div class="input-group">
                                <span class="input-group-text bg-white text-primary border-end-0">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text"
                                       id="editStudentSearch"
                                       class="form-control border-start-0 ps-0"
                                       placeholder="Type to change linked student..."
                                       autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" id="editClearStudentBtn" title="Clear selection" style="display:none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <input type="hidden" name="student_id" id="editSelectedStudentId" value="0">
                            <div id="editStudentSuggestions" class="student-suggestions-dropdown"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Type student ID or name to link or change student.</small>
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none" id="editBtnUnlinkStudent" onclick="markEditStudentUnlink()" style="display:none;">
                                <i class="fas fa-unlink me-1"></i>Clear / Unlink Student
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fas fa-save me-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Form for Toggle Status -->
<form id="toggleStatusForm" method="POST" action="users.php" style="display:none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="toggleUserId">
    <input type="hidden" name="new_status" id="toggleNewStatus">
</form>

<script>
const allStudentsData = <?= json_encode($students_json_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const parentRoleIds = <?= json_encode($parent_role_ids) ?>;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function highlightText(text, query) {
    if (!query) return escapeHtml(text);
    const escapedText = escapeHtml(text);
    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp('(' + escapedQuery + ')', 'gi');
    return escapedText.replace(regex, '<mark>$1</mark>');
}

function generateRandomParentPassword(inputId) {
    const chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    let res = 'AMS-';
    for (let i = 0; i < 6; i++) {
        res += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const elem = document.getElementById(inputId);
    if (elem) elem.value = res;
}

// Reusable Typeahead Builder
function setupStudentTypeahead(config) {
    const searchInput    = document.getElementById(config.inputId);
    const clearBtn       = document.getElementById(config.clearBtnId);
    const suggestionsBox = document.getElementById(config.suggestionsId);
    const hiddenInput    = document.getElementById(config.hiddenId);
    const displayInput   = config.displayId ? document.getElementById(config.displayId) : null;

    let currentMatches = [];
    let activeIndex = -1;

    function render(query) {
        query = (query || '').trim().toLowerCase();
        if (!query) {
            suggestionsBox.style.display = 'none';
            return;
        }

        currentMatches = allStudentsData.filter(s => {
            return s.student_code.toLowerCase().includes(query) ||
                   s.name.toLowerCase().includes(query) ||
                   s.class_full.toLowerCase().includes(query);
        }).slice(0, 10);

        if (currentMatches.length === 0) {
            suggestionsBox.innerHTML = '<div class="student-suggestion-empty">No matching active students found</div>';
            suggestionsBox.style.display = 'block';
            return;
        }

        let html = '';
        currentMatches.forEach((s, idx) => {
            const codeH = highlightText(s.student_code, query);
            const nameH = highlightText(s.name, query);
            const parentNote = s.parent_username ? `<span class="badge bg-warning bg-opacity-25 text-dark ms-1" style="font-size:0.7rem;">Linked to ${escapeHtml(s.parent_username)}</span>` : '';

            html += `
                <div class="student-suggestion-item" data-index="${idx}">
                    <div class="stu-main">
                        <span class="stu-code">${codeH}</span>
                        <span>${nameH}</span>
                        ${parentNote}
                    </div>
                    <span class="stu-class">${escapeHtml(s.class_full)}</span>
                </div>
            `;
        });

        suggestionsBox.innerHTML = html;
        suggestionsBox.style.display = 'block';
        activeIndex = -1;

        suggestionsBox.querySelectorAll('.student-suggestion-item').forEach(item => {
            item.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'), 10);
                selectItem(currentMatches[idx]);
            });
        });
    }

    function selectItem(student) {
        hiddenInput.value = student.id;
        searchInput.value = student.display;
        if (displayInput) {
            displayInput.value = student.display;
        }
        clearBtn.style.display = 'inline-block';
        suggestionsBox.style.display = 'none';
        if (typeof config.onSelect === 'function') {
            config.onSelect(student);
        }
    }

    function clear() {
        hiddenInput.value = '0';
        searchInput.value = '';
        if (displayInput) {
            displayInput.value = '';
        }
        clearBtn.style.display = 'none';
        suggestionsBox.style.display = 'none';
        if (typeof config.onClear === 'function') {
            config.onClear();
        }
    }

    clearBtn.addEventListener('click', clear);

    searchInput.addEventListener('input', function() {
        if (hiddenInput.value !== '0') {
            hiddenInput.value = '0';
            if (displayInput) displayInput.value = '';
        }
        if (this.value.trim()) {
            clearBtn.style.display = 'inline-block';
            render(this.value);
        } else {
            clearBtn.style.display = 'none';
            suggestionsBox.style.display = 'none';
        }
    });

    searchInput.addEventListener('keydown', function(e) {
        if (suggestionsBox.style.display === 'none' || currentMatches.length === 0) return;
        const items = suggestionsBox.querySelectorAll('.student-suggestion-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % items.length;
            updateActive(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + items.length) % items.length;
            updateActive(items);
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && activeIndex < currentMatches.length) {
                e.preventDefault();
                selectItem(currentMatches[activeIndex]);
            }
        } else if (e.key === 'Escape') {
            suggestionsBox.style.display = 'none';
        }
    });

    function updateActive(items) {
        items.forEach((it, idx) => {
            if (idx === activeIndex) {
                it.classList.add('active');
                it.scrollIntoView({ block: 'nearest' });
            } else {
                it.classList.remove('active');
            }
        });
    }

    document.addEventListener('click', function(e) {
        const container = document.getElementById(config.containerId);
        if (container && !container.contains(e.target)) {
            suggestionsBox.style.display = 'none';
        }
    });

    return { selectItem, clear, render };
}

// 1. Setup Typeahead for Create Parent Modal
const cpTypeahead = setupStudentTypeahead({
    containerId:   'cpSearchContainer',
    inputId:       'cpStudentSearch',
    clearBtnId:    'cpClearStudentBtn',
    suggestionsId: 'cpStudentSuggestions',
    hiddenId:      'cpSelectedStudentId',
    displayId:     'cpSelectedStudentDisplay',
    onSelect: function(student) {
        const userIn = document.getElementById('cpUsernameInput');
        if (!userIn.value || userIn.value.startsWith('parent_') || allStudentsData.some(s => s.student_code === userIn.value)) {
            userIn.value = student.student_code;
        }
        const nameIn = document.getElementById('cpParentName');
        if (!nameIn.value) {
            nameIn.value = student.name + ' Parent';
        }
        const pwdIn = document.getElementById('cpPasswordInput');
        if (!pwdIn.value) {
            generateRandomParentPassword('cpPasswordInput');
        }
    }
});

// 2. Setup Typeahead for Quick Link Student Modal
const lsTypeahead = setupStudentTypeahead({
    containerId:   'lsSearchContainer',
    inputId:       'lsStudentSearch',
    clearBtnId:    'lsClearStudentBtn',
    suggestionsId: 'lsStudentSuggestions',
    hiddenId:      'lsSelectedStudentId',
    displayId:     'lsSelectedStudentDisplay'
});

// 3. Setup Typeahead for Edit User Modal
const editTypeahead = setupStudentTypeahead({
    containerId:   'editSearchContainer',
    inputId:       'editStudentSearch',
    clearBtnId:    'editClearStudentBtn',
    suggestionsId: 'editStudentSuggestions',
    hiddenId:      'editSelectedStudentId',
    displayId:     null,
    onSelect: function(student) {
        document.getElementById('editUnlinkStudent').value = '0';
    }
});

// Modal Openers
function openLinkStudentModal(userId, username, currentStudentId, currentStudentDisplay) {
    document.getElementById('linkTargetUserId').value = userId;
    document.getElementById('linkTargetUsernameDisplay').textContent = username;
    document.getElementById('linkUnlinkOnly').value = '0';
    
    const statusText = document.getElementById('linkCurrentStatusText');
    const btnUnlink = document.getElementById('btnUnlinkStudent');
    
    lsTypeahead.clear();

    if (currentStudentId && currentStudentId > 0) {
        statusText.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' + escapeHtml(currentStudentDisplay) + '</span>';
        btnUnlink.style.display = 'inline-block';
    } else {
        statusText.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>No student currently linked</span>';
        btnUnlink.style.display = 'none';
    }

    const modal = new bootstrap.Modal(document.getElementById('linkStudentModal'));
    modal.show();
}

function submitUnlinkStudent() {
    if (confirm('Are you sure you want to unlink this student from the parent account?')) {
        document.getElementById('linkUnlinkOnly').value = '1';
        document.getElementById('linkStudentForm').submit();
    }
}

document.getElementById('linkStudentForm').addEventListener('submit', function(e) {
    const unlinkOnly = document.getElementById('linkUnlinkOnly').value;
    const studentId = parseInt(document.getElementById('lsSelectedStudentId').value, 10);
    if (unlinkOnly !== '1' && (!studentId || studentId <= 0)) {
        e.preventDefault();
        alert('Please search and select a student from the suggestion list, or click Unlink Student.');
        document.getElementById('lsStudentSearch').focus();
    }
});

function openEditUserModal(userId, username, name, email, roleId, currentStudentId, currentStudentDisplay) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editUsernameDisplay').value = username;
    document.getElementById('editNameInput').value = name || '';
    document.getElementById('editEmailInput').value = email || '';
    document.getElementById('editUnlinkStudent').value = '0';
    
    const roleSelect = document.getElementById('editRoleIdSelect');
    roleSelect.value = roleId;

    const linkSection = document.getElementById('editStudentLinkSection');
    const isParentCapable = parentRoleIds.includes(parseInt(roleId, 10));
    linkSection.style.display = isParentCapable ? 'block' : 'none';

    const curText = document.getElementById('editCurrentStudentText');
    const btnUnlink = document.getElementById('editBtnUnlinkStudent');
    editTypeahead.clear();

    if (currentStudentId && currentStudentId > 0) {
        curText.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' + escapeHtml(currentStudentDisplay) + '</span>';
        btnUnlink.style.display = 'inline-block';
    } else {
        curText.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>No student linked</span>';
        btnUnlink.style.display = 'none';
    }

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function onEditRoleChange(selectElem) {
    const roleId = parseInt(selectElem.value, 10);
    const linkSection = document.getElementById('editStudentLinkSection');
    linkSection.style.display = parentRoleIds.includes(roleId) ? 'block' : 'none';
}

function markEditStudentUnlink() {
    if (confirm('Clear the student link for this user?')) {
        document.getElementById('editUnlinkStudent').value = '1';
        editTypeahead.clear();
        document.getElementById('editCurrentStudentText').innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Link will be removed on Save</span>';
        document.getElementById('editBtnUnlinkStudent').style.display = 'none';
    }
}

function autoFillTeacherUsername(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;
    const tid = selectedOption.getAttribute('data-tid') || '';
    const fname = selectedOption.getAttribute('data-firstname') || '';
    const input = document.getElementById('teacherUsernameInput');
    if (input && (!input.value || input.value.startsWith('tch_'))) {
        input.value = 'tch_' + (fname ? fname : tid);
    }
}

function openResetModal(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUsernameDisplay').textContent = username;
    document.getElementById('resetNewPassword').value = '';
    const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    modal.show();
}

function confirmToggleStatus(userId, username, newStatus) {
    const actionWord = (newStatus === 'active') ? 'reactivate' : 'deactivate';
    if (confirm(`Are you sure you want to ${actionWord} the account "${username}"?`)) {
        document.getElementById('toggleUserId').value = userId;
        document.getElementById('toggleNewStatus').value = newStatus;
        document.getElementById('toggleStatusForm').submit();
    }
}
</script>

<?php
include_once __DIR__ . '/../layout_end.php';
?>
