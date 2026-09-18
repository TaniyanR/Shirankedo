<?php
/**
 * 新しい推測不可能な管理画面 (admin-manage-sk89q.php)
 * WordPress風のカラフル＆直感的UI、スマートフォン完全対応
 * 
 * 機能タブ:
 * 1. ダッシュボード & 記事管理
 * 2. 記事の新規手動作成 & トレンド収集実行
 * 3. 🖼️ アイキャッチ画像プール管理 (800x450px・キーワード3つ設定・最大3万枚対応)
 * 4. 🔗 相互リンク・相互RSS管理 (承認・非承認・返還率80%/100%/120%/150%・特別優遇ブースト)
 * 5. 📢 お知らせ管理 (相互リンク承認・解除通知)
 * 6. 💰 アフィリエイト広告スロット設定 (PCヘッダー/サイド上下、スマホヘッダー上下)
 * 7. 🏷️ SEO・カスタムタグ設定 (<meta name="referrer" content="unsafe-url">, <head>タグ, <body>直下タグ)
 * 8. 🔒 セキュリティ設定 (管理画面URLスラッグ変更・管理者パスワード変更)
 */
session_start();
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/TradeEngine.php';

// 初期セットアップテーブルの存在保証
try {
    MigrationAddFeatures::run();
} catch (Throwable $e) {}

// 管理者認証設定（初期値: ID「admin」, パスワード「password」, 登録メールアドレス）
$adminId = SettingsManager::get('admin_id', 'admin');
$adminPass = SettingsManager::get('admin_password', 'password');
$adminEmail = SettingsManager::get('admin_email', 'sogomultilink@gmail.com');
$currentSecretPath = SettingsManager::get('admin_secret_path', 'manage-sk89q');
$thisFileUrl = 'admin-' . $currentSecretPath . '.php';

// URL・ホスト情報
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = "{$protocol}://{$host}";

// 認証・再設定メッセージ
$loginError = '';
$loginSuccessMsg = '';
$forgotError = '';
$forgotSuccessMsg = '';
$previewResetUrl = '';
$resetFormError = '';

$authMode = $_GET['auth_mode'] ?? 'login'; // 'login' | 'forgot' | 'reset'
$resetToken = $_GET['token'] ?? $_POST['token'] ?? '';

// 1. パスワード再設定リクエスト処理（登録メールアドレスへURL送信）
if (isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
    $authMode = 'forgot';
    $inputTarget = trim($_POST['reset_target'] ?? '');

    if (!empty($inputTarget) && ($inputTarget === $adminId || strcasecmp($inputTarget, $adminEmail) === 0)) {
        $token = bin2hex(random_bytes(24));
        $expiry = time() + 3600; // 1時間有効
        SettingsManager::set('admin_reset_token', $token);
        SettingsManager::set('admin_reset_expires', (string)$expiry);

        $resetUrl = "{$baseUrl}/{$thisFileUrl}?auth_mode=reset&token={$token}";
        $previewResetUrl = $resetUrl;

        // メール送信処理
        $subject = "【しらんけど】管理者パスワード再設定のご案内";
        $body = "しらんけど 管理システムです。\n\n"
              . "管理者アカウントのパスワード再設定リクエストを受け付けました。\n"
              . "以下のURLにアクセスして、1時間以内に新しいパスワードを設定してください。\n\n"
              . "▼ パスワード再設定URL:\n"
              . "{$resetUrl}\n\n"
              . "※このURLの有効期限は発行から1時間（" . date('Y/m/d H:i', $expiry) . "まで）です。\n"
              . "※お心当たりがない場合は本メールを破棄してください。パスワードは変更されません。\n";

        $headers = "From: no-reply@" . ($host ?: 'shirankedo.bichi.xyz') . "\r\n"
                 . "Reply-To: no-reply@" . ($host ?: 'shirankedo.bichi.xyz') . "\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "X-Mailer: Shirankedo-Mail/1.0\r\n";

        @mail($adminEmail, $subject, $body, $headers);

        $parts = explode('@', $adminEmail);
        $maskedEmail = (strlen($parts[0]) > 2)
            ? substr($parts[0], 0, 2) . str_repeat('*', max(1, strlen($parts[0]) - 2)) . '@' . ($parts[1] ?? '')
            : $adminEmail;

        $forgotSuccessMsg = "登録メールアドレス（{$maskedEmail}）宛にパスワード再設定用リンクを送信しました。メール内のリンクを開いて新しいパスワードを設定してください。";
    } else {
        $forgotError = '指定された管理者IDまたはメールアドレスが見つかりませんでした。';
    }
}

// 2. 新しいパスワードの設定処理
$savedToken = SettingsManager::get('admin_reset_token');
$savedExpires = (int)SettingsManager::get('admin_reset_expires', '0');
$isTokenValid = !empty($resetToken) && !empty($savedToken) && hash_equals($savedToken, $resetToken) && (time() <= $savedExpires);

if ($authMode === 'reset' && !$isTokenValid && empty($_POST['action'])) {
    $resetFormError = 'パスワード再設定リンクの有効期限が切れているか、無効なURLです。お手数ですが再度申請してください。';
}

if (isset($_POST['action']) && $_POST['action'] === 'do_reset') {
    $authMode = 'reset';
    if ($isTokenValid) {
        $newPass = trim($_POST['new_password'] ?? '');
        $newPassConfirm = trim($_POST['new_password_confirm'] ?? '');
        if (strlen($newPass) < 6) {
            $resetFormError = '新しいパスワードは6文字以上で入力してください。';
        } elseif ($newPass !== $newPassConfirm) {
            $resetFormError = 'パスワード（確認用）が一致しません。';
        } else {
            SettingsManager::set('admin_password', $newPass);
            SettingsManager::set('admin_reset_token', '');
            SettingsManager::set('admin_reset_expires', '0');
            $adminPass = $newPass;
            $authMode = 'login';
            $loginSuccessMsg = 'パスワードを正常に再設定しました！新しいパスワードでログインしてください。';
        }
    } else {
        $resetFormError = '再設定リンクの有効期限が切れています。もう一度再設定を申請してください。';
    }
}

// 3. ログイン処理（IDとパスワードの照合）
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $inputUser = trim($_POST['username'] ?? '');
    $inputPass = $_POST['password'] ?? '';
    if ($inputUser === $adminId && $inputPass === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $adminId;
        $_SESSION['admin_login_time'] = time();
        header("Location: {$thisFileUrl}");
        exit;
    } else {
        $loginError = 'IDまたはパスワードが正しくありません。（初期値: admin / password）';
    }
}

// ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    session_destroy();
    header("Location: {$thisFileUrl}");
    exit;
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);

// DB接続
$db = null;
try {
    $db = Database::getConnection();
} catch (Throwable $e) {}

// --- 管理者POST操作 ---
$flashMessage = '';
$flashType = 'success'; // success | error

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? 'dashboard';
    $op = $_POST['op'] ?? '';

    try {
        // 1. 記事作成
        if ($op === 'create_article') {
            $title = trim($_POST['title'] ?? '');
            $why = trim($_POST['why_trending'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $conclusion = trim($_POST['conclusion_sentence'] ?? '知らんけど。');
            $index = (int)($_POST['shirankedo_index'] ?? 75);
            $label = $index >= 80 ? 'めっちゃ話題' : ($index >= 50 ? '話題' : 'ちょい話題');
            $catId = (int)($_POST['category_id'] ?? 1);
            $slug = 'trend-' . time();
            $imgUrl = trim($_POST['image_url'] ?? '');

            $stmt = $db->prepare("INSERT INTO articles (site_id, category_id, title, slug, why_trending, body, conclusion_sentence, shirankedo_index, index_label, image_url, status, published_at)
                                  VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())");
            $stmt->execute([$catId, $title, $slug, $why, $body, $conclusion, $index, $label, $imgUrl]);
            $flashMessage = '新着記事を正常に作成・公開しました！';
        }

        // 2. 記事ステータス変更（非公開・保留・公開）
        if ($op === 'toggle_article_status') {
            $artId = (int)($_POST['article_id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? 'private';
            $stmt = $db->prepare("UPDATE articles SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $artId]);
            $flashMessage = "記事ID #{$artId} のステータスを「{$newStatus}」に更新しました。";
        }

        // 2-2. AIによるページの生死判定の一括実行
        if ($op === 'evaluate_lifecycle') {
            require_once __DIR__ . '/classes/AiLifecycleEngine.php';
            $res = AiLifecycleEngine::evaluateAll();
            $flashMessage = "⚡ AIによるページの生死判定を実行しました。（全{$res['total']}件中、生存: {$res['active']}件 / 鮮度注意: {$res['warning']}件 / 休眠・非公開: {$res['dormant']}件）";
        }

        // 2-3. 個別記事のAI自動管理フラグ切替
        if ($op === 'toggle_auto_lifecycle') {
            $artId = (int)($_POST['article_id'] ?? 0);
            $enabled = (int)($_POST['auto_lifecycle_enabled'] ?? 1);
            $stmt = $db->prepare("UPDATE articles SET auto_lifecycle_enabled = ? WHERE id = ?");
            $stmt->execute([$enabled, $artId]);
            $flashMessage = "記事ID #{$artId} のAI自動判定対象を更新しました。";
        }

        // 3. アイキャッチ画像プールの追加（URLまたはローカルPCファイルアップロード・800x450px・キーワード3つ）
        if ($op === 'add_pool_image') {
            $url = trim($_POST['url'] ?? '');
            $alt = trim($_POST['alt_text'] ?? 'トレンドアイキャッチ');
            $catId = (int)($_POST['category_id'] ?? 1);
            $kw1 = trim($_POST['kw1'] ?? '');
            $kw2 = trim($_POST['kw2'] ?? '');
            $kw3 = trim($_POST['kw3'] ?? '');

            // ローカルファイルアップロード対応
            if (isset($_FILES['local_image']) && $_FILES['local_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['local_image']['tmp_name'];
                $fileName = $_FILES['local_image']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $safeName = 'eyecatch_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($fileTmp, $dest)) {
                        $url = '/uploads/' . $safeName;
                    }
                }
            }

            if (empty($url)) {
                throw new Exception('画像URLを入力するか、画像をアップロードしてください。');
            }

            $stmt = $db->prepare("INSERT INTO images (site_id, category_id, filename, url, alt_text, is_active) VALUES (1, ?, 'custom_pool.webp', ?, ?, 1)");
            $stmt->execute([$catId, $url, $alt]);
            $newImgId = $db->lastInsertId();

            $kws = array_filter([$kw1, $kw2, $kw3]);
            if (!empty($kws)) {
                $kwStmt = $db->prepare("INSERT INTO image_keywords (image_id, keyword) VALUES (?, ?)");
                foreach ($kws as $k) {
                    $kwStmt->execute([$newImgId, $k]);
                }
            }
            $flashMessage = 'アイキャッチ画像をプールに登録しました！（キーワード3件設定済み）';
        }

        // 4-A. 相互リンク・相互RSSの新規個別登録 (複数RSSフィード対応)
        if ($op === 'add_trade_site') {
            $siteName = trim($_POST['site_name'] ?? '');
            $siteUrl = trim($_POST['url'] ?? '');
            $rawRss = trim($_POST['rss_url'] ?? '');
            $status = $_POST['status'] ?? 'approved';
            $rate = (int)($_POST['return_rate'] ?? 100);
            $isBoosted = !empty($_POST['is_boosted']) ? 1 : 0;
            $boostWeight = (int)($_POST['boost_weight'] ?? 1);
            $fetchNow = !empty($_POST['fetch_now']);

            $rssUrls = TradeEngine::extractRssUrls($rawRss);

            if (empty($siteName) || empty($siteUrl)) {
                $errorMessage = "サイト名とサイトURLは必須項目です。";
            } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errorMessage = "正しいサイトURL（https://〜）を入力してください。";
            } elseif (empty($rssUrls)) {
                $errorMessage = "RSSフィードURLを最低1件以上正しく入力してください（複数ある場合は改行してください）。";
            } else {
                $savedRss = implode("\n", $rssUrls);
                $stmt = $db->prepare("INSERT INTO trade_sites (site_name, url, rss_url, status, return_rate, is_boosted, boost_weight, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$siteName, $siteUrl, $savedRss, $status, $rate, $isBoosted, $boostWeight]);
                $newId = (int)$db->lastInsertId();

                if ($status === 'approved') {
                    $annTitle = "【相互リンク】「" . $siteName . "」様と相互リンク・相互RSSを開始しました";
                    $annBody = "「" . $siteName . "」様（" . $siteUrl . "）と相互リンクおよび相互RSSの提携を開始いたしました。今後ともよろしくお願い申し上げます。";
                    $db->prepare("INSERT INTO announcements (title, body, type, trade_site_id, is_public) VALUES (?, ?, 'trade_approved', ?, 1)")
                       ->execute([$annTitle, $annBody, $newId]);
                }

                $feedCount = count($rssUrls);
                $fetchMsg = "";
                if ($fetchNow) {
                    $stats = TradeEngine::fetchRssFeeds($newId);
                    $fetchMsg = " 直ちに全{$feedCount}件のRSSを巡回し、新着記事{$stats['items_saved']}件を取得しました！";
                }
                $flashMessage = "相互リンク・RSS提携サイト「{$siteName}」（RSS {$feedCount}件登録）を登録しました！{$fetchMsg}";
            }
        }

        // 4-B. 相互リンク・相互RSSの一括バルク登録 (複数RSSフィード対応)
        if ($op === 'bulk_add_trade_sites') {
            $bulkText = trim($_POST['bulk_data'] ?? '');
            $lines = preg_split('/[\r\n]+/', $bulkText);
            $addedCount = 0;
            $totalFeeds = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) continue;
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) >= 2) {
                    $bName = $parts[0];
                    $bUrl = $parts[1];
                    $bRawRss = $parts[2] ?? '';
                    $bRssUrls = TradeEngine::extractRssUrls($bRawRss);
                    if (empty($bRssUrls)) {
                        continue;
                    }
                    if (filter_var($bUrl, FILTER_VALIDATE_URL)) {
                        $savedRss = implode("\n", $bRssUrls);
                        $stmt = $db->prepare("INSERT INTO trade_sites (site_name, url, rss_url, status, return_rate, created_at) VALUES (?, ?, ?, 'approved', 100, NOW())");
                        $stmt->execute([$bName, $bUrl, $savedRss]);
                        $addedCount++;
                        $totalFeeds += count($bRssUrls);
                    }
                }
            }
            if ($addedCount > 0) {
                if (!empty($_POST['fetch_now_bulk'])) {
                    $stats = TradeEngine::fetchRssFeeds();
                    $flashMessage = "提携サイト{$addedCount}件（合計RSS {$totalFeeds}フィード）を一括登録し、新着記事{$stats['items_saved']}件を取得・同期しました！";
                } else {
                    $flashMessage = "提携サイト{$addedCount}件（合計RSS {$totalFeeds}フィード）を一括登録しました！";
                }
            } else {
                $errorMessage = "有効な提携サイトデータが見つかりませんでした。「サイト名 | サイトURL | RSS URL1, RSS URL2」の形式で入力してください。";
            }
        }

        // 4-C. 相互リンク・相互RSSの設定更新 (複数RSSフィード対応)
        if ($op === 'update_trade_site') {
            $tradeId = (int)($_POST['trade_id'] ?? 0);
            $siteName = trim($_POST['site_name'] ?? '');
            $siteUrl = trim($_POST['url'] ?? '');
            $rawRss = trim($_POST['rss_url'] ?? '');
            $status = $_POST['status'] ?? 'pending';
            $rate = (int)($_POST['return_rate'] ?? 100);
            $isBoosted = !empty($_POST['is_boosted']) ? 1 : 0;
            $boostWeight = (int)($_POST['boost_weight'] ?? 1);

            $rssUrls = TradeEngine::extractRssUrls($rawRss);
            $savedRss = !empty($rssUrls) ? implode("\n", $rssUrls) : $rawRss;

            // 以前のステータスを取得してお知らせ連動
            $prev = $db->prepare("SELECT site_name, url, status FROM trade_sites WHERE id = ?");
            $prev->execute([$tradeId]);
            $oldSite = $prev->fetch();

            if (!empty($siteName) && !empty($siteUrl)) {
                $stmt = $db->prepare("UPDATE trade_sites SET site_name = ?, url = ?, rss_url = ?, status = ?, return_rate = ?, is_boosted = ?, boost_weight = ? WHERE id = ?");
                $stmt->execute([$siteName, $siteUrl, $savedRss, $status, $rate, $isBoosted, $boostWeight, $tradeId]);
            } else {
                $stmt = $db->prepare("UPDATE trade_sites SET rss_url = ?, status = ?, return_rate = ?, is_boosted = ?, boost_weight = ? WHERE id = ?");
                $stmt->execute([$savedRss, $status, $rate, $isBoosted, $boostWeight, $tradeId]);
            }

            // 承認時にお知らせ自動投稿
            if ($oldSite && $oldSite['status'] !== 'approved' && $status === 'approved') {
                $targetName = !empty($siteName) ? $siteName : $oldSite['site_name'];
                $targetUrl = !empty($siteUrl) ? $siteUrl : $oldSite['url'];
                $annTitle = "【相互リンク】「" . $targetName . "」様と相互リンク・相互RSSを開始しました";
                $annBody = "「" . $targetName . "」様（" . $targetUrl . "）と相互リンクおよび相互RSSの提携を開始いたしました。今後ともよろしくお願い申し上げます。";
                $db->prepare("INSERT INTO announcements (title, body, type, trade_site_id, is_public) VALUES (?, ?, 'trade_approved', ?, 1)")
                   ->execute([$annTitle, $annBody, $tradeId]);
            }
            // 削除・解除時にお知らせ自動投稿
            if ($oldSite && $oldSite['status'] === 'approved' && ($status === 'deleted' || $status === 'rejected')) {
                $annTitle = "【相互リンク】「" . $oldSite['site_name'] . "」様との相互リンクを終了いたしました";
                $annBody = "「" . $oldSite['site_name'] . "」様との相互リンク・相互RSSの掲載を終了いたしました。これまでありがとうございました。";
                $db->prepare("INSERT INTO announcements (title, body, type, trade_site_id, is_public) VALUES (?, ?, 'trade_removed', ?, 1)")
                   ->execute([$annTitle, $annBody, $tradeId]);
            }

            $feedCount = count($rssUrls);
            $flashMessage = "相互リンク・RSSサイトの設定を更新しました（登録RSS: {$feedCount}件）。";
        }

        // 4-D. 相互リンク・RSSサイトの完全削除
        if ($op === 'delete_trade_site') {
            $tradeId = (int)($_POST['trade_id'] ?? 0);
            $db->prepare("DELETE FROM trade_feed_items WHERE trade_site_id = ?")->execute([$tradeId]);
            $db->prepare("DELETE FROM trade_sites WHERE id = ?")->execute([$tradeId]);
            $flashMessage = "提携サイトおよび関連RSS記事キャッシュを削除しました。";
        }

        // 4-E. 登録RSSの今すぐ巡回・取得
        if ($op === 'fetch_trade_rss') {
            $targetSiteId = !empty($_POST['trade_id']) ? (int)$_POST['trade_id'] : null;
            $stats = TradeEngine::fetchRssFeeds($targetSiteId);
            $errMsg = !empty($stats['errors']) ? ' (※一部エラー: ' . implode(' / ', array_slice($stats['errors'], 0, 2)) . ')' : '';
            $flashMessage = "RSS巡回完了: 提携{$stats['sites_checked']}サイト、合計{$stats['feeds_checked']}フィードを巡回し、最新記事{$stats['items_saved']}件を同期・更新しました！{$errMsg}";
        }

        // 5. アフィリエイト広告スロット & 個別表示/非表示設定の更新
        if ($op === 'save_ads') {
            SettingsManager::set('show_ads', isset($_POST['show_ads']) ? '1' : '0');
            // 個別広告枠ごとの表示/非表示設定
            SettingsManager::set('ad_pc_header_enabled', isset($_POST['ad_pc_header_enabled']) ? '1' : '0');
            SettingsManager::set('ad_pc_sidebar_top_enabled', isset($_POST['ad_pc_sidebar_top_enabled']) ? '1' : '0');
            SettingsManager::set('ad_pc_sidebar_bottom_enabled', isset($_POST['ad_pc_sidebar_bottom_enabled']) ? '1' : '0');
            SettingsManager::set('ad_sp_header_top_enabled', isset($_POST['ad_sp_header_top_enabled']) ? '1' : '0');
            SettingsManager::set('ad_sp_header_bottom_enabled', isset($_POST['ad_sp_header_bottom_enabled']) ? '1' : '0');
            SettingsManager::set('ad_article_middle_enabled', isset($_POST['ad_article_middle_enabled']) ? '1' : '0');
            SettingsManager::set('ad_article_bottom_enabled', isset($_POST['ad_article_bottom_enabled']) ? '1' : '0');

            // 広告コード
            SettingsManager::set('ad_pc_header', $_POST['ad_pc_header'] ?? '');
            SettingsManager::set('ad_pc_sidebar_top', $_POST['ad_pc_sidebar_top'] ?? '');
            SettingsManager::set('ad_pc_sidebar_bottom', $_POST['ad_pc_sidebar_bottom'] ?? '');
            SettingsManager::set('ad_sp_header_top', $_POST['ad_sp_header_top'] ?? '');
            SettingsManager::set('ad_sp_header_bottom', $_POST['ad_sp_header_bottom'] ?? '');
            SettingsManager::set('ad_article_middle', $_POST['ad_article_middle'] ?? '');
            SettingsManager::set('ad_article_bottom', $_POST['ad_article_bottom'] ?? '');
            $flashMessage = 'アフィリエイト広告スロット・個別表示/非表示設定を保存しました。';
        }

        // 5-2. 相互RSS表示/非表示設定の更新
        if ($op === 'save_rss_settings') {
            SettingsManager::set('show_rss', isset($_POST['show_rss']) ? '1' : '0');
            $flashMessage = '相互RSS表示設定を保存しました。';
        }

        // 5-3. Gemini API設定の更新
        if ($op === 'save_gemini') {
            SettingsManager::set('gemini_api_key', trim($_POST['gemini_api_key'] ?? ''));
            SettingsManager::set('gemini_model', trim($_POST['gemini_model'] ?? 'gemini-2.5-flash'));
            $flashMessage = 'Gemini AI API設定を保存しました。';
        }

        // 6. SEO・カスタムタグの更新 (<meta name="referrer" content="unsafe-url"> 等)
        if ($op === 'save_tags') {
            SettingsManager::set('head_custom_tags', $_POST['head_custom_tags'] ?? '');
            SettingsManager::set('body_top_tags', $_POST['body_top_tags'] ?? '');
            $flashMessage = 'SEOメタタグ・body直下カスタムタグを保存しました。';
        }

        // 7. セキュリティ・アカウント設定（ID変更・パスワード変更・登録メール変更・URLスラッグ変更）
        if ($op === 'save_security') {
            $newAdminId = trim($_POST['admin_id'] ?? '');
            $newAdminPass = trim($_POST['admin_password'] ?? '');
            $newAdminPassConfirm = trim($_POST['admin_password_confirm'] ?? '');
            $newAdminEmail = trim($_POST['admin_email'] ?? '');
            $newSecret = trim($_POST['admin_secret_path'] ?? '');

            if (!empty($newAdminId)) {
                if (strlen($newAdminId) >= 3 && preg_match('/^[a-zA-Z0-9_-]+$/', $newAdminId)) {
                    SettingsManager::set('admin_id', $newAdminId);
                    $adminId = $newAdminId;
                    $_SESSION['admin_username'] = $newAdminId;
                } else {
                    throw new Exception('管理者IDは3文字以上の半角英数字（ハイフン・アンダースコア可）で指定してください。');
                }
            }

            if (!empty($newAdminEmail)) {
                if (filter_var($newAdminEmail, FILTER_VALIDATE_EMAIL)) {
                    SettingsManager::set('admin_email', $newAdminEmail);
                    $adminEmail = $newAdminEmail;
                } else {
                    throw new Exception('有効なパスワード再設定用メールアドレスを入力してください。');
                }
            }

            if (!empty($newAdminPass)) {
                if (strlen($newAdminPass) < 6) {
                    throw new Exception('新しいパスワードは6文字以上で設定してください。');
                }
                if ($newAdminPassConfirm !== '' && $newAdminPass !== $newAdminPassConfirm) {
                    throw new Exception('パスワード（確認用）が一致しません。もう一度ご確認ください。');
                }
                SettingsManager::set('admin_password', $newAdminPass);
                $adminPass = $newAdminPass;
            }

            if (!empty($newSecret)) {
                // 英数字ハイフンのみ許可
                $sanitizedSecret = preg_replace('/[^a-zA-Z0-9_-]/', '', $newSecret);
                if (strlen($sanitizedSecret) >= 6) {
                    SettingsManager::set('admin_secret_path', $sanitizedSecret);
                    $currentSecretPath = $sanitizedSecret;
                    $thisFileUrl = 'admin-' . $sanitizedSecret . '.php';
                } else {
                    throw new Exception('シークレットURLスラッグは6文字以上の半角英数字で指定してください。');
                }
            }

            $flashMessage = "管理者アカウント（ID・パスワード・メールアドレス）およびセキュリティ設定を更新しました。現在の管理画面URLは 「/{$thisFileUrl}」 です。";
        }

        // 8. トレンド自動収集ワーカー実行
        if ($op === 'run_worker') {
            if (file_exists(__DIR__ . '/cron/worker.php')) {
                ob_start();
                include __DIR__ . '/cron/worker.php';
                $out = ob_get_clean();
                $flashMessage = '自動収集ワーカーを実行しました！<br><pre class="text-xs mt-2 p-2 bg-stone-900 text-stone-200 rounded max-h-40 overflow-y-auto">' . htmlspecialchars(mb_substr($out, 0, 500)) . '...</pre>';
            } else {
                $flashMessage = 'cron/worker.php が見つかりませんでした。';
            }
        }

    } catch (Throwable $e) {
        $flashMessage = 'エラー: ' . $e->getMessage();
        $flashType = 'error';
    }
}

// 各種データ取得
$currentTab = $_GET['tab'] ?? 'dashboard';

$articles = [];
$totalArticles = 0;
$totalIn = 0;
$totalOut = 0;
$tradeSites = [];
$poolImages = [];
$announcements = [];
$categories = [];
$feedItemCount = 0;
$totalFeedUrlsCount = 0;

if ($db && $isLoggedIn) {
    try {
        $totalArticles = (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
        $articles = $db->query("SELECT a.id, a.title, a.slug, a.shirankedo_index, a.index_label, a.status, a.published_at, a.image_url, a.lifecycle_status, a.lifecycle_reason, a.auto_lifecycle_enabled, c.name as category_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id ORDER BY a.id DESC LIMIT 100")->fetchAll();
        $categories = $db->query("SELECT id, name FROM categories WHERE site_id = 1")->fetchAll();
        
        $countActive = 0;
        $countWarning = 0;
        $countDormant = 0;
        $countOnHold = 0;
        foreach ($articles as $art) {
            $st = $art['status'] ?? 'published';
            $ls = $art['lifecycle_status'] ?? 'active';
            if ($st === 'on_hold') $countOnHold++;
            elseif ($ls === 'dormant' || $st === 'private') $countDormant++;
            elseif ($ls === 'warning') $countWarning++;
            else $countActive++;
        }

        // 相互リンク・アクセストレード集計
        $tradeSites = $db->query("SELECT * FROM trade_sites ORDER BY id DESC")->fetchAll();
        $inSum = $db->query("SELECT SUM(in_count) as total_in, SUM(out_count) as total_out FROM trade_sites")->fetch();
        $totalIn = (int)($inSum['total_in'] ?? 0);
        $totalOut = (int)($inSum['total_out'] ?? 0);

        $feedItemCount = (int)($db->query("SELECT COUNT(*) FROM trade_feed_items")->fetchColumn() ?: 0);
        $totalFeedUrlsCount = 0;
        foreach ($tradeSites as $ts) {
            $totalFeedUrlsCount += count(TradeEngine::extractRssUrls($ts['rss_url'] ?? ''));
        }

        // アイキャッチ画像プール
        $poolImages = $db->query("SELECT i.*, 
                                  (SELECT GROUP_CONCAT(keyword SEPARATOR ', ') FROM image_keywords WHERE image_id = i.id) as keywords
                                  FROM images i ORDER BY i.id DESC LIMIT 30")->fetchAll();

        // お知らせ一覧
        $announcements = $db->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 20")->fetchAll();

        // アクセス解析データ取得
        $analyticsStats = AnalyticsTracker::getStats(14);

    } catch (Throwable $e) {}
}

// 整理されたタブ定義 (順序変更・グループ分け)
$navTabs = [
    // 【メイン運用】
    'dashboard' => ['icon' => '📊', 'label' => 'ダッシュボード', 'badge' => null, 'group' => 'メイン運用'],
    'articles' => ['icon' => '📝', 'label' => '記事一覧・生死判定', 'badge' => $totalArticles, 'group' => 'メイン運用'],
    'gemini' => ['icon' => '✨', 'label' => 'Gemini AI自動生成', 'badge' => null, 'group' => 'メイン運用'],
    'images' => ['icon' => '🖼️', 'label' => 'アイキャッチプール', 'badge' => count($poolImages), 'group' => 'メイン運用'],

    // 【収益・集客連携】
    'ads' => ['icon' => '💰', 'label' => '広告・アフィリエイト設定', 'badge' => null, 'group' => '収益・集客'],
    'trade' => ['icon' => '🔗', 'label' => '相互リンク・相互RSS', 'badge' => count($tradeSites), 'group' => '収益・集客'],
    'analytics' => ['icon' => '📈', 'label' => 'アクセス解析', 'badge' => null, 'group' => '収益・集客'],

    // 【運用・システム】
    'seo_tags' => ['icon' => '🏷️', 'label' => 'SEO・タグ設定', 'badge' => null, 'group' => '運用・設定'],
    'announcements' => ['icon' => '📢', 'label' => 'お知らせ管理', 'badge' => count($announcements), 'group' => '運用・設定'],
    'security' => ['icon' => '🔒', 'label' => 'セキュリティ・環境', 'badge' => null, 'group' => '運用・設定'],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - しらんけど WordPress風コンソール</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Shippori+Mincho+B1:wght@600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
        .font-mincho { font-family: 'Shippori Mincho B1', serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen antialiased flex flex-col">

    <?php if (!$isLoggedIn): ?>
        <!-- 認証コンテナ（ログイン・パスワード忘れ・再設定） -->
        <div class="flex-1 flex items-center justify-center p-4">
            <div class="bg-white max-w-md w-full rounded-3xl border border-slate-200 p-8 shadow-xl space-y-6">
                <div class="text-center space-y-2">
                    <div class="w-14 h-14 rounded-2xl bg-amber-500 text-slate-950 font-black text-2xl flex items-center justify-center shadow-lg mx-auto rotate-[-3deg]">
                        知
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">しらんけど 管理コンソール</h1>
                    <p class="text-xs text-slate-500">
                        <?= $authMode === 'forgot' ? 'パスワード再設定の申請' : ($authMode === 'reset' ? '新しいパスワードの設定' : '管理者IDとパスワードを入力してログイン') ?>
                    </p>
                </div>

                <!-- 成功メッセージ通知 -->
                <?php if ($loginSuccessMsg): ?>
                    <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold text-center">
                        <?= htmlspecialchars($loginSuccessMsg) ?>
                    </div>
                <?php endif; ?>

                <!-- エラーメッセージ通知 -->
                <?php if ($loginError): ?>
                    <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold text-center">
                        <?= htmlspecialchars($loginError) ?>
                    </div>
                <?php endif; ?>

                <?php if ($authMode === 'forgot'): ?>
                    <!-- パスワード忘れ・再設定申請フォーム -->
                    <?php if ($forgotSuccessMsg): ?>
                        <div class="space-y-4">
                            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-medium leading-relaxed space-y-2">
                                <div class="font-bold flex items-center gap-1 text-emerald-800">
                                    <span>✉️</span> 送信完了
                                </div>
                                <p><?= htmlspecialchars($forgotSuccessMsg) ?></p>
                            </div>

                            <?php if ($previewResetUrl): ?>
                                <div class="p-3 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1">
                                    <div class="font-bold">🧪 開発・テスト用ショートカット:</div>
                                    <a href="<?= htmlspecialchars($previewResetUrl) ?>" class="text-amber-700 font-mono font-bold underline break-all text-[11px] block">
                                        パスワード再設定画面を開く →
                                    </a>
                                </div>
                            <?php endif; ?>

                            <a href="?auth_mode=login" class="block w-full py-3 text-center rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                                ← ログイン画面へ戻る
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($forgotError): ?>
                            <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold text-center">
                                <?= htmlspecialchars($forgotError) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="forgot_password">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700">登録管理者ID または 登録メールアドレス</label>
                                <input type="text" name="reset_target" required autofocus placeholder="例: admin または sogomultilink@gmail.com" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm">
                                <p class="text-[11px] text-slate-400">※ ご登録のメールアドレス宛に再設定URL（1時間有効）を送信します。</p>
                            </div>

                            <button type="submit" class="w-full py-3.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-sm shadow-md transition-all">
                                再設定メールを送信する →
                            </button>

                            <div class="pt-2 text-center">
                                <a href="?auth_mode=login" class="text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors">
                                    ← ログイン画面に戻る
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>

                <?php elseif ($authMode === 'reset'): ?>
                    <!-- パスワード再設定実行フォーム -->
                    <?php if ($resetFormError): ?>
                        <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold text-center">
                            <?= htmlspecialchars($resetFormError) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isTokenValid): ?>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="do_reset">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($resetToken) ?>">

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700">新しいパスワード (6文字以上)</label>
                                <input type="password" name="new_password" required minlength="6" autofocus placeholder="新しいパスワードを入力" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm font-mono">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold text-slate-700">新しいパスワード（確認用）</label>
                                <input type="password" name="new_password_confirm" required minlength="6" placeholder="もう一度入力してください" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm font-mono">
                            </div>

                            <button type="submit" class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-md transition-all">
                                新しいパスワードを保存する →
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4 space-y-4">
                            <a href="?auth_mode=forgot" class="inline-block px-5 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition-colors">
                                再設定メールを再度申請する
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="pt-2 text-center">
                        <a href="?auth_mode=login" class="text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors">
                            ← ログイン画面に戻る
                        </a>
                    </div>

                <?php else: ?>
                    <!-- 通常ログインフォーム (ID & パスワード) -->
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="login">

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-700">管理者ID</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>" required autofocus placeholder="管理者IDを入力（初期値: admin）" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm font-mono">
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-700">管理者パスワード</label>
                                <a href="?auth_mode=forgot" class="text-[11px] font-bold text-amber-600 hover:text-amber-700 transition-colors">
                                    パスワードをお忘れですか？
                                </a>
                            </div>
                            <input type="password" name="password" required placeholder="管理者パスワード（初期値: password）" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm font-mono">
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 text-[11px] text-slate-500 space-y-0.5">
                            <div class="font-bold text-slate-700">💡 初期管理者アカウント</div>
                            <div>ID: <code class="font-bold text-slate-900 bg-white px-1.5 py-0.5 rounded border border-slate-200">admin</code> / パスワード: <code class="font-bold text-slate-900 bg-white px-1.5 py-0.5 rounded border border-slate-200">password</code></div>
                        </div>

                        <button type="submit" class="w-full py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-bold text-sm shadow-md transition-all">
                            ログインする →
                        </button>
                    </form>

                    <div class="pt-2 flex items-center justify-between text-[11px] text-slate-400">
                        <a href="/" class="hover:underline">← トップページへ戻る</a>
                        <a href="?auth_mode=forgot" class="text-amber-600 hover:underline">パスワード再設定</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- ログイン中: WordPress風管理ダッシュボード -->

        <!-- トップヘッダーバー (WP Admin Bar風) -->
        <header class="bg-slate-900 text-white px-4 sm:px-6 py-2.5 flex items-center justify-between border-b border-slate-800 sticky top-0 z-50">
            <div class="flex items-center gap-3">
                <a href="/" target="_blank" class="flex items-center gap-2 text-xs font-bold text-slate-300 hover:text-white transition-colors">
                    <span class="w-6 h-6 rounded-lg bg-amber-500 text-slate-950 font-black flex items-center justify-center text-xs">知</span>
                    <span class="hidden sm:inline">しらんけど サイトを表示 ↗</span>
                </a>
            </div>

            <div class="flex items-center gap-3 text-xs">
                <span class="text-slate-400 hidden sm:inline">👤 <strong class="text-slate-200 font-bold"><?= htmlspecialchars($_SESSION['admin_username'] ?? $adminId) ?></strong> でログイン中</span>
                <a href="?logout=1" class="px-3 py-1 rounded-xl bg-slate-800 hover:bg-rose-900/80 text-rose-300 font-bold border border-slate-700 transition-colors">
                    ログアウト
                </a>
            </div>
        </header>

        <!-- メインレイアウト: 左サイドバー + 右コンテンツ -->
        <div class="flex-1 flex flex-col md:flex-row">
            
            <!-- WordPress風 左サイドバー -->
            <aside class="w-full md:w-64 bg-slate-950 text-slate-300 border-r border-slate-800 flex-shrink-0 p-4 space-y-6">
                <!-- サイトタイトル -->
                <div class="px-2 py-1">
                    <div class="text-sm font-black text-white tracking-wide">しらんけど 管理システム</div>
                    <div class="text-[10px] text-slate-500">v2.4 Auto-Trend & Trade Engine</div>
                </div>

                <!-- メニューナビゲーション (グループ分け) -->
                <nav class="space-y-0.5">
                    <?php 
                    $currentGroup = null;
                    foreach ($navTabs as $tabKey => $t): 
                        $group = $t['group'] ?? 'その他';
                        if ($group !== $currentGroup):
                            $currentGroup = $group;
                    ?>
                        <div class="pt-3 pb-1 px-3 text-[10px] font-black tracking-wider text-slate-500">
                            ▼ <?= htmlspecialchars($group) ?>
                        </div>
                    <?php 
                        endif;
                        $isActive = $currentTab === $tabKey;
                        $btnClass = $isActive 
                            ? 'bg-amber-500 text-slate-950 font-black shadow-md' 
                            : 'text-slate-300 hover:bg-slate-900 hover:text-white font-medium';
                    ?>
                        <a href="?tab=<?= $tabKey ?>" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-all <?= $btnClass ?>">
                            <div class="flex items-center gap-2">
                                <span class="text-sm"><?= $t['icon'] ?></span>
                                <span><?= $t['label'] ?></span>
                            </div>
                            <?php if ($t['badge'] !== null): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isActive ? 'bg-slate-950 text-amber-300' : 'bg-slate-800 text-slate-400' ?>">
                                    <?= $t['badge'] ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- 即時実行アクション -->
                <div class="pt-3 border-t border-slate-800/80 space-y-2">
                    <div class="text-[11px] font-bold text-slate-400 px-2">⚡ ワンクリック実行</div>
                    <form method="POST">
                        <input type="hidden" name="op" value="evaluate_lifecycle">
                        <button type="submit" class="w-full py-2 px-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                            <span>⚡ AI生死判定を一括実行</span>
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="op" value="run_worker">
                        <button type="submit" class="w-full py-2 px-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                            <span>🚀 トレンド自動収集を実行</span>
                        </button>
                    </form>
                </div>
            </aside>

            <!-- 右側メインコンテンツパネル -->
            <main class="flex-1 p-4 sm:p-8 space-y-6 overflow-x-hidden">

                <!-- フラッシュ通知メッセージ -->
                <?php if ($flashMessage): ?>
                    <div class="p-4 rounded-2xl text-xs sm:text-sm font-bold shadow-sm border <?= $flashType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900' ?>">
                        <?= $flashMessage ?>
                    </div>
                <?php endif; ?>

                <!-- 1. ダッシュボード タブ -->
                <?php if ($currentTab === 'dashboard'): ?>
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight">ダッシュボード</h1>
                                <p class="text-xs text-slate-500">しらんけど トレンドサイト全体の稼働状況・アクセス返還ステータス</p>
                            </div>
                        </div>

                        <!-- 統計カラフルカード -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-3xl p-5 text-white shadow-md space-y-2">
                                <div class="text-xs font-bold text-indigo-100 flex items-center justify-between">
                                    <span>公開記事数</span>
                                    <span>📝</span>
                                </div>
                                <div class="text-3xl font-black"><?= $totalArticles ?> <span class="text-xs font-normal">本</span></div>
                                <div class="text-[11px] text-indigo-100">一次情報確認済みトレンド</div>
                            </div>

                            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-3xl p-5 text-white shadow-md space-y-2">
                                <div class="text-xs font-bold text-emerald-100 flex items-center justify-between">
                                    <span>相互アクセス流入 (IN)</span>
                                    <span>📥</span>
                                </div>
                                <div class="text-3xl font-black"><?= number_format($totalIn) ?> <span class="text-xs font-normal">アクセス</span></div>
                                <div class="text-[11px] text-emerald-100">相手サイトからの逆アクセス</div>
                            </div>

                            <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-3xl p-5 text-white shadow-md space-y-2">
                                <div class="text-xs font-bold text-amber-100 flex items-center justify-between">
                                    <span>相互アクセス送出 (OUT)</span>
                                    <span>📤</span>
                                </div>
                                <div class="text-3xl font-black"><?= number_format($totalOut) ?> <span class="text-xs font-normal">アクセス</span></div>
                                <div class="text-[11px] text-amber-100">返還率（80〜150%）で還元中</div>
                            </div>

                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-3xl p-5 text-white shadow-md space-y-2">
                                <div class="text-xs font-bold text-purple-100 flex items-center justify-between">
                                    <span>提携サイト数</span>
                                    <span>🔗</span>
                                </div>
                                <div class="text-3xl font-black"><?= count($tradeSites) ?> <span class="text-xs font-normal">サイト</span></div>
                                <div class="text-[11px] text-purple-100">相互リンク & 相互RSS承認済み</div>
                            </div>
                        </div>

                        <!-- 最近の記事一覧クイックプレビュー -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-black text-slate-900">最新公開記事 (直近20件)</h2>
                                <a href="?tab=articles" class="text-xs text-amber-600 hover:text-amber-700 font-bold">すべて見る →</a>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-slate-400">
                                            <th class="py-2.5 font-bold">ID</th>
                                            <th class="py-2.5 font-bold">タイトル</th>
                                            <th class="py-2.5 font-bold">指数</th>
                                            <th class="py-2.5 font-bold">ステータス</th>
                                            <th class="py-2.5 font-bold">公開日時</th>
                                            <th class="py-2.5 font-bold text-right">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($articles as $a): ?>
                                            <tr class="hover:bg-slate-50">
                                                <td class="py-3 font-mono font-bold text-slate-500">#<?= $a['id'] ?></td>
                                                <td class="py-3 font-bold text-slate-900">
                                                    <a href="article.php?id=<?= $a['id'] ?>" target="_blank" class="hover:text-amber-600">
                                                        <?= htmlspecialchars($a['title']) ?> ↗
                                                    </a>
                                                </td>
                                                <td class="py-3">
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                        <?= $a['shirankedo_index'] ?>点
                                                    </span>
                                                </td>
                                                <td class="py-3">
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold <?= $a['status'] === 'published' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' ?>">
                                                        <?= $a['status'] ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 text-slate-500"><?= $a['published_at'] ?></td>
                                                <td class="py-3 text-right">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="op" value="toggle_article_status">
                                                        <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                                        <input type="hidden" name="new_status" value="<?= $a['status'] === 'published' ? 'private' : 'published' ?>">
                                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[11px] font-bold <?= $a['status'] === 'published' ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                                                            <?= $a['status'] === 'published' ? '非公開' : '公開' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <!-- 2. 📝 記事一覧・ページの生死判定 (AI自動ライフサイクル管理) タブ -->
                <?php elseif ($currentTab === 'articles'): ?>
                    <div class="space-y-6">
                        <!-- ヘッダーと一括AI判定ボタン -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                                    <span>📝</span> 記事一覧・ページの生死判定
                                </h1>
                                <p class="text-xs text-slate-500 mt-1">
                                    ページの生死は基本的にAIが自動判定（鮮度・検索需要・読者投票・安全ブレーキを総合評価）。需要終息記事は自動休眠（非公開）へ移行します。
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST">
                                    <input type="hidden" name="op" value="evaluate_lifecycle">
                                    <button type="submit" class="px-4 py-2.5 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black text-xs shadow-md transition-all flex items-center gap-1.5">
                                        <span>⚡ AIによる全記事の生死判定を一括実行</span>
                                    </button>
                                </form>
                                <button onclick="document.getElementById('manual-create-card').classList.toggle('hidden')" class="px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
                                    <span>＋ 新規記事を手動投稿</span>
                                </button>
                            </div>
                        </div>

                        <!-- ページの生死サマリーカード -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                                    <span>🟢 生存・公開中</span>
                                    <span class="text-xs">良好</span>
                                </div>
                                <div class="text-2xl font-black text-emerald-600"><?= $countActive ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-slate-400">需要継続・鮮度良好</div>
                            </div>

                            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                                    <span>🟡 鮮度注意</span>
                                    <span class="text-xs">要観察</span>
                                </div>
                                <div class="text-2xl font-black text-amber-500"><?= $countWarning ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-slate-400">公開14日経過 / 懐疑投票有</div>
                            </div>

                            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                                    <span>🔴 AI自動休眠</span>
                                    <span class="text-xs">非公開</span>
                                </div>
                                <div class="text-2xl font-black text-rose-600"><?= $countDormant ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-slate-400">トレンド終息のためAI休眠</div>
                            </div>

                            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                                    <span>⚠️ 安全保留・下書き</span>
                                    <span class="text-xs">ブレーキ</span>
                                </div>
                                <div class="text-2xl font-black text-purple-600"><?= $countOnHold ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-slate-400">危険キーワード検知中</div>
                            </div>
                        </div>

                        <!-- 記事一覧テーブル (検索・フィルタ機能付き) -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-slate-900 text-sm">全記事リスト (計 <?= count($articles) ?> 件)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="text" 
                                        id="article-search-input" 
                                        placeholder="タイトル・理由で絞り込み..." 
                                        oninput="filterArticles()"
                                        class="px-3.5 py-1.5 rounded-xl border border-slate-200 text-xs w-64 focus:outline-none focus:border-amber-500"
                                    >
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-slate-400 font-bold">
                                            <th class="py-2.5 w-14">画像</th>
                                            <th class="py-2.5">タイトル / カテゴリ</th>
                                            <th class="py-2.5">しらんけど指数</th>
                                            <th class="py-2.5">公開日・経過</th>
                                            <th class="py-2.5">AI生死判定ステータス</th>
                                            <th class="py-2.5">AI判定理由</th>
                                            <th class="py-2.5 text-center">AI自動管理</th>
                                            <th class="py-2.5 text-right">手動ステータス操作</th>
                                        </tr>
                                    </thead>
                                    <tbody id="articles-tbody" class="divide-y divide-slate-100">
                                        <?php foreach ($articles as $a): 
                                            $pubTime = strtotime($a['published_at'] ?? 'now');
                                            $daysOld = max(0, round((time() - $pubTime) / 86400));
                                            $ls = $a['lifecycle_status'] ?? 'active';
                                            $status = $a['status'] ?? 'published';
                                            $autoEnabled = (int)($a['auto_lifecycle_enabled'] ?? 1);

                                            if ($status === 'on_hold') {
                                                $badgeText = '⚠️ 安全保留';
                                                $badgeClass = 'bg-purple-50 text-purple-800 border-purple-200';
                                            } elseif ($ls === 'dormant' || $status === 'private') {
                                                $badgeText = '🔴 休眠 (非公開)';
                                                $badgeClass = 'bg-rose-50 text-rose-800 border-rose-200';
                                            } elseif ($ls === 'warning') {
                                                $badgeText = '🟡 鮮度低下注意';
                                                $badgeClass = 'bg-amber-50 text-amber-800 border-amber-200';
                                            } else {
                                                $badgeText = '🟢 生存 (公開中)';
                                                $badgeClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';
                                            }
                                        ?>
                                            <tr class="hover:bg-slate-50 article-row" data-search="<?= htmlspecialchars(mb_strtolower($a['title'] . ' ' . ($a['lifecycle_reason'] ?? ''))) ?>">
                                                <td class="py-3">
                                                    <div class="w-12 h-8 rounded-lg bg-slate-200 overflow-hidden border border-slate-200">
                                                        <?php if (!empty($a['image_url'])): ?>
                                                            <img src="<?= htmlspecialchars($a['image_url']) ?>" alt="" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex items-center justify-center text-[10px] text-slate-400">画像無</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>

                                                <td class="py-3 max-w-xs">
                                                    <div class="font-bold text-slate-900 leading-snug">
                                                        <a href="article.php?id=<?= $a['id'] ?>" target="_blank" class="hover:text-amber-600 transition-colors">
                                                            <?= htmlspecialchars($a['title']) ?> ↗
                                                        </a>
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 flex items-center gap-2 mt-0.5">
                                                        <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-bold">
                                                            <?= htmlspecialchars($a['category_name'] ?? '総合') ?>
                                                        </span>
                                                        <span>ID #<?= $a['id'] ?></span>
                                                    </div>
                                                </td>

                                                <td class="py-3 whitespace-nowrap">
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-50 text-amber-800 border border-amber-200">
                                                        <?= $a['shirankedo_index'] ?>点
                                                    </span>
                                                </td>

                                                <td class="py-3 whitespace-nowrap">
                                                    <div class="text-slate-700 font-bold"><?= $daysOld === 0 ? '本日公開' : "公開{$daysOld}日目" ?></div>
                                                    <div class="text-[10px] text-slate-400"><?= substr($a['published_at'] ?? '', 0, 10) ?></div>
                                                </td>

                                                <td class="py-3 whitespace-nowrap">
                                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black border <?= $badgeClass ?>">
                                                        <?= $badgeText ?>
                                                    </span>
                                                </td>

                                                <td class="py-3 max-w-sm">
                                                    <div class="text-[11px] text-slate-600 leading-tight">
                                                        <?= htmlspecialchars($a['lifecycle_reason'] ?: ($daysOld >= 30 ? '公開後30日以上経過' : '鮮度良好')) ?>
                                                    </div>
                                                </td>

                                                <td class="py-3 text-center whitespace-nowrap">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="op" value="toggle_auto_lifecycle">
                                                        <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                                        <input type="hidden" name="auto_lifecycle_enabled" value="<?= $autoEnabled ? '0' : '1' ?>">
                                                        <button type="submit" class="px-2 py-1 rounded-lg text-[10px] font-bold border transition-colors <?= $autoEnabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-300 hover:bg-slate-200' ?>" title="クリックで手動固定/自動判定を切替">
                                                            <?= $autoEnabled ? '🤖 AI自動判定: ON' : '✋ 手動固定: OFF' ?>
                                                        </button>
                                                    </form>
                                                </td>

                                                <td class="py-3 text-right whitespace-nowrap">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="op" value="toggle_article_status">
                                                        <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                                        <input type="hidden" name="new_status" value="<?= $status === 'published' ? 'private' : 'published' ?>">
                                                        <button type="submit" class="px-3 py-1 rounded-xl text-xs font-bold transition-colors <?= $status === 'published' ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200' ?>">
                                                            <?= $status === 'published' ? '非公開へ' : '公開へ' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <script>
                        function filterArticles() {
                            const query = document.getElementById('article-search-input').value.toLowerCase().trim();
                            const rows = document.querySelectorAll('.article-row');
                            rows.forEach(r => {
                                const text = r.getAttribute('data-search') || '';
                                if (!query || text.includes(query)) {
                                    r.style.display = '';
                                } else {
                                    r.style.display = 'none';
                                }
                            });
                        }
                        </script>

                        <!-- 手動新規記事作成カード (折りたたみ可能) -->
                        <div id="manual-create-card" class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <h2 class="text-base font-black text-slate-900">記事の新規作成（手動投稿）</h2>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="op" value="create_article">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">記事タイトル <span class="text-rose-600">*</span></label>
                                        <input type="text" name="title" required placeholder="例: 千鳥の新番組が異例のTVer1位を獲得した件" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">カテゴリ</label>
                                        <select name="category_id" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">💡 なぜ話題？（要約ボックス）</label>
                                    <textarea name="why_trending" rows="2" placeholder="新企画の予測不能な展開がSNSで急上昇し..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500"></textarea>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">記事本文（客観的事実に基づいたファクト）</label>
                                    <textarea name="body" rows="6" placeholder="客観的事実に基づいた本文を入力..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500 leading-relaxed"></textarea>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">締めの言葉（しらんけど構文）</label>
                                        <input type="text" name="conclusion_sentence" value="今後の展開に注目が集まります。しらんけど。" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm font-mincho focus:outline-none focus:border-amber-500">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">アイキャッチ画像URL (800x450px推奨)</label>
                                        <input type="text" name="image_url" placeholder="https://images.unsplash.com/photo-..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-6 py-3 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-bold text-xs shadow-md transition-all">
                                        記事を公開する →
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <!-- 3. 🖼️ アイキャッチ画像プール管理 タブ (最大3万枚対応・キーワード3つ) -->
                <?php elseif ($currentTab === 'images'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">アイキャッチ画像プール管理</h1>
                            <p class="text-xs text-slate-500">最大30,000枚規模対応。Gemini AIが記事の重要キーワードと照合して最適な画像（800×450px）を自動選定します</p>
                        </div>

                        <!-- 画像追加フォーム -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                            <h2 class="text-base font-black text-slate-900">新しいアイキャッチ画像の追加登録</h2>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="op" value="add_pool_image">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">💻 ローカルPCから画像をアップロード</label>
                                        <input type="file" name="local_image" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800">
                                        <p class="text-[10px] text-slate-400">※ JPG, PNG, WEBP, GIF (800x450px推奨)</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">🌐 または 画像URLを直接指定</label>
                                        <input type="url" name="url" placeholder="https://... または /uploads/image.webp" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:outline-none focus:border-amber-500">
                                        <p class="text-[10px] text-slate-400">※ ファイルを選択しない場合はURLを入力してください</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">画像説明（altテキスト）</label>
                                        <input type="text" name="alt_text" placeholder="例: お笑いステージ・バラエティ収録イメージ" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">カテゴリ</label>
                                        <select name="category_id" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500">
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- キーワード3つ設定 -->
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">自動マッチング用 キーワード（3つ設定）</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <input type="text" name="kw1" placeholder="キーワード1 (例: 千鳥)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                        <input type="text" name="kw2" placeholder="キーワード2 (例: お笑い)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                        <input type="text" name="kw3" placeholder="キーワード3 (例: テレビ)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-6 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all">
                                        プールに登録する
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 登録済み画像一覧 -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <h2 class="text-base font-black text-slate-900">登録済み画像プール (最新30件)</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                                <?php foreach ($poolImages as $pi): ?>
                                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50 space-y-2 p-2 flex flex-col justify-between">
                                        <div class="aspect-video bg-slate-200 rounded-xl overflow-hidden">
                                            <img src="<?= htmlspecialchars($pi['url']) ?>" alt="<?= htmlspecialchars($pi['alt_text']) ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="space-y-1">
                                            <div class="text-[11px] font-bold text-slate-800 truncate"><?= htmlspecialchars($pi['alt_text']) ?></div>
                                            <div class="text-[10px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-100 truncate">
                                                🏷️ <?= htmlspecialchars($pi['keywords'] ?: '未設定') ?>
                                            </div>
                                            <div class="text-[10px] text-slate-400 flex items-center justify-between">
                                                <span>使用: <?= $pi['use_count'] ?>回</span>
                                                <span>ID #<?= $pi['id'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                <!-- 4. 🔗 相互リンク・相互RSS返還 タブ -->
                <?php elseif ($currentTab === 'trade'): ?>
                    <div class="space-y-6">
                        <!-- ヘッダー & トップ操作バー -->
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                                    <span>🔗</span> 相互リンク・相互RSS & アクセス返還管理
                                </h1>
                                <p class="text-xs text-slate-500 mt-1">
                                    1サイトにつき<strong>複数のRSSフィード</strong>（通常フィード・カテゴリ別・速報用など）を登録可能。流入（IN）に応じたアクセス返還（100%、80%、120%、150%）と特別優遇枠を管理します。
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <!-- 全RSS一括巡回ボタン -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="op" value="fetch_trade_rss">
                                    <button type="submit" class="px-4 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm flex items-center gap-2 transition-all">
                                        <span>⚡</span> 全提携サイトのRSSを一括巡回・更新
                                    </button>
                                </form>

                                <!-- 相互RSS表示/非表示トグルスイッチ -->
                                <form method="POST" class="bg-white border border-slate-200 px-4 py-2.5 rounded-2xl shadow-sm flex items-center gap-3">
                                    <input type="hidden" name="op" value="save_rss_settings">
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" name="show_rss" value="1" <?= SettingsManager::get('show_rss', '1') === '1' ? 'checked' : '' ?> onchange="this.form.submit()" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400">
                                        <span class="text-xs font-bold text-slate-800">相互RSS枠を表示</span>
                                    </label>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= SettingsManager::get('show_rss', '1') === '1' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>">
                                        <?= SettingsManager::get('show_rss', '1') === '1' ? '表示中' : '非表示' ?>
                                    </span>
                                </form>
                            </div>
                        </div>

                        <!-- サマリーメトリクス (4カラム) -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                                <div class="text-[11px] font-bold text-slate-400">提携サイト数</div>
                                <div class="text-2xl font-black text-slate-900 mt-1"><?= count($tradeSites) ?> <span class="text-xs font-normal text-slate-400">サイト</span></div>
                                <div class="text-[11px] text-slate-500 mt-1">承認・保留中を含む</div>
                            </div>
                            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                                <div class="text-[11px] font-bold text-indigo-500">登録RSSフィード総数</div>
                                <div class="text-2xl font-black text-indigo-600 mt-1"><?= $totalFeedUrlsCount ?> <span class="text-xs font-normal text-slate-400">フィード</span></div>
                                <div class="text-[11px] text-slate-500 mt-1">複数登録フィード合算</div>
                            </div>
                            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                                <div class="text-[11px] font-bold text-emerald-500">取得済み最新記事</div>
                                <div class="text-2xl font-black text-emerald-600 mt-1"><?= number_format($feedItemCount) ?> <span class="text-xs font-normal text-slate-400">件</span></div>
                                <div class="text-[11px] text-slate-500 mt-1">RSSキャッシュ保持</div>
                            </div>
                            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm">
                                <div class="text-[11px] font-bold text-amber-500">アクセストレード比率</div>
                                <div class="text-base font-black text-slate-900 mt-1">
                                    <span class="text-emerald-600">IN <?= number_format($totalIn) ?></span> / <span class="text-amber-600">OUT <?= number_format($totalOut) ?></span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-1">返還率: <?= $totalIn > 0 ? round(($totalOut / $totalIn) * 100) : 0 ?>%</div>
                            </div>
                        </div>

                        <!-- 相互リンク・相互RSSの新規登録フォーム (複数RSSフィード対応) -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                    <span>➕</span> 提携サイト & 複数RSSフィードの新規登録
                                </h2>
                                <span class="text-xs text-indigo-600 font-bold bg-indigo-50 px-2.5 py-1 rounded-full">複数RSS登録対応</span>
                            </div>

                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="op" value="add_trade_site">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">提携先サイト名 <span class="text-rose-500">*</span></label>
                                        <input type="text" name="site_name" required placeholder="例: 爆速まとめアンテナ" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">提携先サイトURL <span class="text-rose-500">*</span></label>
                                        <input type="url" name="url" required placeholder="https://example.com/" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">
                                        RSSフィードURL <span class="text-rose-500">*</span>
                                        <span class="text-indigo-600 font-normal ml-1">（複数ある場合は改行して1行に1URLずつ入力してください）</span>
                                    </label>
                                    <textarea name="rss_url" rows="3" required placeholder="https://example.com/feed/&#10;https://example.com/category/tech/rss/&#10;https://example.com/rss.xml" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none leading-relaxed"></textarea>
                                    <p class="text-[11px] text-slate-500 mt-1">
                                        💡 <strong>複数RSS対応:</strong> 1つのサイトに複数のRSSフィード（カテゴリ別、更新頻度別など）を登録できます。自動的に巡回・集約して記事を取得します。
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">アクセス返還率</label>
                                        <select name="return_rate" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold bg-white">
                                            <option value="80">80% 返還</option>
                                            <option value="100" selected>100% 返還 (等倍)</option>
                                            <option value="120">120% 返還</option>
                                            <option value="150">150% 返還 (還元)</option>
                                            <option value="200">200% 返還 (倍返し)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">ステータス</label>
                                        <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold bg-white">
                                            <option value="approved" selected>承認（掲載開始）</option>
                                            <option value="pending">保留（未承認）</option>
                                        </select>
                                    </div>

                                    <div class="flex flex-col justify-end">
                                        <label class="flex items-center gap-2 cursor-pointer pb-2">
                                            <input type="checkbox" name="is_boosted" value="1" class="rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                                            <span class="text-xs font-bold text-amber-800">特別優遇枠（優先表示）</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="fetch_now" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-400">
                                        <span class="text-xs font-bold text-slate-700">登録直後に全RSSフィードを巡回して記事を取得する</span>
                                    </label>

                                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-all">
                                        提携サイト & 複数RSSを登録する
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 一括バルク登録フォーム (折りたたみ) -->
                        <details class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm group">
                            <summary class="font-bold text-xs text-slate-700 cursor-pointer flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <span>📋</span> 提携サイトのまとめて一括インポート（バルク登録）
                                </span>
                                <span class="text-indigo-600 text-[11px] group-open:hidden">開いて入力 ▾</span>
                                <span class="text-slate-400 text-[11px] hidden group-open:inline">閉じる ▴</span>
                            </summary>
                            <form method="POST" class="mt-4 space-y-3 pt-3 border-t border-slate-100">
                                <input type="hidden" name="op" value="bulk_add_trade_sites">
                                <p class="text-xs text-slate-500">
                                    1行に1サイトずつ「<code>サイト名 | サイトURL | RSS URL1, RSS URL2...</code>」の形式で入力してください。複数RSSはカンマまたはスペース区切りで指定できます。
                                </p>
                                <textarea name="bulk_data" rows="4" placeholder="テストアンテナ1 | https://site1.example.com | https://site1.example.com/rss1.xml, https://site1.example.com/rss2.xml&#10;テストアンテナ2 | https://site2.example.com | https://site2.example.com/feed/" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="fetch_now_bulk" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-400">
                                        <span class="text-xs font-bold text-slate-700">登録後に全RSSを自動巡回する</span>
                                    </label>
                                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm">
                                        一括インポートを実行
                                    </button>
                                </div>
                            </form>
                        </details>

                        <!-- 提携サイト一覧 & 各サイト複数RSS管理テーブル -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3">
                                <div>
                                    <h2 class="text-base font-black text-slate-900">提携サイト一覧 (アクセス比率 & 登録RSS管理)</h2>
                                    <p class="text-xs text-slate-500">各サイトの登録RSSフィード数やURLの編集、即時クローラー実行が可能です</p>
                                </div>
                                <span class="text-xs font-bold text-slate-400">全 <?= count($tradeSites) ?> 件</span>
                            </div>
                            
                            <?php if (empty($tradeSites)): ?>
                                <p class="text-xs text-slate-400 py-6 text-center">まだ提携サイトはありません。上のフォームから登録するか、「相互リンク依頼」ページから受け付け可能です。</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-100 text-slate-400">
                                                <th class="py-2.5 font-bold">提携サイト名 & URL</th>
                                                <th class="py-2.5 font-bold">登録RSSフィード</th>
                                                <th class="py-2.5 font-bold">IN / OUT</th>
                                                <th class="py-2.5 font-bold">返還率設定</th>
                                                <th class="py-2.5 font-bold">特別優遇</th>
                                                <th class="py-2.5 font-bold">ステータス</th>
                                                <th class="py-2.5 font-bold text-right">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <?php foreach ($tradeSites as $ts): 
                                                $siteRssList = TradeEngine::extractRssUrls($ts['rss_url'] ?? '');
                                                $rssCount = count($siteRssList);
                                            ?>
                                                <tr class="hover:bg-slate-50 transition-colors">
                                                    <!-- サイト情報 & 編集トリガー -->
                                                    <td class="py-3 max-w-[200px]">
                                                        <div class="font-bold text-slate-900 truncate" title="<?= htmlspecialchars($ts['site_name']) ?>">
                                                            <?= htmlspecialchars($ts['site_name']) ?>
                                                        </div>
                                                        <a href="<?= htmlspecialchars($ts['url']) ?>" target="_blank" class="text-indigo-600 hover:underline block truncate text-[11px] mt-0.5" title="<?= htmlspecialchars($ts['url']) ?>">
                                                            🌐 <?= htmlspecialchars($ts['url']) ?>
                                                        </a>
                                                    </td>

                                                    <!-- 登録RSSフィード一覧 (複数URL表示) -->
                                                    <td class="py-3 max-w-[280px]">
                                                        <div class="flex items-center gap-1.5 mb-1">
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold <?= $rssCount > 1 ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-700' ?>">
                                                                📡 RSS <?= $rssCount ?>件登録
                                                            </span>
                                                            <button type="button" onclick="document.getElementById('edit-modal-<?= $ts['id'] ?>').classList.remove('hidden')" class="text-[10px] text-indigo-600 hover:text-indigo-800 font-bold hover:underline">
                                                                ✏️ RSS編集
                                                            </button>
                                                        </div>
                                                        <div class="space-y-0.5 max-h-16 overflow-y-auto pr-1">
                                                            <?php if (empty($siteRssList)): ?>
                                                                <span class="text-slate-400 text-[10px]">RSS未設定</span>
                                                            <?php else: ?>
                                                                <?php foreach ($siteRssList as $feedIdx => $fUrl): ?>
                                                                    <div class="text-[10px] text-slate-600 font-mono truncate flex items-center gap-1" title="<?= htmlspecialchars($fUrl) ?>">
                                                                        <span class="text-slate-400">#<?= $feedIdx + 1 ?></span>
                                                                        <a href="<?= htmlspecialchars($fUrl) ?>" target="_blank" class="hover:text-indigo-600 hover:underline truncate">
                                                                            <?= htmlspecialchars($fUrl) ?>
                                                                        </a>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>

                                                    <!-- IN / OUT -->
                                                    <td class="py-3 font-mono">
                                                        <div class="text-emerald-600 font-bold">IN: <?= number_format($ts['in_count']) ?></div>
                                                        <div class="text-amber-600 font-bold">OUT: <?= number_format($ts['out_count']) ?></div>
                                                    </td>

                                                    <!-- インライン更新フォーム -->
                                                    <form method="POST">
                                                        <input type="hidden" name="op" value="update_trade_site">
                                                        <input type="hidden" name="trade_id" value="<?= $ts['id'] ?>">
                                                        <input type="hidden" name="rss_url" value="<?= htmlspecialchars($ts['rss_url'] ?? '') ?>">

                                                        <td class="py-3">
                                                            <select name="return_rate" class="px-2 py-1 rounded-xl border border-slate-200 text-xs font-bold bg-white">
                                                                <option value="80" <?= $ts['return_rate'] == 80 ? 'selected' : '' ?>>80% 返還</option>
                                                                <option value="100" <?= $ts['return_rate'] == 100 ? 'selected' : '' ?>>100% (等倍)</option>
                                                                <option value="120" <?= $ts['return_rate'] == 120 ? 'selected' : '' ?>>120% 返還</option>
                                                                <option value="150" <?= $ts['return_rate'] == 150 ? 'selected' : '' ?>>150% (還元)</option>
                                                                <option value="200" <?= $ts['return_rate'] == 200 ? 'selected' : '' ?>>200% (倍返し)</option>
                                                            </select>
                                                        </td>

                                                        <td class="py-3">
                                                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                                                <input type="checkbox" name="is_boosted" value="1" <?= $ts['is_boosted'] ? 'checked' : '' ?> class="rounded border-slate-300 text-amber-500">
                                                                <span class="text-[11px] font-bold text-amber-800">優遇枠</span>
                                                            </label>
                                                        </td>

                                                        <td class="py-3">
                                                            <select name="status" class="px-2 py-1 rounded-xl border border-slate-200 text-xs font-bold <?= $ts['status'] === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : ($ts['status'] === 'pending' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-slate-100 text-slate-600') ?>">
                                                                <option value="pending" <?= $ts['status'] === 'pending' ? 'selected' : '' ?>>保留</option>
                                                                <option value="approved" <?= $ts['status'] === 'approved' ? 'selected' : '' ?>>承認 (掲載中)</option>
                                                                <option value="rejected" <?= $ts['status'] === 'rejected' ? 'selected' : '' ?>>非承認</option>
                                                                <option value="deleted" <?= $ts['status'] === 'deleted' ? 'selected' : '' ?>>解除</option>
                                                            </select>
                                                        </td>

                                                        <td class="py-3 text-right space-x-1 whitespace-nowrap">
                                                            <button type="submit" class="px-2.5 py-1 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-[11px] shadow-sm">
                                                                保存
                                                            </button>
                                                    </form>

                                                    <!-- 個別RSS巡回ボタン -->
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="op" value="fetch_trade_rss">
                                                        <input type="hidden" name="trade_id" value="<?= $ts['id'] ?>">
                                                        <button type="submit" title="このサイトの全RSSを今すぐ取得" class="px-2 py-1 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[11px] border border-indigo-200">
                                                            ⚡
                                                        </button>
                                                    </form>

                                                    <!-- 削除ボタン -->
                                                    <form method="POST" class="inline" onsubmit="return confirm('提携サイト「<?= htmlspecialchars($ts['site_name']) ?>」と取得記事キャッシュを完全に削除しますか？');">
                                                        <input type="hidden" name="op" value="delete_trade_site">
                                                        <input type="hidden" name="trade_id" value="<?= $ts['id'] ?>">
                                                        <button type="submit" title="削除" class="px-2 py-1 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[11px] border border-rose-200">
                                                            🗑️
                                                        </button>
                                                    </form>
                                                    </td>
                                                </tr>

                                                <!-- 各提携サイトの複数RSS編集モーダル -->
                                                <div id="edit-modal-<?= $ts['id'] ?>" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
                                                    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 border border-slate-200">
                                                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                                            <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                                                                <span>📡</span> 提携サイト & 複数RSSフィードの編集
                                                            </h3>
                                                            <button type="button" onclick="document.getElementById('edit-modal-<?= $ts['id'] ?>').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg font-bold">
                                                                ✕
                                                            </button>
                                                        </div>

                                                        <form method="POST" class="space-y-4">
                                                            <input type="hidden" name="op" value="update_trade_site">
                                                            <input type="hidden" name="trade_id" value="<?= $ts['id'] ?>">

                                                            <div>
                                                                <label class="block text-xs font-bold text-slate-700 mb-1">サイト名</label>
                                                                <input type="text" name="site_name" value="<?= htmlspecialchars($ts['site_name']) ?>" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
                                                            </div>

                                                            <div>
                                                                <label class="block text-xs font-bold text-slate-700 mb-1">サイトURL</label>
                                                                <input type="url" name="url" value="<?= htmlspecialchars($ts['url']) ?>" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs">
                                                            </div>

                                                            <div>
                                                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                                                    RSSフィードURL一覧
                                                                    <span class="text-indigo-600 font-normal ml-1">（複数ある場合は改行して入力）</span>
                                                                </label>
                                                                <textarea name="rss_url" rows="4" required class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-mono leading-relaxed focus:ring-2 focus:ring-indigo-500 focus:outline-none"><?= htmlspecialchars($ts['rss_url'] ?? '') ?></textarea>
                                                                <p class="text-[11px] text-slate-500 mt-1">
                                                                    ※ 1行に1つずつURLを記述してください。保存時に自動的に全フィードが登録されます。
                                                                </p>
                                                            </div>

                                                            <div class="grid grid-cols-2 gap-3">
                                                                <div>
                                                                    <label class="block text-xs font-bold text-slate-700 mb-1">返還率</label>
                                                                    <select name="return_rate" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold bg-white">
                                                                        <option value="80" <?= $ts['return_rate'] == 80 ? 'selected' : '' ?>>80% 返還</option>
                                                                        <option value="100" <?= $ts['return_rate'] == 100 ? 'selected' : '' ?>>100% (等倍)</option>
                                                                        <option value="120" <?= $ts['return_rate'] == 120 ? 'selected' : '' ?>>120% 返還</option>
                                                                        <option value="150" <?= $ts['return_rate'] == 150 ? 'selected' : '' ?>>150% (還元)</option>
                                                                        <option value="200" <?= $ts['return_rate'] == 200 ? 'selected' : '' ?>>200% (倍返し)</option>
                                                                    </select>
                                                                </div>

                                                                <div>
                                                                    <label class="block text-xs font-bold text-slate-700 mb-1">ステータス</label>
                                                                    <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold bg-white">
                                                                        <option value="pending" <?= $ts['status'] === 'pending' ? 'selected' : '' ?>>保留</option>
                                                                        <option value="approved" <?= $ts['status'] === 'approved' ? 'selected' : '' ?>>承認（掲載中）</option>
                                                                        <option value="rejected" <?= $ts['status'] === 'rejected' ? 'selected' : '' ?>>非承認</option>
                                                                        <option value="deleted" <?= $ts['status'] === 'deleted' ? 'selected' : '' ?>>解除</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                                                                <button type="button" onclick="document.getElementById('edit-modal-<?= $ts['id'] ?>').classList.add('hidden')" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-bold">
                                                                    キャンセル
                                                                </button>
                                                                <button type="submit" class="px-5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm">
                                                                    変更を保存する
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- 5. 💰 アフィリエイト広告スロット設定 タブ -->
                <?php elseif ($currentTab === 'ads'): ?>
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                                    <span>💰</span> アフィリエイト広告・個別枠設定
                                </h1>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    広告枠ごとに個別に「表示 / 非表示」を設定できます。A8.net、もしもアフィリエイト、バリューコマース等の広告タグを配置できます。
                                </p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="op" value="save_ads">

                            <!-- マスター表示切替 -->
                            <div class="bg-amber-50 border border-amber-200 rounded-3xl p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="text-sm font-black text-amber-950 flex items-center gap-2">
                                        <span>📢</span> アフィリエイト広告 全体マスター表示切替
                                    </div>
                                    <p class="text-xs text-amber-800">
                                        チェックを外すと、個別設定にかかわらずサイト全体の広告枠が一括で非表示になります（審査時などに便利です）。
                                    </p>
                                </div>
                                <label class="relative flex items-center gap-2.5 cursor-pointer bg-white px-5 py-3 rounded-2xl border border-amber-300 shadow-sm">
                                    <input type="checkbox" name="show_ads" value="1" <?= SettingsManager::get('show_ads', '1') === '1' ? 'checked' : '' ?> class="w-5 h-5 rounded text-amber-500 focus:ring-amber-400">
                                    <span class="text-xs font-black text-slate-800">サイト全体で広告を表示する</span>
                                </label>
                            </div>

                            <!-- PC専用広告スロット -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                    <span class="text-lg">💻</span>
                                    <h2 class="text-base font-black text-slate-900">PC専用 広告スロット（個別表示・非表示対応）</h2>
                                </div>

                                <!-- 1. PCヘッダー -->
                                <?php $pcHeaderOn = SettingsManager::get('ad_pc_header_enabled', '1') === '1'; ?>
                                <div class="p-4 rounded-2xl border <?= $pcHeaderOn ? 'border-slate-200 bg-slate-50/50' : 'border-rose-200 bg-rose-50/30' ?> space-y-3">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div>
                                            <div class="text-xs font-black text-slate-900 flex items-center gap-2">
                                                <span>① PC ヘッダー内 (468×60px / 728×90px)</span>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $pcHeaderOn ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                                    <?= $pcHeaderOn ? '🟢 個別表示: ON' : '⚪ 個別非表示: OFF' ?>
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-slate-500">PCトップ及び記事ページ上部のヘッダー横・ロゴ横に配置されます。</p>
                                        </div>
                                        <label class="flex items-center gap-2 cursor-pointer bg-white px-3.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 shadow-xs">
                                            <input type="checkbox" name="ad_pc_header_enabled" value="1" <?= $pcHeaderOn ? 'checked' : '' ?> class="w-4 h-4 rounded text-amber-500">
                                            <span>この枠を表示する</span>
                                        </label>
                                    </div>
                                    <textarea name="ad_pc_header" rows="3" placeholder="<a href='...'><img src='...' alt='広告'></a> または JavaScriptタグ" class="w-full p-3 rounded-xl border border-slate-200 font-mono text-xs bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_pc_header')) ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- 2. PCサイドバー上 -->
                                    <?php $pcSideTopOn = SettingsManager::get('ad_pc_sidebar_top_enabled', '1') === '1'; ?>
                                    <div class="p-4 rounded-2xl border <?= $pcSideTopOn ? 'border-slate-200 bg-slate-50/50' : 'border-rose-200 bg-rose-50/30' ?> space-y-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="text-xs font-black text-slate-900">② PC サイド上 (300×250px)</div>
                                                <div class="text-[10px] text-slate-500">サイドバー最上部レクタングル</div>
                                            </div>
                                            <label class="flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                                                <input type="checkbox" name="ad_pc_sidebar_top_enabled" value="1" <?= $pcSideTopOn ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded text-amber-500">
                                                <span>表示</span>
                                            </label>
                                        </div>
                                        <textarea name="ad_pc_sidebar_top" rows="3" class="w-full p-2.5 rounded-xl border border-slate-200 font-mono text-xs bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_pc_sidebar_top')) ?></textarea>
                                    </div>

                                    <!-- 3. PCサイドバー下 -->
                                    <?php $pcSideBottomOn = SettingsManager::get('ad_pc_sidebar_bottom_enabled', '1') === '1'; ?>
                                    <div class="p-4 rounded-2xl border <?= $pcSideBottomOn ? 'border-slate-200 bg-slate-50/50' : 'border-rose-200 bg-rose-50/30' ?> space-y-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="text-xs font-black text-slate-900">③ PC サイド下 (300×250px)</div>
                                                <div class="text-[10px] text-slate-500">ランキング・RSS下部の追従領域</div>
                                            </div>
                                            <label class="flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                                                <input type="checkbox" name="ad_pc_sidebar_bottom_enabled" value="1" <?= $pcSideBottomOn ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded text-amber-500">
                                                <span>表示</span>
                                            </label>
                                        </div>
                                        <textarea name="ad_pc_sidebar_bottom" rows="3" class="w-full p-2.5 rounded-xl border border-slate-200 font-mono text-xs bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_pc_sidebar_bottom')) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- スマホ専用広告スロット -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                    <span class="text-lg">📱</span>
                                    <h2 class="text-base font-black text-slate-900">スマホ専用 広告スロット（個別表示・非表示対応）</h2>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- 4. スマホヘッダー上 -->
                                    <?php $spHeadTopOn = SettingsManager::get('ad_sp_header_top_enabled', '1') === '1'; ?>
                                    <div class="p-4 rounded-2xl border <?= $spHeadTopOn ? 'border-slate-200 bg-slate-50/50' : 'border-rose-200 bg-rose-50/30' ?> space-y-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="text-xs font-black text-slate-900">④ スマホ ヘッダー上 (300×250px)</div>
                                                <div class="text-[10px] text-slate-500">ファーストビュー最上部</div>
                                            </div>
                                            <label class="flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                                                <input type="checkbox" name="ad_sp_header_top_enabled" value="1" <?= $spHeadTopOn ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded text-amber-500">
                                                <span>表示</span>
                                            </label>
                                        </div>
                                        <textarea name="ad_sp_header_top" rows="3" class="w-full p-2.5 rounded-xl border border-slate-200 font-mono text-xs bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_sp_header_top')) ?></textarea>
                                    </div>

                                    <!-- 5. スマホヘッダー下 -->
                                    <?php $spHeadBottomOn = SettingsManager::get('ad_sp_header_bottom_enabled', '1') === '1'; ?>
                                    <div class="p-4 rounded-2xl border <?= $spHeadBottomOn ? 'border-slate-200 bg-slate-50/50' : 'border-rose-200 bg-rose-50/30' ?> space-y-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="text-xs font-black text-slate-900">⑤ スマホ ヘッダー下 (300×250px)</div>
                                                <div class="text-[10px] text-slate-500">記事タイトル直下</div>
                                            </div>
                                            <label class="flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                                                <input type="checkbox" name="ad_sp_header_bottom_enabled" value="1" <?= $spHeadBottomOn ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded text-amber-500">
                                                <span>表示</span>
                                            </label>
                                        </div>
                                        <textarea name="ad_sp_header_bottom" rows="3" class="w-full p-2.5 rounded-xl border border-slate-200 font-mono text-xs bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_sp_header_bottom')) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- 記事詳細ページ内 広告スロット -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                    <span class="text-lg">📄</span>
                                    <h2 class="text-base font-black text-slate-900">記事ページ内 広告スロット（インフィード・本文下）</h2>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- 6. 記事本文中 -->
                                    <?php $artMiddleOn = SettingsManager::get('ad_article_middle_enabled', '1') === '1'; ?>
                                    <div class="p-4 rounded-2xl border <?= $artMiddleOn ? 'border-slate-200 bg-slate-50/50' : 'border-rose-200 bg-rose-50/30' ?> space-y-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="text-xs font-black text-slate-900">⑥ 記事本文中 (インフィード / 300×250px)</div>
                                                <div class="text-[10px] text-slate-500">なぜ話題ボックスと本文の間</div>
                                            </div>
                                            <label class="flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                                                <input type="checkbox" name="ad_article_middle_enabled" value="1" <?= $artMiddleOn ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded text-amber-500">
                                                <span>表示</span>
                                            </label>
                                        </div>
                                        <textarea name="ad_article_middle" rows="3" placeholder="本文中インフィード広告タグ..." class="w-full p-2.5 rounded-xl border border-slate-200 font-mono text-xs bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_article_middle')) ?></textarea>
                                    </div>

                                    <!-- 7. 記事下部 -->
                                    <?php $artBottomOn = SettingsManager::get('ad_article_bottom_enabled', '1') === '1'; ?>
                                    <div class="p-4 rounded-2xl border <?= $artBottomOn ? 'border-slate-200 bg-slate-50/50' : 'border-rose-200 bg-rose-50/30' ?> space-y-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <div class="text-xs font-black text-slate-900">⑦ 記事下部 (関連記事上 / 300×250px〜)</div>
                                                <div class="text-[10px] text-slate-500">本文読了後・投票ボタンの直下</div>
                                            </div>
                                            <label class="flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                                                <input type="checkbox" name="ad_article_bottom_enabled" value="1" <?= $artBottomOn ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded text-amber-500">
                                                <span>表示</span>
                                            </label>
                                        </div>
                                        <textarea name="ad_article_bottom" rows="3" placeholder="記事直下広告タグ..." class="w-full p-2.5 rounded-xl border border-slate-200 font-mono text-xs bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('ad_article_bottom')) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-8 py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-black text-xs shadow-md transition-all">
                                    広告の個別表示・コード設定を保存する
                                </button>
                            </div>
                        </form>
                    </div>

                <!-- 6. 🏷️ SEO・カスタムタグ設定 タブ -->
                <?php elseif ($currentTab === 'seo_tags'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">SEO・メタタグ & カスタムタグ設定</h1>
                            <p class="text-xs text-slate-500">アクセストレード用リファラータグ、Google Search Console、Bing Webmaster Tools、Analytics等の挿入</p>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="op" value="save_tags">

                            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-bold text-slate-800">&lt;head&gt; 内に挿入するカスタムタグ</label>
                                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                            ✓ &lt;meta name="referrer" content="unsafe-url"&gt; 適用中
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500">Google Search Console所有権メタタグ、Bing Webmasterメタタグ、OGPタグなどを自由に追加できます。</p>
                                    <textarea name="head_custom_tags" rows="6" class="w-full p-3.5 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('head_custom_tags')) ?></textarea>
                                </div>

                                <div class="space-y-1.5 pt-4 border-t border-slate-100">
                                    <label class="block text-xs font-bold text-slate-800">&lt;body&gt; 直下に挿入するカスタムタグ</label>
                                    <p class="text-[11px] text-slate-500">Google Tag Manager（GTM body）、アクセスカウンター、トラッキングコード等の挿入場所です。</p>
                                    <textarea name="body_top_tags" rows="6" class="w-full p-3.5 rounded-2xl border border-slate-200 font-mono text-xs bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500"><?= htmlspecialchars(SettingsManager::get('body_top_tags')) ?></textarea>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-black text-xs shadow-md transition-all">
                                        タグ設定を保存する
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                <!-- 7. 📢 お知らせ一覧 タブ -->
                <?php elseif ($currentTab === 'announcements'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">お知らせ一覧（相互リンク承認・解除通知）</h1>
                            <p class="text-xs text-slate-500">提携サイトの承認・解除時に自動投稿されたお知らせ履歴です</p>
                        </div>

                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <?php if (empty($announcements)): ?>
                                <p class="text-xs text-slate-400 py-6 text-center">まだお知らせはありません。</p>
                            <?php else: ?>
                                <div class="divide-y divide-slate-100">
                                    <?php foreach ($announcements as $ann): ?>
                                        <div class="py-4 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $ann['type'] === 'trade_approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                                                    <?= $ann['type'] === 'trade_approved' ? '相互提携' : 'お知らせ' ?>
                                                </span>
                                                <span class="text-xs text-slate-400"><?= $ann['published_at'] ?></span>
                                            </div>
                                            <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($ann['title']) ?></h3>
                                            <p class="text-xs text-slate-600"><?= nl2br(htmlspecialchars($ann['body'])) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- 8. 🔒 セキュリティ・アカウント設定 タブ (ID・パスワード・メール・URL) -->
                <?php elseif ($currentTab === 'security'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">アカウント・セキュリティ設定</h1>
                            <p class="text-xs text-slate-500">管理者ログインID、パスワード、再設定用メールアドレス、推測不能なシークレットURLを設定・変更できます</p>
                        </div>

                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="op" value="save_security">

                                <!-- アカウント認証情報セクション -->
                                <div class="space-y-4">
                                    <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
                                        <span class="text-lg">👤</span>
                                        <h2 class="text-sm font-black text-slate-900">管理者ログインアカウント設定</h2>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <!-- 管理者ID -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-bold text-slate-700">
                                                管理者ログインID <span class="text-rose-600">*</span>
                                            </label>
                                            <input type="text" name="admin_id" value="<?= htmlspecialchars($adminId) ?>" required minlength="3" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500 bg-slate-50 focus:bg-white">
                                            <p class="text-[11px] text-slate-400">※ 3文字以上の半角英数字（初期値: admin）</p>
                                        </div>

                                        <!-- パスワード再設定用メールアドレス -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-bold text-slate-700">
                                                パスワード再設定用メールアドレス <span class="text-rose-600">*</span>
                                            </label>
                                            <input type="email" name="admin_email" value="<?= htmlspecialchars($adminEmail) ?>" required class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:border-amber-500 bg-slate-50 focus:bg-white">
                                            <p class="text-[11px] text-slate-400">※ パスワード紛失時に再設定用リンクを受信するアドレスです</p>
                                        </div>
                                    </div>

                                    <!-- パスワード変更 -->
                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                                        <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                            <span>🔑</span> パスワード変更（変更する場合のみ入力）
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div class="space-y-1">
                                                <label class="block text-[11px] font-bold text-slate-600">新しいパスワード</label>
                                                <input type="password" name="admin_password" placeholder="変更する場合のみ入力（6文字以上）" class="w-full px-4 py-2 rounded-xl border border-slate-200 font-mono text-xs focus:outline-none focus:border-amber-500 bg-white">
                                            </div>
                                            <div class="space-y-1">
                                                <label class="block text-[11px] font-bold text-slate-600">新しいパスワード（確認用）</label>
                                                <input type="password" name="admin_password_confirm" placeholder="もう一度入力" class="w-full px-4 py-2 rounded-xl border border-slate-200 font-mono text-xs focus:outline-none focus:border-amber-500 bg-white">
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-slate-400">※ 空欄のまま保存した場合は現在のパスワードが維持されます。</p>
                                    </div>
                                </div>

                                <!-- URLスラッグセクション -->
                                <div class="space-y-4 pt-4 border-t border-slate-100">
                                    <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
                                        <span class="text-lg">🛡️</span>
                                        <h2 class="text-sm font-black text-slate-900">推測不能なシークレット管理URL</h2>
                                    </div>

                                    <div class="space-y-2 bg-amber-50 border border-amber-200 p-4 rounded-2xl">
                                        <div class="text-xs font-black text-amber-900 flex items-center gap-1.5">
                                            <span>🔗</span> 現在のアクセスURL
                                        </div>
                                        <div class="text-sm font-mono font-bold text-amber-950 break-all">
                                            https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'shirankedo.bichi.xyz') ?>/<?= htmlspecialchars($thisFileUrl) ?>
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">
                                            管理画面シークレットURLスラッグ <span class="text-rose-600">*</span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-mono text-slate-400">/admin-</span>
                                            <input type="text" name="admin_secret_path" value="<?= htmlspecialchars($currentSecretPath) ?>" required minlength="6" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500">
                                            <span class="text-xs font-mono text-slate-400">.php</span>
                                        </div>
                                        <p class="text-[11px] text-slate-400">
                                            ※ ランダムな英数字を設定することで、攻撃者が管理画面の場所を特定できなくなります。
                                        </p>
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-black text-xs shadow-md transition-all">
                                        アカウント・セキュリティ設定を更新する
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <!-- 9. 📈 アクセス解析 タブ -->
                <?php elseif ($currentTab === 'analytics'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">リアルタイム・アクセス解析</h1>
                            <p class="text-xs text-slate-500">しらんけど サイトのPV数、流入元（X、Instagram、検索、相互RSS）、端末別比率を詳しく可視化します</p>
                        </div>

                        <?php 
                        $stats = AnalyticsTracker::getStats(14);
                        $totalPv = $stats['total_pv'] ?? 0;
                        $todayPv = $stats['today_pv'] ?? 0;
                        $yesterdayPv = $stats['yesterday_pv'] ?? 0;
                        ?>

                        <!-- サマリーカード -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-400">総ページビュー数 (全期間)</div>
                                <div class="text-3xl font-black text-slate-900"><?= number_format($totalPv) ?> <span class="text-xs font-normal text-slate-500">PV</span></div>
                            </div>
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-emerald-600">本日のアクセス数 (Today)</div>
                                <div class="text-3xl font-black text-emerald-600"><?= number_format($todayPv) ?> <span class="text-xs font-normal text-emerald-500">PV</span></div>
                            </div>
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-400">昨日のアクセス数 (Yesterday)</div>
                                <div class="text-3xl font-black text-slate-700"><?= number_format($yesterdayPv) ?> <span class="text-xs font-normal text-slate-500">PV</span></div>
                            </div>
                        </div>

                        <!-- 流入元 & 端末比率 -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- 流入元 (リファラー) -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                    <span>🌐</span> 主な参照元 (Referrer)
                                </h2>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-100 text-slate-400">
                                                <th class="py-2 font-bold">ドメイン / 参照元</th>
                                                <th class="py-2 font-bold text-right">アクセス数</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <?php if (empty($stats['referers'])): ?>
                                                <tr><td colspan="2" class="py-4 text-center text-slate-400">まだ参照元データがありません</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($stats['referers'] as $ref): ?>
                                                    <tr>
                                                        <td class="py-2.5 font-bold text-slate-800"><?= htmlspecialchars($ref['referer_host']) ?></td>
                                                        <td class="py-2.5 font-mono font-bold text-amber-600 text-right"><?= number_format($ref['count']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 端末比率 (デバイス) -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                    <span>📱</span> 端末比率 (Device Ratio)
                                </h2>
                                <div class="space-y-3 pt-2">
                                    <?php 
                                    $devSum = array_sum($stats['devices'] ?? []) ?: 1;
                                    $devLabels = ['mobile' => 'スマートフォン (Mobile)', 'pc' => 'パソコン (Desktop)', 'tablet' => 'タブレット (Tablet)'];
                                    foreach (['mobile', 'pc', 'tablet'] as $d): 
                                        $cnt = $stats['devices'][$d] ?? 0;
                                        $pct = round(($cnt / $devSum) * 100, 1);
                                    ?>
                                        <div class="space-y-1">
                                            <div class="flex justify-between text-xs font-bold text-slate-700">
                                                <span><?= $devLabels[$d] ?></span>
                                                <span class="font-mono"><?= $cnt ?> PV (<?= $pct ?>%)</span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                                <div class="bg-amber-500 h-full rounded-full" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- 人気記事ランキング -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>🔥</span> 人気記事ランキング (直近14日間)
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-slate-400">
                                            <th class="py-2.5 font-bold">順位</th>
                                            <th class="py-2.5 font-bold">記事タイトル</th>
                                            <th class="py-2.5 font-bold">しらんけど指数</th>
                                            <th class="py-2.5 font-bold text-right">閲覧数 (PV)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php if (empty($stats['top_articles'])): ?>
                                            <tr><td colspan="4" class="py-4 text-center text-slate-400">閲覧ログがまだ記録されていません</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($stats['top_articles'] as $idx => $ta): ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-3 font-black text-amber-600">#<?= $idx + 1 ?></td>
                                                    <td class="py-3 font-bold text-slate-900">
                                                        <a href="article.php?id=<?= $ta['id'] ?>" target="_blank" class="hover:text-amber-600">
                                                            <?= htmlspecialchars($ta['title']) ?> ↗
                                                        </a>
                                                    </td>
                                                    <td class="py-3">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                            <?= $ta['shirankedo_index'] ?>点
                                                        </span>
                                                    </td>
                                                    <td class="py-3 font-mono font-bold text-slate-800 text-right">
                                                        <?= number_format($ta['pv']) ?> PV
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <!-- 10. ✨ Gemini API設定 タブ -->
                <?php elseif ($currentTab === 'gemini'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Gemini AI 設定</h1>
                            <p class="text-xs text-slate-500">Google Gemini APIキーを登録し、話題の自動収集・一次情報確認・記事自動生成パイプラインを稼働させます</p>
                        </div>

                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <form method="POST" class="space-y-5">
                                <input type="hidden" name="op" value="save_gemini">

                                <div class="bg-indigo-50 border border-indigo-200 p-4 rounded-2xl text-xs text-indigo-900 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <span>💡</span> Google AI StudioでAPIキーを取得できます
                                    </div>
                                    <p class="text-indigo-700">
                                        Google AI Studio (<a href="https://aistudio.google.com/" target="_blank" class="underline font-bold">https://aistudio.google.com/</a>) で発行したAPIキーを入力してください。
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">Gemini API Key</label>
                                    <input type="password" name="gemini_api_key" value="<?= htmlspecialchars(SettingsManager::get('gemini_api_key')) ?>" placeholder="AIzaSy..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500">
                                    <p class="text-[11px] text-slate-400">※ 入力されたキーはデータベースに暗号化保存され、記事自動生成時にのみ利用されます。</p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">使用AIモデル</label>
                                    <select name="gemini_model" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:border-amber-500">
                                        <option value="gemini-2.5-flash" <?= SettingsManager::get('gemini_model', 'gemini-2.5-flash') === 'gemini-2.5-flash' ? 'selected' : '' ?>>Gemini 2.5 Flash (推奨・最高速&高精度)</option>
                                        <option value="gemini-2.5-pro" <?= SettingsManager::get('gemini_model') === 'gemini-2.5-pro' ? 'selected' : '' ?>>Gemini 2.5 Pro (超高知能・長文推論)</option>
                                        <option value="gemini-1.5-flash" <?= SettingsManager::get('gemini_model') === 'gemini-1.5-flash' ? 'selected' : '' ?>>Gemini 1.5 Flash (安定版)</option>
                                    </select>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 text-white font-black text-xs shadow-md transition-all">
                                        Gemini API設定を保存する
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php endif; ?>

            </main>
        </div>
    <?php endif; ?>

</body>
</html>
