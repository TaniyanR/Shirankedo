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

        // 登録画像がまだない場合の安全なプレースホルダー
        return [
            'id' => null,
            'url' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&q=80',
            'alt_text' => 'しらんけどトレンドニュース',
            'source' => 'default'
        ];
    }

    private static function recordUsage(int $imageId): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE images SET use_count = use_count + 1, last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$imageId]);
    }
}
