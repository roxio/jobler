<?php

class Database {
    private static $pdo;


    public static function getConnection() {

        if (self::$pdo == null) {
            try {

                self::$pdo = new PDO(
                    'mysql:host=localhost;dbname=jobler',
                    'root',
                    ''
                );

                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            } catch (PDOException $e) {

                die("Connection failed: " . $e->getMessage());
            }
        }

        return self::$pdo;
    }


    public static function closeConnection() {
        self::$pdo = null;
    }
}

?>