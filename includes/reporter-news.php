<?php
declare(strict_types=1);

function casting_news_reviewer(int $id): bool
{
    $user = get_user_by('id', $id);
    return $user && in_array(strtolower((string) $user->user_login), ['eshahabian', 'ardavan'], true);
}

function casting_news_state(array $row): string
{
    $state = (string) ($row['state'] ?? 'inbox');
    return in_array($state, ['inbox', 'archived', 'deleted'], true) ? $state : 'inbox';
}

/** Move a completed submission without deleting either its content or media. */
function casting_news_move(int $reviewer, string $owner, string $id, string $state): void
{
    if (!casting_news_reviewer($reviewer)) {
        throw new RuntimeException('اجازه مدیریت خبرها را ندارید.');
    }
    if (!preg_match('/^[1-9][0-9]*$/D', $owner) || !preg_match('/^[a-f0-9]{32}$/D', $id)
        || !in_array($state, ['inbox', 'archived', 'deleted'], true)) {
        throw new RuntimeException('درخواست مدیریت خبر نامعتبر است.');
    }
    $base = casting_news_dir() . '/' . $owner . '/' . $id;
    if (!is_file($base . '.json')) { throw new RuntimeException('خبر پیدا نشد.'); }
    $lock = fopen($base . '.lock', 'c+b');
    if (!$lock || !flock($lock, LOCK_EX)) { throw new RuntimeException('ذخیره تغییرات ممکن نشد.'); }
    try {
        $row = json_decode((string) file_get_contents($base . '.json'), true, 512, JSON_THROW_ON_ERROR);
        if (empty($row['done'])) { throw new RuntimeException('خبر هنوز ارسال نشده است.'); }
        if (casting_news_state($row) === $state) { return; }
        $row['state'] = $state;
        $row['state_changed_at'] = gmdate('c');
        $row['state_changed_by'] = $reviewer;
        if ($state === 'archived') { $row['archived_at'] = $row['state_changed_at']; }
        if ($state === 'deleted') { $row['deleted_at'] = $row['state_changed_at']; }
        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($base . '.json.tmp', $json) !== strlen($json) || !rename($base . '.json.tmp', $base . '.json')) {
            throw new RuntimeException('ذخیره تغییرات ممکن نشد.');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Use the portal's Jalali calendar and Tehran time for archive day groups. */
function casting_news_date(string $value, bool $with_time = false): string
{
    require_once __DIR__ . '/jalali.php';
    try { $date = (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Tehran')); }
    catch (Throwable $e) { return 'تاریخ نامشخص'; }
    [$year, $month, $day] = casting_gregorian_to_jalali((int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
    return sprintf('%04d/%02d/%02d', $year, $month, $day) . ($with_time ? ' — ' . $date->format('H:i') : '');
}

function casting_news_render_card(array $row): void
{
    $state = casting_news_state($row);
    ?>
    <article class="dash-card">
      <h3><?= casting_e($row['title']) ?></h3>
      <p><?= casting_e($row['reporter']) ?> — ارسال: <?= casting_e(casting_news_date($row['created'], true)) ?></p>
      <p><strong><?= nl2br(casting_e($row['lead'])) ?></strong></p>
      <div style="white-space:pre-wrap;overflow-wrap:anywhere"><?= casting_e($row['body']) ?></div>
      <?php if (function_exists('casting_news_render_publish')) { casting_news_render_publish($row); } ?>
      <div class="news-card-actions">
      <?php
        $photo = is_array($row['photo'] ?? null) ? $row['photo'] : null;
        $video = is_array($row['video'] ?? null) ? $row['video'] : null;
        $download = static function (array $row, string $file, string $label, string $name): void {
            $href = casting_url('reporters.php?download=' . $row['id'] . '&owner=' . $row['uid'] . '&file=' . $file);
            echo '<a class="btn btn-ghost" href="' . casting_e($href) . '">' . casting_e($label) . ' ' . casting_e($name) . '</a>';
        };
      ?>
      <?php if ($photo && (int) ($photo['size'] ?? 0) > 0): ?>
        <?php $download($row, 'photo', 'دریافت عکس', (string) ($photo['name'] ?? '')); ?>
      <?php elseif ((int) ($row['size'] ?? 0) > 0): ?>
        <?php $download($row, 'legacy', 'دریافت', (string) ($row['name'] ?? '')); ?>
      <?php endif; ?>
      <?php if ($video && (int) ($video['size'] ?? 0) > 0): ?>
        <?php $download($row, 'video', 'دریافت ویدیو', (string) ($video['name'] ?? '')); ?>
      <?php endif; ?>
      <form method="post" action="<?= casting_e(casting_url('reporters.php')) ?>" class="news-manage-actions">
        <?php wp_nonce_field('casting_news_manage'); ?>
        <input type="hidden" name="news_manage" value="1">
        <input type="hidden" name="owner" value="<?= (int) $row['uid'] ?>">
        <input type="hidden" name="id" value="<?= casting_e($row['id']) ?>">
        <?php if ($state !== 'inbox'): ?><button class="btn btn-ghost" name="state" value="inbox">بازگرداندن به دریافتی‌ها</button><?php endif; ?>
        <?php if ($state !== 'archived'): ?><button class="btn btn-primary" name="state" value="archived">انتقال به آرشیو</button><?php endif; ?>
        <?php if ($state !== 'deleted'): ?><button class="btn btn-ghost" name="state" value="deleted">حذف</button><?php endif; ?>
      </form>
      </div>
    </article>
    <?php
}

/** Storage must be outside the document root: arbitrary originals are never public. */
function casting_news_dir(): string
{
    $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $dir = defined('CASTING_NEWS_PRIVATE_DIR') ? (string) CASTING_NEWS_PRIVATE_DIR : dirname(rtrim(ABSPATH, '/\\')) . '/casting-private-news';
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
        throw new RuntimeException('ساخت پوشه خصوصی خبرها ممکن نشد.');
    }
    $real = realpath($dir);
    $norm = static fn(string $p): string => strtolower(str_replace('\\', '/', rtrim($p, '/\\'))) . '/';
    if (!$real || !$root || str_starts_with($norm($real), $norm($root))) {
        throw new RuntimeException('پوشه خبرها باید خارج از مسیر عمومی سایت تنظیم شود.');
    }
    return $real;
}

function casting_news_slot(string $slot): string
{
    if (!in_array($slot, ['photo', 'video'], true)) {
        throw new RuntimeException('نوع فایل نامعتبر است.');
    }

    return $slot;
}

function casting_news_media_path(string $base, string $slot): string
{
    return $base . '.' . casting_news_slot($slot) . '.bin';
}

function casting_news_media_offset(string $path): int
{
    clearstatcache(true, $path);

    return is_file($path) ? (int) filesize($path) : 0;
}

function casting_news_truncate(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $handle = fopen($path, 'c+b');
    if ($handle) {
        ftruncate($handle, 0);
        fclose($handle);
    }
}

function casting_news_looks_like_image(string $path): bool
{
    if (function_exists('getimagesize')) {
        $info = @getimagesize($path);
        if (is_array($info) && (int) ($info[0] ?? 0) > 0 && (int) ($info[1] ?? 0) > 0) {
            $mime = (string) ($info['mime'] ?? '');

            return $mime === '' || (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml');
        }
    }
    $head = (string) file_get_contents($path, false, null, 0, 16);

    return str_starts_with($head, "\xFF\xD8\xFF")
        || str_starts_with($head, "\x89PNG\x0D\x0A\x1A\x0A")
        || str_starts_with($head, 'GIF87a')
        || str_starts_with($head, 'GIF89a')
        || (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP')
        || str_starts_with($head, 'BM')
        || (substr($head, 4, 4) === 'ftyp' && in_array(substr($head, 8, 4), ['heic', 'heif', 'mif1', 'avif'], true));
}

function casting_news_media_kind(string $path, string $name = ''): string
{
    if (!is_file($path)) {
        return '';
    }
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($detected)) {
                $mime = $detected;
            }
        }
    } elseif (function_exists('mime_content_type')) {
        $detected = mime_content_type($path);
        if (is_string($detected)) {
            $mime = $detected;
        }
    }
    if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
        return 'image';
    }
    if (str_starts_with($mime, 'video/')) {
        return 'video';
    }
    if (casting_news_looks_like_image($path)) {
        return 'image';
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $videos = ['mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v', 'mpeg', 'mpg', 'wmv', '3gp'];
    if (in_array($ext, $videos, true) && ($mime === '' || $mime === 'application/octet-stream')) {
        return 'video';
    }

    return '';
}

/**
 * @param array<string, mixed> $meta
 * @return array{path:string,name:string}|null
 */
function casting_news_download_file(string $base, array $meta, string $file): ?array
{
    if (empty($meta['done'])) {
        return null;
    }
    if ($file === 'photo' && is_file($base . '.photo.bin')) {
        $name = (string) ($meta['photo']['name'] ?? 'photo');

        return ['path' => $base . '.photo.bin', 'name' => $name !== '' ? $name : 'photo'];
    }
    if ($file === 'video' && is_file($base . '.video.bin')) {
        $name = (string) ($meta['video']['name'] ?? 'video');

        return ['path' => $base . '.video.bin', 'name' => $name !== '' ? $name : 'video'];
    }
    if (($file === '' || $file === 'legacy' || $file === 'photo') && is_file($base . '.bin')) {
        $name = (string) ($meta['name'] ?? 'news-media');

        return ['path' => $base . '.bin', 'name' => $name !== '' ? $name : 'news-media'];
    }

    return null;
}

function casting_news_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/** A locked manifest serializes chunk writes, status requests and finalization. */
function casting_news_api(int $uid): void
{
    if (!wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'casting_news')) {
        casting_news_json(['error' => 'نشست منقضی شده؛ صفحه را تازه کنید.'], 403);
    }
    $id = (string) ($_POST['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
        casting_news_json(['error' => 'شناسه ارسال نامعتبر است.'], 400);
    }
    $dir = casting_news_dir() . '/' . $uid;
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
        throw new RuntimeException('ذخیره‌سازی در دسترس نیست.');
    }
    $base = $dir . '/' . $id;
    $lock = fopen($base . '.lock', 'c+b');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('قفل ذخیره‌سازی در دسترس نیست.');
    }
    $new_submission = false;
    try {
        $meta = is_file($base . '.json') ? json_decode((string) file_get_contents($base . '.json'), true, 512, JSON_THROW_ON_ERROR) : null;
        $action = (string) ($_POST['action'] ?? '');
        $slot = '';
        if (in_array($action, ['start', 'chunk', 'status'], true)) {
            $slot = casting_news_slot((string) ($_POST['slot'] ?? ''));
        }
        if ($action === 'start' && !$meta) {
            $meta = ['id' => $id, 'uid' => $uid, 'done' => false];
        }
        if (!$meta) {
            throw new RuntimeException('ارسال پیدا نشد.');
        }
        if ($action === 'start' && empty($meta['done'])) {
            $size = filter_var($_POST['size'] ?? 0, FILTER_VALIDATE_INT);
            if ($size === false || $size < 1) {
                throw new RuntimeException('حجم فایل نامعتبر است.');
            }
            $name = sanitize_text_field(basename(str_replace('\\', '/', (string) ($_POST['name'] ?? ''))));
            if ($name === '') {
                throw new RuntimeException('نام فایل نامعتبر است.');
            }
            $current = is_array($meta[$slot] ?? null) ? $meta[$slot] : null;
            $same = $current && (int) ($current['size'] ?? -1) === $size && (string) ($current['name'] ?? '') === $name;
            if (!$same) {
                $meta[$slot] = ['name' => $name, 'size' => $size];
                casting_news_truncate(casting_news_media_path($base, $slot));
            }
        }
        $offset = $slot !== '' ? casting_news_media_offset(casting_news_media_path($base, $slot)) : 0;
        if ($action === 'chunk' && empty($meta['done'])) {
            if (!is_array($meta[$slot] ?? null)) {
                throw new RuntimeException('ابتدا بارگذاری این فایل را شروع کنید.');
            }
            $path = casting_news_media_path($base, $slot);
            $file = $_FILES['chunk'] ?? [];
            if ((int) ($_POST['offset'] ?? -1) !== $offset) {
                casting_news_json(['offset' => $offset], 409);
            }
            $length = (int) ($file['size'] ?? 0);
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $length < 1 || $length > 1024 * 1024 || $offset + $length > (int) $meta[$slot]['size'] || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new RuntimeException('بخش فایل دریافت نشد؛ دوباره تلاش کنید.');
            }
            $out = fopen($path, 'c+b');
            $in = fopen((string) $file['tmp_name'], 'rb');
            if (!$out || !$in) {
                throw new RuntimeException('باز کردن فایل ممکن نشد.');
            }
            fseek($out, $offset);
            $written = stream_copy_to_stream($in, $out);
            fflush($out);
            fclose($in);
            fclose($out);
            if ($written !== $length) {
                $rollback = fopen($path, 'c+b');
                if ($rollback) { ftruncate($rollback, $offset); fclose($rollback); }
                throw new RuntimeException('ذخیره فایل کامل نشد.');
            }
            $offset += $length;
        }
        if ($action === 'finish' && empty($meta['done'])) {
            $photo = is_array($meta['photo'] ?? null) ? $meta['photo'] : null;
            $photo_path = casting_news_media_path($base, 'photo');
            if (!$photo || (int) ($photo['size'] ?? 0) < 1 || casting_news_media_offset($photo_path) !== (int) $photo['size']) {
                throw new RuntimeException('ارسال عکس الزامی است و باید کامل بارگذاری شود.');
            }
            if (casting_news_media_kind($photo_path, (string) ($photo['name'] ?? '')) !== 'image') {
                casting_news_truncate($photo_path);
                throw new RuntimeException('فایل عکس معتبر نیست. یک تصویر JPEG، PNG، WebP یا GIF انتخاب کنید.');
            }
            $video_path = casting_news_media_path($base, 'video');
            if ((string) ($_POST['has_video'] ?? '') === '1') {
                $video = is_array($meta['video'] ?? null) ? $meta['video'] : null;
                if (!$video || (int) ($video['size'] ?? 0) < 1 || casting_news_media_offset($video_path) !== (int) $video['size']) {
                    throw new RuntimeException('آپلود ویدیو هنوز کامل نشده است.');
                }
                if (casting_news_media_kind($video_path, (string) ($video['name'] ?? '')) !== 'video') {
                    casting_news_truncate($video_path);
                    throw new RuntimeException('فایل ویدیو معتبر نیست. یک ویدیو انتخاب کنید یا این بخش را خالی بگذارید.');
                }
            } else {
                unset($meta['video']);
                if (is_file($video_path)) {
                    unlink($video_path);
                }
            }
            foreach (['title', 'lead', 'body'] as $field) {
                $meta[$field] = sanitize_textarea_field(wp_unslash((string) ($_POST[$field] ?? '')));
                if (trim($meta[$field]) === '') {
                    throw new RuntimeException('تیتر، لید و متن خبر را تکمیل کنید.');
                }
            }
            $user = get_user_by('id', $uid);
            $meta['reporter'] = (string) $user->display_name;
            $meta['created'] = gmdate('c');
            $meta['done'] = true;
            $new_submission = true;
            $offset = casting_news_media_offset($photo_path);
        }
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($base . '.json.tmp', $json) !== strlen($json) || !rename($base . '.json.tmp', $base . '.json')) {
            throw new RuntimeException('ذخیره اطلاعات خبر ممکن نشد.');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    if ($new_submission) {
        require_once __DIR__ . '/admin-message-sms.php';
        casting_admin_message_notify_sms($uid, 0, 'news');
    }
    casting_news_json(['offset' => $offset, 'done' => $meta['done']]);
}
