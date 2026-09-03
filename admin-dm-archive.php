<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_super_admin($user_id) && !casting_user_is_portal_owner($user_id)) {
    wp_die('فقط مدیر اصلی به این بخش دسترسی دارد.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;
$archives = casting_dm_admin_list_archives($per_page, $offset);

casting_render_panel_start('آرشیو پیام‌های ویرایش‌شده', 'admin-dm-archive');
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>آرشیو پیام‌های ویرایش‌شده</h1>
  <p class="lede">هر بار کاربر پیامی را ویرایش کند، متن اصلی (قبل از ویرایش) اینجا ذخیره می‌شود. نسخهٔ فعلی در گفتگو برای طرف مقابل نمایش داده می‌شود.</p>

  <?php if ($archives === []) : ?>
    <p class="empty-state">هنوز پیام ویرایش‌شده‌ای در آرشیو نیست.</p>
  <?php else : ?>
    <div class="admin-dm-archive-list">
      <?php foreach ($archives as $row) : ?>
        <article class="admin-dm-archive-item">
          <header class="admin-dm-archive-head">
            <strong>#<?= (int) $row['message_id'] ?></strong>
            <time><?= casting_e((string) $row['archived_at']) ?></time>
          </header>
          <ul class="info-list admin-dm-archive-meta">
            <li><strong>فرستنده:</strong> <?= casting_e((string) $row['sender_name']) ?> <span class="meta">(#<?= (int) $row['sender_id'] ?>)</span></li>
            <li><strong>گیرنده:</strong> <?= casting_e((string) $row['recipient_name']) ?> <span class="meta">(#<?= (int) $row['recipient_id'] ?>)</span></li>
          </ul>
          <div class="admin-dm-archive-body">
            <h3 class="panel-section-title">متن اصلی (آرشیو)</h3>
            <p><?= nl2br(casting_e((string) $row['body'])) ?></p>
          </div>
          <?php if ((string) ($row['current_message'] ?? '') !== '' && (string) $row['current_message'] !== (string) $row['body']) : ?>
            <div class="admin-dm-archive-body admin-dm-archive-current">
              <h3 class="panel-section-title">متن فعلی در گفتگو</h3>
              <p><?= nl2br(casting_e((string) $row['current_message'])) ?></p>
            </div>
          <?php endif; ?>
          <p class="meta">
            <a href="chat.php?with=<?= (int) $row['recipient_id'] ?>">مشاهده گفتگو (فرستنده)</a>
          </p>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if (count($archives) >= $per_page) : ?>
      <p class="meta"><a href="admin-dm-archive.php?page=<?= $page + 1 ?>">صفحه بعد →</a></p>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
