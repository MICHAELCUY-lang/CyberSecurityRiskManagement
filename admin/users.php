<?php
/**
 * admin/users.php — Admin: User Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_new.php';
require_once __DIR__ . '/../auth.php';
requireRole('admin');

$pageTitle   = 'Manage Users';
$currentPage = 'admin_users';

$db   = getAuditDB();
$user = currentUser();

$error   = '';
$success = '';

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = (int)$_POST['delete_user'];
    if ($uid === $user['id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        $success = 'User deleted.';
    }
}

// Change role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $uid     = (int)$_POST['change_role'];
    $newRole = in_array($_POST['new_role'] ?? '', ['admin','auditor']) ? $_POST['new_role'] : 'auditor';
    $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $uid]);
    $success = 'Role updated.';
}

// Load all users with audit count
$users = $db->query("
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           (SELECT COUNT(*) FROM audits a WHERE a.auditor_id = u.id) AS audit_count
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Manage Users</h1>
        <span class="breadcrumb">Admin Panel</span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>

        <!-- User Stats -->
        <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:20px;">
            <?php
            $admins   = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
            $auditors = count(array_filter($users, fn($u) => $u['role'] === 'auditor'));
            ?>
            <div class="kpi-card">
                <div class="kpi-label">Total Users</div>
                <div class="kpi-value"><?= count($users) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Admins</div>
                <div class="kpi-value"><?= $admins ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Auditors</div>
                <div class="kpi-value safe"><?= $auditors ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><?= count($users) ?> Registered Users</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Audits</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr <?= $u['id'] == $user['id'] ? 'style="background:var(--bg-elevated)"' : '' ?>>
                            <td class="text-muted font-mono"><?= $u['id'] ?></td>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($u['name']) ?>
                                <?php if ($u['id'] == $user['id']): ?>
                                    <span style="font-size:10px;color:var(--text-dim);margin-left:6px;">(you)</span>
                                <?php endif ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge <?= $u['role'] === 'admin' ? 'badge-compliant' : 'badge-partial' ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td class="font-mono text-muted"><?= $u['audit_count'] ?></td>
                            <td class="text-muted" style="font-size:12px;"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ($u['id'] != $user['id']): ?>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <!-- Change role -->
                                    <form method="POST" style="display:flex;gap:6px;">
                                        <input type="hidden" name="change_role" value="<?= $u['id'] ?>">
                                        <select name="new_role" style="width:100px;font-size:11px;padding:4px 8px;">
                                            <option value="auditor" <?= $u['role'] === 'auditor' ? 'selected' : '' ?>>Auditor</option>
                                            <option value="admin"   <?= $u['role'] === 'admin'   ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" class="btn btn-ghost"
                                                style="font-size:10px;padding:3px 10px;">Save</button>
                                    </form>
                                    <!-- Delete -->
                                    <form method="POST" onsubmit="return confirm('Delete this user and all their audits?')">
                                        <input type="hidden" name="delete_user" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-danger"
                                                style="font-size:10px;padding:3px 10px;">Delete</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <span class="text-muted" style="font-size:11px;">— current account —</span>
                                <?php endif ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add User Form -->
        <div class="card" style="max-width:600px;">
            <div class="card-title">Add New User</div>
            <?php
            // Handle add user
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
                $name     = trim($_POST['new_name'] ?? '');
                $email    = trim($_POST['new_email'] ?? '');
                $password = $_POST['new_pass'] ?? '';
                $role     = in_array($_POST['new_role_add'] ?? '', ['admin','auditor']) ? $_POST['new_role_add'] : 'auditor';

                if (!$name || !$email || !$password) {
                    echo '<div class="alert alert-error">All fields required.</div>';
                } elseif (strlen($password) < 6) {
                    echo '<div class="alert alert-error">Password must be at least 6 characters.</div>';
                } else {
                    $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $chk->execute([$email]);
                    if ($chk->fetch()) {
                        echo '<div class="alert alert-error">Email already registered.</div>';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
                           ->execute([$name, $email, $hash, $role]);
                        echo '<div class="alert alert-success">User created successfully.</div>';
                    }
                }
            }
            ?>
            <form method="POST">
                <input type="hidden" name="add_user" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="new_name" required placeholder="Jane Doe">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="new_email" required placeholder="jane@company.com">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="new_role_add">
                            <option value="auditor">Auditor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="new_pass" required placeholder="Min 6 characters">
                    </div>
                </div>
                <button type="submit" class="btn" style="margin-top:16px;">Add User</button>
            </form>
        </div>

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include __DIR__ . '/../partials/footer.php'; ?>
