<?php
/**
 * XMLサイトマップ自動生成 (Google Search Console & Bing Webmaster Tools 対応)
 */
header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/config.php';

$domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'shirankedo.bichi.xyz');

$articles = [];
$categories = [];
try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT slug, id, published_at FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 1000");
    $articles = $stmt->fetchAll();

    $catStmt = $db->query("SELECT slug FROM categories ORDER BY sort_order ASC");
    $categories = $catStmt->fetchAll();
} catch (Throwable $e) {}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- トップページ -->
    <url>
        <loc><?= htmlspecialchars($domain) ?>/</loc>
        <changefreq>hourly</changefreq>
        <priority>1.0</priority>
    </url>
    <!-- 固定ページ -->
    <url>
        <loc><?= htmlspecialchars($domain) ?>/page.php?slug=about</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($domain) ?>/page.php?slug=trade</loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($domain) ?>/page.php?slug=news</loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($domain) ?>/page.php?slug=privacy-policy</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($domain) ?>/page.php?slug=que</loc>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>

    <!-- カテゴリ一覧 -->
    <?php foreach ($categories as $cat): ?>
    <url>
        <loc><?= htmlspecialchars($domain) ?>/?cat=<?= urlencode($cat['slug']) ?></loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>

    <!-- 公開記事一覧 -->
    <?php foreach ($articles as $art): ?>
    <url>
        <loc><?= htmlspecialchars($domain) ?>/article.php?id=<?= $art['id'] ?></loc>
        <lastmod><?= date('c', strtotime($art['published_at'] ?? 'now')) ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <?php endforeach; ?>
</urlset>
