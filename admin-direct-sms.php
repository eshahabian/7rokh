<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/sms.php';
require_once __DIR__ . '/includes/portal-broadcast-sms.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_can_manage_sms($user_id)) {
    wp_die('فقط مدیران اصلی پورتال (eshahabian و ardavan) به این بخش دسترسی دارند.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$error = '';
$success = '';
$search = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$target_id = (int) ($_GET['user'] ?? $_POST['target_id'] ?? 0);
$message = (string) ($_POST['message'] ?? '');
$mobile_input = trim((string) ($_POST['mobile'] ?? ''));
$results = $search !== '' ? casting_portal_direct_sms_search_users($search) : [];
$debug = function_exists('casting_sms_last_debug') ? casting_sms_last_debug() : null;
$enabled = function_exists('casting_sms_is_configured') && casting_sms_is_configured();

$target = $target_id > 0 ? get_user_by('id', $target_id) : false;
$target_row = ($target instanceof WP_User && casting_get_user_role($target_id) !== '')
    ? casting_portal_direct_sms_user_row($target)
    : null;
if ($target_row && $mobile_input === '') {
    $mobile_input = (string) $target_row['mobile'];
} elseif ($mobile_input === '' && $search !== '') {
    $maybe_mobile = casting_normalize_mobile($search);
    if ($maybe_mobile !== '' && preg_match('/^09\d{9}$/', $maybe_mobile)) {
        $mobile_input = $maybe_mobile;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_direct_sms_admin')) {
            $error = 'درخواست نامعتبر است.';
        } else {
            $action = sanitize_key((string) ($_POST['action'] ?? 'send'));
            if ($action !== 'send') {
                $error = 'عملیات نامعتبر است.';
            } elseif (!casting_portal_broadcast_sms_message_is_valid($message)) {
                $error = 'متن پیامک را وارد کنید (حداکثر ۵۰۰ کاراکتر).';
            } else {
                $send_mobile = $mobile_input;
                $send_user_id = $target_id;
                if ($send_mobile === '' && $target_row) {
                    $send_mobile = (string) $target_row['mobile'];
                }
                if ($send_mobile === '' && $search !== '') {
                    $send_mobile = $search;
                }
                $result = casting_portal_direct_sms_send($send_mobile, $message, $send_user_id);
                $debug = casting_sms_last_debug();
                $mobile_input = (string) ($result['mobile'] !== '' ? $result['mobile'] : $send_mobile);
                if (!empty($result['ok'])) {
                    $who = $target_row
                        ? ($target_row['name'] . ' (' . $target_row['login'] . ')')
                        : $result['mobile'];
                    $success = 'پیامک به ' . $who . ' — ' . $result['mobile'] . ' ارسال شد.'
                        . ($result['ref_id'] !== '' ? ' (refId: ' . $result['ref_id'] . ')' : '');
                } else {
                    $error = (string) ($result['error'] !== '' ? $result['error'] : 'ارسال ناموفق بود.');
                }
            }
        }
    } catch (Throwable $e) {
        $error = 'خطای داخلی: ' . $e->getMessage();
        if (function_exists('error_log')) {
            error_log('[casting-direct-sms] ' . $e->getMessage());
        }
    }
}

casting_render_panel_start('پیامک به کاربر خاص', 'admin-direct-sms');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>ارسال پیامک به شخص یا کاربر خاص</h1>
  <p class="lede">کاربر پورتال را با نام، نام کاربری، ایمیل، شناسه یا موبایل پیدا کنید، یا مستقیم شماره بدهید و متن دلخواه بفرستید. فقط مدیران اصلی (<code>eshahabian</code> و <code>ardavan</code>) به این بخش دسترسی دارند.</p>

  <dl class="admin-mail-status">
    <dt>ارسال فعال</dt>
    <dd><?= $enabled ? '✓ بله' : '✗ خیر — کلید پیامک را در config.local.php تنظیم کنید' ?></dd>
  </dl>

  <form class="form admin-search-form" method="get" action="admin-direct-sms.php">
    <div class="field">
      <label for="q">جستجوی کاربر</label>
      <input id="q" name="q" type="search" value="<?= casting_e($search) ?>" placeholder="نام، نام کاربری، ایمیل، شناسه یا موبایل">
    </div>
    <button class="btn btn-primary" type="submit">جستجو</button>
  </form>

  <?php if ($search !== '' && $results === []) : ?>
    <p class="empty-state">کاربری پیدا نشد. می‌توانید همان شماره را در فرم زیر وارد کنید و مستقیم پیامک بفرستید.</p>
  <?php elseif ($results !== []) : ?>
    <ul class="admin-user-pick-list">
      <?php foreach ($results as $row) : ?>
        <li>
          <a href="admin-direct-sms.php?user=<?= (int) $row['id'] ?>&amp;q=<?= rawurlencode($search) ?>">
            <strong><?= casting_e($row['name']) ?></strong>
            <span class="meta"><?= casting_e($row['login']) ?></span>
            <?php if ($row['mobile'] !== '') : ?>
              <span class="meta" dir="ltr"><?= casting_e($row['mobile']) ?></span>
            <?php endif; ?>
            <?php if (!empty($row['suspended'])) : ?><span class="chip chip-danger">معلق</span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($target_row) : ?>
    <div class="admin-user-detail">
      <h2 class="panel-section-title"><?= casting_e($target_row['name']) ?></h2>
      <ul class="info-list">
        <li><strong>نام کاربری:</strong> <?= casting_e($target_row['login']) ?></li>
        <li><strong>ایمیل:</strong> <?= casting_e($target_row['email']) ?></li>
        <li><strong>نقش:</strong> <?= casting_e(casting_role_label($target_row['role'])) ?></li>
        <li><strong>موبایل‌های ثبت‌شده:</strong>
          <?php if ($target_row['mobiles'] === []) : ?>
            ثبت نشده
          <?php else : ?>
            <span dir="ltr"><?= casting_e(implode('، ', $target_row['mobiles'])) ?></span>
          <?php endif; ?>
        </li>
      </ul>
    </div>
  <?php endif; ?>

  <form class="form" method="post" action="admin-direct-sms.php">
    <?php wp_nonce_field('casting_direct_sms_admin'); ?>
    <input type="hidden" name="action" value="send">
    <input type="hidden" name="q" value="<?= casting_e($search) ?>">
    <input type="hidden" name="target_id" value="<?= $target_row ? (int) $target_row['id'] : 0 ?>">
    <div class="field">
      <label for="mobile">شماره موبایل گیرنده</label>
      <input id="mobile" name="mobile" type="tel" required pattern="09[0-9]{9}" value="<?= casting_e($mobile_input) ?>" placeholder="09121234567">
      <p class="field-hint">از پروفایل کاربر پر می‌شود؛ می‌توانید شماره دیگری هم بگذارید.</p>
    </div>
    <div class="field">
      <label for="message">متن پیامک</label>
      <textarea id="message" name="message" rows="6" maxlength="500" required placeholder="متن دلخواه شما..."><?= casting_e($message) ?></textarea>
      <p class="field-hint">حداکثر ۵۰۰ کاراکتر.</p>
    </div>
    <button class="btn btn-primary" type="submit" <?= $enabled ? '' : 'disabled' ?> onclick="return confirm('پیامک به این شماره ارسال شود؟');">ارسال پیامک</button>
  </form>

  <?php if (is_array($debug)) : ?>
    <details class="dash-card" style="margin:1rem 0;padding:1rem;">
      <summary><strong>آخرین پاسخ API پیامک</strong></summary>
      <p class="meta" dir="ltr"><?= casting_e((string) ($debug['at'] ?? '')) ?> · HTTP <?= (int) ($debug['http'] ?? 0) ?> · <?= !empty($debug['ok']) ? 'ok' : 'fail' ?></p>
      <?php if (!empty($debug['parsed_error'])) : ?>
        <p class="flash flash-error"><?= casting_e((string) $debug['parsed_error']) ?></p>
      <?php endif; ?>
      <pre dir="ltr" style="white-space:pre-wrap;overflow:auto;max-height:280px;font-size:0.8rem;"><?= casting_e((string) wp_json_encode([
          'request' => $debug['request'] ?? null,
          'body'    => $debug['body'] ?? null,
          'ref_id'  => $debug['ref_id'] ?? null,
      ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
    </details>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
