<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/opportunities.php';
require_once __DIR__ . '/includes/chat-rules.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_opportunities_ensure_tables();

$tab = ((string) ($_GET['tab'] ?? 'open')) === 'mine' ? 'mine' : 'open';
$error = '';
$open_id = max(0, (int) ($_GET['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_opportunity_apply')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $action = sanitize_key((string) ($_POST['opp_action'] ?? 'apply'));
        $oid = max(0, (int) ($_POST['opportunity_id'] ?? 0));
        if ($action === 'withdraw') {
            $result = casting_opportunity_withdraw($user_id, $oid);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', 'اپلای لغو شد.');
                casting_redirect('opportunities.php?tab=mine');
            }
        } else {
            $result = casting_opportunity_apply($user_id, $oid, (string) ($_POST['note'] ?? ''));
            if (!$result['ok']) {
                $error = $result['error'];
                $open_id = $oid;
            } else {
                casting_set_flash('success', 'اپلای شما ثبت شد.');
                casting_redirect('opportunities.php?tab=mine');
            }
        }
    }
}

$open_list = casting_opportunities_list_open(40);
$my_apps = casting_opportunity_list_my_applications($user_id, 40);
$app_labels = casting_opportunity_application_status_labels();

casting_render_panel_start('فرصت‌ها', 'opportunities');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <?php casting_render_panel_heading('فرصت‌ها و فراخوان‌های باز'); ?>
  <p class="meta">فراخوان‌های عمومی را ببینید و اپلای کنید — مثل بورد فرصت‌های کستینگ.</p>

  <div class="admin-tabs" role="tablist">
    <a class="admin-tab<?= $tab === 'open' ? ' is-active' : '' ?>" href="opportunities.php?tab=open">فراخوان‌های باز (<?= count($open_list) ?>)</a>
    <a class="admin-tab<?= $tab === 'mine' ? ' is-active' : '' ?>" href="opportunities.php?tab=mine">اپلای‌های من (<?= count($my_apps) ?>)</a>
  </div>

  <?php if ($tab === 'mine') : ?>
    <?php if ($my_apps === []) : ?>
      <p class="empty-state">هنوز اپلایی ثبت نکرده‌اید. از تب «فراخوان‌های باز» یکی را انتخاب کنید.</p>
    <?php else : ?>
      <div class="home-opportunity-list">
        <?php foreach ($my_apps as $app) :
            $oid = (int) ($app['opportunity_id'] ?? 0);
            $st = (string) ($app['status'] ?? 'pending');
            $director = get_user_by('id', (int) ($app['director_id'] ?? 0));
            ?>
          <article class="home-opportunity-card">
            <div class="home-opportunity-body">
              <h3><?= casting_e((string) ($app['opp_title'] ?? 'فراخوان')) ?></h3>
              <p class="meta">
                <?= $director ? casting_e((string) $director->display_name) : 'کارگردان' ?>
                <?php if (!empty($app['project_type'])) : ?> · <?= casting_e((string) $app['project_type']) ?><?php endif; ?>
                <?php if (!empty($app['role_title'])) : ?> · <?= casting_e((string) $app['role_title']) ?><?php endif; ?>
                <?php if (!empty($app['location'])) : ?> · <?= casting_e((string) $app['location']) ?><?php endif; ?>
              </p>
              <p class="home-opportunity-status">
                <?= casting_e($app_labels[$st] ?? $st) ?>
                · <?= casting_e(casting_opportunity_format_date((string) ($app['created_at'] ?? ''))) ?>
                <?php if ((string) ($app['opp_status'] ?? '') !== 'open') : ?> · فراخوان بسته شده<?php endif; ?>
              </p>
              <?php if (trim((string) ($app['note'] ?? '')) !== '') : ?>
                <p class="meta"><?= nl2br(casting_e((string) $app['note'])) ?></p>
              <?php endif; ?>
            </div>
            <?php if ($st === 'pending') : ?>
              <div class="home-opportunity-actions">
                <form method="post" action="opportunities.php?tab=mine" onsubmit="return confirm('اپلای لغو شود؟');">
                  <?php wp_nonce_field('casting_opportunity_apply'); ?>
                  <input type="hidden" name="opp_action" value="withdraw">
                  <input type="hidden" name="opportunity_id" value="<?= $oid ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">انصراف</button>
                </form>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else : ?>
    <?php if ($open_list === []) : ?>
      <p class="empty-state">فعلاً فراخوان بازی در فید عمومی نیست.</p>
    <?php else : ?>
      <div class="home-opportunity-list">
        <?php foreach ($open_list as $op) :
            $oid = (int) ($op['id'] ?? 0);
            $director = get_user_by('id', (int) ($op['director_id'] ?? 0));
            $mine = casting_opportunity_get_application($oid, $user_id);
            $already = $mine && (string) ($mine['status'] ?? '') !== 'withdrawn';
            $is_own = (int) ($op['director_id'] ?? 0) === $user_id;
            $expanded = $open_id === $oid;
            ?>
          <article class="home-opportunity-card<?= $expanded ? ' is-unread' : '' ?>" id="opp-<?= $oid ?>">
            <div class="home-opportunity-body">
              <h3><?= casting_e((string) ($op['title'] ?? 'فراخوان')) ?></h3>
              <p class="meta">
                <?php if ($director) : ?>
                  <button type="button" class="link-button" data-member-preview="<?= (int) $director->ID ?>"><?= casting_e((string) $director->display_name) ?></button>
                <?php else : ?>کارگردان<?php endif; ?>
                <?php if (!empty($op['project_type'])) : ?> · <?= casting_e((string) $op['project_type']) ?><?php endif; ?>
                <?php if (!empty($op['role_title'])) : ?> · <?= casting_e((string) $op['role_title']) ?><?php endif; ?>
                <?php if (!empty($op['location'])) : ?> · <?= casting_e((string) $op['location']) ?><?php endif; ?>
                · <?= casting_e(casting_opportunity_format_date((string) ($op['created_at'] ?? ''))) ?>
              </p>
              <p><?= nl2br(casting_e((string) ($op['message'] ?? ''))) ?></p>
              <?php if ($already) : ?>
                <p class="home-opportunity-status">اپلای شما: <?= casting_e($app_labels[(string) ($mine['status'] ?? 'pending')] ?? 'ثبت‌شده') ?></p>
              <?php endif; ?>
            </div>
            <div class="home-opportunity-actions">
              <?php if ($is_own) : ?>
                <a class="btn btn-ghost btn-sm" href="<?= casting_e(casting_url('director-desk.php?project=' . (int) ($op['project_id'] ?? 0) . '&opp=' . $oid)) ?>">متقاضیان</a>
              <?php elseif ($already) : ?>
                <a class="btn btn-ghost btn-sm" href="opportunities.php?tab=mine">مشاهده اپلای</a>
              <?php elseif ($expanded) : ?>
                <form class="form" method="post" action="opportunities.php?tab=open&amp;id=<?= $oid ?>#opp-<?= $oid ?>">
                  <?php wp_nonce_field('casting_opportunity_apply'); ?>
                  <input type="hidden" name="opp_action" value="apply">
                  <input type="hidden" name="opportunity_id" value="<?= $oid ?>">
                  <div class="field">
                    <label for="note-<?= $oid ?>">یادداشت کوتاه (اختیاری)</label>
                    <textarea id="note-<?= $oid ?>" name="note" rows="3" maxlength="1000" placeholder="چرا مناسب این نقش هستید…"></textarea>
                  </div>
                  <button class="btn btn-primary btn-sm" type="submit">ارسال اپلای</button>
                  <a class="btn btn-ghost btn-sm" href="opportunities.php?tab=open">انصراف</a>
                </form>
              <?php else : ?>
                <a class="btn btn-primary btn-sm" href="opportunities.php?tab=open&amp;id=<?= $oid ?>#opp-<?= $oid ?>">اپلای</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
