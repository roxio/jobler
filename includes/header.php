<?php

?>

<header>
    <nav>
        <ul>
            <li><a href="../index.php">Strona główna</a></li>
            <li><a href="dashboard.php">Panel administracyjny</a></li>
            <li><a href="users.php">Użytkownicy</a></li>
            <li><a href="ads.php">Ogłoszenia</a></li>
			 <li><a href="categories.php">Kategorie</a></li>
            <li><a href="logout.php">Wyloguj</a></li>
			<li></li>
			<li>
			<!-- Sprawdzanie sesji -->
<?php if (is_logged_in()): ?>
        <span>Witaj, <?= $_SESSION['username']; ?> (<?= get_user_role($_SESSION['user_id']); ?>)</span>
<?php else: ?>
    <p>Nie jesteś zalogowany.</p>
<?php endif; ?>
</li>
        </ul>
    </nav>
</header>

