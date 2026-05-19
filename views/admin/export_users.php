<?php
require_once __DIR__ . '/_auth.php';
requireAdminAccess();
include_once('../../models/User.php');
include_once('../../models/Language.php');
$userModel = new User();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, [
    __t('admin.reports.col.id'),
    __t('admin.export.name'),
    __t('admin.reports.col.email'),
    __t('admin.common.role')
]);

$users = $userModel->getAllUsers();
foreach ($users as $user) {
    fputcsv($output, $user);
}
fclose($output);
exit;
