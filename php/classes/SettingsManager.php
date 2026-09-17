<?php
/**
 * サイト設定・広告・SEOタグ・推測不能管理画面URLヘルパー (SettingsManager.php)
 */
require_once __DIR__ . '/Database.php';

class SettingsManager {
    private static ?array $cache = null;

    /**
     * 全設定を1回のクエリでキャッシュ
     */
    private static function loadCache(): void {
        if (self::$cache !== null) return;
        self::$cache = [];
        try {
            $db = Database::getConnection();
            $rows = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE site_id = 1")->fetchAll();
            foreach ($rows as $r) {
                self::$cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Throwable $e) {
            // DB未接続時など
        }
    }

    public static function get(string $key, string $default = ''): string {
        self::loadCache();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO site_settings (site_id, setting_key, setting_value) 
                                  VALUES (1, ?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value]);
            if (self::$cache !== null) {
                self::$cache[$key] = $value;
            }
        } catch (Throwable $e) {}
    }

    /**
     * 推測不可能なシークレット管理画面URLを取得
     * 例: /admin-manage-sk89q.php
     */
    public static function getAdminUrl(): string {
        $secretPath = self::get('admin_secret_path', 'manage-sk89q');
        return 'admin-' . $secretPath . '.php';
    }

    /**
     * 管理画面のシークレットパス検証
     */
    public static function isValidAdminSecret(string $inputSecret): bool {
        $currentSecret = self::get('admin_secret_path', 'manage-sk89q');
        return hash_equals($currentSecret, $inputSecret);
    }
}
