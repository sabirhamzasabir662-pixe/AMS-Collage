<?php
/**
 * admin/roles.php — Roles & Granular Permissions Management
 * Arsalan Model School and College Management System
 * Phase B1.5: Custom Roles & Granular RBAC
 */

require_once __DIR__ . '/../database.php';
require_login();
require_role(['admin']);
require_permission('users.manage');

$user = current_user();
$page_title  = 'Roles & Permissions';
$active_menu = 'admin_roles';
$breadcrumb  = ['Administration', 'Roles & Permissions'];
$depth       = '../';

// Fetch all available permissions grouped by module
$all_permissions = db_fetch_all("SELECT * FROM permissions ORDER BY module ASC, id ASC");
$permissions_by_module = [];
$perm_id_map = [];
$users_manage_perm_id = null;

foreach ($all_permissions as $p) {
    $permissions_by_module[$p['module']][] = $p;
    $perm_id_map[$p['id']] = $p;
    if ($p['module'] === 'users' && $p['action'] === 'manage') {
        $users_manage_perm_id = (int)$p['id'];
    }
}

// ═══════════════════════════════════════════════════════════════
// POST ACTION HANDLERS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // ─────────────────────────────────────────────────────────────
    // 1. CREATE CUSTOM ROLE
    // ─────────────────────────────────────────────────────────────
    if ($action === 'create_role') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $perm_ids    = $_POST['permissions'] ?? [];

        if ($name === '') {
            set_flash('error', 'Role name is required.');
            header('Location: roles.php');
            exit;
        }

        // Check uniqueness
        $existing = db_fetch_one("SELECT id FROM roles WHERE LOWER(name) = LOWER(?) LIMIT 1", 's', [$name]);
        if ($existing) {
            set_flash('error', "A role with the name '{$name}' already exists.");
            header('Location: roles.php');
            exit;
        }

        // Insert role
        db_execute(
            "INSERT INTO roles (name, description, is_system_role) VALUES (?, ?, 0)",
            'ss',
            [$name, $description ?: null]
        );
        $new_role_id = db_last_id();

        // Assign permissions
        $assigned_count = 0;
        if (is_array($perm_ids) && count($perm_ids) > 0) {
            foreach ($perm_ids as $pid) {
                $pid = (int)$pid;
                if (isset($perm_id_map[$pid])) {
                    db_execute("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)", 'ii', [$new_role_id, $pid]);
                    $assigned_count++;
                }
            }
        }

        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Created custom role',
            'role',
            $new_role_id,
            "Created role '{$name}' with {$assigned_count} permission(s)."
        );

        set_flash('success', "Custom role '{$name}' created successfully with {$assigned_count} permission(s).");
        header('Location: roles.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. UPDATE ROLE & PERMISSIONS
    // ─────────────────────────────────────────────────────────────
    if ($action === 'update_role') {
        $role_id     = (int)($_POST['role_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $perm_ids    = $_POST['permissions'] ?? [];

        $role = db_fetch_one("SELECT * FROM roles WHERE id = ? LIMIT 1", 'i', [$role_id]);
        if (!$role) {
            set_flash('error', 'Role not found.');
            header('Location: roles.php');
            exit;
        }

        $is_system = (int)$role['is_system_role'] === 1;

        // Name validation for non-system roles
        if (!$is_system) {
            if ($name === '') {
                set_flash('error', 'Role name cannot be empty.');
                header('Location: roles.php');
                exit;
            }
            $dup = db_fetch_one("SELECT id FROM roles WHERE LOWER(name) = LOWER(?) AND id != ? LIMIT 1", 'si', [$name, $role_id]);
            if ($dup) {
                set_flash('error', "Another role named '{$name}' already exists.");
                header('Location: roles.php');
                exit;
            }
        } else {
            // Keep system role name unchanged
            $name = $role['name'];
        }

        // Clean permission list
        $clean_perms = [];
        if (is_array($perm_ids)) {
            foreach ($perm_ids as $pid) {
                $pid = (int)$pid;
                if (isset($perm_id_map[$pid])) {
                    $clean_perms[] = $pid;
                }
            }
        }

        // ── Safety Guard 1: Logged-in admin's own role cannot lose users.manage ──
        $current_user_row = db_fetch_one("SELECT role_id, role FROM users WHERE id = ?", 'i', [(int)$_SESSION['user_id']]);
        if ($current_user_row && (int)$current_user_row['role_id'] === $role_id) {
            if ($users_manage_perm_id && !in_array($users_manage_perm_id, $clean_perms, true)) {
                set_flash('error', "Safety restriction: You cannot remove 'users.manage' permission from your own assigned role.");
                header('Location: roles.php');
                exit;
            }
        }

        // ── Safety Guard 2: Total roles with users.manage cannot drop to 0 ──
        if ($users_manage_perm_id && !in_array($users_manage_perm_id, $clean_perms, true)) {
            $other_roles_with_manage = db_count(
                "SELECT COUNT(DISTINCT role_id) FROM role_permissions WHERE permission_id = ? AND role_id != ?",
                'ii',
                [$users_manage_perm_id, $role_id]
            );
            if ($other_roles_with_manage === 0) {
                set_flash('error', "Safety restriction: At least one role must retain 'users.manage' permission.");
                header('Location: roles.php');
                exit;
            }
        }

        // Update role details
        db_execute(
            "UPDATE roles SET name = ?, description = ? WHERE id = ?",
            'ssi',
            [$name, $description ?: null, $role_id]
        );

        // Synchronize permissions
        db_execute("DELETE FROM role_permissions WHERE role_id = ?", 'i', [$role_id]);
        foreach ($clean_perms as $pid) {
            db_execute("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", 'ii', [$role_id, $pid]);
        }

        // If updating current user's role, refresh their cached session permissions
        if ($current_user_row && (int)$current_user_row['role_id'] === $role_id) {
            $_SESSION['permissions'] = load_user_permissions((int)$_SESSION['user_id']);
        }

        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Updated role permissions',
            'role',
            $role_id,
            "Updated role '{$name}' with " . count($clean_perms) . " permissions."
        );

        set_flash('success', "Role '{$name}' updated successfully with " . count($clean_perms) . " permission(s).");
        header('Location: roles.php');
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 3. DELETE ROLE
    // ─────────────────────────────────────────────────────────────
    if ($action === 'delete_role') {
        $role_id = (int)($_POST['role_id'] ?? 0);

        $role = db_fetch_one("SELECT * FROM roles WHERE id = ? LIMIT 1", 'i', [$role_id]);
        if (!$role) {
            set_flash('error', 'Role not found.');
            header('Location: roles.php');
            exit;
        }

        // Cannot delete system roles
        if ((int)$role['is_system_role'] === 1) {
            set_flash('error', "System role '{$role['name']}' is protected and cannot be deleted.");
            header('Location: roles.php');
            exit;
        }

        // Cannot delete if users are assigned
        $user_count = db_count("SELECT COUNT(*) FROM users WHERE role_id = ?", 'i', [$role_id]);
        if ($user_count > 0) {
            set_flash('error', "Cannot delete role '{$role['name']}' because {$user_count} user(s) are currently assigned to it. Please reassign them first.");
            header('Location: roles.php');
            exit;
        }

        // Delete role (cascade deletes role_permissions)
        db_execute("DELETE FROM role_permissions WHERE role_id = ?", 'i', [$role_id]);
        db_execute("DELETE FROM roles WHERE id = ?", 'i', [$role_id]);

        log_activity(
            (int)$_SESSION['user_id'],
            $_SESSION['role'],
            'Deleted custom role',
            'role',
            $role_id,
            "Deleted role '{$role['name']}'."
        );

        set_flash('success', "Custom role '{$role['name']}' was successfully deleted.");
        header('Location: roles.php');
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// DATA FETCHING FOR VIEW
// ═══════════════════════════════════════════════════════════════
$roles = db_fetch_all("SELECT * FROM roles ORDER BY is_system_role DESC, id ASC");

// Fetch assigned permissions per role
$rp_raw = db_fetch_all(
    "SELECT rp.role_id, rp.permission_id, p.module, p.action, p.label 
     FROM role_permissions rp
     JOIN permissions p ON rp.permission_id = p.id
     ORDER BY p.module ASC, p.id ASC"
);

$role_perms = [];
$role_perm_details = [];
foreach ($rp_raw as $r) {
    $role_perms[$r['role_id']][] = (int)$r['permission_id'];
    $role_perm_details[$r['role_id']][] = $r;
}

// Fetch user counts per role
$user_counts_raw = db_fetch_all("SELECT role_id, COUNT(*) AS total FROM users WHERE role_id IS NOT NULL GROUP BY role_id");
$user_counts = [];
foreach ($user_counts_raw as $uc) {
    $user_counts[(int)$uc['role_id']] = (int)$uc['total'];
}

$total_roles_count  = count($roles);
$system_roles_count = count(array_filter($roles, fn($r) => (int)$r['is_system_role'] === 1));
$custom_roles_count = $total_roles_count - $system_roles_count;
$total_perms_count  = count($all_permissions);

include_once __DIR__ . '/../layout.php';
?>

<div class="container-fluid px-0">

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-3 me-3">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Roles</div>
                        <h4 class="mb-0 fw-bold"><?= $total_roles_count ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-dark bg-opacity-10 text-dark p-3 me-3">
                        <i class="fas fa-lock fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">System Roles</div>
                        <h4 class="mb-0 fw-bold"><?= $system_roles_count ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 text-success p-3 me-3">
                        <i class="fas fa-user-tag fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Custom Roles</div>
                        <h4 class="mb-0 fw-bold"><?= $custom_roles_count ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 bg-info bg-opacity-10 text-info p-3 me-3">
                        <i class="fas fa-key fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Granular Permissions</div>
                        <h4 class="mb-0 fw-bold"><?= $total_perms_count ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="fas fa-user-shield text-primary me-2"></i>Roles &amp; Permission Matrix
                </h5>
                <small class="text-muted">Define custom access roles and configure fine-grained module privileges.</small>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                    <i class="fas fa-plus-circle me-1"></i> Add Custom Role
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 60px;">#ID</th>
                            <th>Role Name</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Permissions</th>
                            <th>Assigned Users</th>
                            <th>Created</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($roles as $r): ?>
                        <?php
                        $rid = (int)$r['id'];
                        $is_sys = (int)$r['is_system_role'] === 1;
                        $assigned_pids = $role_perms[$rid] ?? [];
                        $assigned_count = count($assigned_pids);
                        $assigned_users = $user_counts[$rid] ?? 0;
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold text-muted"><?= $rid ?></td>
                            <td>
                                <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                                    <span><?= h($r['name']) ?></span>
                                    <?php if ($r['name'] === 'Admin'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.7rem;">Super Admin</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small"><?= h($r['description'] ?: '—') ?></span>
                            </td>
                            <td>
                                <?php if ($is_sys): ?>
                                    <span class="badge bg-secondary px-2 py-1"><i class="fas fa-lock me-1"></i>System</span>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-user-edit me-1"></i>Custom</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                    <i class="fas fa-check-double me-1"></i><?= $assigned_count ?> / <?= $total_perms_count ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="fas fa-users text-muted me-1"></i><?= $assigned_users ?> user(s)
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= date('M d, Y', strtotime($r['created_at'])) ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <!-- Edit Role & Permissions -->
                                    <button type="button" class="btn btn-outline-primary"
                                            title="Configure Role & Permissions"
                                            onclick="openEditRoleModal(<?= htmlspecialchars(json_encode([
                                                'id' => $rid,
                                                'name' => $r['name'],
                                                'description' => $r['description'] ?? '',
                                                'is_system' => $is_sys,
                                                'perm_ids' => $assigned_pids
                                            ]), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fas fa-sliders-h me-1"></i> Edit Permissions
                                    </button>

                                    <!-- Delete Button (Only for custom roles) -->
                                    <?php if (!$is_sys): ?>
                                        <button type="button" class="btn btn-outline-danger btn-delete-role"
                                                title="<?= $assigned_users > 0 ? 'Click to view blocked deletion reason' : 'Delete Custom Role' ?>"
                                                data-role-id="<?= $rid ?>"
                                                data-role-name="<?= h($r['name']) ?>"
                                                data-user-count="<?= $assigned_users ?>"
                                                onclick="handleDeleteRoleClick(<?= $rid ?>, '<?= h(addslashes($r['name'])) ?>', <?= $assigned_users ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-secondary disabled" title="System roles cannot be deleted" disabled>
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: ADD CUSTOM ROLE                                          -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="roles.php" class="modal-content">
            <input type="hidden" name="action" value="create_role">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addRoleLabel">
                    <i class="fas fa-plus-circle me-2"></i>Create New Custom Role
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Librarian, Exam Officer, Accountant" required>
                        <small class="text-muted">Descriptive identifier for this permission set.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Access to view students and manage library records">
                        <small class="text-muted">Brief overview of responsibilities.</small>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-tasks text-primary me-2"></i>Module Permissions Grid</h6>
                        <small class="text-muted">Select the specific actions this role is authorized to perform.</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleAllCheckboxes('addRoleModal', true)">Select All</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleAllCheckboxes('addRoleModal', false)">Deselect All</button>
                    </div>
                </div>

                <!-- Permission Modules -->
                <div class="row g-3">
                    <?php foreach ($permissions_by_module as $mod => $perms): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border shadow-none bg-light bg-opacity-50">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-uppercase small text-primary">
                                        <i class="fas fa-folder me-1"></i><?= h(strtoupper($mod)) ?>
                                    </span>
                                    <div class="form-check form-check-inline m-0">
                                        <input class="form-check-input mod-select-all" type="checkbox" 
                                               id="mod_add_<?= h($mod) ?>"
                                               onchange="toggleModuleCheckboxes(this, 'add_mod_group_<?= h($mod) ?>')">
                                        <label class="form-check-label small text-muted" for="mod_add_<?= h($mod) ?>">All</label>
                                    </div>
                                </div>
                                <div class="card-body p-2 add_mod_group_<?= h($mod) ?>">
                                    <?php foreach ($perms as $p): ?>
                                        <div class="form-check py-1">
                                            <input class="form-check-input perm-chk" type="checkbox" name="permissions[]" value="<?= (int)$p['id'] ?>" id="perm_add_<?= (int)$p['id'] ?>">
                                            <label class="form-check-label small" for="perm_add_<?= (int)$p['id'] ?>">
                                                <?= h($p['label']) ?>
                                                <code class="text-muted ms-1" style="font-size: 0.72rem;"><?= h($p['module'] . '.' . $p['action']) ?></code>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i>Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: EDIT ROLE & PERMISSIONS                                  -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="roles.php" class="modal-content">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="role_id" id="editRoleId">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="editRoleLabel">
                    <i class="fas fa-sliders-h me-2 text-warning"></i>Edit Role &amp; Permissions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editRoleName" class="form-control" required>
                        <small id="editRoleNameNote" class="text-muted">Role identifier.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Description</label>
                        <input type="text" name="description" id="editRoleDescription" class="form-control">
                        <small class="text-muted">Role description &amp; scope.</small>
                    </div>
                </div>

                <div id="editAdminWarning" class="alert alert-warning py-2 mb-3 d-none">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important Safety Rule:</strong> The <code>users.manage</code> permission cannot be removed from the Admin role to prevent administrative lockout.
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-tasks text-primary me-2"></i>Assigned Module Permissions</h6>
                        <small class="text-muted">Check or uncheck individual module actions.</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleAllCheckboxes('editRoleModal', true)">Select All</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleAllCheckboxes('editRoleModal', false)">Deselect All</button>
                    </div>
                </div>

                <!-- Permission Modules -->
                <div class="row g-3">
                    <?php foreach ($permissions_by_module as $mod => $perms): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border shadow-none bg-light bg-opacity-50">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-uppercase small text-primary">
                                        <i class="fas fa-folder me-1"></i><?= h(strtoupper($mod)) ?>
                                    </span>
                                    <div class="form-check form-check-inline m-0">
                                        <input class="form-check-input mod-select-all" type="checkbox" 
                                               id="mod_edit_<?= h($mod) ?>"
                                               onchange="toggleModuleCheckboxes(this, 'edit_mod_group_<?= h($mod) ?>')">
                                        <label class="form-check-label small text-muted" for="mod_edit_<?= h($mod) ?>">All</label>
                                    </div>
                                </div>
                                <div class="card-body p-2 edit_mod_group_<?= h($mod) ?>">
                                    <?php foreach ($perms as $p): ?>
                                        <div class="form-check py-1">
                                            <input class="form-check-input perm-chk edit-perm-chk" type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?= (int)$p['id'] ?>" 
                                                   id="perm_edit_<?= (int)$p['id'] ?>"
                                                   data-code="<?= h($p['module'] . '.' . $p['action']) ?>">
                                            <label class="form-check-label small" for="perm_edit_<?= (int)$p['id'] ?>">
                                                <?= h($p['label']) ?>
                                                <code class="text-muted ms-1" style="font-size: 0.72rem;"><?= h($p['module'] . '.' . $p['action']) ?></code>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-check me-1"></i>Update Permissions</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: DELETE ROLE CONFIRMATION                                 -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="roles.php" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" id="modalDeleteRoleId" value="">
            
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold" id="deleteRoleModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Custom Role
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Case A: Users are assigned (Blocked) -->
                <div id="deleteRoleBlockedView" class="d-none">
                    <div class="alert alert-danger d-flex align-items-center mb-3">
                        <i class="fas fa-ban fa-2x me-3"></i>
                        <div>
                            <div class="fw-bold fs-6">Cannot Delete Role</div>
                            <div class="small">This role currently has assigned members.</div>
                        </div>
                    </div>
                    <p class="mb-3 text-muted">
                        The role <strong id="deleteRoleBlockedName" class="text-dark"></strong> cannot be deleted because <strong id="deleteRoleUserCount" class="text-danger"></strong> user account(s) are currently assigned to it.
                    </p>
                    <p class="small text-muted mb-0">
                        To delete this role, please first go to <a href="users.php" class="fw-semibold">User Management</a> and reassign or deactivate those accounts.
                    </p>
                </div>

                <!-- Case B: Zero users (Can delete) -->
                <div id="deleteRoleConfirmView">
                    <p class="fs-6 mb-3">
                        Are you sure you want to permanently delete the custom role <strong id="modalDeleteRoleNameDisplay" class="text-danger"></strong>?
                    </p>
                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-dark small mb-0">
                        <i class="fas fa-info-circle text-warning me-1"></i>
                        This action is irreversible and will remove all permission associations for this role.
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btnConfirmDeleteRole" class="btn btn-danger px-4 fw-semibold">
                    <i class="fas fa-trash-alt me-1"></i> Permanently Delete
                </button>
                <a href="users.php" id="btnGoToUsers" class="btn btn-outline-primary px-3 d-none">
                    <i class="fas fa-users-cog me-1"></i> Manage Users
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAllCheckboxes(modalId, checked) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    const chks = modal.querySelectorAll('.perm-chk');
    chks.forEach(chk => { chk.checked = checked; });
    const modChks = modal.querySelectorAll('.mod-select-all');
    modChks.forEach(chk => { chk.checked = checked; });
}

function toggleModuleCheckboxes(masterCheckbox, groupClass) {
    const group = document.querySelector('.' + groupClass);
    if (!group) return;
    const chks = group.querySelectorAll('.perm-chk');
    chks.forEach(chk => { chk.checked = masterCheckbox.checked; });
}

function openEditRoleModal(role) {
    document.getElementById('editRoleId').value = role.id;
    const nameInput = document.getElementById('editRoleName');
    nameInput.value = role.name;
    document.getElementById('editRoleDescription').value = role.description || '';

    const warningBox = document.getElementById('editAdminWarning');
    const note = document.getElementById('editRoleNameNote');

    if (role.is_system) {
        nameInput.setAttribute('readonly', 'readonly');
        nameInput.classList.add('bg-light');
        note.textContent = 'System role names are permanent and cannot be modified.';
    } else {
        nameInput.removeAttribute('readonly');
        nameInput.classList.remove('bg-light');
        note.textContent = 'Custom role identifier.';
    }

    if (role.name.toLowerCase() === 'admin') {
        warningBox.classList.remove('d-none');
    } else {
        warningBox.classList.add('d-none');
    }

    // Reset all checkboxes in edit modal
    const editChks = document.querySelectorAll('.edit-perm-chk');
    const permSet = new Set(role.perm_ids.map(Number));
    editChks.forEach(chk => {
        chk.checked = permSet.has(Number(chk.value));
    });

    // Update module 'All' checkboxes state
    document.querySelectorAll('#editRoleModal .mod-select-all').forEach(modChk => {
        const parentCard = modChk.closest('.card');
        if (parentCard) {
            const children = parentCard.querySelectorAll('.edit-perm-chk');
            const allChecked = Array.from(children).every(c => c.checked);
            modChk.checked = (children.length > 0 && allChecked);
        }
    });

    const modal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    modal.show();
}

function handleDeleteRoleClick(roleId, roleName, userCount) {
    document.getElementById('modalDeleteRoleId').value = roleId;

    const blockedView = document.getElementById('deleteRoleBlockedView');
    const confirmView = document.getElementById('deleteRoleConfirmView');
    const deleteBtn = document.getElementById('btnConfirmDeleteRole');
    const goToUsersBtn = document.getElementById('btnGoToUsers');

    if (userCount > 0) {
        document.getElementById('deleteRoleBlockedName').textContent = `"${roleName}"`;
        document.getElementById('deleteRoleUserCount').textContent = userCount;
        blockedView.classList.remove('d-none');
        confirmView.classList.add('d-none');
        deleteBtn.classList.add('d-none');
        goToUsersBtn.classList.remove('d-none');
    } else {
        document.getElementById('modalDeleteRoleNameDisplay').textContent = `"${roleName}"`;
        blockedView.classList.add('d-none');
        confirmView.classList.remove('d-none');
        deleteBtn.classList.remove('d-none');
        goToUsersBtn.classList.add('d-none');
    }

    const modal = new bootstrap.Modal(document.getElementById('deleteRoleModal'));
    modal.show();
}

// Global fallback for any delete button click
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-delete-role');
        if (!btn) return;
        const roleId = parseInt(btn.dataset.roleId || 0, 10);
        const roleName = btn.dataset.roleName || '';
        const userCount = parseInt(btn.dataset.userCount || 0, 10);
        if (roleId > 0) {
            handleDeleteRoleClick(roleId, roleName, userCount);
        }
    });
});
</script>

<?php
include_once __DIR__ . '/../layout_end.php';
?>
