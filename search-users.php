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
    casting_redirect('home.php');
}

$filters = casting_parse_member_search_filters($_GET);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = casting_query_members($user_id, $filters, $page, 24);
$members = $result['users'];
$total = $result['total'];
$pages = max(1, (int) ceil($total / 24));
$search_active = casting_member_search_filters_active($filters);
$advanced_open = casting_member_search_advanced_filters_active($filters);

if (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1') {
    casting_render_member_search_results($members, $user_id, $total, $page, $pages, $filters);
    exit;
}

casting_render_panel_start('جستجوی کاربران', 'search');
casting_render_flash();
?>
<section class="dash-card dash-card-search">
  <div class="member-search-results-anchor member-search-results-anchor--top" data-member-search-results-anchor="top"<?= $search_active ? '' : ' hidden' ?>>
    <?php if ($search_active) : ?>
      <div id="member-search-results" data-member-search-results>
        <?php casting_render_member_search_results($members, $user_id, $total, $page, $pages, $filters); ?>
      </div>
    <?php endif; ?>
  </div>

  <h1>کشف استعداد</h1>
  <p class="lede">عکس‌ها را اسکن کنید؛ برای جزئیات، پیام و دنبال کردن روی هدشات بزنید.</p>

  <form class="filter-bar filter-bar-wide filter-bar-headshot" method="get" action="search-users.php" data-member-search-form>
    <div class="filter-primary">
      <?php casting_render_member_search_activity_fields($filters); ?>
      <?php casting_render_member_search_gender_field($filters); ?>
      <?php casting_render_body_metric_search_fields($filters, ['age']); ?>
      <?php casting_render_location_fields($filters['province'], $filters['city'], '', false, 'filter-activity-fields'); ?>
    </div>

    <details class="filter-details"<?= $advanced_open ? ' open' : '' ?>>
      <summary>فیلترهای بیشتر</summary>
      <div class="filter-advanced">
        <?php casting_render_body_metric_search_fields($filters, ['height', 'weight']); ?>
        <?php casting_render_member_search_appearance_fields($filters); ?>
        <?php casting_render_member_search_skill_org_fields($filters); ?>
        <?php casting_render_member_search_phase1_fields($filters); ?>
      </div>
    </details>

    <div class="filter-actions">
      <button class="btn btn-primary" type="submit">جستجو</button>
      <a class="btn btn-ghost" href="search-users.php">پاک کردن</a>
    </div>
  </form>

  <div class="member-search-results-anchor member-search-results-anchor--bottom" data-member-search-results-anchor="bottom"<?= $search_active ? ' hidden' : '' ?>>
    <?php if (!$search_active) : ?>
      <div id="member-search-results" data-member-search-results>
        <?php casting_render_member_search_results($members, $user_id, $total, $page, $pages, $filters); ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php casting_render_panel_end(); ?>
