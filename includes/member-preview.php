<?php
declare(strict_types=1);

require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/visitors.php';
require_once __DIR__ . '/panel-profile.php';
require_once __DIR__ . '/chat.php';
require_once __DIR__ . '/request.php';

function casting_format_jalali_datetime_compact(string $mysql): string
{
    $mysql = trim($mysql);
    if ($mysql === '') {
        return '—';
    }
    $ymd = substr($mysql, 0, 10);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return '—';
    }
    [$jy, $jm, $jd] = casting_gregorian_to_jalali((int) $m[1], (int) $m[2], (int) $m[3]);
    $time = strlen($mysql) >= 16 ? substr($mysql, 11, 5) : '';

    return $time !== ''
        ? sprintf('%d/%02d/%02d %s', $jy, $jm, $jd, $time)
        : sprintf('%d/%02d/%02d', $jy, $jm, $jd);
}

function casting_member_preview_visit_count(int $member_id): int
{
    $log = get_user_meta($member_id, 'casting_profile_visitors', true);

    return is_array($log) ? count($log) : 0;
}

function casting_member_preview_completion_percent(array $profile, int $user_id = 0): int
{
    $items = casting_profile_completion_items($profile, $user_id);
    if ($items === []) {
        return 0;
    }
    $done = 0;
    foreach ($items as $item) {
        if (!empty($item['done'])) {
            $done++;
        }
    }

    return (int) round(($done / count($items)) * 100);
}

function casting_member_preview_can_view(int $viewer_id, int $member_id): bool
{
    if ($member_id <= 0 || $viewer_id <= 0 || $viewer_id === $member_id) {
        return false;
    }
    if (function_exists('casting_user_can_view_member_profile')) {
        return casting_user_can_view_member_profile($viewer_id, $member_id);
    }
    if (casting_get_user_role($member_id) === '') {
        return false;
    }
    if (casting_get_user_role($viewer_id) === '') {
        return false;
    }
    $profile = casting_get_profile($member_id);
    if (!$profile['visible']) {
        return false;
    }
    if (function_exists('casting_users_block_each_other') && casting_users_block_each_other($viewer_id, $member_id)) {
        return false;
    }

    return true;
}

function casting_member_preview_show_employer_actions(int $viewer_id, int $member_id): bool
{
    if (!function_exists('casting_user_can_invite_member')) {
        require_once __DIR__ . '/request.php';
    }

    return casting_user_can_invite_member($viewer_id, $member_id);
}

function casting_member_preview_is_favorite(int $viewer_id, int $member_id): bool
{
    if (!casting_user_is_director_role($viewer_id) || casting_get_user_role($member_id) !== 'talent') {
        return false;
    }
    if (!function_exists('casting_director_get_workspace')) {
        require_once __DIR__ . '/director-workspace.php';
    }
    $workspace = casting_director_get_workspace($viewer_id, $member_id);

    return !empty($workspace['is_highlight']);
}

/**
 * @param array<string, mixed> $payload
 * @return array{ok:bool,error:string,redirect?:string,highlight?:bool,message?:string}
 */
function casting_member_preview_handle_action(int $viewer_id, int $member_id, string $action, array $payload = []): array
{
    if (!casting_member_preview_can_view($viewer_id, $member_id)) {
        return ['ok' => false, 'error' => 'دسترسی مجاز نیست.'];
    }

    if ($action === 'add_to_project') {
        if (!function_exists('casting_director_quick_add_talent')) {
            require_once __DIR__ . '/director-desk.php';
        }
        $result = casting_director_quick_add_talent($viewer_id, $member_id, $payload);
        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'افزودن به پروژه ناموفق بود.')];
        }

        return [
            'ok'      => true,
            'error'   => '',
            'message' => (string) ($result['message'] ?? 'به پروژه اضافه شد.'),
        ];
    }

    if ($action === 'favorite') {
        if (!casting_user_is_director_role($viewer_id) || casting_get_user_role($member_id) !== 'talent') {
            return ['ok' => false, 'error' => 'فقط کارگردان می‌تواند به لیست کاندیدا اضافه کند.'];
        }
        if (!function_exists('casting_director_get_workspace')) {
            require_once __DIR__ . '/director-workspace.php';
        }
        $workspace = casting_director_get_workspace($viewer_id, $member_id);
        $workspace['is_highlight'] = empty($workspace['is_highlight']);
        $saved = casting_director_save_workspace($viewer_id, $member_id, $workspace);
        if (!$saved['ok']) {
            return ['ok' => false, 'error' => (string) ($saved['error'] ?? 'ذخیره ناموفق بود.')];
        }

        return [
            'ok'        => true,
            'error'     => '',
            'highlight' => !empty($workspace['is_highlight']),
            'message'   => !empty($workspace['is_highlight']) ? 'به لیست کاندیدا اضافه شد.' : 'کاندید حذف شد.',
        ];
    }

    if ($action === 'follow') {
        if (!function_exists('casting_follow_toggle')) {
            require_once __DIR__ . '/follows.php';
        }
        $result = casting_follow_toggle($viewer_id, $member_id);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'عملیات ناموفق بود.')];
        }

        return [
            'ok'        => true,
            'error'     => '',
            'following' => !empty($result['following']),
            'message'   => (string) ($result['message'] ?? ''),
        ];
    }

    if (!casting_member_preview_show_employer_actions($viewer_id, $member_id)) {
        return ['ok' => false, 'error' => 'این عملیات برای نقش شما فعال نیست.'];
    }

    if ($action === 'interest') {
        $message = casting_employer_default_outreach_message($viewer_id);
        $result = casting_send_talent_request($viewer_id, $member_id, $message, 'دعوت همکاری');
        if (!$result['ok']) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'ارسال ناموفق بود.')];
        }

        return [
            'ok'       => true,
            'error'    => '',
            'redirect' => 'my-requests.php?box=sent',
            'message'  => !empty($result['warning']) ? (string) $result['warning'] : 'دعوت همکاری ارسال شد.',
        ];
    }

    if ($action === 'invite_sms') {
        $result = casting_send_cooperation_invite_sms($viewer_id, $member_id);
        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'ارسال پیامک ناموفق بود.')];
        }

        return [
            'ok'      => true,
            'error'   => '',
            'message' => (string) ($result['message'] ?? 'پیامک دعوت به همکاری ارسال شد.'),
        ];
    }

    return ['ok' => false, 'error' => 'عملیات نامعتبر است.'];
}

function casting_render_member_preview_lightbox_shell(): void
{
    ?>
<div class="member-preview-lightbox" data-member-preview-lightbox aria-hidden="true" data-member-preview-nonce="<?= casting_e(wp_create_nonce('casting_member_preview')) ?>">
  <div class="member-preview-panel" role="dialog" aria-modal="true" aria-labelledby="member-preview-title">
    <button type="button" class="member-preview-close" data-member-preview-close aria-label="بستن">×</button>
    <div class="member-preview-body" data-member-preview-body>
      <p class="meta">در حال بارگذاری…</p>
    </div>
  </div>
</div>
    <?php
}

function casting_render_member_preview_panel(int $member_id, int $viewer_id): void
{
    $member = get_user_by('id', $member_id);
    if (!$member || !casting_member_preview_can_view($viewer_id, $member_id)) {
        echo '<p class="empty-state">پروفایل در دسترس نیست.</p>';
        return;
    }

    casting_record_profile_visit($member_id, $viewer_id);
    if (casting_user_is_director_role($viewer_id) && casting_get_user_role($member_id) === 'talent') {
        casting_director_record_talent_view($viewer_id, $member_id);
    }

    $profile = casting_get_profile($member_id);
    $role = casting_get_user_role($member_id);
    $premium = casting_user_is_premium($member_id);
    $photo = (string) ($profile['photo_url'] ?? '');
    $online = casting_member_is_online($member_id);
    $membership_code = (string) ($profile['membership_number'] ?? '');
    $join_at = casting_format_jalali_datetime_compact((string) $member->user_registered);
    $last_active = casting_format_jalali_datetime_compact((string) get_user_meta($member_id, 'casting_last_active', true));
    $visit_count = casting_member_preview_visit_count($member_id);
    $completion = casting_member_preview_completion_percent($profile, $member_id);
    $show_phones = function_exists('casting_viewer_can_see_contact_numbers')
        && casting_viewer_can_see_contact_numbers($viewer_id)
        && $viewer_id !== $member_id;
    $show_actions = casting_member_preview_show_employer_actions($viewer_id, $member_id);
    $can_favorite = casting_user_is_director_role($viewer_id) && $role === 'talent';
    $is_favorite = $can_favorite && casting_member_preview_is_favorite($viewer_id, $member_id);
    if (!function_exists('casting_follow_can_target')) {
        require_once __DIR__ . '/follows.php';
    }
    $can_follow = casting_follow_can_target($viewer_id, $member_id);
    $viewer_premium = casting_user_is_premium($viewer_id);
    $can_sms = function_exists('casting_user_can_send_invite_sms')
        ? casting_user_can_send_invite_sms($viewer_id)
        : $viewer_premium;
    $free_hint = casting_employer_free_messages_hint($viewer_id);
    $chat_ok = casting_can_user_send_dm($viewer_id, $member_id)['ok'];
    $chat_open = casting_can_user_open_dm($viewer_id, $member_id);
    $age_label = ($profile['age'] ?? '') !== '' ? (string) $profile['age'] . ' ساله' : '—';
    $city_label = (string) ($profile['city'] ?? '');
    if ($city_label === '' && ($profile['province'] ?? '') !== '') {
        $city_label = casting_province_labels()[(string) $profile['province']] ?? '';
    }
    $role_label = casting_user_public_role_label($member_id);
    ?>
    <header class="member-preview-head">
      <div class="member-preview-avatar-wrap">
        <?php if ($photo !== '') : ?>
          <img class="member-preview-avatar" src="<?= casting_e($photo) ?>" alt="">
        <?php else : ?>
          <span class="member-preview-avatar member-preview-avatar--empty">?</span>
        <?php endif; ?>
        <?php casting_render_presence_dot($member_id, 'md'); ?>
      </div>
      <div class="member-preview-head-text">
        <div class="member-card-badge-row">
          <?php casting_render_official_page_badge($member_id); ?>
        </div>
        <h2 class="member-preview-title" id="member-preview-title"><?= casting_e((string) $member->display_name) ?></h2>
        <p class="member-preview-role"><?= casting_e($role_label) ?></p>
        <?php if ($membership_code !== '') : ?>
          <p class="member-preview-code">کد کاربری: <span dir="ltr"><?= casting_e($membership_code) ?></span></p>
        <?php endif; ?>
        <p class="member-preview-status<?= $online ? ' member-preview-status--online' : ' member-preview-status--offline' ?>"><?= $online ? 'آنلاین' : 'آفلاین' ?></p>
      </div>
    </header>

    <ul class="member-preview-facts">
      <li><span class="member-preview-icon" aria-hidden="true">📅</span><span><?= casting_e($age_label) ?></span></li>
      <li><span class="member-preview-icon" aria-hidden="true">📍</span><span><?= casting_e($city_label !== '' ? $city_label : '—') ?></span></li>
      <li><span class="member-preview-icon" aria-hidden="true">👤</span><span>عضویت <?= $premium ? 'ویژه' : 'عادی' ?></span></li>
      <?php if ($show_phones) : ?>
        <?php
        $nums = casting_profile_contact_numbers($profile);
        if ($nums['mobile'] !== '') :
            ?>
          <li><span class="member-preview-icon" aria-hidden="true">📱</span><span dir="ltr"><?= casting_e($nums['mobile']) ?></span></li>
        <?php endif; ?>
        <?php if ($nums['mobile2'] !== '') : ?>
          <li><span class="member-preview-icon" aria-hidden="true">📱</span><span dir="ltr"><?= casting_e($nums['mobile2']) ?></span></li>
        <?php endif; ?>
        <?php if ($nums['phone'] !== '') : ?>
          <li><span class="member-preview-icon" aria-hidden="true">☎</span><span dir="ltr"><?= casting_e($nums['phone']) ?></span></li>
        <?php endif; ?>
      <?php endif; ?>
      <li><span class="member-preview-icon" aria-hidden="true">🗓</span><span>تاریخ عضویت: <?= casting_e($join_at) ?></span></li>
      <li><span class="member-preview-icon" aria-hidden="true">🕒</span><span>آخرین فعالیت: <?= casting_e($last_active) ?></span></li>
      <li><span class="member-preview-icon" aria-hidden="true">👁</span><span>بازدید: <?= (int) $visit_count ?> بار</span></li>
      <li><span class="member-preview-icon" aria-hidden="true">📊</span><span>تکمیل پروفایل: <?= (int) $completion ?>٪</span></li>
    </ul>

    <?php if ($show_actions || $can_favorite || $can_follow || $viewer_id !== $member_id) : ?>
      <div class="member-preview-actions">
        <?php if ($can_follow) : ?>
          <?php casting_render_follow_button($viewer_id, $member_id, 'member-preview-btn member-preview-btn--follow'); ?>
        <?php endif; ?>
        <?php if ($viewer_id !== $member_id) : ?>
          <?php if (!empty($chat_open['ok'])) : ?>
            <a class="btn member-preview-btn member-preview-btn--interest" href="chat.php?with=<?= (int) $member_id ?>">پیام به این کاربر</a>
          <?php else : ?>
            <button
              type="button"
              class="btn member-preview-btn member-preview-btn--interest is-disabled"
              disabled
              title="<?= casting_e((string) ($chat_open['error'] ?? 'طبق جدول دسترسی پیام‌رسان، امکان ارسال پیام نیست.')) ?>"
            >پیام به این کاربر</button>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($show_actions) : ?>
          <button
            type="button"
            class="btn member-preview-btn member-preview-btn--sms"
            data-member-preview-action="invite_sms"
            data-member-id="<?= (int) $member_id ?>"
            <?= $can_sms ? '' : ' disabled title="فقط برای اعضای ویژه"' ?>
          >پیامک دعوت به همکاری</button>
        <?php endif; ?>
        <?php if ($can_favorite) : ?>
          <button
            type="button"
            class="btn member-preview-btn member-preview-btn--favorite<?= $is_favorite ? ' is-active' : '' ?>"
            data-member-preview-action="favorite"
            data-member-id="<?= (int) $member_id ?>"
          ><?= $is_favorite ? 'حذف کاندید' : 'لیست کاندیدا' ?></button>
        <?php endif; ?>
      </div>
      <?php if ($can_favorite) : ?>
        <?php
        if (!function_exists('casting_director_list_projects')) {
            require_once __DIR__ . '/director-desk.php';
        }
        $preview_projects = casting_director_list_projects($viewer_id);
        $preview_roles_map = casting_director_projects_roles_map($viewer_id);
        $preview_types = casting_director_project_type_labels();
        unset($preview_types['film'], $preview_types['series'], $preview_types['other']);
        ?>
        <form class="form member-preview-add-project" data-preview-add-project data-member-id="<?= (int) $member_id ?>" data-preview-roles="<?= casting_e((string) wp_json_encode($preview_roles_map, JSON_UNESCAPED_UNICODE)) ?>">
          <h3 class="member-preview-add-title">افزودن به پروژه</h3>
          <?php if ($preview_projects !== []) : ?>
            <div class="field">
              <label for="preview-project-<?= (int) $member_id ?>">پروژه</label>
              <select id="preview-project-<?= (int) $member_id ?>" name="project_id" data-preview-project-select>
                <option value="">انتخاب پروژه</option>
                <?php foreach ($preview_projects as $project) : ?>
                  <option value="<?= (int) $project['id'] ?>"><?= casting_e((string) $project['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="preview-role-<?= (int) $member_id ?>">نقش موجود</label>
              <select id="preview-role-<?= (int) $member_id ?>" name="role_id" data-preview-role-select>
                <option value="">ابتدا پروژه را انتخاب کنید</option>
              </select>
            </div>
          <?php endif; ?>
          <?php if ($preview_projects === []) : ?>
            <div class="field">
              <label for="preview-project-title-<?= (int) $member_id ?>">نام پروژه جدید</label>
              <input id="preview-project-title-<?= (int) $member_id ?>" name="project_title" type="text" maxlength="191" placeholder="مثلاً نام فیلم">
            </div>
            <div class="field">
              <label for="preview-project-type-<?= (int) $member_id ?>">نوع</label>
              <select id="preview-project-type-<?= (int) $member_id ?>" name="project_type">
                <?php foreach ($preview_types as $key => $label) : ?>
                  <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          <div class="field">
            <label for="preview-role-title-<?= (int) $member_id ?>">یا نقش جدید</label>
            <input id="preview-role-title-<?= (int) $member_id ?>" name="role_title" type="text" maxlength="191" placeholder="مثلاً نقش اصلی">
          </div>
          <button class="btn btn-primary member-preview-btn" type="submit">افزودن به پروژه</button>
        </form>
      <?php endif; ?>
      <?php if ($show_actions && $free_hint !== '') : ?>
        <p class="field-hint member-preview-hint"><?= casting_e($free_hint) ?></p>
      <?php endif; ?>
      <?php if ($show_actions && !$can_sms) : ?>
        <p class="field-hint member-preview-hint">دکمه پیامک دعوت به همکاری فقط برای اعضای ویژه فعال است.</p>
      <?php endif; ?>
    <?php endif; ?>

    <div class="member-preview-footer">
      <a class="btn btn-ghost" href="member.php?id=<?= (int) $member_id ?>">مشاهده پروفایل کامل</a>
    </div>
    <?php
}
