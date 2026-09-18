<?php
/**
 * 相互リンク・相互RSS依頼フォーム、お知らせページ対応
 * 対応スラッグ:
 * - about: サイトについて
 * - privacy-policy: プライバシーポリシー
 * - que: お問い合わせフォーム
 * - trade: 相互リンク・相互RSS依頼フォーム
 * - news: お知らせ一覧 (相互リンク承認・解除通知)
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/SettingsManager.php';
require_once __DIR__ . '/classes/TradeEngine.php';

$slug = $_GET['slug'] ?? 'about';
$siteName = 'しらんけど';

// DB接続
$db = null;
try {
    $db = Database::getConnection();
} catch (Throwable $e) {}

// 1. お問い合わせフォーム送信処理
$contactSuccess = false;
$contactError = '';
if ($slug === 'que' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $websiteTrap = trim($_POST['website'] ?? ''); // ハニーポット

    if (!empty($websiteTrap)) {
        $contactSuccess = true;
    } elseif (empty($name) || empty($email) || empty($message)) {
        $contactError = 'お名前、メールアドレス、お問い合わせ内容は必須項目です。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = '正しいメールアドレスの形式で入力してください。';
    } else {
        if ($db) {
            try {
                $stmt = $db->prepare("INSERT INTO contacts (name, email, subject, message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            } catch (Throwable $e) {}
        }
        $contactSuccess = true;
    }
}

// 2. 相互リンク・相互RSS依頼フォーム送信処理
$tradeSuccess = false;
$tradeError = '';
if ($slug === 'trade' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteTitle = trim($_POST['site_name'] ?? '');
    $siteUrl = trim($_POST['url'] ?? '');
    $rawRss = trim($_POST['rss_url'] ?? '');
    $honeyTrap = trim($_POST['trap_field'] ?? '');

    $validRssUrls = TradeEngine::extractRssUrls($rawRss);

    if (!empty($honeyTrap)) {
        $tradeSuccess = true;
    } elseif (empty($siteTitle) || empty($siteUrl) || empty($rawRss)) {
        $tradeError = 'サイト名、URL、RSSのURLはすべて必須項目です。';
    } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $tradeError = 'サイトURLは「https://〜」の正しい形式でご入力ください。';
    } elseif (empty($validRssUrls)) {
        $tradeError = 'RSSのURLは「https://〜」の正しい形式で1つ以上ご入力ください（複数ある場合は改行してください）。';
    } else {
        if ($db) {
            try {
                // 既存登録チェック
                $check = $db->prepare("SELECT id FROM trade_sites WHERE url = ?");
                $check->execute([$siteUrl]);
                if ($check->fetch()) {
                    $tradeError = 'このサイトURLは既に登録申請済みです。管理者の承認をお待ちください。';
                } else {
                    $savedRss = implode("\n", $validRssUrls);
                    $stmt = $db->prepare("INSERT INTO trade_sites (site_name, url, rss_url, status, return_rate, created_at) VALUES (?, ?, ?, 'pending', 100, NOW())");
                    $stmt->execute([$siteTitle, $siteUrl, $savedRss]);
                    $tradeSuccess = true;
                }
            } catch (Throwable $e) {
                $tradeError = '送信中にエラーが発生しました: ' . $e->getMessage();
            }
        }
    }
}

// 3. お知らせ一覧の取得
$newsList = [];
if ($slug === 'news' && $db) {
    try {
        $newsList = $db->query("SELECT * FROM announcements WHERE is_public = 1 ORDER BY published_at DESC LIMIT 50")->fetchAll();
    } catch (Throwable $e) {}
}

// ページごとのメタ情報
$metaTitles = [
    'about' => 'サイトについて',
    'privacy-policy' => 'Privacy Policy（プライバシーポリシー）',
    'que' => 'お問い合わせ',
    'trade' => '相互リンク・相互RSS依頼',
    'news' => 'お知らせ（相互リンク提携情報）'
];
$pageTitle = ($metaTitles[$slug] ?? 'ページ') . ' | ' . $siteName;

// カスタムタグ
$headCustomTags = SettingsManager::get('head_custom_tags');
$bodyTopTags = SettingsManager::get('body_top_tags');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaTitles[$slug] ?? 'しらんけどの固定ページ') ?>です。">
    <meta name="referrer" content="unsafe-url">
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
        <div class="max-w-4xl mx-auto flex items-center justify-between gap-4">
            <a href="/" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500 text-stone-950 font-black text-xl flex items-center justify-center shadow-md rotate-[-2deg]">
                    知
                </div>
                <div>
                    <span class="text-xl font-black tracking-tight text-stone-950 block leading-none">
                        <?= htmlspecialchars($siteName) ?>
                    </span>
                    <span class="text-[11px] text-stone-500 tracking-wider block mt-0.5">
                        ネット話題を客観分析。最後はしらんけど。
                    </span>
                </div>
            </a>

            <div class="flex items-center gap-2 sm:gap-3 text-xs font-bold">
                <a href="/" class="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 transition-colors">
                    ← トップへ戻る
                </a>
            </div>
        </div>
    </header>

    <!-- サブナビゲーション -->
    <div class="bg-white border-b border-stone-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 flex items-center gap-2 overflow-x-auto py-2 text-xs font-bold scrollbar-none">
            <a href="page.php?slug=about" class="px-3.5 py-1.5 rounded-xl transition-all whitespace-nowrap <?= $slug === 'about' ? 'bg-stone-950 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-100' ?>">
                サイトについて
            </a>
            <a href="page.php?slug=trade" class="px-3.5 py-1.5 rounded-xl transition-all whitespace-nowrap <?= $slug === 'trade' ? 'bg-stone-950 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-100' ?>">
                🤝 相互リンク依頼
            </a>
            <a href="page.php?slug=news" class="px-3.5 py-1.5 rounded-xl transition-all whitespace-nowrap <?= $slug === 'news' ? 'bg-stone-950 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-100' ?>">
                📢 お知らせ
            </a>
            <a href="page.php?slug=privacy-policy" class="px-3.5 py-1.5 rounded-xl transition-all whitespace-nowrap <?= $slug === 'privacy-policy' ? 'bg-stone-950 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-100' ?>">
                プライバシーポリシー
            </a>
            <a href="page.php?slug=que" class="px-3.5 py-1.5 rounded-xl transition-all whitespace-nowrap <?= $slug === 'que' ? 'bg-stone-950 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-100' ?>">
                お問い合わせ
            </a>
        </div>
    </div>

    <!-- メインコンテンツ -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-10 shadow-sm space-y-8">

            <!-- 1. サイトについて -->
            <?php if ($slug === 'about'): ?>
                <div class="space-y-6">
                    <div class="border-b border-stone-100 pb-4">
                        <span class="text-xs font-bold text-amber-800 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">About</span>
                        <h1 class="text-2xl sm:text-3xl font-black text-stone-950 mt-2">サイトについて</h1>
                    </div>
                    <div class="prose max-w-none text-stone-700 text-sm sm:text-base leading-relaxed space-y-4">
                        <p class="font-bold text-stone-900">
                            「しらんけど」は、SNSや検索エンジンで今まさに急上昇しているトレンド話題を自動収集し、一次情報（公式発表・大手報道機関）を確認した上で要約してお届けするメディアです。
                        </p>
                        <p>
                            ネット上の噂やセンセーショナルな言説に惑わされず、客観的な事実（ファクト）だけを抽出。最後に関西特有のクッション表現「しらんけど。」を添えることで、適度な距離感とユーモアを持ってトレンドを楽しめる場を提供しています。
                        </p>
                        <div class="bg-stone-50 border-l-4 border-amber-500 p-4 rounded-r-2xl font-mincho text-stone-800">
                            「ネットの情報は真に受けすぎず、気楽に楽しむのが一番です。しらんけど。」
                        </div>
                    </div>
                </div>

            <!-- 2. 相互リンク・相互RSS依頼フォーム -->
            <?php elseif ($slug === 'trade'): ?>
                <div class="space-y-6">
                    <div class="border-b border-stone-100 pb-4">
                        <span class="text-xs font-bold text-amber-800 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">Trade Request</span>
                        <h1 class="text-2xl sm:text-3xl font-black text-stone-950 mt-2">相互リンク・相互RSS依頼</h1>
                    </div>

                    <p class="text-xs sm:text-sm text-stone-600 leading-relaxed">
                        当サイトでは、アンテナサイト・まとめサイト・ブログ運営者様との相互リンクおよび相互RSSを広く募集しております。<br>
                        当サイトは<strong>アクセス返還エンジン</strong>を導入しており、貴サイトからいただいたアクセス（IN）に応じて、100%以上の比率で確実にアクセス（OUT）をお返しいたします。
                    </p>

                    <div class="bg-amber-50/70 border border-amber-200 p-4 rounded-2xl text-xs text-amber-950 space-y-1.5">
                        <div class="font-black text-amber-900 flex items-center gap-1.5">
                            <span>📌</span> 掲載条件と仕様
                        </div>
                        <ul class="list-disc list-inside space-y-1 text-[11px] text-stone-700">
                            <li>相互リンク（テキストリンク）はPC版サイドバーに掲載されます。</li>
                            <li>相互RSSは、PC版（ヘッダー下画像・サイドバー画像・本文下画像＆テキスト・フッター上画像）、スマホ版（ヘッダー画像＆テキスト・フッターテキスト）に自動配信されます。</li>
                            <li>アイキャッチ画像を含まない記事は、自動的にテキスト枠のみで配信されます。</li>
                            <li>審査後、承認されたサイト様は「お知らせ」ページにて提携開始の告知を行います。</li>
                        </ul>
                    </div>

                    <?php if ($tradeSuccess): ?>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center space-y-3">
                            <div class="text-3xl">🎉</div>
                            <h2 class="text-base font-black text-emerald-900">相互リンク・相互RSSのご依頼を受け付けました！</h2>
                            <p class="text-xs text-emerald-800">
                                ご申請ありがとうございます。管理者が確認の上、順次承認とお知らせ掲載を行います。<br>
                                貴サイト側でも当サイト（<strong><?= htmlspecialchars($siteName) ?></strong> / <code class="font-bold">https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'shirankedo.bichi.xyz') ?></code>）へのリンク・RSS登録をお願いいたします。
                            </p>
                        </div>
                    <?php else: ?>
                        <?php if ($tradeError): ?>
                            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold">
                                <?= htmlspecialchars($tradeError) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-4">
                            <!-- ハニーポット（スパム除け） -->
                            <div class="hidden">
                                <input type="text" name="trap_field" value="">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">
                                    貴サイト名 <span class="text-rose-600">*</span>
                                </label>
                                <input type="text" name="site_name" required placeholder="例: トレンドアンテナ速報" value="<?= htmlspecialchars($_POST['site_name'] ?? '') ?>" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">
                                    貴サイトURL <span class="text-rose-600">*</span>
                                </label>
                                <input type="url" name="url" required placeholder="https://example.com" value="<?= htmlspecialchars($_POST['url'] ?? '') ?>" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">
                                    貴サイトRSSフィードURL（複数登録可能） <span class="text-rose-600">*</span>
                                </label>
                                <textarea name="rss_url" required rows="3" placeholder="https://example.com/rss.xml&#10;https://example.com/feed/&#10;（複数ある場合は改行して入力）" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors font-mono"><?= htmlspecialchars($_POST['rss_url'] ?? '') ?></textarea>
                                <p class="text-[11px] text-stone-500">※ カテゴリ別RSSなど複数のフィードをお持ちの場合は、改行して複数入力いただけます。</p>
                            </div>

                            <div class="pt-3">
                                <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-stone-950 hover:bg-stone-800 text-white font-black text-sm shadow-md transition-all flex items-center justify-center gap-2">
                                    <span>相互リンク・相互RSSを申請する</span>
                                    <span>→</span>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

            <!-- 3. お知らせ一覧 -->
            <?php elseif ($slug === 'news'): ?>
                <div class="space-y-6">
                    <div class="border-b border-stone-100 pb-4">
                        <span class="text-xs font-bold text-amber-800 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">Announcements</span>
                        <h1 class="text-2xl sm:text-3xl font-black text-stone-950 mt-2">お知らせ</h1>
                    </div>

                    <?php if (empty($newsList)): ?>
                        <div class="text-center py-12 text-xs text-stone-400">
                            現在新しいお知らせはありません。
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-stone-100">
                            <?php foreach ($newsList as $n): ?>
                                <article class="py-5 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $n['type'] === 'trade_approved' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($n['type'] === 'trade_removed' ? 'bg-rose-50 text-rose-800 border border-rose-200' : 'bg-stone-100 text-stone-700') ?>">
                                            <?= $n['type'] === 'trade_approved' ? '相互提携開始' : ($n['type'] === 'trade_removed' ? 'リンク掲載終了' : 'お知らせ') ?>
                                        </span>
                                        <time class="text-xs text-stone-400"><?= date('Y年m月d日 H:i', strtotime($n['published_at'])) ?></time>
                                    </div>
                                    <h2 class="text-base font-bold text-stone-950">
                                        <?= htmlspecialchars($n['title']) ?>
                                    </h2>
                                    <p class="text-xs sm:text-sm text-stone-600 leading-relaxed">
                                        <?= nl2br(htmlspecialchars($n['body'])) ?>
                                    </p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <!-- 4. プライバシーポリシー -->
            <?php elseif ($slug === 'privacy-policy'): ?>
                <div class="space-y-6">
                    <div class="border-b border-stone-100 pb-4">
                        <span class="text-xs font-bold text-amber-800 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">Privacy Policy</span>
                        <h1 class="text-2xl sm:text-3xl font-black text-stone-950 mt-2">プライバシーポリシー</h1>
                    </div>
                    <div class="prose max-w-none text-stone-700 text-xs sm:text-sm leading-relaxed space-y-4">
                        <h3 class="font-bold text-stone-900 text-base">1. 個人情報の収集・利用目的</h3>
                        <p>当サイトでは、お問い合わせや相互リンク申請の際に、サイト名・URL・メールアドレス等の個人情報をご登録いただく場合があります。これらの個人情報は、ご質問への回答や提携管理のためにのみ利用し、目的外の利用は行いません。</p>
                        <h3 class="font-bold text-stone-900 text-base">2. アクセス解析とCookie</h3>
                        <p>当サイトでは、Google Analyticsをはじめとするアクセス解析ツールや、アクセストレードのためのリファラー情報を使用しています。これらはトラフィックデータの収集のために匿名で処理され、個人を特定するものではありません。</p>
                        <h3 class="font-bold text-stone-900 text-base">3. 免責事項</h3>
                        <p>当サイトの掲載内容は、各機関の一次報道に基づき万全を期して要約しておりますが、その正確性・安全性を保証するものではありません。情報の利用によって生じたいかなる損害についても責任を負いかねます。しらんけど。</p>
                    </div>
                </div>

            <!-- 5. お問い合わせ -->
            <?php elseif ($slug === 'que'): ?>
                <div class="space-y-6">
                    <div class="border-b border-stone-100 pb-4">
                        <span class="text-xs font-bold text-amber-800 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">Contact</span>
                        <h1 class="text-2xl sm:text-3xl font-black text-stone-950 mt-2">お問い合わせ</h1>
                    </div>

                    <?php if ($contactSuccess): ?>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center space-y-3">
                            <div class="text-3xl">✉️</div>
                            <h2 class="text-base font-black text-emerald-900">お問い合わせを送信しました</h2>
                            <p class="text-xs text-emerald-800">
                                メッセージをお送りいただきありがとうございます。内容を確認の上、必要に応じてご連絡いたします。
                            </p>
                        </div>
                    <?php else: ?>
                        <?php if ($contactError): ?>
                            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold">
                                <?= htmlspecialchars($contactError) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-4">
                            <div class="hidden"><input type="text" name="website" value=""></div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">お名前 <span class="text-rose-600">*</span></label>
                                <input type="text" name="name" required class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">メールアドレス <span class="text-rose-600">*</span></label>
                                <input type="email" name="email" required class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">件名</label>
                                <input type="text" name="subject" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-stone-800">お問い合わせ内容 <span class="text-rose-600">*</span></label>
                                <textarea name="message" required rows="6" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors leading-relaxed"></textarea>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-stone-950 hover:bg-stone-800 text-white font-black text-sm shadow-md transition-all flex items-center justify-center gap-2">
                                    <span>お問い合わせを送信する</span>
                                    <span>→</span>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <!-- フッター -->
    <footer class="bg-stone-900 text-stone-400 text-xs py-8 px-4 border-t border-stone-800 mt-12">
        <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="space-y-1 text-center sm:text-left">
                <div class="text-white font-black text-sm tracking-wider">
                    <?= htmlspecialchars($siteName) ?>
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
