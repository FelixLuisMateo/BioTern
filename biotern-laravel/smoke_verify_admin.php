<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
// Bootstrap the application
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
// Enable facades
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

use Illuminate\Support\Facades\DB;

$email = 'admin@biotern.com';
$user = DB::table('users')->where('email', $email)->first();
if ($user) {
    echo "FOUND\n";
    var_export($user);
    echo "\n";
} else {
    echo "NOT FOUND\n";
}
