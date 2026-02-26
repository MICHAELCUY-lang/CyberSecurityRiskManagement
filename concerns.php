<?php
/**
 * concerns.php — OCTAVE Allegro Step 4: Areas of Concern
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Areas of Concern';
$currentPage = 'concerns';
$db   = getAuditDB();
$user = currentUser();

$error = $success = '';
$selectedContainerId = (int)($_GET['container_id'] ?? 0);
$selectedAssetId     = (int)($_GET['asset_id'] ?? 0);

// Build container list accessible by this user
if ($user['role'] === 'admin') {
    $containerList = $db->query("
        SELECT c.id, c.name, c.type, a.id AS asset_id, a.name AS asset_name, au.system_name
        FROM asset_containers c
        JOIN assets a ON a.id=c.asset_id
        JOIN audits au ON au.id=a.audit_id
        ORDER BY au.created_at DESC, a.created_at DESC, c.type, c.name
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT c.id, c.name, c.type, a.id AS asset_id, a.name AS asset_name, au.system_name
        FROM asset_containers c
        JOIN assets a ON a.id=c.asset_id
        JOIN audits au ON au.id=a.audit_id
        WHERE au.auditor_id=?
        ORDER BY au.created_at DESC, a.created_at DESC, c.type, c.name
    ");
    $stmt->execute([$user['id']]);
    $containerList = $stmt->fetchAll();
}

// Filter by asset_id if specified
if ($selectedAssetId) {
    $containerList = array_filter($containerList, fn($c) => $c['asset_id'] == $selectedAssetId);
    $containerList = array_values($containerList);
}
if (!$selectedContainerId && $containerList) $selectedContainerId = $containerList[0]['id'];

// Delete concern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_concern'])) {
    $db->prepare("DELETE FROM areas_of_concern WHERE id=?")->execute([(int)$_POST['delete_concern']]);
    header("Location: concerns.php?container_id=$selectedContainerId&deleted=1"); exit;
}

// Save concern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_concern'])) {
    $cid  = (int)($_POST['container_id'] ?? $selectedContainerId);
    $desc = trim($_POST['description'] ?? '');
    if (!$desc) { $error = 'Description is required.'; }
    else {
        $db->prepare("INSERT INTO areas_of_concern (container_id, description) VALUES (?,?)")
           ->execute([$cid, $desc]);
        $success = 'Area of concern added.';
    }
}

// Load concerns
$concerns = [];
if ($selectedContainerId) {
    $stmt = $db->prepare("
        SELECT ac.*, (SELECT COUNT(*) FROM threat_scenarios ts WHERE ts.concern_id=ac.id) AS scenario_count
        FROM areas_of_concern ac WHERE ac.container_id=? ORDER BY ac.created_at
    ");
    $stmt->execute([$selectedContainerId]);
    $concerns = $stmt->fetchAll();
}

// Current container info
$currentContainer = null;
foreach ($containerList as $c) {
    if ($c['id'] == $selectedContainerId) { $currentContainer = $c; break; }
}

$typeColors = ['Technical'=>'#4a8cff','Physical'=>'#ffdd55','People'=>'#22c55e'];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Areas of Concern</h1>
        <span class="breadcrumb">OCTAVE Allegro — Step 4 of 8</span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>
        <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Concern removed.</div><?php endif ?>

        <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:16px;">
            <div style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4a8cff;margin-bottom:8px;">⚠ OCTAVE Allegro — Step 4</div>
            <p style="font-size:13px;line-height:1.7;color:var(--text-muted);margin:0;">
                For each container, document <strong style="color:var(--text);">areas of concern</strong> — 
                specific security worries that practitioners identify from their knowledge of the container's environment.
                Express these in plain language: <em>"If [condition], then [consequence] could happen."</em>
                Each concern will produce one or more threat scenarios in Step 5.
            </p>
        </div>

        <!-- Container selector -->
        <form method="GET" style="margin-bottom:16px;display:flex;gap:10px;align-items:center;">
            <label style="font-size:12px;color:var(--text-muted);">Container:</label>
            <select name="container_id" onchange="this.form.submit()" style="width:500px;">
                <?php foreach ($containerList as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $selectedContainerId == $c['id'] ? 'selected' : '' ?>>
                    [<?= htmlspecialchars($c['system_name']) ?> › <?= htmlspecialchars($c['asset_name']) ?>]
                    <?= $c['type'] ?>: <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach ?>
            </select>
        </form>

        <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        <!-- Add concern -->
        <div class="card">
            <div class="card-title">
                Add Area of Concern
                <?php if ($currentContainer): ?>
                <span style="font-size:11px;font-weight:400;color:var(--text-dim);margin-left:8px;">
                    &nbsp;for:
                    <span style="color:<?= $typeColors[$currentContainer['type']] ?? '#fff' ?>;">
                        <?= $currentContainer['type'] ?>
                    </span>
                    — <?= htmlspecialchars($currentContainer['name']) ?>
                </span>
                <?php endif ?>
            </div>

            <div style="margin-bottom:16px;padding:12px;background:var(--bg-elevated);border-radius:3px;font-size:12px;color:var(--text-muted);">
                <strong style="color:var(--text);">Examples of areas of concern:</strong>
                <ul style="margin:8px 0 0;padding-left:16px;line-height:2;">
                    <li>If this server is compromised via an unpatched vulnerability, customer data could be disclosed</li>
                    <li>If a disgruntled employee copies data to a USB drive, confidential records could be stolen</li>
                    <li>If the cloud provider suffers an outage, operations could be interrupted</li>
                </ul>
            </div>

            <form method="POST">
                <input type="hidden" name="save_concern" value="1">
                <input type="hidden" name="container_id" value="<?= $selectedContainerId ?>">
                <div class="form-group">
                    <label>Area of Concern Description *</label>
                    <textarea name="description" rows="4" required
                        placeholder="Describe a specific security concern for this container in plain language..."></textarea>
                </div>
                <button type="submit" class="btn">⚠ Add Concern</button>
            </form>
        </div>

        <!-- Concern list -->
        <div>
        <div class="card" style="position:sticky;top:20px;">
            <div class="card-title">Concerns for this Container (<?= count($concerns) ?>)</div>
            <?php if (empty($concerns)): ?>
                <p class="text-muted" style="font-size:12px;">No concerns documented yet for this container.</p>
            <?php else: ?>
            <?php foreach ($concerns as $i => $c): ?>
            <div style="padding:12px;background:var(--bg-elevated);border-radius:3px;margin-bottom:8px;
                        border-left:3px solid #ffdd55;">
                <div style="font-size:11px;color:#ffdd55;font-weight:700;margin-bottom:6px;">
                    Concern #<?= $i+1 ?>
                    <span style="font-weight:400;color:var(--text-dim);margin-left:8px;">
                        <?= $c['scenario_count'] ?> scenario<?= $c['scenario_count'] != 1 ? 's' : '' ?>
                    </span>
                </div>
                <div style="font-size:12px;line-height:1.6;color:var(--text);margin-bottom:8px;">
                    <?= htmlspecialchars($c['description']) ?>
                </div>
                <div style="display:flex;gap:6px;">
                    <a href="threat_scenarios.php?concern_id=<?= $c['id'] ?>"
                       class="btn btn-ghost" style="font-size:10px;padding:3px 10px;">Scenarios →</a>
                    <form method="POST" onsubmit="return confirm('Remove this concern?')" style="margin-left:auto;">
                        <input type="hidden" name="delete_concern" value="<?= $c['id'] ?>">
                        <button class="btn btn-danger" style="font-size:10px;padding:3px 10px;">Del</button>
                    </form>
                </div>
            </div>
            <?php endforeach ?>
            <?php endif ?>

            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:8px;">
                <a href="containers.php" class="btn btn-ghost" style="flex:1;text-align:center;font-size:10px;">← Step 3</a>
                <a href="threat_scenarios.php" class="btn" style="flex:1;text-align:center;font-size:10px;">Step 5: Threats →</a>
            </div>
        </div>
        </div>

        </div><!-- /grid -->
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
