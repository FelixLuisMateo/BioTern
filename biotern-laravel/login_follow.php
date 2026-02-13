<?php
$base = 'http://127.0.0.1:8000';
$cookie = __DIR__ . '/cookie_follow.txt';
@unlink($cookie);

function do_request($url, $method = 'GET', $data = null, $cookieFile = null){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $out = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$info, $out];
}

// GET login page
list($info, $out) = do_request($base . '/login', 'GET', null, $cookie);
// extract CSRF
if (preg_match('/name="_token" value="([^"]+)"/', $out, $m)) {
    $token = $m[1];
} else {
    $token = '';
}

echo "Extracted CSRF token: {$token}\n\n";

// POST credentials
$post = http_build_query(['login' => 'admin@biotern.com', 'password' => 'password', '_token' => $token]);
list($info, $out) = do_request($base . '/login', 'POST', $post, $cookie);

function print_headers_and_status($info, $out){
    $header_len = $info['header_size'];
    $header = substr($out, 0, $header_len);
    $body = substr($out, $header_len);
    echo "HTTP/{$info['http_version']} {$info['http_code']}\n";
    echo "--- Response headers ---\n";
    echo $header . "\n";
    echo "--- Body snippet (first 1000 chars) ---\n";
    echo substr($body, 0, 1000) . "\n\n";
}

print_headers_and_status($info, $out);

// Manually follow redirects (up to 5)
$max = 5;
$redirects = 0;
$currentOut = $out;
while ($redirects < $max) {
    if (!preg_match('/\r\nLocation:\s*(.+)\r\n/i', $currentOut, $m)) {
        break;
    }
    $loc = trim($m[1]);
    if (strpos($loc, 'http') !== 0) {
        // make absolute
        $loc = rtrim($base, '/') . '/' . ltrim($loc, '/');
    }
    echo "Following redirect to: {$loc}\n\n";
    list($info, $out) = do_request($loc, 'GET', null, $cookie);
    print_headers_and_status($info, $out);
    $currentOut = $out;
    $redirects++;
}

echo "--- Cookie file contents ---\n";
if (file_exists($cookie)) {
    echo file_get_contents($cookie) . "\n";
} else {
    echo "(no cookie file)\n";
}

echo "Done.\n";
