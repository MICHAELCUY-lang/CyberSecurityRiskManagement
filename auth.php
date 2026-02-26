<?php
/**
 * auth.php — Authentication Middleware
 * Include at top of every protected page AFTER session_start().
 * Usage:
 *   session_start();
 *   require_once __DIR__ . '/auth.php';
 *   // optionally: requireRole('admin');
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? 'Unknown',
        'role' => $_SESSION['user_role'] ?? 'auditor',
        'email'=> $_SESSION['user_email']?? '',
    ];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: auth/login.php');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    $user = currentUser();
    if ($user['role'] !== $role) {
        http_response_code(403);
        die('<div style="font-family:monospace;padding:40px;background:#111;color:#fff;border-left:4px solid #dc2626;">
            <strong>403 Forbidden</strong><br>You do not have permission to access this page.
            <br><br><a href="dashboard.php" style="color:#aaa;">← Return to Dashboard</a>
        </div>');
    }
}
