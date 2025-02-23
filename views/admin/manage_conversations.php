<?php
session_start();
include_once('../../models/Message.php');

$messageModel = new Message();

// Paginacja i sortowanie
$limit = $_GET['per_page'] ?? 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;
$sortColumn = $_GET['sort'] ?? 'conversation_id';
$sortOrder = $_GET['order'] ?? 'DESC';

// Pobranie konwersacji
$totalConversations = $messageModel->countConversations();
$conversations = $messageModel->getAllConversations($limit, $offset, $sortColumn, $sortOrder);
$totalPages = ceil($totalConversations / $limit);
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools"></i> Admin Panel</h5>
					<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <nav class="nav">
					<?php include 'sidebar.php'; ?>
                    </nav>
					<?php endif; ?>
                </div>

                <div class="card-body">


                <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-1"><i class="bi bi-chat-left-quote"></i> Konwersacje</h5>
    <nav class="nav">
                    <!-- Formularz sortowania i paginacji -->
                    <form method="GET" class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex">
                            <select name="per_page" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                                <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?= $limit == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                            </select>

                            <select name="sort" class="form-select form-select-sm me-2">
                                <option value="job_id" <?= $sortColumn == 'job_id' ? 'selected' : ''; ?>>Job ID</option>
                                <option value="conversation_id" <?= $sortColumn == 'conversation_id' ? 'selected' : ''; ?>>Conversation ID</option>
                            </select>

                            <select name="order" class="form-select form-select-sm me-2">
                                <option value="ASC" <?= $sortOrder == 'ASC' ? 'selected' : ''; ?>>Rosnąco</option>
                                <option value="DESC" <?= $sortOrder == 'DESC' ? 'selected' : ''; ?>>Malejąco</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">🔍</button>
                    </form>
 </nav>
</div>
                <div class="card-body">
                    <!-- Tabela konwersacji -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Job ID</th>
                                    <th>Conversation ID</th>
                                    <th>Podgląd</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversations as $conv) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($conv['job_id']); ?></td>
                                        <td><?php echo htmlspecialchars($conv['conversation_id']); ?></td>
                                        <td>
                                            <a href="?conversation_id=<?php echo $conv['conversation_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye"></i> Podgląd
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginacja -->
                       <div class="d-flex justify-content-between align-items-center">
                                    <button type="submit" class="btn btn-danger btn-sm">Usuń zaznaczone</button>
                                    <div>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination">
                                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?= max(1, $page - 1); ?>&per_page=<?= $limit ?>&search=<?= htmlspecialchars($search) ?>&sort=<?= htmlspecialchars($sortColumn) ?>&order=<?= htmlspecialchars($sortOrder) ?>">Poprzednia</a>
                                                </li>
                                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                    <li class="page-item <?= $i == $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?page=<?= $i; ?>&per_page=<?= $limit ?>&search=<?= htmlspecialchars($search) ?>&sort=<?= htmlspecialchars($sortColumn) ?>&order=<?= htmlspecialchars($sortOrder) ?>"><?= $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?= min($totalPages, $page + 1); ?>&per_page=<?= $limit ?>&search=<?= htmlspecialchars($search) ?>&sort=<?= htmlspecialchars($sortColumn) ?>&order=<?= htmlspecialchars($sortOrder) ?>">Następna</a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </form>
                        </div>
</div></div>

				
        <div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
        </div>
  
            </div>	
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>

            <!-- Podgląd wiadomości -->
            <?php if (isset($_GET['conversation_id'])): 
                $conversation_id = $_GET['conversation_id'];
                $messages = $messageModel->getConversationById($conversation_id);
            ?>
                <div class="card shadow mt-4">
                    <div class="card-header">
                        <h5><i class="bi bi-envelope"></i> Wiadomości w konwersacji #<?php echo htmlspecialchars($conversation_id); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg) : ?>
                            <div class="message-box p-3 border rounded mb-2">
                                <p>
                                    <strong>Od: <?php echo htmlspecialchars($msg['sender_id']); ?> → <?php echo htmlspecialchars($msg['receiver_id']); ?></strong>
                                </p>
                                <p><?php echo htmlspecialchars($msg['content']); ?></p>
                                <small class="text-muted"><i class="bi bi-clock"></i> <?php echo htmlspecialchars($msg['created_at']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>




    