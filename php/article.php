<?php
/**
 * 記事詳細ページ (article.php)
 * URL: /article.php?id=XX または /article.php?slug=YY または /article/YY (.htaccess経由)
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/SettingsManager.php';
require_once __DIR__ . '/classes/TradeEngine.php';
require_once __DIR__ . '/classes/AnalyticsTracker.php';

// 流入アクセスの自動トラッキング (INカウント加算)
TradeEngine::trackIncomingReferrer();

$articleId = (int)($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

$dbConnected = false;
$db = null;
try {
    $db = Database::getConnection();
    $dbConnected = true;
} catch (Throwable $e) {
    // DB接続エラー
}

$site = null;
if ($dbConnected) {
    try {
        $site = SiteManager::resolveCurrentSite();
    } catch (Throwable $e) {
        // フォールバック
    }
}

// 記事取得
$article = null;
if ($dbConnected && ($articleId > 0 || !empty($slug))) {
    try {
        if ($articleId > 0) {
            $stmt = $db->prepare("SELECT a.*, c.name as category_name, c.slug as category_slug
                                  FROM articles a
                                  LEFT JOIN categories c ON a.category_id = c.id
                                  WHERE a.id = ? AND a.status = 'published' AND a.image_url IS NOT NULL AND TRIM(a.image_url) != '' LIMIT 1");
            $stmt->execute([$articleId]);
        } else {
            $stmt = $db->prepare("SELECT a.*, c.name as category_name, c.slug as category_slug
                                  FROM articles a
                                  LEFT JOIN categories c ON a.category_id = c.id
                                  WHERE a.slug = ? AND a.status = 'published' AND a.image_url IS NOT NULL AND TRIM(a.image_url) != '' LIMIT 1");
            $stmt->execute([$slug]);
        }
        $article = $stmt->fetch();
    } catch (Throwable $e) {
        $article = null;
    }
}

// 記事が存在しない場合のフォールバック（デモ記事データ）
if (!$article) {
    if ($articleId === 1 || $slug === 'trend-demo-1') {
        $article = [
            'id' => 1,
            'title' => '千鳥の新番組が異例のTVer週間ランキング1位を獲得した件',
            'slug' => 'trend-demo-1',
            'category_name' => 'エンタメ',
            'shirankedo_index' => 88,
            'index_label' => 'めっちゃ話題',
            'why_trending' => '新企画の予測不能なロケ展開がSNSで話題を呼び、放送後わずか3日で再生数200万回を突破しました。',
            'body' => "お笑いコンビ・千鳥が出演する深夜バラエティ番組の新企画が、民放公式テレビ配信サービス「TVer」の総合ランキングにおいて異例の週間1位を獲得しました。\n\n番組関係者によると、事前告知なしで決行された岡山ロケの模様がSNS上で大きな反響を呼び、放送終了直後から切り抜き動画や言及ポストが急増。関連キーワードがトレンド入りを果たすなど、深夜枠としては極めて高い視聴熱を記録しています。\n\n同局プロデューサーは「視聴者のリアルタイムな共感と反響が今回の数字につながった」とコメントしています。",
            'conclusion_sentence' => '次回の放送でもこの勢いを維持できるのか、今後の企画展開に注目が集まります。しらんけど。',
            'image_url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=800&q=80',
            'published_at' => date('Y-m-d H:i:s'),
        ];
    } elseif ($articleId === 2 || $slug === 'trend-demo-2') {
        $article = [
            'id' => 2,
            'title' => '大人気ハンティングアクション最新作、全世界同時体験版が配信開始',
            'slug' => 'trend-demo-2',
            'category_name' => 'ゲーム',
            'shirankedo_index' => 94,
            'index_label' => 'めっちゃ話題',
            'why_trending' => 'シリーズ待望の最新作が突如体験版の配信を開始し、同時接続プレイヤー数が歴代記録を更新しました。',
            'body' => "人気ゲームシリーズの最新ナンバリングタイトルにおいて、全世界同時での無料オープンベータテストが本日未明より開始されました。\n\n公式サイトおよび各プラットフォームの発表によると、配信開始直後からアクセスが集中し、一部サーバーで入場制限が実施されるほどの盛り上がりを見せています。ユーザーからは刷新されたグラフィックや新アクションに対する高評価が寄せられています。",
            'conclusion_sentence' => '本編発売日にはさらに大きな熱狂が巻き起こりそうです。しらんけど。',
            'image_url' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=800&q=80',
            'published_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ];
    } else {
        header("HTTP/1.1 404 Not Found");
        echo "<!DOCTYPE html><html lang='ja'><head><meta charset='UTF-8'><title>記事が見つかりません</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-stone-100 flex items-center justify-center min-h-screen p-4'><div class='bg-white p-8 rounded-3xl border border-stone-200 text-center max-w-md shadow-xl'><h1 class='text-2xl font-black text-stone-900 mb-2'>記事が見つかりません</h1><p class='text-xs text-stone-600 mb-6'>指定された記事は削除されたか、URLが変更された可能性があります。</p><a href='index.php' class='px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs'>トップページへ戻る</a></div></body></html>";
        exit;
    }
}

// 投票・コメントカウント
$voteBelieved = 0;
$voteSkeptical = 0;
$comments = [];

if ($dbConnected && isset($article['id'])) {
    try {
        $countStmt = $db->prepare("SELECT 
            SUM(CASE WHEN vote_type = 'believed' THEN 1 ELSE 0 END) as believed,
            SUM(CASE WHEN vote_type = 'skeptical' THEN 1 ELSE 0 END) as skeptical
            FROM votes WHERE article_id = ?");
        $countStmt->execute([$article['id']]);
        $counts = $countStmt->fetch();
        $voteBelieved = (int)($counts['believed'] ?? 0);
        $voteSkeptical = (int)($counts['skeptical'] ?? 0);

        $comStmt = $db->prepare("SELECT * FROM comments WHERE article_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 50");
        $comStmt->execute([$article['id']]);
        $comments = $comStmt->fetchAll();
    } catch (Throwable $e) {
        // テーブルが存在しない場合などはゼロ扱い
    }
}

// 関連・最新記事（おすすめ：アイキャッチ画像設定済みのみ）
$recentArticles = [];
if ($dbConnected) {
    try {
        $recStmt = $db->prepare("SELECT id, title, slug, shirankedo_index, index_label, image_url, published_at 
                                 FROM articles 
                                 WHERE id != ? AND status = 'published' AND image_url IS NOT NULL AND TRIM(image_url) != ''
                                 ORDER BY published_at DESC LIMIT 4");
        $recStmt->execute([$article['id'] ?? 0]);
        $recentArticles = $recStmt->fetchAll();
    } catch (Throwable $e) {}
}

// 閲覧トラッキング
AnalyticsTracker::track('article', (int)($article['id'] ?? null));

// 表示切り替えフラグ
$showAds = SettingsManager::get('show_ads', '1') === '1';
$showRss = SettingsManager::get('show_rss', '1') === '1';

// ステマ規制法対応 アフィリエイト広告表記 (PR表記)
$affiliatePrNoticeEnabled = SettingsManager::get('affiliate_pr_notice_enabled', '1') === '1';
$affiliatePrNoticeText = SettingsManager::get('affiliate_pr_notice_text', '当サイトはアフィリエイト広告を利用しています。');

// 本文下 相互RSS（画像+テキスト）
$bodyBelowRss = $showRss ? TradeEngine::getDisplayFeedItems(false, 6) : [];

// PCサイドバー 相互RSS（画像付き）
$sideRss = $showRss ? TradeEngine::getDisplayFeedItems(true, 5) : [];

// 相互リンク一覧
$approvedLinks = $showRss ? TradeEngine::getApprovedLinks() : [];

// 広告タグと個別枠ごとの表示/非表示フラグ
$adPcHeaderEnabled = $showAds && SettingsManager::get('ad_pc_header_enabled', '1') === '1';
$adPcSidebarTopEnabled = $showAds && SettingsManager::get('ad_pc_sidebar_top_enabled', '1') === '1';
$adPcSidebarBottomEnabled = $showAds && SettingsManager::get('ad_pc_sidebar_bottom_enabled', '1') === '1';
$adSpHeaderTopEnabled = $showAds && SettingsManager::get('ad_sp_header_top_enabled', '1') === '1';
$adSpHeaderBottomEnabled = $showAds && SettingsManager::get('ad_sp_header_bottom_enabled', '1') === '1';
$adArticleMiddleEnabled = $showAds && SettingsManager::get('ad_article_middle_enabled', '1') === '1';
$adArticleBottomEnabled = $showAds && SettingsManager::get('ad_article_bottom_enabled', '1') === '1';

$adPcHeader = SettingsManager::get('ad_pc_header');
$adPcSidebarTop = SettingsManager::get('ad_pc_sidebar_top');
$adPcSidebarBottom = SettingsManager::get('ad_pc_sidebar_bottom');
$adSpHeaderTop = SettingsManager::get('ad_sp_header_top');
$adSpHeaderBottom = SettingsManager::get('ad_sp_header_bottom');
$adArticleMiddle = SettingsManager::get('ad_article_middle');
$adArticleBottom = SettingsManager::get('ad_article_bottom');

// カスタムタグ
$headCustomTags = SettingsManager::get('head_custom_tags');
$bodyTopTags = SettingsManager::get('body_top_tags');

$score = (int)($article['shirankedo_index'] ?? 50);
$colorClass = $score >= 80 ? 'bg-rose-50 text-rose-800 border-rose-200' :
              ($score >= 50 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-stone-100 text-stone-800 border-stone-200');

// SNSシェア用データ
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'shirankedo.bichi.xyz') . $_SERVER['REQUEST_URI'];
$shareTitle = $article['title'] . ' | しらんけど';
$shareTwitterUrl = 'https://twitter.com/intent/tweet?text=' . urlencode("【話題度: {$score}/100】" . $article['title'] . "\n#しらんけど\n") . '&url=' . urlencode($currentUrl);
$sharePinterestUrl = 'https://pinterest.com/pin/create/button/?url=' . urlencode($currentUrl) . '&media=' . urlencode($article['image_url'] ?? '') . '&description=' . urlencode($article['title'] . ' - ' . $article['why_trending']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($article['title']) ?> - <?= htmlspecialchars($site['name'] ?? 'しらんけど') ?></title>
    <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($article['why_trending']), 0, 120)) ?>">
    <meta name="referrer" content="unsafe-url">
    <meta property="og:title" content="<?= htmlspecialchars($article['title']) ?> - しらんけど">
    <meta property="og:description" content="<?= htmlspecialchars($article['why_trending']) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>">
    <?php if (!empty($article['image_url'])): ?>
    <meta property="og:image" content="<?= htmlspecialchars($article['image_url']) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($article['title']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($article['why_trending']) ?>">
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
        <div class="max-w-6xl mx-auto flex items-center justify-between gap-4">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500 text-stone-950 font-black text-xl flex items-center justify-center shadow-md rotate-[-2deg]">
                    知
                </div>
                <div>
                    <span class="text-xl font-black tracking-tight text-stone-950 block leading-none">
                        <?= htmlspecialchars($site['name'] ?? 'しらんけど') ?>
                    </span>
                    <span class="text-[11px] text-stone-500 tracking-wider block mt-0.5">
                        ネット話題を客観分析。最後はしらんけど。
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
                <a href="index.php" class="px-3.5 py-1.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold transition-all">
                    ← トレンド一覧
                </a>
            </div>
        </div>
    </header>

    <!-- ステマ規制法対応 アフィリエイト広告表記 (PRバー) -->
    <?php if ($affiliatePrNoticeEnabled): ?>
        <div class="bg-amber-50/90 border-b border-amber-200/70 px-4 py-1.5 text-center text-[11px] text-amber-950 font-medium tracking-wide flex items-center justify-center gap-1.5 shadow-xs">
            <span class="inline-block px-1.5 py-0.5 rounded bg-amber-200 text-amber-950 font-black text-[10px]">PR</span>
            <span><?= htmlspecialchars($affiliatePrNoticeText) ?></span>
        </div>
    <?php endif; ?>

    <!-- スマホ専用 ヘッダー上 広告枠 (300x250) -->
    <?php if ($adSpHeaderTopEnabled && !empty($adSpHeaderTop)): ?>
        <div class="lg:hidden flex justify-center py-2 bg-stone-50 border-b border-stone-200">
            <?= $adSpHeaderTop ?>
        </div>
    <?php endif; ?>

    <!-- メインコンテンツレイアウト (記事エリア + PCサイドバー) -->
    <div class="max-w-6xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 flex flex-col lg:flex-row gap-8">
        
        <!-- 左側メイン記事カラム -->
        <main class="flex-1 min-w-0 space-y-8">
            <article class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-10 shadow-sm space-y-6">
                
                <!-- ヘッダー情報 -->
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="px-3 py-1 rounded-full bg-stone-900 text-white font-bold">
                            <?= htmlspecialchars($article['category_name'] ?? 'ニュース') ?>
                        </span>
                        <span class="text-stone-400">•</span>
                        <time class="text-stone-500"><?= date('Y年m月d日 H:i', strtotime($article['published_at'] ?? 'now')) ?></time>
                        <span class="text-stone-400">•</span>
                        <span class="px-2.5 py-0.5 rounded-lg font-bold border <?= $colorClass ?>">
                            しらんけど指数: <?= $score ?>点 (<?= htmlspecialchars($article['index_label'] ?? '話題') ?>)
                        </span>
                        <?php if ($affiliatePrNoticeEnabled): ?>
                            <span class="text-stone-400">•</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100/80 text-amber-950 text-[10px] font-bold border border-amber-200">
                                <span>📢</span> <?= htmlspecialchars($affiliatePrNoticeText) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black text-stone-950 leading-snug">
                        <?= htmlspecialchars($article['title']) ?>
                    </h1>
                </div>

                <!-- SNSシェアボタン (上部) -->
                <div class="flex items-center gap-2 border-y border-stone-100 py-3">
                    <span class="text-xs font-bold text-stone-400">シェア:</span>
                    <!-- X / Twitter -->
                    <a href="<?= htmlspecialchars($shareTwitterUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-3 py-1.5 rounded-xl bg-black text-white hover:bg-stone-800 text-xs font-black flex items-center gap-1.5 transition-colors shadow-sm">
                        <span>𝕏</span> <span>ポスト</span>
                    </a>
                    <!-- Pinterest -->
                    <a href="<?= htmlspecialchars($sharePinterestUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-3 py-1.5 rounded-xl bg-rose-600 text-white hover:bg-rose-500 text-xs font-black flex items-center gap-1.5 transition-colors shadow-sm">
                        <span>📌</span> <span>Pin</span>
                    </a>
                    <!-- Instagram案内ボタン -->
                    <button onclick="navigator.clipboard.writeText('<?= addslashes($currentUrl) ?>'); alert('URLをコピーしました！インスタストーリーやリンクでシェアできます。');" class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-purple-600 via-pink-600 to-amber-500 text-white hover:opacity-90 text-xs font-black flex items-center gap-1.5 transition-opacity shadow-sm">
                        <span>📸</span> <span>URLコピー</span>
                    </button>
                </div>

                <!-- アイキャッチ画像 (800x450px) -->
                <?php if (!empty($article['image_url'])): ?>
                    <div class="rounded-2xl overflow-hidden aspect-video bg-stone-100 shadow-inner">
                        <img src="<?= htmlspecialchars($article['image_url']) ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="w-full h-full object-cover">
                    </div>
                <?php endif; ?>

                <!-- なぜ話題？（ハイライトボックス） -->
                <div class="bg-amber-50/80 border-2 border-amber-200/90 rounded-2xl p-5 sm:p-6 text-sm text-amber-950 space-y-2">
                    <div class="font-black text-amber-900 flex items-center gap-2 text-base">
                        <span>💡</span> なぜ話題？
                    </div>
                    <p class="leading-relaxed font-bold">
                        <?= htmlspecialchars($article['why_trending']) ?>
                    </p>
                </div>

                <!-- 記事本文中 広告枠 (インフィード / 300x250) -->
                <?php if ($adArticleMiddleEnabled && !empty($adArticleMiddle)): ?>
                    <div class="flex justify-center my-4 overflow-hidden">
                        <?= $adArticleMiddle ?>
                    </div>
                <?php endif; ?>

                <!-- 記事本文 -->
                <div class="prose max-w-none text-stone-800 leading-relaxed text-sm sm:text-base space-y-4 font-sans whitespace-pre-wrap">
                    <?= nl2br(htmlspecialchars($article['body'])) ?>
                </div>

                <!-- 締めの言葉（しらんけど構文） -->
                <div class="bg-stone-50 border-l-4 border-amber-500 p-5 rounded-r-2xl font-mincho text-sm sm:text-base text-stone-900">
                    <?= htmlspecialchars($article['conclusion_sentence']) ?>
                </div>

                <!-- SNSシェアボタン (下部) -->
                <div class="bg-stone-50 p-4 rounded-2xl border border-stone-200/60 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-xs font-bold text-stone-700">この話題を友達やフォロワーに教える：</div>
                    <div class="flex items-center gap-2">
                        <a href="<?= htmlspecialchars($shareTwitterUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-3.5 py-1.5 rounded-xl bg-black text-white hover:bg-stone-800 text-xs font-black shadow-sm transition-all">
                            𝕏 ポスト
                        </a>
                        <a href="<?= htmlspecialchars($sharePinterestUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-3.5 py-1.5 rounded-xl bg-rose-600 text-white hover:bg-rose-500 text-xs font-black shadow-sm transition-all">
                            📌 ピン留め
                        </a>
                        <button onclick="navigator.clipboard.writeText('<?= addslashes($currentUrl) ?>'); alert('記事URLをクリップボードにコピーしました！');" class="px-3.5 py-1.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white text-xs font-black shadow-sm transition-all">
                            📋 リンクコピー
                        </button>
                    </div>
                </div>

                <!-- リアルタイム投票ブロック -->
                <div class="border-t border-stone-100 pt-8 space-y-4">
                    <div class="text-sm font-bold text-stone-800 flex items-center justify-between">
                        <span>📊 この話題、あなたはどう思う？（リアルタイム投票）</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <button id="btnVoteBelieved" onclick="sendVote('believed')" class="py-4 px-4 rounded-2xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 font-black text-sm flex flex-col items-center justify-center gap-1.5 transition-all shadow-sm active:scale-98">
                            <span class="text-base">👍 ほんまや！</span>
                            <span class="text-xs font-normal text-emerald-700" id="countBelieved"><?= $voteBelieved ?>票</span>
                        </button>
                        <button id="btnVoteSkeptical" onclick="sendVote('skeptical')" class="py-4 px-4 rounded-2xl bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-900 font-black text-sm flex flex-col items-center justify-center gap-1.5 transition-all shadow-sm active:scale-98">
                            <span class="text-base">🤔 しらんけど…</span>
                            <span class="text-xs font-normal text-amber-700" id="countSkeptical"><?= $voteSkeptical ?>票</span>
                        </button>
                    </div>
                </div>

                <!-- コメント投稿ブロック -->
                <div class="border-t border-stone-100 pt-8 space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-stone-900">💬 コメント（<?= count($comments) ?>件）</h2>
                        <a href="php/comment-rules.php" target="_blank" class="text-xs text-stone-400 hover:text-stone-600 underline">投稿ルール</a>
                    </div>

                    <!-- コメント一覧 -->
                    <div id="commentList" class="space-y-3">
                        <?php if (empty($comments)): ?>
                            <p class="text-xs text-stone-400 py-4 text-center bg-stone-50 rounded-2xl">まだコメントはありません。最初の感想を投稿してみましょう！</p>
                        <?php else: ?>
                            <?php foreach ($comments as $com): ?>
                                <div class="p-4 rounded-2xl bg-stone-50 border border-stone-100 text-xs space-y-1">
                                    <div class="flex items-center justify-between text-stone-500 font-bold">
                                        <span><?= htmlspecialchars($com['author_name'] ?? '名無しさん') ?></span>
                                        <span class="text-[11px] font-normal"><?= date('m/d H:i', strtotime($com['created_at'])) ?></span>
                                    </div>
                                    <p class="text-stone-800 leading-relaxed"><?= nl2br(htmlspecialchars($com['body'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 投稿フォーム -->
                    <form onsubmit="submitComment(event)" class="space-y-3 pt-2">
                        <input type="text" id="commentAuthor" placeholder="お名前 (省略時は名無しさん)" class="w-full text-xs p-3 rounded-xl border border-stone-200 focus:outline-none focus:border-stone-900">
                        <textarea id="commentBody" rows="3" required placeholder="コメント本文（誹謗中傷・個人情報は自動遮断されます）" class="w-full text-xs p-3 rounded-xl border border-stone-200 focus:outline-none focus:border-stone-900"></textarea>
                        <button type="submit" class="w-full py-3 rounded-xl bg-stone-950 hover:bg-stone-800 text-white font-bold text-xs transition-colors shadow">
                            コメントを投稿する
                        </button>
                    </form>
                    <div id="commentAlert" class="text-xs hidden p-3 rounded-xl"></div>
                </div>

            </article>

            <!-- 📡 本文下: 相互RSS (画像＋テキスト) -->
            <?php if ($showRss && !empty($bodyBelowRss)): ?>
                <section class="bg-white rounded-3xl border border-stone-200/80 p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <h3 class="text-xs font-black text-stone-900 flex items-center gap-1.5">
                            <span class="text-amber-500">📡</span> 提携サイトの最新トピックス (相互RSS)
                        </h3>
                        <a href="page.php?slug=trade" class="text-[11px] text-stone-400 hover:text-stone-900 font-bold">相互RSS募集 ↗</a>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach ($bodyBelowRss as $rss): ?>
                            <a href="<?= htmlspecialchars(TradeEngine::getOutboundLink((int)$rss['trade_site_id'], $rss['url'])) ?>" target="_blank" rel="noopener" class="flex gap-3 p-2.5 rounded-2xl hover:bg-amber-50/50 border border-stone-100 hover:border-amber-200 transition-all group">
                                <?php if (!empty($rss['has_image']) && !empty($rss['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($rss['image_url']) ?>" alt="" class="w-16 h-16 rounded-xl object-cover flex-shrink-0 bg-stone-100">
                                <?php endif; ?>
                                <div class="flex-1 min-w-0 space-y-1">
                                    <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded truncate inline-block">
                                        <?= htmlspecialchars($rss['site_name']) ?>
                                    </span>
                                    <h4 class="text-xs font-bold text-stone-900 group-hover:text-amber-900 leading-snug line-clamp-2">
                                        <?= htmlspecialchars($rss['title']) ?>
                                    </h4>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- スマホ専用 ヘッダー下 広告枠 (300x250) -->
            <?php if ($adSpHeaderBottomEnabled && !empty($adSpHeaderBottom)): ?>
                <div class="lg:hidden flex justify-center py-4 bg-stone-50 rounded-2xl border border-stone-200">
                    <?= $adSpHeaderBottom ?>
                </div>
            <?php endif; ?>

            <!-- 記事下部 広告枠 (300x250 / レスポンシブ) -->
            <?php if ($adArticleBottomEnabled && !empty($adArticleBottom)): ?>
                <div class="flex justify-center my-6 p-4 bg-stone-50 rounded-2xl border border-stone-200 overflow-hidden">
                    <?= $adArticleBottom ?>
                </div>
            <?php endif; ?>

            <!-- 他のトレンド話題 -->
            <?php if (!empty($recentArticles)): ?>
                <div class="space-y-4">
                    <h3 class="font-black text-stone-900 text-base">🔥 他の注目トレンド</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($recentArticles as $rec): ?>
                            <a href="article.php?id=<?= $rec['id'] ?>" class="flex items-center gap-3 p-3 bg-white rounded-2xl border border-stone-200 hover:border-amber-400 shadow-sm transition-all group">
                                <?php if (!empty($rec['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($rec['image_url']) ?>" alt="" class="w-16 h-16 rounded-xl object-cover">
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <span class="text-[10px] font-bold text-amber-800 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                        <?= $rec['shirankedo_index'] ?>点
                                    </span>
                                    <h4 class="text-xs font-bold text-stone-900 group-hover:text-amber-800 truncate mt-1">
                                        <?= htmlspecialchars($rec['title']) ?>
                                    </h4>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </main>

        <!-- 右側 PCサイドバーカラム (300px広告・相互リンク・相互RSS) -->
        <aside class="w-full lg:w-80 flex-shrink-0 space-y-6">
            
            <!-- PCサイドバー上 広告枠 (300x250) -->
            <?php if ($adPcSidebarTopEnabled && !empty($adPcSidebarTop)): ?>
                <div class="hidden lg:flex justify-center bg-white p-3 rounded-3xl border border-stone-200 shadow-sm">
                    <?= $adPcSidebarTop ?>
                </div>
            <?php endif; ?>

            <!-- PC専用 相互RSS (画像) -->
            <?php if ($showRss && !empty($sideRss)): ?>
                <div class="bg-white rounded-3xl border border-stone-200 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-2.5">
                        <h3 class="text-xs font-black text-stone-900 flex items-center gap-1.5">
                            <span>📡</span> 提携アンテナ更新
                        </h3>
                        <span class="text-[10px] text-stone-400 font-bold">画像RSS</span>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($sideRss as $sr): ?>
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

    <!-- 相互リンク・提携アンテナサイト集 (全幅グリッド表示で相互リンク枠を大幅拡充) -->
    <?php if ($showRss && !empty($approvedLinks)): ?>
        <section class="max-w-6xl mx-auto px-4 sm:px-6 my-6 w-full">
            <div class="bg-white rounded-3xl border border-stone-200 p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🤝</span>
                        <h2 class="text-xs sm:text-sm font-black text-stone-900 tracking-tight">相互リンク・提携アンテナサイト一覧</h2>
                        <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 text-[10px] font-bold border border-amber-200">随時募集中</span>
                    </div>
                    <a href="page.php?slug=trade" class="text-xs text-amber-700 hover:text-amber-800 font-bold flex items-center gap-1 hover:underline">
                        <span>＋ 相互リンク・RSS提携依頼はこちら</span>
                    </a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 text-xs">
                    <?php foreach ($approvedLinks as $al): ?>
                        <a href="<?= htmlspecialchars(TradeEngine::getOutboundLink((int)$al['id'], $al['url'])) ?>" target="_blank" rel="noopener" class="p-2.5 rounded-xl bg-stone-50 hover:bg-amber-50 border border-stone-200/70 hover:border-amber-300 text-stone-700 hover:text-amber-900 font-medium transition-all flex items-center justify-between group truncate" title="<?= htmlspecialchars($al['site_name']) ?>">
                            <span class="truncate"><?= htmlspecialchars($al['site_name']) ?></span>
                            <span class="text-stone-300 group-hover:text-amber-500 text-[10px] ml-1 shrink-0">↗</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- フッター -->
    <footer class="bg-stone-900 text-stone-400 text-xs py-8 px-4 mt-8 border-t border-stone-800">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="space-y-1 text-center sm:text-left">
                <div class="text-white font-black text-sm tracking-wider">
                    <?= htmlspecialchars($site['name'] ?? 'しらんけど') ?>
                </div>
                <p class="text-[11px] text-stone-500">
                    客観的事実と一次報道に基づき要約しています。判断は自己責任でお願いします。しらんけど。
                </p>
                <?php if ($affiliatePrNoticeEnabled): ?>
                    <p class="text-[11px] text-amber-400/90 font-medium pt-1">
                        ※ <?= htmlspecialchars($affiliatePrNoticeText) ?>
                    </p>
                <?php endif; ?>
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

    <script>
        const articleId = <?= (int)($article['id'] ?? 0) ?>;

        async function sendVote(voteType) {
            if (!articleId) return;
            const formData = new FormData();
            formData.append('action', 'vote');
            formData.append('article_id', articleId);
            formData.append('vote_type', voteType);
            try {
                const res = await fetch('index.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('countBelieved').textContent = (data.counts.believed || 0) + '票';
                    document.getElementById('countSkeptical').textContent = (data.counts.skeptical || 0) + '票';
                    alert('投票を受け付けました！');
                } else {
                    alert(data.error || '投票に失敗しました');
                }
            } catch (e) {
                alert('通信エラーが発生しました');
            }
        }

        async function submitComment(e) {
            e.preventDefault();
            if (!articleId) return;
            const author = document.getElementById('commentAuthor').value;
            const body = document.getElementById('commentBody').value;
            const alertBox = document.getElementById('commentAlert');
            const formData = new FormData();
            formData.append('action', 'comment');
            formData.append('article_id', articleId);
            formData.append('author_name', author);
            formData.append('body', body);
            try {
                const res = await fetch('index.php', { method: 'POST', body: formData });
                const data = await res.json();
                alertBox.classList.remove('hidden');
                if (data.success) {
                    alertBox.className = 'text-xs p-3 rounded-xl bg-emerald-50 text-emerald-900 border border-emerald-200';
                    alertBox.textContent = 'コメントを投稿しました。ページを再読込すると反映されます。';
                    document.getElementById('commentBody').value = '';
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alertBox.className = 'text-xs p-3 rounded-xl bg-rose-50 text-rose-900 border border-rose-200';
                    alertBox.textContent = data.error || '投稿エラーが発生しました。';
                }
            } catch (e) {
                alertBox.classList.remove('hidden');
                alertBox.className = 'text-xs p-3 rounded-xl bg-rose-50 text-rose-900 border border-rose-200';
                alertBox.textContent = '通信エラーが発生しました。';
            }
        }
    </script>
</body>
</html>
