<?php
/**
 * threat_scenarios.php — OCTAVE Allegro Step 5: Threat Scenarios
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Threat Scenarios';
$currentPage = 'threat_scenarios';
$db   = getAuditDB();
$user = currentUser();

$error = $success = '';
$selectedConcernId = (int)($_GET['concern_id'] ?? 0);

// Build concern list
if ($user['role'] === 'admin') {
    $concernList = $db->query("
        SELECT ac.id, ac.description, ac.container_id,
               c.name AS container_name, c.type AS container_type,
               a.name AS asset_name, au.system_name
        FROM areas_of_concern ac
        JOIN asset_containers c ON c.id=ac.container_id
        JOIN assets a ON a.id=c.asset_id
        JOIN audits au ON au.id=a.audit_id
        ORDER BY au.created_at DESC, a.created_at DESC, ac.created_at
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT ac.id, ac.description, ac.container_id,
               c.name AS container_name, c.type AS container_type,
               a.name AS asset_name, au.system_name
        FROM areas_of_concern ac
        JOIN asset_containers c ON c.id=ac.container_id
        JOIN assets a ON a.id=c.asset_id
        JOIN audits au ON au.id=a.audit_id
        WHERE au.auditor_id=?
        ORDER BY au.created_at DESC, a.created_at DESC, ac.created_at
    ");
    $stmt->execute([$user['id']]);
    $concernList = $stmt->fetchAll();
}
if (!$selectedConcernId && $concernList) $selectedConcernId = $concernList[0]['id'];

$currentConcern = null;
foreach ($concernList as $c) {
    if ($c['id'] == $selectedConcernId) { $currentConcern = $c; break; }
}

// Delete scenario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_scenario'])) {
    $db->prepare("DELETE FROM threat_scenarios WHERE id=?")->execute([(int)$_POST['delete_scenario']]);
    header("Location: threat_scenarios.php?concern_id=$selectedConcernId&deleted=1"); exit;
}

// Save scenario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scenario'])) {
    $cid    = (int)($_POST['concern_id'] ?? $selectedConcernId);
    $actor  = in_array($_POST['actor'] ?? '', ['Internal Human','External Human','System','Natural']) ? $_POST['actor'] : 'External Human';
    $access = in_array($_POST['access_method'] ?? '', ['Network','Physical','Remote','Supply Chain','Other']) ? $_POST['access_method'] : 'Network';
    $motive = trim($_POST['motive'] ?? '');
    $conseq = in_array($_POST['consequence'] ?? '', ['Disclosure','Modification','Destruction','Interruption']) ? $_POST['consequence'] : 'Disclosure';
    $desc   = trim($_POST['description'] ?? '');

    $db->prepare("INSERT INTO threat_scenarios (concern_id,actor,access_method,motive,consequence,description) VALUES (?,?,?,?,?,?)")
       ->execute([$cid,$actor,$access,$motive,$conseq,$desc]);

    // Auto-create risk record for Step 6
    $scenarioId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO risks (scenario_id, cia_impacted, consequence_detail) VALUES (?,?,?)")
       ->execute([$scenarioId, 'C', $conseq . ': ' . ($desc ?: $motive)]);

    $success = 'Threat scenario added and risk record created (Steps 5 & 6).';
}

// Load scenarios
$scenarios = [];
if ($selectedConcernId) {
    $stmt = $db->prepare("
        SELECT ts.*, r.id AS risk_id
        FROM threat_scenarios ts
        LEFT JOIN risks r ON r.scenario_id=ts.id
        WHERE ts.concern_id=? ORDER BY ts.created_at
    ");
    $stmt->execute([$selectedConcernId]);
    $scenarios = $stmt->fetchAll();
}

$actorColors = [
    'Internal Human'=>'#ffdd55','External Human'=>'#dc2626',
    'System'=>'#4a8cff','Natural'=>'#22c55e'
];
$conseqColors = [
    'Disclosure'=>'#dc2626','Modification'=>'#f97316',
    'Destruction'=>'#7f1d1d','Interruption'=>'#ffdd55'
];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Threat Scenarios</h1>
        <span class="breadcrumb">OCTAVE Allegro — Step 5 of 8</span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>
        <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Scenario removed.</div><?php endif ?>

        <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:16px;">
            <div style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4a8cff;margin-bottom:8px;">▲ OCTAVE Allegro — Step 5</div>
            <p style="font-size:13px;line-height:1.7;color:var(--text-muted);margin:0;">
                Convert each area of concern into a structured <strong style="color:var(--text);">threat scenario</strong>
                documenting: <span style="color:#ffdd55;">WHO</span> (actor) →
                <span style="color:#4a8cff;">HOW</span> (access) →
                <span style="color:#22c55e;">WHY</span> (motive) →
                <span style="color:#dc2626;">WHAT</span> (consequence).
                Each scenario automatically creates a risk record in Step 6.
            </p>
        </div>

        <!-- Concern selector -->
        <form method="GET" style="margin-bottom:16px;display:flex;gap:10px;align-items:center;">
            <label style="font-size:12px;color:var(--text-muted);">Concern:</label>
            <select name="concern_id" onchange="this.form.submit()" style="width:600px;">
                <?php foreach ($concernList as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $selectedConcernId == $c['id'] ? 'selected' : '' ?>>
                    [<?= htmlspecialchars($c['system_name']) ?> › <?= htmlspecialchars($c['asset_name']) ?> › <?= $c['container_type'] ?>: <?= htmlspecialchars($c['container_name']) ?>]
                    <?= htmlspecialchars(substr($c['description'], 0, 60)) ?>...
                </option>
                <?php endforeach ?>
            </select>
        </form>

        <div style="display:grid;grid-template-columns:1fr 420px;gap:20px;align-items:start;">

        <!-- Threat scenario form -->
        <div class="card">
            <div class="card-title">
                Define Threat Scenario
                <?php if ($currentConcern): ?>
                <span style="font-size:11px;font-weight:400;color:var(--text-dim);margin-left:8px;">
                    from concern: <?= htmlspecialchars(substr($currentConcern['description'], 0, 50)) ?>...
                </span>
                <?php endif ?>
            </div>
            <form method="POST">
                <input type="hidden" name="save_scenario" value="1">
                <input type="hidden" name="concern_id" value="<?= $selectedConcernId ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Threat Actor *
                            <span class="text-muted" style="font-size:10px;font-weight:400;"> — WHO?</span>
                        </label>
                        <select name="actor">
                            <option value="External Human">External Human (outsider, attacker)</option>
                            <option value="Internal Human">Internal Human (employee, contractor)</option>
                            <option value="System">System (software bug, misconfiguration)</option>
                            <option value="Natural">Natural (disaster, power failure)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Access Method *
                            <span class="text-muted" style="font-size:10px;font-weight:400;"> — HOW?</span>
                        </label>
                        <select name="access_method">
                            <option value="Network">Network (internet, intranet, WiFi)</option>
                            <option value="Physical">Physical (on-site access)</option>
                            <option value="Remote">Remote access / VPN</option>
                            <option value="Supply Chain">Supply Chain / Third-party</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Motive / Reason
                            <span class="text-muted" style="font-size:10px;font-weight:400;"> — WHY?</span>
                        </label>
                        <input type="text" name="motive" placeholder="e.g. Financial gain, espionage, sabotage, negligence, natural disaster...">
                    </div>
                    <div class="form-group">
                        <label>Consequence *
                            <span class="text-muted" style="font-size:10px;font-weight:400;"> — WHAT happens?</span>
                        </label>
                        <select name="consequence">
                            <option value="Disclosure">Disclosure (unauthorized access/leak)</option>
                            <option value="Modification">Modification (tampering/corruption)</option>
                            <option value="Destruction">Destruction (deletion/damage)</option>
                            <option value="Interruption">Interruption (outage/unavailability)</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Scenario Narrative
                            <span class="text-muted" style="font-size:10px;font-weight:400;"> — describe the complete scenario</span>
                        </label>
                        <textarea name="description" rows="3"
                            placeholder="e.g. An external attacker exploits an unpatched vulnerability via the internet to exfiltrate customer PII records for financial gain..."></textarea>
                    </div>
                </div>

                <div style="margin-top:12px;padding:10px;background:#0a1520;border-radius:3px;font-size:11px;color:var(--text-dim);">
                    ℹ Saving this scenario will automatically create a <strong style="color:var(--text);">Risk record</strong> in Step 6.
                    You can then score it in the Risk Register (Steps 6–8).
                </div>

                <button type="submit" class="btn" style="margin-top:12px;">▲ Add Threat Scenario →</button>
            </form>
        </div>

        <!-- Scenario list -->
        <div>
        <div class="card" style="position:sticky;top:20px;">
            <div class="card-title">Scenarios (<?= count($scenarios) ?>)</div>
            <?php if (empty($scenarios)): ?>
                <p class="text-muted" style="font-size:12px;">No threat scenarios defined for this concern yet.</p>
            <?php else: ?>
            <?php foreach ($scenarios as $i => $s): ?>
            <div style="padding:12px;background:var(--bg-elevated);border-radius:3px;margin-bottom:8px;
                        border-left:3px solid <?= $conseqColors[$s['consequence']] ?? '#888' ?>;">
                <div style="font-size:10px;font-weight:700;margin-bottom:8px;display:flex;gap:8px;flex-wrap:wrap;">
                    <span style="color:<?= $actorColors[$s['actor']] ?? '#fff' ?>;">⚡ <?= $s['actor'] ?></span>
                    <span style="color:#4a8cff;">→ <?= $s['access_method'] ?></span>
                    <span style="color:<?= $conseqColors[$s['consequence']] ?? '#888' ?>;margin-left:auto;">
                        <?= $s['consequence'] ?>
                    </span>
                </div>
                <?php if ($s['motive']): ?>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">
                    Motive: <?= htmlspecialchars($s['motive']) ?>
                </div>
                <?php endif ?>
                <?php if ($s['description']): ?>
                <div style="font-size:11px;color:var(--text);line-height:1.5;margin-bottom:8px;">
                    <?= htmlspecialchars($s['description']) ?>
                </div>
                <?php endif ?>
                <?php if ($s['risk_id']): ?>
                <div style="font-size:10px;color:#22c55e;margin-bottom:6px;">✔ Risk #<?= $s['risk_id'] ?> created</div>
                <?php endif ?>
                <div style="display:flex;gap:6px;">
                    <?php if ($s['risk_id']): ?>
                    <a href="risk_register.php?risk_id=<?= $s['risk_id'] ?>"
                       class="btn btn-ghost" style="font-size:10px;padding:3px 10px;">Analyze →</a>
                    <?php endif ?>
                    <form method="POST" onsubmit="return confirm('Delete scenario and its risk?')" style="margin-left:auto;">
                        <input type="hidden" name="delete_scenario" value="<?= $s['id'] ?>">
                        <button class="btn btn-danger" style="font-size:10px;padding:3px 10px;">Del</button>
                    </form>
                </div>
            </div>
            <?php endforeach ?>
            <?php endif ?>

            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:8px;">
                <a href="concerns.php" class="btn btn-ghost" style="flex:1;text-align:center;font-size:10px;">← Step 4</a>
                <a href="risk_register.php" class="btn" style="flex:1;text-align:center;font-size:10px;">Step 6–8: Risk Register →</a>
            </div>
        </div>
        </div>

        </div><!-- /grid -->
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
