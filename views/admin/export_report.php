<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();
include_once('../../models/Report.php');
include_once('../../models/Database.php');
include_once('../../models/Language.php');


$pdo = Database::getConnection();


$reportModel = new Report($pdo);


$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'timestamp';


$reports = $reportModel->getUserActivityReports($userId, $activityType, $searchTerm, $startDate, $endDate, $sortBy);


header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="raporty.csv"');


$output = fopen('php://output', 'w');


fputcsv($output, [
    __t('admin.export.user_id'),
    __t('admin.export.activity_type'),
    __t('admin.reports.col.date'),
    __t('admin.export.details')
]);


foreach ($reports as $report) {
    fputcsv($output, [
        $report['user_id'],
        $report['activity_type'],
        $report['timestamp'],
        $report['details']
    ]);
}


fclose($output);
exit;
?>
