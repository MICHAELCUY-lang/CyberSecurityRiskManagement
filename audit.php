<?php
/**
 * audit.php — Security Audit Hub
 * Lists all audits with their status, compliance score, risk level, and quick actions.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Security Audits';
$currentPage = 'audit';

$db   = getAuditDB();
$user = currentUser();

// ── Load audits ──────────────────────────────────────────────
if ($user['role'] === 'admin') {
    $stmt = $db->prepare("
        SELECT a.*, u.name AS auditor_name,
               o.name AS org_name, u2.name AS auditee_name,
               (SELECT COUNT(*) FROM findings f WHERE f.audit_id = a.id) AS finding_count,
               (SELECT COUNT(*) FROM audit_answers aa WHERE aa.audit_id = a.id) AS answer_count,
               (SELECT COUNT(*) FROM evidence e WHERE e.audit_id = a.id) AS evidence_count
        FROM audits a
        JOIN users u ON u.id = a.auditor_id
        LEFT JOIN organizations o ON o.id = a.organization_id
        LEFT JOIN users u2 ON u2.id = a.auditee_id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT a.*, u.name AS auditor_name,
               o.name AS org_name, u2.name AS auditee_name,
               (SELECT COUNT(*) FROM findings f WHERE f.audit_id = a.id) AS finding_count,
               (SELECT COUNT(*) FROM audit_answers aa WHERE aa.audit_id = a.id) AS answer_count,
               (SELECT COUNT(*) FROM evidence e WHERE e.audit_id = a.id) AS evidence_count
        FROM audits a
        JOIN users u ON u.id = a.auditor_id
        LEFT JOIN organizations o ON o.id = a.organization_id
        LEFT JOIN users u2 ON u2.id = a.auditee_id
        WHERE a.auditor_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$user['id']]);
}
$audits = $stmt->fetchAll();

// ── Summary stats ────────────────────────────────────────────
$totalAudits    = count($audits);
$avgCompliance  = $totalAudits > 0 ? round(array_sum(array_column($audits, 'compliance_score')) / $totalAudits, 1) : 0;
$highRiskCount  = count(array_filter($audits, fn($a) => in_array($a['risk_level'], ['High', 'Critical'])));
$completedCount = count(array_filter($audits, fn($a) => (int)$a['answer_count'] > 0));

$levelColors = [
    'Low'      => '#22c55e',
    'Medium'   => '#ffdd55',
    'High'     => '#f97316',
    'Critical' => '#dc2626',
];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Security Audits</h1>
        <div style="display:flex;align-items:center;gap:12px;">
            <span class="breadcrumb">OCTAVE Allegro / Security Audit</span>
            <a href="new_audit.php" class="btn" style="font-size:11px;padding:5px 16px;">+ New Audit</a>
        </div>
    </div>
    <div class="content-area">

        <!-- ── KPI Summary ─────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
            <?php
            $kpis = [
                ['label'=>'Total Audits',      'value'=>$totalAudits,              'color'=>'#fff'],
                ['label'=>'Completed',          'value'=>$completedCount,           'color'=>'#22c55e'],
                ['label'=>'Avg Compliance',     'value'=>$avgCompliance . '%',       'color'=>$avgCompliance >= 80 ? '#4a8cff' : ($avgCompliance >= 50 ? '#f0f0f0' : '#dc2626')],
                ['label'=>'High / Critical Risk','value'=>$highRiskCount,           'color'=>$highRiskCount > 0 ? '#dc2626' : '#22c55e'],
            ];
            foreach ($kpis as $kpi):
            ?>
            <div class="card" style="text-align:center;padding:20px 16px;">
                <div style="font-size:28px;font-weight:800;color:<?= $kpi['color'] ?>;letter-spacing:-.02em;">
                    <?= $kpi['value'] ?>
                </div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-top:4px;">
                    <?= $kpi['label'] ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>

        <!-- ── Audit Table ─────────────────────────────────── -->
        <?php if (empty($audits)): ?>
        <div class="card" style="text-align:center;padding:48px 24px;">
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">
                No audits yet. Start your first security audit.
            </div>
            <a href="new_audit.php" class="btn">+ Start New Audit</a>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;">
                <span>Audit Records (<?= $totalAudits ?>)</span>
                <a href="new_audit.php" class="btn" style="font-size:10px;padding:4px 14px;">+ New</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:32px;">#</th>
                            <th>Organization</th>
                            <th>System</th>
                            <th>Users</th>
                            <th>Date</th>
                            <th style="text-align:center;">Risk</th>
                            <th style="text-align:center;">Compliance</th>
                            <th style="text-align:center;">Checklist</th>
                            <th style="text-align:center;">Findings</th>
                            <th style="text-align:center;">Evidence</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audits as $i => $a):
                            $lv      = $a['risk_level'] ?: 'Low';
                            $lvColor = $levelColors[$lv] ?? '#888';
                            $comp    = (float)$a['compliance_score'];
                            $compColor = $comp >= 80 ? '#4a8cff' : ($comp >= 50 ? '#f0f0f0' : '#dc2626');
                            $answered = (int)$a['answer_count'];
                        ?>
                        <tr>
                            <td class="text-muted font-mono" style="font-size:10px;"><?= $i + 1 ?></td>
                            <td style="font-size:11px;"><?= htmlspecialchars($a['org_name'] ?: '—') ?></td>
                            <td>
                                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($a['system_name']) ?></div>
                                <?php if ($a['description']): ?>
                                <div style="font-size:10px;color:var(--text-dim);margin-top:2px;">
                                    <?= htmlspecialchars(substr($a['description'], 0, 60)) ?><?= strlen($a['description']) > 60 ? '…' : '' ?>
                                </div>
                                <?php endif ?>
                                <?php if ($a['final_opinion']): ?>
                                <span style="font-size:9px;color:var(--chart-blue);border:1px solid var(--chart-blue);padding:1px 4px;border-radius:2px;display:inline-block;margin-top:4px;">
                                    <?= htmlspecialchars($a['final_opinion']) ?>
                                </span>
                                <?php endif ?>
                            </td>
                            <td style="font-size:10px;white-space:nowrap;">
                                <div style="color:var(--text-muted);">Auditor: <?= htmlspecialchars($a['auditor_name']) ?></div>
                                <?php if ($a['auditee_name']): ?>
                                <div style="color:var(--chart-blue);margin-top:2px;">Auditee: <?= htmlspecialchars($a['auditee_name']) ?></div>
                                <?php endif ?>
                            </td>
                            <td style="font-size:11px;color:var(--text-muted);white-space:nowrap;"><?= htmlspecialchars($a['audit_date']) ?></td>
                            <td style="text-align:center;">
                                <?php if ($answered > 0): ?>
                                <span style="font-size:10px;font-weight:700;color:<?= $lvColor ?>;
                                             border:1px solid <?= $lvColor ?>44;border-radius:3px;padding:2px 8px;white-space:nowrap;">
                                    <?= $lv ?>
                                </span>
                                <?php else: ?>
                                <span style="font-size:10px;color:var(--text-dim);">—</span>
                                <?php endif ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($answered > 0): ?>
                                <span style="font-size:13px;font-weight:800;color:<?= $compColor ?>;">
                                    <?= number_format($comp, 1) ?>%
                                </span>
                                <?php else: ?>
                                <span style="font-size:10px;color:var(--text-dim);">Pending</span>
                                <?php endif ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($answered > 0): ?>
                                <span style="font-size:11px;color:#22c55e;font-weight:600;"><?= $answered ?>/10 ✓</span>
                                <?php else: ?>
                                <span style="font-size:10px;color:#ffdd55;">0/10</span>
                                <?php endif ?>
                            </td>
                            <td style="text-align:center;">
                                <span style="font-size:11px;<?= (int)$a['finding_count'] > 0 ? 'color:#dc2626;font-weight:600;' : 'color:var(--text-dim);' ?>">
                                    <?= (int)$a['finding_count'] ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span style="font-size:11px;color:var(--text-muted);"><?= (int)$a['evidence_count'] ?></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:5px;flex-wrap:nowrap;">
                                    <?php if ($answered === 0): ?>
                                    <a href="checklist.php?audit_id=<?= $a['id'] ?>"
                                       class="btn" style="font-size:10px;padding:4px 10px;white-space:nowrap;">
                                        ▶ Start
                                    </a>
                                    <?php else: ?>
                                    <a href="checklist.php?audit_id=<?= $a['id'] ?>"
                                       class="btn btn-ghost" style="font-size:10px;padding:4px 10px;white-space:nowrap;">
                                        ↻ Redo
                                    </a>
                                    <a href="report_detail.php?id=<?= $a['id'] ?>"
                                       class="btn btn-ghost" style="font-size:10px;padding:4px 10px;white-space:nowrap;">
                                        📄 Report
                                    </a>
                                    <a href="ai_analysis.php?audit_id=<?= $a['id'] ?>"
                                       class="btn btn-ghost" style="font-size:10px;padding:4px 10px;white-space:nowrap;">
                                        🤖 AI
                                    </a>
                                    <?php endif ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Audit workflow guide -->
        <div class="card" style="margin-top:16px;">
            <div class="card-title">Audit Workflow</div>
            <div style="display:flex;gap:0;align-items:stretch;">
                <?php
                $wfSteps = [
                    '1. New Audit'   => 'new_audit.php',
                    '2. Checklist'   => '#',
                    '3. Risk & Score'=> '#',
                    '4. Evidence'    => 'evidence.php',
                    '5. AI Analysis' => 'ai_analysis.php',
                    '6. Report'      => 'reports.php',
                ];
                $wi = 0;
                foreach ($wfSteps as $label => $link):
                    $isFirst = $wi === 0;
                    $wi++;
                ?>
                <a href="<?= $link ?>"
                   style="flex:1;text-align:center;padding:10px 8px;border:1px solid var(--border);
                          <?= !$isFirst ? 'border-left:none;' : '' ?>
                          font-size:10px;font-weight:700;letter-spacing:.05em;text-decoration:none;
                          color:var(--text-muted);background:var(--bg-elevated);
                          transition:background .12s;"
                   onmouseover="this.style.background='#222'"
                   onmouseout="this.style.background='var(--bg-elevated)'">
                    <?= htmlspecialchars($label) ?>
                </a>
                <?php endforeach ?>
            </div>
        </div>

        <?php endif ?>

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include __DIR__ . '/partials/footer.php'; ?>
