<?php
/**
 * user_guide.php — User Guide & Workflow Instructions
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle = 'User Guide';
$currentPage = 'user_guide';
$user = currentUser();

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<style>
.guide-step {
    padding: 24px;
    margin-bottom: 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 4px;
    display: flex;
    gap: 20px;
}
.guide-step-num {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--bg-elevated);
    border: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 800;
    color: #4a8cff;
}
.guide-step-num.classic { color: #f97316; }
.guide-step h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
}
.guide-step p {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.6;
    margin: 0 0 12px 0;
}
.guide-step ul {
    margin: 0 0 12px 0;
    padding-left: 20px;
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.6;
}
.guide-step li { margin-bottom: 4px; }
.guide-step .btn-ghost {
    font-size: 11px;
    padding: 4px 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.section-header {
    margin: 30px 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-light);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--text-dim);
}
</style>

<div class="main-content">
    <div class="page-header">
        <h1>User Guide</h1>
        <div class="breadcrumb">Guidance / User Guide</div>
    </div>
    <div class="content-area">

        <div class="card" style="margin-bottom: 30px; border-left: 4px solid #4a8cff;">
            <h2 style="margin:0 0 10px 0; font-size:18px;">Welcome to the Platform</h2>
            <p style="font-size:13px; color:var(--text-muted); line-height:1.6; margin:0;">
                This platform is designed to assist you in executing comprehensive cybersecurity risk assessments based on the 
                <strong>OCTAVE Allegro</strong> methodology, alongside Classic Audits. Follow the steps below sequentially to ensure an accurate and complete assessment.
            </p>
        </div>

        <div class="section-header">⬡ OCTAVE Allegro Workflow</div>

        <div class="guide-step">
            <div class="guide-step-num">S1</div>
            <div>
                <h3>Step 1: Set Risk Measurement Criteria</h3>
                <p>Define the organization's qualitative risk measurement criteria. This establishes the baseline for evaluating impacts across five key areas.</p>
                <ul>
                    <li>Rate the potential impact to <strong>Reputation, Financial, Productivity, Safety, and Legal</strong> on a scale of 0 to 5.</li>
                    <li>These criteria will later be used to calculate the overall risk score objectively.</li>
                </ul>
                <a href="risk_criteria.php" class="btn btn-ghost">Go to Risk Criteria ➔</a>
            </div>
        </div>

        <div class="guide-step">
            <div class="guide-step-num">S2</div>
            <div>
                <h3>Step 2: Develop Information Asset Profiles</h3>
                <p>Identify the key information assets that are critical to the organization's operations.</p>
                <ul>
                    <li>Define the asset, its owner, and state its primary security requirement (Confidentiality, Integrity, or Availability).</li>
                    <li>Establish specific CIA impact values (0-5) if the asset were to be compromised.</li>
                </ul>
                <a href="assets.php" class="btn btn-ghost">Go to Asset Profiles ➔</a>
            </div>
        </div>

        <div class="guide-step">
            <div class="guide-step-num">S3</div>
            <div>
                <h3>Step 3: Identify Information Asset Containers</h3>
                <p>Determine where the identified information assets live. Assets exist in containers, which can be technical (servers, databases), physical (filing cabinets, laptops), or human (people who know the data).</p>
                <ul>
                    <li>For each asset, list the containers that store, transport, or process it.</li>
                    <li>Specify whether the container is Internal or External to the organization.</li>
                </ul>
                <a href="containers.php" class="btn btn-ghost">Go to Containers ➔</a>
            </div>
        </div>

        <div class="guide-step">
            <div class="guide-step-num">S4</div>
            <div>
                <h3>Step 4: Identify Areas of Concern</h3>
                <p>Brainstorm potential situations where an asset's security requirements could be breached within its containers.</p>
                <ul>
                    <li>Think about realistic threats: theft, hardware failure, accidental exposure, or cyber attacks.</li>
                    <li>Document the concern in a narrative format.</li>
                </ul>
                <a href="concerns.php" class="btn btn-ghost">Go to Areas of Concern ➔</a>
            </div>
        </div>

        <div class="guide-step">
            <div class="guide-step-num">S5</div>
            <div>
                <h3>Step 5: Develop Threat Scenarios</h3>
                <p>Formalize each Area of Concern into a structured Threat Scenario by identifying specific actors and motives.</p>
                <ul>
                    <li><strong>Actor:</strong> Who or what is causing the threat? (e.g., Internal Human, Natural Disaster).</li>
                    <li><strong>Means:</strong> How is the actor accessing the container?</li>
                    <li><strong>Motive & Outcome:</strong> Was it deliberate or accidental? What is the consequence (Disclosure, Modification, Loss)?</li>
                </ul>
                <a href="threat_scenarios.php" class="btn btn-ghost">Go to Threat Scenarios ➔</a>
            </div>
        </div>

        <div class="guide-step">
            <div class="guide-step-num">S6-8</div>
            <div>
                <h3>Steps 6–8: Risk Register & Response</h3>
                <p>Evaluate the risks identified in Step 5 and determine how the organization will address them.</p>
                <ul>
                    <li>Assign a <strong>Likelihood</strong> (Low=1, Medium=2, High=3) to the threat scenario.</li>
                    <li>The system will automatically compute the Risk Score based on your S1 Criteria and S2 Asset Impact.</li>
                    <li>Select a <strong>Response Strategy</strong>: Mitigate (Fix), Accept (Do nothing), Transfer (Insurance), or Avoid.</li>
                </ul>
                <a href="risk_register.php" class="btn btn-ghost">Go to Risk Register ➔</a>
            </div>
        </div>

        <div class="section-header">⊞ Classic Audit Workflow</div>

        <div class="guide-step">
            <div class="guide-step-num classic">1</div>
            <div>
                <h3>Create Organization & Audit</h3>
                <p>Before conducting an audit or using checklists, you must define the Organization and create an Audit instance.</p>
                <ul>
                    <li>Go to <strong>Organization</strong> and add your client/company. Switch to it as your Active Organization.</li>
                    <li>Go to <strong>New Audit</strong> and instantiate an audit for a specific system within that organization.</li>
                </ul>
                <div style="display:flex;gap:10px;">
                    <a href="organization.php" class="btn btn-ghost">Organization ➔</a>
                    <a href="new_audit.php" class="btn btn-ghost">New Audit ➔</a>
                </div>
            </div>
        </div>

        <div class="guide-step">
            <div class="guide-step-num classic">2</div>
            <div>
                <h3>Checklist & Compliance</h3>
                <p>Fill out structured questionnaires to verify the implementation of critical security controls.</p>
                <ul>
                    <li>Select an active audit and complete the <strong>Checklist</strong> parameters.</li>
                    <li>Review the aggregate compliance score in the <strong>Compliance</strong> dashboard to identify systematic weak points.</li>
                </ul>
                <a href="checklist.php" class="btn btn-ghost">Go to Checklist ➔</a>
            </div>
        </div>

        <div class="guide-step">
            <div class="guide-step-num classic">3</div>
            <div>
                <h3>Generate Final Reports & AI Analysis</h3>
                <p>Consolidate all findings, evidence, OCTAVE Allegro data, and checklist compliance into a unified audit report.</p>
                <ul>
                    <li>View <strong>Audit Reports</strong> to see the full narrative generated for a specific audit.</li>
                    <li>Click <strong>Export PDF</strong> to download a professional presentation of your findings.</li>
                    <li>Use <strong>AI Analysis</strong> to receive an automated, intelligent summary of the audit's risk profile.</li>
                </ul>
                <a href="reports.php" class="btn btn-ghost">Go to Audit Reports ➔</a>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
