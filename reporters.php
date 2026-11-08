<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/reporter-news.php';
$user = casting_require_casting_user();
$uid = (int) $user->ID;
casting_nocache();
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
<section class="dash-card">
  <h2>خبرهای دریافتی</h2>
  <?php
  try {
      $rows = [];
      foreach (glob(casting_news_dir() . '/*/*.json') ?: [] as $path) {
          $row = json_decode((string) file_get_contents($path), true);
          if (!empty($row['done'])) { $rows[] = $row; }
      }
      usort($rows, static fn(array $a, array $b): int => strcmp($b['created'], $a['created']));
      if (!$rows) { echo '<p>هنوز خبری دریافت نشده است.</p>'; }
      foreach ($rows as $row): ?>
        <article class="dash-card">
          <h3><?= casting_e($row['title']) ?></h3>
          <p><?= casting_e($row['reporter']) ?> — <?= casting_e($row['created']) ?></p>
          <p><strong><?= nl2br(casting_e($row['lead'])) ?></strong></p>
          <div style="white-space:pre-wrap;overflow-wrap:anywhere"><?= casting_e($row['body']) ?></div>
          <?php if ($row['size'] > 0): ?>
          <a class="btn btn-ghost" href="<?= casting_e(casting_url('reporters.php?download=' . $row['id'] . '&owner=' . $row['uid'])) ?>">دریافت <?= casting_e($row['name']) ?></a>
          <?php endif; ?>
        </article>
      <?php endforeach;
  } catch (Throwable $e) { echo '<p role="alert">' . casting_e($e->getMessage()) . '</p>'; }
  ?>
</section>
<?php endif; ?>
<script src="<?= casting_e(casting_asset('js/reporter-news.js')) ?>" defer></script>
<?php casting_render_panel_end(); ?>
