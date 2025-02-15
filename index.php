<?php
include 'includes/db.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Zleceń</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Witamy w systemie zleceń</h1>
    <?php if (isset($_SESSION['user_id'])): ?>
        <p>Zalogowany jako: <?= htmlspecialchars($_SESSION['username']) ?></p>
        <a href="logout.php">Wyloguj</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            | <a href="admin.php">Panel Admina</a>
        <?php endif; ?>
    <?php else: ?>
        <a href="login.php">Zaloguj</a> | <a href="register.php">Zarejestruj</a>
    <?php endif; ?>

    <h2>Filtruj zlecenia:</h2>
    <form method="GET" action="index.php">
        <label for="status">Status:</label>
        <select name="status" id="status">
            <option value="">Wszystkie</option>
            <option value="open">Otwarte</option>
            <option value="in_progress">W trakcie realizacji</option>
            <option value="completed">Zakończone</option>
        </select>

        <label for="min_budget">Budżet od:</label>
        <input type="number" name="min_budget" id="min_budget" step="0.01">

        <label for="max_budget">Budżet do:</label>
        <input type="number" name="max_budget" id="max_budget" step="0.01">

        <label for="keywords">Słowa kluczowe:</label>
        <input type="text" name="keywords" id="keywords">

        <button type="submit">Filtruj</button>
    </form>

    <h2>Aktualne zlecenia:</h2>
    <ul>
        <?php
        $status = $_GET['status'] ?? '';
        $min_budget = $_GET['min_budget'] ?? '';
        $max_budget = $_GET['max_budget'] ?? '';
        $keywords = $_GET['keywords'] ?? '';

        $query = "SELECT * FROM jobs WHERE 1=1";
        $params = [];

        if (!empty($status)) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }

        if (!empty($min_budget)) {
            $query .= " AND budget >= :min_budget";
            $params[':min_budget'] = $min_budget;
        }

        if (!empty($max_budget)) {
            $query .= " AND budget <= :max_budget";
            $params[':max_budget'] = $max_budget;
        }

        if (!empty($keywords)) {
            $query .= " AND (title LIKE :keywords OR description LIKE :keywords)";
            $params[':keywords'] = '%' . $keywords . '%';
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <li>
                <strong><?= htmlspecialchars($row['title']) ?></strong>
                <p><?= htmlspecialchars($row['description']) ?></p>
                <p>Budżet: <?= htmlspecialchars($row['budget']) ?> PLN</p>
            </li>
        <?php endwhile; ?>
    </ul>
</body>
</html>
