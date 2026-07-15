<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/visitors.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/chat-rules.php';
require_once __DIR__ . '/includes/panel.php';

$viewer = casting_require_casting_user();
$viewer_id = (int) $viewer->ID;
$id = (int) ($_GET['id'] ?? 0);
$member = $id > 0 ? get_user_by('id', $id) : false;
$member_role = $member ? casting_get_user_role((int) $member->ID) : '';

if (!$member || $member_role === '') {
    casting_set_flash('error', 'کاربر پیدا نشد.');
    casting_redirect('search-users.php');
}

$is_self = $viewer_id === $id;
if (!$is_self) {
    casting_record_profile_visit($id, $viewer_id);
}

$profile = casting_get_profile($id);
if (!$profile['visible'] && !$is_self) {
    casting_set_flash('error', 'این پروفایل فعلاً قابل مشاهده نیست.');
    casting_redirect('search-users.php');
}

$chat_allow = casting_can_users_chat($viewer_id, $id);
$is_blocked = casting_is_blocked($viewer_id, $id);
$error = '';
$project = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['block_action'], $_POST['block_id'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_block')) {
            $error = 'درخواست نامعتبر است.';
        } else {
            $target = (int) $_POST['block_id'];
            if ((string) $_POST['block_action'] === 'block') {
                $res = casting_block_user($viewer_id, $target);
            } else {
                $res = casting_unblock_user($viewer_id, $target);
            }
            casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'انجام شد.' : $res['error']);
            casting_redirect('member.php?id=' . $target);
        }
    } elseif (!$is_self && casting_is_employer_role(casting_get_user_role($viewer_id)) && $member_role === 'talent') {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_request_' . $id)) {
            $error = 'نشست منقضی شده. دوباره تلاش کنید.';
        } else {
            $project = (string) ($_POST['project'] ?? '');
            $message = (string) ($_POST['message'] ?? '');
            $result = casting_send_talent_request($viewer_id, $id, $message, $project);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', !empty($result['warning']) ? $result['warning'] : 'درخواست ارسال شد.');
                casting_redirect('member.php?id=' . $id);
            }
        }
    }
}

$genders = casting_gender_labels();
$provinces = casting_province_labels();
$yes_no = casting_yes_no_labels();
$availability_labels = casting_availability_labels();
$language_levels = casting_language_level_labels();
$skills_text = casting_format_skill_labels($profile['skill_items'] ?? [], (string) ($profile['skills_other'] ?? ''));
$premium = casting_user_is_premium($id);

casting_render_panel_start($member->display_name, 'myprofile');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card profile-view">
  <a class="back-link" href="<?= $is_self ? 'panel.php' : 'search-users.php' ?>">← بازگشت</a>

  <div class="profile-hero">
    <div class="profile-photo">
      <?php if ($profile['photo_full'] !== '' || $profile['photo_url'] !== '') : ?>
        <img src="<?= casting_e($profile['photo_full'] !== '' ? $profile['photo_full'] : $profile['photo_url']) ?>" alt="">
      <?php else : ?>
        <div class="photo-placeholder tall">بدون عکس</div>
      <?php endif; ?>
    </div>
    <div class="profile-info">
      <span class="chip"><?= casting_e(casting_role_label($member_role)) ?><?php if ($premium) : ?> · ویژه<?php endif; ?></span>
      <h1><?= casting_e($member->display_name) ?><?php if ($is_self) : ?> <span class="meta">(پروفایل شما)</span><?php endif; ?></h1>
      <div class="cta-row" style="margin:0.75rem 0 1rem">
        <?php if (!$is_self && $chat_allow['ok']) : ?>
          <a class="btn btn-primary" href="chat.php?with=<?= $id ?>">پیام</a>
        <?php endif; ?>
        <?php if (!$is_self) : ?>
          <?php if ($is_blocked) : ?>
            <form method="post" action="member.php?id=<?= $id ?>" style="display:inline">
              <?php wp_nonce_field('casting_block'); ?>
              <input type="hidden" name="block_id" value="<?= $id ?>">
              <button class="btn btn-ghost" type="submit" name="block_action" value="unblock">رفع بلاک</button>
            </form>
          <?php else : ?>
            <form method="post" action="member.php?id=<?= $id ?>" style="display:inline">
              <?php wp_nonce_field('casting_block'); ?>
              <input type="hidden" name="block_id" value="<?= $id ?>">
              <button class="btn btn-reject" type="submit" name="block_action" value="block">بلاک</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <ul class="info-list">
        <li><strong>سن:</strong> <?= casting_e($profile['age'] !== '' ? $profile['age'] . ' سال' : '—') ?></li>
        <li><strong>جنسیت:</strong> <?= casting_e($genders[$profile['gender']] ?? '—') ?></li>
        <li><strong>قد:</strong> <?= casting_e($profile['height'] !== '' ? $profile['height'] . ' سانتی‌متر' : '—') ?></li>
        <li><strong>وزن:</strong> <?= casting_e(($profile['weight'] ?? '') !== '' ? $profile['weight'] . ' کیلوگرم' : '—') ?></li>
        <li><strong>وضعیت سلامت:</strong> <?= casting_e(($profile['health_status'] ?? '') !== '' ? $profile['health_status'] : '—') ?></li>
        <li><strong>استان:</strong> <?= casting_e($provinces[$profile['province'] ?? ''] ?? '—') ?></li>
        <li><strong>شهر:</strong> <?= casting_e($profile['city'] !== '' ? $profile['city'] : '—') ?></li>
        <li><strong>وضعیت آمادگی:</strong> <?= casting_e($availability_labels[$profile['availability'] ?? ''] ?? '—') ?></li>
        <li><strong>تشکل‌های هنری:</strong> <?= casting_e(casting_format_artistic_membership($profile['artistic_membership'] ?? [])) ?></li>
        <li><strong>مهارت‌ها:</strong> <?= casting_e($skills_text !== '' ? $skills_text : '—') ?></li>
      </ul>
      <?php
      $activity_groups = casting_group_activities_for_display($profile['activities'] ?? []);
      if ($activity_groups) :
          ?>
        <div class="activity-display">
          <h2>نوع فعالیت</h2>
          <?php foreach ($activity_groups as $group) : ?>
            <p><strong><?= casting_e($group['category']) ?>:</strong> <?= casting_e(implode('، ', $group['items'])) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($profile['bio'] !== '') : ?>
    <div class="bio-block"><h2>درباره</h2><p><?= nl2br(casting_e($profile['bio'])) ?></p></div>
  <?php endif; ?>

  <?php if (!$is_self && casting_is_employer_role(casting_get_user_role($viewer_id)) && $member_role === 'talent') : ?>
    <div class="bio-block request-box" id="request-box">
      <h2>ارسال درخواست همکاری</h2>
      <form class="form" method="post" action="member.php?id=<?= $id ?>">
        <?php wp_nonce_field('casting_request_' . $id); ?>
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
<?php casting_render_panel_end(); ?>
