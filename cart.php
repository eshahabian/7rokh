<?php
declare(strict_types=1);

/**
 * سبد خرید — مهمان هم می‌تواند ببیند و انتخاب کند؛ پرداخت نیازمند ورود است
 */
require_once __DIR__ . '/includes/bootstrap.php';
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
        casting_set_flash('success', 'به سبد خرید اضافه شد.');
    } else {
        casting_set_flash('error', $result['error']);
    }
    casting_redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_cart')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $action = sanitize_key((string) ($_POST['cart_action'] ?? ''));
        if ($action === 'remove') {
            $result = casting_cart_remove((string) ($_POST['item_id'] ?? ''));
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'از سبد حذف شد.' : $result['error']);
            casting_redirect('cart.php');
        }
        if ($action === 'clear') {
            casting_cart_clear();
            casting_set_flash('success', 'سبد خرید خالی شد.');
            casting_redirect('cart.php');
        }
        if ($action === 'checkout') {
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
    casting_render_panel_start('سبد خرید', 'cart');
} else {
    casting_render_head('سبد خرید', 'page-cart-guest');
    casting_render_header('cart');
    echo '<main class="wrap panel-page cart-guest-page">';
}
casting_render_flash();
?>
<section class="dash-card cart-card">
  <h1>سبد خرید</h1>
  <?php if ($logged_in) : ?>
    <p class="meta">اقلام انتخاب‌شده را بررسی کنید؛ سپس به خلاصه سفارش و درگاه بانکی بروید.</p>
  <?php else : ?>
    <p class="meta">خدمات را ببینید و به سبد اضافه کنید. برای پرداخت نهایی باید وارد شوید یا ثبت‌نام کنید.</p>
  <?php endif; ?>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>

  <?php if ($cart['items'] === []) : ?>
    <div class="cart-empty-hero">
      <p class="empty-state">سبد خرید شما خالی است.</p>
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
              <?= casting_e(casting_format_toman((int) ($item['amount_base'] ?? 0))) ?>
              + مالیات
              = <strong><?= casting_e(casting_format_toman((int) ($item['amount_final'] ?? 0))) ?></strong>
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
        <li><strong>مالیات بر ارزش افزوده:</strong> <?= casting_e(casting_format_toman((int) $totals['vat'])) ?></li>
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
      <form method="post" action="cart.php" onsubmit="return confirm('سبد خرید خالی شود؟');">
        <?php wp_nonce_field('casting_cart'); ?>
        <input type="hidden" name="cart_action" value="clear">
        <button class="btn btn-ghost" type="submit">خالی کردن سبد</button>
      </form>
    </div>
  <?php endif; ?>
</section>

<section class="dash-card cart-shop-card" id="cart-shop">
  <h2>خدمات قابل خرید</h2>
  <p class="meta">روی هر کاشی بزنید تا به سبد اضافه شود.</p>

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
              <strong><?= casting_e(casting_format_toman((int) $tile['price_final'])) ?></strong>
              <span class="meta">با مالیات</span>
            </p>
            <a class="btn btn-primary btn-sm shop-tile-add" href="<?= casting_e($add_href) ?>">افزودن به سبد</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>

<?php if (!$logged_in) : ?>
  <div
    class="cart-auth-modal<?= $auth_needed ? ' is-open' : '' ?>"
    id="cart-auth-modal"
    data-cart-auth-modal
    <?= $auth_needed ? '' : 'hidden' ?>
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
      <p class="meta">برای ادامه پرداخت باید ورود یا ثبت‌نام انجام دهید. اقلام سبد شما بعد از ورود حفظ می‌شود.</p>
      <div class="cta-row cart-auth-actions">
        <a class="btn btn-primary" href="<?= casting_e(casting_url('login.php?intent=cart')) ?>">ورود</a>
        <a class="btn btn-ghost" href="<?= casting_e(casting_url('register.php?intent=cart')) ?>">ثبت‌نام / عضویت</a>
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
      };
      var close = function () {
        modal.classList.remove("is-open");
        modal.hidden = true;
        document.body.classList.remove("cart-auth-modal-open");
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
