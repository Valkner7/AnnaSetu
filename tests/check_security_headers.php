<?php
// Run: php tests/check_security_headers.php   (exit 0 = pass)
// Starts PHP's built-in web server on a free local port, requests the login page (no database
// needed) and checks the real response headers, with and without an HTTPS proxy in front.
$root = dirname(__DIR__);
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; $fail += $ok ? 0 : 1; }

// Find a free port.
$sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$sock) { check('could find a free port', false); exit(1); }
$port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
fclose($sock);

$cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$root/public", "$root/public/index.php"];
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/sec_headers_srv.log', 'w'], 2 => ['redirect', 1]], $pipes);
if (!is_resource($proc)) { check('could start the test web server', false); exit(1); }

function fetch($port, $extra = '') {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => $extra, 'ignore_errors' => true, 'timeout' => 5, 'follow_location' => 0]]);
    $body = @file_get_contents("http://127.0.0.1:$port/?url=auth/login", false, $ctx);
    return $body === false ? null : $http_response_header;
}
function header_value($headers, $name) {
    foreach ($headers ?? [] as $h) { if (stripos($h, $name . ':') === 0) { return trim(substr($h, strlen($name) + 1)); } }
    return null;
}

try {
    $plain = null;
    for ($i = 0; $i < 40 && $plain === null; $i++) { $plain = fetch($port); if ($plain === null) { usleep(100000); } }
    check('test web server answered', $plain !== null);
    if ($plain !== null) {
        $https = fetch($port, "X-Forwarded-Proto: https\r\n");
        check('X-Content-Type-Options: nosniff', header_value($plain, 'X-Content-Type-Options') === 'nosniff');
        check('X-Frame-Options: SAMEORIGIN', header_value($plain, 'X-Frame-Options') === 'SAMEORIGIN');
        check('Referrer-Policy is set', header_value($plain, 'Referrer-Policy') === 'strict-origin-when-cross-origin');
        $pp = (string) header_value($plain, 'Permissions-Policy');
        check('Permissions-Policy keeps location for this site', strpos($pp, 'geolocation=(self)') !== false);
        check('Permissions-Policy keeps the microphone for this site (voice question)', strpos($pp, 'microphone=(self)') !== false);
        check('Permissions-Policy turns camera, payment and usb off', strpos($pp, 'camera=()') !== false && strpos($pp, 'payment=()') !== false && strpos($pp, 'usb=()') !== false);
        $csp = (string) header_value($plain, 'Content-Security-Policy');
        check('Content-Security-Policy is sent', $csp !== '');
        check('CSP blocks plugins and foreign base tags', strpos($csp, "object-src 'none'") !== false && strpos($csp, "base-uri 'self'") !== false);
        check('CSP only lets this site frame it and post forms', strpos($csp, "frame-ancestors 'self'") !== false && strpos($csp, "form-action 'self'") !== false);
        check('CSP allows the CDNs the pages use', strpos($csp, 'https://cdn.jsdelivr.net') !== false && strpos($csp, 'https://cdnjs.cloudflare.com') !== false);
        check('CSP allows blob audio (spoken advisory reply)', strpos($csp, 'media-src') !== false && strpos($csp, 'blob:') !== false);
        check('no HSTS over plain HTTP', header_value($plain, 'Strict-Transport-Security') === null);
        $hsts = (string) header_value($https, 'Strict-Transport-Security');
        check('HSTS sent when the request came over HTTPS', preg_match('/^max-age=\d{6,}$/', $hsts) === 1);
        check('HSTS has no includeSubDomains or preload', stripos($hsts, 'includeSubDomains') === false && stripos($hsts, 'preload') === false);
    }
} finally {
    proc_terminate($proc);
    proc_close($proc);
}
exit($fail ? 1 : 0);
