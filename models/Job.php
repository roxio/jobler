<?php
include_once(__DIR__ . '/Database.php');

class Job {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function installOrUpdateSchema() {
        $this->installArchiveColumns();
    }

    public function createJob($userId, $title, $description, $pointsRequired, $categoryId) {
        $sql = "INSERT INTO jobs (user_id, title, description, points_required, category_id, status, created_at, updated_at)
                VALUES (:user_id, :title, :description, :points_required, :category_id, 'open', NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':points_required', $pointsRequired, PDO::PARAM_INT);
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
        return $stmt->execute();
    }


    public function getCategories() {
        $sql = "SELECT * FROM categories";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getUserJobs($userId) {
        $this->ensureArchiveColumns();
        $sql = "SELECT * FROM jobs WHERE user_id = :user_id AND deleted_at IS NULL AND archived_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getJobDetails($jobId) {
        $sql = "SELECT jobs.*, users.name as user_name
                FROM jobs
                LEFT JOIN users ON jobs.user_id = users.id
                WHERE jobs.id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function updateJob($jobId, $title, $description, $status, $pointsRequired) {
        $sql = "UPDATE jobs SET title = :title, description = :description, points_required = :points_required, status = :status, updated_at = NOW() WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':points_required', $pointsRequired, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':job_id', $jobId);
        return $stmt->execute();
    }


    public function updateJobStatus($jobId, $status) {
        $sql = "UPDATE jobs SET status = :status, updated_at = NOW() WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':job_id', $jobId);
        return $stmt->execute();
    }


    public function deleteJob($id) {
        $this->ensureArchiveColumns();
        $this->refundResponsePoints($id);
        $sql = "UPDATE jobs SET deleted_at = COALESCE(deleted_at, NOW()), archived_at = COALESCE(archived_at, NOW()), archive_reason = 'admin_archived', updated_at = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function restoreJob($id) {
        $this->ensureArchiveColumns();
        $sql = "UPDATE jobs SET deleted_at = NULL, archived_at = NULL, archive_reason = NULL, updated_at = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }


    public function permanentlyDeleteJob($id) {
        $this->ensureArchiveColumns();
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("DELETE FROM conversation_reports WHERE job_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM messages WHERE job_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM responses WHERE job_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM job_change_history WHERE job_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM job_reports WHERE job_id = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM job_images WHERE job_id = :id")->execute(['id' => $id]);
            $deleted = $this->pdo->prepare("DELETE FROM jobs WHERE id = :id")->execute(['id' => $id]);
            $this->pdo->commit();
            return $deleted;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Permanent job delete error: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureArchiveColumns() {
        return;
    }

    private function installArchiveColumns() {
        $columns = [
            'budget_estimate' => "ALTER TABLE jobs ADD COLUMN budget_estimate DECIMAL(10,2) DEFAULT NULL",
            'realization_time' => "ALTER TABLE jobs ADD COLUMN realization_time VARCHAR(120) DEFAULT NULL",
            'validity_days' => "ALTER TABLE jobs ADD COLUMN validity_days INT(11) NOT NULL DEFAULT 7",
            'expires_at' => "ALTER TABLE jobs ADD COLUMN expires_at DATETIME DEFAULT NULL",
            'work_mode' => "ALTER TABLE jobs ADD COLUMN work_mode VARCHAR(20) NOT NULL DEFAULT 'remote'",
            'primary_image' => "ALTER TABLE jobs ADD COLUMN primary_image VARCHAR(255) NOT NULL DEFAULT 'no_image.jpg'",
            'deleted_at' => "ALTER TABLE jobs ADD COLUMN deleted_at DATETIME DEFAULT NULL",
            'archived_at' => "ALTER TABLE jobs ADD COLUMN archived_at DATETIME DEFAULT NULL",
            'archive_reason' => "ALTER TABLE jobs ADD COLUMN archive_reason VARCHAR(80) DEFAULT NULL",
            'user_completion_requested_at' => "ALTER TABLE jobs ADD COLUMN user_completion_requested_at DATETIME DEFAULT NULL",
            'executor_completion_requested_at' => "ALTER TABLE jobs ADD COLUMN executor_completion_requested_at DATETIME DEFAULT NULL",
            'completion_disputed_at' => "ALTER TABLE jobs ADD COLUMN completion_disputed_at DATETIME DEFAULT NULL",
            'completed_at' => "ALTER TABLE jobs ADD COLUMN completed_at DATETIME DEFAULT NULL",
            'review_deadline' => "ALTER TABLE jobs ADD COLUMN review_deadline DATETIME DEFAULT NULL",
        ];

        $stmt = $this->pdo->query("SHOW COLUMNS FROM jobs");
        $existingColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($columns as $column => $sql) {
            if (!in_array($column, $existingColumns, true)) {
                $this->pdo->exec($sql);
            }
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM jobs LIKE 'status'");
        $statusColumn = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($statusColumn && stripos($statusColumn['Type'] ?? '', 'completed') === false) {
            $this->pdo->exec("ALTER TABLE jobs MODIFY status ENUM('open','active','in_progress','completed','under_review','closed','inactive') NOT NULL DEFAULT 'open'");
        }
    }

    public function archiveExpiredJobs($retry = true) {
        try {
            return $this->pdo->exec("
                UPDATE jobs
                SET archived_at = COALESCE(archived_at, NOW()),
                    archive_reason = CASE
                        WHEN deleted_at IS NOT NULL THEN 'auto_year_after_delete'
                        ELSE 'auto_year_after_publish'
                    END,
                    updated_at = NOW()
                WHERE archived_at IS NULL
                  AND (
                      created_at <= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                      OR (deleted_at IS NOT NULL AND deleted_at <= DATE_SUB(NOW(), INTERVAL 1 YEAR))
                  )
            ");
        } catch (PDOException $e) {
            if (!$retry) {
                throw $e;
            }

            $this->installOrUpdateSchema();
            return $this->archiveExpiredJobs(false);
        }
    }

    public function processCompletionTimeouts() {
        $this->ensureArchiveColumns();

        return $this->pdo->exec("
            UPDATE jobs
            SET status = 'completed',
                completed_at = COALESCE(completed_at, NOW()),
                review_deadline = COALESCE(review_deadline, DATE_ADD(NOW(), INTERVAL 30 DAY)),
                updated_at = NOW()
            WHERE status = 'in_progress'
              AND completion_disputed_at IS NULL
              AND completed_at IS NULL
              AND (
                    (user_completion_requested_at IS NOT NULL
                     AND executor_completion_requested_at IS NULL
                     AND user_completion_requested_at <= DATE_SUB(NOW(), INTERVAL 10 DAY))
                 OR (executor_completion_requested_at IS NOT NULL
                     AND user_completion_requested_at IS NULL
                     AND executor_completion_requested_at <= DATE_SUB(NOW(), INTERVAL 10 DAY))
              )
        ");
    }

    public function markCompletion($jobId, $userId) {
        $this->ensureArchiveColumns();

        try {
            $this->pdo->beginTransaction();
            $job = $this->getParticipantJobForUpdate($jobId, $userId);

            if (!$job || $job['status'] !== 'in_progress') {
                $this->pdo->rollBack();
                return false;
            }

            $role = $this->resolveParticipantRole($job, $userId);
            if ($role === null) {
                $this->pdo->rollBack();
                return false;
            }

            $column = $role === 'user' ? 'user_completion_requested_at' : 'executor_completion_requested_at';
            $otherColumn = $role === 'user' ? 'executor_completion_requested_at' : 'user_completion_requested_at';
            $completeNow = !empty($job[$otherColumn]);

            if ($completeNow) {
                $stmt = $this->pdo->prepare("
                    UPDATE jobs
                    SET {$column} = COALESCE({$column}, NOW()),
                        status = 'completed',
                        completed_at = COALESCE(completed_at, NOW()),
                        review_deadline = COALESCE(review_deadline, DATE_ADD(NOW(), INTERVAL 30 DAY)),
                        updated_at = NOW()
                    WHERE id = :job_id
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE jobs
                    SET {$column} = COALESCE({$column}, NOW()),
                        updated_at = NOW()
                    WHERE id = :job_id
                ");
            }

            $stmt->execute(['job_id' => (int)$jobId]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Job completion mark error: ' . $e->getMessage());
            return false;
        }
    }

    public function disputeCompletion($jobId, $userId) {
        $this->ensureArchiveColumns();

        try {
            $this->pdo->beginTransaction();
            $job = $this->getParticipantJobForUpdate($jobId, $userId);

            if (!$job || $job['status'] !== 'in_progress') {
                $this->pdo->rollBack();
                return false;
            }

            $role = $this->resolveParticipantRole($job, $userId);
            if ($role === null) {
                $this->pdo->rollBack();
                return false;
            }

            $ownColumn = $role === 'user' ? 'user_completion_requested_at' : 'executor_completion_requested_at';
            $otherColumn = $role === 'user' ? 'executor_completion_requested_at' : 'user_completion_requested_at';

            if (empty($job[$otherColumn]) || !empty($job[$ownColumn])) {
                $this->pdo->rollBack();
                return false;
            }

            $stmt = $this->pdo->prepare("
                UPDATE jobs
                SET status = 'under_review',
                    completion_disputed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :job_id
            ");
            $stmt->execute(['job_id' => (int)$jobId]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Job completion dispute error: ' . $e->getMessage());
            return false;
        }
    }

    public function openDispute($jobId, $userId) {
        $this->ensureArchiveColumns();

        try {
            $this->pdo->beginTransaction();
            $job = $this->getParticipantJobForUpdate($jobId, $userId);

            if (!$job || !in_array($job['status'], ['completed', 'in_progress'], true)) {
                $this->pdo->rollBack();
                return false;
            }

            if ($this->resolveParticipantRole($job, $userId) === null) {
                $this->pdo->rollBack();
                return false;
            }

            $stmt = $this->pdo->prepare("
                UPDATE jobs
                SET status = 'under_review',
                    completion_disputed_at = COALESCE(completion_disputed_at, NOW()),
                    updated_at = NOW()
                WHERE id = :job_id
            ");
            $stmt->execute(['job_id' => (int)$jobId]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Job dispute open error: ' . $e->getMessage());
            return false;
        }
    }

    public function getCompletionContext($jobId, $userId) {
        $this->ensureArchiveColumns();

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM jobs
            WHERE id = :job_id
              AND deleted_at IS NULL
              AND archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['job_id' => (int)$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            return null;
        }

        $role = $this->resolveParticipantRole($job, $userId);
        if ($role === null) {
            return null;
        }

        $ownColumn = $role === 'user' ? 'user_completion_requested_at' : 'executor_completion_requested_at';
        $otherColumn = $role === 'user' ? 'executor_completion_requested_at' : 'user_completion_requested_at';
        $otherRequestedAt = $job[$otherColumn] ?? null;
        $autoConfirmAt = $otherRequestedAt ? date('Y-m-d H:i:s', strtotime($otherRequestedAt . ' +10 days')) : null;

        return [
            'job' => $job,
            'role' => $role,
            'own_requested_at' => $job[$ownColumn] ?? null,
            'other_requested_at' => $otherRequestedAt,
            'auto_confirm_at' => $autoConfirmAt,
            'can_mark_complete' => $job['status'] === 'in_progress' && empty($job[$ownColumn]),
            'can_dispute' => $job['status'] === 'in_progress' && !empty($job[$otherColumn]) && empty($job[$ownColumn]),
            'can_rate' => $job['status'] === 'completed' && !empty($job['review_deadline']) && strtotime($job['review_deadline']) >= time(),
        ];
    }

    private function getParticipantJobForUpdate($jobId, $userId) {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM jobs
            WHERE id = :job_id
              AND (user_id = :user_id OR executor_id = :user_id)
              AND deleted_at IS NULL
              AND archived_at IS NULL
            FOR UPDATE
        ");
        $stmt->execute(['job_id' => (int)$jobId, 'user_id' => (int)$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function resolveParticipantRole(array $job, $userId) {
        $userId = (int)$userId;
        if ((int)$job['user_id'] === $userId) {
            return 'user';
        }
        if (!empty($job['executor_id']) && (int)$job['executor_id'] === $userId) {
            return 'executor';
        }
        return null;
    }

    private function refundResponsePoints($jobId) {
        $stmt = $this->pdo->prepare("SELECT executor_id, points_reserved FROM responses WHERE job_id = :job_id AND points_reserved > 0");
        $stmt->execute(['job_id' => $jobId]);
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($responses as $response) {
            $points = (int)$response['points_reserved'];
            if ($points > 0) {
                $this->pdo->prepare("UPDATE users SET account_balance = account_balance + :points WHERE id = :executor_id")
                    ->execute(['points' => $points, 'executor_id' => (int)$response['executor_id']]);
            }
        }

        $this->pdo->prepare("UPDATE responses SET status = CASE WHEN status = 'accepted' THEN 'cancelled' ELSE 'refunded' END, points_reserved = 0 WHERE job_id = :job_id AND points_reserved > 0")
            ->execute(['job_id' => $jobId]);
    }

    public function getAvailableJobs() {
        $this->ensureArchiveColumns();
        $sql = "SELECT * FROM jobs WHERE status = 'open' AND executor_id IS NULL AND deleted_at IS NULL AND archived_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getJobsWithExecutor() {
        $sql = "SELECT * FROM jobs WHERE executor_id IS NOT NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAllJobs($limit = null, $offset = null) {
        $sql = "SELECT * FROM jobs ORDER BY created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $this->pdo->prepare($sql);

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            if ($offset !== null) {
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getJobCount() {
        $sql = "SELECT COUNT(*) FROM jobs";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchColumn();
    }


    public function getJobsWithPagination($limit, $offset) {
        $stmt = $this->pdo->prepare("SELECT * FROM jobs ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function countAllJobs() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM jobs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }


    public function getJobsWithPaginationAndSearch($limit, $offset, $search, $category = null) {
        $sql = "SELECT * FROM jobs WHERE title LIKE :search";
        if ($category) {
            $sql .= " AND category_id = :category";
        }
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        if ($category) {
            $stmt->bindValue(':category', $category, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function countJobsWithSearch($search) {
        $sql = "SELECT COUNT(*) as total FROM jobs WHERE title LIKE :search";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }


    public function getNewJobsCount() {
        $sql = "SELECT COUNT(*) FROM jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }


    public function getNewJobsPerDay() {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM jobs WHERE created_at > NOW() - INTERVAL 7 DAY GROUP BY DATE(created_at)";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }


    public function searchJobs($searchTerm, $limit = null, $offset = null) {
        $sql = "SELECT id, title, description, status, created_at FROM jobs
                WHERE id LIKE :search OR title LIKE :search OR description LIKE :search
                ORDER BY created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $likeTerm = "%".$searchTerm."%";
        $stmt->bindParam(':search', $likeTerm, PDO::PARAM_STR);

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            if ($offset !== null) {
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function countSearchJobs($searchTerm) {
        $sql = "SELECT COUNT(*) as total FROM jobs
                WHERE id LIKE :search OR title LIKE :search OR description LIKE :search";

        $stmt = $this->pdo->prepare($sql);
        $likeTerm = "%".$searchTerm."%";
        $stmt->bindParam(':search', $likeTerm, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
	public function getActiveJobsCount() {
    $stmt = $this->pdo->prepare("SELECT COUNT(*) as cnt FROM jobs WHERE status = 'active'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['cnt'] : 0;
}
public function getJobsByUserId($userId, $limit = 5) {
    $this->ensureArchiveColumns();
    $query = "SELECT j.*, 0 as offer_count
              FROM jobs j
              WHERE j.user_id = ?
                AND j.deleted_at IS NULL
                AND j.archived_at IS NULL
              ORDER BY j.created_at DESC
              LIMIT ?";
    $stmt = $this->pdo->prepare($query);
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getTotalJobsWithSearch($search = '') {
    if ($search) {
        $sql = "SELECT COUNT(*) FROM jobs WHERE title LIKE :search OR description LIKE :search";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn();
    } else {
        $sql = "SELECT COUNT(*) FROM jobs";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchColumn();
    }
}
public function getJobsWithFilters($limit, $offset, $sortColumn, $sortOrder, $search = '', $statusFilter = '', $categoryFilter = '', $userFilter = '', $dateFrom = '', $dateTo = '') {
    $whereConditions = [];
    $params = [];

    if ($search) {
        $whereConditions[] = "(j.title LIKE :search OR j.description LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($statusFilter === 'archived') {
        $whereConditions[] = "j.archived_at IS NOT NULL";
    } elseif ($statusFilter) {
        $whereConditions[] = "j.status = :status";
        $params[':status'] = $statusFilter;
    }

    if ($categoryFilter) {
        $whereConditions[] = "j.category_id = :category_id";
        $params[':category_id'] = $categoryFilter;
    }

    if ($userFilter) {
        $whereConditions[] = "j.user_id = :user_id";
        $params[':user_id'] = $userFilter;
    }

    if ($dateFrom) {
        $whereConditions[] = "j.created_at >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo) {
        $whereConditions[] = "j.created_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';


    $allowedSortColumns = ['id', 'title', 'points_required', 'created_at', 'status'];
    if (!in_array($sortColumn, $allowedSortColumns)) {
        $sortColumn = 'created_at';
    }


    $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

    $query = "SELECT j.*, u.name as user_name, c.name as category_name
              FROM jobs j
              LEFT JOIN users u ON j.user_id = u.id
              LEFT JOIN categories c ON j.category_id = c.id
              $whereClause
              ORDER BY j.$sortColumn $sortOrder
              LIMIT :limit OFFSET :offset";

    $stmt = $this->pdo->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function countJobsWithFilters($search = '', $statusFilter = '', $categoryFilter = '', $userFilter = '', $dateFrom = '', $dateTo = '') {
    $whereConditions = [];
    $params = [];

    if ($search) {
        $whereConditions[] = "(title LIKE :search OR description LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($statusFilter === 'archived') {
        $whereConditions[] = "archived_at IS NOT NULL";
    } elseif ($statusFilter) {
        $whereConditions[] = "status = :status";
        $params[':status'] = $statusFilter;
    }

    if ($categoryFilter) {
        $whereConditions[] = "category_id = :category_id";
        $params[':category_id'] = $categoryFilter;
    }

    if ($userFilter) {
        $whereConditions[] = "user_id = :user_id";
        $params[':user_id'] = $userFilter;
    }

    if ($dateFrom) {
        $whereConditions[] = "created_at >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo) {
        $whereConditions[] = "created_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $query = "SELECT COUNT(*) as total FROM jobs $whereClause";
    $stmt = $this->pdo->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ? (int)$result['total'] : 0;
}
public function countJobsByStatus($status) {
    $sql = "SELECT COUNT(*) as count FROM jobs WHERE status = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$status]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

public function getJobStats() {
    $stats = [
        'total' => $this->countJobs(),
        'open' => $this->countJobsByStatus('open'),
        'active' => $this->countJobsByStatus('active'),
        'closed' => $this->countJobsByStatus('closed'),
        'inactive' => $this->countJobsByStatus('inactive')
    ];
    return $stats;
}
}
?>
