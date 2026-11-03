<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/intro-clip.php';

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
        $res = casting_intro_clip_delete($user_id);
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'کلیپ حذف شد.' : $res['error']);
    } else {
        $res = casting_intro_clip_save_upload($user_id, 'intro_clip');
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'کلیپ ذخیره شد.' : $res['error']);
    }
    casting_redirect('intro-clip.php');
}

$clip = casting_intro_clip_get($user_id);
$stream = $clip !== null ? casting_intro_clip_stream_url($user_id) : '';
$missing = $clip !== null && casting_intro_clip_absolute_path($user_id) === '';

casting_render_panel_start('۷رخ از نگاه تو', 'intro-clip', 'page-panel page-negah');
casting_render_flash();
?>
<div class="negah-banner-frame">
<section class="negah-banner" aria-label="فراخوان ۷رخ از نگاه تو">
  <div class="negah-banner-brand">
    <img src="<?= casting_e(casting_asset('img/logo.png')) ?>" alt="۷رخ" width="72" height="84">
  </div>
  <div class="negah-banner-copy">
    <h2>۷رخ از نگاه تو</h2>
    <p>مسابقه تولید محتوا<br>و ساخت تیزر تبلیغاتی</p>
  </div>
  <div class="negah-banner-art" aria-hidden="true">
    <div class="negah-filmstrip">
      <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
    <svg class="negah-masks" viewBox="0 0 120 78" fill="none">
      <path d="M18 18c0-8 8-14 20-14s20 6 20 14v16c0 10-8 20-20 24-12-4-20-14-20-24V18z" stroke="#c6a15a" stroke-width="1.6"/>
      <path d="M28 28c1.2 2 2.2 2 3.4 0M44 28c1.2 2 2.2 2 3.4 0" stroke="#c6a15a" stroke-width="1.4" stroke-linecap="round"/>
      <path d="M32 46c4 4 10 4 14 0" stroke="#c6a15a" stroke-width="1.4" stroke-linecap="round"/>
      <path d="M62 18c0-8 8-14 20-14s20 6 20 14v16c0 10-8 20-20 24-12-4-20-14-20-24V18z" stroke="#c6a15a" stroke-width="1.6"/>
      <path d="M72 28c1.2-2 2.2-2 3.4 0M88 28c1.2-2 2.2-2 3.4 0" stroke="#c6a15a" stroke-width="1.4" stroke-linecap="round"/>
      <path d="M76 48c4-4 10-4 14 0" stroke="#c6a15a" stroke-width="1.4" stroke-linecap="round"/>
    </svg>
  </div>
  <div class="negah-banner-prize">
    <span>جایزه:</span>
    <strong>۵۰ میلیون تومان</strong>
    <i></i>
    <ul>
      <li>مدت اثر: ۳۰ تا ۹۰ ثانیه</li>
      <li>آخرین مهلت ارسال: ۱۵ آذر ۱۴۰۵</li>
    </ul>
  </div>
  <p class="negah-banner-send">ارسال اثر از طریق پرتال ۷رخ</p>
  <p class="negah-banner-legal">حق استفاده از تمامی آثار ارسالی در فضای مجازی و بستر اینترنت، برای ۷رخ محفوظ است.</p>
</section>
</div>
<section class="dash-card">
  <?php casting_render_panel_heading('۷رخ از نگاه تو'); ?>
  <p class="lede">اثر را همین‌جا بفرستید. مدت اثر بین ۳۰ تا ۹۰ ثانیه است و فایل روی هاست با نام و شماره موبایل حساب ذخیره می‌شود. اگر قبلاً اثری فرستاده باشید، فایل جدید جای قبلی را می‌گیرد. ارسال برای اعضایی است که اشتراک ویژه فعال دارند. مدیران پورتال از این شرط مستثنی هستند.</p>

  <?php if ($is_admin) : ?>
    <p class="cta-row">
      <a class="btn btn-ghost btn-sm" href="<?= casting_e(casting_url('admin-intro-clips.php')) ?>">کلیپ‌های اعضا</a>
    </p>
  <?php endif; ?>

  <?php if (!$can_upload) : ?>
    <div class="flash flash-error" role="status">
      ارسال کلیپ فقط برای اعضایی است که اشتراک ویژه فعال دارند. مدیران پورتال از این محدودیت مستثنی هستند.
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
        <label for="intro_clip"><?= $clip !== null ? 'جایگزینی اثر' : 'انتخاب اثر' ?></label>
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
      <button class="btn btn-primary" type="submit"><?= $clip !== null ? 'جایگزینی اثر' : 'ارسال اثر' ?></button>
    </form>
  <?php endif; ?>

  <h2 class="panel-section-title">اثر شما</h2>
  <?php if ($clip === null) : ?>
    <p class="empty-state">هنوز اثری نفرستاده‌اید.</p>
  <?php elseif ($missing || $stream === '') : ?>
    <p class="empty-state">اطلاعات کلیپ ثبت شده، ولی فایل روی هاست پیدا نشد.</p>
  <?php else : ?>
    <article class="admin-media-card">
      <div class="admin-media-preview">
        <video src="<?= casting_e($stream) ?>" controls preload="metadata" playsinline></video>
      </div>
      <div class="admin-media-body">
        <p class="meta">
          مدت: <?= casting_e(casting_intro_clip_duration_label((float) $clip['duration'])) ?>
          · <?= casting_e(casting_intro_clip_format_when((string) $clip['uploaded_at'])) ?>
        </p>
        <p class="meta" dir="auto">نام فایل روی هاست: <?= casting_e((string) $clip['file']) ?></p>
        <?php if (!$can_upload) : ?>
          <p class="field-hint">برای جایگزینی کلیپ، اشتراک ویژه باید فعال باشد. مدیران پورتال از این شرط مستثنی هستند.</p>
        <?php endif; ?>
        <form method="post" action="intro-clip.php" onsubmit="return confirm('این کلیپ حذف شود؟');">
          <?php wp_nonce_field('casting_intro_clip'); ?>
          <input type="hidden" name="intro_action" value="delete">
          <button class="btn btn-ghost btn-sm" type="submit">حذف کلیپ</button>
        </form>
      </div>
    </article>
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
