<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/director-workspace.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$role = casting_get_user_role($user_id);
$is_director = casting_user_is_director_role($user_id);
$view = isset($_GET['view']) && (string) $_GET['view'] === 'archive' ? 'archive' : 'active';
$box = 'default';
if ($is_director) {
    $box = isset($_GET['box']) && (string) $_GET['box'] === 'received' ? 'received' : 'sent';
}

function casting_my_requests_redirect_url(string $view, string $box = 'default'): string
{
    $url = 'my-requests.php';
    $params = [];
    if ($view === 'archive') {
        $params['view'] = 'archive';
    }
    if ($box === 'received') {
        $params['box'] = 'received';
    }
    if ($params === []) {
        return $url;
    }

    return $url . '?' . http_build_query($params);
}

$redirect_base = casting_my_requests_redirect_url($view, $box);
$compose_open = isset($_GET['compose']) && (string) $_GET['compose'] === '1';
$compose_project = '';
$compose_message = '';
$compose_talent_id = 0;
$compose_error = '';

if ($is_director && $box === 'sent' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_collaboration_request'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_send_request')) {
        $compose_error = 'نشست منقضی شده. دوباره تلاش کنید.';
        $compose_open = true;
    } else {
        $compose_talent_id = (int) ($_POST['talent_id'] ?? 0);
        $compose_project = (string) ($_POST['project'] ?? '');
        $compose_message = (string) ($_POST['message'] ?? '');
        $result = casting_send_talent_request($user_id, $compose_talent_id, $compose_message, $compose_project);
        if (!$result['ok']) {
            $compose_error = $result['error'] ?? 'ارسال درخواست ناموفق بود.';
            $compose_open = true;
        } else {
            casting_set_flash('success', !empty($result['warning']) ? $result['warning'] : 'درخواست ارسال شد.');
            casting_redirect('my-requests.php?box=sent');
        }
    }
}

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
        $post_box = isset($_POST['box']) && (string) $_POST['box'] === 'received' ? 'received' : 'sent';
        $archive_direction = $is_director ? $post_box : 'default';
        if ($action === 'archive') {
            $result = casting_move_user_request($user_id, $request_id, 'active', 'archive', $archive_direction);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'درخواست به بایگانی منتقل شد.' : $result['error']);
        } elseif ($action === 'unarchive') {
            $result = casting_move_user_request($user_id, $request_id, 'archive', 'active', $archive_direction);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'درخواست از بایگانی بازگردانده شد.' : $result['error']);
        } else {
            casting_set_flash('error', 'عملیات نامعتبر است.');
        }
    }
    $post_view = isset($_POST['view']) && (string) $_POST['view'] === 'archive' ? 'archive' : 'active';
    $post_box = isset($_POST['box']) && (string) $_POST['box'] === 'received' ? 'received' : 'sent';
    casting_redirect(casting_my_requests_redirect_url($post_view, $is_director ? $post_box : 'default'));
}

if (($role === 'talent' || $is_director) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['decision'])) {
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
    casting_redirect(casting_my_requests_redirect_url('active', $is_director ? 'received' : 'default'));
}

if ($is_director) {
    $direction = $box === 'received' ? 'received' : 'sent';
    $requests = $view === 'archive'
        ? casting_get_user_request_list_by_direction($user_id, $direction, 'archive')
        : casting_get_user_request_list_by_direction($user_id, $direction, 'active');
    $archive_count = count(casting_get_user_request_list_by_direction($user_id, $direction, 'archive'));
} else {
    $requests = $view === 'archive' ? casting_user_archived_requests($user_id) : casting_user_requests($user_id);
    $archive_count = count(casting_user_archived_requests($user_id));
}

casting_render_panel_start('درخواست‌ها', 'my-requests');
casting_render_flash();
?>
<section class="dash-card">
  <h1>درخواست‌ها</h1>
  <?php if ($is_director) : ?>
    <nav class="admin-tabs request-box-tabs" aria-label="نوع درخواست‌ها">
      <a class="admin-tab <?= $box === 'sent' ? 'is-active' : '' ?>" href="<?= casting_e(casting_my_requests_redirect_url($view, 'sent')) ?>">درخواست‌های ارسالی</a>
      <a class="admin-tab <?= $box === 'received' ? 'is-active' : '' ?>" href="<?= casting_e(casting_my_requests_redirect_url($view, 'received')) ?>">درخواست‌های دریافتی</a>
    </nav>
  <?php endif; ?>
  <?php if ($role === 'talent' || casting_is_employer_role($role)) : ?>
    <nav class="admin-tabs request-view-tabs" aria-label="نمایش درخواست‌ها">
      <a class="admin-tab <?= $view === 'active' ? 'is-active' : '' ?>" href="<?= casting_e(casting_my_requests_redirect_url('active', $is_director ? $box : 'default')) ?>">فعال</a>
      <a class="admin-tab <?= $view === 'archive' ? 'is-active' : '' ?>" href="<?= casting_e(casting_my_requests_redirect_url('archive', $is_director ? $box : 'default')) ?>">
        بایگانی<?= $archive_count > 0 ? ' (' . $archive_count . ')' : '' ?>
      </a>
    </nav>
  <?php endif; ?>
  <?php if ($role === 'talent') : ?>
    <p class="lede"><?= $view === 'archive'
        ? 'درخواست‌های بایگانی‌شده. می‌توانید هر زمان آن‌ها را بازگردانید یا گفتگو را ادامه دهید.'
        : 'درخواست‌های همکاری و بازیگری که کارگردان‌ها و تهیه‌کنندگان برای شما فرستاده‌اند.' ?></p>
    <?php casting_render_talent_requests_list($user_id, $requests, 'my-requests.php', $view); ?>
  <?php elseif ($is_director) : ?>
    <?php if ($box === 'received') : ?>
      <p class="lede"><?= $view === 'archive'
          ? 'درخواست‌های دریافتی بایگانی‌شده.'
          : 'درخواست‌هایی که تهیه‌کنندگان و دیگر کارفرماها برای شما فرستاده‌اند.' ?></p>
      <?php casting_render_talent_requests_list($user_id, $requests, 'my-requests.php', $view, 'received'); ?>
    <?php else : ?>
      <p class="lede"><?= $view === 'archive'
          ? 'درخواست‌های ارسالی بایگانی‌شده.'
          : 'درخواست‌هایی که برای بازیگران و هنرمندان ارسال کرده‌اید.' ?></p>
      <?php if ($view === 'active') :
          $highlighted_talents = casting_director_list_highlighted_talents($user_id);
          if ($compose_error !== '') {
              echo '<div class="flash flash-error" role="alert">' . casting_e($compose_error) . '</div>';
          }
          casting_render_director_send_request_compose(
              $user_id,
              $highlighted_talents,
              $compose_open || $compose_error !== '',
              $compose_project,
              $compose_message,
              $compose_talent_id
          );
      endif; ?>
      <?php casting_render_employer_sent_requests_list($user_id, $requests, 'my-requests.php', $view, 'sent'); ?>
    <?php endif; ?>
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
