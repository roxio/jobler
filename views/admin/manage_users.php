<?php
session_start();
include_once('../../models/User.php');

// Utwórz instancję klasy User
$userModel = new User();

// Parametry paginacji, sortowania i wyszukiwania
$limit = $_GET['per_page'] ?? 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;
$sortColumn = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$search = $_GET['search'] ?? '';

// Pobierz dane użytkowników i liczbę wszystkich użytkowników
$total_users = $userModel->getTotalUsers($search);
$users = $userModel->getPaginatedUsers($limit, $offset, $sortColumn, $sortOrder, $search);
$totalPages = ceil($total_users / $limit);
?>
<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 col-lg-12 main-content">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">🛠️ Admin Panel</h5>
					<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <nav class="nav">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt fa-lg"></i> Dashboard
                        </a>
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users fa-lg"></i> Użytkownicy
                        </a>
                        <a class="nav-link" href="manage_jobs.php">
                            <i class="fas fa-briefcase fa-lg"></i> Ogłoszenia
                        </a>
                        <a class="nav-link" href="manage_conversations.php">
                            <i class="fas fa-comments fa-lg"></i> Konwersacje
                        </a>
                        <a class="nav-link" href="site_settings.php">
                            <i class="fas fa-cogs fa-lg"></i> Ustawienia
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-line fa-lg"></i> Raporty
                        </a>
                    </nav>
					<?php endif; ?>
                </div>

                <div class="card-body">
                    <!-- Status wiadomości -->
                    <?php if (isset($_GET['status'])): ?>
                        <div class="alert 
                            <?php echo $_GET['status'] == 'error' || $_GET['status'] == 'error_points' ? 'alert-danger' : 'alert-success'; ?> 
                            alert-dismissible fade show" role="alert">
                            <?php 
                                $messages = [
                                    'deleted' => '✅ Wybrani użytkownicy zostali pomyślnie usunięci.',
                                    'activated' => '✅ Konto użytkownika zostało aktywowane.',
                                    'error' => '❌ Wystąpił błąd. Spróbuj ponownie.',
                                    'points_added' => '✅ Punkty zostały dodane.',
                                    'error_points' => '❌ Wystąpił błąd podczas dodawania punktów.'
                                ];
                                echo $messages[$_GET['status']] ?? '';
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-1">👥 Zarządzaj użytkownikami</h5>
    <nav class="nav">
        <form method="GET" class="d-flex">
		<div class="col-auto me-2 ">
            <input type="text" name="search" class="form-control form-control-sm me-2 style="width: 200px;" placeholder="Szukaj..." value="<?= htmlspecialchars($search); ?>">
			</div>
            <?php $perPage = $_GET['per_page'] ?? 10; ?>
            <select name="per_page" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                <option value="10" <?= $perPage == 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?= $perPage == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?= $perPage == 50 ? 'selected' : ''; ?>>50</option>
            </select>

            <select name="sort" class="form-select form-select-sm me-2">
                <option value="id" <?= $sortColumn == 'id' ? 'selected' : ''; ?>>ID</option>
                <option value="created_at" <?= $sortColumn == 'created_at' ? 'selected' : ''; ?>>Data</option>
                <option value="account_balance" <?= $sortColumn == 'account_balance' ? 'selected' : ''; ?>>Punkty</option>
                <option value="registration_ip" <?= $sortColumn == 'registration_ip' ? 'selected' : ''; ?>>Adres IP</option>
            </select>

            <select name="order" class="form-select form-select-sm me-2">
                <option value="ASC" <?= $sortOrder == 'ASC' ? 'selected' : ''; ?>>Rosnąco</option>
                <option value="DESC" <?= $sortOrder == 'DESC' ? 'selected' : ''; ?>>Malejąco</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">🔍</button>
        </form>
    </nav>
</div>
                <div class="card-body">
                    <form method="POST" action="../admin/delete_users.php">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                   <th style="width: 1%"><input type="checkbox" id="select-all"></th>
									<th style="width: 1%">ID</th>
									<th style="width: 10%">Imię</th>
									<th style="width: 20%">Email</th>
									<th style="width: 5%">Rola</th>
									<th style="width: 5%">Utworzone</th>
									<th style="width: 10%">First IP</th>
									<th style="width: 10%">Last IP</th>
									<th style="width: 5%">Status</th>
									<th style="width: 8%">Saldo</th>
									<th style="width: 20%">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user) : ?>
								
                                    <tr class="<?= (!empty($user['need_change']) && $user['need_change'] == 1) ? 'table-info' : ''; ?>">
    <td><input type="checkbox" name="user_ids[]" value="<?= $user['id']; ?>"></td>
    <td><?php echo htmlspecialchars($user['id']); ?></td>
    <td><?php echo htmlspecialchars($user['name']); ?></td>
    <td><?php echo htmlspecialchars($user['email']); ?></td>
   <td>
    <div class="d-flex align-items-center">
        <span class="badge <?= $user['role'] == 'admin' ? 'bg-danger' : 'bg-primary'; ?> me-2">
            <?= ucfirst($user['role']); ?>
        </span>
        <?php if (!empty($user['need_change']) && $user['need_change'] == 1): ?>
            <form action="change_role.php" method="POST" class="d-inline">
                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                <input type="hidden" name="current_role" value="<?php echo $user['role']; ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-arrow-repeat"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</td>
    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
    <td><?php echo htmlspecialchars($user['registration_ip']); ?></td>
    <td><?php echo htmlspecialchars($user['last_login_ip']); ?></td>
    <td>
        <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
            <?= $user['status'] == 'active' ? 'Aktywne' : 'Nieaktywne'; ?>
        </span>
    </td>
    <td><?= isset($user['account_balance']) ? $user['account_balance'] . ' pkt' : 'Brak'; ?></td>
    <td>
        <div class="btn-group" role="group">
            <form action="add_points.php" method="POST" id="addPointsForm-<?= $user['id']; ?>" class="input-group">
                <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                <input type="number" name="points_to_add" min="1" class="form-control form-control-sm" placeholder="Punkty" required>
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-database-add"></i>
                </button>
            </form>
            <a href="../admin/edit_user.php?id=<?= $user['id']; ?>" class="btn btn-warning btn-sm">
                <i class="bi bi-pencil"></i>
            </a>
            <a href="../admin/delete_user.php?id=<?= $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Na pewno chcesz usunąć?');">
                <i class="bi bi-trash"></i>
            </a>
            <?php if ($user['status'] == 'active'): ?>
                <a href="../admin/deactivate_user.php?id=<?= $user['id']; ?>" class="btn btn-secondary btn-sm">
                    <i class="bi bi-person-fill-slash"></i>
                </a>
            <?php else: ?>
                <a href="../admin/activate_user.php?id=<?= $user['id']; ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-person-fill-check"></i>
                </a>
            <?php endif; ?>
        </div>
    </td>
</tr>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
                    </div>
                </div>
				
        <div class="container">
            <span class="text-muted">&copy; 2025 System Zleceń - Wszelkie prawa zastrzeżone.</span>
        </div>
  
            </div>	
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>

<script>
document.getElementById('select-all').addEventListener('change', function() {
    document.querySelectorAll('input[name="user_ids[]"]').forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});
</script>
