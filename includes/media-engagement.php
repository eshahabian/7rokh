<?php
declare(strict_types=1);

require_once __DIR__ . '/user-media.php';

function casting_media_likes_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_media_likes';
}

function casting_media_comments_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_media_comments';
}

function casting_media_views_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_media_views';
}

function casting_media_engagement_install(): void
{
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $likes = casting_media_likes_table();
    $comments = casting_media_comments_table();
    $views = casting_media_views_table();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql_likes = "CREATE TABLE {$likes} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        media_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY media_user (media_id, user_id),
        KEY media_id (media_id),
        KEY user_id (user_id)
    ) {$charset};";

    $sql_comments = "CREATE TABLE {$comments} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        media_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        body TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY media_id (media_id),
        KEY user_id (user_id),
        KEY media_created (media_id, created_at)
    ) {$charset};";

    $sql_views = "CREATE TABLE {$views} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        media_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY media_user (media_id, user_id),
        KEY media_id (media_id),
        KEY user_id (user_id)
    ) {$charset};";

    dbDelta($sql_likes);
    dbDelta($sql_comments);
    dbDelta($sql_views);
    update_option('casting_media_engagement_db_version', '2');
}

function casting_media_engagement_ensure(): void
{
    if ((string) get_option('casting_media_engagement_db_version', '') !== '2') {
        casting_media_engagement_install();
    }
}

function casting_media_get_row(int $media_id): ?array
{
    if ($media_id <= 0) {
        return null;
    }
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $media_id), ARRAY_A);

    return is_array($row) ? $row : null;
}

/**
 * آیا بیننده می‌تواند با این پست تعامل کند؟ (لایک/کامنت/ذخیره)
 */
function casting_media_user_can_engage(int $viewer_id, array $row): bool
{
    if ($viewer_id <= 0) {
        return false;
    }
    $owner_id = (int) ($row['user_id'] ?? 0);
    if ($owner_id <= 0) {
        return false;
    }
    if ($owner_id === $viewer_id) {
        return true;
    }
    if ((string) ($row['status'] ?? '') !== 'approved') {
        return false;
    }
    if (function_exists('casting_users_block_each_other')) {
        require_once __DIR__ . '/blocks.php';
        if (casting_users_block_each_other($viewer_id, $owner_id)) {
            return false;
        }
    }
    if (function_exists('casting_user_can_view_member_profile')) {
        return casting_user_can_view_member_profile($viewer_id, $owner_id);
    }

    return casting_get_user_role($owner_id) !== '';
}

function casting_media_like_count(int $media_id): int
{
    if ($media_id <= 0) {
        return 0;
    }
    casting_media_engagement_ensure();
    global $wpdb;
    $table = casting_media_likes_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE media_id = %d", $media_id));
}

function casting_media_user_liked(int $media_id, int $user_id): bool
{
    if ($media_id <= 0 || $user_id <= 0) {
        return false;
    }
    casting_media_engagement_ensure();
    global $wpdb;
    $table = casting_media_likes_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE media_id = %d AND user_id = %d LIMIT 1",
        $media_id,
        $user_id
    ));

    return (int) $id > 0;
}

/**
 * @return array{ok:bool,error:string,liked?:bool,count?:int}
 */
function casting_media_toggle_like(int $media_id, int $user_id): array
{
    if ($user_id <= 0 || casting_get_user_role($user_id) === '') {
        return ['ok' => false, 'error' => 'برای لایک باید وارد شوید.'];
    }
    $row = casting_media_get_row($media_id);
    if ($row === null || ($row['status'] ?? '') !== 'approved') {
        return ['ok' => false, 'error' => 'پست معتبر نیست.'];
    }
    if (!casting_media_user_can_engage($user_id, $row)) {
        return ['ok' => false, 'error' => 'اجازه تعامل با این پست را ندارید.'];
    }
    casting_media_engagement_ensure();
    global $wpdb;
    $table = casting_media_likes_table();
    $liked = casting_media_user_liked($media_id, $user_id);
    if ($liked) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete($table, ['media_id' => $media_id, 'user_id' => $user_id], ['%d', '%d']);
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->insert($table, [
            'media_id'   => $media_id,
            'user_id'    => $user_id,
            'created_at' => current_time('mysql'),
        ], ['%d', '%d', '%s']);
    }

    return [
        'ok'    => true,
        'error' => '',
        'liked' => !$liked,
        'count' => casting_media_like_count($media_id),
    ];
}

function casting_media_comment_count(int $media_id): int
{
    if ($media_id <= 0) {
        return 0;
    }
    casting_media_engagement_ensure();
    global $wpdb;
    $table = casting_media_comments_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE media_id = %d", $media_id));
}

function casting_media_view_count(int $media_id): int
{
    if ($media_id <= 0) {
        return 0;
    }
    casting_media_engagement_ensure();
    global $wpdb;
    $table = casting_media_views_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE media_id = %d", $media_id));
}

/**
 * بازدید یکتای هر کاربر از پست. بازدید خودِ صاحب پست شمرده نمی‌شود.
 *
 * @return array{ok:bool,error:string,count?:int,recorded?:bool}
 */
function casting_media_record_view(int $media_id, int $user_id): array
{
    if ($user_id <= 0 || casting_get_user_role($user_id) === '') {
        return ['ok' => false, 'error' => 'برای ثبت بازدید باید وارد شوید.'];
    }
    $row = casting_media_get_row($media_id);
    if ($row === null || ($row['status'] ?? '') !== 'approved') {
        return ['ok' => false, 'error' => 'پست معتبر نیست.'];
    }
    $count = casting_media_view_count($media_id);
    $owner_id = (int) ($row['user_id'] ?? 0);
    if ($owner_id === $user_id) {
        return ['ok' => true, 'error' => '', 'count' => $count, 'recorded' => false];
    }
    if (!casting_media_user_can_engage($user_id, $row)) {
        return ['ok' => false, 'error' => 'اجازه مشاهده این پست را ندارید.'];
    }
    casting_media_engagement_ensure();
    global $wpdb;
    $table = casting_media_views_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$table} (media_id, user_id, created_at) VALUES (%d, %d, %s)",
        $media_id,
        $user_id,
        current_time('mysql')
    ));

    return [
        'ok'       => true,
        'error'    => '',
        'count'    => casting_media_view_count($media_id),
        'recorded' => (int) $wpdb->rows_affected > 0,
    ];
}

/**
 * @return list<array{id:int,media_id:int,user_id:int,body:string,created_at:string,name:string}>
 */
function casting_media_list_comments(int $media_id, int $limit = 30): array
{
    if ($media_id <= 0) {
        return [];
    }
    casting_media_engagement_ensure();
    global $wpdb;
    $table = casting_media_comments_table();
    $limit = max(1, min(80, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE media_id = %d ORDER BY id ASC LIMIT %d",
        $media_id,
        $limit
    ), ARRAY_A);
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $uid = (int) ($row['user_id'] ?? 0);
        $user = $uid > 0 ? get_user_by('id', $uid) : null;
        $out[] = [
            'id'         => (int) ($row['id'] ?? 0),
            'media_id'   => (int) ($row['media_id'] ?? 0),
            'user_id'    => $uid,
            'body'       => (string) ($row['body'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'name'       => $user ? (string) $user->display_name : 'کاربر',
        ];
    }

    return $out;
}

/**
 * @return array{ok:bool,error:string,comment?:array{id:int,media_id:int,user_id:int,body:string,created_at:string,name:string},count?:int}
 */
function casting_media_add_comment(int $media_id, int $user_id, string $body): array
{
    if ($user_id <= 0 || casting_get_user_role($user_id) === '') {
        return ['ok' => false, 'error' => 'برای کامنت باید وارد شوید.'];
    }
    $row = casting_media_get_row($media_id);
    if ($row === null || ($row['status'] ?? '') !== 'approved') {
        return ['ok' => false, 'error' => 'پست معتبر نیست.'];
    }
    if (!casting_media_user_can_engage($user_id, $row)) {
        return ['ok' => false, 'error' => 'اجازه تعامل با این پست را ندارید.'];
    }
    $body = sanitize_textarea_field($body);
    $body = trim($body);
    if ($body === '') {
        return ['ok' => false, 'error' => 'متن کامنت خالی است.'];
    }
    if (function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') > 400 : strlen($body) > 400) {
        return ['ok' => false, 'error' => 'کامنت حداکثر ۴۰۰ کاراکتر باشد.'];
    }
    casting_media_engagement_ensure();
    global $wpdb;
    $table = casting_media_comments_table();
    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ok = $wpdb->insert($table, [
        'media_id'   => $media_id,
        'user_id'    => $user_id,
        'body'       => $body,
        'created_at' => $now,
    ], ['%d', '%d', '%s', '%s']);
    if (!$ok) {
        return ['ok' => false, 'error' => 'ثبت کامنت ناموفق بود.'];
    }
    $user = get_user_by('id', $user_id);

    return [
        'ok'      => true,
        'error'   => '',
        'comment' => [
            'id'         => (int) $wpdb->insert_id,
            'media_id'   => $media_id,
            'user_id'    => $user_id,
            'body'       => $body,
            'created_at' => $now,
            'name'       => $user ? (string) $user->display_name : 'کاربر',
        ],
        'count'   => casting_media_comment_count($media_id),
    ];
}

/**
 * نوار لایک/کامنت/بازدید زیر پست
 */
function casting_render_media_engagement(int $media_id, int $viewer_id, bool $compact = false): void
{
    if ($media_id <= 0) {
        return;
    }
    $likes = casting_media_like_count($media_id);
    $comments = casting_media_comment_count($media_id);
    $views = casting_media_view_count($media_id);
    $liked = $viewer_id > 0 && casting_media_user_liked($media_id, $viewer_id);
    $list = $compact ? [] : casting_media_list_comments($media_id, 40);
    $preview_limit = 2;
    $preview = array_slice($list, 0, $preview_limit);
    $show_more = !$compact && ($comments > $preview_limit || count($list) > $preview_limit);
    if (!function_exists('casting_user_can_save_media')) {
        require_once __DIR__ . '/media-protect.php';
    }
    $can_save = $viewer_id > 0 && casting_user_can_save_media($viewer_id);
    $saved = $can_save && casting_media_is_saved($viewer_id, $media_id);
    ?>
  <div class="media-engage" data-media-engage="<?= (int) $media_id ?>">
    <div class="media-engage-actions">
      <button
        type="button"
        class="media-engage-like<?= $liked ? ' is-liked' : '' ?>"
        data-media-like="<?= (int) $media_id ?>"
        aria-pressed="<?= $liked ? 'true' : 'false' ?>"
        <?= $viewer_id <= 0 ? 'disabled' : '' ?>
      >
        <span aria-hidden="true"><?= $liked ? '♥' : '♡' ?></span>
        <span data-like-count><?= (int) $likes ?></span>
        <span class="media-engage-label">پسند</span>
      </button>
      <?php if ($can_save) : ?>
      <button
        type="button"
        class="media-engage-save<?= $saved ? ' is-saved' : '' ?>"
        data-media-save="<?= (int) $media_id ?>"
        aria-pressed="<?= $saved ? 'true' : 'false' ?>"
        title="<?= $saved ? 'حذف از ذخیره‌شده‌ها' : 'ذخیره در پروفایل' ?>"
      >
        <span aria-hidden="true"><?= $saved ? '🔖' : '📑' ?></span>
        <span class="media-engage-label"><?= $saved ? 'ذخیره شد' : 'ذخیره' ?></span>
      </button>
      <?php endif; ?>
      <span class="media-engage-comment-count" title="کامنت">
        <span aria-hidden="true">💬</span>
        <span data-comment-count><?= (int) $comments ?></span>
      </span>
      <span class="media-engage-view-count" title="بازدید">
        <span aria-hidden="true">👁</span>
        <span data-view-count><?= (int) $views ?></span>
        <span class="media-engage-label">بازدید</span>
      </span>
    </div>
    <?php if (!$compact) : ?>
      <ul class="media-engage-comments is-preview" data-media-comments="<?= (int) $media_id ?>">
        <?php foreach ($preview as $c) : ?>
          <li>
            <strong><?= casting_e($c['name']) ?></strong>
            <span><?= nl2br(casting_e($c['body'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <ul class="media-engage-comments media-engage-comments--full" data-media-comments-full="<?= (int) $media_id ?>" hidden>
        <?php foreach ($list as $c) : ?>
          <li>
            <strong><?= casting_e($c['name']) ?></strong>
            <span><?= nl2br(casting_e($c['body'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($show_more) : ?>
        <button type="button" class="link-button media-engage-more" data-post-expand>بیشتر…</button>
      <?php endif; ?>
      <?php if ($viewer_id > 0) : ?>
        <form class="media-engage-form" data-media-comment-form="<?= (int) $media_id ?>">
          <input type="text" name="body" maxlength="400" placeholder="کامنت بنویسید…" required autocomplete="off">
          <button class="btn btn-ghost media-engage-submit" type="submit">ارسال</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
    <?php
}

function casting_render_post_lightbox_shell(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
<div class="post-lightbox" data-post-lightbox aria-hidden="true">
  <div class="post-lightbox-panel" role="dialog" aria-modal="true" aria-labelledby="post-lightbox-title">
    <div class="post-lightbox-head">
      <h2 class="post-lightbox-title" id="post-lightbox-title">پست</h2>
      <button type="button" class="btn btn-ghost btn-sm post-lightbox-close" data-post-lightbox-close>بستن</button>
    </div>
    <div class="post-lightbox-body" data-post-lightbox-body></div>
  </div>
</div>
    <?php
}
