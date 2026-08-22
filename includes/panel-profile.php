<?php
declare(strict_types=1);

require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/director-workspace.php';
require_once __DIR__ . '/director-desk.php';
require_once __DIR__ . '/request.php';

function casting_panel_render_section(int $user_id, callable $render, string $section = ''): void
{
    try {
        $render();
    } catch (Throwable $e) {
        $label = $section !== '' ? $section : 'پنل';
        error_log('[casting-portal] panel section (' . $label . '): ' . $e->getMessage());
        if (function_exists('casting_user_is_super_admin') && casting_user_is_super_admin($user_id)) {
            echo '<div class="flash flash-error" role="alert"><strong>خطا در بارگذاری بخش'
                . ($section !== '' ? ' «' . casting_e($section) . '»' : '')
                . ':</strong> ' . casting_e($e->getMessage()) . '</div>';
        }
    }
}

function casting_render_profile_portraits(array $portraits, bool $actor_set = true): void
{
    if (!function_exists('casting_portrait_slots')) {
        require_once __DIR__ . '/profile.php';
    }
    $dims = casting_portrait_display_dimensions();
    $slots = $actor_set
        ? casting_all_portrait_slots()
        : ['medium' => 'عکس پروفایل'];
    ?>
    <div class="profile-portraits<?= $actor_set ? ' profile-portraits--actor' : ' profile-portraits--single' ?>">
      <?php foreach ($slots as $slot => $label) :
          $shot = casting_portrait_shot($portraits, $slot);
          if ($slot === 'medium' && empty($shot['id']) && function_exists('casting_primary_portrait') && empty($portraits['profile']['id'])) {
              $shot = casting_primary_portrait($portraits);
          }
          $thumb = $shot['url'] !== '' ? $shot['url'] : $shot['full'];
          $full = $shot['full'] !== '' ? $shot['full'] : $thumb;
          ?>
        <figure class="profile-portrait-item">
          <div class="portrait-frame profile-portrait-thumb">
            <?php if ($thumb !== '') : ?>
              <img
                src="<?= casting_e($thumb) ?>"
                alt="<?= casting_e($label) ?>"
                width="<?= (int) $dims['width'] ?>"
                height="<?= (int) $dims['height'] ?>"
                decoding="async"
              >
              <button
                type="button"
                class="profile-portrait-zoom"
                data-portrait-lightbox="<?= casting_e($full) ?>"
                aria-label="نمایش بزرگ <?= casting_e($label) ?>"
              ></button>
            <?php else : ?>
              <div class="photo-placeholder portrait-frame-empty"><?= casting_e($label) ?></div>
            <?php endif; ?>
          </div>
          <figcaption><?= casting_e($label) ?></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
    <?php
}

/**
 * @return array{error:string,success:string,profile:array|null}
 */
function casting_process_profile_post(int $user_id): array
{
    $out = ['error' => '', 'success' => '', 'profile' => null];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $out;
    }
    if (casting_upload_post_too_large()) {
        $out['error'] = casting_upload_post_too_large_message();

        return $out;
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_profile')) {
        return $out;
    }

    $video = casting_handle_video_upload($user_id);
    if (!$video['ok']) {
        $out['error'] = $video['error'];
        return $out;
    }

    $save = casting_save_profile($user_id, [
        'name'                => $_POST['name'] ?? '',
        'birthdate'           => casting_birthdate_from_jalali_post($_POST) ?? '',
        'age'                 => $_POST['age'] ?? '',
        'gender'              => $_POST['gender'] ?? '',
        'email'               => $_POST['email'] ?? '',
        'mobile'              => $_POST['mobile'] ?? '',
        'mobile2'             => $_POST['mobile2'] ?? '',
        'phone'               => $_POST['phone'] ?? '',
        'province'            => $_POST['province'] ?? '',
        'city'                => $_POST['city'] ?? '',
        'height'              => $_POST['height'] ?? '',
        'weight'              => $_POST['weight'] ?? '',
        'health_well'         => $_POST['health_well'] ?? '',
        'health_status'       => $_POST['health_status'] ?? '',
        'experience'          => $_POST['experience'] ?? '',
        'artistic_membership' => $_POST['artistic_membership'] ?? '',
        'artistic_orgs'       => $_POST['artistic_orgs'] ?? [],
        'artistic_other_items'=> $_POST['artistic_other_items'] ?? [],
        'activity_license'    => $_POST['activity_license'] ?? '',
        'look'                => $_POST['look'] ?? '',
        'eye_color'           => $_POST['eye_color'] ?? '',
        'hair_color'          => $_POST['hair_color'] ?? '',
        'accent'              => $_POST['accent'] ?? '',
        'accent_other'        => $_POST['accent_other'] ?? '',
        'apparent_age_range'  => $_POST['apparent_age_range'] ?? '',
        'skill_items'         => casting_parse_skill_items_post($_POST),
        'language_items'      => casting_parse_language_items_post($_POST),
        'availability'        => $_POST['availability'] ?? '',
        'bio'                 => $_POST['bio'] ?? '',
        'work_history'        => $_POST['work_history'] ?? '',
        'award_items'         => casting_parse_award_items_post($_POST),
        'work_credits'        => casting_parse_work_credits_post($_POST),
        'artistic_works'      => casting_parse_artistic_works_post($_POST),
        'education'           => $_POST['education'] ?? '',
        'education_items'     => casting_parse_education_items_post($_POST),
        'activities'          => casting_parse_activities_post($_POST, $user_id),
        'video_url'           => $_POST['video_url'] ?? '',
        'visible'             => !empty($_POST['visible']),
    ]);
    if (!$save['ok']) {
        $out['error'] = $save['error'];
        return $out;
    }

    $out['success'] = 'پروفایل ذخیره شد.';
    $out['profile'] = casting_get_profile($user_id);
    return $out;
}

/**
 * @return array<int, array{label:string,done:bool,href:string,hint:string}>
 */
function casting_profile_completion_items(array $profile, int $user_id = 0): array
{
    $items = [];
    $hints = casting_portrait_slot_hints();
    $hide_talent = casting_profile_hides_talent_fields($profile['activities'] ?? [], $user_id);
    $show_portraits = casting_profile_shows_portraits($profile['activities'] ?? [], $user_id);
    $actor_photos = $user_id > 0
        ? casting_user_uses_actor_portrait_set($user_id)
        : !$hide_talent;

    if ($show_portraits && $actor_photos) {
        foreach (casting_all_portrait_slots() as $slot => $label) {
            $shot = casting_portrait_shot($profile['portraits'] ?? [], $slot);
            $done = (bool) ($shot['id'] > 0 || $shot['full'] !== '' || $shot['url'] !== '');
            $items[] = [
                'label' => 'عکس ' . $label,
                'done'  => $done,
                'href'  => 'profile-photo.php',
                'hint'  => $hints[$slot] ?? '',
            ];
        }
    } elseif ($show_portraits) {
        $shot = casting_portrait_shot($profile['portraits'] ?? [], 'medium');
        if (empty($shot['id']) && function_exists('casting_primary_portrait')) {
            $shot = casting_primary_portrait($profile['portraits'] ?? []);
        }
        $done = (bool) ($shot['id'] > 0 || $shot['full'] !== '' || $shot['url'] !== '');
        $items[] = [
            'label' => 'عکس پروفایل',
            'done'  => $done,
            'href'  => 'profile-photo.php',
            'hint'  => 'یک عکس واضح از خودتان',
        ];
    }

    $email = (string) ($profile['email'] ?? '');
    $email_done = is_email($email) && !(function_exists('casting_is_placeholder_email') && casting_is_placeholder_email($email));
    $name = trim((string) ($profile['name'] ?? ''));
    $login = $user_id > 0 ? (string) (get_userdata($user_id)->user_login ?? '') : '';
    $name_done = $name !== '' && casting_strlen($name) >= 2 && strcasecmp($name, $login) !== 0;

    $edit = 'edit-profile.php';
    $checks = [
        ['label' => 'نام و نام خانوادگی', 'done' => $name_done, 'href' => $edit, 'hint' => ''],
        ['label' => 'تاریخ تولد', 'done' => (bool) (($profile['birthdate'] ?? '') !== '' || ($profile['age'] ?? '') !== ''), 'href' => $edit, 'hint' => ''],
        ['label' => 'جنسیت', 'done' => (bool) (($profile['gender'] ?? '') !== ''), 'href' => $edit, 'hint' => ''],
        ['label' => 'ایمیل', 'done' => $email_done, 'href' => 'change-email.php', 'hint' => ''],
        ['label' => 'موبایل', 'done' => (bool) (($profile['mobile'] ?? '') !== ''), 'href' => $edit, 'hint' => ''],
        ['label' => 'استان و شهر', 'done' => (bool) (($profile['province'] ?? '') !== '' && ($profile['city'] ?? '') !== ''), 'href' => $edit, 'hint' => ''],
    ];
    if (!$hide_talent) {
        $checks[] = ['label' => 'قد و وزن', 'done' => (bool) (($profile['height'] ?? '') !== '' && ($profile['weight'] ?? '') !== ''), 'href' => $edit, 'hint' => ''];
    }
    $checks[] = ['label' => 'نوع فعالیت', 'done' => (bool) !empty($profile['activities']), 'href' => $edit, 'hint' => ''];
    $checks[] = ['label' => 'درباره من', 'done' => (bool) (($profile['bio'] ?? '') !== ''), 'href' => $edit, 'hint' => ''];

    return array_merge($items, $checks);
}

function casting_profile_completion_percent(array $profile, int $user_id = 0): int
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

function casting_profile_needs_completion(array $profile, int $user_id = 0): bool
{
    return casting_profile_completion_percent($profile, $user_id) < 100;
}

function casting_panel_missing_label(string $value, string $edit_href = '#edit-profile'): string
{
    if (trim($value) === '' || $value === '—') {
        return '<span class="info-missing">تکمیل نشده</span>';
    }

    return casting_e($value);
}

function casting_render_panel_completion_card(array $profile, int $user_id = 0): void
{
    $hide_talent = casting_profile_hides_talent_fields($profile['activities'] ?? [], $user_id);
    $show_portraits = casting_profile_shows_portraits($profile['activities'] ?? [], $user_id);
    $items = casting_profile_completion_items($profile, $user_id);
    $done_count = 0;
    $missing = [];
    foreach ($items as $item) {
        if (!empty($item['done'])) {
            $done_count++;
        } else {
            $missing[] = $item;
        }
    }
    $total = count($items);
    $percent = $total > 0 ? (int) round(($done_count / $total) * 100) : 0;
    ?>
<section class="dash-card panel-completion" id="completion">
  <div class="panel-completion-head">
    <div>
      <h2 class="panel-section-title">تکمیل پروفایل</h2>
      <?php if ($done_count === $total) : ?>
      <p class="meta panel-completion-meta">همه موارد اصلی تکمیل شده است.</p>
      <?php else : ?>
      <p class="meta panel-completion-meta">برای بهتر دیده شدن پروفایل خودتون را تکمیل کنید<?= $total > $done_count ? ' — ' . ($total - $done_count) . ' مورد باقی مانده' : '' ?>.</p>
      <?php endif; ?>
    </div>
    <div class="panel-completion-meter" aria-label="پیشرفت تکمیل پروفایل">
      <span class="panel-completion-value"><?= $percent ?>٪</span>
      <span class="panel-completion-bar" style="--progress: <?= $percent ?>"></span>
    </div>
  </div>

  <?php if ($show_portraits) :
      $actor_photos = casting_user_uses_actor_portrait_set($user_id);
      $slot_map = $actor_photos
          ? casting_all_portrait_slots()
          : ['medium' => 'عکس پروفایل'];
      ?>
  <div class="panel-photo-slots<?= $actor_photos ? ' panel-photo-slots--actor' : ' panel-photo-slots--single' ?>">
    <?php foreach ($slot_map as $slot => $label) :
        $shot = casting_portrait_shot($profile['portraits'] ?? [], $slot);
        if ($slot === 'medium' && empty($shot['id'])) {
            $shot = casting_primary_portrait($profile['portraits'] ?? []);
        }
        $src = $shot['url'] !== '' ? $shot['url'] : $shot['full'];
        $hint = $actor_photos
            ? (casting_portrait_slot_hints()[$slot] ?? '')
            : 'یک عکس واضح از خودتان';
        ?>
      <a class="panel-photo-slot<?= $src === '' ? ' is-empty' : '' ?><?= $slot === 'profile' ? ' panel-photo-slot--profile' : '' ?>" href="profile-photo.php">
        <?php if ($src !== '') : ?>
          <?php $dims = casting_portrait_display_dimensions(); ?>
          <span class="portrait-frame panel-photo-slot-frame">
            <img
              src="<?= casting_e($src) ?>"
              alt="<?= casting_e($label) ?>"
              width="<?= (int) $dims['width'] ?>"
              height="<?= (int) $dims['height'] ?>"
              decoding="async"
            >
          </span>
        <?php else : ?>
          <span class="panel-photo-slot-empty">+</span>
        <?php endif; ?>
        <span class="panel-photo-slot-label"><?= casting_e($label) ?></span>
        <span class="panel-photo-slot-hint"><?= $src === '' ? 'بارگذاری نشده' : casting_e($hint) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($missing) : ?>
    <ul class="panel-missing-list">
      <?php foreach ($missing as $item) : ?>
        <li>
          <span><?= casting_e($item['label']) ?></span>
          <a href="<?= casting_e($item['href']) ?>">تکمیل</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
    <?php
}

function casting_render_member_profile_view(int $member_id, int $viewer_id, bool $embedded = false, string $project = '', string $message = ''): void
{
    $member = get_user_by('id', $member_id);
    if (!$member) {
        return;
    }

    $member_role = casting_get_user_role($member_id);
    if ($member_role === '') {
        return;
    }

    $is_self = $viewer_id === $member_id;
    $profile = casting_get_profile($member_id);
    $hide_talent_details = casting_profile_hides_talent_fields($profile['activities'] ?? [], $member_id);
    $show_portraits = casting_profile_shows_portraits($profile['activities'] ?? [], $member_id);
    $actor_photos = casting_user_uses_actor_portrait_set($member_id);
    $genders = casting_gender_labels();
    $provinces = casting_province_labels();
    $availability_labels = casting_availability_labels();
    $eye_colors = casting_eye_color_labels();
    $hair_colors = casting_hair_color_labels();
    $age_ranges = casting_age_range_options();
    $skills_text = casting_format_skill_labels($profile['skill_items'] ?? [], (string) ($profile['skills_other'] ?? ''));
    $premium = casting_user_is_premium($member_id);
    $viewer_role = casting_get_user_role($viewer_id);
    if ($message === '' && !$is_self && casting_user_can_send_casting_requests($viewer_id)) {
        $message = casting_employer_default_outreach_message($viewer_id);
    }
    $invite_message_locked = !$is_self
        && casting_is_employer_role($viewer_role)
        && function_exists('casting_employer_must_use_fixed_outreach')
        && casting_employer_must_use_fixed_outreach($viewer_id);
    $chat_allow = !$is_self ? casting_can_user_open_dm($viewer_id, $member_id) : ['ok' => false];
    $is_blocked = !$is_self ? casting_is_blocked($viewer_id, $member_id) : false;
    $director_workspace = null;
    $show_director_tools = !$is_self
        && casting_user_is_director_role($viewer_id)
        && $member_role === 'talent';
    if ($show_director_tools) {
        $director_workspace = casting_director_get_workspace($viewer_id, $member_id);
    }
    $director_section_class = static function (string $key) use ($director_workspace): string {
        if (!is_array($director_workspace)) {
            return '';
        }
        return casting_director_section_class($director_workspace, $key);
    };
    ?>
<section class="dash-card profile-view">
  <?php if (!$embedded) : ?>
    <?php if ($is_self && function_exists('casting_render_panel_heading')) : ?>
      <?php casting_render_panel_heading('پروفایل من'); ?>
    <?php else : ?>
      <a class="back-link" href="<?= $is_self ? 'panel.php' : 'search-users.php' ?>">← بازگشت</a>
    <?php endif; ?>
  <?php endif; ?>

  <div class="profile-hero<?= $embedded ? ' profile-hero--panel' : '' ?>">
    <?php if (!$embedded && $show_portraits) : ?>
    <div class="profile-portraits-wrap<?= $director_section_class('portraits') ?>">
      <?php if ($show_director_tools && !empty($director_workspace['viewed'])) : ?>
        <?php casting_render_director_viewed_badge(true, 'director-viewed-badge--profile'); ?>
      <?php endif; ?>
      <?php casting_render_profile_portraits($profile['portraits'], $actor_photos); ?>
    </div>
    <?php endif; ?>
    <div class="profile-info">
      <span class="chip"><?= casting_e(casting_user_profile_chip_label($member_id, $viewer_id)) ?><?php if ($premium) : ?> · ویژه<?php endif; ?></span>
      <?php
      if (!function_exists('casting_render_official_page_badge')) {
          require_once __DIR__ . '/follows.php';
      }
      casting_render_official_page_badge($member_id);
      ?>
      <h2 class="panel-section-title"><?= casting_e($member->display_name) ?><?php if ($is_self) : ?> <span class="meta">(پروفایل شما)</span><?php endif; ?></h2>
      <?php
      if (!$is_self && $viewer_id > 0) {
          if (!function_exists('casting_follow_can_target')) {
              require_once __DIR__ . '/follows.php';
          }
          ?>
      <div class="profile-follow-row">
        <span class="meta profile-followers-count"><?= (int) casting_followers_count($member_id) ?> دنبال‌کننده</span>
        <?php if (casting_follow_can_target($viewer_id, $member_id)) : ?>
          <?php casting_render_follow_button($viewer_id, $member_id, 'btn-sm'); ?>
        <?php endif; ?>
      </div>
          <?php
      }
      ?>
      <?php if (!$is_self && $viewer_id > 0) : ?>
        <?php
        if (!function_exists('casting_render_profile_message_cta')) {
            require_once __DIR__ . '/chat-rules.php';
        }
        casting_render_profile_message_cta($viewer_id, $member_id, (string) $member->display_name, $chat_allow);
        ?>
        <div class="block-user-section">
          <?php if ($is_blocked) : ?>
            <form method="post" action="member.php?id=<?= $member_id ?>">
              <?php wp_nonce_field('casting_block'); ?>
              <input type="hidden" name="block_id" value="<?= $member_id ?>">
              <button class="btn btn-ghost" type="submit" name="block_action" value="unblock">رفع بلاک</button>
            </form>
          <?php else : ?>
            <div class="block-user-wrap">
              <?php casting_render_block_user_form('member.php?id=' . $member_id, $member_id, 'casting_block', 'member'); ?>
            </div>
          <?php endif; ?>
        </div>
      <?php elseif ($embedded) : ?>
        <div class="cta-row profile-panel-actions">
          <?php if ($show_portraits) : ?>
          <a class="btn btn-primary" href="profile-photo.php"><?= $actor_photos ? 'ویرایش عکس‌ها' : 'ویرایش عکس پروفایل' ?></a>
          <?php endif; ?>
          <a class="btn btn-ghost" href="#edit-profile">ویرایش اطلاعات</a>
        </div>
        <?php casting_render_premium_account_links('cta-row profile-panel-actions profile-premium-links'); ?>
      <?php endif; ?>
      <ul class="info-list<?= $director_section_class('info') ?>">
        <?php if ($is_self && ($profile['membership_number'] ?? '') !== '') : ?>
          <li><strong>شماره عضویت:</strong> <span class="membership-number"><?= casting_e((string) $profile['membership_number']) ?></span></li>
        <?php endif; ?>
        <?php if ($is_self) : ?>
          <?php
          if (!function_exists('casting_get_referral_code')) {
              require_once __DIR__ . '/referral.php';
          }
          $self_referral_code = casting_get_referral_code($member_id);
          $self_active_duration = casting_user_active_duration_label($member_id);
          ?>
          <?php if ($self_referral_code !== '') : ?>
            <li><strong>کد معرفی:</strong> <span class="membership-number referral-code" dir="ltr"><?= casting_e($self_referral_code) ?></span> · <a href="#referral-code">جزئیات</a></li>
          <?php endif; ?>
          <li><strong>مدت فعال بودن:</strong> <?= casting_e($self_active_duration) ?></li>
          <li><strong>ایمیل:</strong> <?= $embedded
              ? casting_panel_missing_label(is_email((string) ($profile['email'] ?? '')) ? (string) $profile['email'] : '')
              : casting_e(is_email((string) ($profile['email'] ?? '')) ? (string) $profile['email'] : '—') ?>
            <?php if ($is_self) : ?> · <a href="change-email.php">ویرایش</a><?php endif; ?></li>
        <?php endif; ?>
        <?php if (casting_viewer_can_see_contact_numbers($viewer_id) && !$is_self) : ?>
          <?php casting_render_contact_number_items($profile); ?>
        <?php endif; ?>
        <li><strong>سن:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($profile['age'] !== '' ? $profile['age'] . ' سال' : '')
            : casting_e($profile['age'] !== '' ? $profile['age'] . ' سال' : '—') ?></li>
        <?php if (!$hide_talent_details) : ?>
        <?php if (!$embedded && ($profile['apparent_age_range'] ?? '') !== '' && isset($age_ranges[$profile['apparent_age_range']])) : ?>
          <li><strong>سن ظاهری:</strong> <?= casting_e($age_ranges[$profile['apparent_age_range']]['label']) ?></li>
        <?php elseif ($embedded && $is_self) : ?>
          <li><strong>سن ظاهری:</strong> <?= casting_panel_missing_label(
              (($profile['apparent_age_range'] ?? '') !== '' && isset($age_ranges[$profile['apparent_age_range']]))
                  ? $age_ranges[$profile['apparent_age_range']]['label']
                  : ''
          ) ?></li>
        <?php endif; ?>
        <?php endif; ?>
        <li><strong>جنسیت:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($genders[$profile['gender']] ?? '')
            : casting_e($genders[$profile['gender']] ?? '—') ?></li>
        <?php if (!$hide_talent_details) : ?>
        <?php if (!$embedded && ($profile['eye_color'] ?? '') !== '') : ?>
          <li><strong>رنگ چشم:</strong> <?= casting_e($eye_colors[$profile['eye_color']] ?? '—') ?></li>
        <?php elseif ($embedded && $is_self) : ?>
          <li><strong>رنگ چشم:</strong> <?= casting_panel_missing_label($eye_colors[$profile['eye_color']] ?? '') ?></li>
        <?php endif; ?>
        <?php if (!$embedded && ($profile['hair_color'] ?? '') !== '') : ?>
          <li><strong>رنگ مو:</strong> <?= casting_e($hair_colors[$profile['hair_color']] ?? '—') ?></li>
        <?php elseif ($embedded && $is_self) : ?>
          <li><strong>رنگ مو:</strong> <?= casting_panel_missing_label($hair_colors[$profile['hair_color']] ?? '') ?></li>
        <?php endif; ?>
        <?php if (!$embedded && ($profile['accent'] ?? '') !== '') : ?>
          <li><strong>لهجه:</strong> <?= casting_e(casting_format_accent_display((string) $profile['accent'], (string) ($profile['accent_other'] ?? ''))) ?></li>
        <?php elseif ($embedded && $is_self) : ?>
          <li><strong>لهجه:</strong> <?= casting_panel_missing_label(
              ($profile['accent'] ?? '') !== ''
                  ? casting_format_accent_display((string) $profile['accent'], (string) ($profile['accent_other'] ?? ''))
                  : ''
          ) ?></li>
        <?php endif; ?>
        <li><strong>قد:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label(casting_format_body_metric_value('height', (string) ($profile['height'] ?? '')))
            : casting_e(casting_format_body_metric_value('height', (string) ($profile['height'] ?? '')) !== ''
                ? casting_format_body_metric_value('height', (string) ($profile['height'] ?? ''))
                : '—') ?></li>
        <li><strong>وزن:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label(casting_format_body_metric_value('weight', (string) ($profile['weight'] ?? '')))
            : casting_e(casting_format_body_metric_value('weight', (string) ($profile['weight'] ?? '')) !== ''
                ? casting_format_body_metric_value('weight', (string) ($profile['weight'] ?? ''))
                : '—') ?></li>
        <?php endif; ?>
        <?php if (!$hide_talent_details) : ?>
        <li><strong>وضعیت سلامت:</strong> <?= casting_e(casting_format_health_display(
            (string) ($profile['health_well'] ?? 'healthy'),
            (string) ($profile['health_status'] ?? '')
        )) ?></li>
        <?php endif; ?>
        <li><strong>استان:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($provinces[$profile['province'] ?? ''] ?? '')
            : casting_e($provinces[$profile['province'] ?? ''] ?? '—') ?></li>
        <li><strong>شهر:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($profile['city'] !== '' ? $profile['city'] : '')
            : casting_e($profile['city'] !== '' ? $profile['city'] : '—') ?></li>
        <?php if (!$hide_talent_details) : ?>
        <li><strong>وضعیت آمادگی:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($availability_labels[$profile['availability'] ?? ''] ?? '')
            : casting_e($availability_labels[$profile['availability'] ?? ''] ?? '—') ?></li>
        <?php endif; ?>
        <li><strong>تشکل‌های هنری:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label(casting_format_artistic_membership($profile['artistic_membership'] ?? []))
            : casting_e(casting_format_artistic_membership($profile['artistic_membership'] ?? [])) ?></li>
        <?php if (!$hide_talent_details) : ?>
        <li class="<?= $director_section_class('skills') ?>"><strong>مهارت‌ها:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($skills_text !== '' ? $skills_text : '')
            : casting_e($skills_text !== '' ? $skills_text : '—') ?></li>
        <?php endif; ?>
      </ul>
      <?php
      $activity_groups = casting_group_activities_for_display($profile['activities'] ?? [], $member_id, $viewer_id);
      if ($activity_groups) :
          ?>
        <div class="activity-display<?= $director_section_class('activities') ?>">
          <h3>نوع فعالیت</h3>
          <?php foreach ($activity_groups as $group) : ?>
            <p><strong><?= casting_e($group['category']) ?>:</strong> <?= casting_e(implode('، ', $group['items'])) ?></p>
          <?php endforeach; ?>
        </div>
      <?php elseif ($embedded && $is_self) : ?>
        <div class="activity-display activity-display--missing">
          <h3>نوع فعالیت</h3>
          <p><?= casting_panel_missing_label('') ?> — <a href="#edit-profile">افزودن</a></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php
  if (!function_exists('casting_render_public_media_gallery')) {
      require_once __DIR__ . '/user-media.php';
  }
  casting_render_public_media_gallery($member_id);
  if (!function_exists('casting_user_has_admin_permission')) {
      require_once __DIR__ . '/admin-access.php';
  }
  casting_render_admin_approved_media_section($member_id);
  ?>

  <?php if ($profile['bio'] !== '') : ?>
    <div class="bio-block<?= $director_section_class('bio') ?>"><h3>درباره</h3><p><?= nl2br(casting_e($profile['bio'])) ?></p></div>
  <?php elseif ($embedded && $is_self) : ?>
    <div class="bio-block bio-block--missing"><h3>درباره</h3><p><?= casting_panel_missing_label('') ?> — <a href="#edit-profile">نوشتن معرفی</a></p></div>
  <?php endif; ?>

  <?php
  // بخش‌های پروفایل استعداد که قبلاً ذخیره می‌شدند ولی روی پروفایل عمومی دیده نمی‌شدند
  $look_labels = casting_look_labels();
  $look_key = (string) ($profile['look'] ?? '');
  $look_label = $look_labels[$look_key] ?? '';
  $experience_years = trim((string) ($profile['experience'] ?? ''));
  $license_key = (string) ($profile['activity_license'] ?? '');
  $license_label = $license_key === 'yes' ? 'دارد' : ($license_key === 'no' ? 'ندارد' : '');
  $work_credits = is_array($profile['work_credits'] ?? null) ? $profile['work_credits'] : [];
  $artistic_works = is_array($profile['artistic_works'] ?? null) ? $profile['artistic_works'] : [];
  $education_items = is_array($profile['education_items'] ?? null) ? $profile['education_items'] : [];
  $language_items = is_array($profile['language_items'] ?? null) ? $profile['language_items'] : [];
  $education_note = trim((string) ($profile['education'] ?? ''));
  $work_history = trim((string) ($profile['work_history'] ?? ''));
  $award_items = casting_normalize_award_items($profile['award_items'] ?? ($profile['awards'] ?? []));
  $has_awards = $award_items !== [];
  $video_file = trim((string) ($profile['video_file_url'] ?? ''));
  $video_link = trim((string) ($profile['video_url'] ?? ''));
  $work_types = casting_work_type_labels();
  $degree_labels = casting_education_degree_labels();
  $lang_levels = casting_language_level_labels();
  $has_career_block = $look_label !== '' || $experience_years !== '' || $license_label !== ''
      || $work_credits !== [] || $artistic_works !== [] || $work_history !== '' || $has_awards
      || $education_items !== [] || $education_note !== '' || $language_items !== []
      || $video_file !== '' || $video_link !== '';
  ?>
  <?php if ($has_career_block) : ?>
    <div class="profile-career-stack<?= $director_section_class('career') ?>">
      <?php if ($video_file !== '' || $video_link !== '') : ?>
        <div class="bio-block profile-video-block">
          <h3>ویدیو معرفی</h3>
          <?php if ($video_file !== '') : ?>
            <div class="profile-video-player">
              <?php
              if (!function_exists('casting_render_protected_video')) {
                  require_once __DIR__ . '/media-protect.php';
              }
              casting_render_protected_video($video_file, casting_media_protect_viewer_label(), [
                  'class'         => 'media-protect--intro',
                  'attachment_id' => (int) ($profile['video_id'] ?? 0),
              ]);
              ?>
            </div>
          <?php endif; ?>
          <?php if ($video_link !== '') : ?>
            <p class="meta"><a href="<?= casting_e($video_link) ?>">مشاهده لینک ویدیو</a></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($look_label !== '' || $experience_years !== '' || $license_label !== '') : ?>
        <div class="bio-block">
          <h3>مشخصات حرفه‌ای</h3>
          <ul class="info-list">
            <?php if ($look_label !== '') : ?>
              <li><strong>رنگ پوست:</strong> <?= casting_e($look_label) ?></li>
            <?php endif; ?>
            <?php if ($experience_years !== '') : ?>
              <li><strong>سابقه:</strong> <?= casting_e($experience_years) ?> سال</li>
            <?php endif; ?>
            <?php if ($license_label !== '') : ?>
              <li><strong>پروانه فعالیت:</strong> <?= casting_e($license_label) ?></li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($work_credits !== [] || $artistic_works !== [] || $work_history !== '') : ?>
        <div class="bio-block">
          <h3>سوابق کاری</h3>
          <?php if ($work_credits !== []) : ?>
            <ul class="profile-credit-list">
              <?php foreach ($work_credits as $credit) :
                  $ctype = (string) ($credit['type'] ?? 'film');
                  $ctitle = trim((string) ($credit['title'] ?? ''));
                  if ($ctitle === '') {
                      continue;
                  }
                  ?>
                <li>
                  <span class="profile-credit-type"><?= casting_e($work_types[$ctype] ?? $ctype) ?></span>
                  <span class="profile-credit-title"><?= casting_e($ctitle) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($artistic_works !== []) : ?>
            <p class="meta"><strong>آثار هنری:</strong></p>
            <ul class="profile-credit-list">
              <?php foreach ($artistic_works as $work) :
                  $wtype = (string) ($work['type'] ?? 'film');
                  $wtitle = trim((string) ($work['title'] ?? ''));
                  if ($wtitle === '') {
                      continue;
                  }
                  ?>
                <li>
                  <span class="profile-credit-type"><?= casting_e($work_types[$wtype] ?? $wtype) ?></span>
                  <span class="profile-credit-title"><?= casting_e($wtitle) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($work_history !== '') : ?>
            <p><?= nl2br(casting_e($work_history)) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($has_awards) : ?>
        <div class="bio-block">
          <h3>جوایز و افتخارات</h3>
          <ul class="credit-list">
            <?php foreach ($award_items as $award) :
                $atitle = trim((string) ($award['title'] ?? ''));
                if ($atitle === '') {
                    continue;
                }
                $ayear = trim((string) ($award['year'] ?? ''));
                ?>
              <li>
                <?php if ($ayear !== '') : ?>
                  <span class="credit-type"><?= casting_e($ayear) ?></span>
                <?php endif; ?>
                <?= casting_e($atitle) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($language_items !== []) : ?>
        <div class="bio-block">
          <h3>زبان‌ها</h3>
          <ul class="profile-credit-list">
            <?php foreach ($language_items as $lang) :
                $lname = trim((string) ($lang['name'] ?? ''));
                if ($lname === '') {
                    continue;
                }
                $llevel = (string) ($lang['level'] ?? '');
                $llevel_label = $lang_levels[$llevel] ?? '';
                ?>
              <li>
                <span class="profile-credit-title"><?= casting_e($lname) ?></span>
                <?php if ($llevel_label !== '') : ?>
                  <span class="profile-credit-type"><?= casting_e($llevel_label) ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($education_items !== [] || $education_note !== '') : ?>
        <div class="bio-block">
          <h3>تحصیلات</h3>
          <?php if ($education_items !== []) : ?>
            <ul class="profile-credit-list">
              <?php foreach ($education_items as $edu) :
                  $degree = (string) ($edu['degree'] ?? '');
                  $uni = trim((string) ($edu['university'] ?? ''));
                  $degree_label = $degree_labels[$degree] ?? '';
                  if ($degree_label === '' && $uni === '') {
                      continue;
                  }
                  ?>
                <li>
                  <?php if ($degree_label !== '') : ?>
                    <span class="profile-credit-type"><?= casting_e($degree_label) ?></span>
                  <?php endif; ?>
                  <?php if ($uni !== '') : ?>
                    <span class="profile-credit-title"><?= casting_e($uni) ?></span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($education_note !== '') : ?>
            <p><?= nl2br(casting_e($education_note)) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php elseif ($embedded && $is_self && !$hide_talent_details) : ?>
    <div class="bio-block bio-block--missing">
      <h3>سوابق و ویدیو</h3>
      <p><?= casting_panel_missing_label('') ?> — <a href="#edit-profile">تکمیل سوابق، زبان، تحصیل و ویدیو</a></p>
    </div>
  <?php endif; ?>

  <?php
  if ($is_self || (function_exists('casting_user_is_super_admin') && casting_user_is_super_admin($viewer_id))) {
      if (!function_exists('casting_render_referral_profile_section')) {
          require_once __DIR__ . '/referral.php';
      }
      casting_render_referral_profile_section(
          $member_id,
          function_exists('casting_user_is_super_admin') && casting_user_is_super_admin($viewer_id) && !$is_self
      );
  }
  ?>

  <?php if ($show_director_tools && is_array($director_workspace)) : ?>
    <?php casting_render_director_talent_workspace_panel($viewer_id, $member_id, $director_workspace); ?>
    <?php casting_render_director_desk_talent_panel($viewer_id, $member_id, max(0, (int) ($_GET['role'] ?? 0))); ?>
  <?php endif; ?>

  <?php if (!$is_self && casting_user_can_invite_member($viewer_id, $member_id)) : ?>
    <?php $invite_types = casting_invitation_project_type_labels(); ?>
    <div class="bio-block request-box" id="request-box">
      <h3>ارسال فراخوان کستینگ</h3>
      <p class="field-hint">این فراخوان در بخش «فراخوان کستینگ» گیرنده دیده می‌شود، نه در پیام‌ها.</p>
      <form class="form" method="post" action="member.php?id=<?= $member_id ?>">
        <?php wp_nonce_field('casting_request_' . $member_id); ?>
        <div class="form-grid">
          <div class="field">
            <label for="project">نام پروژه</label>
            <input id="project" name="project" type="text" required maxlength="191" value="<?= casting_e($project) ?>">
          </div>
          <div class="field">
            <label for="project_type">نوع پروژه</label>
            <select id="project_type" name="project_type">
              <option value="">انتخاب کنید</option>
              <?php foreach ($invite_types as $key => $label) : ?>
                <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="role_needed">نقش یا تخصص موردنظر</label>
            <input id="role_needed" name="role_needed" type="text" maxlength="191">
          </div>
          <div class="field">
            <label for="project_city">شهر پروژه</label>
            <input id="project_city" name="project_city" type="text" maxlength="120">
          </div>
        </div>
        <div class="field">
          <label for="message">توضیح کوتاه</label>
          <?php if ($invite_message_locked) : ?>
            <input type="hidden" name="message" value="<?= casting_e($message) ?>">
            <textarea id="message" rows="6" maxlength="2000" readonly><?= casting_e($message) ?></textarea>
            <p class="field-hint"><?= casting_e(casting_employer_free_messages_hint($viewer_id)) ?></p>
          <?php else : ?>
            <textarea id="message" name="message" rows="6" required maxlength="2000"><?= casting_e($message) ?></textarea>
          <?php endif; ?>
        </div>
        <button class="btn btn-primary" type="submit">ارسال فراخوان</button>
      </form>
    </div>
  <?php endif; ?>
</section>
    <?php
}

function casting_render_profile_edit_form(int $user_id, array $profile, bool $open = false): void
{
    $hide_talent_profile = casting_profile_hides_talent_fields($profile['activities'] ?? [], $user_id);
    $talent_hidden = $hide_talent_profile ? ' hidden' : '';
    if ($open) {
        ?>
<section class="dash-card panel-profile-edit" id="edit-profile">
  <?php casting_render_panel_heading('ویرایش پروفایل'); ?>
  <div class="panel-edit-body">
        <?php
    } else {
        ?>
<details class="dash-card panel-profile-edit panel-edit-details" id="edit-profile">
  <summary class="panel-edit-summary">
    <h2 class="panel-section-title">ویرایش پروفایل</h2>
    <span class="panel-edit-toggle">باز / بسته</span>
  </summary>
  <div class="panel-edit-body">
        <?php
    }
    ?>
  <p class="lede">می‌توانید همهٔ اطلاعات پروفایل را دوباره تغییر دهید؛ فیلدهای ستاره‌دار همچنان الزامی‌اند.<?php if (casting_profile_shows_portraits($profile['activities'] ?? [], $user_id)) : ?> برای <?= casting_user_uses_actor_portrait_set($user_id) ? 'عکس‌ها' : 'عکس پروفایل' ?> به <a href="profile-photo.php">ویرایش تصویر</a> بروید.<?php endif; ?> رمز عبور را از <a href="change-password.php">تنظیمات</a> عوض کنید.</p>

  <form class="form" method="post" action="edit-profile.php#edit-profile" enctype="multipart/form-data" data-loading data-talent-profile-toggle>
    <?php wp_nonce_field('casting_profile'); ?>

    <h3 class="panel-section-title" id="account-email">اطلاعات حساب</h3>
    <div class="field">
      <label for="name">نام و نام خانوادگی <span class="req-mark">*</span></label>
      <input id="name" name="name" type="text" required autocomplete="name" value="<?= casting_e($profile['name'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="username_display">نام کاربری</label>
      <input id="username_display" type="text" value="<?= casting_e($profile['username'] ?? '') ?>" readonly disabled>
      <p class="field-hint">نام کاربری برای ورود ثابت است و قابل تغییر نیست.</p>
    </div>
    <div class="field">
      <label for="email">ایمیل <span class="req-mark">*</span></label>
      <input id="email" name="email" type="email" required autocomplete="email" value="<?= casting_e($profile['email'] ?? '') ?>">
      <p class="field-hint">برای ورود، اعلان‌ها و بازیابی رمز. برای دیگر اعضا نمایش داده نمی‌شود. می‌توانید از <a href="change-email.php">تغییر ایمیل</a> هم استفاده کنید.</p>
    </div>

    <div class="form-grid">
      <div class="field">
        <label for="mobile">موبایل <span class="req-mark">*</span></label>
        <input id="mobile" name="mobile" type="tel" required inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($profile['mobile'] ?? '') ?>" placeholder="09121234567">
        <p class="field-hint">فقط خودتان و مدیران اصلی سایت این شماره را می‌بینند. برای تغییر با تأیید پیامک به <a href="change-phone.php">تغییر شماره تلفن</a> بروید.</p>
      </div>
      <div class="field">
        <label for="phone">تلفن ثابت</label>
        <input id="phone" name="phone" type="tel" inputmode="numeric" value="<?= casting_e($profile['phone'] ?? '') ?>" placeholder="02112345678">
        <p class="field-hint">برای دیگر اعضا نمایش داده نمی‌شود.</p>
      </div>
    </div>
    <?php casting_render_optional_mobile2_field((string) ($profile['mobile2'] ?? '')); ?>

    <?php casting_render_activity_fields($profile['activities'] ?? [], true, $user_id); ?>

    <?php casting_render_jalali_birthday_fields($profile['birthdate'], true); ?>
    <div class="field">
      <label for="age_display">سن (خودکار از تاریخ تولد)</label>
      <select id="age_display" data-age-output data-age-plus="<?= (int) casting_body_metric_plus_value('age') ?>" disabled aria-live="polite">
        <option value="">بعد از انتخاب تاریخ پر می‌شود</option>
        <?php foreach (casting_body_metric_options('age') as $opt) : ?>
          <option value="<?= casting_e($opt['value']) ?>" <?= casting_body_metric_select_value('age', (string) ($profile['age'] ?? '')) === $opt['value'] ? 'selected' : '' ?>><?= casting_e($opt['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="age" value="<?= casting_e($profile['age']) ?>">
    </div>

    <fieldset class="field">
      <legend>جنسیت <span class="req-mark">*</span></legend>
      <div class="role-grid role-grid-2">
        <?php foreach (casting_gender_labels() as $key => $label) : ?>
          <label class="role-option">
            <input type="radio" name="gender" value="<?= casting_e($key) ?>" <?= $profile['gender'] === $key ? 'checked' : '' ?> required>
            <span><?= casting_e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="field" data-talent-profile-field<?= $talent_hidden ?>>
      <legend>رنگ پوست <span class="req-mark">*</span></legend>
      <div class="role-grid role-grid-3">
        <?php foreach (casting_look_labels() as $key => $label) : ?>
          <label class="role-option">
            <input type="radio" name="look" value="<?= casting_e($key) ?>" <?= $profile['look'] === $key ? 'checked' : '' ?><?= $hide_talent_profile ? '' : ' required' ?>>
            <span><?= casting_e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div data-talent-profile-field<?= $talent_hidden ?>>
    <?php casting_render_talent_trait_fields([
        'eye_color' => (string) ($profile['eye_color'] ?? ''),
        'hair_color' => (string) ($profile['hair_color'] ?? ''),
        'accent' => (string) ($profile['accent'] ?? ''),
        'accent_other' => (string) ($profile['accent_other'] ?? ''),
        'apparent_age_range' => (string) ($profile['apparent_age_range'] ?? ''),
    ]); ?>
    </div>

    <div class="form-grid" data-talent-profile-field<?= $talent_hidden ?>>
      <?php $need_body = casting_activities_need_body_metrics($profile['activities'] ?? []); ?>
      <div class="field">
        <label for="height">قد (سانتی‌متر)<?= $need_body ? ' <span class="req-mark">*</span>' : '' ?></label>
        <?php casting_render_body_metric_select('height', 'height', 'height', (string) ($profile['height'] ?? ''), 'انتخاب کنید', $need_body); ?>
        <p class="field-hint">برای بازیگری الزامی است</p>
      </div>
      <div class="field">
        <label for="weight">وزن (کیلوگرم)<?= $need_body ? ' <span class="req-mark">*</span>' : '' ?></label>
        <?php casting_render_body_metric_select('weight', 'weight', 'weight', (string) ($profile['weight'] ?? ''), 'انتخاب کنید', $need_body); ?>
        <p class="field-hint">برای بازیگری الزامی است</p>
      </div>
    </div>

    <div data-talent-profile-field<?= $talent_hidden ?>>
    <?php casting_render_health_fields(
        (string) ($profile['health_well'] ?? 'healthy'),
        (string) ($profile['health_status'] ?? ''),
        !$hide_talent_profile
    ); ?>
    </div>

    <?php casting_render_location_fields((string) ($profile['province'] ?? ''), (string) ($profile['city'] ?? ''), '', true, 'form-grid', false); ?>

    <?php
    $artistic = $profile['artistic_membership'] ?? ['has' => '', 'orgs' => [], 'other_items' => []];
    casting_render_artistic_membership_fields(
        (string) ($artistic['has'] ?? ''),
        is_array($artistic['orgs'] ?? null) ? $artistic['orgs'] : [],
        is_array($artistic['other_items'] ?? null) ? $artistic['other_items'] : []
    );
    ?>

    <div class="form-grid">
      <div class="field">
        <label for="activity_license">دارای پروانه فعالیت <span class="req-mark">*</span></label>
        <select id="activity_license" name="activity_license" required>
          <option value="">انتخاب کنید</option>
          <?php foreach (casting_yes_no_labels() as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= ($profile['activity_license'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="experience">سابقه فعالیت (سال) <span class="req-mark">*</span></label>
        <input id="experience" name="experience" type="number" min="0" max="60" required value="<?= casting_e($profile['experience'] !== '' ? $profile['experience'] : '0') ?>">
      </div>
      <div class="field" data-talent-profile-field<?= $talent_hidden ?>>
        <label for="availability">وضعیت آمادگی برای همکاری <span class="req-mark">*</span></label>
        <select id="availability" name="availability"<?= $hide_talent_profile ? '' : ' required' ?>>
          <option value="">انتخاب کنید</option>
          <?php foreach (casting_availability_labels() as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= ($profile['availability'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div data-talent-profile-field<?= $talent_hidden ?>>
    <?php casting_render_language_fields($profile['language_items'] ?? []); ?>
    </div>
    <div data-talent-profile-field<?= $talent_hidden ?>>
    <?php casting_render_skill_fields($profile['skill_items'] ?? [], (string) ($profile['skills_other'] ?? '')); ?>
    </div>
    <?php casting_render_profile_work_sections($profile); ?>

    <div class="field">
      <label for="work_history">توضیح بیشتر درباره سابقه کاری (اختیاری)</label>
      <textarea id="work_history" name="work_history" rows="2"><?= casting_e($profile['work_history']) ?></textarea>
    </div>

    <?php casting_render_award_fields($profile['award_items'] ?? []); ?>

    <?php casting_render_education_fields($profile['education_items'] ?? []); ?>

    <div class="field">
      <label for="education">توضیح بیشتر درباره تحصیل (اختیاری)</label>
      <textarea id="education" name="education" rows="2"><?= casting_e($profile['education']) ?></textarea>
    </div>

    <div class="field">
      <label for="bio">درباره من</label>
      <textarea id="bio" name="bio" rows="3"><?= casting_e($profile['bio']) ?></textarea>
    </div>

    <div class="field" data-talent-profile-field<?= $talent_hidden ?> data-file-preview-card>
      <label for="video">آپلود ویدیو معرفی</label>
      <div class="video-preview-frame" data-file-preview-frame<?= $profile['video_file_url'] === '' ? ' hidden' : '' ?>>
        <?php if ($profile['video_file_url'] !== '') : ?>
          <video controls playsinline preload="metadata" data-file-preview-video src="<?= casting_e($profile['video_file_url']) ?>"></video>
        <?php else : ?>
          <video controls playsinline preload="metadata" data-file-preview-video></video>
        <?php endif; ?>
      </div>
      <input id="video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime" data-file-preview-input data-file-preview-kind="video" data-upload-kind="video" data-max-bytes="<?= (int) casting_upload_max_bytes('video') ?>">
      <p class="field-hint">MP4 / WebM / MOV — حداکثر <?= casting_e(casting_upload_max_label_fa('video')) ?></p>
      <?php if ($profile['video_file_url'] !== '') : ?>
        <p class="field-hint"><a href="<?= casting_e($profile['video_file_url']) ?>">ویدیو فعلی</a></p>
      <?php endif; ?>
    </div>

    <div class="field" data-talent-profile-field<?= $talent_hidden ?>>
      <label for="video_url">یا لینک ویدیو (آپارات / یوتیوب)</label>
      <input id="video_url" name="video_url" type="url" placeholder="https://" value="<?= casting_e($profile['video_url']) ?>">
    </div>

    <label class="check-row">
      <input type="checkbox" name="visible" value="1" <?= !empty($profile['visible']) ? 'checked' : '' ?>>
      <span>پروفایل برای کارفرماها قابل مشاهده باشد</span>
    </label>

    <button class="btn btn-primary" type="submit">ذخیره پروفایل</button>
  </form>
  </div>
<?php if ($open) : ?>
</section>
<?php else : ?>
</details>
<?php endif; ?>
    <?php
}
