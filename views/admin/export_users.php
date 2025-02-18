<?php
include_once('../../models/User.php');
$userModel = new User();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Nazwa', 'E-mail', 'Rola']);

$users = $userModel->getAllUsers();
foreach ($users as $user) {
    fputcsv($output, $user);
}
fclose($output);
exit;
