<?php

class Database {
    private static $pdo;  // Statyczne połączenie z bazą danych

    // Metoda do uzyskiwania połączenia z bazą danych
    public static function getConnection() {
        // Sprawdzenie, czy połączenie już istnieje
        if (self::$pdo == null) {
            try {
                // Tworzenie połączenia z bazą danych
                self::$pdo = new PDO(
                    'mysql:host=localhost;dbname=jobler',  // Zmień na swoje dane bazy
                    'root',  // Zmień na swoją nazwę użytkownika
                    ''   // Zmień na swoje hasło
                );
                // Ustawienie trybu błędów
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Domyślny tryb pobierania danych

            } catch (PDOException $e) {
                // Zatrzymanie skryptu i wyświetlenie błędu w przypadku nieudanego połączenia
                die("Connection failed: " . $e->getMessage());
            }
        }

        return self::$pdo;  // Zwrócenie instancji połączenia
    }

    // Zamykanie połączenia (opcjonalne, ale warto mieć)
    public static function closeConnection() {
        self::$pdo = null;
    }
}

?>