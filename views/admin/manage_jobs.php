<?php
session_start();
include_once('../../models/Job.php');

// Utwórz instancję klasy Job
$jobModel = new Job();

// Pobierz wszystkie ogłoszenia
$jobs = $jobModel->getAllJobs(); // Zakładam, że masz metodę getAllJobs() w modelu Job

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($searchTerm) {
    $jobs = $jobModel->searchJobs($searchTerm);
} else {
    $jobs = $jobModel->getAllJobs();
}
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
					<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <nav class="nav">
					<?php include 'sidebar.php'; ?>
                    </nav>
					<?php endif; ?>
                </div>

                <div class="card-body">
				
				 <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-1"><i class="bi bi-list-check"></i> Zarządzaj ogłoszeniami</h5>
	<nav class="nav">
        <form method="GET" class="d-flex">
		<div class="input-group mb-3">
        <input type="text" name="search" class="form-control" placeholder="Wyszukaj po ID, tytule lub opisie..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button class="btn btn-primary" type="submit">Szukaj</button>
    </div>
	
	

                </nav>
</div>
                <div class="card-body">
				

    <?php if (!empty($jobs)) : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th style="width: 1%">ID</th>
                    <th style="width: 34%">Tytuł</th>
                    <th style="width: 35%">Opis</th>
					<th style="width: 10%">Data dodania</th>
                    <th style="width: 5%">Status</th>
                    <th style="width: 15%">Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($job['id']); ?></td>
                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                        <td><?php echo htmlspecialchars($job['description']); ?></td>
						<td><?php echo htmlspecialchars($job['created_at']); ?></td>
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
</div></div></div>
        <div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
        </div>
  
            </div>	
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>