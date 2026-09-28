<?php
/**
 * 実データだけを使うトレンド収集エンジン。
 *
 * 取得元:
 * - Google Trends 日本 急上昇ワード RSS
 * - Google ニュース 日本トップ RSS（補完）
 *
 * 取得に失敗した場合、架空ワードや乱数スコアは作成しない。
 */
class TrendCollector {
    public static function collectAndIntegrate(int $siteId): array {
        $db = Database::getConnection();
        $rawTrends = self::fetchFromSources();

        $insertedCount = 0;
        $updatedCount = 0;

        foreach ($rawTrends as $t) {
            $normalized = self::normalizeKeyword($t['keyword']);
            if ($normalized === '') {
                continue;
            }

            $stmt = $db->prepare("SELECT id FROM trend_candidates WHERE site_id = ? AND normalized_keyword = ? LIMIT 1");
            $stmt->execute([$siteId, $normalized]);
            $existingId = $stmt->fetchColumn();

            $google = (int)($t['scores']['google'] ?? 0);
            $news = (int)($t['scores']['news'] ?? 0);
            $scores = [
                'google' => $google,
                'yahoo' => 0,
                'youtube' => 0,
                'news' => $news,
                'game' => 0,
            ];

            $calc = ShirankedoIndex::calculate($siteId, $scores);
            $index = (int)$calc['index'];

            if ($existingId) {
                $upd = $db->prepare("UPDATE trend_candidates
                                     SET display_keyword = ?,
                                         sources_json = ?,
                                         google_score = ?,
                                         yahoo_score = 0,
                                         youtube_score = 0,
                                         news_score = ?,
                                         game_score = 0,
                                         shirankedo_index = ?,
                                         is_rapid_rise = ?,
                                         growth_rate = ?,
                                         last_updated_at = NOW()
                                     WHERE id = ?");
                $upd->execute([
                    $t['keyword'],
                    json_encode($t['sources'], JSON_UNESCAPED_UNICODE),
                    $google,
                    $news,
                    $index,
                    !empty($t['is_rapid']) ? 1 : 0,
                    (float)($t['growth_rate'] ?? 0),
                    $existingId,
                ]);
                $updatedCount++;
            } else {
                $ins = $db->prepare("INSERT INTO trend_candidates
                    (site_id, normalized_keyword, display_keyword, sources_json,
                     google_score, yahoo_score, youtube_score, news_score, game_score,
                     shirankedo_index, is_rapid_rise, growth_rate, first_detected_at, status)
                    VALUES (?, ?, ?, ?, ?, 0, 0, ?, 0, ?, ?, ?, NOW(), 'candidate')");
                $ins->execute([
                    $siteId,
                    $normalized,
                    $t['keyword'],
                    json_encode($t['sources'], JSON_UNESCAPED_UNICODE),
                    $google,
                    $news,
                    $index,
                    !empty($t['is_rapid']) ? 1 : 0,
                    (float)($t['growth_rate'] ?? 0),
                ]);
                $insertedCount++;
            }
        }

        return [
            'inserted' => $insertedCount,
            'updated' => $updatedCount,
            'fetched' => count($rawTrends),
        ];
    }

    public static function normalizeKeyword(string $kw): string {
        $kw = mb_convert_kana($kw, 'asKV', 'UTF-8');
        $kw = mb_strtolower($kw, 'UTF-8');
        $kw = preg_replace('/\s+/', '', $kw);
        return trim($kw);
    }

    private static function fetchFromSources(): array {
        $trends = [];

        // Google Trends 日本 急上昇ワード。approx_traffic は実データを段階スコア化。
        $xmlContent = self::fetchUrlWithTimeout('https://trends.google.co.jp/trending/rss?geo=JP', 6);
        if ($xmlContent) {
            $parsed = @simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($parsed && isset($parsed->channel->item)) {
                $count = 0;
                foreach ($parsed->channel->item as $item) {
                    if ($count >= 25) break;
                    $title = trim((string)$item->title);
                    if ($title === '') continue;

                    $approxTraffic = null;
                    $namespaces = $item->getNamespaces(true);
                    if (isset($namespaces['ht'])) {
                        $ht = $item->children($namespaces['ht']);
                        $rawTraffic = trim((string)($ht->approx_traffic ?? ''));
                        if (preg_match('/(\d[\d,]*)/', $rawTraffic, $m)) {
                            $approxTraffic = (int)str_replace(',', '', $m[1]);
                        }
                    }

                    $googleScore = self::trafficToScore($approxTraffic);
                    $trends[] = [
                        'keyword' => $title,
                        'sources' => ['google_trends'],
                        'scores' => ['google' => $googleScore, 'news' => 0],
                        'growth_rate' => 0,
                        'is_rapid' => $approxTraffic !== null && $approxTraffic >= 100000,
                    ];
                    $count++;
                }
            }
        }

        // Google Trendsだけに偏らないよう、Googleニュースも毎回補完する。
        // 同一キーワードは正規化して重複除外する。
        {
            $newsXml = self::fetchUrlWithTimeout('https://news.google.com/rss?hl=ja&gl=JP&ceid=JP:ja', 6);
            if ($newsXml) {
                $newsParsed = @simplexml_load_string($newsXml, 'SimpleXMLElement', LIBXML_NOCDATA);
                if ($newsParsed && isset($newsParsed->channel->item)) {
                    $existing = [];
                    foreach ($trends as $t) {
                        $existing[self::normalizeKeyword($t['keyword'])] = true;
                    }

                    foreach ($newsParsed->channel->item as $item) {
                        if (count($trends) >= 40) break;
                        $title = trim((string)$item->title);
                        $title = preg_replace('/ - [^ -]+$/u', '', $title);
                        if (mb_strlen($title) < 5) continue;

                        $key = self::normalizeKeyword($title);
                        if ($key === '' || isset($existing[$key])) continue;
                        $existing[$key] = true;

                        $trends[] = [
                            'keyword' => $title,
                            'sources' => ['google_news'],
                            'scores' => ['google' => 0, 'news' => 70],
                            'growth_rate' => 0,
                            'is_rapid' => false,
                        ];
                    }
                }
            }
        }

        return $trends;
    }

    private static function trafficToScore(?int $traffic): int {
        if ($traffic === null) return 60;
        if ($traffic >= 1000000) return 100;
        if ($traffic >= 500000) return 95;
        if ($traffic >= 200000) return 90;
        if ($traffic >= 100000) return 85;
        if ($traffic >= 50000) return 80;
        if ($traffic >= 20000) return 75;
        if ($traffic >= 10000) return 70;
        return 65;
    }

    private static function fetchUrlWithTimeout(string $url, int $timeout = 6): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ShirankedoTrendFetcher/1.0)',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && is_string($res) && $res !== '') {
                return $res;
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'Mozilla/5.0 (compatible; ShirankedoTrendFetcher/1.0)',
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return is_string($res) && $res !== '' ? $res : null;
    }
}
