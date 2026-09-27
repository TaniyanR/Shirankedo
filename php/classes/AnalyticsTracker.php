<?php
/**
 * 実アクセス解析トラッカー
 *
 * 計測方針:
 * - GETの実ページ閲覧だけをPVとして記録
 * - 管理者・Bot・クローラー・プレビュー取得を除外
 * - UUは1st-party Cookie(sk_vid)で識別
 * - セッションは30分Cookie(sk_sid)で識別
 * - v3以前の旧ログは新しい集計へ混ぜない
 */
require_once __DIR__ . '/Database.php';

class AnalyticsTracker {
    private const TRACKING_VERSION = 3;
    private const VISITOR_COOKIE = 'sk_vid';
    private const SESSION_COOKIE = 'sk_sid';

    public static function track(string $pageType = 'home', ?int $articleId = null, int $siteId = 1): void {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }

        // 管理画面へログインしているブラウザは表サイトを見ても計測しない。
        if (($_COOKIE['shirankedo_admin'] ?? '') === '1') {
            return;
        }

        $ua = trim($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '' || self::isBot($ua)) {
            return;
        }

        $ip = trim($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return;
        }

        $visitorId = self::ensureCookie(self::VISITOR_COOKIE, 400 * 86400);
        $sessionId = self::ensureCookie(self::SESSION_COOKIE, 1800);
        if ($visitorId === '' || $sessionId === '') {
            return;
        }

        $visitorHash = hash('sha256', $visitorId);
        $sessionHash = hash('sha256', $sessionId);

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '/');
        if ($pageType === 'article' && $articleId) {
            $requestPath .= '#article-' . $articleId;
        }

        $referer = trim($_SERVER['HTTP_REFERER'] ?? '');
        $refHost = self::normalizeHost((string)(parse_url($referer, PHP_URL_HOST) ?: ''));
        $currentHost = self::normalizeHost($_SERVER['HTTP_HOST'] ?? '');

        if ($refHost === '' || ($currentHost !== '' && $refHost === $currentHost)) {
            $refHost = 'direct';
            $referer = '';
        }

        $device = 'desktop';
        if (preg_match('/(iPhone|Android.*Mobile|Windows Phone|Mobile)/i', $ua)) {
            $device = 'mobile';
        } elseif (preg_match('/(iPad|Android(?!.*Mobile)|Tablet)/i', $ua)) {
            $device = 'tablet';
        }

        try {
            $db = Database::getConnection();

            // 同一ブラウザ・同一ページの数秒以内の二重読込は1PVにまとめる。
            $dup = $db->prepare("SELECT id
                                 FROM access_logs
                                 WHERE site_id = ?
                                   AND tracking_version = ?
                                   AND visitor_hash = ?
                                   AND request_path = ?
                                   AND created_at >= DATE_SUB(NOW(), INTERVAL 3 SECOND)
                                 LIMIT 1");
            $dup->execute([$siteId, self::TRACKING_VERSION, $visitorHash, $requestPath]);
            if ($dup->fetchColumn()) {
                return;
            }

            $stmt = $db->prepare("INSERT INTO access_logs
                (site_id, page_type, article_id, visitor_hash, session_hash, request_path,
                 ip_address, referer_host, full_referer, user_agent, device_type, tracking_version, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $siteId,
                $pageType,
                $articleId,
                $visitorHash,
                $sessionHash,
                mb_substr($requestPath, 0, 255),
                $ip,
                $refHost,
                $referer !== '' ? mb_substr($referer, 0, 500) : null,
                mb_substr($ua, 0, 255),
                $device,
                self::TRACKING_VERSION
            ]);
        } catch (Throwable $e) {
            // 解析の失敗で表サイトを止めない。
        }
    }

    public static function getStats(int $days = 30, int $siteId = 1): array {
        $days = max(1, min(365, $days));
        $stats = [
            'periods' => [
                1 => ['pv' => 0, 'uu' => 0],
                7 => ['pv' => 0, 'uu' => 0],
                30 => ['pv' => 0, 'uu' => 0],
            ],
            'daily_chart' => [],
            'top_articles' => [],
            'referers' => [],
            'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0],
            'total_pv' => 0,
            'tracking_since' => null,
        ];

        try {
            $db = Database::getConnection();

            foreach ([1, 7, 30] as $period) {
                $sql = "SELECT COUNT(*) AS pv,
                               COUNT(DISTINCT visitor_hash) AS uu
                        FROM access_logs
                        WHERE site_id = ?
                          AND tracking_version = ?
                          AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$period . " DAY)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$siteId, self::TRACKING_VERSION]);
                $row = $stmt->fetch();
                $stats['periods'][$period] = [
                    'pv' => (int)($row['pv'] ?? 0),
                    'uu' => (int)($row['uu'] ?? 0),
                ];
            }

            $stmt = $db->prepare("SELECT COUNT(*) AS pv, MIN(created_at) AS started
                                  FROM access_logs
                                  WHERE site_id = ? AND tracking_version = ?");
            $stmt->execute([$siteId, self::TRACKING_VERSION]);
            $row = $stmt->fetch();
            $stats['total_pv'] = (int)($row['pv'] ?? 0);
            $stats['tracking_since'] = $row['started'] ?? null;

            $chartSql = "SELECT DATE(created_at) AS log_date,
                                COUNT(*) AS pv,
                                COUNT(DISTINCT visitor_hash) AS uu
                         FROM access_logs
                         WHERE site_id = ?
                           AND tracking_version = ?
                           AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . max(0, $days - 1) . " DAY)
                         GROUP BY DATE(created_at)
                         ORDER BY log_date ASC";
            $chartStmt = $db->prepare($chartSql);
            $chartStmt->execute([$siteId, self::TRACKING_VERSION]);
            $rows = $chartStmt->fetchAll();

            $byDate = [];
            foreach ($rows as $row) {
                $byDate[$row['log_date']] = [
                    'date' => $row['log_date'],
                    'pv' => (int)$row['pv'],
                    'uu' => (int)$row['uu'],
                ];
            }
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} day"));
                $stats['daily_chart'][] = $byDate[$date] ?? ['date' => $date, 'pv' => 0, 'uu' => 0];
            }

            $popular = $db->prepare(
                "SELECT a.id, a.title, a.shirankedo_index, COUNT(l.id) AS pv,
                        COUNT(DISTINCT l.visitor_hash) AS uu
                 FROM access_logs l
                 JOIN articles a ON l.article_id = a.id
                 WHERE l.site_id = ?
                   AND l.tracking_version = ?
                   AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY a.id, a.title, a.shirankedo_index
                 ORDER BY pv DESC
                 LIMIT 10"
            );
            $popular->execute([$siteId, self::TRACKING_VERSION]);
            $stats['top_articles'] = $popular->fetchAll();

            $currentHost = self::normalizeHost($_SERVER['HTTP_HOST'] ?? '');
            $refSql = "SELECT LOWER(referer_host) AS referer_host, COUNT(*) AS count,
                              COUNT(DISTINCT visitor_hash) AS uu
                       FROM access_logs
                       WHERE site_id = ?
                         AND tracking_version = ?
                         AND referer_host IS NOT NULL
                         AND referer_host NOT IN ('', 'direct')
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $params = [$siteId, self::TRACKING_VERSION];
            if ($currentHost !== '') {
                $refSql .= " AND LOWER(referer_host) <> ?";
                $params[] = $currentHost;
            }
            $refSql .= " GROUP BY LOWER(referer_host) ORDER BY count DESC LIMIT 10";
            $refStmt = $db->prepare($refSql);
            $refStmt->execute($params);
            $stats['referers'] = $refStmt->fetchAll();

            $devStmt = $db->prepare(
                "SELECT device_type, COUNT(*) AS count
                 FROM access_logs
                 WHERE site_id = ?
                   AND tracking_version = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY device_type"
            );
            $devStmt->execute([$siteId, self::TRACKING_VERSION]);
            foreach ($devStmt->fetchAll() as $dr) {
                $key = $dr['device_type'] ?? 'desktop';
                if (isset($stats['devices'][$key])) {
                    $stats['devices'][$key] = (int)$dr['count'];
                }
            }
        } catch (Throwable $e) {
            // 管理画面は空データで表示継続。
        }

        return $stats;
    }

    private static function ensureCookie(string $name, int $ttl): string {
        $value = trim($_COOKIE[$name] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $value)) {
            try {
                $value = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                return '';
            }
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        @setcookie($name, $value, [
            'expires' => time() + $ttl,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
        return $value;
    }

    private static function isBot(string $ua): bool {
        return (bool)preg_match(
            '/(bot|crawler|crawl|spider|slurp|bingpreview|facebookexternalhit|twitterbot|discordbot|telegrambot|whatsapp|line-poker|google-inspectiontool|googleother|googlebot|adsbot|mediapartners|bingbot|yandex|baiduspider|duckduckbot|applebot|petalbot|bytespider|semrush|ahrefs|mj12bot|dotbot|uptime|monitor|lighthouse|pagespeed|headless|phantomjs|curl|wget|python|httpclient|preview)/i',
            $ua
        );
    }

    private static function normalizeHost(string $host): string {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host);
        return preg_replace('/^www\./', '', $host);
    }
}
