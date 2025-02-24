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

$search = $_GET['search'] ?? '';

// Pobranie konwersacji z uwzględnieniem wyszukiwania
$totalConversations = $messageModel->countConversations($search);
$conversations = $messageModel->getAllConversations($limit, $offset, $sortColumn, $sortOrder, $search);

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
				<!--obsługa błędów-->
                <div class="card-body">
<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type']; ?>" role="alert">
        <?= $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
<?php endif; ?>

                <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-1"><i class="bi bi-chat-left-quote"></i> Konwersacje</h5>
    <nav class="nav">
                    <!-- Formularz sortowania i paginacji -->
                    <form method="GET" class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex">
        <!-- Pole wyszukiwania -->
        <input type="text" name="search" class="form-control form-control-sm me-2" 
               placeholder="Szukaj..." value="<?= htmlspecialchars($search ?? ''); ?>" 
               onchange="this.form.submit()">

        <!-- Wybór liczby elementów na stronę -->
        <select name="per_page" class="form-select form-select-sm me-1" onchange="this.form.submit()">
            <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
            <option value="25" <?= $limit == 25 ? 'selected' : ''; ?>>25</option>
            <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
        </select>

        <!-- Wybór kolumny do sortowania -->
        <select name="sort" class="form-select form-select-sm me-1">
            <option value="job_id" <?= $sortColumn == 'job_id' ? 'selected' : ''; ?>>Job ID</option>
            <option value="conversation_id" <?= $sortColumn == 'conversation_id' ? 'selected' : ''; ?>>Conversation ID</option>
        </select>

        <!-- Wybór porządku sortowania -->
        <select name="order" class="form-select form-select-sm me-1">
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
<form method="POST" action="delete_conversations.php">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
    <tr>
        <th style="width: 1%"><input type="checkbox" id="select-all"></th>
        <th style="width: 5%">Job</th>
        <th style="width: 5%">ID</th>
        <th style="width: 40%">Temat</th> 
        <th style="width: 40%">Najnowsza wiadomość</th>
        <th style="width: 10%">Podgląd</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($conversations as $conv) : ?>
        <tr>
            <td><input type="checkbox" name="conversation_ids[]" value="<?= $conv['conversation_id']; ?>"></td>
            <td><?php echo htmlspecialchars($conv['job_id']); ?></td>
            <td><?php echo htmlspecialchars($conv['conversation_id']); ?></td>
            <td><?php echo htmlspecialchars($conv['title']); ?></td>
            <td><?php echo htmlspecialchars($conv['latest_message']); ?></td> <!-- Wyświetlenie najnowszej wiadomości -->
            <td>
                <button type="button" class="btn btn-primary btn-sm view-conversation" 
                        data-bs-toggle="modal" 
                        data-bs-target="#conversationModal" 
                        data-conversation-id="<?= htmlspecialchars($conv['conversation_id']); ?>">
                    <i class="bi bi-eye"></i> Podgląd
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>

        </table>
    </div>



    <!-- Przycisk usuwania -->
    <div class="d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-danger btn-sm">Usuń zaznaczone</button>
</form>


                    <!-- Paginacja -->
                   
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
</div></div></div>

				
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
			
			<div class="modal fade" id="conversationModal" tabindex="-1" aria-labelledby="conversationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="conversationModalLabel">Podgląd Konwersacji</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="conversationContent">
                    <p class="text-muted">Ładowanie wiadomości...</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".view-conversation").forEach(button => {
        button.addEventListener("click", function () {
            let conversationId = this.getAttribute("data-conversation-id");
            let modalContent = document.getElementById("conversationContent");

            // Pokazanie komunikatu o ładowaniu
            modalContent.innerHTML = '<p class="text-muted">Ładowanie wiadomości...</p>';

            // Wysyłanie żądania AJAX do pobrania wiadomości
            fetch("load_conversation.php?conversation_id=" + conversationId)
                .then(response => response.text())
                .then(data => {
                    modalContent.innerHTML = data;
                })
                .catch(error => {
                    modalContent.innerHTML = '<p class="text-danger">Błąd podczas ładowania wiadomości.</p>';
                });
        });
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Zaznaczanie/odznaczanie wszystkich checkboxów
    document.getElementById("select-all").addEventListener("change", function () {
        const checkboxes = document.querySelectorAll('input[name="conversation_ids[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
});
</script>




    