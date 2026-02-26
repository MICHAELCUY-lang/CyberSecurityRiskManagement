<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
session_start();

$pageTitle   = 'Risk Register';
$currentPage = 'risk';
$db = getAuditDB();

$activeOrg = (int)($_SESSION['active_org'] ?? 0);

// Sorting
$sortCol = in_array($_GET['sort'] ?? '', ['risk_score','likelihood','impact','asset_name']) ? $_GET['sort'] : 'risk_score';
$sortDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$toggleDir = $sortDir === 'ASC' ? 'desc' : 'asc';

// Filter
$filterLevel = $_GET['level'] ?? '';
$validLevels = ['Low','Medium','High','Critical'];

$params = [];
$where  = '';
if ($activeOrg) {
    $where    = 'WHERE au.organization_id = ?';
    $params[] = $activeOrg;
}
if ($filterLevel && in_array($filterLevel, $validLevels)) {
    $where   .= ($where ? ' AND' : 'WHERE') . ' (CASE WHEN av.risk_score >= 13 THEN \'Critical\' WHEN av.risk_score >= 8 THEN \'High\' WHEN av.risk_score >= 4 THEN \'Medium\' ELSE \'Low\' END) = ?';
    $params[] = $filterLevel;
}

$safeSort = ['risk_score'=>'av.risk_score','likelihood'=>'av.assigned_likelihood','impact'=>'v.mapped_impact','asset_name'=>'a.name'][$sortCol];

$riskRegister = $db->prepare("
    SELECT av.id AS av_id, a.name AS asset_name, v.vuln_name, v.category,
           av.assigned_likelihood AS likelihood, '-' AS impact, av.risk_score, 
           CASE 
               WHEN av.risk_score >= 13 THEN 'Critical'
               WHEN av.risk_score >= 8 THEN 'High'
               WHEN av.risk_score >= 4 THEN 'Medium'
               ELSE 'Low'
           END AS risk_level, 
            v.mapped_impact AS impact_description
    FROM asset_vulnerabilities av
    JOIN assets a ON a.id = av.asset_id
    JOIN audits au ON au.id = a.audit_id
    JOIN owasp_library v ON v.id = av.vuln_id
    $where
    ORDER BY $safeSort $sortDir
");
$riskRegister->execute($params);
$risks = $riskRegister->fetchAll();

// Summary stats
$statsCounts = ['Low'=>0,'Medium'=>0,'High'=>0,'Critical'=>0];
foreach ($risks as $r) { $statsCounts[$r['risk_level']]++; }
$statsTotal = array_sum($statsCounts) ?: 1;

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';

function sortLink(string $col, string $label, string $currentCol, string $dir, string $level): string {
    $arrow = ($currentCol === $col) ? ($dir === 'ASC' ? ' ^' : ' v') : '';
    $toggleDir = ($currentCol === $col && $dir === 'ASC') ? 'desc' : 'asc';
    $href = "risk.php?sort={$col}&dir={$toggleDir}" . ($level ? "&level={$level}" : '');
    return '<a href="' . htmlspecialchars($href) . '" style="color:inherit;text-decoration:none;">'
        . htmlspecialchars($label) . $arrow . '</a>';
}
?>

<div class="main-content">
    <div class="page-header">
        <h1>Risk Register</h1>
        <span class="breadcrumb">OCTAVE Allegro / Risk Register</span>
    </div>
    <div class="content-area">

        <?php if (!$activeOrg): ?>
        <div class="alert alert-info">Select an active organization first. <a href="organization.php" style="color:inherit;text-decoration:underline;">Go to Organization</a></div>
        <?php else: ?>

        <!-- Summary Bars -->
        <div class="card">
            <div class="card-title">Risk Summary</div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                <?php foreach ($statsCounts as $level => $count):
                    $pct = round($count / $statsTotal * 100);
                    $color = in_array($level, ['High','Critical']) ? 'red' : 'blue';
                    $levelClass = strtolower($level);
                ?>
                <div>
                    <div class="flex-between mb-1">
                        <span class="badge badge-<?= $levelClass ?>"><?= $level ?></span>
                        <span class="font-mono text-muted"><?= $count ?></span>
                    </div>
                    <div class="sev-bar-track">
                        <div class="sev-bar-fill <?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex-between mb-2">
            <div class="flex gap-2">
                <a href="risk.php?sort=<?= $sortCol ?>&dir=<?= strtolower($sortDir) ?>" class="btn btn-ghost <?= !$filterLevel ? '' : '' ?>" style="font-size:11px;padding:5px 12px;">All</a>
                <?php foreach ($validLevels as $lvl): ?>
                <a href="risk.php?sort=<?= $sortCol ?>&dir=<?= strtolower($sortDir) ?>&level=<?= $lvl ?>"
                   class="btn btn-ghost" style="font-size:11px;padding:5px 12px;<?= $filterLevel===$lvl ? 'border-color:#fff;color:#fff;' : '' ?>">
                   <?= $lvl ?>
                </a>
                <?php endforeach ?>
            </div>
            <span class="text-muted" style="font-size:12px;"><?= count($risks) ?> record(s)</span>
        </div>

        <!-- Risk Register Table -->
        <div class="card" style="padding:0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= sortLink('asset_name','Asset',$sortCol,$sortDir,$filterLevel) ?></th>
                            <th>Vulnerability</th>
                            <th>Category</th>
                            <th><?= sortLink('likelihood','Likelihood',$sortCol,$sortDir,$filterLevel) ?></th>
                            <th><?= sortLink('impact','Impact',$sortCol,$sortDir,$filterLevel) ?></th>
                            <th><?= sortLink('risk_score','Score',$sortCol,$sortDir,$filterLevel) ?></th>
                            <th>Risk Level</th>
                            <th>Impact Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($risks)): ?>
                        <tr><td colspan="9" class="text-muted" style="text-align:center;padding:32px;">No risk data found. Assign vulnerabilities to assets first.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($risks as $i => $r):
                            $isCritical = $r['risk_level'] === 'Critical';
                            $isHigh     = $r['risk_level'] === 'High';
                            $rowStyle   = $isCritical ? 'border-left:3px solid #8b0000;' : ($isHigh ? 'border-left:3px solid #dc2626;' : '');
                        ?>
                        <tr style="<?= $rowStyle ?>">
                            <td class="font-mono text-muted"><?= $i+1 ?></td>
                            <td><strong><?= htmlspecialchars($r['asset_name']) ?></strong></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($r['vuln_name']) ?></td>
                            <td style="font-size:11px;" class="text-muted"><?= htmlspecialchars($r['category']) ?></td>
                            <td class="font-mono" style="text-align:center;"><?= $r['likelihood'] ?>/5</td>
                            <td class="font-mono" style="text-align:center;"><?= $r['impact'] ?>/5</td>
                            <td class="font-mono <?= $isHigh||$isCritical ? 'text-danger' : 'text-blue' ?>"
                                style="font-size:16px;font-weight:700;">
                                <?= $r['risk_score'] ?>
                            </td>
                            <td><span class="badge badge-<?= strtolower($r['risk_level']) ?>"><?= $r['risk_level'] ?></span></td>
                            <td style="font-size:11px;color:var(--text-muted);max-width:280px;">
                                <?= htmlspecialchars($r['impact_description'] ?? '-') ?>
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
