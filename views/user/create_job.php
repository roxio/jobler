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
    $title = $_POST['title'];
    $description = $_POST['description'];
    $pointsRequired = $_POST['points_required'];
    $categoryId = $_POST['category_id'];

    if (empty($title) || empty($description) || empty($pointsRequired) || empty($categoryId)) {
        $error = 'Wszystkie pola są wymagane.';
    } else {
        if ($pointsRequired < 1 || $pointsRequired > 10) {
            $error = 'Liczba punktów musi być w zakresie od 1 do 10.';
        } else {
            $userId = $_SESSION['user_id'];
            $result = $jobModel->createJob($userId, $title, $description, $pointsRequired, $categoryId);

            if ($result) {
                header('Location: ../user/dashboard.php');
                exit;
            } else {
                $error = 'Wystąpił błąd podczas dodawania ogłoszenia.';
            }
        }
    }
}

$categories = $jobModel->getCategories();

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
    <label for="category_id">Kategoria</label>
    <select class="form-control" id="category_id" name="category_id" required>
        <option value="">Wybierz kategorię</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

        <div class="form-group">
            <label for="description">Treść ogłoszenia</label>
            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
        </div>
        <div class="form-group">
            <label for="points_required">Wymagana liczba punktów od wykonawcy</label>
            <input type="number" class="form-control" id="points_required" name="points_required" min="1" max="10" value="1" required>
        </div>
        <button type="submit" class="btn btn-primary">Dodaj ogłoszenie</button>
    </form>
</div>

<?php 
// Include stopkę
include('../partials/footer.php');
?>
