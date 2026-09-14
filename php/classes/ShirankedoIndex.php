<?php
/**
 * しらんけど指数 算出・急上昇判定エンジン
 */
class ShirankedoIndex {
    /**
     * 100点満点のしらんけど指数を計算
     * Googleトレンド(30), Yahooリアルタイム(25), ニュース(20), YouTube(15), ゲーム(10)
     */
    public static function calculate(int $siteId, array $scores): array {
        // 設定された配点を取得 (デフォルト合計100)
        $weights = [
            'google'  => (int)SiteManager::getSiteSetting($siteId, 'weight_google', '30'),
            'yahoo'   => (int)SiteManager::getSiteSetting($siteId, 'weight_yahoo', '25'),
            'news'    => (int)SiteManager::getSiteSetting($siteId, 'weight_news', '20'),
            'youtube' => (int)SiteManager::getSiteSetting($siteId, 'weight_youtube', '15'),
            'game'    => (int)SiteManager::getSiteSetting($siteId, 'weight_game', '10'),
        ];

        $totalWeighted = 0;
        $totalMax = 0;

        foreach ($weights as $source => $weight) {
            $rawScore = $scores[$source] ?? 0; // 0〜100
            // 該当ジャンルで無関係な項目は正規化対象
            $totalWeighted += ($rawScore / 100.0) * $weight;
            $totalMax += $weight;
        }

        $index = $totalMax > 0 ? (int)round(($totalWeighted / $totalMax) * 100) : 50;
        $index = max(0, min(100, $index));

        // 指数ラベル判定
        $label = self::getLabel($index);

        return [
            'index' => $index,
            'label' => $label,
        ];
    }

    public static function getLabel(int $index): string {
        if ($index >= 80) {
            return 'めっちゃ話題';
        } elseif ($index >= 60) {
            return 'かなり話題';
        } elseif ($index >= 30) {
            return '話題';
        } else {
            return 'ちょい話題';
        }
    }

    /**
     * 急上昇判定 (+145%などの急激な伸び)
     */
    public static function checkRapidRise(float $growthRate): bool {
        return $growthRate >= 100.0;
    }

    /**
     * 初出からの経過時間を自然な日本語で整形
     */
    public static function formatElapsedTime(string $firstDetectedAt): string {
        $firstTime = strtotime($firstDetectedAt);
        $diffSec = time() - $firstTime;

        if ($diffSec < 3600) {
            $mins = max(1, (int)round($diffSec / 60));
            return "話題発生から {$mins}分";
        } elseif ($diffSec < 86400) {
            $hours = (int)floor($diffSec / 3600);
            return "話題発生から {$hours}時間";
        } else {
            $days = (int)floor($diffSec / 86400) + 1;
            return "話題継続 {$days}日目";
        }
    }
}
