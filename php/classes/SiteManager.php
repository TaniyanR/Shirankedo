<?php
/**
 * サイト管理 & ワイルドカードサブドメイン解決
 */
class SiteManager {
    private static ?array $currentSite = null;

    /**
     * アクセスされたホスト名からサイトを特定 (ワイルドカードサブドメイン対応)
     */
    public static function resolveCurrentSite(): array {
        if (self::$currentSite !== null) {
            return self::$currentSite;
        }

        $host = $_SERVER['HTTP_HOST'] ?? MAIN_DOMAIN;
        $host = strtolower(explode(':', $host)[0]); // ポート番号除去

        // サブドメイン抽出 (例: game.example.com -> game)
        $subdomain = '';
        $mainDomain = strtolower(MAIN_DOMAIN);
        if ($host !== $mainDomain && str_ends_with($host, '.' . $mainDomain)) {
            $subdomain = substr($host, 0, - (strlen($mainDomain) + 1));
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM sites WHERE subdomain = ? AND is_public = 1 LIMIT 1");
        $stmt->execute([$subdomain]);
        $site = $stmt->fetch();

        // 該当がなければメイン総合サイト (subdomain='') を取得
        if (!$site) {
            $stmt = $db->prepare("SELECT * FROM sites WHERE subdomain = '' LIMIT 1");
            $stmt->execute();
            $site = $stmt->fetch();
        }

        if (!$site) {
            // 万一初期サイトが存在しない場合のフォールバック
            $site = [
                'id' => 1,
                'subdomain' => '',
                'name' => 'しらんけど',
                'description' => '「いま日本で何が話題か」を自動分析するトレンドサイト。しらんけど。',
                'genre' => 'general',
                'is_public' => 1,
                'allow_auto_publish' => 1,
                'youtube_thumbnail_enabled' => 1
            ];
        }

        self::$currentSite = $site;
        return $site;
    }

    public static function getSiteSetting(int $siteId, string $key, string $default = ''): string {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE site_id = ? AND setting_key = ? LIMIT 1");
        $stmt->execute([$siteId, $key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    public static function setSiteSetting(int $siteId, string $key, string $value): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO site_settings (site_id, setting_key, setting_value) 
                              VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$siteId, $key, $value]);
    }
}
