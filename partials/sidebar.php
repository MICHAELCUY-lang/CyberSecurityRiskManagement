
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
        overflow-y: hidden; /* Prevent internal scrollbar if possible */
    }
    .sidebar-brand {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }
    .sidebar-brand .brand-title {
        font-size: 13px; font-weight: 800;
        letter-spacing: .08em; text-transform: uppercase;
        color: #fff; line-height: 1.2;
    }
    .sidebar-brand .brand-sub {
        font-size: 9px; font-weight: 600;
        letter-spacing: .1em; text-transform: uppercase;
        color: var(--text-dim); margin-top: 4px;
    }
    
    .sidebar-menus {
        flex: 1;
        overflow-y: auto;
        /* Hide scrollbar visually while allowing scroll on tiny screens */
        scrollbar-width: thin;
        scrollbar-color: var(--border) transparent;
    }
    .sidebar-menus::-webkit-scrollbar { width: 4px; }
    .sidebar-menus::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    .sidebar-section { border-bottom: 1px solid var(--border-light); }
    .sidebar-section-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 20px; cursor: pointer; user-select: none;
        font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
        color: var(--text-dim); transition: background .15s, color .15s;
    }
    .sidebar-section-header:hover { background: rgba(255,255,255,0.03); color: var(--text); }
    .sidebar-section-header .header-label { display: flex; align-items: center; gap: 8px; }
    .sidebar-section-header .toggle-icon { font-size: 10px; transition: transform .3s ease; opacity: .6; }
    
    .sidebar-section.active .sidebar-section-header { color: #fff; background: rgba(255,255,255,0.02); border-bottom: 1px solid rgba(255,255,255,0.05); }
    .sidebar-section.active .sidebar-section-header .toggle-icon { transform: rotate(180deg); opacity: 1; }
    .sidebar-section.active .header-label.oa-label { color: #4a8cff; }

    .sidebar-section-content {
        max-height: 0; overflow: hidden;
        transition: max-height .3s cubic-bezier(0.4, 0, 0.2, 1);
        background: #060a0f; /* Subtle dark background for nested items */
    }
    .sidebar-section.active .sidebar-section-content {
        max-height: 500px; /* Large enough to fit inner content comfortably */
    }
    
    .sidebar-nav { padding: 4px 0; }
    .sidebar-nav a {
        display: flex; align-items: center; gap: 10px;
        padding: 7px 20px; font-size: 11.5px; font-weight: 500;
        color: var(--text-muted); text-decoration: none;
        transition: color .15s, background .15s, padding-left .15s;
        border-left: 2px solid transparent; white-space: nowrap;
    }
    .sidebar-nav a:hover  { color: var(--text); background: rgba(255,255,255,0.04); padding-left: 22px; }
    .sidebar-nav a.active { color: #fff; background: rgba(255,255,255,0.06); border-left-color: #fff; font-weight: 700; padding-left: 22px; }
    
    .sidebar-nav a.oa-link { color: #6a9fd8; }
    .sidebar-nav a.oa-link:hover  { color: #aad4ff; background: rgba(74,140,255,0.08); }
    .sidebar-nav a.oa-link.active { color: #fff; background: rgba(74,140,255,0.15); border-left-color: #4a8cff; }
    
    .nav-step {
        font-size: 8px; font-weight: 800; letter-spacing: .08em;
        padding: 2px 5px; border-radius: 3px; margin-left: auto;
        background: #0d2035; color: #4a8cff; border: 1px solid #1a3a5c;
        flex-shrink: 0;
    }
    .nav-icon { font-size: 13px; opacity: .7; width: 14px; text-align: center; }

    .sidebar-user {
        padding: 14px 20px;
        border-top: 1px solid var(--border);
        background: var(--bg-card);
        flex-shrink: 0;
    }
    .sidebar-user .user-name  { font-size: 11.5px; font-weight: 700; color: var(--text); }
    .sidebar-user .user-role  {
        display: inline-block; font-size: 8.5px; font-weight: 800;
        letter-spacing: .12em; text-transform: uppercase;
        padding: 2px 6px; border-radius: 3px; margin-top: 4px;
        background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border-light);
    }
    .sidebar-user .user-role.admin { background: #1a1a00; color: #ffdd55; border-color: #443300; }
    .sidebar-logout {
        display: block; margin-top: 8px; font-size: 10.5px; font-weight: 600;
        color: var(--text-dim); text-decoration: none; transition: color .15s;
    }
    .sidebar-logout:hover { color: #dc2626; }
</style>

<?php
$octavePages = ['risk_criteria', 'assets', 'containers', 'concerns', 'threat_scenarios', 'risk_register'];
$isOctaveActive = in_array($currentPage, $octavePages);
$guidancePages = ['user_guide', 'references'];
$isGuidanceActive = in_array($currentPage, $guidancePages);
// Default to Classic if not in OCTAVE or Guidance (or if empty)
$isClassicActive = !$isOctaveActive && !$isGuidanceActive;
?>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-title">⬢ Security Audit</div>
        <div class="brand-sub">Risk Management Platform</div>
    </div>

    <div class="sidebar-menus">
        <!-- OCTAVE Allegro Workflow -->
        <div class="sidebar-section <?= $isOctaveActive ? 'active' : '' ?>" id="sec-octave">
            <div class="sidebar-section-header" onclick="toggleSidebarSection(this)">
                <span class="header-label oa-label"><span style="font-size:12px;">⬡</span> OCTAVE Allegro</span>
                <span class="toggle-icon">▼</span>
            </div>
            <div class="sidebar-section-content">
                <nav class="sidebar-nav">
                    <a href="<?= $base ?>risk_criteria.php" class="oa-link <?= $currentPage === 'risk_criteria' ? 'active' : '' ?>">
                        <span class="nav-icon">⚖</span> Risk Criteria <span class="nav-step">S1</span>
                    </a>
                    <a href="<?= $base ?>assets.php" class="oa-link <?= $currentPage === 'assets' ? 'active' : '' ?>">
                        <span class="nav-icon">◈</span> Asset Profiles <span class="nav-step">S2</span>
                    </a>
                    <a href="<?= $base ?>containers.php" class="oa-link <?= $currentPage === 'containers' ? 'active' : '' ?>">
                        <span class="nav-icon">▣</span> Containers <span class="nav-step">S3</span>
                    </a>
                    <a href="<?= $base ?>concerns.php" class="oa-link <?= $currentPage === 'concerns' ? 'active' : '' ?>">
                        <span class="nav-icon">△</span> Areas of Concern <span class="nav-step">S4</span>
                    </a>
                    <a href="<?= $base ?>threat_scenarios.php" class="oa-link <?= $currentPage === 'threat_scenarios' ? 'active' : '' ?>">
                        <span class="nav-icon">▲</span> Threat Scenarios <span class="nav-step">S5</span>
                    </a>
                    <a href="<?= $base ?>risk_register.php" class="oa-link <?= $currentPage === 'risk_register' ? 'active' : '' ?>">
                        <span class="nav-icon">◆</span> Risk Register <span class="nav-step">S6–8</span>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Classic Audit Tools -->
        <div class="sidebar-section <?= $isClassicActive ? 'active' : '' ?>" id="sec-classic">
            <div class="sidebar-section-header" onclick="toggleSidebarSection(this)">
                <span class="header-label"><span style="font-size:12px;">⊞</span> Classic Audit</span>
                <span class="toggle-icon">▼</span>
            </div>
            <div class="sidebar-section-content">
                <nav class="sidebar-nav">
                    <a href="<?= $base ?>organization.php" class="<?= $currentPage === 'organization' ? 'active' : '' ?>">
                        <span class="nav-icon">⊞</span> Organization
                    </a>
                    <a href="<?= $base ?>dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                        <span class="nav-icon">◈</span> Dashboard
                    </a>
                    <a href="<?= $base ?>new_audit.php" class="<?= $currentPage === 'new_audit' ? 'active' : '' ?>">
                        <span class="nav-icon">＋</span> New Audit
                    </a>
                    <a href="<?= $base ?>reports.php" class="<?= $currentPage === 'reports' ? 'active' : '' ?>">
                        <span class="nav-icon">▣</span> Audit Reports
                    </a>
                    <a href="<?= $base ?>evidence.php" class="<?= $currentPage === 'evidence' ? 'active' : '' ?>">
                        <span class="nav-icon">⬡</span> Evidence
                    </a>
                    <a href="<?= $base ?>checklist.php" class="<?= $currentPage === 'checklist' ? 'active' : '' ?>">
                        <span class="nav-icon">☑</span> Checklist
                    </a>
                    <a href="<?= $base ?>compliance.php" class="<?= $currentPage === 'compliance' ? 'active' : '' ?>">
                        <span class="nav-icon">★</span> Compliance
                    </a>
                    <a href="<?= $base ?>findings.php" class="<?= $currentPage === 'findings' ? 'active' : '' ?>">
                        <span class="nav-icon">⌕</span> Findings
                    </a>
                    <a href="<?= $base ?>risk.php" class="<?= $currentPage === 'risk' ? 'active' : '' ?>">
                        <span class="nav-icon">▲</span> Risk Matrix
                    </a>
                    <a href="<?= $base ?>ai_analysis.php" class="<?= $currentPage === 'ai' ? 'active' : '' ?>">
                        <span class="nav-icon">◎</span> AI Analysis
                    </a>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= $base ?>admin/users.php" class="<?= $currentPage === 'admin_users' ? 'active' : '' ?>">
                        <span class="nav-icon">⊙</span> Manage Users
                    </a>
                    <?php endif ?>
                </nav>
            </div>
        </div>

        <!-- Guidance & Info -->
        <div class="sidebar-section <?= $isGuidanceActive ? 'active' : '' ?>" id="sec-guidance">
            <div class="sidebar-section-header" onclick="toggleSidebarSection(this)">
                <span class="header-label"><span style="font-size:12px;">ℹ</span> Guidance & Info</span>
                <span class="toggle-icon">▼</span>
            </div>
            <div class="sidebar-section-content">
                <nav class="sidebar-nav">
                    <a href="<?= $base ?>user_guide.php" class="<?= $currentPage === 'user_guide' ? 'active' : '' ?>">
                        <span class="nav-icon">📖</span> User Guide
                    </a>
                    <a href="<?= $base ?>references.php" class="<?= $currentPage === 'references' ? 'active' : '' ?>">
                        <span class="nav-icon">📚</span> References
                    </a>
                </nav>
            </div>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <span class="user-role <?= $user['role'] === 'admin' ? 'admin' : '' ?>">
            <?= ucfirst($user['role']) ?>
        </span>
        <a href="<?= $base ?>auth/logout.php" class="sidebar-logout">⎋ Sign out</a>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Restore states from localStorage
    const states = JSON.parse(localStorage.getItem('sidebarStates') || '{}');
    document.querySelectorAll('.sidebar-section').forEach(sec => {
        const id = sec.id;
        if (id && states[id] !== undefined) {
            if (states[id]) {
                sec.classList.add('active');
            } else {
                sec.classList.remove('active');
            }
        }
    });
});

function toggleSidebarSection(headerEl) {
    const parentSection = headerEl.parentElement;
    parentSection.classList.toggle('active');
    
    // Save state
    if (parentSection.id) {
        const states = JSON.parse(localStorage.getItem('sidebarStates') || '{}');
        states[parentSection.id] = parentSection.classList.contains('active');
        localStorage.setItem('sidebarStates', JSON.stringify(states));
    }
}
</script>
