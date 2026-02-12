<?php
// One-off smoke tester: boot Laravel and POST to /register_submit
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$postData = [
    'role' => 'student',
    'first_name' => 'SmokeTinker',
    'last_name' => 'Test',
    'email' => 'smoke+tinker@example.com',
    'username' => 'smoke_tinker',
    'password' => 'Secret123!',
    'student_id' => 'SMK999',
    'course_id' => '1',
    'section' => '1',
    'address' => 'Smoke Address'
];

$request = Illuminate\Http\Request::create('/register_submit', 'POST', $postData);
try {
    $response = $kernel->handle($request);
    echo "HTTP:" . $response->getStatusCode() . PHP_EOL;
    echo $response->getContent() . PHP_EOL;
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
