<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

$filters = [
    'q'    => (string) ($_GET['q'] ?? ''),
    'role' => (string) ($_GET['role'] ?? ''),
    'city' => (string) ($_GET['city'] ?? ''),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = casting_query_members($user_id, $filters, $page, 20);
$members = $result['users'];
$total = $result['total'];
$pages = max(1, (int) ceil($total / 20));

casting_render_panel_start('جستجوی کاربران', 'search');
casting_render_flash();
?>
<section class="dash-card">
  <h1>جستجوی کاربران</h1>
  <p class="meta"><?= (int) $total ?> کاربر · اعضای ویژه در اولویت نمایش</p>

  <form class="filter-bar" method="get" action="search-users.php">
    <div class="field">
      <label for="q">نام</label>
      <input id="q" name="q" type="search" value="<?= casting_e($filters['q']) ?>" placeholder="جستجو…">
    </div>
    <div class="field">
      <label for="role">نقش</label>
      <select id="role" name="role">
        <option value="">همه</option>
        <?php foreach (CASTING_ROLES as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="city">شهر</label>
      <select id="city" name="city">
        <option value="">همه</option>
        <?php foreach (casting_get_cities() as $city_name) : ?>
          <option value="<?= casting_e($city_name) ?>" <?= $filters['city'] === $city_name ? 'selected' : '' ?>><?= casting_e($city_name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-actions">
      <button class="btn btn-primary" type="submit">جستجو</button>
      <a class="btn btn-ghost" href="search-users.php">پاک کردن</a>
    </div>
  </form>

  <?php if (!$members) : ?>
    <p class="empty-state">کاربری پیدا نشد.</p>
  <?php else : ?>
    <div class="member-grid">
      <?php foreach ($members as $member) : ?>
        <?php casting_render_member_card($member, $user_id); ?>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1) : ?>
      <nav class="pager" aria-label="صفحه‌بندی">
        <?php for ($p = 1; $p <= $pages; $p++) : ?>
          <a class="pager-link <?= $p === $page ? 'is-active' : '' ?>" href="search-users.php?<?= casting_e(http_build_query(array_merge($filters, ['page' => $p]))) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
