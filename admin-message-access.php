<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/chat-rules.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_portal_owner($user_id)) {
    wp_die('این بخش فقط برای مدیر اصلی پورتال (eshahabian) است.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$labels = casting_activity_labels();

// ---- AJAX: روشن/خاموش فوری ----
if (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo wp_json_encode(['ok' => false, 'error' => 'متد نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_msg_access_toggle')) {
        echo wp_json_encode(['ok' => false, 'error' => 'نشست منقضی شده. صفحه را تازه کنید.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $from = sanitize_key((string) ($_POST['from'] ?? ''));
    $to = sanitize_key((string) ($_POST['to'] ?? ''));
    $field = sanitize_key((string) ($_POST['field'] ?? 'enabled'));
    // force را صریح از رشته بخوان — empty('0') در PHP برابر true است و خطرناک است
    $force = null;
    if (array_key_exists('force', $_POST)) {
        $force_raw = (string) $_POST['force'];
        if ($force_raw === '1' || strtolower($force_raw) === 'true' || strtolower($force_raw) === 'on') {
            $force = true;
        } elseif ($force_raw === '0' || strtolower($force_raw) === 'false' || strtolower($force_raw) === 'off') {
            $force = false;
        }
    }
    $result = casting_message_access_toggle_edge(
        $from,
        $to,
        $field === 'require_project' ? 'require_project' : 'enabled',
        $force
    );
    echo wp_json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$error = '';
$success = '';
$from_filter = sanitize_key((string) ($_GET['from'] ?? 'producer'));
if ($from_filter === '' || !isset($labels[$from_filter])) {
    $from_filter = 'producer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_msg_access')) {
        $error = 'درخواست نامعتبر است.';
    } elseif (sanitize_key((string) ($_POST['access_action'] ?? '')) === 'reset') {
        casting_message_access_reset_to_defaults();
        $success = 'قوانین به حالت پیش‌فرض سلسله‌مراتبی بازگشت.';
    }
}

$data = casting_message_access_get();
$allowed_map = [];
foreach (casting_message_access_targets_for($from_filter) as $row) {
    $allowed_map[$row['to']] = $row;
}

$toggle_nonce = wp_create_nonce('casting_msg_access_toggle');

casting_render_panel_start('دسترسی پیام‌رسان', 'admin-msg-access');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide msg-access-page" data-msg-access-page data-toggle-nonce="<?= casting_e($toggle_nonce) ?>">
  <h1>جدول دسترسی پیام‌رسان</h1>
  <p class="lede">
    برای هر نقش فرستنده مشخص کنید به کدام نقش‌ها می‌تواند پیام بدهد.
    با دکمه <strong>روشن / خاموش</strong> دسترسی فوراً عوض و ذخیره می‌شود.
    فقط حساب <code>eshahabian</code> این صفحه را می‌بیند.
  </p>
  <p class="meta">
    <?= !empty($data['customized']) ? 'وضعیت: سفارشی‌سازی‌شده' : 'وضعیت: پیش‌فرض سیستم' ?>
    · تعداد روابط فعال کل: <?= (int) count(array_filter($data['edges'], static fn ($e) => !empty($e['enabled']) && !empty($e['can_start']))) ?>
  </p>
  <div class="flash flash-error msg-access-ajax-error" hidden role="alert"></div>
  <div class="flash flash-success msg-access-ajax-ok" hidden role="status"></div>

  <form class="form filter-bar" method="get" action="admin-message-access.php">
    <div class="field">
      <label for="from">نقش فرستنده (سطر جدول)</label>
      <select id="from" name="from" onchange="this.form.submit()">
        <?php foreach ($labels as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $from_filter === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <h2 class="panel-section-title">روابط «<?= casting_e($labels[$from_filter] ?? $from_filter) ?>» → دیگران</h2>

  <div class="msg-access-table-wrap">
    <table class="msg-access-table">
      <thead>
        <tr>
          <th scope="col">گیرنده</th>
          <th scope="col">دسترسی پیام</th>
          <th scope="col">فقط با پروژه / رابطه</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($labels as $to_key => $to_label) :
            if ($to_key === $from_filter) {
                continue;
            }
            $row = $allowed_map[$to_key] ?? null;
            $on = $row !== null && !empty($row['enabled']);
            $require = $row !== null && !empty($row['require_project']);
            ?>
          <tr data-from="<?= casting_e($from_filter) ?>" data-to="<?= casting_e($to_key) ?>">
            <td>
              <strong><?= casting_e($to_label) ?></strong>
              <div class="meta"><code><?= casting_e($to_key) ?></code></div>
            </td>
            <td>
              <button
                type="button"
                class="msg-toggle <?= $on ? 'is-on' : 'is-off' ?>"
                data-msg-toggle="enabled"
                aria-pressed="<?= $on ? 'true' : 'false' ?>"
              >
                <span class="msg-toggle-knob" aria-hidden="true"></span>
                <span class="msg-toggle-label"><?= $on ? 'روشن' : 'خاموش' ?></span>
              </button>
            </td>
            <td>
              <button
                type="button"
                class="msg-toggle msg-toggle--soft <?= $require ? 'is-on' : 'is-off' ?>"
                data-msg-toggle="require_project"
                aria-pressed="<?= $require ? 'true' : 'false' ?>"
                <?= $on ? '' : 'disabled' ?>
              >
                <span class="msg-toggle-knob" aria-hidden="true"></span>
                <span class="msg-toggle-label"><?= $require ? 'فعال' : 'غیرفعال' ?></span>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form class="form" method="post" action="admin-message-access.php?from=<?= casting_e($from_filter) ?>" style="margin-top:1.5rem" onsubmit="return confirm('همه تنظیمات سفارشی پاک شود و پیش‌فرض برگردد؟');">
    <?php wp_nonce_field('casting_msg_access'); ?>
    <input type="hidden" name="access_action" value="reset">
    <button class="btn btn-reject" type="submit">بازگشت کامل به پیش‌فرض سیستم</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
