<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

$viewer = casting_require_login('employer');
$id = (int) ($_GET['id'] ?? 0);
$talent = $id > 0 ? get_user_by('id', $id) : false;

if (!$talent || casting_get_user_role((int) $talent->ID) !== 'talent') {
    casting_set_flash('error', 'هنرجو پیدا نشد.');
    casting_redirect('talents.php');
}

$profile = casting_get_profile((int) $talent->ID);
if (!$profile['visible'] && (int) $viewer->ID !== (int) $talent->ID) {
    casting_set_flash('error', 'این پروفایل فعلاً قابل مشاهده نیست.');
    casting_redirect('talents.php');
}

$genders = casting_gender_labels();
$gender_label = $genders[$profile['gender']] ?? '—';

casting_render_head($talent->display_name, 'page-talent-view');
casting_render_header('talents');
casting_render_flash();
?>
<main class="wrap dash">
  <section class="dash-card profile-view">
    <a class="back-link" href="talents.php">← بازگشت به لیست</a>

    <div class="profile-hero">
      <div class="profile-photo">
        <?php if ($profile['photo_full'] !== '' || $profile['photo_url'] !== '') : ?>
          <img src="<?= casting_e($profile['photo_full'] !== '' ? $profile['photo_full'] : $profile['photo_url']) ?>" alt="<?= casting_e($talent->display_name) ?>">
        <?php else : ?>
          <div class="photo-placeholder tall">بدون عکس</div>
        <?php endif; ?>
      </div>
      <div class="profile-info">
        <span class="chip">هنرجو</span>
        <h1><?= casting_e($talent->display_name) ?></h1>
        <ul class="info-list">
          <li><strong>سن:</strong> <?= casting_e($profile['age'] !== '' ? $profile['age'] . ' سال' : '—') ?></li>
          <li><strong>جنسیت:</strong> <?= casting_e($gender_label) ?></li>
          <li><strong>شهر:</strong> <?= casting_e($profile['city'] !== '' ? $profile['city'] : '—') ?></li>
          <li><strong>قد:</strong> <?= casting_e($profile['height'] !== '' ? $profile['height'] . ' سانتی‌متر' : '—') ?></li>
          <li><strong>سابقه:</strong> <?= casting_e($profile['experience'] !== '' ? $profile['experience'] . ' سال' : '—') ?></li>
          <li><strong>تیپ:</strong> <?= casting_e($profile['look'] !== '' ? $profile['look'] : '—') ?></li>
          <li><strong>مهارت‌ها:</strong> <?= casting_e($profile['skills'] !== '' ? $profile['skills'] : '—') ?></li>
        </ul>
        <?php if ($profile['video_url'] !== '') : ?>
          <a class="btn btn-primary" href="<?= casting_e($profile['video_url']) ?>" target="_blank" rel="noopener noreferrer">مشاهده ویدیو</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($profile['bio'] !== '') : ?>
      <div class="bio-block">
        <h2>درباره / سوابق</h2>
        <p><?= nl2br(casting_e($profile['bio'])) ?></p>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php casting_render_footer(); ?>
