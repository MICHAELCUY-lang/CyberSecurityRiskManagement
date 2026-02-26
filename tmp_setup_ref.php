<?php
require 'db_new.php';
$db = getAuditDB();

$db->exec("
CREATE TABLE IF NOT EXISTS system_references (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    badge VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    citation TEXT NOT NULL,
    link VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_references (id, title, badge, description, citation, link) VALUES 
(1, 'OCTAVE Allegro', 'Primary Framework', 'The primary methodology driving asset profiling, container identification, threat scenario building, and risk register analysis in this platform is OCTAVE Allegro. It focuses heavily on information assets and their containers to drastically reduce the complexity of comprehensive risk assessments.', 'Caralli, R. A., Stevens, J. F., Young, L. R., & Wilson, W. R. (2007). Introducing OCTAVE Allegro: Improving the Information Security Risk Assessment Process (CMU/SEI-2007-TR-012). Software Engineering Institute, Carnegie Mellon University. http://resources.sei.cmu.edu/library/asset-view.cfm?AssetID=8419', 'https://resources.sei.cmu.edu/library/asset-view.cfm?assetid=8419'),
(2, 'NIST Risk Management Framework (RMF)', 'Supporting Concept', 'While the specific 8-step process follows OCTAVE Allegro, the generalized risk calculation (Likelihood × Impact) and compliance checklist concepts align closely with the principles outlined in the NIST Special Publication 800-30 (Guide for Conducting Risk Assessments).', 'Joint Task Force Transformation Initiative. (2012). Guide for Conducting Risk Assessments (NIST SP 800-30 Rev. 1). National Institute of Standards and Technology. https://doi.org/10.6028/NIST.SP.800-30r1', 'https://csrc.nist.gov/pubs/sp/800/30/r1/final'),
(3, 'OWASP Risk Rating Methodology', 'Scoring Inference', 'The categorization of Risk Levels (Low, Medium, High, Critical) and the approach to linking Threat Agents, Vulnerability Factors, and Business Impacts draws conceptual inspiration from the Open Web Application Security Project (OWASP) Risk Rating methodology to ensure consistent and standard reporting output.', 'OWASP Foundation. OWASP Risk Rating Methodology. https://owasp.org/www-community/threat-modeling/OWASP_Risk_Rating_Methodology', 'https://owasp.org/www-community/OWASP_Risk_Rating_Methodology');
");

echo "Schema updated successfully.\n";
