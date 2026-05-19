<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
if (isAdminLoggedIn()) {
    logAdminAction($_SESSION['admin_id'], 'logout');
    logoutAdmin();
}
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
