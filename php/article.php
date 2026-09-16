<?php
/**
 * 記事詳細ページ (article.php)
 * URL: /article.php?id=XX または /article.php?slug=YY または /article/YY (.htaccess経由)
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';

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
                                  WHERE a.id = ? AND a.status = 'published' LIMIT 1");
            $stmt->execute([$articleId]);
        } else {
            $stmt = $db->prepare("SELECT a.*, c.name as category_name, c.slug as category_slug
                                  FROM articles a
                                  LEFT JOIN categories c ON a.category_id = c.id
                                  WHERE a.slug = ? AND a.status = 'published' LIMIT 1");
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
            'image_url' => 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=800&q=80',
            'why_trending' => '新企画の予測不能なロケ展開がSNSで話題を呼び、放送後わずか3日で再生数200万回を突破しました。',
            'body' => "お笑いコンビ・千鳥による冠新番組が、放送開始直後から圧倒的な反響を集めています。\n\nTVerのバラエティ部門において週間ランキング1位を獲得したほか、X（旧Twitter）でも関連ワードが終日トレンド上位を独占しました。\n\n番組内での予測不可能な街頭ロケ展開と独特のツッコミ演出が視聴者の笑いを誘い、切り抜き動画や感想投稿が急速に拡散されたことが要因と見られています。\n\n次週以降のゲスト発表も期待されており、しばらくはこの勢いが続きそうです。",
            'conclusion_sentence' => '次回の放送でもこの勢いを維持できるのか、今後の企画展開に注目が集まります。しらんけど。',
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
            'image_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=800&q=80',
            'why_trending' => 'シリーズ待望の最新作が突如体験版の配信を開始し、同時接続プレイヤー数が歴代記録を更新しました。',
            'body' => "人気ゲームシリーズの最新ナンバリングタイトルにおいて、全世界同時での無料オープンベータテストが本日未明より開始されました。\n\n公式サイトおよび各プラットフォームの発表によると、配信開始直後からアクセスが集中し、一部サーバーで入場制限が実施されるほどの盛り上がりを見せています。\n\nユーザーからは刷新されたグラフィックや新アクションに対する高評価が寄せられており、SNS上でも討伐タイムアタック動画が多数投稿されています。",
            'conclusion_sentence' => '本編発売日にはさらに大きな熱狂が巻き起こりそうです。しらんけど。',
            'published_at' => date('Y-m-d H:i:s'),
        ];
    } else {
        http_response_code(404);
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

// 関連・最新記事（おすすめ）
$recentArticles = [];
if ($dbConnected) {
    try {
        $recStmt = $db->prepare("SELECT id, title, slug, shirankedo_index, index_label, image_url, published_at 
                                 FROM articles 
                                 WHERE id != ? AND status = 'published' 
                                 ORDER BY published_at DESC LIMIT 4");
        $recStmt->execute([$article['id'] ?? 0]);
        $recentArticles = $recStmt->fetchAll();
    } catch (Throwable $e) {}
}

$score = (int)($article['shirankedo_index'] ?? 50);
$colorClass = $score >= 80 ? 'bg-rose-50 text-rose-800 border-rose-200' :
              ($score >= 50 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-stone-100 text-stone-800 border-stone-200');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($article['title']) ?> - <?= htmlspecialchars($site['name'] ?? 'しらんけど') ?></title>
    <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($article['why_trending']), 0, 120)) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($article['title']) ?> - しらんけど">
    <meta property="og:description" content="<?= htmlspecialchars($article['why_trending']) ?>">
    <meta property="og:type" content="article">
    <?php if (!empty($article['image_url'])): ?>
    <meta property="og:image" content="<?= htmlspecialchars($article['image_url']) ?>">
    <?php endif; ?>
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

            <div class="flex items-center gap-2 sm:gap-3 text-xs">
                <a href="index.php" class="px-3.5 py-1.5 rounded-xl bg-stone-900 hover:bg-stone-800 text-white font-bold transition-all">
                    ← トレンド一覧
                </a>
            </div>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-10">
        
        <article class="bg-white rounded-3xl border border-stone-200 shadow-sm overflow-hidden p-6 sm:p-10 space-y-8">
            
            <!-- メタ情報 -->
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="px-3 py-1 rounded-full bg-stone-100 text-stone-800 font-bold">
                        <?= htmlspecialchars($article['category_name'] ?? '総合') ?>
                    </span>
                    <span class="text-stone-400">•</span>
                    <time class="text-stone-500"><?= date('Y年m月d日 H:i', strtotime($article['published_at'] ?? 'now')) ?></time>
                    <span class="text-stone-400">•</span>
                    <span class="px-2.5 py-0.5 rounded-lg font-bold border <?= $colorClass ?>">
                        しらんけど指数: <?= $score ?>点 (<?= htmlspecialchars($article['index_label'] ?? '話題') ?>)
                    </span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-black text-stone-950 leading-snug">
                    <?= htmlspecialchars($article['title']) ?>
                </h1>
            </div>

            <!-- アイキャッチ画像 -->
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

            <!-- 記事本文 -->
            <div class="prose max-w-none text-stone-800 leading-relaxed text-sm sm:text-base space-y-4 font-sans whitespace-pre-wrap">
                <?= nl2br(htmlspecialchars($article['body'])) ?>
            </div>

            <!-- 締めの言葉（しらんけど構文） -->
            <div class="bg-stone-50 border-l-4 border-amber-500 p-5 rounded-r-2xl font-mincho text-sm sm:text-base text-stone-900">
                <?= htmlspecialchars($article['conclusion_sentence']) ?>
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

        <!-- 他のトレンド話題 -->
        <?php if (!empty($recentArticles)): ?>
            <div class="mt-12 space-y-4">
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

    <!-- フッター -->
    <footer class="bg-stone-900 text-stone-400 text-xs py-8 px-4 mt-12 border-t border-stone-800">
        <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
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
