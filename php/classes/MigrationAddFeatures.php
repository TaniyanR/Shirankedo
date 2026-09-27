<?php
/**
 * 相互リンク・画像・アクセス解析などの追加テーブルを安全に展開するマイグレーション。
 * ダミーの提携サイトやアクセス数は作成しない。
 */
require_once __DIR__ . '/Database.php';

class MigrationAddFeatures {
    public static function run(): void {
        $db = Database::getConnection();

        $db->exec("CREATE TABLE IF NOT EXISTS trade_sites (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          site_id INT UNSIGNED NOT NULL DEFAULT 1,
          site_name VARCHAR(191) NOT NULL,
          url VARCHAR(512) NOT NULL,
          rss_url TEXT NOT NULL,
          status ENUM('pending','approved','rejected','deleted') NOT NULL DEFAULT 'pending',
          return_rate INT NOT NULL DEFAULT 100,
          is_boosted TINYINT(1) NOT NULL DEFAULT 0,
          boost_weight INT NOT NULL DEFAULT 1,
          in_count INT UNSIGNED NOT NULL DEFAULT 0,
          out_count INT UNSIGNED NOT NULL DEFAULT 0,
          today_in INT UNSIGNED NOT NULL DEFAULT 0,
          today_out INT UNSIGNED NOT NULL DEFAULT 0,
          last_in_at DATETIME NULL,
          last_rss_fetched_at DATETIME NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_status_rate (status, return_rate),
          KEY idx_in_count (in_count)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS trade_feed_items (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          trade_site_id INT UNSIGNED NOT NULL,
          title VARCHAR(255) NOT NULL,
          url VARCHAR(512) NOT NULL,
          image_url VARCHAR(512) NULL,
          has_image TINYINT(1) NOT NULL DEFAULT 0,
          published_at DATETIME NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY idx_trade_item_url (trade_site_id, url(191)),
          KEY idx_trade_pub (published_at),
          KEY idx_has_image (has_image)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS announcements (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          site_id INT UNSIGNED NOT NULL DEFAULT 1,
          title VARCHAR(255) NOT NULL,
          body TEXT NOT NULL,
          type ENUM('trade_approved','trade_removed','general') NOT NULL DEFAULT 'general',
          trade_site_id INT UNSIGNED NULL,
          is_public TINYINT(1) NOT NULL DEFAULT 1,
          published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_public_date (is_public, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS access_logs (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          site_id INT UNSIGNED NOT NULL DEFAULT 1,
          page_type VARCHAR(50) NOT NULL DEFAULT 'home',
          article_id INT UNSIGNED NULL,
          visitor_hash VARCHAR(64) NOT NULL,
          session_hash VARCHAR(64) NULL,
          request_path VARCHAR(255) NULL,
          ip_address VARCHAR(45) NOT NULL,
          referer_host VARCHAR(255) NOT NULL DEFAULT 'direct',
          full_referer VARCHAR(500) NULL,
          user_agent VARCHAR(255) NULL,
          device_type ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
          tracking_version TINYINT UNSIGNED NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_created (created_at),
          KEY idx_article (article_id),
          KEY idx_referer (referer_host),
          KEY idx_device (device_type),
          KEY idx_tracking_created (tracking_version, created_at),
          KEY idx_visitor_created (visitor_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 既存access_logsをv3計測へ安全に拡張。
        try { $db->exec("ALTER TABLE access_logs ADD COLUMN session_hash VARCHAR(64) NULL AFTER visitor_hash"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE access_logs ADD COLUMN request_path VARCHAR(255) NULL AFTER session_hash"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE access_logs ADD COLUMN tracking_version TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER device_type"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE access_logs ADD KEY idx_tracking_created (tracking_version, created_at)"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE access_logs ADD KEY idx_visitor_created (visitor_hash, created_at)"); } catch (Throwable $e) {}

        // ロゴ・favicon・OGP画像はDB保存。
        $db->exec("CREATE TABLE IF NOT EXISTS site_assets (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          site_id INT UNSIGNED NOT NULL DEFAULT 1,
          asset_type ENUM('logo','favicon','ogp') NOT NULL,
          filename VARCHAR(255) NOT NULL,
          mime_type VARCHAR(100) NOT NULL,
          data MEDIUMBLOB NOT NULL,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY idx_site_asset (site_id, asset_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 固定ページ管理
        $db->exec("CREATE TABLE IF NOT EXISTS static_pages (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          site_id INT UNSIGNED NOT NULL DEFAULT 1,
          title VARCHAR(191) NOT NULL,
          slug VARCHAR(191) NOT NULL,
          body_html MEDIUMTEXT NOT NULL,
          special_type ENUM('content','trade','news','contact') NOT NULL DEFAULT 'content',
          status ENUM('published','draft') NOT NULL DEFAULT 'published',
          sort_order INT NOT NULL DEFAULT 0,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY idx_site_slug (site_id, slug),
          KEY idx_status_sort (site_id, status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try { $db->exec("ALTER TABLE static_pages ADD COLUMN special_type ENUM('content','trade','news','contact') NOT NULL DEFAULT 'content' AFTER body_html"); } catch (Throwable $e) {}

        // 初回だけ既存5ページをDBへ移行。以後、削除したページを自動復活させない。
        $seededStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE site_id = 1 AND setting_key = 'static_pages_seeded' LIMIT 1");
        $seededStmt->execute();
        $staticPagesSeeded = (string)($seededStmt->fetchColumn() ?: '');

        if ($staticPagesSeeded !== '1') {
            $initialPages = [
                [
                    'サイトについて',
                    'about',
                    '<p><strong>「しらんけど」は、SNSや検索エンジンで今まさに急上昇しているトレンド話題を自動収集し、一次情報（公式発表・大手報道機関）を確認した上で要約してお届けするメディアです。</strong></p><p>ネット上の噂やセンセーショナルな言説に惑わされず、客観的な事実（ファクト）だけを抽出。最後に関西特有のクッション表現「しらんけど。」を添えることで、適度な距離感とユーモアを持ってトレンドを楽しめる場を提供しています。</p><blockquote>ネットの情報は真に受けすぎず、気楽に楽しむのが一番です。しらんけど。</blockquote>',
                    'content',
                    10
                ],
                [
                    '相互リンク依頼',
                    'trade',
                    '<p>当サイトでは、アンテナサイト・まとめサイト・ブログ運営者様との相互リンクおよび相互RSSを広く募集しております。</p><p>下の申請フォームから、サイト名・URL・RSSをご入力ください。</p>',
                    'trade',
                    20
                ],
                [
                    'お知らせ',
                    'news',
                    '<p>当サイトからのお知らせを掲載しています。</p>',
                    'news',
                    30
                ],
                [
                    'プライバシーポリシー',
                    'privacy-policy',
                    '<h3>1. 個人情報の収集・利用目的</h3><p>当サイトでは、お問い合わせや相互リンク申請の際に、サイト名・URL・メールアドレス等の個人情報をご登録いただく場合があります。これらの個人情報は、ご質問への回答や提携管理のためにのみ利用し、目的外の利用は行いません。</p><h3>2. アクセス解析とCookie</h3><p>当サイトでは、アクセス状況の把握のためにCookie等を利用する場合があります。収集した情報はサイト運営・改善のために利用します。</p><h3>3. 免責事項</h3><p>当サイトの掲載内容は正確性に配慮していますが、その完全性・安全性を保証するものではありません。情報の利用によって生じた損害について責任を負いかねます。</p>',
                    'content',
                    40
                ],
                [
                    'お問い合わせ',
                    'que',
                    '<p>当サイトへのご連絡は、下のお問い合わせフォームからお願いいたします。</p>',
                    'contact',
                    50
                ],
            ];

            $pageIns = $db->prepare("INSERT IGNORE INTO static_pages
                (site_id, title, slug, body_html, special_type, status, sort_order)
                VALUES (1, ?, ?, ?, ?, 'published', ?)");
            foreach ($initialPages as $page) {
                $pageIns->execute($page);
            }

            $mark = $db->prepare("INSERT INTO site_settings (site_id, setting_key, setting_value)
                                  VALUES (1, 'static_pages_seeded', '1')
                                  ON DUPLICATE KEY UPDATE setting_value = '1'");
            $mark->execute();
        }

        // 画像管理の初期フォルダ。
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

        // 運用に必要な一般設定のみ初期化。認証情報はInstaller側で管理する。
        $defaults = [
            'head_custom_tags' => '<meta name="referrer" content="unsafe-url">' . "\n",
            'body_top_tags' => '',
            'show_ads' => '1',
            'show_rss' => '1',
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-2.5-flash',
            'auto_post_enabled' => '1',
            'auto_post_interval_hours' => '3',
            'ad_pc_header_enabled' => '1',
            'ad_pc_sidebar_top_enabled' => '1',
            'ad_pc_sidebar_bottom_enabled' => '1',
            'ad_sp_header_top_enabled' => '1',
            'ad_sp_header_bottom_enabled' => '1',
            'ad_article_middle_enabled' => '1',
            'ad_article_bottom_enabled' => '1',
            'ad_pc_header' => '',
            'ad_pc_sidebar_top' => '',
            'ad_pc_sidebar_bottom' => '',
            'ad_sp_header_top' => '',
            'ad_sp_header_bottom' => '',
            'ad_article_middle' => '',
            'ad_article_bottom' => '',
        ];
        $stmt = $db->prepare("INSERT IGNORE INTO site_settings (site_id, setting_key, setting_value) VALUES (1, ?, ?)");
        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        try {
            $db->exec("ALTER TABLE trade_sites MODIFY COLUMN rss_url TEXT NOT NULL");
        } catch (Throwable $e) {}
    }
}
