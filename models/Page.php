<?php
class Page {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    // Pobranie strony po slugu
    public function getPageBySlug($slug) {
        $query = "SELECT * FROM pages WHERE slug = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Dodanie nowej strony
    public function addPage($title, $content, $slug) {
        $query = "INSERT INTO pages (title, content, slug, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sss", $title, $content, $slug);
        return $stmt->execute();
    }

    // Edycja strony
    public function updatePage($id, $title, $content) {
        $query = "UPDATE pages SET title = ?, content = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ssi", $title, $content, $id);
        return $stmt->execute();
    }
}

?>