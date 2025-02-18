<?php
include_once('../../models/Report.php');
include_once('../../models/Database.php'); // Połączenie z bazą danych

// Uzyskanie połączenia z bazą danych
$pdo = Database::getConnection();

// Tworzymy instancję modelu Report
$reportModel = new Report($pdo);

// Walidacja parametrów (przykładowe wartości, powinny pochodzić z formularza lub GET/POST)
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;

$userReports = [];
$jobReports = [];

// Pobranie raportów, jeśli ID użytkownika lub ogłoszenia zostało przekazane
if ($userId) {
    $userReports = $reportModel->getUserActivityReports($userId);
}

if ($jobId) {
    $jobReports = $reportModel->getJobReports($jobId);
}

include '../partials/header.php';
?>
<h1>Raporty</h1>

<div>
    <?php if ($userId): ?>
        <h3>Raporty aktywności użytkowników (ID użytkownika: <?php echo $userId; ?>)</h3>
        <table>
            <thead>
                <tr>
                    <th>ID użytkownika</th>
                    <th>Typ aktywności</th>
                    <th>Data</th>
                    <th>Szczegóły</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userReports as $report): ?>
                    <tr>
                        <td><?php echo $report['user_id']; ?></td>
                        <td><?php echo $report['activity_type']; ?></td>
                        <td><?php echo $report['timestamp']; ?></td>
                        <td><?php echo $report['details']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($jobId): ?>
        <h3>Raporty ogłoszeń (ID ogłoszenia: <?php echo $jobId; ?>)</h3>
        <table>
            <thead>
                <tr>
                    <th>ID ogłoszenia</th>
                    <th>Typ aktywności</th>
                    <th>Data</th>
                    <th>Szczegóły</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobReports as $report): ?>
                    <tr>
                        <td><?php echo $report['job_id']; ?></td>
                        <td><?php echo $report['activity_type']; ?></td>
                        <td><?php echo $report['timestamp']; ?></td>
                        <td><?php echo $report['details']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
include '../partials/footer.php';
?>
