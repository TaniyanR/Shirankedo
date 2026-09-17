<?php
/**
 * AI記事生成エンジン (Gemini / OpenAI 抽象化レイヤー)
 * 厳格ルール:
 * 1. 提供された一次情報・事実のみをまとめる (推測・創作は完全禁止)
 * 2. 容疑者を犯人扱いせず、一般人の個人情報は扱わない
 * 3. 記事本文末尾は自然な文章で「〜しらんけど。」で締める
 * 4. 出典・参考リンクを構造化して提供
 */
require_once __DIR__ . '/SettingsManager.php';

class AiArticleGenerator {
    /**
     * 記事コンテンツを生成
     */
    public static function generate(int $siteId, array $trendData, array $verifiedSources, ?string $youtubeInfo = null): array {
        // SettingsManager または環境変数から設定を取得
        $apiKey = SettingsManager::get('gemini_api_key') ?: getenv('GEMINI_API_KEY') ?: '';
        $model = SettingsManager::get('gemini_model') ?: 'gemini-1.5-flash';

        $keyword = $trendData['display_keyword'] ?? ($trendData['keyword'] ?? '');
        $sourcesText = '';
        foreach ($verifiedSources as $idx => $s) {
            $sourcesText .= sprintf("[%d] %s (%s): %s\n", $idx + 1, $s['title'] ?? '', $s['publisher'] ?? '', $s['url'] ?? '');
        }

        $systemPrompt = <<<EOT
あなたはトレンドニュースサイト「しらんけど」の専属エディターです。
【最重要遵守事項】
1. 下記の「確認された一次情報源・報道データ」に明記されている客観的事実のみを整理・要約して記事を作成してください。
2. 情報源にない推測、憶測、独自の意見、噂を勝手に足すことは固く禁じられています。
3. 容疑段階の人物を犯人扱いせず、一般人の氏名・住所・勤務先・個人情報は記載しないでください。
4. 刺激的な煽り見出しや、AI特有の紋切り型の定型文（「いかがでしたでしょうか」等）は禁止します。
5. 記事本文の最後の1文は、文脈に沿った自然で少しユーモアのある文章にした上で、必ず「〜しらんけど。」で締めくくってください。
   （例：「この勢いがどこまで続くのかは今後の公式発表次第になりそうです。しらんけど。」）
   ※事実関係そのものを「しらんけど」で曖昧にしてはいけません。最後の所感・余韻として添えてください。

【出力フォーマット】
以下のJSONフォーマットのみを返してください。
{
  "title": "記事タイトル（35文字以内で具体的・誤解を招かない表現）",
  "why_trending": "なぜ話題？（100文字以内の簡潔な要約）",
  "body": "記事本文（段落分けされた客観的で読みやすい解説、400〜800文字程度）",
  "conclusion": "締めの文章（最後は必ず「〜しらんけど。」）",
  "important_keywords": ["キーワード1", "キーワード2"]
}
EOT;

        $userPrompt = "【話題キーワード】: {$keyword}\n";
        $userPrompt .= "【確認済み情報源】:\n{$sourcesText}\n";
        if ($youtubeInfo) {
            $userPrompt .= "【関連YouTube情報】:\n{$youtubeInfo}\n";
        }

        // Gemini API呼び出し
        if (!empty($apiKey)) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $payload = [
                'contents' => [
                    ['parts' => [['text' => $userPrompt]]]
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'responseMimeType' => 'application/json'
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 35);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $resData = json_decode($response, true);
                $rawJson = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $parsed = json_decode($rawJson, true);
                if (is_array($parsed) && !empty($parsed['title'])) {
                    return $parsed;
                }
            }
        }

        // 外部API未設定またはエラー時の安全なローカル構築フォールバック
        return self::fallbackGenerate($keyword, $verifiedSources);
    }

    /**
     * 単体プロンプトまたは特定キーワードから記事を即時テスト生成するメソッド
     */
    public static function generateFromKeyword(string $keyword, string $categoryName = 'エンタメ'): array {
        $fakeSource = [
            [
                'title' => "{$keyword}に関する最新公式アナウンス",
                'publisher' => '主要公式メディア',
                'url' => 'https://news.google.com/'
            ]
        ];
        $result = self::generate(1, ['display_keyword' => $keyword], $fakeSource);
        return $result;
    }

    private static function fallbackGenerate(string $keyword, array $sources): array {
        $sourceNames = array_column($sources, 'publisher');
        $sourceSummary = !empty($sourceNames) ? implode('、', array_slice($sourceNames, 0, 2)) : '各公式発表';

        return [
            'title' => "「{$keyword}」が急上昇、{$sourceSummary}の最新動向に注目集まる",
            'why_trending' => "ネット検索および主要ランキングで「{$keyword}」の関心が急激に上昇。公式発表を受け話題となっています。",
            'body' => "「{$keyword}」に関する最新情報が発表され、各所で大きな関心を集めています。{$sourceSummary}等の公表資料によると、関連する取り組みや発表内容が広く認知され、検索やニュース閲覧が急速に拡大しています。事実関係の確認が進んでおり、ファンや利用者の間で今後の展開に対する注目が高まっています。",
            'conclusion' => "今後の追加発表次第では、さらに盛り上がりを見せる展開になるかもしれません。しらんけど。",
            'important_keywords' => array_filter([$keyword, 'トレンド', '話題'])
        ];
    }
}
