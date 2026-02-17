<?php
$dbFile = __DIR__ . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "database.sqlite";
$pdo = new PDO("sqlite:" . $dbFile, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function q(PDO $pdo, string $sql, array $params = []): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

$uids = [30 => "Angelique", 36 => "Barbara", 37 => "Cherise"];

echo "DB: $dbFile\n\n";

foreach ($uids as $uid => $label) {
  echo "============================\n";
  echo "USER $label (id=$uid)\n";
  echo "============================\n";

  $u = q($pdo, "SELECT id,name,email FROM users WHERE id=?", [$uid]);
  echo "-- user --\n", json_encode($u, JSON_PRETTY_PRINT), "\n\n";

  $ws = q($pdo, "SELECT * FROM worksheets WHERE user_id=? ORDER BY period DESC", [$uid]);
  echo "-- worksheets --\n", json_encode($ws, JSON_PRETTY_PRINT), "\n\n";

  $tg = q($pdo, "SELECT * FROM targets WHERE user_id=? ORDER BY period DESC", [$uid]);
  echo "-- targets --\n", json_encode($tg, JSON_PRETTY_PRINT), "\n\n";
}
