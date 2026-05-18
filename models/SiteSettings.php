<?php
include_once('Database.php');

class SiteSettings {
    private $pdo;
    private $settingsColumnsEnsured = false;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    private function ensureSettingsColumns() {
        if ($this->settingsColumnsEnsured) {
            return;
        }

        $columns = [
            'facebook_url' => "ALTER TABLE site_settings ADD COLUMN facebook_url VARCHAR(255) DEFAULT NULL",
            'twitter_url' => "ALTER TABLE site_settings ADD COLUMN twitter_url VARCHAR(255) DEFAULT NULL",
            'instagram_url' => "ALTER TABLE site_settings ADD COLUMN instagram_url VARCHAR(255) DEFAULT NULL",
            'linkedin_url' => "ALTER TABLE site_settings ADD COLUMN linkedin_url VARCHAR(255) DEFAULT NULL",
            'contact_email' => "ALTER TABLE site_settings ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL",
            'contact_phone' => "ALTER TABLE site_settings ADD COLUMN contact_phone VARCHAR(50) DEFAULT NULL",
            'contact_address' => "ALTER TABLE site_settings ADD COLUMN contact_address TEXT DEFAULT NULL",
            'business_hours' => "ALTER TABLE site_settings ADD COLUMN business_hours VARCHAR(255) DEFAULT NULL",
            'smtp_server' => "ALTER TABLE site_settings ADD COLUMN smtp_server VARCHAR(255) DEFAULT NULL",
            'smtp_port' => "ALTER TABLE site_settings ADD COLUMN smtp_port INT(11) DEFAULT NULL",
            'smtp_username' => "ALTER TABLE site_settings ADD COLUMN smtp_username VARCHAR(255) DEFAULT NULL",
            'smtp_password' => "ALTER TABLE site_settings ADD COLUMN smtp_password VARCHAR(255) DEFAULT NULL",
            'default_language' => "ALTER TABLE site_settings ADD COLUMN default_language VARCHAR(10) NOT NULL DEFAULT 'pl'",
            'layout_variant' => "ALTER TABLE site_settings ADD COLUMN layout_variant VARCHAR(50) NOT NULL DEFAULT 'classic'",
            'company_name' => "ALTER TABLE site_settings ADD COLUMN company_name VARCHAR(255) DEFAULT NULL",
            'company_tax_id' => "ALTER TABLE site_settings ADD COLUMN company_tax_id VARCHAR(50) DEFAULT NULL",
            'company_addresses' => "ALTER TABLE site_settings ADD COLUMN company_addresses TEXT DEFAULT NULL",
            'company_emails' => "ALTER TABLE site_settings ADD COLUMN company_emails TEXT DEFAULT NULL",
            'company_phones' => "ALTER TABLE site_settings ADD COLUMN company_phones TEXT DEFAULT NULL",
            'favicon' => "ALTER TABLE site_settings ADD COLUMN favicon VARCHAR(255) DEFAULT NULL",
            'meta_title' => "ALTER TABLE site_settings ADD COLUMN meta_title VARCHAR(255) DEFAULT NULL",
            'maintenance_mode' => "ALTER TABLE site_settings ADD COLUMN maintenance_mode TINYINT(1) NOT NULL DEFAULT 0",
            'maintenance_message' => "ALTER TABLE site_settings ADD COLUMN maintenance_message TEXT DEFAULT NULL",
            'email_templates' => "ALTER TABLE site_settings ADD COLUMN email_templates MEDIUMTEXT DEFAULT NULL",
            'sitemap_enabled' => "ALTER TABLE site_settings ADD COLUMN sitemap_enabled TINYINT(1) NOT NULL DEFAULT 1",
            'sitemap_last_generated' => "ALTER TABLE site_settings ADD COLUMN sitemap_last_generated DATETIME DEFAULT NULL",
            'last_system_backup' => "ALTER TABLE site_settings ADD COLUMN last_system_backup VARCHAR(255) DEFAULT NULL",
            'last_database_backup' => "ALTER TABLE site_settings ADD COLUMN last_database_backup VARCHAR(255) DEFAULT NULL",
        ];

        $stmt = $this->pdo->query("SHOW COLUMNS FROM site_settings");
        $existingColumns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($columns as $column => $sql) {
            if (!in_array($column, $existingColumns, true)) {
                $this->pdo->exec($sql);
            }
        }

        $this->settingsColumnsEnsured = true;
    }

    public function getSettings() {
        $this->ensureSettingsColumns();

        $query = $this->pdo->query("SELECT * FROM site_settings LIMIT 1");
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return $this->createDefaultSettings();
        }

        return $result;
    }

    private function createDefaultSettings() {
        $defaultSettings = [
            'title' => 'Jobler - Platforma Zleceń',
            'logo' => 'default-logo.png',
            'categories' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'max_ads' => 10,
            'promotion_fee' => 10.00,
            'smtp_server' => '',
            'smtp_port' => '',
            'smtp_username' => '',
            'smtp_password' => '',
            'facebook_url' => '',
            'twitter_url' => '',
            'instagram_url' => '',
            'linkedin_url' => '',
            'contact_email' => 'info@jobler.pl',
            'contact_phone' => '+48 123 456 789',
            'contact_address' => 'ul. Przykładowa 123, 00-000 Warszawa',
            'business_hours' => 'Pon-Pt: 8:00-18:00',
            'default_language' => 'pl',
            'layout_variant' => 'classic',
            'company_name' => '',
            'company_tax_id' => '',
            'company_addresses' => '[]',
            'company_emails' => '[]',
            'company_phones' => '[]',
            'favicon' => '',
            'meta_title' => '',
            'maintenance_mode' => 0,
            'maintenance_message' => '',
            'email_templates' => '{}',
            'sitemap_enabled' => 1,
            'sitemap_last_generated' => null,
            'last_system_backup' => '',
            'last_database_backup' => '',
        ];

        $sql = "INSERT INTO site_settings
                    (title, logo, categories, meta_description, meta_keywords, max_ads, promotion_fee,
                     smtp_server, smtp_port, smtp_username, smtp_password, facebook_url, twitter_url,
                     instagram_url, linkedin_url, contact_email, contact_phone, contact_address,
                     business_hours, default_language, layout_variant, company_name, company_tax_id,
                     company_addresses, company_emails, company_phones, favicon, meta_title,
                     maintenance_mode, maintenance_message, email_templates, sitemap_enabled,
                     sitemap_last_generated, last_system_backup, last_database_backup)
                VALUES
                    (:title, :logo, :categories, :meta_description, :meta_keywords, :max_ads, :promotion_fee,
                     :smtp_server, :smtp_port, :smtp_username, :smtp_password, :facebook_url, :twitter_url,
                     :instagram_url, :linkedin_url, :contact_email, :contact_phone, :contact_address,
                     :business_hours, :default_language, :layout_variant, :company_name, :company_tax_id,
                     :company_addresses, :company_emails, :company_phones, :favicon, :meta_title,
                     :maintenance_mode, :maintenance_message, :email_templates, :sitemap_enabled,
                     :sitemap_last_generated, :last_system_backup, :last_database_backup)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($defaultSettings);

        return $defaultSettings;
    }

    public function updateSettings($data) {
        $this->ensureSettingsColumns();

        $sql = "UPDATE site_settings SET
                title = :title,
                logo = :logo,
                meta_description = :meta_description,
                meta_keywords = :meta_keywords,
                max_ads = :max_ads,
                promotion_fee = :promotion_fee,
                smtp_server = :smtp_server,
                smtp_port = :smtp_port,
                smtp_username = :smtp_username,
                smtp_password = :smtp_password,
                facebook_url = :facebook_url,
                twitter_url = :twitter_url,
                instagram_url = :instagram_url,
                linkedin_url = :linkedin_url,
                contact_email = :contact_email,
                contact_phone = :contact_phone,
                contact_address = :contact_address,
                business_hours = :business_hours,
                default_language = :default_language,
                layout_variant = :layout_variant,
                company_name = :company_name,
                company_tax_id = :company_tax_id,
                company_addresses = :company_addresses,
                company_emails = :company_emails,
                company_phones = :company_phones,
                favicon = :favicon,
                meta_title = :meta_title,
                maintenance_mode = :maintenance_mode,
                maintenance_message = :maintenance_message,
                email_templates = :email_templates,
                sitemap_enabled = :sitemap_enabled
                WHERE id = 1";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function getSiteErrors() {
        $query = "SELECT COUNT(*) AS error_count FROM system_logs WHERE log_level = 'ERROR'";
        $stmt = $this->pdo->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['error_count'];
    }

    public function logTransaction($userId, $amount, $description) {
        $stmt = $this->pdo->prepare("INSERT INTO transaction_history (user_id, amount, description, created_at) VALUES (:user_id, :amount, :description, NOW())");
        $stmt->execute([
            'user_id' => $userId,
            'amount' => $amount,
            'description' => $description,
        ]);
    }

    public function logAdminLogin($adminId, $ipAddress) {
        $stmt = $this->pdo->prepare("INSERT INTO admin_login_history (admin_id, ip_address, login_time) VALUES (:admin_id, :ip_address, NOW())");
        $stmt->execute([
            'admin_id' => $adminId,
            'ip_address' => $ipAddress,
        ]);
    }

    public function getSiteViews() {
        $sql = "SELECT views FROM site_stats LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['views'] : 0;
    }

    public function updateSiteViews($views) {
        $sql = "UPDATE site_stats SET views = :views WHERE id = 1";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['views' => $views]);
    }

    public function addCategory($name, $parent_id = null) {
        $stmt = $this->pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (:name, :parent_id)");
        $stmt->execute([
            'name' => $name,
            'parent_id' => $parent_id,
        ]);
        return $this->pdo->lastInsertId();
    }

    public function updateCategory($id, $name, $parentId = null) {
        if ($parentId !== null && (int)$parentId === (int)$id) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE categories SET name = :name, parent_id = :parent_id WHERE id = :id");
        return $stmt->execute([
            'id' => $id,
            'name' => $name,
            'parent_id' => $parentId,
        ]);
    }

    public function deleteCategory($id) {
        $childStmt = $this->pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = :id");
        $childStmt->execute(['id' => $id]);

        if ((int)$childStmt->fetchColumn() > 0) {
            return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getCategoriesByParent($parentId = null) {
        if ($parentId === null || $parentId === '') {
            $stmt = $this->pdo->prepare("
                SELECT c.*, (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) AS child_count
                FROM categories c
                WHERE c.parent_id IS NULL
                ORDER BY c.name ASC, c.id ASC
            ");
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare("
                SELECT c.*, (SELECT COUNT(*) FROM categories child WHERE child.parent_id = c.id) AS child_count
                FROM categories c
                WHERE c.parent_id = :parent_id
                ORDER BY c.name ASC, c.id ASC
            ");
            $stmt->bindValue(':parent_id', (int)$parentId, PDO::PARAM_INT);
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFlatCategories() {
        $stmt = $this->pdo->query("SELECT id, name, parent_id FROM categories ORDER BY parent_id IS NULL DESC, parent_id, name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories() {
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY parent_id IS NULL DESC, parent_id, id");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $structuredCategories = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] === null) {
                $structuredCategories[$category['id']] = [
                    'name' => $category['name'],
                    'subcategories' => [],
                ];
            } else {
                $structuredCategories[$category['parent_id']]['subcategories'][] = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                ];
            }
        }
        return $structuredCategories;
    }

    public function logSettingsChange($userId, $changeDescription, $timestamp) {
        $query = "INSERT INTO settings_log (user_id, change_description, timestamp) VALUES (:user_id, :change_description, :timestamp)";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':change_description', $changeDescription);
        $stmt->bindParam(':timestamp', $timestamp);
        $stmt->execute();
    }

    public function updateSMTPSettings($smtpServer, $smtpPort, $smtpUsername, $smtpPassword) {
        $sql = "UPDATE site_settings
                SET smtp_server = :smtp_server,
                    smtp_port = :smtp_port,
                    smtp_username = :smtp_username,
                    smtp_password = :smtp_password
                WHERE id = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':smtp_server', $smtpServer);
        $stmt->bindParam(':smtp_port', $smtpPort);
        $stmt->bindParam(':smtp_username', $smtpUsername);
        $stmt->bindParam(':smtp_password', $smtpPassword);

        return $stmt->execute();
    }

    public function updateSettingValue($column, $value) {
        $this->ensureSettingsColumns();

        $allowedColumns = ['sitemap_last_generated', 'last_system_backup', 'last_database_backup'];
        if (!in_array($column, $allowedColumns, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE site_settings SET {$column} = :value WHERE id = 1");
        return $stmt->execute(['value' => $value]);
    }
}
?>
