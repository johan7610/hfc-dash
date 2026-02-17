<?php
$dbFile = __DIR__ . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "database.sqlite";
$pdo = new PDO("sqlite:" . $dbFile);

$uid = 30;
$period = '2026-02';

$live = $pdo->prepare("SELECT COUNT(*) FROM listing_stock_agents WHERE user_id=?");
$live->execute([$uid]);
$liveCount = (int)$live->fetchColumn();

echo "Live listing count = $liveCount\n";

$upd = $pdo->prepare("
UPDATE worksheets
SET current_listings = ?, updated_at = datetime('now')
WHERE user_id = ? AND period = ?
");
$upd->execute([$liveCount, $uid, $period]);

echo "Rows updated = " . $upd->rowCount() . "\n";

$check = $pdo->prepare("SELECT user_id,period,current_listings FROM worksheets WHERE user_id=? AND period=?");
$check->execute([$uid,$period]);

echo json_encode($check->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT), "\n";