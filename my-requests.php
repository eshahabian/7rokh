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
$compose_project_type = '';
$compose_role_needed = '';
$compose_project_city = '';
$compose_message = '';
$compose_talent_id = 0;
$compose_error = '';
$open_request_id = '';
if (isset($_GET['open'])) {
    $open_request_id = casting_request_id_from_open_token(sanitize_text_field((string) $_GET['open']));
}

if ($is_director && $box === 'sent' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_collaboration_request'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_send_request')) {
        $compose_error = 'نشست منقضی شده. دوباره تلاش کنید.';
        $compose_open = true;
    } else {
        $compose_talent_id = (int) ($_POST['talent_id'] ?? 0);
        $compose_project = (string) ($_POST['project'] ?? '');
        $compose_project_type = (string) ($_POST['project_type'] ?? '');
        $compose_role_needed = (string) ($_POST['role_needed'] ?? '');
        $compose_project_city = (string) ($_POST['project_city'] ?? '');
        $compose_message = (string) ($_POST['message'] ?? '');
        $result = casting_send_talent_request($user_id, $compose_talent_id, $compose_message, $compose_project, [
            'project_type' => $compose_project_type,
            'role_needed'  => $compose_role_needed,
            'project_city' => $compose_project_city,
        ]);
        if (!$result['ok']) {
            $compose_error = $result['error'] ?? 'ارسال دعوت ناموفق بود.';
            $compose_open = true;
        } else {
            casting_set_flash('success', !empty($result['warning']) ? $result['warning'] : 'دعوت همکاری ارسال شد.');
            casting_redirect('my-requests.php?box=sent');
        }
    }
}

if ($open_request_id !== '') {
    $opened = casting_find_user_request($user_id, $open_request_id);
    if ($opened !== null) {
        $updated = $opened;
        $role_now = casting_get_user_role($user_id);
        $is_owner = (int) ($opened['employer_id'] ?? 0) === $user_id;
        $is_recipient = (int) ($opened['talent_id'] ?? 0) === $user_id
            || (($role_now === 'talent' || ($role_now === 'director' && !$is_owner))
                && (int) ($opened['employer_id'] ?? 0) !== $user_id);

        if ($is_recipient && (string) ($updated['seen_at'] ?? '') === '') {
            $updated['seen_at'] = current_time('mysql');
            casting_update_request_everywhere($updated);
        } elseif ($is_owner && casting_request_status_key($updated) === 'interested'
            && (string) ($updated['employer_seen_at'] ?? '') === '') {
            casting_mark_request_employer_seen($user_id, $open_request_id);
        }
    }
}

// مشاهدهٔ جزئیات از طریق ?seen= (لینک‌های ارسالی)
$seen_token = sanitize_text_field((string) ($_GET['seen'] ?? ''));
if ($seen_token !== '' && casting_is_employer_role($role)) {
    $seen_id = casting_request_id_from_open_token($seen_token);
    if ($seen_id !== '') {
        casting_mark_request_employer_seen($user_id, $seen_id);
        if ($open_request_id === '') {
            $open_request_id = $seen_id;
        }
    }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_manage_action'], $_POST['request_id'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_manage_request')) {
        casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
    } elseif (!casting_is_employer_role($role)) {
        casting_set_flash('error', 'این عملیات برای نقش شما فعال نیست.');
    } else {
        $action = sanitize_key((string) $_POST['request_manage_action']);
        $request_id = sanitize_text_field((string) $_POST['request_id']);
        $post_box = isset($_POST['box']) && (string) $_POST['box'] === 'received' ? 'received' : 'sent';
        $manage_direction = ($is_director && $post_box === 'sent') || (!$is_director && casting_is_employer_role($role)) ? 'sent' : 'default';
        if ($action === 'withdraw') {
            $result = casting_withdraw_request($user_id, $request_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'درخواست پس گرفته شد و از لیست بازیگر حذف شد.' : $result['error']);
        } elseif ($action === 'delete') {
            $result = casting_delete_user_request($user_id, $request_id, $manage_direction);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'درخواست از لیست شما حذف شد.' : $result['error']);
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
        if ($result['ok']) {
            $status = (string) ($result['status'] ?? '');
            if ($status === 'interested') {
                $msg = 'پاسخ «علاقه‌مندم» ثبت شد. می‌توانید با ارسال‌کننده گفتگو کنید.';
            } elseif ($status === 'need_info') {
                $msg = 'پاسخ «نیاز به اطلاعات بیشتر» ثبت شد.';
            } else {
                $msg = 'پاسخ «تمایلی ندارم» ثبت شد.';
            }
            casting_set_flash('success', $msg);
        } else {
            casting_set_flash('error', (string) ($result['error'] ?? 'ثبت پاسخ ناموفق بود.'));
        }
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

casting_render_panel_start('فراخوان کستینگ', 'my-requests');
casting_render_flash();
?>
<section class="dash-card">
  <h1>فراخوان کستینگ</h1>
  <?php if ($is_director) : ?>
    <?php $acceptance_count = casting_user_new_acceptance_count($user_id); ?>
    <nav class="admin-tabs request-box-tabs" aria-label="نوع فراخوان‌ها">
      <a class="admin-tab <?= $box === 'sent' ? 'is-active' : '' ?>" href="<?= casting_e(casting_my_requests_redirect_url($view, 'sent')) ?>">
        فراخوان‌های ارسالی
        <?php if ($acceptance_count > 0) : ?>
          <span class="nav-badge" aria-label="<?= (int) $acceptance_count ?> قبول جدید"><?= (int) $acceptance_count ?></span>
        <?php endif; ?>
      </a>
      <a class="admin-tab <?= $box === 'received' ? 'is-active' : '' ?>" href="<?= casting_e(casting_my_requests_redirect_url($view, 'received')) ?>">فراخوان‌های دریافتی</a>
    </nav>
  <?php endif; ?>
  <?php if ($role === 'talent' || casting_is_employer_role($role)) : ?>
    <nav class="admin-tabs request-view-tabs" aria-label="نمایش فراخوان‌ها">
      <a class="admin-tab <?= $view === 'active' ? 'is-active' : '' ?>" href="<?= casting_e(casting_my_requests_redirect_url('active', $is_director ? $box : 'default')) ?>">فعال</a>
      <a class="admin-tab <?= $view === 'archive' ? 'is-active' : '' ?>" href="<?= casting_e(casting_my_requests_redirect_url('archive', $is_director ? $box : 'default')) ?>">
        بایگانی<?= $archive_count > 0 ? ' (' . $archive_count . ')' : '' ?>
      </a>
    </nav>
  <?php endif; ?>
  <?php if ($role === 'talent') : ?>
    <p class="lede"><?= $view === 'archive'
        ? 'فراخوان‌های بایگانی‌شده. از پیام‌های عادی جدا هستند.'
        : 'فراخوان‌های کستینگ از پیام‌های عادی جدا هستند. جزئیات هر فراخوان را ببینید و پاسخ دهید.' ?></p>
    <?php casting_render_talent_requests_list($user_id, $requests, 'my-requests.php', $view, 'default', $open_request_id); ?>
  <?php elseif ($is_director) : ?>
    <?php if ($box === 'received') : ?>
      <p class="lede"><?= $view === 'archive'
          ? 'فراخوان‌های دریافتی بایگانی‌شده.'
          : 'فراخوان‌هایی که تهیه‌کنندگان و دیگر کارفرماها برای شما فرستاده‌اند.' ?></p>
      <?php casting_render_talent_requests_list($user_id, $requests, 'my-requests.php', $view, 'received', $open_request_id); ?>
    <?php else : ?>
      <p class="lede"><?= $view === 'archive'
          ? 'فراخوان‌های ارسالی بایگانی‌شده.'
          : 'فراخوان‌های کستینگ که برای اعضا ارسال کرده‌اید (جدا از پیام‌ها).' ?></p>
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
              $compose_talent_id,
              $compose_project_type,
              $compose_role_needed,
              $compose_project_city
          );
      endif; ?>
      <?php casting_render_employer_sent_requests_list($user_id, $requests, 'my-requests.php', $view, 'sent'); ?>
    <?php endif; ?>
  <?php elseif (casting_is_employer_role($role)) : ?>
    <p class="lede"><?= $view === 'archive'
        ? 'فراخوان‌های بایگانی‌شده.'
        : 'فراخوان‌هایی که برای اعضا ارسال کرده‌اید.' ?></p>
    <?php casting_render_employer_sent_requests_list($user_id, $requests, 'my-requests.php', $view); ?>
  <?php else : ?>
    <p class="meta">برای این نقش فراخوانی ثبت نشده است.</p>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
