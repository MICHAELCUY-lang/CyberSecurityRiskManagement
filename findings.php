<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$pageTitle   = 'Findings';
$currentPage = 'findings';
$db = getDB();

$activeOrg = (int)($_SESSION['active_org'] ?? 0);

// Filter by risk level
$filterLevel = $_GET['level'] ?? '';
$validLevels = ['Low','Medium','High','Critical'];

$params = [];
$where  = '';
if ($activeOrg) {
    $where    = 'WHERE a.organization_id = ?';
    $params[] = $activeOrg;
}
if ($filterLevel && in_array($filterLevel, $validLevels)) {
    $where   .= ($where ? ' AND' : 'WHERE') . ' f.risk_level = ?';
    $params[] = $filterLevel;
}

$findings = $db->prepare("
    SELECT f.id, a.asset_name, f.issue, f.risk_level, f.recommendation, f.created_at
    FROM findings f
    JOIN assets a ON a.id = f.asset_id
    $where
    ORDER BY FIELD(f.risk_level,'Critical','High','Medium','Low'), f.created_at DESC
");
$findings->execute($params);
$findings = $findings->fetchAll();

// Stats
$statsCounts = ['Low'=>0,'Medium'=>0,'High'=>0,'Critical'=>0];
if ($activeOrg) {
    $allF = $db->prepare("
        SELECT f.risk_level, COUNT(*) AS cnt
        FROM findings f
        JOIN assets a ON a.id=f.asset_id
        WHERE a.organization_id=?
        GROUP BY f.risk_level
    ");
    $allF->execute([$activeOrg]);
    foreach ($allF->fetchAll() as $r) { $statsCounts[$r['risk_level']] = (int)$r['cnt']; }
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Findings</h1>
        <span class="breadcrumb">OCTAVE Allegro / Findings</span>
    </div>
    <div class="content-area">

        <?php if (!$activeOrg): ?>
        <div class="alert alert-info">Select an active organization first. <a href="organization.php" style="color:inherit;text-decoration:underline;">Go to Organization</a></div>
        <?php else: ?>

        <!-- Summary -->
        <div class="kpi-grid">
            <?php foreach ($statsCounts as $level => $count): ?>
            <div class="kpi-card">
                <div class="kpi-label"><?= $level ?></div>
                <div class="kpi-value <?= in_array($level,['High','Critical']) && $count>0 ? 'danger' : '' ?>">
                    <?= $count ?>
                </div>
                <div class="kpi-sub">finding<?= $count!==1?'s':'' ?></div>
            </div>
            <?php endforeach ?>
        </div>

        <!-- Filter + Print -->
        <div class="flex-between mb-2">
            <div class="flex gap-2">
                <a href="findings.php" class="btn btn-ghost" style="font-size:11px;padding:5px 12px;">All</a>
                <?php foreach ($validLevels as $lvl): ?>
                <a href="findings.php?level=<?= $lvl ?>"
                   class="btn btn-ghost" style="font-size:11px;padding:5px 12px;<?= $filterLevel===$lvl ? 'border-color:#fff;color:#fff;' : '' ?>">
                   <?= $lvl ?>
                </a>
                <?php endforeach ?>
            </div>
            <button onclick="window.print()" class="btn btn-ghost" style="font-size:11px;padding:5px 14px;">Print Report</button>
        </div>

        <!-- Findings Table -->
        <div class="card" style="padding:0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Issue</th>
                            <th>Risk Level</th>
                            <th>Recommendation</th>
                            <th>Identified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($findings)): ?>
                        <tr>
                            <td colspan="6" class="text-muted" style="text-align:center;padding:40px;">
                                No findings generated yet. Findings are auto-created from High/Critical risks and non-compliant audit results.
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($findings as $i => $f):
                            $isCritical = $f['risk_level'] === 'Critical';
                            $isHigh     = $f['risk_level'] === 'High';
                            $rowStyle   = $isCritical ? 'border-left:3px solid #8b0000;' : ($isHigh ? 'border-left:3px solid #dc2626;' : '');
                        ?>
                        <tr style="<?= $rowStyle ?>">
                            <td class="font-mono text-muted" style="white-space:nowrap;">F-<?= str_pad($f['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><strong><?= htmlspecialchars($f['asset_name']) ?></strong></td>
                            <td style="font-size:12px;max-width:320px;"><?= htmlspecialchars($f['issue']) ?></td>
                            <td><span class="badge badge-<?= strtolower($f['risk_level']) ?>"><?= $f['risk_level'] ?></span></td>
                            <td style="font-size:12px;color:var(--text-muted);max-width:280px;">
                                <?= htmlspecialchars($f['recommendation']) ?>
                            </td>
                            <td style="font-size:11px;color:var(--text-dim);white-space:nowrap;">
                                <?= date('d M Y', strtotime($f['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>
    </div>
<?php include __DIR__ . '/partials/footer.php'; ?>
