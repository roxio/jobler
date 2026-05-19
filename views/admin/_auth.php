<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/models/AccessControl.php';

function currentAccessControl() {
    static $accessControl = null;

    if ($accessControl === null) {
        $accessControl = new AccessControl();
    }

    return $accessControl;
}

function requireAdminAccess($permissionKey = null) {
    $accessControl = currentAccessControl();
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId || !$accessControl->hasAnyAdminAccess((int)$userId)) {
        denyAdminAccess();
    }

    if ($permissionKey === null) {
        $permissionKey = $accessControl->permissionForAdminFile(basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php'));
    }

    if (!$accessControl->hasPermission((int)$userId, $permissionKey)) {
        denyAdminAccess();
    }
}

function canAdminAccess($permissionKey) {
    $userId = $_SESSION['user_id'] ?? null;
    return $userId ? currentAccessControl()->hasPermission((int)$userId, $permissionKey) : false;
}

function denyAdminAccess() {
    header('HTTP/1.0 403 Forbidden');
    echo 'Brak uprawnien do przegladania tej strony.';
    exit;
}
?>
