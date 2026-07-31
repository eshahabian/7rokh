<?php
declare(strict_types=1);

require_once __DIR__ . '/follows.php';
require_once __DIR__ . '/user-media.php';
require_once __DIR__ . '/media-engagement.php';

/**
 * فراخوان‌های کستینگ دریافتی برای فید خانه
 *
 * @return list<array<string, mixed>>
 */
function casting_home_casting_call_feed(int $user_id, int $limit = 8): array
{
    if ($user_id <= 0) {
        return [];
    }
    if (!function_exists('casting_user_received_requests')) {
        require_once __DIR__ . '/request.php';
    }
    $all = casting_user_received_requests($user_id, 'active');
    $calls = [];
    foreach ($all as $req) {
        if (($req['kind'] ?? '') !== 'casting_call') {
            continue;
        }
        $calls[] = $req;
    }
    usort($calls, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    return array_slice($calls, 0, max(1, min(20, $limit)));
}

/**
 * پست‌های تأییدشدهٔ کسانی که کاربر دنبال می‌کند
 *
 * @return list<array<string, mixed>>
 */
function casting_following_media_feed(int $user_id, int $limit = 12): array
{
    if ($user_id <= 0) {
        return [];
    }
    casting_follows_ensure_table();
    casting_user_media_ensure_table();
    global $wpdb;
    $media = casting_user_media_table();
    $follows = casting_follows_table();
    $limit = max(1, min(40, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT m.* FROM {$media} m
         INNER JOIN {$follows} f ON f.followed_id = m.user_id AND f.follower_id = %d
         WHERE m.status = 'approved'
         ORDER BY m.id DESC
         LIMIT %d",
        $user_id,
        $limit
    ), ARRAY_A);

    return is_array($rows) ? $rows : [];
}

/**
 * آخرین فالوئرهای جدید (ساده — از جدول فالو)
 *
 * @return list<array{follower_id:int,created_at:string,name:string}>
 */
function casting_recent_followers_for(int $user_id, int $limit = 5): array
{
    if ($user_id <= 0) {
        return [];
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();
    $limit = max(1, min(20, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT follower_id, created_at FROM {$table} WHERE followed_id = %d ORDER BY id DESC LIMIT %d",
        $user_id,
        $limit
    ), ARRAY_A);
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $fid = (int) ($row['follower_id'] ?? 0);
        $user = $fid > 0 ? get_user_by('id', $fid) : null;
        if (!$user) {
            continue;
        }
        $out[] = [
            'follower_id' => $fid,
            'created_at'  => (string) ($row['created_at'] ?? ''),
            'name'        => (string) $user->display_name,
        ];
    }

    return $out;
}

function casting_render_home_opportunities_section(int $user_id): void
{
    if (!function_exists('casting_invitation_project_type_labels')) {
        require_once __DIR__ . '/request.php';
    }
    $calls = casting_home_casting_call_feed($user_id, 6);
    $types = casting_invitation_project_type_labels();
    ?>
  <section class="panel-ads-section home-feed-section" aria-labelledby="home-opportunities-title">
    <header class="panel-ads-head">
      <h2 id="home-opportunities-title">فرصت‌ها و فراخوان‌ها</h2>
      <a class="btn btn-ghost btn-sm" href="<?= casting_e(casting_url('my-requests.php')) ?>">همه فراخوان‌ها</a>
    </header>
    <?php if ($calls === []) : ?>
      <p class="empty-state">فعلاً فراخوان جدیدی برای شما نیست.</p>
    <?php else : ?>
      <div class="home-opportunity-list">
        <?php foreach ($calls as $req) :
            $req_id = (string) ($req['id'] ?? '');
            $status = casting_request_status_key($req);
            $unread = casting_request_is_unread($user_id, $req);
            $ptype = (string) ($req['project_type'] ?? '');
            $ptype_label = $types[$ptype] ?? $ptype;
            $open = casting_url('my-requests.php' . ($req_id !== '' ? '?open=' . rawurlencode($req_id) : ''));
            ?>
          <article class="home-opportunity-card<?= $unread ? ' is-unread' : '' ?>">
            <div class="home-opportunity-body">
              <h3>
                <?php if ($unread) : ?><span class="req-status req-status-new">جدید</span> <?php endif; ?>
                <?= casting_e((string) ($req['project'] ?? 'فراخوان کستینگ')) ?>
              </h3>
              <p class="meta">
                <?= casting_e((string) ($req['employer'] ?? 'کارفرما')) ?>
                <?php if ($ptype_label !== '') : ?> · <?= casting_e($ptype_label) ?><?php endif; ?>
                <?php if (!empty($req['role_needed'])) : ?> · <?= casting_e((string) $req['role_needed']) ?><?php endif; ?>
                <?php if (!empty($req['project_city'])) : ?> · <?= casting_e((string) $req['project_city']) ?><?php endif; ?>
              </p>
              <p class="home-opportunity-status"><?= casting_e(casting_request_status_label($status)) ?></p>
            </div>
            <div class="home-opportunity-actions">
              <a class="btn btn-primary btn-sm" href="<?= casting_e($open) ?>#invitation-detail">مشاهده و پاسخ</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
    <?php
}

function casting_render_feed_media_card(array $item, int $viewer_id): void
{
    $id = (int) ($item['id'] ?? 0);
    $owner_id = (int) ($item['user_id'] ?? 0);
    $owner = $owner_id > 0 ? get_user_by('id', $owner_id) : null;
    $url = casting_user_media_url($item);
    $thumb = casting_user_media_thumb_url($item);
    if ($url === '' || $id <= 0) {
        return;
    }
    $is_video = ($item['media_type'] ?? '') === 'video';
    $caption = trim((string) ($item['caption'] ?? ''));
    $name = $owner ? (string) $owner->display_name : 'کاربر';
    $role = $owner_id > 0 ? casting_user_public_role_label($owner_id) : '';
    ?>
  <article class="home-feed-post" data-media-id="<?= $id ?>">
    <header class="home-feed-post-head">
      <button type="button" class="link-button home-feed-author" data-member-preview="<?= $owner_id ?>">
        <strong><?= casting_e($name) ?></strong>
        <?php if ($role !== '') : ?><span class="meta"><?= casting_e($role) ?></span><?php endif; ?>
      </button>
    </header>
    <div class="home-feed-post-media<?= $is_video ? ' is-video' : '' ?>">
      <?php if ($is_video) : ?>
        <video src="<?= casting_e($url) ?>" controls preload="metadata" playsinline<?= $thumb !== '' && $thumb !== $url ? ' poster="' . casting_e($thumb) . '"' : '' ?>></video>
      <?php else : ?>
        <a href="<?= casting_e($url) ?>" target="_blank" rel="noopener">
          <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
        </a>
      <?php endif; ?>
    </div>
    <?php if ($caption !== '') : ?>
      <p class="home-feed-caption"><?= nl2br(casting_e($caption)) ?></p>
    <?php endif; ?>
    <?php casting_render_media_engagement($id, $viewer_id, false); ?>
  </article>
    <?php
}

function casting_render_home_following_feed_section(int $user_id): void
{
    $posts = casting_following_media_feed($user_id, 10);
    $recent_followers = casting_recent_followers_for($user_id, 3);
    ?>
  <section class="panel-ads-section home-feed-section" aria-labelledby="home-following-feed-title">
    <header class="panel-ads-head">
      <h2 id="home-following-feed-title">از دنبال‌شده‌ها</h2>
      <a class="btn btn-ghost btn-sm" href="<?= casting_e(casting_url('following.php?tab=following')) ?>">دنبال‌شده‌ها</a>
    </header>
    <?php if ($recent_followers !== []) : ?>
      <p class="home-feed-followers-hint meta">
        دنبال‌کننده‌های تازه:
        <?php
        $names = [];
        foreach ($recent_followers as $f) {
            $names[] = '<button type="button" class="link-button" data-member-preview="' . (int) $f['follower_id'] . '">' . casting_e($f['name']) . '</button>';
        }
        echo implode(' · ', $names);
        ?>
      </p>
    <?php endif; ?>
    <?php if ($posts === []) : ?>
      <p class="empty-state">هنوز پستی از کسانی که دنبال می‌کنید نیست. افراد را دنبال کنید تا فید اینجا پر شود.</p>
    <?php else : ?>
      <div class="home-following-feed">
        <?php foreach ($posts as $item) :
            casting_render_feed_media_card($item, $user_id);
        endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
    <?php
}
