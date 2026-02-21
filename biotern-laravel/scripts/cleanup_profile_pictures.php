<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('students')->whereNotNull('profile_picture')->get();
$cleared = 0;
foreach ($rows as $s) {
    $p = public_path($s->profile_picture);
    if (!file_exists($p) || !is_file($p)) {
        DB::table('students')->where('id', $s->id)->update(['profile_picture' => null]);
        echo "cleared id={$s->id}\n";
        $cleared++;
    }
}

echo "done. cleared={$cleared}\n";
