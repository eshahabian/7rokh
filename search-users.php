<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_can_member_search($user_id)) {
    if (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'جستجو برای کارگردان‌ها یا اعضای دارای اشتراک ویژه فعال است.';
        exit;
    }
    casting_set_flash('error', 'جستجوی کاربران برای کارگردان‌ها یا اعضای دارای اشتراک ویژه فعال است.');
    casting_redirect('panel.php');
}

$filters = casting_parse_member_search_filters($_GET);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = casting_query_members($user_id, $filters, $page, 20);
$members = $result['users'];
$total = $result['total'];
$pages = max(1, (int) ceil($total / 20));

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

    <?php casting_render_member_search_profile_cluster($filters); ?>

    <?php casting_render_body_metric_search_fields($filters, ['height', 'weight', 'age']); ?>

    <?php casting_render_location_fields($filters['province'], $filters['city'], '', false, 'filter-location-inline'); ?>

    <?php casting_render_member_search_phase1_fields($filters); ?>

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
