<?php
// Rozpocznij sesję
session_start();

// Załaduj modele
include_once('../../models/Job.php');

// Utwórz instancję klasy Job
$jobModel = new Job();

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

// Sprawdź, czy przesłano ID ogłoszenia
if (!isset($_GET['id'])) {
    header('Location: /views/user/dashboard.php');
    exit;
}

$jobId = $_GET['id'];
$userId = $_SESSION['user_id'];

// Pobierz szczegóły ogłoszenia
$job = $jobModel->getJobDetails($jobId);

// Sprawdź, czy ogłoszenie należy do użytkownika
if ($job['user_id'] != $userId) {
    header('Location: /views/user/dashboard.php');
    exit;
}

// Obsłuż formularz edycji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $status = $_POST['status'];

    // Zaktualizuj ogłoszenie
    $jobModel->updateJob($jobId, $title, $description, $status);

    // Przekieruj na dashboard
    header('Location: /views/user/dashboard.php');
    exit;
}

// Include nagłówek
include('../partials/header.php');
?>

<div class="container">
    <h1>Edytuj ogłoszenie</h1>
    <form method="POST">
        <div class="form-group">
            <label for="title">Tytuł</label>
            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Opis</label>
            <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($job['description']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select class="form-control" id="status" name="status" required>
                <option value="open" <?php echo $job['status'] === 'open' ? 'selected' : ''; ?>>Aktywne</option>
                <option value="closed" <?php echo $job['status'] === 'closed' ? 'selected' : ''; ?>>Nieaktywne</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
        <a href="/views/user/dashboard.php" class="btn btn-secondary">Anuluj</a>
    </form>
</div>

<?php 
// Include stopkę
include('../partials/footer.php');
?>