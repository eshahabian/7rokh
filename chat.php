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
            $allow = casting_can_users_chat($my_id, $start_id);
            if (!$allow['ok']) {
                $error = $allow['error'];
            } else {
                casting_redirect('chat.php?with=' . $start_id);
            }
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
$contacts = casting_dm_allowed_contacts($my_id);
$peer = null;
$thread = [];
$peer_allow = ['ok' => false, 'error' => ''];
$is_blocked = false;
$peer_had_unread = false;

if ($peer_id > 0) {
    $peer = get_user_by('id', $peer_id);
    if (!$peer || casting_get_user_role($peer_id) === '') {
        $error = $error !== '' ? $error : 'کاربر پیدا نشد.';
        $peer_id = 0;
    } else {
        $peer_had_unread = casting_dm_unread_count($my_id, $peer_id) > 0;
        casting_dm_mark_read($my_id, $peer_id);
        if ($request_id !== '') {
            $open = casting_open_request_chat($my_id, $request_id);
            if (!$open['ok']) {
                $error = $error !== '' ? $error : $open['error'];
            } else {
                $active_request = casting_find_user_request($my_id, $request_id);
            }
        }
        $thread = casting_dm_thread($my_id, $peer_id);
        $peer_allow = casting_can_users_chat($my_id, $peer_id);
        $is_blocked = casting_is_blocked($my_id, $peer_id);
    }
}

$conversations = casting_dm_conversations($my_id);

casting_render_panel_start('پیام کاربران', 'messages');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card chat-card">
  <h1>پیام کاربران</h1>
  <p class="meta">پیام خصوصی · در صورت مزاحمت می‌توانید کاربر را بلاک کنید.</p>

  <div class="chat-layout">
    <aside class="chat-sidebar">
      <h2 class="chat-side-title">گفتگوها</h2>
      <?php if (!$conversations) : ?>
        <p class="empty-state chat-side-empty">هنوز گفتگویی ندارید.</p>
      <?php else : ?>
        <ul class="chat-conv-list">
          <?php foreach ($conversations as $conv) :
              $conv_unread = (int) ($conv['unread'] ?? 0);
              ?>
            <li>
              <a class="chat-conv-item<?= $peer_id === (int) $conv['peer_id'] ? ' is-active' : '' ?><?= $conv_unread > 0 ? ' has-unread' : '' ?>" href="chat.php?with=<?= (int) $conv['peer_id'] ?>">
                <?php casting_render_chat_avatar((int) $conv['peer_id'], (string) $conv['name'], $conv_unread > 0); ?>
                <span class="chat-conv-body">
                  <strong><?= casting_e($conv['name']) ?></strong>
                  <span><?= casting_e(casting_role_label($conv['role'])) ?></span>
                  <em><?= casting_e(casting_chat_preview($conv['last_message'])) ?></em>
                </span>
                <?php if ($conv_unread > 0) : ?>
                  <span class="chat-conv-badge" aria-hidden="true"><?= $conv_unread ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form class="chat-start-form form" method="post" action="chat.php">
        <?php wp_nonce_field('casting_dm'); ?>
        <input type="hidden" name="action" value="start">
        <div class="field">
          <label for="peer_id">شروع گفتگوی جدید</label>
          <select id="peer_id" name="peer_id" required <?= $contacts === [] ? 'disabled' : '' ?>>
            <option value="">انتخاب مخاطب…</option>
            <?php foreach ($contacts as $contact) : ?>
              <option value="<?= (int) $contact['id'] ?>" <?= $peer_id === (int) $contact['id'] ? 'selected' : '' ?>>
                <?= casting_e($contact['name'] . ' — ' . casting_role_label($contact['role'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-ghost" type="submit" <?= $contacts === [] ? 'disabled' : '' ?>>باز کردن</button>
      </form>
    </aside>

    <div class="chat-main">
      <?php if ($peer && $peer_id > 0) : ?>
        <header class="chat-peer-head">
          <div class="chat-peer-title">
            <?php casting_render_chat_avatar($peer_id, (string) $peer->display_name, $peer_had_unread); ?>
            <div>
              <strong><?= casting_e($peer->display_name) ?></strong>
              <?php if ($peer_had_unread) : ?>
                <span class="chat-new-badge">پیام جدید</span>
              <?php endif; ?>
              <span><?= casting_e(casting_role_label(casting_get_user_role($peer_id))) ?></span>
            </div>
          </div>
          <div class="cta-row">
            <a class="btn btn-ghost btn-sm" href="member.php?id=<?= $peer_id ?>">پروفایل</a>
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
          <?php if (!$thread) : ?>
            <p class="empty-state">هنوز پیامی نیست.</p>
          <?php else : ?>
            <?php foreach ($thread as $msg) : ?>
              <article class="chat-bubble <?= !empty($msg['is_mine']) ? 'is-mine' : '' ?>">
                <header>
                  <strong><?= !empty($msg['is_mine']) ? 'شما' : casting_e($peer->display_name) ?></strong>
                  <time><?= casting_e($msg['created_at']) ?></time>
                </header>
                <p><?= nl2br(casting_e($msg['message'])) ?></p>
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
              <textarea id="message" name="message" rows="3" required maxlength="2000" placeholder="پیامتان را بنویسید…"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">ارسال</button>
          </form>
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
