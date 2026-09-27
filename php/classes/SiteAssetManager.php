<?php
require_once __DIR__ . '/Database.php';

class SiteAssetManager {
    public static function saveUploadedAsset(int $siteId, string $type, array $file): void {
        if (!in_array($type, ['logo', 'favicon', 'ogp'], true)) {
            throw new Exception('不正な画像種別です。');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new Exception('画像アップロードに失敗しました。');
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new Exception('画像は5MB以内にしてください。');
        }

        $tmp = $file['tmp_name'] ?? '';
        $data = @file_get_contents($tmp);
        if ($data === false || $data === '') {
            throw new Exception('画像データを読み込めませんでした。');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($data) ?: '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];
        if (!in_array($mime, $allowed, true)) {
            throw new Exception('JPG / PNG / WEBP / GIF / ICO 画像を使用してください。');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO site_assets (site_id, asset_type, filename, mime_type, data)
                              VALUES (?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE
                                filename = VALUES(filename),
                                mime_type = VALUES(mime_type),
                                data = VALUES(data),
                                updated_at = NOW()");
        $stmt->execute([
            $siteId,
            $type,
            mb_substr((string)($file['name'] ?? $type), 0, 255),
            $mime,
            $data
        ]);
    }

    public static function exists(int $siteId, string $type): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM site_assets WHERE site_id = ? AND asset_type = ? LIMIT 1");
            $stmt->execute([$siteId, $type]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function get(int $siteId, string $type): ?array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT filename, mime_type, data, updated_at FROM site_assets WHERE site_id = ? AND asset_type = ? LIMIT 1");
            $stmt->execute([$siteId, $type]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function url(string $type, int $siteId = 1): string {
        return 'site-asset.php?type=' . rawurlencode($type) . '&site=' . $siteId;
    }
}
