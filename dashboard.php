<?php
/**
 * dashboard.php — Security Audit Management Platform Dashboard
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

$db = getAuditDB();
$user = currentUser();

// --- KPI Queries ---
$totalAudits = (int)$db->query("SELECT COUNT(*) FROM audits")->fetchColumn();
$totalFindings = (int)$db->query("SELECT COUNT(*) FROM findings")->fetchColumn();

$avgCompliance = $db->query("SELECT AVG(compliance_score) FROM audits WHERE compliance_score > 0")->fetchColumn();
$avgCompliance = $avgCompliance ? number_format((float)$avgCompliance, 1) : 'N/A';

// Risk distribution
$riskDist = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Critical' => 0];
$rows = $db->query("SELECT risk_level, COUNT(*) AS cnt FROM audits GROUP BY risk_level")->fetchAll();
foreach ($rows as $r) $riskDist[$r['risk_level']] = (int)$r['cnt'];

// Latest 5 audits
$latestAudits = $db->query("
    SELECT a.id, a.system_name, a.audit_date, a.risk_level, a.compliance_score,
           u.name AS auditor_name
    FROM audits a
    JOIN users u ON u.id = a.auditor_id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll();

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <span class="breadcrumb">Welcome back, <?= htmlspecialchars($user['name']) ?></span>
    </div>
    <div class="content-area">

        <!-- KPI Cards -->
        <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
            <div class="kpi-card">
                <div class="kpi-label">Total Audits</div>
                <div class="kpi-value"><?= $totalAudits ?></div>
                <div class="kpi-sub">Security audits performed</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Risk Findings</div>
                <div class="kpi-value <?= $totalFindings > 0 ? 'danger' : '' ?>"><?= $totalFindings ?></div>
                <div class="kpi-sub">Auto-generated audit findings</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Avg Compliance</div>
                <div class="kpi-value <?= is_numeric($avgCompliance) && (float)$avgCompliance < 50 ? 'danger' : (is_numeric($avgCompliance) && (float)$avgCompliance >= 80 ? 'safe' : '') ?>">
                    <?= is_numeric($avgCompliance) ? $avgCompliance . '%' : $avgCompliance ?>
                </div>
                <div class="kpi-sub">Across all audits</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Critical / High</div>
                <?php $highCrit = ($riskDist['Critical'] + $riskDist['High']); ?>
                <div class="kpi-value <?= $highCrit > 0 ? 'danger' : '' ?>"><?= $highCrit ?></div>
                <div class="kpi-sub">Audits needing urgent attention</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">AI Reports</div>
                <?php $aiCount = (int)$db->query("SELECT COUNT(*) FROM ai_reports")->fetchColumn(); ?>
                <div class="kpi-value safe"><?= $aiCount ?></div>
                <div class="kpi-sub">AI analyses generated</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:20px;">

            <!-- Risk Summary -->
            <div class="card">
                <div class="card-title">Risk Level Summary</div>
                <?php
                $distTotal = array_sum($riskDist) ?: 1;
                foreach ($riskDist as $level => $count):
                    $pct = round($count / $distTotal * 100);
                    $color = in_array($level, ['High','Critical']) ? 'red' : 'blue';
                ?>
                <div style="margin-bottom:14px;">
                    <div class="flex-between mb-1">
                        <span class="badge badge-<?= strtolower($level) ?>"><?= $level ?></span>
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

                <?php if ($totalAudits === 0): ?>
                    <p class="text-muted" style="font-size:12px;margin-top:8px;">No audits yet. <a href="new_audit.php" style="color:#aaa;">Start one →</a></p>
                <?php endif ?>
            </div>

            <!-- Latest Audit Reports -->
            <div class="card">
                <div class="card-title flex-between">
                    <span>Latest Audit Reports</span>
                    <a href="reports.php" class="btn btn-ghost" style="padding:4px 12px;font-size:10px;">View All</a>
                </div>
                <?php if (empty($latestAudits)): ?>
                    <p class="text-muted">No audits performed yet.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>System</th>
                                <th>Date</th>
                                <th>Auditor</th>
                                <th>Risk</th>
                                <th>Compliance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestAudits as $a): ?>
                            <tr>
                                <td>
                                    <a href="report_detail.php?id=<?= $a['id'] ?>"
                                       style="color:var(--text);text-decoration:none;font-weight:600;">
                                        <?= htmlspecialchars($a['system_name']) ?>
                                    </a>
                                </td>
                                <td class="text-muted" style="font-size:12px;"><?= htmlspecialchars($a['audit_date']) ?></td>
                                <td style="font-size:12px;"><?= htmlspecialchars($a['auditor_name']) ?></td>
                                <td><span class="badge badge-<?= strtolower($a['risk_level']) ?>"><?= $a['risk_level'] ?></span></td>
                                <td class="font-mono <?= (float)$a['compliance_score'] < 50 ? 'text-danger' : '' ?>">
                                    <?= number_format((float)$a['compliance_score'], 1) ?>%
                                </td>
                            </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <?php endif ?>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="card mt-2">
            <div class="card-title">Quick Actions</div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="new_audit.php"    class="btn">＋ New Audit</a>
                <a href="reports.php"      class="btn btn-ghost">▣ View Reports</a>
                <a href="evidence.php"     class="btn btn-ghost">⬡ Upload Evidence</a>
                <a href="ai_analysis.php"  class="btn btn-ghost">◎ AI Analysis</a>
            </div>
        </div>

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include __DIR__ . '/partials/footer.php'; ?>
