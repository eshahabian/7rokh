<?php
declare(strict_types=1);

/**
 * اثر مسابقه «۷رخ از نگاه تو» — بین ۳۰ تا ۹۰ ثانیه.
 * هر ارسال جدا می‌ماند و با تاریخ ارسال از بقیه جدا می‌شود.
 * آپلود: عضو دارای اشتراک ویژه، یا مدیر پورتال (مستثنی از شرط اشتراک).
 * مشاهده فهرست کلیپ‌ها: فقط نام‌های کاربری eshahabian و ardavan.
 */

const CASTING_INTRO_CLIP_MIN_SECONDS = 30.0;
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
    if ($user_id <= 0 || !function_exists('get_user_by')) {
        return false;
    }
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    $login = strtolower((string) $user->user_login);

    return in_array($login, ['eshahabian', 'ardavan'], true);
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
 * @param array<string, mixed> $raw
 * @return array{id:string,file:string,bytes:int,duration:float,mime:string,uploaded_at:string,display_name:string,mobile:string}|null
 */
function casting_intro_clip_normalize_row(array $raw): ?array
{
    $file = basename((string) ($raw['file'] ?? ''));
    if ($file === '' || $file === '.' || $file === '..') {
        return null;
    }
    $uploaded_at = (string) ($raw['uploaded_at'] ?? '');
    $id = strtolower((string) ($raw['id'] ?? ''));
    if (!preg_match('/^[a-f0-9]{8,24}$/', $id)) {
        $id = substr(md5($file . '|' . $uploaded_at), 0, 12);
    }

    return [
        'id'           => $id,
        'file'         => $file,
        'bytes'        => (int) ($raw['bytes'] ?? 0),
        'duration'     => (float) ($raw['duration'] ?? 0),
        'mime'         => (string) ($raw['mime'] ?? 'video/mp4'),
        'uploaded_at'        => $uploaded_at,
        'display_name'       => (string) ($raw['display_name'] ?? ''),
        'mobile'             => (string) ($raw['mobile'] ?? ''),
        'rights_accepted_at' => (string) ($raw['rights_accepted_at'] ?? ''),
        'owner_hidden'       => (string) ($raw['owner_hidden'] ?? '') === '1' ? '1' : '',
        'dir'                => rtrim(str_replace('\\', '/', (string) ($raw['dir'] ?? '')), '/'),
    ];
}

/**
 * @return list<array{id:string,file:string,bytes:int,duration:float,mime:string,uploaded_at:string,display_name:string,mobile:string}>
 */
function casting_intro_clip_rows(int $user_id): array
{
    if ($user_id <= 0) {
        return [];
    }
    $raw = get_user_meta($user_id, CASTING_INTRO_CLIP_META, true);
    if (!is_array($raw) || $raw === []) {
        return [];
    }

    $source = isset($raw['file']) ? [$raw] : $raw;
    $out = [];
    $changed = isset($raw['file']);
    foreach ($source as $item) {
        if (!is_array($item)) {
            continue;
        }
        $row = casting_intro_clip_normalize_row($item);
        if ($row === null) {
            continue;
        }
        if ((string) ($item['id'] ?? '') !== $row['id']) {
            $changed = true;
        }
        $out[] = $row;
    }
    usort($out, static function (array $a, array $b): int {
        return strcmp((string) $b['uploaded_at'], (string) $a['uploaded_at']);
    });
    if ($changed) {
        update_user_meta($user_id, CASTING_INTRO_CLIP_META, $out);
    }

    return $out;
}

/**
 * @return array{id:string,file:string,bytes:int,duration:float,mime:string,uploaded_at:string,display_name:string,mobile:string}|null
 */
function casting_intro_clip_row(int $user_id, string $clip_id): ?array
{
    $clip_id = strtolower($clip_id);
    foreach (casting_intro_clip_rows($user_id) as $row) {
        if ($row['id'] === $clip_id) {
            return $row;
        }
    }

    return null;
}

function casting_intro_clip_dir_is_allowed(string $dir): bool
{
    $base = basename(rtrim(str_replace('\\', '/', $dir), '/'));

    return $base === 'casting-intro-clips';
}

function casting_intro_clip_fs_path(string $dir): string
{
    return str_replace('/', DIRECTORY_SEPARATOR, rtrim(str_replace('\\', '/', $dir), '/'));
}

/**
 * پوشه خصوصی کنار public_html، بعد پوشه آپلود خود وردپرس داخل سایت.
 *
 * @return list<string>
 */
function casting_intro_clip_candidate_dirs(): array
{
    $dirs = [];
    $push = static function (string $dir) use (&$dirs): void {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '' || !casting_intro_clip_dir_is_allowed($dir)) {
            return;
        }
        $dirs[$dir] = $dir;
    };

    $saved = (string) get_option('casting_intro_clip_dir', '');
    if ($saved !== '') {
        $push($saved);
    }
    if (defined('ABSPATH')) {
        $root = rtrim(str_replace('\\', '/', (string) ABSPATH), '/');
        $parent = dirname($root);
        if ($parent !== '' && $parent !== '.' && $parent !== $root) {
            $push($parent . '/casting-intro-clips');
        }
        $push($root . '/wp-content/uploads/casting-intro-clips');
        if (preg_match('#^(.*?)/(?:public_html|7rokh\\.ir)(?:/|$)#', $root, $homeMatch) === 1) {
            $push($homeMatch[1] . '/casting-intro-clips');
        }
    }
    $walk = str_replace('\\', '/', dirname(__DIR__));
    for ($step = 0; $step < 6 && $walk !== '' && $walk !== '/' && $walk !== '.'; $step++) {
        $push($walk . '/casting-intro-clips');
        $up = dirname($walk);
        if ($up === $walk) {
            break;
        }
        $walk = $up;
    }
    if (!function_exists('wp_upload_dir') && defined('ABSPATH')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if (function_exists('wp_upload_dir')) {
        $uploads = wp_upload_dir();
        if (is_array($uploads) && empty($uploads['error']) && !empty($uploads['basedir'])) {
            $push(rtrim(str_replace('\\', '/', (string) $uploads['basedir']), '/') . '/casting-intro-clips');
        }
    }

    return array_values($dirs);
}

function casting_intro_clip_dir_probe(string $fs): bool
{
    if ($fs === '' || !is_dir($fs) || !is_writable($fs)) {
        return false;
    }
    $probe = $fs . DIRECTORY_SEPARATOR . '.write-probe-' . bin2hex(random_bytes(3));
    $written = @file_put_contents($probe, 'ok');
    $visible = $written !== false && is_file($probe);
    if (is_file($probe)) {
        @unlink($probe);
    }

    return $visible;
}

function casting_intro_clip_storage_dir(): string
{
    $usable = [];
    foreach (casting_intro_clip_candidate_dirs() as $dir) {
        $fs = casting_intro_clip_fs_path($dir);
        if (!is_dir($fs) && function_exists('wp_mkdir_p')) {
            wp_mkdir_p($fs);
        }
        if (!casting_intro_clip_dir_probe($fs)) {
            continue;
        }
        $usable[] = $fs;
    }
    if ($usable === []) {
        return '';
    }

    $uploads = '';
    $private = '';
    foreach ($usable as $fs) {
        $norm = str_replace('\\', '/', $fs);
        if (str_contains($norm, '/wp-content/uploads/casting-intro-clips')) {
            if ($uploads === '') {
                $uploads = $fs;
            }
            continue;
        }
        if ($private === '') {
            $private = $fs;
        }
    }
    $chosen = $private !== '' ? $private : $uploads;
    if ($chosen === '') {
        $chosen = $usable[0];
    }
    casting_intro_clip_protect_dir($chosen);
    update_option('casting_intro_clip_dir', $chosen, false);

    return $chosen;
}

/**
 * @return list<string>
 */
function casting_intro_clip_file_tokens(string $filename): array
{
    $tokens = [];
    if (preg_match_all('/\d{8,}/', $filename, $nums)) {
        foreach ($nums[0] as $token) {
            $tokens[] = $token;
        }
    }
    if (preg_match_all('/[a-f0-9]{6,}/i', $filename, $hex)) {
        foreach ($hex[0] as $token) {
            if (!ctype_digit($token)) {
                $tokens[] = strtolower($token);
            }
        }
    }
    if (preg_match('/\d{4}-\d{2}-\d{2}/', $filename, $date)) {
        $tokens[] = $date[0];
    }

    return array_values(array_unique($tokens));
}

function casting_intro_clip_names_match(string $wanted, string $actual): bool
{
    return casting_intro_clip_name_score($wanted, $actual) > 0;
}

function casting_intro_clip_name_score(string $wanted, string $actual): int
{
    if ($wanted === $actual) {
        return 1000;
    }
    $hay = strtolower($actual);
    $score = 0;
    if (preg_match('/09\d{9}/', $wanted, $mobile) === 1 && str_contains($hay, $mobile[0])) {
        $score += 100;
    }
    foreach (casting_intro_clip_file_tokens($wanted) as $token) {
        if (str_contains($hay, strtolower($token))) {
            $score += 20;
        }
    }
    if (preg_match('/[a-f0-9]{6}$/i', pathinfo($wanted, PATHINFO_FILENAME), $suffix) === 1
        && str_contains($hay, strtolower($suffix[0]))) {
        $score += 40;
    }

    return $score;
}

function casting_intro_clip_locate_file(string $filename, string $preferred_dir = ''): string
{
    $filename = basename(str_replace('\\', '/', $filename));
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return '';
    }

    $dirs = [];
    $push = static function (string $dir) use (&$dirs): void {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '' || isset($dirs[$dir])) {
            return;
        }
        if (!casting_intro_clip_dir_is_allowed($dir)) {
            return;
        }
        $dirs[$dir] = $dir;
    };
    if ($preferred_dir !== '') {
        $push($preferred_dir);
    }
    foreach (casting_intro_clip_candidate_dirs() as $dir) {
        $push($dir);
    }

    foreach ($dirs as $dir) {
        $fs = casting_intro_clip_fs_path($dir);
        if (!is_dir($fs)) {
            continue;
        }
        $direct = $fs . DIRECTORY_SEPARATOR . $filename;
        if (is_file($direct)) {
            return $direct;
        }
        $listed = @scandir($fs);
        if (!is_array($listed)) {
            continue;
        }
        $fallback = '';
        $fallbackScore = 0;
        $fallbackMtime = 0;
        foreach ($listed as $entry) {
            if ($entry === '.' || $entry === '..' || str_contains($entry, '/') || str_contains($entry, '\\')) {
                continue;
            }
            if (!preg_match('/\.(mp4|webm|mov)$/i', $entry) || str_contains($entry, '.uploading.')) {
                continue;
            }
            $path = $fs . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }
            $same = $entry === $filename;
            if (!$same && function_exists('normalizer_normalize')) {
                $left = normalizer_normalize($entry, Normalizer::FORM_C);
                $right = normalizer_normalize($filename, Normalizer::FORM_C);
                $same = is_string($left) && is_string($right) && $left === $right;
            }
            if ($same) {
                return $path;
            }
            $score = casting_intro_clip_name_score($filename, $entry);
            $mtime = (int) @filemtime($path);
            if ($score > $fallbackScore || ($score === $fallbackScore && $score > 0 && $mtime >= $fallbackMtime)) {
                $fallback = $path;
                $fallbackScore = $score;
                $fallbackMtime = $mtime;
            }
        }
        if ($fallback !== '' && $fallbackScore >= 100) {
            return $fallback;
        }
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

function casting_intro_clip_absolute_path(int $user_id, string $clip_id): string
{
    $row = casting_intro_clip_row($user_id, $clip_id);
    if ($row === null) {
        return '';
    }
    $path = casting_intro_clip_locate_file((string) $row['file'], (string) ($row['dir'] ?? ''));
    if ($path === '') {
        return '';
    }
    $found_file = basename(str_replace('\\', '/', $path));
    $found_dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
    if ($found_file !== (string) $row['file'] || $found_dir !== (string) ($row['dir'] ?? '')) {
        casting_intro_clip_remember_location($user_id, $clip_id, $found_dir, $found_file);
    }

    return $path;
}

function casting_intro_clip_remember_location(int $user_id, string $clip_id, string $dir, string $file): void
{
    $rows = casting_intro_clip_rows($user_id);
    $changed = false;
    foreach ($rows as $index => $row) {
        if ($row['id'] !== $clip_id) {
            continue;
        }
        $rows[$index]['dir'] = $dir;
        $rows[$index]['file'] = $file;
        $changed = true;
        break;
    }
    if ($changed) {
        update_user_meta($user_id, CASTING_INTRO_CLIP_META, $rows);
    }
}

function casting_intro_clip_ascii_stem(string $login, string $mobile, string $shamsi, int $user_id): string
{
    $login = strtolower($login);
    $login = preg_replace('/[^a-z0-9]+/', '-', $login) ?? '';
    $login = trim($login, '-');
    if ($login === '') {
        $login = 'user-' . $user_id;
    }
    $mobile = preg_replace('/\D+/', '', $mobile) ?? '';
    if ($mobile === '') {
        $mobile = 'nomobile';
    }
    $shamsi = preg_replace('/[^0-9-]/', '', $shamsi) ?? '';
    if ($shamsi === '') {
        $shamsi = 'date';
    }

    return $login . '.' . $mobile . '.' . $shamsi;
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

function casting_intro_clip_duration_is_allowed(float $seconds): bool
{
    return $seconds >= CASTING_INTRO_CLIP_MIN_SECONDS - 0.5
        && $seconds <= CASTING_INTRO_CLIP_MAX_SECONDS + 0.5;
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

function casting_intro_clip_shamsi_file_date(string $mysql): string
{
    if (!function_exists('casting_jalali_parts_from_gregorian')) {
        $jalali = __DIR__ . '/jalali.php';
        if (is_file($jalali)) {
            require_once $jalali;
        }
    }
    $ymd = substr(trim($mysql), 0, 10);
    if (!function_exists('casting_jalali_parts_from_gregorian')) {
        return preg_replace('/\D+/', '', $ymd) ?: 'date';
    }
    $parts = casting_jalali_parts_from_gregorian($ymd);
    if ((int) ($parts['jy'] ?? 0) <= 0) {
        return preg_replace('/\D+/', '', $ymd) ?: 'date';
    }

    return sprintf('%04d-%02d-%02d', (int) $parts['jy'], (int) $parts['jm'], (int) $parts['jd']);
}

function casting_intro_clip_stream_url(int $owner_id, string $clip_id): string
{
    if ($owner_id <= 0 || casting_intro_clip_row($owner_id, $clip_id) === null) {
        return '';
    }

    return casting_url('intro-clip-stream.php?' . http_build_query([
        'uid' => $owner_id,
        'cid' => $clip_id,
        'n'   => wp_create_nonce('casting_intro_clip_' . $owner_id . '_' . $clip_id),
    ]));
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_intro_clip_save_upload(int $user_id, string $field): array
{
    if (!casting_user_can_upload_intro_clip($user_id)) {
        return ['ok' => false, 'error' => 'ارسال اثر فقط برای اعضایی است که اشتراک ویژه فعال دارند.'];
    }
    if ((string) ($_POST['intro_rights_accepted'] ?? '') !== '1') {
        return ['ok' => false, 'error' => 'برای ارسال اثر، شرایط استفاده از اثر را بپذیرید.'];
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
        return ['ok' => false, 'error' => 'مدت ویدیو از فایل خوانده نشد. یک MP4 بین ۳۰ تا ۹۰ ثانیه بفرستید.'];
    }
    if (!casting_intro_clip_duration_is_allowed($duration)) {
        return [
            'ok'    => false,
            'error' => 'این ویدیو ' . casting_intro_clip_duration_label($duration) . ' است. مدت اثر باید بین ۳۰ تا ۹۰ ثانیه باشد.',
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
    $uploaded_at = current_time('mysql');
    $shamsi = casting_intro_clip_shamsi_file_date($uploaded_at);
    $filename = casting_intro_clip_unique_filename($dir, $user_id, $label . '.' . $phone_part . '.' . $shamsi, $ext);

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
    if ($saved_duration <= 0 || !casting_intro_clip_duration_is_allowed($saved_duration)) {
        unlink($staging);

        return ['ok' => false, 'error' => 'مدت ویدیو بعد از ذخیره تأیید نشد. مدت اثر باید بین ۳۰ تا ۹۰ ثانیه باشد.'];
    }

    if (is_file($dest)) {
        unlink($staging);

        return ['ok' => false, 'error' => 'نام فایل روی هاست تکراری بود. یک بار دیگر ارسال کنید.'];
    }
    if (!rename($staging, $dest)) {
        unlink($staging);

        return ['ok' => false, 'error' => 'جابه‌جایی فایل روی هاست ناموفق بود.'];
    }
    if (!is_file($dest)) {
        $ascii = casting_intro_clip_ascii_stem((string) $user->user_login, $phone_part, $shamsi, $user_id);
        $ascii_name = $ascii . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $ascii_dest = $dir . DIRECTORY_SEPARATOR . $ascii_name;
        if ((@rename($dest, $ascii_dest) || @copy($dest, $ascii_dest)) && is_file($ascii_dest)) {
            if (is_file($dest) && $dest !== $ascii_dest) {
                @unlink($dest);
            }
            $dest = $ascii_dest;
            $filename = $ascii_name;
        }
    }
    if (!is_file($dest)) {
        return ['ok' => false, 'error' => 'فایل بعد از ذخیره در پوشه هاست دیده نشد.'];
    }
    @chmod($dest, 0644);

    $mime = $ftype !== '' ? $ftype : 'video/' . ($ext === 'mov' ? 'quicktime' : $ext);
    $row = [
        'id'                 => bin2hex(random_bytes(6)),
        'file'               => $filename,
        'bytes'              => (int) filesize($dest),
        'duration'           => $saved_duration,
        'mime'               => $mime,
        'uploaded_at'        => $uploaded_at,
        'display_name'       => $display !== '' ? $display : (string) $user->user_login,
        'mobile'             => $mobile,
        'rights_accepted_at' => $uploaded_at,
        'owner_hidden'       => '',
        'dir'                => rtrim(str_replace('\\', '/', $dir), '/'),
    ];
    $rows = casting_intro_clip_rows($user_id);
    array_unshift($rows, $row);
    update_user_meta($user_id, CASTING_INTRO_CLIP_META, $rows);
    casting_intro_clip_notify_reviewers($user_id, (string) $row['display_name']);

    return ['ok' => true, 'error' => ''];
}

function casting_intro_clip_unique_filename(string $dir, int $user_id, string $stem, string $ext): string
{
    $stem = casting_intro_clip_safe_label($stem, 110);
    if ($stem === '') {
        $stem = 'user-' . $user_id;
    }
    $ext = strtolower($ext);
    $candidate = $stem . '.' . $ext;
    $path = $dir . DIRECTORY_SEPARATOR . $candidate;
    if ($dir !== '' && !is_file($path)) {
        return $candidate;
    }

    return $stem . '_' . $user_id . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
}

function casting_intro_clip_owner_of_basename(string $basename): int
{
    $users = get_users([
        'meta_key' => CASTING_INTRO_CLIP_META,
        'number'   => 500,
        'fields'   => 'ID',
    ]);
    foreach ($users as $id) {
        foreach (casting_intro_clip_rows((int) $id) as $row) {
            if ($row['file'] === $basename) {
                return (int) $id;
            }
        }
    }

    return 0;
}

/**
 * حذف از پروفایل خود کاربر؛ فایل روی هاست و فهرست مدیران می‌ماند.
 *
 * @return array{ok:bool,error:string}
 */
function casting_intro_clip_hide_from_owner(int $user_id, string $clip_id): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'حساب نامعتبر است.'];
    }
    $clip_id = strtolower($clip_id);
    if (!preg_match('/^[a-f0-9]{8,24}$/', $clip_id)) {
        return ['ok' => false, 'error' => 'اثر انتخاب‌شده نامعتبر است.'];
    }
    $rows = casting_intro_clip_rows($user_id);
    $found = false;
    foreach ($rows as $index => $item) {
        if ($item['id'] !== $clip_id) {
            continue;
        }
        $rows[$index]['owner_hidden'] = '1';
        $found = true;
        break;
    }
    if (!$found) {
        return ['ok' => false, 'error' => 'این اثر برای حذف پیدا نشد.'];
    }
    update_user_meta($user_id, CASTING_INTRO_CLIP_META, $rows);

    return ['ok' => true, 'error' => ''];
}

/**
 * @return list<array{id:string,file:string,bytes:int,duration:float,mime:string,uploaded_at:string,display_name:string,mobile:string,rights_accepted_at:string,owner_hidden:string}>
 */
function casting_intro_clip_visible_rows(int $user_id): array
{
    $out = [];
    foreach (casting_intro_clip_rows($user_id) as $row) {
        if (($row['owner_hidden'] ?? '') === '1') {
            continue;
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_intro_clip_delete(int $user_id, string $clip_id): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'حساب نامعتبر است.'];
    }
    $clip_id = strtolower($clip_id);
    if (!preg_match('/^[a-f0-9]{8,24}$/', $clip_id)) {
        return ['ok' => false, 'error' => 'اثر انتخاب‌شده نامعتبر است.'];
    }
    $row = casting_intro_clip_row($user_id, $clip_id);
    if ($row === null) {
        return ['ok' => false, 'error' => 'این اثر برای حذف پیدا نشد.'];
    }
    $path = casting_intro_clip_absolute_path($user_id, $clip_id);
    $kept = [];
    foreach (casting_intro_clip_rows($user_id) as $item) {
        if ($item['id'] !== $clip_id) {
            $kept[] = $item;
        }
    }
    if ($kept === []) {
        delete_user_meta($user_id, CASTING_INTRO_CLIP_META);
    } else {
        update_user_meta($user_id, CASTING_INTRO_CLIP_META, $kept);
    }
    if ($path !== '' && is_file($path)) {
        unlink($path);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return list<array{user_id:int,clip_id:string,name:string,login:string,mobile:string,file:string,duration:float,uploaded_at:string,bytes:int,missing:bool}>
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
        foreach (casting_intro_clip_rows($id) as $row) {
            $mobile = $row['mobile'] !== '' ? $row['mobile'] : casting_intro_clip_mobile($id);
            $out[] = [
                'user_id'     => $id,
                'clip_id'     => $row['id'],
                'name'        => trim((string) $user->display_name) !== '' ? (string) $user->display_name : $row['display_name'],
                'login'       => (string) $user->user_login,
                'mobile'      => $mobile,
                'file'        => $row['file'],
                'duration'    => $row['duration'],
                'uploaded_at' => $row['uploaded_at'],
                'bytes'        => $row['bytes'],
                'missing'      => casting_intro_clip_absolute_path($id, $row['id']) === '',
                'owner_hidden' => ($row['owner_hidden'] ?? '') === '1',
            ];
        }
    }
    usort($out, static function (array $a, array $b): int {
        return strcmp((string) $b['uploaded_at'], (string) $a['uploaded_at']);
    });

    return $out;
}

function casting_intro_clip_submitter_count(): int
{
    $people = [];
    foreach (casting_intro_clip_list() as $row) {
        $people[(int) $row['user_id']] = true;
    }

    return count($people);
}

function casting_intro_clip_notify_reviewers(int $uploader_id, string $display_name): void
{
    if (!function_exists('casting_sms_send_text')) {
        $sms = __DIR__ . '/sms.php';
        if (is_file($sms)) {
            require_once $sms;
        }
    }
    if (!function_exists('casting_sms_send_text') || !function_exists('get_user_by')) {
        return;
    }
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }

    $name = trim($display_name);
    if ($name === '') {
        $name = 'یک عضو';
    }
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 40, 'UTF-8');
    }
    $count = casting_intro_clip_submitter_count();
    $message = '۷رخ: اثر جدید از ' . $name . ' ثبت شد. ' . $count . ' نفر ویدیو گذاشته‌اند.';

    foreach (['eshahabian', 'ardavan'] as $login) {
        $reviewer = get_user_by('login', $login);
        if (!$reviewer instanceof WP_User) {
            continue;
        }
        $reviewer_id = (int) $reviewer->ID;
        if ($reviewer_id <= 0 || $reviewer_id === $uploader_id) {
            continue;
        }
        $mobiles = [];
        foreach (['casting_mobile', 'casting_mobile2'] as $meta_key) {
            $mobile = casting_normalize_mobile((string) get_user_meta($reviewer_id, $meta_key, true));
            if (preg_match('/^09\d{9}$/', $mobile) === 1) {
                $mobiles[$mobile] = true;
            }
        }
        foreach (array_keys($mobiles) as $mobile) {
            try {
                casting_sms_send_text($mobile, $message);
            } catch (Throwable $e) {
                // ارسال اثر نباید به‌خاطر پیامک متوقف شود
            }
        }
    }
}

function casting_intro_clip_output(int $owner_id, string $clip_id): void
{
    $path = casting_intro_clip_absolute_path($owner_id, $clip_id);
    $row = casting_intro_clip_row($owner_id, $clip_id);
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
