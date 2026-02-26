<?php
/**
 * references.php — Methodology & Citations
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle = 'References & Methodology';
$currentPage = 'references';
$user = currentUser();
$db = getAuditDB();

// Handle Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $stmt = $db->prepare("INSERT INTO system_references (title, badge, description, citation, link, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['title'],
        $_POST['badge'],
        $_POST['description'],
        $_POST['citation'],
        $_POST['link'] ?: null,
        $user['id']
    ]);
    header('Location: references.php?added=1');
    exit;
}

// Handle Deletion (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $user['role'] === 'admin') {
    $stmt = $db->prepare("DELETE FROM system_references WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    header('Location: references.php?deleted=1');
    exit;
}

// Fetch all references
$references = $db->query("SELECT * FROM system_references ORDER BY created_at ASC")->fetchAll();

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<style>
.ref-card {
    padding: 24px;
    margin-bottom: 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 4px;
}
.ref-card h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.ref-card h3 span.badge {
    font-size: 9px;
    letter-spacing: .1em;
    padding: 2px 6px;
    background: var(--bg-elevated);
    border: 1px solid var(--border-light);
    color: var(--text-dim);
    text-transform: uppercase;
    font-weight: 800;
}
.ref-card p {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0 0 16px 0;
}
.ref-citation {
    padding: 12px 16px;
    background: var(--bg-elevated);
    border-left: 3px solid var(--border-light);
    font-family: monospace;
    font-size: 11px;
    color: var(--text-dim);
    white-space: pre-wrap;
    line-height: 1.5;
    margin-bottom: 12px;
}
.ref-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #4a8cff;
    text-decoration: none;
    transition: color 0.15s;
}
.ref-link:hover { color: #8cbaff; text-decoration: underline; }
.ref-link.external::after { content: '↗'; font-family: monospace; font-size: 14px; }
</style>

<div class="main-content">
    <div class="page-header flex-between">
        <div>
            <h1>References & Methodology</h1>
            <div class="breadcrumb">Guidance / References</div>
        </div>
        <?php if ($user['role'] === 'admin'): ?>
        <button class="btn" onclick="document.getElementById('addModal').style.display='block'">＋ Add Reference</button>
        <?php endif ?>
    </div>
    <div class="content-area">

        <?php if (isset($_GET['added'])): ?>
            <div style="padding:12px; background:#064e3b; color:#34d399; margin-bottom:20px; font-size:12px; border-left:4px solid #10b981;">
                ✔ Reference added successfully.
            </div>
        <?php endif ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div style="padding:12px; background:#7f1d1d; color:#fca5a5; margin-bottom:20px; font-size:12px; border-left:4px solid #ef4444;">
                ✔ Reference deleted successfully.
            </div>
        <?php endif ?>

        <div class="card" style="margin-bottom: 30px; border-left: 4px solid #fff;">
            <p style="font-size:14px; color:var(--text-muted); line-height:1.6; margin:0;">
                This platform is strictly built upon established cybersecurity risk assessment frameworks. 
                The core logic, data structures, and workflows are directly derived from the 
                <strong>OCTAVE Allegro</strong> methodology developed by the Software Engineering Institute (SEI) at Carnegie Mellon University, 
                supplemented by industry-standard compliance checklist auditing practices.
            </p>
        </div>

        <?php if (empty($references)): ?>
            <p class="text-muted">No references found.</p>
        <?php else: ?>
            <?php foreach ($references as $ref): ?>
            <div class="ref-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <h3>
                        <?= htmlspecialchars($ref['title']) ?> 
                        <?php if ($ref['badge']): ?><span class="badge"><?= htmlspecialchars($ref['badge']) ?></span><?php endif ?>
                    </h3>
                    <?php if ($user['role'] === 'admin'): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this reference?');" style="margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $ref['id'] ?>">
                        <button type="submit" class="btn btn-ghost" style="color:#ef4444; padding:4px 8px; font-size:11px;">✖ Delete</button>
                    </form>
                    <?php endif ?>
                </div>
                
                <p><?= nl2br(htmlspecialchars($ref['description'])) ?></p>
                
                <?php if ($ref['citation']): ?>
                <div class="ref-citation"><?= htmlspecialchars($ref['citation']) ?></div>
                <?php endif ?>

                <?php if ($ref['link']): ?>
                <a href="<?= htmlspecialchars($ref['link']) ?>" target="_blank" class="ref-link external">View Original Publication</a>
                <?php endif ?>
            </div>
            <?php endforeach ?>
        <?php endif ?>

    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border); width:500px; border-radius:4px; margin: 5vh auto; max-height: 90vh; overflow-y:auto; display:flex; flex-direction:column;">
        <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:var(--bg-card);">
            <h2 style="margin:0; font-size:16px;">Add New Reference</h2>
            <button class="btn btn-ghost" onclick="document.getElementById('addModal').style.display='none'" style="font-size:16px; padding:4px 8px;">✕</button>
        </div>
        <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">Title / Framework Name <span style="color:#ef4444">*</span></label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. NIST CSF 2.0">
            </div>
            
            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">Badge Label <span style="color:#ef4444">*</span></label>
                <input type="text" name="badge" class="form-control" required placeholder="e.g. Supporting Concept">
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">Description <span style="color:#ef4444">*</span></label>
                <textarea name="description" class="form-control" required rows="3" placeholder="Explain how this reference is used in the platform..."></textarea>
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">Citation Text <span style="color:#ef4444">*</span></label>
                <textarea name="citation" class="form-control" required rows="3" placeholder="Full academic or formal citation..."></textarea>
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">URL Link (Optional)</label>
                <input type="url" name="link" class="form-control" placeholder="https://...">
            </div>

            <div class="form-actions" style="margin-top:24px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn">Save Reference</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
