use Illuminate\Support\Facades\DB;

$uid = 30;

$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$out = [];

foreach ($tables as $t) {
  $name = $t->name;

  if (str_starts_with($name, 'sqlite_')) continue;

  $cols = DB::select("PRAGMA table_info('$name')");
  $hasUserId = false;

  foreach ($cols as $c) {
    if ($c->name === 'user_id') { $hasUserId = true; break; }
  }

  if (!$hasUserId) continue;

  try {
    $cnt = DB::table($name)->where('user_id', $uid)->count();
    if ($cnt > 0) $out[] = ['table' => $name, 'count' => $cnt];
  } catch (Throwable $e) {
  }
}

echo json_encode($out, JSON_PRETTY_PRINT), PHP_EOL;
