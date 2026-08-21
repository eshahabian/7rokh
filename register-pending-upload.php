<?php
declare(strict_types=1);

/**
 * آپلود فوری عکس/ویدیو هنگام ثبت‌نام — تا با رفرش یا قطع اینترنت از بین نروند
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/rate-limit.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo wp_json_encode(['ok' => false, 'error' => 'روش نامعتبر'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (casting_upload_post_too_large()) {
    http_response_code(413);
    echo wp_json_encode(['ok' => false, 'error' => casting_upload_post_too_large_message()], JSON_UNESCAPED_UNICODE);
    exit;
}

$nonce = (string) ($_POST['_wpnonce'] ?? '');
if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_register')) {
    http_response_code(403);
    echo wp_json_encode(['ok' => false, 'error' => 'نشست منقضی شده. صفحه را رفرش کنید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = casting_rate_limit_check('register_upload');
if ($limit !== null) {
    http_response_code(429);
    echo wp_json_encode(['ok' => false, 'error' => $limit], JSON_UNESCAPED_UNICODE);
    exit;
}

$error = casting_register_pending_capture_uploads();
if ($error !== '') {
    casting_rate_limit_hit('register_upload');
    http_response_code(400);
    echo wp_json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

casting_rate_limit_clear('register_upload');
$pending = casting_register_pending_media_get();
$portraits = [];
foreach ($pending['portraits'] as $slot => $item) {
    if (!is_array($item)) {
        continue;
    }
    $portraits[$slot] = [
        'url'  => (string) ($item['url'] ?? ''),
        'full' => (string) ($item['full'] ?? ($item['url'] ?? '')),
        'name' => (string) ($item['name'] ?? ''),
    ];
}
$video = null;
if (is_array($pending['video'] ?? null)) {
    $video = [
        'url'  => (string) ($pending['video']['url'] ?? ''),
        'name' => (string) ($pending['video']['name'] ?? ''),
    ];
}

echo wp_json_encode([
    'ok'        => true,
    'portraits' => $portraits,
    'video'     => $video,
], JSON_UNESCAPED_UNICODE);
