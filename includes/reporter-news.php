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
      <?php if ($row['size'] > 0): ?>
      <p><a class="btn btn-ghost" href="<?= casting_e(casting_url('reporters.php?download=' . $row['id'] . '&owner=' . $row['uid'])) ?>">دریافت <?= casting_e($row['name']) ?></a></p>
      <?php endif; ?>
      <form method="post" action="<?= casting_e(casting_url('reporters.php')) ?>" class="cta-row">
        <?php wp_nonce_field('casting_news_manage'); ?>
        <input type="hidden" name="news_manage" value="1">
        <input type="hidden" name="owner" value="<?= (int) $row['uid'] ?>">
        <input type="hidden" name="id" value="<?= casting_e($row['id']) ?>">
        <?php if ($state !== 'inbox'): ?><button class="btn btn-ghost" name="state" value="inbox">بازگرداندن به دریافتی‌ها</button><?php endif; ?>
        <?php if ($state !== 'archived'): ?><button class="btn btn-primary" name="state" value="archived">انتقال به آرشیو</button><?php endif; ?>
        <?php if ($state !== 'deleted'): ?><button class="btn btn-ghost" name="state" value="deleted">حذف</button><?php endif; ?>
      </form>
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
    try {
        $meta = is_file($base . '.json') ? json_decode((string) file_get_contents($base . '.json'), true, 512, JSON_THROW_ON_ERROR) : null;
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'start' && !$meta) {
            $size = filter_var($_POST['size'] ?? 0, FILTER_VALIDATE_INT);
            if ($size === false || $size < 0) {
                throw new RuntimeException('حجم فایل نامعتبر است.');
            }
            $meta = ['id' => $id, 'uid' => $uid, 'name' => sanitize_text_field(basename(str_replace('\\', '/', (string) ($_POST['name'] ?? '')))), 'size' => $size, 'done' => false];
        }
        if (!$meta) {
            throw new RuntimeException('ارسال پیدا نشد.');
        }
        clearstatcache(true, $base . '.bin');
        $offset = is_file($base . '.bin') ? (int) filesize($base . '.bin') : 0;
        if ($action === 'chunk' && !$meta['done']) {
            $file = $_FILES['chunk'] ?? [];
            if ((int) ($_POST['offset'] ?? -1) !== $offset) {
                casting_news_json(['offset' => $offset], 409);
            }
            $length = (int) ($file['size'] ?? 0);
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $length < 1 || $length > 1024 * 1024 || $offset + $length > $meta['size'] || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new RuntimeException('بخش فایل دریافت نشد؛ دوباره تلاش کنید.');
            }
            $out = fopen($base . '.bin', 'c+b');
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
                $rollback = fopen($base . '.bin', 'c+b');
                if ($rollback) { ftruncate($rollback, $offset); fclose($rollback); }
                throw new RuntimeException('ذخیره فایل کامل نشد.');
            }
            $offset += $length;
        }
        if ($action === 'finish' && !$meta['done']) {
            if ($offset !== $meta['size']) {
                throw new RuntimeException('آپلود هنوز کامل نشده است.');
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
        }
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($base . '.json.tmp', $json) !== strlen($json) || !rename($base . '.json.tmp', $base . '.json')) {
            throw new RuntimeException('ذخیره اطلاعات خبر ممکن نشد.');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    casting_news_json(['offset' => $offset, 'done' => $meta['done']]);
}
