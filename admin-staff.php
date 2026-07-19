<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_require_admin_permission('manage_staff');

$error = '';
$search = trim((string) ($_GET['q'] ?? ''));
$target_id = (int) ($_GET['user'] ?? 0);
$results = $search !== '' ? casting_admin_search_casting_users($search) : [];
$staff_list = casting_list_staff_users();
$permission_defs = casting_admin_permission_definitions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_staff'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_staff')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $target_id = (int) ($_POST['target_id'] ?? 0);
        $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
        $result = casting_save_user_staff_permissions($target_id, $perms, $user_id);
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            casting_set_flash('success', 'دسترسی‌ها ذخیره شد.');
            casting_redirect('admin-staff.php?user=' . $target_id);
        }
    }
}

$target = $target_id > 0 ? get_user_by('id', $target_id) : false;
$target_perms = $target ? casting_user_staff_permissions($target_id) : [];
$target_super = $target ? casting_user_is_super_admin($target_id) : false;

casting_render_panel_start('دسترسی مدیران', 'admin-staff');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card">
  <h1>دسترسی مدیران</h1>
  <p class="meta">به کاربران مختلف فقط دسترسی‌های لازم را بدهید — مثل تأیید فیش، تراکنش‌ها، تعلیق و رفع بلاک.</p>

  <form class="form admin-search-form" method="get" action="admin-staff.php">
    <div class="field">
      <label for="q">جستجوی کاربر</label>
      <input id="q" name="q" type="search" value="<?= casting_e($search) ?>" placeholder="نام، ایمیل یا نام کاربری">
    </div>
    <button class="btn btn-primary" type="submit">جستجو</button>
  </form>

  <?php if ($search !== '' && !$results) : ?>
    <p class="empty-state">کاربری پیدا نشد.</p>
  <?php elseif ($results) : ?>
    <ul class="admin-user-pick-list">
      <?php foreach ($results as $row) : ?>
        <li>
          <a href="admin-staff.php?user=<?= (int) $row['id'] ?>">
            <strong><?= casting_e($row['name']) ?></strong>
            <span class="meta"><?= casting_e($row['login']) ?> · <?= casting_e(casting_role_label($row['role'])) ?></span>
            <?php if ($row['staff']) : ?><span class="chip">مدیر</span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($target && casting_get_user_role($target_id) !== '') : ?>
    <div class="admin-staff-editor">
      <h2 class="panel-section-title"><?= casting_e($target->display_name) ?> <span class="meta">@<?= casting_e($target->user_login) ?></span></h2>
      <?php if ($target_super) : ?>
        <p class="meta">این کاربر مدیر اصلی است و همه دسترسی‌ها را دارد.</p>
      <?php else : ?>
        <form class="form" method="post" action="admin-staff.php?user=<?= $target_id ?>">
          <?php wp_nonce_field('casting_admin_staff'); ?>
          <input type="hidden" name="save_staff" value="1">
          <input type="hidden" name="target_id" value="<?= $target_id ?>">
          <fieldset class="field admin-perm-grid">
            <legend>دسترسی‌ها</legend>
            <?php foreach ($permission_defs as $key => $label) :
                if ($key === 'manage_staff' && !casting_user_is_super_admin($user_id)) {
                    continue;
                }
                ?>
              <label class="check-row">
                <input type="checkbox" name="permissions[]" value="<?= casting_e($key) ?>" <?= in_array($key, $target_perms, true) ? 'checked' : '' ?>>
                <span><?= casting_e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <button class="btn btn-primary" type="submit">ذخیره دسترسی‌ها</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($staff_list) : ?>
    <div class="admin-staff-list">
      <h2 class="panel-section-title">مدیران فعلی</h2>
      <ul class="panel-list">
        <?php foreach ($staff_list as $row) : ?>
          <li class="panel-list-item">
            <div>
              <strong><?= casting_e($row['name']) ?></strong>
              <span class="meta">@<?= casting_e($row['login']) ?><?= $row['super'] ? ' · مدیر اصلی' : '' ?></span>
              <?php if (!$row['super'] && $row['permissions']) : ?>
                <span class="meta"><?= casting_e(implode('، ', array_map(static fn(string $k): string => $permission_defs[$k] ?? $k, $row['permissions']))) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!$row['super']) : ?>
              <a class="btn btn-ghost btn-sm" href="admin-staff.php?user=<?= (int) $row['id'] ?>">ویرایش</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
