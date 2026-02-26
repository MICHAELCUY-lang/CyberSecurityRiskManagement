<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$pageTitle   = 'Organization';
$currentPage = 'organization';
$db = getDB();
$message = '';
$error   = '';

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name     = trim($_POST['name'] ?? '');
        $sector   = trim($_POST['sector'] ?? '');
        $empCount = (int)($_POST['employee_count'] ?? 0);
        $sysType  = trim($_POST['system_type'] ?? '');
        $exposure = $_POST['exposure_level'] ?? 'Low';

        $validExposure = ['Low','Medium','High','Critical'];
        if (!in_array($exposure, $validExposure)) $exposure = 'Low';

        if (empty($name) || empty($sector)) {
            $error = 'Organization name and sector are required.';
        } else {
            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO organizations (name, sector, employee_count, system_type, exposure_level) VALUES (?,?,?,?,?)");
                $stmt->execute([$name, $sector, $empCount, $sysType, $exposure]);
                $newId = $db->lastInsertId();
                $_SESSION['active_org'] = $newId;
                $message = 'Organization created and set as active.';
            } else {
                $editId = (int)($_POST['edit_id'] ?? 0);
                $stmt = $db->prepare("UPDATE organizations SET name=?, sector=?, employee_count=?, system_type=?, exposure_level=? WHERE id=?");
                $stmt->execute([$name, $sector, $empCount, $sysType, $exposure, $editId]);
                $message = 'Organization updated.';
            }
        }
    }

    if ($action === 'set_active') {
        $orgId = (int)($_POST['org_id'] ?? 0);
        $_SESSION['active_org'] = $orgId;
        $message = 'Active organization updated.';
    }

    if ($action === 'delete') {
        $delId = (int)($_POST['del_id'] ?? 0);
        $db->prepare("DELETE FROM organizations WHERE id=?")->execute([$delId]);
        if ($_SESSION['active_org'] == $delId) unset($_SESSION['active_org']);
        $message = 'Organization deleted.';
    }
}

// --- Fetch edit target ---
$editOrg = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM organizations WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editOrg = $stmt->fetch();
}

// --- Fetch all orgs ---
$orgs = $db->query("SELECT * FROM organizations ORDER BY created_at DESC")->fetchAll();
$activeOrg = (int)($_SESSION['active_org'] ?? 0);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Organization Profile</h1>
        <span class="breadcrumb">OCTAVE Allegro / Organization</span>
    </div>
    <div class="content-area">

        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Form -->
        <div class="card">
            <div class="card-title"><?= $editOrg ? 'Edit Organization' : 'Register Organization' ?></div>
            <form method="POST" action="organization.php">
                <?php if ($editOrg): ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="edit_id" value="<?= $editOrg['id'] ?>">
                <?php else: ?>
                <input type="hidden" name="action" value="create">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Organization Name</label>
                        <input type="text" id="name" name="name" required
                               value="<?= htmlspecialchars($editOrg['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="sector">Sector / Industry</label>
                        <input type="text" id="sector" name="sector" required
                               placeholder="e.g. Finance, Healthcare, Education"
                               value="<?= htmlspecialchars($editOrg['sector'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="employee_count">Employee Count</label>
                        <input type="number" id="employee_count" name="employee_count" min="0"
                               value="<?= htmlspecialchars((string)($editOrg['employee_count'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="system_type">Primary System Type</label>
                        <input type="text" id="system_type" name="system_type"
                               placeholder="e.g. ERP, Web Application, Database"
                               value="<?= htmlspecialchars($editOrg['system_type'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="exposure_level">Exposure Level</label>
                        <select id="exposure_level" name="exposure_level">
                            <?php foreach (['Low','Medium','High','Critical'] as $lvl): ?>
                            <option value="<?= $lvl ?>"
                                <?= ($editOrg['exposure_level'] ?? 'Low') === $lvl ? 'selected' : '' ?>>
                                <?= $lvl ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="mt-2 flex gap-2">
                    <button type="submit" class="btn"><?= $editOrg ? 'Update Organization' : 'Create Organization' ?></button>
                    <?php if ($editOrg): ?>
                    <a href="organization.php" class="btn btn-ghost">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Organizations Table -->
        <?php if (!empty($orgs)): ?>
        <div class="card">
            <div class="card-title">Registered Organizations</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Sector</th>
                            <th>Employees</th>
                            <th>System Type</th>
                            <th>Exposure</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orgs as $org): ?>
                        <tr <?= $org['id'] == $activeOrg ? 'style="border-left:3px solid #fff;"' : '' ?>>
                            <td class="font-mono text-muted"><?= $org['id'] ?></td>
                            <td>
                                <?= htmlspecialchars($org['name']) ?>
                                <?php if ($org['id'] == $activeOrg): ?>
                                <span class="badge badge-compliant" style="margin-left:6px;">Active</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($org['sector']) ?></td>
                            <td class="font-mono"><?= number_format((int)$org['employee_count']) ?></td>
                            <td><?= htmlspecialchars($org['system_type'] ?? '-') ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($org['exposure_level']) ?>">
                                    <?= $org['exposure_level'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <?php if ($org['id'] != $activeOrg): ?>
                                    <form method="POST" action="organization.php" style="display:inline;">
                                        <input type="hidden" name="action" value="set_active">
                                        <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                        <button type="submit" class="btn btn-ghost" style="font-size:11px;padding:4px 10px;">Set Active</button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="organization.php?edit=<?= $org['id'] ?>" class="btn btn-ghost" style="font-size:11px;padding:4px 10px;">Edit</a>
                                    <form method="POST" action="organization.php" style="display:inline;"
                                          onsubmit="return confirm('Delete this organization and all associated data?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="del_id" value="<?= $org['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="font-size:11px;padding:4px 10px;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
