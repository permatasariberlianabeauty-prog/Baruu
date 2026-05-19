<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();

if (isLoggedIn()) {
    // Remove DB session token
    db()->execute(
        'DELETE FROM user_sessions WHERE user_id = ?',
        'i', $_SESSION['user_id']
    );
    // Optionally trigger popup before session clear
    // (can't show after logout, noted for UX)
    logoutUser();
}

header('Location: ' . BASE_URL . '/auth/login.php');
exit;
