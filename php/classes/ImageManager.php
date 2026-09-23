<?php
/**
 * 画像選択マネージャー (10,000枚規模対応)
 * 優先順位: 1.専用画像 ＞ 2.グループ画像 ＞ 3.カテゴリ汎用 ＞ 4.サイト共通
 * 連続使用防止 & 平準化ロジック
 */
class ImageManager {
    /**
     * 記事キーワードとカテゴリから最適な画像を1枚選定
     */
    public static function selectBestImage(int $siteId, array $keywords, ?int $categoryId = null, ?string $youtubeThumbnail = null, bool $useYoutubeThumb = true): ?array {
        $db = Database::getConnection();

        // YouTubeサムネイルが有効かつ存在する場合
        if ($useYoutubeThumb && !empty($youtubeThumbnail)) {
            return [
                'id' => null,
                'url' => $youtubeThumbnail,
                'alt_text' => 'YouTube公式サムネイル',
                'source' => 'youtube'
            ];
        }

        // 1. 重要キーワードに合致する専用画像 (キーワード完全一致・部分一致)
        if (!empty($keywords)) {
            $placeholders = implode(',', array_fill(0, count($keywords), '?'));
            $sql = "SELECT i.* FROM images i
                    JOIN image_keywords ik ON i.id = ik.image_id
                    WHERE i.site_id = ? AND i.is_active = 1 AND ik.keyword IN ($placeholders)
                    ORDER BY i.last_used_at ASC, i.use_count ASC, RAND()
                    LIMIT 1";
            $params = array_merge([$siteId], $keywords);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $img = $stmt->fetch();
            if ($img) {
                self::recordUsage((int)$img['id']);
                return $img;
            }

            // 2. 画像グループ名に合致するグループ画像 (例: 千鳥, ダウンタウン)
            $sqlGroup = "SELECT i.* FROM images i
                         JOIN image_groups ig ON i.group_id = ig.id
                         WHERE i.site_id = ? AND i.is_active = 1 AND ig.name IN ($placeholders)
                         ORDER BY i.last_used_at ASC, i.use_count ASC, RAND()
                         LIMIT 1";
            $stmtGroup = $db->prepare($sqlGroup);
            $stmtGroup->execute($params);
            $imgGroup = $stmtGroup->fetch();
            if ($imgGroup) {
                self::recordUsage((int)$imgGroup['id']);
                return $imgGroup;
            }
        }

        // 3. カテゴリ汎用画像
        if ($categoryId) {
            $stmtCat = $db->prepare("SELECT * FROM images 
                                    WHERE site_id = ? AND category_id = ? AND is_active = 1
                                    ORDER BY last_used_at ASC, use_count ASC, RAND()
                                    LIMIT 1");
            $stmtCat->execute([$siteId, $categoryId]);
            $imgCat = $stmtCat->fetch();
            if ($imgCat) {
                self::recordUsage((int)$imgCat['id']);
                return $imgCat;
            }
        }

        // 4. サイト共通画像 (フォールバック)
        $stmtCommon = $db->prepare("SELECT * FROM images 
                                   WHERE site_id = ? AND is_active = 1
                                   ORDER BY last_used_at ASC, use_count ASC, RAND()
                                   LIMIT 1");
        $stmtCommon->execute([$siteId]);
        $imgCommon = $stmtCommon->fetch();
        if ($imgCommon) {
            self::recordUsage((int)$imgCommon['id']);
            return $imgCommon;
        }

        // プールに画像がない場合の挙動設定 (hold: 保留・非公開 / default_image: 予備のニュース画像を設定)
        $emptyBehavior = SettingsManager::get('pool_empty_behavior', 'default_image');
        if ($emptyBehavior === 'hold') {
            // プールに画像がない場合はnullを返し、記事を「画像未設定（非公開・保留）」にする
            return null;
        }

        // 登録画像がまだない場合の安全なプレースホルダー (初期フォールバック画像)
        return [
            'id' => null,
            'url' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&q=80',
            'alt_text' => 'しらんけどトレンドニュース',
            'source' => 'default'
        ];
    }

    /**
     * 商用フリー初期画像セット（12ジャンル・厳選高品質）を一括プリセット登録
     */
    public static function seedDefaultPresets(int $siteId = 1): int {
        $db = Database::getConnection();
        
        $presets = [
            [
                'url' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1000&q=80',
                'alt' => '最新スマートフォン・モバイル端末・ITガジェット',
                'keywords' => ['iPhone', 'スマホ', 'Apple', 'Android']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?auto=format&fit=crop&w=1000&q=80',
                'alt' => 'エンタメ・お笑いステージ・バラエティ',
                'keywords' => ['千鳥', 'お笑い', 'バラエティ', '芸人']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=1000&q=80',
                'alt' => '最新ゲーム・新作RPG・ゲームコントローラー',
                'keywords' => ['ゲーム', 'モンハン', 'Steam', '新作']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&q=80',
                'alt' => '速報ニュース・最新報道・一次発表',
                'keywords' => ['ニュース', '速報', '報道', '発表']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=1000&q=80',
                'alt' => 'スタジアム・スポーツ速報・アスリート',
                'keywords' => ['大谷', '野球', 'スポーツ', 'サッカー']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?auto=format&fit=crop&w=1000&q=80',
                'alt' => 'SNSトレンド・ネット話題・急上昇ワード',
                'keywords' => ['SNS', 'Twitter', 'X', '炎上']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1000&q=80',
                'alt' => '最先端テクノロジー・AI・サイバー',
                'keywords' => ['AI', 'Google', 'テック', 'IT']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1000&q=80',
                'alt' => '音楽フェス・ライブ・アーティストコンサート',
                'keywords' => ['音楽', 'ライブ', 'フェス', '新曲']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?auto=format&fit=crop&w=1000&q=80',
                'alt' => 'テレビ特番・スタジオ収録・番組改編',
                'keywords' => ['テレビ', '放送', '特番', '冠番組']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=1000&q=80',
                'alt' => '映画館・劇場スクリーン・アニメ新作',
                'keywords' => ['映画', 'アニメ', '劇場', 'マンガ']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1000&q=80',
                'alt' => '人気グルメ・話題の新作スイーツ・フード',
                'keywords' => ['グルメ', '新作', 'フード', 'カフェ']
            ],
            [
                'url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1000&q=80',
                'alt' => 'ビジネス街・経済トレンド・企業動向',
                'keywords' => ['企業', 'ビジネス', '経済', '株価']
            ]
        ];

        $insertedCount = 0;
        foreach ($presets as $p) {
            // URL重複チェック
            $chk = $db->prepare("SELECT id FROM images WHERE site_id = ? AND url = ?");
            $chk->execute([$siteId, $p['url']]);
            if ($chk->fetch()) {
                continue;
            }

            $stmt = $db->prepare("INSERT INTO images (site_id, category_id, filename, url, alt_text, is_active, created_at) VALUES (?, 1, 'preset.webp', ?, ?, 1, NOW())");
            $stmt->execute([$siteId, $p['url'], $p['alt']]);
            $imgId = (int)$db->lastInsertId();

            if (!empty($p['keywords'])) {
                $kwStmt = $db->prepare("INSERT INTO image_keywords (image_id, keyword) VALUES (?, ?)");
                foreach ($p['keywords'] as $kw) {
                    $kwStmt->execute([$imgId, $kw]);
                }
            }
            $insertedCount++;
        }

        return $insertedCount;
    }

    private static function recordUsage(int $imageId): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE images SET use_count = use_count + 1, last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$imageId]);
    }
}
