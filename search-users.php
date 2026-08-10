<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/search-saved.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_can_member_search($user_id)) {
    if (
        (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1')
        || (isset($_POST['saved_search_ajax']) && (string) $_POST['saved_search_ajax'] === '1')
    ) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(['ok' => false, 'error' => 'جستجو برای کارگردان‌ها یا اعضای دارای اشتراک ویژه فعال است.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    casting_set_flash('error', 'جستجوی کاربران برای کارگردان‌ها یا اعضای دارای اشتراک ویژه فعال است.');
    casting_redirect('home.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saved_search_action'])) {
    $wants_json = isset($_POST['saved_search_ajax']) && (string) $_POST['saved_search_ajax'] === '1';
    $action = sanitize_key((string) $_POST['saved_search_action']);
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_saved_search')) {
        if ($wants_json) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        casting_set_flash('error', 'درخواست نامعتبر است.');
        casting_redirect('search-users.php');
    }

    if ($action === 'save') {
        $result = casting_saved_search_save(
            $user_id,
            (string) ($_POST['saved_search_name'] ?? ''),
            is_array($_POST['filters'] ?? null) ? $_POST['filters'] : $_POST
        );
        if ($wants_json) {
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode([
                'ok'       => $result['ok'],
                'error'    => $result['error'],
                'item'     => $result['item'] ?? null,
                'searches' => casting_saved_searches_public_list($user_id),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!$result['ok']) {
            casting_set_flash('error', $result['error']);
            casting_redirect('search-users.php');
        }
        $item = $result['item'] ?? null;
        casting_set_flash('success', 'جستجو در حساب شما ذخیره شد.');
        casting_redirect(casting_saved_search_url(
            is_array($item['filters'] ?? null) ? $item['filters'] : [],
            (string) ($item['id'] ?? '')
        ));
    }

    if ($action === 'delete') {
        $result = casting_saved_search_delete($user_id, (string) ($_POST['saved_search_id'] ?? ''));
        if ($wants_json) {
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode([
                'ok'       => $result['ok'],
                'error'    => $result['error'],
                'searches' => casting_saved_searches_public_list($user_id),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!$result['ok']) {
            casting_set_flash('error', $result['error']);
        } else {
            casting_set_flash('success', 'جستجوی ذخیره‌شده حذف شد.');
        }
        casting_redirect('search-users.php');
    }
}

$filters = casting_parse_member_search_filters($_GET);
$filters['viewer_id'] = $user_id;
$active_saved_id = sanitize_key((string) ($_GET['saved'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = casting_query_members($user_id, $filters, $page, 24);
$members = $result['users'];
$total = $result['total'];
$pages = max(1, (int) ceil($total / 24));
$advanced_open = casting_member_search_advanced_filters_active($filters);

if (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1') {
    casting_render_member_search_results($members, $user_id, $total, $page, $pages, $filters);
    exit;
}

$saved_nonce = wp_create_nonce('casting_saved_search');

casting_render_panel_start('جستجوی کاربران', 'search');
casting_render_flash();
?>
<section class="dash-card dash-card-search">
  <h1>کشف استعداد</h1>
  <p class="lede">عکس‌ها را اسکن کنید؛ برای جزئیات، پیام و دنبال کردن روی هدشات بزنید.</p>

  <?php casting_render_saved_searches_bar($user_id, $filters, $active_saved_id); ?>

  <form id="member-search-filters" class="filter-bar filter-bar-wide filter-bar-headshot filter-bar--sticky" method="get" action="search-users.php" data-member-search-form data-saved-search-nonce="<?= casting_e($saved_nonce) ?>">
    <div class="filter-primary">
      <?php casting_render_member_search_activity_fields($filters); ?>
      <?php casting_render_member_search_gender_field($filters); ?>
      <?php casting_render_body_metric_search_fields($filters, ['age']); ?>
      <?php casting_render_location_fields($filters['province'], $filters['city'], '', false, 'filter-activity-fields'); ?>
    </div>

    <?php casting_render_member_search_quick_chips($filters); ?>

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
      <button type="button" class="btn btn-ghost" data-saved-search-open<?= casting_member_search_filters_active($filters) ? '' : ' disabled' ?>>ذخیره این جستجو</button>
    </div>
  </form>

  <div id="member-search-results" class="member-search-results" data-member-search-results>
    <?php casting_render_member_search_results($members, $user_id, $total, $page, $pages, $filters); ?>
  </div>
</section>
<?php casting_render_panel_end(); ?>
