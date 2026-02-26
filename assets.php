<?php
/**
 * assets.php — OCTAVE Allegro Step 2: Information Asset Profiles
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Information Asset Profiles';
$currentPage = 'assets';
$db   = getAuditDB();
$user = currentUser();

$error = $success = '';
$selectedAuditId = (int)($_GET['audit_id'] ?? 0);

// Audit list
if ($user['role'] === 'admin') {
    $auditList = $db->query("SELECT id, system_name FROM audits ORDER BY created_at DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, system_name FROM audits WHERE auditor_id=? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $auditList = $stmt->fetchAll();
}
if (!$selectedAuditId && $auditList) $selectedAuditId = $auditList[0]['id'];

// Delete asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_asset'])) {
    $db->prepare("DELETE FROM assets WHERE id=?")->execute([(int)$_POST['delete_asset']]);
    header("Location: assets.php?audit_id=$selectedAuditId&deleted=1"); exit;
}

// Save new/edit asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_asset'])) {
    $auditId   = (int)($_POST['audit_id'] ?? $selectedAuditId);
    $assetId   = (int)($_POST['asset_id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $owner     = trim($_POST['owner_name'] ?? '');
    $rationale = trim($_POST['rationale'] ?? '');
    $confid    = max(1, min(5, (int)($_POST['cia_confidentiality'] ?? 3)));
    $integr    = max(1, min(5, (int)($_POST['cia_integrity'] ?? 3)));
    $avail     = max(1, min(5, (int)($_POST['cia_availability'] ?? 3)));
    $primary   = in_array($_POST['primary_req'] ?? 'C', ['C','I','A']) ? $_POST['primary_req'] : 'C';

    $critScore = $confid + $integr + $avail;
    $critLevel = $critScore >= 13 ? 'Critical' : ($critScore >= 10 ? 'High' : ($critScore >= 6 ? 'Medium' : 'Low'));

    if (!$name) { $error = 'Asset name is required.'; }
    else {
        if ($assetId) {
            $db->prepare("UPDATE assets SET name=?,description=?,owner_name=?,rationale=?,cia_confidentiality=?,cia_integrity=?,cia_availability=?,primary_req=?,criticality_score=?,criticality_level=? WHERE id=?")
               ->execute([$name,$desc,$owner,$rationale,$confid,$integr,$avail,$primary,$critScore,$critLevel,$assetId]);
        } else {
            $db->prepare("INSERT INTO assets (audit_id,name,description,owner_name,rationale,cia_confidentiality,cia_integrity,cia_availability,primary_req,criticality_score,criticality_level) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$auditId,$name,$desc,$owner,$rationale,$confid,$integr,$avail,$primary,$critScore,$critLevel]);
        }
        $success = 'Asset profile saved.';
    }
}

// Load assets for selected audit
$assets = [];
if ($selectedAuditId) {
    $stmt = $db->prepare("
        SELECT a.*, 
               (SELECT COUNT(*) FROM asset_containers c WHERE c.asset_id = a.id) AS container_count
        FROM assets a WHERE a.audit_id=? ORDER BY a.created_at DESC");
    $stmt->execute([$selectedAuditId]);
    $assets = $stmt->fetchAll();
}

// Edit mode
$editAsset = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM assets WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editAsset = $stmt->fetch();
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Information Asset Profiles</h1>
        <span class="breadcrumb">OCTAVE Allegro — Step 2 of 8</span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>
        <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Asset deleted.</div><?php endif ?>

        <!-- Step explanation -->
        <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:16px;">
            <div style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4a8cff;margin-bottom:8px;">◈ OCTAVE Allegro — Step 2</div>
            <p style="font-size:13px;line-height:1.7;color:var(--text-muted);margin:0;">
                Identify and profile all <strong style="color:var(--text);">critical information assets</strong> — the specific data this system processes, not the system itself.
                Each asset must document: what it is, who owns it, why it is critical, and its CIA security requirements.
            </p>
        </div>

        <!-- Audit selector -->
        <div style="margin-bottom:16px;">
            <form method="GET" style="display:flex;gap:10px;align-items:center;">
                <label style="font-size:12px;color:var(--text-muted);">Audit:</label>
                <select name="audit_id" onchange="this.form.submit()" style="width:300px;">
                    <?php foreach ($auditList as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $selectedAuditId == $a['id'] ? 'selected' : '' ?>>
                        #<?= $a['id'] ?> — <?= htmlspecialchars($a['system_name']) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </form>
        </div>

        <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        <!-- Left: Asset form -->
        <div>
        <div class="card">
            <div class="card-title"><?= $editAsset ? 'Edit Asset' : 'Add Information Asset' ?></div>
            <form method="POST">
                <input type="hidden" name="save_asset" value="1">
                <input type="hidden" name="audit_id" value="<?= $selectedAuditId ?>">
                <?php if ($editAsset): ?><input type="hidden" name="asset_id" value="<?= $editAsset['id'] ?>"><?php endif ?>

                <div class="form-grid">
                    <div class="form-group full">
                        <label>Asset Name *
                            <span class="text-muted" style="font-size:10px;font-weight:400;">(e.g. "Customer PII Database", "Financial Transaction Records")</span>
                        </label>
                        <input type="text" name="name" required placeholder="Name the information asset — not the system"
                               value="<?= htmlspecialchars($editAsset['name'] ?? '') ?>">
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Briefly describe what this asset is..."><?= htmlspecialchars($editAsset['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Asset Owner <span class="text-muted" style="font-size:10px;">(business owner, not IT)</span></label>
                        <input type="text" name="owner_name" placeholder="e.g. Chief Financial Officer"
                               value="<?= htmlspecialchars($editAsset['owner_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Primary Security Requirement</label>
                        <select name="primary_req">
                            <?php foreach (['C'=>'Confidentiality','I'=>'Integrity','A'=>'Availability'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($editAsset['primary_req'] ?? 'C') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Asset Rationale <span class="text-muted" style="font-size:10px;">(Why is this asset critical to the organization?)</span></label>
                        <textarea name="rationale" rows="2" placeholder="e.g. This data is required for regulatory compliance and business continuity..."><?= htmlspecialchars($editAsset['rationale'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- CIA Requirements -->
                <div style="margin-top:16px;padding:16px;background:var(--bg-elevated);border-radius:3px;">
                    <div style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:12px;">CIA Security Requirements (1-5)</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <?php foreach (['cia_confidentiality'=>['C','Confidentiality','#4a8cff'],'cia_integrity'=>['I','Integrity','#22c55e'],'cia_availability'=>['A','Availability','#ffdd55']] as $field=>[$code,$label,$color]): ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                <label style="font-size:12px;"><span style="color:<?= $color ?>;font-weight:800;"><?= $code ?></span> <?= $label ?></label>
                                <span class="font-mono" style="color:<?= $color ?>;" id="cia_<?= $code ?>"><?= $editAsset[$field] ?? 3 ?></span>
                            </div>
                            <input type="range" name="<?= $field ?>" min="1" max="5" step="1"
                                   value="<?= $editAsset[$field] ?? 3 ?>"
                                   oninput="document.getElementById('cia_<?= $code ?>').textContent=this.value">
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="submit" class="btn">◈ <?= $editAsset ? 'Update' : 'Add' ?> Asset</button>
                    <?php if ($editAsset): ?>
                    <a href="assets.php?audit_id=<?= $selectedAuditId ?>" class="btn btn-ghost">Cancel</a>
                    <?php endif ?>
                </div>
            </form>
        </div>
        </div>

        <!-- Right: Asset list -->
        <div>
        <div class="card" style="position:sticky;top:20px;">
            <div class="card-title">Assets (<?= count($assets) ?>)</div>
            <?php if (empty($assets)): ?>
                <p class="text-muted" style="font-size:12px;">No assets defined yet for this audit.</p>
            <?php else: ?>
            <?php foreach ($assets as $a): ?>
            <div style="padding:12px;border:1px solid var(--border);border-radius:3px;margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px;">
                    <div>
                        <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($a['name']) ?></div>
                        <?php if ($a['owner_name']): ?>
                        <div style="font-size:11px;color:var(--text-dim);">Owner: <?= htmlspecialchars($a['owner_name']) ?></div>
                        <?php endif ?>
                    </div>
                    <span style="font-size:10px;font-weight:800;padding:2px 6px;border-radius:2px;background:#0d1a2d;color:#4a8cff;border:1px solid #1a3a5c;">
                        Primary: <?= $a['primary_req'] ?>
                    </span>
                    <span style="font-size:10px;font-weight:800;padding:2px 6px;border-radius:2px;background:var(--bg-elevated);color:<?= $a['criticality_level']==='Critical'?'#dc2626':($a['criticality_level']==='High'?'#f97316':($a['criticality_level']==='Medium'?'#ffdd55':'#22c55e')) ?>;border:1px solid #333;">
                        <?= htmlspecialchars($a['criticality_level'] ?? 'Unrated') ?> (<?= $a['criticality_score'] ?? 0 ?>)
                    </span>
                </div>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <?php foreach (['C'=>['cia_confidentiality','#4a8cff'],'I'=>['cia_integrity','#22c55e'],'A'=>['cia_availability','#ffdd55']] as $k=>[$field,$col]): ?>
                    <span style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:2px;background:var(--bg-elevated);color:<?= $col ?>;">
                        <?= $k ?>:<?= $a[$field] ?>
                    </span>
                    <?php endforeach ?>
                    <span style="font-size:10px;color:var(--text-dim);margin-left:auto;">
                        <?= $a['container_count'] ?> container<?= $a['container_count'] != 1 ? 's' : '' ?>
                    </span>
                </div>
                <div style="display:flex;gap:6px;">
                    <a href="assets.php?audit_id=<?= $selectedAuditId ?>&edit=<?= $a['id'] ?>"
                       class="btn btn-ghost" style="font-size:10px;padding:3px 10px;">Edit</a>
                    <a href="vulnerabilities.php?asset_id=<?= $a['id'] ?>"
                       class="btn btn-ghost" style="font-size:10px;padding:3px 10px;color:#f97316;">OWASP Vulns →</a>
                    <a href="containers.php?asset_id=<?= $a['id'] ?>"
                       class="btn btn-ghost" style="font-size:10px;padding:3px 10px;">Containers →</a>
                    <form method="POST" onsubmit="return confirm('Delete this asset?')" style="margin-left:auto;">
                        <input type="hidden" name="delete_asset" value="<?= $a['id'] ?>">
                        <button class="btn btn-danger" style="font-size:10px;padding:3px 10px;">Del</button>
                    </form>
                </div>
            </div>
            <?php endforeach ?>
            <?php endif ?>

            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:8px;">
                <a href="risk_criteria.php?audit_id=<?= $selectedAuditId ?>"
                   class="btn btn-ghost" style="flex:1;text-align:center;font-size:10px;">← Step 1</a>
                <a href="vulnerabilities.php<?= !empty($assets) ? '?asset_id='.$assets[0]['id'] : '' ?>"
                   class="btn" style="flex:1;text-align:center;font-size:10px;">OWASP Vuln →</a>
            </div>
        </div>
        </div>

        </div><!-- /grid -->
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
