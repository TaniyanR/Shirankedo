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
        $rawModel = SettingsManager::get('gemini_model') ?: 'gemini-2.0-flash';
        $model = self::normalizeModelName($rawModel);

        $keyword = $trendData['display_keyword'] ?? ($trendData['keyword'] ?? '');
        $sourcesText = '';
        foreach ($verifiedSources as $idx => $s) {
            $sourcesText .= sprintf("[%d] %s (%s): %s\n", $idx + 1, $s['title'] ?? '', $s['publisher'] ?? '', $s['url'] ?? '');
            if (!empty($s['summary'])) {
                $sourcesText .= "    概要: " . trim($s['summary']) . "\n";
            }
            if (!empty($s['published_at'])) {
                $sourcesText .= "    公開日時: " . trim($s['published_at']) . "\n";
            }
        }

        $systemPrompt = <<<EOT
あなたはトレンドニュースサイト「しらんけど」の専属エディターです。
【最重要遵守事項】
1. 下記の「確認された一次情報源・報道データ」に明記されている客観的事実のみを整理・要約して記事を作成してください。
2. 情報源にない推測、憶測、独自の意見、噂を勝手に足すことは固く禁じられています。
3. 容疑段階の人物を犯人扱いせず、一般人の氏名・住所・勤務先・個人情報は記載しないでください。
4. 刺激的な煽り見出しや、AI特有の紋切り型の定型文（「いかがでしたでしょうか」等）は禁止します。
5. 本文は1,200〜2,000文字程度を目安に、6〜10段落で構成してください。「何が起きたか」「背景」「公表されている具体情報」「なぜ注目されているか」「今後確認すべき点」を、情報源にある範囲で整理してください。
6. 文字数を増やすための水増し、同じ内容の言い換え、情報源にない数字・コメント・反応の創作は禁止します。確認できる情報が少ない場合は無理に2,000文字へ伸ばさず、事実の範囲で簡潔にしてください。
7. 記事本文の最後の1文は、文脈に沿った自然で少しユーモアのある文章にした上で、必ず「〜しらんけど。」で締めくくってください。
   （例：「この勢いがどこまで続くのかは今後の公式発表次第になりそうです。しらんけど。」）
   ※事実関係そのものを「しらんけど」で曖昧にしてはいけません。最後の所感・余韻として添えてください。

【出力フォーマット】
以下のJSONフォーマットのみを返してください。
{
  "title": "記事タイトル（35文字以内で具体的・誤解を招かない表現）",
  "why_trending": "なぜ話題？（100文字以内の簡潔な要約）",
  "body": "記事本文（6〜10段落、1,200〜2,000文字程度を基本。確認できる事実が少ない場合は無理に水増ししない）",
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
            $apiResult = self::callGeminiApi($apiKey, $model, $systemPrompt, $userPrompt);
            if ($apiResult['success']) {
                SettingsManager::set('gemini_last_status', 'SUCCESS (HTTP 200) - ' . date('Y-m-d H:i:s'));
                SettingsManager::set('gemini_last_error', '');
                return $apiResult['data'];
            }

            // モデルが404等の場合は安定版 gemini-1.5-flash で自動フォールバック再試行
            if ($model !== 'gemini-1.5-flash') {
                $retryResult = self::callGeminiApi($apiKey, 'gemini-1.5-flash', $systemPrompt, $userPrompt);
                if ($retryResult['success']) {
                    SettingsManager::set('gemini_last_status', 'SUCCESS (HTTP 200, gemini-1.5-flash) - ' . date('Y-m-d H:i:s'));
                    SettingsManager::set('gemini_last_error', '');
                    return $retryResult['data'];
                }
                SettingsManager::set('gemini_last_status', 'ERROR (' . $retryResult['code'] . ') - ' . date('Y-m-d H:i:s'));
                SettingsManager::set('gemini_last_error', $retryResult['error']);
            } else {
                SettingsManager::set('gemini_last_status', 'ERROR (' . $apiResult['code'] . ') - ' . date('Y-m-d H:i:s'));
                SettingsManager::set('gemini_last_error', $apiResult['error']);
            }
        }

        // 外部API未設定またはエラー時の安全なローカル構築フォールバック
        return self::fallbackGenerate($keyword, $verifiedSources);
    }

    /**
     * Gemini モデル名の正規化（廃止された 2.0-flash を Google 推奨の 2.5-flash へ自動昇格）
     */
    public static function normalizeModelName(string $model): string {
        $model = trim($model);
        if ($model === 'gemini-2.0-flash' || empty($model)) {
            return 'gemini-2.5-flash';
        }
        if ($model === 'gemini-2.0-pro') {
            return 'gemini-2.5-pro';
        }
        return $model;
    }

    /**
     * Gemini API 呼び出し実処理
     */
    private static function callGeminiApi(string $apiKey, string $model, string $systemPrompt, string $userPrompt): array {
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
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            $rawJson = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $parsed = json_decode($rawJson, true);
            if (is_array($parsed) && !empty($parsed['title'])) {
                return ['success' => true, 'data' => $parsed, 'code' => 200];
            }
        }

        $errorMsg = $curlError ?: ($response ?: 'Empty response');
        if ($resData = json_decode($response, true)) {
            if (isset($resData['error']['message'])) {
                $errorMsg = $resData['error']['message'];
            }
        }

        // 404またはモデル廃止の場合、別モデルで自動再試行
        if ($httpCode === 404 && $model !== 'gemini-2.5-flash') {
            return self::callGeminiApi($apiKey, 'gemini-2.5-flash', $systemPrompt, $userPrompt);
        } elseif ($httpCode === 404 && $model === 'gemini-2.5-flash') {
            return self::callGeminiApi($apiKey, 'gemini-1.5-flash', $systemPrompt, $userPrompt);
        }

        return ['success' => false, 'code' => $httpCode, 'error' => $errorMsg];
    }

    /**
     * API接続診断テスト
     */
    public static function testApiKey(string $apiKey, string $model): array {
        $model = self::normalizeModelName($model);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Ping. Return JSON: {"status": "ok", "message": "Gemini API connected successfully."}']]]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            return [
                'success' => true,
                'message' => "Gemini API 接続成功！ (モデル: {$model}, HTTP 200)"
            ];
        }

        // 404の場合、gemini-2.5-flash または gemini-1.5-flash で自動再テスト
        if ($httpCode === 404 && $model !== 'gemini-2.5-flash') {
            $retry = self::testApiKey($apiKey, 'gemini-2.5-flash');
            if ($retry['success']) {
                SettingsManager::set('gemini_model', 'gemini-2.5-flash');
                return [
                    'success' => true,
                    'message' => "モデルを最新の gemini-2.5-flash へ自動更新し接続成功！ (HTTP 200)"
                ];
            }
        }

        $errorDesc = "HTTPステータス: {$httpCode}";
        if ($curlError) {
            $errorDesc .= " / cURLエラー: {$curlError}";
        }
        if ($resData = json_decode($response, true)) {
            if (isset($resData['error']['message'])) {
                $errorDesc .= " / " . $resData['error']['message'];
            }
        }

        return [
            'success' => false,
            'message' => "接続失敗: {$errorDesc}"
        ];
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

        $sourceTitles = array_values(array_filter(array_map(fn($s) => trim($s['title'] ?? ''), $sources)));
        $sourceLines = [];
        foreach (array_slice($sourceTitles, 0, 5) as $title) {
            $sourceLines[] = "・" . $title;
        }
        $sourceList = !empty($sourceLines) ? implode("\n", $sourceLines) : "・参照元の取得に失敗しました";

        return [
            'title' => "「{$keyword}」が話題、確認できる最新情報を整理",
            'why_trending' => "「{$keyword}」に関する複数の報道・発表が確認され、注目が集まっています。",
            'body' => "「{$keyword}」について、現在確認できる報道・発表を整理します。\n\n今回参照できた主な情報は次のとおりです。\n{$sourceList}\n\n各情報源の内容は更新される可能性があります。現時点で確認できない数字や発言、SNS上の反応などは記事内で補完していません。\n\n詳しい内容や最新情報については、記事下部の「参考・出典」から各参照ページをご確認ください。",
            'conclusion' => "追加情報が出れば状況が変わる可能性もあるので、続報は原典で確認するのが確実です。しらんけど。",
            'important_keywords' => array_filter([$keyword, 'トレンド', '話題'])
        ];
    }
}
