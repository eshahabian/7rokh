<?php
declare(strict_types=1);

/**
 * سبد خرید — مهمان هم می‌تواند ببیند و انتخاب کند؛ پرداخت نیازمند ورود است
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();

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
$auth_need_confirm = false;

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

$action = sanitize_key((string) ($_GET['action'] ?? $_POST['cart_action'] ?? ''));

// افزودن از لینک / کاشی
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $service = sanitize_key((string) ($_GET['service'] ?? ''));
    $plan = sanitize_key((string) ($_GET['plan'] ?? ''));
    $project_id = max(0, (int) ($_GET['project'] ?? 0));
    $result = casting_cart_add($service, $plan, $project_id);
    if ($result['ok']) {
        casting_set_flash('success', 'به سفارش‌ها اضافه شد.');
    } else {
        casting_set_flash('error', $result['error']);
    }
    casting_redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $password = (string) ($_POST['password'] ?? '');
            $password2 = (string) ($_POST['password2'] ?? '');
            if ($password !== $password2) {
                $auth_error = 'تکرار رمز عبور با رمز یکسان نیست.';
            } else {
                $role = $cart_role_from_items();
                $result = casting_register_user($auth_name, $auth_username, $auth_email, $password, $role);
                if (!$result['ok']) {
                    casting_rate_limit_hit('register');
                    $auth_error = (string) ($result['error'] ?? 'ثبت‌نام ناموفق بود.');
                } else {
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
    } elseif ($action === 'remove') {
        $result = casting_cart_remove((string) ($_POST['item_id'] ?? ''));
        casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'از سبد حذف شد.' : $result['error']);
        casting_redirect('cart.php');
    } elseif ($action === 'clear') {
        casting_cart_clear();
        casting_set_flash('success', 'سفارش‌ها خالی شد.');
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

if ($logged_in) {
    casting_render_panel_start('سفارش‌ها', 'cart');
} else {
    casting_render_head('سفارش‌ها', 'page-cart-guest');
    casting_render_header('cart');
    echo '<main class="wrap panel-page cart-guest-page">';
}
casting_render_flash();
?>
<section class="dash-card cart-card">
  <h1>سفارش‌ها</h1>
  <?php if ($logged_in) : ?>
    <p class="meta">اقلام انتخاب‌شده را بررسی کنید؛ سپس به خلاصه سفارش و درگاه بانکی بروید.</p>
  <?php else : ?>
    <p class="meta">خدمات را ببینید و به سفارش‌ها اضافه کنید. برای پرداخت نهایی باید وارد شوید یا ثبت‌نام کنید.</p>
  <?php endif; ?>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>

  <?php if ($cart['items'] === []) : ?>
    <div class="cart-empty-hero">
      <p class="empty-state">هنوز سفارشی ندارید.</p>
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
              <?php endif; ?>
              <?php if ((string) ($item['duration_label'] ?? '') !== '') : ?>
                · <?= casting_e((string) $item['duration_label']) ?>
              <?php endif; ?>
            </p>
            <p class="cart-item-price">
              مبلغ بسته: <?= casting_e(casting_format_toman((int) ($item['amount_base'] ?? 0))) ?>
              · قابل پرداخت: <strong><?= casting_e(casting_format_toman((int) ($item['amount_final'] ?? 0))) ?></strong>
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
        <li><strong>جمع مبلغ اصلی:</strong> <?= casting_e(casting_format_toman((int) $totals['base'])) ?></li>
        <?php if ((int) $totals['discount'] > 0) : ?>
          <li><strong>تخفیف:</strong> <?= casting_e(casting_format_toman((int) $totals['discount'])) ?></li>
        <?php endif; ?>
        <li class="checkout-total"><strong>مبلغ قابل پرداخت:</strong> <?= casting_e(casting_format_toman((int) $totals['final'])) ?></li>
      </ul>
    </div>

    <div class="cta-row cart-actions">
      <?php if ($logged_in) : ?>
        <form method="post" action="cart.php">
          <?php wp_nonce_field('casting_cart'); ?>
          <input type="hidden" name="cart_action" value="checkout">
          <button class="btn btn-primary" type="submit">ادامه به خلاصه سفارش</button>
        </form>
      <?php else : ?>
        <button class="btn btn-primary" type="button" data-cart-auth-open>ادامه به پرداخت</button>
      <?php endif; ?>
      <form method="post" action="cart.php" onsubmit="return confirm('سفارش‌ها خالی شود؟');">
        <?php wp_nonce_field('casting_cart'); ?>
        <input type="hidden" name="cart_action" value="clear">
        <button class="btn btn-ghost" type="submit">خالی کردن</button>
      </form>
    </div>
  <?php endif; ?>
</section>

<section class="dash-card cart-shop-card" id="cart-shop">
  <h2>خدمات قابل خرید</h2>
  <p class="meta">روی هر کاشی بزنید تا به سفارش‌ها اضافه شود.</p>

  <?php foreach ($tiles_by_group as $group => $tiles) : ?>
    <h3 class="shop-group-title"><?= casting_e($group) ?></h3>
    <div class="shop-tile-scroller" role="list">
      <?php foreach ($tiles as $tile) :
          $add_href = casting_cart_add_url((string) $tile['service'], (string) $tile['plan']);
          ?>
        <article class="shop-tile" role="listitem">
          <div class="shop-tile-media shop-tile-media--<?= casting_e((string) $tile['service'] === 'premium' ? 'premium' : 'call') ?><?= (string) ($tile['image'] ?? '') !== '' ? ' has-image' : '' ?>">
            <?php if ((string) ($tile['image'] ?? '') !== '') : ?>
              <img class="shop-tile-img" src="<?= casting_e((string) $tile['image']) ?>" alt="<?= casting_e((string) $tile['label']) ?>" loading="lazy" width="400" height="400">
            <?php else : ?>
              <span class="shop-tile-mark" aria-hidden="true"><?= (string) $tile['service'] === 'premium' ? 'ویژه' : 'فراخوان' ?></span>
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
            </p>
            <a class="btn btn-primary btn-sm shop-tile-add" href="<?= casting_e($add_href) ?>">افزودن</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>

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
        <form class="form cart-auth-form" method="post" action="cart.php" autocomplete="on">
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
