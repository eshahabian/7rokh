<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

casting_require_login('employer');

$filters = [
    'q'       => isset($_GET['q']) ? (string) $_GET['q'] : '',
    'gender'  => isset($_GET['gender']) ? (string) $_GET['gender'] : '',
    'city'    => isset($_GET['city']) ? (string) $_GET['city'] : '',
    'age_min' => isset($_GET['age_min']) ? (string) $_GET['age_min'] : '',
    'age_max' => isset($_GET['age_max']) ? (string) $_GET['age_max'] : '',
    'look'    => isset($_GET['look']) ? (string) $_GET['look'] : '',
];

$page = max(1, (int) ($_GET['page'] ?? 1));
$result = casting_query_talents($filters, $page, 12);
$users = $result['users'];
$total = $result['total'];
$pages = max(1, (int) ceil($total / 12));
$genders = casting_gender_labels();
$looks = casting_look_labels();

casting_render_head('هنرجویان', 'page-talents');
casting_render_header('talents');
casting_render_flash();
?>
<main class="wrap dash">
  <section class="dash-card">
    <h1>جستجوی هنرجو</h1>
    <p class="meta"><?= (int) $total ?> هنرجو پیدا شد</p>

    <div class="quick-filters" aria-label="فیلتر سریع جنسیت">
      <?php
      $base = $filters;
      unset($base['gender']);
      $qs_all = http_build_query(array_filter($base, static fn($v) => $v !== '' && $v !== null));
      ?>
      <a class="quick-chip <?= $filters['gender'] === '' ? 'is-active' : '' ?>" href="talents.php<?= $qs_all !== '' ? '?' . casting_e($qs_all) : '' ?>">همه</a>
      <?php foreach ($genders as $key => $label) :
          $q = $filters;
          $q['gender'] = $key;
          $href = 'talents.php?' . http_build_query(array_filter($q, static fn($v) => $v !== '' && $v !== null));
          ?>
        <a class="quick-chip <?= $filters['gender'] === $key ? 'is-active' : '' ?>" href="<?= casting_e($href) ?>"><?= casting_e($label) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="quick-filters" aria-label="فیلتر سریع چهره">
      <?php
      $base_look = $filters;
      unset($base_look['look']);
      $qs_look_all = http_build_query(array_filter($base_look, static fn($v) => $v !== '' && $v !== null));
      ?>
      <a class="quick-chip <?= $filters['look'] === '' ? 'is-active' : '' ?>" href="talents.php<?= $qs_look_all !== '' ? '?' . casting_e($qs_look_all) : '' ?>">همه چهره‌ها</a>
      <?php foreach ($looks as $key => $label) :
          $q = $filters;
          $q['look'] = $key;
          $href = 'talents.php?' . http_build_query(array_filter($q, static fn($v) => $v !== '' && $v !== null));
          ?>
        <a class="quick-chip <?= $filters['look'] === $key ? 'is-active' : '' ?>" href="<?= casting_e($href) ?>"><?= casting_e($label) ?></a>
      <?php endforeach; ?>
    </div>

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
        <label for="look">چهره</label>
        <select id="look" name="look">
          <option value="">همه</option>
          <?php foreach ($looks as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['look'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="age_min">از سن</label>
        <input id="age_min" name="age_min" type="number" min="5" max="100" value="<?= casting_e($filters['age_min']) ?>" placeholder="مثلاً ۱۸">
      </div>
      <div class="field">
        <label for="age_max">تا سن</label>
        <input id="age_max" name="age_max" type="number" min="5" max="100" value="<?= casting_e($filters['age_max']) ?>" placeholder="مثلاً ۳۵">
      </div>
      <div class="field">
        <label for="city">شهر</label>
        <input id="city" name="city" type="text" value="<?= casting_e($filters['city']) ?>">
      </div>
      <div class="filter-actions">
        <button class="btn btn-primary" type="submit">اعمال فیلتر</button>
        <a class="btn btn-ghost" href="talents.php">پاک کردن</a>
      </div>
    </form>

    <?php if (!$users) : ?>
      <p class="empty-state">هنرجویی با این فیلترها پیدا نشد. اگر تازه ثبت‌نام کرده‌اند، از آن‌ها بخواهید پروفایل را تکمیل کنند.</p>
    <?php else : ?>
      <div class="talent-grid">
        <?php foreach ($users as $talent) :
            $pid = (int) $talent->ID;
            $p = casting_get_profile($pid);
            $g = $genders[$p['gender']] ?? '';
            $look_label = $looks[$p['look']] ?? $p['look'];
            ?>
          <a class="talent-card" href="talent.php?id=<?= $pid ?>">
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
