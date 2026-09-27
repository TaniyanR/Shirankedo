<?php
/**
 * 記事生成用の実ニュース参照元収集。
 * GoogleニュースRSSから実在する記事候補を取得し、タイトル・媒体名・URL・概要を返す。
 */
class NewsSourceCollector {
    public static function collect(string $keyword, int $limit = 5): array {
        $keyword = trim($keyword);
        if ($keyword === '') return [];

        $url = 'https://news.google.com/rss/search?q=' . rawurlencode($keyword)
             . '&hl=ja&gl=JP&ceid=JP:ja';

        $xmlString = self::fetch($url);
        if (!$xmlString) return [];

        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml || !isset($xml->channel->item)) return [];

        $sources = [];
        $seen = [];

        foreach ($xml->channel->item as $item) {
            if (count($sources) >= max(1, $limit)) break;

            $title = trim((string)$item->title);
            $link = trim((string)$item->link);
            $publisher = trim((string)($item->source ?? ''));
            $description = trim(strip_tags((string)($item->description ?? '')));
            $publishedAt = trim((string)($item->pubDate ?? ''));

            if ($title === '' || $link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }

            $key = mb_strtolower($title);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            // Googleニュースのタイトル末尾に媒体名が付く場合はpublisherとして補完。
            if ($publisher === '' && preg_match('/\s+-\s+([^\-]+)$/u', $title, $m)) {
                $publisher = trim($m[1]);
            }
            if ($publisher === '') $publisher = 'Googleニュース掲載媒体';

            $sources[] = [
                'source_type' => 'news',
                'title' => mb_substr($title, 0, 255),
                'publisher' => mb_substr($publisher, 0, 128),
                'url' => mb_substr($link, 0, 512),
                'summary' => mb_substr($description, 0, 1000),
                'published_at' => $publishedAt,
                'reliability_score' => 90,
            ];
        }

        return $sources;
    }

    private static function fetch(string $url): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ShirankedoSourceCollector/1.0)',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && is_string($body) && $body !== '') {
                return $body;
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'user_agent' => 'Mozilla/5.0 (compatible; ShirankedoSourceCollector/1.0)',
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) && $body !== '' ? $body : null;
    }
}
