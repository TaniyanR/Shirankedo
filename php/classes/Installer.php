<?php
/**
 * しらんけど 自動インストーラー & データベースセットアップウィザード
 * （別サーバーへの初回設置時にDB情報を入力してワンクリックで自動構築）
 */
require_once __DIR__ . '/Database.php';

class Installer {

    /**
     * DB接続および基本テーブルが存在し、正常稼働状態かを判定
     */
    public static function isInstalled(): bool {
        $error = null;
        $pdo = Database::testConnection($error);
        if (!$pdo) {
            return false;
        }

        try {
            // 基本テーブルの存在確認
            $stmt = $pdo->query("SHOW TABLES LIKE 'sites'");
            if ($stmt->rowCount() === 0) {
                return false;
            }
            $stmt2 = $pdo->query("SHOW TABLES LIKE 'settings'");
            if ($stmt2->rowCount() === 0) {
                return false;
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * db_config.php にDB接続情報を保存
     */
    public static function saveDbConfig(string $host, string $port, string $name, string $user, string $pass): bool {
        $configFile = dirname(__DIR__) . '/db_config.php';
        $escapedHost = addcslashes($host, "'\\");
        $escapedPort = addcslashes($port, "'\\");
        $escapedName = addcslashes($name, "'\\");
        $escapedUser = addcslashes($user, "'\\");
        $escapedPass = addcslashes($pass, "'\\");

        $content = "<?php\n"
                 . "/**\n"
                 . " * データベース接続構成ファイル (自動生成)\n"
                 . " * 生成日時: " . date('Y-m-d H:i:s') . "\n"
                 . " */\n"
                 . "define('DB_HOST', '{$escapedHost}');\n"
                 . "define('DB_PORT', '{$escapedPort}');\n"
                 . "define('DB_NAME', '{$escapedName}');\n"
                 . "define('DB_USER', '{$escapedUser}');\n"
                 . "define('DB_PASS', '{$escapedPass}');\n"
                 . "define('DB_CHARSET', 'utf8mb4');\n";

        $res = @file_put_contents($configFile, $content);
        if ($res !== false) {
            @chmod($configFile, 0644);
            return true;
        }
        return false;
    }

    /**
     * データベーススキーマと初期データをセットアップ
     */
    public static function setupTables(PDO $pdo): void {
        // 1. database.sql の実行
        $sqlPath = dirname(__DIR__) . '/database.sql';
        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            // DROP TABLEや一括クエリを実行
            $pdo->exec($sql);
        }

        // 2. votes テーブルの作成
        $pdo->exec("CREATE TABLE IF NOT EXISTS `votes` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `article_id` INT UNSIGNED NOT NULL,
          `vote_type` VARCHAR(32) NOT NULL DEFAULT 'believed',
          `voter_hash` VARCHAR(64) NOT NULL DEFAULT '',
          `ip_address` VARCHAR(64) DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_article` (`article_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. デフォルトサイトの登録
        $siteCount = (int)$pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn();
        if ($siteCount === 0) {
            $stmt = $pdo->prepare("INSERT INTO sites (id, subdomain, name, description, genre) VALUES 
                (1, '', 'しらんけど', 'いま日本で話題のトレンドを客観分析し、一次情報とともにお届けするサイト。しらんけど。', 'general'),
                (2, 'game', 'しらんけど ゲーム速報', 'Steam・新作ゲーム・大型アプデのトレンドまとめ。しらんけど。', 'game'),
                (3, 'entame', 'しらんけど エンタメ', 'お笑い・バラエティ・芸能カルチャーの話題。しらんけど。', 'entertainment')");
            $stmt->execute();
        }

        // 4. デフォルトカテゴリの登録
        $catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        if ($catCount === 0) {
            $catStmt = $pdo->prepare("INSERT INTO categories (site_id, slug, name, sort_order) VALUES
                (1, 'all', '総合', 1),
                (1, 'entertainment', 'エンタメ', 2),
                (1, 'sports', 'スポーツ', 3),
                (1, 'tech', 'テクノロジー', 4),
                (1, 'anime', 'アニメ・マンガ', 5),
                (1, 'game', 'ゲーム', 6),
                (1, 'social', '時事・社会', 7),
                (1, 'gourmet', 'グルメ', 8)");
            $catStmt->execute();
        }

        // 5. 機能拡張マイグレーション (trade_sites, trade_feed_items, announcements, analytics等)
        require_once __DIR__ . '/MigrationAddFeatures.php';
        MigrationAddFeatures::run();

        // 6. 管理者アカウントの初期保存 (admin / password)
        $settingsStmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $settingsStmt->execute(['admin_id', 'admin']);
        $settingsStmt->execute(['admin_password', 'password']);
        $settingsStmt->execute(['admin_email', 'sogomultilink@gmail.com']);
        $settingsStmt->execute(['admin_secret_path', 'manage-sk89q']);
    }

    /**
     * セットアップウィザード画面を表示してスクリプト終了
     */
    public static function renderWizard(?string $errorMessage = null, array $defaults = []): void {
        $host = $_POST['db_host'] ?? ($defaults['host'] ?? (defined('DB_HOST') ? DB_HOST : 'localhost'));
        if (empty($host)) {
            $host = 'localhost';
        }
        $port = $_POST['db_port'] ?? ($defaults['port'] ?? (defined('DB_PORT') ? DB_PORT : '3306'));
        $name = $_POST['db_name'] ?? ($defaults['name'] ?? (defined('DB_NAME') && DB_NAME !== 'shirankedo_db' ? DB_NAME : ''));
        $user = $_POST['db_user'] ?? ($defaults['user'] ?? (defined('DB_USER') && DB_USER !== 'root' ? DB_USER : ''));
        $pass = $_POST['db_pass'] ?? ($defaults['pass'] ?? '');

        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>データベース初期セットアップ - しらんけど</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Shippori+Mincho+B1:wght@600;800&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
            </style>
        </head>
        <body class="bg-slate-100 text-slate-800 min-h-screen antialiased flex flex-col justify-center items-center p-4">
            <div class="bg-white max-w-xl w-full rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-xl space-y-6 my-8">
                <div class="text-center space-y-2">
                    <div class="w-14 h-14 rounded-2xl bg-amber-500 text-slate-950 font-black text-2xl flex items-center justify-center shadow-lg mx-auto rotate-[-3deg]">
                        知
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">データベース初期セットアップ</h1>
                    <p class="text-xs text-slate-500">
                        お使いの環境のMySQL接続情報を入力してください。<br>
                        接続確認後、必要なテーブルと初期データを自動構築します。
                    </p>
                </div>

                <?php if ($errorMessage): ?>
                    <div class="p-4 rounded-2xl bg-rose-50 border-2 border-rose-300 text-rose-900 text-xs font-medium space-y-2">
                        <div class="font-black flex items-center gap-2 text-rose-700 text-sm">
                            <span class="text-lg">❌</span> データベースに接続できませんでした
                        </div>
                        <p class="break-all font-mono text-xs bg-white p-2.5 rounded-xl border border-rose-200 text-rose-800 font-bold"><?= htmlspecialchars($errorMessage) ?></p>
                        <p class="text-[11px] text-slate-600 mt-1">
                            ※ ホスト名・データベース名・ユーザー名・パスワードが正しいかご確認ください。
                        </p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>" class="space-y-5">
                    <input type="hidden" name="__installer_action" value="install">

                    <!-- DB設定セクション -->
                    <div class="space-y-4">
                        <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5 border-b border-slate-100 pb-2">
                            <span>🗄️</span> 接続情報の入力
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2 space-y-1">
                                <label class="block text-xs font-bold text-slate-800">
                                    データベースホスト名 <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($host) ?>" required placeholder="localhost または DBホスト名" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                                <p class="text-[10px] text-slate-400">※ 同一サーバー内の場合は通常 localhost です</p>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-800">ポート番号</label>
                                <input type="number" name="db_port" value="<?= htmlspecialchars($port) ?>" required placeholder="3306" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-800">データベース名 (Database Name) <span class="text-rose-500">*</span></label>
                            <input type="text" name="db_name" value="<?= htmlspecialchars($name) ?>" required placeholder="例: shirankedo_db または 作成したDB名" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                            <p class="text-[10px] text-slate-400">※ MySQLで作成したデータベースの名前を入力してください（「localhost」ではありません）</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-800">ユーザー名 (User) <span class="text-rose-500">*</span></label>
                                <input type="text" name="db_user" value="<?= htmlspecialchars($user) ?>" required placeholder="例: root または DBユーザー名" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-800">パスワード (Password)</label>
                                <input type="password" name="db_pass" value="<?= htmlspecialchars($pass) ?>" placeholder="MySQLユーザーのパスワード" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-4 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-sm shadow-md transition-all flex items-center justify-center gap-2 mt-4 cursor-pointer">
                        <span>⚡</span> 接続テスト & データベース初期セットアップを実行する
                    </button>
                </form>

                <!-- FTP直接設置の案内 -->
                <div class="pt-4 border-t border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-600 flex items-center gap-1">
                            📁 ファイルで直接設定を作成・設置する場合
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-relaxed">
                        下記内容の <code>php/db_config.php</code> ファイルを作成して配置することでもDB接続が有効になります。
                    </p>
                    <pre class="p-3 bg-slate-900 text-amber-300 rounded-xl font-mono text-[11px] overflow-x-auto leading-relaxed">&lt;?php
define('DB_HOST', '<?= htmlspecialchars($host ?: 'localhost') ?>');
define('DB_PORT', '3306');
define('DB_NAME', '<?= htmlspecialchars($name ?: 'your_database_name') ?>');
define('DB_USER', '<?= htmlspecialchars($user ?: 'your_db_user') ?>');
define('DB_PASS', 'ここにパスワードを入力');
define('DB_CHARSET', 'utf8mb4');
</pre>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * インストール処理のハンドラー
     */
    public static function handleInstallationRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__installer_action']) && $_POST['__installer_action'] === 'install') {
            $host = trim($_POST['db_host'] ?? 'localhost');
            $port = trim($_POST['db_port'] ?? '3306');
            $name = trim($_POST['db_name'] ?? '');
            $user = trim($_POST['db_user'] ?? '');
            $pass = $_POST['db_pass'] ?? '';

            $defaults = [
                'host' => $host,
                'port' => $port,
                'name' => $name,
                'user' => $user,
                'pass' => $pass,
            ];

            if (empty($name) || empty($user)) {
                self::renderWizard('データベース名とユーザー名は必須です。', $defaults);
            }

            // 1. 接続テスト
            $error = null;
            $pdo = Database::testConnection($error, $host, $port, $name, $user, $pass);
            if (!$pdo) {
                self::renderWizard("データベース接続に失敗しました: " . $error, $defaults);
            }

            // 2. db_config.php への書き込み
            $saved = self::saveDbConfig($host, $port, $name, $user, $pass);
            if (!$saved) {
                $escapedHost = addcslashes($host, "'\\");
                $escapedPort = addcslashes($port, "'\\");
                $escapedName = addcslashes($name, "'\\");
                $escapedUser = addcslashes($user, "'\\");
                $escapedPass = addcslashes($pass, "'\\");
                $snippet = "<?php\ndefine('DB_HOST', '{$escapedHost}');\ndefine('DB_PORT', '{$escapedPort}');\ndefine('DB_NAME', '{$escapedName}');\ndefine('DB_USER', '{$escapedUser}');\ndefine('DB_PASS', '{$escapedPass}');\ndefine('DB_CHARSET', 'utf8mb4');\n";

                self::renderWizard(
                    "設定ファイル <code>php/db_config.php</code> への自動書き込みに失敗しました（ディレクトリの権限エラー）。<br>"
                    . "FTP等で <code>php/db_config.php</code> を作成し、下記の内容を保存してから再度実行してください：<br>"
                    . "<textarea readonly class='w-full h-24 mt-2 p-2 font-mono text-[11px] bg-slate-900 text-slate-100 rounded-xl'>" . htmlspecialchars($snippet) . "</textarea>",
                    $defaults
                );
            }

            // 3. テーブルおよび初期データの作成
            try {
                self::setupTables($pdo);
            } catch (Throwable $e) {
                self::renderWizard("テーブル作成中にエラーが発生しました: " . $e->getMessage(), $defaults);
            }

            // 4. セッションリセット & 自動ログイン
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = 'admin';
                $_SESSION['admin_login_time'] = time();
            }

            // リダイレクト (GET)
            $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? 'admin-manage-sk89q.php');
            if ($scriptName === 'index.php') {
                header("Location: admin-manage-sk89q.php?installed=1");
            } else {
                header("Location: {$scriptName}?installed=1");
            }
            exit;
        }
    }
}
