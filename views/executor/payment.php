<?php
session_start();
require_once '../../models/Executor.php';
require_once '../../models/Language.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$executorId = $_SESSION['user_id'];
$executor = new Executor();


if (!$executor->isExecutor($executorId)) {
    $error = __t('executor.not_executor_html');
} else {

    $executorBalance = $executor->getExecutorBalance($executorId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pointsToAdd = (int) $_POST['points'];


        if ($pointsToAdd > 0) {

            $paymentAmount = $pointsToAdd;


            $itemName = urlencode(__t('executor.paypal_item_name'));
            header("Location: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=PAYPAL_EMAIL&item_name=$itemName&amount=$paymentAmount&currency_code=PLN&return=http://localhost/confirmation.php&cancel_return=http://localhost/cancel.php");
            exit;
        } else {
            $error = __t('executor.payment_invalid_points');
        }
    }
}

include '../partials/header.php';
?>

<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= htmlspecialchars(__t('executor.payment_title')) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars(__t('executor.account_balance')) ?>: <?= isset($executorBalance) ? (int)$executorBalance : 0 ?> pkt</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars(__t('nav.executor_panel')) ?></a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (!isset($error)): ?>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?= htmlspecialchars(__t('executor.account_balance')) ?></div>
                        <div class="h3 mb-0"><?= (int)$executorBalance ?> pkt</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><?= htmlspecialchars(__t('executor.top_up_account')) ?></h2>
                    </div>
                    <div class="card-body">
                        <form action="payment.php" method="POST">
                            <div class="mb-3">
                                <label for="points" class="form-label"><?= htmlspecialchars(__t('executor.points_amount')) ?></label>
                                <input type="number" name="points" id="points" class="form-control" min="1" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><?= htmlspecialchars(__t('executor.go_to_payment')) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
