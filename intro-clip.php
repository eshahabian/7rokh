<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/intro-clip.php';

if (!casting_current_user()) {
    $_SESSION['casting_login_intent'] = 'negah';
    casting_redirect('login.php?intent=negah');
}

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$can_upload = casting_user_can_upload_intro_clip($user_id);
$is_admin = casting_intro_clip_is_portal_admin($user_id);
$mobile = casting_intro_clip_mobile($user_id);
$can_submit = $can_upload && ($mobile !== '' || $is_admin);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (casting_upload_post_too_large()) {
        casting_set_flash('error', casting_upload_post_too_large_message());
        casting_redirect('intro-clip.php');
    }
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_intro_clip')) {
        casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
        casting_redirect('intro-clip.php');
    }
    $action = sanitize_key((string) ($_POST['intro_action'] ?? 'upload'));
    if ($action === 'delete') {
        $res = casting_intro_clip_delete($user_id, (string) ($_POST['clip_id'] ?? ''));
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'اثر حذف شد. می‌توانید اثر دیگری بفرستید.' : $res['error']);
    } else {
        $res = casting_intro_clip_save_upload($user_id, 'intro_clip');
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'اثر ذخیره شد.' : $res['error']);
    }
    casting_redirect('intro-clip.php');
}

$clips = casting_intro_clip_rows($user_id);

casting_render_panel_start('۷رخ از نگاه تو', 'intro-clip', 'page-panel page-negah');
casting_render_flash();
?>
<div class="negah-banner-frame">
  <img
    class="negah-banner-photo"
    src="<?= casting_e(casting_asset('images/negah-banner.jpg')) ?>"
    alt="۷رخ از نگاه تو — مسابقه تولید محتوا و ساخت تیزر تبلیغاتی، جایزه ۴۹ میلیون تومان"
    width="1024"
    height="512"
  >
</div>
<section class="dash-card">
  <?php casting_render_panel_heading('۷رخ از نگاه تو'); ?>
  <p class="lede">اثر را همین‌جا بفرستید. مدت اثر بین ۳۰ تا ۹۰ ثانیه است و فایل روی هاست با نام و شماره موبایل حساب ذخیره می‌شود. هر ارسال جدا می‌ماند و با تاریخ ارسال از بقیه جدا می‌شود. اگر خواستید، یک اثر را حذف کنید و اثر دیگری بفرستید. ارسال برای اعضایی است که اشتراک ویژه فعال دارند.</p>

  <?php if ($is_admin) : ?>
    <p class="cta-row">
      <a class="btn btn-ghost btn-sm" href="<?= casting_e(casting_url('admin-intro-clips.php')) ?>">کلیپ‌های اعضا</a>
    </p>
  <?php endif; ?>

  <?php if (!$can_upload) : ?>
    <div class="flash flash-error" role="status">
      ارسال اثر فقط برای اعضایی است که اشتراک ویژه فعال دارند.
    </div>
    <p class="cta-row">
      <a class="btn btn-primary" href="<?= casting_e(casting_url('cart.php')) ?>">خرید اشتراک ویژه</a>
    </p>
  <?php elseif ($mobile === '' && !$is_admin) : ?>
    <div class="flash flash-error" role="status">
      شماره موبایل حساب ثبت نشده. فایل باید با نام و شماره شما ذخیره شود.
    </div>
    <p class="cta-row">
      <a class="btn btn-primary" href="<?= casting_e(casting_url('change-phone.php')) ?>">ثبت شماره موبایل</a>
    </p>
  <?php endif; ?>

  <?php if ($can_submit) : ?>
    <form id="intro-clip-form" class="form" method="post" enctype="multipart/form-data" action="intro-clip.php">
      <?php wp_nonce_field('casting_intro_clip'); ?>
      <input type="hidden" name="intro_action" value="upload">
      <div class="field">
        <label for="intro_clip">انتخاب اثر</label>
        <input
          id="intro_clip"
          name="intro_clip"
          type="file"
          accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov"
          required
          data-upload-kind="video"
          data-max-bytes="<?= (int) casting_upload_max_bytes('video') ?>"
        >
        <p class="field-hint">MP4 / WebM / MOV — بین ۳۰ تا ۹۰ ثانیه و حداکثر <?= casting_e(casting_upload_max_label_fa('video')) ?>. نام فایل روی هاست از نام نمایشی و شماره موبایل حساب ساخته می‌شود.</p>
        <p id="intro-clip-duration-note" class="field-hint" hidden></p>
      </div>
      <button class="btn btn-primary" type="submit">ارسال اثر</button>
    </form>
  <?php endif; ?>

  <h2 class="panel-section-title">آثار شما</h2>
  <?php if ($clips === []) : ?>
    <p class="empty-state">هنوز اثری نفرستاده‌اید.</p>
  <?php else : ?>
    <div class="admin-media-list">
      <?php foreach ($clips as $clip) :
          $stream = casting_intro_clip_stream_url($user_id, (string) $clip['id']);
          $missing = casting_intro_clip_absolute_path($user_id, (string) $clip['id']) === '';
          ?>
        <article class="admin-media-card">
          <div class="admin-media-preview">
            <?php if (!$missing && $stream !== '') : ?>
              <video src="<?= casting_e($stream) ?>" controls preload="metadata" playsinline></video>
            <?php else : ?>
              <p class="empty-state">فایل روی هاست پیدا نشد.</p>
            <?php endif; ?>
          </div>
          <div class="admin-media-body">
            <p class="meta">
              تاریخ ارسال: <?= casting_e(casting_intro_clip_format_when((string) $clip['uploaded_at'])) ?>
              · مدت: <?= casting_e(casting_intro_clip_duration_label((float) $clip['duration'])) ?>
            </p>
            <p class="meta" dir="auto">نام فایل روی هاست: <?= casting_e((string) $clip['file']) ?></p>
            <form method="post" action="intro-clip.php" onsubmit="return confirm('این اثر حذف شود؟ بعد از حذف می‌توانید اثر دیگری بفرستید.');">
              <?php wp_nonce_field('casting_intro_clip'); ?>
              <input type="hidden" name="intro_action" value="delete">
              <input type="hidden" name="clip_id" value="<?= casting_e((string) $clip['id']) ?>">
              <button class="btn btn-ghost btn-sm" type="submit">حذف این اثر</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php if ($can_submit) : ?>
<script>
  (function () {
    var input = document.getElementById('intro_clip');
    var note = document.getElementById('intro-clip-duration-note');
    if (!input) return;
    var min = 30;
    var max = 90;
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file || !note) return;
      var url = URL.createObjectURL(file);
      var video = document.createElement('video');
      video.preload = 'metadata';
      video.onloadedmetadata = function () {
        var seconds = video.duration;
        URL.revokeObjectURL(url);
        note.hidden = false;
        if (!isFinite(seconds) || seconds <= 0) {
          note.textContent = 'مدت این فایل در مرورگر خوانده نشد. هنگام ذخیره، سرور بازه ۳۰ تا ۹۰ ثانیه را بررسی می‌کند.';
          return;
        }
        if (seconds < min - 0.5 || seconds > max + 0.5) {
          input.value = '';
          note.textContent = 'این ویدیو حدود ' + Math.round(seconds) + ' ثانیه است. مدت اثر باید بین ۳۰ تا ۹۰ ثانیه باشد.';
          return;
        }
        note.textContent = 'مدت ویدیو حدود ' + Math.round(seconds) + ' ثانیه است.';
      };
      video.onerror = function () {
        URL.revokeObjectURL(url);
        note.hidden = false;
        note.textContent = 'پیش‌نمایش مدت ممکن نشد. سرور هنگام ذخیره، بازه ۳۰ تا ۹۰ ثانیه را بررسی می‌کند.';
      };
      video.src = url;
    });
  })();
</script>
<?php endif; ?>
<?php casting_render_panel_end(); ?>
