<?php
include_once('../../models/Job.php');

// Utwórz instancję klasy Job
$jobModel = new Job();

// Pobierz wszystkie ogłoszenia
$jobs = $jobModel->getAllJobs(); // Zakładam, że masz metodę getAllJobs() w modelu Job
?>

<?php include '../partials/header.php'; ?>

<div class="container">
    <h1>Zarządzaj ogłoszeniami</h1>

    <?php if (!empty($jobs)) : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tytuł</th>
                    <th>Opis</th>
                    <th>Status</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($job['id']); ?></td>
                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                        <td><?php echo htmlspecialchars($job['description']); ?></td>
                        <td><?php echo htmlspecialchars($job['status']); ?></td>
                        <td>
                            <a href="../admin/edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-warning">Edytuj</a>
                            <a href="../admin/delete_job.php?id=<?php echo $job['id']; ?>" class="btn btn-danger" onclick="return confirm('Na pewno chcesz usunąć to ogłoszenie?')">Usuń</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>Brak ogłoszeń w systemie.</p>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>