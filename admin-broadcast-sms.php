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

if (!casting_user_is_portal_owner($user_id)) {
    wp_die('فقط مدیر اصلی پورتال به این بخش دسترسی دارد.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$error = '';
$success = '';
$batch_result = null;
$message = (string) ($_POST['message'] ?? '');
$debug = function_exists('casting_sms_last_debug') ? casting_sms_last_debug() : null;
$test_mobile = '';
try {
    $test_mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
} catch (Throwable $e) {
    $test_mobile = '';
}
$recipient_total = count(casting_portal_broadcast_sms_build_recipient_list(false));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_broadcast_sms_admin')) {
            $error = 'درخواست نامعتبر است.';
        } else {
            $action = sanitize_key((string) ($_POST['action'] ?? ''));
            $message = (string) ($_POST['message'] ?? '');

            if ($action === 'test_self') {
                if ($test_mobile === '' || !preg_match('/^09\d{9}$/', $test_mobile)) {
                    $error = 'موبایل معتبر در پروفایل شما نیست.';
                } elseif (!casting_portal_broadcast_sms_message_is_valid($message)) {
                    $error = 'متن پیامک را وارد کنید (حداکثر ۵۰۰ کاراکتر).';
                } else {
                    $result = casting_portal_broadcast_sms_send_text($test_mobile, $message);
                    $debug = casting_sms_last_debug();
                    if (!empty($result['ok'])) {
                        $success = 'پیامک تست به ' . $test_mobile . ' ارسال شد.'
                            . ($result['ref_id'] !== '' ? ' (refId: ' . $result['ref_id'] . ')' : '');
                    } else {
                        $error = (string) ($result['error'] !== '' ? $result['error'] : 'ارسال ناموفق بود.');
                    }
                }
            } elseif ($action === 'dry_run' || $action === 'send_batch') {
                if (!casting_portal_broadcast_sms_message_is_valid($message)) {
                    $error = 'متن پیامک را وارد کنید (حداکثر ۵۰۰ کاراکتر).';
                } else {
                    $limit = max(1, min(200, (int) ($_POST['limit'] ?? 30)));
                    $offset = max(0, (int) ($_POST['offset'] ?? 0));
                    $batch_result = casting_portal_broadcast_sms_run_batch($message, $limit, $offset, $action === 'dry_run');
                    if (empty($batch_result['ok'])) {
                        $error = (string) ($batch_result['errors'][0] ?? 'اجرای دسته‌ای ناموفق بود.');
                    } elseif ($action === 'dry_run') {
                        $success = 'پیش‌نمایش: ' . (int) $batch_result['sent'] . ' پیامک از '
                            . (int) $batch_result['total'] . ' شماره.';
                    } else {
                        $success = (int) $batch_result['sent'] . ' پیامک ارسال شد، '
                            . (int) $batch_result['failed'] . ' ناموفق.';
                    }
                }
            } elseif ($action === 'dry_run_all' || $action === 'send_all') {
                if (!casting_portal_broadcast_sms_message_is_valid($message)) {
                    $error = 'متن پیامک را وارد کنید (حداکثر ۵۰۰ کاراکتر).';
                } else {
                    $batch_result = casting_portal_broadcast_sms_run_all($message, $action === 'dry_run_all', 50);
                    if (empty($batch_result['ok'])) {
                        $error = (string) ($batch_result['errors'][0] ?? 'ارسال کامل ناموفق بود.');
                    } elseif ($action === 'dry_run_all') {
                        $success = 'پیش‌نمایش کل: ' . (int) $batch_result['sent'] . ' پیامک به '
                            . (int) $batch_result['total'] . ' شماره یکتا.';
                    } else {
                        $success = 'ارسال کامل: ' . (int) $batch_result['sent'] . ' پیامک ارسال شد، '
                            . (int) $batch_result['failed'] . ' ناموفق (از '
                            . (int) $batch_result['total'] . ' شماره).';
                    }
                }
            } else {
                $error = 'عملیات نامعتبر است.';
            }
        }
    } catch (Throwable $e) {
        $error = 'خطای داخلی: ' . $e->getMessage();
        if (function_exists('error_log')) {
            error_log('[casting-broadcast-sms] ' . $e->getMessage());
        }
    }
}

$recipient_total = count(casting_portal_broadcast_sms_build_recipient_list(false));
$enabled = function_exists('casting_sms_is_configured') && casting_sms_is_configured();

casting_render_panel_start('پیامک همگانی', 'admin-broadcast-sms');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>ارسال پیامک دلخواه به اعضای پورتال</h1>
  <p class="lede">متن دلخواه را بنویسید و به همه شماره‌های معتبر اعضای پورتال (موبایل اصلی و دوم) ارسال کنید. شماره‌های تکراری یک‌بار پیامک می‌گیرند. کاربران تعلیق‌شده حذف می‌شوند.</p>

  <dl class="admin-mail-status">
    <dt>ارسال فعال</dt>
    <dd><?= $enabled ? '✓ بله' : '✗ خیر — کلید پیامک را در config.local.php تنظیم کنید' ?></dd>
    <dt>تعداد شماره‌های یکتا</dt>
    <dd><?= (int) $recipient_total ?> شماره</dd>
  </dl>

  <form class="form" method="post" action="admin-broadcast-sms.php">
    <?php wp_nonce_field('casting_broadcast_sms_admin'); ?>
    <div class="field">
      <label for="message">متن پیامک</label>
      <textarea id="message" name="message" rows="6" maxlength="500" required placeholder="متن دلخواه شما..."><?= casting_e($message) ?></textarea>
      <p class="field-hint">حداکثر ۵۰۰ کاراکتر. لینک ممکن است توسط پنل پیامک فیلتر شود.</p>
    </div>

    <p class="meta">تست به موبایل شما: <strong dir="ltr"><?= $test_mobile !== '' ? casting_e($test_mobile) : '— ثبت نشده' ?></strong></p>
    <div class="cta-row" style="margin-bottom:1.5rem;">
      <button class="btn btn-ghost" type="submit" name="action" value="test_self" <?= $test_mobile === '' ? 'disabled' : '' ?>>ارسال تست به خودم</button>
      <button class="btn btn-ghost" type="submit" name="action" value="dry_run_all">پیش‌نمایش همه</button>
      <button class="btn btn-primary" type="submit" name="action" value="send_all" onclick="return confirm('پیامک به <?= (int) $recipient_total ?> شماره ارسال شود؟');">ارسال به همه</button>
    </div>

    <details class="dash-card" style="padding:1rem;margin:0;">
      <summary><strong>ارسال دسته‌ای (پیشرفته)</strong></summary>
      <div class="field" style="margin-top:1rem;">
        <label for="limit">تعداد در هر دسته</label>
        <input id="limit" name="limit" type="number" min="1" max="200" value="30">
      </div>
      <div class="field">
        <label for="offset">شروع از شماره (offset)</label>
        <input id="offset" name="offset" type="number" min="0" value="<?= $batch_result ? (int) ($batch_result['next_offset'] ?? 0) : 0 ?>">
      </div>
      <div class="cta-row">
        <button class="btn btn-ghost" type="submit" name="action" value="dry_run">پیش‌نمایش دسته</button>
        <button class="btn btn-primary" type="submit" name="action" value="send_batch">ارسال دسته</button>
      </div>
    </details>
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

  <?php if (is_array($batch_result) && !empty($batch_result['items'])) : ?>
    <div class="admin-table-wrap" style="margin-top:1.5rem;">
      <table class="admin-table">
        <thead>
          <tr>
            <th>کاربر</th>
            <th>موبایل</th>
            <th>وضعیت</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($batch_result['items'] as $row) : ?>
            <tr>
              <td><?= casting_e((string) $row['name']) ?> <span class="meta"><?= casting_e((string) $row['login']) ?></span></td>
              <td dir="ltr"><?= casting_e((string) $row['mobile']) ?></td>
              <td><?= casting_e((string) $row['status']) ?><?= ($row['error'] ?? '') !== '' ? ' — ' . casting_e((string) $row['error']) : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
