use Illuminate\Support\Facades\DB;

$users = DB::table("users")
  ->select("id","name","email")
  ->where("name","like","%Angel%")
  ->orWhere("email","like","%angel%")
  ->orderBy("id")
  ->get();

echo "=== USERS MATCHING ANGEL ===\n";
echo $users->toJson(JSON_PRETTY_PRINT), "\n\n";

if ($users->count() === 0) {
  echo "No Angelique user found.\n";
  return;
}

$uid = $users->first()->id;

echo "=== USING user_id = {$uid} (first match) ===\n\n";

$ws = DB::table("worksheets")->where("user_id", $uid)->get();
echo "=== WORKSHEETS ROWS FOR user_id {$uid} ===\n";
echo $ws->toJson(JSON_PRETTY_PRINT), "\n\n";

$cols = DB::select('PRAGMA table_info("worksheets")');
$rowObj = DB::table("worksheets")->where("user_id", $uid)->first();

if (!$rowObj) {
  echo "NO worksheet row for user_id={$uid}\n";
  return;
}

$row = (array) $rowObj;

$sus = [];
foreach ($cols as $c) {
  $name = $c->name;
  $type = strtoupper((string)$c->type);
  if (!array_key_exists($name, $row)) continue;
  $v = $row[$name];
  if ($v === null) continue;

  if (is_string($v)) {
    $trim = trim($v);
    if ($trim === "") continue;

    $looksNumericCol =
      (str_contains($type, "INT") ||
       str_contains($type, "REAL") ||
       str_contains($type, "NUM") ||
       str_contains($type, "DEC"));

    if ($looksNumericCol && !is_numeric($trim)) {
      $sus[$name] = ["type" => $type, "value" => $v];
    }
  }
}

echo "=== SUSPICIOUS NON-NUMERIC VALUES IN NUMERIC COLUMNS (user_id {$uid}) ===\n";
echo json_encode($sus, JSON_PRETTY_PRINT), "\n";
