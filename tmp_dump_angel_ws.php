use Illuminate\Support\Facades\DB;

$uid = 30;

$ws = DB::table('worksheets')->where('user_id', $uid)->get();
echo $ws->toJson(JSON_PRETTY_PRINT), PHP_EOL;
