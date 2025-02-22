<?php
session_start();
require_once '../../models/Executor.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$executorId = $_SESSION['user_id'];

$executor = new Executor();

// Pobranie dostępnych ofert
$availableJobs = $executor->getAvailableJobs();

// DEBUG: Wyświetlenie dostępnych ofert
error_log("Available jobs: " . print_r($availableJobs, true));

if (empty($availableJobs)) {
    $message = "Brak dostępnych ofert do rozważenia.";
}

include '../partials/header.php';
?>

<div class="container">
    <h1>Lista dostępnych ofert</h1>

    <?php if (isset($message)): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($availableJobs as $job): ?>
                <?php
                // Sprawdź status odpowiedzi i konwersacji
                $responseStatus = $executor->hasRespondedToJob($executorId, $job['id']);

                // DEBUG: Wyświetlenie statusu odpowiedzi
                error_log("Response status for job_id {$job['id']}: " . print_r($responseStatus, true));

                $hasResponded = $responseStatus !== false;
                $conversationExists = $hasResponded && $responseStatus['conversation_id'] !== null;
                ?>

                <div class="list-group-item <?php echo $hasResponded ? 'faded' : ''; ?>">
                    <h5 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                    <small>Data utworzenia: <?php echo date('d-m-Y', strtotime($job['created_at'])); ?></small>
                    <br>
                    <!-- Wyświetlanie wymaganej liczby punktów -->
                    <small>Wymagane punkty: <?php echo htmlspecialchars($job['points_required']); ?></small>
                    <br>
                    <?php if ($conversationExists): ?>
                        <a href="../messages/conversation.php?conversation_id=<?php echo $responseStatus['conversation_id']; ?>" class="btn btn-secondary mt-2">Przejdź do konwersacji</a>
                    <?php elseif ($hasResponded): ?>
                        <button class="btn btn-secondary mt-2" disabled>Odpowiedź wysłana</button>
                    <?php else: ?>
                        <a href="respond_offer.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary mt-2">Odpowiedz na ofertę</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
