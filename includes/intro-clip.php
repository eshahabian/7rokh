<?php
declare(strict_types=1);

/**
 * کلیپ معرفی اعضا — حداکثر ۹۰ ثانیه، یک فایل برای هر عضو.
 * آپلود: عضو دارای اشتراک ویژه، یا مدیر پورتال (مستثنی از شرط اشتراک).
 * مشاهده فهرست: فقط مدیران پورتال (eshahabian و ardavan).
 */

const CASTING_INTRO_CLIP_MAX_SECONDS = 90.0;
const CASTING_INTRO_CLIP_META = 'casting_intro_clip';

function casting_intro_clip_is_portal_admin(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (!function_exists('casting_user_is_super_admin')) {
        $admin = __DIR__ . '/admin-access.php';
        if (is_file($admin)) {
            require_once $admin;
        }
    }
    if (function_exists('casting_user_is_super_admin') && casting_user_is_super_admin($user_id)) {
        return true;
    }
    if (function_exists('casting_user_is_listed_portal_admin') && casting_user_is_listed_portal_admin($user_id)) {
        return true;
    }
    if (function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id)) {
        return true;
    }

    return false;
}

function casting_user_can_upload_intro_clip(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (casting_intro_clip_is_portal_admin($user_id)) {
        return true;
    }
    if (!function_exists('casting_user_is_premium')) {
        require_once __DIR__ . '/premium.php';
    }

    return casting_user_is_premium($user_id);
}

function casting_user_can_review_intro_clips(int $user_id): bool
{
    return casting_intro_clip_is_portal_admin($user_id);
}

function casting_user_can_view_intro_clip(int $viewer_id, int $owner_id): bool
{
    if ($viewer_id <= 0 || $owner_id <= 0) {
        return false;
    }
    if ($viewer_id === $owner_id) {
        return true;
    }

    return casting_user_can_review_intro_clips($viewer_id);
}

function casting_intro_clip_mobile(int $user_id): string
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
    if (preg_match('/^09\d{9}$/', $mobile) === 1) {
        return $mobile;
    }

    return '';
}

function casting_intro_clip_safe_label(string $value, int $max = 60): string
{
    $value = trim($value);
    $value = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0"], '', $value);
    $value = preg_replace('/\s+/u', '_', $value) ?? '';
    $value = preg_replace('/[^\p{L}\p{N}_.-]+/u', '', $value) ?? '';
    $value = trim($value, '._-');
    if ($value === '' || $value === '.' || $value === '..') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    return substr($value, 0, $max);
}

/**
 * @return array{file:string,bytes:int,duration:float,mime:string,uploaded_at:string,display_name:string,mobile:string}|null
 */
function casting_intro_clip_get(int $user_id): ?array
{
    if ($user_id <= 0) {
        return null;
    }
    $raw = get_user_meta($user_id, CASTING_INTRO_CLIP_META, true);
    if (!is_array($raw)) {
        return null;
    }
    $file = basename((string) ($raw['file'] ?? ''));
    if ($file === '' || $file === '.' || $file === '..') {
        return null;
    }

    return [
        'file'         => $file,
        'bytes'        => (int) ($raw['bytes'] ?? 0),
        'duration'     => (float) ($raw['duration'] ?? 0),
        'mime'         => (string) ($raw['mime'] ?? 'video/mp4'),
        'uploaded_at'  => (string) ($raw['uploaded_at'] ?? ''),
        'display_name' => (string) ($raw['display_name'] ?? ''),
        'mobile'       => (string) ($raw['mobile'] ?? ''),
    ];
}

function casting_intro_clip_dir_is_allowed(string $dir): bool
{
    $base = basename(rtrim(str_replace('\\', '/', $dir), '/'));

    return $base === 'casting-intro-clips';
}

function casting_intro_clip_storage_dir(): string
{
    $saved = (string) get_option('casting_intro_clip_dir', '');
    if ($saved !== '' && casting_intro_clip_dir_is_allowed($saved) && is_dir($saved) && is_writable($saved)) {
        casting_intro_clip_protect_dir($saved);

        return $saved;
    }

    $candidates = [];
    if (defined('ABSPATH')) {
        $parent = dirname(rtrim(ABSPATH, '/\\'));
        if ($parent !== '' && $parent !== '.' && $parent !== rtrim(ABSPATH, '/\\')) {
            $candidates[] = $parent . DIRECTORY_SEPARATOR . 'casting-intro-clips';
        }
    }
    if (!function_exists('wp_upload_dir')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $uploads = wp_upload_dir();
    if (is_array($uploads) && empty($uploads['error']) && !empty($uploads['basedir'])) {
        $candidates[] = (string) $uploads['basedir'] . DIRECTORY_SEPARATOR . 'casting-intro-clips';
    }

    foreach ($candidates as $dir) {
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            continue;
        }
        if (!is_dir($dir) || !is_writable($dir) || !casting_intro_clip_dir_is_allowed($dir)) {
            continue;
        }
        casting_intro_clip_protect_dir($dir);
        update_option('casting_intro_clip_dir', $dir, false);

        return $dir;
    }

    return '';
}

function casting_intro_clip_protect_dir(string $dir): void
{
    $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($ht)) {
        $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
        file_put_contents($ht, $rules);
    }
    $index = $dir . DIRECTORY_SEPARATOR . 'index.php';
    if (!is_file($index)) {
        file_put_contents($index, "<?php\nhttp_response_code(403);\nexit;\n");
    }
}

function casting_intro_clip_absolute_path(int $user_id): string
{
    $row = casting_intro_clip_get($user_id);
    if ($row === null) {
        return '';
    }
    $dir = casting_intro_clip_storage_dir();
    if ($dir === '') {
        return '';
    }
    $base = realpath($dir);
    $path = $dir . DIRECTORY_SEPARATOR . $row['file'];
    $real = realpath($path);
    if ($base === false || $real === false || !is_file($real)) {
        return '';
    }
    $baseNorm = rtrim(str_replace('\\', '/', $base), '/') . '/';
    $realNorm = str_replace('\\', '/', $real);
    if (!str_starts_with($realNorm, $baseNorm)) {
        return '';
    }

    return $real;
}

function casting_intro_clip_probe_duration(string $path, string $ext): float
{
    $duration = casting_intro_clip_duration_seconds($path);
    if ($duration > 0) {
        return $duration;
    }
    $ext = strtolower($ext);
    if (!in_array($ext, ['mp4', 'webm', 'mov'], true)) {
        return 0.0;
    }
    $probe = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'casting-intro-probe-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!@copy($path, $probe)) {
        return 0.0;
    }
    $duration = casting_intro_clip_duration_seconds($probe);
    @unlink($probe);

    return $duration;
}

function casting_intro_clip_duration_seconds(string $path): float
{
    if (!is_file($path)) {
        return 0.0;
    }
    if (!function_exists('wp_read_video_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    $meta = wp_read_video_metadata($path);
    if (!is_array($meta) || !isset($meta['length'])) {
        return 0.0;
    }

    return (float) $meta['length'];
}

function casting_intro_clip_duration_label(float $seconds): string
{
    $seconds = (int) round($seconds);
    if ($seconds < 60) {
        return $seconds . ' ثانیه';
    }
    $minutes = intdiv($seconds, 60);
    $rest = $seconds % 60;
    if ($rest === 0) {
        return $minutes . ' دقیقه';
    }

    return $minutes . ' دقیقه و ' . $rest . ' ثانیه';
}

function casting_intro_clip_format_when(string $mysql): string
{
    $mysql = trim($mysql);
    if ($mysql === '') {
        return '—';
    }
    if (!function_exists('casting_format_jalali_from_gregorian')) {
        $jalali = __DIR__ . '/jalali.php';
        if (is_file($jalali)) {
            require_once $jalali;
        }
    }
    if (!function_exists('casting_format_jalali_from_gregorian')) {
        return $mysql;
    }
    $date = casting_format_jalali_from_gregorian(substr($mysql, 0, 10));
    $time = strlen($mysql) >= 16 ? substr($mysql, 11, 5) : '';
    if ($date === '') {
        return $mysql;
    }

    return trim($date . ($time !== '' ? '، ' . $time : ''));
}

function casting_intro_clip_stream_url(int $owner_id): string
{
    if ($owner_id <= 0 || casting_intro_clip_get($owner_id) === null) {
        return '';
    }

    return casting_url('intro-clip-stream.php?' . http_build_query([
        'uid' => $owner_id,
        'n'   => wp_create_nonce('casting_intro_clip_' . $owner_id),
    ]));
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_intro_clip_save_upload(int $user_id, string $field): array
{
    if (!casting_user_can_upload_intro_clip($user_id)) {
        return ['ok' => false, 'error' => 'ارسال کلیپ فقط برای اعضای دارای اشتراک ویژه است. مدیران پورتال از این شرط مستثنی هستند.'];
    }
    if (empty($_FILES[$field]['name']) || !is_array($_FILES[$field])) {
        return ['ok' => false, 'error' => 'یک ویدیو انتخاب کنید.'];
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
        return ['ok' => false, 'error' => 'حساب پیدا نشد.'];
    }

    $mobile = casting_intro_clip_mobile($user_id);
    $is_admin = casting_intro_clip_is_portal_admin($user_id);
    if ($mobile === '' && !$is_admin) {
        return ['ok' => false, 'error' => 'شماره موبایل حساب ثبت نشده. اول شماره را در بخش تغییر شماره ذخیره کنید تا فایل با نام و شماره شما روی هاست بماند.'];
    }

    if (!function_exists('casting_require_media_includes')) {
        require_once __DIR__ . '/profile.php';
    }
    casting_require_media_includes();

    $file = &$_FILES[$field];
    $norm = casting_normalize_uploaded_file_type($file, 'video');
    if (!$norm['ok']) {
        return ['ok' => false, 'error' => $norm['error']];
    }
    $ftype = (string) ($norm['type'] ?? '');
    $allowed = [
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
    ];
    $name = strtolower((string) ($file['name'] ?? ''));
    $ext_ok = preg_match('/\.(mp4|webm|mov)$/', $name) === 1;
    if (!isset($allowed[$ftype]) && !$ext_ok) {
        return ['ok' => false, 'error' => 'فقط ویدیو MP4، WebM یا MOV مجاز است.'];
    }
    $ext = $allowed[$ftype] ?? strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4', 'webm', 'mov'], true)) {
        return ['ok' => false, 'error' => 'فقط ویدیو MP4، WebM یا MOV مجاز است.'];
    }
    $size_check = casting_uploaded_file_within_limit($file, 'video');
    if (!$size_check['ok']) {
        return ['ok' => false, 'error' => $size_check['error']];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'فایل آپلود معتبر نیست.'];
    }

    $duration = casting_intro_clip_probe_duration($tmp, $ext);
    if ($duration <= 0) {
        return ['ok' => false, 'error' => 'مدت ویدیو از فایل خوانده نشد. یک MP4 با مدت مشخص و حداکثر ۹۰ ثانیه بفرستید.'];
    }
    if ($duration > CASTING_INTRO_CLIP_MAX_SECONDS + 0.5) {
        return [
            'ok'    => false,
            'error' => 'این ویدیو ' . casting_intro_clip_duration_label($duration) . ' است. حداکثر ۹۰ ثانیه مجاز است.',
        ];
    }

    $dir = casting_intro_clip_storage_dir();
    if ($dir === '') {
        return ['ok' => false, 'error' => 'پوشه ذخیره روی هاست در دسترس نیست.'];
    }

    $display = trim((string) $user->display_name);
    $label = casting_intro_clip_safe_label($display);
    if ($label === '') {
        $label = casting_intro_clip_safe_label((string) $user->user_login);
    }
    if ($label === '') {
        $label = 'user-' . $user_id;
    }
    $phone_part = $mobile !== '' ? $mobile : 'بدون-شماره';
    $filename = casting_intro_clip_unique_filename($user_id, $label . '_' . $phone_part, $ext);

    $dest = $dir . DIRECTORY_SEPARATOR . $filename;
    $staging_base = $filename;
    $suffix = '.' . $ext;
    if (str_ends_with(strtolower($filename), $suffix)) {
        $staging_base = substr($filename, 0, -strlen($suffix));
    }
    $staging = $dir . DIRECTORY_SEPARATOR . $staging_base . '.uploading.' . $ext;
    if (is_file($staging)) {
        unlink($staging);
    }
    if (!move_uploaded_file($tmp, $staging)) {
        return ['ok' => false, 'error' => 'ذخیره فایل روی هاست ناموفق بود.'];
    }

    $saved_duration = casting_intro_clip_duration_seconds($staging);
    if ($saved_duration <= 0 || $saved_duration > CASTING_INTRO_CLIP_MAX_SECONDS + 0.5) {
        unlink($staging);

        return ['ok' => false, 'error' => 'مدت ویدیو بعد از ذخیره تأیید نشد. حداکثر ۹۰ ثانیه مجاز است.'];
    }

    $previous = casting_intro_clip_get($user_id);
    if (is_file($dest)) {
        unlink($dest);
    }
    if (!rename($staging, $dest)) {
        unlink($staging);

        return ['ok' => false, 'error' => 'جابه‌جایی فایل روی هاست ناموفق بود.'];
    }
    @chmod($dest, 0640);

    $mime = $ftype !== '' ? $ftype : 'video/' . ($ext === 'mov' ? 'quicktime' : $ext);
    $row = [
        'file'         => $filename,
        'bytes'        => (int) filesize($dest),
        'duration'     => $saved_duration,
        'mime'         => $mime,
        'uploaded_at'  => current_time('mysql'),
        'display_name' => $display !== '' ? $display : (string) $user->user_login,
        'mobile'       => $mobile,
    ];
    update_user_meta($user_id, CASTING_INTRO_CLIP_META, $row);

    if ($previous !== null && $previous['file'] !== $filename) {
        $old = $dir . DIRECTORY_SEPARATOR . $previous['file'];
        $oldReal = realpath($old);
        $base = realpath($dir);
        if ($oldReal !== false && $base !== false && is_file($oldReal)) {
            $baseNorm = rtrim(str_replace('\\', '/', $base), '/') . '/';
            if (str_starts_with(str_replace('\\', '/', $oldReal), $baseNorm)) {
                unlink($oldReal);
            }
        }
    }

    return ['ok' => true, 'error' => ''];
}

function casting_intro_clip_unique_filename(int $user_id, string $stem, string $ext): string
{
    $stem = casting_intro_clip_safe_label($stem, 80);
    if ($stem === '') {
        $stem = 'user-' . $user_id;
    }
    $ext = strtolower($ext);
    $candidate = $stem . '.' . $ext;
    $dir = casting_intro_clip_storage_dir();
    $path = $dir . DIRECTORY_SEPARATOR . $candidate;
    if (!is_file($path)) {
        return $candidate;
    }
    $owner = casting_intro_clip_owner_of_basename($candidate);
    if ($owner === $user_id) {
        return $candidate;
    }

    return $stem . '_' . $user_id . '.' . $ext;
}

function casting_intro_clip_owner_of_basename(string $basename): int
{
    $users = get_users([
        'meta_key' => CASTING_INTRO_CLIP_META,
        'number'   => 500,
        'fields'   => 'ID',
    ]);
    foreach ($users as $id) {
        $row = casting_intro_clip_get((int) $id);
        if ($row !== null && $row['file'] === $basename) {
            return (int) $id;
        }
    }

    return 0;
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_intro_clip_delete(int $user_id): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'حساب نامعتبر است.'];
    }
    $row = casting_intro_clip_get($user_id);
    if ($row === null) {
        return ['ok' => false, 'error' => 'کلیپی برای حذف نیست.'];
    }
    $path = casting_intro_clip_absolute_path($user_id);
    delete_user_meta($user_id, CASTING_INTRO_CLIP_META);
    if ($path !== '' && is_file($path)) {
        unlink($path);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return list<array{user_id:int,name:string,login:string,mobile:string,file:string,duration:float,uploaded_at:string,bytes:int,missing:bool}>
 */
function casting_intro_clip_list(): array
{
    $users = get_users([
        'meta_key' => CASTING_INTRO_CLIP_META,
        'number'   => 500,
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    ]);
    $out = [];
    foreach ($users as $user) {
        if (!$user instanceof WP_User) {
            continue;
        }
        $id = (int) $user->ID;
        $row = casting_intro_clip_get($id);
        if ($row === null) {
            continue;
        }
        $mobile = $row['mobile'] !== '' ? $row['mobile'] : casting_intro_clip_mobile($id);
        $out[] = [
            'user_id'     => $id,
            'name'        => trim((string) $user->display_name) !== '' ? (string) $user->display_name : $row['display_name'],
            'login'       => (string) $user->user_login,
            'mobile'      => $mobile,
            'file'        => $row['file'],
            'duration'    => $row['duration'],
            'uploaded_at' => $row['uploaded_at'],
            'bytes'       => $row['bytes'],
            'missing'     => casting_intro_clip_absolute_path($id) === '',
        ];
    }
    usort($out, static function (array $a, array $b): int {
        return strcmp((string) $b['uploaded_at'], (string) $a['uploaded_at']);
    });

    return $out;
}

function casting_intro_clip_output(int $owner_id): void
{
    $path = casting_intro_clip_absolute_path($owner_id);
    $row = casting_intro_clip_get($owner_id);
    if ($path === '' || $row === null) {
        status_header(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'فایل پیدا نشد.';
        exit;
    }

    $mime = $row['mime'] !== '' ? $row['mime'] : 'video/mp4';
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
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $status = 206;
    }

    $length = $end - $start + 1;
    $ext = strtolower((string) pathinfo($row['file'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4', 'webm', 'mov'], true)) {
        $ext = 'mp4';
    }

    status_header($status);
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $length);
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header(
        'Content-Disposition: inline; filename="clip.' . $ext . '"; filename*=UTF-8\'\'' . rawurlencode($row['file'])
    );
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
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
    $chunk = 65536;
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
}
