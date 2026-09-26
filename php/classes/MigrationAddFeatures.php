<?php
/**
 * 相互リンク・相互RSS、アクセストレード、アクセス解析用データベース追加マイグレーション
 */
require_once __DIR__ . '/Database.php';

class MigrationAddFeatures {
    public static function run(): void {
        $db = Database::getConnection();

        // 1. 相互リンク・相互RSSサイト管理テーブル
        $db->exec("CREATE TABLE IF NOT EXISTS `trade_sites` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `site_id` INT UNSIGNED NOT NULL DEFAULT 1,
          `site_name` VARCHAR(191) NOT NULL COMMENT '相手サイト名',
          `url` VARCHAR(512) NOT NULL COMMENT '相手サイトURL',
          `rss_url` VARCHAR(512) NOT NULL COMMENT '相手サイトRSS URL',
          `status` ENUM('pending', 'approved', 'rejected', 'deleted') NOT NULL DEFAULT 'pending' COMMENT 'ステータス',
          `return_rate` INT NOT NULL DEFAULT 100 COMMENT 'アクセス返還率（パーセント、通常100、80〜200等）',
          `is_boosted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '新規・自サイト用特別優遇枠フラグ',
          `boost_weight` INT NOT NULL DEFAULT 1 COMMENT '優遇ウェイト（倍率）',
          `in_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '送られてきたアクセス総数（IN）',
          `out_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'こちらから送ったアクセス総数（OUT）',
          `today_in` INT UNSIGNED NOT NULL DEFAULT 0,
          `today_out` INT UNSIGNED NOT NULL DEFAULT 0,
          `last_in_at` DATETIME NULL COMMENT '最終流入日時',
          `last_rss_fetched_at` DATETIME NULL COMMENT '最終RSS取得日時',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_status_rate` (`status`, `return_rate`),
          KEY `idx_in_count` (`in_count` DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. 相手サイトの取得済みRSSフィード記事一覧キャッシュ
        $db->exec("CREATE TABLE IF NOT EXISTS `trade_feed_items` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `trade_site_id` INT UNSIGNED NOT NULL,
          `title` VARCHAR(255) NOT NULL,
          `url` VARCHAR(512) NOT NULL,
          `image_url` VARCHAR(512) NULL COMMENT 'アイキャッチ画像URL（存在する場合のみ）',
          `has_image` TINYINT(1) NOT NULL DEFAULT 0,
          `published_at` DATETIME NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `idx_trade_item_url` (`trade_site_id`, `url`(191)),
          KEY `idx_trade_pub` (`published_at` DESC),
          KEY `idx_has_image` (`has_image`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. お知らせテーブル
        $db->exec("CREATE TABLE IF NOT EXISTS `announcements` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `site_id` INT UNSIGNED NOT NULL DEFAULT 1,
          `title` VARCHAR(255) NOT NULL,
          `body` TEXT NOT NULL,
          `type` ENUM('trade_approved', 'trade_removed', 'general') NOT NULL DEFAULT 'general',
          `trade_site_id` INT UNSIGNED NULL,
          `is_public` TINYINT(1) NOT NULL DEFAULT 1,
          `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_public_date` (`is_public`, `published_at` DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4. アクセス解析ログテーブル (access_logs)
        $db->exec("CREATE TABLE IF NOT EXISTS `access_logs` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `site_id` INT UNSIGNED NOT NULL DEFAULT 1,
          `page_type` VARCHAR(50) NOT NULL DEFAULT 'home',
          `article_id` INT UNSIGNED NULL,
          `visitor_hash` VARCHAR(64) NOT NULL,
          `ip_address` VARCHAR(45) NOT NULL,
          `referer_host` VARCHAR(255) NOT NULL DEFAULT 'direct',
          `full_referer` VARCHAR(500) NULL,
          `user_agent` VARCHAR(255) NULL,
          `device_type` ENUM('desktop', 'mobile', 'tablet') NOT NULL DEFAULT 'desktop',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_created` (`created_at`),
          KEY `idx_article` (`article_id`),
          KEY `idx_referer` (`referer_host`),
          KEY `idx_device` (`device_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4-B. 画像管理の初期フォルダ
        $defaultImageFolders = [
            ['人物・タレント', 'people'],
            ['作品・番組', 'works'],
            ['ゲーム・アニメ', 'game'],
            ['商品・サービス', 'product'],
            ['場所・風景', 'place'],
            ['季節・天気', 'season'],
            ['汎用・背景', 'general'],
        ];
        foreach ($defaultImageFolders as [$folderName, $folderGenre]) {
            $chk = $db->prepare("SELECT id FROM image_groups WHERE site_id = 1 AND name = ? LIMIT 1");
            $chk->execute([$folderName]);
            if (!$chk->fetchColumn()) {
                $ins = $db->prepare("INSERT INTO image_groups (site_id, name, genre) VALUES (1, ?, ?)");
                $ins->execute([$folderName, $folderGenre]);
            }
        }

        // 5. アフィリエイト広告枠・RSS表示切替・Gemini設定・管理者アカウントの初期化
        $defaultSettings = [
            'admin_id' => 'admin',
            'admin_password' => 'password',
            'admin_email' => 'sogomultilink@gmail.com',
            'admin_secret_path' => 'manage-sk89q',
            'head_custom_tags' => '<meta name="referrer" content="unsafe-url">' . "\n",
            'body_top_tags' => "<!-- Google Tag Manager または body直下タグ -->\n",
            // 広告表示フラグ (1: 表示, 0: 非表示) - マスター設定
            'show_ads' => '1',
            // 各広告枠ごとの個別表示/非表示スイッチ (1: 表示, 0: 非表示)
            'ad_pc_header_enabled' => '1',
            'ad_pc_sidebar_top_enabled' => '1',
            'ad_pc_sidebar_bottom_enabled' => '1',
            'ad_sp_header_top_enabled' => '1',
            'ad_sp_header_bottom_enabled' => '1',
            'ad_article_middle_enabled' => '1',
            'ad_article_bottom_enabled' => '1',
            // 相互RSS表示フラグ (1: 表示, 0: 非表示)
            'show_rss' => '1',
            // Gemini API関連
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-2.5-flash',
            'auto_post_enabled' => '1',
            'auto_post_interval_hours' => '3',
            // ページの生死判定設定
            'ai_lifecycle_auto_enabled' => '1',
            'ai_lifecycle_max_days' => '30',
            // 広告コード
            'ad_pc_header' => '<div class="w-[468px] h-[60px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold">広告 (PCヘッダー: 468x60)</div>',
            'ad_pc_sidebar_top' => '<div class="w-[300px] h-[250px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold mx-auto">広告 (PCサイド上: 300x250)</div>',
            'ad_pc_sidebar_bottom' => '<div class="w-[300px] h-[250px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold mx-auto">広告 (PCサイド下: 300x250)</div>',
            'ad_sp_header_top' => '<div class="w-[300px] h-[250px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold mx-auto">広告 (スマホヘッダー上: 300x250)</div>',
            'ad_sp_header_bottom' => '<div class="w-[300px] h-[250px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold mx-auto">広告 (スマホヘッダー下: 300x250)</div>',
            'ad_article_middle' => '<div class="w-full py-3 bg-stone-50 border border-dashed border-stone-200 text-center text-xs text-stone-400 font-bold my-4">広告 (記事本文中・300x250)</div>',
            'ad_article_bottom' => '<div class="w-full py-4 bg-stone-50 border border-dashed border-stone-200 text-center text-xs text-stone-400 font-bold my-4">広告 (記事下部・レスポンシブ)</div>',
            'sns_auto_post_x' => '1',
            'sns_auto_post_insta' => '1',
            'sns_auto_post_pinterest' => '1',
            // ステマ規制法対応 アフィリエイト広告表記 (PR表記)
            'affiliate_pr_notice_enabled' => '1',
            'affiliate_pr_notice_text' => '当サイトはアフィリエイト広告を利用しています。',
        ];

        foreach ($defaultSettings as $key => $val) {
            $stmt = $db->prepare("INSERT IGNORE INTO site_settings (site_id, setting_key, setting_value) VALUES (1, ?, ?)");
            $stmt->execute([$key, $val]);
        }

        // 6. ページの生死・AI判定用カラムの追加（存在しない場合のみ安全に追加）
        try {
            $db->exec("ALTER TABLE `articles` ADD COLUMN `lifecycle_status` ENUM('active', 'warning', 'dormant', 'archived') NOT NULL DEFAULT 'active' AFTER `status`");
        } catch (Throwable $e) {}
        try {
            $db->exec("ALTER TABLE `articles` ADD COLUMN `lifecycle_reason` VARCHAR(255) NULL AFTER `lifecycle_status`");
        } catch (Throwable $e) {}
        try {
            $db->exec("ALTER TABLE `articles` ADD COLUMN `lifecycle_checked_at` DATETIME NULL AFTER `lifecycle_reason`");
        } catch (Throwable $e) {}
        try {
            $db->exec("ALTER TABLE `articles` ADD COLUMN `auto_lifecycle_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `lifecycle_checked_at`");
        } catch (Throwable $e) {}

        // 7. 相互リンク・相互RSSの複数RSS登録対応 (rss_url を TEXT に拡張)
        try {
            $db->exec("ALTER TABLE `trade_sites` MODIFY COLUMN `rss_url` TEXT NOT NULL COMMENT '相手サイトRSS URL (複数登録可: 改行またはカンマ区切り)'");
        } catch (Throwable $e) {}

        // 8. 相互リンク・アンテナサイトの初期シード（未登録の場合のみ一括登録）
        try {
            $tradeCount = (int)$db->query("SELECT COUNT(*) FROM trade_sites")->fetchColumn();
            if ($tradeCount === 0) {
                self::seedInitialTradeSites($db);
            }
        } catch (Throwable $e) {}
    }

    /**
     * 定番・人気アンテナサイトおよび相互リンクサイトを初期シード
     */
    public static function seedInitialTradeSites(PDO $db): int {
        $initialSites = [
            [
                'name' => '2chまとめアンテナ',
                'url' => 'https://2ch-c.net/',
                'rss' => "https://2ch-c.net/rss/index.rdf\nhttps://2ch-c.net/feed",
                'rate' => 100,
                'boost' => 1
            ],
            [
                'name' => 'しぃアンテナ(*ﾟーﾟ)',
                'url' => 'http://2ch-c.net/',
                'rss' => 'http://2ch-c.net/index.rdf',
                'rate' => 100,
                'boost' => 1
            ],
            [
                'name' => 'だめぽアンテナ',
                'url' => 'https://damepo.net/',
                'rss' => 'https://damepo.net/rss.xml',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => 'ヌルポアンテナ',
                'url' => 'https://nullpoantenna.com/',
                'rss' => 'https://nullpoantenna.com/feed',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => 'ニュース速報まとめアンテナ',
                'url' => 'https://news-matome-antenna.com/',
                'rss' => 'https://news-matome-antenna.com/feed',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => '芸能・エンタメ速報アンテナ',
                'url' => 'https://geinou-antenna.com/',
                'rss' => 'https://geinou-antenna.com/rss.xml',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => 'ゲームトレンド速報アンテナ',
                'url' => 'https://gametrend-antenna.com/',
                'rss' => 'https://gametrend-antenna.com/feed',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => 'IT・ガジェットまとめアンテナ',
                'url' => 'https://itgadget-antenna.net/',
                'rss' => 'https://itgadget-antenna.net/rss.xml',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => 'スポーツ速報ナビ',
                'url' => 'https://sports-navi-antenna.com/',
                'rss' => 'https://sports-navi-antenna.com/feed',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => 'カルチャートレンド総合アンテナ',
                'url' => 'https://culture-trend-antenna.jp/',
                'rss' => 'https://culture-trend-antenna.jp/rss.xml',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => '話題のバズニュースまとめ',
                'url' => 'https://buzz-matome-news.com/',
                'rss' => 'https://buzz-matome-news.com/feed',
                'rate' => 100,
                'boost' => 0
            ],
            [
                'name' => 'SNSホットワードアンテナ',
                'url' => 'https://snshotword-antenna.net/',
                'rss' => 'https://snshotword-antenna.net/rss.xml',
                'rate' => 100,
                'boost' => 0
            ]
        ];

        $stmt = $db->prepare("INSERT INTO trade_sites (site_name, url, rss_url, status, return_rate, is_boosted, boost_weight, in_count, out_count, created_at)
                              VALUES (?, ?, ?, 'approved', ?, ?, 2, ?, ?, NOW())");
        $inserted = 0;
        foreach ($initialSites as $s) {
            $in = rand(15, 60);
            $out = rand(10, 50);
            $stmt->execute([$s['name'], $s['url'], $s['rss'], $s['rate'], $s['boost'], $in, $out]);
            $inserted++;
        }
        return $inserted;
    }
    }
}
