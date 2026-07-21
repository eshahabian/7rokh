<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$role = casting_get_user_role($user_id);

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
    casting_redirect('my-requests.php');
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

$requests = casting_user_requests($user_id);
$request_count = count($requests);

casting_render_panel_start('درخواست‌های من', 'my-requests');
casting_render_flash();
?>
<section class="dash-card">
  <h1>درخواست‌های من</h1>
  <?php if ($role === 'talent') : ?>
    <p class="lede">درخواست‌های همکاری و بازیگری که کارگردان‌ها و تهیه‌کنندگان برای شما فرستاده‌اند.</p>
    <?php casting_render_talent_requests_list($user_id, $requests); ?>
  <?php elseif (casting_is_employer_role($role)) : ?>
    <p class="lede">درخواست‌هایی که برای هنرمندان و بازیگران ارسال کرده‌اید.</p>
    <?php casting_render_employer_sent_requests_list($user_id, $requests); ?>
  <?php else : ?>
    <p class="meta">برای این نقش درخواستی ثبت نشده است.</p>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
