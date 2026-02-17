<?php
$dbFile = __DIR__ . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "database.sqlite";
if (!file_exists($dbFile)) {
  fwrite(STDERR, "FAIL: DB not found at $dbFile\n");
  exit(1);
}

$uid = 30; // Angelique

$pdo = new PDO("sqlite:" . $dbFile, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "DB: $dbFile\n";
echo "User ID: $uid\n\n";

function q($pdo, $sql, $params = []) {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

$u = q($pdo, "SELECT id,name,email FROM users WHERE id = ?", [$uid]);
echo "=== USER ===\n";
echo json_encode($u, JSON_PRETTY_PRINT), "\n\n";

$ws = q($pdo, "SELECT * FROM worksheets WHERE user_id = ?", [$uid]);
echo "=== WORKSHEETS (rows for user_id) ===\n";
echo json_encode($ws, JSON_PRETTY_PRINT), "\n\n";

$tables = q($pdo, "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$out = [];
foreach ($tables as $t) {
  $name = $t["name"];
  if (strpos($name, "sqlite_") === 0) continue;

  $cols = q($pdo, "PRAGMA table_info('$name')");
  $hasUserId = false;
  foreach ($cols as $c) {
    if (($c["name"] ?? "") === "user_id") { $hasUserId = true; break; }
  }
  if (!$hasUserId) continue;

  try {
    $cnt = q($pdo, "SELECT COUNT(*) AS c FROM '$name' WHERE user_id = ?", [$uid])[0]["c"] ?? 0;
    if ((int)$cnt > 0) $out[] = ["table" => $name, "count" => (int)$cnt];
  } catch (Throwable $e) {}
}

echo "=== TABLES WITH user_id rows for Angelique ===\n";
echo json_encode($out, JSON_PRETTY_PRINT), "\n";