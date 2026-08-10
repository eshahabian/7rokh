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
$type_filter = sanitize_key((string) ($_GET['type'] ?? 'all'));
if ($type_filter !== 'all' && !isset(casting_opportunity_filter_type_labels()[$type_filter])) {
    $type_filter = 'all';
}
$error = '';
$open_id = max(0, (int) ($_GET['id'] ?? 0));
$can_admin_delete = casting_user_can_admin_delete_opportunity($user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_key((string) ($_POST['opp_action'] ?? 'apply'));
    if ($action === 'admin_delete') {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_opportunity_admin')) {
            $error = 'درخواست نامعتبر است.';
        } else {
            $oid = max(0, (int) ($_POST['opportunity_id'] ?? 0));
            $result = casting_admin_delete_opportunity($user_id, $oid);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', 'فراخوان حذف شد.');
                casting_redirect('opportunities.php?tab=open');
            }
        }
    } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_opportunity_apply')) {
        $error = 'درخواست نامعتبر است.';
    } else {
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

$open_list_all = casting_opportunities_list_open(40);
$open_list = casting_opportunities_filter_by_type($open_list_all, $type_filter);
$my_apps = casting_opportunity_list_my_applications($user_id, 40);
$app_labels = casting_opportunity_application_status_labels();

casting_render_panel_start('فرصت‌ها', 'opportunities');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide opp-board">
  <?php casting_render_panel_heading('فرصت‌ها و فراخوان‌های باز'); ?>
  <p class="lede opp-board-lede">عنوان، نوع پروژه، نقش و مکان را یک‌نگاه ببینید؛ بعد اپلای کنید.</p>

  <div class="admin-tabs" role="tablist">
    <a class="admin-tab<?= $tab === 'open' ? ' is-active' : '' ?>" href="opportunities.php?tab=open">فراخوان‌های باز (<?= count($open_list_all) ?>)</a>
    <a class="admin-tab<?= $tab === 'mine' ? ' is-active' : '' ?>" href="opportunities.php?tab=mine">اپلای‌های من (<?= count($my_apps) ?>)</a>
  </div>

  <?php if ($tab === 'mine') : ?>
    <?php if ($my_apps === []) : ?>
      <div class="empty-state empty-state--search" role="status">
        <h2 class="empty-state-title">هنوز اپلایی ندارید</h2>
        <p class="empty-state-text">از تب فراخوان‌های باز یک فرصت مناسب انتخاب کنید و اپلای بفرستید.</p>
        <div class="cta-row empty-state-actions">
          <a class="btn btn-primary" href="opportunities.php?tab=open">مشاهده فراخوان‌ها</a>
        </div>
      </div>
    <?php else : ?>
      <div class="home-opportunity-list opp-card-list">
        <?php foreach ($my_apps as $app) : ?>
          <?php casting_render_opportunity_application_card($app, $app_labels); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else : ?>
    <?php casting_render_opportunity_type_chips($type_filter); ?>

    <?php if ($open_list_all === []) : ?>
      <div class="empty-state empty-state--search" role="status">
        <h2 class="empty-state-title">فعلاً فراخوان بازی نیست</h2>
        <p class="empty-state-text">به‌زودی فرصت‌های جدید اینجا می‌آیند. کارگردان‌ها می‌توانند از «پروژه‌ها» فراخوان منتشر کنند.</p>
      </div>
    <?php elseif ($open_list === []) : ?>
      <div class="empty-state empty-state--search" role="status">
        <h2 class="empty-state-title">در این دسته فراخوانی نیست</h2>
        <p class="empty-state-text">نوع دیگری را انتخاب کنید یا همه فراخوان‌ها را ببینید.</p>
        <div class="cta-row empty-state-actions">
          <a class="btn btn-primary" href="opportunities.php?tab=open">همه فراخوان‌ها</a>
        </div>
      </div>
    <?php else : ?>
      <p class="meta member-search-count"><?= count($open_list) ?> فراخوان<?= $type_filter !== 'all' ? ' در این دسته' : '' ?></p>
      <div class="home-opportunity-list opp-card-list">
        <?php foreach ($open_list as $op) :
            $oid = (int) ($op['id'] ?? 0);
            $mine = casting_opportunity_get_application($oid, $user_id);
            $already = $mine && (string) ($mine['status'] ?? '') !== 'withdrawn';
            $status_label = '';
            if ($already) {
                $status_label = 'اپلای شما: ' . (string) ($app_labels[(string) ($mine['status'] ?? 'pending')] ?? 'ثبت‌شده');
            }
            casting_render_opportunity_card($op, [
                'expanded'         => $open_id === $oid,
                'already'          => $already,
                'is_own'           => (int) ($op['director_id'] ?? 0) === $user_id,
                'can_admin_delete' => $can_admin_delete,
                'status_label'     => $status_label,
                'show_message'     => true,
            ]);
        endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
