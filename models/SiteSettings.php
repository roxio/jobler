<?php

include_once('Database.php');

class SiteSettings {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();  // Uzyskanie połączenia z bazą danych
    }

    // Pobieranie ustawień strony
    public function getSettings() {
        $query = $this->pdo->query("SELECT * FROM site_settings LIMIT 1");
        $result = $query->fetch(PDO::FETCH_ASSOC);

        // Dodajemy sprawdzenie, czy wynik jest prawidłowy
        if ($result === false) {
            return null;  // Zwracamy null lub pustą tablicę, w zależności od tego, jak chcesz obsługiwać ten przypadek
        }

        if (isset($result['categories'])) {
            $result['categories'] = explode(',', $result['categories']); // Konwersja na tablicę
        }
        return $result;
    }

    // Aktualizacja tytułu strony
    public function updateTitle($title) {
        $stmt = $this->pdo->prepare("UPDATE site_settings SET title = :title WHERE id = 1");
        $stmt->execute(['title' => $title]);
    }

    // Aktualizacja logo strony
    public function updateLogo($logo) {
        $stmt = $this->pdo->prepare("UPDATE site_settings SET logo = :logo WHERE id = 1");
        $stmt->execute(['logo' => $logo]);
    }

    // Aktualizacja kategorii
    public function updateCategories($categories) {
        // Zamiana wierszy na ciąg tekstowy oddzielony przecinkami
        $categoriesString = implode(',', array_map('trim', explode("\n", $categories)));
        $stmt = $this->pdo->prepare("UPDATE site_settings SET categories = :categories WHERE id = 1");
        $stmt->execute(['categories' => $categoriesString]);
    }

    // Pobranie liczby ustawień
    public function getSettingCount() {
        $sql = "SELECT COUNT(*) FROM site_settings";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchColumn();
    }

    // Aktualizacja wszystkich ustawień w jednym wywołaniu
    public function updateAllSettings($title, $logo, $categories) {
        $categoriesString = implode(',', array_map('trim', explode("\n", $categories)));

        $stmt = $this->pdo->prepare("UPDATE site_settings SET title = :title, logo = :logo, categories = :categories WHERE id = 1");
        return $stmt->execute([
            'title' => $title,
            'logo' => $logo,
            'categories' => $categoriesString
        ]);
    }

    // Pobieranie liczby odwiedzin strony
    public function getSiteViews() {
        $sql = "SELECT views FROM site_stats LIMIT 1"; // Zakładamy, że tabela site_stats przechowuje liczbę odwiedzin
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['views'] : 0; // Zwraca liczbę odwiedzin lub 0, jeśli brak danych
    }

    // Aktualizacja liczby odwiedzin strony
    public function updateSiteViews($views) {
        $sql = "UPDATE site_stats SET views = :views WHERE id = 1"; // Zakładamy, że tabela ma jedno stałe ID
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['views' => $views]);
    }
}
?>
