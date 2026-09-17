<?php
/**
 * 相互リンク・相互RSS、アクセストレード（逆アクセス返還エンジン）用データベース追加マイグレーション
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

        // 3. お知らせテーブル（相互リンク承認・解除・メンテ等の通知用）
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

        // 4. アフィリエイト広告枠 & カスタムタグ設定テーブル (site_settings を活用、初期値挿入)
        $defaultSettings = [
            'admin_secret_path' => 'manage-sk89q', // 推測不可能な管理画面パス
            'head_custom_tags' => '<meta name="referrer" content="unsafe-url">' . "\n", // 最初からunsafe-url挿入
            'body_top_tags' => "<!-- Google Tag Manager または body直下タグ -->\n",
            'ad_pc_header' => '<div class="w-[468px] h-[60px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold">広告 (PCヘッダー: 468x60)</div>',
            'ad_pc_sidebar_top' => '<div class="w-[300px] h-[250px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold mx-auto">広告 (PCサイド上: 300x250)</div>',
            'ad_pc_sidebar_bottom' => '<div class="w-[300px] h-[250px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold mx-auto">広告 (PCサイド下: 300x250)</div>',
            'ad_sp_header_top' => '<div class="w-[300px] h-[250px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold mx-auto">広告 (スマホヘッダー上: 300x250)</div>',
            'ad_sp_header_bottom' => '<div class="w-[300px] h-[250px] bg-stone-100 border border-dashed border-stone-300 flex items-center justify-center text-xs text-stone-400 font-bold mx-auto">広告 (スマホヘッダー下: 300x250)</div>',
            'sns_auto_post_x' => '1',
            'sns_auto_post_insta' => '1',
            'sns_auto_post_pinterest' => '1',
        ];

        foreach ($defaultSettings as $key => $val) {
            $stmt = $db->prepare("INSERT IGNORE INTO site_settings (site_id, setting_key, setting_value) VALUES (1, ?, ?)");
            $stmt->execute([$key, $val]);
        }
    }
}
