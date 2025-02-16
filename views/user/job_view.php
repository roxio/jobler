<?php
// Załaduj modele
include_once('../../models/Job.php');

// Utwórz instancję klasy Job
$jobModel = new Job();

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

// Pobierz ogłoszenia użytkownika
$userId = $_SESSION['user_id'];
$jobs = $jobModel->getUserJobs($userId);
?>

<div class="job-list">
    <h2>Twoje ogłoszenia</h2>

    <?php if (!empty($jobs)) : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tytuł</th>
                    <th>Opis</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($job['id']); ?></td>
                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                        <td><?php echo htmlspecialchars($job['description']); ?></td>
                        <td><?php echo htmlspecialchars($job['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>Nie masz żadnych ogłoszeń. <a href="create_job.php">Dodaj nowe ogłoszenie</a></p>
    <?php endif; ?>
</div>
