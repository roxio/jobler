<?php
require_once 'Database.php';

class Newsletter {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    // Sprawdź czy email jest już zapisany do newslettera
    public function isSubscribed($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM newsletter_subscriptions WHERE email = :email AND is_active = TRUE");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Zapisz email do newslettera
    public function subscribe($email, $userId = null) {
        // Sprawdź czy email już istnieje
        $existing = $this->isSubscribed($email);
        
        if ($existing) {
            return ['success' => false, 'message' => 'Ten adres email jest już zapisany do newslettera.'];
        }

        // Generuj token weryfikacyjny
        $token = bin2hex(random_bytes(32));

        try {
            $stmt = $this->pdo->prepare("INSERT INTO newsletter_subscriptions (email, user_id, token) VALUES (:email, :user_id, :token)");
            $stmt->execute([
                'email' => $email,
                'user_id' => $userId,
                'token' => $token
            ]);

            // Wyślij email weryfikacyjny
            $this->sendVerificationEmail($email, $token);

            return ['success' => true, 'message' => 'Na podany adres email wysłaliśmy prośbę o potwierdzenie subskrypcji.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Błąd podczas zapisywania do newslettera: ' . $e->getMessage()];
        }
    }

    // Weryfikacja subskrypcji
    public function verifySubscription($token) {
        $stmt = $this->pdo->prepare("UPDATE newsletter_subscriptions SET is_active = TRUE, token = NULL WHERE token = :token");
        $stmt->execute(['token' => $token]);
        
        return $stmt->rowCount() > 0;
    }

    // Wysłanie emaila weryfikacyjnego
    private function sendVerificationEmail($email, $token) {
        $subject = "Potwierdź subskrypcję newslettera";
        $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/verify-newsletter.php?token=" . $token;
        
        $message = "
            <html>
            <head>
                <title>Potwierdzenie subskrypcji newslettera</title>
            </head>
            <body>
                <h2>Dziękujemy za zapisanie się do newslettera!</h2>
                <p>Aby potwierdzić subskrypcję, kliknij w poniższy link:</p>
                <a href='$verificationLink'>$verificationLink</a>
                <p>Jeśli to nie Ty zapisałeś/aś się na newsletter, zignoruj tę wiadomość.</p>
            </body>
            </html>
        ";

        // Nagłówki emaila
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: newsletter@twojastrona.pl' . "\r\n";

        // Wyślij email
        mail($email, $subject, $message, $headers);
    }

    // Pobierz wszystkich subskrybentów
    public function getAllSubscribers() {
        $stmt = $this->pdo->query("SELECT * FROM newsletter_subscriptions WHERE is_active = TRUE ORDER BY subscribed_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Anuluj subskrypcję
    public function unsubscribe($email) {
        $stmt = $this->pdo->prepare("UPDATE newsletter_subscriptions SET is_active = FALSE WHERE email = :email");
        return $stmt->execute(['email' => $email]);
    }
}
?>