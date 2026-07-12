<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$user = casting_current_user();
$role = $user ? casting_get_user_role((int) $user->ID) : '';
$can_post = $user && $role !== '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_post) {
        casting_set_flash('error', 'برای ارسال پیام باید ثبت‌نام کرده و وارد شده باشید.');
        casting_redirect('chat.php');
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_chat')) {
        $error = 'نشست منقضی شده. صفحه را رفرش کنید.';
    } else {
        $result = casting_chat_post((int) $user->ID, (string) ($_POST['message'] ?? ''));
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            casting_redirect('chat.php#latest');
        }
    }
}

$messages = casting_chat_list(100);
$my_id = $user ? (int) $user->ID : 0;

casting_render_head('تالار گفتگو', 'page-chat');
casting_render_header('chat');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<main class="wrap dash">
  <section class="dash-card chat-card">
    <h1>تالار گفتگو</h1>
    <p class="meta">
      همه می‌توانند گفتگو را ببینند.
      <?php if ($can_post) : ?>
        شما وارد شده‌اید و می‌توانید پیام بفرستید.
      <?php else : ?>
        برای ارسال پیام باید <a href="register.php">ثبت‌نام</a> کنید یا وارد شوید.
      <?php endif; ?>
    </p>

    <div class="chat-thread" id="chat-thread">
      <?php if (!$messages) : ?>
        <p class="empty-state">هنوز پیامی نیست<?= $can_post ? '. اولین نفر باشید.' : '.' ?></p>
      <?php else : ?>
        <?php foreach ($messages as $msg) : ?>
          <article class="chat-bubble <?= $my_id > 0 && (int) $msg['user_id'] === $my_id ? 'is-mine' : '' ?>">
            <header>
              <strong><?= casting_e($msg['name']) ?></strong>
              <span><?= casting_e(casting_role_label($msg['role'])) ?></span>
              <time><?= casting_e($msg['created_at']) ?></time>
            </header>
            <p><?= nl2br(casting_e($msg['message'])) ?></p>
          </article>
        <?php endforeach; ?>
        <div id="latest"></div>
      <?php endif; ?>
    </div>

    <?php if ($can_post) : ?>
      <form class="chat-compose form" method="post" action="chat.php">
        <?php wp_nonce_field('casting_chat'); ?>
        <div class="field">
          <label for="message">پیام شما</label>
          <textarea id="message" name="message" rows="3" required maxlength="1000" placeholder="پیامتان را بنویسید…"></textarea>
        </div>
        <button class="btn btn-primary" type="submit">ارسال</button>
      </form>
    <?php else : ?>
      <div class="chat-locked">
        <p>فقط اعضای ثبت‌نام‌شده می‌توانند پیام بفرستند. بازدیدکنندگان فقط می‌بینند.</p>
        <div class="cta-row">
          <a class="btn btn-primary" href="register.php">ثبت‌نام</a>
          <a class="btn btn-ghost" href="login-talent.php">ورود هنرجو</a>
          <a class="btn btn-ghost" href="login-employer.php">ورود کارفرما</a>
        </div>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php casting_render_footer(); ?>
