<?php
session_start();
require_once '../../models/Database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$db = new Database();
$conn = $db->getConnection();

// Sprawdzenie aktualnego statusu need_change
$query = "SELECT need_change FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$userId]);
$needChange = $stmt->fetchColumn();

if ($needChange === false) {
    $error = "Błąd: Nie znaleziono użytkownika.";
} elseif ($needChange == 1) {
    $message = "Zgłoszono już chęć zmiany statusu konta, oczekujesz na akceptację moderatora.";
} else {
    // Aktualizacja need_change na 1
    $updateQuery = "UPDATE users SET need_change = 1 WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    if ($updateStmt->execute([$userId])) {
        $message = "Zgłoszenie zostało wysłane, zmiana rodzaju konta może potrwać do 24h.";
    } else {
        $error = "Wystąpił błąd podczas wysyłania zgłoszenia.";
    }
}

include '../partials/header.php';
?>

<div class="container">
    <h1>Zmiana rodzaju konta</h1>

    <?php if (isset($message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <a href="../executor/payment.php" class="btn btn-primary">Powrót</a>
</div>

<?php include '../partials/footer.php'; ?>
