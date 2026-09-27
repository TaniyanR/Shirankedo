<?php
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/SiteAssetManager.php';

$type = $_GET['type'] ?? '';
$siteId = max(1, (int)($_GET['site'] ?? 1));

if (!in_array($type, ['logo', 'favicon', 'ogp'], true)) {
    http_response_code(404);
    exit;
}

$asset = SiteAssetManager::get($siteId, $type);
if (!$asset) {
    http_response_code(404);
    exit;
}

$etag = '"' . sha1($asset['updated_at'] . '|' . strlen($asset['data'])) . '"';
header('Content-Type: ' . $asset['mime_type']);
header('Cache-Control: public, max-age=3600');
header('ETag: ' . $etag);

if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

echo $asset['data'];
