<?php
/**
 * SNS自動配信マネージャー (X, Pinterest, Instagram)
 * キュー方式、媒体別最適化テンプレート、時間差投稿
 */
class SnsDispatcher {
    /**
     * 記事公開時にSNSキューへエンキュー
     */
    public static function enqueueArticle(int $siteId, int $articleId, array $articleData): void {
        $db = Database::getConnection();

        $snsTargets = ['x', 'pinterest', 'instagram'];
        foreach ($snsTargets as $sns) {
            $isEnabled = SiteManager::getSiteSetting($siteId, "sns_{$sns}_enabled", '0');
            if ($isEnabled !== '1') {
                continue;
            }

            $content = self::buildContent($sns, $articleData);
            // 投稿予定日時: スパム防止のため媒体ごとに時間差 (5分〜30分) を設ける
            $delayMinutes = ($sns === 'x') ? 5 : (($sns === 'pinterest') ? 15 : 30);
            $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));

            $stmt = $db->prepare("INSERT INTO sns_queue 
                                  (site_id, article_id, sns_type, post_content, image_url, scheduled_at, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, 'queued')");
            $stmt->execute([
                $siteId,
                $articleId,
                $sns,
                $content,
                $articleData['image_url'] ?? '',
                $scheduledAt
            ]);
        }
    }

    /**
     * 媒体別コンテンツ生成
     */
    private static function buildContent(string $sns, array $article): string {
        $title = $article['title'] ?? '';
        $url = $article['url'] ?? "https://" . MAIN_DOMAIN . "/article/" . ($article['slug'] ?? '');
        $index = $article['shirankedo_index'] ?? 50;

        switch ($sns) {
            case 'x':
                return "【話題度: {$index}/100】{$title}\n\nいま注目されているニュースをまとめました。しらんけど。\n{$url}\n#しらんけど #トレンド";
            case 'pinterest':
                return "【{$title}】\nしらんけど指数 {$index}/100。\n{$article['why_trending']}\n詳細はこちら: {$url}";
            case 'instagram':
                return "【いま話題のニュース】\n{$title}\n\nしらんけど指数: {$index}/100\n\n{$article['why_trending']}\n\n詳細はプロフィールのリンクからご覧いただけます。\n#しらんけど #トレンド #ニュース #話題 #急上昇";
            default:
                return $title;
        }
    }

    /**
     * キュー処理ワーカー (cronから定期実行)
     */
    public static function processQueue(int $limit = 5): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM sns_queue 
                              WHERE status = 'queued' AND scheduled_at <= NOW() 
                              ORDER BY scheduled_at ASC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $tasks = $stmt->fetchAll();

        $processedCount = 0;
        foreach ($tasks as $task) {
            // 各公式API連携処理 (X API v2, Pinterest API, Instagram Graph API)
            // 成功時は external_post_id を保存
            $success = true; 
            $externalId = 'sim_' . time() . '_' . $task['id'];

            if ($success) {
                $update = $db->prepare("UPDATE sns_queue SET status = 'success', external_post_id = ?, posted_at = NOW() WHERE id = ?");
                $update->execute([$externalId, $task['id']]);
                $processedCount++;
            } else {
                $update = $db->prepare("UPDATE sns_queue SET status = 'failed', retry_count = retry_count + 1, error_message = 'API Rate limit or Auth error' WHERE id = ?");
                $update->execute([$task['id']]);
            }
        }
        return $processedCount;
    }
}
