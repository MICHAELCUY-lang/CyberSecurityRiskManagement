<?php
/**
 * evidence.php — Module 6: Evidence Upload & Management
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle   = 'Evidence';
$currentPage = 'evidence';

$db   = getAuditDB();
$user = currentUser();

$error   = '';
$success = '';

// Pre-select audit if coming from report detail
$preAuditId = (int)($_GET['audit_id'] ?? 0);

// Get all audits for dropdown (auditor sees their own; admin sees all)
if ($user['role'] === 'admin') {
    $auditList = $db->query("SELECT a.id, a.system_name, u.name AS auditor_name FROM audits a JOIN users u ON u.id = a.auditor_id ORDER BY a.created_at DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, system_name FROM audits WHERE auditor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $auditList = $stmt->fetchAll();
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auditId    = (int)($_POST['audit_id']    ?? 0);
    $description = trim($_POST['description'] ?? '');
    $file = $_FILES['evidence_file'] ?? null;

    if (!$auditId) {
        $error = 'Please select an audit.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid file to upload.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','pdf','txt','log'];

        if (!in_array($ext, $allowed)) {
            $error = 'File type not allowed. Allowed: jpg, png, pdf, txt, log.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'File too large. Maximum size: 5 MB.';
        } else {
            $dir = __DIR__ . '/uploads/audit_' . $auditId . '/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $fileName  = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $destPath  = $dir . $fileName;
            $webPath   = 'uploads/audit_' . $auditId . '/' . $fileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $ins = $db->prepare("INSERT INTO evidence (audit_id, file_path, description) VALUES (?,?,?)");
                $ins->execute([$auditId, $webPath, $description]);
                $success = 'Evidence uploaded successfully.';
            } else {
                $error = 'Failed to save file. Check server write permissions.';
            }
        }
    }
}

// Load all evidence (with audit name)
if ($user['role'] === 'admin') {
    $evidence = $db->query("
        SELECT e.id, e.audit_id, e.file_path, e.description, e.uploaded_at,
               a.system_name
        FROM evidence e
        JOIN audits a ON a.id = e.audit_id
        ORDER BY e.uploaded_at DESC
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT e.id, e.audit_id, e.file_path, e.description, e.uploaded_at,
               a.system_name
        FROM evidence e
        JOIN audits a ON a.id = e.audit_id
        WHERE a.auditor_id = ?
        ORDER BY e.uploaded_at DESC
    ");
    $stmt->execute([$user['id']]);
    $evidence = $stmt->fetchAll();
}

// Delete evidence (admin or own)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_evidence'])) {
    $evId = (int)$_POST['delete_evidence'];
    // verify ownership
    $ev = $db->prepare("SELECT e.*, a.auditor_id FROM evidence e JOIN audits a ON a.id = e.audit_id WHERE e.id = ?");
    $ev->execute([$evId]);
    $evRow = $ev->fetch();
    if ($evRow && ($user['role'] === 'admin' || $evRow['auditor_id'] == $user['id'])) {
        @unlink(__DIR__ . '/' . $evRow['file_path']);
        $db->prepare("DELETE FROM evidence WHERE id = ?")->execute([$evId]);
        header('Location: evidence.php?deleted=1');
        exit;
    }
}

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Evidence Management</h1>
        <span class="breadcrumb">Module 6</span>
    </div>
    <div class="content-area">

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif ?>
        <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Evidence deleted.</div><?php endif ?>

        <!-- Upload Form -->
        <div class="card" style="max-width:700px;">
            <div class="card-title">Upload Evidence</div>
            <p class="text-muted" style="font-size:12px;margin-bottom:20px;">
                Supported formats: JPG, PNG, GIF, PDF, TXT, LOG — maximum 5 MB per file.
            </p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="audit_id">Audit *</label>
                        <select id="audit_id" name="audit_id" required>
                            <option value="">— Select Audit —</option>
                            <?php foreach ($auditList as $a): ?>
                            <option value="<?= $a['id'] ?>"
                                <?= (($preAuditId && $preAuditId == $a['id']) || ($_POST['audit_id'] ?? '') == $a['id']) ? 'selected' : '' ?>>
                                #<?= $a['id'] ?> — <?= htmlspecialchars($a['system_name']) ?>
                                <?= isset($a['auditor_name']) ? '(' . htmlspecialchars($a['auditor_name']) . ')' : '' ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label for="evidence_file">File *</label>
                        <input type="file" id="evidence_file" name="evidence_file" required
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.log">
                    </div>
                    <div class="form-group full">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"
                            placeholder="Describe what this evidence shows (e.g. Firewall ruleset screenshot, access log excerpt)…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn" style="margin-top:16px;">⬆ Upload Evidence</button>
            </form>
        </div>

        <!-- Evidence List -->
        <div class="card">
            <div class="card-title"><?= count($evidence) ?> Evidence File<?= count($evidence) !== 1 ? 's' : '' ?></div>
            <?php if (empty($evidence)): ?>
                <p class="text-muted">No evidence uploaded yet.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Audit</th>
                            <th>File</th>
                            <th>Description</th>
                            <th>Uploaded</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evidence as $ev): ?>
                        <tr>
                            <td style="font-size:12px;">
                                <a href="report_detail.php?id=<?= $ev['audit_id'] ?>"
                                   style="color:var(--text);text-decoration:none;">
                                    <?= htmlspecialchars($ev['system_name']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= htmlspecialchars($ev['file_path']) ?>" target="_blank"
                                   style="color:var(--chart-blue);text-decoration:none;font-size:12px;">
                                    📎 <?= htmlspecialchars(basename($ev['file_path'])) ?>
                                </a>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted);max-width:300px;">
                                <?= htmlspecialchars($ev['description'] ?: '—') ?>
                            </td>
                            <td class="text-muted" style="font-size:12px;">
                                <?= date('Y-m-d H:i', strtotime($ev['uploaded_at'])) ?>
                            </td>
                            <td>
                                <form method="POST"
                                      onsubmit="return confirm('Delete this evidence file?')">
                                    <input type="hidden" name="delete_evidence" value="<?= $ev['id'] ?>">
                                    <button type="submit" class="btn btn-danger"
                                            style="font-size:10px;padding:3px 10px;">Delete</button>
                                </form>
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
