<?php
$base = 'http://127.0.0.1:8000';
$cookie = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'biotern_cookie.txt';

$ch = curl_init($base . '/register');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
]);
$html = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);
if ($err) { echo "GET_ERR: $err\n"; exit(1); }

if (! preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
    if (! preg_match('/name=\'_token\'\s+value=\'([^\']+)\'/', $html, $m)) {
        echo "CSRF_TOKEN_NOT_FOUND\n"; exit(1);
    }
}
$token = $m[1];

$post = [
    '_token' => $token,
    'role' => 'admin',
    'first_name' => 'Auto',
    'last_name' => 'Tester',
    'email' => 'autotester@example.com',
    'phone' => '0000000000',
    'username' => 'autotester',
    'account_email' => 'autotester@example.com',
    'password' => 'password',
    'confirm_password' => 'password'
];

$ch = curl_init($base . '/register_submit');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post,
    CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
]);
$body = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);
if ($err) { echo "POST_ERR: $err\n"; exit(1); }

echo "HTTP_CODE: " . ($info['http_code'] ?? 'unknown') . "\n";
echo "FINAL_URL: " . ($info['url'] ?? 'unknown') . "\n";
if (stripos($body, 'Registration successful') !== false || stripos($body, 'registered=admin') !== false) {
    echo "REGISTER_OK\n";
} else {
    $snippet = substr(strip_tags($body), 0, 800);
    echo "REGISTER_FAIL_SNIPPET: " . $snippet . "\n";
}
