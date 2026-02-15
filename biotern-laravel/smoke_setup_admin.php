<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

// Bootstrap and make facades work
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

try {
    $request = Illuminate\Http\Request::create('/setup-admin', 'GET');
    $response = $kernel->handle($request);
    echo "HTTP:" . $response->getStatusCode() . PHP_EOL;
    echo $response->getContent() . PHP_EOL;
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
