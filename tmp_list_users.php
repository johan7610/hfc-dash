use Illuminate\Support\Facades\DB;

$users = DB::table('users')
  ->select('id','name','email')
  ->orderBy('id')
  ->get();

echo $users->toJson(JSON_PRETTY_PRINT), PHP_EOL;
