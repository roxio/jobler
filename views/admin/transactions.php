<?php
session_start();

require_once __DIR__ . '/_auth.php';
requireAdminAccess();

include_once('../../config/config.php');
include_once('../../models/User.php');
include_once('../../models/Job.php');
include_once('../../models/TransactionHistory.php');
include_once('../../models/Message.php');
include_once('../../models/Language.php');


require_once __DIR__ . '/_auth.php';
requireAdminAccess();


$transactionModel = new TransactionHistory($pdo);
$userModel = new User();


$userIdFilter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';


$limit = isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 25, 50, 100]) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;


$allowedSortColumns = ['id', 'user_id', 'amount', 'type', 'status', 'created_at'];
$sortColumn = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';


$transactions = $transactionModel->getTransactionsWithFilters($limit, $offset, $sortColumn, $sortOrder, $userIdFilter);
$totalTransactions = $transactionModel->countTransactionsWithFilters($userIdFilter);
$totalPages = ceil($totalTransactions / $limit);


$userDetails = null;
if (!empty($userIdFilter)) {
    $userDetails = $userModel->getUserById($userIdFilter);
}

function safeEcho($data, $default = '') {
    return isset($data) ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8') : $default;
}

function buildUrl($params = []) {
    $currentParams = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }
    return '?' . http_build_query($currentParams);
}
?>

<?php include '../partials/header.php'; ?>

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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <a href="view_user.php?id=<?= $userIdFilter ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(__t('admin.transactions.back_to_user')) ?>
                            </a>
                            <?php if (!empty($userIdFilter) && $userDetails): ?>
                                <span class="ms-2"><?= htmlspecialchars(__t('admin.transactions.user_transactions', ['name' => $userDetails['name'], 'id' => $userIdFilter])) ?></span>
                            <?php else: ?>
                                <span class="ms-2"><?= htmlspecialchars(__t('admin.transactions.all_transactions')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($userIdFilter)): ?>
                    <div class="row mb-4">
                        <?php
                        $stats = $transactionModel->getUserTransactionStats($userIdFilter);
                        ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($stats['total_count']) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.transactions.total')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($stats['income_total'], 2) ?> PLN</h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.transactions.income')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($stats['expense_total'], 2) ?> PLN</h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.transactions.expense')) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center p-2">
                                    <h5 class="mb-0"><?= number_format($stats['completed_count']) ?></h5>
                                    <p class="mb-0 small"><?= htmlspecialchars(__t('admin.transactions.completed')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><i class="bi bi-currency-exchange"></i> <?= htmlspecialchars(__t('admin.transactions.title')) ?></h5>
                            <div class="d-flex align-items-center">
                                <a href="<?= buildUrl(['user_id' => null, 'page' => 1]) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> <?= htmlspecialchars(__t('admin.common.clear')) ?>
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th><?= htmlspecialchars(__t('admin.user')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.transactions.amount')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.transactions.type')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.common.status')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.transactions.description')) ?></th>
                                            <th><?= htmlspecialchars(__t('admin.date')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($transactions)): ?>
                                            <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary">#<?= safeEcho($transaction['id']); ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="view_user.php?id=<?= safeEcho($transaction['user_id']); ?>" class="text-decoration-none">
                                                            <?= safeEcho($transaction['user_name'] ?? __t('admin.transactions.user_fallback', ['id' => $transaction['user_id']])) ?>
                                                        </a>
                                                    </td>
                                                    <td class="fw-bold <?= $transaction['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                                                        <?= number_format($transaction['amount'], 2) ?> PLN
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $transaction['type'] == 'income' ? 'success' : 'danger'; ?>">
                                                            <?= htmlspecialchars($transaction['type'] == 'income' ? __t('admin.transactions.income_single') : __t('admin.transactions.expense_single')) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $transaction['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                            <?= htmlspecialchars($transaction['status'] == 'completed' ? __t('admin.transactions.completed_single') : __t('admin.transactions.pending_single')) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= safeEcho(mb_substr($transaction['description'], 0, 50)) ?>
                                                        <?= mb_strlen($transaction['description']) > 50 ? '...' : '' ?>
                                                    </td>
                                                    <td>
                                                        <small><?= date('Y-m-d', strtotime($transaction['created_at'])) ?></small>
                                                        <br><small class="text-muted"><?= date('H:i', strtotime($transaction['created_at'])) ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
                                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                                    <p class="mt-3"><?= htmlspecialchars(__t('admin.transactions.no_transactions')) ?></p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    <span class="text-muted">
                                        <?= htmlspecialchars(__t('admin.transactions.displayed', ['shown' => count($transactions), 'total' => number_format($totalTransactions)])) ?>
                                    </span>
                                </div>
                                <div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination pagination-sm">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => 1]) ?>" aria-label="<?= htmlspecialchars(__t('admin.common.first')) ?>">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => $page - 1]) ?>" aria-label="<?= htmlspecialchars(__t('admin.common.previous')) ?>">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>

                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $page + 2);
                                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                <li class="page-item <?= $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="<?= buildUrl(['page' => $i]) ?>"><?= $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => $page + 1]) ?>" aria-label="<?= htmlspecialchars(__t('admin.common.next')) ?>">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?= buildUrl(['page' => $totalPages]) ?>" aria-label="<?= htmlspecialchars(__t('admin.common.last')) ?>">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
