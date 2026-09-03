<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel-profile.php';
require_once __DIR__ . '/includes/sms.php';
require_once __DIR__ . '/includes/profile-completion-sms.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_portal_owner($user_id)) {
    wp_die('فقط مدیر اصلی پورتال به این بخش دسترسی دارد.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$error = '';
$success = '';
$batch_result = null;
$last_test_variant = '';
$last_test_code = 0;
$debug = function_exists('casting_sms_last_debug') ? casting_sms_last_debug() : null;
$test_mobile = casting_profile_completion_sms_user_mobile($user_id);
if ($test_mobile === '' && function_exists('casting_normalize_mobile')) {
    try {
        $test_mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
    } catch (Throwable $e) {
        $test_mobile = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_profile_sms_admin')) {
            $error = 'درخواست نامعتبر است.';
        } else {
            $action = sanitize_key((string) ($_POST['action'] ?? ''));
            if ($action === 'test_self' || $action === 'test_self_no_link') {
                if ($test_mobile === '' || !preg_match('/^09\d{9}$/', $test_mobile)) {
                    $error = 'موبایل معتبر در پروفایل شما نیست.';
                } else {
                    $with_link = $action !== 'test_self_no_link';
                    $result = casting_profile_completion_sms_send_test($test_mobile, $with_link);
                    $debug = casting_sms_last_debug();
                    $last_test_variant = (string) ($result['variant'] ?? '');
                    $last_test_code = (int) ($result['code'] ?? 0);
                    if (!empty($result['ok'])) {
                        $success = 'درخواست ارسال به ' . $test_mobile . ' ثبت شد'
                            . ($with_link ? ' (با لینک)' : ' (بدون لینک)')
                            . ($result['ref_id'] !== '' ? ' — refId: ' . $result['ref_id'] : '')
                            . ($last_test_code !== 0 ? ' — کد: ' . $last_test_code : '')
                            . '. اگر پیامک نرسید، گزارش ارسال پنل WebOne را با همین refId چک کنید.';
                    } else {
                        $error = (string) ($result['error'] !== '' ? $result['error'] : 'ارسال ناموفق بود.')
                            . ($last_test_code > 0 ? ' (کد ' . $last_test_code . ')' : '');
                    }
                }
            } elseif ($action === 'dry_run' || $action === 'send_batch') {
                $limit = max(1, min(200, (int) ($_POST['limit'] ?? 30)));
                $page = max(1, (int) ($_POST['page'] ?? 1));
                $batch_result = casting_profile_completion_sms_run_batch($limit, $action === 'dry_run', $page, $page <= 1);
                if (empty($batch_result['ok'])) {
                    $error = (string) ($batch_result['errors'][0] ?? 'اجرای دسته‌ای ناموفق بود.');
                } elseif ($action === 'dry_run') {
                    $success = 'پیش‌نمایش: ' . (int) $batch_result['sent'] . ' پیامک ارسال می‌شد ('
                        . (int) $batch_result['scanned'] . ' کاربر بررسی شد).';
                } else {
                    $success = (int) $batch_result['sent'] . ' پیامک ارسال شد. '
                        . (int) $batch_result['failed'] . ' ناموفق، '
                        . (int) $batch_result['skipped'] . ' رد شد.';
                }
            } elseif ($action === 'send_all' || $action === 'dry_run_all') {
                $batch_result = casting_profile_completion_sms_run_all($action === 'dry_run_all', 50, 50);
                if (empty($batch_result['ok'])) {
                    $error = (string) ($batch_result['errors'][0] ?? 'ارسال کامل ناموفق بود.');
                } elseif ($action === 'dry_run_all') {
                    $success = 'پیش‌نمایش کل: ' . (int) $batch_result['sent'] . ' پیامک ('
                        . (int) $batch_result['scanned'] . ' کاربر بررسی شد، '
                        . (int) $batch_result['pages'] . ' صفحه).';
                } else {
                    $success = 'ارسال کامل: ' . (int) $batch_result['sent'] . ' پیامک ارسال شد. '
                        . (int) $batch_result['failed'] . ' ناموفق، '
                        . (int) $batch_result['skipped'] . ' رد شد.'
                        . (!empty($batch_result['done']) ? '' : ' (هنوز کاربر باقی مانده — دوباره بزنید.)');
                }
            } else {
                $error = 'عملیات نامعتبر است.';
            }
        }
    } catch (Throwable $e) {
        $error = 'خطای داخلی: ' . $e->getMessage();
        if (function_exists('error_log')) {
            error_log('[casting-profile-sms-admin] ' . $e->getMessage());
        }
    }
}

$message_preview = casting_profile_completion_sms_message_body(
    !(defined('CASTING_PROFILE_SMS_REMINDER_INCLUDE_LINK') && !CASTING_PROFILE_SMS_REMINDER_INCLUDE_LINK)
);
$threshold = casting_profile_completion_sms_threshold_percent();
$always_logins = implode(', ', casting_profile_completion_sms_always_send_logins());
$cooldown_days = casting_profile_completion_sms_cooldown_days();
$cron_url = casting_profile_completion_sms_cron_url();
$enabled = casting_profile_completion_sms_is_enabled();

casting_render_panel_start('پیامک تکمیل پروفایل', 'admin-profile-sms');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>یادآوری پیامکی تکمیل پروفایل</h1>
  <p class="lede">به اعضای پورتال با تکمیل پروفایل زیر <?= (int) $threshold ?>٪ (بدون لینک) پیامک یادآوری ارسال می‌شود. هر کاربر حداکثر یک‌بار در <?= (int) $cooldown_days ?> روز پیامک می‌گیرد. کاربران <code><?= casting_e($always_logins) ?></code> همیشه در لیست ارسال هستند.</p>

  <dl class="admin-mail-status">
    <dt>ارسال فعال</dt>
    <dd><?= $enabled ? '✓ بله' : '✗ خیر — کلید پیامک یا CASTING_PROFILE_SMS_REMINDER_ENABLED را بررسی کنید' ?></dd>
    <dt>آستانه تکمیل پروفایل</dt>
    <dd>زیر <?= (int) $threshold ?>٪</dd>
    <dt>لینک داخل پیامک</dt>
    <dd><?= (defined('CASTING_PROFILE_SMS_REMINDER_INCLUDE_LINK') && CASTING_PROFILE_SMS_REMINDER_INCLUDE_LINK) ? '✓ فعال' : '✗ غیرفعال (فعلاً بدون لینک)' ?></dd>
    <dt>فاصله ارسال مجدد</dt>
    <dd><?= (int) $cooldown_days ?> روز</dd>
    <dt>کرون خودکار</dt>
    <dd><?php if ($cron_url !== '') : ?>
      <code dir="ltr" style="word-break:break-all;"><?= casting_e($cron_url) ?></code>
      <p class="meta">در cPanel یک Cron Job روزانه بگذارید. کلید را در <code>config.local.php</code> با <code>CASTING_PROFILE_SMS_CRON_KEY</code> تنظیم کنید.</p>
    <?php else : ?>
      ✗ <code>CASTING_PROFILE_SMS_CRON_KEY</code> در config.local.php تنظیم نشده است.
    <?php endif; ?></dd>
  </dl>

  <div class="dash-card" style="margin:1rem 0;padding:1rem;">
    <h2>متن پیامک</h2>
    <pre dir="rtl" style="white-space:pre-wrap;margin:0;"><?= casting_e($message_preview) ?></pre>
    <p class="meta">اگر پیامک با لینک نمی‌رسد، احتمالاً پنل WebOne آن را فیلتر کرده (کد ۷). «تست بدون لینک» را بزنید یا الگوی تأییدشده در پنل بسازید.</p>
  </div>

  <form class="form" method="post" action="admin-profile-sms.php" style="margin-bottom:1.5rem;">
    <?php wp_nonce_field('casting_profile_sms_admin'); ?>
    <p class="meta">موبایل مقصد از پروفایل شما: <strong dir="ltr"><?= $test_mobile !== '' ? casting_e($test_mobile) : '— ثبت نشده' ?></strong></p>
    <div class="cta-row">
      <button class="btn btn-primary" type="submit" name="action" value="test_self" <?= $test_mobile === '' ? 'disabled' : '' ?>>تست با لینک</button>
      <button class="btn btn-ghost" type="submit" name="action" value="test_self_no_link" <?= $test_mobile === '' ? 'disabled' : '' ?>>تست بدون لینک</button>
    </div>
  </form>

  <?php if (is_array($debug)) : ?>
    <details class="dash-card" open style="margin:1rem 0;padding:1rem;">
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

    <div class="dash-card" style="margin:1.5rem 0;padding:1rem;">
      <h2>ارسال همین الان به همه</h2>
      <p class="meta">زیر <?= (int) $threshold ?>٪ + کاربران <?= casting_e($always_logins) ?> — بدون لینک، با رعایت محدودیت <?= (int) $cooldown_days ?> روزه.</p>
      <form class="form" method="post" action="admin-profile-sms.php">
        <?php wp_nonce_field('casting_profile_sms_admin'); ?>
        <div class="cta-row">
          <button class="btn btn-ghost" type="submit" name="action" value="dry_run_all">پیش‌نمایش همه</button>
          <button class="btn btn-primary" type="submit" name="action" value="send_all" onclick="return confirm('پیامک به همه واجد شرایط ارسال شود؟');">ارسال به همه الان</button>
        </div>
      </form>
    </div>

  <form class="form" method="post" action="admin-profile-sms.php">
    <?php wp_nonce_field('casting_profile_sms_admin'); ?>
    <div class="field">
      <label for="limit">حداکثر ارسال در هر اجرا</label>
      <input id="limit" name="limit" type="number" min="1" max="200" value="30">
    </div>
    <div class="field">
      <label for="page">شروع از صفحه (برای ادامه دسته قبلی)</label>
      <input id="page" name="page" type="number" min="1" value="<?= $batch_result ? (int) ($batch_result['next_page'] ?? 1) : 1 ?>">
    </div>
    <div class="cta-row">
      <button class="btn btn-ghost" type="submit" name="action" value="dry_run">پیش‌نمایش (بدون ارسال)</button>
      <button class="btn btn-primary" type="submit" name="action" value="send_batch" onclick="return confirm('پیامک به کاربران واجد شرایط ارسال شود؟');">ارسال دسته‌ای</button>
    </div>
  </form>

  <?php if (is_array($batch_result) && !empty($batch_result['items'])) : ?>
    <div class="admin-table-wrap" style="margin-top:1.5rem;">
      <table class="admin-table">
        <thead>
          <tr>
            <th>کاربر</th>
            <th>موبایل</th>
            <th>تکمیل</th>
            <th>وضعیت</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($batch_result['items'] as $row) : ?>
            <tr>
              <td><?= casting_e((string) $row['name']) ?> <span class="meta">#<?= (int) $row['id'] ?></span></td>
              <td dir="ltr"><?= casting_e((string) $row['mobile']) ?></td>
              <td><?= (int) $row['percent'] ?>٪</td>
              <td><?= casting_e((string) $row['status']) ?><?= ($row['error'] ?? '') !== '' ? ' — ' . casting_e((string) $row['error']) : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
