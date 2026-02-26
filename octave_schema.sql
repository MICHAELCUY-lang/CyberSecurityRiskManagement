-- ============================================================
-- OCTAVE Allegro Extension Schema
-- Run AFTER schema_new.sql
-- Adds 8 OCTAVE Allegro methodology tables
-- ============================================================
USE security_audit;

-- ============================================================
-- STEP 1: Risk Measurement Criteria
-- Organization defines impact area priorities per audit
-- ============================================================
CREATE TABLE IF NOT EXISTS risk_criteria (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id           INT UNSIGNED NOT NULL,
    reputation_weight  TINYINT NOT NULL DEFAULT 3 COMMENT '1=Low 5=Critical',
    financial_weight   TINYINT NOT NULL DEFAULT 3,
    productivity_weight TINYINT NOT NULL DEFAULT 3,
    safety_weight      TINYINT NOT NULL DEFAULT 3,
    legal_weight       TINYINT NOT NULL DEFAULT 3,
    notes              TEXT,
    created_by         INT UNSIGNED NOT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_id)   REFERENCES audits(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STEP 2: Information Asset Profiles
-- One audit may cover multiple information assets
-- ============================================================
CREATE TABLE IF NOT EXISTS assets (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id             INT UNSIGNED NOT NULL,
    name                 VARCHAR(200) NOT NULL,
    description          TEXT,
    owner_name           VARCHAR(200),
    rationale            TEXT COMMENT 'Why is this asset critical?',
    cia_confidentiality  TINYINT NOT NULL DEFAULT 3 COMMENT '1-5',
    cia_integrity        TINYINT NOT NULL DEFAULT 3,
    cia_availability     TINYINT NOT NULL DEFAULT 3,
    primary_req          ENUM('C','I','A') NOT NULL DEFAULT 'C'
                         COMMENT 'Most critical security requirement',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STEP 3: Information Asset Containers
-- Where the asset is stored, transported, or processed
-- ============================================================
CREATE TABLE IF NOT EXISTS asset_containers (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id     INT UNSIGNED NOT NULL,
    type         ENUM('Technical','Physical','People') NOT NULL,
    location     ENUM('Internal','External') NOT NULL DEFAULT 'Internal',
    name         VARCHAR(200) NOT NULL,
    description  TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STEP 4: Areas of Concern
-- Per-container, free-form security concerns
-- ============================================================
CREATE TABLE IF NOT EXISTS areas_of_concern (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    container_id INT UNSIGNED NOT NULL,
    description  TEXT NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (container_id) REFERENCES asset_containers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STEP 5: Threat Scenarios
-- Structured: Actor → Access → Motive → Asset → Consequence
-- ============================================================
CREATE TABLE IF NOT EXISTS threat_scenarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concern_id    INT UNSIGNED NOT NULL,
    actor         ENUM('Internal Human','External Human','System','Natural') NOT NULL,
    access_method ENUM('Network','Physical','Remote','Supply Chain','Other') NOT NULL,
    motive        TEXT,
    consequence   ENUM('Disclosure','Modification','Destruction','Interruption') NOT NULL,
    description   TEXT COMMENT 'Free-form scenario narrative',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (concern_id) REFERENCES areas_of_concern(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STEP 6: Risk Identification
-- One risk record per threat scenario
-- ============================================================
CREATE TABLE IF NOT EXISTS risks (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scenario_id        INT UNSIGNED NOT NULL UNIQUE,
    cia_impacted       ENUM('C','I','A') NOT NULL DEFAULT 'C',
    consequence_detail TEXT,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scenario_id) REFERENCES threat_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STEP 7: Risk Analysis — Likelihood × Impact
-- Per-area impact scores weighted by risk_criteria Step 1
-- ============================================================
CREATE TABLE IF NOT EXISTS risk_analysis (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    risk_id              INT UNSIGNED NOT NULL UNIQUE,
    likelihood           TINYINT NOT NULL DEFAULT 3 COMMENT '1=Very Low 5=Very High',
    impact_reputation    TINYINT NOT NULL DEFAULT 3 COMMENT '1-5',
    impact_financial     TINYINT NOT NULL DEFAULT 3,
    impact_productivity  TINYINT NOT NULL DEFAULT 3,
    impact_safety        TINYINT NOT NULL DEFAULT 3,
    impact_legal         TINYINT NOT NULL DEFAULT 3,
    risk_score           DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    risk_level           ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (risk_id) REFERENCES risks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STEP 8: Risk Response Decisions
-- Mitigate / Accept / Transfer / Avoid per risk
-- ============================================================
CREATE TABLE IF NOT EXISTS risk_responses (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    risk_id           INT UNSIGNED NOT NULL UNIQUE,
    response          ENUM('Mitigate','Accept','Transfer','Avoid') NOT NULL DEFAULT 'Mitigate',
    rationale         TEXT,
    responsible_owner VARCHAR(200),
    target_date       DATE,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (risk_id) REFERENCES risks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Done
SELECT 'OCTAVE Allegro schema loaded: 8 tables created.' AS status;
