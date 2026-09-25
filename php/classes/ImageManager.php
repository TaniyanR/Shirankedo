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

        // 1. 重要キーワードに合致する専用画像 (完全一致 & 部分一致・平準化選定)
        if (!empty($keywords)) {
            // 完全一致検索
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

            // 部分一致検索 (記事キーワードに含まれる、または画像キーワードを含む)
            $likeClauses = [];
            $likeParams = [$siteId];
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if (mb_strlen($kw) >= 2) {
                    $likeClauses[] = "(ik.keyword LIKE ? OR ? LIKE CONCAT('%', ik.keyword, '%'))";
                    $likeParams[] = "%{$kw}%";
                    $likeParams[] = $kw;
                }
            }
            if (!empty($likeClauses)) {
                $sqlLike = "SELECT i.* FROM images i
                            JOIN image_keywords ik ON i.id = ik.image_id
                            WHERE i.site_id = ? AND i.is_active = 1 AND (" . implode(' OR ', $likeClauses) . ")
                            ORDER BY i.last_used_at ASC, i.use_count ASC, RAND()
                            LIMIT 1";
                $stmtLike = $db->prepare($sqlLike);
                $stmtLike->execute($likeParams);
                $imgLike = $stmtLike->fetch();
                if ($imgLike) {
                    self::recordUsage((int)$imgLike['id']);
                    return $imgLike;
                }
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

        // 5. 登録画像がない場合でも記事タイトル入りの美しいアイキャッチを完全自動生成
        $titleForBanner = !empty($keywords[0]) ? $keywords[0] : '話題の最新トレンドニュース';
        return [
            'id' => null,
            'url' => self::generateSvgBanner($titleForBanner),
            'alt_text' => htmlspecialchars($titleForBanner) . ' - しらんけどニュース',
            'source' => 'auto_generated'
        ];
    }

    /**
     * 外部画像が無くても美しい、記事タイトル入りモダンSVGアイキャッチバナーを自動生成
     */
    public static function generateSvgBanner(string $title, string $category = 'TREND'): string {
        $escapedTitle = htmlspecialchars(mb_substr($title, 0, 36));
        $escapedCategory = htmlspecialchars(mb_substr($category, 0, 15));
        
        // ランダムグラデーションの組み合わせ
        $gradients = [
            ['#0f172a', '#1e293b', '#f59e0b'],
            ['#1e1b4b', '#312e81', '#6366f1'],
            ['#18181b', '#27272a', '#10b981'],
            ['#2e1065', '#3b0764', '#ec4899'],
        ];
        $grad = $gradients[abs(crc32($title)) % count($gradients)];
        $c1 = $grad[0];
        $c2 = $grad[1];
        $accent = $grad[2];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">'
             . '<defs>'
             . '<linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">'
             . '<stop offset="0%" stop-color="' . $c1 . '"/>'
             . '<stop offset="100%" stop-color="' . $c2 . '"/>'
             . '</linearGradient>'
             . '</defs>'
             . '<rect width="1200" height="630" fill="url(#bg)"/>'
             . '<circle cx="1050" cy="150" r="300" fill="' . $accent . '" opacity="0.12"/>'
             . '<circle cx="150" cy="500" r="220" fill="' . $accent . '" opacity="0.08"/>'
             . '<rect x="80" y="80" width="160" height="38" rx="19" fill="' . $accent . '"/>'
             . '<text x="160" y="104" fill="#0f172a" font-family="sans-serif" font-size="16" font-weight="900" text-anchor="middle" letter-spacing="2">' . $escapedCategory . '</text>'
             . '<text x="80" y="280" fill="#ffffff" font-family="sans-serif" font-size="52" font-weight="900" line-height="1.3">'
             . '<tspan x="80" dy="0">' . htmlspecialchars(mb_substr($escapedTitle, 0, 18)) . '</tspan>'
             . (mb_strlen($escapedTitle) > 18 ? '<tspan x="80" dy="65">' . htmlspecialchars(mb_substr($escapedTitle, 18, 18)) . '</tspan>' : '')
             . '</text>'
             . '<line x1="80" y1="460" x2="240" y2="460" stroke="' . $accent . '" stroke-width="4" stroke-linecap="round"/>'
             . '<text x="80" y="520" fill="#94a3b8" font-family="sans-serif" font-size="24" font-weight="700">客観ファクトまとめ速報メディア</text>'
             . '<text x="80" y="555" fill="#f8fafc" font-family="sans-serif" font-size="28" font-weight="900">しらんけど</text>'
             . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * 商用フリー初期画像セット（全7大ジャンル・計64枚・厳選高品質）を一括プリセット登録
     */
    public static function seedDefaultPresets(int $siteId = 1, string $genre = 'all'): int {
        $db = Database::getConnection();
        
        $presets = [
            // === 1. IT・テクノロジー・ガジェット (10枚) ===
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '最新スマートフォン・モバイル端末・ITガジェット',
                'keywords' => ['iPhone', 'スマホ', 'Apple', 'Android', '携帯', 'スマート', 'iOS']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '最先端テクノロジー・AI・サイバー空間',
                'keywords' => ['AI', 'Google', 'テック', 'IT', '人工知能', 'ChatGPT', 'Gemini', '生成AI']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'ノートパソコン・プログラミング・Web開発',
                'keywords' => ['PC', 'パソコン', 'Web', 'アプリ', 'プログラミング', '開発', 'MacBook']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'SNSトレンド・ネット話題・急上昇ワード',
                'keywords' => ['SNS', 'Twitter', 'X', '炎上', 'トレンド', 'バズ', 'ネット', 'ポスト']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1563770660941-20978e870e26?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'サイバーセキュリティ・データ暗号化・情報セキュリティ',
                'keywords' => ['セキュリティ', '障害', 'ウイルス', 'ハッキング', '漏洩', 'バグ', 'サーバー']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1592478411213-6153e4ebc07d?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'VRゴーグル・仮想空間メタバース・次世代デバイス',
                'keywords' => ['VR', 'メタバース', 'Apple Vision', 'ゴーグル', '仮想', '3D', 'MR']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'スマートウォッチ・ウェアラブル端末・ヘルスケア',
                'keywords' => ['ウォッチ', 'Apple Watch', 'スマートウォッチ', 'ウェアラブル', '健康']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1508614589041-895b88991e3e?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '次世代ドローン・航空テクノロジー・自動操縦',
                'keywords' => ['ドローン', '空撮', '自動運転', 'ロボット', '次世代']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '科学研究所・新技術開発・イノベーション',
                'keywords' => ['研究', '科学', '実験', '開発', '特許', '発表', 'ノーベル']
            ],
            [
                'genre' => 'tech',
                'url' => 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'タブレット端末・スタイラスペン・デジタルノート',
                'keywords' => ['iPad', 'タブレット', 'ペン', 'イラスト', 'デジタル']
            ],

            // === 2. 芸能・エンタメ・音楽・テレビ (10枚) ===
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'エンタメ・お笑いステージ・バラエティ',
                'keywords' => ['千鳥', 'お笑い', 'バラエティ', '芸人', '吉本', '漫才', 'コント', 'M-1']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '音楽フェス・ライブ・アーティストコンサート',
                'keywords' => ['音楽', 'ライブ', 'フェス', '新曲', 'アーティスト', 'バンド', 'ロック', 'ツアー']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'テレビ特番・スタジオ収録・番組改編',
                'keywords' => ['テレビ', '放送', '特番', '冠番組', 'スタジオ', 'ドラマ', '朝ドラ', '大河']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '映画館・劇場スクリーン・新作公開',
                'keywords' => ['映画', '劇場', 'ロードショー', '興行', 'ハリウッド', 'シネマ', '公開']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'アイドルステージ・ペンライト・歓声',
                'keywords' => ['アイドル', '推し', '坂道', '乃木坂', 'K-POP', 'BTS', 'ファン', '卒業']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'レコーディングスタジオ・マイク・楽曲制作',
                'keywords' => ['ボーカル', '新曲', 'アルバム', '主題歌', '配信', 'MV', 'カバー']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '舞台・カーテンコール・演劇シアター',
                'keywords' => ['舞台', '俳優', '女優', 'ミュージカル', '劇団', '上演']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'ポッドキャスト・ラジオ配信・スタジオマイク',
                'keywords' => ['ラジオ', 'ポッドキャスト', '音声', '配信', 'パーソナリティ', '声優']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'クラブ・DJ・ダンスミュージック',
                'keywords' => ['DJ', 'ダンス', 'クラブ', 'EDM', 'イベント', 'ビート']
            ],
            [
                'genre' => 'entame',
                'url' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'パーティー・祝賀セレモニー・表彰式',
                'keywords' => ['結婚', '熱愛', '電撃', '発表', 'セレモニー', '受賞', 'アカデミー']
            ],

            // === 3. スポーツ・アスリート (10枚) ===
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '野球場・プロ野球スタジアム・ボール',
                'keywords' => ['大谷', '野球', 'MLB', 'プロ野球', 'ドジャース', 'ホームラン', '甲子園', 'WBC']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1579952363873-27f3bade9f55?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'サッカー・スタジアム・ゴールネット',
                'keywords' => ['サッカー', 'Jリーグ', '日本代表', 'ワールドカップ', 'プレミア', 'ゴール', '移籍']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'バスケットボール・コート・ダンクシュート',
                'keywords' => ['バスケ', 'NBA', 'Bリーグ', '八村', 'スラムダンク', 'シュート']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'テニスコート・ラケット・テニスボール',
                'keywords' => ['テニス', '錦織', '全米', 'ウィンブルドン', '四大大会', 'ラリー']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '格闘技・ボクシングリング・ファイト',
                'keywords' => ['格闘技', 'ボクシング', 'RIZIN', '井上尚弥', 'KO', 'チャンピオン', 'UFC']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'マラソン・陸上トラック・ランニング',
                'keywords' => ['マラソン', '駅伝', '陸上', '五輪', 'オリンピック', 'ランナー', '箱根駅伝']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'ゴルフ場・グリーン・フラッグピン',
                'keywords' => ['ゴルフ', 'マスターズ', 'ツアー', 'ホールインワン', '石川遼', '松山英樹']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'スキー・スノーボード・雪山ゲレンデ',
                'keywords' => ['スキー', 'スノボ', '冬季', '雪山', 'ゲレンデ', 'ジャンプ']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'モータースポーツ・F1レース・サーキット',
                'keywords' => ['F1', 'レース', 'サーキット', 'モータースポーツ', 'グランプリ', 'スーパーカー']
            ],
            [
                'genre' => 'sports',
                'url' => 'https://images.unsplash.com/photo-1530549387789-4c1017266635?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '競泳・プール・スイミングレース',
                'keywords' => ['水泳', '競泳', 'プール', 'タイム', 'メダル', '金メダル']
            ],

            // === 4. ゲーム・アニメ・マンガ・サブカル (8枚) ===
            [
                'genre' => 'game',
                'url' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '最新ゲーム・新作RPG・ゲームコントローラー',
                'keywords' => ['ゲーム', 'モンハン', 'Steam', '新作', 'PS5', 'Switch', '任天堂', 'RPG']
            ],
            [
                'genre' => 'game',
                'url' => 'https://images.unsplash.com/photo-1578632767115-351597cf2477?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'アニメ・イラスト制作・デジタルコミック',
                'keywords' => ['アニメ', '作画', '声優', 'マンガ', '漫画', 'キャラクター', '放送']
            ],
            [
                'genre' => 'game',
                'url' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'マンガ単行本・コミック棚・連載作品',
                'keywords' => ['ジャンプ', '連載', 'コミック', '完結', '単行本', '発売', '原作']
            ],
            [
                'genre' => 'game',
                'url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'eスポーツ・対戦ゲームモニター・ゲーミングチェア',
                'keywords' => ['eスポーツ', 'プロゲーマー', '大会', 'Apex', 'LoL', 'Valorant', '配信']
            ],
            [
                'genre' => 'game',
                'url' => 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'VTuber・アバター配信・バーチャルタレント',
                'keywords' => ['VTuber', 'にじさんじ', 'ホロライブ', 'アバター', 'スパチャ', '同接']
            ],
            [
                'genre' => 'game',
                'url' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'アーケード・ゲームセンター・レトロゲーム',
                'keywords' => ['ゲーセン', 'レトロゲーム', 'ファミコン', 'アーケード', '格ゲー']
            ],
            [
                'genre' => 'game',
                'url' => 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'トレーディングカードゲーム・TCG・対戦パック',
                'keywords' => ['ポケカ', '遊戯王', 'カード', 'トレカ', 'パック', '高騰', '限定']
            ],
            [
                'genre' => 'game',
                'url' => 'https://images.unsplash.com/photo-1563089145-599997674d42?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'ネオンライト・サイバーパンク・ゲーミング部屋',
                'keywords' => ['ネオン', 'グッズ', 'イベント', 'コラボ', '一番くじ', 'フィギュア']
            ],

            // === 5. グルメ・スイーツ・飲食 (10枚) ===
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '熱々ラーメン・こだわりスープ・チャーシュー麺',
                'keywords' => ['ラーメン', 'つけ麺', '家系', '二郎', '名店', '行列', '中華']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'カフェ・ラテアート・挽きたて珈琲',
                'keywords' => ['カフェ', 'コーヒー', 'スタバ', '珈琲', 'ラテ', '喫茶店', 'モーニング']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'グルメバーガー・ファストフード・ポテト',
                'keywords' => ['ハンバーガー', 'マック', 'マクドナルド', 'バーガー', 'ポテト', 'ファストフード']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '極上ステーキ・焼き肉・グリル肉料理',
                'keywords' => ['肉', '焼肉', 'ステーキ', '牛丼', '牛肉', 'カルビ', 'ディナー']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '新鮮な寿司・江戸前握り・海鮮盛り合わせ',
                'keywords' => ['寿司', 'スシロー', '回転寿司', '海鮮', 'マグロ', '和食', '刺身']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '人気グルメ・話題の新作スイーツ・フード',
                'keywords' => ['グルメ', '新作', 'フード', 'ランチ', '絶品', '食べ放題']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1587314168485-3236d6710814?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '絶品スイーツ・イチゴパフェ・ケーキ',
                'keywords' => ['スイーツ', 'ケーキ', 'パフェ', 'チョコ', 'デザート', 'アイス', 'プリン']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1514933651103-005eec06c04b?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '冷えたビール・乾杯・居酒屋おつまみ',
                'keywords' => ['ビール', '酒', '乾杯', '居酒屋', 'チューハイ', '飲み会', 'ハイボール']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '焼き立てピザ・チーズ料理・イタリアン',
                'keywords' => ['ピザ', 'チーズ', 'パスタ', 'イタリアン', 'ドミノ']
            ],
            [
                'genre' => 'gourmet',
                'url' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '焼きたてパン・ベーカリー・クロワッサン',
                'keywords' => ['パン', 'ベーカリー', 'クロワッサン', '食パン', 'サンドイッチ']
            ],

            // === 6. 社会・ニュース・事件・経済・政治 (8枚) ===
            [
                'genre' => 'society',
                'url' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '速報ニュース・最新報道・一次発表',
                'keywords' => ['ニュース', '速報', '報道', '発表', '声明', '公式']
            ],
            [
                'genre' => 'society',
                'url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'ビジネス街・経済トレンド・大手企業動向',
                'keywords' => ['企業', 'ビジネス', '経済', '株価', '決算', '買収', '倒産', '黒字']
            ],
            [
                'genre' => 'society',
                'url' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '裁判所・法の天秤・判決法廷',
                'keywords' => ['裁判', '判決', '起訴', '逮捕', '容疑', '弁護', '損害賠償']
            ],
            [
                'genre' => 'society',
                'url' => 'https://images.unsplash.com/photo-1526304640581-d334cdbbf45e?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '紙幣・日本円・物価マネートレンド',
                'keywords' => ['値上げ', '円安', '物価', '税金', '給料', 'ボーナス', '貯金', '投資']
            ],
            [
                'genre' => 'society',
                'url' => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '国会・政治・選挙演説',
                'keywords' => ['政治', '選挙', '内閣', '総理', '首相', '国会', '法案', '自民']
            ],
            [
                'genre' => 'society',
                'url' => 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '警察・パトカー・緊急出動',
                'keywords' => ['警察', '事件', '事故', 'パトカー', '捜査', '取締', '防犯']
            ],
            [
                'genre' => 'society',
                'url' => 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '医療・病院・ヘルスケアニュース',
                'keywords' => ['医療', '病院', '健康', '感染', 'インフル', '薬', 'ワクチン']
            ],
            [
                'genre' => 'society',
                'url' => 'https://images.unsplash.com/photo-1521295121783-8a321d551ad2?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '地球儀・世界情勢・国際ニュース',
                'keywords' => ['海外', '国際', '世界', 'アメリカ', '大統領', '条約', '首脳']
            ],

            // === 7. 自然・天気・季節・ペット・ライフスタイル (8枚) ===
            [
                'genre' => 'lifestyle',
                'url' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => 'かわいい猫・子猫・癒やしペット',
                'keywords' => ['猫', 'ねこ', '子猫', 'ネコ', 'ペット', 'かわいい', '癒やし', '動物']
            ],
            [
                'genre' => 'lifestyle',
                'url' => 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '元気な犬・愛犬・散歩',
                'keywords' => ['犬', 'イヌ', 'いぬ', '愛犬', 'ドッグ', 'ペット']
            ],
            [
                'genre' => 'lifestyle',
                'url' => 'https://images.unsplash.com/photo-1534274988757-a28bf1a57c17?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '大雨・台風・激しい雷雨',
                'keywords' => ['台風', '大雨', '警報', '雷', '天気', '大雪', '寒波', '低気圧']
            ],
            [
                'genre' => 'lifestyle',
                'url' => 'https://images.unsplash.com/photo-1522383225653-ed111181a951?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '満開の桜・春の訪れ・お花見',
                'keywords' => ['桜', '春', '花見', '満開', '入学', '卒業', '開花']
            ],
            [
                'genre' => 'lifestyle',
                'url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '青い海・白い砂浜・夏のバカンス',
                'keywords' => ['夏', '海', '猛暑', '猛暑日', 'プール', 'バカンス', '熱中症', '真夏']
            ],
            [
                'genre' => 'lifestyle',
                'url' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '秋の紅葉・美しい山々と自然景観',
                'keywords' => ['秋', '紅葉', '行楽', '旅行', '連休', '観光', 'ドライブ']
            ],
            [
                'genre' => 'lifestyle',
                'url' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '飛行機・空港・フライト旅行',
                'keywords' => ['旅行', '空港', '飛行機', 'ANA', 'JAL', '新幹線', '帰省', 'GW']
            ],
            [
                'genre' => 'lifestyle',
                'url' => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?auto=format&fit=crop&w=1000&h=563&q=80',
                'alt' => '自動車・ドライブ・高速道路',
                'keywords' => ['車', '自動車', 'ドライブ', '渋滞', 'EV', 'トヨタ', 'ホンダ']
            ]
        ];

        // 特定ジャンルのみ絞り込み（'all' 以外の場合）
        if ($genre !== 'all') {
            $presets = array_filter($presets, function($p) use ($genre) {
                return ($p['genre'] ?? '') === $genre;
            });
        }

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
