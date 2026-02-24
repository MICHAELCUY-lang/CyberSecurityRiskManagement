<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$pageTitle   = 'Audit';
$currentPage = 'audit';
$db = getDB();
$message = '';
$error   = '';

$activeOrg = (int)($_SESSION['active_org'] ?? 0);

// ============================================================
// COMPLIANCE RECALCULATOR
// ============================================================
function recalcCompliance(PDO $db, int $orgId): void {
    // Count audit_results for all assets in org
    $stmt = $db->prepare("
        SELECT ar.status, COUNT(*) AS cnt
        FROM audit_results ar
        JOIN assets a ON a.id = ar.asset_id
        WHERE a.organization_id = ?
        GROUP BY ar.status
    ");
    $stmt->execute([$orgId]);
    $rows = $stmt->fetchAll();

    $total     = 0;
    $compliant = 0;
    foreach ($rows as $r) {
        if ($r['status'] !== 'not_applicable') {
            $total += (int)$r['cnt'];
            if ($r['status'] === 'compliant') {
                $compliant += (int)$r['cnt'];
            }
        }
    }

    $pct = $total > 0 ? round($compliant / $total * 100, 2) : 0;
    $status = $pct >= 80 ? 'Compliant' : ($pct >= 50 ? 'Needs Improvement' : 'Non-Compliant');

    $db->prepare("
        INSERT INTO compliance_scores (organization_id, score_percentage, status)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE score_percentage=VALUES(score_percentage), status=VALUES(status), calculated_at=NOW()
    ")->execute([$orgId, $pct, $status]);
}

// ============================================================
// AUTO-GENERATE FINDING FROM AUDIT RESULT
// ============================================================
function generateFindingFromAudit(PDO $db, int $assetId, string $checklistTitle, string $status): void {
    if ($status !== 'non_compliant') return;

    $aName = $db->prepare("SELECT asset_name FROM assets WHERE id=?");
    $aName->execute([$assetId]);
    $assetName = $aName->fetchColumn();

    $issue = "[Non-Compliant] Audit control '{$checklistTitle}' failed for asset '{$assetName}'.";
    $rec   = "Review and remediate the control '{$checklistTitle}' for '{$assetName}'. Implement corrective action and re-audit.";
    $riskLevel = 'Medium'; // audit-based findings default Medium

    $exists = $db->prepare("SELECT id FROM findings WHERE asset_id=? AND issue=?");
    $exists->execute([$assetId, $issue]);
    if (!$exists->fetchColumn()) {
        $db->prepare("INSERT INTO findings (asset_id, issue, risk_level, recommendation) VALUES (?,?,?,?)")
           ->execute([$assetId, $issue, $riskLevel, $rec]);
    }
}

// ============================================================
// HANDLE POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_result') {
        $arId    = (int)($_POST['ar_id'] ?? 0);
        $status  = $_POST['status'] ?? 'not_applicable';
        $notes   = trim($_POST['notes'] ?? '');
        $assetId = (int)($_POST['asset_id'] ?? 0);

        $validStatuses = ['compliant','partial','non_compliant','not_applicable'];
        if (!in_array($status, $validStatuses)) $status = 'not_applicable';

        if ($arId) {
            $db->prepare("UPDATE audit_results SET status=?, notes=?, audited_at=NOW() WHERE id=?")
               ->execute([$status, $notes, $arId]);
        } else {
            $checklistId = (int)($_POST['checklist_id'] ?? 0);
            $db->prepare("
                INSERT INTO audit_results (checklist_id, asset_id, status, notes)
                VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE status=VALUES(status), notes=VALUES(notes), audited_at=NOW()
            ")->execute([$checklistId, $assetId, $status, $notes]);
        }

        // Auto-finding for non_compliant
        $cTitle = trim($_POST['checklist_title'] ?? '');
        if ($cTitle && $assetId) {
            generateFindingFromAudit($db, $assetId, $cTitle, $status);
        }

        // Recalculate compliance
        if ($activeOrg) recalcCompliance($db, $activeOrg);

        $message = 'Audit result saved. Compliance score recalculated.';
    }

    // File upload
    if ($action === 'upload_evidence') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        if (!$assetId) {
            $error = 'Select an asset for evidence upload.';
        } elseif (!isset($_FILES['evidence_file']) || $_FILES['evidence_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'No file uploaded or upload error occurred.';
        } else {
            $file     = $_FILES['evidence_file'];
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = UPLOAD_ALLOWED;
            $maxSize  = UPLOAD_MAX_SIZE;

            if (!in_array($ext, $allowed)) {
                $error = 'File type not allowed. Allowed: ' . implode(', ', $allowed);
            } elseif ($file['size'] > $maxSize) {
                $error = 'File too large. Maximum: ' . ($maxSize / 1024 / 1024) . ' MB.';
            } else {
                $safeName = date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
                $dest     = UPLOAD_DIR . $safeName;
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $db->prepare("INSERT INTO evidence_files (asset_id, file_path, file_type) VALUES (?,?,?)")
                       ->execute([$assetId, 'uploads/' . $safeName, $ext]);
                    $message = 'Evidence file uploaded successfully.';
                } else {
                    $error = 'File move failed. Check uploads/ directory permissions.';
                }
            }
        }
    }
}

// --- Data ---
$assets = [];
if ($activeOrg) {
    $stmt = $db->prepare("SELECT id, asset_name FROM assets WHERE organization_id=? ORDER BY asset_name");
    $stmt->execute([$activeOrg]);
    $assets = $stmt->fetchAll();
}

$selectedAsset = (int)($_GET['asset'] ?? ($assets[0]['id'] ?? 0));

// Checklist items + existing results for selected asset
$auditItems = [];
if ($selectedAsset) {
    // All checklist items (global + asset-linked auto-generated)
    $auditItems = $db->prepare("
        SELECT ac.id AS checklist_id, ac.title, ac.description, ac.framework_source,
               COALESCE(ar.id, 0) AS ar_id,
               COALESCE(ar.status, 'not_applicable') AS status,
               COALESCE(ar.notes, '') AS notes,
               ar.audited_at
        FROM audit_checklist ac
        LEFT JOIN audit_results ar ON ar.checklist_id = ac.id AND ar.asset_id = ?
        ORDER BY ac.framework_source, ac.title
    ");
    $auditItems->execute([$selectedAsset]);
    $auditItems = $auditItems->fetchAll();
}

// Evidence files for selected asset
$evidenceFiles = [];
if ($selectedAsset) {
    $stmt = $db->prepare("SELECT * FROM evidence_files WHERE asset_id=? ORDER BY uploaded_at DESC");
    $stmt->execute([$selectedAsset]);
    $evidenceFiles = $stmt->fetchAll();
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Audit Controls</h1>
        <span class="breadcrumb">OCTAVE Allegro / Audit</span>
    </div>
    <div class="content-area">

        <?php if (!$activeOrg): ?>
        <div class="alert alert-info">Select an active organization first. <a href="organization.php" style="color:inherit;text-decoration:underline;">Go to Organization</a></div>
        <?php else: ?>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;">
            <div>
                <!-- Asset Selector -->
                <div class="card" style="padding:14px 20px;">
                    <form method="GET" action="audit.php" style="display:flex;align-items:center;gap:12px;">
                        <label style="margin:0;white-space:nowrap;">Auditing Asset:</label>
                        <select name="asset" onchange="this.form.submit()" style="flex:1;">
                            <option value="">-- Select Asset --</option>
                            <?php foreach ($assets as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $a['id']==$selectedAsset ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['asset_name']) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </form>
                </div>

                <!-- Audit Checklist -->
                <?php if ($selectedAsset && !empty($auditItems)): ?>
                <?php
                $currentFw = '';
                foreach ($auditItems as $item):
                    if ($item['framework_source'] !== $currentFw):
                        if ($currentFw) echo '</div></div>'; // close previous group
                        $currentFw = $item['framework_source'];
                        echo '<div class="card"><div class="card-title">' . htmlspecialchars($currentFw) . '</div>';
                    endif;
                ?>
                <div style="border:1px solid var(--border);border-radius:3px;padding:14px 16px;margin-bottom:12px;">
                    <form method="POST" action="audit.php?asset=<?= $selectedAsset ?>">
                        <input type="hidden" name="action" value="save_result">
                        <input type="hidden" name="ar_id" value="<?= $item['ar_id'] ?>">
                        <input type="hidden" name="checklist_id" value="<?= $item['checklist_id'] ?>">
                        <input type="hidden" name="asset_id" value="<?= $selectedAsset ?>">
                        <input type="hidden" name="checklist_title" value="<?= htmlspecialchars($item['title']) ?>">

                        <div class="flex-between mb-1">
                            <strong style="font-size:13px;"><?= htmlspecialchars($item['title']) ?></strong>
                            <span class="badge badge-<?= str_replace('_','-',$item['status']) ?>">
                                <?= str_replace('_',' ', $item['status']) ?>
                            </span>
                        </div>
                        <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
                            <?= htmlspecialchars($item['description'] ?? '') ?>
                        </p>
                        <div class="form-grid" style="grid-template-columns:180px 1fr auto;">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <?php foreach (['compliant'=>'Compliant','partial'=>'Partial','non_compliant'=>'Non-Compliant','not_applicable'=>'Not Applicable'] as $val=>$lbl): ?>
                                    <option value="<?= $val ?>" <?= $item['status']===$val ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Notes / Evidence Reference</label>
                                <input type="text" name="notes" value="<?= htmlspecialchars($item['notes']) ?>"
                                       placeholder="Auditor notes...">
                            </div>
                            <div class="form-group" style="justify-content:flex-end;">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn" style="white-space:nowrap;">Save</button>
                            </div>
                        </div>
                        <?php if ($item['audited_at']): ?>
                        <div style="font-size:10px;color:var(--text-dim);margin-top:4px;">
                            Last audited: <?= htmlspecialchars($item['audited_at']) ?>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php if ($currentFw) echo '</div></div>'; ?>

                <?php elseif ($selectedAsset): ?>
                <div class="alert alert-info">No checklist items linked to this asset yet. Assign vulnerabilities to auto-generate items, or they will use the global checklist.</div>
                <?php endif; ?>
            </div>

            <!-- Sidebar: Evidence Upload -->
            <div>
                <div class="card">
                    <div class="card-title">Evidence Upload</div>
                    <?php if ($selectedAsset): ?>
                    <form method="POST" enctype="multipart/form-data" action="audit.php?asset=<?= $selectedAsset ?>">
                        <input type="hidden" name="action" value="upload_evidence">
                        <input type="hidden" name="asset_id" value="<?= $selectedAsset ?>">
                        <div class="form-group mb-2">
                            <label>File (max 5 MB)</label>
                            <input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt" required>
                            <div style="font-size:10px;color:var(--text-dim);margin-top:4px;">
                                Allowed: jpg, png, gif, pdf, txt
                            </div>
                        </div>
                        <button type="submit" class="btn" style="width:100%;">Upload Evidence</button>
                    </form>

                    <!-- Evidence file list -->
                    <?php if (!empty($evidenceFiles)): ?>
                    <div style="margin-top:16px;">
                        <div class="card-title" style="margin-bottom:10px;">Uploaded Files</div>
                        <?php foreach ($evidenceFiles as $ef): ?>
                        <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:12px;">
                            <a href="<?= htmlspecialchars($ef['file_path']) ?>" target="_blank"
                               style="color:var(--text);text-decoration:underline;">
                                <?= htmlspecialchars(basename($ef['file_path'])) ?>
                            </a>
                            <div style="font-size:10px;color:var(--text-dim);">
                                <?= strtoupper($ef['file_type']) ?> &nbsp;|&nbsp;
                                <?= date('d M Y H:i', strtotime($ef['uploaded_at'])) ?>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <p class="text-muted" style="font-size:12px;">Select an asset to upload evidence.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
