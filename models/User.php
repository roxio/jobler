<?php
include_once(__DIR__ . '/Database.php');
include_once(__DIR__ . '/AccessControl.php');
include_once(__DIR__ . '/Language.php');

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function installOrUpdateSchema() {
        $this->ensureProfileColumns();
    }

    private function ensureProfileColumns() {
        $columns = [
            'original_email' => "ALTER TABLE users ADD COLUMN original_email VARCHAR(255) DEFAULT NULL",
            'original_name' => "ALTER TABLE users ADD COLUMN original_name VARCHAR(255) DEFAULT NULL",
            'original_username' => "ALTER TABLE users ADD COLUMN original_username VARCHAR(255) DEFAULT NULL",
            'original_phone' => "ALTER TABLE users ADD COLUMN original_phone VARCHAR(50) DEFAULT NULL",
            'profile_updated_at' => "ALTER TABLE users ADD COLUMN profile_updated_at DATETIME DEFAULT NULL",
        ];

        $stmt = $this->pdo->query("SHOW COLUMNS FROM users");
        $existingColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($columns as $column => $sql) {
            if (!in_array($column, $existingColumns, true)) {
                $this->pdo->exec($sql);
            }
        }

        $this->pdo->exec("
            UPDATE users
            SET original_email = COALESCE(original_email, email),
                original_name = COALESCE(original_name, name),
                original_username = COALESCE(original_username, username),
                original_phone = COALESCE(original_phone, phone)
            WHERE original_email IS NULL
               OR original_name IS NULL
               OR original_username IS NULL
               OR original_phone IS NULL
        ");
    }

    public function register($email, $password, $name, $username, $role = 'user', $phone = '') {
        if (!in_array($role, ['user', 'executor'], true)) {
            $role = 'user';
        }

        $registrationIp = $_SERVER['REMOTE_ADDR'];
        $sql = "SELECT * FROM users WHERE email = :email OR username = :username";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['error' => __t('auth.user_exists')];
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (email, password, name, username, role, phone, registration_ip, original_email, original_name, original_username, original_phone, created_at, updated_at)
                VALUES (:email, :password, :name, :username, :role, :phone, :registration_ip, :original_email, :original_name, :original_username, :original_phone, NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':registration_ip', $registrationIp);
        $stmt->bindParam(':original_email', $email);
        $stmt->bindParam(':original_name', $name);
        $stmt->bindParam(':original_username', $username);
        $stmt->bindParam(':original_phone', $phone);

        try {
            $saved = $stmt->execute();
        } catch (Throwable $e) {
            $this->installOrUpdateSchema();
            $saved = $stmt->execute();
        }

        if ($saved) {
            $userId = $this->pdo->lastInsertId();
            return ['success' => __t('auth.registration_success'), 'id' => $userId, 'role' => $role];
        }

        return ['error' => __t('auth.registration_error')];
    }

    public function login($email, $password) {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);

        if (!$stmt->execute()) {
            return false;
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && $user['status'] !== 'active') {
            return ['error' => __t('auth.account_blocked')];
        }

        if ($user && password_verify($password, $user['password'])) {
            $lastLoginIp = $_SERVER['REMOTE_ADDR'];
            $this->updateLastLoginIp($user['id'], $lastLoginIp);
            return $user;
        }

        return false;
    }

public function getUserById($userId) {
    $sql = "SELECT id, email, name, role, created_at, updated_at, registration_ip,
                   last_login_ip, status, account_balance, last_login,
                   email_verified_at, username, phone, need_change, avatar, newsletter_subscription,
                   user_agent, original_email, original_name, original_username, original_phone, profile_updated_at
            FROM users WHERE id = :user_id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

    public function updateUser($userId, $email) {
        $sql = "UPDATE users SET email = :email, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_id', $userId);
        return $stmt->execute();
    }

    public function updateProfile($userId, $data) {
        $name = trim((string)($data['name'] ?? ''));
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $newsletter = !empty($data['newsletter_subscription']) ? 1 : 0;

        if ($name === '' || $username === '' || $email === '') {
            return ['error' => __t('auth.profile_required')];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => __t('auth.email_invalid')];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE (email = :email OR username = :username) AND id <> :user_id LIMIT 1");
        $stmt->execute([
            'email' => $email,
            'username' => $username,
            'user_id' => $userId,
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['error' => __t('auth.profile_duplicate')];
        }

        $stmt = $this->pdo->prepare("
            UPDATE users
            SET name = :name,
                username = :username,
                email = :email,
                phone = :phone,
                newsletter_subscription = :newsletter_subscription,
                profile_updated_at = NOW(),
                updated_at = NOW()
            WHERE id = :user_id
        ");

        try {
            $saved = $stmt->execute([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'newsletter_subscription' => $newsletter,
                'user_id' => $userId,
            ]);
        } catch (Throwable $e) {
            $this->installOrUpdateSchema();
            $saved = $stmt->execute([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'newsletter_subscription' => $newsletter,
                'user_id' => $userId,
            ]);
        }

        if (!$saved) {
            return ['error' => __t('auth.profile_save_error')];
        }

        $_SESSION['user_name'] = $name;
        return ['success' => __t('auth.profile_saved')];
    }

    public function changePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $userId);
        return $stmt->execute();
    }

    public function addPointsToUser($userId, $points) {
        $sql = "UPDATE users SET account_balance = account_balance + :points WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':points', $points, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getAllUsers() {
        $sql = "SELECT id, name, email, role, status, created_at, updated_at, registration_ip, last_login_ip, account_balance, need_change FROM users";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaginatedUsers($limit, $offset, $sortColumn, $sortOrder, $search) {
        $searchQuery = '';
        if ($search) {
            $searchQuery = "WHERE name LIKE :search OR email LIKE :search OR id LIKE :search";
        }

        $query = "SELECT * FROM users $searchQuery ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        if ($search) {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalUsers($search) {
        $searchQuery = '';
        if ($search) {
            $searchQuery = "WHERE name LIKE :search OR email LIKE :search OR id LIKE :search";
        }

        $query = "SELECT COUNT(*) FROM users $searchQuery";
        $stmt = $this->pdo->prepare($query);
        if ($search) {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPaginatedUsersWithFilters($limit, $offset, $sortColumn, $sortOrder, $search, $statusFilter, $roleFilter, $dateFrom, $dateTo) {
        $whereConditions = [];
        $params = [];


        if ($search) {
            $whereConditions[] = "(name LIKE :search OR email LIKE :search OR id LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($statusFilter) {
            $whereConditions[] = "status = :status";
            $params[':status'] = $statusFilter;
        }

        if ($roleFilter) {
            $whereConditions[] = "role = :role";
            $params[':role'] = $roleFilter;
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
        $query = "SELECT * FROM users $whereClause ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalUsersWithFilters($search, $statusFilter, $roleFilter, $dateFrom, $dateTo) {
        $whereConditions = [];
        $params = [];


        if ($search) {
            $whereConditions[] = "(name LIKE :search OR email LIKE :search OR id LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($statusFilter) {
            $whereConditions[] = "status = :status";
            $params[':status'] = $statusFilter;
        }

        if ($roleFilter) {
            $whereConditions[] = "role = :role";
            $params[':role'] = $roleFilter;
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
        $query = "SELECT COUNT(*) FROM users $whereClause";
        $stmt = $this->pdo->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function countUsersByStatus($status) {
        $sql = "SELECT COUNT(*) FROM users WHERE status = :status";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function countUsersByRole($role) {
        $sql = "SELECT COUNT(*) FROM users WHERE role = :role";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function countNewUsersToday() {
        $sql = "SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function countUsersNeedingAttention() {
        $sql = "SELECT COUNT(*) FROM users WHERE need_change = 1 OR status = 'inactive'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getUserRole($userId) {
        $sql = "SELECT role FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user['role'] : null;
    }

    public function getUserCount() {
        $sql = "SELECT COUNT(*) FROM users";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchColumn();
    }

    public function getNewUsersCount() {
        $sql = "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

public function deleteUser($userId) {
		if ($userId == $_SESSION['user_id']) {
			throw new Exception("Cannot delete yourself");     }
			$sql = "DELETE FROM users WHERE id = :user_id";
			$stmt = $this->pdo->prepare($sql);
			$stmt->bindParam(':user_id', $userId);
		return $stmt->execute();
	}

    public function deleteUsers($userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $query = "DELETE FROM users WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($userIds);
    }

    public function deactivateUser($userId) {
        $query = "UPDATE users SET status = 'inactive' WHERE id = :user_id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function activateUser($userId) {
        $query = "UPDATE users SET status = 'active' WHERE id = :user_id AND status = 'inactive'";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateLastLoginIp($userId, $lastLoginIp) {
        $sql = "UPDATE users SET last_login_ip = :last_login_ip WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':last_login_ip', $lastLoginIp);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getResponsesForUserJobs($userId) {
        $query = "SELECT j.title AS title, r.message AS message, r.created_at AS created_at,
                 u.name AS executor_name, r.executor_id AS executor_id, j.id AS job_id
                 FROM jobs j
                 INNER JOIN responses r ON j.id = r.job_id
                 INNER JOIN users u ON r.executor_id = u.id
                 WHERE j.user_id = :user_id
                 ORDER BY r.created_at DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addBalanceToUser($userId, $balanceToAdd) {
        $sql = "UPDATE users SET account_balance = account_balance + :balance_to_add, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':balance_to_add', $balanceToAdd, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getPendingAccountChangesCount() {
        $query = "SELECT COUNT(*) FROM users WHERE need_change = 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function changeUserRole($user_id, $new_role) {
        if (!array_key_exists($new_role, AccessControl::roles())) {
            return false;
        }

        $query = "UPDATE users SET role = :new_role, need_change = 0 WHERE id = :user_id";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([':new_role' => $new_role, ':user_id' => $user_id]);
    }

    public function getNewUsersPerDay() {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at > NOW() - INTERVAL 7 DAY GROUP BY DATE(created_at)";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLastActivity($userId) {
        $sql = "SELECT last_login FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['last_login'] : null;
    }

    public function updateLastActivity($userId) {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        return $stmt->execute();
    }

public function getUnreadReportsCount() {
    $stmt = $this->pdo->prepare("SELECT COUNT(*) as cnt FROM reports WHERE status = 'unread'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['cnt'] : 0;
}
public function getRecentActivities($limit = 10) {
    $stmt = $this->pdo->prepare("
        SELECT a.*, u.username
        FROM user_activities a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.timestamp DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function countUsersWithJobs() {
    $sql = "SELECT COUNT(DISTINCT user_id) FROM jobs";
    $stmt = $this->pdo->query($sql);
    return $stmt->fetchColumn();
}
public function countVerifiedUsers() {
    $sql = "SELECT COUNT(*) FROM users WHERE status = 'verified'";
    $stmt = $this->pdo->query($sql);
    return $stmt->fetchColumn();
}
public function getUsersByIds($userIds) {
    if (empty($userIds)) return [];

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $query = "SELECT id, name, email, role, status, created_at, last_login,
                     account_balance, registration_ip, email_verified_at
              FROM users
              WHERE id IN ($placeholders)
              ORDER BY name";

    $stmt = $this->pdo->prepare($query);
    $stmt->execute($userIds);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function hasActiveJobs($userId) {
    $query = "SELECT COUNT(*) FROM jobs WHERE user_id = ? AND status = 'active'";
    $stmt = $this->pdo->prepare($query);
    $stmt->execute([$userId]);

    return $stmt->fetchColumn() > 0;
}

public function hasPendingTransactions($userId) {
    $query = "SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'pending'";
    $stmt = $this->pdo->prepare($query);
    $stmt->execute([$userId]);

    return $stmt->fetchColumn() > 0;
}

public function hasActiveConversations($userId) {
    $query = "SELECT COUNT(*) FROM conversations WHERE user_id = ? AND status = 'active'";
    $stmt = $this->pdo->prepare($query);
    $stmt->execute([$userId]);

    return $stmt->fetchColumn() > 0;
}
public function getUserStatistics($userId) {
    return [
        'total_jobs' => $this->countUserJobs($userId),
        'active_jobs' => $this->countUserActiveJobs($userId),
        'total_transactions' => $this->countUserTransactions($userId),
        'total_messages' => $this->countUserMessages($userId)
    ];
}
public function getLoginHistory($userId, $limit = 10) {
    $query = "SELECT * FROM user_login_history
              WHERE user_id = ?
              ORDER BY login_time DESC
              LIMIT ?";
    $stmt = $this->pdo->prepare($query);
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
private function countUserJobs($userId) {
    $query = "SELECT COUNT(*) FROM jobs WHERE user_id = ?";
    $stmt = $this->pdo->prepare($query);
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

private function countUserActiveJobs($userId) {
    $query = "SELECT COUNT(*) FROM jobs WHERE user_id = ? AND status = 'active'";
    $stmt = $this->pdo->prepare($query);
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

private function countUserTransactions($userId) {
    $query = "SELECT COUNT(*) FROM transactions WHERE user_id = ?";
    $stmt = $this->pdo->prepare($query);
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

private function countUserMessages($userId) {
    $query = "SELECT COUNT(*) FROM messages WHERE receiver_id = ? OR sender_id = ?";
    $stmt = $this->pdo->prepare($query);
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchColumn();
}
public function logLoginAttempt($userId, $ipAddress, $success = true, $userAgent = null) {

    $logUserId = (!empty($userId) && is_numeric($userId)) ? (int)$userId : null;

    $sql = "INSERT INTO user_login_history (user_id, ip_address, login_time, success, user_agent)
            VALUES (:user_id, :ip_address, NOW(), :success, :user_agent)";

    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':user_id',    $logUserId,   PDO::PARAM_INT);
    $stmt->bindParam(':ip_address', $ipAddress);
    $stmt->bindParam(':success',    $success,     PDO::PARAM_BOOL);
    $stmt->bindParam(':user_agent', $userAgent);

    return $stmt->execute();
}
public function updateNewsletterPreference($userId, $preference) {
    $stmt = $this->pdo->prepare("UPDATE users SET newsletter_subscription = :preference WHERE id = :user_id");
    return $stmt->execute([
        'preference' => $preference,
        'user_id' => $userId
    ]);
}
public function updateLastLogin($userId) {
    $sql = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    return $stmt->execute();
}
public function getUserIdByEmail($email) {
    $sql = "SELECT id FROM users WHERE email = :email";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}
public function addPointsWithTransaction($userId, $pointsToAdd, $description = '') {
    try {
        $this->pdo->beginTransaction();


        $sql = "UPDATE users SET account_balance = account_balance + :points_to_add, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':points_to_add', $pointsToAdd, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();


        $transactionSql = "INSERT INTO transactions (user_id, amount, type, description, status, created_at)
                          VALUES (:user_id, :amount, 'payment', :description, 'completed', NOW())";
        $transactionStmt = $this->pdo->prepare($transactionSql);
        $transactionStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $transactionStmt->bindParam(':amount', $pointsToAdd, PDO::PARAM_STR);
        $transactionStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $transactionStmt->execute();

        $this->pdo->commit();
        return true;

    } catch (Exception $e) {
        $this->pdo->rollBack();
        error_log(__t('model.user.add_points_error_log', ['error' => $e->getMessage()]));
        return false;
    }
}
}
?>
