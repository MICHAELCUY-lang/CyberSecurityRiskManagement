<?php
/**
 * risk_register.php — OCTAVE Allegro Steps 6, 7 & 8:
 *   Step 6: Risk Identification (auto-generated from threat scenarios)
 *   Step 7: Risk Analysis — Likelihood × Impact (org-weighted)
 *   Step 8: Risk Response Selection (Mitigate/Accept/Transfer/Avoid)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Risk Register';
$currentPage = 'risk_register';
$db   = getAuditDB();
$user = currentUser();

$error = $success = '';
$filterAuditId = (int)($_GET['audit_id'] ?? 0);
$focusRiskId   = (int)($_GET['risk_id'] ?? 0);

// Audit list
if ($user['role'] === 'admin') {
    $auditList = $db->query("SELECT id, system_name FROM audits ORDER BY created_at DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, system_name FROM audits WHERE auditor_id=? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $auditList = $stmt->fetchAll();
}
if (!$filterAuditId && $auditList) $filterAuditId = $auditList[0]['id'];

// Save Step 7: Risk Analysis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_analysis'])) {
    $riskId   = (int)($_POST['risk_id'] ?? 0);
    $cia      = in_array($_POST['cia_impacted'] ?? 'C', ['C','I','A']) ? $_POST['cia_impacted'] : 'C';
    $likely   = max(1, min(5, (int)($_POST['likelihood'] ?? 3)));
    $impRep   = max(1, min(5, (int)($_POST['impact_reputation'] ?? 3)));
    $impFin   = max(1, min(5, (int)($_POST['impact_financial'] ?? 3)));
    $impProd  = max(1, min(5, (int)($_POST['impact_productivity'] ?? 3)));
    $impSafe  = max(1, min(5, (int)($_POST['impact_safety'] ?? 3)));
    $impLegal = max(1, min(5, (int)($_POST['impact_legal'] ?? 3)));

    // Load org criteria weights for this audit
    $criteriaStmt = $db->prepare("
        SELECT rc.* FROM risk_criteria rc
        JOIN audits a ON a.id=rc.audit_id
        JOIN risks r ON r.id=?
        JOIN threat_scenarios ts ON ts.id=r.scenario_id
        JOIN areas_of_concern ac ON ac.id=ts.concern_id
        JOIN asset_containers c ON c.id=ac.container_id
        JOIN assets ast ON ast.id=c.asset_id
        WHERE ast.audit_id=a.id LIMIT 1
    ");
    $criteriaStmt->execute([$riskId]);
    $criteria = $criteriaStmt->fetch();

    // Default weights=3 if no criteria set
    $wRep  = (float)($criteria['reputation_weight']   ?? 3);
    $wFin  = (float)($criteria['financial_weight']    ?? 3);
    $wProd = (float)($criteria['productivity_weight'] ?? 3);
    $wSafe = (float)($criteria['safety_weight']       ?? 3);
    $wLeg  = (float)($criteria['legal_weight']        ?? 3);
    $totalW = $wRep + $wFin + $wProd + $wSafe + $wLeg;

    // Weighted impact sum
    $weightedImpact = (
        ($impRep * $wRep) + ($impFin * $wFin) + ($impProd * $wProd) +
        ($impSafe * $wSafe) + ($impLegal * $wLeg)
    ) / $totalW;

    $riskScore = round($likely * $weightedImpact, 2);

    // Classify: max possible = 5 × 5 = 25
    $riskLevel = match(true) {
        $riskScore >= 15 => 'Critical',
        $riskScore >= 9  => 'High',
        $riskScore >= 4  => 'Medium',
        default          => 'Low',
    };

    // Update CIA on risk record
    $db->prepare("UPDATE risks SET cia_impacted=? WHERE id=?")->execute([$cia, $riskId]);

    // Upsert risk_analysis
    $exists = $db->prepare("SELECT id FROM risk_analysis WHERE risk_id=?");
    $exists->execute([$riskId]);
    if ($exists->fetch()) {
        $db->prepare("UPDATE risk_analysis SET likelihood=?,impact_reputation=?,impact_financial=?,impact_productivity=?,impact_safety=?,impact_legal=?,risk_score=?,risk_level=? WHERE risk_id=?")
           ->execute([$likely,$impRep,$impFin,$impProd,$impSafe,$impLegal,$riskScore,$riskLevel,$riskId]);
    } else {
        $db->prepare("INSERT INTO risk_analysis (risk_id,likelihood,impact_reputation,impact_financial,impact_productivity,impact_safety,impact_legal,risk_score,risk_level) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$riskId,$likely,$impRep,$impFin,$impProd,$impSafe,$impLegal,$riskScore,$riskLevel]);
    }
    $success = "Risk analysis saved. Score: $riskScore → $riskLevel";
    $focusRiskId = $riskId;
}

// Save Step 8: Risk Response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_response'])) {
    $riskId  = (int)($_POST['risk_id'] ?? 0);
    $resp    = in_array($_POST['response'] ?? '', ['Mitigate','Accept','Transfer','Avoid']) ? $_POST['response'] : 'Mitigate';
    $rat     = trim($_POST['rationale'] ?? '');
    $owner   = trim($_POST['responsible_owner'] ?? '');
    $date    = $_POST['target_date'] ?? null;

    $exists = $db->prepare("SELECT id FROM risk_responses WHERE risk_id=?");
    $exists->execute([$riskId]);
    if ($exists->fetch()) {
        $db->prepare("UPDATE risk_responses SET response=?,rationale=?,responsible_owner=?,target_date=? WHERE risk_id=?")
           ->execute([$resp,$rat,$owner,$date ?: null,$riskId]);
    } else {
        $db->prepare("INSERT INTO risk_responses (risk_id,response,rationale,responsible_owner,target_date) VALUES (?,?,?,?,?)")
           ->execute([$riskId,$resp,$rat,$owner,$date ?: null]);
    }
    $success = "Risk response saved: $resp";
    $focusRiskId = $riskId;
}

// Load all risks for this audit
$risks = [];
if ($filterAuditId) {
    $stmt = $db->prepare("
        SELECT r.id AS risk_id, r.cia_impacted, r.consequence_detail,
               ts.actor, ts.access_method, ts.motive, ts.consequence, ts.description AS scenario_desc,
               ac.description AS concern_desc,
               c.name AS container_name, c.type AS container_type,
               a.name AS asset_name, a.primary_req,
               ra.likelihood, ra.impact_reputation, ra.impact_financial, ra.impact_productivity,
               ra.impact_safety, ra.impact_legal, ra.risk_score, ra.risk_level,
               rr.response, rr.rationale, rr.responsible_owner, rr.target_date
        FROM risks r
        JOIN threat_scenarios ts ON ts.id=r.scenario_id
        JOIN areas_of_concern ac ON ac.id=ts.concern_id
        JOIN asset_containers c ON c.id=ac.container_id
        JOIN assets a ON a.id=c.asset_id
        LEFT JOIN risk_analysis ra ON ra.risk_id=r.id
        LEFT JOIN risk_responses rr ON rr.risk_id=r.id
        WHERE a.audit_id=?
        ORDER BY CASE WHEN ra.risk_level='Critical' THEN 1 WHEN ra.risk_level='High' THEN 2 WHEN ra.risk_level='Medium' THEN 3 ELSE 4 END, ra.risk_score DESC
    ");
    $stmt->execute([$filterAuditId]);
    $risks = $stmt->fetchAll();
}

// Load criteria for selected audit
$criteria = null;
if ($filterAuditId) {
    $stmt = $db->prepare("SELECT * FROM risk_criteria WHERE audit_id=? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$filterAuditId]);
    $criteria = $stmt->fetch();
}

// Focus risk for panel
$focusRisk = null;
if ($focusRiskId) {
    foreach ($risks as $r) {
        if ($r['risk_id'] == $focusRiskId) { $focusRisk = $r; break; }
    }
}
if (!$focusRisk && $risks) $focusRisk = $risks[0];

$levelColors = ['Low'=>'#22c55e','Medium'=>'#ffdd55','High'=>'#f97316','Critical'=>'#dc2626'];
$actorColors = ['Internal Human'=>'#ffdd55','External Human'=>'#dc2626','System'=>'#4a8cff','Natural'=>'#22c55e'];
$respColors  = ['Mitigate'=>'#22c55e','Accept'=>'#ffdd55','Transfer'=>'#4a8cff','Avoid'=>'#dc2626'];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Risk Register</h1>
        <span class="breadcrumb">OCTAVE Allegro — Steps 6, 7 & 8</span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>

        <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:16px;padding:12px 16px;">
            <div style="display:flex;gap:20px;font-size:12px;align-items:center;flex-wrap:wrap;">
                <span style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4a8cff;">◆ Steps 6–8</span>
                <span style="color:var(--text-muted);"><strong style="color:var(--text);">Step 6</strong> — Risks auto-generated from threat scenarios</span>
                <span style="color:var(--text-muted);"><strong style="color:var(--text);">Step 7</strong> — Score each risk: Likelihood × Weighted Impact</span>
                <span style="color:var(--text-muted);"><strong style="color:var(--text);">Step 8</strong> — Select response: Mitigate / Accept / Transfer / Avoid</span>
            </div>
        </div>

        <!-- Audit selector + quick stats -->
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:8px;align-items:center;">
                <select name="audit_id" onchange="this.form.submit()" style="width:300px;">
                    <?php foreach ($auditList as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $filterAuditId == $a['id'] ? 'selected' : '' ?>>
                        #<?= $a['id'] ?> — <?= htmlspecialchars($a['system_name']) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </form>
            <?php
            $counts = ['Critical'=>0,'High'=>0,'Medium'=>0,'Low'=>0,'Unscored'=>0];
            foreach ($risks as $r) {
                if ($r['risk_level']) $counts[$r['risk_level']]++;
                else $counts['Unscored']++;
            }
            ?>
            <?php foreach (['Critical','High','Medium','Low'] as $lv): if (!$counts[$lv]) continue; ?>
            <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:2px;
                         background:<?= $lv === 'Low' ? '#0b1a0b' : ($lv === 'Medium' ? '#1a1500' : ($lv === 'High' ? '#1a0b00' : '#1a0000')) ?>;
                         color:<?= $levelColors[$lv] ?>;border:1px solid <?= $levelColors[$lv] ?>33;">
                <?= $lv ?>: <?= $counts[$lv] ?>
            </span>
            <?php endforeach ?>
            <?php if ($counts['Unscored']): ?>
            <span style="font-size:11px;color:var(--text-dim);">⚪ Unscored: <?= $counts['Unscored'] ?></span>
            <?php endif ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 400px;gap:20px;align-items:start;">

        <!-- Risk list -->
        <div>
        <?php if (empty($risks)): ?>
        <div class="card">
            <p class="text-muted">No risks found for this audit. Create threat scenarios in Step 5 first.</p>
            <a href="threat_scenarios.php" class="btn" style="margin-top:8px;">▲ Go to Step 5: Threat Scenarios →</a>
        </div>
        <?php else: ?>
        <div class="card" style="padding:0;overflow:hidden;">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                <div class="card-title" style="margin:0;padding:0;border:none;">
                    Risk Register — <?= count($risks) ?> risk<?= count($risks) != 1 ? 's' : '' ?>
                </div>
            </div>
            <?php foreach ($risks as $r): ?>
            <?php $isActive = ($focusRisk && $r['risk_id'] == $focusRisk['risk_id']); ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;
                        background:<?= $isActive ? 'var(--bg-elevated)' : 'transparent' ?>;
                        border-left:3px solid <?= $r['risk_level'] ? ($levelColors[$r['risk_level']] ?? '#444') : '#444' ?>;"
                 onclick="location='risk_register.php?audit_id=<?= $filterAuditId ?>&risk_id=<?= $r['risk_id'] ?>'">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px;">
                    <div>
                        <span style="font-size:10px;font-weight:700;color:<?= $actorColors[$r['actor']] ?? '#fff' ?>">
                            <?= $r['actor'] ?>
                        </span>
                        <span style="font-size:10px;color:var(--text-muted);margin:0 6px;">→</span>
                        <span style="font-size:10px;color:#4a8cff;"><?= $r['access_method'] ?></span>
                        <span style="font-size:10px;color:var(--text-muted);margin:0 6px;">→</span>
                        <span style="font-size:10px;font-weight:700;color:#dc2626;"><?= $r['consequence'] ?></span>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                        <?php if ($r['risk_level']): ?>
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:2px;
                                     background:<?= $r['risk_level']==='Low' ? '#0b1a0b' : ($r['risk_level']==='Medium' ? '#1a1500' : ($r['risk_level']==='High' ? '#1a0b00' : '#1a0000')) ?>;
                                     color:<?= $levelColors[$r['risk_level']] ?>;border:1px solid <?= $levelColors[$r['risk_level']] ?>33;">
                            <?= $r['risk_level'] ?> (<?= number_format($r['risk_score'],1) ?>)
                        </span>
                        <?php else: ?>
                        <span style="font-size:10px;color:var(--text-dim);">Unscored</span>
                        <?php endif ?>
                        <?php if ($r['response']): ?>
                        <span style="font-size:9px;font-weight:700;padding:2px 6px;border-radius:2px;
                                     color:<?= $respColors[$r['response']] ?? '#fff' ?>;
                                     background:<?= $r['response']==='Mitigate' ? '#0b1a0b' : ($r['response']==='Accept' ? '#1a1500' : ($r['response']==='Transfer' ? '#0a1520' : '#1a0000')) ?>;
                                     border:1px solid <?= ($respColors[$r['response']] ?? '#fff') ?>33;">
                            <?= $r['response'] ?>
                        </span>
                        <?php endif ?>
                    </div>
                </div>
                <div style="font-size:11px;color:var(--text-muted);">
                    <?= htmlspecialchars($a['name'] ?? $r['asset_name']) ?>
                    &rsaquo; <?= $r['container_type'] ?>: <?= htmlspecialchars($r['container_name']) ?>
                </div>
                <?php if ($r['scenario_desc']): ?>
                <div style="font-size:11px;color:var(--text-dim);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= htmlspecialchars(substr($r['scenario_desc'], 0, 90)) ?>
                </div>
                <?php endif ?>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
        </div>

        <!-- Risk detail panel -->
        <div>
        <?php if ($focusRisk): ?>
        <div class="card" style="position:sticky;top:20px;">
            <div style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;
                        color:<?= $levelColors[$focusRisk['risk_level']] ?? '#888' ?>;margin-bottom:12px;">
                Risk #<?= $focusRisk['risk_id'] ?>
                <?php if ($focusRisk['risk_level']): ?>
                — <?= $focusRisk['risk_level'] ?> (<?= number_format($focusRisk['risk_score'],1) ?>/25)
                <?php endif ?>
            </div>

            <!-- Scenario summary -->
            <div style="padding:10px;background:var(--bg-elevated);border-radius:3px;margin-bottom:16px;font-size:11px;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                    <span style="color:<?= $actorColors[$focusRisk['actor']] ?? '#fff' ?>;font-weight:700;"><?= $focusRisk['actor'] ?></span>
                    <span style="color:var(--text-dim);">via <?= $focusRisk['access_method'] ?></span>
                    <span style="color:#dc2626;font-weight:700;">→ <?= $focusRisk['consequence'] ?></span>
                </div>
                <div style="color:var(--text-muted);"><?= htmlspecialchars(substr($focusRisk['scenario_desc'] ?? $focusRisk['motive'] ?? '', 0, 140)) ?></div>
            </div>

            <!-- Step 7 Form -->
            <div style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#4a8cff;margin-bottom:8px;">
                Step 7 — Risk Analysis (L×I)
            </div>
            <form method="POST">
                <input type="hidden" name="save_analysis" value="1">
                <input type="hidden" name="risk_id" value="<?= $focusRisk['risk_id'] ?>">
                <div style="margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <label style="font-size:12px;font-weight:600;">CIA Impacted</label>
                    </div>
                    <select name="cia_impacted" style="width:100%;padding:6px 8px;">
                        <?php foreach (['C'=>'Confidentiality','I'=>'Integrity','A'=>'Availability'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($focusRisk['cia_impacted'] ?? 'C') === $k ? 'selected' : '' ?>><?= $k ?> — <?= $v ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <!-- Likelihood -->
                <div style="margin-bottom:12px;padding:10px;background:var(--bg-elevated);border-radius:3px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <label style="font-size:12px;font-weight:600;">Likelihood</label>
                        <span class="font-mono" style="color:#4a8cff;" id="lv"><?= $focusRisk['likelihood'] ?? 3 ?>/5</span>
                    </div>
                    <input type="range" name="likelihood" min="1" max="5" step="1"
                           value="<?= $focusRisk['likelihood'] ?? 3 ?>"
                           oninput="document.getElementById('lv').textContent=this.value+'/5'">
                    <div style="display:flex;justify-content:space-between;font-size:9px;color:var(--text-dim);margin-top:2px;">
                        <span>Very Low</span><span>Low</span><span>Medium</span><span>High</span><span>Very High</span>
                    </div>
                </div>

                <!-- Impact per area -->
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;color:var(--text-dim);margin-bottom:6px;">
                    IMPACT PER AREA <span style="font-weight:400;">(weighted by Step 1 criteria)</span>
                </div>
                <?php
                $impFields = [
                    'impact_reputation'   => ['Reputation',   $criteria['reputation_weight']   ?? 3],
                    'impact_financial'    => ['Financial',    $criteria['financial_weight']    ?? 3],
                    'impact_productivity' => ['Productivity', $criteria['productivity_weight'] ?? 3],
                    'impact_safety'       => ['Safety',       $criteria['safety_weight']       ?? 3],
                    'impact_legal'        => ['Legal',        $criteria['legal_weight']        ?? 3],
                ];
                foreach ($impFields as $field => [$label, $weight]): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span style="font-size:10px;width:80px;flex-shrink:0;color:var(--text-muted);"><?= $label ?></span>
                    <input type="range" name="<?= $field ?>" min="1" max="5" step="1"
                           value="<?= $focusRisk[$field] ?? 3 ?>"
                           oninput="this.nextElementSibling.textContent=this.value"
                           style="flex:1;">
                    <span class="font-mono" style="font-size:11px;width:16px;"><?= $focusRisk[$field] ?? 3 ?></span>
                    <span style="font-size:9px;color:var(--text-dim);width:24px;">w=<?= $weight ?></span>
                </div>
                <?php endforeach ?>
                <button type="submit" class="btn" style="width:100%;font-size:11px;margin-top:4px;">
                    ◆ Calculate & Save Risk Score
                </button>
            </form>

            <!-- Step 8 Form -->
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#22c55e;margin-bottom:8px;">
                    Step 8 — Risk Response
                </div>
                <form method="POST">
                    <input type="hidden" name="save_response" value="1">
                    <input type="hidden" name="risk_id" value="<?= $focusRisk['risk_id'] ?>">
                    <div class="form-group" style="margin-bottom:8px;">
                        <select name="response" style="width:100%;padding:6px 8px;">
                            <?php foreach (['Mitigate','Accept','Transfer','Avoid'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($focusRisk['response'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <textarea name="rationale" rows="2" placeholder="Rationale for this decision..."
                                  style="font-size:12px;"><?= htmlspecialchars($focusRisk['rationale'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <input type="text" name="responsible_owner" placeholder="Responsible owner / contact"
                               value="<?= htmlspecialchars($focusRisk['responsible_owner'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <input type="date" name="target_date"
                               value="<?= $focusRisk['target_date'] ?? '' ?>">
                    </div>
                    <button type="submit" class="btn" style="width:100%;font-size:11px;">✔ Save Risk Response</button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <div class="card">
            <p class="text-muted" style="font-size:12px;">Select a risk from the list to analyze it.</p>
        </div>
        <?php endif ?>

        <!-- Full Table View -->
        <div class="card" style="margin-top:16px;">
            <div class="card-title">Complete Risk Matrix</div>
            <?php if (empty($risks)): ?>
            <p class="text-muted" style="font-size:12px;">No risks yet.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Actor</th><th>Consequence</th><th>CIA</th>
                            <th>L</th><th>Score</th><th>Level</th><th>Response</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($risks as $i => $r): ?>
                    <tr onclick="location='risk_register.php?audit_id=<?= $filterAuditId ?>&risk_id=<?= $r['risk_id'] ?>'"
                        style="cursor:pointer;<?= ($focusRisk && $r['risk_id'] == $focusRisk['risk_id']) ? 'background:var(--bg-elevated)' : '' ?>">
                        <td class="font-mono" style="font-size:11px;"><?= $i+1 ?></td>
                        <td style="font-size:10px;color:<?= $actorColors[$r['actor']] ?? '#fff' ?>"><?= explode(' ', $r['actor'])[0] ?></td>
                        <td style="font-size:10px;color:#dc2626;"><?= $r['consequence'] ?></td>
                        <td style="font-size:10px;font-weight:700;"><?= $r['cia_impacted'] ?></td>
                        <td class="font-mono" style="font-size:11px;"><?= $r['likelihood'] ?? '—' ?></td>
                        <td class="font-mono" style="font-size:11px;font-weight:700;"><?= $r['risk_score'] ? number_format($r['risk_score'],1) : '—' ?></td>
                        <td>
                            <?php if ($r['risk_level']): ?>
                            <span style="font-size:10px;font-weight:700;color:<?= $levelColors[$r['risk_level']] ?>;"><?= $r['risk_level'] ?></span>
                            <?php else: ?>—<?php endif ?>
                        </td>
                        <td>
                            <?php if ($r['response']): ?>
                            <span style="font-size:10px;color:<?= $respColors[$r['response']] ?>;"><?= $r['response'] ?></span>
                            <?php else: ?>—<?php endif ?>
                        </td>
                    </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
        </div>

        </div><!-- /grid -->

        <div style="margin-top:16px;display:flex;gap:8px;">
            <a href="threat_scenarios.php" class="btn btn-ghost">← Step 5: Threat Scenarios</a>
            <a href="reports.php" class="btn" style="margin-left:auto;">View Reports →</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
