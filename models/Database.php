<?php

class Database {
    // Static variable to hold the PDO connection instance
    private static $pdo;

    // Method to obtain a PDO connection. It creates a new connection if one doesn't exist.
    public static function getConnection() {
        if (self::$pdo == null) {
            try {
                // Create a new PDO connection with the specified DSN, username, and password.
                self::$pdo = new PDO(
                    'mysql:host=localhost;dbname=jobler', // Change this to your database host and name
                    'root', // Change this to your database username
                    ''      // Change this to your database password
                );
                // Set error mode to throw exceptions
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // Set default fetch mode to associative array
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Terminate the script and display an error message if the connection fails
                die("Connection failed: " . $e->getMessage());
            }
        }

        // Return the PDO connection instance
        return self::$pdo;
    }

    // Method to close the PDO connection (optional but useful)
    public static function closeConnection() {
        self::$pdo = null;
    }
}

?>
