<?php
declare(strict_types=1);

/**
 * سبد خرید جلسه — قبل از خلاصه سفارش و درگاه
 */

function casting_cart_legacy_session_key(): string
{
    return 'casting_cart_v1';
}

/** شناسه کاربر صاحب سبد در session پورتال */
function casting_cart_owner_id(): int
{
    if (function_exists('casting_portal_session_user_id')) {
        return max(0, (int) casting_portal_session_user_id());
    }

    return 0;
}

function casting_cart_guest_session_key(): string
{
    return 'casting_cart_v1_guest';
}

/**
 * کلید سبد به ازای هر کاربر — مهمان هم سبد موقت دارد
 */
function casting_cart_session_key(): string
{
    $uid = casting_cart_owner_id();
    if ($uid > 0) {
        return 'casting_cart_v1_u' . $uid;
    }

    return casting_cart_guest_session_key();
}

function casting_cart_count_cookie_name(): string
{
    return 'casting_cart_count';
}

function casting_cart_session_ready(): bool
{
    return session_status() === PHP_SESSION_ACTIVE;
}

/**
 * دامنهٔ کوکی برای خوانده شدن روی کل سایت (مثلاً 7rokh.ir)
 */
function casting_cart_cookie_domain(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
        return '';
    }
    if (substr($host, 0, 4) === 'www.') {
        $host = substr($host, 4);
    }
    // دامنهٔ والد با نقطه — روی www و بدون www مشترک شود
    if (substr_count($host, '.') >= 1) {
        return '.' . $host;
    }

    return '';
}

/**
 * کوکی دامنهٔ ریشه برای نمایش شمارنده سبد در سایت اصلی وردپرس
 */
function casting_cart_sync_count_cookie(int $count = -1): void
{
    if ($count < 0) {
        try {
            $count = casting_cart_count();
        } catch (Throwable $e) {
            $count = 0;
        }
    }
    $count = max(0, (int) $count);
    $name = casting_cart_count_cookie_name();
    if ($count > 0) {
        $_COOKIE[$name] = (string) $count;
    } else {
        unset($_COOKIE[$name]);
    }
    if (headers_sent()) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && (string) $_SERVER['HTTPS'] !== 'off';
    $domain = casting_cart_cookie_domain();
    $expire = $count > 0 ? (time() + (30 * DAY_IN_SECONDS)) : (time() - YEAR_IN_SECONDS);
    $value = $count > 0 ? (string) $count : '0';

    $variants = [
        ['path' => '/', 'domain' => $domain],
        ['path' => '/', 'domain' => ''],
        ['path' => '/casting-portal/', 'domain' => ''],
    ];
    foreach ($variants as $v) {
        $params = [
            'expires'  => $expire,
            'path'     => $v['path'],
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ];
        if ($v['domain'] !== '') {
            $params['domain'] = $v['domain'];
        }
        setcookie($name, $value, $params);
    }
}

/**
 * مهاجرت سبد مشترک قدیمی به کلید کاربر فعلی (یک‌بار)
 */
function casting_cart_migrate_legacy_if_needed(): void
{
    if (!casting_cart_session_ready()) {
        return;
    }
    $uid = casting_cart_owner_id();
    if ($uid <= 0) {
        return;
    }
    $user_key = casting_cart_session_key();
    $legacy_key = casting_cart_legacy_session_key();
    if ($user_key === $legacy_key) {
        return;
    }
    $user_raw = $_SESSION[$user_key] ?? null;
    $has_user = is_array($user_raw) && !empty($user_raw['items']) && is_array($user_raw['items']);
    if ($has_user) {
        return;
    }
    $legacy = $_SESSION[$legacy_key] ?? null;
    if (!is_array($legacy) || empty($legacy['items']) || !is_array($legacy['items'])) {
        return;
    }
    $_SESSION[$user_key] = [
        'items' => array_values($legacy['items']),
    ];
    unset($_SESSION[$legacy_key]);
}

/**
 * @return array{items: list<array<string, mixed>>}
 */
function casting_cart_get(): array
{
    if (!casting_cart_session_ready()) {
        return ['items' => []];
    }
    if (casting_cart_owner_id() > 0) {
        casting_cart_migrate_legacy_if_needed();
    }
    $raw = $_SESSION[casting_cart_session_key()] ?? null;
    if (!is_array($raw) || !isset($raw['items']) || !is_array($raw['items'])) {
        return ['items' => []];
    }
    $items = [];
    foreach ($raw['items'] as $item) {
        if (is_array($item)) {
            $items[] = $item;
        }
    }

    return ['items' => array_values($items)];
}

/**
 * @param array{items: list<array<string, mixed>>} $cart
 */
function casting_cart_save(array $cart): void
{
    if (!casting_cart_session_ready()) {
        return;
    }
    $items = array_values(is_array($cart['items'] ?? null) ? $cart['items'] : []);
    $_SESSION[casting_cart_session_key()] = [
        'items' => $items,
    ];
    casting_cart_sync_count_cookie(count($items));
}

function casting_cart_clear(): void
{
    if (!casting_cart_session_ready()) {
        return;
    }
    unset($_SESSION[casting_cart_session_key()]);
    unset($_SESSION[casting_cart_legacy_session_key()]);
    if (casting_cart_owner_id() > 0) {
        unset($_SESSION[casting_cart_guest_session_key()]);
    }
    casting_cart_sync_count_cookie(0);
}

/**
 * بعد از ورود: سبد مهمان را به سبد کاربر منتقل کن
 */
function casting_cart_claim_guest_cart(): void
{
    if (!casting_cart_session_ready()) {
        return;
    }
    $uid = casting_cart_owner_id();
    if ($uid <= 0) {
        return;
    }
    $guest_key = casting_cart_guest_session_key();
    $user_key = 'casting_cart_v1_u' . $uid;
    $guest = $_SESSION[$guest_key] ?? null;
    if (!is_array($guest) || empty($guest['items']) || !is_array($guest['items'])) {
        return;
    }
    $user_raw = $_SESSION[$user_key] ?? null;
    $user_items = [];
    if (is_array($user_raw) && isset($user_raw['items']) && is_array($user_raw['items'])) {
        foreach ($user_raw['items'] as $it) {
            if (is_array($it)) {
                $user_items[] = $it;
            }
        }
    }
    foreach ($guest['items'] as $git) {
        if (!is_array($git)) {
            continue;
        }
        $replaced = false;
        foreach ($user_items as $i => $uit) {
            if (
                (string) ($uit['service_key'] ?? '') === (string) ($git['service_key'] ?? '')
                && (string) ($uit['plan_key'] ?? '') === (string) ($git['plan_key'] ?? '')
                && (int) ($uit['project_id'] ?? 0) === (int) ($git['project_id'] ?? 0)
            ) {
                $user_items[$i] = $git;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $user_items[] = $git;
        }
    }
    // فقط یک پلن ویژه
    $premium = null;
    $kept = [];
    foreach ($user_items as $it) {
        if ((string) ($it['service_key'] ?? '') === 'premium') {
            $premium = $it;
            continue;
        }
        $kept[] = $it;
    }
    if ($premium !== null) {
        $kept[] = $premium;
    }
    $_SESSION[$user_key] = ['items' => array_values($kept)];
    unset($_SESSION[$guest_key]);
    casting_cart_sync_count_cookie(count($kept));
}

function casting_cart_count(): int
{
    try {
        return count(casting_cart_get()['items']);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * @return array{ok:bool,error:string,item?:array<string,mixed>}
 */
function casting_cart_build_item(string $service_key, string $plan_key = '', int $project_id = 0): array
{
    if (!function_exists('casting_checkout_build_draft')) {
        require_once __DIR__ . '/checkout.php';
    }
    $built = casting_checkout_build_draft($service_key, $plan_key, $project_id);
    if (!$built['ok'] || empty($built['draft'])) {
        return ['ok' => false, 'error' => $built['error'] ?? 'افزودن به خرید اشتراک ناموفق بود.'];
    }
    $draft = $built['draft'];
    $id = substr(hash('sha256', $service_key . '|' . ($draft['plan_key'] ?? '') . '|' . $project_id . '|' . microtime(true)), 0, 12);
    // در سفارش‌ها هنوز مالیات اعمال نمی‌شود؛ فقط مبلغ پایه ذخیره می‌شود
    $base = max(0, (int) ($draft['amount_base'] ?? 0));
    $discount = max(0, min((int) ($draft['discount'] ?? 0), $base));
    $pre_vat = max(0, $base - $discount);

    return [
        'ok'    => true,
        'error' => '',
        'item'  => [
            'id'             => $id,
            'service_key'    => (string) ($draft['service_key'] ?? $service_key),
            'plan_key'       => (string) ($draft['plan_key'] ?? $plan_key),
            'project_id'     => (int) ($draft['project_id'] ?? $project_id),
            'title'          => (string) ($draft['title'] ?? ''),
            'service_type'   => (string) ($draft['service_type'] ?? ''),
            'plan_label'     => (string) ($draft['plan_label'] ?? ''),
            'duration_label' => (string) ($draft['duration_label'] ?? ''),
            'description'    => (string) ($draft['description'] ?? ''),
            'amount_base'    => $base,
            'discount'       => $discount,
            'vat_amount'     => 0,
            'amount_final'   => $pre_vat,
            'meta'           => is_array($draft['meta'] ?? null) ? $draft['meta'] : [],
            'qty'            => 1,
        ],
    ];
}

/**
 * @return array{ok:bool,error:string,count?:int}
 */
function casting_cart_add(string $service_key, string $plan_key = '', int $project_id = 0): array
{
    $built = casting_cart_build_item($service_key, $plan_key, $project_id);
    if (!$built['ok'] || empty($built['item'])) {
        return ['ok' => false, 'error' => $built['error']];
    }
    $new = $built['item'];
    $cart = casting_cart_get();

    // عضویت ویژه: فقط یک پلن در سبد — پلن جدید جایگزین قبلی می‌شود
    if ((string) $new['service_key'] === 'premium') {
        $kept = [];
        foreach ($cart['items'] as $it) {
            if ((string) ($it['service_key'] ?? '') !== 'premium') {
                $kept[] = $it;
            }
        }
        $cart['items'] = $kept;
    }

    // همان خدمت+پلن+پروژه: جایگزین (بدون تکرار)
    $replaced = false;
    foreach ($cart['items'] as $i => $it) {
        if (
            (string) ($it['service_key'] ?? '') === (string) $new['service_key']
            && (string) ($it['plan_key'] ?? '') === (string) $new['plan_key']
            && (int) ($it['project_id'] ?? 0) === (int) $new['project_id']
        ) {
            $cart['items'][$i] = $new;
            $replaced = true;
            break;
        }
    }
    if (!$replaced) {
        $cart['items'][] = $new;
    }

    casting_cart_save($cart);

    return ['ok' => true, 'error' => '', 'count' => count($cart['items'])];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_cart_remove(string $item_id): array
{
    $item_id = sanitize_text_field($item_id);
    if ($item_id === '') {
        return ['ok' => false, 'error' => 'آیتم نامعتبر است.'];
    }
    $cart = casting_cart_get();
    $before = count($cart['items']);
    $kept = [];
    foreach ($cart['items'] as $it) {
        if ((string) ($it['id'] ?? '') !== $item_id) {
            $kept[] = $it;
        }
    }
    $cart['items'] = $kept;
    casting_cart_save($cart);
    if (count($cart['items']) === $before) {
        return ['ok' => false, 'error' => 'آیتم در سبد پیدا نشد.'];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * جمع سفارش‌ها (بدون مالیات — مالیات فقط هنگام پرداخت)
 *
 * @return array{base:int,discount:int,vat:int,final:int,count:int}
 */
function casting_cart_totals(?array $cart = null): array
{
    if ($cart === null || !isset($cart['items']) || !is_array($cart['items'])) {
        $cart = casting_cart_get();
    }
    $base = 0;
    $discount = 0;
    foreach ($cart['items'] as $it) {
        if (!is_array($it)) {
            continue;
        }
        $qty = max(1, (int) ($it['qty'] ?? 1));
        $base += (int) ($it['amount_base'] ?? 0) * $qty;
        $discount += (int) ($it['discount'] ?? 0) * $qty;
    }
    $subtotal = max(0, $base - $discount);

    return [
        'base'     => $base,
        'discount' => $discount,
        'vat'      => 0,
        'final'    => $subtotal,
        'count'    => count($cart['items']),
    ];
}

/**
 * ساخت سفارش از کل سبد برای صفحه خلاصه
 *
 * @return array{ok:bool,error:string,order?:array<string,mixed>}
 */
function casting_cart_create_order_from_cart(int $user_id): array
{
    if (!function_exists('casting_checkout_create_order')) {
        require_once __DIR__ . '/checkout.php';
    }
    $cart = casting_cart_get();
    if ($cart['items'] === []) {
        return ['ok' => false, 'error' => 'هنوز سفارشی ندارید.'];
    }

    $totals = casting_cart_totals($cart);
    // مالیات بر ارزش افزوده فقط هنگام کلیک پرداخت / ورود به صفحه پرداخت
    $amounts = casting_checkout_calc_amounts((int) $totals['base'], (int) $totals['discount']);
    $titles = [];
    $types = [];
    $durations = [];
    $descs = [];
    $project_id = 0;
    $primary_service = (string) ($cart['items'][0]['service_key'] ?? 'cart');
    $primary_plan = (string) ($cart['items'][0]['plan_key'] ?? 'cart');
    $cart_items_checkout = [];

    foreach ($cart['items'] as $it) {
        if (!is_array($it)) {
            continue;
        }
        $item_amounts = casting_checkout_calc_amounts((int) ($it['amount_base'] ?? 0), (int) ($it['discount'] ?? 0));
        $it['vat_amount'] = $item_amounts['vat'];
        $it['amount_final'] = $item_amounts['final'];
        $cart_items_checkout[] = $it;

        $titles[] = (string) ($it['title'] ?? '');
        if ((string) ($it['service_type'] ?? '') !== '') {
            $types[] = (string) $it['service_type'];
        }
        if ((string) ($it['duration_label'] ?? '') !== '') {
            $durations[] = (string) $it['duration_label'];
        }
        if ((string) ($it['description'] ?? '') !== '') {
            $descs[] = (string) $it['description'];
        }
        if ($project_id <= 0 && (int) ($it['project_id'] ?? 0) > 0) {
            $project_id = (int) $it['project_id'];
        }
    }

    $title = count($titles) === 1
        ? $titles[0]
        : ('سفارش ترکیبی (' . count($titles) . ' مورد)');
    $unique_types = array_values(array_unique($types));
    $service_type = count($unique_types) === 1
        ? $unique_types[0]
        : 'سفارش خدمات ۷رخ';
    $duration = count($durations) === 1 ? $durations[0] : '';
    $description = implode("\n", $descs);

    $service_key = count($cart_items_checkout) === 1 ? $primary_service : 'cart';
    $plan_key = count($cart_items_checkout) === 1 ? $primary_plan : 'multi';
    $first_meta = is_array($cart_items_checkout[0]['meta'] ?? null) ? $cart_items_checkout[0]['meta'] : [];

    $draft = [
        'service_key'    => $service_key,
        'plan_key'       => $plan_key,
        'title'          => $title,
        'service_type'   => $service_type,
        'duration_label' => $duration,
        'description'    => $description !== '' ? $description : 'اقلام سفارش خدمات پورتال ۷رخ.',
        'amount_base'    => $amounts['base'],
        'discount'       => $amounts['discount'],
        'vat_amount'     => $amounts['vat'],
        'amount_final'   => $amounts['final'],
        'project_id'     => $project_id,
        'cancel_url'     => 'cart.php',
        'meta'           => [
            'from_cart'    => true,
            'cart_items'   => $cart_items_checkout,
            'days'         => (int) (($first_meta['days'] ?? 0) ?: 0),
            'months'       => (int) (($first_meta['months'] ?? 0) ?: 0),
            'project_type' => (string) (($first_meta['project_type'] ?? '') ?: ($cart_items_checkout[0]['plan_key'] ?? '')),
        ],
    ];

    $created = casting_checkout_create_order($user_id, $draft);
    if (!empty($created['ok'])) {
        casting_cart_clear();
    }

    return $created;
}

function casting_cart_add_url(string $service_key, string $plan_key = '', int $project_id = 0): string
{
    $q = [
        'action'  => 'add',
        'service' => $service_key,
    ];
    if ($plan_key !== '') {
        $q['plan'] = $plan_key;
    }
    if ($project_id > 0) {
        $q['project'] = (string) $project_id;
    }

    return 'cart.php?' . http_build_query($q);
}
