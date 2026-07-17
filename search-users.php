<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

$filters = casting_parse_member_search_filters($_GET);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = casting_query_members($user_id, $filters, $page, 20);
$members = $result['users'];
$total = $result['total'];
$pages = max(1, (int) ceil($total / 20));

$genders = casting_gender_labels();
$looks = casting_look_labels();
$availability_labels = casting_availability_labels();

casting_render_panel_start('جستجوی کاربران', 'search');
casting_render_flash();
?>
<section class="dash-card">
  <h1>جستجوی کاربران</h1>
  <p class="meta"><?= (int) $total ?> کاربر · اعضای ویژه در اولویت نمایش</p>

  <form class="filter-bar filter-bar-wide" method="get" action="search-users.php">
    <div class="field">
      <label for="gender">جنسیت</label>
      <select id="gender" name="gender">
        <option value="">همه</option>
        <?php foreach ($genders as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['gender'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="look">پوست</label>
      <select id="look" name="look">
        <option value="">همه</option>
        <?php foreach ($looks as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['look'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="health_status">سلامت</label>
      <input id="health_status" name="health_status" type="search" value="<?= casting_e($filters['health_status']) ?>" placeholder="کلمه…">
    </div>

    <?php casting_render_body_metric_search_fields($filters); ?>

    <?php casting_render_location_fields($filters['province'], $filters['city'], '', false, 'filter-location-inline'); ?>

    <?php casting_render_member_search_talent_cluster($filters); ?>

    <?php casting_render_member_search_phase1_fields($filters); ?>

    <?php casting_render_member_search_phase2_fields($filters); ?>

    <div class="field">
      <label for="availability">همکاری</label>
      <select id="availability" name="availability">
        <option value="">همه</option>
        <?php foreach ($availability_labels as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['availability'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
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
