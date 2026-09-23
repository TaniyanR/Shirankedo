<?php
/**
 * 定期実行ワーカー (cronまたは管理画面・疑似cronから実行)
 * 1. トレンド収集 & 統合
 * 2. 上位候補のAI記事自動生成 (安全ブレーキ付き)
 * 3. SNS配信キュー処理
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/SettingsManager.php';

// 出力バッファリング開始（ログ保存用）
ob_start();

$isCli = (php_sapi_name() === 'cli');
$isForce = isset($_GET['force']) || (isset($argv) && in_array('--force', $argv)) || (!empty($forceExecute));

echo "[Worker] 実行開始: " . date('Y-m-d H:i:s') . ($isForce ? " (強制実行モード)" : "") . "\n";

$db = Database::getConnection();

// サイトテーブル初期化の自己修復 (未登録の場合自動作成)
try {
    $sites = $db->query("SELECT id, name, allow_auto_publish FROM sites WHERE is_public = 1")->fetchAll();
    if (empty($sites)) {
        $db->exec("INSERT INTO sites (id, subdomain, name, description, genre, is_public, allow_auto_publish) 
                   VALUES (1, '', 'しらんけど', 'いま話題のトレンドを客観一次情報とともにまとめ。しらんけど。', 'general', 1, 1)
                   ON DUPLICATE KEY UPDATE is_public = 1, allow_auto_publish = 1");
        $sites = $db->query("SELECT id, name, allow_auto_publish FROM sites WHERE is_public = 1")->fetchAll();
        echo "  [Worker初期化] デフォルトサイト(ID:1)を自動構成しました。\n";
    }

    // カテゴリ初期化
    $catCount = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCount === 0) {
        $db->exec("INSERT INTO categories (id, site_id, slug, name, sort_order) VALUES
            (1, 1, 'all', '総合トレンド', 1),
            (2, 1, 'entertainment', 'エンタメ・お笑い', 2),
            (3, 1, 'trend', '話題・SNS', 3),
            (4, 1, 'game', 'ゲーム・新作', 4),
            (5, 1, 'it', 'IT・ネット速報', 5)
            ON DUPLICATE KEY UPDATE name=VALUES(name)");
        echo "  [Worker初期化] 基本カテゴリを自動生成しました。\n";
    }
} catch (Throwable $e) {
    echo "  [Workerエラー] DB初期化時: " . $e->getMessage() . "\n";
    $sites = [];
}

// 自動投稿コントロール設定の取得
$autoPostEnabled = SettingsManager::get('auto_post_enabled', '1') === '1';
$intervalHours = (float)SettingsManager::get('auto_post_interval_hours', '1');
$maxPerDay = (int)SettingsManager::get('auto_post_max_per_day', '10');
$startHour = (int)SettingsManager::get('auto_post_start_hour', '8');
$endHour = (int)SettingsManager::get('auto_post_end_hour', '23');
$defaultStatus = SettingsManager::get('auto_post_default_status', 'published');

$currentHour = (int)date('G'); // 0〜23
$canGenerateArticles = true;
$skipReason = '';

if (!$isForce) {
    if (!$autoPostEnabled) {
        $canGenerateArticles = false;
        $skipReason = 'AI自動投稿が無効化（OFF）に設定されています。';
    } elseif ($startHour <= $endHour) {
        if ($currentHour < $startHour || $currentHour > $endHour) {
            $canGenerateArticles = false;
            $skipReason = "稼働時間外です（設定許可時間帯: {$startHour}時〜{$endHour}時、現在: {$currentHour}時）。";
        }
    } else {
        // 日をまたぐ設定
        if ($currentHour < $startHour && $currentHour > $endHour) {
            $canGenerateArticles = false;
            $skipReason = "稼働時間外です（設定許可時間帯: {$startHour}時〜翌{$endHour}時、現在: {$currentHour}時）。";
        }
    }

    // 1日の上限本数のチェック
    if ($canGenerateArticles && $maxPerDay > 0) {
        $todayCount = (int)$db->query("SELECT COUNT(*) FROM articles WHERE DATE(published_at) = CURDATE()")->fetchColumn();
        if ($todayCount >= $maxPerDay) {
            $canGenerateArticles = false;
            $skipReason = "本日の投稿上限（{$maxPerDay}本）に達しています（本日実績: {$todayCount}本）。";
        }
    }

    // 投稿間隔のチェック
    if ($canGenerateArticles && $intervalHours > 0) {
        $lastPostTime = $db->query("SELECT published_at FROM articles ORDER BY published_at DESC LIMIT 1")->fetchColumn();
        if ($lastPostTime) {
            $diffMinutes = (time() - strtotime($lastPostTime)) / 60;
            $requiredMinutes = $intervalHours * 60;
            if ($diffMinutes < $requiredMinutes) {
                $canGenerateArticles = false;
                $remMinutes = round($requiredMinutes - $diffMinutes);
                $skipReason = "前回投稿からまだ " . round($diffMinutes) . "分しか経過していません（設定間隔: {$intervalHours}時間 = {$requiredMinutes}分、次回可能まで約 {$remMinutes}分）。";
            }
        }
    }
} else {
    echo "  [Worker] 手動/強制モードのため、時間帯・投稿間隔チェックをバイパスします。\n";
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

    // 2. AI記事生成対象の抽出 (1回につき1件生成)
    $stmt = $db->prepare("SELECT * FROM trend_candidates 
                          WHERE site_id = ? AND status = 'candidate' 
                          ORDER BY shirankedo_index DESC, growth_rate DESC 
                          LIMIT 1");
    $stmt->execute([$siteId]);
    $candidates = $stmt->fetchAll();

    // 未処理候補がない場合、完了済みから再活用またはシード追加
    if (empty($candidates)) {
        echo "  [Worker] 未生成のトレンド候補がないため、最新の急上昇候補をリフレッシュします。\n";
        $db->exec("UPDATE trend_candidates SET status = 'candidate' WHERE site_id = {$siteId} ORDER BY last_updated_at DESC LIMIT 2");
        $stmt->execute([$siteId]);
        $candidates = $stmt->fetchAll();
    }

    foreach ($candidates as $cand) {
        echo "  → AI記事生成処理開始: 「{$cand['display_keyword']}」 (しらんけど指数: {$cand['shirankedo_index']})\n";

        // 一次ソースの準備
        $verifiedSources = [
            [
                'source_type' => 'news',
                'title' => "「{$cand['display_keyword']}」に関する主要メディア報道・発表",
                'publisher' => 'Googleニュース / 大手報道各社',
                'url' => 'https://news.google.com/search?q=' . urlencode($cand['display_keyword']) . '&hl=ja&gl=JP&ceid=JP:ja',
                'reliability_score' => 90
            ]
        ];

        // 安全判定 (Safety Brake)
        $safety = SafetyBrake::audit($cand['display_keyword'], '', $verifiedSources);

        // AI記事生成
        try {
            $generated = AiArticleGenerator::generate($siteId, $cand, $verifiedSources);
        } catch (Throwable $ge) {
            echo "    [AI生成例外]: " . $ge->getMessage() . "\n";
            continue;
        }

        // 画像選択 (10,000枚規模マネージャー)
        $selectedImage = ImageManager::selectBestImage(
            $siteId, 
            $generated['important_keywords'] ?? [$cand['display_keyword']]
        );
        $imgUrl = !empty($selectedImage['url']) ? trim($selectedImage['url']) : null;
        $hasImage = !empty($imgUrl);

        // ステータス判定
        $status = ($safety['needs_hold'] || $site['allow_auto_publish'] != 1 || $defaultStatus === 'on_hold' || !$hasImage) ? 'on_hold' : 'published';
        $slug = 'trend-' . time() . '-' . rand(100, 999);
        $dangerReason = $safety['is_dangerous'] 
            ? ($safety['reason'] ?: 'AI検閲: 危険ワード検知') 
            : (!$hasImage ? 'アイキャッチ画像未設定（画像設定後に表へ公開）' : ($safety['reason'] ?: null));

        $artStmt = $db->prepare("INSERT INTO articles 
            (site_id, category_id, trend_candidate_id, title, slug, why_trending, body, conclusion_sentence,
             shirankedo_index, index_label, is_rapid_rise, growth_rate, first_detected_at,
             image_url, status, is_dangerous, danger_reason, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $artStmt->execute([
            $siteId,
            1, // デフォルト総合カテゴリ
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
        } else {
            echo "    ⚠️ 【安全保留】 記事ID #{$articleId}: 「{$generated['title']}」 (理由: " . ($dangerReason ?: '下書き設定') . ")\n";
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

