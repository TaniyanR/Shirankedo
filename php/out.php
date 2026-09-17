<?php
/**
 * 相互リンク・相互RSS クリックスルートラッキング (out.php)
 * OUTアクセスをカウントして相手サイトへリダイレクト
 */
require_once __DIR__ . '/config.php';

$siteId = (int)($_GET['site_id'] ?? 0);
$targetUrl = trim($_GET['url'] ?? '');

if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    header('Location: index.php');
    exit;
}

// OUTアクセスをカウント
if ($siteId > 0) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE trade_sites SET out_count = out_count + 1, today_out = today_out + 1 WHERE id = ?");
        $stmt->execute([$siteId]);
    } catch (Throwable $e) {
        // DB不可時もリダイレクトは通す
    }
}

// 相手先へリダイレクト
header("Location: " . $targetUrl, true, 302);
exit;
