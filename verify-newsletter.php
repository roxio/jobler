<?php
require_once 'config/config.php';
require_once 'models/Newsletter.php';
require_once 'models/Language.php';

$currentLocale = Language::current('frontend');

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $newsletter = new Newsletter();
    
    if ($newsletter->verifySubscription($token)) {
        $message = __t('newsletter.verified');
    } else {
        $message = __t('newsletter.invalid_token');
    }
} else {
    $message = __t('newsletter.missing_token');
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($currentLocale, 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__t('newsletter.verify_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h2 class="mb-4"><?= htmlspecialchars(__t('newsletter.verify_title')) ?></h2>
                        <p><?= htmlspecialchars($message) ?></p>
                        <a href="/?lang=<?= urlencode($currentLocale) ?>" class="btn btn-primary mt-3"><?= htmlspecialchars(__t('common.back_home')) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
