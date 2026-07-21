<?php
declare(strict_types=1);

require_once __DIR__ . '/blocks.php';
require_once __DIR__ . '/chat-rules.php';
require_once __DIR__ . '/chat.php';

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
        'seen_at'       => '',
        'employer_seen_at' => '',
        'chat_seeded'   => false,
        'reply_in_chat' => false,
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

    if (!empty($found['chat_seeded'])) {
        $reply = trim("💬 پاسخ هنرمند:\n" . (string) $found['reply']);
        if ($reply !== '' && casting_dm_insert_raw($talent_id, (int) $found['employer_id'], $reply, (string) $found['replied_at'])) {
            $found['reply_in_chat'] = true;
            casting_update_request_everywhere($found);
        }
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

function casting_find_user_request(int $user_id, string $request_id): ?array
{
    $request_id = trim($request_id);
    if ($request_id === '') {
        return null;
    }
    foreach (casting_user_requests($user_id) as $req) {
        if (is_array($req) && (string) ($req['id'] ?? '') === $request_id) {
            return $req;
        }
    }

    return null;
}

function casting_request_status_key(array $req): string
{
    $status = sanitize_key((string) ($req['status'] ?? 'pending'));
    return $status === 'new' ? 'pending' : $status;
}

function casting_request_is_unread(int $user_id, array $req): bool
{
    $role = casting_get_user_role($user_id);
    $status = casting_request_status_key($req);

    if ($role === 'talent') {
        return $status === 'pending' && (string) ($req['seen_at'] ?? '') === '';
    }
    if (casting_is_employer_role($role)) {
        if ($status === 'pending') {
            return false;
        }

        return (string) ($req['employer_seen_at'] ?? '') === '';
    }

    return false;
}

function casting_user_new_request_count(int $user_id): int
{
    $count = 0;
    foreach (casting_user_requests($user_id) as $req) {
        if (is_array($req) && casting_request_is_unread($user_id, $req)) {
            $count++;
        }
    }

    return $count;
}

function casting_request_seed_chat(array $request): void
{
    if (!empty($request['chat_seeded'])) {
        return;
    }

    $employer_id = (int) ($request['employer_id'] ?? 0);
    $talent_id = (int) ($request['talent_id'] ?? 0);
    if ($employer_id <= 0 || $talent_id <= 0) {
        return;
    }

    $lines = ['📋 درخواست همکاری'];
    if (!empty($request['project'])) {
        $lines[] = 'پروژه: ' . (string) $request['project'];
    }
    $lines[] = '';
    $lines[] = (string) ($request['message'] ?? '');
    $message = trim(implode("\n", $lines));
    if ($message === '') {
        return;
    }

    $created_at = (string) ($request['created_at'] ?? '');
    if ($created_at === '') {
        $created_at = current_time('mysql');
    }

    casting_dm_insert_raw($employer_id, $talent_id, $message, $created_at);
}

/**
 * @return array{ok:bool,error:string,peer_id?:int,request_id?:string}
 */
function casting_open_request_chat(int $user_id, string $request_id): array
{
    $req = casting_find_user_request($user_id, $request_id);
    if ($req === null) {
        return ['ok' => false, 'error' => 'درخواست پیدا نشد.'];
    }

    $role = casting_get_user_role($user_id);
    if ($role === 'talent') {
        $peer_id = (int) ($req['employer_id'] ?? 0);
        if ($peer_id <= 0) {
            return ['ok' => false, 'error' => 'کارفرما پیدا نشد.'];
        }
        $allow = casting_can_users_chat($user_id, $peer_id);
        if (!$allow['ok']) {
            return $allow;
        }

        $updated = $req;
        if ((string) ($updated['seen_at'] ?? '') === '') {
            $updated['seen_at'] = current_time('mysql');
        }
        if (empty($updated['chat_seeded'])) {
            casting_request_seed_chat($updated);
            $updated['chat_seeded'] = true;
        }
        casting_update_request_everywhere($updated);

        return ['ok' => true, 'error' => '', 'peer_id' => $peer_id, 'request_id' => $request_id];
    }

    if (casting_is_employer_role($role)) {
        $peer_id = (int) ($req['talent_id'] ?? 0);
        if ($peer_id <= 0) {
            return ['ok' => false, 'error' => 'هنرمند پیدا نشد.'];
        }
        $allow = casting_can_users_chat($user_id, $peer_id);
        if (!$allow['ok']) {
            return $allow;
        }

        $updated = $req;
        if ((string) ($updated['employer_seen_at'] ?? '') === '') {
            $updated['employer_seen_at'] = current_time('mysql');
        }
        if (empty($updated['chat_seeded'])) {
            casting_request_seed_chat($updated);
            $updated['chat_seeded'] = true;
        }
        if (
            (string) ($updated['reply'] ?? '') !== ''
            && casting_request_status_key($updated) !== 'pending'
            && empty($updated['reply_in_chat'])
        ) {
            $reply = trim("💬 پاسخ هنرمند:\n" . (string) $updated['reply']);
            $replied_at = (string) ($updated['replied_at'] ?? '');
            if ($replied_at === '') {
                $replied_at = current_time('mysql');
            }
            if (casting_dm_insert_raw($peer_id, $user_id, $reply, $replied_at)) {
                $updated['reply_in_chat'] = true;
            }
        }
        casting_update_request_everywhere($updated);

        return ['ok' => true, 'error' => '', 'peer_id' => $peer_id, 'request_id' => $request_id];
    }

    return ['ok' => false, 'error' => 'این بخش برای نقش شما فعال نیست.'];
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
          $req_id = (string) ($req['id'] ?? '');
          $status = casting_request_status_key($req);
          $pending = $status === 'pending';
          $is_unread = casting_request_is_unread($user_id, $req);
          $open_url = $req_id !== '' ? 'my-requests.php?open=' . rawurlencode($req_id) : 'my-requests.php';
          ?>
        <article class="request-item status-<?= casting_e($status) ?><?= $is_unread ? ' is-unread' : '' ?>">
          <a class="request-item-open" href="<?= casting_e($open_url) ?>">
            <header>
              <strong><?= casting_e((string) ($req['employer'] ?? 'کارفرما')) ?></strong>
              <span><?= casting_e((string) ($req['employer_role'] ?? '')) ?></span>
              <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
              <?php if ($is_unread) : ?>
                <span class="req-status req-status-new">جدید</span>
              <?php else : ?>
                <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
              <?php endif; ?>
            </header>
            <?php if (!empty($req['project'])) : ?>
              <p><strong>پروژه:</strong> <?= casting_e((string) $req['project']) ?></p>
            <?php endif; ?>
            <p><?= nl2br(casting_e(casting_chat_preview((string) ($req['message'] ?? ''), 160))) ?></p>
            <span class="request-item-cta">مشاهده و گفتگو با کارفرما ←</span>
          </a>
          <?php if ($pending && !$is_unread) : ?>
            <form class="form request-reply-form" method="post" action="<?= casting_e($form_action) ?>">
              <?php wp_nonce_field('casting_respond_request'); ?>
              <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
              <div class="field">
                <label for="reply-<?= casting_e($req_id) ?>">پاسخ سریع (اختیاری)</label>
                <textarea id="reply-<?= casting_e($req_id) ?>" name="reply" rows="2" maxlength="2000"></textarea>
              </div>
              <div class="cta-row">
                <button class="btn btn-primary" type="submit" name="decision" value="accepted">قبول</button>
                <button class="btn btn-reject" type="submit" name="decision" value="rejected">رد</button>
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
          $req_id = (string) ($req['id'] ?? '');
          $status = casting_request_status_key($req);
          $is_unread = casting_request_is_unread($employer_id, $req);
          $open_url = $req_id !== '' ? 'my-requests.php?open=' . rawurlencode($req_id) : 'my-requests.php';
          ?>
        <article class="request-item status-<?= casting_e($status) ?><?= $is_unread ? ' is-unread' : '' ?>">
          <?php if (!empty($req['talent_id'])) : ?>
          <a class="request-item-open" href="<?= casting_e($open_url) ?>">
            <header>
              <strong><?= casting_e((string) ($req['talent_name'] ?? 'کاربر')) ?></strong>
              <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
              <?php if ($is_unread) : ?>
                <span class="req-status req-status-new">پاسخ جدید</span>
              <?php else : ?>
                <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
              <?php endif; ?>
            </header>
            <?php if (!empty($req['project'])) : ?>
              <p><strong>پروژه:</strong> <?= casting_e((string) $req['project']) ?></p>
            <?php endif; ?>
            <p><?= nl2br(casting_e(casting_chat_preview((string) ($req['message'] ?? ''), 160))) ?></p>
            <?php if (!empty($req['reply'])) : ?>
              <p class="request-reply"><strong>پاسخ هنرمند:</strong> <?= nl2br(casting_e((string) $req['reply'])) ?></p>
            <?php endif; ?>
            <span class="request-item-cta">مشاهده و گفتگو ←</span>
          </a>
          <div class="cta-row">
            <a class="btn btn-ghost" href="member.php?id=<?= (int) $req['talent_id'] ?>">مشاهده پروفایل</a>
          </div>
          <?php else : ?>
            <header>
              <strong><?= casting_e((string) ($req['talent_name'] ?? 'کاربر')) ?></strong>
              <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
              <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
            </header>
            <p><?= nl2br(casting_e((string) ($req['message'] ?? ''))) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}
