<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/reporter-news.php';
$user = casting_require_casting_user();
$uid = (int) $user->ID;
casting_nocache();
$reviewer = casting_news_reviewer($uid);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['news_manage'])) {
    if (!$reviewer || !wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'casting_news_manage')) {
        http_response_code(403);
        exit('دسترسی غیرمجاز یا نشست منقضی شده است.');
    }
    try {
        casting_news_move($uid, (string) ($_POST['owner'] ?? ''), (string) ($_POST['id'] ?? ''), (string) ($_POST['state'] ?? ''));
        casting_set_flash('success', 'وضعیت خبر تغییر کرد.');
    } catch (Throwable $e) { casting_set_flash('error', $e->getMessage()); }
    $tab = in_array((string) ($_POST['state'] ?? ''), ['inbox', 'archived', 'deleted'], true) ? (string) $_POST['state'] : 'inbox';
    casting_redirect('reporters.php?news_tab=' . $tab . '#news-management');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Release the PHP session lock so reconnect/status requests can proceed.
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    try { casting_news_api($uid); }
    catch (Throwable $e) { casting_news_json(['error' => $e->getMessage()], 400); }
}
$reviewer = casting_news_reviewer($uid);
if (isset($_GET['download'])) {
    if (!$reviewer) { http_response_code(403); exit; }
    $id = (string) $_GET['download'];
    $owner = (string) ($_GET['owner'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/D', $id) || !preg_match('/^[1-9][0-9]*$/D', $owner)) { http_response_code(404); exit; }
    $base = casting_news_dir() . '/' . $owner . '/' . $id;
    $meta = is_file($base . '.json') ? json_decode((string) file_get_contents($base . '.json'), true) : null;
    if (empty($meta['done']) || !is_file($base . '.bin')) { http_response_code(404); exit; }
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="news-media"; filename*=UTF-8\'\'' . rawurlencode($meta['name']));
    header('Content-Length: ' . filesize($base . '.bin'));
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    readfile($base . '.bin');
    exit;
}
casting_render_panel_start('خبرنگاران', 'reporters');
casting_render_flash();
?>
<section class="dash-card">
  <?php casting_render_panel_heading('خبرنگاران'); ?>
  <p>خبر خود را برای پایگاه خبری 7رخ ارسال کنید. خبر و فایل ارسالی فقط برای eshahabian و ardavan قابل مشاهده است.</p>
  <form id="reporter-news-form" class="form" data-user="<?= $uid ?>">
    <?php wp_nonce_field('casting_news'); ?>
    <div class="field"><label for="reporter-name">نام و نام خانوادگی خبرنگار</label><input id="reporter-name" value="<?= casting_e((string) $user->display_name) ?>" readonly></div>
    <div class="field"><label for="news-title">تیتر خبر</label><input id="news-title" name="title" required></div>
    <div class="field"><label for="news-lead">لید خبر</label><textarea id="news-lead" name="lead" rows="3" required></textarea></div>
    <div class="field"><label for="news-body">متن خبر</label><textarea id="news-body" name="body" rows="10" required></textarea></div>
    <div class="field"><label for="news-media">عکس یا ویدیو (اختیاری)</label><input id="news-media" type="file"><p class="field-hint">همه فرمت‌ها پذیرفته می‌شوند؛ سقف حجم در پورتال تعیین نشده است. پس از بستن صفحه، برای ادامه همان فایل را دوباره انتخاب کنید.</p></div>
    <button class="btn btn-primary" type="submit">ارسال</button>
    <progress id="news-progress" max="100" value="0" hidden style="width:100%"></progress>
    <p id="news-status" role="status" aria-live="polite"></p>
  </form>
</section>
<?php if ($reviewer): ?>
<section class="dash-card" id="news-management">
  <h2>مدیریت خبرها</h2>
  <p>خبرها را پس از بررسی به آرشیو منتقل کنید. حذف، خبر را به بخش حذف‌شده‌ها می‌برد و متن و فایل آن محفوظ می‌ماند.</p>
  <?php
  try {
      $rows = [];
      foreach (glob(casting_news_dir() . '/*/*.json') ?: [] as $path) {
          $row = json_decode((string) file_get_contents($path), true);
          if (!empty($row['done'])) { $rows[] = $row; }
      }
      usort($rows, static fn(array $a, array $b): int => strcmp($b['created'], $a['created']));
      $tabs = ['inbox' => 'خبرهای دریافتی', 'archived' => 'آرشیو خبرها', 'deleted' => 'حذف‌شده‌ها'];
      $active_tab = isset($tabs[(string) ($_GET['news_tab'] ?? '')]) ? (string) $_GET['news_tab'] : 'inbox';
      echo '<nav class="news-tabs" aria-label="بخش‌های مدیریت خبرها">';
      foreach ($tabs as $state => $label) {
          $count = count(array_filter($rows, static fn(array $row): bool => casting_news_state($row) === $state));
          echo '<a class="news-tab" href="' . casting_e(casting_url('reporters.php?news_tab=' . $state . '#news-management')) . '"' . ($state === $active_tab ? ' aria-current="page"' : '') . '>' . casting_e($label) . ' <span class="news-tab-count">' . $count . '</span></a>';
      }
      echo '</nav>';
      foreach ($tabs as $state => $label) {
          if ($state !== $active_tab) { continue; }
          $items = array_values(array_filter($rows, static fn(array $row): bool => casting_news_state($row) === $state));
          echo '<section class="news-tab-content" aria-label="' . casting_e($label) . '">';
          if (!$items) { echo '<p class="empty-state">خبری در این بخش نیست.</p>'; }
          $day = '';
          foreach ($items as $row) {
              // Group by original submission date so archive organization stays stable.
              $row_day = casting_news_date($row['created']);
              if ($row_day !== $day) {
                  echo '<h3>' . casting_e($row_day) . '</h3>';
                  $day = $row_day;
              }
              casting_news_render_card($row);
          }
          echo '</section>';
      }
  } catch (Throwable $e) { echo '<p role="alert">' . casting_e($e->getMessage()) . '</p>'; }
  ?>
</section>
<?php endif; ?>
<link rel="stylesheet" href="<?= casting_e(casting_asset('css/reporter-news.css')) ?>">
<script src="<?= casting_e(casting_asset('js/reporter-news.js')) ?>" defer></script>
<?php casting_render_panel_end(); ?>
