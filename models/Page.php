<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Language.php';

class Page {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function installOrUpdateSchema() {
        $this->ensureSchema();
        $this->seedDefaultPages();
    }

    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT *
            FROM pages
            ORDER BY sort_order ASC, title ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVisibleInMenu($locale = null) {
        return $this->getVisibleByPlacement('show_in_menu', $locale);
    }

    public function getVisibleInFooter($locale = null) {
        return $this->getVisibleByPlacement('show_in_footer', $locale);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM pages WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getBySlug($slug, $publishedOnly = true, $locale = null) {
        $sql = "SELECT * FROM pages WHERE slug = :slug";
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['slug' => $this->slugify($slug)]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        return $page ? $this->applyTranslation($page, $locale) : false;
    }

    public function save(array $data) {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $title = trim((string)($data['title'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));

        if ($title === '') {
            return ['error' => 'Podaj tytul podstrony.'];
        }

        $slug = $this->slugify($slug !== '' ? $slug : $title);
        if ($slug === '') {
            return ['error' => 'Nie udalo sie utworzyc adresu URL.'];
        }

        if ($this->slugExists($slug, $id)) {
            return ['error' => 'Podstrona o takim adresie URL juz istnieje.'];
        }

        $payload = [
            'title' => $title,
            'slug' => $slug,
            'content' => trim((string)($data['content'] ?? '')),
            'meta_title' => trim((string)($data['meta_title'] ?? '')),
            'meta_description' => trim((string)($data['meta_description'] ?? '')),
            'status' => ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
            'show_in_menu' => !empty($data['show_in_menu']) ? 1 : 0,
            'show_in_footer' => !empty($data['show_in_footer']) ? 1 : 0,
            'sort_order' => (int)($data['sort_order'] ?? 100),
        ];

        if ($id > 0) {
            $payload['id'] = $id;
            $stmt = $this->pdo->prepare("
                UPDATE pages
                SET title = :title,
                    slug = :slug,
                    content = :content,
                    meta_title = :meta_title,
                    meta_description = :meta_description,
                    status = :status,
                    show_in_menu = :show_in_menu,
                    show_in_footer = :show_in_footer,
                    sort_order = :sort_order,
                    updated_at = NOW()
                WHERE id = :id
            ");
            try {
                $stmt->execute($payload);
            } catch (Throwable $e) {
                $this->installOrUpdateSchema();
                $stmt->execute($payload);
            }
            $this->saveTranslations($id, $data['translations'] ?? []);
            return ['success' => true, 'id' => $id, 'slug' => $slug];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO pages
                (title, slug, content, meta_title, meta_description, status, show_in_menu, show_in_footer, sort_order, created_at, updated_at)
            VALUES
                (:title, :slug, :content, :meta_title, :meta_description, :status, :show_in_menu, :show_in_footer, :sort_order, NOW(), NOW())
        ");
        try {
            $stmt->execute($payload);
        } catch (Throwable $e) {
            $this->installOrUpdateSchema();
            $stmt->execute($payload);
        }

        $newId = (int)$this->pdo->lastInsertId();
        $this->saveTranslations($newId, $data['translations'] ?? []);

        return ['success' => true, 'id' => $newId, 'slug' => $slug];
    }

    public function delete($id) {
        $this->pdo->prepare("DELETE FROM page_translations WHERE page_id = :id")->execute(['id' => (int)$id]);
        $stmt = $this->pdo->prepare("DELETE FROM pages WHERE id = :id");
        return $stmt->execute(['id' => (int)$id]);
    }

    public function publicUrl(array $page, $locale = null) {
        $url = '/page.php?slug=' . rawurlencode($page['slug']);
        if ($locale) {
            $url .= '&lang=' . rawurlencode(Language::normalize($locale));
        }
        return $url;
    }

    public function getTranslations($pageId) {
        $stmt = $this->pdo->prepare("SELECT * FROM page_translations WHERE page_id = :page_id");
        $stmt->execute(['page_id' => (int)$pageId]);
        $translations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $translation) {
            $translations[$translation['locale']] = $translation;
        }
        return $translations;
    }

    private function getVisibleByPlacement($column, $locale = null) {
        $allowed = ['show_in_menu', 'show_in_footer'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }

        $stmt = $this->pdo->query("
            SELECT *
            FROM pages
            WHERE status = 'published' AND {$column} = 1
            ORDER BY sort_order ASC, title ASC
        ");
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pages as $index => $page) {
            $pages[$index] = $this->applyTranslation($page, $locale);
        }
        return $pages;
    }

    private function slugExists($slug, $excludeId = 0) {
        $stmt = $this->pdo->prepare("SELECT id FROM pages WHERE slug = :slug AND id <> :id LIMIT 1");
        $stmt->execute(['slug' => $slug, 'id' => (int)$excludeId]);
        return (bool)$stmt->fetchColumn();
    }

    private function ensureSchema() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content MEDIUMTEXT DEFAULT NULL,
                slug VARCHAR(255) NOT NULL,
                meta_title VARCHAR(255) DEFAULT NULL,
                meta_description TEXT DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                show_in_menu TINYINT(1) NOT NULL DEFAULT 0,
                show_in_footer TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 100,
                created_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY uniq_pages_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS page_translations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT NOT NULL,
                locale VARCHAR(10) NOT NULL,
                title VARCHAR(255) DEFAULT NULL,
                content MEDIUMTEXT DEFAULT NULL,
                meta_title VARCHAR(255) DEFAULT NULL,
                meta_description TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY uniq_page_locale (page_id, locale),
                KEY idx_page_translations_locale (locale)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->addColumnIfMissing('meta_title', "ALTER TABLE pages ADD COLUMN meta_title VARCHAR(255) DEFAULT NULL");
        $this->addColumnIfMissing('meta_description', "ALTER TABLE pages ADD COLUMN meta_description TEXT DEFAULT NULL");
        $this->addColumnIfMissing('status', "ALTER TABLE pages ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft'");
        $this->addColumnIfMissing('show_in_menu', "ALTER TABLE pages ADD COLUMN show_in_menu TINYINT(1) NOT NULL DEFAULT 0");
        $this->addColumnIfMissing('show_in_footer', "ALTER TABLE pages ADD COLUMN show_in_footer TINYINT(1) NOT NULL DEFAULT 0");
        $this->addColumnIfMissing('sort_order', "ALTER TABLE pages ADD COLUMN sort_order INT NOT NULL DEFAULT 100");

        try {
            $this->pdo->exec("ALTER TABLE pages MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        } catch (Throwable $e) {
            error_log('Pages id migration warning: ' . $e->getMessage());
        }

        try {
            $indexes = $this->pdo->query("SHOW INDEX FROM pages WHERE Key_name = 'uniq_pages_slug'")->fetchAll(PDO::FETCH_ASSOC);
            if (!$indexes) {
                $this->pdo->exec("ALTER TABLE pages ADD UNIQUE KEY uniq_pages_slug (slug)");
            }
        } catch (Throwable $e) {
            error_log('Pages slug index warning: ' . $e->getMessage());
        }
    }

    private function addColumnIfMissing($column, $sql) {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM pages LIKE :column_name");
        $stmt->execute(['column_name' => $column]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec($sql);
        }
    }

    private function seedDefaultPages() {
        $defaults = [
            ['title' => __t('model.pages.privacy_policy'), 'slug' => 'polityka-prywatnosci', 'show_in_footer' => 1, 'sort_order' => 10],
            ['title' => __t('model.pages.terms'), 'slug' => 'regulamin', 'show_in_footer' => 1, 'sort_order' => 20],
            ['title' => __t('model.pages.contact'), 'slug' => 'kontakt', 'show_in_footer' => 1, 'sort_order' => 30],
        ];

        foreach ($defaults as $page) {
            if ($this->getBySlug($page['slug'], false)) {
                continue;
            }

            $this->save([
                'title' => $page['title'],
                'slug' => $page['slug'],
                'content' => __t('model.pages.default_content'),
                'status' => 'published',
                'show_in_footer' => $page['show_in_footer'],
                'show_in_menu' => 0,
                'sort_order' => $page['sort_order'],
            ]);
        }
    }

    private function saveTranslations($pageId, array $translations) {
        if (empty($translations)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO page_translations
                (page_id, locale, title, content, meta_title, meta_description, created_at, updated_at)
            VALUES
                (:page_id, :locale, :title, :content, :meta_title, :meta_description, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                content = VALUES(content),
                meta_title = VALUES(meta_title),
                meta_description = VALUES(meta_description),
                updated_at = NOW()
        ");

        foreach ($translations as $locale => $translation) {
            $locale = Language::normalize($locale);
            $stmt->execute([
                'page_id' => (int)$pageId,
                'locale' => $locale,
                'title' => trim((string)($translation['title'] ?? '')),
                'content' => trim((string)($translation['content'] ?? '')),
                'meta_title' => trim((string)($translation['meta_title'] ?? '')),
                'meta_description' => trim((string)($translation['meta_description'] ?? '')),
            ]);
        }
    }

    private function applyTranslation(array $page, $locale = null) {
        $locale = Language::normalize($locale ?: Language::current());
        $stmt = $this->pdo->prepare("SELECT * FROM page_translations WHERE page_id = :page_id AND locale = :locale LIMIT 1");
        $stmt->execute(['page_id' => (int)$page['id'], 'locale' => $locale]);
        $translation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$translation) {
            return $page;
        }

        foreach (['title', 'content', 'meta_title', 'meta_description'] as $field) {
            if (isset($translation[$field]) && trim((string)$translation[$field]) !== '') {
                $page[$field] = $translation[$field];
            }
        }

        $page['active_locale'] = $locale;
        return $page;
    }

    private function slugify($value) {
        $value = trim((string)$value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return substr($value, 0, 255);
    }
}
?>
