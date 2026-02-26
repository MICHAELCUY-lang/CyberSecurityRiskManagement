<?php
/**
 * containers.php — OCTAVE Allegro Step 3: Asset Container Identification
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Asset Containers';
$currentPage = 'containers';
$db   = getAuditDB();
$user = currentUser();

$error = $success = '';

// Support both ?asset_id=X and ?audit_id=X
$selectedAssetId = (int)($_GET['asset_id'] ?? 0);
$selectedAuditId = (int)($_GET['audit_id'] ?? 0);

// Build asset list
if ($user['role'] === 'admin') {
    $assetList = $db->query("SELECT a.id, a.name, a.audit_id, au.system_name FROM assets a JOIN audits au ON au.id=a.audit_id ORDER BY au.created_at DESC, a.created_at DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT a.id, a.name, a.audit_id, au.system_name FROM assets a JOIN audits au ON au.id=a.audit_id WHERE au.auditor_id=? ORDER BY au.created_at DESC, a.created_at DESC");
    $stmt->execute([$user['id']]);
    $assetList = $stmt->fetchAll();
}
if (!$selectedAssetId && $assetList) $selectedAssetId = $assetList[0]['id'];

// Delete container
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_container'])) {
    $db->prepare("DELETE FROM asset_containers WHERE id=?")->execute([(int)$_POST['delete_container']]);
    header("Location: containers.php?asset_id=$selectedAssetId&deleted=1"); exit;
}

// Save container
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_container'])) {
    $assetId = (int)($_POST['asset_id'] ?? $selectedAssetId);
    $name    = trim($_POST['name'] ?? '');
    $type    = in_array($_POST['type'] ?? '', ['Technical','Physical','People']) ? $_POST['type'] : 'Technical';
    $loc     = in_array($_POST['location'] ?? '', ['Internal','External']) ? $_POST['location'] : 'Internal';
    $desc    = trim($_POST['description'] ?? '');

    if (!$name) { $error = 'Container name is required.'; }
    else {
        $db->prepare("INSERT INTO asset_containers (asset_id, type, location, name, description) VALUES (?,?,?,?,?)")
           ->execute([$assetId, $type, $loc, $name, $desc]);
        $success = "Container added.";
    }
}

// Load containers for selected asset
$containers = [];
if ($selectedAssetId) {
    $stmt = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM areas_of_concern ac WHERE ac.container_id=c.id) AS concern_count FROM asset_containers c WHERE c.asset_id=? ORDER BY c.type, c.created_at");
    $stmt->execute([$selectedAssetId]);
    $containers = $stmt->fetchAll();
}

// Current asset info
$currentAsset = null;
if ($selectedAssetId) {
    $stmt = $db->prepare("SELECT a.*, au.system_name FROM assets a JOIN audits au ON au.id=a.audit_id WHERE a.id=?");
    $stmt->execute([$selectedAssetId]);
    $currentAsset = $stmt->fetch();
}

$typeIcons = ['Technical'=>'💻','Physical'=>'🏢','People'=>'👤'];
$locColors = ['Internal'=>'#22c55e','External'=>'#dc2626'];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Asset Container Identification</h1>
        <span class="breadcrumb">OCTAVE Allegro — Step 3 of 8</span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>
        <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Container deleted.</div><?php endif ?>

        <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:16px;">
            <div style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4a8cff;margin-bottom:8px;">▣ OCTAVE Allegro — Step 3</div>
            <p style="font-size:13px;line-height:1.7;color:var(--text-muted);margin:0;">
                For each information asset, identify all <strong style="color:var(--text);">containers</strong> — the locations where the asset is stored, transported, or processed.
                Three types: <strong style="color:#4a8cff;">Technical</strong> (servers, apps, databases),
                <strong style="color:#ffdd55;">Physical</strong> (offices, media, data centers),
                <strong style="color:#22c55e;">People</strong> (employees, vendors, contractors with access).
            </p>
        </div>

        <!-- Asset selector -->
        <form method="GET" style="margin-bottom:16px;display:flex;gap:10px;align-items:center;">
            <label style="font-size:12px;color:var(--text-muted);">Asset:</label>
            <select name="asset_id" onchange="this.form.submit()" style="width:380px;">
                <?php foreach ($assetList as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $selectedAssetId == $a['id'] ? 'selected' : '' ?>>
                    [<?= htmlspecialchars($a['system_name']) ?>] <?= htmlspecialchars($a['name']) ?>
                </option>
                <?php endforeach ?>
            </select>
        </form>

        <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        <!-- Add container form -->
        <div class="card">
            <div class="card-title">Add Container
                <?php if ($currentAsset): ?>
                <span style="font-size:11px;font-weight:400;color:var(--text-dim);margin-left:8px;">
                    for: <?= htmlspecialchars($currentAsset['name']) ?>
                </span>
                <?php endif ?>
            </div>
            <form method="POST">
                <input type="hidden" name="save_container" value="1">
                <input type="hidden" name="asset_id" value="<?= $selectedAssetId ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Container Type *</label>
                        <select name="type" id="ctype" onchange="updateTypeHint()">
                            <option value="Technical">💻 Technical</option>
                            <option value="Physical">🏢 Physical</option>
                            <option value="People">👤 People</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Location *</label>
                        <select name="location">
                            <option value="Internal">Internal (org-controlled)</option>
                            <option value="External">External (third-party/cloud)</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Container Name *
                            <span id="type_hint" style="font-size:10px;color:var(--text-dim);font-weight:400;"> — e.g. Web Application Server, Cloud Storage Bucket</span>
                        </label>
                        <input type="text" name="name" required placeholder="Name this container specifically">
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Describe the container's role in storing or processing this asset..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn" style="margin-top:8px;">▣ Add Container</button>
            </form>
        </div>

        <!-- Container list -->
        <div>
        <div class="card" style="position:sticky;top:20px;">
            <div class="card-title">Containers (<?= count($containers) ?>)</div>
            <?php if (empty($containers)): ?>
                <p class="text-muted" style="font-size:12px;">No containers mapped yet for this asset.</p>
            <?php else: ?>
            <?php $grouped = array_fill_keys(['Technical','Physical','People'], []);
                  foreach ($containers as $c) $grouped[$c['type']][] = $c; ?>
            <?php foreach ($grouped as $type => $list): if (!$list) continue; ?>
            <div style="margin-bottom:12px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:6px;">
                    <?= $typeIcons[$type] ?> <?= $type ?>
                </div>
                <?php foreach ($list as $c): ?>
                <div style="padding:10px 12px;background:var(--bg-elevated);border-radius:3px;margin-bottom:6px;">
                    <div style="display:flex;justify-content:space-between;align-items:start;">
                        <div>
                            <div style="font-size:12px;font-weight:600;"><?= htmlspecialchars($c['name']) ?></div>
                            <div style="display:flex;gap:8px;margin-top:4px;">
                                <span style="font-size:10px;color:<?= $locColors[$c['location']] ?>;">●&nbsp;<?= $c['location'] ?></span>
                                <span style="font-size:10px;color:var(--text-dim);"><?= $c['concern_count'] ?> concern<?= $c['concern_count'] != 1 ? 's' : '' ?></span>
                            </div>
                        </div>
                        <div style="display:flex;gap:4px;flex-shrink:0;">
                            <a href="concerns.php?container_id=<?= $c['id'] ?>"
                               class="btn btn-ghost" style="font-size:9px;padding:2px 8px;">Concerns →</a>
                            <form method="POST" onsubmit="return confirm('Delete container?')" style="display:inline;">
                                <input type="hidden" name="delete_container" value="<?= $c['id'] ?>">
                                <button class="btn btn-danger" style="font-size:9px;padding:2px 8px;">✕</button>
                            </form>
                        </div>
                    </div>
                    <?php if ($c['description']): ?>
                    <div style="font-size:11px;color:var(--text-dim);margin-top:4px;"><?= htmlspecialchars($c['description']) ?></div>
                    <?php endif ?>
                </div>
                <?php endforeach ?>
            </div>
            <?php endforeach ?>
            <?php endif ?>

            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:8px;">
                <a href="assets.php<?= $currentAsset ? '?audit_id='.$currentAsset['audit_id'] : '' ?>"
                   class="btn btn-ghost" style="flex:1;text-align:center;font-size:10px;">← Step 2</a>
                <a href="concerns.php<?= $selectedAssetId ? '?asset_id='.$selectedAssetId : '' ?>"
                   class="btn" style="flex:1;text-align:center;font-size:10px;">Step 4: Concerns →</a>
            </div>
        </div>
        </div>

        </div><!-- /grid -->
    </div>
</div>

<script>
const typeHints = {
    Technical: 'e.g. Web Application Server, Cloud Storage Bucket, Database Server',
    Physical:  'e.g. Data Center, Laptop, External Hard Drive, Filing Cabinet',
    People:    'e.g. Database Administrator, Third-party Vendor, Support Team'
};
function updateTypeHint() {
    const t = document.getElementById('ctype').value;
    document.getElementById('type_hint').textContent = ' — ' + typeHints[t];
}
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
