<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/intro-clip.php';

$user = casting_require_casting_user();
$admin_id = (int) $user->ID;
if (!casting_user_can_review_intro_clips($admin_id)) {
    wp_die('فقط مدیران پورتال (eshahabian و ardavan) به این بخش دسترسی دارند.', 'دسترسی غیرمجاز', ['response' => 403]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_admin_intro_clip')) {
        casting_set_flash('error', 'نشست منقضی شده.');
        casting_redirect('admin-intro-clips.php');
    }
    $action = sanitize_key((string) ($_POST['intro_action'] ?? ''));
    $owner_id = (int) ($_POST['user_id'] ?? 0);
    if ($action === 'delete' && $owner_id > 0) {
        $res = casting_intro_clip_delete($owner_id);
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'کلیپ حذف شد.' : $res['error']);
    }
    casting_redirect('admin-intro-clips.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$items = casting_intro_clip_list();
if ($q !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $items = array_values(array_filter($items, static function (array $row) use ($needle): bool {
        $hay = $row['name'] . ' ' . $row['login'] . ' ' . $row['mobile'] . ' ' . $row['file'];
        $hay = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);

        return str_contains($hay, $needle);
    }));
}

casting_render_panel_start('کلیپ‌های اعضا', 'admin-intro-clips');
casting_render_flash();
?>
<section class="dash-card">
  <?php casting_render_panel_heading('کلیپ‌های اعضا'); ?>

  <form class="form" method="get" action="admin-intro-clips.php">
    <div class="field">
      <label for="intro-clip-q">جستجو با نام، نام کاربری یا شماره</label>
      <input id="intro-clip-q" name="q" type="search" value="<?= casting_e($q) ?>" placeholder="مثلاً نام یا ۰۹۱۲…">
    </div>
    <button class="btn btn-ghost btn-sm" type="submit">جستجو</button>
  </form>

  <h2 class="panel-section-title"><?= (int) count($items) ?> کلیپ</h2>
  <?php if ($items === []) : ?>
    <p class="empty-state"><?= $q !== '' ? 'موردی با این جستجو پیدا نشد.' : 'هنوز کسی کلیپ نفرستاده است.' ?></p>
  <?php else : ?>
    <div class="admin-media-list">
      <?php foreach ($items as $item) :
          $owner_id = (int) $item['user_id'];
          $stream = $item['missing'] ? '' : casting_intro_clip_stream_url($owner_id);
          ?>
        <article class="admin-media-card">
          <div class="admin-media-preview">
            <?php if ($stream !== '') : ?>
              <video src="<?= casting_e($stream) ?>" controls preload="metadata" playsinline></video>
            <?php else : ?>
              <p class="empty-state">فایل روی هاست پیدا نشد.</p>
            <?php endif; ?>
          </div>
          <div class="admin-media-body">
            <h2><?= casting_e((string) $item['name']) ?></h2>
            <p class="meta">
              @<?= casting_e((string) $item['login']) ?>
              <?php if ((string) $item['mobile'] !== '') : ?>
                · <span dir="ltr"><?= casting_e((string) $item['mobile']) ?></span>
              <?php else : ?>
                · بدون شماره
              <?php endif; ?>
            </p>
            <p class="meta">
              مدت: <?= casting_e(casting_intro_clip_duration_label((float) $item['duration'])) ?>
              · <?= casting_e(casting_intro_clip_format_when((string) $item['uploaded_at'])) ?>
            </p>
            <p class="meta" dir="auto">فایل: <?= casting_e((string) $item['file']) ?></p>
            <div class="cta-row">
              <a class="btn btn-ghost btn-sm" href="<?= casting_e(casting_url('member.php?id=' . $owner_id)) ?>">پروفایل</a>
              <form method="post" action="admin-intro-clips.php" onsubmit="return confirm('کلیپ این کاربر حذف شود؟');">
                <?php wp_nonce_field('casting_admin_intro_clip'); ?>
                <input type="hidden" name="intro_action" value="delete">
                <input type="hidden" name="user_id" value="<?= $owner_id ?>">
                <button class="btn btn-ghost btn-sm" type="submit">حذف</button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
