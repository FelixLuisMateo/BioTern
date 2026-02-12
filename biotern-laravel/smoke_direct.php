<?php
// Directly instantiate the controller and call handle() to avoid HTTP middleware (CSRF).
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
// Ensure facades and container helpers work when calling controller directly
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
// Bootstrap the application (register service providers, config bindings)
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

try {
    $controller = new App\Http\Controllers\RegisterSubmitController();
    $request = Illuminate\Http\Request::create('/register_submit', 'POST', [
        'role' => 'student',
        'first_name' => 'DirectTinker',
        'last_name' => 'Test',
        'email' => 'direct@example.com',
        'username' => 'direct_user',
        'password' => 'Secret123!',
        'student_id' => 'DIR001',
        'course_id' => '1',
        'section' => '1',
        'address' => 'Direct Address'
    ]);

    $response = $controller->handle($request);
    if ($response instanceof Illuminate\Http\RedirectResponse) {
        echo "REDIRECT: " . $response->getTargetUrl() . PHP_EOL;
    } elseif ($response instanceof Illuminate\Http\Response) {
        echo "HTTP: " . $response->getStatusCode() . PHP_EOL;
        echo $response->getContent() . PHP_EOL;
    } else {
        var_dump($response);
    }
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
