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
          contact_email VARCHAR(255) NULL,
          partnership_type ENUM('link_only','link_rss') NOT NULL DEFAULT 'link_rss',
          application_source ENUM('admin','external') NOT NULL DEFAULT 'admin',
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
                    '<h2>しらんけどについて</h2><p><strong>「しらんけど」は、ネット上で話題になっている出来事や急上昇ワードを見つけ、できるだけ分かりやすく整理して紹介するトレンドメディアです。</strong></p><p>話題性だけを追いかけるのではなく、元情報や報道内容を確認しながら、何が起きているのかを短時間で把握できるようにまとめることを目的としています。</p><blockquote>ネットの話題は、少し距離を置いて気楽に楽しむくらいがちょうどいい。しらんけど。</blockquote><h2>当サイトの方針</h2><p>できる限り一次情報、公式発表、信頼できる報道などを確認し、事実と推測を混同しないよう心がけています。ただし、公開後に情報が更新されたり、内容に誤りが含まれる可能性もあります。重要な判断を行う際は、必ず公式情報や原典もご確認ください。</p><h2>当サイト情報</h2><p>サイト名：しらんけど<br>URL：https://shirankedo.moe/</p><h2>リンクについて</h2><p>当サイトはリンクフリーです。トップページ・記事ページ・固定ページのいずれにも自由にリンクしていただけます。事前のご連絡も必要ありません。</p><p>ただし、当サイトに掲載している画像・文章・外部サイト由来の素材等について、権利者の許可なく転載・再配布することはご遠慮ください。</p><h2>相互リンク・相互RSSについて</h2><p>当サイトでは、関連サイト・ブログ・アンテナサイト等との相互リンクや相互RSSを受け付けています。ご希望の場合は「相互リンク依頼」ページからお申し込みください。</p><h2>お問い合わせ</h2><p>掲載内容の確認、修正依頼、削除依頼、その他当サイトに関するご連絡は「お問い合わせ」ページからお願いいたします。</p><h2>サイトの使い方</h2><p>トップページでは新着記事や話題の記事を確認できます。記事ページでは、話題になっている理由、関連情報、参照元などを確認できます。気になる話題を見つけた際の入口としてご利用ください。</p>',
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
                    '<h2>プライバシーポリシー</h2><p>「しらんけど」（以下「当サイト」）では、利用者の個人情報およびアクセス情報を適切に取り扱うため、以下の方針を定めます。</p><h3>1. 個人情報の利用目的</h3><p>当サイトでは、お問い合わせや相互リンク・相互RSSの申請時に、名前、メールアドレス、サイト名、サイトURL、RSS URL等をご入力いただく場合があります。</p><p>これらの情報は、お問い合わせへの回答、申請内容の確認、提携管理、必要なご連絡のために利用し、それ以外の目的では利用しません。</p><h3>2. 個人情報の第三者への開示</h3><p>当サイトでは、取得した個人情報を適切に管理し、本人の同意がある場合、法令に基づく場合、または人の生命・身体・財産の保護のために必要な場合を除き、第三者へ開示しません。</p><h3>3. 個人情報の開示・訂正・削除等</h3><p>ご本人から、ご自身の個人情報について開示、訂正、追加、削除、利用停止等のご希望があった場合は、ご本人確認のうえ、合理的な範囲で対応します。</p><h3>4. Cookieとアクセス解析</h3><p>当サイトでは、サイトの利用状況を把握し改善するため、独自のアクセス解析機能を使用しています。アクセス解析では、PV・UU・参照元・端末種別等を計測するため、Cookieを利用します。</p><p>また、アクセス解析ログとしてIPアドレス、ブラウザ情報、参照元URL等を保存する場合があります。これらはサイト運営・不正アクセス対策・アクセス傾向の把握のために利用し、通常は特定の個人を識別する目的では使用しません。</p><p>Cookieはブラウザの設定から無効にできます。ただし、一部の計測や機能が正しく動作しなくなる場合があります。</p><h3>5. 広告について</h3><p>当サイトでは、アフィリエイト広告や外部広告を掲載する場合があります。広告をクリックして外部サイトへ移動した場合、移動先サイトでは当サイトとは異なるプライバシーポリシーやCookie等の仕組みが適用されることがあります。</p><p>広告先での商品購入・契約・個人情報の登録等については、各広告主・サービス提供者の規約およびプライバシーポリシーをご確認ください。</p><h3>6. 外部サイトへのリンク</h3><p>当サイトには外部サイトへのリンクが含まれます。リンク先で提供される情報・サービス・個人情報の取り扱いについて、当サイトは管理しておりません。</p><h3>7. 免責事項</h3><p>当サイトでは、できる限り正確な情報を掲載するよう努めていますが、内容の正確性・完全性・最新性を保証するものではありません。</p><p>当サイトの情報を利用したこと、または外部サイトへ移動したことによって生じた損害等について、当サイトは責任を負いかねます。重要な判断を行う場合は、必ず公式情報や原典をご確認ください。</p><h3>8. 著作権・権利関係について</h3><p>当サイトに掲載する文章・画像等の権利は、当サイトまたは各権利者に帰属します。権利上の問題がある掲載物については、確認のうえ必要に応じて対応しますので、お問い合わせページよりご連絡ください。</p><h3>9. プライバシーポリシーの変更</h3><p>当サイトは、法令やサービス内容の変更等に応じて、本ポリシーを適宜見直すことがあります。変更後の内容は本ページに掲載した時点から適用されます。</p>',
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
        try {
            $db->exec("ALTER TABLE trade_sites ADD COLUMN contact_email VARCHAR(255) NULL AFTER rss_url");
        } catch (Throwable $e) {}
        try {
            $db->exec("ALTER TABLE trade_sites ADD COLUMN partnership_type ENUM('link_only','link_rss') NOT NULL DEFAULT 'link_rss' AFTER contact_email");
        } catch (Throwable $e) {}
        try {
            $db->exec("ALTER TABLE trade_sites ADD COLUMN application_source ENUM('admin','external') NOT NULL DEFAULT 'admin' AFTER partnership_type");
        } catch (Throwable $e) {}
    }
}
