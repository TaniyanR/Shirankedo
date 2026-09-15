<?php
/**
 * 固定ページ管理コントローラー (page.php)
 * 対応スラッグ:
 * - about: サイトについて
 * - privacy-policy: プライバシーポリシー
 * - que: お問い合わせフォーム
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? 'about';
$siteName = 'しらんけど';
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// DB接続
$db = null;
try {
    $db = Database::getConnection();
} catch (Throwable $e) {
    // DB接続不可時も表示可能
}

// お問い合わせフォーム送信処理
$contactSuccess = false;
$contactError = '';

if ($slug === 'que' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $websiteTrap = trim($_POST['website'] ?? ''); // ハニーポット（スパム対策）

    if (!empty($websiteTrap)) {
        // スパムロボットが入力した場合は静かに成功扱い
        $contactSuccess = true;
    } elseif (empty($name) || empty($email) || empty($message)) {
        $contactError = 'お名前、メールアドレス、お問い合わせ内容は必須項目です。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = '正しいメールアドレスの形式で入力してください。';
    } else {
        $saved = false;
        if ($db) {
            try {
                // テーブル存在確認・作成
                $db->exec("CREATE TABLE IF NOT EXISTS contacts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) DEFAULT '',
                    message TEXT NOT NULL,
                    ip_address VARCHAR(45) DEFAULT '',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $stmt = $db->prepare("INSERT INTO contacts (name, email, subject, message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
                $saved = true;
            } catch (Throwable $e) {
                // DB保存エラー時はログ保存
            }
        }

        if (!$saved) {
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $logLine = sprintf("[%s] Name: %s, Email: %s, Subject: %s, IP: %s\nMessage:\n%s\n--------------------\n",
                date('Y-m-d H:i:s'), $name, $email, $subject, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $message
            );
            @file_put_contents($logDir . '/contact.log', $logLine, FILE_APPEND | LOCK_EX);
        }

        $contactSuccess = true;
    }
}

// ページごとのメタ情報
$metaTitles = [
    'about' => 'サイトについて',
    'privacy-policy' => 'Privacy Policy（プライバシーポリシー）',
    'que' => 'お問い合わせ'
];
$pageTitle = ($metaTitles[$slug] ?? 'ページ') . ' | ' . $siteName;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaTitles[$slug] ?? 'しらんけどの固定ページ') ?>です。">
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
        <div class="max-w-4xl mx-auto px-4 sm:px-6 flex items-center gap-2 overflow-x-auto py-2 text-xs font-bold">
            <a href="page.php?slug=about" class="px-3.5 py-1.5 rounded-xl transition-all whitespace-nowrap <?= $slug === 'about' ? 'bg-stone-950 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-100' ?>">
                サイトについて
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

            <?php if ($slug === 'about'): ?>
                <!-- ================= サイトについて ================= -->
                <div class="border-b border-stone-100 pb-5">
                    <h1 class="text-2xl sm:text-3xl font-black text-stone-950 tracking-tight">サイトについて</h1>
                    <p class="text-xs sm:text-sm text-stone-500 mt-1">「しらんけど」の運営理念・基本情報・リンクポリシーについて</p>
                </div>

                <div class="prose max-w-none text-stone-700 text-xs sm:text-sm leading-relaxed space-y-6">
                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 <?= htmlspecialchars($siteName) ?> 紹介 】
                        </h2>
                        <p>
                            <strong><?= htmlspecialchars($siteName) ?></strong>（<a href="<?= htmlspecialchars($currentUrl) ?>" class="text-amber-700 underline font-mono"><?= htmlspecialchars($currentUrl) ?></a>）は、インターネット上やSNSで今まさに話題となっているトレンド・ニュースを自動収集し、客観的な一次報道や公式発表の事実関係をもとに整理して提供するトレンド情報サイトです。
                        </p>
                        <p>
                            噂や偏向報道、過激な誹謗中傷に流されず、「実際のところ何が起きているのか？」を分かりやすくまとめ、記事の締めくくりには関西特有のクッション言葉である<strong>「〜しらんけど。」</strong>を添えることで、過剰に白黒をつけず肩の力を抜いて情報に接していただくことを目指しています。
                        </p>
                    </section>

                    <section class="space-y-2 bg-stone-50 p-5 rounded-2xl border border-stone-200">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2">
                            【 当サイト情報 】
                        </h2>
                        <ul class="space-y-1.5 font-mono text-xs">
                            <li><strong>サイト名：</strong> <?= htmlspecialchars($siteName) ?></li>
                            <li><strong>URL：</strong> <a href="<?= htmlspecialchars($currentUrl) ?>" class="text-stone-900 underline"><?= htmlspecialchars($currentUrl) ?></a></li>
                            <li><strong>運営形態：</strong> トレンド自動分析・事実検証メディア</li>
                            <li><strong>免責理念：</strong> 客観的事実を尊重しますが、最終判断は各自の責任でお願いします（しらんけど）。</li>
                        </ul>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 リンクについて 】
                        </h2>
                        <p>
                            当サイトは<strong>完全リンクフリー</strong>です。<br>
                            トップページはもちろん、どの個別記事・カテゴリページに対しても事前の許可なく自由にリンクを貼っていただいて構いません。SNSやブログでのご紹介・言及も歓迎いたします。<br>
                            ただし、記事内で引用・紹介している各ニュース配信元・メディア企業様・権利者様の画像・動画等の著作物につきましては、権利元の規約に従う必要がありますので、二次利用や無断転載・ダウンロードはご遠慮ください。
                        </p>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 相互リンクについて 】
                        </h2>
                        <p>
                            当サイトでは現在、個別での相互リンクの募集は原則として行っておりません。<br>
                            誠に恐れ入りますが、あらかじめご了承ください。
                        </p>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 独自指標「しらんけど指数」について 】
                        </h2>
                        <p>
                            当サイトでは、各話題の「勢い」「注目度」「話題の急上昇性」を0〜100点のスコアで可視化する<strong>「しらんけど指数」</strong>を独自に算出しています。<br>
                            点数が高いほど世間で大きな盛り上がりを見せている話題となりますが、真偽の最終保証を行うものではありません。軽やかな気持ちでお楽しみください。しらんけど。
                        </p>
                    </section>
                </div>

            <?php elseif ($slug === 'privacy-policy'): ?>
                <!-- ================= プライバシーポリシー ================= -->
                <div class="border-b border-stone-100 pb-5">
                    <h1 class="text-2xl sm:text-3xl font-black text-stone-950 tracking-tight">Privacy Policy</h1>
                    <p class="text-xs sm:text-sm text-stone-500 mt-1">個人情報保護方針・アクセス解析および免責事項について</p>
                </div>

                <div class="prose max-w-none text-stone-700 text-xs sm:text-sm leading-relaxed space-y-6">
                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 <?= htmlspecialchars($siteName) ?> について 】
                        </h2>
                        <p>
                            「<?= htmlspecialchars($siteName) ?>」（以下「当サイト」）にお越しいただき誠にありがとうございます。<br>
                            当サイトでは、適切な広告配信およびアクセス解析技術を活用して運営を行っております。
                        </p>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 リンクについて 】
                        </h2>
                        <p>
                            当サイトはリンクフリーです。<br>
                            どのページのどの記事にリンクを貼っていただいてもかまいません。<br>
                            ただし画像や引用メディアは元サイト・権利者様のものであり、お借りしている・引用しているだけですので、二次使用やダウンロードはご遠慮ください。
                        </p>
                    </section>

                    <section class="space-y-2 bg-stone-50 p-5 rounded-2xl border border-stone-200 font-mono text-xs">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 font-sans mb-1">
                            【 当サイト情報 】
                        </h2>
                        <div>サイト名：<?= htmlspecialchars($siteName) ?></div>
                        <div>URL：<?= htmlspecialchars($currentUrl) ?></div>
                        <div>お問い合わせ：<a href="page.php?slug=que" class="text-amber-800 underline font-sans">お問い合わせフォームはこちら</a></div>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 個人情報の利用目的 】
                        </h2>
                        <p>
                            当サイトでは、お問い合わせフォームのご利用時やコメント投稿時などに、氏名（ハンドルネーム）、メールアドレス等の個人情報をご入力いただく場合がございます。<br>
                            これらの個人情報は、ご質問への回答や必要な情報を電子メール等でご連絡する場合、あるいはコメント欄の健全な運営・スパム防止にのみ利用させていただくものであり、それ以外の目的では利用いたしません。
                        </p>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 個人情報の第三者への開示 】
                        </h2>
                        <p>
                            当サイトでは、お預かりした個人情報を適切に管理し、以下に該当する場合を除いて第三者に開示することはありません。
                        </p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>ご本人様の同意がある場合</li>
                            <li>法令に基づき開示することが必要である場合</li>
                            <li>人の生命、身体または財産の保護のために必要がある場合</li>
                            <li>公衆衛生の向上や児童の健全な育成のために特に必要な場合</li>
                        </ul>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 個人情報の開示、訂正、追加、削除、利用停止 】
                        </h2>
                        <p>
                            ご本人様から個人データの開示、訂正、削除、利用停止のご希望があった場合には、ご本人様であることを確認させていただいた上で、速やかに対応いたします。
                        </p>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 アクセス解析ツール（Googleアナリティクス等）について 】
                        </h2>
                        <p>
                            当サイトでは、サイトの利用動向を分析し改善に役立てる目的で「Googleアナリティクス」をはじめとするアクセス解析ツールを使用する場合があります。<br>
                            これらはトラフィックデータの収集のためにCookie（クッキー）を使用しています。<br>
                            このデータは匿名で収集されており、個人を特定するものではありません。Cookieを無効にすることで収集を拒否することが可能ですので、お使いのブラウザの設定をご確認ください。
                        </p>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 広告配信に関して 】
                        </h2>
                        <p>
                            当サイトでは、第三者配信事業者による広告サービスや成果報酬型広告（アフィリエイトプログラム）を利用する場合があります。<br>
                            広告配信事業者は、ユーザーの興味に応じた商品やサービスの広告を表示するため、当サイトや他サイトへのアクセスに関する情報（氏名、住所、メール アドレス、電話番号は含まれません）を使用することがあります。<br>
                            また、成果報酬型広告の効果測定および不正防止のため、閲覧したサイトのURL、表示・クリック日時等のアクセス情報が外部事業者に送信される場合がありますが、個人を特定する情報ではなく、目的外利用されることはありません。
                        </p>
                    </section>

                    <section class="space-y-2 bg-amber-50/70 border border-amber-200 p-5 rounded-2xl text-amber-950">
                        <h2 class="text-base sm:text-lg font-black text-amber-900 flex items-center gap-2 font-sans mb-1">
                            【 免責事項 】
                        </h2>
                        <p>
                            当サイトからリンクやバナーなどによって他のサイトに移動された場合、移動先サイトで提供される情報、サービス等について当サイトは一切の責任を負いません。<br>
                            当サイトのコンテンツ・情報につきましては、一次情報や公式報道に基づき可能な限り正確な情報を掲載するよう努めておりますが、情報の即時性やネットトレンドの性質上、誤情報が入り込んだり情報が古くなっている場合もございます。<br>
                            当サイトに掲載された内容によって生じた損害等の一切の責任を負いかねますのでご了承ください。<br>
                            サイト名の通り、最終的なご判断はご自身にてお願いいたします。しらんけど。
                        </p>
                    </section>

                    <section class="space-y-2">
                        <h2 class="text-base sm:text-lg font-black text-stone-950 flex items-center gap-2 border-l-4 border-amber-500 pl-3">
                            【 プライバシーポリシーの変更について 】
                        </h2>
                        <p>
                            当サイトは、個人情報に関して適用される日本の法令を遵守するとともに、本ポリシーの内容を適宜見直し、その改善に努めます。<br>
                            修正された最新のプライバシーポリシーは常に本ページにて開示されます。
                        </p>
                    </section>
                </div>

            <?php elseif ($slug === 'que'): ?>
                <!-- ================= お問い合わせ ================= -->
                <div class="border-b border-stone-100 pb-5">
                    <h1 class="text-2xl sm:text-3xl font-black text-stone-950 tracking-tight">お問い合わせ</h1>
                    <p class="text-xs sm:text-sm text-stone-500 mt-1">ご意見・ご要望・権利関係のご連絡は下記フォームよりお願いいたします。</p>
                </div>

                <?php if ($contactSuccess): ?>
                    <div class="bg-emerald-50 border-2 border-emerald-300 rounded-2xl p-6 sm:p-8 text-center space-y-3">
                        <div class="text-3xl">✉️</div>
                        <h2 class="text-lg sm:text-xl font-black text-emerald-900">お問い合わせを送信しました</h2>
                        <p class="text-xs sm:text-sm text-emerald-800 leading-relaxed max-w-md mx-auto">
                            お問い合わせいただき誠にありがとうございます。<br>
                            内容を確認の上、必要な場合はご連絡差し上げます。しらんけど。
                        </p>
                        <div class="pt-4">
                            <a href="/" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-stone-950 hover:bg-stone-800 text-white font-bold text-xs transition-colors">
                                ← トップページへ戻る
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <p class="text-xs sm:text-sm text-stone-600 leading-relaxed">
                            当サイトに関するお問い合わせ、記事内容に関するご指摘、削除要請や権利関係のご相談は、下記のお問い合わせフォームより送信してください。
                        </p>

                        <?php if (!empty($contactError)): ?>
                            <div class="p-4 bg-rose-50 border border-rose-300 rounded-2xl text-xs sm:text-sm text-rose-900 font-bold">
                                ⚠️ <?= htmlspecialchars($contactError) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="page.php?slug=que" class="space-y-4">
                            <!-- ハニーポット（非表示フィールド：スパム対策） -->
                            <input type="text" name="website" value="" autocomplete="off" tabindex="-1" style="display:none !important;" aria-hidden="true">

                            <div class="space-y-1.5">
                                <label for="contact-name" class="block text-xs font-bold text-stone-800">
                                    お名前 <span class="text-rose-600">*</span>
                                </label>
                                <input type="text" id="contact-name" name="name" required maxlength="100" placeholder="山田 太郎 / ハンドルネーム可" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            </div>

                            <div class="space-y-1.5">
                                <label for="contact-email" class="block text-xs font-bold text-stone-800">
                                    メールアドレス <span class="text-rose-600">*</span>
                                </label>
                                <input type="email" id="contact-email" name="email" required maxlength="254" placeholder="example@domain.com" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>

                            <div class="space-y-1.5">
                                <label for="contact-subject" class="block text-xs font-bold text-stone-800">
                                    件名
                                </label>
                                <input type="text" id="contact-subject" name="subject" maxlength="150" placeholder="記事内容について / サイトに関するご要望 等" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                            </div>

                            <div class="space-y-1.5">
                                <label for="contact-message" class="block text-xs font-bold text-stone-800">
                                    お問い合わせ内容 <span class="text-rose-600">*</span>
                                </label>
                                <textarea id="contact-message" name="message" required rows="6" placeholder="お問い合わせ内容を具体的にご入力ください。" class="w-full text-xs sm:text-sm p-3.5 rounded-2xl border border-stone-200 bg-stone-50/50 focus:bg-white focus:outline-none focus:border-stone-900 transition-colors leading-relaxed"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full py-4 px-6 rounded-2xl bg-stone-950 hover:bg-stone-800 text-white font-black text-sm shadow-md transition-all flex items-center justify-center gap-2">
                                    <span>お問い合わせを送信する</span>
                                    <span>→</span>
                                </button>
                            </div>

                            <p class="text-[11px] text-stone-400 text-center font-mincho pt-2">
                                「送信いただいた内容はプライバシーポリシーに則り適切に取り扱います。しらんけど。」
                            </p>
                        </form>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- 未知のスラッグ -->
                <div class="text-center py-12 space-y-4">
                    <h1 class="text-2xl font-black text-stone-900">ページが見つかりません</h1>
                    <p class="text-xs text-stone-500">指定されたページ（<?= htmlspecialchars($slug) ?>）は存在しないか、移動した可能性があります。</p>
                    <a href="/" class="inline-block px-4 py-2 bg-stone-900 text-white rounded-xl text-xs font-bold">トップページへ</a>
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
                <a href="page.php?slug=privacy-policy" class="hover:text-amber-400 transition-colors">プライバシーポリシー</a>
                <a href="page.php?slug=que" class="hover:text-amber-400 transition-colors">お問い合わせ</a>
            </div>
        </div>
    </footer>

</body>
</html>
