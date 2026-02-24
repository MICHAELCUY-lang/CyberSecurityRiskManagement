<?php
/**
 * Partial: sidebar.php
 * Set $currentPage (e.g. 'dashboard') before including.
 */
$currentPage = $currentPage ?? '';

$nav = [
    'dashboard'       => ['label' => 'Dashboard',        'href' => 'dashboard.php'],
    'organization'    => ['label' => 'Organization',     'href' => 'organization.php'],
    'assets'          => ['label' => 'Assets',           'href' => 'assets.php'],
    'vulnerabilities' => ['label' => 'Vulnerabilities',  'href' => 'vulnerabilities.php'],
    'risk'            => ['label' => 'Risk Register',    'href' => 'risk.php'],
    'audit'           => ['label' => 'Audit',            'href' => 'audit.php'],
    'compliance'      => ['label' => 'Compliance',       'href' => 'compliance.php'],
    'findings'        => ['label' => 'Findings',         'href' => 'findings.php'],
    'ai'              => ['label' => 'AI Advisor',       'href' => 'ai.php'],
];
?>
<style>
    /* ---- Sidebar ---- */
    .sidebar {
        width: var(--sidebar-w);
        background: var(--bg-card);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 100;
        overflow-y: auto;
    }

    .sidebar-brand {
        padding: 18px 20px;
        border-bottom: 1px solid var(--border);
    }

    .sidebar-brand .brand-title {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #fff;
        line-height: 1.3;
    }

    .sidebar-brand .brand-sub {
        font-size: 9px;
        font-weight: 600;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--text-dim);
        margin-top: 4px;
    }

    .sidebar-section {
        padding: 16px 0 8px;
    }

    .sidebar-section-label {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--text-dim);
        padding: 0 20px;
        margin-bottom: 4px;
    }

    .sidebar-nav a {
        display: block;
        padding: 9px 20px;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-muted);
        text-decoration: none;
        transition: color .12s, background .12s;
        border-left: 3px solid transparent;
    }

    .sidebar-nav a:hover {
        color: var(--text);
        background: var(--bg-elevated);
    }

    .sidebar-nav a.active {
        color: #fff;
        background: var(--bg-elevated);
        border-left-color: #fff;
        font-weight: 700;
    }

    .sidebar-footer {
        margin-top: auto;
        padding: 14px 20px;
        border-top: 1px solid var(--border);
        font-size: 10px;
        color: var(--text-dim);
        letter-spacing: .05em;
    }
</style>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-title">OCTAVE Allegro</div>
        <div class="brand-sub">Cyber Risk Audit Platform</div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Workflow</div>
        <nav class="sidebar-nav">
            <?php foreach ($nav as $key => $item): ?>
            <a href="<?= $item['href'] ?>"
               class="<?= $currentPage === $key ? 'active' : '' ?>">
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach ?>
        </nav>
    </div>

    <div class="sidebar-footer">
        OCTAVE Allegro v1.0<br>
        <?= date('Y') ?> - Academic Build
    </div>
</aside>
