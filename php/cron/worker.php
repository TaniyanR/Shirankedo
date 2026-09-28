<?php
/**
 * 定期実行ワーカー (cronまたは管理画面・疑似cronから実行)
 * 1. トレンド収集 & 統合
 * 2. 上位候補のAI記事自動生成 (安全ブレーキ付き)
 * 3. SNS配信キュー処理
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/SettingsManager.php';
require_once __DIR__ . '/../classes/NewsSourceCollector.php';

// 出力バッファリング開始（ログ保存用）
ob_start();

$isCli = (php_sapi_name() === 'cli');
$isForce = isset($_GET['force']) || (isset($argv) && in_array('--force', $argv)) || (!empty($forceExecute));

echo "[Worker] 実行開始: " . date('Y-m-d H:i:s') . ($isForce ? " (強制実行モード)" : "") . "\n";

$db = Database::getConnection();

// サイトテーブル初期化の自己修復 (未登録の場合自動作成)
try {
    $sites = $db->query("SELECT id, name FROM sites")->fetchAll();
    if (empty($sites)) {
        $db->exec("INSERT INTO sites (id, subdomain, name, description, genre, is_public, allow_auto_publish)
                   VALUES (1, '', 'しらんけど', 'いま話題のトレンドを客観一次情報とともにまとめ。しらんけど。', 'general', 1, 1)
                   ON DUPLICATE KEY UPDATE name = VALUES(name)");
        $sites = $db->query("SELECT id, name FROM sites")->fetchAll();
        echo "  [Worker初期化] デフォルトサイト(ID:1)を自動構成しました。\n";
    }

    // カテゴリ初期化
    $catCount = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCount === 0) {
        $db->exec("INSERT INTO categories (id, site_id, slug, name, sort_order) VALUES
            (1, 1, 'all', '総合', 1),
            (2, 1, 'entertainment', 'エンタメ', 2),
            (3, 1, 'sports', 'スポーツ', 3),
            (4, 1, 'tech', 'テクノロジー', 4),
            (5, 1, 'anime', 'アニメ・マンガ', 5),
            (6, 1, 'game', 'ゲーム', 6),
            (7, 1, 'social', '時事・社会', 7),
            (8, 1, 'gourmet', 'グルメ', 8)
            ON DUPLICATE KEY UPDATE name=VALUES(name), slug=VALUES(slug), sort_order=VALUES(sort_order)");
        echo "  [Worker初期化] 基本カテゴリを自動生成しました。\n";
    }
} catch (Throwable $e) {
    echo "  [Workerエラー] DB初期化時: " . $e->getMessage() . "\n";
    $sites = [];
}

// 自動投稿コントロール設定の取得
$autoPostEnabled = SettingsManager::get('auto_post_enabled', '1') === '1';
$intervalHours = (float)SettingsManager::get('auto_post_interval_hours', '1');
$canGenerateArticles = true;
$skipReason = '';

if (!$isForce) {
    if (!$autoPostEnabled) {
        $canGenerateArticles = false;
        $skipReason = 'AI自動投稿が無効化（OFF）に設定されています。';
    }

    // 投稿間隔だけを自動投稿の制御条件にする。
    if ($canGenerateArticles && $intervalHours > 0) {
        $lastPostTime = $db->query("SELECT published_at FROM articles WHERE published_at IS NOT NULL ORDER BY published_at DESC LIMIT 1")->fetchColumn();
        if ($lastPostTime) {
            $diffMinutes = (time() - strtotime($lastPostTime)) / 60;
            $requiredMinutes = $intervalHours * 60;
            if ($diffMinutes < $requiredMinutes) {
                $canGenerateArticles = false;
                $remMinutes = max(1, round($requiredMinutes - $diffMinutes));
                $skipReason = "前回投稿からの設定間隔（{$intervalHours}時間）待ちです。次回可能まで約 {$remMinutes}分。";
            }
        }
    }
} else {
    echo "  [Worker] 手動/強制モードのため、投稿間隔チェックをバイパスします。\n";
}

foreach ($sites as $site) {
    $siteId = (int)$site['id'];
    echo "[Site {$siteId}: {$site['name']}] トレンド収集実行中...\n";
    
    // 1. トレンド収集
    try {
        $stats = TrendCollector::collectAndIntegrate($siteId);
        echo "  → トレンド収集完了: 新規 {$stats['inserted']}件 / 更新 {$stats['updated']}件\n";
    } catch (Throwable $te) {
        echo "  → トレンド収集例外: " . $te->getMessage() . "\n";
    }

    // 2. AI記事生成の可否判定
    if (!$canGenerateArticles) {
        echo "  [AI記事自動生成スキップ] {$skipReason}\n";
        continue;
    }

    // 2. AI記事生成対象の抽出
    // 完了済み候補は再利用せず、直近記事と重複しにくい未生成候補から選ぶ。
    $stmt = $db->prepare("SELECT tc.*
                          FROM trend_candidates tc
                          WHERE tc.site_id = ?
                            AND tc.status = 'candidate'
                            AND NOT EXISTS (
                                SELECT 1 FROM articles a
                                WHERE a.site_id = tc.site_id
                                  AND a.trend_candidate_id = tc.id
                            )
                          ORDER BY tc.is_rapid_rise DESC,
                                   tc.shirankedo_index DESC,
                                   tc.growth_rate DESC,
                                   tc.last_updated_at DESC
                          LIMIT 12");
    $stmt->execute([$siteId]);
    $candidatePool = $stmt->fetchAll();
    $candidates = [];

    if (!empty($candidatePool)) {
        $recentStmt = $db->prepare("SELECT title FROM articles
                                    WHERE site_id = ?
                                      AND published_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                                    ORDER BY published_at DESC
                                    LIMIT 20");
        $recentStmt->execute([$siteId]);
        $recentText = mb_strtolower(implode(' ', array_column($recentStmt->fetchAll(), 'title')));

        foreach ($candidatePool as $candidate) {
            $kw = trim((string)($candidate['display_keyword'] ?? ''));
            if ($kw === '') continue;
            if ($recentText !== '' && mb_strlen($kw) >= 3 && mb_strpos($recentText, mb_strtolower($kw)) !== false) {
                continue;
            }
            $candidates[] = $candidate;
            break;
        }

        if (empty($candidates)) {
            $candidates[] = $candidatePool[0];
        }
    }

    if (empty($candidates)) {
        echo "  [Worker] 新しく記事化できる未生成トレンド候補がありません。完了済み候補の使い回しは行いません。\n";
        continue;
    }

    foreach ($candidates as $cand) {
        echo "  → AI記事生成処理開始: 「{$cand['display_keyword']}」 (しらんけど指数: {$cand['shirankedo_index']})\n";

        // 実際の参照ページを複数取得
        $verifiedSources = NewsSourceCollector::collect($cand['display_keyword'], 5);
        if (empty($verifiedSources)) {
            echo "    [記事生成スキップ] 参照ページを取得できなかったため、推測記事は作成しません。\n";
            continue;
        }

        // 安全判定 (Safety Brake)
        $safety = SafetyBrake::audit($cand['display_keyword'], '', $verifiedSources);
        if (!empty($safety['needs_hold']) || !empty($safety['is_dangerous'])) {
            $db->prepare("UPDATE trend_candidates SET status = 'ignored' WHERE id = ?")->execute([$cand['id']]);
            echo "    [記事生成スキップ] 公開できない安全判定のため記事は保存しません。\n";
            continue;
        }

        // AI記事生成
        try {
            $generated = AiArticleGenerator::generate($siteId, $cand, $verifiedSources);
        } catch (Throwable $ge) {
            echo "    [AI生成例外]: " . $ge->getMessage() . "\n";
            continue;
        }

        if (($generated['_generation_mode'] ?? 'fallback') !== 'ai') {
            $geminiError = SettingsManager::get('gemini_last_error', 'Gemini APIから正常な記事を取得できませんでした');
            echo "    [記事生成スキップ] Gemini生成失敗: {$geminiError}\n";
            continue;
        }

        // 画像選択 (10,000枚規模マネージャー)
        $selectedImage = ImageManager::selectBestImage(
            $siteId, 
            $generated['important_keywords'] ?? [$cand['display_keyword']]
        );
        $imgUrl = !empty($selectedImage['url']) ? trim($selectedImage['url']) : null;
        $hasImage = !empty($imgUrl);

        if (!$hasImage) {
            $db->prepare("UPDATE trend_candidates SET status = 'ignored' WHERE id = ?")->execute([$cand['id']]);
            echo "    [記事生成スキップ] アイキャッチ画像を選定できなかったため記事は保存しません。\n";
            continue;
        }

        // 公開できる品質の記事だけDBへ保存する。
        $status = 'published';
        $slug = 'trend-' . time() . '-' . rand(100, 999);
        $dangerReason = null;

        // AIが選んだカテゴリへ自動振り分け。
        $categorySlug = $generated['category_slug'] ?? 'all';
        $allowedCategorySlugs = ['all', 'entertainment', 'sports', 'tech', 'anime', 'game', 'social', 'gourmet'];
        if (!in_array($categorySlug, $allowedCategorySlugs, true)) {
            $categorySlug = 'all';
        }
        $catStmt = $db->prepare("SELECT id FROM categories WHERE site_id = ? AND slug = ? LIMIT 1");
        $catStmt->execute([$siteId, $categorySlug]);
        $categoryId = (int)($catStmt->fetchColumn() ?: 1);

        $artStmt = $db->prepare("INSERT INTO articles 
            (site_id, category_id, trend_candidate_id, title, slug, why_trending, body, conclusion_sentence,
             shirankedo_index, index_label, is_rapid_rise, growth_rate, first_detected_at,
             image_url, status, is_dangerous, danger_reason, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $artStmt->execute([
            $siteId,
            $categoryId,
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
            $imgUrl,
            $status,
            $safety['is_dangerous'] ? 1 : 0,
            $dangerReason
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
            try {
                SnsDispatcher::enqueueArticle($siteId, $articleId, [
                    'title' => $generated['title'],
                    'slug' => $slug,
                    'why_trending' => $generated['why_trending'],
                    'shirankedo_index' => $cand['shirankedo_index'],
                    'image_url' => $selectedImage['url'] ?? null
                ]);
            } catch (Throwable $se) {}
            echo "    ✓ 【公開完了】 記事ID #{$articleId}: 「{$generated['title']}」\n";
        }
    }
}

// 3. SNSキュー処理
try {
    $snsProcessed = SnsDispatcher::processQueue(5);
    echo "[SNS] キュー配信処理完了: {$snsProcessed}件\n";
} catch (Throwable $e) {}

// 4. 相互リンク・相互RSS巡回
try {
    require_once __DIR__ . '/../classes/TradeEngine.php';
    echo "[TradeEngine] 相互RSSフィード巡回中...\n";
    $rssStats = TradeEngine::fetchRssFeeds();
    echo "  → 巡回完了: 提携{$rssStats['sites_checked']}サイト / {$rssStats['feeds_checked']}フィード / 取得記事{$rssStats['items_saved']}件\n";
} catch (Throwable $e) {}

echo "[Worker] 正常終了: " . date('Y-m-d H:i:s') . "\n";

// 出力ログ保存
$capturedLog = ob_get_clean();
echo $capturedLog;

try {
    SettingsManager::set('last_cron_executed_at', date('Y-m-d H:i:s'));
    SettingsManager::set('last_cron_log', mb_substr($capturedLog, 0, 4000));
    SettingsManager::set('last_cron_status', 'OK');

    $logFile = __DIR__ . '/worker.log';
    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "]\n" . $capturedLog . "\n--------------------\n", FILE_APPEND);
} catch (Throwable $e) {}

