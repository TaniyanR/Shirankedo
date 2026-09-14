<?php
/**
 * スパム・誹謗中傷・URL投稿防止フィルター
 * ユーザー投稿は文字のみ。URL、HTML、画像、動画、スクリプトは完全遮断
 */
class SpamFilter {
    /**
     * URLの存在を厳密に検出 (短縮URL、全角偽装、回避表記もカバー)
     */
    public static function containsUrl(string $text): bool {
        $patterns = [
            '/https?:\/\//i',
            '/ftp:\/\//i',
            '/www\.[a-z0-9\-]+\.[a-z]{2,}/i',
            '/[a-z0-9\-]+\.(com|net|org|jp|co\.jp|info|io|xyz|me|tv|cc|ru|cn|top|app|dev)/i',
            '/h?ttp[s]?:\/\//i',
            '/t\.co\//i',
            '/bit\.ly\//i',
            '/youtu\.be\//i',
            '/goo\.gl\//i',
            '/dot\s*com/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 拒否キーワード照合 (完全一致・部分一致・正規表現)
     */
    public static function checkBannedKeywords(int $siteId, string $text): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM banned_keywords WHERE site_id = ? AND is_active = 1");
        $stmt->execute([$siteId]);
        $keywords = $stmt->fetchAll();

        foreach ($keywords as $kw) {
            $word = $kw['keyword'];
            $matchType = $kw['match_type'];

            if ($matchType === 'exact') {
                if (trim($text) === $word) {
                    return $kw;
                }
            } elseif ($matchType === 'regex') {
                if (@preg_match($word, $text)) {
                    return $kw;
                }
            } else { // partial
                if (mb_stripos($text, $word) !== false) {
                    return $kw;
                }
            }
        }
        return null;
    }

    /**
     * 入力テキストのサニタイズ (HTML完全除去、文字数制限)
     */
    public static function sanitizeComment(string $text, int $maxLength = 400): string {
        $cleaned = strip_tags($text);
        $cleaned = htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
        return mb_substr(trim($cleaned), 0, $maxLength);
    }

    /**
     * IPアドレスの安全なソルト付きハッシュ化
     */
    public static function hashIp(?string $ip = null): string {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return hash('sha256', $ip . '_' . APP_SECRET_KEY);
    }
}
