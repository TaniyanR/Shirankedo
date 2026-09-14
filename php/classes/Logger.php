<?php
/**
 * ログ記録クラス
 */
class Logger {
    public static function log(string $category, string $message, ?int $siteId = null, ?array $details = null): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO system_logs (site_id, category, message, details_json, created_at) 
                                  VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $siteId,
                $category,
                $message,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null
            ]);
        } catch (Exception $e) {
            error_log("Shirankedo Logger Failure: " . $e->getMessage());
        }
    }
}
