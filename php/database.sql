-- 「しらんけど」完全自動トレンドサイトシステム データベーススキーマ (MySQL 8.0+ / MariaDB 10.5+)
-- 文字コード: utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. サイト管理テーブル (マルチサイト対応)
CREATE TABLE IF NOT EXISTS `sites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subdomain` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'サブドメイン (空欄=メイン/総合)',
  `name` VARCHAR(128) NOT NULL COMMENT 'サイト名',
  `description` TEXT NULL COMMENT 'サイト説明',
  `genre` VARCHAR(64) NOT NULL DEFAULT 'general' COMMENT '対象ジャンル',
  `logo_url` VARCHAR(255) NULL,
  `favicon_url` VARCHAR(255) NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '公開/非公開',
  `allow_auto_publish` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '安全記事の自動公開ON/OFF',
  `youtube_thumbnail_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'YouTubeサムネイル使用ON/OFF',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_subdomain` (`subdomain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. カテゴリテーブル
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_site_slug` (`site_id`, `slug`),
  KEY `idx_site_sort` (`site_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. トレンド候補テーブル (複数元から収集・統合)
CREATE TABLE IF NOT EXISTS `trend_candidates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `normalized_keyword` VARCHAR(191) NOT NULL COMMENT '正規化キーワード (重複判定用)',
  `display_keyword` VARCHAR(255) NOT NULL,
  `sources_json` JSON NOT NULL COMMENT '検出元一覧 (google, yahoo, youtube, news, game)',
  `google_score` INT NOT NULL DEFAULT 0,
  `yahoo_score` INT NOT NULL DEFAULT 0,
  `youtube_score` INT NOT NULL DEFAULT 0,
  `news_score` INT NOT NULL DEFAULT 0,
  `game_score` INT NOT NULL DEFAULT 0,
  `shirankedo_index` INT NOT NULL DEFAULT 0 COMMENT '0-100のしらんけど指数',
  `is_rapid_rise` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '🔥急上昇中判定',
  `growth_rate` DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT '変化率 (+145%など)',
  `first_detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '初出検出日時',
  `last_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `article_id` INT UNSIGNED NULL COMMENT '生成された記事ID',
  `status` ENUM('candidate', 'verified', 'processing', 'completed', 'ignored') NOT NULL DEFAULT 'candidate',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_site_keyword` (`site_id`, `normalized_keyword`),
  KEY `idx_site_index` (`site_id`, `shirankedo_index` DESC),
  KEY `idx_site_rapid` (`site_id`, `is_rapid_rise`, `last_updated_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 記事テーブル
CREATE TABLE IF NOT EXISTS `articles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NULL,
  `trend_candidate_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `why_trending` TEXT NOT NULL COMMENT 'なぜ話題？サマリー',
  `body` LONGTEXT NOT NULL COMMENT '記事本文 (確認済み事実のみ)',
  `conclusion_sentence` VARCHAR(255) NOT NULL COMMENT '末尾文 (〜しらんけど。)',
  `shirankedo_index` INT NOT NULL DEFAULT 50 COMMENT 'しらんけど指数 (0-100)',
  `index_label` VARCHAR(32) NOT NULL DEFAULT '話題' COMMENT 'ちょい話題/話題/かなり話題/めっちゃ話題',
  `is_rapid_rise` TINYINT(1) NOT NULL DEFAULT 0,
  `growth_rate` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `first_detected_at` DATETIME NOT NULL,
  `image_url` VARCHAR(255) NULL,
  `youtube_video_id` VARCHAR(32) NULL,
  `status` ENUM('candidate', 'collecting', 'ai_pending', 'generated', 'review_pending', 'on_hold', 'scheduled', 'published', 'private', 'error') NOT NULL DEFAULT 'published',
  `is_dangerous` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '危険ジャンルフラグ (自動保留対象)',
  `danger_reason` VARCHAR(255) NULL,
  `source_count` INT NOT NULL DEFAULT 1,
  `views_count` INT NOT NULL DEFAULT 0,
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_site_slug` (`site_id`, `slug`),
  KEY `idx_site_status_published` (`site_id`, `status`, `published_at` DESC),
  KEY `idx_site_index` (`site_id`, `shirankedo_index` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 記事出典テーブル (事実確認一次ソース)
CREATE TABLE IF NOT EXISTS `article_sources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `site_id` INT UNSIGNED NOT NULL,
  `source_type` ENUM('official', 'news', 'youtube', 'other') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `url` VARCHAR(512) NOT NULL,
  `publisher` VARCHAR(128) NOT NULL,
  `reliability_score` INT NOT NULL DEFAULT 90,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article` (`article_id`),
  KEY `idx_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. 画像グループテーブル (人物・作品・ゲーム等)
CREATE TABLE IF NOT EXISTS `image_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL COMMENT 'グループ名 (例: 千鳥, ダウンタウン)',
  `genre` VARCHAR(64) NOT NULL DEFAULT 'general',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. 画像ライブラリ (10,000枚規模対応)
CREATE TABLE IF NOT EXISTS `images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NULL,
  `category_id` INT UNSIGNED NULL,
  `filename` VARCHAR(255) NOT NULL,
  `url` VARCHAR(512) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `use_count` INT NOT NULL DEFAULT 0 COMMENT '使用回数 (平準化用)',
  `last_used_at` DATETIME NULL COMMENT '最終使用日時 (連続使用防止用)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_active_usage` (`site_id`, `is_active`, `use_count`, `last_used_at`),
  KEY `idx_group` (`group_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. 画像キーワードマッピングテーブル
CREATE TABLE IF NOT EXISTS `image_keywords` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_id` INT UNSIGNED NOT NULL,
  `keyword` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_keyword` (`keyword`),
  KEY `idx_image` (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. ユーザー投票テーブル (知ってた/知らんかった, もっと伸びる/もう終わる)
CREATE TABLE IF NOT EXISTS `user_votes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `article_id` INT UNSIGNED NOT NULL,
  `poll_type` ENUM('knew_ratio', 'future_growth') NOT NULL,
  `vote_value` VARCHAR(32) NOT NULL COMMENT 'knew/didnt_know or grow/end',
  `ip_hash` CHAR(64) NOT NULL COMMENT 'SHA256ハッシュ化IP',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_vote_dedup` (`article_id`, `poll_type`, `ip_hash`),
  KEY `idx_article_poll` (`article_id`, `poll_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. ユーザーコメントテーブル (文字限定・URL禁止・拒否語自動遮断)
CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `article_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL COMMENT 'プレーンテキストのみ',
  `ip_hash` CHAR(64) NOT NULL,
  `status` ENUM('approved', 'pending', 'hidden', 'deleted') NOT NULL DEFAULT 'approved',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article_status` (`article_id`, `status`, `created_at` DESC),
  KEY `idx_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. 拒否キーワード管理テーブル
CREATE TABLE IF NOT EXISTS `banned_keywords` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `keyword` VARCHAR(128) NOT NULL,
  `match_type` ENUM('exact', 'partial', 'regex') NOT NULL DEFAULT 'partial',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `reason` VARCHAR(128) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_active` (`site_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. SNS投稿キューテーブル (X, Pinterest, Instagram)
CREATE TABLE IF NOT EXISTS `sns_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `article_id` INT UNSIGNED NOT NULL,
  `sns_type` ENUM('x', 'pinterest', 'instagram') NOT NULL,
  `post_content` TEXT NOT NULL,
  `image_url` VARCHAR(512) NULL,
  `scheduled_at` DATETIME NOT NULL,
  `posted_at` DATETIME NULL,
  `status` ENUM('queued', 'processing', 'success', 'failed') NOT NULL DEFAULT 'queued',
  `external_post_id` VARCHAR(128) NULL,
  `error_message` TEXT NULL,
  `retry_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue_process` (`status`, `scheduled_at`),
  KEY `idx_article` (`article_id`),
  KEY `idx_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. サイト設定テーブル (Key-Value)
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` LONGTEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_site_key` (`site_id`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. システムログテーブル
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NULL,
  `category` ENUM('trend_fetch', 'ai_gen', 'article_publish', 'safety_brake', 'sns_post', 'image_select', 'api_error', 'admin_action') NOT NULL,
  `message` TEXT NOT NULL,
  `details_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_date` (`category`, `created_at` DESC),
  KEY `idx_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. お問い合わせテーブル
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL DEFAULT '',
  `message` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. マイグレーション履歴
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_name` VARCHAR(128) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
