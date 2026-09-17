<?php
/**
 * デフォルトの admin.php
 * セキュリティ向上のため、第三者からのアクセスには 404 Not Found を返し、
 * 存在を完全に隠蔽します。
 */
header("HTTP/1.1 404 Not Found");
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>404 Not Found</title>
</head>
<body style="font-family:sans-serif; text-align:center; padding:50px;">
    <h1>404 Not Found</h1>
    <p>The requested URL was not found on this server.</p>
</body>
</html>
