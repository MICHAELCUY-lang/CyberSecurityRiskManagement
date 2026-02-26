<?php
/**
 * ai_analysis.php — Module 8: AI Security Assistant (Groq API) + History
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'AI Analysis';
$currentPage = 'ai';

$db   = getAuditDB();
$user = currentUser();

$preAuditId  = (int)($_GET['audit_id'] ?? 0);
$viewVersion = (int)($_GET['v'] ?? 0); // 0 = latest
$error   = '';
$success = '';

// Get all audits the user can access
if ($user['role'] === 'admin') {
    $auditList = $db->query("
        SELECT a.id, a.system_name, a.risk_level, a.compliance_score,
               u.name AS auditor_name,
               (SELECT COUNT(*) FROM ai_reports ar WHERE ar.audit_id = a.id) AS ai_count
        FROM audits a JOIN users u ON u.id = a.auditor_id
        ORDER BY a.created_at DESC
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT a.id, a.system_name, a.risk_level, a.compliance_score,
               (SELECT COUNT(*) FROM ai_reports ar WHERE ar.audit_id = a.id) AS ai_count
        FROM audits a WHERE a.auditor_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $auditList = $stmt->fetchAll();
}

// Handle AI generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_audit_id'])) {
    $targetId = (int)$_POST['generate_audit_id'];

    $stmt = $db->prepare("SELECT a.*, u.name AS auditor_name FROM audits a JOIN users u ON u.id = a.auditor_id WHERE a.id = ?");
    $stmt->execute([$targetId]);
    $targetAudit = $stmt->fetch();

    if (!$targetAudit) {
        $error = 'Audit not found.';
    } elseif ($user['role'] !== 'admin' && $targetAudit['auditor_id'] != $user['id']) {
        $error = 'Access denied.';
    } else {
        // Load checklist answers
        $ansStmt = $db->prepare("SELECT question, answer FROM audit_answers WHERE audit_id = ?");
        $ansStmt->execute([$targetId]);
        $answers = $ansStmt->fetchAll();

        // Load findings
        $findStmt = $db->prepare("SELECT finding_text, risk_level, recommendation FROM findings WHERE audit_id = ?");
        $findStmt->execute([$targetId]);
        $findings = $findStmt->fetchAll();

        // Load OCTAVE Allegro data for AI enrichment
        $oaCriteria = $db->prepare("SELECT * FROM risk_criteria WHERE audit_id=? ORDER BY created_at DESC LIMIT 1");
        $oaCriteria->execute([$targetId]); $oaCriteria = $oaCriteria->fetch();

        $oaAssets = $db->prepare("SELECT name, owner_name, rationale, cia_confidentiality, cia_integrity, cia_availability, primary_req FROM assets WHERE audit_id=? ORDER BY created_at");
        $oaAssets->execute([$targetId]); $oaAssets = $oaAssets->fetchAll();

        $oaRisks = $db->prepare("
            SELECT ts.actor, ts.access_method, ts.motive, ts.consequence, ts.description,
                   a.name AS asset_name, c.name AS container_name, c.type AS container_type,
                   r.cia_impacted,
                   ra.likelihood, ra.impact_reputation, ra.impact_financial,
                   ra.impact_productivity, ra.impact_safety, ra.impact_legal,
                   ra.risk_score, ra.risk_level,
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
        $oaRisks->execute([$targetId]); $oaRisks = $oaRisks->fetchAll();

        // Build OCTAVE Allegro context block
        $oaContext = '';

        if ($oaCriteria) {
            $oaContext .= "ORGANIZATIONAL RISK MEASUREMENT CRITERIA (Step 1):\n";
            $oaContext .= "  Reputation: {$oaCriteria['reputation_weight']}/5\n";
            $oaContext .= "  Financial: {$oaCriteria['financial_weight']}/5\n";
            $oaContext .= "  Productivity: {$oaCriteria['productivity_weight']}/5\n";
            $oaContext .= "  Safety: {$oaCriteria['safety_weight']}/5\n";
            $oaContext .= "  Legal/Regulatory: {$oaCriteria['legal_weight']}/5\n\n";
        }

        if (!empty($oaAssets)) {
            $oaContext .= "INFORMATION ASSET PROFILES (Step 2):\n";
            foreach ($oaAssets as $a) {
                $oaContext .= "  Asset: {$a['name']}\n";
                if ($a['owner_name']) $oaContext .= "    Owner: {$a['owner_name']}\n";
                $oaContext .= "    CIA: C={$a['cia_confidentiality']}/5, I={$a['cia_integrity']}/5, A={$a['cia_availability']}/5 (Primary: {$a['primary_req']})\n";
                if ($a['rationale']) $oaContext .= "    Rationale: {$a['rationale']}\n";
            }
            $oaContext .= "\n";
        }

        if (!empty($oaRisks)) {
            $oaContext .= "THREAT SCENARIOS & RISK ANALYSIS (Steps 5-7):\n";
            foreach ($oaRisks as $i => $r) {
                $oaContext .= ($i+1).". [{$r['risk_level']}] Asset: {$r['asset_name']} | Container: {$r['container_type']}: {$r['container_name']}\n";
                $oaContext .= "   Actor: {$r['actor']} | Access: {$r['access_method']} | Consequence: {$r['consequence']} | CIA: {$r['cia_impacted']}\n";
                if ($r['motive']) $oaContext .= "   Motive: {$r['motive']}\n";
                if ($r['description']) $oaContext .= "   Scenario: {$r['description']}\n";
                if ($r['likelihood']) $oaContext .= "   Risk Score: {$r['risk_score']} (L={$r['likelihood']}, Rep={$r['impact_reputation']}, Fin={$r['impact_financial']}, Prod={$r['impact_productivity']}, Safe={$r['impact_safety']}, Legal={$r['impact_legal']})\n";
                if ($r['response']) {
                    $oaContext .= "   Response: {$r['response']}";
                    if ($r['rationale']) $oaContext .= " — {$r['rationale']}";
                    if ($r['responsible_owner']) $oaContext .= " | Owner: {$r['responsible_owner']}";
                    if ($r['target_date']) $oaContext .= " | Target: {$r['target_date']}";
                    $oaContext .= "\n";
                } else {
                    $oaContext .= "   Response: NOT YET SELECTED\n";
                }
            }
            $oaContext .= "\n";
        }

        // Build prompt
        $ansText = '';
        foreach ($answers as $ans) {
            $label = match($ans['answer']) {
                'compliant'     => '✔ Compliant',
                'partial'       => '⚠ Partial',
                'non_compliant' => '✗ Not Compliant',
                default         => $ans['answer'],
            };
            $ansText .= "- {$ans['question']}: {$label}\n";
        }

        $findText = '';
        foreach ($findings as $f) {
            $findText .= "- [{$f['risk_level']}] {$f['finding_text']}\n  Recommendation: {$f['recommendation']}\n";
        }
        if (!$findText) $findText = 'No findings — all controls were marked compliant.';

        $prompt = "You are an expert OCTAVE Allegro cybersecurity risk analyst.\n\n"
            . "Analyze the following OCTAVE Allegro risk assessment data for this system:\n\n"
            . "System: {$targetAudit['system_name']}\n"
            . "Checklist Risk Level: {$targetAudit['risk_level']} (Score: {$targetAudit['risk_score']} / 20)\n"
            . "Compliance Score: {$targetAudit['compliance_score']}%\n\n";

        if ($oaContext) {
            $prompt .= "=== OCTAVE ALLEGRO RISK ASSESSMENT DATA ===\n\n" . $oaContext;
        }

        $prompt .= "=== SECURITY CONTROL AUDIT ===\n\n"
            . "Checklist Results:\n{$ansText}\n"
            . "Audit Findings:\n{$findText}\n\n";

        $prompt .= "Please provide a comprehensive OCTAVE Allegro-grounded analysis in exactly these 5 sections:\n\n"
            . "## 1. SECURITY ANALYSIS\n"
            . "Assess overall risk posture. Reference asset criticality, CIA priorities, and org risk weights if available.\n\n"
            . "## 2. PRIORITY FIXES\n"
            . "The most critical risk scenarios and control gaps that must be addressed immediately (ordered by risk score).\n\n"
            . "## 3. RECOMMENDED IMPROVEMENTS\n"
            . "Medium to long-term security improvements to reduce risk to acceptable levels.\n\n"
            . "## 4. POTENTIAL THREATS\n"
            . "Analysis of the most significant threat scenarios, actors, and consequences — with reference to OCTAVE Allegro findings if available.\n\n"
            . "## 5. BEST PRACTICES\n"
            . "Security best practices to maintain a strong OCTAVE Allegro-aligned security posture.\n\n"
            . "Keep the response clear, structured, and professional. Reference specific threat scenarios and asset names where relevant.";

        // Call Groq API
        $payload = json_encode([
            'model'    => AI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a senior cybersecurity expert specializing in information security audits and risk assessments. Provide actionable, professional security recommendations.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'max_tokens'  => 2048,
            'temperature' => 0.4,
        ]);

        $ch = curl_init(AI_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AI_API_KEY,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $error = "Network error: $curlErr";
        } elseif ($httpCode !== 200) {
            $errData = json_decode($response, true);
            $errMsg  = $errData['error']['message'] ?? $response;
            $error = "AI API error (HTTP $httpCode): " . htmlspecialchars(substr($errMsg, 0, 300));
        } else {
            $data   = json_decode($response, true);
            $aiText = $data['choices'][0]['message']['content'] ?? '';

            if (!$aiText) {
                $error = 'AI returned an empty response. Please try again.';
            } else {
                // Get next version number for this audit
                $verStmt = $db->prepare("SELECT COALESCE(MAX(version), 0) + 1 FROM ai_reports WHERE audit_id = ?");
                $verStmt->execute([$targetId]);
                $nextVer = (int)$verStmt->fetchColumn();

                // Always INSERT — keep full history
                $db->prepare("INSERT INTO ai_reports (audit_id, analysis_text, version) VALUES (?,?,?)")
                   ->execute([$targetId, $aiText, $nextVer]);

                header("Location: ai_analysis.php?audit_id=$targetId&generated=1");
                exit;
            }
        }
    }
}

// Handle delete history entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_history_id']) && $user['role'] === 'admin') {
    $delId = (int)$_POST['delete_history_id'];
    $db->prepare("DELETE FROM ai_reports WHERE id = ?")->execute([$delId]);
    header("Location: ai_analysis.php?audit_id=$preAuditId");
    exit;
}

if (isset($_GET['generated'])) $success = "AI analysis generated successfully.";

// Load selected audit
$viewAudit   = null;
$viewAiReport = null;
$aiHistory   = [];

if ($preAuditId) {
    $stmt = $db->prepare("SELECT a.*, u.name AS auditor_name FROM audits a JOIN users u ON u.id = a.auditor_id WHERE a.id = ?");
    $stmt->execute([$preAuditId]);
    $viewAudit = $stmt->fetch();

    if ($viewAudit) {
        // Load full history ordered newest first
        $histStmt = $db->prepare("SELECT id, version, created_at, analysis_text FROM ai_reports WHERE audit_id = ? ORDER BY version DESC");
        $histStmt->execute([$preAuditId]);
        $aiHistory = $histStmt->fetchAll();

        // Determine which version to show
        if ($viewVersion && $aiHistory) {
            foreach ($aiHistory as $h) {
                if ($h['version'] === $viewVersion || $h['id'] === $viewVersion) {
                    $viewAiReport = $h;
                    break;
                }
            }
        }
        if (!$viewAiReport && !empty($aiHistory)) {
            $viewAiReport = $aiHistory[0]; // latest
        }
    }
}

// Parse AI text into sections
function formatAiSections(string $text): array {
    $sections = [];
    $pattern = '/##\s*\d+\.\s*([A-Z &]+)\n(.*?)(?=\n##\s*\d+\.|$)/s';
    preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
        foreach ($matches as $m) {
            $sections[] = ['title' => trim($m[1]), 'body' => trim($m[2])];
        }
    } else {
        $sections[] = ['title' => 'AI Security Analysis', 'body' => $text];
    }
    return $sections;
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>AI Security Assistant</h1>
        <span class="breadcrumb">Module 8 — Powered by <?= AI_PROVIDER === 'groq' ? 'Groq / ' . AI_MODEL : 'AI API' ?></span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>

        <div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;">

            <!-- ── Left Panel: Audit List ── -->
            <div>
                <div class="card" style="padding:0;overflow:hidden;">
                    <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                        <div class="card-title" style="margin:0;padding:0;border:none;">Audits</div>
                    </div>
                    <?php if (empty($auditList)): ?>
                        <p class="text-muted" style="padding:16px;">No audits yet. <a href="new_audit.php" style="color:#aaa;">Create one →</a></p>
                    <?php else: ?>
                    <?php foreach ($auditList as $a):
                        $isSelected = ($preAuditId === (int)$a['id']);
                    ?>
                    <div style="padding:12px 16px;border-bottom:1px solid var(--border);
                                background:<?= $isSelected ? 'var(--bg-elevated)' : 'transparent' ?>;
                                border-left:3px solid <?= $isSelected ? '#fff' : 'transparent' ?>;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <a href="ai_analysis.php?audit_id=<?= $a['id'] ?>"
                               style="font-size:13px;font-weight:600;color:var(--text);text-decoration:none;">
                                <?= htmlspecialchars($a['system_name']) ?>
                            </a>
                            <span class="badge badge-<?= strtolower($a['risk_level']) ?>"><?= $a['risk_level'] ?></span>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;display:flex;gap:10px;">
                            <span><?= number_format((float)$a['compliance_score'], 1) ?>%</span>
                            <?php if (isset($a['auditor_name'])): ?>
                            <span><?= htmlspecialchars($a['auditor_name']) ?></span>
                            <?php endif ?>
                            <?php if ($a['ai_count'] > 0): ?>
                            <span style="color:#22c55e;">◎ <?= $a['ai_count'] ?> analysis</span>
                            <?php endif ?>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="generate_audit_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn <?= $a['ai_count'] > 0 ? 'btn-ghost' : '' ?>"
                                style="width:100%;font-size:10px;padding:5px;justify-content:center;">
                                <?= $a['ai_count'] > 0 ? '↻ Re-analyze' : '◎ Generate Analysis' ?>
                            </button>
                        </form>
                    </div>
                    <?php endforeach ?>
                    <?php endif ?>
                </div>
            </div>

            <!-- ── Right Panel: AI Report + History ── -->
            <div>
                <?php if ($viewAudit && $viewAiReport):
                    $sections = formatAiSections($viewAiReport['analysis_text']);
                    $sectionIcons  = ['◈','▲','◆','⚠','✦'];
                    $sectionColors = ['var(--chart-blue)','#dc2626','#888','#ffdd55','#22c55e'];
                ?>

                <!-- History Tab Bar -->
                <?php if (count($aiHistory) > 1): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:0;border-bottom:none;border-radius:4px 4px 0 0;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-right:4px;">
                            History:
                        </span>
                        <?php foreach ($aiHistory as $h): ?>
                        <a href="ai_analysis.php?audit_id=<?= $preAuditId ?>&v=<?= $h['id'] ?>"
                           style="font-size:11px;font-weight:<?= $viewAiReport['id'] === $h['id'] ? '700' : '400' ?>;
                                  color:<?= $viewAiReport['id'] === $h['id'] ? '#fff' : 'var(--text-muted)' ?>;
                                  background:<?= $viewAiReport['id'] === $h['id'] ? 'var(--bg-elevated)' : 'transparent' ?>;
                                  text-decoration:none;padding:4px 10px;border:1px solid var(--border);border-radius:2px;
                                  white-space:nowrap;">
                            v<?= $h['version'] ?>
                            <span style="font-weight:400;opacity:.7;"> — <?= date('d M H:i', strtotime($h['created_at'])) ?></span>
                        </a>
                        <?php endforeach ?>
                        <span style="font-size:10px;color:var(--text-dim);margin-left:auto;"><?= count($aiHistory) ?> total</span>
                    </div>
                </div>
                <?php endif ?>

                <!-- AI Report Card -->
                <div class="card" style="<?= count($aiHistory) > 1 ? 'border-top:none;border-radius:0 0 4px 4px;' : '' ?>">
                    <div class="card-title flex-between">
                        <span>
                            <?= htmlspecialchars($viewAudit['system_name']) ?>
                            <span style="font-size:10px;font-weight:400;color:var(--text-dim);margin-left:8px;">
                                v<?= $viewAiReport['version'] ?>
                            </span>
                        </span>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="font-size:11px;color:var(--text-dim);">
                                <?= date('Y-m-d H:i', strtotime($viewAiReport['created_at'])) ?>
                            </span>
                            <?php if ($user['role'] === 'admin'): ?>
                            <form method="POST" onsubmit="return confirm('Delete this analysis version?')" style="display:inline;">
                                <input type="hidden" name="delete_history_id" value="<?= $viewAiReport['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="font-size:10px;padding:3px 10px;">Del</button>
                            </form>
                            <?php endif ?>
                        </div>
                    </div>

                    <!-- Summary bar -->
                    <div style="display:flex;gap:16px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border);flex-wrap:wrap;">
                        <div>
                            <div class="text-muted" style="font-size:10px;margin-bottom:4px;">RISK</div>
                            <span class="badge badge-<?= strtolower($viewAudit['risk_level']) ?>"><?= $viewAudit['risk_level'] ?></span>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:10px;margin-bottom:4px;">COMPLIANCE</div>
                            <strong><?= number_format((float)$viewAudit['compliance_score'], 1) ?>%</strong>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:10px;margin-bottom:4px;">RISK SCORE</div>
                            <strong><?= $viewAudit['risk_score'] ?> / 20</strong>
                        </div>
                        <div style="margin-left:auto;display:flex;gap:8px;">
                            <a href="report_detail.php?id=<?= $viewAudit['id'] ?>"
                               class="btn btn-ghost" style="font-size:10px;padding:5px 12px;">View Full Report</a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="generate_audit_id" value="<?= $viewAudit['id'] ?>">
                                <button type="submit" class="btn btn-ghost" style="font-size:10px;padding:5px 12px;">↻ Re-analyze</button>
                            </form>
                        </div>
                    </div>

                    <!-- Sections -->
                    <?php foreach ($sections as $i => $sec): ?>
                    <div style="border-left:3px solid <?= $sectionColors[$i % count($sectionColors)] ?>;
                                padding:14px 18px;margin-bottom:14px;background:var(--bg-elevated);border-radius:0 3px 3px 0;">
                        <div style="font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;
                                    color:<?= $sectionColors[$i % count($sectionColors)] ?>;margin-bottom:8px;">
                            <?= $sectionIcons[$i % count($sectionIcons)] ?> <?= htmlspecialchars($sec['title']) ?>
                        </div>
                        <div style="font-size:13px;line-height:1.75;white-space:pre-wrap;color:var(--text);">
                            <?= htmlspecialchars($sec['body']) ?>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>

                <!-- History Summary Table -->
                <?php if (count($aiHistory) > 0): ?>
                <div class="card" style="margin-top:16px;">
                    <div class="card-title">Analysis History — <?= htmlspecialchars($viewAudit['system_name']) ?>
                        <span style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:8px;"><?= count($aiHistory) ?> version<?= count($aiHistory) > 1 ? 's' : '' ?></span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Version</th>
                                    <th>Generated</th>
                                    <th>Preview</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aiHistory as $h): ?>
                                <tr <?= $viewAiReport['id'] === $h['id'] ? 'style="background:var(--bg-elevated)"' : '' ?>>
                                    <td class="font-mono">
                                        v<?= $h['version'] ?>
                                        <?php if ($h['id'] === $aiHistory[0]['id']): ?>
                                        <span style="font-size:9px;color:#22c55e;font-weight:700;margin-left:6px;">LATEST</span>
                                        <?php endif ?>
                                    </td>
                                    <td style="font-size:12px;color:var(--text-muted);">
                                        <?= date('Y-m-d H:i:s', strtotime($h['created_at'])) ?>
                                    </td>
                                    <td style="font-size:11px;color:var(--text-muted);max-width:260px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                                        <?= htmlspecialchars(substr(strip_tags($h['analysis_text']), 0, 100)) ?>…
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;">
                                            <a href="ai_analysis.php?audit_id=<?= $preAuditId ?>&v=<?= $h['id'] ?>"
                                               class="btn btn-ghost" style="font-size:10px;padding:3px 10px;">View</a>
                                            <?php if ($user['role'] === 'admin'): ?>
                                            <form method="POST" onsubmit="return confirm('Delete v<?= $h['version'] ?>?')">
                                                <input type="hidden" name="delete_history_id" value="<?= $h['id'] ?>">
                                                <button type="submit" class="btn btn-danger"
                                                        style="font-size:10px;padding:3px 10px;">Del</button>
                                            </form>
                                            <?php endif ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif ?>

                <?php elseif ($viewAudit && empty($aiHistory)): ?>
                <div class="card">
                    <div class="card-title">Generate First Analysis</div>
                    <p class="text-muted">
                        Belum ada AI analysis untuk <strong style="color:var(--text);"><?= htmlspecialchars($viewAudit['system_name']) ?></strong>.
                    </p>
                    <form method="POST" style="margin-top:16px;">
                        <input type="hidden" name="generate_audit_id" value="<?= $viewAudit['id'] ?>">
                        <button type="submit" class="btn">◎ Generate AI Analysis →</button>
                    </form>
                </div>

                <?php else: ?>
                <div class="card">
                    <div class="card-title">AI Security Analysis</div>
                    <p class="text-muted">
                        Pilih audit dari panel kiri, lalu klik <strong style="color:var(--text);">Generate Analysis</strong>
                        untuk mendapatkan analisis keamanan dari <?= strtoupper(AI_PROVIDER) ?>.
                    </p>
                    <div style="margin-top:20px;padding:18px;background:var(--bg-elevated);border-radius:3px;font-size:12px;color:var(--text-muted);line-height:1.8;">
                        <strong style="color:var(--text);">Fitur History:</strong><br>
                        Setiap kali kamu klik <em>Re-analyze</em>, hasil analisis baru akan disimpan sebagai versi baru.
                        Kamu bisa bandingkan atau melihat kembali analisis sebelumnya kapan saja.
                    </div>
                </div>
                <?php endif ?>
            </div>

        </div><!-- /grid -->

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include __DIR__ . '/partials/footer.php'; ?>
