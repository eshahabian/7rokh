<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$role = casting_get_user_role($user_id);
$view = isset($_GET['view']) && (string) $_GET['view'] === 'archive' ? 'archive' : 'active';
$redirect_base = $view === 'archive' ? 'my-requests.php?view=archive' : 'my-requests.php';

if (isset($_GET['open'])) {
    $open = casting_open_request_chat($user_id, sanitize_text_field((string) $_GET['open']));
    if ($open['ok']) {
        casting_redirect(
            'chat.php?with=' . (int) $open['peer_id']
            . '&request=' . rawurlencode((string) ($open['request_id'] ?? ''))
            . '#latest'
        );
    }
    casting_set_flash('error', (string) ($open['error'] ?? 'باز کردن درخواست ممکن نبود.'));
    casting_redirect($redirect_base);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_archive_action'], $_POST['request_id'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_archive_request')) {
        casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
    } else {
        $action = sanitize_key((string) $_POST['request_archive_action']);
        $request_id = sanitize_text_field((string) $_POST['request_id']);
        if ($action === 'archive') {
            $result = casting_archive_user_request($user_id, $request_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'درخواست به بایگانی منتقل شد.' : $result['error']);
        } elseif ($action === 'unarchive') {
            $result = casting_unarchive_user_request($user_id, $request_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'درخواست از بایگانی بازگردانده شد.' : $result['error']);
        } else {
            casting_set_flash('error', 'عملیات نامعتبر است.');
        }
    }
    $post_view = isset($_POST['view']) && (string) $_POST['view'] === 'archive' ? 'archive' : 'active';
    casting_redirect($post_view === 'archive' ? 'my-requests.php?view=archive' : 'my-requests.php');
}

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
    casting_redirect('my-requests.php');
}

$requests = $view === 'archive' ? casting_user_archived_requests($user_id) : casting_user_requests($user_id);
$archive_count = count(casting_user_archived_requests($user_id));

casting_render_panel_start('درخواست‌های من', 'my-requests');
casting_render_flash();
?>
<section class="dash-card">
  <h1>درخواست‌های من</h1>
  <?php if ($role === 'talent' || casting_is_employer_role($role)) : ?>
    <nav class="admin-tabs request-view-tabs" aria-label="نمایش درخواست‌ها">
      <a class="admin-tab <?= $view === 'active' ? 'is-active' : '' ?>" href="my-requests.php">فعال</a>
      <a class="admin-tab <?= $view === 'archive' ? 'is-active' : '' ?>" href="my-requests.php?view=archive">
        بایگانی<?= $archive_count > 0 ? ' (' . $archive_count . ')' : '' ?>
      </a>
    </nav>
  <?php endif; ?>
  <?php if ($role === 'talent') : ?>
    <p class="lede"><?= $view === 'archive'
        ? 'درخواست‌های بایگانی‌شده. می‌توانید هر زمان آن‌ها را بازگردانید یا گفتگو را ادامه دهید.'
        : 'درخواست‌های همکاری و بازیگری که کارگردان‌ها و تهیه‌کنندگان برای شما فرستاده‌اند.' ?></p>
    <?php casting_render_talent_requests_list($user_id, $requests, 'my-requests.php', $view); ?>
  <?php elseif (casting_is_employer_role($role)) : ?>
    <p class="lede"><?= $view === 'archive'
        ? 'درخواست‌های بایگانی‌شده. می‌توانید هر زمان آن‌ها را بازگردانید یا گفتگو را ادامه دهید.'
        : 'درخواست‌هایی که برای هنرمندان و بازیگران ارسال کرده‌اید.' ?></p>
    <?php casting_render_employer_sent_requests_list($user_id, $requests, 'my-requests.php', $view); ?>
  <?php else : ?>
    <p class="meta">برای این نقش درخواستی ثبت نشده است.</p>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
