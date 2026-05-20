<?php
session_start();
require_once('../../config/config.php');
require_once('../../models/Newsletter.php');
require_once('../../models/User.php');
require_once('../../models/Language.php');


require_once __DIR__ . '/_auth.php';
requireAdminAccess();

$newsletter = new Newsletter();
$userModel = new User();


$subscribers = $newsletter->getAllSubscribers();


if (isset($_GET['delete'])) {
    $email = $_GET['delete'];
    $deleted = $newsletter->unsubscribe($email);
    if ($deleted) {
        $successMessage = __t('admin.newsletter.deleted');
    } else {
        $errorMessage = __t('admin.newsletter.delete_error');
    }
    header('Location: newsletter_manager.php?message=' . ($deleted ? 'success' : 'error'));
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
    $subject = $_POST['subject'];
    $message = $_POST['message'];


    $activeSubscribers = array_filter($subscribers, function($sub) {
        return $sub['is_active'] == 1;
    });

    $sentCount = 0;
    foreach ($activeSubscribers as $subscriber) {
        if (sendNewsletterEmail($subscriber['email'], $subject, $message)) {
            $sentCount++;
        }
    }

    $successMessage = __t('admin.newsletter.sent', ['count' => $sentCount]);
}


function sendNewsletterEmail($email, $subject, $message) {
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
                    <p>© " . date('Y') . " " . $_SERVER['HTTP_HOST'] . " - " . htmlspecialchars(__t('admin.newsletter.rights'), ENT_QUOTES, 'UTF-8') . "</p>
                    <p><a href='https://" . $_SERVER['HTTP_HOST'] . "/unsubscribe-newsletter.php?email=$email'>" . htmlspecialchars(__t('admin.newsletter.unsubscribe'), ENT_QUOTES, 'UTF-8') . "</a></p>
                </div>
            </div>
        </body>
        </html>
    ";

    return @mail($email, $subject, $fullMessage, $headers);
}


if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=newsletter_subscribers_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Email', __t('admin.newsletter.csv_subscription_date'), __t('admin.newsletter.csv_status'), __t('admin.newsletter.csv_user_id')]);

    foreach ($subscribers as $subscriber) {
        fputcsv($output, [
            $subscriber['email'],
            $subscriber['subscribed_at'],
            $subscriber['is_active'] ? __t('admin.newsletter.active') : __t('admin.newsletter.inactive'),
            $subscriber['user_id'] ?? __t('admin.newsletter.none')
        ]);
    }

    fclose($output);
    exit;
}
?>

<?php include '../partials/header.php'; ?>
<?php if (isset($_GET['message']) && $_GET['message'] === 'success'): ?>
    <?php $successMessage = __t('admin.newsletter.deactivated'); ?>
<?php elseif (isset($_GET['message']) && $_GET['message'] === 'error'): ?>
    <?php $errorMessage = __t('admin.newsletter.status_error'); ?>
<?php endif; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> <?= htmlspecialchars(__t('admin.panel')) ?></h5>
                    <nav class="nav">
                        <?php include 'sidebar.php'; ?>
                    </nav>
                </div>

                <div class="card-body">
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-envelope"></i> <?= htmlspecialchars(__t('admin.newsletter.manage')) ?></h5>
                            <div>
                                <a href="newsletter_manager.php?export=csv" class="btn btn-sm btn-success">
                                    <i class="bi bi-download"></i> <?= htmlspecialchars(__t('admin.newsletter.export_csv')) ?>
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
                                            <h6><i class="bi bi-send"></i> <?= htmlspecialchars(__t('admin.newsletter.send')) ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="mb-3">
                                                    <label class="form-label"><?= htmlspecialchars(__t('admin.newsletter.subject')) ?></label>
                                                    <input type="text" name="subject" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label"><?= htmlspecialchars(__t('admin.newsletter.content_html')) ?></label>
                                                    <textarea name="message" class="form-control" rows="6" required></textarea>
                                                </div>
                                                <button type="submit" name="send_newsletter" class="btn btn-primary">
                                                    <i class="bi bi-send"></i> <?= htmlspecialchars(__t('admin.newsletter.send_to', ['count' => count(array_filter($subscribers, fn($s) => $s['is_active']))])) ?>
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
                                            <h6><i class="bi bi-people"></i> <?= htmlspecialchars(__t('admin.newsletter.subscribers_list', ['count' => count($subscribers)])) ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Email</th>
                                                            <th><?= htmlspecialchars(__t('admin.newsletter.subscription_date')) ?></th>
                                                            <th><?= htmlspecialchars(__t('admin.common.status')) ?></th>
                                                            <th><?= htmlspecialchars(__t('admin.newsletter.user_id')) ?></th>
                                                            <th><?= htmlspecialchars(__t('admin.common.actions')) ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($subscribers)): ?>
                                                            <tr>
                                                                <td colspan="5" class="text-center"><?= htmlspecialchars(__t('admin.newsletter.no_subscribers')) ?></td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($subscribers as $subscriber): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                                                    <td><?php echo date('Y-m-d H:i', strtotime($subscriber['subscribed_at'])); ?></td>
                                                                    <td>
                                                                        <span class="badge bg-<?php echo $subscriber['is_active'] ? 'success' : 'secondary'; ?>">
                                                                            <?php echo htmlspecialchars($subscriber['is_active'] ? __t('admin.newsletter.active') : __t('admin.newsletter.inactive')); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($subscriber['user_id']): ?>
                                                                            <a href="view_user.php?id=<?php echo (int)$subscriber['user_id']; ?>">
                                                                                #<?php echo $subscriber['user_id']; ?>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <?= htmlspecialchars(__t('admin.newsletter.none')) ?>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <a href="newsletter_manager.php?delete=<?php echo urlencode($subscriber['email']); ?>"
                                                                           class="btn btn-sm btn-danger"
                                                                           onclick="return confirm('<?= htmlspecialchars(__t('admin.newsletter.delete_confirm'), ENT_QUOTES) ?>')">
                                                                            <i class="bi bi-trash"></i> <?= htmlspecialchars(__t('admin.common.delete')) ?>
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
