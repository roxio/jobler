<?php
session_start();
require_once '../../models/Executor.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$executorId = $_SESSION['user_id'];
$executor = new Executor();

// Pobranie salda konta wykonawcy
$executorBalance = $executor->getExecutorBalance($executorId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pointsToAdd = (int) $_POST['points'];
    
    // Sprawdzamy, czy użytkownik wybrał odpowiednią liczbę punktów (większą niż 0)
    if ($pointsToAdd > 0) {
        // Wartość do zapłaty (1 punkt = 1 zł)
        $paymentAmount = $pointsToAdd;

        // Przekierowanie do strony płatności PayPal z kwotą do zapłaty
        header("Location: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=TWÓJ_EMAIL_PAYPAL&item_name=Doładowanie konta wykonawcy&amount=$paymentAmount&currency_code=PLN&return=http://localhost/confirmation.php&cancel_return=http://localhost/cancel.php");
        exit;
    } else {
        $error = "Wybierz liczbę punktów do doładowania.";
    }
}

include '../partials/header.php';
?>

<div class="container">
    <h1>Doładuj konto</h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="payment.php" method="POST">
        <div class="mb-3">
            <label for="points" class="form-label">Ilość punktów:</label>
            <input type="number" name="points" id="points" class="form-control" min="1" required>
        </div>
        <button type="submit" class="btn btn-primary">Przejdź do płatności</button>
    </form>
</div>

<?php include '../partials/footer.php'; ?>
