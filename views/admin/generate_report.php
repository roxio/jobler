<?php
include_once('../../models/Report.php');
include_once('../../models/Database.php');

// Uzyskanie połączenia z bazą danych
$pdo = Database::getConnection();

// Tworzymy instancję modelu Report
$reportModel = new Report($pdo);

// Zmienna do przechowywania komunikatów
$message = "";

// Pobranie dostępnych użytkowników
$query = "SELECT id, username FROM users";
$stmt = $pdo->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obsługuje dodanie raportu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $jobId = isset($_POST['job_id']) ? (int)$_POST['job_id'] : null;
    $activityType = isset($_POST['activity_type']) ? $_POST['activity_type'] : '';
    $details = isset($_POST['details']) ? $_POST['details'] : '';

    // Sprawdzamy, czy dane są prawidłowe
    if ($userId && $activityType && $details) {
        // Dodaj raport aktywności użytkownika
        if ($userId) {
            $reportModel->addUserActivityReport($userId, $activityType, $details);
            $message = "Raport aktywności użytkownika został dodany.";
        }
    } elseif ($jobId && $activityType && $details) {
        // Dodaj raport dla ogłoszenia
        if ($jobId) {
            $reportModel->addJobReport($jobId, $activityType, $details);
            $message = "Raport aktywności ogłoszenia został dodany.";
        }
    } else {
        $message = "Wszystkie pola muszą być wypełnione.";
    }
}

include '../partials/header.php';
?>

<h1>Generowanie raportu</h1>
<?php if ($message): ?>
    <p><?php echo $message; ?></p>
<?php endif; ?>

<!-- Formularz do generowania raportu -->
<form method="post" action="">
    <div>
        <label for="activity_type">Typ aktywności:</label>
        <select name="activity_type" id="activity_type" required>
            <option value="login">Logowanie</option>
            <option value="post_job">Dodanie ogłoszenia</option>
            <option value="apply_job">Aplikacja na ogłoszenie</option>
            <!-- Dodaj inne typy aktywności w zależności od potrzeb -->
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

<?php
include '../partials/footer.php';
?>
