<?php
/**
 * 「しらんけど」自動マイグレーションスクリプト
 */
require_once __DIR__ . '/config.php';

function runMigrations() {
    $db = Database::getConnection();

    // database.sql を読み込んでテーブル作成
    $sql = file_get_contents(__DIR__ . '/database.sql');
    $db->exec($sql);

    // デフォルトサイト確認 & 作成
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM sites");
    $res = $stmt->fetch();
    if ($res['cnt'] == 0) {
        // 総合メインサイト
        $stmt = $db->prepare("INSERT INTO sites (id, subdomain, name, description, genre) VALUES 
            (1, '', 'しらんけど', 'いま日本で話題のトレンドを客観分析し、一次情報とともにお届けするサイト。しらんけど。', 'general'),
            (2, 'game', 'しらんけど ゲーム速報', 'Steam・新作ゲーム・大型アプデのトレンドまとめ。しらんけど。', 'game'),
            (3, 'entame', 'しらんけど エンタメ', 'お笑い・バラエティ・芸能カルチャーの話題。しらんけど。', 'entertainment')");
        $stmt->execute();

        // カテゴリ投入
        $catStmt = $db->prepare("INSERT INTO categories (site_id, slug, name, sort_order) VALUES
            (1, 'all', '総合', 1),
            (1, 'entertainment', 'エンタメ', 2),
            (1, 'tech', 'テクノロジー', 3),
            (1, 'game', 'ゲーム', 4),
            (1, 'social', 'SNS・ネット話題', 5),
            (2, 'steam', 'Steam/PC', 1),
            (2, 'console', 'コンシューマー', 2),
            (2, 'mobile', 'アプリ', 3)");
        $catStmt->execute();

        // 拒否キーワード初期データ投入
        $kwStmt = $db->prepare("INSERT INTO banned_keywords (site_id, keyword, match_type, reason) VALUES
            (1, '死ね', 'partial', '誹謗中傷・脅迫'),
            (1, '殺す', 'partial', '脅迫'),
            (1, 'ガイジ', 'partial', '差別用語'),
            (1, 'バカ', 'exact', '過度な中傷'),
            (1, 'ゴミカス', 'partial', '侮辱'),
            (1, '晒す', 'partial', '晒し行為'),
            (1, '電話番号', 'partial', '個人情報誘導'),
            (1, '口座', 'partial', '詐欺誘導')");
        $kwStmt->execute();

        // 画像グループ初期投入
        $grpStmt = $db->prepare("INSERT INTO image_groups (id, site_id, name, genre) VALUES
            (1, 1, '千鳥', 'entertainment'),
            (2, 1, 'ダウンタウン', 'entertainment'),
            (3, 1, 'モンスターハンター', 'game')");
        $grpStmt->execute();

        // 初期画像ライブラリ投入
        $imgStmt = $db->prepare("INSERT INTO images (id, site_id, group_id, category_id, filename, url, alt_text) VALUES
            (1, 1, 1, 2, 'chidori_001.webp', 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=800&q=80', 'お笑いステージイメージ'),
            (2, 1, 2, 2, 'downtown_001.webp', 'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=800&q=80', 'スタジオマイクイメージ'),
            (3, 1, 3, 4, 'game_hunter_001.webp', 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=800&q=80', 'ファンタジーアクションゲームイメージ'),
            (4, 1, NULL, 1, 'trend_news_default.webp', 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=800&q=80', 'ニュース速報イメージ')");
        $imgStmt->execute();

        // 画像キーワード投入
        $ikStmt = $db->prepare("INSERT INTO image_keywords (image_id, keyword) VALUES
            (1, '千鳥'), (1, '大悟'), (1, 'ノブ'), (1, 'お笑い'),
            (2, 'ダウンタウン'), (2, '松本人志'), (2, '浜田雅功'), (2, 'バラエティ'),
            (3, 'モンスターハンター'), (3, 'モンハン'), (3, 'Monster Hunter'), (3, 'ゲーム')");
        $ikStmt->execute();
    }

    echo "マイグレーション完了: データベース構造と初期データが正常にセットアップされました。\n";
}

if (php_sapi_name() === 'cli') {
    runMigrations();
}
