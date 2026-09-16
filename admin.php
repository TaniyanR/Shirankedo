<?php
/**
 * ルート直下 admin.php エントリポイント
 */
if (file_exists(__DIR__ . '/php/admin.php')) {
    require_once __DIR__ . '/php/admin.php';
} else {
    echo "<h1>404 Not Found</h1><p>php/admin.php が見つかりません。</p>";
}
