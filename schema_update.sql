-- 1. Modify users table
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'auditor', 'auditee') NOT NULL;

-- 2. Create organizations table
CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sector VARCHAR(100),
    employee_count INT,
    system_type VARCHAR(100),
    exposure_level VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Modify audits table
ALTER TABLE audits 
    ADD COLUMN organization_id INT DEFAULT NULL AFTER id,
    ADD COLUMN auditee_id INT DEFAULT NULL AFTER auditor_id,
    ADD COLUMN final_opinion ENUM('Secure', 'Acceptable Risk', 'Needs Immediate Action') DEFAULT NULL AFTER compliance_score;

-- 4. Modify assets table
ALTER TABLE assets
    ADD COLUMN criticality_score INT DEFAULT NULL AFTER cia_availability,
    ADD COLUMN criticality_level VARCHAR(20) DEFAULT NULL AFTER criticality_score;

-- 5. Create owasp_library table
CREATE TABLE IF NOT EXISTS owasp_library (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100),
    vuln_name VARCHAR(255),
    default_likelihood INT,
    mapped_threat VARCHAR(255),
    mapped_impact VARCHAR(255),
    required_control TEXT
);

-- 6. Seed owasp_library
INSERT INTO owasp_library (category, vuln_name, default_likelihood, mapped_threat, mapped_impact, required_control) VALUES
('Injection', 'SQL Injection', 3, 'Internal/External Attacker via Database Theft', 'Confidentiality Breach & Data Loss', 'Verify parameterized queries, ORM usage, and database input sanitization.'),
('Injection', 'Command Injection', 3, 'External Attacker executing OS commands', 'System Compromise', 'Verify input validation and avoidance of direct OS shell executions.'),
('Injection', 'LDAP Injection', 2, 'External Attacker via Directory Service', 'Unauthorized Access', 'Verify LDAP input sanitization and secure query construction.'),
('Broken Authentication', 'Weak Password Policy', 3, 'Attacker via Brute Force or Credential Stuffing', 'Account Takeover', 'Verify password complexity (12+ chars), lockout mechanism, and minimum length enforcement.'),
('Broken Authentication', 'No Account Lockout', 3, 'Attacker via Automated Password Guessing', 'Account Takeover', 'Verify rate limiting and account lockout after consecutive failed attempts.'),
('Broken Authentication', 'Session Hijacking', 2, 'Attacker stealing active user session', 'Account Compromise', 'Verify secure session cookies (HttpOnly, Secure) and session timeouts.'),
('Sensitive Data Exposure', 'No HTTPS / TLS', 2, 'Man-in-the-Middle (MitM) Attack', 'Data Exposure in Transit', 'Verify TLS certificate validity & HTTPS HSTS enforcement.'),
('Sensitive Data Exposure', 'Weak Encryption', 2, 'Attacker decrypting stolen data', 'Confidentiality Breach', 'Verify use of modern algorithms (AES-256) and secure key management.'),
('Sensitive Data Exposure', 'Exposed Database Backup', 3, 'Attacker finding unauthenticated backup files', 'Massive Data Loss', 'Verify backup storage access controls and encryption at rest.'),
('Access Control', 'IDOR', 3, 'Attacker manipulating object references', 'Unauthorized Data Access', 'Verify strict authorization checks for all user-requested resource IDs.'),
('Access Control', 'Privilege Escalation', 3, 'Attacker elevating rights to Administrator', 'System Compromise', 'Verify role-based access control (RBAC) and least-privilege principles.'),
('Security Misconfiguration', 'Default Credentials', 3, 'Attacker logging in with out-of-box passwords', 'System Takeover', 'Verify all default accounts/passwords have been disabled or changed.'),
('Security Misconfiguration', 'Directory Listing Enabled', 1, 'Attacker browsing file directory structure', 'Information Disclosure', 'Verify web server configuration disables directory browsing.'),
('Security Misconfiguration', 'Exposed Admin Panel', 2, 'Attacker targeting public-facing management UI', 'Unauthorized Access', 'Verify admin interfaces are restricted via VPN, IP whitelisting, or internal networks.'),
('Security Misconfiguration', 'Open Unneeded Ports', 2, 'Attacker scanning and exploiting available services', 'Network Intrusions', 'Verify firewall inbound/outbound rules & port exposure minimize attack surface.'),
('Cross-Site Attacks', 'XSS', 2, 'Attacker injecting client-side scripts', 'Account Compromise / Session Hijacking', 'Verify context-aware output encoding and Content Security Policy (CSP).'),
('Cross-Site Attacks', 'CSRF', 2, 'Attacker forcing unauthorized state changes', 'Unauthorized Action', 'Verify anti-CSRF tokens and SameSite cookie attributes.'),
('Logging & Monitoring', 'No Audit Logs', 1, 'Covert Activity by malicious insider/outsider', 'Incident Undetected', 'Verify comprehensive logging of authentication, admin actions, and key transactions.'),
('Dependency Issues', 'Outdated Server Software', 3, 'Remote Code Execution using known CVEs', 'System Takeover', 'Verify automated patch management and regular vulnerability scanning.');

-- 7. Create asset_vulnerabilities pivot table
CREATE TABLE IF NOT EXISTS asset_vulnerabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    vuln_id INT NOT NULL,
    assigned_likelihood INT,
    risk_score INT
);
