<?php
/**
 * ルート直下 article.php エントリポイント
 */
if (file_exists(__DIR__ . '/php/article.php')) {
    require_once __DIR__ . '/php/article.php';
} else {
    echo "<h1>404 Not Found</h1><p>php/article.php が見つかりません。</p>";
}
