<?php
include_once('../../models/User.php');

// Utwórz instancję klasy User
$userModel = new User();

// Pobierz wszystkich użytkowników
$users = $userModel->getAllUsers(); // Zakładam, że masz metodę getAllUsers() w modelu User
?>

<?php include '../partials/header.php'; ?>

<div class="container">
    <h1>Zarządzaj użytkownikami</h1>

    <?php if (!empty($users)) : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Imię</th>
                    <th>Email</th>
                    <th>Rola</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td>
                            <a href="/admin/edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">Edytuj</a>
                            <a href="/admin/delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('Na pewno chcesz usunąć tego użytkownika?')">Usuń</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>Brak użytkowników w systemie.</p>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>
