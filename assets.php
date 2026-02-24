<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$pageTitle   = 'Assets';
$currentPage = 'assets';
$db = getDB();
$message = '';
$error   = '';

$activeOrg = (int)($_SESSION['active_org'] ?? 0);

// --- Handle form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        if (!$activeOrg) {
            $error = 'No active organization selected. Go to Organization page first.';
        } else {
            $assetName  = trim($_POST['asset_name'] ?? '');
            $owner      = trim($_POST['owner'] ?? '');
            $location   = trim($_POST['location'] ?? '');
            $assetType  = $_POST['asset_type'] ?? 'Software';
            $ciaC       = max(1, min(3, (int)($_POST['cia_confidentiality'] ?? 1)));
            $ciaI       = max(1, min(3, (int)($_POST['cia_integrity'] ?? 1)));
            $ciaA       = max(1, min(3, (int)($_POST['cia_availability'] ?? 1)));
            $criticality = $ciaC + $ciaI + $ciaA; // 3-9

            $validTypes = ['Hardware','Software','Data','People','Process','Facility','Network'];
            if (!in_array($assetType, $validTypes)) $assetType = 'Software';

            if (empty($assetName)) {
                $error = 'Asset name is required.';
            } else {
                if ($action === 'create') {
                    $stmt = $db->prepare("
                        INSERT INTO assets (organization_id, asset_name, owner, location, asset_type,
                            cia_confidentiality, cia_integrity, cia_availability, criticality_score)
                        VALUES (?,?,?,?,?,?,?,?,?)
                    ");
                    $stmt->execute([$activeOrg, $assetName, $owner, $location, $assetType, $ciaC, $ciaI, $ciaA, $criticality]);
                    $message = 'Asset created successfully.';
                } else {
                    $editId = (int)($_POST['edit_id'] ?? 0);
                    $stmt = $db->prepare("
                        UPDATE assets SET asset_name=?, owner=?, location=?, asset_type=?,
                            cia_confidentiality=?, cia_integrity=?, cia_availability=?, criticality_score=?
                        WHERE id=? AND organization_id=?
                    ");
                    $stmt->execute([$assetName, $owner, $location, $assetType, $ciaC, $ciaI, $ciaA, $criticality, $editId, $activeOrg]);
                    $message = 'Asset updated.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $delId = (int)($_POST['del_id'] ?? 0);
        $db->prepare("DELETE FROM assets WHERE id=? AND organization_id=?")->execute([$delId, $activeOrg]);
        $message = 'Asset deleted.';
    }
}

// --- Fetch edit target ---
$editAsset = null;
if (isset($_GET['edit']) && $activeOrg) {
    $stmt = $db->prepare("SELECT * FROM assets WHERE id=? AND organization_id=?");
    $stmt->execute([(int)$_GET['edit'], $activeOrg]);
    $editAsset = $stmt->fetch();
}

// --- Fetch assets ---
$assets = [];
if ($activeOrg) {
    $stmt = $db->prepare("SELECT * FROM assets WHERE organization_id=? ORDER BY created_at DESC");
    $stmt->execute([$activeOrg]);
    $assets = $stmt->fetchAll();
}

// Org name
$orgName = '';
if ($activeOrg) {
    $orgRow = $db->prepare("SELECT name FROM organizations WHERE id=?");
    $orgRow->execute([$activeOrg]);
    $orgName = $orgRow->fetchColumn() ?: '';
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';

function critBadge(int $score): string {
    if ($score <= 4) return 'badge-low';
    if ($score <= 6) return 'badge-medium';
    if ($score <= 8) return 'badge-high';
    return 'badge-critical';
}
function critLabel(int $score): string {
    if ($score <= 4) return 'Low';
    if ($score <= 6) return 'Medium';
    if ($score <= 8) return 'High';
    return 'Critical';
}
?>

<div class="main-content">
    <div class="page-header">
        <h1>Assets</h1>
        <span class="breadcrumb">
            <?= $orgName ? htmlspecialchars($orgName) . ' / ' : '' ?>Assets
        </span>
    </div>
    <div class="content-area">

        <?php if (!$activeOrg): ?>
        <div class="alert alert-info">
            No active organization selected.
            <a href="organization.php" style="color:inherit;text-decoration:underline;">Go to Organization page</a> to create or select one.
        </div>
        <?php else: ?>

        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Form -->
        <div class="card">
            <div class="card-title"><?= $editAsset ? 'Edit Asset' : 'Register Asset' ?></div>
            <form method="POST" action="assets.php">
                <?php if ($editAsset): ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="edit_id" value="<?= $editAsset['id'] ?>">
                <?php else: ?>
                <input type="hidden" name="action" value="create">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="asset_name">Asset Name</label>
                        <input type="text" id="asset_name" name="asset_name" required
                               value="<?= htmlspecialchars($editAsset['asset_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="owner">Asset Owner</label>
                        <input type="text" id="owner" name="owner"
                               placeholder="e.g. IT Department, Finance Team"
                               value="<?= htmlspecialchars($editAsset['owner'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location"
                               placeholder="e.g. Data Center, Cloud (AWS), Building A"
                               value="<?= htmlspecialchars($editAsset['location'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="asset_type">Asset Type</label>
                        <select id="asset_type" name="asset_type">
                            <?php foreach (['Hardware','Software','Data','People','Process','Facility','Network'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($editAsset['asset_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <!-- CIA Triad -->
                <div style="margin-top:20px;padding:16px;border:1px solid var(--border);border-radius:3px;">
                    <div class="card-title" style="margin-bottom:12px;">CIA Triad Values <span class="text-muted" style="font-weight:400;font-size:11px;">(1=Low, 2=Medium, 3=High)</span></div>
                    <div class="form-grid">
                        <?php
                        $ciaFields = [
                            'cia_confidentiality' => 'Confidentiality',
                            'cia_integrity'       => 'Integrity',
                            'cia_availability'    => 'Availability',
                        ];
                        foreach ($ciaFields as $fname => $flabel):
                        ?>
                        <div class="form-group">
                            <label for="<?= $fname ?>"><?= $flabel ?></label>
                            <select id="<?= $fname ?>" name="<?= $fname ?>">
                                <?php for ($v=1; $v<=3; $v++): ?>
                                <option value="<?= $v ?>" <?= (int)($editAsset[$fname] ?? 1) === $v ? 'selected' : '' ?>>
                                    <?= $v ?> — <?= ['','Low','Medium','High'][$v] ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-1 text-muted" style="font-size:11px;">
                        Criticality Score = C + I + A (3-9). Automatically calculated on save.
                    </div>
                </div>

                <div class="mt-2 flex gap-2">
                    <button type="submit" class="btn"><?= $editAsset ? 'Update Asset' : 'Add Asset' ?></button>
                    <?php if ($editAsset): ?>
                    <a href="assets.php" class="btn btn-ghost">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Assets Table -->
        <?php if (!empty($assets)): ?>
        <div class="card">
            <div class="card-title">Asset Inventory — <?= htmlspecialchars($orgName) ?></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Asset Name</th>
                            <th>Type</th>
                            <th>Owner</th>
                            <th>Location</th>
                            <th>C</th><th>I</th><th>A</th>
                            <th>Criticality</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $a): ?>
                        <tr>
                            <td class="font-mono text-muted"><?= $a['id'] ?></td>
                            <td><?= htmlspecialchars($a['asset_name']) ?></td>
                            <td><span class="badge badge-partial"><?= $a['asset_type'] ?></span></td>
                            <td><?= htmlspecialchars($a['owner'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['location'] ?? '-') ?></td>
                            <td class="font-mono"><?= $a['cia_confidentiality'] ?></td>
                            <td class="font-mono"><?= $a['cia_integrity'] ?></td>
                            <td class="font-mono"><?= $a['cia_availability'] ?></td>
                            <td>
                                <span class="badge <?= critBadge((int)$a['criticality_score']) ?>">
                                    <?= $a['criticality_score'] ?> / <?= critLabel((int)$a['criticality_score']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="assets.php?edit=<?= $a['id'] ?>" class="btn btn-ghost" style="font-size:11px;padding:4px 10px;">Edit</a>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete this asset and all associated data?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="del_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="font-size:11px;padding:4px 10px;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; // active org ?>
    </div>
</div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
