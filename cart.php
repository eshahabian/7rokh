<?php
declare(strict_types=1);

/**
 * خرید اشتراک — مهمان هم می‌تواند ببیند و انتخاب کند؛ پرداخت نیازمند ورود است
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/gateway.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();

if (casting_request_is_mellat_callback()) {
    casting_gateway_finish_mellat_callback();
}

$user = casting_current_user();
$user_id = $user ? (int) $user->ID : 0;
$logged_in = $user_id > 0 && casting_get_user_role($user_id) !== '';
$error = '';
$auth_needed = false;
$auth_tab = sanitize_key((string) ($_POST['auth_tab'] ?? 'login'));
if ($auth_tab !== 'register') {
    $auth_tab = 'login';
}
$auth_error = '';
$auth_login = '';
$auth_name = '';
$auth_username = '';
$auth_email = '';
$auth_referral_code = '';
$auth_need_confirm = false;
$premium = $logged_in && casting_user_is_premium($user_id);
$can_approve_receipts = $logged_in && casting_user_has_admin_permission($user_id, 'approve_receipts');
$admin_filter = sanitize_key((string) ($_GET['status'] ?? 'pending'));
if (!in_array($admin_filter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $admin_filter = 'pending';
}
$plans = casting_premium_plans();

$cart_continue_checkout = static function (int $uid): void {
    casting_cart_claim_guest_cart();
    $created = casting_cart_create_order_from_cart($uid);
    if (!$created['ok']) {
        casting_set_flash('error', (string) ($created['error'] ?? 'ادامه پرداخت ممکن نشد.'));
        casting_redirect('cart.php');
    }
    casting_redirect('checkout.php?order=' . rawurlencode((string) $created['order']['order_code']));
};

$cart_role_from_items = static function (): string {
    $cart = casting_cart_get();
    foreach ($cart['items'] as $it) {
        if ((string) ($it['service_key'] ?? '') === 'casting_call') {
            return 'director';
        }
    }

    return 'talent';
};

if ($logged_in) {
    casting_cart_claim_guest_cart();
}
casting_cart_sync_count_cookie();

if ($can_approve_receipts && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_receipt'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_receipt')) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
    } else {
        $receipt_id = (int) ($_POST['receipt_id'] ?? 0);
        $action_receipt = (string) ($_POST['action'] ?? '');
        if ($action_receipt === 'approve') {
            $result = casting_approve_premium_receipt($receipt_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'فیش تأیید و حساب کاربری ویژه فعال شد.' : $result['error']);
        } elseif ($action_receipt === 'reject') {
            $result = casting_reject_premium_receipt($receipt_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'فیش رد شد.' : $result['error']);
        }
    }
    casting_redirect('cart.php?status=' . $admin_filter . '#admin-receipts');
}

$action = sanitize_key((string) ($_GET['action'] ?? $_POST['cart_action'] ?? ''));

// افزودن از لینک / کاشی — فقط با nonce (جلوگیری از CSRF روی GET)
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $add_nonce = (string) ($_GET['_wpnonce'] ?? '');
    if ($add_nonce === '' || !wp_verify_nonce($add_nonce, 'casting_cart_add')) {
        casting_set_flash('error', 'درخواست نامعتبر است. از داخل پورتال اضافه کنید.');
        casting_redirect('cart.php');
    }
    $service = sanitize_key((string) ($_GET['service'] ?? ''));
    $plan = sanitize_key((string) ($_GET['plan'] ?? ''));
    $project_id = max(0, (int) ($_GET['project'] ?? 0));
    $result = casting_cart_add($service, $plan, $project_id);
    if ($result['ok']) {
        casting_set_flash('success', 'به خرید اشتراک اضافه شد.');
        casting_redirect('cart.php');
    }
    casting_set_flash('error', $result['error']);
    casting_redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_receipt'])) {
    $action = sanitize_key((string) ($_POST['cart_action'] ?? ''));
    $is_auth_action = $action === 'auth_login' || $action === 'auth_register';
    $nonce_action = $is_auth_action ? 'casting_cart_auth' : 'casting_cart';

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], $nonce_action)) {
        $error = 'درخواست نامعتبر است.';
        if ($is_auth_action) {
            $auth_needed = true;
            $auth_error = $error;
        }
    } elseif ($action === 'auth_login' && !$logged_in) {
        $auth_needed = true;
        $auth_tab = 'login';
        $rate_error = casting_rate_limit_check('login');
        if ($rate_error !== null) {
            $auth_error = $rate_error;
        } else {
            $auth_login = (string) ($_POST['login'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $force_login = !empty($_POST['force_login']);
            $result = casting_login($auth_login, $password, '', $force_login);
            if (!$result['ok']) {
                $auth_need_confirm = !empty($result['need_confirm']);
                if (!$auth_need_confirm) {
                    casting_rate_limit_hit('login');
                }
                $auth_error = (string) ($result['error'] ?? 'ورود ناموفق بود.');
            } else {
                casting_rate_limit_clear('login');
                $uid = (int) ($result['user']->ID ?? 0);
                $cart_continue_checkout($uid);
            }
        }
    } elseif ($action === 'auth_register' && !$logged_in) {
        $auth_needed = true;
        $auth_tab = 'register';
        $rate_error = casting_rate_limit_check('register');
        if ($rate_error !== null) {
            $auth_error = $rate_error;
        } else {
            $auth_name = (string) ($_POST['name'] ?? '');
            $auth_username = (string) ($_POST['username'] ?? '');
            $auth_email = (string) ($_POST['email'] ?? '');
            $auth_referral_code = (string) ($_POST['referral_code'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $password2 = (string) ($_POST['password2'] ?? '');
            if ($password !== $password2) {
                $auth_error = 'تکرار رمز عبور با رمز یکسان نیست.';
            } else {
                if (!function_exists('casting_validate_referral_code_for_register')) {
                    require_once __DIR__ . '/includes/referral.php';
                }
                $referral_check = casting_validate_referral_code_for_register($auth_referral_code);
                if (!$referral_check['ok']) {
                    $auth_error = (string) ($referral_check['error'] ?? 'کد معرفی معتبر نیست.');
                } else {
                    $role = $cart_role_from_items();
                    $result = casting_register_user($auth_name, $auth_username, $auth_email, $password, $role);
                    if (!$result['ok']) {
                        casting_rate_limit_hit('register');
                        $auth_error = (string) ($result['error'] ?? 'ثبت‌نام ناموفق بود.');
                    } else {
                        if (trim($auth_referral_code) !== '' && function_exists('casting_apply_referral_code')) {
                            casting_apply_referral_code((int) $result['user_id'], $auth_referral_code);
                        }
                        $login = casting_login($auth_email, $password, '', true);
                        if (!$login['ok']) {
                            casting_rate_limit_clear('register');
                            $auth_tab = 'login';
                            $auth_login = $auth_email;
                            $auth_error = 'ثبت‌نام شد؛ لطفاً وارد شوید. ' . (string) ($login['error'] ?? '');
                        } else {
                            casting_rate_limit_clear('register');
                            casting_rate_limit_clear('login');
                            update_user_meta((int) $result['user_id'], 'casting_cart_quick_register', '1');
                            $uid = (int) ($login['user']->ID ?? $result['user_id']);
                            $cart_continue_checkout($uid);
                        }
                    }
                }
            }
        }
    } elseif ($action === 'remove') {
        $result = casting_cart_remove((string) ($_POST['item_id'] ?? ''));
        casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'از لیست حذف شد.' : $result['error']);
        casting_redirect('cart.php');
    } elseif ($action === 'clear') {
        casting_cart_clear();
        casting_set_flash('success', 'لیست خرید اشتراک خالی شد.');
        casting_redirect('cart.php');
    } elseif ($action === 'checkout') {
        if (!$logged_in) {
            $auth_needed = true;
        } else {
            $created = casting_cart_create_order_from_cart($user_id);
            if (!$created['ok']) {
                $error = $created['error'];
            } else {
                casting_redirect('checkout.php?order=' . rawurlencode((string) $created['order']['order_code']));
            }
        }
    }
}

$cart = casting_cart_get();
$totals = casting_cart_totals($cart);
$shop_tiles = casting_shop_catalog_tiles();
$tiles_by_group = [];
foreach ($shop_tiles as $tile) {
    $g = (string) ($tile['group'] ?? 'سایر');
    if (!isset($tiles_by_group[$g])) {
        $tiles_by_group[$g] = [];
    }
    $tiles_by_group[$g][] = $tile;
}
$admin_receipts = $can_approve_receipts ? casting_admin_list_receipts($admin_filter === 'all' ? '' : $admin_filter) : [];

if ($logged_in) {
    casting_render_panel_start('خرید اشتراک', 'cart');
} else {
    casting_render_head('خرید اشتراک', 'page-cart-guest');
    casting_render_header('cart');
    echo '<main class="wrap panel-page cart-guest-page">';
}
casting_render_flash();
?>
<section class="dash-card cart-card">
  <h1>خرید اشتراک</h1>
  <?php if ($logged_in) : ?>
    <p class="meta">اقلام انتخاب‌شده را بررسی کنید؛ برای پرداخت، دکمهٔ زیر را بزنید. مالیات فقط در مرحلهٔ پرداخت محاسبه می‌شود.</p>
  <?php else : ?>
    <p class="meta">خدمات را ببینید و به لیست اضافه کنید. برای پرداخت باید وارد شوید یا ثبت‌نام کنید. مالیات فقط هنگام پرداخت اعمال می‌شود.</p>
  <?php endif; ?>

  <?php if ($logged_in && $premium) : ?>
    <div class="flash flash-success">حساب کاربری ویژه فعال است.</div>
    <?php casting_render_premium_countdown($user_id); ?>
  <?php endif; ?>

  <?php if ($can_approve_receipts) : ?>
    <?php $pending_count = casting_admin_pending_receipt_count(); ?>
    <div class="premium-admin-notice">
      <strong>مدیریت پرداخت‌ها (مدیران)</strong>
      <p class="meta">تأیید فیش‌های قدیمی در صورت نیاز.</p>
      <?php if ($pending_count > 0) : ?>
        <p><a class="btn btn-primary btn-sm" href="#admin-receipts"><?= (int) $pending_count ?> فیش در انتظار تأیید</a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>

  <?php if ($cart['items'] === []) : ?>
    <div class="cart-empty-hero">
      <p class="empty-state">هنوز موردی انتخاب نکرده‌اید. از لیست خدمات پایین اضافه کنید.</p>
    </div>
  <?php else : ?>
    <div class="cart-list">
      <?php foreach ($cart['items'] as $item) : ?>
        <article class="cart-item">
          <div class="cart-item-body">
            <strong><?= casting_e((string) ($item['title'] ?? '')) ?></strong>
            <p class="meta">
              <?= casting_e((string) ($item['service_type'] ?? '')) ?>
              <?php if ((string) ($item['plan_label'] ?? '') !== '') : ?>
                · <?= casting_e((string) $item['plan_label']) ?>
              <?php elseif ((string) ($item['duration_label'] ?? '') !== '') : ?>
                · <?= casting_e((string) $item['duration_label']) ?>
              <?php endif; ?>
            </p>
            <p class="cart-item-price">
              <strong><?= casting_e(casting_format_toman((int) ($item['amount_base'] ?? 0))) ?></strong>
            </p>
          </div>
          <form method="post" action="cart.php" class="cart-item-remove">
            <?php wp_nonce_field('casting_cart'); ?>
            <input type="hidden" name="cart_action" value="remove">
            <input type="hidden" name="item_id" value="<?= casting_e((string) ($item['id'] ?? '')) ?>">
            <button class="btn btn-ghost btn-sm" type="submit">حذف</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="cart-totals bio-block">
      <ul class="info-list">
        <li><strong>تعداد اقلام:</strong> <?= (int) $totals['count'] ?></li>
        <li><strong>جمع مبلغ (بدون مالیات):</strong> <?= casting_e(casting_format_toman((int) $totals['base'])) ?></li>
        <?php if ((int) $totals['discount'] > 0) : ?>
          <li><strong>تخفیف:</strong> <?= casting_e(casting_format_toman((int) $totals['discount'])) ?></li>
        <?php endif; ?>
      </ul>
      <p class="meta cart-vat-hint">مالیات بر ارزش افزوده ۱۰٪ هنگام پرداخت اضافه می‌شود.</p>
    </div>

    <div class="cta-row cart-actions">
      <?php if ($logged_in) : ?>
        <form method="post" action="cart.php">
          <?php wp_nonce_field('casting_cart'); ?>
          <input type="hidden" name="cart_action" value="checkout">
          <button class="btn btn-primary" type="submit">پرداخت</button>
        </form>
      <?php else : ?>
        <button class="btn btn-primary" type="button" data-cart-auth-open>پرداخت</button>
      <?php endif; ?>
      <form method="post" action="cart.php" onsubmit="return confirm('لیست خرید اشتراک خالی شود؟');">
        <?php wp_nonce_field('casting_cart'); ?>
        <input type="hidden" name="cart_action" value="clear">
        <button class="btn btn-ghost" type="submit">خالی کردن</button>
      </form>
    </div>
  <?php endif; ?>
</section>

<section class="dash-card cart-shop-card" id="cart-shop">
  <h2>خدمات قابل خرید</h2>
  <p class="meta">روی هر کاشی بزنید تا به خرید اشتراک اضافه شود.</p>

  <?php foreach ($tiles_by_group as $group => $tiles) : ?>
    <h3 class="shop-group-title"><?= casting_e($group) ?></h3>
    <div class="shop-tile-scroller" role="list">
      <?php foreach ($tiles as $tile) :
          $add_href = casting_cart_add_url((string) $tile['service'], (string) $tile['plan']);
          ?>
        <article class="shop-tile" role="listitem">
          <?php
            $tile_service = (string) ($tile['service'] ?? '');
            $tile_media = $tile_service === 'premium' ? 'premium' : ($tile_service === 'advertising' ? 'ad' : 'call');
            $tile_mark = $tile_service === 'premium' ? 'ویژه' : ($tile_service === 'advertising' ? 'تبلیغ' : 'فراخوان');
          ?>
          <div class="shop-tile-media shop-tile-media--<?= casting_e($tile_media) ?><?= (string) ($tile['image'] ?? '') !== '' ? ' has-image' : '' ?>">
            <?php if ((string) ($tile['image'] ?? '') !== '') : ?>
              <img class="shop-tile-img" src="<?= casting_e((string) $tile['image']) ?>" alt="<?= casting_e((string) $tile['label']) ?>" loading="lazy" width="400" height="400">
            <?php else : ?>
              <span class="shop-tile-mark" aria-hidden="true"><?= casting_e($tile_mark) ?></span>
            <?php endif; ?>
            <?php if ((string) ($tile['badge'] ?? '') !== '') : ?>
              <span class="shop-tile-badge"><?= casting_e((string) $tile['badge']) ?></span>
            <?php endif; ?>
          </div>
          <div class="shop-tile-body">
            <strong class="shop-tile-title"><?= casting_e((string) $tile['label']) ?></strong>
            <p class="shop-tile-meta"><?= casting_e((string) $tile['meta']) ?></p>
            <p class="shop-tile-price">
              <strong><?= casting_e(casting_format_toman((int) $tile['price_base'])) ?></strong>
              <span class="meta">+ مالیات در پرداخت</span>
            </p>
            <a class="btn btn-primary btn-sm shop-tile-add" href="<?= casting_e($add_href) ?>">افزودن</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>

<div class="bio-block premium-payment-block" style="margin-top:1.25rem">
  <h2>نکته مهم</h2>
  <ul class="info-list">
    <li>با زدن «افزودن» وارد لیست خرید اشتراک می‌شوید؛ سپس پرداخت. تا پرداخت موفق، حساب شارژ نمی‌شود.</li>
    <li>عضویت ویژه: ۳ ماه ۲۱۰٬۰۰۰ · ۶ ماه ۳۷۰٬۰۰۰ · ۱۲ ماه ۷۰۰٬۰۰۰ تومان (+ مالیات هنگام پرداخت).</li>
    <li>فراخوان تئاتر، فیلم کوتاه و مستند: ۷۰۰٬۰۰۰ تومان (+ مالیات هنگام پرداخت) · سینمایی و تلویزیونی: ۷٬۰۰۰٬۰۰۰ تومان (+ مالیات هنگام پرداخت).</li>
    <li>تبلیغات: بنر پوستر تئاتر ۱٬۰۰۰٬۰۰۰ · بنر پوستر فیلم ۳٬۰۰۰٬۰۰۰ · بنر پوستر فیلم مستند ۱ تومان (+ مالیات هنگام پرداخت).</li>
  </ul>
</div>

<?php if ($can_approve_receipts) : ?>
<section class="dash-card" id="admin-receipts" style="margin-top:1rem">
  <h2 class="panel-section-title">مدیریت فیش‌ها و ارتقا به ویژه</h2>
  <p class="meta">تأیید فیش = فعال‌سازی حساب کاربری ویژه طبق پلن · پس از پایان اعتبار خودکار غیرفعال می‌شود.</p>

  <nav class="admin-tabs" aria-label="فیلتر وضعیت">
    <?php foreach (['pending' => 'در انتظار', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'all' => 'همه'] as $key => $label) : ?>
      <a class="admin-tab <?= $admin_filter === $key ? 'is-active' : '' ?>" href="cart.php?status=<?= casting_e($key) ?>#admin-receipts"><?= casting_e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$admin_receipts) : ?>
    <p class="empty-state">فیشی در این بخش نیست.</p>
  <?php else : ?>
    <div class="admin-receipt-list">
      <?php foreach ($admin_receipts as $row) :
          $uid = (int) $row['user_id'];
          $u = get_user_by('id', $uid);
          $row_plan_key = (string) $row['plan_key'];
          $plan_label = $plans[$row_plan_key]['label'] ?? $row_plan_key;
          $status = (string) $row['status'];
          ?>
        <article class="admin-receipt-item">
          <header>
            <div>
              <strong>#<?= (int) $row['id'] ?> — <?= casting_e($u ? $u->display_name : 'کاربر') ?></strong>
              <span class="meta"><?= casting_e($plan_label) ?> · <?= casting_e(number_format((int) $row['amount'])) ?> تومان</span>
            </div>
            <span class="chip"><?= casting_e(casting_premium_status_label($status)) ?></span>
          </header>
          <ul class="info-list admin-receipt-meta">
            <li><strong>شماره پیگیری:</strong> <?= casting_e((string) $row['reference_code']) ?></li>
            <li><strong>تاریخ:</strong> <?= casting_e((string) $row['created_at']) ?></li>
            <?php if ($u) : ?>
              <li><strong>ایمیل:</strong> <?= casting_e($u->user_email) ?></li>
            <?php endif; ?>
          </ul>
          <?php if ((int) ($row['attachment_id'] ?? 0) > 0) : ?>
            <?php casting_render_receipt_thumbnail((int) $row['attachment_id']); ?>
          <?php endif; ?>
          <?php if ($status === 'pending') : ?>
            <div class="cta-row">
              <form method="post" action="cart.php?status=<?= casting_e($admin_filter) ?>#admin-receipts">
                <?php wp_nonce_field('casting_admin_receipt'); ?>
                <input type="hidden" name="admin_receipt" value="1">
                <input type="hidden" name="receipt_id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-primary" type="submit" name="action" value="approve">تأیید و فعال‌سازی ویژه</button>
                <button class="btn btn-reject" type="submit" name="action" value="reject">رد</button>
              </form>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!$logged_in) :
    $auth_modal_open = $auth_needed || $auth_error !== '';
    ?>
  <div
    class="cart-auth-modal<?= $auth_modal_open ? ' is-open' : '' ?>"
    id="cart-auth-modal"
    data-cart-auth-modal
    <?= $auth_modal_open ? '' : 'hidden' ?>
    role="dialog"
    aria-modal="true"
    aria-labelledby="cart-auth-title"
  >
    <button type="button" class="cart-auth-modal-backdrop" data-cart-auth-close aria-label="بستن"></button>
    <div class="cart-auth-modal-panel">
      <div class="cart-auth-modal-head">
        <h2 id="cart-auth-title">ورود یا ثبت‌نام برای پرداخت</h2>
        <button type="button" class="btn btn-ghost btn-sm" data-cart-auth-close>بستن</button>
      </div>
      <p class="meta">اقلام سبد بعد از ورود حفظ می‌شود. در همین پنجره وارد شوید یا ثبت‌نام کنید.</p>

      <nav class="admin-tabs cart-auth-tabs" aria-label="ورود یا ثبت‌نام">
        <button type="button" class="admin-tab<?= $auth_tab === 'login' ? ' is-active' : '' ?>" data-cart-auth-tab="login">ورود</button>
        <button type="button" class="admin-tab<?= $auth_tab === 'register' ? ' is-active' : '' ?>" data-cart-auth-tab="register">ثبت‌نام</button>
      </nav>

      <?php if ($auth_error !== '') : ?>
        <div class="flash flash-error cart-auth-flash" role="alert"><?= casting_e($auth_error) ?></div>
      <?php endif; ?>

      <div class="cart-auth-pane<?= $auth_tab === 'login' ? ' is-active' : '' ?>" data-cart-auth-pane="login"<?= $auth_tab === 'login' ? '' : ' hidden' ?>>
        <form class="form cart-auth-form" method="post" action="cart.php" autocomplete="on" data-remember-credentials>
          <?php wp_nonce_field('casting_cart_auth'); ?>
          <input type="hidden" name="cart_action" value="auth_login">
          <input type="hidden" name="auth_tab" value="login">
          <div class="field">
            <label for="cart-auth-login">نام کاربری یا ایمیل</label>
            <input id="cart-auth-login" name="login" type="text" required autocomplete="username" value="<?= casting_e($auth_login) ?>">
          </div>
          <div class="field">
            <label for="cart-auth-password">رمز عبور</label>
            <div class="password-field">
              <input id="cart-auth-password" name="password" type="password" required autocomplete="current-password" data-password-input>
              <button type="button" class="password-toggle" data-password-toggle aria-label="نمایش رمز عبور" title="نمایش رمز عبور" aria-pressed="false">
                <svg class="password-toggle-icon password-toggle-icon--show" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                  <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                  <circle fill="none" stroke="currentColor" stroke-width="1.8" cx="12" cy="12" r="3"/>
                </svg>
                <svg class="password-toggle-icon password-toggle-icon--hide" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" hidden>
                  <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-4.4M9.9 5.1A10.5 10.5 0 0 1 12 5c6.5 0 10 7 10 7a17.3 17.3 0 0 1-4.1 4.7M6.1 6.1A17.5 17.5 0 0 0 2 12s3.5 7 10 7a10.4 10.4 0 0 0 4.2-.9"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="field">
            <label class="checkbox-row" for="cart-remember-credentials">
              <input id="cart-remember-credentials" type="checkbox" data-remember-credentials-check>
              <span>ذخیره نام کاربری و رمز عبور</span>
            </label>
          </div>
          <?php if ($auth_need_confirm) : ?>
            <input type="hidden" name="force_login" value="1">
            <p class="meta" role="status">با ادامه، نشست دستگاه قبلی قطع می‌شود. رمز را دوباره وارد کنید.</p>
            <button class="btn btn-primary cart-auth-submit" type="submit">ادامه ورود و پرداخت</button>
          <?php else : ?>
            <button class="btn btn-primary cart-auth-submit" type="submit">ورود و ادامه پرداخت</button>
          <?php endif; ?>
        </form>
      </div>

      <div class="cart-auth-pane<?= $auth_tab === 'register' ? ' is-active' : '' ?>" data-cart-auth-pane="register"<?= $auth_tab === 'register' ? '' : ' hidden' ?>>
        <p class="meta">حساب سریع برای پرداخت. تکمیل پروفایل کامل را بعداً در پنل انجام دهید.</p>
        <form class="form cart-auth-form" method="post" action="cart.php" autocomplete="on">
          <?php wp_nonce_field('casting_cart_auth'); ?>
          <input type="hidden" name="cart_action" value="auth_register">
          <input type="hidden" name="auth_tab" value="register">
          <div class="field">
            <label for="cart-auth-name">نام و نام خانوادگی</label>
            <input id="cart-auth-name" name="name" type="text" required autocomplete="name" value="<?= casting_e($auth_name) ?>">
          </div>
          <div class="field">
            <label for="cart-auth-username">نام کاربری</label>
            <input id="cart-auth-username" name="username" type="text" required autocomplete="username" value="<?= casting_e($auth_username) ?>">
          </div>
          <div class="field">
            <label for="cart-auth-email">ایمیل</label>
            <input id="cart-auth-email" name="email" type="email" required autocomplete="email" value="<?= casting_e($auth_email) ?>">
          </div>
          <div class="field">
            <label for="cart-auth-reg-password">رمز عبور</label>
            <div class="password-field">
              <input id="cart-auth-reg-password" name="password" type="password" required minlength="8" autocomplete="new-password" data-password-input>
              <button type="button" class="password-toggle" data-password-toggle aria-label="نمایش رمز عبور" title="نمایش رمز عبور" aria-pressed="false">
                <svg class="password-toggle-icon password-toggle-icon--show" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                  <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                  <circle fill="none" stroke="currentColor" stroke-width="1.8" cx="12" cy="12" r="3"/>
                </svg>
                <svg class="password-toggle-icon password-toggle-icon--hide" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" hidden>
                  <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-4.4M9.9 5.1A10.5 10.5 0 0 1 12 5c6.5 0 10 7 10 7a17.3 17.3 0 0 1-4.1 4.7M6.1 6.1A17.5 17.5 0 0 0 2 12s3.5 7 10 7a10.4 10.4 0 0 0 4.2-.9"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="field">
            <label for="cart-auth-reg-password2">تکرار رمز عبور</label>
            <input id="cart-auth-reg-password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
          </div>
          <div class="field">
            <label for="cart-auth-referral">کد معرفی (اختیاری)</label>
            <input id="cart-auth-referral" name="referral_code" type="text" maxlength="32" autocomplete="off" dir="ltr" value="<?= casting_e($auth_referral_code) ?>" placeholder="7ROKHAB12CD34">
          </div>
          <button class="btn btn-primary cart-auth-submit" type="submit">ثبت‌نام و ادامه پرداخت</button>
        </form>
      </div>
    </div>
  </div>
  <script>
    (function () {
      var modal = document.querySelector("[data-cart-auth-modal]");
      if (!modal) return;
      var open = function () {
        modal.hidden = false;
        modal.classList.add("is-open");
        document.body.classList.add("cart-auth-modal-open");
        var active = modal.querySelector(".cart-auth-pane.is-active input:not([type=hidden])");
        if (active) {
          try { active.focus(); } catch (e) {}
        }
      };
      var close = function () {
        modal.classList.remove("is-open");
        modal.hidden = true;
        document.body.classList.remove("cart-auth-modal-open");
      };
      var showTab = function (tab) {
        modal.querySelectorAll("[data-cart-auth-tab]").forEach(function (btn) {
          btn.classList.toggle("is-active", btn.getAttribute("data-cart-auth-tab") === tab);
        });
        modal.querySelectorAll("[data-cart-auth-pane]").forEach(function (pane) {
          var on = pane.getAttribute("data-cart-auth-pane") === tab;
          pane.classList.toggle("is-active", on);
          pane.hidden = !on;
        });
      };
      document.querySelectorAll("[data-cart-auth-open]").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          open();
        });
      });
      document.querySelectorAll("[data-cart-auth-close]").forEach(function (btn) {
        btn.addEventListener("click", close);
      });
      modal.querySelectorAll("[data-cart-auth-tab]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          showTab(btn.getAttribute("data-cart-auth-tab") || "login");
        });
      });
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal.classList.contains("is-open")) close();
      });
      if (modal.classList.contains("is-open")) open();
    })();
  </script>
<?php endif; ?>

<?php
if ($logged_in) {
    casting_render_panel_end();
} else {
    echo '</main>';
    casting_render_footer();
}
?>
