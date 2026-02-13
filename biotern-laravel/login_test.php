<?php
$base = 'http://127.0.0.1:8000';
$cookie = __DIR__ . '/cookie.txt';
@unlink($cookie);
$ch = curl_init($base . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$out = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// extract _token from the login page form
preg_match('/name="_token" value="([^"]+)"/', $out, $m);
$token = $m[1] ?? '';

$ch = curl_init($base . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['login' => 'admin@biotern.com', 'password' => 'password', '_token' => $token]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$out = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP info:\n";
print_r($info);
echo "\nResponse:\n";
echo $out;
echo "\nCookie file contents:\n";
echo file_get_contents($cookie);
