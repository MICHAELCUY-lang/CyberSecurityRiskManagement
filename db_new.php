<?php
/**
 * db_new.php — PDO connection for the Security Audit database (singleton)
 */
require_once __DIR__ . '/config.php';

function getAuditDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=security_audit;charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;padding:20px;background:#111;color:#fff;border-left:4px solid #dc2626;">
                <strong>Database Error</strong><br>' . htmlspecialchars($e->getMessage()) . '
                <br><br>Ensure the <code>security_audit</code> database exists. Run <code>schema_new.sql</code> first.
            </div>');
        }
    }
    return $pdo;
}
