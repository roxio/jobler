<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['db_host'];
    $dbname = $_POST['db_name'];
    $user = $_POST['db_user'];
    $pass = $_POST['db_pass'];

    // Testowanie połączenia z bazą danych
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Nie udało się połączyć z bazą danych: " . $e->getMessage());
    }

    // Wgrywanie struktury bazy danych z db.sql
    $sql_file = __DIR__ . '/db.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            die("Błąd podczas importowania bazy danych: " . $e->getMessage());
        }
    } else {
        die("Plik db.sql nie istnieje!");
    }

    // Tworzenie pliku konfiguracyjnego db.php
    $config_content = "<?php
$host = '$host';
$dbname = '$dbname';
$user = '$user';
$pass = '$pass';

try {
    $pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8\", \$user, \$pass);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException \$e) {
    die(\"Error: \" . \$e->getMessage());
}
?>";
    file_put_contents(__DIR__ . '/includes/db.php', $config_content);

    echo "Instalacja zakończona pomyślnie! Możesz teraz usunąć plik install.php.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalator Systemu Zleceń</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Instalator Systemu Zleceń</h1>
    <form method="POST" action="install.php">
        <label for="db_host">Host bazy danych:</label>
        <input type="text" id="db_host" name="db_host" required value="localhost">

        <label for="db_name">Nazwa bazy danych:</label>
        <input type="text" id="db_name" name="db_name" required>

        <label for="db_user">Użytkownik bazy danych:</label>
        <input type="text" id="db_user" name="db_user" required>

        <label for="db_pass">Hasło bazy danych:</label>
        <input type="password" id="db_pass" name="db_pass">

        <button type="submit">Instaluj</button>
    </form>
</body>
</html>
