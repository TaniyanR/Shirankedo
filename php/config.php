<?php
/**
 * 「しらんけど」トレンドサイトシステム 設定ファイル
 */

// DB接続設定
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'shirankedo_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

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
