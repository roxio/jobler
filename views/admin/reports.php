<?php
session_start();
include_once('../../models/Report.php');
include_once('../../models/Database.php'); // Połączenie z bazą danych

// Uzyskanie połączenia z bazą danych
$pdo = Database::getConnection();

// Tworzymy instancję modelu Report
$reportModel = new Report($pdo);

// Zmienna do przechowywania komunikatów
$message = "";

// Walidacja parametrów (przykładowe wartości, powinny pochodzić z formularza lub GET/POST)
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
$searchTerm = isset($_GET['search']) ? $_GET['search'] : ''; // Wyszukiwanie
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : ''; // Filtrowanie po dacie
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : ''; // Filtrowanie po dacie
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Numer strony

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Domyślnie 10 raportów na stronę
$offset = ($page - 1) * $limit;

$totalPagesUser = 1; // Domyślna wartość, aby uniknąć błędu

$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$jobStatus = isset($_GET['job_status']) ? $_GET['job_status'] : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'timestamp'; // Domyślnie sortujemy po dacie

// Pobranie raportów użytkowników
$userReports = $reportModel->getUserActivityReports($userId, $activityType, $searchTerm, $startDate, $endDate, $sortBy, $limit, $offset);

// Pobranie dostępnych użytkowników
$query = "SELECT id, username FROM users";
$stmt = $pdo->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pobranie raportów, jeśli ID użytkownika lub ogłoszenia zostało przekazane
if ($userId) {
    $userReports = $reportModel->getUserActivityReports($userId, $searchTerm, $startDate, $endDate, $limit, $offset);
    $totalUserReports = $reportModel->countUserReports($userId, $searchTerm, $startDate, $endDate);
    $totalPagesUser = ceil($totalUserReports / $limit);
}

if ($jobId) {
    $jobReports = $reportModel->getJobReports($jobId, $searchTerm, $startDate, $endDate, $limit, $offset);
    $totalJobReports = $reportModel->countJobReports($jobId, $searchTerm, $startDate, $endDate);
    $totalPagesJob = ceil($totalJobReports / $limit);
}

// Obsługuje dodanie raportu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : null;
    $activityType = isset($_POST['activity_type']) ? $_POST['activity_type'] : '';
    $details = isset($_POST['details']) ? $_POST['details'] : '';

    if ($activityType && $details) {
        if ($userId && !$jobId) {
            // Dodanie raportu aktywności użytkownika
            $reportModel->addUserActivityReport($userId, $activityType, $details);
            $message = "Raport aktywności użytkownika został dodany.";
        } elseif ($jobId && !$userId) {
            // Dodanie raportu ogłoszenia
            $reportModel->addJobReport($jobId, $activityType, $details);
            $message = "Raport ogłoszenia został dodany.";
        } elseif ($userId && $activityType === 'payment') {
            // Dodanie raportu płatności (jeśli dotyczy płatności)
            $reportModel->addPaymentReport($userId, $details);
            $message = "Raport płatności został dodany.";
        } else {
            $message = "Nieprawidłowe dane - wybierz użytkownika lub ogłoszenie!";
        }
    } else {
        $message = "Wszystkie pola muszą być wypełnione.";
    }
}

function checkPermission($userId, $permissionType) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM user_permissions WHERE user_id = :user_id AND permission_type = :permission_type");
    $stmt->execute(['user_id' => $userId, 'permission_type' => $permissionType]);
    return $stmt->rowCount() > 0;
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
    <h5 class="mb-1"><i class="bi bi-info-square"></i> Raporty</h5>
</div>
<div class="card-header d-flex justify-content-between align-items-center">
 <nav class="nav">

            <!-- Formularz filtrowania raportów -->
            <form action="reports.php" method="get" class="d-flex flex-wrap">
               <div class="col-auto me-1 mb-2">
			   <input type="text" name="search" placeholder="Wyszukaj raport..." value="<?php echo htmlspecialchars($searchTerm); ?>" />
                </div>
                <!-- Filtracja po typie aktywności -->
                <select name="activity_type" class="col-auto me-1 mb-2">
                    <option value="">Wybierz typ aktywności</option>
                    <option value="login" <?php echo ($activityType == 'login' ? 'selected' : ''); ?>>Logowanie</option>
                    <option value="post_job" <?php echo ($activityType == 'post_job' ? 'selected' : ''); ?>>Dodanie ogłoszenia</option>
                    <option value="apply_job" <?php echo ($activityType == 'apply_job' ? 'selected' : ''); ?>>Aplikacja na ogłoszenie</option>
                </select>

                <!-- Filtracja po stanie ogłoszenia -->
                <select name="job_status" class="col-auto me-1 mb-2">
                    <option value="">Wybierz status ogłoszenia</option>
                    <option value="active" <?php echo ($jobStatus == 'active' ? 'selected' : ''); ?>>Aktywne</option>
                    <option value="closed" <?php echo ($jobStatus == 'closed' ? 'selected' : ''); ?>>Zakończone</option>
                </select>

                <input class="col-auto me-1 mb-2" type="date" name="start_date" value="<?php echo $startDate; ?>" />
                <input class="col-auto me-1 mb-2" type="date" name="end_date" value="<?php echo $endDate; ?>" />
                
                <!-- Sortowanie wyników -->
                <select name="sort_by" class="col-auto me-1 mb-2">
                    <option value="timestamp" <?php echo ($sortBy == 'timestamp' ? 'selected' : ''); ?>>Data</option>
                    <option value="activity_type" <?php echo ($sortBy == 'activity_type' ? 'selected' : ''); ?>>Typ aktywności</option>
                    <option value="user_id" <?php echo ($sortBy == 'user_id' ? 'selected' : ''); ?>>ID użytkownika</option>
                </select>

                <button type="submit" class="btn btn-primary col-auto"><i class="bi bi-filter-square"></i></button> 
				
                
                    <a href="export_report.php?user_id=<?php echo $userId; ?>&activity_type=<?php echo $activityType; ?>&search=<?php echo $searchTerm; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&sort_by=<?php echo $sortBy; ?>" class="btn btn-secondary"><i class="bi bi-filetype-csv"></i></a>
               
                    <a href="export_pdf.php?user_id=<?php echo $userId; ?>&activity_type=<?php echo $activityType; ?>&search=<?php echo $searchTerm; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&sort_by=<?php echo $sortBy; ?>" class="btn btn-secondary"><i class="bi bi-filetype-pdf"></i></a>
                
            </form>
	</nav>
	</div>
                <div class="card-body">
                
			
                    
							
            <div class="row">
            <!-- Przyciski lub linki do generowania nowych raportów -->
            <div>
                <a href="reports.php?generate=true" class="btn btn-primary">Generuj raport</a>
            </div>

            <?php if ($message): ?>
                <p><?php echo $message; ?></p>
            <?php endif; ?>

            <?php if (isset($_GET['generate'])): ?>
                <!-- Formularz do generowania raportu -->
                <form method="post" action="">
                    <div>
                        <label for="activity_type">Typ aktywności:</label>
                        <select name="activity_type" id="activity_type" required>
                            <option value="login">Logowanie</option>
                            <option value="post_job">Dodanie ogłoszenia</option>
                            <option value="apply_job">Aplikacja na ogłoszenie</option>
                        </select>
                    </div>

                    <!-- Formularz dla raportu użytkownika -->
                    <div>
                        <label for="user_id">ID użytkownika:</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">Wybierz użytkownika</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Formularz dla raportu ogłoszenia -->
                    <div>
                        <label for="job_id">ID ogłoszenia:</label>
                        <input type="number" name="job_id" id="job_id" min="1" />
                    </div>

                    <div>
                        <label for="details">Szczegóły raportu:</label>
                        <textarea name="details" id="details" required></textarea>
                    </div>

                    <div>
                        <button type="submit">Generuj raport</button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Wyświetlanie raportów użytkowników -->
            <?php if ($userId): ?>
                <h3>Raporty aktywności użytkowników (ID użytkownika: <?php echo $userId; ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID użytkownika</th>
                            <th>Typ aktywności</th>
                            <th>Data</th>
                            <th>Szczegóły</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userReports as $report): ?>
                            <tr>
                                <td><?php echo $report['user_id']; ?></td>
                                <td><?php echo $report['activity_type']; ?></td>
                                <td><?php echo $report['timestamp']; ?></td>
                                <td><?php echo $report['details']; ?></td>
                                <td>
                                    <a href="delete_report.php?id=<?php echo $report['id']; ?>" class="btn btn-danger">Usuń</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginacja -->
                <div class="pagination">
                    <a href="?user_id=<?php echo $userId; ?>&page=1" class="btn btn-secondary">Pierwsza</a>
                    <a href="?user_id=<?php echo $userId; ?>&page=<?php echo max(1, $page - 1); ?>" class="btn btn-secondary">Poprzednia</a>
                    <span>Strona <?php echo $page; ?> z <?php echo $totalPagesUser; ?></span>
                    <a href="?user_id=<?php echo $userId; ?>&page=<?php echo min($totalPagesUser, $page + 1); ?>" class="btn btn-secondary">Następna</a>
                    <a href="?user_id=<?php echo $userId; ?>&page=<?php echo $totalPagesUser; ?>" class="btn btn-secondary">Ostatnia</a>
                </div>
            <?php endif; ?>

            <!-- Wyświetlanie raportów ogłoszeń -->
            <?php if ($jobId): ?>
                <h3>Raporty ogłoszeń (ID ogłoszenia: <?php echo $jobId; ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID ogłoszenia</th>
                            <th>Typ aktywności</th>
                            <th>Data</th>
                            <th>Szczegóły</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobReports as $report): ?>
                            <tr>
                                <td><?php echo $report['job_id']; ?></td>
                                <td><?php echo $report['activity_type']; ?></td>
                                <td><?php echo $report['timestamp']; ?></td>
                                <td><?php echo $report['details']; ?></td>
                                <td>
                                    <a href="delete_report.php?id=<?php echo $report['id']; ?>" class="btn btn-danger">Usuń</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginacja -->
                <div class="pagination">
                    <a href="?job_id=<?php echo $jobId; ?>&page=1" class="btn btn-secondary">Pierwsza</a>
                    <a href="?job_id=<?php echo $jobId; ?>&page=<?php echo max(1, $page - 1); ?>" class="btn btn-secondary">Poprzednia</a>
                    <span>Strona <?php echo $page; ?> z <?php echo $totalPagesJob; ?></span>
                    <a href="?job_id=<?php echo $jobId; ?>&page=<?php echo min($totalPagesJob, $page + 1); ?>" class="btn btn-secondary">Następna</a>
                    <a href="?job_id=<?php echo $jobId; ?>&page=<?php echo $totalPagesJob; ?>" class="btn btn-secondary">Ostatnia</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

</div>
				
        <div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
        </div>
  
            </div>	
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>

<script>
document.getElementById('select-all').addEventListener('change', function() {
    document.querySelectorAll('input[name="user_ids[]"]').forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});
</script>
