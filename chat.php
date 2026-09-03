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
        } elseif ($action === 'edit') {
            $message_id = (int) ($_POST['message_id'] ?? 0);
            $result = casting_dm_edit_message($my_id, $message_id, (string) ($_POST['message'] ?? ''));
            if (!$result['ok']) {
                $error = $result['error'];
                $peer_id = (int) ($_POST['peer_id'] ?? $peer_id);
            } else {
                casting_set_flash('success', 'پیام ویرایش شد.');
                casting_redirect('chat.php?with=' . (int) ($_POST['peer_id'] ?? $peer_id) . '#latest');
            }
        } else {
            $to = (int) ($_POST['peer_id'] ?? 0);
            if (!empty($_FILES['photo']['name'])) {
                $result = casting_dm_send_photo($my_id, $to, 'photo');
            } else {
                $result = casting_dm_send($my_id, $to, (string) ($_POST['message'] ?? ''));
            }
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
$compose_default = '';
$compose_locked = false;
if ($peer_id > 0 && !empty($peer_allow['ok']) && casting_is_employer_role(casting_get_user_role($my_id))) {
    if (casting_employer_must_use_fixed_outreach($my_id) || !$thread) {
        $compose_default = casting_employer_default_outreach_message($my_id);
    }
    $compose_locked = casting_employer_must_use_fixed_outreach($my_id);
}
$employer_free_hint = casting_employer_free_messages_hint($my_id);
$thread_last_id = 0;
foreach ($thread as $msg) {
    $thread_last_id = max($thread_last_id, (int) ($msg['id'] ?? 0));
}
$inbox_fp = casting_dm_inbox_fingerprint($my_id);

casting_render_panel_start(
    'پیام‌های من',
    'messages',
    'page-panel page-messages' . ($peer && $peer_id > 0 ? ' page-messages-thread' : '')
);
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card chat-card">
  <?php casting_render_panel_heading('پیام‌ها'); ?>
  <p class="meta">کسانی که به شما پیام داده‌اند. روی هر ردیف بزنید تا گفتگو باز شود. برای شروع پیام جدید، به پروفایل همان کاربر بروید.</p>
  <?php if ($employer_free_hint !== '') : ?>
    <p class="meta chat-employer-quota"><?= casting_e($employer_free_hint) ?></p>
  <?php endif; ?>

  <div class="chat-layout<?= $peer && $peer_id > 0 ? ' chat-layout--thread' : ' chat-layout--inbox' ?>" data-chat-inbox="<?= casting_e($inbox_fp) ?>">
    <aside class="chat-sidebar">
      <h2 class="chat-side-title">گفتگوها</h2>
      <?php if (!$conversations) : ?>
        <p class="empty-state chat-side-empty">هنوز کسی به شما پیام نداده. از پروفایل اعضا می‌توانید پیام بدهید.</p>
      <?php else : ?>
        <ul class="chat-conv-list" data-chat-inbox="<?= casting_e($inbox_fp) ?>">
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
              $conv_last = trim((string) ($conv['last_message'] ?? ''));
              if ($conv_last !== '') {
                  if (function_exists('mb_strlen') && mb_strlen($conv_last, 'UTF-8') > 48) {
                      $conv_last = mb_substr($conv_last, 0, 48, 'UTF-8') . '…';
                  } elseif (strlen($conv_last) > 48) {
                      $conv_last = substr($conv_last, 0, 48) . '…';
                  }
              }
              ?>
            <li>
              <a class="chat-conv-item<?= $peer_id === $conv_peer ? ' is-active' : '' ?><?= $conv_unread > 0 ? ' has-unread' : '' ?><?= !empty($conv['locked']) ? ' is-locked' : '' ?>" href="chat.php?with=<?= $conv_peer ?>">
                <?php casting_render_chat_avatar($conv_peer, $conv_name, $conv_unread > 0); ?>
                <span class="chat-conv-body">
                  <strong class="chat-conv-name"><?= casting_e($conv_name) ?></strong>
                  <?php if ($conv_last !== '') : ?>
                    <span class="chat-conv-snippet"><?= casting_e($conv_last) ?></span>
                  <?php endif; ?>
                </span>
                <?php if ($conv_unread > 0) : ?>
                  <span class="chat-conv-badge"><?= $conv_unread > 9 ? '۹+' : (string) $conv_unread ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </aside>

    <div class="chat-main">
      <?php if ($peer && $peer_id > 0) : ?>
        <header class="chat-peer-head">
          <div class="chat-peer-head-start">
            <a class="chat-back-inbox" href="chat.php" aria-label="بازگشت به گفتگوها">
              <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                <path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="M15 4.5 7.5 12 15 19.5"/>
              </svg>
            </a>
            <?php
            $peer_name = casting_dm_peer_display_name($peer_id);
            $peer_role = casting_dm_peer_role_label($peer_id);
            $peer_profile_open = !casting_user_profile_is_hidden($peer_id);
            ?>
            <?php if ($peer_profile_open) : ?>
              <a class="chat-peer-title" href="member.php?id=<?= $peer_id ?>">
            <?php else : ?>
              <div class="chat-peer-title">
            <?php endif; ?>
                <?php casting_render_chat_avatar($peer_id, $peer_name, $peer_had_unread); ?>
                <span class="chat-peer-text">
                  <strong><?= casting_e($peer_name) ?></strong>
                  <?php if ($peer_had_unread) : ?>
                    <span class="chat-new-badge">پیام جدید</span>
                  <?php endif; ?>
                  <?php if ($peer_role !== '') : ?>
                    <span class="chat-peer-role"><?= casting_e($peer_role) ?></span>
                  <?php endif; ?>
                </span>
            <?php if ($peer_profile_open) : ?>
              </a>
            <?php else : ?>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($peer_profile_open) : ?>
            <a class="btn btn-ghost btn-sm chat-peer-profile-btn" href="member.php?id=<?= $peer_id ?>">پروفایل</a>
          <?php endif; ?>
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

        <div
          class="chat-thread"
          id="chat-thread"
          data-chat-live
          data-peer-id="<?= (int) $peer_id ?>"
          data-last-id="<?= (int) $thread_last_id ?>"
          data-peer-name="<?= casting_e(casting_dm_peer_display_name($peer_id)) ?>"
          data-locked="<?= $thread_locked ? '1' : '0' ?>"
        >
          <?php if ($thread_locked) : ?>
            <div class="chat-premium-gate">
              <?php casting_render_dm_premium_send_notice(); ?>
            </div>
          <?php elseif (!$thread) : ?>
            <p class="empty-state" data-chat-empty>هنوز پیامی نیست.</p>
          <?php else : ?>
            <?php foreach ($thread as $msg) : ?>
              <article
                class="chat-bubble <?= !empty($msg['is_mine']) ? 'is-mine' : '' ?>"
                data-msg-id="<?= (int) ($msg['id'] ?? 0) ?>"
                data-can-edit="<?= !empty($msg['can_edit']) ? '1' : '0' ?>"
                data-edited="<?= !empty($msg['is_edited']) ? '1' : '0' ?>"
                <?php if (!empty($msg['photo_url'])) : ?>data-photo-url="<?= casting_e((string) $msg['photo_url']) ?>"<?php endif; ?>
              >
                <header>
                  <strong><?= !empty($msg['is_mine']) ? 'شما' : casting_e(casting_dm_peer_display_name($peer_id)) ?></strong>
                  <time datetime="<?= casting_e($msg['created_at']) ?>"><?= casting_e($msg['created_at']) ?></time>
                </header>
                <?php casting_render_dm_bubble_body($msg); ?>
                <?php if (!empty($msg['is_mine'])) : ?>
                  <footer class="chat-bubble-foot">
                    <?php casting_render_dm_receipt_ticks((string) ($msg['receipt'] ?? 'sent')); ?>
                  </footer>
                <?php endif; ?>
                <?php casting_render_dm_bubble_actions($msg); ?>
              </article>
            <?php endforeach; ?>
            <div id="latest"></div>
          <?php endif; ?>
        </div>

        <?php if ($peer_allow['ok']) : ?>
          <form class="chat-compose form" method="post" action="chat.php?with=<?= $peer_id ?>" data-chat-live-send enctype="multipart/form-data">
            <?php wp_nonce_field('casting_dm'); ?>
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="peer_id" value="<?= $peer_id ?>">
            <div class="field">
              <label for="message">پیام شما</label>
              <div class="chat-compose-bar">
                <?php if (!$compose_locked) : ?>
                  <label class="chat-compose-attach" title="ارسال عکس">
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" data-chat-photo>
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                      <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M21.4 11.6 12.2 20.8a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 1 1-2.8-2.8l8.5-8.5"/>
                    </svg>
                    <span class="sr-only">ارسال عکس</span>
                  </label>
                <?php endif; ?>
                <div class="chat-compose-input">
                  <?php if ($compose_locked) : ?>
                    <input type="hidden" name="message" value="<?= casting_e($compose_default) ?>">
                    <textarea id="message" rows="1" maxlength="2000" readonly><?= casting_e($compose_default) ?></textarea>
                  <?php else : ?>
                    <textarea id="message" name="message" rows="1" maxlength="2000" placeholder="پیامتان را بنویسید…"><?= casting_e($compose_default) ?></textarea>
                  <?php endif; ?>
                </div>
                <div class="chat-compose-send">
                  <button class="chat-compose-icon-btn" type="button" data-chat-share-compose title="ارسال به چند نفر" aria-label="ارسال به چند نفر">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                      <circle cx="9" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.7"/>
                      <path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M3.6 18.5c.4-2.8 2.6-4.6 5.4-4.6s5 1.8 5.4 4.6"/>
                      <circle cx="16.6" cy="8.4" r="2.4" fill="none" stroke="currentColor" stroke-width="1.7"/>
                      <path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M13.4 18.5c.5-2.1 2.1-3.5 4.2-3.6 2.2.2 3.9 1.6 4.4 3.6"/>
                    </svg>
                  </button>
                  <button class="chat-compose-send-btn" type="submit" title="ارسال" aria-label="ارسال">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                      <path fill="currentColor" d="M3.4 11.2 20.2 3.6c.7-.3 1.4.4 1.1 1.1l-7.6 16.8c-.3.7-1.3.7-1.6 0l-2.4-6.3-6.3-2.4c-.7-.3-.7-1.3 0-1.6Z"/>
                    </svg>
                  </button>
                </div>
              </div>
              <?php if ($employer_free_hint !== '') : ?>
                <p class="field-hint"><?= casting_e($employer_free_hint) ?></p>
              <?php endif; ?>
            </div>
          </form>
        <?php elseif ($thread_locked || (string) ($peer_allow['error'] ?? '') === casting_dm_premium_required_notice_message()) : ?>
          <div class="chat-premium-gate">
            <?php casting_render_dm_premium_send_notice(); ?>
          </div>
        <?php else : ?>
          <p class="meta"><?= casting_e($peer_allow['error']) ?></p>
        <?php endif; ?>

        <aside class="chat-moderation-tile" aria-label="مدیریت گفتگو">
          <?php
          $close_form_id = 'chat-close-thread-' . $peer_id;
          $close_btn_html = '';
          if ($can_close_thread) {
              $close_btn_html = '<button class="btn btn-reject btn-sm chat-moderation-close-btn" type="submit" form="' . casting_e($close_form_id) . '">بستن گفتگو</button>';
          }
          ?>
          <?php if ($can_close_thread) : ?>
            <form id="<?= casting_e($close_form_id) ?>" class="chat-moderation-close-form" method="post" action="chat.php?with=<?= $peer_id ?>" onsubmit="return confirm('با بستن گفتگو، طرف مقابل دیگر نمی‌تواند پیام بدهد تا دوباره با پیام شما باز شود. ادامه می‌دهید؟');">
              <?php wp_nonce_field('casting_dm'); ?>
              <input type="hidden" name="action" value="close_thread">
              <input type="hidden" name="peer_id" value="<?= $peer_id ?>">
            </form>
          <?php endif; ?>
          <?php if ($is_blocked) : ?>
            <div class="chat-moderation-actions">
              <form method="post" action="chat.php?with=<?= $peer_id ?>">
                <?php wp_nonce_field('casting_dm'); ?>
                <input type="hidden" name="action" value="unblock">
                <input type="hidden" name="peer_id" value="<?= $peer_id ?>">
                <button class="btn btn-ghost btn-sm" type="submit">رفع بلاک</button>
              </form>
              <?= $close_btn_html ?>
            </div>
          <?php else : ?>
            <div class="block-user-wrap">
              <?php casting_render_block_user_form('chat.php?with=' . $peer_id, $peer_id, 'casting_dm', 'chat', '', $close_btn_html); ?>
            </div>
          <?php endif; ?>
        </aside>
      <?php else : ?>
        <div class="chat-empty-main">
          <p>یک گفتگو را از فهرست انتخاب کنید. برای شروع پیام، به پروفایل همان کاربر بروید.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php casting_render_panel_end(); ?>
