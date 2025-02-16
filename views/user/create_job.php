<?php
// Rozpocznij sesję
session_start();

// Załaduj modele
include_once('../../models/Job.php');

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    // Jeśli użytkownik nie jest zalogowany, przekieruj go do strony logowania
    header('Location: /public/login.php');
    exit;
}

// Utwórz instancję klasy Job
$jobModel = new Job();

// Zmienna na błędy
$error = '';

// Sprawdź, czy formularz został wysłany
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pobierz dane z formularza
    $title = $_POST['title'];
    $description = $_POST['description'];

    // Sprawdź, czy wszystkie pola są wypełnione
    if (empty($title) || empty($description)) {
        $error = 'Wszystkie pola są wymagane.';
    } else {
        // Pobierz ID użytkownika z sesji
        $userId = $_SESSION['user_id'];

        // Dodaj ogłoszenie do bazy danych
        $result = $jobModel->createJob($userId, $title, $description);

        if ($result) {
            // Przekierowanie do panelu użytkownika po pomyślnym dodaniu ogłoszenia
            header('Location: ../user/dashboard.php');
            exit;
        } else {
            $error = 'Wystąpił błąd podczas dodawania ogłoszenia. Spróbuj ponownie.';
        }
    }
}

// Include nagłówek
include('../partials/header.php');
?>

<div class="container">
    <h1>Dodaj nowe ogłoszenie</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="create_job.php" method="POST">
        <div class="form-group">
            <label for="title">Tytuł ogłoszenia</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>
        <div class="form-group">
            <label for="description">Opis ogłoszenia</label>
            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Dodaj ogłoszenie</button>
    </form>
</div>

<?php 
// Include stopkę
include('../partials/footer.php');
?>
