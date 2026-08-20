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
        $kind = (string) ($req['kind'] ?? '');
        if ($kind !== 'casting_call' && $kind !== 'invitation' && $kind !== '') {
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
 * آخرین پست‌های تأییدشدهٔ همهٔ کاربران (به‌ترتیب تاریخ)
 *
 * @return list<array<string, mixed>>
 */
function casting_latest_media_feed(int $limit = 20): array
{
    casting_user_media_ensure_table();
    global $wpdb;
    $media = casting_user_media_table();
    $limit = max(1, min(60, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT m.* FROM {$media} m
         WHERE m.status = 'approved'
         ORDER BY m.created_at DESC, m.id DESC
         LIMIT %d",
        $limit
    ), ARRAY_A);

    return is_array($rows) ? $rows : [];
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
         ORDER BY m.created_at DESC, m.id DESC
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
    if (!function_exists('casting_opportunities_list_open')) {
        require_once __DIR__ . '/opportunities.php';
    }
    if (!function_exists('casting_invitation_project_type_labels')) {
        require_once __DIR__ . '/request.php';
    }
    casting_opportunities_ensure_tables();
    $open = casting_opportunities_list_open(6);
    $personal = casting_home_casting_call_feed($user_id, 4);
    ?>
  <section class="panel-ads-section home-feed-section" aria-labelledby="home-opportunities-title">
    <header class="panel-ads-head">
      <h2 id="home-opportunities-title">فرصت‌ها و فراخوان‌ها</h2>
      <a class="btn btn-ghost btn-sm" href="<?= casting_e(casting_url('opportunities.php')) ?>">همه فرصت‌ها</a>
    </header>

    <?php if ($open === [] && $personal === []) : ?>
      <div class="empty-state empty-state--search" role="status">
        <h2 class="empty-state-title">فعلاً فراخوانی نیست</h2>
        <p class="empty-state-text">کارگردان‌ها می‌توانند از «پروژه‌ها» فراخوان منتشر کنند. بعداً دوباره سر بزنید.</p>
      </div>
    <?php else : ?>
      <?php if ($open !== []) : ?>
        <h3 class="panel-section-title opp-home-subtitle">فراخوان‌های باز</h3>
        <div class="home-opportunity-list opp-card-list">
          <?php foreach ($open as $op) :
              $oid = (int) ($op['id'] ?? 0);
              $mine = casting_opportunity_get_application($oid, $user_id);
              $already = $mine && (string) ($mine['status'] ?? '') !== 'withdrawn';
              casting_render_opportunity_card($op, [
                  'compact' => true,
                  'already' => $already,
              ]);
          endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($personal !== []) : ?>
        <h3 class="panel-section-title" style="font-size:1rem;margin:1rem 0 0.65rem;">دعوت‌های مستقیم به شما</h3>
        <div class="home-opportunity-list">
          <?php
          $types = casting_invitation_project_type_labels();
          foreach ($personal as $req) :
              $req_id = (string) ($req['id'] ?? '');
              $status = casting_request_status_key($req);
              $unread = casting_request_is_unread($user_id, $req);
              $ptype = (string) ($req['project_type'] ?? '');
              $ptype_label = $types[$ptype] ?? $ptype;
              $open_url = casting_request_open_url($req_id);
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
                </p>
                <p class="home-opportunity-status"><?= casting_e(casting_request_status_label($status)) ?></p>
              </div>
              <div class="home-opportunity-actions">
                <a class="btn btn-ghost btn-sm" href="<?= casting_e($open_url) ?>">مشاهده و پاسخ</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
    $can_open_profile = $owner_id > 0 && (
        !(function_exists('casting_user_profile_is_hidden') && casting_user_profile_is_hidden($owner_id))
        || (function_exists('casting_user_can_view_member_profile') && casting_user_can_view_member_profile($viewer_id, $owner_id))
    );
    ?>
  <article class="home-feed-post" data-media-id="<?= $id ?>">
    <header class="home-feed-post-head">
      <?php if (!$can_open_profile) : ?>
      <span class="home-feed-author">
        <strong><?= casting_e($name) ?></strong>
        <?php if ($role !== '') : ?><span class="meta"><?= casting_e($role) ?></span><?php endif; ?>
      </span>
      <?php else : ?>
      <button type="button" class="link-button home-feed-author" data-member-preview="<?= $owner_id ?>">
        <strong><?= casting_e($name) ?></strong>
        <?php if ($role !== '') : ?><span class="meta"><?= casting_e($role) ?></span><?php endif; ?>
      </button>
      <?php endif; ?>
    </header>
    <div class="home-feed-post-media<?= $is_video ? ' is-video' : '' ?>">
      <?php
      if (!function_exists('casting_render_protected_video')) {
          require_once __DIR__ . '/media-protect.php';
      }
      $wm = casting_media_protect_viewer_label();
      if ($is_video) {
          casting_render_protected_video($url, $wm, [
              'class'         => 'media-protect--feed',
              'poster'        => ($thumb !== '' && $thumb !== $url) ? $thumb : '',
              'attachment_id' => (int) ($item['attachment_id'] ?? 0),
          ]);
      } else {
          casting_render_protected_image($thumb !== '' ? $thumb : $url, $wm, [
              'class' => 'media-protect--feed',
          ]);
      }
      ?>
    </div>
    <?php if ($caption !== '') :
        $caption_long = (function_exists('mb_strlen') ? mb_strlen($caption, 'UTF-8') : strlen($caption)) > 90;
        ?>
      <p class="home-feed-caption<?= $caption_long ? ' is-clamped' : '' ?>"><?= nl2br(casting_e($caption)) ?></p>
      <?php if ($caption_long) : ?>
        <button type="button" class="link-button media-engage-more" data-post-expand>بیشتر…</button>
      <?php endif; ?>
    <?php endif; ?>
    <?php casting_render_media_engagement($id, $viewer_id, false); ?>
  </article>
    <?php
}

function casting_render_home_following_feed_section(int $user_id): void
{
    $posts = casting_latest_media_feed(24);
    ?>
  <section class="panel-ads-section home-feed-section" aria-labelledby="home-latest-feed-title">
    <header class="panel-ads-head">
      <h2 id="home-latest-feed-title">آخرین پست‌ها</h2>
    </header>
    <?php if ($posts === []) : ?>
      <p class="empty-state">هنوز پست تأییدشده‌ای برای نمایش نیست.</p>
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
