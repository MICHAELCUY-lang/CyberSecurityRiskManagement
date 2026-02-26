<?php
/**
 * new_audit.php — Module 1: Create a New Audit Entry
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'New Audit';
$currentPage = 'new_audit';

$db   = getAuditDB();
$user = currentUser();
$error = '';

$orgs = $db->query("SELECT id, name FROM organizations ORDER BY name")->fetchAll();
$auditees = $db->query("SELECT id, name FROM users WHERE role='auditee' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $systemName  = trim($_POST['system_name']  ?? '');
    $description = trim($_POST['description']  ?? '');
    $auditDate   = trim($_POST['audit_date']   ?? '');
    $orgId       = (int)($_POST['organization_id'] ?? 0);
    $auditeeId   = (int)($_POST['auditee_id'] ?? 0);

    if (!$systemName || !$auditDate) {
        $error = 'System name and audit date are required.';
    } else {
        $stmt = $db->prepare("INSERT INTO audits (organization_id, system_name, description, audit_date, auditor_id, auditee_id) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$orgId ?: null, $systemName, $description, $auditDate, $user['id'], $auditeeId ?: null]);
        $auditId = (int)$db->lastInsertId();
        header("Location: checklist.php?audit_id=$auditId");
        exit;
    }
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>New Security Audit</h1>
        <span class="breadcrumb">Audit / Create</span>
    </div>
    <div class="content-area">

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif ?>

        <div class="card" style="max-width:680px;">
            <div class="card-title">Audit Information</div>
            <p class="text-muted" style="font-size:12px;margin-bottom:20px;">
                Fill in the basic details about the system being audited.
                You will complete the security checklist in the next step.
            </p>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="organization_id">Organization</label>
                        <select id="organization_id" name="organization_id">
                            <option value="">-- Default / None --</option>
                            <?php foreach ($orgs as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label for="system_name">System Name *</label>
                        <input type="text" id="system_name" name="system_name" required
                            placeholder="e.g. Core Banking Application"
                            value="<?= htmlspecialchars($_POST['system_name'] ?? '') ?>">
                    </div>
                    <div class="form-group full">
                        <label for="description">System Description</label>
                        <textarea id="description" name="description" rows="4"
                            placeholder="Describe the system, its purpose, scope of this audit, and any relevant context..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="audit_date">Audit Date *</label>
                        <input type="date" id="audit_date" name="audit_date" required
                            value="<?= htmlspecialchars($_POST['audit_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Auditor</label>
                        <input type="text" value="<?= htmlspecialchars($user['name']) ?>" disabled
                            style="opacity:.6;cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="auditee_id">Assigned Auditee</label>
                        <select id="auditee_id" name="auditee_id">
                            <option value="">-- None --</option>
                            <?php foreach ($auditees as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top:24px;display:flex;gap:12px;">
                    <button type="submit" class="btn">Continue to Checklist →</button>
                    <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Process Steps Indicator -->
        <div class="card" style="max-width:680px;">
            <div class="card-title">Audit Workflow</div>
            <div style="display:flex;gap:0;align-items:stretch;">
                <?php
                $steps = [
                    ['label'=>'1. System Info', 'active'=>true,  'done'=>false],
                    ['label'=>'2. Checklist',   'active'=>false, 'done'=>false],
                    ['label'=>'3. Risk & Score', 'active'=>false, 'done'=>false],
                    ['label'=>'4. Report',       'active'=>false, 'done'=>false],
                ];
                foreach ($steps as $i => $step):
                    $bg = $step['active'] ? '#fff' : 'var(--bg-elevated)';
                    $fg = $step['active'] ? '#000' : 'var(--text-muted)';
                ?>
                <div style="flex:1;background:<?= $bg ?>;color:<?= $fg ?>;
                    padding:10px 14px;font-size:11px;font-weight:700;letter-spacing:.05em;
                    text-align:center;border:1px solid var(--border);
                    <?= $i > 0 ? 'border-left:none;' : '' ?>">
                    <?= $step['label'] ?>
                </div>
                <?php endforeach ?>
            </div>
        </div>

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include __DIR__ . '/partials/footer.php'; ?>
