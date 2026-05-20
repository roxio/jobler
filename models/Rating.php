<?php
include_once(__DIR__ . '/Database.php');

class Rating {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function installOrUpdateSchema() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ratings (
                id INT(11) NOT NULL AUTO_INCREMENT,
                job_id INT(11) NOT NULL,
                reviewer_id INT(11) NOT NULL,
                reviewee_id INT(11) NOT NULL,
                reviewer_role VARCHAR(20) NOT NULL,
                reviewee_role VARCHAR(20) NOT NULL,
                rating TINYINT(1) NOT NULL,
                comment TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_rating_job_pair (job_id, reviewer_id, reviewee_id),
                KEY idx_ratings_reviewee (reviewee_id),
                KEY idx_ratings_job (job_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function submitRating($jobId, $reviewerId, $rating, $comment = '') {
        $jobId = (int)$jobId;
        $reviewerId = (int)$reviewerId;
        $rating = (int)$rating;
        $comment = trim((string)$comment);

        if ($jobId <= 0 || $reviewerId <= 0 || $rating < 1 || $rating > 5) {
            return false;
        }

        $job = $this->getCompletedJobForRating($jobId);
        if (!$job) {
            return false;
        }

        $ownerId = (int)$job['user_id'];
        $executorId = (int)$job['executor_id'];

        if ($reviewerId === $ownerId) {
            $revieweeId = $executorId;
            $reviewerRole = 'user';
            $revieweeRole = 'executor';
        } elseif ($reviewerId === $executorId) {
            $revieweeId = $ownerId;
            $reviewerRole = 'executor';
            $revieweeRole = 'user';
        } else {
            return false;
        }

        if ($revieweeId <= 0 || $revieweeId === $reviewerId) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ratings (job_id, reviewer_id, reviewee_id, reviewer_role, reviewee_role, rating, comment, created_at)
                VALUES (:job_id, :reviewer_id, :reviewee_id, :reviewer_role, :reviewee_role, :rating, :comment, NOW())
            ");

            return $stmt->execute([
                'job_id' => $jobId,
                'reviewer_id' => $reviewerId,
                'reviewee_id' => $revieweeId,
                'reviewer_role' => $reviewerRole,
                'reviewee_role' => $revieweeRole,
                'rating' => $rating,
                'comment' => $comment !== '' ? $comment : null,
            ]);
        } catch (Throwable $e) {
            error_log('Rating submit error: ' . $e->getMessage());
            return false;
        }
    }

    public function getRatingForJobByReviewer($jobId, $reviewerId) {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ratings
            WHERE job_id = :job_id AND reviewer_id = :reviewer_id
            LIMIT 1
        ");
        $stmt->execute(['job_id' => (int)$jobId, 'reviewer_id' => (int)$reviewerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSummaryForUser($userId) {
        $stmt = $this->pdo->prepare("
            SELECT AVG(rating) AS average_rating, COUNT(*) AS rating_count
            FROM ratings
            WHERE reviewee_id = :user_id
        ");
        $stmt->execute(['user_id' => (int)$userId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'average_rating' => isset($summary['average_rating']) ? round((float)$summary['average_rating'], 2) : 0,
            'rating_count' => (int)($summary['rating_count'] ?? 0),
        ];
    }

    private function getCompletedJobForRating($jobId) {
        $stmt = $this->pdo->prepare("
            SELECT id, user_id, executor_id, status, completed_at, review_deadline
            FROM jobs
            WHERE id = :job_id
              AND status = 'completed'
              AND executor_id IS NOT NULL
              AND completed_at IS NOT NULL
              AND review_deadline >= NOW()
            LIMIT 1
        ");
        $stmt->execute(['job_id' => (int)$jobId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
