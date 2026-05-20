<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();
require_once('../../vendor/autoload.php');
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


$pdf = new TCPDF();
$pdf->AddPage();


$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, __t('admin.export.activity_reports_title'), 0, 1, 'C');


$pdf->SetFont('helvetica', '', 12);


$pdf->Cell(40, 10, __t('admin.export.user_id'), 1);
$pdf->Cell(40, 10, __t('admin.export.activity_type'), 1);
$pdf->Cell(40, 10, __t('admin.reports.col.date'), 1);
$pdf->Cell(70, 10, __t('admin.export.details'), 1);
$pdf->Ln();


foreach ($reports as $report) {
    $pdf->Cell(40, 10, $report['user_id'], 1);
    $pdf->Cell(40, 10, $report['activity_type'], 1);
    $pdf->Cell(40, 10, $report['timestamp'], 1);
    $pdf->Cell(70, 10, $report['details'], 1);
    $pdf->Ln();
}


$pdf->Output('raporty.pdf', 'D');
exit;
?>
