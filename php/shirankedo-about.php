<?php
require_once __DIR__ . '/config.php';
$site = SiteManager::resolveCurrentSite();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>しらんけど指数とは？ - <?= htmlspecialchars($site['name']) ?></title>
  <meta name="description" content="独自話題度指標「しらんけど指数」についての解説ページです。">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.7; color: #1e293b; background: #f8fafc; margin: 0; padding: 20px; }
    .container { max-width: 800px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    h1 { color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; font-size: 1.8rem; }
    .badge-box { background: #f1f5f9; padding: 20px; border-radius: 8px; margin: 24px 0; }
    .highlight { font-weight: bold; color: #dc2626; }
    .footer-note { margin-top: 32px; padding: 16px; background: #fffbeb; border-left: 4px solid #f59e0b; font-size: 1.1rem; }
    a { color: #2563eb; text-decoration: none; }
  </style>
</head>
<body>
  <div class="container">
    <a href="/">&larr; トップページに戻る</a>
    <h1>しらんけど指数とは？</h1>
    
    <p>「しらんけど指数」は、いまインターネット上でどれくらい話題になっているのかを、独自アルゴリズムで算出した<strong>100点満点の話題度指標</strong>です。</p>

    <div class="badge-box">
      <h3>📊 指数の目安</h3>
      <ul>
        <li><strong>80〜100点: めっちゃ話題</strong> (Google検索・SNS・YouTubeなど複数媒体で同時多発的に急上昇中)</li>
        <li><strong>60〜79点: かなり話題</strong> (特定のプラットフォームで大きな反響を集めている)</li>
        <li><strong>30〜59点: 話題</strong> (関心がじわじわ高まっている、または特定コミュニティで注目)</li>
        <li><strong>0〜29点: ちょい話題</strong> (一部でささやかれ始めている兆候)</li>
      </ul>
    </div>

    <h2>指数の算出基準と特徴</h2>
    <ul>
      <li><strong>複数のトレンド情報を総合的に参照：</strong> Googleトレンドの検索上昇、Yahoo!リアルタイム検索の言及頻度、YouTubeの急上昇動画、ニュース閲覧ランキング、ゲームランキングなどを横断集計しています。</li>
      <li><strong>複合的な話題度：</strong> 単一のSNSで騒がれているだけでは高得点にならず、検索や報道など「異なる場所で同時に話題になっているか」を重視してスコアリングします。</li>
      <li><strong>時間の経過とともに変動：</strong> ネットの関心は移り変わるため、指数はリアルタイムに再計算され、ピークを過ぎると徐々に落ち着きます。</li>
    </ul>

    <h2>⚠️ ご利用にあたっての注意事項</h2>
    <ul>
      <li>本指数は<strong>世論調査や統計調査ではありません</strong>。</li>
      <li>実際の検索数や閲覧PVの絶対値そのものを表すものではありません。</li>
      <li>当サイトの独自集計による推定値であり、Google社、LINEヤフー社、YouTube（Google社）、その他各情報提供元から公認・提携された指数ではありません。</li>
    </ul>

    <div class="footer-note">
      <p>要するに、<strong>「なんか今これめっちゃ見かけるな」を数字にしたようなもの</strong>です。しらんけど。</p>
    </div>
  </div>
</body>
</html>
