<?php
/**
 * report_detail.php — Module 7: Full Audit Report
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Audit Report';
$currentPage = 'reports';

$db      = getAuditDB();
$user    = currentUser();
$auditId = (int)($_GET['id'] ?? 0);

// Load audit
$stmt = $db->prepare("SELECT a.*, u.name AS auditor_name FROM audits a JOIN users u ON u.id = a.auditor_id WHERE a.id = ?");
$stmt->execute([$auditId]);
$audit = $stmt->fetch();
if (!$audit) { echo '<div style="padding:40px;color:#fff;font-family:monospace;">Report not found.</div>'; exit; }

// Load checklist answers
$answers = $db->prepare("SELECT question, answer FROM audit_answers WHERE audit_id = ? ORDER BY id");
$answers->execute([$auditId]);
$answers = $answers->fetchAll();

// Load findings
$findings = $db->prepare("SELECT finding_text, risk_level, recommendation FROM findings WHERE audit_id = ? ORDER BY FIELD(risk_level,'Critical','High','Medium','Low')");
$findings->execute([$auditId]);
$findings = $findings->fetchAll();

// Load evidence
$evidence = $db->prepare("SELECT id, file_path, description, uploaded_at FROM evidence WHERE audit_id = ?");
$evidence->execute([$auditId]);
$evidence = $evidence->fetchAll();

// Load AI report (latest version)
$aiReport = $db->prepare("SELECT analysis_text, created_at FROM ai_reports WHERE audit_id = ? ORDER BY version DESC LIMIT 1");
$aiReport->execute([$auditId]);
$aiReport = $aiReport->fetch();

// ── OCTAVE Allegro Data ──
$oaCriteria = $db->prepare("SELECT * FROM risk_criteria WHERE audit_id=? ORDER BY created_at DESC LIMIT 1");
$oaCriteria->execute([$auditId]); $oaCriteria = $oaCriteria->fetch();

$oaAssets = $db->prepare("SELECT * FROM assets WHERE audit_id=? ORDER BY created_at");
$oaAssets->execute([$auditId]); $oaAssets = $oaAssets->fetchAll();

$oaRisks = $db->prepare("
    SELECT r.id AS risk_id, r.cia_impacted,
           ts.actor, ts.access_method, ts.motive, ts.consequence, ts.description AS scenario_desc,
           ac.description AS concern_desc,
           c.name AS container_name, c.type AS container_type,
           a.name AS asset_name,
           ra.likelihood, ra.risk_score, ra.risk_level,
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
$oaRisks->execute([$auditId]); $oaRisks = $oaRisks->fetchAll();

// Compliance rating label
function complianceRating(float $score): string {
    if ($score >= 90) return 'Excellent';
    if ($score >= 75) return 'Good';
    if ($score >= 50) return 'Needs Improvement';
    return 'Poor';
}

// Handle admin delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_audit']) && $user['role'] === 'admin') {
    $db->prepare("DELETE FROM audits WHERE id = ?")->execute([$auditId]);
    header('Location: reports.php?deleted=1');
    exit;
}

// Handle Final Opinion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_opinion'])) {
    $opinion = $_POST['final_opinion'];
    $db->prepare("UPDATE audits SET final_opinion = ? WHERE id = ?")->execute([$opinion, $auditId]);
    header("Location: report_detail.php?id=$auditId&saved=1");
    exit;
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Audit Report</h1>
        <div style="display:flex;align-items:center;gap:12px;">
            <span class="breadcrumb">Reports / #<?= $auditId ?></span>
            <?php if ($user['role'] === 'admin'): ?>
            <form method="POST" onsubmit="return confirm('Delete this audit report permanently?')">
                <input type="hidden" name="delete_audit" value="1">
                <button type="submit" class="btn btn-danger" style="font-size:10px;padding:5px 12px;">Delete Report</button>
            </form>
            <?php endif ?>
        </div>
    </div>
    <div class="content-area">

        <!-- 1. Audit Information -->
        <div class="card">
            <div class="card-title">1. Audit Information</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
                <div>
                    <div class="text-muted" style="font-size:10px;letter-spacing:.08em;margin-bottom:4px;">SYSTEM</div>
                    <div style="font-size:16px;font-weight:700;"><?= htmlspecialchars($audit['system_name']) ?></div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;letter-spacing:.08em;margin-bottom:4px;">AUDIT DATE</div>
                    <div><?= htmlspecialchars($audit['audit_date']) ?></div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;letter-spacing:.08em;margin-bottom:4px;">AUDITOR</div>
                    <div><?= htmlspecialchars($audit['auditor_name']) ?></div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;letter-spacing:.08em;margin-bottom:4px;">REPORT DATE</div>
                    <div><?= date('Y-m-d', strtotime($audit['created_at'])) ?></div>
                </div>
            </div>
            <?php if ($audit['description']): ?>
            <p style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted);">
                <?= nl2br(htmlspecialchars($audit['description'])) ?>
            </p>
            <?php endif ?>
            
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);" class="no-print">
                <div class="text-muted" style="font-size:10px;letter-spacing:.08em;margin-bottom:8px;">FINAL AUDIT OPINION</div>
                <?php if ($user['role'] === 'admin' || $user['id'] == $audit['auditor_id']): ?>
                <form method="POST" style="display:flex;gap:10px;align-items:center;">
                    <select name="final_opinion" style="font-size:12px;padding:4px 8px;min-width:200px;">
                        <option value="">-- Select Opinion --</option>
                        <option value="Unqualified" <?= ($audit['final_opinion']??'') === 'Unqualified' ? 'selected' : '' ?>>Unqualified (Clean)</option>
                        <option value="Qualified" <?= ($audit['final_opinion']??'') === 'Qualified' ? 'selected' : '' ?>>Qualified (Modified)</option>
                        <option value="Adverse" <?= ($audit['final_opinion']??'') === 'Adverse' ? 'selected' : '' ?>>Adverse</option>
                        <option value="Disclaimer" <?= ($audit['final_opinion']??'') === 'Disclaimer' ? 'selected' : '' ?>>Disclaimer of Opinion</option>
                    </select>
                    <button type="submit" class="btn" style="font-size:10px;padding:4px 12px;">Save Opinion</button>
                    <?php if (isset($_GET['saved'])): ?><span style="color:#22c55e;font-size:11px;">✔ Saved</span><?php endif ?>
                </form>
                <?php endif ?>
            </div>
            
            <?php if (!empty($audit['final_opinion'])): ?>
            <div class="print-only" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);display:block;">
                <div class="text-muted" style="font-size:10px;letter-spacing:.08em;margin-bottom:8px;">FINAL AUDIT OPINION</div>
                <div style="font-size:16px;font-weight:700;color:var(--chart-blue);">
                    <?= htmlspecialchars($audit['final_opinion']) ?> Opinion
                </div>
            </div>
            <?php endif ?>
        </div>

        <!-- 2. Risk + Compliance Summary -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="card">
                <div class="card-title">2. Risk Assessment</div>
                <div style="display:flex;align-items:center;gap:24px;">
                    <div>
                        <div class="text-muted" style="font-size:10px;margin-bottom:4px;">RISK SCORE</div>
                        <div style="font-size:48px;font-weight:700;line-height:1;
                            color:<?= in_array($audit['risk_level'],['High','Critical']) ? '#dc2626' : '#f0f0f0' ?>;">
                            <?= $audit['risk_score'] ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-dim);margin-top:4px;">out of 20</div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:10px;margin-bottom:8px;">RISK LEVEL</div>
                        <span class="badge badge-<?= strtolower($audit['risk_level']) ?>"
                              style="font-size:14px;padding:6px 16px;">
                            <?= $audit['risk_level'] ?>
                        </span>
                        <div style="font-size:11px;color:var(--text-dim);margin-top:8px;">
                            0–3: Low │ 4–7: Medium │ 8–12: High │ 13+: Critical
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-title">3. Compliance Score</div>
                <div style="display:flex;align-items:center;gap:24px;">
                    <div>
                        <div class="text-muted" style="font-size:10px;margin-bottom:4px;">SCORE</div>
                        <?php $score = (float)$audit['compliance_score']; ?>
                        <div style="font-size:48px;font-weight:700;line-height:1;
                            color:<?= $score < 50 ? '#dc2626' : ($score >= 80 ? '#2563eb' : '#f0f0f0') ?>;">
                            <?= number_format($score, 1) ?>%
                        </div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:10px;margin-bottom:8px;">RATING</div>
                        <div style="font-size:16px;font-weight:700;">
                            <?= complianceRating($score) ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-dim);margin-top:8px;">
                            ≥90%: Excellent │ ≥75%: Good<br>≥50%: Needs Improvement │ &lt;50%: Poor
                        </div>
                    </div>
                </div>
                </div>
                <!-- Compliance bar -->
                <div class="sev-bar-wrap" style="margin-top:16px;">
                    <div class="sev-bar-track" style="height:8px;">
                        <div class="sev-bar-fill <?= $score < 50 ? 'red' : 'blue' ?>"
                             style="width:<?= $score ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 3.5 Risk Matrix -->
        <?php
        $matrix = [3=>[1=>0,2=>0,3=>0], 2=>[1=>0,2=>0,3=>0], 1=>[1=>0,2=>0,3=>0]];
        foreach ($oaRisks as $r) {
            $l = (int)($r['likelihood'] ?? 1);
            if ($l < 1) $l = 1; if ($l > 3) $l = 3;
            $score = (float)$r['risk_score'];
            $iVal = $score / ($l ?: 1); // Extract impact from total risk score
            $i = $iVal <= 5 ? 1 : ($iVal <= 10 ? 2 : 3);
            $matrix[$l][$i]++;
        }
        ?>
        <div class="card" style="margin-top:20px;">
            <div class="card-title">3.5 Risk Matrix (3x3)</div>
            <div style="display:flex;align-items:center;justify-content:center;padding:20px;">
                <table style="border-collapse:collapse;text-align:center;width:300px;">
                    <tr>
                        <td rowspan="4" style="writing-mode:vertical-rl;transform:rotate(180deg);font-size:10px;font-weight:700;color:var(--text-muted);border:none;">LIKELIHOOD</td>
                        <td style="height:60px;width:60px;font-size:10px;color:var(--text-muted);">High (3)</td>
                        <td style="background:#ffdd55;color:#000;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[3][1] ?: '' ?></td>
                        <td style="background:#f97316;color:#fff;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[3][2] ?: '' ?></td>
                        <td style="background:#dc2626;color:#fff;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[3][3] ?: '' ?></td>
                    </tr>
                    <tr>
                        <td style="height:60px;width:60px;font-size:10px;color:var(--text-muted);">Med (2)</td>
                        <td style="background:#22c55e;color:#fff;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[2][1] ?: '' ?></td>
                        <td style="background:#ffdd55;color:#000;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[2][2] ?: '' ?></td>
                        <td style="background:#f97316;color:#fff;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[2][3] ?: '' ?></td>
                    </tr>
                    <tr>
                        <td style="height:60px;width:60px;font-size:10px;color:var(--text-muted);">Low (1)</td>
                        <td style="background:#22c55e;color:#fff;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[1][1] ?: '' ?></td>
                        <td style="background:#22c55e;color:#fff;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[1][2] ?: '' ?></td>
                        <td style="background:#ffdd55;color:#000;font-weight:700;border:1px solid var(--border); font-size:18px;"><?= $matrix[1][3] ?: '' ?></td>
                    </tr>
                    <tr>
                        <td style="border:none;"></td>
                        <td style="font-size:10px;color:var(--text-muted);padding-top:8px;border:none;">Low (1)</td>
                        <td style="font-size:10px;color:var(--text-muted);padding-top:8px;border:none;">Med (2)</td>
                        <td style="font-size:10px;color:var(--text-muted);padding-top:8px;border:none;">High (3)</td>
                    </tr>
                    <tr>
                        <td colspan="5" style="font-size:10px;font-weight:700;color:var(--text-muted);border:none;padding-top:12px;">IMPACT</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 4. Checklist Results -->
        <div class="card">
            <div class="card-title">4. Checklist Results</div>
            <?php if (empty($answers)): ?>
                <p class="text-muted">No checklist data recorded.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>Control Item</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($answers as $i => $ans): ?>
                        <tr>
                            <td class="text-muted font-mono" style="width:40px;"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($ans['question']) ?></td>
                            <td>
                                <?php
                                $badgeClass = match($ans['answer']) {
                                    'compliant'     => 'badge-compliant',
                                    'partial'       => 'badge-partial',
                                    'non_compliant' => 'badge-non-compliant',
                                    default         => 'badge-na',
                                };
                                $label = match($ans['answer']) {
                                    'compliant'     => '✔ Compliant',
                                    'partial'       => '⚠ Partial',
                                    'non_compliant' => '✗ Not Compliant',
                                    default         => 'N/A',
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>

        <!-- 5. Findings -->
        <div class="card">
            <div class="card-title">5. Audit Findings & Recommendations</div>
            <?php if (empty($findings)): ?>
                <p class="text-muted" style="color:#22c55e;">✔ No findings generated — all controls are compliant.</p>
            <?php else: ?>
            <?php foreach ($findings as $f): ?>
            <div style="border-left:3px solid <?= in_array($f['risk_level'],['High','Critical']) ? '#dc2626' : '#2563eb' ?>;
                        padding:12px 16px;margin-bottom:12px;background:var(--bg-elevated);border-radius:0 3px 3px 0;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                    <span class="badge badge-<?= strtolower($f['risk_level']) ?>"><?= $f['risk_level'] ?></span>
                    <strong style="font-size:13px;"><?= htmlspecialchars($f['finding_text']) ?></strong>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">
                    <strong>Recommendation:</strong> <?= htmlspecialchars($f['recommendation']) ?>
                </div>
            </div>
            <?php endforeach ?>
            <?php endif ?>
        </div>

        <!-- 6. Evidence -->
        <div class="card">
            <div class="card-title flex-between">
                <span>6. Evidence</span>
                <a href="evidence.php?audit_id=<?= $auditId ?>" class="btn btn-ghost" style="font-size:10px;padding:4px 12px;">+ Upload</a>
            </div>
            <?php if (empty($evidence)): ?>
                <p class="text-muted">No evidence uploaded yet.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>File</th><th>Description</th><th>Uploaded</th></tr></thead>
                    <tbody>
                        <?php foreach ($evidence as $ev): ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($ev['file_path']) ?>" target="_blank"
                                   style="color:var(--chart-blue);text-decoration:none;">
                                    📎 <?= htmlspecialchars(basename($ev['file_path'])) ?>
                                </a>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($ev['description'] ?? '—') ?></td>
                            <td style="font-size:12px;color:var(--text-dim);"><?= date('Y-m-d', strtotime($ev['uploaded_at'])) ?></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>

        <!-- 7. AI Report -->
        <div class="card">
            <div class="card-title flex-between">
                <span>7. AI Security Analysis</span>
                <?php if (!$aiReport): ?>
                <a href="ai_analysis.php?audit_id=<?= $auditId ?>" class="btn" style="font-size:10px;padding:5px 14px;">◎ Generate AI Analysis</a>
                <?php endif ?>
            </div>
            <?php if ($aiReport): ?>
                <div style="font-size:12px;color:var(--text-dim);margin-bottom:12px;">
                    Generated: <?= date('Y-m-d H:i', strtotime($aiReport['created_at'])) ?>
                </div>
                <div style="font-size:13px;line-height:1.7;white-space:pre-wrap;color:var(--text);">
                    <?= htmlspecialchars($aiReport['analysis_text']) ?>
                </div>
            <?php else: ?>
                <p class="text-muted">AI analysis not yet generated for this audit.</p>
            <?php endif ?>
        </div>

        <div style="margin-top:8px;display:flex;gap:12px;" class="no-print">
            <a href="reports.php" class="btn btn-ghost">← All Reports</a>
            <?php if (!$aiReport): ?>
            <a href="ai_analysis.php?audit_id=<?= $auditId ?>" class="btn">◎ Generate AI Analysis</a>
            <?php endif ?>
            <a href="risk_register.php?audit_id=<?= $auditId ?>" class="btn btn-ghost">◆ Risk Register</a>
            <button onclick="window.print()" class="btn btn-ghost">🖨 Print Report</button>
            <button onclick="exportToPDF()" class="btn btn-ghost" style="color:#dc2626;">📄 Export PDF</button>
        </div>

        <?php if ($oaCriteria || !empty($oaAssets) || !empty($oaRisks)): ?>
        <div style="margin-top:24px;padding-top:16px;border-top:2px solid #1a3a5c;">
            <div style="font-size:10px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#4a8cff;margin-bottom:16px;">
                ⬡ OCTAVE ALLEGRO RISK ASSESSMENT
            </div>

            <!-- OA Step 1: Risk Criteria -->
            <?php if ($oaCriteria): ?>
            <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:16px;">
                <div class="card-title">OA Step 1 — Risk Measurement Criteria</div>
                <?php
                $criteriaAreas = [
                    'Reputation'   => $oaCriteria['reputation_weight'],
                    'Financial'    => $oaCriteria['financial_weight'],
                    'Productivity' => $oaCriteria['productivity_weight'],
                    'Safety'       => $oaCriteria['safety_weight'],
                    'Legal'        => $oaCriteria['legal_weight'],
                ];
                arsort($criteriaAreas);
                ?>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;">
                <?php foreach ($criteriaAreas as $area => $w): ?>
                <div style="text-align:center;padding:10px;background:var(--bg-elevated);border-radius:3px;">
                    <div style="font-size:10px;color:var(--text-dim);margin-bottom:4px;"><?= $area ?></div>
                    <div style="font-size:20px;font-weight:800;color:<?= $w>=4?'#dc2626':($w>=3?'#ffdd55':'#22c55e') ?>;"><?= $w ?></div>
                    <div style="font-size:9px;color:var(--text-dim);">/ 5</div>
                </div>
                <?php endforeach ?>
                </div>
                <?php if ($oaCriteria['notes']): ?>
                <p style="margin-top:10px;font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($oaCriteria['notes']) ?></p>
                <?php endif ?>
            </div>
            <?php endif ?>

            <!-- OA Step 2: Asset Profiles -->
            <?php if (!empty($oaAssets)): ?>
            <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:16px;">
                <div class="card-title">OA Step 2 — Information Asset Profiles (<?= count($oaAssets) ?>)</div>
                <?php foreach ($oaAssets as $a): ?>
                <div style="padding:12px;background:var(--bg-elevated);border-radius:3px;margin-bottom:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px;">
                        <div>
                            <strong style="font-size:13px;"><?= htmlspecialchars($a['name']) ?></strong>
                            <?php if ($a['owner_name']): ?>
                            <span style="font-size:11px;color:var(--text-dim);margin-left:10px;">Owner: <?= htmlspecialchars($a['owner_name']) ?></span>
                            <?php endif ?>
                        </div>
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;background:#0d1a2d;color:#4a8cff;border:1px solid #1a3a5c;border-radius:2px;">
                            Primary: <?= $a['primary_req'] ?>
                        </span>
                    </div>
                    <div style="display:flex;gap:10px;font-size:11px;">
                        <span style="color:#4a8cff;">C: <?= $a['cia_confidentiality'] ?>/5</span>
                        <span style="color:#22c55e;">I: <?= $a['cia_integrity'] ?>/5</span>
                        <span style="color:#ffdd55;">A: <?= $a['cia_availability'] ?>/5</span>
                        <?php if ($a['rationale']): ?>
                        <span style="color:var(--text-dim);margin-left:8px;"><?= htmlspecialchars(substr($a['rationale'],0,120)) ?></span>
                        <?php endif ?>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>

            <!-- OA Steps 6-7: Risk Matrix -->
            <?php if (!empty($oaRisks)): ?>
            <?php
            $lvColors = ['Low'=>'#22c55e','Medium'=>'#ffdd55','High'=>'#f97316','Critical'=>'#dc2626'];
            $actColors = ['Internal Human'=>'#ffdd55','External Human'=>'#dc2626','System'=>'#4a8cff','Natural'=>'#22c55e'];
            $respColors = ['Mitigate'=>'#22c55e','Accept'=>'#ffdd55','Transfer'=>'#4a8cff','Avoid'=>'#dc2626'];
            ?>
            <div class="card" style="border-left:3px solid #4a8cff;margin-bottom:16px;">
                <div class="card-title">OA Steps 5–7 — Threat Scenarios & Risk Matrix (<?= count($oaRisks) ?> risks)</div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th><th>Asset</th><th>Container</th>
                                <th>Actor</th><th>Access</th><th>Consequence</th>
                                <th>CIA</th><th>L</th><th>Score</th><th>Level</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($oaRisks as $i => $r): ?>
                        <tr>
                            <td class="font-mono" style="font-size:10px;"><?= $i+1 ?></td>
                            <td style="font-size:11px;"><?= htmlspecialchars($r['asset_name']) ?></td>
                            <td style="font-size:10px;color:var(--text-muted);"><?= htmlspecialchars($r['container_name']) ?></td>
                            <td style="font-size:10px;color:<?= $actColors[$r['actor']] ?? '#fff' ?>;"><?= explode(' ',$r['actor'])[0] ?></td>
                            <td style="font-size:10px;color:#4a8cff;"><?= $r['access_method'] ?></td>
                            <td style="font-size:10px;color:#dc2626;font-weight:700;"><?= $r['consequence'] ?></td>
                            <td style="font-size:11px;font-weight:700;"><?= $r['cia_impacted'] ?></td>
                            <td class="font-mono" style="font-size:11px;"><?= $r['likelihood'] ?? '—' ?></td>
                            <td class="font-mono" style="font-size:11px;font-weight:700;"><?= $r['risk_score'] ? number_format($r['risk_score'],1) : '—' ?></td>
                            <td>
                                <?php if ($r['risk_level']): ?>
                                <span style="font-size:10px;font-weight:700;color:<?= $lvColors[$r['risk_level']] ?>"><?= $r['risk_level'] ?></span>
                                <?php else: ?>—<?php endif ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- OA Step 8: Risk Responses -->
            <?php $responded = array_filter($oaRisks, fn($r) => !empty($r['response'])); ?>
            <?php if (!empty($responded)): ?>
            <div class="card" style="border-left:3px solid #22c55e;margin-bottom:16px;">
                <div class="card-title">OA Step 8 — Risk Response Decisions (<?= count($responded) ?>/<?= count($oaRisks) ?>)</div>
                <?php foreach ($responded as $r): ?>
                <div style="padding:12px;background:var(--bg-elevated);border-radius:3px;margin-bottom:8px;
                            border-left:3px solid <?= $respColors[$r['response']] ?? '#888' ?>;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:12px;font-weight:700;"><?= htmlspecialchars($r['asset_name']) ?>
                            <span style="font-size:10px;color:var(--text-dim);font-weight:400;"> — <?= $r['consequence'] ?></span>
                        </span>
                        <span style="font-size:11px;font-weight:700;padding:2px 10px;border-radius:2px;
                                     color:<?= $respColors[$r['response']] ?>;
                                     background:<?= $r['response']==='Mitigate'?'#0b1a0b':($r['response']==='Accept'?'#1a1500':($r['response']==='Transfer'?'#0a1520':'#1a0000')) ?>;
                                     border:1px solid <?= ($respColors[$r['response']]) ?>33;">
                            <?= $r['response'] ?>
                        </span>
                    </div>
                    <?php if ($r['rationale']): ?><div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;"><?= htmlspecialchars($r['rationale']) ?></div><?php endif ?>
                    <div style="font-size:11px;color:var(--text-dim);display:flex;gap:16px;">
                        <?php if ($r['responsible_owner']): ?><span>Owner: <?= htmlspecialchars($r['responsible_owner']) ?></span><?php endif ?>
                        <?php if ($r['target_date']): ?><span>Target: <?= $r['target_date'] ?></span><?php endif ?>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
            <?php endif ?>

        </div><!-- /oa section -->
        <?php endif ?>


    </div><!-- /.content-area -->
</div><!-- /.main-content -->

<!-- Include html2pdf.js for native PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function exportToPDF() {
    // Hide purely interactive UI for export
    const noPrintElems = document.querySelectorAll('.no-print');
    noPrintElems.forEach(el => el.style.display = 'none');
    
    // Un-hide print only elems
    const printOnlyElems = document.querySelectorAll('.print-only');
    printOnlyElems.forEach(el => el.style.display = 'block');

    const opt = {
      margin:       0.5,
      filename:     'Audit_Report_<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9]+/', '_', $audit['system_name'])) ?>.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    
    const element = document.querySelector('.content-area');
    html2pdf().set(opt).from(element).save().then(() => {
        // Restore interactive UI
        noPrintElems.forEach(el => el.style.display = '');
        printOnlyElems.forEach(el => el.style.display = 'none');
    });
}
</script>
<style>
.print-only { display: none; }
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    .content-area { max-width: 100%; margin: 0; }
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
}
</style>
<?php include __DIR__ . '/partials/footer.php'; ?>
