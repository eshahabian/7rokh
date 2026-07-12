<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_require_login('talent');
$user_id = (int) $user->ID;
$role = casting_get_user_role($user_id);
$profile = casting_get_profile($user_id);
$complete = casting_profile_complete($profile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['decision'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_respond_request')) {
        casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
    } else {
        $result = casting_respond_to_request(
            $user_id,
            (string) $_POST['request_id'],
            (string) $_POST['decision'],
            (string) ($_POST['reply'] ?? '')
        );
        if (!$result['ok']) {
            casting_set_flash('error', $result['error']);
        } else {
            $msg = $result['status'] === 'accepted'
                ? 'درخواست قبول شد و به کارفرما اطلاع داده شد.'
                : 'درخواست رد شد و به کارفرما اطلاع داده شد.';
            casting_set_flash('success', $msg);
        }
    }
    casting_redirect('dashboard-talent.php#requests');
}

$requests = casting_get_talent_requests($user_id);

casting_render_head('پنل هنرجو', 'page-dash');
casting_render_header('dash');
if (isset($_GET['welcome'])) {
    echo '<div class="flash flash-success" role="alert">ثبت‌نام و ورود با موفقیت انجام شد. پروفایلتان را تکمیل کنید.</div>';
}
casting_render_flash();
?>
<main class="wrap dash">
  <section class="dash-card">
    <span class="chip"><?= casting_e(casting_role_label($role)) ?></span>
    <h1>سلام، <?= casting_e($user->display_name) ?></h1>
    <?php if (!$complete) : ?>
      <p class="meta">پروفایلتان کامل نیست. سن، شهر، جنسیت و عکس را اضافه کنید تا کارفرماها شما را ببینند.</p>
    <?php else : ?>
      <p class="meta">پروفایل آماده است<?= $profile['visible'] ? ' و برای کارفرماها قابل مشاهده است' : '؛ فعلاً از دید کارفرماها مخفی است' ?>.</p>
    <?php endif; ?>
    <div class="cta-row">
      <a class="btn btn-primary" href="profile-talent.php"><?= $complete ? 'ویرایش پروفایل' : 'تکمیل پروفایل' ?></a>
      <a class="btn btn-ghost" href="chat.php">تالار گفتگو</a>
      <a class="btn btn-ghost" href="logout.php">خروج</a>
    </div>
  </section>

  <section class="dash-card" id="requests" style="margin-top:1rem">
    <h2 style="margin:0 0 0.5rem;font-size:1.25rem">درخواست‌های همکاری</h2>
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
            <?php if (!empty($req['employer_mail'])) : ?>
              <p class="field-hint">ایمیل کارفرما: <a href="mailto:<?= casting_e((string) $req['employer_mail']) ?>"><?= casting_e((string) $req['employer_mail']) ?></a></p>
            <?php endif; ?>

            <?php if ($pending) : ?>
              <form class="form request-reply-form" method="post" action="dashboard-talent.php#requests">
                <?php wp_nonce_field('casting_respond_request'); ?>
                <input type="hidden" name="request_id" value="<?= casting_e((string) ($req['id'] ?? '')) ?>">
                <div class="field">
                  <label for="reply-<?= casting_e((string) ($req['id'] ?? '')) ?>">نظر شما (اختیاری)</label>
                  <textarea id="reply-<?= casting_e((string) ($req['id'] ?? '')) ?>" name="reply" rows="3" maxlength="2000" placeholder="اگر توضیحی دارید بنویسید…"></textarea>
                </div>
                <div class="cta-row">
                  <button class="btn btn-primary" type="submit" name="decision" value="accepted">قبول می‌کنم</button>
                  <button class="btn btn-reject" type="submit" name="decision" value="rejected">رد می‌کنم</button>
                </div>
              </form>
            <?php else : ?>
              <?php if (!empty($req['reply'])) : ?>
                <p class="reply-box"><strong>نظر شما:</strong> <?= nl2br(casting_e((string) $req['reply'])) ?></p>
              <?php endif; ?>
              <?php if (!empty($req['replied_at'])) : ?>
                <p class="field-hint">زمان پاسخ: <?= casting_e((string) $req['replied_at']) ?></p>
              <?php endif; ?>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php casting_render_footer(); ?>
