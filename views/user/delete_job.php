<?php
session_start();

include_once('../../models/Database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = Database::getConnection();
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = (int)$_SESSION['user_id'];

if ($jobId <= 0) {
    header('Location: /views/user/job_list.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, user_id FROM jobs WHERE id = :id AND user_id = :user_id FOR UPDATE");
    $stmt->execute(['id' => $jobId, 'user_id' => $userId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $pdo->rollBack();
        header('Location: /views/user/job_list.php');
        exit;
    }

    $responsesStmt = $pdo->prepare("
        SELECT id, executor_id, points_reserved
        FROM responses
        WHERE job_id = :job_id AND points_reserved > 0
        FOR UPDATE
    ");
    $responsesStmt->execute(['job_id' => $jobId]);
    $responses = $responsesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($responses as $response) {
        $points = (int)$response['points_reserved'];
        if ($points > 0) {
            $refundStmt = $pdo->prepare("UPDATE users SET account_balance = account_balance + :points WHERE id = :executor_id");
            $refundStmt->execute([
                'points' => $points,
                'executor_id' => (int)$response['executor_id'],
            ]);
        }
    }

    $pdo->prepare("
        UPDATE responses
        SET status = CASE WHEN status = 'accepted' THEN 'cancelled' ELSE 'refunded' END,
            points_reserved = 0
        WHERE job_id = :job_id AND points_reserved > 0
    ")->execute(['job_id' => $jobId]);

    $pdo->prepare("
        UPDATE jobs
        SET deleted_at = COALESCE(deleted_at, NOW()),
            archived_at = COALESCE(archived_at, NOW()),
            archive_reason = 'user_deleted',
            updated_at = NOW()
        WHERE id = :job_id AND user_id = :user_id
    ")->execute(['job_id' => $jobId, 'user_id' => $userId]);

    $pdo->commit();
    header('Location: /views/user/job_list.php?status=archived');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Job archive error: ' . $e->getMessage());
    header('Location: /views/user/job_list.php?status=archive_error');
    exit;
}
