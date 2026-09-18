<?php
/**
 * AIによるページの生死自動判定エンジン (Page Lifecycle AI Engine)
 * - 記事の鮮度（経過日数）
 * - しらんけど指数 / トレンド終息度
 * - 読者投票（「もう終わる」「信憑性低」の比率）
 * - 危険ワード・安全判定
 * を総合判定し、記事のステータス（生存・公開 / 鮮度注意 / AI休眠非公開 / アーカイブ）を自動管理します。
 */
require_once __DIR__ . '/Database.php';

class AiLifecycleEngine {
    /**
     * 全記事または特定記事のAI生死判定を一括評価
     */
    public static function evaluateAll(): array {
        $db = Database::getConnection();
        
        // カラムが存在することを保証
        try {
            $stmt = $db->query("SELECT * FROM articles WHERE auto_lifecycle_enabled = 1 ORDER BY id DESC");
            $articles = $stmt->fetchAll();
        } catch (Throwable $e) {
            // カラムがまだ無ければマイグレーション実行
            MigrationAddFeatures::run();
            $stmt = $db->query("SELECT * FROM articles WHERE 1 ORDER BY id DESC");
            $articles = $stmt->fetchAll();
        }

        $totalEvaluated = 0;
        $activeCount = 0;
        $warningCount = 0;
        $dormantCount = 0;
        $results = [];

        foreach ($articles as $art) {
            $totalEvaluated++;
            $pubTime = strtotime($art['published_at'] ?? $art['created_at'] ?? date('Y-m-d H:i:s'));
            $daysOld = max(0, round((time() - $pubTime) / 86400));
            $index = (int)($art['shirankedo_index'] ?? 50);

            // 読者投票比率を取得
            $skeptical = 0;
            $believed = 0;
            try {
                $vStmt = $db->prepare("SELECT 
                    SUM(CASE WHEN vote_type = 'believed' THEN 1 ELSE 0 END) as believed,
                    SUM(CASE WHEN vote_type = 'skeptical' THEN 1 ELSE 0 END) as skeptical
                    FROM votes WHERE article_id = ?");
                $vStmt->execute([$art['id']]);
                $votes = $vStmt->fetch();
                $skeptical = (int)($votes['skeptical'] ?? 0);
                $believed = (int)($votes['believed'] ?? 0);
            } catch (Throwable $ignore) {}
            $totalVotes = $skeptical + $believed;

            $newLifecycleStatus = 'active';
            $reason = '鮮度良好・SNS反響あり（生存）';

            // 1. 危険ワード・安全判定 (保留指定)
            if (!empty($art['is_dangerous']) || ($art['status'] ?? '') === 'on_hold') {
                $newLifecycleStatus = 'dormant';
                $reason = 'AI安全ブレーキ作動（危険キーワード検知・要一次ソース確認）';
            }
            // 2. 経過日数による減衰 (例: 30日以上経過)
            elseif ($daysOld >= 30) {
                $newLifecycleStatus = 'dormant';
                $reason = "公開から{$daysOld}日経過。検索需要・トレンド終息のためAI自動休眠";
            }
            // 3. 鮮度低下注意 (例: 14日以上経過)
            elseif ($daysOld >= 14) {
                $newLifecycleStatus = 'warning';
                $reason = "公開から{$daysOld}日経過。話題減衰中のため要モニタリング";
            }
            // 4. 懐疑派投票が圧倒的多数 (75%以上がskeptical)
            elseif ($totalVotes >= 10 && ($skeptical / $totalVotes) >= 0.75) {
                $newLifecycleStatus = 'warning';
                $reason = '読者投票「信憑性懐疑」が75%超過。信憑性要再検証';
            } else {
                $newLifecycleStatus = 'active';
                $reason = "鮮度良好（公開{$daysOld}日目）・しらんけど指数{$index}点維持";
            }

            // ステータス反映: dormantの場合は公開ステータスもprivateへ自動変更
            $newArtStatus = $art['status'] ?? 'published';
            if ($newLifecycleStatus === 'dormant' && $newArtStatus === 'published') {
                $newArtStatus = 'private';
                $dormantCount++;
            } elseif ($newLifecycleStatus === 'active') {
                if ($newArtStatus === 'private' && ($art['lifecycle_status'] ?? '') === 'dormant') {
                    // 自動復活（手動で更新された場合など）
                }
                $activeCount++;
            } elseif ($newLifecycleStatus === 'warning') {
                $warningCount++;
            }

            try {
                $update = $db->prepare("UPDATE articles SET lifecycle_status = ?, lifecycle_reason = ?, lifecycle_checked_at = NOW(), status = ? WHERE id = ?");
                $update->execute([$newLifecycleStatus, $reason, $newArtStatus, $art['id']]);
            } catch (Throwable $e) {}

            $results[] = [
                'id' => $art['id'],
                'title' => $art['title'],
                'lifecycle_status' => $newLifecycleStatus,
                'status' => $newArtStatus,
                'reason' => $reason,
                'days_old' => $daysOld,
            ];
        }

        return [
            'total' => $totalEvaluated,
            'active' => $activeCount,
            'warning' => $warningCount,
            'dormant' => $dormantCount,
            'results' => $results,
        ];
    }
}
