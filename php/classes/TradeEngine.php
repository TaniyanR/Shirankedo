<?php
/**
 * 相互リンク・相互RSS返還エンジン (TradeEngine.php)
 * - 逆アクセスの記録 (INカウント)
 * - アクセス返還率（80%, 100%, 120%, 150%など）と特別優遇枠に応じた重み付け出稿計算
 * - RSSフィードの自動取得＆パース（画像付き判定・テキストのみ分離）
 * - 配信クリック時のOUTトラッキング
 */
require_once __DIR__ . '/Database.php';

class TradeEngine {
    /**
     * 流入（INアクセス）を検知してカウント
     */
    public static function trackIncomingReferrer(): void {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (empty($referer)) return;

        $refHost = parse_url($referer, PHP_URL_HOST);
        if (empty($refHost)) return;

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM trade_sites WHERE status = 'approved' AND (url LIKE ? OR url LIKE ?) LIMIT 1");
            $like1 = "%://" . $refHost . "/%";
            $like2 = "%://" . $refHost;
            $stmt->execute([$like1, $like2]);
            $site = $stmt->fetch();

            if ($site) {
                $update = $db->prepare("UPDATE trade_sites SET in_count = in_count + 1, today_in = today_in + 1, last_in_at = NOW() WHERE id = ?");
                $update->execute([$site['id']]);
            }
        } catch (Throwable $e) {
            // エラー時は静かに握りつぶす
        }
    }

    /**
     * 重み付け確率（返還率×INアクセス数＋特別優遇ブースト）に基づいて、表示するRSSフィード記事を取得
     * 
     * @param bool $requireImage 画像必須かどうか (PCヘッダー下/サイド/フッター上などはtrue, 本文下やスマホテキストはfalse)
     * @param int $limit 取得件数
     * @return array
     */
    public static function getDisplayFeedItems(bool $requireImage = false, int $limit = 5): array {
        try {
            $db = Database::getConnection();
            
            // 承認済みサイトのリストと返還比率を取得
            $sites = $db->query("SELECT id, site_name, url, return_rate, is_boosted, boost_weight, in_count, out_count FROM trade_sites WHERE status = 'approved'")->fetchAll();
            if (empty($sites)) {
                return self::getFallbackFeedItems($requireImage, $limit);
            }

            // スコア算出 (IN数 * 返還率% * ブースト係数 - OUTペナルティ)
            // 目標: 相手がくれた分(返還率考慮)だけ返す
            $weightedSiteIds = [];
            foreach ($sites as $s) {
                $targetOut = max(1, round(($s['in_count'] * ($s['return_rate'] / 100))));
                // 優遇枠の場合はウェイトを加算
                if ($s['is_boosted']) {
                    $targetOut *= max(2, (int)$s['boost_weight']);
                }
                
                // 還元残高 = 送るべきアクセス数 - 既に送ったアクセス数
                $balance = $targetOut - $s['out_count'];
                $weight = max(1, $balance > 0 ? $balance : 1);
                
                // 重み付けプールにIDを詰める（最大50でキャップ）
                $poolEntries = min(50, (int)$weight);
                for ($i = 0; $i < $poolEntries; $i++) {
                    $weightedSiteIds[] = $s['id'];
                }
            }

            // ランダムに選定
            shuffle($weightedSiteIds);
            $selectedSiteIds = array_slice(array_unique($weightedSiteIds), 0, $limit * 2);
            if (empty($selectedSiteIds)) {
                $selectedSiteIds = array_column($sites, 'id');
            }

            $inPlaceholders = implode(',', array_fill(0, count($selectedSiteIds), '?'));
            $sql = "SELECT i.*, s.site_name 
                    FROM trade_feed_items i
                    JOIN trade_sites s ON i.trade_site_id = s.id
                    WHERE i.trade_site_id IN ($inPlaceholders) AND s.status = 'approved'";
            if ($requireImage) {
                $sql .= " AND i.has_image = 1 AND i.image_url IS NOT NULL AND i.image_url != ''";
            }
            $sql .= " ORDER BY i.published_at DESC LIMIT " . (int)$limit;

            $stmt = $db->prepare($sql);
            $stmt->execute($selectedSiteIds);
            $items = $stmt->fetchAll();

            if (empty($items)) {
                return self::getFallbackFeedItems($requireImage, $limit);
            }

            return $items;
        } catch (Throwable $e) {
            return self::getFallbackFeedItems($requireImage, $limit);
        }
    }

    /**
     * OUTクリックスルーURL生成
     */
    public static function getOutboundLink(int $tradeSiteId, string $targetUrl): string {
        return "out.php?site_id=" . $tradeSiteId . "&url=" . urlencode($targetUrl);
    }

    /**
     * 承認済み全サイトのリンク一覧（相互リンク集用）
     */
    public static function getApprovedLinks(): array {
        try {
            $db = Database::getConnection();
            $sites = $db->query("SELECT id, site_name, url FROM trade_sites WHERE status = 'approved' ORDER BY is_boosted DESC, in_count DESC, id ASC LIMIT 100")->fetchAll();
            if (!empty($sites)) {
                return $sites;
            }
            return self::getFallbackApprovedLinks();
        } catch (Throwable $e) {
            return self::getFallbackApprovedLinks();
        }
    }

    /**
     * 初期表示用 フォールバック相互リンク一覧
     */
    public static function getFallbackApprovedLinks(): array {
        return [
            ['id' => 1, 'site_name' => '2chまとめアンテナ', 'url' => 'https://2ch-c.net/'],
            ['id' => 2, 'site_name' => 'しぃアンテナ(*ﾟーﾟ)', 'url' => 'http://2ch-c.net/'],
            ['id' => 3, 'site_name' => 'だめぽアンテナ', 'url' => 'https://damepo.net/'],
            ['id' => 4, 'site_name' => 'ヌルポアンテナ', 'url' => 'https://nullpoantenna.com/'],
            ['id' => 5, 'site_name' => 'ニュース速報まとめアンテナ', 'url' => 'https://news-matome-antenna.com/'],
            ['id' => 6, 'site_name' => '芸能・エンタメ速報アンテナ', 'url' => 'https://geinou-antenna.com/'],
            ['id' => 7, 'site_name' => 'ゲームトレンド速報アンテナ', 'url' => 'https://gametrend-antenna.com/'],
            ['id' => 8, 'site_name' => 'IT・ガジェットまとめアンテナ', 'url' => 'https://itgadget-antenna.net/'],
            ['id' => 9, 'site_name' => 'スポーツ速報ナビ', 'url' => 'https://sports-navi-antenna.com/'],
            ['id' => 10, 'site_name' => 'カルチャートレンド総合アンテナ', 'url' => 'https://culture-trend-antenna.jp/'],
            ['id' => 11, 'site_name' => '話題のバズニュースまとめ', 'url' => 'https://buzz-matome-news.com/'],
            ['id' => 12, 'site_name' => 'SNSホットワードアンテナ', 'url' => 'https://snshotword-antenna.net/']
        ];
    }

    /**
     * 文字列から複数RSSフィードのURL配列を抽出（改行・カンマ区切り対応）
     */
    public static function extractRssUrls(string $rawRss): array {
        $lines = preg_split('/[\r\n,]+/', $rawRss);
        $urls = [];
        foreach ($lines as $line) {
            $u = trim($line);
            if (!empty($u) && filter_var($u, FILTER_VALIDATE_URL)) {
                $urls[] = $u;
            }
        }
        return array_values(array_unique($urls));
    }

    /**
     * 登録された提携サイト（または指定サイト）の全RSSフィードを巡回して記事キャッシュを更新
     * 1サイトに複数登録されたRSSフィードもすべて巡回します。
     *
     * @param int|null $tradeSiteId
     * @return array
     */
    public static function fetchRssFeeds(?int $tradeSiteId = null): array {
        $stats = [
            'sites_checked' => 0,
            'feeds_checked' => 0,
            'items_saved' => 0,
            'errors' => []
        ];

        try {
            $db = Database::getConnection();
            if ($tradeSiteId !== null) {
                $stmt = $db->prepare("SELECT id, site_name, url, rss_url FROM trade_sites WHERE id = ?");
                $stmt->execute([$tradeSiteId]);
                $sites = $stmt->fetchAll();
            } else {
                $sites = $db->query("SELECT id, site_name, url, rss_url FROM trade_sites WHERE status = 'approved'")->fetchAll();
            }

            if (empty($sites)) {
                return $stats;
            }

            $stats['sites_checked'] = count($sites);

            $insertStmt = $db->prepare("INSERT INTO trade_feed_items 
                (trade_site_id, title, url, image_url, has_image, published_at)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    image_url = VALUES(image_url), 
                    has_image = VALUES(has_image), 
                    published_at = VALUES(published_at)");

            $updateSiteStmt = $db->prepare("UPDATE trade_sites SET last_rss_fetched_at = NOW() WHERE id = ?");

            foreach ($sites as $site) {
                $siteId = (int)$site['id'];
                $feedUrls = self::extractRssUrls($site['rss_url'] ?? '');
                if (empty($feedUrls)) {
                    continue;
                }

                $siteHadSuccess = false;
                foreach ($feedUrls as $feedUrl) {
                    $stats['feeds_checked']++;
                    try {
                        $items = self::parseSingleRssFeed($feedUrl);
                        foreach ($items as $item) {
                            $insertStmt->execute([
                                $siteId,
                                mb_substr($item['title'], 0, 255),
                                mb_substr($item['url'], 0, 500),
                                !empty($item['image_url']) ? mb_substr($item['image_url'], 0, 500) : null,
                                $item['has_image'] ? 1 : 0,
                                $item['published_at']
                            ]);
                            $stats['items_saved']++;
                        }
                        $siteHadSuccess = true;
                    } catch (Throwable $feedErr) {
                        $stats['errors'][] = "「{$site['site_name']}」({$feedUrl}): " . $feedErr->getMessage();
                    }
                }

                if ($siteHadSuccess) {
                    $updateSiteStmt->execute([$siteId]);
                }
            }
        } catch (Throwable $e) {
            $stats['errors'][] = "DB接続エラー: " . $e->getMessage();
        }

        return $stats;
    }

    /**
     * 単一のRSS / AtomフィードURLを取得＆パース
     */
    private static function parseSingleRssFeed(string $url): array {
        $xmlString = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Shirankedo-TradeRSS/1.0; +https://shirankedo.bichi.xyz/)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $xmlString = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 6,
                    'user_agent' => 'Shirankedo-TradeRSS/1.0'
                ]
            ]);
            $xmlString = @file_get_contents($url, false, $ctx);
        }

        if (empty($xmlString)) {
            throw new Exception("フィードの取得に失敗しました (空のレスポンス)");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            throw new Exception("XMLパースエラー");
        }

        $items = [];
        $namespaces = $xml->getNamespaces(true);

        // 1. RSS 2.0 (<channel><item>)
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $it) {
                $title = trim((string)$it->title);
                $link = trim((string)$it->link);
                if (empty($title) || empty($link)) continue;

                $pubDate = !empty($it->pubDate) ? date('Y-m-d H:i:s', strtotime((string)$it->pubDate)) : date('Y-m-d H:i:s');
                $imageUrl = self::extractImageFromXmlItem($it, $namespaces);

                $items[] = [
                    'title' => $title,
                    'url' => $link,
                    'image_url' => $imageUrl,
                    'has_image' => !empty($imageUrl),
                    'published_at' => $pubDate
                ];
                if (count($items) >= 20) break;
            }
        }
        // 2. Atom (<entry>)
        elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $title = trim((string)$entry->title);
                $link = '';
                if (isset($entry->link)) {
                    foreach ($entry->link as $l) {
                        $rel = (string)($l['rel'] ?? 'alternate');
                        if ($rel === 'alternate' || empty($link)) {
                            $link = (string)$l['href'];
                        }
                    }
                }
                if (empty($title) || empty($link)) continue;

                $published = (string)($entry->published ?? $entry->updated ?? '');
                $pubDate = !empty($published) ? date('Y-m-d H:i:s', strtotime($published)) : date('Y-m-d H:i:s');
                $imageUrl = self::extractImageFromXmlItem($entry, $namespaces);

                $items[] = [
                    'title' => $title,
                    'url' => $link,
                    'image_url' => $imageUrl,
                    'has_image' => !empty($imageUrl),
                    'published_at' => $pubDate
                ];
                if (count($items) >= 20) break;
            }
        }
        // 3. RDF / RSS 1.0 (<item>)
        elseif (isset($xml->item)) {
            foreach ($xml->item as $it) {
                $title = trim((string)$it->title);
                $link = trim((string)$it->link);
                if (empty($title) || empty($link)) continue;

                $dc = $it->children($namespaces['dc'] ?? '');
                $dcDate = (string)($dc->date ?? '');
                $pubDate = !empty($dcDate) ? date('Y-m-d H:i:s', strtotime($dcDate)) : date('Y-m-d H:i:s');
                $imageUrl = self::extractImageFromXmlItem($it, $namespaces);

                $items[] = [
                    'title' => $title,
                    'url' => $link,
                    'image_url' => $imageUrl,
                    'has_image' => !empty($imageUrl),
                    'published_at' => $pubDate
                ];
                if (count($items) >= 20) break;
            }
        }

        return $items;
    }

    /**
     * XMLアイテムノードからアイキャッチ画像URLを抽出
     */
    private static function extractImageFromXmlItem(SimpleXMLElement $node, array $namespaces): ?string {
        // A. <enclosure type="image/..." url="...">
        if (isset($node->enclosure)) {
            foreach ($node->enclosure as $enc) {
                $type = (string)($enc['type'] ?? '');
                $url = (string)($enc['url'] ?? '');
                if (str_starts_with($type, 'image/') || preg_match('/\.(jpg|jpeg|png|webp|gif)/i', $url)) {
                    return $url;
                }
            }
        }

        // B. media:content / media:thumbnail
        if (isset($namespaces['media'])) {
            $media = $node->children($namespaces['media']);
            if (isset($media->content)) {
                foreach ($media->content as $mc) {
                    $url = (string)($mc['url'] ?? '');
                    if (!empty($url)) return $url;
                }
            }
            if (isset($media->thumbnail)) {
                $thumbUrl = (string)($media->thumbnail['url'] ?? '');
                if (!empty($thumbUrl)) return $thumbUrl;
            }
        }

        // C. description または content:encoded 内の <img> タグ
        $htmlText = (string)$node->description;
        if (isset($namespaces['content'])) {
            $content = $node->children($namespaces['content']);
            if (isset($content->encoded)) {
                $htmlText .= ' ' . (string)$content->encoded;
            }
        }
        if (!empty($htmlText) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $htmlText, $m)) {
            $imgCandidate = $m[1];
            if (filter_var($imgCandidate, FILTER_VALIDATE_URL)) {
                return $imgCandidate;
            }
        }

        return null;
    }

    /**
     * 初期デモ用フィードアイテム（相互RSS未登録時）
     */
    private static function getFallbackFeedItems(bool $requireImage, int $limit): array {
        $fallbacks = [
            [
                'id' => 1,
                'trade_site_id' => 0,
                'site_name' => 'トレンドニュース速報',
                'title' => '【話題】今年流行りの最新便利グッズBEST5が発表！',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2,
                'trade_site_id' => 0,
                'site_name' => 'エンタメまとめch',
                'title' => '深夜の突撃ロケ企画でハプニング連発、ネット騒然！',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 3,
                'trade_site_id' => 0,
                'site_name' => 'ゲームギーク速報',
                'title' => '全世界待望の新作アクションRPG、配信直後に歴代同接記録を更新',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 4,
                'trade_site_id' => 0,
                'site_name' => 'ネットの噂アンテナ',
                'title' => '会話を丸く収めるクッション言葉「しらんけど」の威力',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 5,
                'trade_site_id' => 0,
                'site_name' => 'カルチャーラボ',
                'title' => '話題のAIツールを使った創作活動が急速に拡大中',
                'url' => '#',
                'image_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=400&q=80',
                'has_image' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ]
        ];

        if ($requireImage) {
            return array_slice(array_filter($fallbacks, fn($x) => $x['has_image']), 0, $limit);
        }
        return array_slice($fallbacks, 0, $limit);
    }
}
