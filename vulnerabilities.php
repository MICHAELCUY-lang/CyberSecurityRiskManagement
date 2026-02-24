<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$pageTitle   = 'Vulnerabilities';
$currentPage = 'vulnerabilities';
$db = getDB();
$message = '';
$error   = '';

$activeOrg = (int)($_SESSION['active_org'] ?? 0);

// ============================================================
// RISK ENGINE
// ============================================================
function calcRiskLevel(int $score): string {
    if ($score >= 15) return 'Critical';
    if ($score >= 10) return 'High';
    if ($score >= 5)  return 'Medium';
    return 'Low';
}

// ============================================================
// CHECKLIST AUTO-GENERATOR
// ============================================================
function autoGenerateChecklist(PDO $db, int $assetId, string $vulnName): void {
    $rules = [
        'Weak Password Policy'     => ['title' => 'Password Complexity Enforcement',
                                        'desc'  => 'Verify password complexity, length requirements, and expiry policies are enforced.',
                                        'fw'    => 'NIST SP 800-63B'],
        'Insecure Transport'       => ['title' => 'TLS Certificate and HTTPS Enforcement',
                                        'desc'  => 'Verify all web interfaces enforce HTTPS with valid TLS certificates and HSTS headers.',
                                        'fw'    => 'OWASP TLS'],
        'No HTTPS'                 => ['title' => 'TLS Certificate and HTTPS Enforcement',
                                        'desc'  => 'Verify all web interfaces enforce HTTPS with valid TLS certificates and HSTS headers.',
                                        'fw'    => 'OWASP TLS'],
    ];

    foreach ($rules as $keyword => $item) {
        if (stripos($vulnName, $keyword) !== false) {
            // Find or create checklist entry
            $stmt = $db->prepare("SELECT id FROM audit_checklist WHERE title=? LIMIT 1");
            $stmt->execute([$item['title']]);
            $checklistId = $stmt->fetchColumn();

            if (!$checklistId) {
                $ins = $db->prepare("INSERT INTO audit_checklist (title, description, framework_source) VALUES (?,?,?)");
                $ins->execute([$item['title'], $item['desc'], $item['fw']]);
                $checklistId = $db->lastInsertId();
            }

            // Link to asset in audit_results (ignore if already exists)
            $link = $db->prepare("
                INSERT IGNORE INTO audit_results (checklist_id, asset_id, status)
                VALUES (?, ?, 'not_applicable')
            ");
            $link->execute([$checklistId, $assetId]);
        }
    }
}

// ============================================================
// HANDLE POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $assetId   = (int)($_POST['asset_id'] ?? 0);
        $vulnId    = (int)($_POST['vulnerability_id'] ?? 0);
        $likelihood = max(1, min(5, (int)($_POST['likelihood'] ?? 3)));
        $impact     = max(1, min(5, (int)($_POST['impact'] ?? 3)));
        $riskScore  = $likelihood * $impact;
        $riskLevel  = calcRiskLevel($riskScore);

        if (!$assetId || !$vulnId) {
            $error = 'Please select an asset and a vulnerability.';
        } else {
            // Verify asset belongs to active org
            $chk = $db->prepare("SELECT id FROM assets WHERE id=? AND organization_id=?");
            $chk->execute([$assetId, $activeOrg]);
            if (!$chk->fetchColumn()) {
                $error = 'Invalid asset selection.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO asset_vulnerabilities (asset_id, vulnerability_id, likelihood, impact, risk_score, risk_level)
                    VALUES (?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE likelihood=VALUES(likelihood), impact=VALUES(impact),
                        risk_score=VALUES(risk_score), risk_level=VALUES(risk_level)
                ");
                $stmt->execute([$assetId, $vulnId, $likelihood, $impact, $riskScore, $riskLevel]);

                // Auto-generate checklist items
                $vRow = $db->prepare("SELECT name FROM vulnerabilities WHERE id=?");
                $vRow->execute([$vulnId]);
                $vulnName = $vRow->fetchColumn();
                autoGenerateChecklist($db, $assetId, $vulnName);

                // Auto-generate finding for High/Critical
                if (in_array($riskLevel, ['High','Critical'])) {
                    $vDesc = $db->prepare("SELECT name, impact_description FROM vulnerabilities WHERE id=?");
                    $vDesc->execute([$vulnId]);
                    $vInfo = $vDesc->fetch();

                    $aName = $db->prepare("SELECT asset_name FROM assets WHERE id=?");
                    $aName->execute([$assetId]);
                    $assetName = $aName->fetchColumn();

                    $issue  = "[{$riskLevel}] {$vInfo['name']} detected on asset '{$assetName}'. {$vInfo['impact_description']}";
                    $rec    = "Immediately review and remediate '{$vInfo['name']}' on '{$assetName}'. Perform a full vulnerability assessment and apply compensating controls.";

                    // Insert finding (avoid exact duplicates)
                    $exists = $db->prepare("SELECT id FROM findings WHERE asset_id=? AND issue=?");
                    $exists->execute([$assetId, $issue]);
                    if (!$exists->fetchColumn()) {
                        $db->prepare("INSERT INTO findings (asset_id, issue, risk_level, recommendation) VALUES (?,?,?,?)")
                           ->execute([$assetId, $issue, $riskLevel, $rec]);
                    }
                }

                $message = "Vulnerability assigned. Risk Score: {$riskScore} ({$riskLevel}).";
            }
        }
    }

    if ($action === 'remove') {
        $avId = (int)($_POST['av_id'] ?? 0);
        $db->prepare("DELETE FROM asset_vulnerabilities WHERE id=?")->execute([$avId]);
        $message = 'Vulnerability mapping removed.';
    }
}

// --- Data ---
$vulnLibrary = $db->query("SELECT * FROM vulnerabilities ORDER BY category, name")->fetchAll();

$assets = [];
if ($activeOrg) {
    $stmt = $db->prepare("SELECT id, asset_name FROM assets WHERE organization_id=? ORDER BY asset_name");
    $stmt->execute([$activeOrg]);
    $assets = $stmt->fetchAll();
}

// Risk register for active org
$riskRegister = [];
if ($activeOrg) {
    $stmt = $db->prepare("
        SELECT av.id AS av_id, a.asset_name, v.name AS vuln_name, v.category,
               av.likelihood, av.impact, av.risk_score, av.risk_level
        FROM asset_vulnerabilities av
        JOIN assets a ON a.id = av.asset_id
        JOIN vulnerabilities v ON v.id = av.vulnerability_id
        WHERE a.organization_id = ?
        ORDER BY av.risk_score DESC
    ");
    $stmt->execute([$activeOrg]);
    $riskRegister = $stmt->fetchAll();
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Vulnerabilities</h1>
        <span class="breadcrumb">OCTAVE Allegro / Vulnerabilities</span>
    </div>
    <div class="content-area">

        <?php if (!$activeOrg): ?>
        <div class="alert alert-info">Select an active organization first. <a href="organization.php" style="color:inherit;text-decoration:underline;">Go to Organization</a></div>
        <?php else: ?>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

            <!-- Assign Form -->
            <div class="card">
                <div class="card-title">Assign Vulnerability to Asset</div>
                <form method="POST" action="vulnerabilities.php">
                    <input type="hidden" name="action" value="assign">
                    <div class="form-group mb-2">
                        <label for="asset_id">Asset</label>
                        <select id="asset_id" name="asset_id" required>
                            <option value="">-- Select Asset --</option>
                            <?php foreach ($assets as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['asset_name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <label for="vulnerability_id">Vulnerability</label>
                        <select id="vulnerability_id" name="vulnerability_id" required>
                            <option value="">-- Select Vulnerability --</option>
                            <?php
                            $currentCat = '';
                            foreach ($vulnLibrary as $v):
                                if ($v['category'] !== $currentCat) {
                                    if ($currentCat) echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars($v['category']) . '">';
                                    $currentCat = $v['category'];
                                }
                            ?>
                            <option value="<?= $v['id'] ?>"
                                    data-likelihood="<?= $v['default_likelihood'] ?>"
                                    data-impact="<?= $v['default_impact'] ?>">
                                <?= htmlspecialchars($v['name']) ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if ($currentCat) echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="likelihood">Likelihood (1-5)</label>
                            <select id="likelihood" name="likelihood">
                                <?php for ($i=1; $i<=5; $i++): ?>
                                <option value="<?= $i ?>" <?= $i===3 ? 'selected' : '' ?>>
                                    <?= $i ?> — <?= ['','Very Low','Low','Medium','High','Very High'][$i] ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="impact">Impact (1-5)</label>
                            <select id="impact" name="impact">
                                <?php for ($i=1; $i<=5; $i++): ?>
                                <option value="<?= $i ?>" <?= $i===3 ? 'selected' : '' ?>>
                                    <?= $i ?> — <?= ['','Negligible','Minor','Moderate','Major','Severe'][$i] ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div id="risk-preview" style="margin:12px 0;padding:10px 12px;border:1px solid var(--border);border-radius:3px;font-size:12px;color:var(--text-muted);">
                        Risk Preview: Score = <strong id="preview-score">9</strong>
                        &nbsp;|&nbsp; Level: <strong id="preview-level">Medium</strong>
                    </div>
                    <button type="submit" class="btn">Assign Vulnerability</button>
                </form>
            </div>

            <!-- Vulnerability Library -->
            <div class="card" style="max-height:520px;overflow-y:auto;">
                <div class="card-title">OWASP Vulnerability Library</div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Vulnerability</th>
                                <th>Category</th>
                                <th>Def. L</th>
                                <th>Def. I</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vulnLibrary as $v): ?>
                            <tr>
                                <td style="font-size:12px;"><?= htmlspecialchars($v['name']) ?></td>
                                <td style="font-size:11px;" class="text-muted"><?= htmlspecialchars($v['category']) ?></td>
                                <td class="font-mono"><?= $v['default_likelihood'] ?></td>
                                <td class="font-mono"><?= $v['default_impact'] ?></td>
                            </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Risk Register Table -->
        <?php if (!empty($riskRegister)): ?>
        <div class="card">
            <div class="card-title">Asset-Vulnerability Risk Register</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Vulnerability</th>
                            <th>Category</th>
                            <th>Likelihood</th>
                            <th>Impact</th>
                            <th>Risk Score</th>
                            <th>Risk Level</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riskRegister as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['asset_name']) ?></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($r['vuln_name']) ?></td>
                            <td style="font-size:11px;" class="text-muted"><?= htmlspecialchars($r['category']) ?></td>
                            <td class="font-mono"><?= $r['likelihood'] ?>/5</td>
                            <td class="font-mono"><?= $r['impact'] ?>/5</td>
                            <td class="font-mono <?= in_array($r['risk_level'], ['High','Critical']) ? 'text-danger' : 'text-blue' ?>">
                                <?= $r['risk_score'] ?>
                            </td>
                            <td><span class="badge badge-<?= strtolower($r['risk_level']) ?>"><?= $r['risk_level'] ?></span></td>
                            <td>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Remove this vulnerability mapping?')">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="av_id" value="<?= $r['av_id'] ?>">
                                    <button type="submit" class="btn btn-danger" style="font-size:11px;padding:4px 10px;">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; // active org ?>
    </div>
</div>
</div>

<script>
// Real-time risk score preview
(function() {
    const likEl = document.getElementById('likelihood');
    const impEl = document.getElementById('impact');
    const vulnEl = document.getElementById('vulnerability_id');
    const scoreEl = document.getElementById('preview-score');
    const levelEl = document.getElementById('preview-level');

    function getLevel(score) {
        if (score >= 15) return 'Critical';
        if (score >= 10) return 'High';
        if (score >= 5)  return 'Medium';
        return 'Low';
    }

    function update() {
        if (!likEl || !impEl) return;
        const score = parseInt(likEl.value) * parseInt(impEl.value);
        const level = getLevel(score);
        scoreEl.textContent = score;
        levelEl.textContent = level;
        levelEl.style.color = ['High','Critical'].includes(level) ? '#dc2626' : '#2563eb';
    }

    // Auto-fill defaults when vuln selected
    if (vulnEl) {
        vulnEl.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const lik = opt.dataset.likelihood;
            const imp = opt.dataset.impact;
            if (lik) likEl.value = lik;
            if (imp) impEl.value = imp;
            update();
        });
    }

    if (likEl) likEl.addEventListener('change', update);
    if (impEl) impEl.addEventListener('change', update);
    update();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
