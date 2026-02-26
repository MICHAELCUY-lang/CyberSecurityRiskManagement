<?php
/**
 * vulnerabilities.php — OWASP Vulnerability Selection
 * Bridges Asset Identification (Step 2) and Threat Scenarios (Step 5).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'OWASP Vulnerabilities';
$currentPage = 'assets'; // keep active menu as assets
$db   = getAuditDB();
$user = currentUser();

$error = $success = '';
$assetId = (int)($_GET['asset_id'] ?? 0);

// Validate asset
$stmt = $db->prepare("
    SELECT a.*, au.system_name, au.id AS audit_id 
    FROM assets a 
    JOIN audits au ON au.id = a.audit_id 
    WHERE a.id = ?
");
$stmt->execute([$assetId]);
$asset = $stmt->fetch();

if (!$asset) {
    die("Asset not found or no permission.");
}

// Fetch OWASP Library grouped by category
$owaspLib = $db->query("SELECT * FROM owasp_library ORDER BY category, vuln_name")->fetchAll();
$groupedVulns = [];
foreach ($owaspLib as $v) {
    $groupedVulns[$v['category']][] = $v;
}

// Fetch currently selected
$stmt = $db->prepare("SELECT vuln_id FROM asset_vulnerabilities WHERE asset_id = ?");
$stmt->execute([$assetId]);
$selectedVulns = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vulns'])) {
    $selectedIds = $_POST['vulns'] ?? [];
    
    try {
        $db->beginTransaction();
        
        // 1. Clear old vulns
        $db->prepare("DELETE FROM asset_vulnerabilities WHERE asset_id = ?")->execute([$assetId]);
        
        // Ensure auto-container and auto-concern exist for this asset to anchor Threat Scenarios
        $stmt = $db->prepare("SELECT id FROM asset_containers WHERE asset_id=? AND name='OWASP Auto-Generated'");
        $stmt->execute([$assetId]);
        $containerId = $stmt->fetchColumn();
        if (!$containerId) {
            $db->prepare("INSERT INTO asset_containers (asset_id, name, type, description) VALUES (?, 'OWASP Auto-Generated', 'Technical', 'Auto-generated for OWASP vulnerabilities')")->execute([$assetId]);
            $containerId = $db->lastInsertId();
        }

        $stmt = $db->prepare("SELECT id FROM areas_of_concern WHERE container_id=? AND description='OWASP Auto-Generated Concerns'");
        $stmt->execute([$containerId]);
        $concernId = $stmt->fetchColumn();
        if (!$concernId) {
            $db->prepare("INSERT INTO areas_of_concern (container_id, description) VALUES (?, 'OWASP Auto-Generated Concerns')")->execute([$containerId]);
            $concernId = $db->lastInsertId();
        }

        // We clean up previous auto-generated threat scenarios for this concern
        // Since it's a dedicated concern, we can just delete all scenarios under it
        $stmt = $db->prepare("SELECT id FROM threat_scenarios WHERE concern_id=?");
        $stmt->execute([$concernId]);
        $oldScenarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($oldScenarios) {
            $inQuery = implode(',', array_fill(0, count($oldScenarios), '?'));
            $db->prepare("DELETE FROM risks WHERE scenario_id IN ($inQuery)")->execute($oldScenarios);
            $db->prepare("DELETE FROM threat_scenarios WHERE concern_id = ?")->execute([$concernId]);
        }
        
        $insVuln   = $db->prepare("INSERT INTO asset_vulnerabilities (asset_id, vuln_id, assigned_likelihood, risk_score) VALUES (?, ?, ?, ?)");
        $insThreat = $db->prepare("INSERT INTO threat_scenarios (concern_id, actor, access_method, motive, consequence, description) VALUES (?, ?, 'Network', 'Malicious Intent', ?, ?)");
        $insRisk   = $db->prepare("INSERT INTO risks (scenario_id, cia_impacted, consequence_detail) VALUES (?, ?, ?)");
        
        foreach ($selectedIds as $vid) {
            // Find vuln details
            $vuln = null;
            foreach ($owaspLib as $v) {
                if ($v['id'] == $vid) { $vuln = $v; break; }
            }
            if (!$vuln) continue;
            
            // Calculate Risk Score = Likelihood * CIA Impact
            $likelihood = $vuln['default_likelihood']; // 1 to 3
            $impactScore = $asset['criticality_score'] ?? 9; // Max 15
            $riskScore = $likelihood * $impactScore;
            
            $insVuln->execute([$assetId, $vid, $likelihood, $riskScore]);
            
            // Map to consequence
            $conseq = 'Disclosure';
            if (stripos($vuln['mapped_impact'], 'tamper') !== false) $conseq = 'Modification';
            if (stripos($vuln['mapped_impact'], 'loss') !== false || stripos($vuln['mapped_impact'], 'destroy') !== false) $conseq = 'Destruction';
            if (stripos($vuln['mapped_impact'], 'avail') !== false) $conseq = 'Interruption';
            
            $desc = "Auto-generated from OWASP vulnerability: " . $vuln['vuln_name'] . " (" . $vuln['mapped_threat'] . ") - Impact: " . $vuln['mapped_impact'];
            $actor = stripos($vuln['mapped_threat'], 'Internal') !== false ? 'Internal Human' : 'External Human';

            $insThreat->execute([$concernId, $actor, $conseq, $desc]);
            $scenarioId = $db->lastInsertId();
            
            $insRisk->execute([$scenarioId, $asset['primary_req'], $vuln['mapped_impact']]);
        }
        
        $db->commit();
        $success = "Vulnerabilities saved. Threat scenarios and risks auto-generated successfully.";
        // Refresh selected
        $stmt = $db->prepare("SELECT vuln_id FROM asset_vulnerabilities WHERE asset_id = ?");
        $stmt->execute([$assetId]);
        $selectedVulns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error saving: " . $e->getMessage();
    }
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>OWASP Vulnerability Selection</h1>
        <div style="display:flex;align-items:center;gap:12px;">
            <span class="breadcrumb"><?= htmlspecialchars($asset['system_name']) ?> › <?= htmlspecialchars($asset['name']) ?></span>
            <a href="assets.php?audit_id=<?= $asset['audit_id'] ?>" class="btn btn-ghost" style="font-size:11px;padding:5px 16px;">← Back to Assets</a>
        </div>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>

        <div class="card" style="border-left:3px solid #ffdd55;margin-bottom:16px;">
            <div style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#ffdd55;margin-bottom:8px;">◈ Mapped Workflow: OWASP Integration</div>
            <p style="font-size:13px;line-height:1.7;color:var(--text-muted);margin:0;">
                Select detected or suspected OWASP vulnerabilities for <strong style="color:var(--text);"><?= htmlspecialchars($asset['name']) ?></strong>.
                The system will automatically assign likelihood, suggest impact, generate <strong style="color:var(--text);">OCTAVE Threat Scenarios</strong>, calculate risk scores, and dynamically build the Audit Checklist based on your selections.
            </p>
        </div>

        <form method="POST">
            <input type="hidden" name="save_vulns" value="1">
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;margin-bottom:20px;">
                <?php foreach ($groupedVulns as $category => $vulns): ?>
                <div class="card" style="padding:16px;">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--text-dim);margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:6px;">
                        <?= htmlspecialchars($category) ?>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($vulns as $v): 
                            $isChecked = in_array($v['id'], $selectedVulns);
                        ?>
                        <div style="display:flex;align-items:flex-start;gap:8px;">
                            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;flex:1;">
                                <input type="checkbox" name="vulns[]" value="<?= $v['id'] ?>" <?= $isChecked ? 'checked' : '' ?> style="margin-top:2px;">
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:<?= $isChecked ? '#4a8cff' : 'var(--text)' ?>;"><?= htmlspecialchars($v['vuln_name']) ?></div>
                                    <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">
                                        <?= htmlspecialchars($v['mapped_threat']) ?> → <?= htmlspecialchars($v['mapped_impact']) ?>
                                    </div>
                                </div>
                            </label>
                            <button type="button" class="btn btn-ghost" style="font-size:10px;padding:2px 8px;color:#22c55e;border:1px solid #1a3a1a;margin-top:2px;" onclick="explainVuln(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['vuln_name'])) ?>')">
                                ✨ AI
                            </button>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
            
            <button type="submit" class="btn">Save Vulnerabilities & Auto-Generate Threats</button>
            <a href="threat_scenarios.php<?= $asset['audit_id'] ? '?audit_id='.$asset['audit_id'] : '' ?>" class="btn btn-ghost" style="margin-left:8px;">Review Threat Scenarios →</a>
        </form>

    </div>
</div>

<!-- AI Modal -->
<div id="aiModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--bg-elevated);padding:24px;border-radius:6px;width:90%;max-width:550px;border-top:3px solid #22c55e;box-shadow:0 10px 40px rgba(0,0,0,0.6);">
        <h3 id="aiModalTitle" style="margin-top:0;font-size:16px;">AI Explanation</h3>
        <div id="aiModalContent" style="font-size:13px;line-height:1.6;color:var(--text);margin-top:16px;max-height:60vh;overflow-y:auto;padding-right:8px;">
            Loading explanation...
        </div>
        <div style="margin-top:20px;text-align:right;">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('aiModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<script>
async function explainVuln(id, title) {
    document.getElementById('aiModal').style.display = 'flex';
    document.getElementById('aiModalTitle').innerText = 'Explaining: ' + title;
    document.getElementById('aiModalContent').innerHTML = '<span style="color:var(--text-muted);">✨ Consulting AI Advisor...</span>';
    
    try {
        const res = await fetch('api_explain_vuln.php?id=' + id);
        const data = await res.json();
        if (data.error) {
            document.getElementById('aiModalContent').innerHTML = '<span style="color:#dc2626;">' + data.error + '</span>';
        } else {
            document.getElementById('aiModalContent').innerHTML = data.explanation;
        }
    } catch (e) {
        document.getElementById('aiModalContent').innerHTML = '<span style="color:#dc2626;">Network error.</span>';
    }
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>

