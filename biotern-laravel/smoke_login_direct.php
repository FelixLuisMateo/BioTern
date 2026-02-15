<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
// Bootstrap the application
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
// Enable facades
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

use Illuminate\Http\Request;

// Local admin credentials used earlier
$login = 'admin@biotern.com';
$password = 'password';

$request = Request::create('/login', 'POST', ['login' => $login, 'password' => $password]);
// Resolve controller and call method directly (bypass middleware)
$session = null;
try {
    $session = $app->make('session.store');
} catch (Exception $e) {
    // ignore
}
if ($session) {
    $request->setLaravelSession($session);
}

$controller = app()->make(\App\Http\Controllers\AuthController::class);
$response = $controller->login($request);

if (is_object($response) && method_exists($response, 'getContent')) {
    echo "RESPONSE (status: " . ($response->getStatusCode() ?? 'n/a') . ")\n";
    echo $response->getContent() . "\n";
} else {
    echo "RESPONSE: ";
    var_export($response);
    echo "\n";
}
