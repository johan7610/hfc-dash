<?php
$dbFile = __DIR__ . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "database.sqlite";
$pdo = new PDO("sqlite:" . $dbFile, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function one(PDO $pdo, string $sql, array $params=[]): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

$uid = 30;

echo "=== worksheet stored current_listings ===\n";
echo json_encode(one($pdo, "SELECT user_id,period,current_listings FROM worksheets WHERE user_id=? ORDER BY period DESC", [$uid]), JSON_PRETTY_PRINT), "\n\n";

echo "=== live listing_stock_agents rows (assignments) ===\n";
echo json_encode(one($pdo, "SELECT COUNT(*) AS c FROM listing_stock_agents WHERE user_id=?", [$uid]), JSON_PRETTY_PRINT), "\n\n";

echo "=== live listing_stocks rows (stocks) ===\n";
echo json_encode(one($pdo, "SELECT COUNT(*) AS c FROM listing_stocks WHERE user_id=?", [$uid]), JSON_PRETTY_PRINT), "\n\n";

echo "=== sample listing_stock_agents (up to 10) ===\n";
echo json_encode(one($pdo, "SELECT * FROM listing_stock_agents WHERE user_id=? LIMIT 10", [$uid]), JSON_PRETTY_PRINT), "\n\n";

echo "=== sample listing_stocks (up to 10) ===\n";
echo json_encode(one($pdo, "SELECT * FROM listing_stocks WHERE user_id=? LIMIT 10", [$uid]), JSON_PRETTY_PRINT), "\n";