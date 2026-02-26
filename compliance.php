<?php
/**
 * compliance.php — Compliance Dashboard
 * Calculates compliance from security_audit.audits table.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle      = 'Compliance';
$currentPage    = 'compliance';
$includeCharts  = true;

$db   = getAuditDB();
$user = currentUser();

// ── Load audits with compliance data ────────────────────────
if ($user['role'] === 'admin') {
    $stmt = $db->prepare("
        SELECT a.id, a.system_name, a.audit_date, a.auditor_id,
               a.risk_score, a.risk_level, a.compliance_score, a.created_at,
               u.name AS auditor_name,
               (SELECT COUNT(*) FROM audit_answers aa WHERE aa.audit_id = a.id) AS answer_count
        FROM audits a
        JOIN users u ON u.id = a.auditor_id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT a.id, a.system_name, a.audit_date, a.auditor_id,
               a.risk_score, a.risk_level, a.compliance_score, a.created_at,
               u.name AS auditor_name,
               (SELECT COUNT(*) FROM audit_answers aa WHERE aa.audit_id = a.id) AS answer_count
        FROM audits a
        JOIN users u ON u.id = a.auditor_id
        WHERE a.auditor_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$user['id']]);
}
$audits = $stmt->fetchAll();

// ── Filter to completed audits only (have answers) ──────────
$completed = array_values(array_filter($audits, fn($a) => (int)$a['answer_count'] > 0));

// ── Aggregate stats ─────────────────────────────────────────
$totalCompleted     = count($completed);
$avgCompliance      = $totalCompleted > 0
    ? round(array_sum(array_column($completed, 'compliance_score')) / $totalCompleted, 1)
    : 0;

$countCompliant     = count(array_filter($completed, fn($a) => (float)$a['compliance_score'] >= 85));
$countNeedsImprove  = count(array_filter($completed, fn($a) => (float)$a['compliance_score'] >= 60 && (float)$a['compliance_score'] < 85));
$countNonCompliant  = count(array_filter($completed, fn($a) => (float)$a['compliance_score'] < 60));

// ── Chart data ───────────────────────────────────────────────
$chartLabels = [];
$chartScores = [];
$chartColors = [];
foreach (array_slice($completed, 0, 15) as $a) {
    $chartLabels[] = strlen($a['system_name']) > 18 ? substr($a['system_name'], 0, 16) . '…' : $a['system_name'];
    $chartScores[] = (float)$a['compliance_score'];
    $chartColors[] = (float)$a['compliance_score'] >= 85 ? 'rgba(74,140,255,0.8)' : 'rgba(220,38,38,0.8)';
}

$levelColors = ['Low'=>'#22c55e','Medium'=>'#ffdd55','High'=>'#f97316','Critical'=>'#dc2626'];

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Compliance</h1>
        <span class="breadcrumb">OCTAVE Allegro / Compliance Dashboard</span>
    </div>
    <div class="content-area">

        <?php if (empty($completed)): ?>
        <div class="alert alert-info">
            No completed audits yet.
            <a href="new_audit.php" style="color:inherit;text-decoration:underline;margin-left:6px;">Start a new audit →</a>
        </div>
        <?php else: ?>

        <!-- ── Overall Score Banner ─────────────────────────── -->
        <div class="card" style="text-align:center;padding:32px 24px;margin-bottom:20px;">
            <div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;">
                Overall Average Compliance Score
            </div>
            <div style="font-size:64px;font-weight:800;letter-spacing:-.03em;
                        color:<?= $avgCompliance >= 85 ? '#4a8cff' : ($avgCompliance >= 60 ? '#f0f0f0' : '#dc2626') ?>;">
                <?= number_format($avgCompliance, 1) ?>%
            </div>
            <div style="margin:8px 0 16px;">
                <?php
                $overallStatus = $avgCompliance >= 85 ? 'Compliant' : ($avgCompliance >= 60 ? 'Needs Improvement' : 'Non-Compliant');
                $badgeClass    = $avgCompliance >= 85 ? 'badge-compliant' : ($avgCompliance >= 60 ? 'badge-partial' : 'badge-non-compliant');
                ?>
                <span class="badge <?= $badgeClass ?>" style="font-size:13px;padding:4px 20px;">
                    <?= $overallStatus ?>
                </span>
            </div>
            <div style="max-width:480px;margin:0 auto;">
                <div class="sev-bar-track" style="height:10px;">
                    <div class="sev-bar-fill <?= $avgCompliance >= 60 ? 'blue' : 'red' ?>"
                         style="width:<?= min($avgCompliance, 100) ?>%"></div>
                </div>
            </div>
            <div style="font-size:11px;color:var(--text-dim);margin-top:12px;">
                Based on <?= $totalCompleted ?> completed audit<?= $totalCompleted !== 1 ? 's' : '' ?>
                &nbsp;·&nbsp;
                <span style="color:#22c55e;"><?= $countCompliant ?> Compliant</span>
                &nbsp;·&nbsp;
                <span style="color:#f0f0f0;"><?= $countNeedsImprove ?> Needs Improvement</span>
                &nbsp;·&nbsp;
                <span style="color:#dc2626;"><?= $countNonCompliant ?> Non-Compliant</span>
            </div>
            <div style="margin-top:16px;font-size:12px;color:var(--text-muted);">
                Thresholds: &nbsp;
                <span class="badge badge-compliant">Compliant = 85%+</span> &nbsp;
                <span class="badge badge-partial">Needs Improvement = 60–84%</span> &nbsp;
                <span class="badge badge-non-compliant">Non-Compliant = below 60%</span>
            </div>
        </div>

        <!-- ── KPI Strip ─────────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;">
            <?php
            $kpis = [
                ['label'=>'Total Audits',     'value'=>count($audits),       'color'=>'#fff'],
                ['label'=>'Completed',         'value'=>$totalCompleted,      'color'=>'#22c55e'],
                ['label'=>'Compliant (≥85%)', 'value'=>$countCompliant,       'color'=>'#4a8cff'],
                ['label'=>'Non-Compliant',    'value'=>$countNonCompliant,    'color'=>$countNonCompliant > 0 ? '#dc2626' : '#22c55e'],
            ];
            foreach ($kpis as $kpi):
            ?>
            <div class="card" style="text-align:center;padding:18px 12px;">
                <div style="font-size:26px;font-weight:800;color:<?= $kpi['color'] ?>;"><?= $kpi['value'] ?></div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-top:4px;">
                    <?= $kpi['label'] ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>

        <!-- ── Chart + Table ─────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

            <!-- Chart -->
            <div class="card">
                <div class="card-title">Compliance Score per Audit</div>
                <canvas id="complianceChart" height="280"></canvas>
            </div>

            <!-- Per-Audit Table -->
            <div class="card">
                <div class="card-title">Per-Audit Breakdown</div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>System</th>
                                <th>Date</th>
                                <th style="text-align:center;">Risk</th>
                                <th style="text-align:center;">Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed as $a):
                                $comp   = (float)$a['compliance_score'];
                                $status = $comp >= 85 ? 'Compliant' : ($comp >= 60 ? 'Needs Impr.' : 'Non-Compliant');
                                $badge  = $comp >= 85 ? 'badge-compliant' : ($comp >= 60 ? 'badge-partial' : 'badge-non-compliant');
                                $lv     = $a['risk_level'] ?: 'Low';
                                $lvClr  = $levelColors[$lv] ?? '#888';
                            ?>
                            <tr>
                                <td>
                                    <a href="report_detail.php?id=<?= $a['id'] ?>"
                                       style="color:var(--text);text-decoration:none;font-size:12px;font-weight:600;">
                                        <?= htmlspecialchars($a['system_name']) ?>
                                    </a>
                                </td>
                                <td style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($a['audit_date']) ?></td>
                                <td style="text-align:center;">
                                    <span style="font-size:10px;font-weight:700;color:<?= $lvClr ?>;
                                                 border:1px solid <?= $lvClr ?>44;border-radius:3px;padding:2px 6px;">
                                        <?= $lv ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-size:12px;font-weight:800;
                                                 color:<?= $comp >= 85 ? '#4a8cff' : ($comp >= 60 ? '#f0f0f0' : '#dc2626') ?>;">
                                        <?= number_format($comp, 1) ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $badge ?>" style="font-size:9px;">
                                        <?= $status ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
        <script>
        (function(){
            const ctx = document.getElementById('complianceChart');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chartLabels) ?>,
                    datasets: [{
                        label: 'Compliance Score (%)',
                        data: <?= json_encode($chartScores) ?>,
                        backgroundColor: <?= json_encode($chartColors) ?>,
                        borderColor: <?= json_encode(array_map(fn($c) => str_replace('0.8', '1', $c), $chartColors)) ?>,
                        borderWidth: 1,
                        borderRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: { ticks: { color: '#888', font:{size:9} }, grid:{ color:'#2a2a2a' } },
                        y: {
                            ticks: { color: '#888', stepSize: 20 },
                            grid: { color:'#2a2a2a' },
                            beginAtZero: true,
                            max: 100
                        }
                    },
                    plugins: {
                        legend: { labels: { color: '#888', font:{size:11} } },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.parsed.y + '%'
                            }
                        }
                    }
                }
            });
        })();
        </script>

        <?php endif ?>

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include __DIR__ . '/partials/footer.php'; ?>
