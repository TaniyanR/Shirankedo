<?php
/**
 * データベース接続 & クエリラッパー (PDO Prepared Statement 徹底)
 */
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
        return self::$instance;
    }

    public static function resetConnection(): void {
        self::$instance = null;
    }

    /**
     * 指定したパラメータまたは現在設定でDB接続をテストする
     */
    public static function testConnection(
        ?string &$errorMessage = null,
        ?string $host = null,
        ?string $port = null,
        ?string $name = null,
        ?string $user = null,
        ?string $pass = null
    ): ?PDO {
        $host = $host ?? DB_HOST;
        $port = $port ?? DB_PORT;
        $name = $name ?? DB_NAME;
        $user = $user ?? DB_USER;
        $pass = $pass ?? DB_PASS;

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ];
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            return null;
        }
    }
}
