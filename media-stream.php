<?php
declare(strict_types=1);

/**
 * استریم فایل پیوست فقط برای کاربر مجاز پورتال.
 * URL مستقیم wp-content در پلیر محافظت‌شده استفاده نمی‌شود.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/media-protect.php';

casting_nocache();

$user = casting_require_api_casting_user(false);
$viewer_id = (int) $user->ID;

$aid = (int) ($_GET['aid'] ?? 0);
$nonce = (string) ($_GET['n'] ?? '');
if ($aid <= 0 || !wp_verify_nonce($nonce, 'casting_stream_' . $aid)) {
    status_header(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'دسترسی نامعتبر است.';
    exit;
}

if (!casting_user_can_stream_attachment($viewer_id, $aid)) {
    status_header(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'اجازه مشاهده این فایل را ندارید.';
    exit;
}

$path = get_attached_file($aid);
if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
    status_header(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'فایل پیدا نشد.';
    exit;
}

$mime = get_post_mime_type($aid);
if (!is_string($mime) || $mime === '') {
    $ftype = wp_check_filetype($path);
    $mime = is_array($ftype) && !empty($ftype['type']) ? (string) $ftype['type'] : 'application/octet-stream';
}

$size = (int) filesize($path);
$start = 0;
$end = max(0, $size - 1);
$status = 200;

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') {
        $start = (int) $m[1];
    }
    if ($m[2] !== '') {
        $end = (int) $m[2];
    }
    if ($end >= $size) {
        $end = $size - 1;
    }
    if ($start > $end || $start < 0) {
        status_header(416);
        header("Content-Range: bytes */{$size}");
        exit;
    }
    $status = 206;
}

$length = $end - $start + 1;

status_header($status);
header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Content-Length: ' . $length);
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="media"');
if ($status === 206) {
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}

$fp = fopen($path, 'rb');
if ($fp === false) {
    status_header(500);
    exit;
}
if ($start > 0) {
    fseek($fp, $start);
}
$remaining = $length;
$chunk = 8192;
while ($remaining > 0 && !feof($fp)) {
    $read = ($remaining > $chunk) ? $chunk : $remaining;
    $data = fread($fp, $read);
    if ($data === false) {
        break;
    }
    echo $data;
    $remaining -= strlen($data);
    if (connection_aborted()) {
        break;
    }
}
fclose($fp);
exit;
