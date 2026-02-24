<?php
/**
 * OCTAVE Allegro - PDO Database Connection (singleton)
 */

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;padding:20px;background:#111;color:#fff;border-left:4px solid #fff;">
                <strong>Database Connection Error</strong><br>' . htmlspecialchars($e->getMessage()) . '
                <br><br>Please configure your <code>.env</code> file and ensure MySQL is running.
            </div>');
        }
    }
    return $pdo;
}
