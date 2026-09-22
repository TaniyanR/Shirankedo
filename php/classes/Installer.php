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
        $host = $_POST['db_host'] ?? ($defaults['host'] ?? (defined('DB_HOST') ? DB_HOST : ''));
        if (empty($host)) {
            $host = 'localhost';
        }
        $port = $_POST['db_port'] ?? ($defaults['port'] ?? (defined('DB_PORT') ? DB_PORT : '3306'));
        $name = $_POST['db_name'] ?? ($defaults['name'] ?? (defined('DB_NAME') && DB_NAME !== 'shirankedo_db' ? DB_NAME : ''));
        $user = $_POST['db_user'] ?? ($defaults['user'] ?? (defined('DB_USER') && DB_USER !== 'root' ? DB_USER : ''));
        $pass = $_POST['db_pass'] ?? ($defaults['pass'] ?? '');

        // シン・レンタルサーバー特有の localhost エラー検知
        $isLocalhostAccessDenied = ($errorMessage && stripos($errorMessage, "Access denied") !== false && stripos($host, "localhost") !== false);

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
                        サーバー環境（シン・レンタルサーバー等）のMySQL接続情報を入力してください。<br>
                        接続確認後、必要なテーブルと初期データを自動構築します。
                    </p>
                </div>

                <?php if ($errorMessage): ?>
                    <div class="p-4 rounded-2xl bg-rose-50 border-2 border-rose-300 text-rose-900 text-xs font-medium space-y-3">
                        <div class="font-black flex items-center gap-2 text-rose-700 text-sm">
                            <span class="text-lg">❌</span> データベースに接続できませんでした
                        </div>
                        <p class="break-all font-mono text-xs bg-white p-2.5 rounded-xl border border-rose-200 text-rose-800 font-bold"><?= htmlspecialchars($errorMessage) ?></p>

                        <?php if ($isLocalhostAccessDenied): ?>
                            <div class="p-3.5 bg-amber-50 border-2 border-amber-400 rounded-2xl text-amber-950 space-y-2">
                                <div class="font-black flex items-center gap-1.5 text-amber-900 text-sm">
                                    <span>⚠️</span> 【失敗の原因】ホスト名が「localhost」のままです！
                                </div>
                                <div class="text-xs leading-relaxed space-y-1.5 text-slate-800">
                                    <p>シン・レンタルサーバーでは、ホスト名に <strong>localhost は使用できません</strong>。</p>
                                    <p class="bg-white p-2 rounded-lg border border-amber-200">
                                        👉 シン・レンタルサーバーの<strong>「サーバーパネル」＞「MySQL設定」</strong>を開き、画面最下部にある<strong>『MySQLホスト名』</strong>（例: <code class="font-bold text-rose-600 bg-rose-50 px-1 py-0.5 rounded">mysql○○○○.shin-server.jp</code> 等）をコピーして、下記の「データベースホスト名」に貼り付けてください。
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- シン・レンタルサーバーの設定手順 -->
                <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 text-slate-800 text-xs space-y-2">
                    <div class="font-black text-amber-950 flex items-center gap-1.5 text-sm">
                        <span>💡</span> シン・レンタルサーバーでの入力箇所の確認手順
                    </div>
                    <div class="text-[11px] text-slate-700 space-y-1.5 leading-relaxed">
                        <p>1. <strong>データベースホスト名:</strong> サーバーパネルの「MySQL設定」画面の<strong>一番下</strong>に記載されているホスト名（※ <code>localhost</code> ではありません）</p>
                        <p>2. <strong>データベース名:</strong> サーバーパネルで作成したDB名（例: <code>ganmodokir_〇〇</code>）</p>
                        <p>3. <strong>ユーザー名:</strong> サーバーパネルで作成したMySQLユーザー名（例: <code>ganmodokir_shi</code>）</p>
                        <p>4. <strong>パスワード:</strong> ユーザー作成時にご自身で決めたパスワード</p>
                        <p class="text-amber-800 font-bold">※「MySQL設定」の「MySQL一覧」で、該当データベースの『アクセス権所有ユーザ』にユーザーが追加されている必要があります。</p>
                    </div>
                </div>

                <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>" class="space-y-5" onsubmit="return validateForm(this);">
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
                                    <span class="text-[10px] font-normal text-rose-600 ml-1">※ localhost は不可</span>
                                </label>
                                <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($host === 'localhost' ? '' : $host) ?>" required placeholder="例: mysql1001.shin-server.jp" class="w-full px-3.5 py-2.5 rounded-xl border-2 border-slate-200 bg-white focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 text-xs font-mono text-slate-900 font-bold">
                                <p class="text-[10px] text-slate-500">※ サーバーパネル「MySQL設定」最下部のホスト名を入力してください</p>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-800">ポート番号</label>
                                <input type="number" name="db_port" value="<?= htmlspecialchars($port) ?>" required placeholder="3306" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-800">データベース名 (Database Name) <span class="text-rose-500">*</span></label>
                            <input type="text" name="db_name" value="<?= htmlspecialchars($name) ?>" required placeholder="例: ganmodokir_db" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-800">ユーザー名 (User) <span class="text-rose-500">*</span></label>
                                <input type="text" name="db_user" value="<?= htmlspecialchars($user) ?>" required placeholder="例: ganmodokir_shi" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                            </div>
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-800">パスワード (Password) <span class="text-rose-500">*</span></label>
                                <input type="password" name="db_pass" value="<?= htmlspecialchars($pass) ?>" required placeholder="MySQLユーザーのパスワード" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 text-xs font-mono text-slate-900">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-4 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-sm shadow-md transition-all flex items-center justify-center gap-2 mt-4 cursor-pointer">
                        <span>⚡</span> 接続テスト & データベース初期セットアップを実行する
                    </button>
                </form>

                <script>
                function validateForm(form) {
                    var host = form.db_host.value.trim().toLowerCase();
                    if (host === 'localhost' || host === '127.0.0.1') {
                        alert('【ご注意】\nシン・レンタルサーバーではホスト名に「localhost」は使用できません。\n\nサーバーパネルの「データベース」＞「MySQL設定」の最下部に記載されている『MySQLホスト名』（例: mysql○○○○.shin-server.jp）を入力してください。');
                        form.db_host.focus();
                        return false;
                    }
                    return true;
                }
                </script>

                <!-- FTP直接設置の案内 -->
                <div class="pt-4 border-t border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-600 flex items-center gap-1">
                            📁 FTPで直接設定ファイルを作成する場合 (確実・推奨)
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-relaxed">
                        画面からうまくいかない場合は、メモ帳等で下記内容の <code>php/db_config.php</code> ファイルを作成し、FileZilla等のFTPで <code>php/</code> ディレクトリにアップロードするだけでDB接続が完了します。
                    </p>
                    <pre class="p-3 bg-slate-900 text-amber-300 rounded-xl font-mono text-[11px] overflow-x-auto leading-relaxed">&lt;?php
define('DB_HOST', 'mysql○○○○.shin-server.jp'); // サーバーパネル最下部のホスト名
define('DB_PORT', '3306');
define('DB_NAME', '<?= htmlspecialchars($name ?: 'ganmodokir_db') ?>');
define('DB_USER', '<?= htmlspecialchars($user ?: 'ganmodokir_shi') ?>');
define('DB_PASS', 'ここにMySQLパスワードを入力');
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
