<?php
/**
 * 軽量アクセス解析トラッカー (AnalyticsTracker.php)
 * - PV / UU
 * - 日別PV・UU推移
 * - 外部参照元
 * - デバイス
 * - 人気記事
 *
 * UUは「IP + User-Agent」の安定ハッシュで集計し、期間をまたいでも同一訪問者を
 * 毎日別人として数えない。
 */
require_once __DIR__ . '/Database.php';

class AnalyticsTracker {
    public static function track(string $pageType = 'home', ?int $articleId = null): void {
        $ua = trim($_SERVER['HTTP_USER_AGENT'] ?? '');

        // 明確なBot / クローラー / プレビュー / 自動取得を除外
        if ($ua === '' || preg_match('/(bot|crawl|crawler|spider|slurp|bingpreview|facebookexternalhit|twitterbot|discordbot|telegrambot|whatsapp|line-poker|google-inspectiontool|lighthouse|pagespeed|headlesschrome|phantomjs|curl|wget|python-requests|httpclient|uptime|monitor)/i', $ua)) {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '') {
            return;
        }

        $referer = trim($_SERVER['HTTP_REFERER'] ?? '');
        $refHost = self::normalizeHost((string)(parse_url($referer, PHP_URL_HOST) ?: ''));
        $currentHost = self::normalizeHost($_SERVER['HTTP_HOST'] ?? '');

        // 同一サイト内遷移は流入元として数えない
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

        // 日付を含めない安定ハッシュ。同一訪問者を期間集計で重複させない。
        $visitorHash = hash('sha256', $ip . '|' . $ua);

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO access_logs
                (site_id, page_type, article_id, visitor_hash, ip_address, referer_host, full_referer, user_agent, device_type, created_at)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $pageType,
                $articleId,
                $visitorHash,
                $ip,
                $refHost,
                $referer !== '' ? mb_substr($referer, 0, 500) : null,
                mb_substr($ua, 0, 255),
                $device
            ]);
        } catch (Throwable $e) {
            // アクセス解析の失敗で表サイトを止めない
        }
    }

    public static function getStats(int $days = 30): array {
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
        ];

        try {
            $db = Database::getConnection();

            // 旧方式の不正確な集計を新画面へ持ち込まないため、v2導入時点を集計開始点として保存。
            $startStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE site_id = 1 AND setting_key = 'analytics_v2_started_at' LIMIT 1");
            $startStmt->execute();
            $trackingSince = $startStmt->fetchColumn();
            if (!$trackingSince) {
                $trackingSince = date('Y-m-d H:i:s');
                $ins = $db->prepare("INSERT INTO site_settings (site_id, setting_key, setting_value) VALUES (1, 'analytics_v2_started_at', ?)
                                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $ins->execute([$trackingSince]);
            }

            // 旧ログはvisitor_hashに日付を含むため、期間UUはIP+UAをその場で安定化して集計。
            foreach ([1, 7, 30] as $period) {
                $sql = "SELECT
                            COUNT(*) AS pv,
                            COUNT(DISTINCT SHA2(CONCAT(COALESCE(ip_address,''), '|', COALESCE(user_agent,'')), 256)) AS uu
                        FROM access_logs
                        WHERE site_id = 1
                          AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$period . " DAY)
                          AND created_at >= ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$trackingSince]);
                $row = $stmt->fetch();
                $stats['periods'][$period] = [
                    'pv' => (int)($row['pv'] ?? 0),
                    'uu' => (int)($row['uu'] ?? 0),
                ];
            }

            $totalStmt = $db->prepare("SELECT COUNT(*) FROM access_logs WHERE site_id = 1 AND created_at >= ?");
            $totalStmt->execute([$trackingSince]);
            $stats['total_pv'] = (int)($totalStmt->fetchColumn() ?: 0);
            $stats['tracking_since'] = $trackingSince;

            // 直近N日の日別PV/UU。欠損日は0で補完する。
            $chartSql = "SELECT
                            DATE(created_at) AS log_date,
                            COUNT(*) AS pv,
                            COUNT(DISTINCT SHA2(CONCAT(COALESCE(ip_address,''), '|', COALESCE(user_agent,'')), 256)) AS uu
                         FROM access_logs
                         WHERE site_id = 1
                           AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . max(0, $days - 1) . " DAY)
                           AND created_at >= ?
                         GROUP BY DATE(created_at)
                         ORDER BY log_date ASC";
            $chartStmt = $db->prepare($chartSql);
            $chartStmt->execute([$trackingSince]);
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

            // 人気記事（30日）
            $stats['top_articles'] = $db->query(
                "SELECT a.id, a.title, a.shirankedo_index, COUNT(l.id) AS pv
                 FROM access_logs l
                 JOIN articles a ON l.article_id = a.id
                 WHERE l.site_id = 1
                   AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   AND l.created_at >= " . $db->quote($trackingSince) . "
                 GROUP BY a.id, a.title, a.shirankedo_index
                 ORDER BY pv DESC
                 LIMIT 10"
            )->fetchAll();

            // 外部参照元だけを表示。自サイトはtrack時にdirect化済み。
            $currentHost = self::normalizeHost($_SERVER['HTTP_HOST'] ?? '');
            $refSql = "SELECT LOWER(referer_host) AS referer_host, COUNT(*) AS count
                       FROM access_logs
                       WHERE site_id = 1
                         AND referer_host IS NOT NULL
                         AND referer_host NOT IN ('', 'direct')
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                         AND created_at >= ?";
            $refParams = [$trackingSince];
            if ($currentHost !== '') {
                $refSql .= " AND LOWER(referer_host) <> ?";
                $refParams[] = $currentHost;
            }
            $refSql .= " GROUP BY LOWER(referer_host) ORDER BY count DESC LIMIT 10";
            $refStmt = $db->prepare($refSql);
            $refStmt->execute($refParams);
            $stats['referers'] = $refStmt->fetchAll();

            // デバイス比率も直近30日
            $devRows = $db->query(
                "SELECT device_type, COUNT(*) AS count
                 FROM access_logs
                 WHERE site_id = 1
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   AND created_at >= " . $db->quote($trackingSince) . "
                 GROUP BY device_type"
            )->fetchAll();
            foreach ($devRows as $dr) {
                $key = $dr['device_type'] ?? 'desktop';
                if (isset($stats['devices'][$key])) {
                    $stats['devices'][$key] = (int)$dr['count'];
                }
            }
        } catch (Throwable $e) {
            // 解析画面自体は空データで表示を継続
        }

        return $stats;
    }

    private static function normalizeHost(string $host): string {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host);
        return preg_replace('/^www\./', '', $host);
    }
}
