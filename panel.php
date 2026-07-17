<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/panel-profile.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$role = casting_get_user_role($user_id);
$profile = casting_get_profile($user_id);
$complete = casting_profile_complete($profile);
$premium = casting_user_is_premium($user_id);
$profile_error = '';
$profile_success = '';

if ($role === 'talent' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['decision'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_respond_request')) {
        casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
    } else {
        $result = casting_respond_to_request(
            $user_id,
            (string) $_POST['request_id'],
            (string) $_POST['decision'],
            (string) ($_POST['reply'] ?? '')
        );
        casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? ($result['status'] === 'accepted' ? 'درخواست قبول شد.' : 'درخواست رد شد.')
            : $result['error']);
    }
    casting_redirect('panel.php#requests');
}

$profile_post = casting_process_profile_post($user_id);
if ($profile_post['error'] !== '') {
    $profile_error = $profile_post['error'];
}
if ($profile_post['success'] !== '') {
    $profile_success = $profile_post['success'];
}
if ($profile_post['profile'] !== null) {
    $profile = $profile_post['profile'];
    $complete = casting_profile_complete($profile);
}

$requests = $role === 'talent' ? casting_get_talent_requests($user_id) : [];
$sent = casting_is_employer_role($role) ? casting_get_employer_sent_requests($user_id) : [];

casting_render_panel_start('پنل کاربری', 'panel');
if (isset($_GET['welcome'])) {
    echo '<div class="flash flash-success" role="alert">ثبت‌نام و ورود با موفقیت انجام شد.</div>';
}
if ($profile_error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($profile_error) . '</div>';
}
if ($profile_success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($profile_success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-welcome">
  <span class="chip"><?= casting_e(casting_role_label($role)) ?><?php if ($premium) : ?> · ویژه<?php endif; ?></span>
  <h1>سلام، <?= casting_e($user->display_name) ?></h1>
  <?php if (!$complete) : ?>
    <p class="meta">پروفایلتان کامل نیست. برای دیده‌شدن بهتر، اطلاعات و عکس را تکمیل کنید.</p>
  <?php else : ?>
    <p class="meta">پروفایل آماده است<?= $profile['visible'] ? ' و قابل مشاهده است' : '؛ فعلاً مخفی است' ?>.</p>
  <?php endif; ?>
  <?php if ($premium) : ?>
    <p class="meta">اشتراک ویژه تا <?= casting_e(casting_premium_until_label($user_id)) ?> فعال است.</p>
  <?php endif; ?>
  <div class="cta-row">
    <a class="btn btn-ghost" href="#completion">تکمیل پروفایل</a>
    <a class="btn btn-primary" href="#edit-profile">ویرایش اطلاعات</a>
    <a class="btn btn-ghost" href="chat.php">پیام‌ها</a>
  </div>
</section>

<?php casting_render_panel_completion_card($profile); ?>

<div class="panel-profile-stack" id="profile">
  <h2 class="panel-section-title panel-stack-heading">پروفایل من</h2>
  <?php casting_render_member_profile_view($user_id, $user_id, true); ?>
</div>

<?php casting_render_profile_edit_form($user_id, $profile, $profile_error !== '' || $profile_success !== '' || !$complete || isset($_GET['edit'])); ?>

<?php if ($role === 'talent') : ?>
<section class="dash-card" id="requests">
  <h2 class="panel-section-title">درخواست‌های همکاری</h2>
  <?php if (!$requests) : ?>
    <p class="meta">هنوز درخواستی نیامده است.</p>
  <?php else : ?>
    <div class="request-list">
      <?php foreach ($requests as $req) :
          $status = (string) ($req['status'] ?? 'pending');
          if ($status === 'new') {
              $status = 'pending';
          }
          $pending = $status === 'pending';
          ?>
        <article class="request-item status-<?= casting_e($status) ?>">
          <header>
            <strong><?= casting_e((string) ($req['employer'] ?? 'کارفرما')) ?></strong>
            <span><?= casting_e((string) ($req['employer_role'] ?? '')) ?></span>
            <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
            <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
          </header>
          <?php if (!empty($req['project'])) : ?>
            <p><strong>پروژه:</strong> <?= casting_e((string) $req['project']) ?></p>
          <?php endif; ?>
          <p><?= nl2br(casting_e((string) ($req['message'] ?? ''))) ?></p>
          <?php if ($pending) : ?>
            <form class="form request-reply-form" method="post" action="panel.php#requests">
              <?php wp_nonce_field('casting_respond_request'); ?>
              <input type="hidden" name="request_id" value="<?= casting_e((string) ($req['id'] ?? '')) ?>">
              <div class="field">
                <label for="reply-<?= casting_e((string) ($req['id'] ?? '')) ?>">نظر شما (اختیاری)</label>
                <textarea id="reply-<?= casting_e((string) ($req['id'] ?? '')) ?>" name="reply" rows="3" maxlength="2000"></textarea>
              </div>
              <div class="cta-row">
                <button class="btn btn-primary" type="submit" name="decision" value="accepted">قبول</button>
                <button class="btn btn-reject" type="submit" name="decision" value="rejected">رد</button>
                <?php if (!empty($req['employer_id']) && casting_can_users_chat($user_id, (int) $req['employer_id'])['ok']) : ?>
                  <a class="btn btn-ghost" href="chat.php?with=<?= (int) $req['employer_id'] ?>">پاسخ در پیام</a>
                <?php endif; ?>
              </div>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (casting_is_employer_role($role)) : ?>
<section class="dash-card">
  <h2 class="panel-section-title">درخواست‌های ارسال‌شده</h2>
  <?php if (!$sent) : ?>
    <p class="meta">هنوز درخواستی نفرستاده‌اید. از <a href="search-users.php">جستجوی کاربران</a> شروع کنید.</p>
  <?php else : ?>
    <div class="request-list">
      <?php foreach ($sent as $req) : ?>
        <article class="request-item status-<?= casting_e((string) ($req['status'] ?? 'pending')) ?>">
          <header>
            <strong><?= casting_e((string) ($req['talent_name'] ?? 'کاربر')) ?></strong>
            <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
            <span class="req-status"><?= casting_e(casting_request_status_label((string) ($req['status'] ?? 'pending'))) ?></span>
          </header>
          <p><?= nl2br(casting_e((string) ($req['message'] ?? ''))) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php casting_render_panel_end(); ?>
