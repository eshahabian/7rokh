<?php
declare(strict_types=1);

/**
 * سبد خرید جلسه — قبل از خلاصه سفارش و درگاه
 */

function casting_cart_session_key(): string
{
    return 'casting_cart_v1';
}

/**
 * @return array{items: list<array<string, mixed>>}
 */
function casting_cart_get(): array
{
    $raw = $_SESSION[casting_cart_session_key()] ?? null;
    if (!is_array($raw) || !isset($raw['items']) || !is_array($raw['items'])) {
        return ['items' => []];
    }
    $items = [];
    foreach ($raw['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $items[] = $item;
    }

    return ['items' => array_values($items)];
}

/**
 * @param array{items: list<array<string, mixed>>} $cart
 */
function casting_cart_save(array $cart): void
{
    $_SESSION[casting_cart_session_key()] = [
        'items' => array_values($cart['items'] ?? []),
    ];
}

function casting_cart_clear(): void
{
    unset($_SESSION[casting_cart_session_key()]);
}

function casting_cart_count(): int
{
    return count(casting_cart_get()['items']);
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
        return ['ok' => false, 'error' => $built['error'] ?? 'افزودن به سبد ناموفق بود.'];
    }
    $draft = $built['draft'];
    $id = substr(hash('sha256', $service_key . '|' . ($draft['plan_key'] ?? '') . '|' . $project_id . '|' . microtime(true)), 0, 12);

    return [
        'ok'   => true,
        'error'=> '',
        'item' => [
            'id'             => $id,
            'service_key'    => (string) ($draft['service_key'] ?? $service_key),
            'plan_key'       => (string) ($draft['plan_key'] ?? $plan_key),
            'project_id'     => (int) ($draft['project_id'] ?? $project_id),
            'title'          => (string) ($draft['title'] ?? ''),
            'service_type'   => (string) ($draft['service_type'] ?? ''),
            'plan_label'     => (string) ($draft['plan_label'] ?? ''),
            'duration_label' => (string) ($draft['duration_label'] ?? ''),
            'description'    => (string) ($draft['description'] ?? ''),
            'amount_base'    => (int) ($draft['amount_base'] ?? 0),
            'discount'       => (int) ($draft['discount'] ?? 0),
            'vat_amount'     => (int) ($draft['vat_amount'] ?? 0),
            'amount_final'   => (int) ($draft['amount_final'] ?? 0),
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
        $cart['items'] = array_values(array_filter(
            $cart['items'],
            static fn(array $it): bool => (string) ($it['service_key'] ?? '') !== 'premium'
        ));
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
    $cart['items'] = array_values(array_filter(
        $cart['items'],
        static fn(array $it): bool => (string) ($it['id'] ?? '') !== $item_id
    ));
    casting_cart_save($cart);
    if (count($cart['items']) === $before) {
        return ['ok' => false, 'error' => 'آیتم در سبد پیدا نشد.'];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{base:int,discount:int,vat:int,final:int,count:int}
 */
function casting_cart_totals(array $cart = []): array
{
    if ($cart === []) {
        $cart = casting_cart_get();
    }
    $base = 0;
    $discount = 0;
    $vat = 0;
    $final = 0;
    foreach ($cart['items'] as $it) {
        $qty = max(1, (int) ($it['qty'] ?? 1));
        $base += (int) ($it['amount_base'] ?? 0) * $qty;
        $discount += (int) ($it['discount'] ?? 0) * $qty;
        $vat += (int) ($it['vat_amount'] ?? 0) * $qty;
        $final += (int) ($it['amount_final'] ?? 0) * $qty;
    }

    return [
        'base'     => $base,
        'discount' => $discount,
        'vat'      => $vat,
        'final'    => $final,
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
        return ['ok' => false, 'error' => 'سبد خرید خالی است.'];
    }

    $totals = casting_cart_totals($cart);
    $titles = [];
    $types = [];
    $durations = [];
    $descs = [];
    $project_id = 0;
    $primary_service = (string) ($cart['items'][0]['service_key'] ?? 'cart');
    $primary_plan = (string) ($cart['items'][0]['plan_key'] ?? 'cart');

    foreach ($cart['items'] as $it) {
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
    $service_type = count(array_unique($types)) === 1
        ? $types[0]
        : 'سبد خرید خدمات ۷رخ';
    $duration = count($durations) === 1 ? $durations[0] : '';
    $description = implode("\n", $descs);

    // اگر فقط یک آیتم است، سفارش همان خدمت باشد تا فعال‌سازی ساده‌تر شود
    $service_key = count($cart['items']) === 1 ? $primary_service : 'cart';
    $plan_key = count($cart['items']) === 1 ? $primary_plan : 'multi';

    $draft = [
        'service_key'    => $service_key,
        'plan_key'       => $plan_key,
        'title'          => $title,
        'service_type'   => $service_type,
        'duration_label' => $duration,
        'description'    => $description !== '' ? $description : 'اقلام سبد خرید خدمات پورتال ۷رخ.',
        'amount_base'    => $totals['base'],
        'discount'       => $totals['discount'],
        'vat_amount'     => $totals['vat'],
        'amount_final'   => $totals['final'],
        'project_id'     => $project_id,
        'cancel_url'     => 'cart.php',
        'meta'           => [
            'from_cart'  => true,
            'cart_items' => $cart['items'],
            'days'       => (int) (($cart['items'][0]['meta']['days'] ?? 0) ?: 0),
            'months'     => (int) (($cart['items'][0]['meta']['months'] ?? 0) ?: 0),
            'project_type' => (string) (($cart['items'][0]['meta']['project_type'] ?? '') ?: ($cart['items'][0]['plan_key'] ?? '')),
        ],
    ];

    $created = casting_checkout_create_order($user_id, $draft);
    if ($created['ok']) {
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
