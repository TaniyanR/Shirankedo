<?php
/**
 * 「しらんけど」単独稼働 PHPフロントエンド & API
 * レンタルサーバー（Apache + PHP + MySQL）でそのまま完璧に動作するフロントエンドです。
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';

// DB接続チェック
$dbConnected = false;
$dbError = '';
try {
    $db = Database::getConnection();
    $dbConnected = true;
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
    } catch (Throwable $e) {
        // テーブルが存在しない可能性
    }
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
        $authorName = trim($_POST['author_name'] ?? '名無しさん');
        $body = trim($_POST['body'] ?? '');
        $siteId = $site ? (int)$site['id'] : 1;

        if (empty($body)) {
            echo json_encode(['success' => false, 'error' => 'コメント本文を入力してください']);
            exit;
        }

        // スパム & 拒否ワードチェック
        $filterResult = SpamFilter::checkComment($siteId, $body, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (!$filterResult['allowed']) {
            echo json_encode(['success' => false, 'error' => '投稿エラー: ' . $filterResult['reason']]);
            exit;
        }

        try {
            $stmt = $db->prepare("INSERT INTO comments (article_id, site_id, author_name, body, status, ip_address, created_at) VALUES (?, ?, ?, ?, 'approved', ?, NOW())");
            $stmt->execute([$articleId, $siteId, $authorName ?: '名無しさん', $body, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

            echo json_encode(['success' => true, 'comment' => [
                'author_name' => htmlspecialchars($authorName ?: '名無しさん'),
                'body' => nl2br(htmlspecialchars($body)),
                'created_at' => date('Y/m/d H:i')
            ]]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// --- 記事一覧 & カテゴリ取得 ---
$articles = [];
$categories = [];
if ($dbConnected && $site) {
    try {
        $catStmt = $db->prepare("SELECT * FROM categories WHERE site_id = ? ORDER BY sort_order ASC");
        $catStmt->execute([(int)$site['id']]);
        $categories = $catStmt->fetchAll();

        $selectedCategory = $_GET['cat'] ?? 'all';
        $whereSql = "WHERE a.site_id = ? AND a.status = 'published'";
        $params = [(int)$site['id']];

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
        $artStmt = $db->prepare($artSql);
        $artStmt->execute($params);
        $articles = $artStmt->fetchAll();
    } catch (Throwable $e) {
        // テーブル未マイグレーション
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site['name'] ?? 'しらんけど') ?> - 完全自動トレンドサイト</title>
    <meta name="description" content="<?= htmlspecialchars($site['description'] ?? 'ネット上の話題を客観分析し、一次情報とともにお届けするトレンドメディア。しらんけど。') ?>">
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
        <div class="max-w-6xl mx-auto flex items-center justify-between gap-4">
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

            <div class="flex items-center gap-2 sm:gap-3 text-xs">
                <a href="page.php?slug=about" class="px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold border border-amber-200 transition-all">
                    サイトについて
                </a>
                <a href="page.php?slug=que" class="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-800 font-bold transition-all">
                    お問い合わせ
                </a>
                <a href="php/migrate.php" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold transition-all">
                    ⚙️ DB設定
                </a>
            </div>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-6">

        <!-- DB未設定・マイグレーション未実行の場合の親切アラート -->
        <?php if (!$dbConnected || empty($articles)): ?>
            <div class="bg-amber-50 border-2 border-amber-300 rounded-3xl p-6 sm:p-8 space-y-4 shadow-md">
                <div class="flex items-center gap-3 text-amber-900 font-black text-lg">
                    <span class="text-2xl">🚀</span>
                    <span>サーバーへの設置が完了しました！あと1ステップです</span>
                </div>
                <p class="text-xs sm:text-sm text-amber-800 leading-relaxed">
                    データベーステーブルが未作成、または初期データがまだ投入されていません。<br>
                    以下のボタンから<strong>「DB自動セットアップ」</strong>を1度実行してください。全テーブルと初期サンプル記事が自動生成されます。
                </p>
                <div class="pt-2 flex flex-wrap items-center gap-4">
                    <a href="php/migrate.php" class="inline-flex items-center justify-center px-6 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow transition-transform active:scale-95">
                        👉 データベース自動セットアップ画面を開く
                    </a>
                    <?php if ($dbError): ?>
                        <div class="text-xs text-rose-700 font-mono bg-rose-50 px-3 py-2 rounded-xl border border-rose-200">
                            DB接続情報エラー: <?= htmlspecialchars($dbError) ?><br>
                            ※ <code class="font-bold">php/config.php</code> のホスト・ユーザー・パスワードをご確認ください。
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

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

        <!-- 記事一覧グリッド -->
        <?php if (!empty($articles)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($articles as $art): 
                    $score = (int)$art['shirankedo_index'];
                    $colorClass = $score >= 80 ? 'bg-rose-50 text-rose-800 border-rose-200' : ($score >= 50 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-stone-100 text-stone-800 border-stone-200');
                    $barColor = $score >= 80 ? 'bg-rose-500' : ($score >= 50 ? 'bg-amber-500' : 'bg-stone-400');
                    $imgSrc = $art['custom_image_url'] ?: 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=800&q=80';
                ?>
                    <article class="bg-white rounded-3xl border border-stone-200/80 overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col group">
                        <!-- アイキャッチ画像 -->
                        <div class="relative h-44 sm:h-48 overflow-hidden bg-stone-100">
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($art['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <?php if ($art['is_rapid_rise']): ?>
                                <span class="absolute top-3 left-3 bg-rose-600 text-white text-[11px] font-black px-2.5 py-1 rounded-full shadow-md flex items-center gap-1 animate-pulse">
                                    🔥 急上昇
                                </span>
                            <?php endif; ?>
                            <span class="absolute top-3 right-3 bg-stone-900/80 backdrop-blur-md text-white text-[11px] font-bold px-2.5 py-1 rounded-full">
                                <?= htmlspecialchars($art['category_name'] ?? 'ニュース') ?>
                            </span>
                        </div>

                        <!-- 記事内容 -->
                        <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                            <div class="space-y-2.5">
                                <!-- 指数ゲージ -->
                                <div class="flex items-center justify-between text-xs font-bold border-b border-stone-100 pb-2.5">
                                    <span class="text-stone-500">しらんけど指数</span>
                                    <div class="flex items-center gap-2">
                                        <div class="w-20 h-2 rounded-full bg-stone-100 overflow-hidden">
                                            <div class="h-full <?= $barColor ?>" style="width: <?= $score ?>%"></div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-md text-[11px] border <?= $colorClass ?>">
                                            <?= $score ?>点 (<?= htmlspecialchars($art['index_label']) ?>)
                                        </span>
                                    </div>
                                </div>

                                <h2 class="font-black text-stone-950 text-base sm:text-lg leading-snug line-clamp-2">
                                    <?= htmlspecialchars($art['title']) ?>
                                </h2>

                                <div class="bg-amber-50/60 border border-amber-100/80 rounded-2xl p-3 text-xs text-amber-950 leading-relaxed">
                                    <span class="font-bold text-amber-900 block mb-0.5">💡 なぜ話題？</span>
                                    <?= htmlspecialchars($art['why_trending']) ?>
                                </div>
                            </div>

                            <!-- 締め文句とアクション -->
                            <div class="space-y-3 pt-2">
                                <div class="text-xs text-stone-600 font-mincho bg-stone-50 p-2.5 rounded-xl border border-stone-100 italic">
                                    <?= htmlspecialchars($art['conclusion_sentence']) ?>
                                </div>

                                <div class="flex items-center justify-between text-xs text-stone-500 pt-1">
                                    <span><?= date('m/d H:i', strtotime($art['published_at'])) ?></span>
                                    <button onclick="openModal(<?= htmlspecialchars(json_encode($art), ENT_QUOTES, 'UTF-8') ?>)" class="px-3.5 py-1.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold transition-colors">
                                        詳細を読む →
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- フッター -->
    <footer class="bg-stone-900 text-stone-400 text-xs py-8 px-4 mt-12 border-t border-stone-800">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
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
                <a href="page.php?slug=privacy-policy" class="hover:text-amber-400 transition-colors">プライバシーポリシー</a>
                <a href="page.php?slug=que" class="hover:text-amber-400 transition-colors">お問い合わせ</a>
                <a href="php/comment-rules.php" class="hover:text-amber-400 transition-colors">利用規約</a>
            </div>
        </div>
    </footer>

    <!-- 記事詳細 & 投票・コメント モーダル -->
    <div id="articleModal" class="fixed inset-0 z-50 bg-stone-950/70 backdrop-blur-sm hidden items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white max-w-2xl w-full rounded-3xl border border-stone-200 overflow-hidden shadow-2xl my-8">
            <div class="p-6 sm:p-8 space-y-6">
                <!-- モーダルヘッダー -->
                <div class="flex items-start justify-between gap-4 border-b border-stone-100 pb-4">
                    <span id="modalCategory" class="px-3 py-1 rounded-full bg-stone-100 text-stone-800 text-xs font-bold">
                        カテゴリ
                    </span>
                    <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-stone-100 hover:bg-stone-200 text-stone-700 flex items-center justify-center font-black">
                        ✕
                    </button>
                </div>

                <!-- 記事タイトル & 指数 -->
                <div>
                    <h2 id="modalTitle" class="text-xl sm:text-2xl font-black text-stone-950 leading-snug"></h2>
                    <div class="mt-3 flex items-center gap-3 text-xs text-stone-500">
                        <span id="modalDate"></span>
                        <span id="modalScoreBadge" class="px-2.5 py-0.5 rounded-lg font-bold border"></span>
                    </div>
                </div>

                <!-- なぜ話題？ -->
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs sm:text-sm text-amber-950 space-y-1">
                    <div class="font-black text-amber-900 flex items-center gap-1.5">
                        <span>💡</span> なぜ話題？
                    </div>
                    <p id="modalWhy" class="leading-relaxed"></p>
                </div>

                <!-- 記事本文 -->
                <div class="space-y-4 text-xs sm:text-sm text-stone-800 leading-relaxed whitespace-pre-wrap font-sans" id="modalBody"></div>

                <!-- 締めの言葉 -->
                <div class="bg-stone-50 border-l-4 border-amber-500 p-4 rounded-r-2xl font-mincho text-xs sm:text-sm text-stone-900" id="modalConclusion"></div>

                <!-- アンケート投票エリア -->
                <div class="border-t border-stone-100 pt-6 space-y-3">
                    <div class="text-xs font-bold text-stone-700 flex items-center justify-between">
                        <span>📊 あなたの所感は？（リアルタイム投票）</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <button id="btnVoteBelieved" onclick="sendVote('believed')" class="py-3 px-4 rounded-2xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 font-black text-xs sm:text-sm flex flex-col items-center justify-center gap-1 transition-all">
                            <span>👍 ほんまや！</span>
                            <span class="text-[11px] font-normal text-emerald-700" id="countBelieved">0票</span>
                        </button>
                        <button id="btnVoteSkeptical" onclick="sendVote('skeptical')" class="py-3 px-4 rounded-2xl bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-900 font-black text-xs sm:text-sm flex flex-col items-center justify-center gap-1 transition-all">
                            <span>🤔 しらんけど…</span>
                            <span class="text-[11px] font-normal text-amber-700" id="countSkeptical">0票</span>
                        </button>
                    </div>
                </div>

                <!-- コメント投稿エリア -->
                <div class="border-t border-stone-100 pt-6 space-y-4">
                    <h3 class="text-xs font-bold text-stone-800">💬 コメントを投稿する</h3>
                    <form onsubmit="submitComment(event)" class="space-y-3">
                        <input type="text" id="commentAuthor" placeholder="お名前 (省略時は名無しさん)" class="w-full text-xs p-3 rounded-xl border border-stone-200 focus:outline-none focus:border-stone-900">
                        <textarea id="commentBody" rows="3" required placeholder="コメント本文（誹謗中傷・個人情報は自動遮断されます）" class="w-full text-xs p-3 rounded-xl border border-stone-200 focus:outline-none focus:border-stone-900"></textarea>
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-stone-950 hover:bg-stone-800 text-white font-bold text-xs transition-colors shadow">
                            コメントを送信
                        </button>
                    </form>
                    <div id="commentAlert" class="text-xs hidden p-3 rounded-xl"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- モーダル制御スクリプト -->
    <script>
        let currentArticle = null;

        function openModal(art) {
            currentArticle = art;
            document.getElementById('modalTitle').textContent = art.title;
            document.getElementById('modalCategory').textContent = art.category_name || 'ニュース';
            document.getElementById('modalDate').textContent = art.published_at;
            document.getElementById('modalWhy').textContent = art.why_trending;
            document.getElementById('modalBody').textContent = art.body;
            document.getElementById('modalConclusion').textContent = art.conclusion_sentence;

            const badge = document.getElementById('modalScoreBadge');
            badge.textContent = `しらんけど指数: ${art.shirankedo_index}点 (${art.index_label})`;
            badge.className = 'px-2.5 py-0.5 rounded-lg font-bold border ' + 
                (art.shirankedo_index >= 80 ? 'bg-rose-50 text-rose-800 border-rose-200' : 
                 art.shirankedo_index >= 50 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-stone-100 text-stone-800 border-stone-200');

            document.getElementById('countBelieved').textContent = (art.vote_believed || 0) + '票';
            document.getElementById('countSkeptical').textContent = (art.vote_skeptical || 0) + '票';

            document.getElementById('articleModal').classList.remove('hidden');
            document.getElementById('articleModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('articleModal').classList.add('hidden');
            document.getElementById('articleModal').classList.remove('flex');
            currentArticle = null;
        }

        async function sendVote(voteType) {
            if (!currentArticle) return;
            const formData = new FormData();
            formData.append('action', 'vote');
            formData.append('article_id', currentArticle.id);
            formData.append('vote_type', voteType);

            try {
                const res = await fetch('', { method: 'POST', body: formData });
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
            if (!currentArticle) return;

            const author = document.getElementById('commentAuthor').value;
            const body = document.getElementById('commentBody').value;
            const alertBox = document.getElementById('commentAlert');

            const formData = new FormData();
            formData.append('action', 'comment');
            formData.append('article_id', currentArticle.id);
            formData.append('author_name', author);
            formData.append('body', body);

            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                alertBox.classList.remove('hidden');

                if (data.success) {
                    alertBox.className = 'text-xs p-3 rounded-xl bg-emerald-50 text-emerald-900 border border-emerald-200';
                    alertBox.textContent = 'コメントを投稿しました。';
                    document.getElementById('commentBody').value = '';
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
