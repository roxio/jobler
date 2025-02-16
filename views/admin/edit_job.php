<?php
// Załaduj model Job
include_once('../../models/Job.php');

// Utwórz instancję klasy Job
$jobModel = new Job();

// Sprawdź, czy w URL znajduje się parametr "id"
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $jobId = $_GET['id'];

    // Pobierz dane ogłoszenia na podstawie ID
    $job = $jobModel->getJobDetails($jobId);

    // Sprawdź, czy ogłoszenie istnieje
    if (!$job) {
        echo "Ogłoszenie nie istnieje.";
        exit;
    }

    // Sprawdź, czy formularz został wysłany
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = $_POST['title'];
        $description = $_POST['description'];

        // Zaktualizuj ogłoszenie
        $updateSuccess = $jobModel->updateJob($jobId, $title, $description);

        if ($updateSuccess) {
            header("Location: manage_jobs.php"); // Przekierowanie do zarządzania ogłoszeniami
            exit;
        } else {
            $errorMessage = "Błąd podczas aktualizacji ogłoszenia.";
        }
    }
} else {
    echo "Nieprawidłowe ID ogłoszenia.";
    exit;
}

?>

<?php include '../partials/header.php'; ?>

<div class="container">
    <h1>Edytuj ogłoszenie</h1>

    <?php if (isset($errorMessage)) : ?>
        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="title" class="form-label">Tytuł</label>
            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($job['title']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Opis</label>
            <textarea id="description" name="description" class="form-control" required><?php echo htmlspecialchars($job['description']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
    </form>
</div>

<?php include '../partials/footer.php'; ?>
