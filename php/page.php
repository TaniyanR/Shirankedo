<?php
/**
 * DB管理の固定ページ表示
 * 特殊slug:
 * - trade: 相互リンク・相互RSS申請フォームを本文下に表示
 * - news: お知らせ一覧を本文下に表示
 * - que: お問い合わせフォームを本文下に表示
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/SettingsManager.php';
require_once __DIR__ . '/classes/TradeEngine.php';
require_once __DIR__ . '/classes/StaticPageManager.php';

try {
    MigrationAddFeatures::run();
} catch (Throwable $e) {}

$slug = trim($_GET['slug'] ?? 'about');
$siteName = 'しらんけど';
$db = null;

try {
    $db = Database::getConnection();
    $siteRow = $db->query("SELECT name FROM sites WHERE id = 1 LIMIT 1")->fetch();
    if (!empty($siteRow['name'])) {
        $siteName = $siteRow['name'];
    }
} catch (Throwable $e) {}

$page = null;
$publishedPages = [];
try {
    $page = StaticPageManager::findBySlug($slug, 1, true);
    $publishedPages = StaticPageManager::published(1);
} catch (Throwable $e) {}

if (!$page) {
    http_response_code(404);
    $pageTitle = 'ページが見つかりません | ' . $siteName;
} else {
    $pageTitle = $page['title'] . ' | ' . $siteName;
}

// お問い合わせフォーム
$contactSuccess = false;
$contactError = '';
if ($page && ($page['special_type'] ?? 'content') === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $websiteTrap = trim($_POST['website'] ?? '');

    if ($websiteTrap !== '') {
        $contactSuccess = true;
    } elseif ($name === '' || $email === '' || $message === '') {
        $contactError = 'お名前、メールアドレス、お問い合わせ内容は必須項目です。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = '正しいメールアドレスの形式で入力してください。';
    } else {
        if ($db) {
            try {
                $stmt = $db->prepare("INSERT INTO contacts (name, email, subject, message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (Throwable $e) {}
        }
        $contactSuccess = true;
    }
}

// 相互リンク・相互RSS申請
$tradeSuccess = false;
$tradeError = '';
if ($page && ($page['special_type'] ?? 'content') === 'trade' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteTitle = trim($_POST['site_name'] ?? '');
    $siteUrl = trim($_POST['url'] ?? '');
    $rawRss = trim($_POST['rss_url'] ?? '');
    $honeyTrap = trim($_POST['trap_field'] ?? '');
    $validRssUrls = TradeEngine::extractRssUrls($rawRss);

    if ($honeyTrap !== '') {
        $tradeSuccess = true;
    } elseif ($siteTitle === '' || $siteUrl === '' || $rawRss === '') {
        $tradeError = 'サイト名、URL、RSSのURLはすべて必須項目です。';
    } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $tradeError = 'サイトURLは「https://〜」の正しい形式でご入力ください。';
    } elseif (empty($validRssUrls)) {
        $tradeError = 'RSSのURLは正しい形式で1つ以上ご入力ください。';
    } else {
        if ($db) {
            try {
                $check = $db->prepare("SELECT id FROM trade_sites WHERE url = ? LIMIT 1");
                $check->execute([$siteUrl]);
                if ($check->fetch()) {
                    $tradeError = 'このサイトURLは既に登録申請済みです。';
                } else {
                    $savedRss = implode("\n", $validRssUrls);
                    $stmt = $db->prepare("INSERT INTO trade_sites (site_id, site_name, url, rss_url, status, return_rate, created_at) VALUES (1, ?, ?, ?, 'pending', 100, NOW())");
                    $stmt->execute([$siteTitle, $siteUrl, $savedRss]);
                    $tradeSuccess = true;
                }
            } catch (Throwable $e) {
                $tradeError = '送信中にエラーが発生しました。';
            }
        }
    }
}

// お知らせ一覧
$newsList = [];
if ($page && ($page['special_type'] ?? 'content') === 'news' && $db) {
    try {
        $newsList = $db->query("SELECT * FROM announcements WHERE site_id = 1 AND is_public = 1 ORDER BY published_at DESC LIMIT 50")->fetchAll();
    } catch (Throwable $e) {}
}

$headCustomTags = SettingsManager::get('head_custom_tags');
$bodyTopTags = SettingsManager::get('body_top_tags');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page ? mb_substr(strip_tags($page['body_html'] ?? ''), 0, 120) : 'ページが見つかりません') ?>">
    <meta name="referrer" content="unsafe-url">
    <?= $headCustomTags ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&family=Shippori+Mincho+B1:wght@600;800&display=swap" rel="stylesheet">
    <style>
        .font-mincho { font-family: 'Shippori Mincho B1', serif; }
        .font-sans { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
        .page-body h2, .page-body h3 { font-weight: 800; color: #1c1917; margin-top: 1.25rem; margin-bottom: .5rem; }
        .page-body h2 { font-size: 1.25rem; }
        .page-body h3 { font-size: 1.05rem; }
        .page-body p { margin: .75rem 0; line-height: 1.9; }
        .page-body ul, .page-body ol { margin: .75rem 0; padding-left: 1.5rem; }
        .page-body ul { list-style: disc; }
        .page-body ol { list-style: decimal; }
        .page-body blockquote { margin: 1rem 0; padding: 1rem; border-left: 4px solid #f59e0b; background: #fafaf9; border-radius: 0 .75rem .75rem 0; }
        .page-body a { color: #b45309; text-decoration: underline; }
    </style>
</head>
<body class="bg-stone-100 text-stone-900 min-h-screen flex flex-col font-sans antialiased">
    <?= $bodyTopTags ?>

    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-stone-200 px-4 sm:px-6 py-3 shadow-sm">
        <div class="max-w-4xl mx-auto flex items-center justify-between gap-4">
            <a href="/" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500 text-stone-950 font-black text-xl flex items-center justify-center shadow-md rotate-[-2deg]">知</div>
                <div>
                    <span class="text-xl font-black tracking-tight text-stone-950 block leading-none"><?= htmlspecialchars($siteName) ?></span>
                    <span class="text-[11px] text-stone-500 tracking-wider block mt-0.5">ネット話題を客観分析。最後はしらんけど。</span>
                </div>
            </a>
            <a href="/" class="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-bold">← トップへ戻る</a>
        </div>
    </header>

    <?php if (!empty($publishedPages)): ?>
    <div class="bg-white border-b border-stone-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 flex items-center gap-2 overflow-x-auto py-2 text-xs font-bold">
            <?php foreach ($publishedPages as $navPage): ?>
                <a href="page.php?slug=<?= rawurlencode($navPage['slug']) ?>" class="px-3.5 py-1.5 rounded-xl transition-all whitespace-nowrap <?= $slug === $navPage['slug'] ? 'bg-stone-950 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-100' ?>">
                    <?= htmlspecialchars($navPage['title']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <?php if (!$page): ?>
            <div class="bg-white rounded-3xl border border-stone-200 p-10 text-center shadow-sm">
                <div class="text-4xl mb-3">404</div>
                <h1 class="text-xl font-black text-stone-900">ページが見つかりません</h1>
                <p class="text-sm text-stone-500 mt-2">削除されたか、現在非公開のページです。</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-10 shadow-sm space-y-8">
                <div class="border-b border-stone-100 pb-4">
                    <h1 class="text-2xl sm:text-3xl font-black text-stone-950"><?= htmlspecialchars($page['title']) ?></h1>
                </div>

                <?php if (trim($page['body_html'] ?? '') !== ''): ?>
                    <div class="page-body text-stone-700 text-sm sm:text-base leading-relaxed">
                        <?= $page['body_html'] ?>
                    </div>
                <?php endif; ?>

                <?php if (($page['special_type'] ?? 'content') === 'trade'): ?>
                    <?php if ($tradeSuccess): ?>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center space-y-3">
                            <div class="text-3xl">🎉</div>
                            <h2 class="text-base font-black text-emerald-900">相互リンク・相互RSSのご依頼を受け付けました</h2>
                        </div>
                    <?php else: ?>
                        <?php if ($tradeError): ?>
                            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold"><?= htmlspecialchars($tradeError) ?></div>
                        <?php endif; ?>
                        <form method="POST" class="space-y-4 pt-2">
                            <div class="hidden"><input type="text" name="trap_field" value=""></div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">貴サイト名 <span class="text-rose-600">*</span></label>
                                <input type="text" name="site_name" required value="<?= htmlspecialchars($_POST['site_name'] ?? '') ?>" class="w-full text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50 focus:bg-white focus:outline-none focus:border-stone-900">
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">貴サイトURL <span class="text-rose-600">*</span></label>
                                <input type="url" name="url" required value="<?= htmlspecialchars($_POST['url'] ?? '') ?>" placeholder="https://example.com" class="w-full text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50 focus:bg-white focus:outline-none focus:border-stone-900">
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">RSSフィードURL（複数可） <span class="text-rose-600">*</span></label>
                                <textarea name="rss_url" required rows="4" class="w-full text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50 focus:bg-white focus:outline-none focus:border-stone-900 font-mono"><?= htmlspecialchars($_POST['rss_url'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="w-full py-4 rounded-2xl bg-stone-950 hover:bg-stone-800 text-white font-black text-sm">相互リンク・相互RSSを申請する →</button>
                        </form>
                    <?php endif; ?>

                <?php elseif (($page['special_type'] ?? 'content') === 'news'): ?>
                    <?php if (empty($newsList)): ?>
                        <div class="text-center py-10 text-xs text-stone-400">現在新しいお知らせはありません。</div>
                    <?php else: ?>
                        <div class="divide-y divide-stone-100">
                            <?php foreach ($newsList as $n): ?>
                                <article class="py-5 space-y-2">
                                    <time class="text-xs text-stone-400"><?= date('Y年m月d日 H:i', strtotime($n['published_at'])) ?></time>
                                    <h2 class="text-base font-bold text-stone-950"><?= htmlspecialchars($n['title']) ?></h2>
                                    <p class="text-sm text-stone-600 leading-relaxed"><?= nl2br(htmlspecialchars($n['body'])) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif (($page['special_type'] ?? 'content') === 'contact'): ?>
                    <?php if ($contactSuccess): ?>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center space-y-3">
                            <div class="text-3xl">✉️</div>
                            <h2 class="text-base font-black text-emerald-900">お問い合わせを送信しました</h2>
                        </div>
                    <?php else: ?>
                        <?php if ($contactError): ?>
                            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold"><?= htmlspecialchars($contactError) ?></div>
                        <?php endif; ?>
                        <form method="POST" class="space-y-4 pt-2">
                            <div class="hidden"><input type="text" name="website" value=""></div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">お名前 <span class="text-rose-600">*</span></label>
                                <input type="text" name="name" required class="w-full text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50 focus:bg-white focus:outline-none focus:border-stone-900">
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">メールアドレス <span class="text-rose-600">*</span></label>
                                <input type="email" name="email" required class="w-full text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50 focus:bg-white focus:outline-none focus:border-stone-900">
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">件名</label>
                                <input type="text" name="subject" class="w-full text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50 focus:bg-white focus:outline-none focus:border-stone-900">
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">お問い合わせ内容 <span class="text-rose-600">*</span></label>
                                <textarea name="message" required rows="6" class="w-full text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50 focus:bg-white focus:outline-none focus:border-stone-900"></textarea>
                            </div>
                            <button type="submit" class="w-full py-4 rounded-2xl bg-stone-950 hover:bg-stone-800 text-white font-black text-sm">お問い合わせを送信する →</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-stone-900 text-stone-400 text-xs py-8 px-4 border-t border-stone-800 mt-12">
        <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-white font-black text-sm"><?= htmlspecialchars($siteName) ?></div>
            <?php if (!empty($publishedPages)): ?>
            <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-bold">
                <?php foreach ($publishedPages as $footerPage): ?>
                    <a href="page.php?slug=<?= rawurlencode($footerPage['slug']) ?>" class="hover:text-amber-400 transition-colors"><?= htmlspecialchars($footerPage['title']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </footer>
</body>
</html>
