<?php
require_once 'config/config.php';
require_once 'models/Newsletter.php';
require_once 'models/Language.php';

$currentLocale = Language::current('frontend');

if (isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = __t('newsletter.invalid_email');
    } else {
        $newsletter = new Newsletter();

        if ($newsletter->unsubscribe($email)) {
            $message = __t('newsletter.unsubscribed');
        } else {
            $message = __t('newsletter.unsubscribe_error');
        }
    }
} else {
    $message = __t('newsletter.missing_email');
}

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($currentLocale, 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__t('newsletter.unsubscribe_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h2 class="mb-4"><?= htmlspecialchars(__t('newsletter.unsubscribe_title')) ?></h2>
                        <p><?= htmlspecialchars($message) ?></p>
                        <a href="/?lang=<?= urlencode($currentLocale) ?>" class="btn btn-primary mt-3"><?= htmlspecialchars(__t('common.back_home')) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
