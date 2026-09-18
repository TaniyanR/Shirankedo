<?php
/**
 * 「しらんけど」トレンドサイトシステム 設定ファイル
 */

// DB接続設定 (db_config.php が存在すれば最優先でロード)
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'shirankedo_db');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ルートURL・ワイルドカードドメイン設定
define('MAIN_DOMAIN', getenv('MAIN_DOMAIN') ?: ($_SERVER['HTTP_HOST'] ?? 'shirankedo.bichi.xyz'));
define('APP_SECRET_KEY', getenv('APP_SECRET_KEY') ?: 'shirankedo_super_secure_token_2026');

// セッション開始 (安全な設定)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// タイムゾーン
date_default_timezone_set('Asia/Tokyo');

// オートローダー
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
