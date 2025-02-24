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
    try {
        $stmt = $this->pdo->prepare("UPDATE site_settings SET title = :title WHERE id = 1");
        $stmt->execute(['title' => htmlspecialchars(trim($title))]);
    } catch (PDOException $e) {
        error_log("Błąd aktualizacji tytułu: " . $e->getMessage());
        return false;
    }
    return true;
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
	
	// Errors? Jakie errors?
public function getSiteErrors() {
        $query = "SELECT COUNT(*) AS error_count FROM system_logs WHERE log_level = 'ERROR'";
        $stmt = $this->pdo->query($query); // Używamy query() do wykonania zapytania

        // Zwrócenie wyniku z użyciem fetch(PDO::FETCH_ASSOC)
        $row = $stmt->fetch(PDO::FETCH_ASSOC); // Poprawiona metoda
        return $row['error_count'];
    }	
	// Kategorie lecą
	public function getCategories() {
    $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY parent_id IS NULL DESC, parent_id, id");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $structuredCategories = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] === null) {
            $structuredCategories[$category['id']] = [
                'name' => $category['name'],
                'subcategories' => []
            ];
        } else {
            $structuredCategories[$category['parent_id']]['subcategories'][] = [
                'id' => $category['id'],
                'name' => $category['name']
            ];
        }
    }

    return $structuredCategories;
}
// Dodaj kategorię
public function addCategory($name, $parent_id = null) {
    $stmt = $this->pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (:name, :parent_id)");
    return $stmt->execute([
        'name' => htmlspecialchars(trim($name)),
        'parent_id' => $parent_id
    ]);
}
// Usuń kategorię
public function deleteCategory($id) {
    $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = :id");
    return $stmt->execute(['id' => $id]);
}



}
?>
