-- ============================================================
-- Security Audit Management System - MySQL Schema
-- Run: mysql -u root -p < schema_new.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS security_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE security_audit;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(200) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','auditor') NOT NULL DEFAULT 'auditor',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- AUDITS
-- ============================================================
CREATE TABLE IF NOT EXISTS audits (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    system_name      VARCHAR(200) NOT NULL,
    description      TEXT,
    audit_date       DATE NOT NULL,
    auditor_id       INT UNSIGNED NOT NULL,
    risk_score       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    risk_level       ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',
    compliance_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auditor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- AUDIT ANSWERS (checklist responses)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_answers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id    INT UNSIGNED NOT NULL,
    question    VARCHAR(255) NOT NULL,
    answer      ENUM('compliant','partial','non_compliant') NOT NULL DEFAULT 'compliant',
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- FINDINGS (auto-generated)
-- ============================================================
CREATE TABLE IF NOT EXISTS findings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id        INT UNSIGNED NOT NULL,
    finding_text    TEXT NOT NULL,
    risk_level      ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',
    recommendation  TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- EVIDENCE FILES
-- ============================================================
CREATE TABLE IF NOT EXISTS evidence (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id    INT UNSIGNED NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    description TEXT,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- AI REPORTS
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_reports (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id        INT UNSIGNED NOT NULL,
    version         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    analysis_text   LONGTEXT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_id (audit_id),
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED: Default Admin User
-- Email: admin@admin.com | Password: admin123
-- Change password after first login!
-- ============================================================
INSERT IGNORE INTO users (name, email, password, role) VALUES (
    'Administrator',
    'admin@admin.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
);

