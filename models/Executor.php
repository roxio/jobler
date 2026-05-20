<?php

include_once('Database.php');
include_once('Job.php');
include_once('Message.php');
include_once('Language.php');

class Executor {
    private $pdo;
    private $workflowColumnsEnsured = false;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function installOrUpdateSchema() {
        $this->installOfferWorkflowColumns();
    }

    private function ensureOfferWorkflowColumns() {
        return;
    }

    private function installOfferWorkflowColumns() {
        if ($this->workflowColumnsEnsured) {
            return;
        }

        $responseColumns = [
            'proposed_price' => "ALTER TABLE responses ADD COLUMN proposed_price DECIMAL(10,2) DEFAULT NULL",
            'scope' => "ALTER TABLE responses ADD COLUMN scope TEXT DEFAULT NULL",
            'points_reserved' => "ALTER TABLE responses ADD COLUMN points_reserved INT(11) NOT NULL DEFAULT 0",
            'status' => "ALTER TABLE responses ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'",
            'accepted_at' => "ALTER TABLE responses ADD COLUMN accepted_at DATETIME DEFAULT NULL",
        ];
        $jobColumns = [
            'executor_id' => "ALTER TABLE jobs ADD COLUMN executor_id INT(11) DEFAULT NULL",
            'deleted_at' => "ALTER TABLE jobs ADD COLUMN deleted_at DATETIME DEFAULT NULL",
            'archived_at' => "ALTER TABLE jobs ADD COLUMN archived_at DATETIME DEFAULT NULL",
            'archive_reason' => "ALTER TABLE jobs ADD COLUMN archive_reason VARCHAR(80) DEFAULT NULL",
        ];
        $messageColumnUpdates = [
            'conversation_id' => "ALTER TABLE messages MODIFY conversation_id VARCHAR(100) NOT NULL",
        ];

        $stmt = $this->pdo->query("SHOW COLUMNS FROM responses");
        $existingResponseColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($responseColumns as $column => $sql) {
            if (!in_array($column, $existingResponseColumns, true)) {
                $this->pdo->exec($sql);
            }
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM jobs");
        $existingJobColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($jobColumns as $column => $sql) {
            if (!in_array($column, $existingJobColumns, true)) {
                $this->pdo->exec($sql);
            }
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM messages");
        $existingMessageColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($existingMessageColumns as $column) {
            if ($column['Field'] === 'conversation_id' && stripos($column['Type'], 'varchar') === false) {
                $this->pdo->exec($messageColumnUpdates['conversation_id']);
                break;
            }
        }

        $this->workflowColumnsEnsured = true;
    }

    public function getAvailableJobs() {
        $this->ensureOfferWorkflowColumns();

        $sql = "SELECT * FROM jobs WHERE status = 'open' AND executor_id IS NULL ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function respondToJob($executorId, $jobId, $message, $proposedPrice = null, $scope = '') {
        $this->ensureOfferWorkflowColumns();

        try {
            $this->pdo->beginTransaction();

            $jobStmt = $this->pdo->prepare("SELECT * FROM jobs WHERE id = :job_id AND status = 'open' FOR UPDATE");
            $jobStmt->execute([':job_id' => $jobId]);
            $job = $jobStmt->fetch(PDO::FETCH_ASSOC);

            if (!$job || (int)$job['user_id'] === (int)$executorId) {
                $this->pdo->rollBack();
                return false;
            }

            $duplicateStmt = $this->pdo->prepare("SELECT id FROM responses WHERE job_id = :job_id AND executor_id = :executor_id LIMIT 1");
            $duplicateStmt->execute([':job_id' => $jobId, ':executor_id' => $executorId]);
            if ($duplicateStmt->fetch()) {
                $this->pdo->rollBack();
                return false;
            }

            $pointsRequired = (int)$job['points_required'];
            $balanceStmt = $this->pdo->prepare("
                UPDATE users
                SET account_balance = account_balance - :points
                WHERE id = :executor_id AND account_balance >= :points
            ");
            $balanceStmt->execute([':points' => $pointsRequired, ':executor_id' => $executorId]);

            if ($balanceStmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            $insertResponse = $this->pdo->prepare("
                INSERT INTO responses (job_id, executor_id, message, proposed_price, scope, points_reserved, status)
                VALUES (:job_id, :executor_id, :message, :proposed_price, :scope, :points_reserved, 'pending')
            ");
            $insertResponse->execute([
                ':job_id' => $jobId,
                ':executor_id' => $executorId,
                ':message' => $message,
                ':proposed_price' => $proposedPrice !== '' ? $proposedPrice : null,
                ':scope' => $scope,
                ':points_reserved' => $pointsRequired,
            ]);

            $conversationId = $jobId . '_' . min($executorId, $job['user_id']) . '_' . max($executorId, $job['user_id']);
            $initialMessage = trim(
                $message .
                "\n\n" . __t('executor.initial_price_line', ['price' => ($proposedPrice !== null && $proposedPrice !== '' ? $proposedPrice : __t('user.not_provided'))]) .
                "\n" . __t('executor.scope_line', ['scope' => ($scope !== '' ? $scope : __t('user.not_provided'))])
            );

            $insertMessage = $this->pdo->prepare("
                INSERT INTO messages (job_id, sender_id, receiver_id, content, message, conversation_id, created_at, read_status)
                VALUES (:job_id, :sender_id, :receiver_id, :content, :message, :conversation_id, NOW(), 0)
            ");
            $insertMessage->execute([
                ':job_id' => $jobId,
                ':sender_id' => $executorId,
                ':receiver_id' => $job['user_id'],
                ':content' => $initialMessage,
                ':message' => $initialMessage,
                ':conversation_id' => $conversationId,
            ]);

            $this->pdo->commit();
            return $conversationId;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log(__t('executor.respond_error_log', ['error' => $e->getMessage()]));
            return false;
        }
    }

    public function getResponsesForUserJobs($userId) {
        return $this->getUserJobOffers($userId);
    }

    public function getJobResponses($jobId) {
        $sql = "SELECT * FROM messages WHERE job_id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':job_id', $jobId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getJobDetails($jobId) {
        $this->ensureOfferWorkflowColumns();

        $sql = "SELECT * FROM jobs WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':job_id', $jobId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getExecutorById($executorId) {
        $sql = "SELECT * FROM users WHERE id = :executor_id AND role = 'executor'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':executor_id', $executorId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateJobStatus($jobId, $status, $executorId = null) {
        $this->ensureOfferWorkflowColumns();

        $sql = "UPDATE jobs SET status = :status, executor_id = :executor_id WHERE id = :job_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':job_id', $jobId);
        $stmt->bindParam(':executor_id', $executorId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getRespondedJobs($executorId) {
        $this->ensureOfferWorkflowColumns();

        $query = "SELECT jobs.id, jobs.user_id, jobs.title, jobs.description, jobs.points_required,
                         responses.id AS response_id, responses.created_at AS response_date,
                         responses.proposed_price, responses.scope, responses.points_reserved, responses.status
                  FROM jobs
                  INNER JOIN responses ON jobs.id = responses.job_id
                  WHERE responses.executor_id = :executor_id
                  ORDER BY responses.created_at DESC";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':executor_id', $executorId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasRespondedToJob($executorId, $jobId) {
        $this->ensureOfferWorkflowColumns();

        try {
            $queryResponses = "
                SELECT r.id AS response_id, r.executor_id, r.job_id
                FROM responses r
                WHERE r.executor_id = :executorId AND r.job_id = :jobId
                LIMIT 1
            ";
            $stmtResponses = $this->pdo->prepare($queryResponses);
            $stmtResponses->bindParam(':executorId', $executorId, PDO::PARAM_INT);
            $stmtResponses->bindParam(':jobId', $jobId, PDO::PARAM_INT);
            $stmtResponses->execute();
            $response = $stmtResponses->fetch(PDO::FETCH_ASSOC);

            $jobDetails = $this->getJobDetails($jobId);
            if (!$jobDetails) {
                return false;
            }

            $receiverId = $jobDetails['user_id'];
            $computedConversationId = $jobId . "_" . min($executorId, $receiverId) . "_" . max($executorId, $receiverId);

            $queryMessages = "
                SELECT m.conversation_id
                FROM messages m
                WHERE m.job_id = :jobId
                  AND m.conversation_id = :conversationId
                LIMIT 1
            ";
            $stmtMessages = $this->pdo->prepare($queryMessages);
            $stmtMessages->bindParam(':jobId', $jobId, PDO::PARAM_INT);
            $stmtMessages->bindParam(':conversationId', $computedConversationId, PDO::PARAM_STR);
            $stmtMessages->execute();
            $message = $stmtMessages->fetch(PDO::FETCH_ASSOC);

            if ($message) {
                return [
                    'response_id' => $response ? $response['response_id'] : null,
                    'conversation_id' => $message['conversation_id'],
                ];
            }

            if ($response) {
                return [
                    'response_id' => $response['response_id'],
                    'conversation_id' => null,
                ];
            }

            return false;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getExecutorBalance($executorId) {
        $sql = "SELECT account_balance FROM users WHERE id = :executor_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':executor_id', $executorId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['account_balance'] : 0;
    }

    public function getUserJobOffers($userId) {
        $this->ensureOfferWorkflowColumns();

        $query = "SELECT r.id AS response_id, r.message, r.proposed_price, r.scope, r.points_reserved,
                         r.status AS response_status, r.created_at, r.accepted_at,
                         j.id AS job_id, j.title, j.status AS job_status, j.points_required, j.executor_id AS accepted_executor_id,
                         u.name AS executor_name, u.id AS executor_id
                  FROM jobs j
                  INNER JOIN responses r ON j.id = r.job_id
                  INNER JOIN users u ON r.executor_id = u.id
                  WHERE j.user_id = :user_id
                    AND (j.deleted_at IS NULL OR j.deleted_at = '')
                    AND (j.archived_at IS NULL OR j.archived_at = '')
                  ORDER BY j.created_at DESC, r.created_at DESC";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function acceptResponse($userId, $responseId) {
        $this->ensureOfferWorkflowColumns();

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                SELECT r.*, j.user_id, j.status AS job_status
                FROM responses r
                INNER JOIN jobs j ON r.job_id = j.id
                WHERE r.id = :response_id AND j.user_id = :user_id
                FOR UPDATE
            ");
            $stmt->execute([':response_id' => $responseId, ':user_id' => $userId]);
            $acceptedResponse = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$acceptedResponse || $acceptedResponse['job_status'] !== 'open' || $acceptedResponse['status'] !== 'pending') {
                $this->pdo->rollBack();
                return false;
            }

            $jobId = (int)$acceptedResponse['job_id'];
            $executorId = (int)$acceptedResponse['executor_id'];

            $otherStmt = $this->pdo->prepare("
                SELECT id, executor_id, points_reserved
                FROM responses
                WHERE job_id = :job_id AND id <> :response_id AND status = 'pending'
                FOR UPDATE
            ");
            $otherStmt->execute([':job_id' => $jobId, ':response_id' => $responseId]);
            $otherResponses = $otherStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($otherResponses as $response) {
                $points = (int)$response['points_reserved'];
                if ($points > 0) {
                    $refundStmt = $this->pdo->prepare("UPDATE users SET account_balance = account_balance + :points WHERE id = :executor_id");
                    $refundStmt->execute([':points' => $points, ':executor_id' => $response['executor_id']]);
                }
            }

            $this->pdo->prepare("
                UPDATE responses
                SET status = 'rejected', points_reserved = 0
                WHERE job_id = :job_id AND id <> :response_id AND status = 'pending'
            ")->execute([':job_id' => $jobId, ':response_id' => $responseId]);

            $this->pdo->prepare("UPDATE responses SET status = 'accepted', accepted_at = NOW() WHERE id = :response_id")
                ->execute([':response_id' => $responseId]);

            $this->pdo->prepare("UPDATE jobs SET status = 'in_progress', executor_id = :executor_id, updated_at = NOW() WHERE id = :job_id")
                ->execute([':executor_id' => $executorId, ':job_id' => $jobId]);

            $conversationId = $jobId . '_' . min($userId, $executorId) . '_' . max($userId, $executorId);
            $acceptanceMessage = __t('executor.offer_acceptance_message');
            $this->pdo->prepare("
                INSERT INTO messages (job_id, sender_id, receiver_id, content, message, conversation_id, created_at, read_status)
                VALUES (:job_id, :sender_id, :receiver_id, :content, :message, :conversation_id, NOW(), 0)
            ")->execute([
                ':job_id' => $jobId,
                ':sender_id' => $userId,
                ':receiver_id' => $executorId,
                ':content' => $acceptanceMessage,
                ':message' => $acceptanceMessage,
                ':conversation_id' => $conversationId,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log(__t('executor.accept_error_log', ['error' => $e->getMessage()]));
            return false;
        }
    }

    public function isExecutor($userId) {
        $query = "SELECT role FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();

        return $role === 'executor';
    }
}
?>
