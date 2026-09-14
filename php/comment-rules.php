<?php
require_once __DIR__ . '/config.php';
$site = SiteManager::resolveCurrentSite();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>誹謗中傷禁止・コメント利用ルール - <?= htmlspecialchars($site['name']) ?></title>
  <meta name="description" content="当サイトのコメント投稿に関する利用規約・禁止事項および誹謗中傷防止ポリシーです。">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.7; color: #1e293b; background: #f8fafc; margin: 0; padding: 20px; }
    .container { max-width: 800px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    h1 { color: #b91c1c; border-bottom: 2px solid #fee2e2; padding-bottom: 12px; font-size: 1.8rem; }
    h2 { color: #0f172a; margin-top: 28px; }
    .alert-box { background: #fef2f2; border: 1px solid #fca5a5; padding: 18px; border-radius: 8px; margin: 20px 0; }
    ul { padding-left: 24px; }
    li { margin-bottom: 8px; }
    a { color: #2563eb; text-decoration: none; }
  </style>
</head>
<body>
  <div class="container">
    <a href="/">&larr; トップページに戻る</a>
    <h1>誹謗中傷禁止・コメント利用ルール</h1>
    
    <p>当サイト「<?= htmlspecialchars($site['name']) ?>」では、利用者の皆様が安心して健全な意見交換や感想の共有を楽しめるよう、以下のコメント投稿ルールを厳格に定めています。</p>

    <div class="alert-box">
      <strong>【投稿時の大原則】</strong><br>
      ・投稿できる内容は<strong>「文字（プレーンテキスト）のみ」</strong>です。<br>
      ・画像、動画、HTMLタグ、スクリプト、<strong>URL（外部リンク）の書き込みは固く禁止</strong>されています。
    </div>

    <h2>禁止事項</h2>
    <p>以下に該当する、または該当するおそれのある投稿は固く禁止いたします。</p>
    <ul>
      <li><strong>個人への誹謗中傷・名誉毀損・侮辱行為</strong></li>
      <li><strong>企業や団体への根拠なき悪質な中傷や風評被害の流布</strong></li>
      <li><strong>人種、国籍、性別、信条、障がい等に基づく差別的表現</strong></li>
      <li><strong>生命、身体、自由、名誉、財産等に対する脅迫や危害の予告</strong></li>
      <li><strong>真偽不明の噂、虚偽情報（デマ）、憶測の事実化</strong></li>
      <li><strong>第三者へのなりすまし行為</strong></li>
      <li><strong>一般人や関係者の個人情報（本名、住所、電話番号、勤務先・通学先、私生活の情報など）の公開・晒し行為</strong></li>
      <li><strong>過度な煽り、挑発、他者への攻撃的な言動</strong></li>
      <li><strong>犯罪予告、違法行為の教唆・幇助、反社会的表現</strong></li>
      <li><strong>荒らし行為、同一内容や無意味な文字列の連続投稿（連投）</strong></li>
      <li><strong>広告、宣伝、アフィリエイト、他サイトへの誘導</strong></li>
    </ul>

    <h2>違反投稿への対応措置</h2>
    <p>当システムは自動監視システムおよび管理者巡回を行っております。上記禁止事項に抵触した場合、事前の通告なく以下の対応を実施します。</p>
    <ul>
      <li><strong>対象コメントの即時非表示および完全削除</strong></li>
      <li><strong>IPアドレス・端末識別による以後の投稿禁止（アクセス制限・ブロック）</strong></li>
      <li><strong>重大な脅迫、名誉毀損、犯罪予告等については、捜査機関への通報およびログ情報の開示</strong></li>
    </ul>

    <p style="margin-top: 32px; font-size: 0.95rem; color: #64748b;">
      制定日: 2026年<br>
      トレンドサイトシステム「しらんけど」運営事務局
    </p>
  </div>
</body>
</html>
