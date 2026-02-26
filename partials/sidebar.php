
<?php
/**
 * Partial: sidebar.php — Security Audit Management Platform
 * Set $currentPage before including.
 */
require_once __DIR__ . '/../auth.php';
$currentPage = $currentPage ?? '';
$user = currentUser();

// Detect base path for links (works from root and from admin/)
$base = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) $base = '../';
?>
<style>
    .sidebar {
        width: var(--sidebar-w);
        background: var(--bg-card);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0; left: 0;
        height: 100vh;
        z-index: 100;
        overflow-y: auto;
    }
    .sidebar-brand {
        padding: 18px 20px;
        border-bottom: 1px solid var(--border);
    }
    .sidebar-brand .brand-title {
        font-size: 12px; font-weight: 800;
        letter-spacing: .1em; text-transform: uppercase;
        color: #fff; line-height: 1.3;
    }
    .sidebar-brand .brand-sub {
        font-size: 9px; font-weight: 600;
        letter-spacing: .1em; text-transform: uppercase;
        color: var(--text-dim); margin-top: 3px;
    }
    .sidebar-section { padding: 12px 0 4px; }
    .sidebar-section-label {
        font-size: 8px; font-weight: 800;
        letter-spacing: .16em; text-transform: uppercase;
        color: var(--text-dim); padding: 0 20px; margin-bottom: 2px;
    }
    .sidebar-section-label.oa-label { color: #4a8cff; opacity: .8; }
    .sidebar-divider { height: 1px; background: var(--border); margin: 8px 0; }
    .sidebar-nav a {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 20px; font-size: 12px; font-weight: 500;
        color: var(--text-muted); text-decoration: none;
        transition: color .12s, background .12s;
        border-left: 3px solid transparent;
        white-space: nowrap;
    }
    .sidebar-nav a:hover  { color: var(--text); background: var(--bg-elevated); }
    .sidebar-nav a.active { color: #fff; background: var(--bg-elevated); border-left-color: #fff; font-weight: 700; }
    .sidebar-nav a.oa-link { color: #6a9fd8; }
    .sidebar-nav a.oa-link:hover  { color: #aad4ff; background: #0a1520; }
    .sidebar-nav a.oa-link.active { color: #fff; background: #0d2035; border-left-color: #4a8cff; }
    .nav-step {
        font-size: 9px; font-weight: 700; letter-spacing: .06em;
        padding: 1px 5px; border-radius: 2px; margin-left: auto;
        background: #0d2035; color: #4a8cff; border: 1px solid #1a3a5c;
        flex-shrink: 0;
    }
    .nav-icon { font-size: 13px; opacity: .7; }
    .sidebar-user {
        margin-top: auto; padding: 12px 20px;
        border-top: 1px solid var(--border);
    }
    .sidebar-user .user-name  { font-size: 12px; font-weight: 700; color: var(--text); }
    .sidebar-user .user-role  {
        display: inline-block; font-size: 9px; font-weight: 700;
        letter-spacing: .1em; text-transform: uppercase;
        padding: 2px 6px; border-radius: 2px; margin-top: 2px;
        background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border-light);
    }
    .sidebar-user .user-role.admin { background: #1a1a00; color: #ffdd55; border-color: #443300; }
    .sidebar-logout {
        display: block; margin-top: 8px; font-size: 11px;
        color: var(--text-dim); text-decoration: none; transition: color .12s;
    }
    .sidebar-logout:hover { color: #dc2626; }
</style>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-title">🔒 Security Audit</div>
        <div class="brand-sub">OCTAVE Allegro Platform</div>
    </div>

    <!-- OCTAVE Allegro Workflow -->
    <div class="sidebar-section">
        <div class="sidebar-section-label oa-label">⬡ OCTAVE Allegro</div>
        <nav class="sidebar-nav">
            <a href="<?= $base ?>risk_criteria.php"
               class="oa-link <?= $currentPage === 'risk_criteria' ? 'active' : '' ?>">
                <span class="nav-icon">⚖</span> Risk Criteria
                <span class="nav-step">S1</span>
            </a>
            <a href="<?= $base ?>assets.php"
               class="oa-link <?= $currentPage === 'assets' ? 'active' : '' ?>">
                <span class="nav-icon">◈</span> Asset Profiles
                <span class="nav-step">S2</span>
            </a>
            <a href="<?= $base ?>containers.php"
               class="oa-link <?= $currentPage === 'containers' ? 'active' : '' ?>">
                <span class="nav-icon">▣</span> Containers
                <span class="nav-step">S3</span>
            </a>
            <a href="<?= $base ?>concerns.php"
               class="oa-link <?= $currentPage === 'concerns' ? 'active' : '' ?>">
                <span class="nav-icon">⚠</span> Areas of Concern
                <span class="nav-step">S4</span>
            </a>
            <a href="<?= $base ?>threat_scenarios.php"
               class="oa-link <?= $currentPage === 'threat_scenarios' ? 'active' : '' ?>">
                <span class="nav-icon">▲</span> Threat Scenarios
                <span class="nav-step">S5</span>
            </a>
            <a href="<?= $base ?>risk_register.php"
               class="oa-link <?= $currentPage === 'risk_register' ? 'active' : '' ?>">
                <span class="nav-icon">◆</span> Risk Register
                <span class="nav-step">S6–8</span>
            </a>
        </nav>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Classic Audit Tools -->
    <div class="sidebar-section">
        <div class="sidebar-section-label">Classic Audit</div>
        <nav class="sidebar-nav">
            <a href="<?= $base ?>dashboard.php"
               class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">◈</span> Dashboard
            </a>
            <a href="<?= $base ?>new_audit.php"
               class="<?= $currentPage === 'new_audit' ? 'active' : '' ?>">
                <span class="nav-icon">＋</span> New Audit
            </a>
            <a href="<?= $base ?>reports.php"
               class="<?= $currentPage === 'reports' ? 'active' : '' ?>">
                <span class="nav-icon">▣</span> Audit Reports
            </a>
            <a href="<?= $base ?>evidence.php"
               class="<?= $currentPage === 'evidence' ? 'active' : '' ?>">
                <span class="nav-icon">⬡</span> Evidence
            </a>
            <a href="<?= $base ?>ai_analysis.php"
               class="<?= $currentPage === 'ai' ? 'active' : '' ?>">
                <span class="nav-icon">◎</span> AI Analysis
            </a>
            <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= $base ?>admin/users.php"
               class="<?= $currentPage === 'admin_users' ? 'active' : '' ?>">
                <span class="nav-icon">⊙</span> Manage Users
            </a>
            <?php endif ?>
        </nav>
    </div>

    <div class="sidebar-user">
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <span class="user-role <?= $user['role'] === 'admin' ? 'admin' : '' ?>">
            <?= ucfirst($user['role']) ?>
        </span>
        <a href="<?= $base ?>auth/logout.php" class="sidebar-logout">⎋ Sign out</a>
    </div>
</aside>
