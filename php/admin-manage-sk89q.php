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
require_once __DIR__ . '/classes/Installer.php';

// DB初期設定の自動検出 (未インストールまたはDB未接続時はインストーラーを起動)
if (isset($_GET['setup']) || !Installer::isInstalled()) {
    Installer::handleInstallationRequest();
    Installer::renderWizard();
}

// 初期セットアップテーブルの存在保証
try {
    MigrationAddFeatures::run();
} catch (Throwable $e) {}

// 管理者認証設定（登録メールアドレス）
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
$loginSuccessMsg = isset($_GET['installed']) ? 'データベース初期セットアップが完了しました！管理者アカウントでログインしてください。' : '';
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
        $loginError = 'IDまたはパスワードが正しくありません。';
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

            // アイキャッチ画像が無い場合は表に出さず「保留」とする
            $hasImage = !empty($imgUrl);
            $status = $hasImage ? 'published' : 'on_hold';
            $dangerReason = !$hasImage ? 'アイキャッチ画像未設定（画像設定後に表へ公開）' : null;

            $stmt = $db->prepare("INSERT INTO articles (site_id, category_id, title, slug, why_trending, body, conclusion_sentence, shirankedo_index, index_label, image_url, status, danger_reason, published_at)
                                  VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$catId, $title, $slug, $why, $body, $conclusion, $index, $label, $imgUrl, $status, $dangerReason]);
            if ($hasImage) {
                $flashMessage = '新着記事を正常に作成・公開しました！';
            } else {
                $flashMessage = '記事を作成しました。※アイキャッチ画像が未設定のため「保留中」として保存しました。アイキャッチを設定すると表のサイトへ公開されます。';
            }
        }

        // 2. 記事ステータス変更（非公開・保留・公開）
        if ($op === 'toggle_article_status') {
            $artId = (int)($_POST['article_id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? 'private';

            // 公開への変更時、アイキャッチ画像が設定されているか検証
            if ($newStatus === 'published') {
                $chkStmt = $db->prepare("SELECT image_url FROM articles WHERE id = ?");
                $chkStmt->execute([$artId]);
                $artRow = $chkStmt->fetch();
                if (empty($artRow['image_url']) || trim($artRow['image_url']) === '') {
                    throw new Exception("記事ID #{$artId} にはアイキャッチ画像が設定されていません。アイキャッチ画像を設定してから表のサイトへ公開してください。");
                }
            }

            $stmt = $db->prepare("UPDATE articles SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $artId]);
            $flashMessage = "記事ID #{$artId} のステータスを「{$newStatus}」に更新しました。";
        }

        // 2-B. 記事の完全削除
        if ($op === 'delete_article') {
            $artId = (int)($_POST['article_id'] ?? 0);
            $db->prepare("DELETE FROM article_sources WHERE article_id = ?")->execute([$artId]);
            $db->prepare("DELETE FROM comments WHERE article_id = ?")->execute([$artId]);
            $db->prepare("DELETE FROM articles WHERE id = ?")->execute([$artId]);
            $flashMessage = "記事ID #{$artId} を完全に削除しました。";
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

        // 2-4. アイキャッチ画像の設定と即時公開
        if ($op === 'set_eyecatch_and_publish') {
            $artId = (int)($_POST['article_id'] ?? 0);
            $imgUrl = trim($_POST['image_url'] ?? '');
            $publishNow = (int)($_POST['publish_now'] ?? 1);

            if (empty($imgUrl)) {
                throw new Exception('アイキャッチ画像のURLを指定してください。');
            }

            $newStatus = $publishNow === 1 ? 'published' : 'on_hold';
            $dangerReason = $publishNow === 1 ? null : 'アイキャッチ設定済み（保留中）';

            $stmt = $db->prepare("UPDATE articles SET image_url = ?, status = ?, danger_reason = ?, lifecycle_status = 'active' WHERE id = ?");
            $stmt->execute([$imgUrl, $newStatus, $dangerReason, $artId]);

            $flashMessage = $publishNow === 1 
                ? "🎉 記事ID #{$artId} にアイキャッチ画像を設定し、表のサイトへ公開しました！" 
                : "記事ID #{$artId} にアイキャッチ画像を設定しました（保留状態として保存）。";
        }

        // 3. アイキャッチ画像プールの追加（複数ファイル一括アップロード / 複数URL一括登録 / 個別登録）
        if ($op === 'add_pool_image') {
            $catId = (int)($_POST['category_id'] ?? 1);
            $altDefault = trim($_POST['alt_text'] ?? 'トレンドアイキャッチ');
            $kw1 = trim($_POST['kw1'] ?? '');
            $kw2 = trim($_POST['kw2'] ?? '');
            $kw3 = trim($_POST['kw3'] ?? '');
            $kws = array_filter([$kw1, $kw2, $kw3]);

            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $addedCount = 0;

            // A. 複数ファイル一括アップロード処理
            if (isset($_FILES['local_images']) && is_array($_FILES['local_images']['name'])) {
                $totalFiles = count($_FILES['local_images']['name']);
                for ($i = 0; $i < $totalFiles; $i++) {
                    if ($_FILES['local_images']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['local_images']['tmp_name'][$i];
                        $origName = $_FILES['local_images']['name'][$i];
                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                        if (in_array($ext, $allowed)) {
                            $safeName = 'eyecatch_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $dest = $uploadDir . '/' . $safeName;
                            if (move_uploaded_file($tmpName, $dest)) {
                                $fileUrl = '/uploads/' . $safeName;
                                $altText = $altDefault ?: pathinfo($origName, PATHINFO_FILENAME);
                                $stmt = $db->prepare("INSERT INTO images (site_id, category_id, filename, url, alt_text, is_active, created_at) VALUES (1, ?, ?, ?, ?, 1, NOW())");
                                $stmt->execute([$catId, $safeName, $fileUrl, $altText]);
                                $newImgId = (int)$db->lastInsertId();
                                foreach ($kws as $k) {
                                    $db->prepare("INSERT INTO image_keywords (image_id, keyword) VALUES (?, ?)")->execute([$newImgId, $k]);
                                }
                                $addedCount++;
                            }
                        }
                    }
                }
            }
            // 単一ファイル互換
            elseif (isset($_FILES['local_image']) && $_FILES['local_image']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['local_image']['tmp_name'];
                $origName = $_FILES['local_image']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $safeName = 'eyecatch_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($tmpName, $dest)) {
                        $fileUrl = '/uploads/' . $safeName;
                        $altText = $altDefault ?: pathinfo($origName, PATHINFO_FILENAME);
                        $stmt = $db->prepare("INSERT INTO images (site_id, category_id, filename, url, alt_text, is_active, created_at) VALUES (1, ?, ?, ?, ?, 1, NOW())");
                        $stmt->execute([$catId, $safeName, $fileUrl, $altText]);
                        $newImgId = (int)$db->lastInsertId();
                        foreach ($kws as $k) {
                            $db->prepare("INSERT INTO image_keywords (image_id, keyword) VALUES (?, ?)")->execute([$newImgId, $k]);
                        }
                        $addedCount++;
                    }
                }
            }

            // B. 複数行URL（または単一URL）の登録処理
            $rawUrls = trim($_POST['urls'] ?? ($_POST['url'] ?? ''));
            if (!empty($rawUrls)) {
                $urlList = preg_split('/[\r\n]+/', $rawUrls);
                foreach ($urlList as $singleUrl) {
                    $singleUrl = trim($singleUrl);
                    if (!empty($singleUrl) && filter_var($singleUrl, FILTER_VALIDATE_URL)) {
                        // 重複チェック
                        $chk = $db->prepare("SELECT id FROM images WHERE site_id = 1 AND url = ?");
                        $chk->execute([$singleUrl]);
                        if (!$chk->fetch()) {
                            $stmt = $db->prepare("INSERT INTO images (site_id, category_id, filename, url, alt_text, is_active, created_at) VALUES (1, ?, 'custom_pool.webp', ?, ?, 1, NOW())");
                            $stmt->execute([$catId, $singleUrl, $altDefault]);
                            $newImgId = (int)$db->lastInsertId();
                            foreach ($kws as $k) {
                                $db->prepare("INSERT INTO image_keywords (image_id, keyword) VALUES (?, ?)")->execute([$newImgId, $k]);
                            }
                            $addedCount++;
                        }
                    }
                }
            }

            if ($addedCount === 0) {
                throw new Exception('画像ファイルをアップロードするか、有効な画像URLを入力してください。');
            }
            $flashMessage = "アイキャッチ画像をプールに {$addedCount} 件登録しました！";
        }

        // 3-B. 商用フリー初期画像セット（全7大ジャンル・計64枚）の一括プリセット追加
        if ($op === 'seed_preset_images') {
            require_once __DIR__ . '/classes/ImageManager.php';
            $genre = $_POST['genre'] ?? 'all';
            $genreLabels = [
                'all' => '全7大ジャンル（計64枚）',
                'tech' => 'IT・ガジェット・AI',
                'entame' => '芸能・エンタメ・音楽',
                'sports' => 'スポーツ・アスリート',
                'game' => 'ゲーム・アニメ・マンガ',
                'gourmet' => 'グルメ・スイーツ・飲食',
                'society' => '社会・ニュース・経済',
                'lifestyle' => '自然・天気・ペット'
            ];
            $label = $genreLabels[$genre] ?? '厳選画像';
            $seededCount = ImageManager::seedDefaultPresets(1, $genre);
            if ($seededCount > 0) {
                $flashMessage = "🎁 商用フリー厳選画像セット【{$label}】（新たに {$seededCount} 枚）をアイキャッチプールに一括登録しました！";
            } else {
                $flashMessage = "ℹ️ 指定のジャンル（{$label}）の画像は既にすべて登録済みです。";
            }
        }

        // 3-C. プール画像の削除
        if ($op === 'delete_pool_image') {
            $delImgId = (int)($_POST['image_id'] ?? 0);
            $db->prepare("DELETE FROM image_keywords WHERE image_id = ?")->execute([$delImgId]);
            $db->prepare("DELETE FROM images WHERE id = ?")->execute([$delImgId]);
            $flashMessage = "画像をプールから削除しました。";
        }

        // 3-D. アイキャッチプールの挙動設定
        if ($op === 'save_pool_settings') {
            $behavior = $_POST['pool_empty_behavior'] ?? 'hold';
            SettingsManager::set('pool_empty_behavior', $behavior);
            $flashMessage = 'アイキャッチプールの自動判定ルール設定を保存しました。';
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

        // 4-F. 定番アンテナ・相互リンクサイトの一括初期追加（相互リンク枠の拡充）
        if ($op === 'seed_popular_trade_sites') {
            require_once __DIR__ . '/classes/MigrationAddFeatures.php';
            $inserted = MigrationAddFeatures::seedInitialTradeSites($db);
            TradeEngine::fetchRssFeeds();
            $flashMessage = "🌟 定番アンテナ・相互リンクサイト（{$inserted}件）を一括登録し、最新RSSフィードを巡回取得しました！";
        }

        // 4-Z. サイト基本設定の更新
        if ($op === 'save_site_settings') {
            $siteName = trim($_POST['site_name'] ?? '');
            $siteDescription = trim($_POST['site_description'] ?? '');
            $siteGenre = trim($_POST['site_genre'] ?? 'general');
            $logoUrl = trim($_POST['logo_url'] ?? '');
            $faviconUrl = trim($_POST['favicon_url'] ?? '');
            $isPublic = isset($_POST['is_public']) ? 1 : 0;
            $allowAutoPublish = isset($_POST['allow_auto_publish']) ? 1 : 0;
            $youtubeThumbnailEnabled = isset($_POST['youtube_thumbnail_enabled']) ? 1 : 0;

            if ($siteName === '') {
                throw new Exception('サイト名を入力してください。');
            }

            $stmt = $db->prepare("UPDATE sites
                SET name = ?, description = ?, genre = ?, logo_url = ?, favicon_url = ?,
                    is_public = ?, allow_auto_publish = ?, youtube_thumbnail_enabled = ?
                WHERE id = 1");
            $stmt->execute([
                $siteName,
                $siteDescription,
                $siteGenre !== '' ? $siteGenre : 'general',
                $logoUrl !== '' ? $logoUrl : null,
                $faviconUrl !== '' ? $faviconUrl : null,
                $isPublic,
                $allowAutoPublish,
                $youtubeThumbnailEnabled
            ]);
            $flashMessage = 'サイト設定を保存しました。';
        }

        // 5. アフィリエイト広告スロット & 個別表示/非表示設定の更新
        if ($op === 'save_ads') {
            SettingsManager::set('show_ads', isset($_POST['show_ads']) ? '1' : '0');
            // ステマ規制法対応 アフィリエイト広告表記 (PR表記)
            SettingsManager::set('affiliate_pr_notice_enabled', isset($_POST['affiliate_pr_notice_enabled']) ? '1' : '0');
            SettingsManager::set('affiliate_pr_notice_text', trim($_POST['affiliate_pr_notice_text'] ?? '当サイトはアフィリエイト広告を利用しています。'));

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

        // 5-3. API設定の更新
        if ($op === 'save_api_settings') {
            SettingsManager::set('gemini_api_key', trim($_POST['gemini_api_key'] ?? ''));
            SettingsManager::set('gemini_model', trim($_POST['gemini_model'] ?? 'gemini-2.5-flash'));
            $flashMessage = 'API設定を保存しました。';
        }

        // 5-3B. システム設定（自動投稿・クーロン）の更新
        if ($op === 'save_system_settings') {
            SettingsManager::set('auto_post_enabled', isset($_POST['auto_post_enabled']) ? '1' : '0');
            SettingsManager::set('auto_post_interval_hours', trim($_POST['auto_post_interval_hours'] ?? '1'));
            SettingsManager::set('auto_post_max_per_day', (int)($_POST['auto_post_max_per_day'] ?? 10));
            SettingsManager::set('auto_post_start_hour', (int)($_POST['auto_post_start_hour'] ?? 8));
            SettingsManager::set('auto_post_end_hour', (int)($_POST['auto_post_end_hour'] ?? 23));
            SettingsManager::set('auto_post_default_status', $_POST['auto_post_default_status'] ?? 'published');
            $flashMessage = 'システム設定を保存しました。';
        }

        // 5-4. 手動キーワードからの即時AI記事自動生成テスト
        if ($op === 'generate_ai_article') {
            $keyword = trim($_POST['keyword'] ?? '');
            $catId = (int)($_POST['category_id'] ?? 1);
            $status = $_POST['status'] ?? 'published';

            if (empty($keyword)) {
                throw new Exception('AIで生成する話題キーワードを入力してください。');
            }
            $apiKey = SettingsManager::get('gemini_api_key');
            if (empty($apiKey)) {
                throw new Exception('Gemini APIキーが未登録です。下記の設定欄にAPIキーを入力して保存してください。');
            }

            require_once __DIR__ . '/classes/AiArticleGenerator.php';
            require_once __DIR__ . '/classes/ShirankedoIndex.php';
            require_once __DIR__ . '/classes/ImageManager.php';
            require_once __DIR__ . '/classes/SafetyBrake.php';

            // 一次ソースの準備
            $verifiedSources = [
                [
                    'source_type' => 'news',
                    'title' => "「{$keyword}」に関する最新報道・公式発表",
                    'publisher' => '大手報道各社・一次情報',
                    'url' => 'https://news.google.com/search?q=' . urlencode($keyword),
                    'reliability_score' => 90
                ]
            ];

            // 安全判定
            $safety = SafetyBrake::audit($keyword, '', $verifiedSources);

            $trendData = [
                'display_keyword' => $keyword,
                'normalized_keyword' => $keyword,
                'keyword' => $keyword,
                'shirankedo_index' => rand(75, 95),
                'is_rapid_rise' => 1,
                'growth_rate' => 2.8,
                'first_detected_at' => date('Y-m-d H:i:s')
            ];

            $generated = AiArticleGenerator::generate(1, $trendData, $verifiedSources);

            // アイキャッチ画像の選定
            $selectedImage = ImageManager::selectBestImage(1, $generated['important_keywords'] ?? [$keyword]);
            $imgUrl = $selectedImage['url'] ?? '';

            $slug = 'trend-' . time() . '-' . rand(100, 999);
            $index = $trendData['shirankedo_index'];
            $label = ShirankedoIndex::getLabel($index);

            // アイキャッチ画像が無い場合は表に出さず保留とする
            $hasImage = !empty($imgUrl);
            $finalStatus = ($safety['needs_hold'] || $status === 'on_hold' || !$hasImage) ? 'on_hold' : 'published';
            $dangerReason = $safety['is_dangerous'] 
                ? ($safety['reason'] ?: 'AI検閲: 危険ワード検知') 
                : (!$hasImage ? 'アイキャッチ画像未設定（画像設定後に表へ公開）' : ($safety['reason'] ?: null));

            $stmt = $db->prepare("INSERT INTO articles 
                (site_id, category_id, title, slug, why_trending, body, conclusion_sentence, 
                 shirankedo_index, index_label, is_rapid_rise, growth_rate, first_detected_at, 
                 image_url, status, is_dangerous, danger_reason, published_at)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, 1, 2.8, NOW(), ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $catId,
                $generated['title'],
                $slug,
                $generated['why_trending'],
                $generated['body'],
                $generated['conclusion'],
                $index,
                $label,
                $imgUrl,
                $finalStatus,
                $safety['is_dangerous'] ? 1 : 0,
                $dangerReason
            ]);
            $newArtId = $db->lastInsertId();

            $statusText = $finalStatus === 'published' ? '公開' : '下書き（保留）';
            $flashMessage = "✨ AI記事「{$generated['title']}」の自動生成が完了し、{$statusText}として保存しました！（記事ID #{$newArtId}）";
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

        // 7-2. データベース接続構成（db_config.php）の更新
        if ($op === 'save_db_config') {
            $dbHost = trim($_POST['db_host'] ?? 'localhost');
            $dbPort = trim($_POST['db_port'] ?? '3306');
            $dbName = trim($_POST['db_name'] ?? '');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';

            if (empty($dbName) || empty($dbUser)) {
                throw new Exception('データベース名とユーザー名は必須です。');
            }

            $testErr = null;
            $testPdo = Database::testConnection($testErr, $dbHost, $dbPort, $dbName, $dbUser, $dbPass);
            if (!$testPdo) {
                throw new Exception("データベース接続テストに失敗しました: " . $testErr);
            }

            if (!Installer::saveDbConfig($dbHost, $dbPort, $dbName, $dbUser, $dbPass)) {
                throw new Exception("php/db_config.php への書き込みに失敗しました。パーミッションをご確認ください。");
            }

            Database::resetConnection();
            $flashMessage = 'データベース接続構成（php/db_config.php）を正常に更新・保存しました。';
        }

        // 8. トレンド自動収集 & AI記事生成ワーカー即時実行
        if ($op === 'run_worker') {
            if (file_exists(__DIR__ . '/cron/worker.php')) {
                $forceExecute = true;
                ob_start();
                include __DIR__ . '/cron/worker.php';
                $out = ob_get_clean();
                $flashMessage = '🚀 <strong>トレンド自動収集＆AI記事生成ワーカーを実行しました！</strong><br><pre class="text-xs mt-2 p-3 bg-stone-900 text-stone-200 rounded-xl max-h-60 overflow-y-auto font-mono text-left leading-relaxed">' . htmlspecialchars($out) . '</pre>';
            } else {
                $flashMessage = 'cron/worker.php が見つかりませんでした。';
                $flashType = 'error';
            }
        }

        // 9. Gemini API 接続診断テスト
        if ($op === 'test_gemini_api') {
            require_once __DIR__ . '/classes/AiArticleGenerator.php';
            $apiKey = trim($_POST['gemini_api_key'] ?? SettingsManager::get('gemini_api_key'));
            $model = trim($_POST['gemini_model'] ?? SettingsManager::get('gemini_model', 'gemini-2.5-flash'));
            if (empty($apiKey)) {
                throw new Exception('Gemini APIキーを入力してください。');
            }
            $testRes = AiArticleGenerator::testApiKey($apiKey, $model);
            if ($testRes['success']) {
                $flashMessage = '✅ ' . htmlspecialchars($testRes['message']);
            } else {
                $flashMessage = '❌ ' . htmlspecialchars($testRes['message']);
                $flashType = 'error';
            }
        }

    } catch (Throwable $e) {
        $flashMessage = 'エラー: ' . $e->getMessage();
        $flashType = 'error';
    }
}

// 各種データ取得
$currentTab = $_GET['tab'] ?? 'dashboard';
if ($currentTab === 'create') {
    $currentTab = 'create_article';
}
if ($currentTab === 'sns' || $currentTab === 'cron') {
    $currentTab = 'system';
}
if ($currentTab === 'gemini') {
    $currentTab = 'api_settings';
}
if ($currentTab === 'advanced' || $currentTab === 'security' || $currentTab === 'seo_tags') {
    $currentTab = 'system';
}

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
$trendCandidates = [];

if ($db && $isLoggedIn) {
    try {
        $totalArticles = (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
        $articles = $db->query("SELECT a.id, a.title, a.slug, a.shirankedo_index, a.index_label, a.status, a.published_at, a.image_url, a.lifecycle_status, a.lifecycle_reason, a.auto_lifecycle_enabled, c.name as category_name FROM articles a LEFT JOIN categories c ON a.category_id = c.id ORDER BY a.id DESC LIMIT 100")->fetchAll();
        $categories = $db->query("SELECT id, name FROM categories WHERE site_id = 1")->fetchAll();
        
        $countActive = 0;
        $countWarning = 0;
        $countDormant = 0;
        $countOnHold = 0;
        $countNoImage = 0;
        foreach ($articles as $art) {
            $st = $art['status'] ?? 'published';
            $ls = $art['lifecycle_status'] ?? 'active';
            $hasImg = !empty($art['image_url']) && trim($art['image_url']) !== '';
            if (!$hasImg) {
                $countNoImage++;
            }
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
        $totalPoolCount = (int)($db->query("SELECT COUNT(*) FROM images WHERE site_id = 1")->fetchColumn() ?: 0);

        // プールが空（0枚）の場合は、自動的に全7大ジャンルの厳選64枚を初期投入する
        if ($totalPoolCount === 0) {
            require_once __DIR__ . '/classes/ImageManager.php';
            ImageManager::seedDefaultPresets(1, 'all');
            $totalPoolCount = (int)($db->query("SELECT COUNT(*) FROM images WHERE site_id = 1")->fetchColumn() ?: 0);
        }

        $poolImages = $db->query("SELECT i.*, 
                                  (SELECT GROUP_CONCAT(keyword SEPARATOR ', ') FROM image_keywords WHERE image_id = i.id) as keywords
                                  FROM images i WHERE i.site_id = 1 ORDER BY i.id DESC LIMIT 100")->fetchAll();

        // お知らせ一覧
        $announcements = $db->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 20")->fetchAll();

        // アクセス解析データ取得
        $analyticsStats = AnalyticsTracker::getStats(14);

        // トレンド候補データ取得
        try {
            $trendCandidates = $db->query("SELECT * FROM trend_candidates WHERE site_id = 1 ORDER BY shirankedo_index DESC LIMIT 50")->fetchAll();
            if (empty($trendCandidates)) {
                require_once __DIR__ . '/classes/TrendCollector.php';
                TrendCollector::collectAndIntegrate(1);
                $trendCandidates = $db->query("SELECT * FROM trend_candidates WHERE site_id = 1 ORDER BY shirankedo_index DESC LIMIT 50")->fetchAll();
            }
        } catch (Throwable $e) {}

    } catch (Throwable $e) {}
}

// システム稼働ステータス用変数
$geminiApiKey = SettingsManager::get('gemini_api_key', '');
$geminiModel = SettingsManager::get('gemini_model', 'gemini-2.5-flash');
if ($geminiModel === 'gemini-2.0-flash' || empty($geminiModel)) {
    $geminiModel = 'gemini-2.5-flash';
    SettingsManager::set('gemini_model', 'gemini-2.5-flash');
}
$hasGeminiKey = !empty($geminiApiKey);

$autoPostEnabled = SettingsManager::get('auto_post_enabled', '1') === '1';
$intervalHours = (float)SettingsManager::get('auto_post_interval_hours', '1');
$maxPerDay = (int)SettingsManager::get('auto_post_max_per_day', '10');
$lastCronTime = SettingsManager::get('last_cron_executed_at');
$lastCronLog = SettingsManager::get('last_cron_log', '');
$lastCronStatus = SettingsManager::get('last_cron_status', '待機中');

$publishedCount = 0;
foreach ($articles as $art) {
    if (($art['status'] ?? 'published') === 'published') {
        $publishedCount++;
    }
}

// 次回自動投稿までの残り時間計算
$lastPostTime = !empty($articles) ? $articles[0]['published_at'] : null;
$minutesSinceLastPost = $lastPostTime ? round((time() - strtotime($lastPostTime)) / 60) : 999;
$requiredMinutes = $intervalHours * 60;
$canPostNextIn = max(0, round($requiredMinutes - $minutesSinceLastPost));

// 親項目・子項目の機能別グループ定義 (ダッシュボードは親と同じ感覚のため単独親メニュー化し、「ホーム・概要」は完全排除)
// サイドバーの項目名と本文ヘッダータイトルは100%完全一致
$navGroups = [
    'content' => [
        'title' => '記事・コンテンツ機能',
        'icon' => '📝',
        'children' => [
            'create_article' => ['icon' => '✍️', 'label' => '記事をつくる (AI・手動)', 'badge' => null],
            'articles' => ['icon' => '📄', 'label' => '記事一覧・管理', 'badge' => $totalArticles ? (string)$totalArticles : null],
            'held_articles' => ['icon' => '🛡️', 'label' => '危険・保留記事の審査', 'badge' => $countOnHold > 0 ? $countOnHold . '件' : null],
            'images' => ['icon' => '🖼️', 'label' => '画像・素材管理', 'badge' => count($poolImages) ? count($poolImages) . '枚' : null],
        ]
    ],
    'analytics' => [
        'title' => 'アクセス解析・分析',
        'icon' => '📈',
        'children' => [
            'analytics' => ['icon' => '📊', 'label' => '高性能アクセス解析', 'badge' => 'LIVE'],
        ]
    ],
    'monetization' => [
        'title' => '収益・提携・集客機能',
        'icon' => '💰',
        'children' => [
            'ads' => ['icon' => '💵', 'label' => 'アフィリエイト・広告設定', 'badge' => null],
            'trade' => ['icon' => '🔗', 'label' => '相互リンク・相互RSS提携', 'badge' => null],
            'trends' => ['icon' => '🔥', 'label' => '急上昇トレンド候補一覧', 'badge' => !empty($trendCandidates) ? count($trendCandidates) . '件' : null],
        ]
    ],
    'system' => [
        'title' => 'システム管理',
        'icon' => '🛠️',
        'children' => [
            'site_settings' => ['icon' => '⚙️', 'label' => 'サイト設定', 'badge' => null],
            'api_settings' => ['icon' => '🔑', 'label' => 'API設定', 'badge' => $hasGeminiKey ? '接続済' : null],
            'system' => ['icon' => '💻', 'label' => 'システム設定', 'badge' => $autoPostEnabled ? '自動運転' : null],
        ]
    ],
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
                            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus placeholder="管理者IDを入力" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm font-mono">
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-700">管理者パスワード</label>
                                <a href="?auth_mode=forgot" class="text-[11px] font-bold text-amber-600 hover:text-amber-700 transition-colors">
                                    パスワードをお忘れですか？
                                </a>
                            </div>
                            <input type="password" name="password" required placeholder="管理者パスワードを入力" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm font-mono">
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
                <div>
                    <div class="text-sm font-black text-white tracking-wide flex items-center gap-2">
                        <span>しらんけど 管理システム</span>
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono">v2.4 Auto-Trend & Trade Engine</div>
                </div>
            </div>
            <div class="flex items-center gap-2.5 text-xs">
                <!-- 知 しらんけど サイトを表示 ↗ (👤 admin でログイン中の左側に配置) -->
                <a href="index.php" target="_blank" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-xs transition-all group">
                    <span class="w-4 h-4 rounded bg-slate-950 text-amber-400 font-black flex items-center justify-center text-[10px] shrink-0">知</span>
                    <span>しらんけど サイトを表示</span>
                    <span class="text-[11px] group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform">↗</span>
                </a>
                <span class="text-slate-400 hidden sm:inline">👤 <strong class="text-slate-200 font-bold"><?= htmlspecialchars($_SESSION['admin_username'] ?? $adminId) ?></strong> でログイン中</span>
                <a href="?logout=1" class="px-3 py-1 rounded-xl bg-slate-800 hover:bg-rose-900/80 text-rose-300 font-bold border border-slate-700 transition-colors">
                    ログアウト
                </a>
            </div>
        </header>

        <!-- メインレイアウト: 左サイドバー + 右コンテンツ -->
        <div class="flex-1 flex flex-col md:flex-row">
            
            <!-- WordPress風 左サイドバー -->
            <aside class="w-full md:w-64 bg-slate-950 text-slate-300 border-r border-slate-800 flex-shrink-0 p-4 space-y-3">
                <!-- ダッシュボード (親と同じ感覚 - グループ見出し「ホーム・概要」や「階層メニュー」は完全排除) -->
                <?php $isDashActive = ($currentTab === 'dashboard'); ?>
                <a href="?tab=dashboard" class="flex items-center justify-between px-3 py-2.5 rounded-2xl text-xs font-bold transition-all <?= $isDashActive ? 'bg-amber-500 text-slate-950 font-black shadow-sm' : 'bg-slate-900 text-slate-300 hover:bg-slate-850 hover:text-white border border-slate-800' ?>">
                    <div class="flex items-center gap-2.5">
                        <span class="text-base">📊</span>
                        <span class="tracking-tight">ダッシュボード</span>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isDashActive ? 'bg-slate-950 text-amber-300' : 'bg-slate-800 text-emerald-400 border border-slate-700' ?>">
                        稼働中
                    </span>
                </a>

                <nav class="space-y-2.5">
                    <?php foreach ($navGroups as $grpKey => $grp): 
                        $hasActive = array_key_exists($currentTab, $grp['children']);
                    ?>
                        <div class="rounded-2xl overflow-hidden bg-slate-900/90 border <?= $hasActive ? 'border-amber-500/40' : 'border-slate-800/80' ?>">
                            <!-- 親項目ヘッダー -->
                            <div class="flex items-center justify-between px-3 py-2 text-xs font-black <?= $hasActive ? 'text-amber-400 bg-amber-500/10 border-l-2 border-amber-400' : 'text-slate-400 bg-slate-900/80' ?>">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm"><?= $grp['icon'] ?></span>
                                    <span><?= htmlspecialchars($grp['title']) ?></span>
                                </div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-400 font-mono">
                                    <?= count($grp['children']) ?>項目
                                </span>
                            </div>
                            <!-- 子項目リスト -->
                            <div class="p-1 space-y-0.5 bg-slate-950/70 border-t border-slate-900">
                                <?php foreach ($grp['children'] as $tabKey => $t): 
                                    $isActive = $currentTab === $tabKey;
                                    $btnClass = $isActive 
                                        ? 'bg-amber-500 text-slate-950 font-black shadow-sm' 
                                        : 'text-slate-300 hover:bg-slate-800/80 hover:text-white font-medium';
                                ?>
                                    <a href="?tab=<?= $tabKey ?>" class="flex items-center justify-between pl-3 pr-2.5 py-2 rounded-xl text-xs transition-all <?= $btnClass ?>">
                                        <div class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-slate-950' : 'bg-slate-600' ?>"></span>
                                            <span class="text-sm"><?= $t['icon'] ?></span>
                                            <span class="tracking-tight"><?= htmlspecialchars($t['label']) ?></span>
                                        </div>
                                        <?php if (!empty($t['badge'])): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isActive ? 'bg-slate-950 text-amber-300' : 'bg-slate-800 text-slate-400' ?>">
                                                <?= $t['badge'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </nav>

            </aside>

            <!-- 右側メインコンテンツパネル -->
            <main class="flex-1 p-4 sm:p-8 space-y-6 overflow-x-hidden">

                <!-- フラッシュ通知メッセージ -->
                <?php if ($flashMessage): ?>
                    <div class="p-4 rounded-2xl text-xs sm:text-sm font-bold shadow-sm border <?= $flashType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900' ?>">
                        <?= $flashMessage ?>
                    </div>
                <?php endif; ?>

                <!-- 1. ダッシュボード タブ (初心者にも直感的なコントロールセンター) -->
                <?php if ($currentTab === 'dashboard'): ?>
                    <div class="space-y-6">
                        <!-- ダッシュボードヘッダー -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">📊</span>
                                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">ダッシュボード</h1>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        ● 稼働中
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500">
                                    「しらんけど」のAI自動執筆・クーロン稼働状態と記事の運用状況を確認できます
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a href="index.php" target="_blank" class="px-4 py-2.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5">
                                    <span>🌐 表のサイトを見る</span>
                                    <span>↗</span>
                                </a>
                            </div>
                        </div>

                        <!-- 🚦 4大リアルタイム診断カード (システム状態がひと目でわかるランプ) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- 1. Gemini AI ステータス -->
                            <div class="bg-white rounded-3xl border <?= $hasGeminiKey ? 'border-indigo-100 bg-indigo-50/20' : 'border-rose-200 bg-rose-50/40' ?> p-5 shadow-xs flex flex-col justify-between space-y-3">
                                <div class="flex items-start justify-between">
                                    <div class="space-y-1">
                                        <div class="text-[11px] font-bold text-slate-400">AI記事執筆エンジン</div>
                                        <div class="text-sm font-black text-slate-900 flex items-center gap-1.5">
                                            <span>🤖</span>
                                            <span>Gemini AI</span>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $hasGeminiKey ? 'bg-indigo-100 text-indigo-800' : 'bg-rose-100 text-rose-700' ?>">
                                        <?= $hasGeminiKey ? '🟢 接続中' : '🔴 未設定' ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-600 space-y-1">
                                    <div>モデル: <span class="font-mono font-bold text-slate-800"><?= htmlspecialchars($geminiModel) ?></span></div>
                                    <div class="text-[11px] text-slate-400 truncate">キー: <?= $hasGeminiKey ? 'AIza...****' . substr($geminiApiKey, -4) : '未入力' ?></div>
                                </div>
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <?php if ($hasGeminiKey): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="op" value="test_gemini_api">
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-800 font-bold hover:underline cursor-pointer">
                                                🔍 接続テスト
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="?tab=api_settings" class="text-slate-500 hover:text-slate-800 font-bold ml-auto">設定変更 →</a>
                                </div>
                            </div>

                            <!-- 2. 自動投稿 (クーロン) ステータス -->
                            <div class="bg-white rounded-3xl border <?= $autoPostEnabled ? 'border-emerald-100 bg-emerald-50/20' : 'border-amber-200 bg-amber-50/40' ?> p-5 shadow-xs flex flex-col justify-between space-y-3">
                                <div class="flex items-start justify-between">
                                    <div class="space-y-1">
                                        <div class="text-[11px] font-bold text-slate-400">定期実行・クーロン</div>
                                        <div class="text-sm font-black text-slate-900 flex items-center gap-1.5">
                                            <span>⏰</span>
                                            <span>自動投稿スケジュール</span>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $autoPostEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                                        <?= $autoPostEnabled ? '🟢 稼働中' : '⏸️ 停止中' ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-600 space-y-1">
                                    <div>投稿間隔: <span class="font-bold text-slate-800"><?= $intervalHours ?>時間ごと</span> (1日最大<?= $maxPerDay ?>本)</div>
                                    <div class="text-[11px] text-slate-500">
                                        <?php if ($canPostNextIn > 0): ?>
                                            次回可能まで: <span class="font-bold text-amber-700">あと約<?= $canPostNextIn ?>分</span>
                                        <?php else: ?>
                                            次回投稿: <span class="font-bold text-emerald-700">いつでも即時可能</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400 text-[10px] truncate">最終: <?= $lastCronTime ? date('H:i', strtotime($lastCronTime)) : '未記録' ?></span>
                                    <a href="?tab=system" class="text-slate-500 hover:text-slate-800 font-bold ml-auto">間隔調整 →</a>
                                </div>
                            </div>

                            <!-- 3. アイキャッチ画像プール -->
                            <div class="bg-white rounded-3xl border <?= count($poolImages) > 0 ? 'border-amber-100 bg-amber-50/20' : 'border-rose-200 bg-rose-50/40' ?> p-5 shadow-xs flex flex-col justify-between space-y-3">
                                <div class="flex items-start justify-between">
                                    <div class="space-y-1">
                                        <div class="text-[11px] font-bold text-slate-400">画像自動マッチング</div>
                                        <div class="text-sm font-black text-slate-900 flex items-center gap-1.5">
                                            <span>🖼️</span>
                                            <span>アイキャッチ画像</span>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $totalPoolCount > 0 ? 'bg-amber-100 text-amber-900' : 'bg-rose-100 text-rose-700' ?>">
                                        <?= $totalPoolCount > 0 ? $totalPoolCount . '枚 登録済' : '⚠️ 0枚' ?>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-600 space-y-1">
                                    <div>選定方式: <span class="font-bold text-slate-800">AIキーワード照合 (部分一致対応)</span></div>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $totalPoolCount > 0 ? '記事の話題に合った画像を選定' : '※空の場合は予備画像が使われます' ?>
                                    </div>
                                </div>
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="op" value="seed_preset_images">
                                        <input type="hidden" name="genre" value="all">
                                        <button type="submit" class="text-amber-700 hover:text-amber-900 font-bold hover:underline cursor-pointer flex items-center gap-1">
                                            🎁 厳選64枚を一括追加
                                        </button>
                                    </form>
                                    <a href="?tab=images" class="text-slate-500 hover:text-slate-800 font-bold ml-auto">画像一覧 →</a>
                                </div>
                            </div>

                            <!-- 4. 公開記事数 -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-xs flex flex-col justify-between space-y-3">
                                <div class="flex items-start justify-between">
                                    <div class="space-y-1">
                                        <div class="text-[11px] font-bold text-slate-400">サイトコンテンツ</div>
                                        <div class="text-sm font-black text-slate-900 flex items-center gap-1.5">
                                            <span>📝</span>
                                            <span>公開記事数</span>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                                        総数 <?= $totalArticles ?> 本
                                    </span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <div class="text-3xl font-black text-slate-900"><?= $publishedCount ?></div>
                                    <div class="text-xs font-bold text-emerald-600">本 公開中</div>
                                    <?php if ($countOnHold > 0): ?>
                                        <div class="text-xs text-slate-400 font-medium ml-auto">(下書き: <?= $countOnHold ?>本)</div>
                                    <?php endif; ?>
                                </div>
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="text-slate-400 text-[10px]">客観ファクトまとめ</span>
                                    <a href="?tab=articles" class="text-slate-500 hover:text-slate-800 font-bold ml-auto">全記事一覧 →</a>
                                </div>
                            </div>
                        </div>

                        <!-- 📋 「なぜ自動で記事が増えないか」がすぐ分かる診断・実行ログカード -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">📋</span>
                                        <h3 class="text-sm font-black text-slate-900">自動投稿（クーロン）の直近の稼働診断</h3>
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        クーロンが実行された際の動作状況や、スキップ（待機中）の理由を確認できます
                                    </p>
                                </div>
                                <div class="text-[11px] text-slate-400 font-mono">
                                    最終実行日時: <?= $lastCronTime ? htmlspecialchars($lastCronTime) : '未実行（待機中）' ?>
                                </div>
                            </div>

                            <!-- 親切な日本語の状況説明 -->
                            <?php if ($canPostNextIn > 0 && $lastPostTime): ?>
                                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
                                    <span class="text-xl">ℹ️</span>
                                    <div class="text-xs text-amber-950 space-y-1">
                                        <strong class="font-black text-amber-900 block">自動投稿は「正常に待機中」です</strong>
                                        前回の記事投稿（<?= substr($lastPostTime, 11, 5) ?>）からまだ <span class="font-bold text-amber-900"><?= $minutesSinceLastPost ?>分</span> しか経過していません。<br>
                                        現在の設定間隔は「<span class="font-bold text-amber-900"><?= $intervalHours ?>時間ごと</span>」のため、次回の自動生成まで <span class="font-bold text-amber-900">あと約<?= $canPostNextIn ?>分</span> 待機します。<br>
                                        <span class="text-amber-800 text-[11px]">※ すぐに記事を増やしたい場合は、上の「⚡ ワンクリック記事生成」ボタンを押すと待機時間をバイパスして即座に記事が作成されます。</span>
                                    </div>
                                </div>
                            <?php elseif (!$hasGeminiKey): ?>
                                <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 flex items-start gap-3">
                                    <span class="text-xl">⚠️</span>
                                    <div class="text-xs text-rose-950 space-y-1">
                                        <strong class="font-black text-rose-900 block">Gemini APIキーが設定されていません</strong>
                                        記事を自動生成するにはGemini APIキーが必要です。<a href="?tab=api_settings" class="underline font-bold text-rose-800">「API設定」</a>でキーを入力してください。
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3">
                                    <span class="text-xl">✅</span>
                                    <div class="text-xs text-emerald-950 space-y-1">
                                        <strong class="font-black text-emerald-900 block">システムはいつでも次回の自動投稿が可能な状態です</strong>
                                        投稿間隔の条件を満たしており、クーロンが巡回した際に自動で最新トレンド記事が生成されます。
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- 直近のログ詳細 (折りたたみ) -->
                            <?php if (!empty($lastCronLog)): ?>
                                <details class="group bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                    <summary class="text-xs font-bold text-slate-700 cursor-pointer flex items-center justify-between">
                                        <span class="flex items-center gap-1.5">
                                            <span>💻</span> <span>クーロンの詳細実行ログを表示</span>
                                        </span>
                                        <span class="text-slate-400 group-open:hidden text-[11px]">クリックで開く ▾</span>
                                        <span class="text-slate-400 hidden group-open:inline text-[11px]">閉じる ▴</span>
                                    </summary>
                                    <div class="mt-3 pt-3 border-t border-slate-200 font-mono text-[11px] bg-slate-950 text-slate-200 p-4 rounded-xl max-h-48 overflow-y-auto leading-relaxed">
                                        <?= nl2br(htmlspecialchars($lastCronLog)) ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>

                        <!-- 🔰 「初心者スタートアップ・ガイド（やることナビ）」 -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-4">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🔰</span>
                                <h3 class="text-sm font-black text-slate-900">はじめての運用ガイド（やることチェックリスト）</h3>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                                <!-- ステップ1 -->
                                <div class="p-4 rounded-2xl border border-emerald-200 bg-emerald-50/40 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-500 text-[10px]">ステップ 1</span>
                                        <span class="text-emerald-700 font-black">✓ 完了</span>
                                    </div>
                                    <div class="font-black text-slate-900">サイト初期構成</div>
                                    <p class="text-[11px] text-slate-500">データベース・カテゴリ初期化は完了しています。</p>
                                </div>

                                <!-- ステップ2 -->
                                <div class="p-4 rounded-2xl border <?= $hasGeminiKey ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40' ?> space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-500 text-[10px]">ステップ 2</span>
                                        <span class="<?= $hasGeminiKey ? 'text-emerald-700' : 'text-amber-700' ?> font-black">
                                            <?= $hasGeminiKey ? '✓ 完了' : '👉 設定中' ?>
                                        </span>
                                    </div>
                                    <div class="font-black text-slate-900">Gemini APIキー設定</div>
                                    <p class="text-[11px] text-slate-500">
                                        <?= $hasGeminiKey ? 'キー登録済み（接続OK）' : '<a href="?tab=api_settings" class="underline text-amber-700 font-bold">キーを登録してください</a>' ?>
                                    </p>
                                </div>

                                <!-- ステップ3 -->
                                <div class="p-4 rounded-2xl border <?= count($poolImages) > 0 ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40' ?> space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-500 text-[10px]">ステップ 3</span>
                                        <span class="<?= count($poolImages) > 0 ? 'text-emerald-700' : 'text-amber-700' ?> font-black">
                                            <?= count($poolImages) > 0 ? '✓ 準備完了' : '👉 おすすめ' ?>
                                        </span>
                                    </div>
                                    <div class="font-black text-slate-900">アイキャッチ画像の準備</div>
                                    <p class="text-[11px] text-slate-500">
                                        <?php if (count($poolImages) > 0): ?>
                                            現在 <?= count($poolImages) ?>枚 登録済み
                                        <?php else: ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="op" value="seed_preset_images">
                                                <button type="submit" class="underline text-amber-700 font-bold cursor-pointer">
                                                    🎁 12枚一括追加する
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <!-- ステップ4 -->
                                <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-500 text-[10px]">ステップ 4</span>
                                        <span class="text-indigo-600 font-black">収益化へ</span>
                                    </div>
                                    <div class="font-black text-slate-900">広告タグの配置</div>
                                    <p class="text-[11px] text-slate-500">
                                        記事が増えたら <a href="?tab=ads" class="underline text-indigo-600 font-bold">広告タブ</a> からタグを貼るだけです。
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 📝 最新記事一覧クイックプレビュー (直近10件) -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">📝</span>
                                    <h2 class="text-base font-black text-slate-900">公開中・最新記事 (直近10件)</h2>
                                </div>
                                <a href="?tab=articles" class="text-xs text-amber-600 hover:text-amber-700 font-bold">すべて見る (<?= $totalArticles ?>件) →</a>
                            </div>

                            <?php if (empty($articles)): ?>
                                <div class="py-12 text-center space-y-3">
                                    <div class="text-3xl">📝</div>
                                    <p class="text-xs font-bold text-slate-500">まだ記事が作成されていません。</p>
                                    <p class="text-[11px] text-slate-400">上の「ワンクリック記事生成」ボタンを押すと、AIが数秒で最初の記事を作成・公開します！</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-100 text-slate-400">
                                                <th class="py-2.5 font-bold w-14">画像</th>
                                                <th class="py-2.5 font-bold">記事タイトル</th>
                                                <th class="py-2.5 font-bold">カテゴリ</th>
                                                <th class="py-2.5 font-bold">しらんけど指数</th>
                                                <th class="py-2.5 font-bold">状態</th>
                                                <th class="py-2.5 font-bold">公開日時</th>
                                                <th class="py-2.5 font-bold text-right">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <?php foreach (array_slice($articles, 0, 10) as $a): 
                                                $hasImg = !empty($a['image_url']) && trim($a['image_url']) !== '';
                                            ?>
                                                <tr class="hover:bg-slate-50 transition-colors">
                                                    <!-- サムネイル -->
                                                    <td class="py-3">
                                                        <div class="w-12 h-8 rounded-lg overflow-hidden bg-slate-100 border border-slate-200">
                                                            <?php if ($hasImg): ?>
                                                                <img src="<?= htmlspecialchars($a['image_url']) ?>" class="w-full h-full object-cover" alt="">
                                                            <?php else: ?>
                                                                <div class="w-full h-full flex items-center justify-center text-[10px] text-slate-400">No Img</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>

                                                    <!-- タイトル -->
                                                    <td class="py-3 font-bold text-slate-900 max-w-xs">
                                                        <a href="article.php?id=<?= $a['id'] ?>" target="_blank" class="hover:text-amber-600 line-clamp-1">
                                                            <?= htmlspecialchars($a['title']) ?> ↗
                                                        </a>
                                                    </td>

                                                    <!-- カテゴリ -->
                                                    <td class="py-3 text-slate-500 whitespace-nowrap">
                                                        <?= htmlspecialchars($a['category_name'] ?? '総合') ?>
                                                    </td>

                                                    <!-- 指数 -->
                                                    <td class="py-3 whitespace-nowrap">
                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                            <?= $a['shirankedo_index'] ?>点
                                                        </span>
                                                    </td>

                                                    <!-- ステータス -->
                                                    <td class="py-3 whitespace-nowrap">
                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold <?= $a['status'] === 'published' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' ?>">
                                                            <?= $a['status'] === 'published' ? '公開中' : '下書き' ?>
                                                        </span>
                                                    </td>

                                                    <!-- 日時 -->
                                                    <td class="py-3 text-slate-400 whitespace-nowrap text-[11px]">
                                                        <?= substr($a['published_at'] ?? '', 5, 11) ?>
                                                    </td>

                                                    <!-- 操作 -->
                                                    <td class="py-3 text-right whitespace-nowrap space-x-1">
                                                        <?php if ($a['status'] === 'published'): 
                                                            $dashArtUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'shirankedo.bichi.xyz') . dirname($_SERVER['PHP_SELF']) . '/article.php?id=' . $a['id'];
                                                            $dashThreadsUrl = 'https://www.threads.net/intent/post?text=' . urlencode("【話題度: {$a['shirankedo_index']}/100】" . $a['title'] . "\n" . $dashArtUrl . "\n#しらんけど");
                                                        ?>
                                                            <a href="<?= htmlspecialchars($dashThreadsUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-2 py-1 rounded-lg text-[10px] font-black bg-slate-950 hover:bg-slate-800 text-white inline-flex items-center gap-1 shadow-xs" title="Threadsにシェア投稿する">
                                                                <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 192 192"><path d="M141.537 88.9883C140.71 88.5919 139.87 88.2104 139.019 87.8451C137.537 60.5382 122.616 44.905 97.5619 44.745C97.4484 44.7443 97.3355 44.7443 97.222 44.7443C82.2364 44.7443 69.7731 51.1409 62.102 62.7807L75.381 72.8229C80.7061 64.7176 89.4312 60.4851 100.865 60.4851C117.828 60.4851 123.633 74.4447 124.636 93.9669C116.892 92.4285 107.575 92.0569 96.6853 92.8523C64.9048 95.1769 46.103 111.455 46.8974 133.407C47.3789 146.708 55.4377 156.456 68.3216 159.298C81.8282 162.277 96.671 158.468 107.971 149.034C114.382 143.682 119.049 136.634 121.737 128.291C127.02 138.835 136.037 146.077 149.207 147.452C165.65 149.172 178.683 140.75 183.084 125.753C188.082 108.72 177.345 92.4638 159.224 88.0934C154.218 86.8863 148.067 87.3229 141.537 88.9883ZM108.647 132.884C102.133 138.086 92.5936 142.062 82.5936 139.863C73.4936 137.863 68.3936 130.663 68.0936 120.363C67.5936 103.563 80.4936 90.763 108.647 88.684V132.884Z"/></svg>
                                                                <span>Threads</span>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="?tab=articles" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 inline-block">
                                                            編集
                                                        </a>
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
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- 2. 📝 記事一覧・ページの生死判定 (AI自動ライフサイクル管理) タブ -->
                <?php elseif ($currentTab === 'create_article'): ?>
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                            <div>
                                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                                    <span>✍️</span>
                                    <span>記事をつくる (AI・手動)</span>
                                </h1>
                                <p class="text-xs text-slate-500 mt-1">
                                    最新トレンドからの自動生成、キーワード指定のAI生成、トレンド収集をここから実行できます。
                                </p>
                            </div>
                            <form method="POST" class="shrink-0">
                                <input type="hidden" name="op" value="run_worker">
                                <button type="submit" class="px-5 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all flex items-center gap-1.5">
                                    <span>🚀 トレンド自動収集＆AI記事生成を今すぐ実行</span>
                                </button>
                            </form>
                        </div>

                        <!-- ⚡ 【今すぐAIに記事を作らせる】ワンクリック操作ボックス (Hero Box) -->
                        <div class="bg-gradient-to-br from-amber-500/10 via-amber-400/5 to-transparent rounded-3xl border-2 border-amber-300/80 p-6 sm:p-8 shadow-sm space-y-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-amber-200/60 pb-4">
                                <div class="space-y-1">
                                    <div class="text-xs font-black text-amber-900 flex items-center gap-1.5">
                                        <span class="text-lg">⚡</span>
                                        <span>【超かんたん】今すぐ記事を増やしたいときはここ！</span>
                                    </div>
                                    <h2 class="text-lg sm:text-xl font-black text-slate-900">ワンクリック記事生成（2つの作成方法）</h2>
                                </div>
                                <span class="px-3 py-1 rounded-full bg-amber-500 text-slate-950 font-black text-[11px] shadow-2xs self-start sm:self-auto">
                                    数十秒で即座に公開完了
                                </span>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- 方法A: 完全おまかせ（最新トレンドから1本自動生成） -->
                                <div class="bg-white p-6 rounded-2xl border border-amber-200/80 shadow-xs flex flex-col justify-between space-y-4">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-900 text-xs font-black flex items-center justify-center">1</span>
                                            <h3 class="text-sm font-black text-slate-900">完全自動: 最新急上昇トレンドから生成</h3>
                                        </div>
                                        <p class="text-xs text-slate-600 leading-relaxed">
                                            Googleトレンドからいま日本で一番話題のキーワードをAIが自動取得し、一次情報を調べて記事を1本執筆・公開します。
                                        </p>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="op" value="run_worker">
                                        <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer active:scale-98">
                                            <span class="text-base">🚀</span>
                                            <span>最新トレンドから記事を1本今すぐ自動生成</span>
                                        </button>
                                    </form>
                                </div>

                                <!-- 方法B: キーワード指定（好きな有名人・商品・ニュース） -->
                                <div class="bg-white p-6 rounded-2xl border border-amber-200/80 shadow-xs flex flex-col justify-between space-y-4">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-900 text-xs font-black flex items-center justify-center">2</span>
                                            <h3 class="text-sm font-black text-slate-900">キーワード指定: 好きな話題で即座に執筆</h3>
                                        </div>
                                        <p class="text-xs text-slate-600 leading-relaxed">
                                            気になるキーワード（例: 大谷翔平、千鳥、iPhone 16 など）を入力するだけで、AIが一次情報を整理して記事にします。
                                        </p>
                                    </div>
                                    <form method="POST" class="space-y-3">
                                        <input type="hidden" name="op" value="generate_ai_article">
                                        <input type="hidden" name="status" value="published">
                                        <div class="flex gap-2">
                                            <input type="text" name="keyword" required placeholder="例: 大谷翔平、千鳥、iPhone 16..." class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-bold focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 bg-white">
                                            <select name="category_id" class="px-2.5 py-2 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-700">
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                            <span>✨</span>
                                            <span>このキーワードで記事を作成・公開する</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>


                    </div>

                        <!-- 手動記事作成 -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
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

                <?php elseif ($currentTab === 'articles'): ?>
                    <div class="space-y-6">
                        <!-- ヘッダーと一括AI判定ボタン -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                                    <span>📝</span> 記事一覧・管理
                                </h1>
                                <p class="text-xs text-slate-500 mt-1">
                                    ページの生死は基本的にAIが自動判定（鮮度・検索需要・読者投票・安全ブレーキを総合評価）。需要終息記事は自動休眠（非公開）へ移行します。
                                </p>
                            </div>
                        </div>

                        <!-- 稼働ステータスインフォバー -->
                        <?php 
                        $lastCron = SettingsManager::get('last_cron_executed_at');
                        $geminiKey = SettingsManager::get('gemini_api_key');
                        $geminiStatus = SettingsManager::get('gemini_last_status');
                        ?>
                        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                            <div class="flex flex-wrap items-center gap-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-500">Cron自動実行:</span>
                                    <?php if (!empty($lastCron)): ?>
                                        <span class="inline-flex items-center gap-1 text-emerald-700 font-bold bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                            最終実行: <?= htmlspecialchars($lastCron) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-amber-700 font-bold bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200">
                                            ⚠️ サーバーCron未検知（上の「🚀 今すぐ実行」ボタンで手動テスト可能）
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-500">Gemini AI:</span>
                                    <?php if (!empty($geminiKey)): ?>
                                        <span class="text-indigo-700 font-bold bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200">
                                            ✓ APIキー設定済 (<?= htmlspecialchars(SettingsManager::get('gemini_model', 'gemini-2.5-flash')) ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-rose-700 font-bold bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-200">
                                            ⚠️ APIキー未設定
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="?tab=api_settings" class="text-amber-700 hover:text-amber-800 font-bold flex items-center gap-1 self-start md:self-auto hover:underline text-[11px]">
                                <span>⚙️ スケジュール・API設定を開く →</span>
                            </a>
                        </div>

                        <!-- ページの生死 & アイキャッチ設定サマリーカード -->
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                            <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                                    <span>🟢 生存・公開中</span>
                                    <span class="text-xs">良好</span>
                                </div>
                                <div class="text-2xl font-black text-emerald-600"><?= $countActive ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-slate-400">需要継続・鮮度良好</div>
                            </div>

                            <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                                    <span>🟡 鮮度注意</span>
                                    <span class="text-xs">要観察</span>
                                </div>
                                <div class="text-2xl font-black text-amber-500"><?= $countWarning ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-slate-400">公開14日経過 / 懐疑投票有</div>
                            </div>

                            <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                                    <span>🔴 AI自動休眠</span>
                                    <span class="text-xs">非公開</span>
                                </div>
                                <div class="text-2xl font-black text-rose-600"><?= $countDormant ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-slate-400">トレンド終息のためAI休眠</div>
                            </div>

                            <div class="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-slate-500 flex items-center justify-between">
                                    <span>⚠️ 安全保留</span>
                                    <span class="text-xs">下書き</span>
                                </div>
                                <div class="text-2xl font-black text-purple-600"><?= $countOnHold ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-slate-400">危険キーワード等検知</div>
                            </div>

                            <div class="bg-white rounded-3xl p-4 sm:p-5 border border-amber-200 bg-amber-50/20 shadow-sm space-y-1">
                                <div class="text-xs font-bold text-amber-900 flex items-center justify-between">
                                    <span>🖼️ 画像未設定</span>
                                    <span class="text-[10px] font-bold text-rose-600">表に非公開</span>
                                </div>
                                <div class="text-2xl font-black text-amber-600"><?= $countNoImage ?> <span class="text-xs font-normal text-slate-400">記事</span></div>
                                <div class="text-[11px] text-amber-800">アイキャッチ設定待ち</div>
                            </div>
                        </div>

                        <?php if ($countNoImage > 0): ?>
                            <!-- アイキャッチ未設定記事の通知バナー -->
                            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-lg">🖼️</span>
                                    <div>
                                        <span class="font-black">アイキャッチ画像が未設定の記事が <?= $countNoImage ?> 件あります</span>
                                        <p class="text-[11px] text-amber-800 mt-0.5">
                                            ご指定のルールに従い、アイキャッチ画像が設定されていない記事は<strong>表のトップページや記事一覧には表示されません</strong>。下のボタンからアイキャッチ画像を設定すると、即座に表へ公開されます。
                                        </p>
                                    </div>
                                </div>
                                <button type="button" onclick="filterByNoImage()" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-[11px] shadow-xs whitespace-nowrap self-start sm:self-auto">
                                    画像未設定のみ絞り込み
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- 記事一覧テーブル (検索・フィルタ機能付き) -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-slate-900 text-sm">全記事リスト (計 <?= count($articles) ?> 件)</span>
                                    <div class="flex items-center gap-1.5 ml-3">
                                        <button type="button" onclick="setArticleFilter('all')" class="filter-tab-btn active px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 text-white" data-filter="all">すべて</button>
                                        <button type="button" onclick="setArticleFilter('no_image')" class="filter-tab-btn px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 hover:bg-slate-200" data-filter="no_image">🖼️ 画像未設定 (<?= $countNoImage ?>)</button>
                                        <button type="button" onclick="setArticleFilter('published')" class="filter-tab-btn px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 hover:bg-slate-200" data-filter="published">🟢 表に公開中 (<?= $countActive ?>)</button>
                                        <button type="button" onclick="setArticleFilter('on_hold')" class="filter-tab-btn px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 hover:bg-slate-200" data-filter="on_hold">⚠️ 保留・下書き (<?= $countOnHold ?>)</button>
                                    </div>
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
                                            <th class="py-2.5 text-right">アイキャッチ / ステータス操作</th>
                                        </tr>
                                    </thead>
                                    <tbody id="articles-tbody" class="divide-y divide-slate-100">
                                        <?php foreach ($articles as $a): 
                                            $pubTime = strtotime($a['published_at'] ?? 'now');
                                            $daysOld = max(0, round((time() - $pubTime) / 86400));
                                            $ls = $a['lifecycle_status'] ?? 'active';
                                            $status = $a['status'] ?? 'published';
                                            $autoEnabled = (int)($a['auto_lifecycle_enabled'] ?? 1);
                                            $hasImg = !empty($a['image_url']) && trim($a['image_url']) !== '';

                                            if (!$hasImg) {
                                                $badgeText = '🖼️ 画像未設定 (表に非表示)';
                                                $badgeClass = 'bg-rose-50 text-rose-800 border-rose-300';
                                                $filterCategory = 'no_image';
                                            } elseif ($status === 'on_hold') {
                                                $badgeText = '⚠️ 安全保留';
                                                $badgeClass = 'bg-purple-50 text-purple-800 border-purple-200';
                                                $filterCategory = 'on_hold';
                                            } elseif ($ls === 'dormant' || $status === 'private') {
                                                $badgeText = '🔴 休眠 (非公開)';
                                                $badgeClass = 'bg-rose-50 text-rose-800 border-rose-200';
                                                $filterCategory = 'dormant';
                                            } elseif ($ls === 'warning') {
                                                $badgeText = '🟡 鮮度低下注意';
                                                $badgeClass = 'bg-amber-50 text-amber-800 border-amber-200';
                                                $filterCategory = 'published';
                                            } else {
                                                $badgeText = '🟢 生存 (公開中)';
                                                $badgeClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';
                                                $filterCategory = 'published';
                                            }
                                        ?>
                                            <tr class="hover:bg-slate-50 article-row" data-filter-type="<?= $filterCategory ?>" data-has-img="<?= $hasImg ? '1' : '0' ?>" data-search="<?= htmlspecialchars(mb_strtolower($a['title'] . ' ' . ($a['lifecycle_reason'] ?? ''))) ?>">
                                                <td class="py-3">
                                                    <div class="w-12 h-8 rounded-lg bg-slate-200 overflow-hidden border border-slate-200 relative group cursor-pointer" onclick="openSetImageModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['title']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($a['image_url'] ?? ''), ENT_QUOTES) ?>')">
                                                        <?php if ($hasImg): ?>
                                                            <img src="<?= htmlspecialchars($a['image_url']) ?>" alt="" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full flex flex-col items-center justify-center text-[9px] font-bold text-rose-600 bg-rose-50">未設定</div>
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

                                                <td class="py-3 text-right whitespace-nowrap space-x-1.5">
                                                    <?php if (!$hasImg): ?>
                                                        <!-- アイキャッチ未設定の場合の優先ボタン -->
                                                        <button type="button" onclick="openSetImageModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['title']), ENT_QUOTES) ?>', '')" class="px-2.5 py-1.5 rounded-xl text-xs font-black bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition-all inline-flex items-center gap-1">
                                                            <span>🖼️ 画像設定・即時公開</span>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" onclick="openSetImageModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['title']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($a['image_url'] ?? ''), ENT_QUOTES) ?>')" class="px-2 py-1 rounded-xl text-[11px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-all inline-flex items-center gap-1" title="アイキャッチ画像を変更">
                                                            <span>🖼️ 変更</span>
                                                        </button>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="op" value="toggle_article_status">
                                                            <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                                            <input type="hidden" name="new_status" value="<?= $status === 'published' ? 'private' : 'published' ?>">
                                                            <button type="submit" class="px-2.5 py-1 rounded-xl text-xs font-bold transition-colors <?= $status === 'published' ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200' ?>">
                                                                <?= $status === 'published' ? '非公開へ' : '公開へ' ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if ($status === 'published'): 
                                                        $targetArtUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'shirankedo.bichi.xyz') . dirname($_SERVER['PHP_SELF']) . '/article.php?id=' . $a['id'];
                                                        $threadsIntentUrl = 'https://www.threads.net/intent/post?text=' . urlencode("【話題度: {$a['shirankedo_index']}/100】" . $a['title'] . "\n" . $targetArtUrl . "\n#しらんけど");
                                                    ?>
                                                        <a href="<?= htmlspecialchars($threadsIntentUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1.5 rounded-xl text-xs font-black bg-slate-950 hover:bg-slate-800 text-white shadow-xs transition-all inline-flex items-center gap-1 cursor-pointer" title="Threadsにシェア投稿する">
                                                            <svg class="w-3 h-3 fill-current" viewBox="0 0 192 192"><path d="M141.537 88.9883C140.71 88.5919 139.87 88.2104 139.019 87.8451C137.537 60.5382 122.616 44.905 97.5619 44.745C97.4484 44.7443 97.3355 44.7443 97.222 44.7443C82.2364 44.7443 69.7731 51.1409 62.102 62.7807L75.381 72.8229C80.7061 64.7176 89.4312 60.4851 100.865 60.4851C117.828 60.4851 123.633 74.4447 124.636 93.9669C116.892 92.4285 107.575 92.0569 96.6853 92.8523C64.9048 95.1769 46.103 111.455 46.8974 133.407C47.3789 146.708 55.4377 156.456 68.3216 159.298C81.8282 162.277 96.671 158.468 107.971 149.034C114.382 143.682 119.049 136.634 121.737 128.291C127.02 138.835 136.037 146.077 149.207 147.452C165.65 149.172 178.683 140.75 183.084 125.753C188.082 108.72 177.345 92.4638 159.224 88.0934C154.218 86.8863 148.067 87.3229 141.537 88.9883ZM108.647 132.884C102.133 138.086 92.5936 142.062 82.5936 139.863C73.4936 137.863 68.3936 130.663 68.0936 120.363C67.5936 103.563 80.4936 90.763 108.647 88.684V132.884Z"/></svg>
                                                            <span>Threads</span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <form method="POST" class="inline" onsubmit="return confirm('記事「<?= htmlspecialchars(addslashes($a['title']), ENT_QUOTES) ?>」を完全に削除しますか？');">
                                                        <input type="hidden" name="op" value="delete_article">
                                                        <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                                        <button type="submit" class="px-2 py-1 rounded-xl text-xs font-bold text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="記事を完全に削除">
                                                            🗑️ 削除
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- アイキャッチ設定 & 即時公開モーダル -->
                        <div id="set-eyecatch-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
                            <div class="bg-white max-w-lg w-full rounded-3xl border border-slate-200 p-6 sm:p-7 shadow-2xl space-y-5 animate-in fade-in zoom-in duration-150">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xl">🖼️</span>
                                        <h3 class="text-base font-black text-slate-900">アイキャッチ画像の設定 & 公開</h3>
                                    </div>
                                    <button type="button" onclick="closeSetImageModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center text-sm font-bold">✕</button>
                                </div>

                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="op" value="set_eyecatch_and_publish">
                                    <input type="hidden" name="article_id" id="modal-article-id" value="">

                                    <div class="space-y-1">
                                        <label class="block text-xs font-bold text-slate-500">対象記事</label>
                                        <div id="modal-article-title" class="text-xs font-bold text-slate-900 bg-slate-50 p-2.5 rounded-xl border border-slate-200 line-clamp-2"></div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-xs font-bold text-slate-700">アイキャッチ画像URL <span class="text-rose-600">*</span></label>
                                        <input type="url" name="image_url" id="modal-image-url" required placeholder="https://images.unsplash.com/... または /uploads/..." class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-xs focus:outline-none focus:border-amber-500">
                                    </div>

                                    <?php if (!empty($poolImages)): ?>
                                        <div class="space-y-1.5">
                                            <div class="text-[11px] font-bold text-slate-500 flex items-center justify-between">
                                                <span>プール画像からワンクリック選択:</span>
                                                <span class="text-[10px] text-amber-600 font-normal">クリックするとURLが自動入力されます</span>
                                            </div>
                                            <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 max-h-36 overflow-y-auto p-1.5 bg-slate-50 rounded-2xl border border-slate-200">
                                                <?php foreach (array_slice($poolImages, 0, 18) as $pImg): ?>
                                                    <div onclick="selectPoolImage('<?= htmlspecialchars(addslashes($pImg['url']), ENT_QUOTES) ?>')" class="aspect-video rounded-lg overflow-hidden border border-slate-200 hover:border-amber-500 hover:scale-105 transition-all cursor-pointer bg-slate-200 shadow-2xs">
                                                        <img src="<?= htmlspecialchars($pImg['url']) ?>" alt="<?= htmlspecialchars($pImg['alt_text'] ?? '') ?>" class="w-full h-full object-cover">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="bg-amber-50/70 border border-amber-200 p-3.5 rounded-2xl">
                                        <label class="flex items-center gap-2.5 cursor-pointer">
                                            <input type="checkbox" name="publish_now" value="1" checked class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400">
                                            <div>
                                                <span class="text-xs font-black text-slate-900">アイキャッチ設定後、直ちに表のサイトへ公開する</span>
                                                <p class="text-[10px] text-amber-800">チェックを外すと「保留（下書き）」状態のまま保存されます。</p>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-2">
                                        <button type="button" onclick="closeSetImageModal()" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-bold">
                                            キャンセル
                                        </button>
                                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all">
                                            画像を設定して保存・公開
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <script>
                        let currentFilter = 'all';

                        function setArticleFilter(filter) {
                            currentFilter = filter;
                            document.querySelectorAll('.filter-tab-btn').forEach(btn => {
                                if (btn.getAttribute('data-filter') === filter) {
                                    btn.className = 'filter-tab-btn active px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-900 text-white';
                                } else {
                                    btn.className = 'filter-tab-btn px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 hover:bg-slate-200';
                                }
                            });
                            applyFilters();
                        }

                        function filterByNoImage() {
                            setArticleFilter('no_image');
                        }

                        function filterArticles() {
                            applyFilters();
                        }

                        function applyFilters() {
                            const query = document.getElementById('article-search-input').value.toLowerCase().trim();
                            const rows = document.querySelectorAll('.article-row');
                            rows.forEach(r => {
                                const text = r.getAttribute('data-search') || '';
                                const filterType = r.getAttribute('data-filter-type') || '';
                                const hasImg = r.getAttribute('data-has-img') === '1';

                                let matchesFilter = true;
                                if (currentFilter === 'no_image') {
                                    matchesFilter = !hasImg;
                                } else if (currentFilter === 'published') {
                                    matchesFilter = hasImg && filterType === 'published';
                                } else if (currentFilter === 'on_hold') {
                                    matchesFilter = filterType === 'on_hold';
                                }

                                const matchesQuery = !query || text.includes(query);

                                if (matchesFilter && matchesQuery) {
                                    r.style.display = '';
                                } else {
                                    r.style.display = 'none';
                                }
                            });
                        }

                        function openSetImageModal(id, title, currentImg) {
                            document.getElementById('modal-article-id').value = id;
                            document.getElementById('modal-article-title').textContent = '#' + id + ' ' + title;
                            document.getElementById('modal-image-url').value = currentImg || '';
                            document.getElementById('set-eyecatch-modal').classList.remove('hidden');
                        }

                        function closeSetImageModal() {
                            document.getElementById('set-eyecatch-modal').classList.add('hidden');
                        }

                        function selectPoolImage(url) {
                            document.getElementById('modal-image-url').value = url;
                        }
                        </script>

                <?php elseif ($currentTab === 'images'): ?>
                    <div class="space-y-6">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                                    <span>🖼️</span> 画像・素材管理
                                </h1>
                                <p class="text-xs text-slate-500 mt-1">最大30,000枚規模対応。Gemini AIが記事の重要キーワードと照合して最適な画像（800×450px）を自動選定します</p>
                            </div>
                            <div class="text-xs text-slate-400 font-bold">
                                登録済み素材: <?= (int)$totalPoolCount ?> 枚
                            </div>
                        </div>

                        <!-- 💡 なぜプールが空でも画像がついたのか？ の説明 ＆ 動作設定 -->
                        <div class="bg-indigo-50/70 border border-indigo-200 rounded-3xl p-6 shadow-xs space-y-4">
                            <div class="flex items-start gap-3">
                                <span class="text-xl">💡</span>
                                <div class="space-y-1 text-xs text-indigo-950 leading-relaxed">
                                    <strong class="font-black text-sm text-indigo-900 block">アイキャッチ画像の取得元と仕組みについて</strong>
                                    記事作成時にキーワードと画像プール内のキーワード（例:「iPhone」「大谷」「ゲーム」「ラーメン」など）が自動照合され、登録済み素材から最も適した画像が選ばれます。
                                </div>
                            </div>

                            <!-- 動作ルールの設定フォーム -->
                            <form method="POST" class="bg-white p-4 rounded-2xl border border-indigo-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <input type="hidden" name="op" value="save_pool_settings">
                                <?php $curEmptyBehavior = SettingsManager::get('pool_empty_behavior', 'default_image'); ?>
                                <div class="space-y-1">
                                    <div class="text-xs font-black text-slate-800">プールに画像がない（またはキーワードが一致しない）ときの動作</div>
                                    <div class="flex flex-wrap gap-4 pt-1">
                                        <label class="flex items-center gap-1.5 text-xs font-bold text-slate-700 cursor-pointer">
                                            <input type="radio" name="pool_empty_behavior" value="hold" <?= $curEmptyBehavior === 'hold' ? 'checked' : '' ?> class="text-amber-500">
                                            <span>「画像未設定（非公開・保留）」にして管理画面で待機（手動設定向け）</span>
                                        </label>
                                        <label class="flex items-center gap-1.5 text-xs font-bold text-slate-700 cursor-pointer">
                                            <input type="radio" name="pool_empty_behavior" value="default_image" <?= $curEmptyBehavior === 'default_image' ? 'checked' : '' ?> class="text-amber-500">
                                            <span>非常用の汎用画像（速報ニュース写真）を仮設定して即時公開する</span>
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="px-5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition-all shrink-0 cursor-pointer">
                                    ルールを保存
                                </button>
                            </form>
                        </div>

                        <!-- 画像追加フォーム (複数ファイル・複数URL対応) -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                    <span>➕</span> 新しいアイキャッチ画像の一括登録
                                </h2>
                                <span class="text-xs font-bold text-slate-400">複数画像の一括アップロード対応</span>
                            </div>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="op" value="add_pool_image">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">💻 ローカルPCから一括アップロード（複数選択OK）</label>
                                        <input type="file" name="local_images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800 cursor-pointer">
                                        <p class="text-[10px] text-slate-400">※ ShiftキーやCtrlキーで複数ファイルを選択して一気に登録できます (JPG, PNG, WEBP, GIF)</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">🌐 または 画像URLを直接指定（改行で複数行OK）</label>
                                        <textarea name="urls" rows="2" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg" class="w-full px-4 py-2 rounded-2xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-amber-500 font-mono"></textarea>
                                        <p class="text-[10px] text-slate-400">※ 複数ある場合は1行に1つの画像URLを入力してください</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">画像説明（altテキスト・共通タイトル）</label>
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
                                    <label class="block text-xs font-bold text-slate-700">自動マッチング用 キーワード（3つ設定・部分一致対応）</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <input type="text" name="kw1" placeholder="キーワード1 (例: 千鳥)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                        <input type="text" name="kw2" placeholder="キーワード2 (例: お笑い)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                        <input type="text" name="kw3" placeholder="キーワード3 (例: テレビ)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-amber-500">
                                    </div>
                                    <p class="text-[10px] text-slate-400">※ 記事のタイトルや本文にこれらの単語が含まれていると、AIが自動的にこの画像をアイキャッチに採用します</p>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="px-6 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all cursor-pointer">
                                        プールに一括登録する
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 登録済み画像一覧 -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                        <span>📂</span> 登録済み画像プール 
                                        <span class="text-xs bg-slate-900 text-amber-400 px-2.5 py-0.5 rounded-full font-bold"><?= $totalPoolCount ?> 枚</span>
                                    </h2>
                                    <p class="text-xs text-slate-400 mt-0.5">登録された画像は使用回数の少ないものから均等に優先して自動選定されます</p>
                                </div>
                                <?php if (count($poolImages) === 0): ?>
                                    <span class="text-xs text-amber-700 font-bold bg-amber-50 px-2.5 py-1 rounded-full border border-amber-200">
                                        ⚠️ 現在0件（右上のボタンから64枚一括追加可能）
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (count($poolImages) === 0): ?>
                                <div class="p-12 text-center border-2 border-dashed border-slate-200 rounded-3xl space-y-3 bg-slate-50/50">
                                    <div class="text-4xl">🖼️</div>
                                    <div class="font-black text-slate-700 text-sm">現在登録されているアイキャッチ画像はありません</div>
                                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                                        ご自身の画像を追加するか、下のボタンから商用フリーの初期画像セット（全7ジャンル・計64枚）をワンクリックで一括追加できます。
                                    </p>
                                    <form method="POST" class="pt-2">
                                        <input type="hidden" name="op" value="seed_preset_images">
                                        <input type="hidden" name="genre" value="all">
                                        <button type="submit" class="px-6 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all inline-flex items-center gap-2 cursor-pointer">
                                            <span>🎁 商用フリー厳選画像パック（全7ジャンル・計64枚）を一括追加</span>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                                    <?php foreach ($poolImages as $pi): ?>
                                        <div class="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50 space-y-2 p-2 flex flex-col justify-between group relative hover:border-amber-400 transition-colors">
                                            <div class="aspect-video bg-slate-200 rounded-xl overflow-hidden relative">
                                                <img src="<?= htmlspecialchars($pi['url']) ?>" alt="<?= htmlspecialchars($pi['alt_text']) ?>" class="w-full h-full object-cover" loading="lazy">
                                                <form method="POST" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity" onsubmit="return confirm('この画像をプールから削除しますか？');">
                                                    <input type="hidden" name="op" value="delete_pool_image">
                                                    <input type="hidden" name="image_id" value="<?= $pi['id'] ?>">
                                                    <button type="submit" class="w-6 h-6 rounded-full bg-rose-600 hover:bg-rose-700 text-white text-[10px] flex items-center justify-center shadow-md cursor-pointer" title="削除">
                                                        ✕
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-[11px] font-bold text-slate-800 truncate" title="<?= htmlspecialchars($pi['alt_text']) ?>">
                                                    <?= htmlspecialchars($pi['alt_text']) ?>
                                                </div>
                                                <div class="text-[10px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-100 truncate" title="<?= htmlspecialchars($pi['keywords'] ?: '未設定') ?>">
                                                    🏷️ <?= htmlspecialchars($pi['keywords'] ?: '未設定') ?>
                                                </div>
                                                <div class="text-[10px] text-slate-400 flex items-center justify-between pt-0.5">
                                                    <span>使用: <?= $pi['use_count'] ?>回</span>
                                                    <span>ID #<?= $pi['id'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- 4. 🔗 相互リンク・相互RSS返還 タブ -->
                <?php elseif ($currentTab === 'trade'): ?>
                    <div class="space-y-6">
                        <!-- ヘッダー & トップ操作バー -->
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                                    <span>🔗</span> 相互リンク・相互RSS提携
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

                        <!-- 提携アンテナ・相互リンクの一括拡充バナー -->
                        <div class="bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-amber-200 rounded-3xl p-5 sm:p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="text-sm font-black text-slate-900 flex items-center gap-2">
                                    <span>🌟</span> 相互リンク・提携アンテナサイト枠の自動拡充
                                </div>
                                <p class="text-xs text-slate-600">
                                    大手・定番のアンテナサイト・まとめサイト（しぃアンテナ、にゅーもふ、ワロタあんてな、2chまとめくす等 12サイト）をワンクリックで一括追加・相互掲載できます。
                                </p>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="op" value="seed_popular_trade_sites">
                                <button type="submit" class="px-5 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all whitespace-nowrap flex items-center gap-2">
                                    <span>🚀</span> 定番アンテナ12件を一括追加・拡充する
                                </button>
                            </form>
                        </div>

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
                                    <span>💰</span> アフィリエイト・広告設定
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

                            <!-- ステマ規制法対応 アフィリエイト広告表記 (PR表記) -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">⚖️</span>
                                        <div>
                                            <h2 class="text-base font-black text-slate-900">ステマ規制法対応 アフィリエイト広告・PR表記設定</h2>
                                            <p class="text-xs text-slate-500">2023年10月施行の景品表示法（ステマ規制）に基づき、全ページ上部および記事内にPR明記を自動表示します。</p>
                                        </div>
                                    </div>
                                    <label class="relative flex items-center gap-2 cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200 shadow-2xs">
                                        <input type="checkbox" name="affiliate_pr_notice_enabled" value="1" <?= SettingsManager::get('affiliate_pr_notice_enabled', '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400">
                                        <span class="text-xs font-black text-slate-800">PR表記を表示する (推奨: ON)</span>
                                    </label>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-slate-700">表示する告知文言</label>
                                    <input type="text" name="affiliate_pr_notice_text" value="<?= htmlspecialchars(SettingsManager::get('affiliate_pr_notice_text', '当サイトはアフィリエイト広告を利用しています。')) ?>" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-amber-500" placeholder="当サイトはアフィリエイト広告を利用しています。">
                                    <div class="flex items-center gap-2 text-[11px] text-slate-500 pt-1">
                                        <span class="font-bold text-amber-600">表示プレビュー:</span>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200 text-[11px]">
                                            <span class="px-1.5 py-0.2 rounded bg-amber-500 text-slate-950 font-bold text-[9px]">PR</span>
                                            <span><?= htmlspecialchars(SettingsManager::get('affiliate_pr_notice_text', '当サイトはアフィリエイト広告を利用しています。')) ?></span>
                                        </span>
                                    </div>
                                </div>
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

                        <!-- データベース接続情報 (MySQL) セクション -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <div>
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">🗄️</span>
                                        <h2 class="text-sm font-black text-slate-900">データベース接続設定 (MySQL / MariaDB)</h2>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-bold border border-emerald-200">
                                        ● 接続正常稼働中
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-2">
                                    シン・レンタルサーバー、エックスサーバー等の環境に合わせてデータベース接続先を変更・保存できます（保存先: <code>php/db_config.php</code>）。
                                </p>
                            </div>

                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="op" value="save_db_config">

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">ホスト名 (Host) <span class="text-rose-600">*</span></label>
                                        <input type="text" name="db_host" value="<?= htmlspecialchars(DB_HOST) ?>" required class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500 bg-slate-50 focus:bg-white">
                                        <p class="text-[11px] text-slate-400">※ 例: <code>localhost</code> または <code>mysql****.xserver.jp</code></p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">ポート番号</label>
                                        <input type="number" name="db_port" value="<?= htmlspecialchars(DB_PORT) ?>" required class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500 bg-slate-50 focus:bg-white">
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">データベース名 (Database) <span class="text-rose-600">*</span></label>
                                    <input type="text" name="db_name" value="<?= htmlspecialchars(DB_NAME) ?>" required class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500 bg-slate-50 focus:bg-white">
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">ユーザー名 (User) <span class="text-rose-600">*</span></label>
                                        <input type="text" name="db_user" value="<?= htmlspecialchars(DB_USER) ?>" required class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500 bg-slate-50 focus:bg-white">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-700">パスワード (Password)</label>
                                        <input type="password" name="db_pass" value="<?= htmlspecialchars(DB_PASS) ?>" placeholder="パスワード" class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-sm focus:outline-none focus:border-amber-500 bg-slate-50 focus:bg-white">
                                    </div>
                                </div>

                                <div class="pt-2 flex items-center justify-between">
                                    <a href="?setup=1" class="text-xs font-bold text-amber-700 hover:underline">
                                        ⚙️ 初期セットアップウィザードを再表示する
                                    </a>
                                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all">
                                        接続テスト & データベース設定を保存する
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <!-- 9. 📈 アクセス解析 タブ -->
                <?php elseif ($currentTab === 'site_settings'): ?>
                    <?php
                    $siteSettingsRow = [
                        'name' => 'しらんけど',
                        'description' => '',
                        'genre' => 'general',
                        'logo_url' => '',
                        'favicon_url' => '',
                        'is_public' => 1,
                        'allow_auto_publish' => 1,
                        'youtube_thumbnail_enabled' => 1,
                    ];
                    try {
                        $siteStmt = $db->query("SELECT name, description, genre, logo_url, favicon_url, is_public, allow_auto_publish, youtube_thumbnail_enabled FROM sites WHERE id = 1 LIMIT 1");
                        $loadedSite = $siteStmt ? $siteStmt->fetch() : false;
                        if ($loadedSite) {
                            $siteSettingsRow = array_merge($siteSettingsRow, $loadedSite);
                        }
                    } catch (Throwable $e) {}
                    ?>
                    <div class="space-y-6">
                        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                                <span>⚙️</span>
                                <span>サイト設定</span>
                            </h1>
                            <p class="text-xs text-slate-500 mt-1">
                                サイト自体の基本情報と公開設定を管理します。
                            </p>
                        </div>

                        <form method="POST" class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <input type="hidden" name="op" value="save_site_settings">
                            <input type="hidden" name="tab" value="site_settings">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div class="sm:col-span-2 space-y-1.5">
                                    <label class="block text-xs font-black text-slate-700">サイト名</label>
                                    <input type="text" name="site_name" required value="<?= htmlspecialchars($siteSettingsRow['name'] ?? '') ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm">
                                </div>

                                <div class="sm:col-span-2 space-y-1.5">
                                    <label class="block text-xs font-black text-slate-700">サイト説明</label>
                                    <textarea name="site_description" rows="4" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm"><?= htmlspecialchars($siteSettingsRow['description'] ?? '') ?></textarea>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-black text-slate-700">ジャンル</label>
                                    <input type="text" name="site_genre" value="<?= htmlspecialchars($siteSettingsRow['genre'] ?? 'general') ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm">
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-black text-slate-700">ロゴURL</label>
                                    <input type="text" name="logo_url" value="<?= htmlspecialchars($siteSettingsRow['logo_url'] ?? '') ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm">
                                </div>

                                <div class="sm:col-span-2 space-y-1.5">
                                    <label class="block text-xs font-black text-slate-700">favicon URL</label>
                                    <input type="text" name="favicon_url" value="<?= htmlspecialchars($siteSettingsRow['favicon_url'] ?? '') ?>" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-amber-500 text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                                    <input type="checkbox" name="is_public" value="1" <?= !empty($siteSettingsRow['is_public']) ? 'checked' : '' ?> class="w-4 h-4">
                                    <span class="text-xs font-bold text-slate-700">サイトを公開する</span>
                                </label>
                                <label class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                                    <input type="checkbox" name="allow_auto_publish" value="1" <?= !empty($siteSettingsRow['allow_auto_publish']) ? 'checked' : '' ?> class="w-4 h-4">
                                    <span class="text-xs font-bold text-slate-700">安全記事の自動公開</span>
                                </label>
                                <label class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                                    <input type="checkbox" name="youtube_thumbnail_enabled" value="1" <?= !empty($siteSettingsRow['youtube_thumbnail_enabled']) ? 'checked' : '' ?> class="w-4 h-4">
                                    <span class="text-xs font-bold text-slate-700">YouTubeサムネイルを使用</span>
                                </label>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md transition-all">
                                    サイト設定を保存
                                </button>
                            </div>
                        </form>
                    </div>

                <?php elseif ($currentTab === 'analytics'): ?>
                    <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">高性能アクセス解析</h1>
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

                <!-- 11. 🔧 その他の機能（画像・相互RSS・アクセス解析等） -->
                <?php elseif ($currentTab === 'advanced'): ?>
                    <div class="space-y-6">
                        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">🔧</span>
                                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">その他の詳細機能</h1>
                            </div>
                            <p class="text-xs text-slate-500">
                                普段は触らなくてもブログは全自動で動きます。必要に応じて利用できる詳細設定です。
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                            <!-- 1. 画像プール -->
                            <a href="?tab=images" class="bg-white p-6 rounded-3xl border border-slate-200 hover:border-amber-400 hover:shadow-md transition-all space-y-3 block group">
                                <div class="w-10 h-10 rounded-2xl bg-amber-100 flex items-center justify-center text-xl">🖼️</div>
                                <div class="font-black text-slate-900 group-hover:text-amber-600 text-sm">アイキャッチ画像プール</div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    自分の画像をアップロードしたり、キーワードマッチングを登録できます（未登録でも自動適用されます）。
                                </p>
                                <div class="text-xs font-bold text-amber-700 flex items-center gap-1">設定を開く →</div>
                            </a>

                            <!-- 2. 相互RSS・アクセス返還 -->
                            <a href="?tab=trade" class="bg-white p-6 rounded-3xl border border-slate-200 hover:border-amber-400 hover:shadow-md transition-all space-y-3 block group">
                                <div class="w-10 h-10 rounded-2xl bg-indigo-100 flex items-center justify-center text-xl">🔗</div>
                                <div class="font-black text-slate-900 group-hover:text-indigo-600 text-sm">相互リンク & RSSトレード</div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    アンテナサイトや他サイトとアクセスを交換し合う相互RSS機能です。
                                </p>
                                <div class="text-xs font-bold text-indigo-700 flex items-center gap-1">設定を開く →</div>
                            </a>

                            <!-- 3. 広告コード詳細管理 -->
                            <a href="?tab=ads" class="bg-white p-6 rounded-3xl border border-slate-200 hover:border-amber-400 hover:shadow-md transition-all space-y-3 block group">
                                <div class="w-10 h-10 rounded-2xl bg-emerald-100 flex items-center justify-center text-xl">💰</div>
                                <div class="font-black text-slate-900 group-hover:text-emerald-600 text-sm">広告タグ詳細管理</div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    AdSense、アフィリエイトなどの掲載枠（ヘッダー、サイドバー等）を細かく管理します。
                                </p>
                                <div class="text-xs font-bold text-emerald-700 flex items-center gap-1">設定を開く →</div>
                            </a>

                            <!-- 4. アクセス解析 -->
                            <a href="?tab=analytics" class="bg-white p-6 rounded-3xl border border-slate-200 hover:border-amber-400 hover:shadow-md transition-all space-y-3 block group">
                                <div class="w-10 h-10 rounded-2xl bg-blue-100 flex items-center justify-center text-xl">📈</div>
                                <div class="font-black text-slate-900 group-hover:text-blue-600 text-sm">簡易アクセス解析</div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    サイトの日別PV数や参照元ドメインのランキングを確認できます。
                                </p>
                                <div class="text-xs font-bold text-blue-700 flex items-center gap-1">レポートを見る →</div>
                            </a>

                            <!-- 5. SEOメタタグ -->
                            <a href="?tab=seo_tags" class="bg-white p-6 rounded-3xl border border-slate-200 hover:border-amber-400 hover:shadow-md transition-all space-y-3 block group">
                                <div class="w-10 h-10 rounded-2xl bg-purple-100 flex items-center justify-center text-xl">🏷️</div>
                                <div class="font-black text-slate-900 group-hover:text-purple-600 text-sm">SEO & カスタムタグ</div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    Googleサーチコンソール所有権タグやGA4計測タグを埋め込みます。
                                </p>
                                <div class="text-xs font-bold text-purple-700 flex items-center gap-1">設定を開く →</div>
                            </a>

                            <!-- 6. パスワード & 保守 -->
                            <a href="?tab=security" class="bg-white p-6 rounded-3xl border border-slate-200 hover:border-amber-400 hover:shadow-md transition-all space-y-3 block group">
                                <div class="w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center text-xl">🔒</div>
                                <div class="font-black text-slate-900 group-hover:text-slate-700 text-sm">パスワード & 保守</div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    管理画面ログインパスワードの変更やシステム保守を行えます。
                                </p>
                                <div class="text-xs font-bold text-slate-700 flex items-center gap-1">設定を開く →</div>
                            </a>
                        </div>
                    </div>

                <!-- 10. ✨ 基本設定（Gemini AI & 投稿スケジュール管理） タブ -->
                <?php elseif ($currentTab === 'api_settings'): ?>
                    <div class="space-y-6">
                        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                                <span>🔑</span><span>API設定</span>
                            </h1>
                            <p class="text-xs text-slate-500 mt-1">記事生成に使用する外部APIの接続情報を管理します。</p>
                        </div>

                        <form method="POST" class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <input type="hidden" name="op" value="save_api_settings">
                            <input type="hidden" name="tab" value="api_settings">

                            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🤖</span>
                                    <div>
                                        <h2 class="text-base font-black text-slate-900">Gemini API</h2>
                                        <p class="text-xs text-slate-500">APIキーと使用モデルを設定します。</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $hasGeminiKey ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700' ?>">
                                    <?= $hasGeminiKey ? '接続情報あり' : '未設定' ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">Gemini API Key</label>
                                    <input type="password" name="gemini_api_key" id="input_gemini_key" value="<?= htmlspecialchars(SettingsManager::get('gemini_api_key')) ?>" placeholder="AIzaSy..." class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:border-indigo-500 font-mono text-sm">
                                    <button type="button" onclick="testGeminiConnection()" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold hover:underline">🔍 接続テストを実行</button>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">使用AIモデル</label>
                                    <?php $curModel = SettingsManager::get('gemini_model', 'gemini-2.5-flash'); ?>
                                    <select name="gemini_model" id="input_gemini_model" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white text-sm focus:outline-none focus:border-indigo-500">
                                        <option value="gemini-2.5-flash" <?= $curModel === 'gemini-2.5-flash' ? 'selected' : '' ?>>Gemini 2.5 Flash</option>
                                        <option value="gemini-1.5-flash" <?= $curModel === 'gemini-1.5-flash' ? 'selected' : '' ?>>Gemini 1.5 Flash</option>
                                        <option value="gemini-2.5-pro" <?= $curModel === 'gemini-2.5-pro' ? 'selected' : '' ?>>Gemini 2.5 Pro</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-7 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md">API設定を保存</button>
                            </div>
                        </form>

                        <form id="gemini-test-form" method="POST" style="display:none;">
                            <input type="hidden" name="op" value="test_gemini_api">
                            <input type="hidden" name="gemini_api_key" id="test_form_key" value="">
                            <input type="hidden" name="gemini_model" id="test_form_model" value="">
                        </form>
                        <script>
                        function testGeminiConnection() {
                            const key = document.getElementById('input_gemini_key').value.trim();
                            const model = document.getElementById('input_gemini_model').value;
                            if (!key) {
                                alert('Gemini API Key を入力してからテストボタンを押してください。');
                                return;
                            }
                            document.getElementById('test_form_key').value = key;
                            document.getElementById('test_form_model').value = model;
                            document.getElementById('gemini-test-form').submit();
                        }
                        </script>
                    </div>

                <?php elseif ($currentTab === 'system'): ?>
                    <div class="space-y-6">
                        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                                <span>💻</span><span>システム設定</span>
                            </h1>
                            <p class="text-xs text-slate-500 mt-1">自動投稿の動作、実行時間、Cronなどシステム運用に関する設定をまとめています。</p>
                        </div>

                        <form method="POST" class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                            <input type="hidden" name="op" value="save_system_settings">
                            <input type="hidden" name="tab" value="system">

                            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                                <div>
                                    <h2 class="text-base font-black text-slate-900">⏰ 自動投稿・実行設定</h2>
                                    <p class="text-xs text-slate-500">投稿間隔、稼働時間帯、1日の上限を設定します。</p>
                                </div>
                                <span class="px-3 py-1 rounded-full <?= $autoPostEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?> text-[11px] font-bold">
                                    <?= $autoPostEnabled ? '自動投稿: 有効' : '自動投稿: 停止中' ?>
                                </span>
                            </div>

                            <label class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                                <input type="checkbox" name="auto_post_enabled" value="1" <?= SettingsManager::get('auto_post_enabled', '1') === '1' ? 'checked' : '' ?> class="w-5 h-5">
                                <div>
                                    <div class="text-sm font-black text-slate-900">全自動記事投稿を有効にする</div>
                                    <p class="text-xs text-slate-500">OFFの場合は自動生成・投稿を停止します。</p>
                                </div>
                            </label>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">投稿間隔</label>
                                    <?php $currentInterval = SettingsManager::get('auto_post_interval_hours', '1'); ?>
                                    <select name="auto_post_interval_hours" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white text-sm">
                                        <?php foreach (['0.5'=>'30分','1'=>'1時間','2'=>'2時間','3'=>'3時間','4'=>'4時間','6'=>'6時間','12'=>'12時間','24'=>'24時間'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $currentInterval === $value ? 'selected' : '' ?>><?= $label ?>に1本</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">1日の最大自動投稿本数</label>
                                    <?php $currentMax = (int)SettingsManager::get('auto_post_max_per_day', '10'); ?>
                                    <select name="auto_post_max_per_day" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white text-sm">
                                        <?php foreach ([3,5,10,15,20,30,0] as $max): ?>
                                            <option value="<?= $max ?>" <?= $currentMax === $max ? 'selected' : '' ?>><?= $max === 0 ? '無制限' : '1日 '.$max.'本まで' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">自動投稿を許可する時間帯</label>
                                    <?php $startHour=(int)SettingsManager::get('auto_post_start_hour','8'); $endHour=(int)SettingsManager::get('auto_post_end_hour','23'); ?>
                                    <div class="flex items-center gap-2">
                                        <select name="auto_post_start_hour" class="flex-1 px-3 py-3 rounded-2xl border border-slate-200 bg-white text-sm">
                                            <?php for ($h=0;$h<=23;$h++): ?><option value="<?= $h ?>" <?= $startHour===$h?'selected':'' ?>><?= sprintf('%02d:00',$h) ?></option><?php endfor; ?>
                                        </select>
                                        <span class="text-slate-400">〜</span>
                                        <select name="auto_post_end_hour" class="flex-1 px-3 py-3 rounded-2xl border border-slate-200 bg-white text-sm">
                                            <?php for ($h=0;$h<=23;$h++): ?><option value="<?= $h ?>" <?= $endHour===$h?'selected':'' ?>><?= sprintf('%02d:59',$h) ?></option><?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-slate-700">自動生成記事の公開設定</label>
                                    <?php $defaultStatus=SettingsManager::get('auto_post_default_status','published'); ?>
                                    <select name="auto_post_default_status" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-white text-sm">
                                        <option value="published" <?= $defaultStatus==='published'?'selected':'' ?>>即時公開</option>
                                        <option value="on_hold" <?= $defaultStatus==='on_hold'?'selected':'' ?>>保留して確認</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-7 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md">システム設定を保存</button>
                            </div>
                        </form>

                        <div class="bg-slate-900 text-slate-200 rounded-3xl p-6 sm:p-8 space-y-5 shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-4">
                                <div>
                                    <h2 class="text-base font-black text-white">⚙️ サーバーCron設定</h2>
                                    <p class="text-xs text-slate-400 mt-1">サーバー側のCronに以下のコマンドを登録します。</p>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="op" value="run_worker">
                                    <button type="submit" class="px-5 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs">🚀 今すぐワーカーを手動実行</button>
                                </form>
                            </div>
                            <div class="bg-slate-950 p-4 rounded-2xl font-mono text-xs text-amber-300 select-all break-all border border-slate-800">
                                /usr/bin/php <?= htmlspecialchars(__DIR__ . '/cron/worker.php') ?>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-300">📜 直近のCron実行ログ</span>
                                    <span class="text-[11px] text-slate-400">最終実行日時: <?= htmlspecialchars(SettingsManager::get('last_cron_executed_at', '未実行')) ?></span>
                                </div>
                                <?php $lastLog=SettingsManager::get('last_cron_log'); ?>
                                <div class="bg-slate-950 p-3.5 rounded-2xl font-mono text-[11px] text-emerald-400 border border-slate-800 max-h-48 overflow-y-auto whitespace-pre-wrap"><?= !empty($lastLog) ? htmlspecialchars($lastLog) : 'まだCron実行ログがありません。' ?></div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </main>
        </div>
    <?php endif; ?>

</body>
</html>
