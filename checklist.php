<?php
/**
 * checklist.php — Modules 2, 3, 4, 5:
 *   Security Checklist + Risk Analysis + Findings Generator + Compliance Score
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Security Checklist';
$currentPage = 'new_audit';

$db      = getAuditDB();
$user    = currentUser();
$auditId = (int)($_GET['audit_id'] ?? 0);

// Validate audit exists and belongs to this user (or admin)
$stmt = $db->prepare("SELECT * FROM audits WHERE id = ?");
$stmt->execute([$auditId]);
$audit = $stmt->fetch();

if (!$audit) {
    echo '<div style="padding:40px;color:#fff;font-family:monospace;">Audit not found. <a href="new_audit.php" style="color:#aaa;">Start a new audit</a></div>';
    exit;
}
if ($user['role'] !== 'admin' && $audit['auditor_id'] != $user['id']) {
    echo '<div style="padding:40px;color:#fff;font-family:monospace;">Access denied.</div>';
    exit;
}

$stmtCtrl = $db->prepare("
    SELECT av.id AS av_id, av.vuln_id, o.vuln_name, o.required_control, a.name AS asset_name 
    FROM asset_vulnerabilities av
    JOIN owasp_library o ON o.id = av.vuln_id
    JOIN assets a ON a.id = av.asset_id
    WHERE a.audit_id = ?
");
$stmtCtrl->execute([$auditId]);
$dynamicControls = $stmtCtrl->fetchAll();

$checklistItems = [];
$findingsMap = [];

if (empty($dynamicControls)) {
    // Fallback to static if no OWASP vulns selected
    $checklistItems = [
        'firewall'         => 'Firewall Configuration',
        'password_policy'  => 'Password Policy Enforcement',
        'patch_updates'    => 'Operating System & Software Patch Updates',
        'access_control'   => 'Access Control Management',
        'encryption'       => 'Data Encryption Usage',
        'backup_recovery'  => 'Backup & Recovery System',
        'logging'          => 'Logging & Monitoring Implementation',
        'antivirus'        => 'Antivirus / Endpoint Protection',
        'network_seg'      => 'Network Segmentation',
        'mfa'              => 'Multi-Factor Authentication',
    ];
    $findingsMap = [
        'firewall' => [
            'non_compliant' => ['text'=>'Firewall is not properly configured','level'=>'High',   'rec'=>'Implement firewall rules, restrict inbound/outbound traffic, and enforce a default-deny posture.'],
            'partial'       => ['text'=>'Firewall configuration is incomplete','level'=>'Medium','rec'=>'Review existing firewall rules, close unnecessary ports, and document all permitted traffic flows.'],
        ],
        'password_policy' => [
            'non_compliant' => ['text'=>'No formal password policy is enforced','level'=>'High',   'rec'=>'Enforce minimum length (12+ chars), complexity requirements, and expiry policies. Implement a password manager.'],
            'partial'       => ['text'=>'Password policy is partially enforced','level'=>'Medium','rec'=>'Ensure all accounts comply with the password policy. Enable account lockout after failed attempts.'],
        ],
        'patch_updates' => [
            'non_compliant' => ['text'=>'Critical patches are not applied','level'=>'Critical','rec'=>'Immediately patch all critical CVEs. Implement automated patch management and a formal SLA.'],
            'partial'       => ['text'=>'System patches are not consistently applied','level'=>'Medium','rec'=>'Implement automated patch management with defined SLAs. Prioritize critical and high patches.'],
        ],
        'access_control' => [
            'non_compliant' => ['text'=>'Access control is not properly managed','level'=>'High',   'rec'=>'Implement role-based access control (RBAC), apply least-privilege principles, and audit access logs.'],
            'partial'       => ['text'=>'Access control has gaps or inconsistencies','level'=>'Medium','rec'=>'Review and reconcile access rights. Remove stale accounts and enforce separation of duties.'],
        ],
        'encryption' => [
            'non_compliant' => ['text'=>'Data encryption is not implemented','level'=>'Critical','rec'=>'Encrypt all sensitive data at rest (AES-256) and in transit (TLS 1.2+). Enforce HTTPS everywhere.'],
            'partial'       => ['text'=>'Encryption is inconsistently applied','level'=>'High',   'rec'=>'Audit all data stores and channels. Encrypt remaining sensitive data and enforce HTTPS headers (HSTS).'],
        ],
        'backup_recovery' => [
            'non_compliant' => ['text'=>'No backup or recovery system is in place','level'=>'Critical','rec'=>'Implement automated daily backups, store offsite/cloud, and test recovery procedures quarterly.'],
            'partial'       => ['text'=>'Backup and recovery procedures are incomplete','level'=>'Medium','rec'=>'Increase backup frequency, test restoration procedures, and document the RTO/RPO targets.'],
        ],
        'logging' => [
            'non_compliant' => ['text'=>'No logging or monitoring is implemented','level'=>'High',   'rec'=>'Deploy a SIEM solution. Log all authentication events, admin actions, and network activity. Configure alerts.'],
            'partial'       => ['text'=>'Logging coverage is insufficient','level'=>'Medium','rec'=>'Expand logging scope to cover all critical systems. Set up automated alerting for anomalous events.'],
        ],
        'antivirus' => [
            'non_compliant' => ['text'=>'Antivirus / endpoint protection is absent','level'=>'High',   'rec'=>'Deploy endpoint detection and response (EDR) on all endpoints. Ensure definitions are auto-updated.'],
            'partial'       => ['text'=>'Endpoint protection is not fully deployed','level'=>'Medium','rec'=>'Extend endpoint protection to all uncovered assets. Configure real-time scanning and scheduled scans.'],
        ],
        'network_seg' => [
            'non_compliant' => ['text'=>'Network segmentation is not implemented','level'=>'High',   'rec'=>'Segment networks using VLANs or micro-segmentation. Isolate critical systems and DMZ from internal networks.'],
            'partial'       => ['text'=>'Network segmentation is incomplete','level'=>'Medium','rec'=>'Review and extend segmentation to remaining critical assets. Verify inter-segment firewall rules.'],
        ],
        'mfa' => [
            'non_compliant' => ['text'=>'Multi-factor authentication is not enabled','level'=>'Critical','rec'=>'Enable MFA for all accounts, especially privileged users and remote access. Use TOTP or hardware tokens.'],
            'partial'       => ['text'=>'MFA is only partially deployed','level'=>'Medium','rec'=>'Extend MFA to all remaining accounts. Prioritize admin and remote-access accounts immediately.'],
        ],
    ];
} else {
    foreach ($dynamicControls as $ctrl) {
        $key = 'av_' . $ctrl['av_id'];
        $checklistItems[$key] = $ctrl['required_control'] . ' [' . $ctrl['asset_name'] . ']';
        $findingsMap[$key] = [
            'non_compliant' => [
                'text'  => $ctrl['vuln_name'] . ' exposure on ' . $ctrl['asset_name'] . ' is unmitigated',
                'level' => 'High',
                'rec'   => $ctrl['required_control']
            ],
            'partial' => [
                'text'  => $ctrl['vuln_name'] . ' exposure mitigation is incomplete on ' . $ctrl['asset_name'],
                'level' => 'Medium',
                'rec'   => 'Ensure full coverage: ' . $ctrl['required_control']
            ]
        ];
    }
}

$error = '';

// ── Handle POST (checklist submission) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = [];
    $valid = true;
    foreach ($checklistItems as $key => $label) {
        $val = $_POST["answer_$key"] ?? '';
        if (!in_array($val, ['compliant','partial','non_compliant', 'not_applicable'])) {
            $valid = false;
            $error = "Please answer all checklist items.";
            break;
        }
        $answers[$key] = $val;
    }

    if ($valid) {
        // Delete old answers for this audit (re-attempt)
        $db->prepare("DELETE FROM audit_answers WHERE audit_id = ?")->execute([$auditId]);
        $db->prepare("DELETE FROM findings      WHERE audit_id = ?")->execute([$auditId]);

        // Module 3: Risk score
        $scoreMap = ['compliant' => 0, 'partial' => 1, 'non_compliant' => 2, 'not_applicable' => 0];
        $riskTotal = 0;

        // Module 5: Compliance score
        $compliantCount = 0;
        $naCount = 0;

        $insAnswer = $db->prepare("INSERT INTO audit_answers (audit_id, question, answer) VALUES (?,?,?)");
        $insFinding = $db->prepare("INSERT INTO findings (audit_id, finding_text, risk_level, recommendation) VALUES (?,?,?,?)");

        foreach ($checklistItems as $key => $label) {
            $ans = $answers[$key];
            $insAnswer->execute([$auditId, $label, $ans]);

            if ($ans === 'not_applicable') {
                $naCount++;
                continue;
            }

            $riskTotal += $scoreMap[$ans];
            if ($ans === 'compliant') $compliantCount++;

            // Module 4: Auto-generate findings
            if ($ans !== 'compliant' && isset($findingsMap[$key][$ans])) {
                $f = $findingsMap[$key][$ans];
                $insFinding->execute([$auditId, $f['text'], $f['level'], $f['rec']]);
            }
        }

        // Risk level
        $riskLevel = match(true) {
            $riskTotal <= 3  => 'Low',
            $riskTotal <= 7  => 'Medium',
            $riskTotal <= 12 => 'High',
            default          => 'Critical',
        };

        // Compliance score
        $totalApplicable = count($checklistItems) - $naCount;
        $complianceScore = $totalApplicable > 0 ? round(($compliantCount / $totalApplicable) * 100, 2) : 100;

        // Update audit record
        $upd = $db->prepare("UPDATE audits SET risk_score=?, risk_level=?, compliance_score=? WHERE id=?");
        $upd->execute([$riskTotal, $riskLevel, $complianceScore, $auditId]);

        header("Location: report_detail.php?id=$auditId");
        exit;
    }
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Security Checklist</h1>
        <span class="breadcrumb">Audit / <?= htmlspecialchars($audit['system_name']) ?></span>
    </div>
    <div class="content-area">

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif ?>

        <!-- Audit Info Recap -->
        <div class="card" style="max-width:900px;">
            <div class="card-title">Audit: <?= htmlspecialchars($audit['system_name']) ?></div>
            <div style="display:grid;grid-template-columns:auto auto auto;gap:16px 32px;font-size:12px;">
                <div><span class="text-muted">Date:</span> <?= htmlspecialchars($audit['audit_date']) ?></div>
                <div><span class="text-muted">Auditor:</span> <?= htmlspecialchars($user['name']) ?></div>
                <div><span class="text-muted">Audit ID:</span> #<?= $auditId ?></div>
            </div>
            <?php if ($audit['description']): ?>
            <p style="font-size:12px;color:var(--text-muted);margin-top:10px;"><?= htmlspecialchars($audit['description']) ?></p>
            <?php endif ?>
        </div>

        <!-- Scoring Legend -->
        <div style="display:flex;gap:16px;margin-bottom:20px;max-width:900px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
                <span style="display:inline-block;width:10px;height:10px;background:#2563eb;border-radius:50%;"></span>
                <strong>Compliant</strong> <span class="text-muted">= 0 points</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
                <span style="display:inline-block;width:10px;height:10px;background:#888;border-radius:50%;"></span>
                <strong>Partial</strong> <span class="text-muted">= 1 point</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
                <span style="display:inline-block;width:10px;height:10px;background:#dc2626;border-radius:50%;"></span>
                <strong>Not Compliant</strong> <span class="text-muted">= 2 points</span>
            </div>
        </div>

        <form method="POST">
            <?php $i = 1; foreach ($checklistItems as $key => $label): ?>
            <div class="card" style="max-width:900px;margin-bottom:12px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;">
                    <div>
                        <div style="font-size:11px;color:var(--text-dim);margin-bottom:4px;font-weight:700;letter-spacing:.08em;">
                            ITEM <?= $i++ ?> OF <?= count($checklistItems) ?>
                        </div>
                        <div style="font-size:14px;font-weight:600;"><?= htmlspecialchars($label) ?></div>
                    </div>
                    <div style="display:flex;gap:8px;flex-shrink:0;">
                        <?php
                        $opts = [
                            'compliant'      => ['label'=>'✔ Compliant',      'style'=>'border-color:#2563eb;color:#6699ff;'],
                            'partial'        => ['label'=>'⚠ Partial',        'style'=>'border-color:#555;color:#aaa;'],
                            'non_compliant'  => ['label'=>'✗ Not Compliant',  'style'=>'border-color:#dc2626;color:#ff6b6b;'],
                            'not_applicable' => ['label'=>'— N/A',            'style'=>'border-color:#444;color:#888;'],
                        ];
                        foreach ($opts as $val => $opt):
                            $inputId = "ans_{$key}_{$val}";
                        ?>
                        <label for="<?= $inputId ?>"
                               style="cursor:pointer;display:flex;align-items:center;gap:6px;
                                      padding:7px 14px;border:1px solid var(--border-light);border-radius:3px;
                                      font-size:11px;font-weight:700;letter-spacing:.06em;white-space:nowrap;
                                      <?= $opt['style'] ?> transition:background .12s;"
                               class="radio-label">
                            <input type="radio" id="<?= $inputId ?>" name="answer_<?= $key ?>"
                                   value="<?= $val ?>" required
                                   style="accent-color:#fff;">
                            <?= $opt['label'] ?>
                        </label>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
            <?php endforeach ?>

            <div style="margin-top:24px;display:flex;gap:12px;max-width:900px;">
                <button type="submit" class="btn">Submit Checklist & Generate Report →</button>
                <a href="new_audit.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
<style>
.radio-label:has(input:checked) {
    background: var(--bg-elevated) !important;
}
</style>
<?php include __DIR__ . '/partials/footer.php'; ?>
