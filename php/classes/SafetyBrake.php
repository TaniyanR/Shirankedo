<?php
/**
 * 安全判定エンジン (Safety Brake)
 * 危険ジャンル (犯罪・死亡・病気・スキャンダル・一般人等) を検出し、自動公開を停止して保留にする
 */
class SafetyBrake {
    // 厳格検知キーワード一覧
    private static array $dangerKeywords = [
        '犯罪', '逮捕', '容疑', '書類送検', '指名手配', '刑務所',
        '不倫', '浮気', '離婚', '略奪婚',
        '薬物', '大麻', '覚醒剤', '麻薬',
        '病気', '難病', '闘病', '危篤', '感染症',
        '訃報', '死亡', '死去', '亡くなる', '自殺', '遺体', '変死',
        '事故', '多重衝突', '脱線', '火災', '事故責任', '過失',
        '金銭トラブル', '横領', '詐欺', '脱税', '借金',
        '未成年', '女子高生', '中学生', '小学生',
        '個人情報', '流出', '本名特定', '住所特定'
    ];

    /**
     * トピックおよびテキストを監査し、危険度を判定
     */
    public static function audit(string $title, string $content, array $sources = []): array {
        $text = $title . ' ' . $content;
        $matchedWords = [];

        foreach (self::$dangerKeywords as $dangerWord) {
            if (mb_stripos($text, $dangerWord) !== false) {
                $matchedWords[] = $dangerWord;
            }
        }

        // 一次ソースの信頼度スコア検証
        $hasOfficialOrNews = false;
        foreach ($sources as $source) {
            $type = $source['source_type'] ?? '';
            $score = (int)($source['reliability_score'] ?? 0);
            if (($type === 'official' || $type === 'news') && $score >= 80) {
                $hasOfficialOrNews = true;
                break;
            }
        }

        $isDangerous = !empty($matchedWords);
        $needsHold = false;
        $reasons = [];

        if ($isDangerous) {
            $needsHold = true;
            $reasons[] = '危険キーワード検知: ' . implode(', ', array_slice($matchedWords, 0, 5));
        }

        if (!$hasOfficialOrNews) {
            $needsHold = true;
            $reasons[] = '公式発表または信頼できる報道機関の一次ソース不足 (SNS噂のみ)';
        }

        return [
            'is_dangerous' => $isDangerous,
            'needs_hold'   => $needsHold,
            'reason'       => implode(' / ', $reasons),
            'matched'      => $matchedWords,
        ];
    }
}
