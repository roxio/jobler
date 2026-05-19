<?php
ini_set('display_errors', '0');
ob_start();
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Newsletter.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Language.php';

$currentLocale = Language::current('frontend');

function newsletterJsonResponse(array $payload) {
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    newsletterJsonResponse(['success' => false, 'message' => __t('newsletter.invalid_request')]);
}

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    newsletterJsonResponse(['success' => false, 'message' => __t('newsletter.email_invalid_json')]);
}

try {
    $newsletter = new Newsletter();
    $userModel = new User();

    $userId = null;
    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        $userModel->updateNewsletterPreference($userId, true);
    }

    newsletterJsonResponse($newsletter->subscribe($email, $userId));
} catch (Throwable $e) {
    error_log('Newsletter endpoint error: ' . $e->getMessage());
    newsletterJsonResponse(['success' => false, 'message' => __t('newsletter.endpoint_error')]);
}
?>
