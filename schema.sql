-- ============================================================
-- OCTAVE Allegro Audit Platform - MySQL Schema
-- Run: mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS octave_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE octave_audit;

-- ============================================================
-- ORGANIZATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS organizations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    sector          VARCHAR(100) NOT NULL,
    employee_count  INT UNSIGNED DEFAULT 0,
    system_type     VARCHAR(150),
    exposure_level  ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- ASSETS
-- ============================================================
CREATE TABLE IF NOT EXISTS assets (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     INT UNSIGNED NOT NULL,
    asset_name          VARCHAR(200) NOT NULL,
    owner               VARCHAR(150),
    location            VARCHAR(200),
    asset_type          ENUM('Hardware','Software','Data','People','Process','Facility','Network') NOT NULL,
    cia_confidentiality TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- 1-3
    cia_integrity       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    cia_availability    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    criticality_score   TINYINT UNSIGNED NOT NULL DEFAULT 3,  -- sum of CIA (3-9)
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- VULNERABILITIES (OWASP-based library)
-- ============================================================
CREATE TABLE IF NOT EXISTS vulnerabilities (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(200) NOT NULL,
    category            VARCHAR(100) NOT NULL,
    impact_description  TEXT,
    default_likelihood  TINYINT UNSIGNED NOT NULL DEFAULT 3,  -- 1-5
    default_impact      TINYINT UNSIGNED NOT NULL DEFAULT 3   -- 1-5
) ENGINE=InnoDB;

-- ============================================================
-- ASSET <-> VULNERABILITY MAPPING (risk register)
-- ============================================================
CREATE TABLE IF NOT EXISTS asset_vulnerabilities (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id        INT UNSIGNED NOT NULL,
    vulnerability_id INT UNSIGNED NOT NULL,
    likelihood      TINYINT UNSIGNED NOT NULL DEFAULT 3,
    impact          TINYINT UNSIGNED NOT NULL DEFAULT 3,
    risk_score      TINYINT UNSIGNED NOT NULL DEFAULT 9,
    risk_level      ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (vulnerability_id) REFERENCES vulnerabilities(id) ON DELETE CASCADE,
    UNIQUE KEY uq_asset_vuln (asset_id, vulnerability_id)
) ENGINE=InnoDB;

-- ============================================================
-- AUDIT CHECKLIST (reusable items, framework-tagged)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_checklist (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(200) NOT NULL,
    description     TEXT,
    framework_source VARCHAR(100) DEFAULT 'OCTAVE Allegro'
) ENGINE=InnoDB;

-- ============================================================
-- AUDIT RESULTS (per asset per checklist item)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_results (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checklist_id    INT UNSIGNED NOT NULL,
    asset_id        INT UNSIGNED NOT NULL,
    status          ENUM('compliant','partial','non_compliant','not_applicable') NOT NULL DEFAULT 'not_applicable',
    notes           TEXT,
    audited_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES audit_checklist(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    UNIQUE KEY uq_checklist_asset (checklist_id, asset_id)
) ENGINE=InnoDB;

-- ============================================================
-- EVIDENCE FILES
-- ============================================================
CREATE TABLE IF NOT EXISTS evidence_files (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id    INT UNSIGNED NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    file_type   VARCHAR(50),
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- COMPLIANCE SCORES
-- ============================================================
CREATE TABLE IF NOT EXISTS compliance_scores (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     INT UNSIGNED NOT NULL,
    score_percentage    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status              ENUM('Compliant','Needs Improvement','Non-Compliant') NOT NULL DEFAULT 'Non-Compliant',
    calculated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_org_score (organization_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- FINDINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS findings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id        INT UNSIGNED NOT NULL,
    issue           TEXT NOT NULL,
    risk_level      ENUM('Low','Medium','High','Critical') NOT NULL,
    recommendation  TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED: OWASP Vulnerabilities
-- ============================================================
INSERT INTO vulnerabilities (name, category, impact_description, default_likelihood, default_impact) VALUES
('Injection (SQL/Command/LDAP)',        'OWASP A03', 'Attacker sends malicious data to an interpreter, potentially extracting or corrupting database contents.', 4, 5),
('Broken Authentication',              'OWASP A07', 'Weaknesses in authentication allow credential stuffing, brute force, or session hijacking.', 4, 4),
('Sensitive Data Exposure',            'OWASP A02', 'Insufficient protection of sensitive data at rest or in transit allows unauthorized access.', 3, 5),
('XML External Entities (XXE)',        'OWASP A05', 'Poorly configured XML processors evaluate external entity references within XML documents.', 2, 4),
('Broken Access Control',             'OWASP A01', 'Users can act outside of their intended permissions, accessing unauthorized data or functions.', 4, 5),
('Security Misconfiguration',         'OWASP A05', 'Insecure default configuration, incomplete setup, or exposed cloud storage.', 5, 4),
('Cross-Site Scripting (XSS)',         'OWASP A03', 'Attackers inject client-side scripts into web pages viewed by other users.', 4, 3),
('Insecure Deserialization',           'OWASP A08', 'Flaws in deserialization lead to remote code execution or privilege escalation.', 2, 5),
('Using Components with Known Vulnerabilities', 'OWASP A06', 'Libraries and frameworks with known vulnerabilities are exploited.', 3, 4),
('Insufficient Logging and Monitoring','OWASP A09', 'Lack of logging enables attacks to persist undetected for extended periods.', 5, 4),
('Weak Password Policy',              'Access Control', 'Easily guessable passwords allow unauthorized system access.', 4, 4),
('No HTTPS / Insecure Transport',     'Network Security', 'Data transmitted in plaintext is interceptable via man-in-the-middle attacks.', 3, 5),
('Unpatched Software',                'Patch Management', 'Known CVEs in unpatched software provide known attack vectors.', 4, 4),
('Phishing / Social Engineering',     'Human Factor', 'Users are deceived into revealing credentials or installing malware.', 5, 4),
('Insider Threat',                    'Human Factor', 'Malicious or negligent internal users misuse legitimate access.', 2, 5),
('Denial of Service (DoS)',           'Availability', 'Overwhelming system resources renders services unavailable.', 3, 4),
('Physical Access Vulnerability',     'Physical Security', 'Lack of physical controls allows unauthorized access to hardware.', 2, 3),
('Inadequate Backup and Recovery',    'Business Continuity', 'Absence of tested backups results in prolonged downtime or data loss after incidents.', 3, 5);

-- ============================================================
-- SEED: Audit Checklist
-- ============================================================
INSERT INTO audit_checklist (title, description, framework_source) VALUES
('Access Control Policy Review',           'Verify that access control policies are documented, enforced, and reviewed periodically.', 'ISO 27001 A.9'),
('Password Complexity Enforcement',        'Verify password complexity, length requirements, and expiry policies are enforced.', 'NIST SP 800-63B'),
('Multi-Factor Authentication',            'Confirm MFA is enabled for all privileged accounts and remote access systems.', 'CIS Control 6'),
('Patch Management Process',               'Validate that a formal patch management process exists and is followed within defined SLAs.', 'ISO 27001 A.12.6'),
('TLS Certificate and HTTPS Enforcement',  'Verify all web interfaces enforce HTTPS with valid TLS certificates and HSTS headers.', 'OWASP TLS'),
('Firewall Rule Review',                   'Review firewall rule sets for unnecessary access and confirm default-deny posture.', 'CIS Control 9'),
('Data Encryption at Rest',                'Verify sensitive data is encrypted at rest using approved encryption standards (AES-256).', 'NIST SP 800-111'),
('Audit Log Completeness',                 'Confirm that security events are logged, stored securely, and reviewed regularly.', 'ISO 27001 A.12.4'),
('Incident Response Plan',                 'Verify an incident response plan exists, is tested annually, and staff are trained.', 'NIST SP 800-61'),
('Backup and Recovery Testing',            'Confirm backups are taken regularly and recovery procedures are tested at least annually.', 'ISO 27001 A.12.3'),
('Physical Access Controls',               'Verify physical access to server rooms and critical infrastructure is restricted and logged.', 'ISO 27001 A.11'),
('Vendor / Third-Party Risk Assessment',   'Confirm third-party vendors with system access are assessed for security risk.', 'OCTAVE Allegro'),
('Security Awareness Training',            'Verify employees receive annual security awareness training covering phishing and policies.', 'CIS Control 14'),
('Vulnerability Scanning',                 'Confirm regular vulnerability scans are performed and findings are remediated.', 'CIS Control 7'),
('Software Inventory Management',          'Validate that an up-to-date inventory of all software and licenses is maintained.', 'CIS Control 2');
