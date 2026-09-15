<?php
/**
 * 「しらんけど」自動マイグレーションスクリプト (Web & CLI 対応)
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

$message = '';
$status = 'idle'; // idle | success | error
$dbDetails = [
    'host' => DB_HOST,
    'port' => DB_PORT,
    'name' => DB_NAME,
    'user' => DB_USER,
];

function executeMigrations() {
    $db = Database::getConnection();

    // 1. database.sql の読み込みと実行
    $sqlPath = __DIR__ . '/database.sql';
    if (!file_exists($sqlPath)) {
        throw new Exception("database.sql が見つかりません: {$sqlPath}");
    }
    $sql = file_get_contents($sqlPath);
    $db->exec($sql);

    // 2. デフォルトサイト確認 & 作成
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM sites");
    $res = $stmt->fetch();
    $insertedCount = 0;

    if ($res['cnt'] == 0) {
        // 総合メインサイト
        $stmt = $db->prepare("INSERT INTO sites (id, subdomain, name, description, genre) VALUES 
            (1, '', 'しらんけど', 'いま日本で話題のトレンドを客観分析し、一次情報とともにお届けするサイト。しらんけど。', 'general'),
            (2, 'game', 'しらんけど ゲーム速報', 'Steam・新作ゲーム・大型アプデのトレンドまとめ。しらんけど。', 'game'),
            (3, 'entame', 'しらんけど エンタメ', 'お笑い・バラエティ・芸能カルチャーの話題。しらんけど。', 'entertainment')");
        $stmt->execute();

        // カテゴリ投入
        $catStmt = $db->prepare("INSERT INTO categories (site_id, slug, name, sort_order) VALUES
            (1, 'all', '総合', 1),
            (1, 'entertainment', 'エンタメ', 2),
            (1, 'tech', 'テクノロジー', 3),
            (1, 'game', 'ゲーム', 4),
            (1, 'social', 'SNS・ネット話題', 5),
            (2, 'steam', 'Steam/PC', 1),
            (2, 'console', 'コンシューマー', 2),
            (2, 'mobile', 'アプリ', 3)");
        $catStmt->execute();

        // 拒否キーワード初期データ投入
        $kwStmt = $db->prepare("INSERT INTO banned_keywords (site_id, keyword, match_type, reason) VALUES
            (1, '死ね', 'partial', '誹謗中傷・脅迫'),
            (1, '殺す', 'partial', '脅迫'),
            (1, 'ガイジ', 'partial', '差別用語'),
            (1, 'バカ', 'exact', '過度な中傷'),
            (1, 'ゴミカス', 'partial', '侮辱'),
            (1, '晒す', 'partial', '晒し行為'),
            (1, '電話番号', 'partial', '個人情報誘導'),
            (1, '口座', 'partial', '詐欺誘導')");
        $kwStmt->execute();

        // 画像グループ初期投入
        $grpStmt = $db->prepare("INSERT INTO image_groups (id, site_id, name, genre) VALUES
            (1, 1, '千鳥', 'entertainment'),
            (2, 1, 'ダウンタウン', 'entertainment'),
            (3, 1, 'モンスターハンター', 'game')");
        $grpStmt->execute();

        // 初期画像ライブラリ投入
        $imgStmt = $db->prepare("INSERT INTO images (id, site_id, group_id, category_id, filename, url, alt_text) VALUES
            (1, 1, 1, 2, 'chidori_001.webp', 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=800&q=80', 'お笑いステージイメージ'),
            (2, 1, 2, 2, 'downtown_001.webp', 'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=800&q=80', 'スタジオマイクイメージ'),
            (3, 1, 3, 4, 'game_hunter_001.webp', 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=800&q=80', 'ファンタジーアクションゲームイメージ'),
            (4, 1, NULL, 1, 'trend_news_default.webp', 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=800&q=80', 'ニュース速報イメージ')");
        $imgStmt->execute();

        // 画像キーワード投入
        $ikStmt = $db->prepare("INSERT INTO image_keywords (image_id, keyword) VALUES
            (1, '千鳥'), (1, '大悟'), (1, 'ノブ'), (1, 'お笑い'),
            (2, 'ダウンタウン'), (2, '松本人志'), (2, '浜田雅功'), (2, 'バラエティ'),
            (3, 'モンスターハンター'), (3, 'モンハン'), (3, 'Monster Hunter'), (3, 'ゲーム')");
        $ikStmt->execute();

        // サンプル記事投入
        $artStmt = $db->prepare("INSERT INTO articles (site_id, category_id, title, slug, why_trending, body, conclusion_sentence, shirankedo_index, index_label, is_rapid_rise, growth_rate, status, published_at) VALUES 
            (1, 2, '千鳥の新番組が異例のTVer週間ランキング1位を獲得した件', 'chidori-new-show-tver-no1', '新企画の予測不能なロケ展開がSNSで話題を呼び、放送後わずか3日で再生数200万回を突破しました。', 'お笑いコンビ・千鳥が出演する深夜バラエティ番組の新企画が、民放公式テレビ配信サービス「TVer」の総合ランキングにおいて異例の週間1位を獲得しました。\n\n番組関係者によると、事前告知なしで決行された岡山ロケの模様がSNS上で大きな反響を呼び、放送終了直後から切り抜き動画や言及ポストが急増。関連キーワードがトレンド入りを果たすなど、深夜枠としては極めて高い視聴熱を記録しています。\n\n同局プロデューサーは「視聴者のリアルタイムな共感と反響が今回の数字につながった」とコメントしています。', '次回の放送でもこの勢いを維持できるのか、今後の企画展開に注目が集まります。しらんけど。', 88, 'めっちゃ話題', 1, 142.5, 'published', NOW()),
            (1, 4, '大人気ハンティングアクション最新作、全世界同時体験版が配信開始', 'game-hunting-action-demo', 'シリーズ待望の最新作が突如体験版の配信を開始し、同時接続プレイヤー数が歴代記録を更新しました。', '人気ゲームシリーズの最新ナンバリングタイトルにおいて、全世界同時での無料オープンベータテストが本日未明より開始されました。\n\n公式サイトおよび各プラットフォームの発表によると、配信開始直後からアクセスが集中し、一部サーバーで入場制限が実施されるほどの盛り上がりを見せています。ユーザーからは刷新されたグラフィックや新アクションに対する高評価が寄せられています。', '本編発売日にはさらに大きな熱狂が巻き起こりそうです。しらんけど。', 94, 'めっちゃ話題', 1, 210.0, 'published', NOW())");
        $artStmt->execute();

        $insertedCount = 2;
    }

    return [
        'status' => 'success',
        'message' => "マイグレーション完了！全12テーブルの作成と初期マスターデータ（サンプル記事 {$insertedCount}件）の投入が成功しました。",
    ];
}

// 実行判定 (CLI または GET/POST 実行)
$shouldRun = (php_sapi_name() === 'cli') || isset($_POST['run']) || isset($_GET['run']);

if ($shouldRun) {
    try {
        $result = executeMigrations();
        $status = 'success';
        $message = $result['message'];
    } catch (Throwable $e) {
        $status = 'error';
        $message = "エラーが発生しました:\n" . $e->getMessage();
    }
}

// CLI の場合はテキスト出力して終了
if (php_sapi_name() === 'cli') {
    echo ($status === 'success' ? "SUCCESS: " : "ERROR: ") . $message . "\n";
    exit($status === 'success' ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース自動セットアップ - しらんけど</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 text-stone-900 min-h-screen p-4 sm:p-8 flex items-center justify-center font-sans">
    <div class="bg-white max-w-xl w-full rounded-3xl border border-stone-200 p-6 sm:p-8 shadow-xl space-y-6">
        <div class="flex items-center gap-3 border-b border-stone-100 pb-4">
            <div class="w-10 h-10 rounded-xl bg-amber-500 text-stone-950 font-black flex items-center justify-center text-lg shadow-sm">
                設
            </div>
            <div>
                <h1 class="text-xl font-black text-stone-900 tracking-tight">「しらんけど」DB自動セットアップ</h1>
                <p class="text-xs text-stone-500">Database Migration & Initial Seeder</p>
            </div>
        </div>

        <!-- 接続情報プレビュー -->
        <div class="bg-stone-50 border border-stone-200 rounded-2xl p-4 text-xs space-y-1.5 font-mono">
            <div class="font-bold text-stone-700 font-sans mb-1 text-sm">【現在の接続設定 (php/config.php)】</div>
            <div>ホスト: <span class="text-stone-900 font-bold"><?= htmlspecialchars($dbDetails['host']) ?>:<?= htmlspecialchars($dbDetails['port']) ?></span></div>
            <div>データベース名: <span class="text-stone-900 font-bold"><?= htmlspecialchars($dbDetails['name']) ?></span></div>
            <div>ユーザー名: <span class="text-stone-900 font-bold"><?= htmlspecialchars($dbDetails['user']) ?></span></div>
        </div>

        <!-- 結果メッセージ -->
        <?php if ($status === 'success'): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-300 text-emerald-900 rounded-2xl text-xs sm:text-sm font-bold space-y-2">
                <div class="flex items-center gap-2 text-emerald-700 font-black text-base">
                    <span>✅ セットアップ完了</span>
                </div>
                <p><?= nl2br(htmlspecialchars($message)) ?></p>
                <div class="pt-2">
                    <a href="../" class="inline-flex items-center justify-center px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white rounded-xl text-xs font-bold transition-colors">
                        トップページを開く →
                    </a>
                </div>
            </div>
        <?php elseif ($status === 'error'): ?>
            <div class="p-4 bg-rose-50 border border-rose-300 text-rose-900 rounded-2xl text-xs sm:text-sm space-y-2">
                <div class="flex items-center gap-2 text-rose-700 font-black text-base">
                    <span>⚠️ 接続または実行エラー</span>
                </div>
                <pre class="bg-white/80 p-3 rounded-xl border border-rose-200 text-rose-950 text-xs font-mono whitespace-pre-wrap overflow-x-auto"><?= htmlspecialchars($message) ?></pre>
                <p class="text-xs text-rose-800">
                    ※ サーバーのMySQL情報（ホスト名・DB名・ユーザー名・パスワード）が正しいか、<code class="font-bold">php/config.php</code> をご確認ください。
                </p>
            </div>
        <?php else: ?>
            <p class="text-xs sm:text-sm text-stone-600 leading-relaxed">
                ボタンを押すと、MySQL上に全12テーブルの自動作成と、初期設定データ（サイト設定、カテゴリ、拒否キーワード、画像ライブラリ、サンプル記事）を自動投入します。
            </p>
        <?php endif; ?>

        <!-- 実行フォーム -->
        <form method="POST">
            <input type="hidden" name="run" value="1">
            <button type="submit" class="w-full py-3 px-5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow-md transition-all flex items-center justify-center gap-2">
                <span>データベース自動セットアップを実行する</span>
            </button>
        </form>

        <div class="text-[11px] text-stone-400 text-center font-serif">
            「テーブル作成もデータ投入もワンクリックで完了します。しらんけど。」
        </div>
    </div>
</body>
</html>
