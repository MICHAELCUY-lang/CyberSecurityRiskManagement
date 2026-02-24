<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$pageTitle   = 'Compliance';
$currentPage = 'compliance';
$includeCharts = true;
$db = getDB();

$activeOrg = (int)($_SESSION['active_org'] ?? 0);

// Org list for selector
$orgs = $db->query("SELECT id, name FROM organizations ORDER BY name")->fetchAll();

// Compliance score for active org
$compScore  = null;
$compStatus = null;
if ($activeOrg) {
    $stmt = $db->prepare("SELECT score_percentage, status, calculated_at FROM compliance_scores WHERE organization_id=?");
    $stmt->execute([$activeOrg]);
    $compScore = $stmt->fetch();
}

// Per-asset breakdown
$assetBreakdown = [];
if ($activeOrg) {
    $stmt = $db->prepare("
        SELECT a.id, a.asset_name,
               SUM(CASE WHEN ar.status='compliant' THEN 1 ELSE 0 END) AS cnt_compliant,
               SUM(CASE WHEN ar.status='partial' THEN 1 ELSE 0 END) AS cnt_partial,
               SUM(CASE WHEN ar.status='non_compliant' THEN 1 ELSE 0 END) AS cnt_non_compliant,
               SUM(CASE WHEN ar.status='not_applicable' THEN 1 ELSE 0 END) AS cnt_na,
               COUNT(CASE WHEN ar.status != 'not_applicable' THEN 1 END) AS cnt_total
        FROM assets a
        LEFT JOIN audit_results ar ON ar.asset_id = a.id
        WHERE a.organization_id = ?
        GROUP BY a.id, a.asset_name
        ORDER BY a.asset_name
    ");
    $stmt->execute([$activeOrg]);
    $assetBreakdown = $stmt->fetchAll();
}

// Build chart data
$chartLabels = [];
$chartCompliant = [];
$chartNonCompliant = [];
foreach ($assetBreakdown as $ab) {
    $chartLabels[]      = $ab['asset_name'];
    $chartCompliant[]   = (int)$ab['cnt_compliant'];
    $chartNonCompliant[]= (int)$ab['cnt_non_compliant'];
}

$orgName = '';
if ($activeOrg) {
    $r = $db->prepare("SELECT name FROM organizations WHERE id=?");
    $r->execute([$activeOrg]);
    $orgName = $r->fetchColumn() ?: '';
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Compliance</h1>
        <span class="breadcrumb">OCTAVE Allegro / Compliance</span>
    </div>
    <div class="content-area">

        <?php if (!$activeOrg): ?>
        <div class="alert alert-info">
            Select an active organization first.
            <a href="organization.php" style="color:inherit;text-decoration:underline;">Go to Organization</a>
        </div>
        <?php else: ?>

        <!-- Overall Score Banner -->
        <?php if ($compScore): ?>
        <?php
        $pct  = (float)$compScore['score_percentage'];
        $stat = $compScore['status'];
        $bannerClass = $pct >= 80 ? 'alert-info' : ($pct >= 50 ? 'alert-success' : 'alert-error');
        ?>
        <div class="card" style="text-align:center;padding:32px 24px;">
            <div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;">
                Overall Compliance Score — <?= htmlspecialchars($orgName) ?>
            </div>
            <div style="font-size:64px;font-weight:800;letter-spacing:-.03em;
                        color:<?= $pct>=80 ? '#2563eb' : ($pct>=50 ? '#f0f0f0' : '#dc2626') ?>;">
                <?= number_format($pct, 1) ?>%
            </div>
            <div style="margin:8px 0 16px;">
                <span class="badge badge-<?= $pct>=80 ? 'compliant' : ($pct>=50 ? 'partial' : 'non-compliant') ?>"
                      style="font-size:13px;padding:4px 16px;">
                    <?= htmlspecialchars($stat) ?>
                </span>
            </div>
            <div style="max-width:480px;margin:0 auto;">
                <div class="sev-bar-track" style="height:10px;">
                    <div class="sev-bar-fill <?= $pct>=50 ? 'blue' : 'red' ?>" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <div style="font-size:11px;color:var(--text-dim);margin-top:10px;">
                Last calculated: <?= date('d M Y H:i', strtotime($compScore['calculated_at'])) ?>
            </div>
            <div style="margin-top:16px;font-size:12px;color:var(--text-muted);">
                Thresholds: &nbsp;
                <span class="badge badge-compliant">Compliant = 80%+</span> &nbsp;
                <span class="badge badge-partial">Needs Improvement = 50-79%</span> &nbsp;
                <span class="badge badge-non-compliant">Non-Compliant = below 50%</span>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            No compliance data yet. Complete audit checks in the
            <a href="audit.php" style="color:inherit;text-decoration:underline;">Audit page</a>
            to generate a score.
        </div>
        <?php endif; ?>

        <!-- Chart + Per-Asset Breakdown -->
        <?php if (!empty($assetBreakdown)): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

            <!-- Chart -->
            <div class="card">
                <div class="card-title">Compliance per Asset</div>
                <canvas id="complianceChart" height="260"></canvas>
            </div>

            <!-- Per-Asset Table -->
            <div class="card">
                <div class="card-title">Asset-Level Breakdown</div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th style="text-align:center;">Compliant</th>
                                <th style="text-align:center;">Partial</th>
                                <th style="text-align:center;">Non-Compliant</th>
                                <th style="text-align:center;">N/A</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assetBreakdown as $ab):
                                $total = (int)$ab['cnt_total'];
                                $assetPct = $total > 0 ? round((int)$ab['cnt_compliant'] / $total * 100) : 0;
                                $assetStatus = $assetPct >= 80 ? 'compliant' : ($assetPct >= 50 ? 'partial' : 'non-compliant');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($ab['asset_name']) ?></td>
                                <td class="font-mono text-blue" style="text-align:center;"><?= $ab['cnt_compliant'] ?></td>
                                <td class="font-mono text-muted" style="text-align:center;"><?= $ab['cnt_partial'] ?></td>
                                <td class="font-mono text-danger" style="text-align:center;"><?= $ab['cnt_non_compliant'] ?></td>
                                <td class="font-mono" style="text-align:center;color:var(--text-dim);"><?= $ab['cnt_na'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $assetStatus ?>">
                                        <?= $assetPct ?>%
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
                    datasets: [
                        {
                            label: 'Compliant',
                            data: <?= json_encode($chartCompliant) ?>,
                            backgroundColor: 'rgba(37,99,235,0.7)',
                            borderColor: '#2563eb',
                            borderWidth: 1,
                            borderRadius: 2
                        },
                        {
                            label: 'Non-Compliant',
                            data: <?= json_encode($chartNonCompliant) ?>,
                            backgroundColor: 'rgba(220,38,38,0.7)',
                            borderColor: '#dc2626',
                            borderWidth: 1,
                            borderRadius: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: { ticks: { color: '#888', font:{size:10} }, grid:{ color:'#2a2a2a' } },
                        y: { ticks: { color: '#888', stepSize:1 }, grid:{ color:'#2a2a2a' }, beginAtZero:true }
                    },
                    plugins: {
                        legend: {
                            labels: { color: '#888', font:{size:11} }
                        }
                    }
                }
            });
        })();
        </script>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
