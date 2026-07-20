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

if (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1') {
    casting_render_member_search_results($members, $user_id, $total, $page, $pages, $filters);
    exit;
}

casting_render_panel_start('جستجوی کاربران', 'search');
casting_render_flash();
?>
<section class="dash-card">
  <h1>جستجوی کاربران</h1>

  <form class="filter-bar filter-bar-wide" method="get" action="search-users.php" data-member-search-form>
    <?php casting_render_member_search_talent_cluster($filters); ?>

    <div class="field field-name-search">
      <label for="member-name-q">نام</label>
      <div class="name-search-field" data-name-search-field>
        <span class="name-search-ruler" data-name-search-ruler aria-hidden="true"></span>
        <div class="name-search-type">
          <input
            id="member-name-q"
            name="q"
            type="text"
            inputmode="search"
            value="<?= casting_e($filters['q']) ?>"
            placeholder="نام یا نام کاربری…"
            autocomplete="off"
            spellcheck="false"
            data-name-search-input
          >
          <span class="name-search-suffix" data-name-search-ghost aria-hidden="true"></span>
        </div>
        <button type="button" class="name-search-clear" data-name-search-clear hidden aria-label="پاک کردن">×</button>
      </div>
    </div>

    <div class="field">
      <label for="gender">جنسیت</label>
      <select id="gender" name="gender">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($genders as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['gender'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="look">پوست</label>
      <select id="look" name="look">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($looks as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['look'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php casting_render_health_search_field($filters); ?>

    <?php casting_render_body_metric_search_fields($filters); ?>

    <?php casting_render_location_fields($filters['province'], $filters['city'], '', false, 'filter-location-inline'); ?>

    <?php casting_render_member_search_phase1_fields($filters); ?>

    <?php casting_render_member_search_phase2_fields($filters); ?>

    <div class="field">
      <label for="availability">همکاری</label>
      <select id="availability" name="availability">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
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

  <div id="member-search-results" data-member-search-results>
    <?php casting_render_member_search_results($members, $user_id, $total, $page, $pages, $filters); ?>
  </div>
</section>
<?php casting_render_panel_end(); ?>
