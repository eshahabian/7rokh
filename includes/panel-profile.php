<?php
declare(strict_types=1);

require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/director-workspace.php';
require_once __DIR__ . '/director-desk.php';

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

function casting_render_profile_portraits(array $portraits): void
{
    $dims = casting_portrait_display_dimensions();
    ?>
    <div class="profile-portraits">
      <?php foreach (casting_portrait_slots() as $slot => $label) :
          $shot = casting_portrait_shot($portraits, $slot);
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
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_profile')) {
        return $out;
    }

    $video = casting_handle_video_upload($user_id);
    if (!$video['ok']) {
        $out['error'] = $video['error'];
        return $out;
    }

    $save = casting_save_profile($user_id, [
        'birthdate'           => casting_birthdate_from_jalali_post($_POST) ?? '',
        'age'                 => $_POST['age'] ?? '',
        'gender'              => $_POST['gender'] ?? '',
        'email'               => $_POST['email'] ?? '',
        'mobile'              => $_POST['mobile'] ?? '',
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
function casting_profile_completion_items(array $profile): array
{
    $items = [];
    $hints = casting_portrait_slot_hints();
    $hide_talent = casting_profile_hides_talent_fields($profile['activities'] ?? []);

    foreach (casting_portrait_slots() as $slot => $label) {
        if ($hide_talent) {
            break;
        }
        $shot = casting_portrait_shot($profile['portraits'] ?? [], $slot);
        $done = (bool) ($shot['id'] > 0 || $shot['full'] !== '' || $shot['url'] !== '');
        $items[] = [
            'label' => 'عکس ' . $label,
            'done'  => $done,
            'href'  => 'profile-photo.php',
            'hint'  => $hints[$slot] ?? '',
        ];
    }

    $checks = [
        ['label' => 'تاریخ تولد', 'done' => (bool) (($profile['birthdate'] ?? '') !== '' || ($profile['age'] ?? '') !== ''), 'href' => '#edit-profile', 'hint' => ''],
        ['label' => 'جنسیت', 'done' => (bool) (($profile['gender'] ?? '') !== ''), 'href' => '#edit-profile', 'hint' => ''],
        ['label' => 'ایمیل', 'done' => (bool) is_email((string) ($profile['email'] ?? '')), 'href' => '#edit-profile', 'hint' => ''],
        ['label' => 'موبایل', 'done' => (bool) (($profile['mobile'] ?? '') !== ''), 'href' => '#edit-profile', 'hint' => ''],
        ['label' => 'استان و شهر', 'done' => (bool) (($profile['province'] ?? '') !== '' && ($profile['city'] ?? '') !== ''), 'href' => '#edit-profile', 'hint' => ''],
    ];
    if (!$hide_talent) {
        $checks[] = ['label' => 'قد و وزن', 'done' => (bool) (($profile['height'] ?? '') !== '' && ($profile['weight'] ?? '') !== ''), 'href' => '#edit-profile', 'hint' => ''];
    }
    $checks[] = ['label' => 'نوع فعالیت', 'done' => (bool) !empty($profile['activities']), 'href' => '#edit-profile', 'hint' => ''];
    $checks[] = ['label' => 'درباره من', 'done' => (bool) (($profile['bio'] ?? '') !== ''), 'href' => '#edit-profile', 'hint' => ''];

    return array_merge($items, $checks);
}

function casting_panel_missing_label(string $value, string $edit_href = '#edit-profile'): string
{
    if (trim($value) === '' || $value === '—') {
        return '<span class="info-missing">تکمیل نشده</span>';
    }

    return casting_e($value);
}

function casting_render_panel_completion_card(array $profile): void
{
    $items = casting_profile_completion_items($profile);
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
      <p class="meta panel-completion-meta">
        <?= $done_count === $total
            ? 'همه موارد اصلی تکمیل شده است.'
            : ($total - $done_count) . ' مورد هنوز تکمیل نشده — موارد خالی را پر کنید تا پروفایل بهتر دیده شود.' ?>
      </p>
    </div>
    <div class="panel-completion-meter" aria-label="پیشرفت تکمیل پروفایل">
      <span class="panel-completion-value"><?= $percent ?>٪</span>
      <span class="panel-completion-bar" style="--progress: <?= $percent ?>"></span>
    </div>
  </div>

  <div class="panel-photo-slots">
    <?php foreach (casting_portrait_slots() as $slot => $label) :
        $shot = casting_portrait_shot($profile['portraits'] ?? [], $slot);
        $src = $shot['url'] !== '' ? $shot['url'] : $shot['full'];
        $hint = casting_portrait_slot_hints()[$slot] ?? '';
        ?>
      <a class="panel-photo-slot<?= $src === '' ? ' is-empty' : '' ?>" href="profile-photo.php">
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
    $genders = casting_gender_labels();
    $provinces = casting_province_labels();
    $availability_labels = casting_availability_labels();
    $eye_colors = casting_eye_color_labels();
    $hair_colors = casting_hair_color_labels();
    $age_ranges = casting_age_range_options();
    $skills_text = casting_format_skill_labels($profile['skill_items'] ?? [], (string) ($profile['skills_other'] ?? ''));
    $premium = casting_user_is_premium($member_id);
    $viewer_role = casting_get_user_role($viewer_id);
    $chat_allow = !$is_self ? casting_can_users_chat($viewer_id, $member_id) : ['ok' => false];
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
    <a class="back-link" href="<?= $is_self ? 'panel.php' : 'search-users.php' ?>">← بازگشت</a>
  <?php endif; ?>

  <div class="profile-hero<?= $embedded ? ' profile-hero--panel' : '' ?>">
    <?php if (!$embedded) : ?>
    <div class="profile-portraits-wrap<?= $director_section_class('portraits') ?>">
      <?php if ($show_director_tools && !empty($director_workspace['viewed'])) : ?>
        <?php casting_render_director_viewed_badge(true, 'director-viewed-badge--profile'); ?>
      <?php endif; ?>
      <?php casting_render_profile_portraits($profile['portraits']); ?>
    </div>
    <?php endif; ?>
    <div class="profile-info">
      <span class="chip"><?= casting_e(casting_user_profile_chip_label($member_id, $viewer_id)) ?><?php if ($premium) : ?> · ویژه<?php endif; ?></span>
      <h2 class="panel-section-title"><?= casting_e($member->display_name) ?><?php if ($is_self) : ?> <span class="meta">(پروفایل شما)</span><?php endif; ?></h2>
      <?php if (!$is_self) : ?>
        <div class="cta-row" style="margin:0.75rem 0 1rem">
          <?php if ($chat_allow['ok']) : ?>
            <a class="btn btn-primary" href="chat.php?with=<?= $member_id ?>">پیام</a>
          <?php endif; ?>
          <?php if ($is_blocked) : ?>
            <form method="post" action="member.php?id=<?= $member_id ?>" style="display:inline">
              <?php wp_nonce_field('casting_block'); ?>
              <input type="hidden" name="block_id" value="<?= $member_id ?>">
              <button class="btn btn-ghost" type="submit" name="block_action" value="unblock">رفع بلاک</button>
            </form>
          <?php else : ?>
            <div class="block-user-wrap">
              <?php casting_render_block_user_form('member.php?id=' . $member_id, $member_id); ?>
            </div>
          <?php endif; ?>
        </div>
      <?php elseif ($embedded) : ?>
        <div class="cta-row profile-panel-actions">
          <a class="btn btn-primary" href="profile-photo.php">ویرایش عکس‌ها</a>
          <a class="btn btn-ghost" href="#edit-profile">ویرایش اطلاعات</a>
        </div>
      <?php endif; ?>
      <ul class="info-list<?= $director_section_class('info') ?>">
        <?php if ($is_self && ($profile['membership_number'] ?? '') !== '') : ?>
          <li><strong>شماره عضویت:</strong> <span class="membership-number"><?= casting_e((string) $profile['membership_number']) ?></span></li>
        <?php endif; ?>
        <?php if ($is_self) : ?>
          <li><strong>ایمیل:</strong> <?= $embedded
              ? casting_panel_missing_label(is_email((string) ($profile['email'] ?? '')) ? (string) $profile['email'] : '')
              : casting_e(is_email((string) ($profile['email'] ?? '')) ? (string) $profile['email'] : '—') ?></li>
        <?php endif; ?>
        <li><strong>سن:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($profile['age'] !== '' ? $profile['age'] . ' سال' : '')
            : casting_e($profile['age'] !== '' ? $profile['age'] . ' سال' : '—') ?></li>
        <?php if (!$embedded && ($profile['apparent_age_range'] ?? '') !== '' && isset($age_ranges[$profile['apparent_age_range']])) : ?>
          <li><strong>سن ظاهری:</strong> <?= casting_e($age_ranges[$profile['apparent_age_range']]['label']) ?></li>
        <?php elseif ($embedded && $is_self) : ?>
          <li><strong>سن ظاهری:</strong> <?= casting_panel_missing_label(
              (($profile['apparent_age_range'] ?? '') !== '' && isset($age_ranges[$profile['apparent_age_range']]))
                  ? $age_ranges[$profile['apparent_age_range']]['label']
                  : ''
          ) ?></li>
        <?php endif; ?>
        <li><strong>جنسیت:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($genders[$profile['gender']] ?? '')
            : casting_e($genders[$profile['gender']] ?? '—') ?></li>
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
        <?php if (!$hide_talent_details) : ?>
        <li><strong>قد:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($profile['height'] !== '' ? $profile['height'] . ' سانتی‌متر' : '')
            : casting_e($profile['height'] !== '' ? $profile['height'] . ' سانتی‌متر' : '—') ?></li>
        <li><strong>وزن:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label(($profile['weight'] ?? '') !== '' ? $profile['weight'] . ' کیلوگرم' : '')
            : casting_e(($profile['weight'] ?? '') !== '' ? $profile['weight'] . ' کیلوگرم' : '—') ?></li>
        <?php endif; ?>
        <li><strong>وضعیت سلامت:</strong> <?= casting_e(casting_format_health_display(
            (string) ($profile['health_well'] ?? 'healthy'),
            (string) ($profile['health_status'] ?? '')
        )) ?></li>
        <li><strong>استان:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($provinces[$profile['province'] ?? ''] ?? '')
            : casting_e($provinces[$profile['province'] ?? ''] ?? '—') ?></li>
        <li><strong>شهر:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($profile['city'] !== '' ? $profile['city'] : '')
            : casting_e($profile['city'] !== '' ? $profile['city'] : '—') ?></li>
        <li><strong>وضعیت آمادگی:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($availability_labels[$profile['availability'] ?? ''] ?? '')
            : casting_e($availability_labels[$profile['availability'] ?? ''] ?? '—') ?></li>
        <li><strong>تشکل‌های هنری:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label(casting_format_artistic_membership($profile['artistic_membership'] ?? []))
            : casting_e(casting_format_artistic_membership($profile['artistic_membership'] ?? [])) ?></li>
        <li class="<?= $director_section_class('skills') ?>"><strong>مهارت‌ها:</strong> <?= $embedded && $is_self
            ? casting_panel_missing_label($skills_text !== '' ? $skills_text : '')
            : casting_e($skills_text !== '' ? $skills_text : '—') ?></li>
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

  <?php if ($profile['bio'] !== '') : ?>
    <div class="bio-block<?= $director_section_class('bio') ?>"><h3>درباره</h3><p><?= nl2br(casting_e($profile['bio'])) ?></p></div>
  <?php elseif ($embedded && $is_self) : ?>
    <div class="bio-block bio-block--missing"><h3>درباره</h3><p><?= casting_panel_missing_label('') ?> — <a href="#edit-profile">نوشتن معرفی</a></p></div>
  <?php endif; ?>

  <?php if ($show_director_tools && is_array($director_workspace)) : ?>
    <?php casting_render_director_talent_workspace_panel($viewer_id, $member_id, $director_workspace); ?>
    <?php casting_render_director_desk_talent_panel($viewer_id, $member_id, max(0, (int) ($_GET['role'] ?? 0))); ?>
  <?php endif; ?>

  <?php if (!$is_self && casting_is_employer_role($viewer_role) && $member_role === 'talent') : ?>
    <div class="bio-block request-box" id="request-box">
      <h3>ارسال درخواست همکاری</h3>
      <form class="form" method="post" action="member.php?id=<?= $member_id ?>">
        <?php wp_nonce_field('casting_request_' . $member_id); ?>
        <div class="field">
          <label for="project">نام پروژه / نقش (اختیاری)</label>
          <input id="project" name="project" type="text" value="<?= casting_e($project) ?>">
        </div>
        <div class="field">
          <label for="message">متن درخواست</label>
          <textarea id="message" name="message" rows="4" required maxlength="2000"><?= casting_e($message) ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit">ارسال درخواست</button>
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
    ?>
<details class="dash-card panel-profile-edit panel-edit-details" id="edit-profile"<?= $open ? ' open' : '' ?>>
  <summary class="panel-edit-summary">
    <h2 class="panel-section-title">ویرایش پروفایل</h2>
    <span class="panel-edit-toggle">باز / بسته</span>
  </summary>
  <div class="panel-edit-body">
  <p class="lede">اطلاعات و ویدیو را کامل کنید. برای عکس‌ها به <a href="profile-photo.php">ویرایش تصویر</a> بروید.</p>

  <form class="form" method="post" action="panel.php#edit-profile" enctype="multipart/form-data" data-loading data-talent-profile-toggle>
    <?php wp_nonce_field('casting_profile'); ?>

    <div class="field">
      <label for="email">ایمیل</label>
      <input id="email" name="email" type="email" required autocomplete="email" value="<?= casting_e($profile['email'] ?? '') ?>">
      <p class="field-hint">برای ورود، بازیابی رمز و اعلان‌ها. برای دیگر اعضا نمایش داده نمی‌شود.</p>
    </div>

    <div class="form-grid">
      <div class="field">
        <label for="mobile">موبایل</label>
        <input id="mobile" name="mobile" type="tel" inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($profile['mobile'] ?? '') ?>" placeholder="09121234567">
      </div>
      <div class="field">
        <label for="phone">تلفن ثابت</label>
        <input id="phone" name="phone" type="tel" inputmode="numeric" value="<?= casting_e($profile['phone'] ?? '') ?>" placeholder="02112345678">
      </div>
    </div>

    <?php casting_render_jalali_birthday_fields($profile['birthdate'], false); ?>
    <div class="field">
      <label for="age_display">سن (خودکار)</label>
      <input id="age_display" type="text" readonly data-age-output value="<?= $profile['age'] !== '' ? casting_e($profile['age']) . ' سال' : '' ?>">
      <input type="hidden" name="age" value="<?= casting_e($profile['age']) ?>">
    </div>

    <fieldset class="field">
      <legend>جنسیت</legend>
      <div class="role-grid role-grid-3">
        <?php foreach (casting_gender_labels() as $key => $label) : ?>
          <label class="role-option">
            <input type="radio" name="gender" value="<?= casting_e($key) ?>" <?= $profile['gender'] === $key ? 'checked' : '' ?>>
            <span><?= casting_e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="field" data-talent-profile-field<?= $talent_hidden ?>>
      <legend>رنگ پوست</legend>
      <div class="role-grid role-grid-3">
        <?php foreach (casting_look_labels() as $key => $label) : ?>
          <label class="role-option">
            <input type="radio" name="look" value="<?= casting_e($key) ?>" <?= $profile['look'] === $key ? 'checked' : '' ?>>
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
      <div class="field">
        <label for="height">قد (سانتی‌متر)</label>
        <input id="height" name="height" type="number" min="80" max="230" value="<?= casting_e($profile['height']) ?>">
        <p class="field-hint">برای بازیگران الزامی است</p>
      </div>
      <div class="field">
        <label for="weight">وزن (کیلوگرم)</label>
        <input id="weight" name="weight" type="number" min="20" max="250" value="<?= casting_e($profile['weight'] ?? '') ?>">
        <p class="field-hint">برای بازیگران الزامی است</p>
      </div>
    </div>

    <div data-talent-profile-field<?= $talent_hidden ?>>
    <?php casting_render_health_fields(
        (string) ($profile['health_well'] ?? 'healthy'),
        (string) ($profile['health_status'] ?? ''),
        false
    ); ?>
    </div>

    <?php casting_render_location_fields((string) ($profile['province'] ?? ''), (string) ($profile['city'] ?? ''), '', false); ?>

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
        <label for="activity_license">دارای پروانه فعالیت</label>
        <select id="activity_license" name="activity_license">
          <option value="">انتخاب کنید</option>
          <?php foreach (casting_yes_no_labels() as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= ($profile['activity_license'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="experience">سابقه فعالیت (سال)</label>
        <input id="experience" name="experience" type="number" min="0" max="60" value="<?= casting_e($profile['experience'] !== '' ? $profile['experience'] : '0') ?>">
      </div>
      <div class="field" data-talent-profile-field<?= $talent_hidden ?>>
        <label for="availability">وضعیت آمادگی برای همکاری</label>
        <select id="availability" name="availability">
          <option value="">انتخاب کنید</option>
          <?php foreach (casting_availability_labels() as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= ($profile['availability'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php casting_render_activity_fields($profile['activities'] ?? [], true, $user_id); ?>
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

    <?php casting_render_education_fields($profile['education_items'] ?? []); ?>

    <div class="field">
      <label for="education">توضیح بیشتر درباره تحصیل (اختیاری)</label>
      <textarea id="education" name="education" rows="2"><?= casting_e($profile['education']) ?></textarea>
    </div>

    <div class="field">
      <label for="bio">درباره من</label>
      <textarea id="bio" name="bio" rows="3"><?= casting_e($profile['bio']) ?></textarea>
    </div>

    <div class="field" data-talent-profile-field<?= $talent_hidden ?>>
      <label for="video">آپلود ویدیو معرفی</label>
      <input id="video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime">
      <p class="field-hint">MP4 / WebM / MOV — حداکثر ۴۰ مگابایت</p>
      <?php if ($profile['video_file_url'] !== '') : ?>
        <p class="field-hint"><a href="<?= casting_e($profile['video_file_url']) ?>" target="_blank" rel="noopener">ویدیو فعلی</a></p>
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
</details>
    <?php
}
