<?php
declare(strict_types=1);

/**
 * حفاظت نمایش مدیا: جلوگیری از دانلود آسان + واترمارک بیننده
 * (اسکرین‌شات کامل روی وب غیرممکن است؛ فقط سخت‌تر می‌شود.)
 */

function casting_media_protect_viewer_label(?WP_User $viewer = null): string
{
    if (!$viewer instanceof WP_User) {
        $viewer = casting_current_user();
    }
    if (!$viewer instanceof WP_User) {
        return 'مهمان · ۷ رخ';
    }
    $uid = (int) $viewer->ID;
    $name = trim((string) $viewer->display_name);
    $login = trim((string) $viewer->user_login);
    $parts = [];
    if ($name !== '') {
        $parts[] = $name;
    }
    if ($login !== '') {
        $parts[] = '@' . $login;
    }
    if (!function_exists('casting_get_membership_number')) {
        $mn_file = __DIR__ . '/membership-number.php';
        if (is_file($mn_file)) {
            require_once $mn_file;
        }
    }
    if (function_exists('casting_get_membership_number')) {
        $num = casting_get_membership_number($uid);
        if ($num !== '') {
            $parts[] = $num;
        }
    }
    $parts[] = 'id:' . $uid;

    return implode(' · ', $parts);
}

/**
 * لینک استریم داخل پورتال (نه URL مستقیم wp-content) تا دانلود آسان سخت‌تر شود.
 */
function casting_media_stream_url(int $attachment_id): string
{
    if ($attachment_id <= 0) {
        return '';
    }

    return casting_url('media-stream.php?' . http_build_query([
        'aid' => $attachment_id,
        'n'   => wp_create_nonce('casting_stream_' . $attachment_id),
    ]));
}

/**
 * @param array{class?:string,muted?:bool,poster?:string,preload?:string,attachment_id?:int} $opts
 */
function casting_render_protected_video(string $src, string $watermark, array $opts = []): void
{
    $aid = (int) ($opts['attachment_id'] ?? 0);
    if ($aid > 0) {
        $stream = casting_media_stream_url($aid);
        if ($stream !== '') {
            $src = $stream;
        }
    }
    if ($src === '') {
        return;
    }
    $class = trim('media-protect media-protect--video ' . (string) ($opts['class'] ?? ''));
    $muted = !empty($opts['muted']);
    $poster = (string) ($opts['poster'] ?? '');
    $preload = (string) ($opts['preload'] ?? 'metadata');
    $wm = trim($watermark);
    ?>
  <div
    class="<?= casting_e($class) ?>"
    data-media-protect
    data-video-protect
    data-watermark="<?= casting_e($wm) ?>"
  >
    <video
      class="media-protect-source"
      src="<?= casting_e($src) ?>"
      playsinline
      webkit-playsinline
      preload="<?= casting_e($preload) ?>"
      <?= $muted ? 'muted ' : '' ?>
      <?= $poster !== '' ? 'poster="' . casting_e($poster) . '"' : '' ?>
      disablepictureinpicture
      controlslist="nodownload noplaybackrate noremoteplayback"
      oncontextmenu="return false;"
    ></video>
    <canvas class="media-protect-canvas" aria-hidden="true"></canvas>
    <?php casting_render_media_watermark($wm); ?>
    <button type="button" class="media-protect-play" data-video-play aria-label="پخش ویدیو">▶</button>
    <div class="media-protect-controls" hidden>
      <button type="button" class="media-protect-toggle" data-video-toggle aria-label="توقف/پخش">❚❚</button>
      <input class="media-protect-seek" type="range" min="0" max="1000" value="0" step="1" data-video-seek aria-label="زمان">
    </div>
  </div>
    <?php
}

/**
 * @param array{class?:string,alt?:string,loading?:string,href?:bool} $opts
 */
function casting_render_protected_image(string $src, string $watermark, array $opts = []): void
{
    if ($src === '') {
        return;
    }
    $class = trim('media-protect ' . (string) ($opts['class'] ?? ''));
    $alt = (string) ($opts['alt'] ?? '');
    $loading = (string) ($opts['loading'] ?? 'lazy');
    ?>
  <div class="<?= casting_e($class) ?>" data-media-protect>
    <img
      src="<?= casting_e($src) ?>"
      alt="<?= casting_e($alt) ?>"
      loading="<?= casting_e($loading) ?>"
      draggable="false"
      oncontextmenu="return false;"
    >
    <?php casting_render_media_watermark($watermark); ?>
  </div>
    <?php
}

function casting_render_media_watermark(string $label): void
{
    $label = trim($label);
    if ($label === '') {
        return;
    }
    ?>
  <div class="media-watermark" aria-hidden="true">
    <?php for ($i = 0; $i < 6; $i++) : ?>
      <span><?= casting_e($label) ?></span>
    <?php endfor; ?>
  </div>
    <?php
}

/* ---------- ذخیره مدیا برای کارگردان (مثل Save اینستاگرام) ---------- */

function casting_media_saves_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_media_saves';
}

function casting_media_saves_install(): void
{
    global $wpdb;
    $table = casting_media_saves_table();
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        director_id BIGINT UNSIGNED NOT NULL,
        media_id BIGINT UNSIGNED NOT NULL,
        owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY director_media (director_id, media_id),
        KEY director_id (director_id),
        KEY media_id (media_id)
    ) {$charset};");
    update_option('casting_media_saves_db_version', '1');
}

function casting_media_saves_ensure_table(): void
{
    if ((string) get_option('casting_media_saves_db_version', '') !== '1') {
        casting_media_saves_install();
    }
}

function casting_user_can_save_media(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (!function_exists('casting_user_is_director_role')) {
        require_once __DIR__ . '/director-workspace.php';
    }

    return casting_user_is_director_role($user_id);
}

function casting_media_is_saved(int $director_id, int $media_id): bool
{
    if ($director_id <= 0 || $media_id <= 0) {
        return false;
    }
    casting_media_saves_ensure_table();
    global $wpdb;
    $table = casting_media_saves_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE director_id = %d AND media_id = %d LIMIT 1",
        $director_id,
        $media_id
    )) > 0;
}

/**
 * @return array{ok:bool,error:string,saved?:bool}
 */
function casting_media_toggle_save(int $director_id, int $media_id): array
{
    if (!casting_user_can_save_media($director_id)) {
        return ['ok' => false, 'error' => 'فقط کارگردان می‌تواند مدیا را ذخیره کند.'];
    }
    if ($media_id <= 0) {
        return ['ok' => false, 'error' => 'مدیا نامعتبر است.'];
    }
    if (!function_exists('casting_media_get_row')) {
        require_once __DIR__ . '/media-engagement.php';
    }
    $row = casting_media_get_row($media_id);
    if (!is_array($row) || (string) ($row['status'] ?? '') !== 'approved') {
        return ['ok' => false, 'error' => 'این پست قابل ذخیره نیست.'];
    }
    $owner_id = (int) ($row['user_id'] ?? 0);
    if ($owner_id === $director_id) {
        return ['ok' => false, 'error' => 'نمی‌توانید پست خودتان را ذخیره کنید.'];
    }

    casting_media_saves_ensure_table();
    global $wpdb;
    $table = casting_media_saves_table();
    if (casting_media_is_saved($director_id, $media_id)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete($table, ['director_id' => $director_id, 'media_id' => $media_id], ['%d', '%d']);

        return ['ok' => true, 'error' => '', 'saved' => false];
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ok = $wpdb->insert(
        $table,
        [
            'director_id' => $director_id,
            'media_id'    => $media_id,
            'owner_id'    => $owner_id,
            'created_at'  => current_time('mysql'),
        ],
        ['%d', '%d', '%d', '%s']
    );
    if ($ok === false) {
        return ['ok' => false, 'error' => 'ذخیره ناموفق بود.'];
    }

    return ['ok' => true, 'error' => '', 'saved' => true];
}

/**
 * @return list<array<string, mixed>>
 */
function casting_media_list_saved(int $director_id, int $limit = 60): array
{
    if ($director_id <= 0) {
        return [];
    }
    casting_media_saves_ensure_table();
    if (!function_exists('casting_user_media_ensure_table')) {
        require_once __DIR__ . '/user-media.php';
    }
    casting_user_media_ensure_table();
    global $wpdb;
    $saves = casting_media_saves_table();
    $media = casting_user_media_table();
    $limit = max(1, min(120, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, s.created_at AS saved_at
         FROM {$saves} s
         INNER JOIN {$media} m ON m.id = s.media_id
         WHERE s.director_id = %d AND m.status = 'approved'
         ORDER BY s.id DESC
         LIMIT %d",
        $director_id,
        $limit
    ), ARRAY_A);

    return is_array($rows) ? $rows : [];
}

function casting_media_saved_count(int $director_id): int
{
    if ($director_id <= 0) {
        return 0;
    }
    casting_media_saves_ensure_table();
    global $wpdb;
    $table = casting_media_saves_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE director_id = %d",
        $director_id
    ));
}
