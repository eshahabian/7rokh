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

$error = '';
$success = '';
$labels = casting_activity_labels();
$from_filter = sanitize_key((string) ($_GET['from'] ?? $_POST['from_spec'] ?? 'producer'));
if ($from_filter === '' || !isset($labels[$from_filter])) {
    $from_filter = 'producer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_msg_access')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $action = sanitize_key((string) ($_POST['access_action'] ?? 'save'));
        if ($action === 'reset') {
            casting_message_access_reset_to_defaults();
            $success = 'قوانین به حالت پیش‌فرض سلسله‌مراتبی بازگشت.';
        } elseif ($action === 'save') {
            $from_filter = sanitize_key((string) ($_POST['from_spec'] ?? $from_filter));
            $selected = isset($_POST['to_specs']) && is_array($_POST['to_specs']) ? $_POST['to_specs'] : [];
            $require_map = isset($_POST['require_project']) && is_array($_POST['require_project']) ? $_POST['require_project'] : [];
            $targets = [];
            foreach ($selected as $to) {
                $to = sanitize_key((string) $to);
                if ($to === '' || !isset($labels[$to]) || $to === $from_filter) {
                    continue;
                }
                $targets[] = [
                    'to'              => $to,
                    'require_project' => !empty($require_map[$to]),
                    'enabled'         => true,
                ];
            }
            if (casting_message_access_save_from_specialty($from_filter, $targets)) {
                $success = 'دسترسی پیام برای «' . ($labels[$from_filter] ?? $from_filter) . '» ذخیره شد و هم‌اکنون اعمال می‌شود.';
            } else {
                $error = 'ذخیره ناموفق بود.';
            }
        }
    }
}

$data = casting_message_access_get();
$allowed = casting_message_access_targets_for($from_filter);
$allowed_map = [];
foreach ($allowed as $row) {
    $allowed_map[$row['to']] = $row;
}

casting_render_panel_start('دسترسی پیام‌رسان', 'admin-msg-access');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>جدول دسترسی پیام‌رسان</h1>
  <p class="lede">
    قوانین سلسله‌مراتبی شروع گفتگو. فقط شما (eshahabian) این صفحه را می‌بینید.
    تغییرات بلافاصله روی ارسال پیام، دکمه پیام و مخاطبین اعمال می‌شود.
    <?= !empty($data['customized']) ? '<strong>وضعیت: سفارشی‌سازی‌شده</strong>' : '<strong>وضعیت: پیش‌فرض سیستم</strong>' ?>
    — تعداد کل لبه‌ها: <?= count($data['edges']) ?>
  </p>

  <form class="form filter-bar" method="get" action="admin-message-access.php">
    <div class="field">
      <label for="from">نقش / تخصص فرستنده</label>
      <select id="from" name="from" onchange="this.form.submit()">
        <?php foreach ($labels as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $from_filter === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <form class="form" method="post" action="admin-message-access.php?from=<?= casting_e($from_filter) ?>">
    <?php wp_nonce_field('casting_msg_access'); ?>
    <input type="hidden" name="from_spec" value="<?= casting_e($from_filter) ?>">
    <input type="hidden" name="access_action" value="save">

    <p class="field-hint">تیک بزنید چه تخصص‌هایی می‌توانند از طرف «<?= casting_e($labels[$from_filter] ?? $from_filter) ?>» پیام جدید بگیرند. گزینه «نیاز به پروژه/رابطه» یعنی فقط با پروژه مشترک، دعوت، یا گفتگوی قبلی.</p>

    <div class="msg-access-table-wrap">
      <table class="msg-access-table">
        <thead>
          <tr>
            <th scope="col">مجاز</th>
            <th scope="col">گیرنده</th>
            <th scope="col">کلید</th>
            <th scope="col">نیاز به پروژه / رابطه</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($labels as $to_key => $to_label) :
              if ($to_key === $from_filter) {
                  continue;
              }
              $row = $allowed_map[$to_key] ?? null;
              $checked = $row !== null && !empty($row['enabled']);
              $require = $row !== null && !empty($row['require_project']);
              ?>
            <tr>
              <td>
                <input type="checkbox" name="to_specs[]" value="<?= casting_e($to_key) ?>" <?= $checked ? 'checked' : '' ?> id="to-<?= casting_e($to_key) ?>">
              </td>
              <td><label for="to-<?= casting_e($to_key) ?>"><?= casting_e($to_label) ?></label></td>
              <td><code><?= casting_e($to_key) ?></code></td>
              <td>
                <input type="checkbox" name="require_project[<?= casting_e($to_key) ?>]" value="1" <?= $require ? 'checked' : '' ?> title="فقط با پروژه مشترک یا رابطه قبلی">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="cta-row" style="margin-top:1rem">
      <button class="btn btn-primary" type="submit">ذخیره دسترسی این نقش</button>
    </div>
  </form>

  <form class="form" method="post" action="admin-message-access.php?from=<?= casting_e($from_filter) ?>" style="margin-top:1.5rem" onsubmit="return confirm('همه قوانین سفارشی پاک شود و پیش‌فرض سلسله‌مراتبی برگردد؟');">
    <?php wp_nonce_field('casting_msg_access'); ?>
    <input type="hidden" name="access_action" value="reset">
    <button class="btn btn-reject" type="submit">بازگشت به پیش‌فرض سیستم</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
