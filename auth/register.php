<?php
/**
 * auth/register.php — User Registration
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_new.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard.php'); exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $role     = in_array($_POST['role'] ?? '', ['admin','auditor']) ? $_POST['role'] : 'auditor';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getAuditDB();
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
            $ins->execute([$name, $email, $hash, $role]);
            $success = 'Account created! You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Security Audit Platform</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:         #0a0a0a;
            --bg-card:    #141414;
            --bg-el:      #1e1e1e;
            --border:     #2a2a2a;
            --border-l:   #383838;
            --text:       #f0f0f0;
            --text-muted: #888;
            --text-dim:   #555;
            --red:        #dc2626;
            --blue:       #2563eb;
        }
        html, body { height: 100%; background: var(--bg); color: var(--text);
            font-family: 'Segoe UI', system-ui, sans-serif; font-size: 14px; }
        .auth-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .auth-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
        }
        .auth-logo { text-align: center; margin-bottom: 28px; }
        .auth-logo .brand { font-size: 13px; font-weight: 800; letter-spacing: .15em; text-transform: uppercase; }
        .auth-logo .sub   { font-size: 10px; color: var(--text-dim); margin-top: 6px; letter-spacing: .1em; text-transform: uppercase; }
        .auth-title { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
        .auth-sub   { font-size: 12px; color: var(--text-muted); margin-bottom: 24px; }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
                color: var(--text-muted); margin-bottom: 6px; }
        input[type=text], input[type=email], input[type=password], select {
            width: 100%; background: var(--bg); border: 1px solid var(--border-l); border-radius: 3px;
            color: var(--text); padding: 10px 12px; font-size: 13px; font-family: inherit; outline: none;
            transition: border-color .15s;
        }
        input:focus, select:focus { border-color: #fff; }
        select option { background: var(--bg-card); }
        .btn-primary { width: 100%; background: #fff; color: #000; border: none; border-radius: 3px;
            padding: 10px; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            cursor: pointer; transition: background .15s; margin-top: 8px; }
        .btn-primary:hover { background: #d0d0d0; }
        .alert-error   { background: #1a0000; border-left: 3px solid var(--red); color: #ff6b6b;
            padding: 10px 14px; border-radius: 3px; font-size: 12px; margin-bottom: 18px; }
        .alert-success { background: #0a1a0a; border-left: 3px solid #22c55e; color: #86efac;
            padding: 10px 14px; border-radius: 3px; font-size: 12px; margin-bottom: 18px; }
        .auth-link { text-align: center; margin-top: 20px; font-size: 12px; color: var(--text-muted); }
        .auth-link a { color: var(--text); text-decoration: none; font-weight: 600; }
        .auth-link a:hover { text-decoration: underline; }
        .divider { width: 40px; height: 1px; background: var(--border); margin: 0 auto 20px; }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="brand">🔒 Security Audit</div>
            <div class="sub">Management Platform</div>
        </div>
        <div class="divider"></div>
        <div class="auth-title">Create Account</div>
        <div class="auth-sub">Register to access the audit platform</div>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    placeholder="John Smith">
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    placeholder="auditor@company.com">
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="auditor" <?= ($_POST['role'] ?? '') === 'auditor' ? 'selected' : '' ?>>Auditor</option>
                    <option value="admin"   <?= ($_POST['role'] ?? '') === 'admin'   ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Minimum 6 characters">
            </div>
            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" required placeholder="Repeat password">
            </div>
            <button type="submit" class="btn-primary">Create Account →</button>
        </form>

        <div class="auth-link">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</div>
</body>
</html>
