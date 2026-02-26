<?php
// index.php — Entry point: redirect to dashboard (or login)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: auth/login.php');
}
exit;
