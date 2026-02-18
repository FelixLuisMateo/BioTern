<?php
$base = 'http://127.0.0.1:8000';
$cookie = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'biotern_cookie.txt';

// Get login page to obtain CSRF token
$ch = curl_init($base . '/login');
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
if ($err) {
    echo "GET_ERR: $err\n";
    exit(1);
}

if (! preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
    // try alternative pattern
    if (! preg_match('/name=\'_token\'\s+value=\'([^\']+)\'/', $html, $m)) {
        echo "CSRF_TOKEN_NOT_FOUND\n";
        exit(1);
    }
}
$token = $m[1];

// Prepare login POST
$post = [
    '_token' => $token,
    'login' => 'admin',
    'password' => 'password',
    'remember' => '1',
];

$ch = curl_init($base . '/login');
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
if ($err) {
    echo "POST_ERR: $err\n";
    exit(1);
}

echo "HTTP_CODE: " . ($info['http_code'] ?? 'unknown') . "\n";
echo "FINAL_URL: " . ($info['url'] ?? 'unknown') . "\n";
// Look for an authenticated indicator (dashboard route or logout link)
if (stripos($body, 'dashboard') !== false || stripos($body, 'logout') !== false) {
    echo "LOGIN_OK: probable redirect to dashboard or logout found.\n";
} else {
    // print a short snippet of response for debugging
    $snippet = substr(strip_tags($body), 0, 800);
    echo "LOGIN_FAIL_SNIPPET: " . $snippet . "\n";
}
