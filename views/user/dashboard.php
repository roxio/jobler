<?php
// Rozpocznij sesję
session_start();

// Załaduj modele
include_once('../../models/User.php');

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

// Pobierz dane użytkownika
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Include nagłówek
include('../partials/header.php');
?>

<div class="container">
    <div class="row">
        <!-- Panel użytkownika -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Dane użytkownika</h3>
                </div>
                <div class="card-body">
                    <p><strong>ID:</strong> <?php echo htmlspecialchars($userId); ?></p>
                    <p><strong>Imię:</strong> <?php echo htmlspecialchars($userName); ?></p>
                    <a href="edit_profile.php" class="btn btn-primary btn-block">Edytuj dane</a>
                </div>
            </div>

            <div class="mt-4">
                <a href="create_job.php" class="btn btn-success btn-block">Dodaj nowe ogłoszenie</a>
                <a href="logout.php" class="btn btn-danger btn-block">Wyloguj się</a>
            </div>
        </div>

        <!-- Lista ogłoszeń -->
        <div class="col-md-8">
            <?php include('job_view.php'); ?>
        </div>
    </div>
</div>

<?php 
// Include stopkę
include('../partials/footer.php');
?>