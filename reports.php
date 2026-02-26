<?php
/**
 * reports.php — Module 7: Audit Reports List
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Audit Reports';
$currentPage = 'reports';

$db   = getAuditDB();
$user = currentUser();

// Flash message
$deleted = isset($_GET['deleted']) ? 'Report deleted successfully.' : '';

// Filter/search
$search = trim($_GET['q'] ?? '');
$filter = $_GET['risk'] ?? '';

$where  = [];
$params = [];

if ($search) {
    $where[]  = '(a.system_name LIKE ? OR u.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter && in_array($filter, ['Low','Medium','High','Critical'])) {
    $where[]  = 'a.risk_level = ?';
    $params[] = $filter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$audits = $db->prepare("
    SELECT a.id, a.system_name, a.audit_date, a.risk_level, a.risk_score,
           a.compliance_score, a.created_at, u.name AS auditor_name,
           (SELECT COUNT(*) FROM findings f WHERE f.audit_id = a.id) AS findings_count,
           (SELECT COUNT(*) FROM ai_reports ar WHERE ar.audit_id = a.id) AS has_ai
    FROM audits a
    JOIN users u ON u.id = a.auditor_id
    $whereSql
    ORDER BY a.created_at DESC
");
$audits->execute($params);
$audits = $audits->fetchAll();

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Audit Reports</h1>
        <a href="new_audit.php" class="btn" style="font-size:11px;">＋ New Audit</a>
    </div>
    <div class="content-area">

        <?php if ($deleted): ?>
            <div class="alert alert-success"><?= htmlspecialchars($deleted) ?></div>
        <?php endif ?>

        <!-- Filters -->
        <form method="GET" style="display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                placeholder="Search system or auditor…"
                style="width:260px;">
            <select name="risk" style="width:160px;">
                <option value="">All Risk Levels</option>
                <?php foreach (['Low','Medium','High','Critical'] as $r): ?>
                <option value="<?= $r ?>" <?= $filter === $r ? 'selected' : '' ?>><?= $r ?></option>
                <?php endforeach ?>
            </select>
            <button type="submit" class="btn btn-ghost">Filter</button>
            <?php if ($search || $filter): ?>
            <a href="reports.php" class="btn btn-ghost">Clear</a>
            <?php endif ?>
        </form>

        <!-- Report Stats -->
        <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:20px;">
            <?php
            $totals = ['total'=>count($audits), 'critical'=>0, 'high'=>0, 'medium'=>0, 'low'=>0];
            foreach ($audits as $a) $totals[strtolower($a['risk_level'])]++;
            ?>
            <div class="kpi-card">
                <div class="kpi-label">Total</div>
                <div class="kpi-value"><?= $totals['total'] ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Critical</div>
                <div class="kpi-value <?= $totals['critical'] > 0 ? 'danger' : '' ?>"><?= $totals['critical'] ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">High</div>
                <div class="kpi-value <?= $totals['high'] > 0 ? 'danger' : '' ?>"><?= $totals['high'] ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Medium</div>
                <div class="kpi-value"><?= $totals['medium'] ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Low</div>
                <div class="kpi-value safe"><?= $totals['low'] ?></div>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="card">
            <div class="card-title"><?= count($audits) ?> Audit<?= count($audits) !== 1 ? 's' : '' ?> Found</div>
            <?php if (empty($audits)): ?>
                <p class="text-muted">No audits match your criteria. <a href="new_audit.php" style="color:#aaa;">Create one →</a></p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>System</th>
                            <th>Audit Date</th>
                            <th>Auditor</th>
                            <th>Risk</th>
                            <th>Compliance</th>
                            <th>Findings</th>
                            <th>AI</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audits as $a): ?>
                        <tr>
                            <td class="text-muted font-mono"><?= $a['id'] ?></td>
                            <td style="font-weight:600;">
                                <a href="report_detail.php?id=<?= $a['id'] ?>"
                                   style="color:var(--text);text-decoration:none;">
                                    <?= htmlspecialchars($a['system_name']) ?>
                                </a>
                            </td>
                            <td class="text-muted" style="font-size:12px;"><?= htmlspecialchars($a['audit_date']) ?></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($a['auditor_name']) ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($a['risk_level']) ?>"><?= $a['risk_level'] ?></span>
                                <span class="text-muted font-mono" style="font-size:10px;margin-left:4px;"><?= $a['risk_score'] ?>pts</span>
                            </td>
                            <td class="font-mono <?= (float)$a['compliance_score'] < 50 ? 'text-danger' : '' ?>">
                                <?= number_format((float)$a['compliance_score'], 1) ?>%
                            </td>
                            <td class="text-muted font-mono"><?= $a['findings_count'] ?></td>
                            <td>
                                <?php if ($a['has_ai']): ?>
                                    <span style="color:#22c55e;font-size:11px;">✔ Done</span>
                                <?php else: ?>
                                    <a href="ai_analysis.php?audit_id=<?= $a['id'] ?>"
                                       style="font-size:11px;color:var(--text-muted);text-decoration:none;">Generate</a>
                                <?php endif ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:8px;">
                                    <a href="report_detail.php?id=<?= $a['id'] ?>"
                                       class="btn btn-ghost" style="font-size:10px;padding:3px 10px;">View</a>
                                    <?php if ($user['role'] === 'admin'): ?>
                                    <form method="POST" action="report_detail.php?id=<?= $a['id'] ?>"
                                          onsubmit="return confirm('Delete this report?')">
                                        <input type="hidden" name="delete_audit" value="1">
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
            <?php endif ?>
        </div>

    </div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include __DIR__ . '/partials/footer.php'; ?>
