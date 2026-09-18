<?php
/**
 * 「しらんけど」管理画面 & 運用コンソール (admin.php)
 * ログイン・自動収集実行・AI生成・記事承認・禁止ワード管理
 */
session_start();
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';

// 簡易管理者認証 (初期パスワード: admin1234 または 環境変数 ADMIN_PASSWORD)
$adminPass = getenv('ADMIN_PASSWORD') ?: 'admin1234';
$loginError = '';

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $inputPass = $_POST['password'] ?? '';
    if ($inputPass === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'パスワードが正しくありません。';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header('Location: admin.php');
    exit;
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);

// DB接続
$db = null;
try {
    $db = Database::getConnection();
} catch (Throwable $e) {}

// --- 管理アクション (ログイン中のみ) ---
$actionMessage = '';
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';

    // 1. サンプル記事の新規追加
    if ($op === 'create_sample') {
        if ($db) {
            try {
                $title = trim($_POST['title'] ?? '');
                $why = trim($_POST['why_trending'] ?? '');
                $body = trim($_POST['body'] ?? '');
                $conclusion = trim($_POST['conclusion_sentence'] ?? '知らんけど。');
                $index = (int)($_POST['shirankedo_index'] ?? 75);
                $label = $index >= 80 ? 'めっちゃ話題' : ($index >= 50 ? '話題' : 'ちょい話題');
                $catId = (int)($_POST['category_id'] ?? 1);
                $slug = 'trend-' . time();

                $stmt = $db->prepare("INSERT INTO articles (site_id, category_id, title, slug, why_trending, body, conclusion_sentence, shirankedo_index, index_label, status, published_at)
                                      VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())");
                $stmt->execute([$catId, $title, $slug, $why, $body, $conclusion, $index, $label]);
                $actionMessage = '新着記事を正常に公開しました！';
            } catch (Throwable $e) {
                $actionMessage = 'エラー: ' . $e->getMessage();
            }
        }
    }

    // 2. トレンド自動収集ワーカーのトリガー
    if ($op === 'run_worker') {
        if (file_exists(__DIR__ . '/cron/worker.php')) {
            ob_start();
            include __DIR__ . '/cron/worker.php';
            $output = ob_get_clean();
            $actionMessage = '自動収集ワーカーを実行しました！<br><pre class="text-xs mt-2 p-2 bg-stone-900 text-stone-200 rounded">' . htmlspecialchars(mb_substr($output, 0, 400)) . '...</pre>';
        } else {
            $actionMessage = 'cron/worker.php が見つかりませんでした。';
        }
    }

    // 3. 記事ステータス変更（削除または保留）
    if ($op === 'delete_article') {
        $delId = (int)($_POST['article_id'] ?? 0);
        if ($db && $delId > 0) {
            $db->prepare("UPDATE articles SET status = 'private' WHERE id = ?")->execute([$delId]);
            $actionMessage = "記事ID #{$delId} を非公開にしました。";
        }
    }
}

// 統計情報・記事一覧の取得
$totalArticles = 0;
$articleList = [];
$totalComments = 0;
$categories = [];

if ($db) {
    try {
        $totalArticles = (int)$db->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
        $totalComments = (int)$db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
        $articleList = $db->query("SELECT id, title, slug, shirankedo_index, index_label, status, published_at FROM articles ORDER BY id DESC LIMIT 20")->fetchAll();
        $categories = $db->query("SELECT id, name FROM categories WHERE site_id = 1")->fetchAll();
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - しらんけど</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-stone-900 text-stone-100 min-h-screen flex flex-col antialiased">

    <!-- ナビゲーションバー -->
    <header class="bg-stone-950 border-b border-stone-800 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="index.php" class="w-9 h-9 rounded-xl bg-amber-500 text-stone-950 font-black flex items-center justify-center text-lg">
                知
            </a>
            <div>
                <span class="font-black text-white text-base">しらんけど 運用管理コンソール</span>
                <span class="text-[11px] text-stone-400 block">自動収集・AI記事生成・コンテンツ監視</span>
            </div>
        </div>

        <div class="flex items-center gap-3 text-xs">
            <a href="index.php" class="px-3 py-1.5 rounded-xl bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold transition">
                サイトを表示 ↗
            </a>
            <?php if ($isLoggedIn): ?>
                <a href="admin.php?logout=1" class="px-3 py-1.5 rounded-xl bg-rose-950/80 border border-rose-800 hover:bg-rose-900 text-rose-200 font-bold transition">
                    ログアウト
                </a>
            <?php endif; ?>
        </div>
    </header>

    <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-8">

        <?php if (!$isLoggedIn): ?>
            <!-- ログイン画面 -->
            <div class="max-w-md mx-auto my-12 bg-stone-950 border border-stone-800 rounded-3xl p-8 shadow-2xl space-y-6">
                <div class="text-center space-y-2">
                    <div class="inline-flex w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 items-center justify-center text-xl font-bold">
                        🔒
                    </div>
                    <h1 class="text-xl font-black text-white">管理者ログイン</h1>
                    <p class="text-xs text-stone-400">管理者パスワードを入力してログインしてください。</p>
                </div>

                <?php if ($loginError): ?>
                    <div class="bg-rose-950/50 border border-rose-800 text-rose-300 text-xs p-3 rounded-xl">
                        <?= htmlspecialchars($loginError) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label class="block text-xs font-bold text-stone-400 mb-1.5">管理者パスワード</label>
                        <input type="password" name="password" required placeholder="管理者パスワードを入力" class="w-full bg-stone-900 border border-stone-800 text-white rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500">
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow transition active:scale-98">
                        ログインしてコンソールを開く
                    </button>
                </form>
            </div>

        <?php else: ?>
            <!-- 管理ダッシュボード -->
            <div class="space-y-8">

                <?php if ($actionMessage): ?>
                    <div class="bg-amber-500/10 border border-amber-500/30 text-amber-300 p-4 rounded-2xl text-xs sm:text-sm">
                        <?= $actionMessage ?>
                    </div>
                <?php endif; ?>

                <!-- メトリクスバナー -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-stone-950 border border-stone-800 p-5 rounded-2xl space-y-1">
                        <span class="text-xs text-stone-400 font-bold">公開中記事数</span>
                        <div class="text-2xl font-black text-white"><?= $totalArticles ?> <span class="text-xs font-normal text-stone-500">本</span></div>
                    </div>
                    <div class="bg-stone-950 border border-stone-800 p-5 rounded-2xl space-y-1">
                        <span class="text-xs text-stone-400 font-bold">総コメント数</span>
                        <div class="text-2xl font-black text-white"><?= $totalComments ?> <span class="text-xs font-normal text-stone-500">件</span></div>
                    </div>
                    <div class="bg-stone-950 border border-stone-800 p-5 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-xs text-stone-400 font-bold">トレンド自動化ワーカー</span>
                            <div class="text-xs text-emerald-400 font-bold mt-1">● 稼働準備完了</div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="op" value="run_worker">
                            <button type="submit" class="px-3.5 py-2 bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-black rounded-xl shadow active:scale-95 transition">
                                即時実行
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 記事一覧と管理 -->
                <div class="bg-stone-950 border border-stone-800 rounded-3xl p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-black text-white">📑 記事管理一覧 (最新20件)</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-stone-500 border-b border-stone-800">
                                <tr>
                                    <th class="pb-3">ID</th>
                                    <th class="pb-3">タイトル</th>
                                    <th class="pb-3">指数</th>
                                    <th class="pb-3">公開日時</th>
                                    <th class="pb-3 text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-900 text-stone-300">
                                <?php foreach ($articleList as $row): ?>
                                    <tr>
                                        <td class="py-3 font-mono text-stone-500">#<?= $row['id'] ?></td>
                                        <td class="py-3 font-bold text-white max-w-md truncate">
                                            <a href="article.php?id=<?= $row['id'] ?>" target="_blank" class="hover:text-amber-400 underline">
                                                <?= htmlspecialchars($row['title']) ?>
                                            </a>
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 rounded bg-stone-900 text-amber-400 border border-stone-800">
                                                <?= $row['shirankedo_index'] ?>点
                                            </span>
                                        </td>
                                        <td class="py-3 text-stone-500"><?= date('m/d H:i', strtotime($row['published_at'])) ?></td>
                                        <td class="py-3 text-right space-x-2">
                                            <a href="article.php?id=<?= $row['id'] ?>" target="_blank" class="text-amber-400 hover:underline">確認</a>
                                            <form method="POST" class="inline" onsubmit="return confirm('非公開にしますか？')">
                                                <input type="hidden" name="op" value="delete_article">
                                                <input type="hidden" name="article_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="text-rose-400 hover:underline">非公開</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 手動で新規記事を投稿 -->
                <div class="bg-stone-950 border border-stone-800 rounded-3xl p-6 space-y-4">
                    <h2 class="text-base font-black text-white">✍️ 記事の手動新規投稿</h2>
                    <form method="POST" class="space-y-4 text-xs">
                        <input type="hidden" name="op" value="create_sample">
                        <div>
                            <label class="block font-bold text-stone-400 mb-1">記事タイトル</label>
                            <input type="text" name="title" required placeholder="例: ○○がSNSで大バズりした件" class="w-full bg-stone-900 border border-stone-800 rounded-xl p-3 text-white focus:outline-none focus:border-amber-500">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-stone-400 mb-1">カテゴリ</label>
                                <select name="category_id" class="w-full bg-stone-900 border border-stone-800 rounded-xl p-3 text-white focus:outline-none focus:border-amber-500">
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-stone-400 mb-1">しらんけど指数 (0〜100)</label>
                                <input type="number" name="shirankedo_index" value="85" min="0" max="100" class="w-full bg-stone-900 border border-stone-800 rounded-xl p-3 text-white focus:outline-none focus:border-amber-500">
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-stone-400 mb-1">💡 なぜ話題？（要約）</label>
                            <textarea name="why_trending" rows="2" required placeholder="なぜ急上昇しているのかを端的に記載" class="w-full bg-stone-900 border border-stone-800 rounded-xl p-3 text-white focus:outline-none focus:border-amber-500"></textarea>
                        </div>
                        <div>
                            <label class="block font-bold text-stone-400 mb-1">記事本文</label>
                            <textarea name="body" rows="4" required placeholder="客観的事実と背景を記載" class="w-full bg-stone-900 border border-stone-800 rounded-xl p-3 text-white focus:outline-none focus:border-amber-500"></textarea>
                        </div>
                        <div>
                            <label class="block font-bold text-stone-400 mb-1">締めの一言（オチ・しらんけど構文）</label>
                            <input type="text" name="conclusion_sentence" value="今後の動向にも注目が集まります。しらんけど。" class="w-full bg-stone-900 border border-stone-800 rounded-xl p-3 text-white focus:outline-none focus:border-amber-500">
                        </div>
                        <button type="submit" class="px-6 py-3 bg-amber-500 hover:bg-amber-400 text-stone-950 font-black rounded-xl text-xs shadow transition active:scale-95">
                            記事を即時公開する
                        </button>
                    </form>
                </div>

            </div>
        <?php endif; ?>

    </main>

    <footer class="text-center py-6 text-xs text-stone-600 border-t border-stone-900">
        しらんけど 運用管理システム &copy; <?= date('Y') ?>
    </footer>

</body>
</html>
