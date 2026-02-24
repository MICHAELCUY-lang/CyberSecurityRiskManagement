<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
$includeCharts = true;

$db = getDB();

// --- KPI Queries ---
$totalAssets = (int)$db->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$totalVulns  = (int)$db->query("SELECT COUNT(*) FROM asset_vulnerabilities")->fetchColumn();
$highCrit    = (int)$db->query("SELECT COUNT(*) FROM asset_vulnerabilities WHERE risk_level IN ('High','Critical')")->fetchColumn();
$totalFindings = (int)$db->query("SELECT COUNT(*) FROM findings")->fetchColumn();

// Compliance: latest score for active org
$orgId = $_SESSION['active_org'] ?? null;
$complianceScore = null;
$complianceStatus = null;
if ($orgId) {
    $stmt = $db->prepare("SELECT score_percentage, status FROM compliance_scores WHERE organization_id = ? ORDER BY calculated_at DESC LIMIT 1");
    $stmt->execute([$orgId]);
    $cs = $stmt->fetch();
    if ($cs) {
        $complianceScore  = (float)$cs['score_percentage'];
        $complianceStatus = $cs['status'];
    }
}

// Risk distribution
$riskDist = $db->query("
    SELECT risk_level, COUNT(*) AS cnt
    FROM asset_vulnerabilities
    GROUP BY risk_level
    ORDER BY FIELD(risk_level,'Low','Medium','High','Critical')
")->fetchAll();

$distMap = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Critical' => 0];
foreach ($riskDist as $r) { $distMap[$r['risk_level']] = (int)$r['cnt']; }
$distTotal = array_sum($distMap) ?: 1; // avoid div by zero

// Priority risk list (top 10)
$topRisks = $db->query("
    SELECT a.asset_name, v.name AS vuln_name, av.likelihood, av.impact, av.risk_score, av.risk_level
    FROM asset_vulnerabilities av
    JOIN assets a ON a.id = av.asset_id
    JOIN vulnerabilities v ON v.id = av.vulnerability_id
    ORDER BY av.risk_score DESC
    LIMIT 10
")->fetchAll();

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <span class="breadcrumb">OCTAVE Allegro / Dashboard</span>
    </div>
    <div class="content-area">

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Total Assets</div>
                <div class="kpi-value"><?= $totalAssets ?></div>
                <div class="kpi-sub">Registered information assets</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Vulnerability Mappings</div>
                <div class="kpi-value"><?= $totalVulns ?></div>
                <div class="kpi-sub">Asset-vulnerability links</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">High / Critical Risks</div>
                <div class="kpi-value <?= $highCrit > 0 ? 'danger' : '' ?>"><?= $highCrit ?></div>
                <div class="kpi-sub">Require immediate action</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Compliance Score</div>
                <div class="kpi-value <?= $complianceScore !== null && $complianceScore < 50 ? 'danger' : ($complianceScore >= 80 ? 'safe' : '') ?>">
                    <?= $complianceScore !== null ? number_format($complianceScore, 1) . '%' : 'N/A' ?>
                </div>
                <div class="kpi-sub"><?= htmlspecialchars($complianceStatus ?? 'No data yet') ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Open Findings</div>
                <div class="kpi-value <?= $totalFindings > 0 ? 'danger' : '' ?>"><?= $totalFindings ?></div>
                <div class="kpi-sub">Auto-generated findings</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

            <!-- Risk Distribution -->
            <div class="card">
                <div class="card-title">Risk Distribution</div>
                <?php foreach ($distMap as $level => $count):
                    $pct = $distTotal > 0 ? round($count / $distTotal * 100) : 0;
                    $color = in_array($level, ['High','Critical']) ? 'red' : 'blue';
                    $levelClass = strtolower($level);
                ?>
                <div style="margin-bottom:14px;">
                    <div class="flex-between mb-1">
                        <span class="badge badge-<?= $levelClass ?>"><?= $level ?></span>
                        <span class="text-muted font-mono"><?= $count ?></span>
                    </div>
                    <div class="sev-bar-wrap">
                        <div class="sev-bar-track">
                            <div class="sev-bar-fill <?= $color ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <div class="sev-bar-label"><?= $pct ?>%</div>
                    </div>
                </div>
                <?php endforeach ?>
                <canvas id="riskChart" height="180" style="margin-top:16px;"></canvas>
            </div>

            <!-- Priority Risks -->
            <div class="card">
                <div class="card-title">Priority Risk List</div>
                <?php if (empty($topRisks)): ?>
                    <p class="text-muted">No risk data yet. Assign vulnerabilities to assets.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Vulnerability</th>
                                <th>Score</th>
                                <th>Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topRisks as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['asset_name']) ?></td>
                                <td style="font-size:12px;"><?= htmlspecialchars($r['vuln_name']) ?></td>
                                <td class="font-mono <?= in_array($r['risk_level'], ['High','Critical']) ? 'text-danger' : 'text-blue' ?>">
                                    <?= $r['risk_score'] ?>
                                </td>
                                <td><span class="badge badge-<?= strtolower($r['risk_level']) ?>"><?= $r['risk_level'] ?></span></td>
                            </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <?php endif ?>
            </div>
        </div>

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
</div><!-- /.layout -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function() {
    const ctx = document.getElementById('riskChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Low', 'Medium', 'High', 'Critical'],
            datasets: [{
                label: 'Vulnerabilities',
                data: [
                    <?= $distMap['Low'] ?>,
                    <?= $distMap['Medium'] ?>,
                    <?= $distMap['High'] ?>,
                    <?= $distMap['Critical'] ?>
                ],
                backgroundColor: [
                    'rgba(37,99,235,0.5)',
                    'rgba(37,99,235,0.7)',
                    'rgba(220,38,38,0.7)',
                    'rgba(139,0,0,0.9)'
                ],
                borderColor: [
                    '#2563eb','#2563eb','#dc2626','#8b0000'
                ],
                borderWidth: 1,
                borderRadius: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    ticks: { color: '#888', font: { size: 11 } },
                    grid: { color: '#2a2a2a' }
                },
                y: {
                    ticks: { color: '#888', font: { size: 11 }, stepSize: 1 },
                    grid: { color: '#2a2a2a' },
                    beginAtZero: true
                }
            }
        }
    });
})();
</script>
</body>
</html>
