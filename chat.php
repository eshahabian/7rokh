<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();

$user = casting_require_casting_user();
$my_id = (int) $user->ID;
$error = '';
$peer_id = (int) ($_GET['with'] ?? 0);
$request_id = sanitize_text_field((string) ($_GET['request'] ?? ''));
$active_request = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_dm')) {
        $error = 'نشست منقضی شده. صفحه را رفرش کنید.';
    } else {
        $action = (string) ($_POST['action'] ?? 'send');
        if ($action === 'block' || $action === 'unblock') {
            $target = (int) ($_POST['peer_id'] ?? 0);
            if ($action === 'block') {
                $reason = (string) ($_POST['block_reason'] ?? '');
                $res = casting_block_user($my_id, $target, $reason);
            } else {
                $res = casting_unblock_user($my_id, $target);
            }
            casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'انجام شد.' : $res['error']);
            casting_redirect('chat.php?with=' . $target);
        } elseif ($action === 'start') {
            $start_id = (int) ($_POST['peer_id'] ?? 0);
            $allow = casting_can_user_open_dm($my_id, $start_id);
            if (!$allow['ok']) {
                $error = $allow['error'];
            } else {
                casting_redirect('chat.php?with=' . $start_id);
            }
        } elseif ($action === 'close_thread') {
            $target = (int) ($_POST['peer_id'] ?? 0);
            $res = casting_dm_close_thread($my_id, $target);
            casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'گفتگو بسته شد. طرف مقابل دیگر نمی‌تواند پیام بدهد تا دوباره بازش کنید.' : $res['error']);
            casting_redirect('chat.php?with=' . $target);
        } elseif ($action === 'respond_request') {
            $req_id = (string) ($_POST['request_id'] ?? '');
            $result = casting_respond_to_request(
                $my_id,
                $req_id,
                (string) ($_POST['decision'] ?? ''),
                (string) ($_POST['reply'] ?? '')
            );
            if (!$result['ok']) {
                $error = $result['error'];
                $peer_id = (int) ($_POST['peer_id'] ?? $peer_id);
            } else {
                casting_set_flash('success', $result['status'] === 'accepted' ? 'درخواست قبول شد.' : 'درخواست رد شد.');
                casting_redirect('chat.php?with=' . (int) ($_POST['peer_id'] ?? $peer_id) . '&request=' . rawurlencode($req_id) . '#latest');
            }
        } else {
            $to = (int) ($_POST['peer_id'] ?? 0);
            $result = casting_dm_send($my_id, $to, (string) ($_POST['message'] ?? ''));
            if (!$result['ok']) {
                $error = $result['error'];
                $peer_id = $to > 0 ? $to : $peer_id;
            } else {
                casting_redirect('chat.php?with=' . $to . '#latest');
            }
        }
    }
}

$conversations = [];
$contacts = [];
$peer = null;
$thread = [];
$peer_allow = ['ok' => false, 'error' => ''];
$is_blocked = false;
$peer_had_unread = false;
$thread_locked = false;
$thread_closed = false;
$can_close_thread = false;

if ($peer_id > 0) {
    if (!casting_dm_peer_is_listable($peer_id)) {
        casting_redirect('chat.php');
    }
    $peer = get_user_by('id', $peer_id);
    if (!$peer) {
        casting_redirect('chat.php');
    } else {
        $thread_locked = casting_dm_thread_locked_for_user($my_id, $peer_id);
        $thread_closed = casting_dm_thread_is_closed($my_id, $peer_id);
        $can_close_thread = casting_dm_can_close_thread($my_id, $peer_id);
        $peer_had_unread = casting_dm_unread_count($my_id, $peer_id) > 0;
        casting_dm_mark_delivered($my_id, $peer_id);
        if (!$thread_locked) {
            casting_dm_mark_read($my_id, $peer_id);
        }
        if ($request_id !== '') {
            $open = casting_open_request_chat($my_id, $request_id);
            if (!$open['ok']) {
                $error = $error !== '' ? $error : $open['error'];
            } else {
                $active_request = casting_find_user_request($my_id, $request_id);
            }
        }
        if ($thread_locked) {
            $thread = [];
            $peer_allow = [
                'ok'    => false,
                'error' => casting_dm_premium_required_notice_message(),
            ];
        } else {
            $thread = casting_dm_thread($my_id, $peer_id);
            $peer_allow = casting_can_user_send_dm($my_id, $peer_id);
        }
        $is_blocked = casting_is_blocked($my_id, $peer_id);
    }
}

$conversations = casting_dm_conversations($my_id);
if (!function_exists('casting_user_is_super_admin')) {
    require_once __DIR__ . '/includes/admin-access.php';
}
$contacts = casting_dm_allowed_contacts($my_id);
$is_admin_chat = casting_user_is_portal_owner($my_id) || casting_user_is_super_admin($my_id);
$compose_default = '';
$compose_locked = false;
if ($peer_id > 0 && !empty($peer_allow['ok']) && casting_is_employer_role(casting_get_user_role($my_id))) {
    if (casting_employer_must_use_fixed_outreach($my_id) || !$thread) {
        $compose_default = casting_employer_default_outreach_message($my_id);
    }
    $compose_locked = casting_employer_must_use_fixed_outreach($my_id);
}
$employer_free_hint = casting_employer_free_messages_hint($my_id);

casting_render_panel_start('پیام‌های من', 'messages');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card chat-card">
  <?php casting_render_panel_heading('پیام کاربران'); ?>
  <p class="meta">پیام خصوصی · در صورت مزاحمت می‌توانید کاربر را بلاک کنید.</p>
  <?php if ($employer_free_hint !== '') : ?>
    <p class="meta chat-employer-quota"><?= casting_e($employer_free_hint) ?></p>
  <?php endif; ?>

  <div class="chat-layout">
    <aside class="chat-sidebar">
      <h2 class="chat-side-title">گفتگوها</h2>
      <?php if (!$conversations) : ?>
        <p class="empty-state chat-side-empty">هنوز گفتگویی ندارید.</p>
      <?php else : ?>
        <ul class="chat-conv-list">
          <?php foreach ($conversations as $conv) :
              $conv_unread = (int) ($conv['unread'] ?? 0);
              $conv_peer = (int) ($conv['peer_id'] ?? 0);
              if (!casting_dm_peer_is_listable($conv_peer)) {
                  continue;
              }
              $conv_name = trim((string) ($conv['name'] ?? ''));
              if ($conv_name === '') {
                  $conv_name = casting_dm_peer_display_name($conv_peer);
              }
              if ($conv_name === '') {
                  continue;
              }
              ?>
            <li>
              <a class="chat-conv-item<?= $peer_id === $conv_peer ? ' is-active' : '' ?><?= $conv_unread > 0 ? ' has-unread' : '' ?><?= !empty($conv['locked']) ? ' is-locked' : '' ?>" href="chat.php?with=<?= $conv_peer ?>">
                <?php casting_render_chat_avatar($conv_peer, $conv_name, $conv_unread > 0); ?>
                <strong class="chat-conv-name"><?= casting_e($conv_name) ?></strong>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form class="chat-start-form form" method="post" action="chat.php">
        <?php wp_nonce_field('casting_dm'); ?>
        <input type="hidden" name="action" value="start">
        <div class="field">
          <label for="peer_id"><?= $is_admin_chat ? 'پیام به همه اعضا' : 'شروع گفتگوی جدید' ?></label>
          <?php if ($contacts === []) : ?>
            <p class="field-hint">فعلاً مخاطب مجازی برای شروع گفتگو ندارید. دسترسی نقش‌ها را در جدول پیام‌رسان بررسی کنید.</p>
          <?php else : ?>
            <?php if ($is_admin_chat) : ?>
              <input type="search" class="chat-contact-filter" data-chat-contact-filter placeholder="جستجوی عضو…" aria-label="جستجوی عضو" autocomplete="off">
            <?php endif; ?>
            <select id="peer_id" name="peer_id" required size="<?= $is_admin_chat ? '12' : '1' ?>" class="<?= $is_admin_chat ? 'chat-contact-select--admin' : '' ?>" data-chat-contact-select>
              <?php if (!$is_admin_chat) : ?>
                <option value="">انتخاب مخاطب…</option>
              <?php endif; ?>
              <?php foreach ($contacts as $contact) :
                  $cid = (int) ($contact['id'] ?? 0);
                  if ($cid <= 0) {
                      continue;
                  }
                  $cname = (string) ($contact['name'] ?? '');
                  $crole = casting_user_public_role_label($cid);
                  $label = $cname . ($crole !== '' ? ' — ' . $crole : '');
                  ?>
                <option
                  value="<?= $cid ?>"
                  <?= $peer_id === $cid ? 'selected' : '' ?>
                  data-contact-label="<?= casting_e(function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label)) ?>"
                ><?= casting_e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($is_admin_chat) : ?>
              <p class="field-hint"><?= count($contacts) ?> عضو قابل پیام</p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <button class="btn btn-ghost" type="submit" <?= $contacts === [] ? 'disabled' : '' ?>>باز کردن</button>
      </form>
    </aside>

    <div class="chat-main">
      <?php if ($peer && $peer_id > 0) : ?>
        <header class="chat-peer-head">
          <div class="chat-peer-title">
            <?php casting_render_chat_avatar($peer_id, casting_dm_peer_display_name($peer_id), $peer_had_unread); ?>
            <div>
              <strong><?= casting_e(casting_dm_peer_display_name($peer_id)) ?></strong>
              <?php if ($peer_had_unread) : ?>
                <span class="chat-new-badge">پیام جدید</span>
              <?php endif; ?>
              <?php if (casting_dm_peer_role_label($peer_id) !== '') : ?>
              <span><?= casting_e(casting_dm_peer_role_label($peer_id)) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="cta-row">
            <?php if (!casting_user_profile_is_hidden($peer_id)) : ?>
            <a class="btn btn-ghost btn-sm" href="member.php?id=<?= $peer_id ?>">پروفایل</a>
            <?php endif; ?>
            <?php if ($can_close_thread) : ?>
              <form method="post" action="chat.php?with=<?= $peer_id ?>" onsubmit="return confirm('با بستن گفتگو، طرف مقابل دیگر نمی‌تواند پیام بدهد تا دوباره با پیام شما باز شود. ادامه می‌دهید؟');">
                <?php wp_nonce_field('casting_dm'); ?>
                <input type="hidden" name="action" value="close_thread">
                <input type="hidden" name="peer_id" value="<?= $peer_id ?>">
                <button class="btn btn-reject btn-sm" type="submit">بستن گفتگو</button>
              </form>
            <?php endif; ?>
            <?php if ($is_blocked) : ?>
              <form method="post" action="chat.php?with=<?= $peer_id ?>">
                <?php wp_nonce_field('casting_dm'); ?>
                <input type="hidden" name="action" value="unblock">
                <input type="hidden" name="peer_id" value="<?= $peer_id ?>">
                <button class="btn btn-ghost btn-sm" type="submit">رفع بلاک</button>
              </form>
            <?php else : ?>
              <div class="block-user-wrap">
                <?php casting_render_block_user_form('chat.php?with=' . $peer_id, $peer_id, 'casting_dm', 'chat'); ?>
              </div>
            <?php endif; ?>
          </div>
        </header>

        <?php if ($thread_closed) : ?>
          <div class="chat-thread-closed-banner" role="status">
            <?php if (casting_dm_can_reopen_thread($my_id, $peer_id)) : ?>
              <p class="meta">این گفتگو بسته است. با ارسال پیام جدید دوباره باز می‌شود و طرف مقابل می‌تواند پاسخ دهد.</p>
            <?php else : ?>
              <p class="meta">این گفتگو توسط طرف مقابل بسته شده است. تا وقتی دوباره پیام ندهند نمی‌توانید پاسخ بدهید.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (is_array($active_request) && casting_get_user_role($my_id) === 'talent' && casting_request_status_key($active_request) === 'pending') : ?>
          <div class="chat-request-banner">
            <p class="meta">این گفتگو از درخواست همکاری شروع شده است. می‌توانید قبول یا رد کنید، یا مستقیم پیام بدهید.</p>
            <form class="form request-reply-form" method="post" action="chat.php?with=<?= $peer_id ?>&amp;request=<?= casting_e($request_id) ?>">
              <?php wp_nonce_field('casting_dm'); ?>
              <input type="hidden" name="action" value="respond_request">
              <input type="hidden" name="request_id" value="<?= casting_e($request_id) ?>">
              <input type="hidden" name="peer_id" value="<?= $peer_id ?>">
              <div class="field">
                <label for="chat-reply">نظر (اختیاری)</label>
                <textarea id="chat-reply" name="reply" rows="2" maxlength="2000"></textarea>
              </div>
              <div class="cta-row">
                <button class="btn btn-primary" type="submit" name="decision" value="accepted">قبول درخواست</button>
                <button class="btn btn-reject" type="submit" name="decision" value="rejected">رد درخواست</button>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <div class="chat-thread" id="chat-thread">
          <?php if ($thread_locked) : ?>
            <div class="chat-premium-gate">
              <p><?= casting_e(casting_dm_premium_required_notice_message()) ?></p>
              <p class="meta">برای خواندن و پاسخ دادن به پیام‌ها، حساب ویژه را فعال کنید.</p>
              <div class="cta-row">
                <a class="btn btn-primary" href="cart.php">خرید اشتراک</a>
              </div>
            </div>
          <?php elseif (!$thread) : ?>
            <p class="empty-state">هنوز پیامی نیست.</p>
          <?php else : ?>
            <?php foreach ($thread as $msg) : ?>
              <article class="chat-bubble <?= !empty($msg['is_mine']) ? 'is-mine' : '' ?>">
                <header>
                  <strong><?= !empty($msg['is_mine']) ? 'شما' : casting_e(casting_dm_peer_display_name($peer_id)) ?></strong>
                  <time><?= casting_e($msg['created_at']) ?></time>
                </header>
                <p><?= nl2br(casting_e($msg['message'])) ?></p>
                <?php if (!empty($msg['is_mine'])) : ?>
                  <footer class="chat-bubble-foot">
                    <?php casting_render_dm_receipt_ticks((string) ($msg['receipt'] ?? 'sent')); ?>
                  </footer>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
            <div id="latest"></div>
          <?php endif; ?>
        </div>

        <?php if ($peer_allow['ok']) : ?>
          <form class="chat-compose form" method="post" action="chat.php?with=<?= $peer_id ?>">
            <?php wp_nonce_field('casting_dm'); ?>
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="peer_id" value="<?= $peer_id ?>">
            <div class="field">
              <label for="message">پیام شما</label>
              <?php if ($compose_locked) : ?>
                <input type="hidden" name="message" value="<?= casting_e($compose_default) ?>">
                <textarea id="message" rows="8" maxlength="2000" readonly><?= casting_e($compose_default) ?></textarea>
              <?php else : ?>
                <textarea id="message" name="message" rows="8" required maxlength="2000" placeholder="پیامتان را بنویسید…"><?= casting_e($compose_default) ?></textarea>
              <?php endif; ?>
              <?php if ($employer_free_hint !== '') : ?>
                <p class="field-hint"><?= casting_e($employer_free_hint) ?></p>
              <?php endif; ?>
            </div>
            <button class="btn btn-primary" type="submit">ارسال</button>
          </form>
        <?php elseif ($thread_locked) : ?>
          <p class="meta chat-premium-gate-note"><?= casting_e(casting_dm_premium_required_notice_message()) ?></p>
        <?php elseif (casting_user_is_employer_account($my_id)) : ?>
          <div class="chat-premium-gate">
            <p><?= casting_e($peer_allow['error'] !== '' ? $peer_allow['error'] : casting_employer_premium_send_error()) ?></p>
            <div class="cta-row">
              <a class="btn btn-primary" href="cart.php">خرید اشتراک</a>
            </div>
          </div>
        <?php else : ?>
          <p class="meta"><?= casting_e($peer_allow['error']) ?></p>
        <?php endif; ?>
      <?php else : ?>
        <div class="chat-empty-main">
          <p>یک گفتگو انتخاب کنید یا مخاطب جدید باز کنید.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php casting_render_panel_end(); ?>
