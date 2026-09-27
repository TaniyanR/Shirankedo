<?php
require_once __DIR__ . '/Database.php';

class StaticPageManager {
    public static function all(int $siteId = 1): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM static_pages WHERE site_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public static function published(int $siteId = 1): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM static_pages WHERE site_id = ? AND status = 'published' ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $siteId = 1): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM static_pages WHERE id = ? AND site_id = ? LIMIT 1");
        $stmt->execute([$id, $siteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug, int $siteId = 1, bool $publishedOnly = true): ?array {
        $db = Database::getConnection();
        $sql = "SELECT * FROM static_pages WHERE site_id = ? AND slug = ?";
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $sql .= " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$siteId, $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
