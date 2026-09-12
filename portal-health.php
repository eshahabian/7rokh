<?php
/**
 * تشخیص خرابی پورتال — وردپرس را load نمی‌کند.
 * کلید را در config.local.php با CASTING_HEALTH_KEY تعریف کنید.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    require_once $local;
}

$expected = '';
if (defined('CASTING_HEALTH_KEY')) {
    $expected = (string) CASTING_HEALTH_KEY;
}
if ($expected === '') {
    $env = getenv('CASTING_HEALTH_KEY');
    $expected = is_string($env) ? $env : '';
}

$key = (string) ($_GET['key'] ?? '');
if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

require_once __DIR__ . '/includes/safe-load.php';

$files = [
    'index.php',
    'register.php',
    'login.php',
    'edit-profile.php',
    'config.php',
    'includes/bootstrap.php',
    'includes/safe-load.php',
    'includes/profile.php',
    'includes/landing-showcase.php',
    'includes/layout.php',
    'includes/ad-posters.php',
    'includes/auth.php',
    'includes/panel-profile.php',
    'includes/jalali.php',
    'includes/activities.php',
    'includes/hafez.php',
    'includes/captcha.php',
    'includes/otp.php',
    'includes/webhook.php',
    'includes/rules-content.php',
    'mu-plugin/casting-wp-admin-guard.php',
    'mu-plugin/casting-force-domain.php',
];

$optional = [
    'includes/hafez.php' => true,
    'mu-plugin/casting-force-domain.php' => true,
];

echo "7rokh casting-portal health\n";
echo 'PHP ' . PHP_VERSION . "\n\n";

$bad = 0;
foreach ($files as $rel) {
    $path = __DIR__ . '/' . $rel;
    $exists = is_file($path);
    $size = $exists ? (int) filesize($path) : 0;
    $md5 = ($exists && $size > 0) ? (string) md5_file($path) : '-';
    $err = '';
    if (!$exists) {
        $parse = 'MISSING';
        if (empty($optional[$rel])) {
            $bad++;
        }
    } elseif (!casting_php_file_parses($path)) {
        $parse = 'FAIL';
        $err = casting_safe_load_error();
        $bad++;
    } else {
        $parse = 'ok';
    }
    echo sprintf('%-42s %8d  %-7s  %s', $rel, $size, $parse, $md5);
    if ($err !== '') {
        echo '  ' . $err;
    }
    echo "\n";
}

echo "\n";
echo $bad === 0 ? "ALL PARSE OK\n" : "BROKEN FILES: {$bad}\n";
