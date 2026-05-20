<?php
include_once('Database.php');

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
        ];

        $stmt = $this->pdo->query("SHOW COLUMNS FROM jobs");
        $existingColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($columns as $column => $sql) {
            if (!in_array($column, $existingColumns, true)) {
                $this->pdo->exec($sql);
            }
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
