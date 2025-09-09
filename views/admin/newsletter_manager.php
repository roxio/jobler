<?php
session_start();
require_once('../../config/config.php');
require_once('../../models/Newsletter.php');
require_once('../../models/User.php');

// Sprawdź czy użytkownik jest administratorem
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$newsletter = new Newsletter();
$userModel = new User();

// Pobierz wszystkich subskrybentów
$subscribers = $newsletter->getAllSubscribers();

// Obsługa usuwania subskrybenta
if (isset($_GET['delete'])) {
    $email = $_GET['delete'];
    if ($newsletter->unsubscribe($email)) {
        $successMessage = "Subskrybent został usunięty z newslettera.";
    } else {
        $errorMessage = "Błąd podczas usuwania subskrybenta.";
    }
    header('Location: newsletter_manager.php?message=' . ($successMessage ? 'success' : 'error'));
    exit;
}

// Obsługa wysyłania newslettera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    // Pobierz aktywnych subskrybentów
    $activeSubscribers = array_filter($subscribers, function($sub) {
        return $sub['is_active'] == 1;
    });
    
    $sentCount = 0;
    foreach ($activeSubscribers as $subscriber) {
        if ($this->sendNewsletterEmail($subscriber['email'], $subject, $message)) {
            $sentCount++;
        }
    }
    
    $successMessage = "Newsletter wysłany do $sentCount subskrybentów.";
}

// Funkcja do wysyłania emaila newslettera
private function sendNewsletterEmail($email, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: newsletter@' . $_SERVER['HTTP_HOST'] . "\r\n";
    
    $fullMessage = "
        <html>
        <head>
            <title>$subject</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #fff; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Newsletter</h1>
                </div>
                <div class='content'>
                    $message
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " " . $_SERVER['HTTP_HOST'] . " - Wszelkie prawa zastrzeżone</p>
                    <p><a href='https://" . $_SERVER['HTTP_HOST'] . "/unsubscribe-newsletter.php?email=$email'>Wypisz się z newslettera</a></p>
                </div>
            </div>
        </body>
        </html>
    ";
    
    return mail($email, $subject, $fullMessage, $headers);
}

// Obsługa eksportu do CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=newsletter_subscribers_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Email', 'Data zapisu', 'Status', 'ID Użytkownika']);
    
    foreach ($subscribers as $subscriber) {
        fputcsv($output, [
            $subscriber['email'],
            $subscriber['subscribed_at'],
            $subscriber['is_active'] ? 'Aktywny' : 'Nieaktywny',
            $subscriber['user_id'] ?? 'Brak'
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-envelope"></i> Zarządzanie Newsletterem</h5>
                            <div>
                                <a href="newsletter_manager.php?export=csv" class="btn btn-sm btn-success">
                                    <i class="bi bi-download"></i> Eksport CSV
                                </a>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <?php if (isset($successMessage)): ?>
                                <div class="alert alert-success"><?php echo $successMessage; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($errorMessage)): ?>
                                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                            <?php endif; ?>

                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6><i class="bi bi-send"></i> Wyślij newsletter</h6>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="mb-3">
                                                    <label class="form-label">Temat wiadomości</label>
                                                    <input type="text" name="subject" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Treść wiadomości (HTML)</label>
                                                    <textarea name="message" class="form-control" rows="6" required></textarea>
                                                </div>
                                                <button type="submit" name="send_newsletter" class="btn btn-primary">
                                                    <i class="bi bi-send"></i> Wyślij do <?php echo count(array_filter($subscribers, fn($s) => $s['is_active'])); ?> subskrybentów
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6><i class="bi bi-people"></i> Lista subskrybentów (<?php echo count($subscribers); ?>)</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Email</th>
                                                            <th>Data zapisu</th>
                                                            <th>Status</th>
                                                            <th>ID Użytkownika</th>
                                                            <th>Akcje</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($subscribers)): ?>
                                                            <tr>
                                                                <td colspan="5" class="text-center">Brak subskrybentów</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($subscribers as $subscriber): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                                                    <td><?php echo date('Y-m-d H:i', strtotime($subscriber['subscribed_at'])); ?></td>
                                                                    <td>
                                                                        <span class="badge bg-<?php echo $subscriber['is_active'] ? 'success' : 'secondary'; ?>">
                                                                            <?php echo $subscriber['is_active'] ? 'Aktywny' : 'Nieaktywny'; ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($subscriber['user_id']): ?>
                                                                            <a href="user_details.php?id=<?php echo $subscriber['user_id']; ?>">
                                                                                #<?php echo $subscriber['user_id']; ?>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            Brak
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <a href="newsletter_manager.php?delete=<?php echo urlencode($subscriber['email']); ?>" 
                                                                           class="btn btn-sm btn-danger"
                                                                           onclick="return confirm('Czy na pewno chcesz usunąć tego subskrybenta?')">
                                                                            <i class="bi bi-trash"></i> Usuń
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>