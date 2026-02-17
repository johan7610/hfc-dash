use Illuminate\Support\Facades\DB;

$users = DB::table("users")
  ->select("id","name","email")
  ->whereRaw('LOWER(name) LIKE "%" || ? || "%"', ['angel'])
  ->orWhereRaw('LOWER(email) LIKE "%" || ? || "%"', ['angel'])
  ->orderBy("id")
  ->get();

echo $users->toJson(JSON_PRETTY_PRINT), "\n";
