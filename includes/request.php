<?php
declare(strict_types=1);

require_once __DIR__ . '/blocks.php';
require_once __DIR__ . '/chat-rules.php';

/**
 * ارسال درخواست همکاری کارفرما به هنرمند + ایمیل
 */
function casting_send_talent_request(int $employer_id, int $talent_id, string $message, string $project = ''): array
{
    $employer = get_user_by('id', $employer_id);
    $talent = get_user_by('id', $talent_id);

    if (!$employer || !$talent) {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (casting_get_user_role($employer_id) === '' || !casting_is_employer_role(casting_get_user_role($employer_id))) {
        return ['ok' => false, 'error' => 'فقط کارفرما می‌تواند درخواست بفرستد.'];
    }
    if (casting_get_user_role($talent_id) !== 'talent') {
        return ['ok' => false, 'error' => 'گیرنده هنرمند نیست.'];
    }
    if (casting_users_block_each_other($employer_id, $talent_id)) {
        return ['ok' => false, 'error' => 'به‌دلیل بلاک، ارسال درخواست ممکن نیست.'];
    }

    $message = sanitize_textarea_field($message);
    $project = sanitize_text_field($project);
    if ($message === '') {
        return ['ok' => false, 'error' => 'متن درخواست را بنویسید.'];
    }
    if (casting_strlen($message) > 2000) {
        return ['ok' => false, 'error' => 'متن درخواست خیلی بلند است.'];
    }

    $last_key = 'casting_req_last_' . $talent_id;
    $last = (int) get_user_meta($employer_id, $last_key, true);
    if ($last > 0 && (time() - $last) < 15 * 60) {
        return ['ok' => false, 'error' => 'به‌تازگی به این هنرمند درخواست داده‌اید. کمی بعد دوباره تلاش کنید.'];
    }

    $request = [
        'id'            => uniqid('req_', true),
        'employer_id'   => $employer_id,
        'talent_id'     => $talent_id,
        'employer'      => $employer->display_name,
        'employer_role' => casting_role_label(casting_get_user_role($employer_id)),
        'employer_mail' => $employer->user_email,
        'talent_name'    => $talent->display_name,
        'project'       => $project,
        'message'       => $message,
        'created_at'    => current_time('mysql'),
        'status'        => 'pending',
        'reply'         => '',
        'replied_at'    => '',
    ];

    casting_store_request_for_users($request);
    update_user_meta($employer_id, $last_key, time());

    $mail = casting_mail_talent_request($talent, $employer, $request);
    if (!$mail['ok']) {
        return [
            'ok'      => true,
            'warning' => 'درخواست ذخیره شد، ولی ارسال ایمیل ناموفق بود. تنظیم SMTP وردپرس را چک کنید.',
        ];
    }

    return ['ok' => true];
}

function casting_store_request_for_users(array $request): void
{
    $talent_id = (int) $request['talent_id'];
    $employer_id = (int) $request['employer_id'];

    $inbox = get_user_meta($talent_id, 'casting_requests', true);
    if (!is_array($inbox)) {
        $inbox = [];
    }
    array_unshift($inbox, $request);
    update_user_meta($talent_id, 'casting_requests', array_slice($inbox, 0, 100));

    $outbox = get_user_meta($employer_id, 'casting_sent_requests', true);
    if (!is_array($outbox)) {
        $outbox = [];
    }
    array_unshift($outbox, $request);
    update_user_meta($employer_id, 'casting_sent_requests', array_slice($outbox, 0, 100));
}

function casting_update_request_everywhere(array $updated): bool
{
    $talent_id = (int) ($updated['talent_id'] ?? 0);
    $employer_id = (int) ($updated['employer_id'] ?? 0);
    $req_id = (string) ($updated['id'] ?? '');
    if ($talent_id <= 0 || $employer_id <= 0 || $req_id === '') {
        return false;
    }

    $ok = false;
    foreach ([$talent_id => 'casting_requests', $employer_id => 'casting_sent_requests'] as $uid => $meta_key) {
        $list = get_user_meta($uid, $meta_key, true);
        if (!is_array($list)) {
            continue;
        }
        foreach ($list as $i => $item) {
            if (!is_array($item) || (string) ($item['id'] ?? '') !== $req_id) {
                continue;
            }
            $list[$i] = array_merge($item, $updated);
            $ok = true;
            break;
        }
        update_user_meta($uid, $meta_key, $list);
    }

    return $ok;
}

/**
 * پاسخ هنرمند: accept | reject + نظر
 */
function casting_respond_to_request(int $talent_id, string $request_id, string $decision, string $reply): array
{
    $decision = sanitize_key($decision);
    if (!in_array($decision, ['accepted', 'rejected'], true)) {
        return ['ok' => false, 'error' => 'تصمیم نامعتبر است.'];
    }

    $reply = sanitize_textarea_field($reply);
    if (casting_strlen($reply) > 2000) {
        return ['ok' => false, 'error' => 'نظر شما خیلی بلند است.'];
    }

    $inbox = casting_get_talent_requests($talent_id);
    $found = null;
    foreach ($inbox as $item) {
        if (is_array($item) && (string) ($item['id'] ?? '') === $request_id) {
            $found = $item;
            break;
        }
    }
    if (!$found) {
        return ['ok' => false, 'error' => 'درخواست پیدا نشد.'];
    }
    if ((string) ($found['status'] ?? '') !== 'pending' && (string) ($found['status'] ?? '') !== 'new') {
        return ['ok' => false, 'error' => 'به این درخواست قبلاً پاسخ داده شده است.'];
    }

    $found['status'] = $decision;
    $found['reply'] = $reply;
    $found['replied_at'] = current_time('mysql');

    if (!casting_update_request_everywhere($found)) {
        return ['ok' => false, 'error' => 'ذخیره پاسخ ناموفق بود.'];
    }

    $employer = get_user_by('id', (int) $found['employer_id']);
    $talent = get_user_by('id', $talent_id);
    if ($employer && $talent) {
        casting_mail_employer_response($employer, $talent, $found);
    }

    return ['ok' => true, 'status' => $decision];
}

function casting_mail_talent_request(WP_User $talent, WP_User $employer, array $request): array
{
    $to = $talent->user_email;
    if (!is_email($to)) {
        return ['ok' => false, 'error' => 'ایمیل هنرمند معتبر نیست.'];
    }

    $brand = casting_brand();
    $subject = sprintf('[%s] درخواست همکاری از %s', $brand, $employer->display_name);
    $login_url = casting_url('my-requests.php');
    $lines = [
        'سلام ' . $talent->display_name . '،',
        '',
        'یک درخواست همکاری جدید در ' . $brand . ' برای شما ثبت شده است.',
        '',
        'فرستنده: ' . $employer->display_name . ' (' . ($request['employer_role'] ?? 'کارفرما') . ')',
        'ایمیل کارفرما: ' . $employer->user_email,
    ];
    if (!empty($request['project'])) {
        $lines[] = 'پروژه / نقش: ' . $request['project'];
    }
    $lines[] = '';
    $lines[] = 'متن درخواست:';
    $lines[] = $request['message'];
    $lines[] = '';
    $lines[] = 'برای قبول یا رد درخواست وارد پنل شوید:';
    $lines[] = $login_url;
    $lines[] = '';
    $lines[] = '— ' . $brand;

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $employer->display_name . ' <' . $employer->user_email . '>',
    ];

    $sent = wp_mail($to, $subject, implode("\n", $lines), $headers);
    return $sent ? ['ok' => true] : ['ok' => false, 'error' => 'wp_mail failed'];
}

function casting_mail_employer_response(WP_User $employer, WP_User $talent, array $request): array
{
    $to = $employer->user_email;
    if (!is_email($to)) {
        return ['ok' => false, 'error' => 'ایمیل کارفرما معتبر نیست.'];
    }

    $brand = casting_brand();
    $status_label = ($request['status'] ?? '') === 'accepted' ? 'قبول' : 'رد';
    $subject = sprintf('[%s] پاسخ هنرمند (%s): %s', $brand, $status_label, $talent->display_name);

    $lines = [
        'سلام ' . $employer->display_name . '،',
        '',
        'هنرمند «' . $talent->display_name . '» به درخواست شما پاسخ داد.',
        'نتیجه: ' . $status_label,
    ];
    if (!empty($request['project'])) {
        $lines[] = 'پروژه: ' . $request['project'];
    }
    if (!empty($request['reply'])) {
        $lines[] = '';
        $lines[] = 'نظر هنرمند:';
        $lines[] = $request['reply'];
    }
    $lines[] = '';
    $lines[] = 'ایمیل هنرمند: ' . $talent->user_email;
    $lines[] = '';
    $lines[] = '— ' . $brand;

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $talent->display_name . ' <' . $talent->user_email . '>',
    ];

    $sent = wp_mail($to, $subject, implode("\n", $lines), $headers);
    return $sent ? ['ok' => true] : ['ok' => false, 'error' => 'wp_mail failed'];
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_get_talent_requests(int $talent_id): array
{
    $inbox = get_user_meta($talent_id, 'casting_requests', true);
    return is_array($inbox) ? $inbox : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_get_employer_sent_requests(int $employer_id): array
{
    $outbox = get_user_meta($employer_id, 'casting_sent_requests', true);
    return is_array($outbox) ? $outbox : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_user_requests(int $user_id): array
{
    $role = casting_get_user_role($user_id);
    if ($role === 'talent') {
        return casting_get_talent_requests($user_id);
    }
    if (casting_is_employer_role($role)) {
        return casting_get_employer_sent_requests($user_id);
    }

    return [];
}

function casting_user_request_count(int $user_id): int
{
    return count(casting_user_requests($user_id));
}

function casting_request_status_label(string $status): string
{
    if ($status === 'accepted') {
        return 'قبول شده';
    }
    if ($status === 'rejected') {
        return 'رد شده';
    }
    if ($status === 'pending' || $status === 'new') {
        return 'در انتظار پاسخ';
    }
    return $status;
}

function casting_render_talent_requests_list(int $user_id, array $requests, string $form_action = 'my-requests.php'): void
{
    if ($requests === []) {
        echo '<p class="meta">هنوز درخواستی نیامده است.</p>';
        return;
    }
    ?>
    <div class="request-list">
      <?php foreach ($requests as $req) :
          $status = (string) ($req['status'] ?? 'pending');
          if ($status === 'new') {
              $status = 'pending';
          }
          $pending = $status === 'pending';
          ?>
        <article class="request-item status-<?= casting_e($status) ?>">
          <header>
            <strong><?= casting_e((string) ($req['employer'] ?? 'کارفرما')) ?></strong>
            <span><?= casting_e((string) ($req['employer_role'] ?? '')) ?></span>
            <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
            <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
          </header>
          <?php if (!empty($req['project'])) : ?>
            <p><strong>پروژه:</strong> <?= casting_e((string) $req['project']) ?></p>
          <?php endif; ?>
          <p><?= nl2br(casting_e((string) ($req['message'] ?? ''))) ?></p>
          <?php if ($pending) : ?>
            <form class="form request-reply-form" method="post" action="<?= casting_e($form_action) ?>">
              <?php wp_nonce_field('casting_respond_request'); ?>
              <input type="hidden" name="request_id" value="<?= casting_e((string) ($req['id'] ?? '')) ?>">
              <div class="field">
                <label for="reply-<?= casting_e((string) ($req['id'] ?? '')) ?>">نظر شما (اختیاری)</label>
                <textarea id="reply-<?= casting_e((string) ($req['id'] ?? '')) ?>" name="reply" rows="3" maxlength="2000"></textarea>
              </div>
              <div class="cta-row">
                <button class="btn btn-primary" type="submit" name="decision" value="accepted">قبول</button>
                <button class="btn btn-reject" type="submit" name="decision" value="rejected">رد</button>
                <?php if (!empty($req['employer_id']) && casting_can_users_chat($user_id, (int) $req['employer_id'])['ok']) : ?>
                  <a class="btn btn-ghost" href="chat.php?with=<?= (int) $req['employer_id'] ?>">پاسخ در پیام</a>
                <?php endif; ?>
              </div>
            </form>
          <?php elseif (!empty($req['reply'])) : ?>
            <p class="request-reply"><strong>پاسخ شما:</strong> <?= nl2br(casting_e((string) $req['reply'])) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}

function casting_render_employer_sent_requests_list(int $employer_id, array $requests): void
{
    if ($requests === []) {
        ?>
    <p class="meta">هنوز درخواستی نفرستاده‌اید. از <a href="search-users.php">جستجوی کاربران</a> شروع کنید.</p>
        <?php
        return;
    }
    ?>
    <div class="request-list">
      <?php foreach ($requests as $req) :
          $status = (string) ($req['status'] ?? 'pending');
          if ($status === 'new') {
              $status = 'pending';
          }
          ?>
        <article class="request-item status-<?= casting_e($status) ?>">
          <header>
            <strong><?= casting_e((string) ($req['talent_name'] ?? 'کاربر')) ?></strong>
            <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
            <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
          </header>
          <?php if (!empty($req['project'])) : ?>
            <p><strong>پروژه:</strong> <?= casting_e((string) $req['project']) ?></p>
          <?php endif; ?>
          <p><?= nl2br(casting_e((string) ($req['message'] ?? ''))) ?></p>
          <?php if (!empty($req['reply'])) : ?>
            <p class="request-reply"><strong>پاسخ هنرمند:</strong> <?= nl2br(casting_e((string) $req['reply'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($req['talent_id'])) : ?>
            <div class="cta-row">
              <a class="btn btn-ghost" href="member.php?id=<?= (int) $req['talent_id'] ?>">مشاهده پروفایل</a>
              <?php if (casting_can_users_chat($employer_id, (int) $req['talent_id'])['ok']) : ?>
                <a class="btn btn-ghost" href="chat.php?with=<?= (int) $req['talent_id'] ?>">پیام</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}
