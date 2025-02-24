<?php
session_start();
require_once('../../vendor/autoload.php');
include_once('../../models/Report.php');
include_once('../../models/Database.php');

// Uzyskanie połączenia z bazą danych
$pdo = Database::getConnection();

// Tworzymy instancję modelu Report
$reportModel = new Report($pdo);

// Pobieramy dane z formularza
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'timestamp'; // Domyślnie sortujemy po dacie

// Pobieramy raporty na podstawie filtrów
$reports = $reportModel->getUserActivityReports($userId, $activityType, $searchTerm, $startDate, $endDate, $sortBy);

// Tworzymy obiekt TCPDF
$pdf = new TCPDF();
$pdf->AddPage();

// Ustawiamy tytuł
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Raporty aktywności użytkowników', 0, 1, 'C');

// Ustawiamy czcionkę dla tabeli
$pdf->SetFont('helvetica', '', 12);

// Nagłówki tabeli
$pdf->Cell(40, 10, 'ID użytkownika', 1);
$pdf->Cell(40, 10, 'Typ aktywności', 1);
$pdf->Cell(40, 10, 'Data', 1);
$pdf->Cell(70, 10, 'Szczegóły', 1);
$pdf->Ln();

// Zawartość tabeli
foreach ($reports as $report) {
    $pdf->Cell(40, 10, $report['user_id'], 1);
    $pdf->Cell(40, 10, $report['activity_type'], 1);
    $pdf->Cell(40, 10, $report['timestamp'], 1);
    $pdf->Cell(70, 10, $report['details'], 1);
    $pdf->Ln();
}

// Zapisz plik PDF
$pdf->Output('raporty.pdf', 'D');
exit;
?>
