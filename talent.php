<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
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

$error = '';
$project = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_request_' . $id)) {
        $error = 'نشست منقضی شده. دوباره تلاش کنید.';
    } else {
        $project = (string) ($_POST['project'] ?? '');
        $message = (string) ($_POST['message'] ?? '');
        $result = casting_send_talent_request((int) $viewer->ID, $id, $message, $project);
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            if (!empty($result['warning'])) {
                casting_set_flash('error', $result['warning']);
            } else {
                casting_set_flash('success', 'درخواست ارسال شد و ایمیل برای هنرجو رفت.');
            }
            casting_redirect('talent.php?id=' . $id);
        }
    }
}

$genders = casting_gender_labels();
$gender_label = $genders[$profile['gender']] ?? '—';
$looks = casting_look_labels();
$look_label = $looks[$profile['look']] ?? ($profile['look'] !== '' ? $profile['look'] : '—');

casting_render_head($talent->display_name, 'page-talent-view');
casting_render_header('talents');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
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
          <li><strong>تاریخ تولد:</strong> <?= casting_e($profile['birthdate'] !== '' ? casting_format_jalali_from_gregorian($profile['birthdate']) : '—') ?></li>
          <li><strong>جنسیت:</strong> <?= casting_e($gender_label) ?></li>
          <li><strong>چهره:</strong> <?= casting_e($look_label) ?></li>
          <li><strong>شهر:</strong> <?= casting_e($profile['city'] !== '' ? $profile['city'] : '—') ?></li>
          <li><strong>محل سکونت:</strong> <?= casting_e($profile['residence'] !== '' ? $profile['residence'] : '—') ?></li>
          <li><strong>قد:</strong> <?= casting_e($profile['height'] !== '' ? $profile['height'] . ' سانتی‌متر' : '—') ?></li>
          <li><strong>سابقه:</strong> <?= casting_e($profile['experience'] !== '' ? $profile['experience'] . ' سال' : '—') ?></li>
          <li><strong>مهارت‌ها:</strong> <?= casting_e($profile['skills'] !== '' ? $profile['skills'] : '—') ?></li>
        </ul>
        <div class="cta-row">
          <?php if ($profile['video_file_url'] !== '') : ?>
            <a class="btn btn-primary" href="<?= casting_e($profile['video_file_url']) ?>" target="_blank" rel="noopener noreferrer">ویدیو آپلودشده</a>
          <?php endif; ?>
          <?php if ($profile['video_url'] !== '') : ?>
            <a class="btn btn-ghost" href="<?= casting_e($profile['video_url']) ?>" target="_blank" rel="noopener noreferrer">لینک ویدیو</a>
          <?php endif; ?>
          <a class="btn btn-primary" href="#request-box">ارسال درخواست</a>
        </div>
      </div>
    </div>

    <?php if (!empty($profile['work_credits'])) : ?>
      <div class="bio-block">
        <h2>فیلم و تئاتر</h2>
        <ul class="credit-list">
          <?php
          $types = casting_work_type_labels();
          foreach ($profile['work_credits'] as $credit) :
              $type_label = $types[$credit['type']] ?? 'اثر';
              ?>
            <li><span class="credit-type"><?= casting_e($type_label) ?></span> <?= casting_e($credit['title']) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($profile['work_history'] !== '') : ?>
      <div class="bio-block">
        <h2>توضیح سابقه کاری</h2>
        <p><?= nl2br(casting_e($profile['work_history'])) ?></p>
      </div>
    <?php endif; ?>

    <?php if (!empty($profile['education_items'])) : ?>
      <div class="bio-block">
        <h2>سابقه تحصیلی</h2>
        <ul class="credit-list">
          <?php
          $degrees = casting_education_degree_labels();
          foreach ($profile['education_items'] as $item) :
              $degree_label = $degrees[$item['degree']] ?? 'مدرک';
              ?>
            <li><span class="credit-type"><?= casting_e($degree_label) ?></span> <?= casting_e($item['university']) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($profile['education'] !== '') : ?>
      <div class="bio-block">
        <h2>توضیح تحصیل</h2>
        <p><?= nl2br(casting_e($profile['education'])) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($profile['bio'] !== '') : ?>
      <div class="bio-block">
        <h2>درباره</h2>
        <p><?= nl2br(casting_e($profile['bio'])) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($profile['video_file_url'] !== '') : ?>
      <div class="bio-block">
        <h2>ویدیو معرفی</h2>
        <video class="profile-video" controls src="<?= casting_e($profile['video_file_url']) ?>"></video>
      </div>
    <?php endif; ?>

    <div class="bio-block request-box" id="request-box">
      <h2>ارسال درخواست همکاری</h2>
      <p class="meta">با ارسال درخواست، یک ایمیل به هنرجو می‌رود و در پنلش هم دیده می‌شود.</p>
      <form class="form" method="post" action="talent.php?id=<?= $id ?>">
        <?php wp_nonce_field('casting_request_' . $id); ?>
        <div class="field">
          <label for="project">نام پروژه / نقش (اختیاری)</label>
          <input id="project" name="project" type="text" value="<?= casting_e($project) ?>" placeholder="مثلاً نقش اصلی فیلم کوتاه…">
        </div>
        <div class="field">
          <label for="message">متن درخواست</label>
          <textarea id="message" name="message" rows="4" required maxlength="2000" placeholder="توضیح کوتاه درباره همکاری…"><?= casting_e($message) ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit">ارسال درخواست و ایمیل</button>
      </form>
    </div>
  </section>
</main>
<?php casting_render_footer(); ?>
