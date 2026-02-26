<?php
/**
 * risk_criteria.php — OCTAVE Allegro Step 1: Risk Measurement Criteria
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Risk Measurement Criteria';
$currentPage = 'risk_criteria';
$db   = getAuditDB();
$user = currentUser();

$error = $success = '';

// Load audits for selector
if ($user['role'] === 'admin') {
    $auditList = $db->query("SELECT a.id, a.system_name FROM audits a ORDER BY a.created_at DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, system_name FROM audits WHERE auditor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $auditList = $stmt->fetchAll();
}

$selectedAuditId = (int)($_GET['audit_id'] ?? ($auditList[0]['id'] ?? 0));

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auditId = (int)($_POST['audit_id'] ?? 0);
    $fields  = ['reputation_weight','financial_weight','productivity_weight','safety_weight','legal_weight'];
    $vals    = [];
    foreach ($fields as $f) $vals[$f] = max(1, min(5, (int)($_POST[$f] ?? 3)));
    $notes   = trim($_POST['notes'] ?? '');

    // Upsert
    $exists = $db->prepare("SELECT id FROM risk_criteria WHERE audit_id = ?");
    $exists->execute([$auditId]);
    if ($exists->fetch()) {
        $db->prepare("UPDATE risk_criteria SET reputation_weight=?,financial_weight=?,productivity_weight=?,safety_weight=?,legal_weight=?,notes=? WHERE audit_id=?")
           ->execute([$vals['reputation_weight'],$vals['financial_weight'],$vals['productivity_weight'],$vals['safety_weight'],$vals['legal_weight'],$notes,$auditId]);
    } else {
        $db->prepare("INSERT INTO risk_criteria (audit_id,reputation_weight,financial_weight,productivity_weight,safety_weight,legal_weight,notes,created_by) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$auditId,$vals['reputation_weight'],$vals['financial_weight'],$vals['productivity_weight'],$vals['safety_weight'],$vals['legal_weight'],$notes,$user['id']]);
    }
    $success = 'Risk criteria saved.';
    $selectedAuditId = $auditId;
}

// Load existing criteria
$criteria = null;
if ($selectedAuditId) {
    $stmt = $db->prepare("SELECT * FROM risk_criteria WHERE audit_id = ?");
    $stmt->execute([$selectedAuditId]);
    $criteria = $stmt->fetch();
}

$areas = [
    'reputation_weight'  => ['label' => 'Reputation / Customer Confidence', 'icon' => '◈', 'desc' => 'Damage to public image or customer trust'],
    'financial_weight'   => ['label' => 'Financial',                         'icon' => '◆', 'desc' => 'Direct financial loss, fines, or costs'],
    'productivity_weight'=> ['label' => 'Productivity / Operational',        'icon' => '▣', 'desc' => 'Disruption to business operations'],
    'safety_weight'      => ['label' => 'Safety and Health',                 'icon' => '⚠', 'desc' => 'Risk to human safety or health'],
    'legal_weight'       => ['label' => 'Legal / Regulatory',                'icon' => '⚖', 'desc' => 'Legal liability or compliance breach'],
];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Risk Measurement Criteria</h1>
        <span class="breadcrumb">OCTAVE Allegro — Step 1 of 8</span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>

        <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

        <div>
        <!-- Step explanation -->
        <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:0;">
            <div style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4a8cff;margin-bottom:8px;">
                ⚖ OCTAVE Allegro — Step 1
            </div>
            <p style="font-size:13px;line-height:1.7;color:var(--text-muted);margin:0;">
                Before any risk assessment, your organization must define the relative importance of five impact areas.
                These weights are used in Step 7 to calculate organization-calibrated risk scores.
                Rate each area from <strong style="color:var(--text);">1 (Low priority)</strong> to
                <strong style="color:var(--text);">5 (Critical priority)</strong> for your organization.
            </p>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-title">Configure Impact Area Weights</div>
            <form method="POST">
                <div class="form-group" style="margin-bottom:20px;">
                    <label>Audit Context *</label>
                    <select name="audit_id" onchange="this.form.submit()" required>
                        <option value="">— Select Audit —</option>
                        <?php foreach ($auditList as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $selectedAuditId == $a['id'] ? 'selected' : '' ?>>
                            #<?= $a['id'] ?> — <?= htmlspecialchars($a['system_name']) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <?php foreach ($areas as $field => $area): ?>
                <div style="padding:16px;background:var(--bg-elevated);border-radius:3px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <div>
                            <span style="font-size:14px;margin-right:8px;"><?= $area['icon'] ?></span>
                            <strong style="font-size:13px;"><?= $area['label'] ?></strong>
                        </div>
                        <span class="font-mono" style="font-size:18px;font-weight:800;color:var(--chart-blue);"
                              id="val_<?= $field ?>">
                            <?= $criteria[$field] ?? 3 ?>
                        </span>
                    </div>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 10px;"><?= $area['desc'] ?></p>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:10px;color:var(--text-dim);">Low (1)</span>
                        <input type="range" name="<?= $field ?>" min="1" max="5" step="1"
                               value="<?= $criteria[$field] ?? 3 ?>"
                               style="flex:1;"
                               oninput="document.getElementById('val_<?= $field ?>').textContent=this.value">
                        <span style="font-size:10px;color:var(--text-dim);">Critical (5)</span>
                    </div>
                    <!-- Weight labels -->
                    <div style="display:flex;justify-content:space-between;margin-top:4px;padding:0 2px;">
                        <?php foreach (['Low','Med-Low','Medium','Med-High','Critical'] as $lbl): ?>
                        <span style="font-size:9px;color:var(--text-dim);"><?= $lbl ?></span>
                        <?php endforeach ?>
                    </div>
                </div>
                <?php endforeach ?>

                <div class="form-group" style="margin-top:16px;">
                    <label>Notes / Justification</label>
                    <textarea name="notes" rows="3" placeholder="Optional: explain why certain areas are weighted higher..."><?= htmlspecialchars($criteria['notes'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn" style="margin-top:8px;">⚖ Save Risk Criteria →</button>
            </form>
        </div>
        </div><!-- /left col -->

        <!-- Right: Weight summary -->
        <div>
            <div class="card" style="position:sticky;top:20px;">
                <div class="card-title">Weight Summary</div>
                <?php if ($criteria): ?>
                <?php
                $total = $criteria['reputation_weight'] + $criteria['financial_weight'] +
                         $criteria['productivity_weight'] + $criteria['safety_weight'] + $criteria['legal_weight'];
                $areaMap = [
                    'Reputation'   => $criteria['reputation_weight'],
                    'Financial'    => $criteria['financial_weight'],
                    'Productivity' => $criteria['productivity_weight'],
                    'Safety'       => $criteria['safety_weight'],
                    'Legal'        => $criteria['legal_weight'],
                ];
                arsort($areaMap);
                ?>
                <?php foreach ($areaMap as $name => $w): ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                        <span><?= $name ?></span>
                        <span class="font-mono" style="color:<?= $w >= 4 ? '#dc2626' : ($w >= 3 ? '#ffdd55' : '#22c55e') ?>;">
                            <?= $w ?>/5
                        </span>
                    </div>
                    <div style="height:6px;background:var(--bg-elevated);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= ($w/5)*100 ?>%;
                                    background:<?= $w >= 4 ? '#dc2626' : ($w >= 3 ? '#ffdd55' : '#22c55e') ?>;
                                    border-radius:3px;transition:width .3s;"></div>
                    </div>
                </div>
                <?php endforeach ?>
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-size:11px;color:var(--text-muted);">
                    Total weight: <strong style="color:var(--text);"><?= $total ?></strong> / 25 &nbsp;|&nbsp;
                    Highest: <strong style="color:var(--text);"><?= array_key_first($areaMap) ?></strong>
                </div>
                <?php else: ?>
                <p class="text-muted" style="font-size:12px;">Select an audit and save criteria to see the summary.</p>
                <?php endif ?>

                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                    <div style="font-size:10px;font-weight:700;letter-spacing:.1em;color:var(--text-dim);margin-bottom:8px;">NEXT STEP</div>
                    <a href="assets.php<?= $selectedAuditId ? '?audit_id='.$selectedAuditId : '' ?>"
                       class="btn" style="width:100%;text-align:center;justify-content:center;font-size:11px;">
                        Step 2: Asset Profile →
                    </a>
                </div>
            </div>
        </div>

        </div><!-- /grid -->
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
