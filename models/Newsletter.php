<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Language.php';

class Newsletter {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->ensureSchema();
    }

    public function isSubscribed($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM newsletter_subscriptions WHERE email = :email AND is_active = 1");
        $stmt->execute(['email' => trim((string)$email)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSubscriptionByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM newsletter_subscriptions WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => trim((string)$email)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function subscribe($email, $userId = null) {
        $email = trim((string)$email);
        $existing = $this->getSubscriptionByEmail($email);

        if ($existing && (int)$existing['is_active'] === 1) {
            return ['success' => false, 'message' => __t('newsletter.already_subscribed')];
        }

        $token = bin2hex(random_bytes(32));

        try {
            if ($existing) {
                $stmt = $this->pdo->prepare("
                    UPDATE newsletter_subscriptions
                    SET user_id = :user_id, token = :token, is_active = 0, subscribed_at = NOW()
                    WHERE email = :email
                ");
                $stmt->execute([
                    'email' => $email,
                    'user_id' => $userId,
                    'token' => $token,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO newsletter_subscriptions (email, user_id, token, is_active)
                    VALUES (:email, :user_id, :token, 0)
                ");
                $stmt->execute([
                    'email' => $email,
                    'user_id' => $userId,
                    'token' => $token,
                ]);
            }

            $mailSent = $this->sendVerificationEmail($email, $token);

            if (!$mailSent) {
                $this->pdo->prepare("UPDATE newsletter_subscriptions SET is_active = 1, token = NULL WHERE email = :email")
                    ->execute(['email' => $email]);

                return [
                    'success' => true,
                    'message' => __t('newsletter.saved'),
                ];
            }

            return ['success' => true, 'message' => __t('newsletter.confirmation_sent')];
        } catch (PDOException $e) {
            error_log('Newsletter subscribe error: ' . $e->getMessage());
            return ['success' => false, 'message' => __t('newsletter.subscribe_error')];
        }
    }

    public function verifySubscription($token) {
        $stmt = $this->pdo->prepare("UPDATE newsletter_subscriptions SET is_active = 1, token = NULL WHERE token = :token");
        $stmt->execute(['token' => $token]);
        return $stmt->rowCount() > 0;
    }

    private function sendVerificationEmail($email, $token) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $subject = __t('newsletter.email_subject');
        $verificationLink = 'https://' . $host . '/verify-newsletter.php?token=' . urlencode($token);
        $emailTitle = htmlspecialchars(__t('newsletter.email_title'), ENT_QUOTES, 'UTF-8');
        $emailHeading = htmlspecialchars(__t('newsletter.email_heading'), ENT_QUOTES, 'UTF-8');
        $emailBody = htmlspecialchars(__t('newsletter.email_body'), ENT_QUOTES, 'UTF-8');
        $emailIgnore = htmlspecialchars(__t('newsletter.email_ignore'), ENT_QUOTES, 'UTF-8');
        $safeVerificationLink = htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8');
        $message = "
            <html>
            <head><title>{$emailTitle}</title></head>
            <body>
                <h2>{$emailHeading}</h2>
                <p>{$emailBody}</p>
                <a href='{$safeVerificationLink}'>{$safeVerificationLink}</a>
                <p>{$emailIgnore}</p>
            </body>
            </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: newsletter@' . $host . "\r\n";

        return @mail($email, $subject, $message, $headers);
    }

    public function getAllSubscribers() {
        $stmt = $this->pdo->query("SELECT * FROM newsletter_subscriptions ORDER BY subscribed_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unsubscribe($email) {
        $stmt = $this->pdo->prepare("UPDATE newsletter_subscriptions SET is_active = 0 WHERE email = :email");
        return $stmt->execute(['email' => trim((string)$email)]);
    }

    public function getNewsletterStats() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total,
                    COALESCE(SUM(is_active = 1), 0) as active,
                    COALESCE(SUM(is_active = 0), 0) as inactive,
                    COUNT(DISTINCT user_id) as with_account
                FROM newsletter_subscriptions
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'with_account' => 0];
        }
    }

    public function getSubscribersPaginated($limit, $offset, $search = '') {
        $sql = "SELECT * FROM newsletter_subscriptions WHERE 1";

        if (!empty($search)) {
            $sql .= " AND email LIKE :search";
        }

        $sql .= " ORDER BY subscribed_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);

        if (!empty($search)) {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countSubscribers($search = '') {
        $sql = "SELECT COUNT(*) FROM newsletter_subscriptions WHERE 1";

        if (!empty($search)) {
            $sql .= " AND email LIKE :search";
        }

        $stmt = $this->pdo->prepare($sql);

        if (!empty($search)) {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchColumn();
    }

    private function ensureSchema() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                user_id INT DEFAULT NULL,
                subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_active TINYINT(1) DEFAULT 0,
                token VARCHAR(255) DEFAULT NULL,
                UNIQUE KEY email (email),
                KEY user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
?>
