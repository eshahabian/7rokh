<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_require_login('employer');
$role = casting_get_user_role((int) $user->ID);
$sent = casting_get_employer_sent_requests((int) $user->ID);

casting_render_head('پنل کارفرما', 'page-dash');
casting_render_header('dash');
if (isset($_GET['welcome'])) {
    echo '<div class="flash flash-success" role="alert">ثبت‌نام و ورود با موفقیت انجام شد.</div>';
}
casting_render_flash();
?>
<main class="wrap dash">
  <section class="dash-card">
    <span class="chip"><?= casting_e(casting_role_label($role)) ?></span>
    <h1>سلام، <?= casting_e($user->display_name) ?></h1>
    <p class="meta">بین هنرجویان با فیلتر سن، شهر، جنسیت و تیپ جستجو کنید.</p>
    <div class="cta-row">
      <a class="btn btn-primary" href="talents.php">مشاهده هنرجویان</a>
      <a class="btn btn-ghost" href="chat.php">تالار گفتگو</a>
      <a class="btn btn-ghost" href="logout.php">خروج</a>
    </div>
  </section>

  <section class="dash-card" style="margin-top:1rem">
    <h2 style="margin:0 0 0.5rem;font-size:1.25rem">درخواست‌های ارسال‌شده</h2>
    <?php if (!$sent) : ?>
      <p class="meta">هنوز درخواستی نفرستاده‌اید.</p>
    <?php else : ?>
      <div class="request-list">
        <?php foreach ($sent as $req) :
            $status = (string) ($req['status'] ?? 'pending');
            if ($status === 'new') {
                $status = 'pending';
            }
            ?>
          <article class="request-item status-<?= casting_e($status) ?>">
            <header>
              <strong><?= casting_e((string) ($req['talent_name'] ?? 'هنرجو')) ?></strong>
              <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
              <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
            </header>
            <?php if (!empty($req['project'])) : ?>
              <p><strong>پروژه:</strong> <?= casting_e((string) $req['project']) ?></p>
            <?php endif; ?>
            <p><?= nl2br(casting_e((string) ($req['message'] ?? ''))) ?></p>
            <?php if (!empty($req['reply'])) : ?>
              <p class="reply-box"><strong>نظر هنرجو:</strong> <?= nl2br(casting_e((string) $req['reply'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($req['replied_at'])) : ?>
              <p class="field-hint">زمان پاسخ: <?= casting_e((string) $req['replied_at']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php casting_render_footer(); ?>
