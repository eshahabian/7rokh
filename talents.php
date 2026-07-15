<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/layout.php';

casting_require_casting_user();

$filters = [
    'q'         => isset($_GET['q']) ? (string) $_GET['q'] : '',
    'gender'    => isset($_GET['gender']) ? (string) $_GET['gender'] : '',
    'city'      => isset($_GET['city']) ? (string) $_GET['city'] : '',
    'age_range' => isset($_GET['age_range']) ? (string) $_GET['age_range'] : '',
    'look'      => isset($_GET['look']) ? (string) $_GET['look'] : '',
];

$page = max(1, (int) ($_GET['page'] ?? 1));
$result = casting_query_talents($filters, $page, 12);
$users = $result['users'];
$total = $result['total'];
$pages = max(1, (int) ceil($total / 12));
$genders = casting_gender_labels();
$looks = casting_look_labels();
$age_ranges = casting_age_range_options();

casting_render_head('هنرمندان', 'page-talents');
casting_render_header('talents');
casting_render_flash();
?>
<main class="wrap dash">
  <section class="dash-card">
    <h1>جستجوی هنرمند</h1>
    <p class="meta"><?= (int) $total ?> هنرمند پیدا شد</p>

    <form class="filter-bar" method="get" action="talents.php">
      <div class="field">
        <label for="q">نام</label>
        <input id="q" name="q" type="search" value="<?= casting_e($filters['q']) ?>" placeholder="جستجوی نام">
      </div>
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
        <label for="look">رنگ پوست</label>
        <select id="look" name="look">
          <option value="">همه</option>
          <?php foreach ($looks as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['look'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="age_range">سن</label>
        <select id="age_range" name="age_range">
          <option value="">همه</option>
          <?php foreach ($age_ranges as $key => $range) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['age_range'] === $key ? 'selected' : '' ?>><?= casting_e($range['label']) ?></option>
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
        <button class="btn btn-primary" type="submit">اعمال فیلتر</button>
        <a class="btn btn-ghost" href="talents.php">پاک کردن</a>
      </div>
    </form>

    <?php if (!$users) : ?>
      <p class="empty-state">هنرمندی با این فیلترها پیدا نشد. اگر تازه ثبت‌نام کرده‌اند، از آن‌ها بخواهید پروفایل را تکمیل کنند.</p>
    <?php else : ?>
      <div class="talent-grid">
        <?php foreach ($users as $talent) :
            $pid = (int) $talent->ID;
            $p = casting_get_profile($pid);
            $g = $genders[$p['gender']] ?? '';
            $look_label = $looks[$p['look']] ?? ($p['look'] === 'gandoum' ? 'سبزه' : $p['look']);
            ?>
          <a class="talent-card" href="member.php?id=<?= $pid ?>">
            <div class="talent-photo">
              <?php if ($p['photo_url'] !== '') : ?>
                <img src="<?= casting_e($p['photo_url']) ?>" alt="<?= casting_e($talent->display_name) ?>" loading="lazy">
              <?php else : ?>
                <div class="photo-placeholder">بدون عکس</div>
              <?php endif; ?>
            </div>
            <div class="talent-body">
              <h2><?= casting_e($talent->display_name) ?></h2>
              <p>
                <?= casting_e($p['age'] !== '' ? $p['age'] . ' سال' : '') ?>
                <?= $g !== '' ? ' · ' . casting_e($g) : '' ?>
                <?= $p['city'] !== '' ? ' · ' . casting_e($p['city']) : '' ?>
              </p>
              <?php if ($look_label !== '') : ?>
                <p class="talent-look"><?= casting_e($look_label) ?><?= $p['experience'] !== '' ? ' · ' . casting_e($p['experience']) . ' سال سابقه' : '' ?></p>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1) : ?>
        <nav class="pager" aria-label="صفحه‌بندی">
          <?php for ($i = 1; $i <= $pages; $i++) :
              $qs = $_GET;
              $qs['page'] = $i;
              $href = 'talents.php?' . http_build_query($qs);
              ?>
            <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= casting_e($href) ?>"><?= $i ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>
<?php casting_render_footer(); ?>
