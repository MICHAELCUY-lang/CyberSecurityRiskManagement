'use strict';

// ─── TOAST ───────────────────────────────────────
window.showToast = function (type, title, msg, ms = 4000) {
  const c = document.getElementById('toasts');
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<div class="toast-dot"></div>
    <div class="toast-body"><strong>${title}</strong><span>${msg}</span></div>
    <button class="toast-close" onclick="this.closest('.toast').remove()">&#x2715;</button>`;
  c.appendChild(el);
  setTimeout(() => { el.classList.add('remove'); setTimeout(() => el.remove(), 230); }, ms);
};

// ─── MODAL ───────────────────────────────────────
window.openModal = function (html) {
  document.getElementById('modal-body').innerHTML = html;
  document.getElementById('modal-overlay').classList.add('open');
};
window.closeModal = function () { document.getElementById('modal-overlay').classList.remove('open'); };

window.confirmAction = function (msg, cb) {
  openModal(`<h3>Confirm Action</h3>
    <p style="color:var(--t2);margin-bottom:0;font-size:13.5px">${msg}</p>
    <div class="modal-footer">
      <button class="btn btn-ghost btn-sm" onclick="closeModal()">Cancel</button>
      <button class="btn btn-danger btn-sm" id="_ok">Confirm</button>
    </div>`);
  document.getElementById('_ok').onclick = () => { closeModal(); cb(); };
};

// ─── STATE (sessionStorage for cross-page) ───────
const _defaults = {
  assets: [
    { id: 1, name: 'Web Application (Customer Portal)', type: 'Web Application', owner: 'IT Team', location: 'AWS Cloud', c: 5, i: 5, a: 4, criticality: 'Critical' },
    { id: 2, name: 'Database Server (MySQL)', type: 'Database', owner: 'DBA Team', location: 'On-Premise', c: 5, i: 5, a: 5, criticality: 'Critical' },
    { id: 3, name: 'Application Server (Ubuntu)', type: 'Server', owner: 'Ops Team', location: 'Data Center', c: 4, i: 4, a: 5, criticality: 'High' },
    { id: 4, name: 'Cloud Storage (AWS S3)', type: 'Cloud Service', owner: 'Dev Team', location: 'AWS Cloud', c: 4, i: 3, a: 3, criticality: 'High' },
    { id: 5, name: 'Email Server (Exchange)', type: 'Server', owner: 'IT Team', location: 'On-Premise', c: 3, i: 3, a: 4, criticality: 'Medium' },
    { id: 6, name: 'HR Information System', type: 'Web Application', owner: 'HR Dept', location: 'Internal', c: 4, i: 4, a: 3, criticality: 'High' },
    { id: 7, name: 'Network Firewall', type: 'Network Device', owner: 'Ops Team', location: 'Data Center', c: 3, i: 5, a: 5, criticality: 'Critical' },
    { id: 8, name: 'VPN Gateway', type: 'Network Device', owner: 'IT Team', location: 'Data Center', c: 4, i: 4, a: 5, criticality: 'High' },
    { id: 9, name: 'Mobile Application (iOS/Android)', type: 'Mobile App', owner: 'Dev Team', location: 'App Store', c: 3, i: 3, a: 2, criticality: 'Medium' },
    { id: 10, name: 'API Gateway', type: 'API Service', owner: 'Dev Team', location: 'AWS Cloud', c: 4, i: 4, a: 5, criticality: 'High' },
    { id: 11, name: 'Backup Server', type: 'Server', owner: 'Ops Team', location: 'Data Center', c: 3, i: 5, a: 4, criticality: 'High' },
    { id: 12, name: 'LDAP / Active Directory', type: 'Server', owner: 'IT Team', location: 'On-Premise', c: 5, i: 5, a: 5, criticality: 'Critical' },
  ],
  findings: [
    { id: 1, title: 'SQL Injection in Login Endpoint', issue: 'Unsanitised inputs passed directly to database queries. No parameterised statements.', risk: 'Authentication bypass; full data extraction of 50,000+ user records.', asset: 'Web Application (Customer Portal)', recommendation: 'Implement parameterised queries. Deploy WAF rules. Conduct code review.', severity: 'Critical' },
    { id: 2, title: 'Exposed Administrative Ports', issue: 'SSH (22) and RDP (3389) accessible from the public internet.', risk: 'Active brute-force activity detected in server logs. Full system compromise possible.', asset: 'Application Server (Ubuntu)', recommendation: 'Restrict access to VPN only via firewall ACL. Enable fail2ban and account lockout.', severity: 'Critical' },
    { id: 3, title: 'Default Credentials on Network Device', issue: 'Network firewall retains factory-default administrator password.', risk: 'Full network traffic interception and routing manipulation.', asset: 'Network Firewall', recommendation: 'Change all default credentials immediately. Implement privileged access management (PAM).', severity: 'Critical' },
    { id: 4, title: 'No Audit Logging on Database', issue: 'Database server has no query or access logging enabled.', risk: 'Unauthorised access cannot be detected. Violates ISO 27001 A.12.4.', asset: 'Database Server (MySQL)', recommendation: 'Enable MySQL general and slow query logs. Integrate with centralised SIEM.', severity: 'Critical' },
    { id: 5, title: 'Cross-Site Scripting (XSS)', issue: 'Three unescaped user input fields allow reflected XSS payloads.', risk: 'Session hijacking and credential theft via malicious scripts.', asset: 'Web Application (Customer Portal)', recommendation: 'Implement Content Security Policy header. Encode all output. Sanitise server-side.', severity: 'High' },
    { id: 6, title: 'End-of-Life Operating System', issue: 'Ubuntu 18.04 LTS reached end of life in April 2023. No further CVE patches.', risk: 'Multiple publicly known exploits available with no vendor remediation.', asset: 'Application Server (Ubuntu)', recommendation: 'Upgrade to Ubuntu 22.04 LTS or 24.04 LTS within 30 days.', severity: 'High' },
    { id: 7, title: 'Insufficient Password Policy', issue: 'HR system enforces minimum 6-character passwords with no complexity requirement.', risk: 'Account compromise via dictionary attack. Single-factor authentication only.', asset: 'HR Information System', recommendation: 'Enforce minimum 12 characters with complexity. Enable MFA. Set account lockout at 5 attempts.', severity: 'Medium' },
    { id: 8, title: 'Missing CSRF Protection', issue: 'State-changing form submissions lack anti-CSRF tokens.', risk: 'Authenticated users can be tricked into performing unintended actions via forged requests.', asset: 'Web Application (Customer Portal)', recommendation: 'Implement synchroniser token pattern or SameSite cookie attribute.', severity: 'Medium' },
  ],
  checklist: [
    { id: 1, text: 'Access control policies documented and approved', sub: 'ISO 27001 A.9.1', status: 'Compliant' },
    { id: 2, text: 'Multi-factor authentication enforced on critical systems', sub: 'NIST CSF PR.AC-7', status: 'Compliant' },
    { id: 3, text: 'Firewall rules reviewed and approved quarterly', sub: 'ISO 27001 A.13.1', status: 'Partially Compliant' },
    { id: 4, text: 'Patch management process defined and followed', sub: 'ISO 27001 A.12.6', status: 'Partially Compliant' },
    { id: 5, text: 'Security awareness training conducted annually', sub: 'ISO 27001 A.7.2', status: 'Compliant' },
    { id: 6, text: 'Incident response plan documented and tested', sub: 'NIST CSF RS.RP-1', status: 'Partially Compliant' },
    { id: 7, text: 'Data classification policy defined and communicated', sub: 'ISO 27001 A.8.2', status: 'Compliant' },
    { id: 8, text: 'Encryption applied at rest and in transit', sub: 'ISO 27001 A.10.1', status: 'Compliant' },
    { id: 9, text: 'Centralised audit logging on all critical systems', sub: 'ISO 27001 A.12.4', status: 'Non-Compliant' },
    { id: 10, text: 'Vulnerability scanning performed monthly', sub: 'NIST CSF ID.RA-1', status: 'Partially Compliant' },
    { id: 11, text: 'Third-party vendor risk assessments conducted', sub: 'ISO 27001 A.15.2', status: 'Non-Compliant' },
    { id: 12, text: 'Business continuity plan tested annually', sub: 'ISO 27001 A.17.1', status: 'Compliant' },
    { id: 13, text: 'Physical access controls for server rooms in place', sub: 'ISO 27001 A.11.1', status: 'Compliant' },
    { id: 14, text: 'CSRF protection on all web application forms', sub: 'OWASP ASVS 4.2', status: 'Non-Compliant' },
    { id: 15, text: 'Annual penetration testing conducted by third party', sub: 'PCI DSS Req 11.3', status: 'Not Applicable' },
    { id: 16, text: 'Network segmentation implemented and documented', sub: 'ISO 27001 A.13.1', status: 'Partially Compliant' },
    { id: 17, text: 'Data loss prevention (DLP) solution deployed', sub: 'NIST CSF PR.DS-5', status: 'Not Applicable' },
    { id: 18, text: 'Secure SDLC process followed for all applications', sub: 'ISO 27001 A.14.2', status: 'Compliant' },
  ],
  files: [
    { name: 'HTTPS_Config_Screenshot.png', size: '245 KB', type: 'image' },
    { name: 'Firewall_Policy_Report.pdf', size: '1.2 MB', type: 'pdf' },
    { name: 'Vulnerability_Scan_Results.xlsx', size: '380 KB', type: 'excel' },
  ],
};

function _get(k) { try { return JSON.parse(sessionStorage.getItem('cra_' + k)) || _defaults[k]; } catch { return _defaults[k]; } }
function _set(k, v) { try { sessionStorage.setItem('cra_' + k, JSON.stringify(v)); } catch { } }

window.S = {
  get assets() { return _get('assets'); }, setAssets(v) { _set('assets', v); },
  get findings() { return _get('findings'); }, setFindings(v) { _set('findings', v); },
  get checklist() { return _get('checklist'); }, setChecklist(v) { _set('checklist', v); },
  get files() { return _get('files'); }, setFiles(v) { _set('files', v); },
};

// ─── COLOR HELPERS ───────────────────────────────
window.critClass = c => c === 'Critical' ? 'badge-critical' : c === 'High' ? 'badge-high' : c === 'Medium' ? 'badge-medium' : 'badge-low';
window.ciaColor = v => v >= 4 ? 'var(--red)' : v <= 2 ? 'var(--blue)' : 'var(--t2)';

// ─── RISK MATRIX ─────────────────────────────────
window.renderMatrix = function (id) {
  const el = document.getElementById(id); if (!el) return;
  const grid = [[1, 2, 3, 4, 4], [2, 4, 6, 8, 10], [3, 6, 9, 12, 15], [4, 8, 12, 16, 20], [5, 10, 15, 20, 25]];
  const cls = v => v <= 4 ? 'mx-low' : v <= 9 ? 'mx-med' : v <= 14 ? 'mx-high' : 'mx-crit';
  const lbl = ['1', '2', '3', '4', '5'];
  let h = '<div class="mx-lbl vert" style="grid-row:1/6;font-size:9px;opacity:.6">Likelihood</div>';
  for (let r = 4; r >= 0; r--) {
    h += `<div class="mx-lbl" style="font-size:11px">${lbl[r]}</div>`;
    for (let c = 0; c < 5; c++) {
      const v = grid[r][c];
      h += `<div class="mx-cell ${cls(v)}" title="Likelihood ${r + 1} × Impact ${c + 1} = ${v}">${v}</div>`;
    }
  }
  h += '<div></div>' + lbl.map(l => `<div class="mx-lbl" style="font-size:11px">${l}</div>`).join('');
  el.innerHTML = h;
};

// ─── GAUGE ───────────────────────────────────────
window.drawGauge = function (id, pct) {
  const c = document.getElementById(id); if (!c) return;
  const ctx = c.getContext('2d');
  const cx = c.width / 2, cy = c.height - 10, r = 95;
  ctx.clearRect(0, 0, c.width, c.height);

  // Track (dark gray, non-compliant)
  ctx.beginPath(); ctx.arc(cx, cy, r, Math.PI, 0);
  ctx.strokeStyle = pct < 50 ? 'rgba(232,64,64,.18)' : 'rgba(255,255,255,.06)';
  ctx.lineWidth = 13; ctx.stroke();

  // Fill (blue = compliant portion; red if < 50%)
  const col = pct >= 50 ? '#4882f5' : '#e84040';
  ctx.beginPath(); ctx.arc(cx, cy, r, Math.PI, Math.PI + (pct / 100) * Math.PI);
  ctx.strokeStyle = col; ctx.lineWidth = 13; ctx.lineCap = 'round'; ctx.stroke();
};
