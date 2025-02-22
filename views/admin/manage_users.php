<?php
include_once('../../models/User.php');

// Utwórz instancję klasy User
$userModel = new User();

// Pobierz wszystkich użytkowników
$users = $userModel->getAllUsers();
?>

<?php include '../partials/header.php'; ?>


<div class="container-fluid">
    <div class="row">
           <!-- Menu boczne -->
        <?php include 'sidebar.php'; ?>

        <!-- Główna zawartość -->
        <div class="col-md-10 col-lg-10 main-content">
    <h1>Zarządzaj użytkownikami</h1>

    <!-- Wyświetlanie komunikatów o sukcesie lub błędzie -->
    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] == 'deleted'): ?>
            <div class="alert alert-success">Wybrani użytkownicy zostali pomyślnie usunięci.</div>
        <?php elseif ($_GET['status'] == 'error'): ?>
            <div class="alert alert-danger">Nie zaznaczono żadnych użytkowników do usunięcia.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Formularz do usuwania wybranych użytkowników -->
    <form method="POST" action="../admin/delete_users.php">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Usuń</th>
                    <th>ID</th>
                    <th>Imię</th>
                    <th>Email</th>
                    <th>Rola</th>
                    <th>Data utworzenia</th>
                    <th>Adres IP (Rejestracja)</th>
                    <th>Adres IP (Ostatnie logowanie)</th>
                    <th>Status konta</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>">
                        </td>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($user['registration_ip']); ?></td>
                        <td><?php echo htmlspecialchars($user['last_login_ip']); ?></td>
                        <td>
                            <!-- Status konta -->
                            <?php if ($user['status'] == 'active'): ?>
                                <span class="badge badge-success">Aktywne</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Nieaktywne</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="../admin/edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">Edytuj</a>
                            <a href="../admin/delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('Na pewno chcesz usunąć tego użytkownika?')">Usuń</a>
                            <!-- Przycisk do dezaktywacji konta -->
                            <?php if ($user['status'] == 'active'): ?>
                                <a href="../admin/deactivate_user.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary" onclick="return confirm('Na pewno chcesz dezaktywować to konto?')">Dezaktywuj</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="btn btn-danger">Usuń wybranych użytkowników</button>
    </form>

    <!-- Jeśli brak użytkowników, wyświetlamy odpowiednią wiadomość -->
    <?php if (empty($users)) : ?>
        <p>Brak użytkowników w systemie.</p>
    <?php endif; ?>
</div>
</div></div>
<?php include '../partials/footer.php'; ?>
