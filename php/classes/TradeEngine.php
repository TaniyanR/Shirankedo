<?php
/**
 * 相互リンク・相互RSS返還エンジン (TradeEngine.php)
 * - 逆アクセスの記録 (INカウント)
 * - アクセス返還率（80%, 100%, 120%, 150%など）と特別優遇枠に応じた重み付け出稿計算
 * - RSSフィードの自動取得＆パース（画像付き判定・テキストのみ分離）
 * - 配信クリック時のOUTトラッキング
 */
require_once __DIR__ . '/Database.php';

class TradeEngine {
    /**
     * 流入（INアクセス）を検知してカウント
     */
    public static function trackIncomingReferrer(): void {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (empty($referer)) return;

        $refHost = parse_url($referer, PHP_URL_HOST);
        if (empty($refHost)) return;

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM trade_sites WHERE status = 'approved' AND (url LIKE ? OR url LIKE ?) LIMIT 1");
            $like1 = "%://" . $refHost . "/%";
            $like2 = "%://" . $refHost;
            $stmt->execute([$like1, $like2]);
            $site = $stmt->fetch();

            if ($site) {
                $update = $db->prepare("UPDATE trade_sites SET in_count = in_count + 1, today_in = today_in + 1, last_in_at = NOW() WHERE id = ?");
                $update->execute([$site['id']]);
            }
        } catch (Throwable $e) {
            // エラー時は静かに握りつぶす
        }
    }

    /**
     * 重み付け確率（返還率×INアクセス数＋特別優遇ブースト）に基づいて、表示するRSSフィード記事を取得
     * 
     * @param bool $requireImage 画像必須かどうか (PCヘッダー下/サイド/フッター上などはtrue, 本文下やスマホテキストはfalse)
     * @param int $limit 取得件数
     * @return array
     */
    public static function getDisplayFeedItems(bool $requireImage = false, int $limit = 5): array {
        try {
            $db = Database::getConnection();
            
            // 承認済みサイトのリストと返還比率を取得
            $sites = $db->query("SELECT id, site_name, url, return_rate, is_boosted, boost_weight, in_count, out_count FROM trade_sites WHERE status = 'approved'")->fetchAll();
            if (empty($sites)) {
                return self::getFallbackFeedItems($requireImage, $limit);
            }

            // スコア算出 (IN数 * 返還率% * ブースト係数 - OUTペナルティ)
            // 目標: 相手がくれた分(返還率考慮)だけ返す
            $weightedSiteIds = [];
            foreach ($sites as $s) {
                $targetOut = max(1, round(($s['in_count'] * ($s['return_rate'] / 100))));
                // 優遇枠の場合はウェイトを加算
                if ($s['is_boosted']) {
                    $targetOut *= max(2, (int)$s['boost_weight']);
                }
                
                // 還元残高 = 送るべきアクセス数 - 既に送ったアクセス数
                $balance = $targetOut - $s['out_count'];
                $weight = max(1, $balance > 0 ? $balance : 1);
                
                // 重み付けプールにIDを詰める（最大50でキャップ）
                $poolEntries = min(50, (int)$weight);
                for ($i = 0; $i < $poolEntries; $i++) {
                    $weightedSiteIds[] = $s['id'];
                }
            }

            // ランダムに選定
            shuffle($weightedSiteIds);
            $selectedSiteIds = array_slice(array_unique($weightedSiteIds), 0, $limit * 2);
            if (empty($selectedSiteIds)) {
                $selectedSiteIds = array_column($sites, 'id');
            }

            $inPlaceholders = implode(',', array_fill(0, count($selectedSiteIds), '?'));
            $sql = "SELECT i.*, s.site_name 
                    FROM trade_feed_items i
                    JOIN trade_sites s ON i.trade_site_id = s.id
                    WHERE i.trade_site_id IN ($inPlaceholders) AND s.status = 'approved'";
            if ($requireImage) {
                $sql .= " AND i.has_image = 1 AND i.image_url IS NOT NULL AND i.image_url != ''";
            }
            $sql .= " ORDER BY i.published_at DESC LIMIT " . (int)$limit;

            $stmt = $db->prepare($sql);
            $stmt->execute($selectedSiteIds);
            $items = $stmt->fetchAll();

            if (empty($items)) {
                return self::getFallbackFeedItems($requireImage, $limit);
            }

            return $items;
        } catch (Throwable $e) {
            return self::getFallbackFeedItems($requireImage, $limit);
        }
    }

    /**
     * OUTクリックスルーURL生成
     */
    public static function getOutboundLink(int $tradeSiteId, string $targetUrl): string {
        return "out.php?site_id=" . $tradeSiteId . "&url=" . urlencode($targetUrl);
    }

    /**
     * 承認済み全サイトのリンク一覧（PC専用 相互リンク集）
     */
    public static function getApprovedLinks(): array {
        try {
            $db = Database::getConnection();
            return $db->query("SELECT id, site_name, url FROM trade_sites WHERE status = 'approved' ORDER BY in_count DESC, id ASC")->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * 初期デモ用フィードアイテム（相互RSS未登録時）
     */
    private static function getFallbackFeedItems(bool $requireImage, int $limit): array {
        $fallbacks = [
            [
                'id' => 1,
                'trade_site_id' => 0,
                'site_name' => 'トレンドニュース速報',
                'title' => '【話題】今年流行りの最新便利グッズBEST5が発表！',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2,
                'trade_site_id' => 0,
                'site_name' => 'エンタメまとめch',
                'title' => '深夜の突撃ロケ企画でハプニング連発、ネット騒然！',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 3,
                'trade_site_id' => 0,
                'site_name' => 'ゲームギーク速報',
                'title' => '全世界待望の新作アクションRPG、配信直後に歴代同接記録を更新',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 4,
                'trade_site_id' => 0,
                'site_name' => 'ネットの噂アンテナ',
                'title' => '会話を丸く収めるクッション言葉「しらんけど」の威力',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 5,
                'trade_site_id' => 0,
                'site_name' => 'カルチャーラボ',
                'title' => '話題のAIツールを使った創作活動が急速に拡大中',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ]
        ];

        if ($requireImage) {
            return array_slice(array_filter($fallbacks, fn($x) => $x['has_image']), 0, $limit);
        }
        return array_slice($fallbacks, 0, $limit);
    }
}
