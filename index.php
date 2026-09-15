<?php
/**
 * 「しらんけど」ルート エントリポイント
 * ドキュメントルートがリポジトリ直下に設定されているレンタルサーバー環境向け
 */
if (file_exists(__DIR__ . '/php/index.php')) {
    require_once __DIR__ . '/php/index.php';
} else {
    echo "<h1>404 Not Found</h1><p>php/index.php が見つかりません。</p>";
}
