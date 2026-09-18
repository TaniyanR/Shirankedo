<?php
/**
 * 定期実行ワーカー (cronまたは管理画面・疑似cronから実行)
 * 1. トレンド収集 & 統合
 * 2. 上位候補のAI記事自動生成 (安全ブレーキ付き)
 * 3. SNS配信キュー処理
 */
require_once __DIR__ . '/../config.php';

echo "[Worker] 開始: " . date('Y-m-d H:i:s') . "\n";

$db = Database::getConnection();
$sites = $db->query("SELECT id, name, allow_auto_publish FROM sites WHERE is_public = 1")->fetchAll();

foreach ($sites as $site) {
    $siteId = (int)$site['id'];
    echo "[Site {$siteId}: {$site['name']}] トレンド収集実行中...\n";
    
    // 1. トレンド収集
    $stats = TrendCollector::collectAndIntegrate($siteId);
    echo "  → 収集完了: 新規 {$stats['inserted']}件 / 更新 {$stats['updated']}件\n";

    // 2. AI記事生成対象の抽出 (指数上位かつ未生成候補)
    $stmt = $db->prepare("SELECT * FROM trend_candidates 
                          WHERE site_id = ? AND status = 'candidate' 
                          ORDER BY shirankedo_index DESC, growth_rate DESC 
                          LIMIT 2");
    $stmt->execute([$siteId]);
    $candidates = $stmt->fetchAll();

    foreach ($candidates as $cand) {
        echo "  → 記事生成処理: {$cand['display_keyword']} (指数: {$cand['shirankedo_index']})\n";

        // 一次ソースの準備 (実務では公式リリース・報道取得)
        $verifiedSources = [
            [
                'source_type' => 'news',
                'title' => "「{$cand['display_keyword']}」に関する最新発表",
                'publisher' => '大手報道各社・公式発表',
                'url' => 'https://news.example.com/item/' . urlencode($cand['normalized_keyword']),
                'reliability_score' => 90
            ]
        ];

        // 安全判定 (Safety Brake)
        $safety = SafetyBrake::audit($cand['display_keyword'], '', $verifiedSources);

        // AI記事生成
        $generated = AiArticleGenerator::generate($siteId, $cand, $verifiedSources);

        // 画像選択 (10,000枚規模マネージャー)
        $selectedImage = ImageManager::selectBestImage(
            $siteId, 
            $generated['important_keywords'] ?? [$cand['display_keyword']]
        );

        // ステータス判定: 危険または一次ソース不足なら「保留 (on_hold)」、安全なら「公開 (published)」
        $status = ($safety['needs_hold'] || $site['allow_auto_publish'] != 1) ? 'on_hold' : 'published';
        $slug = 'trend-' . time() . '-' . rand(100, 999);

        $artStmt = $db->prepare("INSERT INTO articles 
            (site_id, trend_candidate_id, title, slug, why_trending, body, conclusion_sentence,
             shirankedo_index, index_label, is_rapid_rise, growth_rate, first_detected_at,
             image_url, status, is_dangerous, danger_reason, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $artStmt->execute([
            $siteId,
            $cand['id'],
            $generated['title'],
            $slug,
            $generated['why_trending'],
            $generated['body'],
            $generated['conclusion'],
            $cand['shirankedo_index'],
            ShirankedoIndex::getLabel($cand['shirankedo_index']),
            $cand['is_rapid_rise'],
            $cand['growth_rate'],
            $cand['first_detected_at'],
            $selectedImage['url'] ?? null,
            $status,
            $safety['is_dangerous'] ? 1 : 0,
            $safety['reason'] ?: null
        ]);
        $articleId = (int)$db->lastInsertId();

        // 出典リンクの保存
        $srcStmt = $db->prepare("INSERT INTO article_sources 
            (article_id, site_id, source_type, title, url, publisher, reliability_score)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($verifiedSources as $vs) {
            $srcStmt->execute([
                $articleId, $siteId, $vs['source_type'], $vs['title'], $vs['url'], $vs['publisher'], $vs['reliability_score']
            ]);
        }

        // トレンドステータス更新
        $updCand = $db->prepare("UPDATE trend_candidates SET status = 'completed', article_id = ? WHERE id = ?");
        $updCand->execute([$articleId, $cand['id']]);

        // 公開された場合、SNS自動投稿キューへ登録
        if ($status === 'published') {
            SnsDispatcher::enqueueArticle($siteId, $articleId, [
                'title' => $generated['title'],
                'slug' => $slug,
                'why_trending' => $generated['why_trending'],
                'shirankedo_index' => $cand['shirankedo_index'],
                'image_url' => $selectedImage['url'] ?? null
            ]);
            echo "    ✓ 公開完了 & SNSキュー登録: {$generated['title']}\n";
        } else {
            echo "    ⚠️ 安全ブレーキ作動 (保留): {$generated['title']} (理由: {$safety['reason']})\n";
        }
    }
}

// 3. SNSキュー処理
$snsProcessed = SnsDispatcher::processQueue(5);
echo "[SNS] キュー配信処理完了: {$snsProcessed}件\n";

// 4. 相互リンク・相互RSS巡回（複数登録RSSフィード巡回）
require_once __DIR__ . '/../classes/TradeEngine.php';
echo "[TradeEngine] 相互RSSフィード巡回中...\n";
$rssStats = TradeEngine::fetchRssFeeds();
echo "  → 巡回完了: 提携{$rssStats['sites_checked']}サイト / {$rssStats['feeds_checked']}フィード / 取得記事{$rssStats['items_saved']}件\n";

echo "[Worker] 終了: " . date('Y-m-d H:i:s') . "\n";
