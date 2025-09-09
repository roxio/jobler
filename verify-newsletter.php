<?php
require_once 'config/config.php';
require_once 'models/Newsletter.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $newsletter = new Newsletter();
    
    if ($newsletter->verifySubscription($token)) {
        $message = "Subskrypcja newslettera została potwierdzona!";
    } else {
        $message = "Nieprawidłowy lub przestarzały token weryfikacyjny.";
    }
} else {
    $message = "Brak tokenu weryfikacyjnego.";
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weryfikacja newslettera</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h2 class="mb-4">Weryfikacja newslettera</h2>
                        <p><?= $message ?></p>
                        <a href="/" class="btn btn-primary mt-3">Powrót do strony głównej</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>