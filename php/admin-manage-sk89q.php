<?php
/**
 * 新しい推測不可能な管理画面 (admin-manage-sk89q.php)
 * WordPress風のカラフル＆直感的UI、スマートフォン完全対応
 * 
 * 機能タブ:
 * 1. ダッシュボード & 記事管理
 * 2. 記事の新規手動作成 & トレンド収集実行
 * 3. 🖼️ アイキャッチ画像プール管理 (800x450px・キーワード3つ設定・最大3万枚対応)
 * 4. 🔗 相互リンク・相互RSS管理 (承認・非承認・返還率80%/100%/120%/150%・特別優遇ブースト)
 * 5. 📢 お知らせ管理 (相互リンク承認・解除通知)
 * 6. 💰 アフィリエイト広告スロット設定 (PCヘッダー/サイド上下、スマホヘッダー上下)
 * 7. 🏷️ SEO・カスタムタグ設定 (<meta name="referrer" content="unsafe-url">, <head>タグ, <body>直下タグ)
 * 8. 🔒 セキュリティ設定 (管理画面URLスラッグ変更・管理者パスワード変更)
 */
session_start();
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';

// 初期セットアップテーブルの存在保証
try {
    MigrationAddFeatures::run();
} catch (Throwable $e) {}

// 管理者認証設定
$adminPass = SettingsManager::get('admin_password', getenv('ADMIN_PASSWORD') ?: 'admin1234');
$currentSecretPath = SettingsManager::get('admin_secret_path', 'manage-sk89q');
$thisFileUrl = 'admin-' . $currentSecretPath . '.php';

// ログイン処理
$loginError = '';
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $inputPass = $_POST['password'] ?? '';
    if ($inputPass === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        header("Location: {$thisFileUrl}");
        exit;
    } else {
        $loginError = 'パスワードが正しくありません。';
    }
}

// ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header("Location: {$thisFileUrl}");
    exit;
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);

// DB接続
$db = null;
try {
    $db = Database::getConnection();
} catch (Throwable $e) {}

// --- 管理者POST操作 ---
$flashMessage = '';
$flashType = 'success'; // success | error

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? 'dashboard';
    $op = $_POST['op'] ?? '';

    try {
        // 1. 記事作成
        if ($op === 'create_article') {
            $title = trim($_POST['title'] ?? '');
            $why = trim($_POST['why_trending'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $conclusion = trim($_POST['conclusion_sentence'] ?? '知らんけど。');
            $index = (int)($_POST['shirankedo_index'] ?? 75);
            $label = $index >= 80 ? 'めっちゃ話題' : ($index >= 50 ? '話題' : 'ちょい話題');
            $catId = (int)($_POST['category_id'] ?? 1);
            $slug = 'trend-' . time();
            $imgUrl = trim($_POST['image_url'] ?? '');

            $stmt = $db->prepare("INSERT INTO articles (site_id, category_id, title, slug, why_trending, body, conclusion_sentence, shirankedo_index, index_label, image_url, status, published_at)
                                  VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())");
            $stmt->execute([$catId, $title, $slug, $why, $body, $conclusion, $index, $label, $imgUrl]);
            $flashMessage = '新着記事を正常に作成・公開しました！';
        }

        // 2. 記事ステータス変更（非公開）
        if ($op === 'toggle_article_status') {
            $artId = (int)($_POST['article_id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? 'private';
            $stmt = $db->prepare("UPDATE articles SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $artId]);
            $flashMessage = "記事ID #{$artId} のステータスを更新しました。";
        }

        // 3. アイキャッチ画像プールの追加（URLまたはローカルPCファイルアップロード・800x450px・キーワード3つ）
        if ($op === 'add_pool_image') {
            $url = trim($_POST['url'] ?? '');
            $alt = trim($_POST['alt_text'] ?? 'トレンドアイキャッチ');
            $catId = (int)($_POST['category_id'] ?? 1);
            $kw1 = trim($_POST['kw1'] ?? '');
            $kw2 = trim($_POST['kw2'] ?? '');
            $kw3 = trim($_POST['kw3'] ?? '');

            // ローカルファイルアップロード対応
            if (isset($_FILES['local_image']) && $_FILES['local_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['local_image']['tmp_name'];
                $fileName = $_FILES['local_image']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $safeName = 'eyecatch_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($fileTmp, $dest)) {
                        $url = '/uploads/' . $safeName;
                    }
                }
            }

            if (empty($url)) {
                throw new Exception('画像URLを入力するか、画像をアップロードしてください。');
            }

            $stmt = $db->prepare("INSERT INTO images (site_id, category_id, filename, url, alt_text, is_active) VALUES (1, ?, 'custom_pool.webp', ?, ?, 1)");
            $stmt->execute([$catId, $url, $alt]);
            $newImgId = $db->lastInsertId();

            $kws = array_filter([$kw1, $kw2, $kw3]);
            if (!empty($kws)) {
                $kwStmt = $db->prepare("INSERT INTO image_keywords (image_id, keyword) VALUES (?, ?)");
                foreach ($kws as $k) {
                    $kwStmt->execute([$newImgId, $k]);
                }
            }
            $flashMessage = 'アイキャッチ画像をプールに登録しました！（キーワード3件設定済み）';
        }

        // 4. 相互リンク・相互RSSの承認・返還率更新
        if ($op === 'update_trade_site') {
            $tradeId = (int)($_POST['trade_id'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            $rate = (int)($_POST['return_rate'] ?? 100);
            $isBoosted = !empty($_POST['is_boosted']) ? 1 : 0;
            $boostWeight = (int)($_POST['boost_weight'] ?? 1);

            // 以前のステータスを取得してお知らせ連動
            $prev = $db->prepare("SELECT site_name, url, status FROM trade_sites WHERE id = ?");
            $prev->execute([$tradeId]);
            $oldSite = $prev->fetch();

            $stmt = $db->prepare("UPDATE trade_sites SET status = ?, return_rate = ?, is_boosted = ?, boost_weight = ? WHERE id = ?");
            $stmt->execute([$status, $rate, $isBoosted, $boostWeight, $tradeId]);

            // 承認時にお知らせ自動投稿
            if ($oldSite && $oldSite['status'] !== 'approved' && $status === 'approved') {
                $annTitle = "【相互リンク】「" . $oldSite['site_name'] . "」様と相互リンク・相互RSSを開始しました";
                $annBody = "「" . $oldSite['site_name'] . "」様（" . $oldSite['url'] . "）と相互リンクおよび相互RSSの提携を開始いたしました。今後ともよろしくお願い申し上げます。";
                $db->prepare("INSERT INTO announcements (title, body, type, trade_site_id, is_public) VALUES (?, ?, 'trade_approved', ?, 1)")
                   ->execute([$annTitle, $annBody, $tradeId]);
            }
            // 削除・解除時にお知らせ自動投稿
            if ($oldSite && $oldSite['status'] === 'approved' && ($status === 'deleted' || $status === 'rejected')) {
                $annTitle = "【相互リンク】「" . $oldSite['site_name'] . "」様との相互リンクを終了いたしました";
                $annBody = "「" . $oldSite['site_name'] . "」様との相互リンク・相互RSSの掲載を終了いたしました。これまでありがとうございました。";
                $db->prepare("INSERT INTO announcements (title, body, type, trade_site_id, is_public) VALUES (?, ?, 'trade_removed', ?, 1)")
                   ->execute([$annTitle, $annBody, $tradeId]);
            }

            $flashMessage = "相互リンク・RSSサイトの設定を更新しました。";
        }

        // 5. アフィリエイト広告スロット & 表示/非表示設定の更新
        if ($op === 'save_ads') {
            SettingsManager::set('show_ads', isset($_POST['show_ads']) ? '1' : '0');
            SettingsManager::set('ad_pc_header', $_POST['ad_pc_header'] ?? '');
            SettingsManager::set('ad_pc_sidebar_top', $_POST['ad_pc_sidebar_top'] ?? '');
            SettingsManager::set('ad_pc_sidebar_bottom', $_POST['ad_pc_sidebar_bottom'] ?? '');
            SettingsManager::set('ad_sp_header_top', $_POST['ad_sp_header_top'] ?? '');
            SettingsManager::set('ad_sp_header_bottom', $_POST['ad_sp_header_bottom'] ?? '');
            $flashMessage = 'アフィリエイト広告スロット・表示設定を保存しました。';
        }

        // 5-2. 相互RSS表示/非表示設定の更新
        if ($op === 'save_rss_settings') {
            SettingsManager::set('show_rss', isset($_POST['show_rss']) ? '1' : '0');
            $flashMessage = '相互RSS表示設定を保存しました。';
        }

        // 5-3. Gemini API設定の更新
        if ($op === 'save_gemini') {
            SettingsManager::set('gemini_api_key', trim($_POST['gemini_api_key'] ?? ''));
            SettingsManager::set('gemini_model', trim($_POST['gemini_model'] ?? 'gemini-2.5-flash'));
            $flashMessage = 'Gemini AI API設定を保存しました。';
        }

        // 6. SEO・カスタムタグの更新 (<meta name="referrer" content="unsafe-url"> 等)
        if ($op === 'save_tags') {
            SettingsManager::set('head_custom_tags', $_POST['head_custom_tags'] ?? '');
            SettingsManager::set('body_top_tags', $_POST['body_top_tags'] ?? '');
            $flashMessage = 'SEOメタタグ・body直下カスタムタグを保存しました。';
        }

        // 7. セキュリティ設定（管理画面URLスラッグ変更・パスワード変更）
        if ($op === 'save_security') {
            $newSecret = trim($_POST['admin_secret_path'] ?? '');
            $newAdminPass = trim($_POST['admin_password'] ?? '');

            if (!empty($newSecret)) {
                // 英数字ハイフンのみ許可
                $sanitizedSecret = preg_replace('/[^a-zA-Z0-9_-]/', '', $newSecret);
                if (strlen($sanitizedSecret) >= 6) {
                    SettingsManager::set('admin_secret_path', $sanitizedSecret);
                    $currentSecretPath = $sanitizedSecret;
                    $thisFileUrl = 'admin-' . $sanitizedSecret . '.php';
                } else {
                    throw new Exception('シークレットURLスラッグは6文字以上の半角英数字で指定してください。');
                }
            }

            if (!empty($newAdminPass)) {
                if (strlen($newAdminPass) >= 6) {
                    SettingsManager::set('admin_password', $newAdminPass);
                } else {
                    throw new Exception('新しいパスワードは6文字以上で設定してください。');
                }
            }

            $flashMessage = "セキュリティ設定を保存しました。現在の管理画面URLは 「/{$thisFileUrl}」 です。";
        }

        // 8. トレンド自動収集ワーカー実行
        if ($op === 'run_worker') {
            if (file_exists(__DIR__ . '/cron/worker.php')) {
                ob_start();
                include __DIR__ . '/cron/worker.php';
                $out = ob_get_clean();
                $flashMessage = '自動収集ワーカーを実行しました！<br><pre class="text-xs mt-2 p-2 bg-stone-900 text-stone-200 rounded max-h-40 overflow-y-auto">' . htmlspecialchars(mb_substr($out, 0, 500)) . '...</pre>';
            } else {
                $flashMessage = 'cron/worker.php が見つかりませんでした。';
            }
        }

    } catch (Throwable $e) {
        $flashMessage = 'エラー: ' . $e->getMessage();
        $flashType = 'error';
    }
}

// 各種データ取得
$currentTab = $_GET['tab'] ?? 'dashboard';

$articles = [];
$totalArticles = 0;
$totalIn = 0;
$totalOut = 0;
$tradeSites = [];
$poolImages = [];
$announcements = [];
$categories = [];

if ($db && $isLoggedIn) {
    try {
        $totalArticles = (int)$db->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
        $articles = $db->query("SELECT id, title, slug, shirankedo_index, index_label, status, published_at FROM articles ORDER BY id DESC LIMIT 20")->fetchAll();
        $categories = $db->query("SELECT id, name FROM categories WHERE site_id = 1")->fetchAll();
        
        // 相互リンク・アクセストレード集計
        $tradeSites = $db->query("SELECT * FROM trade_sites ORDER BY id DESC")->fetchAll();
        $inSum = $db->query("SELECT SUM(in_count) as total_in, SUM(out_count) as total_out FROM trade_sites")->fetch();
        $totalIn = (int)($inSum['total_in'] ?? 0);
        $totalOut = (int)($inSum['total_out'] ?? 0);

        // アイキャッチ画像プール
        $poolImages = $db->query("SELECT i.*, 
                                  (SELECT GROUP_CONCAT(keyword SEPARATOR ', ') FROM image_keywords WHERE image_id = i.id) as keywords
                                  FROM images i ORDER BY i.id DESC LIMIT 30")->fetchAll();

        // お知らせ一覧
        $announcements = $db->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 20")->fetchAll();

        // アクセス解析データ取得
        $analyticsStats = AnalyticsTracker::getStats(14);

    } catch (Throwable $e) {}
}

// タブ定義 (WordPress風メニュー)
$navTabs = [
    'dashboard' => ['icon' => '📊', 'label' => 'ダッシュボード', 'badge' => null],
    'analytics' => ['icon' => '📈', 'label' => 'アクセス解析', 'badge' => null],
    'articles' => ['icon' => '📝', 'label' => '記事管理・投稿', 'badge' => $totalArticles],
    'images' => ['icon' => '🖼️', 'label' => 'アイキャッチプール', 'badge' => count($poolImages)],
    'gemini' => ['icon' => '✨', 'label' => 'Gemini API設定', 'badge' => null],
    'trade' => ['icon' => '🔗', 'label' => '相互リンク・RSS返還', 'badge' => count($tradeSites)],
    'ads' => ['icon' => '💰', 'label' => '広告スロット設定', 'badge' => null],
    'seo_tags' => ['icon' => '🏷️', 'label' => 'SEO・タグ設定', 'badge' => null],
    'announcements' => ['icon' => '📢', 'label' => 'お知らせ一覧', 'badge' => count($announcements)],
    'security' => ['icon' => '🔒', 'label' => 'セキュリティ設定', 'badge' => null],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - しらんけど WordPress風コンソール</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Shippori+Mincho+B1:wght@600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
        .font-mincho { font-family: 'Shippori Mincho B1', serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen antialiased flex flex-col">

    <?php if (!$isLoggedIn): ?>
        <!-- ログイン画面 -->
        <div class="flex-1 flex items-center justify-center p-4">
            <div class="bg-white max-w-md w-full rounded-3xl border border-slate-200 p-8 shadow-xl space-y-6">
                <div class="text-center space-y-2">
                    <div class="w-14 h-14 rounded-2xl bg-amber-500 text-slate-950 font-black text-2xl flex items-center justify-center shadow-lg mx-auto rotate-[-3deg]">
                        知
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">しらんけど 管理コンソール</h1>
                    <p class="text-xs text-slate-500">認証パスワードを入力してログインしてください</p>
                </div>

                <?php if ($loginError): ?>
                    <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold text-center">
                        <?= htmlspecialchars($loginError) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700">管理者パスワード</label>
                        <input type="password" name="password" required autofocus placeholder="管理者パスワードを入力してください" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm font-mono">
                    </div>
                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-bold text-sm shadow-md transition-all">
                        ログインする →
                    </button>
                </form>

                <div class="text-center text-[11px] text-slate-400">
                    <a href="/" class="hover:underline">← トップページへ戻る</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- ログイン中: WordPress風管理ダッシュボード -->

        <!-- トップヘッダーバー (WP Admin Bar風) -->
        <header class="bg-slate-900 text-white px-4 sm:px-6 py-2.5 flex items-center justify-between border-b border-slate-800 sticky top-0 z-50">
            <div class="flex items-center gap-3">
                <a href="/" target="_blank" class="flex items-center gap-2 text-xs font-bold text-slate-300 hover:text-white transition-colors">
                    <span class="w-6 h-6 rounded-lg bg-amber-500 text-slate-950 font-black flex items-center justify-center text-xs">知</span>
                    <span class="hidden sm:inline">しらんけど サイトを表示 ↗</span>
                </a>
            </div>

            <div class="flex items-center gap-3 text-xs">
                <span class="text-slate-400 hidden sm:inline">管理者ログイン中</span>
                <a href="?logout=1" class="px-3 py-1 rounded-xl bg-slate-800 hover:bg-rose-900/80 text-rose-300 font-bold border border-slate-700 transition-colors">
                    ログアウト
                </a>
            </div>
        </header>

        <!-- メインレイアウト: 左サイドバー + 右コンテンツ -->
        <div class="flex-1 flex flex-col md:flex-row">
            
            <!-- WordPress風 左サイドバー -->
            <aside class="w-full md:w-64 bg-slate-950 text-slate-300 border-r border-slate-800 flex-shrink-0 p-4 space-y-6">
                <!-- サイトタイトル -->
                <div class="px-2 py-1">
                    <div class="text-sm font-black text-white tracking-wide">しらんけど 管理システム</div>
                    <div class="text-[10px] text-slate-500">v2.4 Auto-Trend & Trade Engine</div>
                </div>

                <!-- メニューナビゲーション -->
                <nav class="space-y-1">
                    <?php foreach ($navTabs as $tabKey => $t): 
                        $isActive = $currentTab === $tabKey;
                        $btnClass = $isActive 
                            ? 'bg-amber-500 text-slate-950 font-black shadow-md' 
                            : 'text-slate-300 hover:bg-slate-900 hover:text-white font-medium';
                    ?>
                        <a href="?tab=<?= $tabKey ?>" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs transition-all <?= $btnClass ?>">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base"><?= $t['icon'] ?></span>
                                <span><?= $t['label'] ?></span>
                            </div>
                            <?php if ($t['badge'] !== null): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isActive ? 'bg-slate-950 text-amber-300' : 'bg-slate-800 text-slate-400' ?>">
                                    <?= $t['badge'] ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- 即時実行アクション -->
                <div class="pt-4 border-t border-slate-800/80 space-y-2">
                    <div class="text-[11px] font-bold text-slate-400 px-2">⚡ ワンクリック実行</div>
                    <form method="POST">
                        <input type="hidden" name="op" value="run_worker">
                        <button type="submit" class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                            <span>🚀 トレンド自動収集を実行</span>
                        </button>
                    </form>
                </div>
            </aside>

            <!-- 右側メインコンテンツパネル -->
            <main class="flex-1 p-4 sm:p-8 space-y-6 overflow-x-hidden">

                <!-- フラッシュ通知メッセージ -->
                <?php if ($flashMessage): ?>
                    <div class="p-4 rounded-2xl text-xs sm:text-sm font-bold shadow-sm border <?= $flashType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900' ?>">
                        <?= $flashMessage ?>
                    </div>
                <?php endif; ?>

                <!-- 1. ダッシュボード タブ -->
                <?php if ($currentTab === 'dashboard'): ?>
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight">ダッシュボード</h1>
                                <p class="text-xs text-slate-500">しらんけど トレンドサイト全体の稼働状況・アクセス返還ステータス</p>
                            </div>
                        </div>

                        <!-- 統計カラフルカード -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-3xl p-5 text-white shadow-md space-y-2">
                                <div class="text-xs font-bold text-indigo-100 flex items-center justify-between">
                                    <span>公開記事数</span>
                                    <span>📝</span>
                                </div>
                                <div class="text-3xl font-black"><?= $totalArticles ?> <span class="text-xs font-normal">本</span></div>
                                <div class="text-[11px] text-indigo-100">一次情報確認済みトレンド</div>
                            </div>

                            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-3xl p-5 text-white shadow-md space-y-2">
                                <div class="text-xs font-bold text-emerald-100 flex items-center justify-between">
                                    <span>相互アクセス流入 (IN)</span>
                                    <span>📥</span>
                                </div>
                                <div class="text-3xl font-black"><?= number_format($totalIn) ?> <span class="text-xs font-normal">アクセス</span></div>
                                <div class="text-[11px] text-emerald-100">相手サイトからの逆アクセス</div>
                            </div>

                            <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-3xl p-5 text-white shadow-md space-y-2">
                                <div class="text-xs font-bold text-amber-100 flex items-center justify-between">
                                    <span>相互アクセス送出 (OUT)</span>
                                    <span>📤</span>
                                </div>
                                <div class="text-3xl font-black"><?= number_format($totalOut) ?> <span class="text-xs font-normal">アクセス</span></div>
                                <div class="text-[11px] text-amber-100">返還率（80〜150%）で還元中</div>
                            </div>

                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-3xl p-5 text-white shadow-md space-y-2">
                                <div class="text-xs font-bold text-purple-100 flex items-center justify-between">
                                    <span>提携サイト数</span>
                                    <span>🔗</span>
                                </div>
                                <div class="text-3xl font-black"><?= count($tradeSites) ?> <span class="text-xs font-normal">サイト</span></div>
                                <div class="text-[11px] text-purple-100">相互リンク & 相互RSS承認済み</div>
                            </div>
                        </div>

                        <!-- 最近の記事一覧クイックプレビュー -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-black text-slate-900">最新公開記事 (直近20件)</h2>
                                <a href="?tab=articles" class="text-xs text-amber-600 hover:text-amber-700 font-bold">すべて見る →</a>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-slate-400">
                                            <th class="py-2.5 font-bold">ID</th>
                                            <th class="py-2.5 font-bold">タイトル</th>
                                            <th class="py-2.5 font-bold">指数</th>
                                            <th class="py-2.5 font-bold">ステータス</th>
                                            <th class="py-2.5 font-bold">公開日時</th>
                                            <th class="py-2.5 font-bold text-right">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($articles as $a): ?>
                                            <tr class="hover:bg-slate-50">
                                                <td class="py-3 font-mono font-bold text-slate-500">#<?= $a['id'] ?></td>
                                                <td class="py-3 font-bold text-slate-900">
                                                    <a href="article.php?id=<?= $a['id'] ?>" target="_blank" class="hover:text-amber-600">
                                                        <?= htmlspecialchars($a['title']) ?> ↗
                                                    </a>
                                                </td>
                                                <td class="py-3">
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                        <?= $a['shirankedo_index'] ?>点
                                                    </span>
                                                </td>
                                                <td class="py-3">
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold <?= $a['status'] === 'published' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' ?>">
                                                        <?= $a['status'] ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 text-slate-500"><?= $a['published_at'] ?></td>
                                                <td class="py-3 text-right">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="op" value="toggle_article_status">
                                                        <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                                        <input type="hidden" name="new_status" value="<?= $a['status'] === 'published' ? 'private' : 'published' ?>">
                                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[11px] font-bold <?= $a['status'] === 'published' ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                                                            <?= $a['status'] === 'published' ? '非公開' : '公開' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <!-- 2. 記事管理・新規手動投稿 タブ -->
                <?php elseif ($currentTab === 'articles'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">記事管理・新規投稿</h1>
                            <p class="text-xs text-slate-500">Gemini AI による自動生成に加え、手動でのトレンド記事作成も可能です</p>
                        </div>

                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <h2 class="text-base font-black text-slate-900">記事の新規作成</h2>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="op" value="create_article">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">記事タイトル <span class="text-rose-600">*</span></label>
                                        <input type="text" name="title" required placeholder="例: 千鳥の新番組が異例のTVer1位を獲得した件" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">カテゴリ</label>
                                        <select name="category_id" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">💡 なぜ話題？（要約ボックス）</label>
                                    <textarea name="why_trending" rows="2" placeholder="新企画の予測不能な展開がSNSで急上昇し..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500"></textarea>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">記事本文（事実確認済みファクト）</label>
                                    <textarea name="body" rows="6" placeholder="客観的事実に基づいた本文を入力..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500 leading-relaxed"></textarea>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">締めの言葉（しらんけど構文）</label>
                                        <input type="text" name="conclusion_sentence" value="今後の展開に注目が集まります。しらんけど。" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm font-mincho focus:outline-none focus:border-amber-500">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">アイキャッチ画像URL (800x450px推奨)</label>
                                        <input type="text" name="image_url" placeholder="https://images.unsplash.com/photo-..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-6 py-3 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-bold text-xs shadow-md transition-all">
                                        記事を公開する →
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <!-- 3. 🖼️ アイキャッチ画像プール管理 タブ (最大3万枚対応・キーワード3つ) -->
                <?php elseif ($currentTab === 'images'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">アイキャッチ画像プール管理</h1>
                            <p class="text-xs text-slate-500">最大30,000枚規模対応。Gemini AIが記事の重要キーワードと照合して最適な画像（800×450px）を自動選定します</p>
                        </div>

                        <!-- 画像追加フォーム -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                            <h2 class="text-base font-black text-slate-900">新しいアイキャッチ画像の追加登録</h2>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="op" value="add_pool_image">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">💻 ローカルPCから画像をアップロード</label>
                                        <input type="file" name="local_image" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800">
                                        <p class="text-[10px] text-slate-400">※ JPG, PNG, WEBP, GIF (800x450px推奨)</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">🌐 または 画像URLを直接指定</label>
                                        <input type="url" name="url" placeholder="https://... または /uploads/image.webp" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:outline-none focus:border-amber-500">
                                        <p class="text-[10px] text-slate-400">※ ファイルを選択しない場合はURLを入力してください</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">画像説明（altテキスト）</label>
                                        <input type="text" name="alt_text" placeholder="例: お笑いステージ・バラエティ収録イメージ" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">カテゴリ</label>
                                        <select name="category_id" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- キーワード3つ設定 -->
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">自動マッチング用 キーワード（3つ設定）</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <input type="text" name="kw1" placeholder="キーワード1 (例: 千鳥)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                        <input type="text" name="kw2" placeholder="キーワード2 (例: お笑い)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                        <input type="text" name="kw3" placeholder="キーワード3 (例: テレビ)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-6 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all">
                                        プールに登録する
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 登録済み画像一覧 -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <h2 class="text-base font-black text-slate-900">登録済み画像プール (最新30件)</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                                <?php foreach ($poolImages as $pi): ?>
                                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50 space-y-2 p-2 flex flex-col justify-between">
                                        <div class="aspect-video bg-slate-200 rounded-xl overflow-hidden">
                                            <img src="<?= htmlspecialchars($pi['url']) ?>" alt="<?= htmlspecialchars($pi['alt_text']) ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-[11px] font-bold text-slate-800 truncate"><?= htmlspecialchars($pi['alt_text']) ?></div>
                                            <div class="text-[10px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-100 truncate">
                                                🏷️ <?= htmlspecialchars($pi['keywords'] ?: '未設定') ?>
                                            </div>
                                            <div class="text-[10px] text-slate-400 flex items-center justify-between">
                                                <span>使用: <?= $pi['use_count'] ?>回</span>
                                                <span>ID #<?= $pi['id'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                <!-- 4. 🔗 相互リンク・相互RSS返還 タブ -->
                <?php elseif ($currentTab === 'trade'): ?>
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight">相互リンク・相互RSS & アクセス返還管理</h1>
                                <p class="text-xs text-slate-500">相手サイトからの流入（IN）に応じたアクセス返還（100%、80%、120%、150%）と特別優遇枠を管理します</p>
                            </div>

                            <!-- 相互RSS表示/非表示トグルスイッチ -->
                            <form method="POST" class="bg-white border border-slate-200 px-4 py-3 rounded-2xl shadow-sm flex items-center gap-3">
                                <input type="hidden" name="op" value="save_rss_settings">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="checkbox" name="show_rss" value="1" <?= SettingsManager::get('show_rss', '1') === '1' ? 'checked' : '' ?> onchange="this.form.submit()" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400">
                                    <span class="text-xs font-bold text-slate-800">サイト上に相互RSS枠を表示する</span>
                                </label>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= SettingsManager::get('show_rss', '1') === '1' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>">
                                    <?= SettingsManager::get('show_rss', '1') === '1' ? '現在: 表示中' : '現在: 非表示' ?>
                                </span>
                            </form>
                        </div>

                        <!-- 相互サイト一覧 & 承認・返還率コントロール -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <h2 class="text-base font-black text-slate-900">提携サイト一覧 (アクセス比率コントロール)</h2>
                            
                            <?php if (empty($tradeSites)): ?>
                                <p class="text-xs text-slate-400 py-6 text-center">まだ相互リンクの依頼はありません。「相互リンク依頼」ページから受け付け可能です。</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-100 text-slate-400">
                                                <th class="py-2.5 font-bold">サイト名</th>
                                                <th class="py-2.5 font-bold">URL / RSS</th>
                                                <th class="py-2.5 font-bold">IN / OUT</th>
                                                <th class="py-2.5 font-bold">返還率設定</th>
                                                <th class="py-2.5 font-bold">特別優遇</th>
                                                <th class="py-2.5 font-bold">ステータス</th>
                                                <th class="py-2.5 font-bold text-right">保存</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <?php foreach ($tradeSites as $ts): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="op" value="update_trade_site">
                                                    <input type="hidden" name="trade_id" value="<?= $ts['id'] ?>">
                                                    <tr class="hover:bg-slate-50">
                                                        <td class="py-3 font-bold text-slate-900"><?= htmlspecialchars($ts['site_name']) ?></td>
                                                        <td class="py-3 text-[11px] text-slate-500 space-y-0.5">
                                                            <a href="<?= htmlspecialchars($ts['url']) ?>" target="_blank" class="text-indigo-600 hover:underline block truncate max-w-xs">
                                                                🌐 <?= htmlspecialchars($ts['url']) ?>
                                                            </a>
                                                            <span class="text-slate-400 block truncate max-w-xs">
                                                                📡 <?= htmlspecialchars($ts['rss_url']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="py-3 font-mono">
                                                            <span class="text-emerald-600 font-bold">IN: <?= $ts['in_count'] ?></span> / 
                                                            <span class="text-amber-600 font-bold">OUT: <?= $ts['out_count'] ?></span>
                                                        </td>
                                                        <td class="py-3">
                                                            <select name="return_rate" class="px-2.5 py-1 rounded-xl border border-slate-200 text-xs font-bold bg-white">
                                                                <option value="80" <?= $ts['return_rate'] == 80 ? 'selected' : '' ?>>80% 返還</option>
                                                                <option value="100" <?= $ts['return_rate'] == 100 ? 'selected' : '' ?>>100% 返還 (等倍)</option>
                                                                <option value="120" <?= $ts['return_rate'] == 120 ? 'selected' : '' ?>>120% 返還</option>
                                                                <option value="150" <?= $ts['return_rate'] == 150 ? 'selected' : '' ?>>150% 返還 (還元)</option>
                                                                <option value="200" <?= $ts['return_rate'] == 200 ? 'selected' : '' ?>>200% 返還 (倍返し)</option>
                                                            </select>
                                                        </td>
                                                        <td class="py-3">
                                                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                                                <input type="checkbox" name="is_boosted" value="1" <?= $ts['is_boosted'] ? 'checked' : '' ?> class="rounded border-slate-300 text-amber-500">
                                                                <span class="text-[11px] font-bold text-amber-800">特別優遇枠</span>
                                                            </label>
                                                        </td>
                                                        <td class="py-3">
                                                            <select name="status" class="px-2.5 py-1 rounded-xl border border-slate-200 text-xs font-bold <?= $ts['status'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800' ?>">
                                                                <option value="pending" <?= $ts['status'] === 'pending' ? 'selected' : '' ?>>保留（未承認）</option>
                                                                <option value="approved" <?= $ts['status'] === 'approved' ? 'selected' : '' ?>>承認（掲載中）</option>
                                                                <option value="rejected" <?= $ts['status'] === 'rejected' ? 'selected' : '' ?>>非承認</option>
                                                                <option value="deleted" <?= $ts['status'] === 'deleted' ? 'selected' : '' ?>>リンク解除</option>
                                                            </select>
                                                        </td>
                                                        <td class="py-3 text-right">
                                                            <button type="submit" class="px-3 py-1 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-[11px]">
                                                                保存
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </form>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- 5. 💰 アフィリエイト広告スロット設定 タブ -->
                <?php elseif ($currentTab === 'ads'): ?>
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight">アフィリエイト広告スロット設定</h1>
                                <p class="text-xs text-slate-500">PC・スマホそれぞれの指定サイズ広告タグ（A8, もしも, バリューコマース等）を設置・管理します</p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="op" value="save_ads">

                            <!-- 広告表示/非表示スイッチ -->
                            <div class="bg-amber-50 border border-amber-200 rounded-3xl p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="text-sm font-black text-amber-950 flex items-center gap-2">
                                        <span>📢</span> アフィリエイト広告マスター表示切替
                                    </div>
                                    <p class="text-xs text-amber-800">
                                        チェックを外すと、サイト全体の広告枠が一括で非表示になります（審査時や純粋なコンテンツ重視時に便利です）。
                                    </p>
                                </div>
                                <label class="relative flex items-center gap-2.5 cursor-pointer bg-white px-5 py-3 rounded-2xl border border-amber-300 shadow-sm">
                                    <input type="checkbox" name="show_ads" value="1" <?= SettingsManager::get('show_ads', '1') === '1' ? 'checked' : '' ?> class="w-5 h-5 rounded text-amber-500 focus:ring-amber-400">
                                    <span class="text-xs font-black text-slate-800">サイト全体で広告を表示する</span>
                                </label>
                            </div>

                            <!-- PC広告スロット -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-5">
                                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                    <span class="text-lg">💻</span>
                                    <h2 class="text-base font-black text-slate-900">PC専用 広告スロット</h2>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">PC ヘッダー内 (468×60px)</label>
                                    <textarea name="ad_pc_header" rows="3" class="w-full p-3 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_pc_header')) ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">PC サイド上 (300×250px)</label>
                                        <textarea name="ad_pc_sidebar_top" rows="3" class="w-full p-3 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_pc_sidebar_top')) ?></textarea>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">PC サイド下 (300×250px)</label>
                                        <textarea name="ad_pc_sidebar_bottom" rows="3" class="w-full p-3 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_pc_sidebar_bottom')) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- スマホ広告スロット -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-5">
                                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                    <span class="text-lg">📱</span>
                                    <h2 class="text-base font-black text-slate-900">スマホ専用 広告スロット</h2>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">スマホ ヘッダー上 (300×250px)</label>
                                        <textarea name="ad_sp_header_top" rows="3" class="w-full p-3 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_sp_header_top')) ?></textarea>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">スマホ ヘッダー下 (300×250px)</label>
                                        <textarea name="ad_sp_header_bottom" rows="3" class="w-full p-3 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_sp_header_bottom')) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-8 py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-black text-xs shadow-md transition-all">
                                    広告設定を保存する
                                </button>
                            </div>
                        </form>
                    </div>

                <!-- 6. 🏷️ SEO・カスタムタグ設定 タブ -->
                <?php elseif ($currentTab === 'seo_tags'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">SEO・メタタグ & カスタムタグ設定</h1>
                            <p class="text-xs text-slate-500">アクセストレード用リファラータグ、Google Search Console、Bing Webmaster Tools、Analytics等の挿入</p>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="op" value="save_tags">

                            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-bold text-slate-800">&lt;head&gt; 内に挿入するカスタムタグ</label>
                                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                            ✓ &lt;meta name="referrer" content="unsafe-url"&gt; 適用中
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500">Google Search Console所有権メタタグ、Bing Webmasterメタタグ、OGPタグなどを自由に追加できます。</p>
                                    <textarea name="head_custom_tags" rows="6" class="w-full p-3.5 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('head_custom_tags')) ?></textarea>
                                </div>

                                <div class="space-y-1.5 pt-4 border-t border-slate-100">
                                    <label class="block text-xs font-bold text-slate-800">&lt;body&gt; 直下に挿入するカスタムタグ</label>
                                    <p class="text-[11px] text-slate-500">Google Tag Manager（GTM body）、アクセスカウンター、トラッキングコード等の挿入場所です。</p>
                                    <textarea name="body_top_tags" rows="6" class="w-full p-3.5 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('body_top_tags')) ?></textarea>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-black text-xs shadow-md transition-all">
                                        タグ設定を保存する
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                <!-- 7. 📢 お知らせ一覧 タブ -->
                <?php elseif ($currentTab === 'announcements'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">お知らせ一覧（相互リンク承認・解除通知）</h1>
                            <p class="text-xs text-slate-500">提携サイトの承認・解除時に自動投稿されたお知らせ履歴です</p>
                        </div>

                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <?php if (empty($announcements)): ?>
                                <p class="text-xs text-slate-400 py-6 text-center">まだお知らせはありません。</p>
                            <?php else: ?>
                                <div class="divide-y divide-slate-100">
                                    <?php foreach ($announcements as $ann): ?>
                                        <div class="py-4 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $ann['type'] === 'trade_approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                                                    <?= $ann['type'] === 'trade_approved' ? '相互提携' : 'お知らせ' ?>
                                                </span>
                                                <span class="text-xs text-slate-400"><?= $ann['published_at'] ?></span>
                                            </div>
                                            <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($ann['title']) ?></h3>
                                            <p class="text-xs text-slate-600"><?= nl2br(htmlspecialchars($ann['body'])) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- 8. 🔒 セキュリティ設定 タブ (推測不能URL & パスワード) -->
                <?php elseif ($currentTab === 'security'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">セキュリティ設定（管理画面URL・パスワード）</h1>
                            <p class="text-xs text-slate-500">外部から想像できない独自のシークレットURLを設定し、第三者の不正アクセスをブロックします</p>
                        </div>

                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <form method="POST" class="space-y-5">
                                <input type="hidden" name="op" value="save_security">

                                <div class="space-y-2 bg-amber-50 border border-amber-200 p-4 rounded-2xl">
                                    <div class="text-xs font-black text-amber-900 flex items-center gap-1.5">
                                        <span>🛡️</span> 現在のアクセスURL
                                    </div>
                                    <div class="text-sm font-mono font-bold text-amber-950">
                                        https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'shirankedo.bichi.xyz') ?>/<?= htmlspecialchars($thisFileUrl) ?>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">
                                        管理画面シークレットURLスラッグ <span class="text-rose-600">*</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-mono text-slate-400">/admin-</span>
                                        <input type="text" name="admin_secret_path" value="<?= htmlspecialchars($currentSecretPath) ?>" required minlength="6" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500">
                                        <span class="text-xs font-mono text-slate-400">.php</span>
                                    </div>
                                    <p class="text-[11px] text-slate-400">
                                        ※ ランダムな英数字を設定することで、攻撃者が管理画面の場所を特定できなくなります。
                                    </p>
                                </div>

                                <div class="space-y-1.5 pt-2 border-t border-slate-100">
                                    <label class="block text-xs font-bold text-slate-700">管理者パスワード変更</label>
                                    <input type="password" name="admin_password" placeholder="変更する場合のみ新しいパスワードを入力（6文字以上）" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500">
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-black text-xs shadow-md transition-all">
                                        セキュリティ設定を更新する
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <!-- 9. 📈 アクセス解析 タブ -->
                <?php elseif ($currentTab === 'analytics'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">リアルタイム・アクセス解析</h1>
                            <p class="text-xs text-slate-500">しらんけど サイトのPV数、流入元（X、Instagram、検索、相互RSS）、端末別比率を詳しく可視化します</p>
                        </div>

                        <?php 
                        $stats = AnalyticsTracker::getStats(14);
                        $totalPv = $stats['total_pv'] ?? 0;
                        $todayPv = $stats['today_pv'] ?? 0;
                        $yesterdayPv = $stats['yesterday_pv'] ?? 0;
                        ?>

                        <!-- サマリーカード -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-400">総ページビュー数 (全期間)</div>
                                <div class="text-3xl font-black text-slate-900"><?= number_format($totalPv) ?> <span class="text-xs font-normal text-slate-500">PV</span></div>
                            </div>
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-emerald-600">本日のアクセス数 (Today)</div>
                                <div class="text-3xl font-black text-emerald-600"><?= number_format($todayPv) ?> <span class="text-xs font-normal text-emerald-500">PV</span></div>
                            </div>
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-400">昨日のアクセス数 (Yesterday)</div>
                                <div class="text-3xl font-black text-slate-700"><?= number_format($yesterdayPv) ?> <span class="text-xs font-normal text-slate-500">PV</span></div>
                            </div>
                        </div>

                        <!-- 流入元 & 端末比率 -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- 流入元 (リファラー) -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                    <span>🌐</span> 主な参照元 (Referrer)
                                </h2>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-100 text-slate-400">
                                                <th class="py-2 font-bold">ドメイン / 参照元</th>
                                                <th class="py-2 font-bold text-right">アクセス数</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <?php if (empty($stats['referers'])): ?>
                                                <tr><td colspan="2" class="py-4 text-center text-slate-400">まだ参照元データがありません</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($stats['referers'] as $ref): ?>
                                                    <tr>
                                                        <td class="py-2.5 font-bold text-slate-800"><?= htmlspecialchars($ref['referer_host']) ?></td>
                                                        <td class="py-2.5 font-mono font-bold text-amber-600 text-right"><?= number_format($ref['count']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 端末比率 (デバイス) -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                    <span>📱</span> 端末比率 (Device Ratio)
                                </h2>
                                <div class="space-y-3 pt-2">
                                    <?php 
                                    $devSum = array_sum($stats['devices'] ?? []) ?: 1;
                                    $devLabels = ['mobile' => 'スマートフォン (Mobile)', 'pc' => 'パソコン (Desktop)', 'tablet' => 'タブレット (Tablet)'];
                                    foreach (['mobile', 'pc', 'tablet'] as $d): 
                                        $cnt = $stats['devices'][$d] ?? 0;
                                        $pct = round(($cnt / $devSum) * 100, 1);
                                    ?>
                                        <div class="space-y-1">
                                            <div class="flex justify-between text-xs font-bold text-slate-700">
                                                <span><?= $devLabels[$d] ?></span>
                                                <span class="font-mono"><?= $cnt ?> PV (<?= $pct ?>%)</span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                                <div class="bg-amber-500 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- 人気記事ランキング -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>🔥</span> 人気記事ランキング (直近14日間)
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-slate-400">
                                            <th class="py-2.5 font-bold">順位</th>
                                            <th class="py-2.5 font-bold">記事タイトル</th>
                                            <th class="py-2.5 font-bold">しらんけど指数</th>
                                            <th class="py-2.5 font-bold text-right">閲覧数 (PV)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php if (empty($stats['top_articles'])): ?>
                                            <tr><td colspan="4" class="py-4 text-center text-slate-400">閲覧ログがまだ記録されていません</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($stats['top_articles'] as $idx => $ta): ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-3 font-black text-amber-600">#<?= $idx + 1 ?></td>
                                                    <td class="py-3 font-bold text-slate-900">
                                                        <a href="article.php?id=<?= $ta['id'] ?>" target="_blank" class="hover:text-amber-600">
                                                            <?= htmlspecialchars($ta['title']) ?> ↗
                                                        </a>
                                                    </td>
                                                    <td class="py-3">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                            <?= $ta['shirankedo_index'] ?>点
                                                        </span>
                                                    </td>
                                                    <td class="py-3 font-mono font-bold text-slate-800 text-right">
                                                        <?= number_format($ta['pv']) ?> PV
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <!-- 10. ✨ Gemini API設定 タブ -->
                <?php elseif ($currentTab === 'gemini'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Gemini AI 設定</h1>
                            <p class="text-xs text-slate-500">Google Gemini APIキーを登録し、話題の自動収集・一次情報確認・記事自動生成パイプラインを稼働させます</p>
                        </div>

                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <form method="POST" class="space-y-5">
                                <input type="hidden" name="op" value="save_gemini">

                                <div class="bg-indigo-50 border border-indigo-200 p-4 rounded-2xl text-xs text-indigo-900 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <span>💡</span> Google AI StudioでAPIキーを取得できます
                                    </div>
                                    <p class="text-indigo-700">
                                        Google AI Studio (<a href="https://aistudio.google.com/" target="_blank" class="underline font-bold">https://aistudio.google.com/</a>) で発行したAPIキーを入力してください。
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">Gemini API Key</label>
                                    <input type="password" name="gemini_api_key" value="<?= htmlspecialchars(SettingsManager::get('gemini_api_key')) ?>" placeholder="AIzaSy..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500">
                                    <p class="text-[11px] text-slate-400">※ 入力されたキーはデータベースに暗号化保存され、記事自動生成時にのみ利用されます。</p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">使用AIモデル</label>
                                    <select name="gemini_model" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:border-amber-500">
                                        <option value="gemini-2.5-flash" <?= SettingsManager::get('gemini_model', 'gemini-2.5-flash') === 'gemini-2.5-flash' ? 'selected' : '' ?>>Gemini 2.5 Flash (推奨・最高速&高精度)</option>
                                        <option value="gemini-2.5-pro" <?= SettingsManager::get('gemini_model') === 'gemini-2.5-pro' ? 'selected' : '' ?>>Gemini 2.5 Pro (超高知能・長文推論)</option>
                                        <option value="gemini-1.5-flash" <?= SettingsManager::get('gemini_model') === 'gemini-1.5-flash' ? 'selected' : '' ?>>Gemini 1.5 Flash (安定版)</option>
                                    </select>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-black text-xs shadow-md transition-all">
                                        Gemini API設定を保存する
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php endif; ?>

            </main>
        </div>
    <?php endif; ?>

</body>
</html>
