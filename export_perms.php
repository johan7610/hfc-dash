<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$rows = DB::table('role_permissions')->get()->map(fn($r) => $r->role.','.$r->permission_key.','.($r->scope ?? ''))->implode(PHP_EOL);
file_put_contents(storage_path('role_permissions_export.csv'), $rows);
echo 'Exported ' . DB::table('role_permissions')->count() . " rows\n";
