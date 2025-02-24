<?php
session_start();
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

// Ustawiamy nagłówki do pobrania pliku CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="raporty.csv"');

// Otwieramy strumień do zapisu
$output = fopen('php://output', 'w');

// Zapisujemy nagłówki kolumn do pliku CSV
fputcsv($output, ['ID użytkownika', 'Typ aktywności', 'Data', 'Szczegóły']);

// Zapisujemy dane raportów do pliku CSV
foreach ($reports as $report) {
    fputcsv($output, [
        $report['user_id'],
        $report['activity_type'],
        $report['timestamp'],
        $report['details']
    ]);
}

// Zamykamy strumień
fclose($output);
exit;
?>
