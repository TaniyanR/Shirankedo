<?php
/**
 * 「しらんけど」単独稼働 PHPフロントエンド & API
 * レンタルサーバー（Apache + PHP + MySQL）でそのまま完璧に動作するフロントエンドです。
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/SettingsManager.php';
require_once __DIR__ . '/classes/TradeEngine.php';
require_once __DIR__ . '/classes/AnalyticsTracker.php';

// アクセス解析トラッキング & 相互リンク逆アクセスの自動記録
TradeEngine::trackIncomingReferrer();
AnalyticsTracker::track('home');

// DB接続チェック
$dbConnected = false;
$dbError = '';
try {
    $db = Database::getConnection();
    $dbConnected = true;

    // テーブルの存在保証
    try {
        MigrationAddFeatures::run();
    } catch (Throwable $ignore) {}
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// サブドメイン解決
$host = $_SERVER['HTTP_HOST'] ?? '';
$subdomain = '';
if (preg_match('/^([a-zA-Z0-9_-]+)\./', $host, $matches)) {
    if (!in_array($matches[1], ['www', 'mail', 'ftp', 'admin'])) {
        $subdomain = $matches[1];
    }
}
$site = null;
if ($dbConnected) {
    try {
        $site = SiteManager::resolveCurrentSite($subdomain);
    } catch (Throwable $e) {}
}

// --- APIエンドポイント処理 (投票・コメント) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$dbConnected) {
        echo json_encode(['success' => false, 'error' => 'Database not connected']);
        exit;
    }

    $action = $_POST['action'];

    // 1. 投票
    if ($action === 'vote') {
        $articleId = (int)($_POST['article_id'] ?? 0);
        $voteType = $_POST['vote_type'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $voterHash = hash('sha256', $ip . '_' . date('Y-m-d'));

        if (!in_array($voteType, ['believed', 'skeptical'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid vote type']);
            exit;
        }

        try {
            $checkStmt = $db->prepare("SELECT id FROM votes WHERE article_id = ? AND voter_hash = ?");
            $checkStmt->execute([$articleId, $voterHash]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'error' => '既に本日投票済みです']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO votes (article_id, vote_type, voter_hash, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$articleId, $voteType, $voterHash, $ip]);

            // 最新集計値返却
            $countStmt = $db->prepare("SELECT 
                SUM(CASE WHEN vote_type = 'believed' THEN 1 ELSE 0 END) as believed,
                SUM(CASE WHEN vote_type = 'skeptical' THEN 1 ELSE 0 END) as skeptical
                FROM votes WHERE article_id = ?");
            $countStmt->execute([$articleId]);
            $counts = $countStmt->fetch();

            echo json_encode(['success' => true, 'counts' => $counts]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 2. コメント投稿
    if ($action === 'comment') {
        $articleId = (int)($_POST['article_id'] ?? 0);
        $author = trim($_POST['author_name'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (empty($body)) {
            echo json_encode(['success' => false, 'error' => 'コメント本文を入力してください']);
            exit;
        }

        try {
            $check = SafetyBrake::auditComment($body);
            if (!$check['safe']) {
                echo json_encode(['success' => false, 'error' => '不適切な表現が含まれているため投稿できませんでした（' . $check['reason'] . '）']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO comments (article_id, author_name, body, ip_address, status) VALUES (?, ?, ?, ?, 'approved')");
            $stmt->execute([$articleId, $author ?: '名無しさん', $body, $ip]);

            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// 記事一覧・カテゴリ一覧の取得
$articles = [];
$categories = [];
$selectedCategory = $_GET['cat'] ?? 'all';

if ($dbConnected) {
    try {
        $catStmt = $db->prepare("SELECT * FROM categories WHERE site_id = ? ORDER BY sort_order ASC");
        $catStmt->execute([$site['id'] ?? 1]);
        $categories = $catStmt->fetchAll();

        $whereSql = "WHERE a.site_id = ? AND a.status = 'published'";
        $params = [$site['id'] ?? 1];

        if ($selectedCategory !== 'all') {
            $whereSql .= " AND c.slug = ?";
            $params[] = $selectedCategory;
        }

        $artSql = "SELECT a.*, c.name as category_name, c.slug as category_slug,
                          (SELECT url FROM images WHERE id = a.thumbnail_image_id LIMIT 1) as custom_image_url,
                          (SELECT COUNT(*) FROM votes WHERE article_id = a.id AND vote_type = 'believed') as vote_believed,
                          (SELECT COUNT(*) FROM votes WHERE article_id = a.id AND vote_type = 'skeptical') as vote_skeptical,
                          (SELECT COUNT(*) FROM comments WHERE article_id = a.id AND status = 'approved') as comment_count
                   FROM articles a
                   LEFT JOIN categories c ON a.category_id = c.id
                   {$whereSql}
                   ORDER BY a.published_at DESC LIMIT 30";
        try {
            $artStmt = $db->prepare($artSql);
            $artStmt->execute($params);
            $articles = $artStmt->fetchAll();
        } catch (Throwable $subEx) {
            $fallbackSql = "SELECT a.*, c.name as category_name, c.slug as category_slug,
                                   0 as vote_believed, 0 as vote_skeptical, 0 as comment_count
                            FROM articles a
                            LEFT JOIN categories c ON a.category_id = c.id
                            {$whereSql}
                            ORDER BY a.published_at DESC LIMIT 30";
            $fbStmt = $db->prepare($fallbackSql);
            $fbStmt->execute($params);
            $articles = $fbStmt->fetchAll();
        }
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

// 表示切り替えフラグ
$showAds = SettingsManager::get('show_ads', '1') === '1';
$showRss = SettingsManager::get('show_rss', '1') === '1';

// 広告スロット設定と個別枠ごとの表示/非表示フラグ
$adPcHeaderEnabled = $showAds && SettingsManager::get('ad_pc_header_enabled', '1') === '1';
$adPcSidebarTopEnabled = $showAds && SettingsManager::get('ad_pc_sidebar_top_enabled', '1') === '1';
$adPcSidebarBottomEnabled = $showAds && SettingsManager::get('ad_pc_sidebar_bottom_enabled', '1') === '1';
$adSpHeaderTopEnabled = $showAds && SettingsManager::get('ad_sp_header_top_enabled', '1') === '1';
$adSpHeaderBottomEnabled = $showAds && SettingsManager::get('ad_sp_header_bottom_enabled', '1') === '1';

$adPcHeader = SettingsManager::get('ad_pc_header');
$adPcSidebarTop = SettingsManager::get('ad_pc_sidebar_top');
$adPcSidebarBottom = SettingsManager::get('ad_pc_sidebar_bottom');
$adSpHeaderTop = SettingsManager::get('ad_sp_header_top');
$adSpHeaderBottom = SettingsManager::get('ad_sp_header_bottom');

// 相互RSS配信アイテムの取得
$pcHeaderBelowRss = $showRss ? TradeEngine::getDisplayFeedItems(true, 4) : []; // PCヘッダー下: 画像あり
$pcSideRss = $showRss ? TradeEngine::getDisplayFeedItems(true, 5) : [];       // PCサイド: 画像あり
$pcFooterAboveRss = $showRss ? TradeEngine::getDisplayFeedItems(true, 4) : []; // PCフッター上: 画像あり
$spHeaderRss = $showRss ? TradeEngine::getDisplayFeedItems(false, 3) : [];    // スマホヘッダー: テキスト+画像
$spFooterRss = $showRss ? TradeEngine::getDisplayFeedItems(false, 4) : [];    // スマホフッター: テキスト

// 相互リンク集
$approvedLinks = $showRss ? TradeEngine::getApprovedLinks() : [];

// カスタムタグ
$headCustomTags = SettingsManager::get('head_custom_tags');
$bodyTopTags = SettingsManager::get('body_top_tags');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site['name'] ?? 'しらんけど') ?> - 完全自動トレンドサイト</title>
    <meta name="description" content="<?= htmlspecialchars($site['description'] ?? 'ネット上の話題を客観分析し、一次情報とともにお届けするトレンドメディア。しらんけど。') ?>">
    <meta name="referrer" content="unsafe-url">
    <meta property="og:title" content="<?= htmlspecialchars($site['name'] ?? 'しらんけど') ?>">
    <meta property="og:description" content="ネット上の話題を客観分析し、一次情報とともにお届けするトレンドメディア。しらんけど。">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <?= $headCustomTags ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&family=Shippori+Mincho+B1:wght@600;800&display=swap" rel="stylesheet">
    <style>
        .font-mincho { font-family: 'Shippori Mincho B1', serif; }
        .font-sans { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-stone-100 text-stone-900 min-h-screen flex flex-col font-sans antialiased selection:bg-amber-200">
    <?= $bodyTopTags ?>

    <!-- ヘッダー -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-stone-200 px-4 sm:px-6 py-3 shadow-sm">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <a href="?" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500 text-stone-950 font-black text-xl flex items-center justify-center shadow-md rotate-[-2deg]">
                    知
                </div>
                <div>
                    <span class="text-xl font-black tracking-tight text-stone-950 block leading-none">
                        <?= htmlspecialchars($site['name'] ?? 'しらんけど') ?>
                    </span>
                    <span class="text-[11px] text-stone-500 tracking-wider block mt-0.5">
                        <?= htmlspecialchars($site['description'] ?? 'ネット話題を客観分析。最後はしらんけど。') ?>
                    </span>
                </div>
            </a>

            <!-- PCヘッダー広告枠 (468x60 / 728x90) -->
            <?php if ($adPcHeaderEnabled && !empty($adPcHeader)): ?>
                <div class="hidden lg:block overflow-hidden max-h-[60px]">
                    <?= $adPcHeader ?>
                </div>
            <?php endif; ?>

            <div class="flex items-center gap-2 sm:gap-3 text-xs">
                <a href="page.php?slug=about" class="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold transition-all">
                    サイトについて
                </a>
                <a href="page.php?slug=trade" class="px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold border border-amber-200 transition-all">
                    🤝 相互リンク依頼
                </a>
                <a href="page.php?slug=que" class="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold transition-all">
                    お問い合わせ
                </a>
            </div>
        </div>
    </header>

    <!-- スマホ専用 ヘッダー上 広告枠 (300x250) -->
    <?php if ($adSpHeaderTopEnabled && !empty($adSpHeaderTop)): ?>
        <div class="lg:hidden flex justify-center py-2 bg-stone-50 border-b border-stone-200">
            <?= $adSpHeaderTop ?>
        </div>
    <?php endif; ?>

    <!-- スマホ専用 ヘッダー 相互RSS (テキスト+画像) -->
    <?php if ($showRss && !empty($spHeaderRss)): ?>
        <div class="lg:hidden bg-white border-b border-stone-200 px-4 py-3 space-y-2">
            <div class="text-[11px] font-black text-stone-700 flex items-center gap-1">
                <span>📡</span> <span>提携アンテナ速報</span>
            </div>
            <div class="grid grid-cols-1 gap-2">
                <?php foreach ($spHeaderRss as $shr): ?>
                    <a href="<?= htmlspecialchars(TradeEngine::getOutboundLink((int)$shr['trade_site_id'], $shr['url'])) ?>" target="_blank" rel="noopener" class="flex items-center gap-2 text-xs text-stone-800 hover:text-amber-800">
                        <?php if (!empty($shr['has_image']) && !empty($shr['image_url'])): ?>
                            <img src="<?= htmlspecialchars($shr['image_url']) ?>" alt="" class="w-8 h-8 rounded-lg object-cover flex-shrink-0">
                        <?php endif; ?>
                        <span class="truncate font-medium leading-snug"><?= htmlspecialchars($shr['title']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- PC専用 ヘッダー下 相互RSS (画像カルーセル/グリッド) -->
    <?php if ($showRss && !empty($pcHeaderBelowRss)): ?>
        <div class="hidden lg:block bg-stone-50 border-b border-stone-200 py-3">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs font-black text-stone-700">📡 提携アンテナ更新 (相互RSS)</span>
                    <span class="text-[10px] text-stone-400">| アクセス還元配信中</span>
                </div>
                <div class="grid grid-cols-4 gap-4">
                    <?php foreach ($pcHeaderBelowRss as $phr): ?>
                        <a href="<?= htmlspecialchars(TradeEngine::getOutboundLink((int)$phr['trade_site_id'], $phr['url'])) ?>" target="_blank" rel="noopener" class="flex gap-2.5 items-center p-2 rounded-2xl bg-white border border-stone-200 hover:border-amber-400 transition-all group">
                            <?php if (!empty($phr['has_image']) && !empty($phr['image_url'])): ?>
                                <img src="<?= htmlspecialchars($phr['image_url']) ?>" alt="" class="w-12 h-12 rounded-xl object-cover flex-shrink-0 bg-stone-100">
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <span class="text-[9px] font-bold text-amber-700 block truncate"><?= htmlspecialchars($phr['site_name']) ?></span>
                                <h4 class="text-xs font-bold text-stone-800 group-hover:text-amber-700 truncate leading-tight"><?= htmlspecialchars($phr['title']) ?></h4>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- スマホ専用 ヘッダー下 広告枠 (300x250) -->
    <?php if ($showAds && !empty($adSpHeaderBottom)): ?>
        <div class="lg:hidden flex justify-center py-2 bg-stone-50 border-b border-stone-200">
            <?= $adSpHeaderBottom ?>
        </div>
    <?php endif; ?>

    <!-- メインコンテンツレイアウト (記事グリッド + PCサイドバー) -->
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 flex flex-col lg:flex-row gap-8">
        
        <!-- 左側メイン記事エリア -->
        <main class="flex-1 min-w-0 space-y-6">

            <!-- カテゴリナビゲーション -->
            <?php if (!empty($categories)): ?>
                <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none text-xs font-bold">
                    <a href="?" class="px-4 py-2 rounded-2xl border transition-all whitespace-nowrap <?= (!isset($_GET['cat']) || $_GET['cat'] === 'all') ? 'bg-stone-950 text-white border-stone-950 shadow-sm' : 'bg-white text-stone-700 border-stone-200 hover:bg-stone-50' ?>">
                        総合トレンド
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?cat=<?= urlencode($cat['slug']) ?>" class="px-4 py-2 rounded-2xl border transition-all whitespace-nowrap <?= (isset($_GET['cat']) && $_GET['cat'] === $cat['slug']) ? 'bg-stone-950 text-white border-stone-950 shadow-sm' : 'bg-white text-stone-700 border-stone-200 hover:bg-stone-50' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 記事一覧グリッド (2カラム) -->
            <?php if (!empty($articles)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <?php foreach ($articles as $art): 
                        $score = (int)$art['shirankedo_index'];
                        $colorClass = $score >= 80 ? 'bg-rose-50 text-rose-800 border-rose-200' : ($score >= 50 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-stone-100 text-stone-800 border-stone-200');
                        $barColor = $score >= 80 ? 'bg-rose-500' : ($score >= 50 ? 'bg-amber-500' : 'bg-stone-400');
                        $imgSrc = $art['custom_image_url'] ?: 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=800&q=80';
                    ?>
                        <article class="bg-white rounded-3xl border border-stone-200/80 overflow-hidden shadow-sm hover:shadow-lg transition-all flex flex-col group">
                            <!-- アイキャッチ画像 (直接 article.php?id=XX へ遷移) -->
                            <a href="article.php?id=<?= $art['id'] ?>" class="relative h-44 sm:h-48 overflow-hidden bg-stone-100 block">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($art['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <?php if ($art['is_rapid_rise']): ?>
                                    <span class="absolute top-3 left-3 bg-rose-600 text-white text-[11px] font-black px-2.5 py-1 rounded-full shadow-md flex items-center gap-1 animate-pulse">
                                        🔥 急上昇
                                    </span>
                                <?php endif; ?>
                                <span class="absolute top-3 right-3 bg-stone-900/80 backdrop-blur-md text-white text-[11px] font-bold px-2.5 py-1 rounded-full">
                                    <?= htmlspecialchars($art['category_name'] ?? 'ニュース') ?>
                                </span>
                            </a>

                            <!-- 記事内容 -->
                            <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                                <div class="space-y-2.5">
                                    <!-- 指数ゲージ -->
                                    <div class="flex items-center justify-between text-xs font-bold border-b border-stone-100 pb-2.5">
                                        <span class="text-stone-500">しらんけど指数</span>
                                        <span class="px-2 py-0.5 rounded-lg border <?= $colorClass ?>">
                                            <?= $score ?>点 (<?= htmlspecialchars($art['index_label'] ?? '話題') ?>)
                                        </span>
                                    </div>
                                    <div class="w-full bg-stone-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full <?= $barColor ?>" style="width: <?= min(100, $score) ?>%"></div>
                                    </div>

                                    <!-- タイトル (クリックで記事ページへ直接遷移) -->
                                    <h2 class="text-base sm:text-lg font-black text-stone-950 leading-snug line-clamp-2">
                                        <a href="article.php?id=<?= $art['id'] ?>" class="hover:text-amber-800 transition-colors">
                                            <?= htmlspecialchars($art['title']) ?>
                                        </a>
                                    </h2>

                                    <a href="article.php?id=<?= $art['id'] ?>" class="block bg-amber-50/60 hover:bg-amber-50 border border-amber-100/80 rounded-2xl p-3 text-xs text-amber-950 leading-relaxed transition-colors">
                                        <span class="font-bold text-amber-900 block mb-0.5">💡 なぜ話題？</span>
                                        <?= htmlspecialchars($art['why_trending']) ?>
                                    </a>
                                </div>

                                <!-- 締め文句とアクション -->
                                <div class="space-y-3 pt-2">
                                    <div class="text-xs text-stone-600 font-mincho bg-stone-50 p-2.5 rounded-xl border border-stone-100 italic">
                                        <?= htmlspecialchars($art['conclusion_sentence']) ?>
                                    </div>

                                    <div class="flex items-center justify-between text-xs text-stone-500 pt-1">
                                        <span><?= date('m/d H:i', strtotime($art['published_at'])) ?></span>
                                        <a href="article.php?id=<?= $art['id'] ?>" class="px-3.5 py-1.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold transition-colors inline-flex items-center gap-1">
                                            記事を読む →
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>

        <!-- 右側 PCサイドバーカラム -->
        <aside class="w-full lg:w-80 flex-shrink-0 space-y-6">
            
            <!-- PCサイドバー上 広告枠 (300x250) -->
            <?php if ($adPcSidebarTopEnabled && !empty($adPcSidebarTop)): ?>
                <div class="hidden lg:flex justify-center bg-white p-3 rounded-3xl border border-stone-200 shadow-sm">
                    <?= $adPcSidebarTop ?>
                </div>
            <?php endif; ?>

            <!-- PCサイドバー 相互RSS (画像) -->
            <?php if ($showRss && !empty($pcSideRss)): ?>
                <div class="bg-white rounded-3xl border border-stone-200 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-2.5">
                        <h3 class="text-xs font-black text-stone-900 flex items-center gap-1.5">
                            <span>📡</span> 提携アンテナ更新
                        </h3>
                        <span class="text-[10px] text-stone-400 font-bold">画像RSS</span>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($pcSideRss as $sr): ?>
                            <a href="<?= htmlspecialchars(TradeEngine::getOutboundLink((int)$sr['trade_site_id'], $sr['url'])) ?>" target="_blank" rel="noopener" class="flex gap-2.5 items-center group">
                                <?php if (!empty($sr['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($sr['image_url']) ?>" alt="" class="w-12 h-12 rounded-xl object-cover bg-stone-100 flex-shrink-0">
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-bold text-stone-800 group-hover:text-amber-600 line-clamp-2 leading-snug">
                                        <?= htmlspecialchars($sr['title']) ?>
                                    </h4>
                                    <span class="text-[10px] text-stone-400"><?= htmlspecialchars($sr['site_name']) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- PC専用 相互リンク集 (テキストリンク一覧) -->
            <?php if ($showRss && !empty($approvedLinks)): ?>
                <div class="bg-white rounded-3xl border border-stone-200 p-5 shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-2.5">
                        <h3 class="text-xs font-black text-stone-900 flex items-center gap-1.5">
                            <span>🤝</span> 相互リンク集
                        </h3>
                        <a href="page.php?slug=trade" class="text-[10px] text-amber-600 font-bold hover:underline">依頼はこちら</a>
                    </div>
                    <ul class="space-y-2 text-xs">
                        <?php foreach ($approvedLinks as $al): ?>
                            <li>
                                <a href="<?= htmlspecialchars(TradeEngine::getOutboundLink((int)$al['id'], $al['url'])) ?>" target="_blank" rel="noopener" class="text-stone-700 hover:text-amber-600 hover:underline flex items-center gap-1 truncate font-medium">
                                    <span>•</span>
                                    <span><?= htmlspecialchars($al['site_name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- PCサイドバー下 広告枠 (300x250) -->
            <?php if ($adPcSidebarBottomEnabled && !empty($adPcSidebarBottom)): ?>
                <div class="hidden lg:flex justify-center bg-white p-3 rounded-3xl border border-stone-200 shadow-sm">
                    <?= $adPcSidebarBottom ?>
                </div>
            <?php endif; ?>

        </aside>

    </div>

    <!-- PC専用 フッター上 相互RSS (画像) -->
    <?php if ($showRss && !empty($pcFooterAboveRss)): ?>
        <div class="hidden lg:block bg-stone-50 border-t border-stone-200 py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black text-stone-800">📡 注目の提携ブログ最新記事</span>
                    <a href="page.php?slug=trade" class="text-[11px] text-stone-500 hover:text-stone-900 font-bold">相互リンク・RSS申請はこちら ↗</a>
                </div>
                <div class="grid grid-cols-4 gap-4">
                    <?php foreach ($pcFooterAboveRss as $pfr): ?>
                        <a href="<?= htmlspecialchars(TradeEngine::getOutboundLink((int)$pfr['trade_site_id'], $pfr['url'])) ?>" target="_blank" rel="noopener" class="flex gap-2.5 items-center p-2.5 rounded-2xl bg-white border border-stone-200 hover:border-amber-400 transition-all group">
                            <?php if (!empty($pfr['has_image']) && !empty($pfr['image_url'])): ?>
                                <img src="<?= htmlspecialchars($pfr['image_url']) ?>" alt="" class="w-12 h-12 rounded-xl object-cover flex-shrink-0 bg-stone-100">
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <span class="text-[9px] font-bold text-amber-700 block truncate"><?= htmlspecialchars($pfr['site_name']) ?></span>
                                <h4 class="text-xs font-bold text-stone-800 group-hover:text-amber-700 truncate leading-tight"><?= htmlspecialchars($pfr['title']) ?></h4>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- スマホ専用 フッター 相互RSS (テキスト) -->
    <?php if ($showRss && !empty($spFooterRss)): ?>
        <div class="lg:hidden bg-stone-50 border-t border-stone-200 p-4 space-y-3">
            <div class="text-xs font-black text-stone-800 flex items-center justify-between">
                <span>📡 提携アンテナ更新</span>
                <a href="page.php?slug=trade" class="text-[10px] text-amber-700 font-bold">相互申請</a>
            </div>
            <ul class="space-y-2 text-xs">
                <?php foreach ($spFooterRss as $sfr): ?>
                    <li>
                        <a href="<?= htmlspecialchars(TradeEngine::getOutboundLink((int)$sfr['trade_site_id'], $sfr['url'])) ?>" target="_blank" rel="noopener" class="text-stone-700 hover:text-amber-800 line-clamp-1">
                            • <?= htmlspecialchars($sfr['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- フッター -->
    <footer class="bg-stone-900 text-stone-400 text-xs py-8 px-4 border-t border-stone-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="space-y-1 text-center sm:text-left">
                <div class="text-white font-black text-sm tracking-wider">
                    <?= htmlspecialchars($site['name'] ?? 'しらんけど') ?>
                </div>
                <p class="text-[11px] text-stone-500">
                    客観的事実と一次報道に基づき要約しています。判断は自己責任でお願いします。しらんけど。
                </p>
            </div>
            <div class="flex items-center gap-4 text-xs font-bold">
                <a href="page.php?slug=about" class="hover:text-amber-400 transition-colors">サイトについて</a>
                <a href="page.php?slug=trade" class="hover:text-amber-400 transition-colors">相互リンク依頼</a>
                <a href="page.php?slug=news" class="hover:text-amber-400 transition-colors">お知らせ</a>
                <a href="page.php?slug=privacy-policy" class="hover:text-amber-400 transition-colors">プライバシーポリシー</a>
                <a href="page.php?slug=que" class="hover:text-amber-400 transition-colors">お問い合わせ</a>
            </div>
        </div>
    </footer>

</body>
</html>
