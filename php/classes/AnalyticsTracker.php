<?php
/**
 * 高速・軽量アクセス解析トラッカー (AnalyticsTracker.php)
 * - ページビュー(PV)
 * - ユニーク訪問者(UU)
 * - 参照元(リファラー: Google, Yahoo, X, Instagram, Pinterest, 相互アンテナ等)
 * - デバイス判定(PC / スマホ / タブレット)
 * - 人気記事ランキング
 * - 日別PV/UU推移
 */
require_once __DIR__ . '/Database.php';

class AnalyticsTracker {
    public static function track(string $pageType = 'home', ?int $articleId = null): void {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // ボット・クローラー除外
        if (preg_match('/(bot|crawl|spider|slurp|facebookexternalhit|curl|wget|Google-InspectionTool)/i', $ua)) {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $refHost = parse_url($referer, PHP_URL_HOST) ?: 'direct';
        $today = date('Y-m-d');

        // デバイス判定
        $device = 'desktop';
        if (preg_match('/(iPhone|Android.*Mobile|Windows Phone)/i', $ua)) {
            $device = 'mobile';
        } elseif (preg_match('/(iPad|Android(?!.*Mobile)|Tablet)/i', $ua)) {
            $device = 'tablet';
        }

        $visitorHash = hash('sha256', $ip . '_' . $ua . '_' . $today);

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO access_logs (site_id, page_type, article_id, visitor_hash, ip_address, referer_host, full_referer, user_agent, device_type, created_at)
                                  VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$pageType, $articleId, $visitorHash, $ip, $refHost, mb_substr($referer, 0, 500), mb_substr($ua, 0, 255), $device]);
        } catch (Throwable $e) {
            // 解析エラーで画面処理を阻害しない
        }
    }

    /**
     * ダッシュボード用集計データ取得
     */
    public static function getStats(int $days = 7): array {
        $stats = [
            'total_pv' => 0,
            'today_pv' => 0,
            'today_uu' => 0,
            'yesterday_pv' => 0,
            'daily_chart' => [],
            'top_articles' => [],
            'referers' => [],
            'devices' => ['desktop' => 0, 'mobile' => 0, 'tablet' => 0]
        ];

        try {
            $db = Database::getConnection();

            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            $stmtToday = $db->prepare("SELECT COUNT(*) as pv, COUNT(DISTINCT visitor_hash) as uu FROM access_logs WHERE DATE(created_at) = ?");
            $stmtToday->execute([$today]);
            $tRow = $stmtToday->fetch();
            $stats['today_pv'] = (int)($tRow['pv'] ?? 0);
            $stats['today_uu'] = (int)($tRow['uu'] ?? 0);

            $stmtYest = $db->prepare("SELECT COUNT(*) as pv FROM access_logs WHERE DATE(created_at) = ?");
            $stmtYest->execute([$yesterday]);
            $stats['yesterday_pv'] = (int)($stmtYest->fetchColumn() ?: 0);

            $stats['total_pv'] = (int)$db->query("SELECT COUNT(*) FROM access_logs")->fetchColumn();

            // 直近の日別推移
            $chartStmt = $db->prepare("SELECT DATE(created_at) as log_date, COUNT(*) as pv, COUNT(DISTINCT visitor_hash) as uu 
                                       FROM access_logs 
                                       WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                                       GROUP BY DATE(created_at) 
                                       ORDER BY log_date ASC");
            $chartStmt->execute([$days]);
            $stats['daily_chart'] = $chartStmt->fetchAll();

            // 人気記事
            $stats['top_articles'] = $db->query("SELECT a.id, a.title, a.shirankedo_index, COUNT(l.id) as pv 
                                                 FROM access_logs l
                                                 JOIN articles a ON l.article_id = a.id
                                                 WHERE l.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                                 GROUP BY a.id
                                                 ORDER BY pv DESC LIMIT 10")->fetchAll();

            // 参照元 (リファラー)
            $stats['referers'] = $db->query("SELECT referer_host, COUNT(*) as count 
                                             FROM access_logs 
                                             WHERE referer_host != 'direct' AND referer_host != ''
                                             GROUP BY referer_host 
                                             ORDER BY count DESC LIMIT 10")->fetchAll();

            // デバイス比率
            $devRows = $db->query("SELECT device_type, COUNT(*) as count FROM access_logs GROUP BY device_type")->fetchAll();
            foreach ($devRows as $dr) {
                $stats['devices'][$dr['device_type']] = (int)$dr['count'];
            }

        } catch (Throwable $e) {}

        return $stats;
    }
}
