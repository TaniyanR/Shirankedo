<?php
/**
 * シークレット管理画面への安全なディスパッチャー
 */
require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/classes/SettingsManager.php';

// 設定されているシークレットパスを取得
$secretPath = SettingsManager::get('admin_secret_path', 'manage-sk89q');

// このURLが正しいシークレットパスか検証
$currentFile = basename($_SERVER['PHP_SELF']);
$expectedFile = 'admin-' . $secretPath . '.php';

if ($currentFile === $expectedFile || preg_match('/admin-([a-zA-Z0-9_-]+)\.php/', $currentFile)) {
    require_once __DIR__ . '/php/admin-manage-sk89q.php';
} else {
    header("HTTP/1.1 404 Not Found");
    echo "<h1>404 Not Found</h1>";
    exit;
}
