<?php
session_start();
require_once '../../models/Database.php';
require_once '../../models/Language.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$db = new Database();
$conn = $db->getConnection();


$query = "SELECT need_change FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$userId]);
$needChange = $stmt->fetchColumn();

if ($needChange === false) {
    $error = __t('user.account_change.user_not_found');
} elseif ($needChange == 1) {
    $message = __t('user.account_change.pending');
} else {

    $updateQuery = "UPDATE users SET need_change = 1 WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    if ($updateStmt->execute([$userId])) {
        $message = __t('user.account_change.sent');
    } else {
        $error = __t('user.account_change.error');
    }
}

include '../partials/header.php';
?>

<div class="container">
    <h1><?= htmlspecialchars(__t('user.account_change.title')) ?></h1>

    <?php if (isset($message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <a href="../executor/payment.php" class="btn btn-primary"><?= htmlspecialchars(__t('user.account_change.back')) ?></a>
</div>

<?php include '../partials/footer.php'; ?>
