<?php
// Rozpocznij sesję
session_start();

include_once('../../models/User.php');


// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

$pdo = Database::getConnection();
$categoriesStmt = $pdo->query("SELECT id, name FROM categories");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Pobierz dane użytkownika
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Tworzenie instancji modelu User
$userModel = new User();

// Pobierz odpowiedzi na ogłoszenia użytkownika
$responses = $userModel->getResponsesForUserJobs($userId);

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

        <!-- Lista ogłoszeń i odpowiedzi -->
        <div class="col-md-8">
            <!-- Sekcja odpowiedzi na oferty -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3>Odpowiedzi na Twoje ogłoszenia</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($responses)): ?>
                        <p>Brak odpowiedzi na Twoje ogłoszenia.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($responses as $response): ?>
                                <li class="list-group-item">
                                    <h5>Ogłoszenie: <?php echo htmlspecialchars($response['title']); ?></h5>
                                    <p><strong>Wykonawca:</strong> <?php echo htmlspecialchars($response['executor_name']); ?></p>
                                    <p><strong>Treść odpowiedzi:</strong> <?php echo nl2br(htmlspecialchars($response['message'])); ?></p>
                                    <p>
                                        <small>
                                            <strong>Data odpowiedzi:</strong> <?php echo date('d-m-Y H:i', strtotime($response['created_at'])); ?>
                                        </small>
                                    </p>
                                    <?php if (isset($response['executor_id'])): ?>
                                        <?php 
                                        // Generujemy conversation_id na podstawie user_id i executor_id
                                        $conversationId = min($userId, $response['executor_id']) . "_" . max($userId, $response['executor_id']);
                                        ?>
                                        <a href="../messages/conversation.php?conversation_id=<?php echo $conversationId; ?>&job_id=<?php echo $response['job_id']; ?>" class="btn btn-sm btn-primary">Przejdź do konwersacji</a>
                                    <?php else: ?>
                                        <span class="text-danger">Brak wykonawcy</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Opcjonalne dodatkowe sekcje -->
        </div>
    </div>
</div>

<?php 
// Include stopkę
include('../partials/footer.php');
?>
