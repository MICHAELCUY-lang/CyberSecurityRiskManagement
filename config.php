<?php
/**
 * OCTAVE Allegro - Configuration
 * Reads credentials from .env file. Never expose this file publicly.
 */

// Load .env file
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Database
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'octave_audit');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// AI
define('AI_API_KEY',  $_ENV['AI_API_KEY']  ?? '');
define('AI_PROVIDER', strtolower($_ENV['AI_PROVIDER'] ?? 'groq')); // 'openai', 'groq', or 'gemini'
define('AI_MODEL',    $_ENV['AI_MODEL']    ?? 'llama-3.3-70b-versatile');

// Build API URL based on provider
define('AI_API_URL', (function() {
    switch (AI_PROVIDER) {
        case 'gemini':
            // Gemini REST — key embedded in URL
            return 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_MODEL . ':generateContent?key=' . AI_API_KEY;
        case 'groq':
            // Groq — OpenAI-compatible endpoint
            return 'https://api.groq.com/openai/v1/chat/completions';
        default:
            // OpenAI
            return $_ENV['AI_API_URL'] ?? 'https://api.openai.com/v1/chat/completions';
    }
})());

// File uploads
define('UPLOAD_DIR',      __DIR__ . '/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_ALLOWED',  ['jpg','jpeg','png','gif','pdf','txt']);

// App
define('APP_NAME', 'OCTAVE Allegro Audit Platform');
