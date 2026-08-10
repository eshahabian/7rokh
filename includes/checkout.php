<?php
declare(strict_types=1);

/**
 * سفارش / Checkout — خلاصه قبل از درگاه (برای بررسی به‌پرداخت)
 */

function casting_vat_rate(): float
{
    return 0.10; // ۱۰٪ افزوده هنگام ورود به درگاه / واریز
}

function casting_orders_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'casting_orders';
}

function casting_orders_install(): void
{
    global $wpdb;
    $table = casting_orders_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_code VARCHAR(40) NOT NULL DEFAULT '',
        user_id BIGINT UNSIGNED NOT NULL,
        service_key VARCHAR(40) NOT NULL DEFAULT '',
        plan_key VARCHAR(40) NOT NULL DEFAULT '',
        title VARCHAR(255) NOT NULL DEFAULT '',
        service_type VARCHAR(120) NOT NULL DEFAULT '',
        duration_label VARCHAR(80) NOT NULL DEFAULT '',
        description TEXT NULL,
        amount_base BIGINT NOT NULL DEFAULT 0,
        discount BIGINT NOT NULL DEFAULT 0,
        vat_amount BIGINT NOT NULL DEFAULT 0,
        amount_final BIGINT NOT NULL DEFAULT 0,
        status VARCHAR(24) NOT NULL DEFAULT 'draft',
        gateway_ref VARCHAR(80) NOT NULL DEFAULT '',
        gateway_trace VARCHAR(80) NOT NULL DEFAULT '',
        project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        meta_json LONGTEXT NULL,
        paid_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY order_code (order_code),
        KEY user_id (user_id),
        KEY status (status),
        KEY service_key (service_key)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('casting_orders_db_version', '1');
}

function casting_orders_ensure_table(): void
{
    if ((string) get_option('casting_orders_db_version', '') !== '1') {
        casting_orders_install();
    }
}

/**
 * کاتالوگ خدمات پولی
 *
 * @return array<string, array<string, mixed>>
 */
function casting_paid_services_catalog(): array
{
    if (!function_exists('casting_premium_plans')) {
        require_once __DIR__ . '/premium.php';
    }
    $plans = casting_premium_plans();
    $p90 = is_array($plans['featured_90'] ?? null) ? $plans['featured_90'] : ['price' => 210000, 'unit_price' => 70000];
    $p180 = is_array($plans['featured_180'] ?? null) ? $plans['featured_180'] : ['price' => 370000, 'unit_price' => 61667];
    $p365 = is_array($plans['featured_365'] ?? null) ? $plans['featured_365'] : ['price' => 700000, 'unit_price' => 58333];

    return [
        'premium' => [
            'key'          => 'premium',
            'title'        => 'عضویت ویژه پرتال ۷رخ',
            'service_type' => 'ارتقای حساب کاربری',
            'duration'     => '۳ ماه',
            'days'         => 90,
            'months'       => 3,
            'unit_price'   => (int) ($p90['unit_price'] ?? 70000),
            'amount_base'  => (int) ($p90['price'] ?? 210000),
            'description'  => 'برنامه فعال‌سازی عضویت ویژه. با فعال‌سازی، به جستجوی کاربران، شروع گفتگو و اولویت در نتایج دسترسی دارید.',
            'plans'        => [
                'featured_90' => [
                    'label'        => 'بسته ۳ ماهه',
                    'plan_key'     => 'featured_90',
                    'days'         => 90,
                    'months'       => 3,
                    'period_label' => '۳ ماه',
                    'amount_base'  => (int) ($p90['price'] ?? 210000),
                ],
                'featured_180' => [
                    'label'        => 'بسته ۶ ماهه',
                    'plan_key'     => 'featured_180',
                    'days'         => 180,
                    'months'       => 6,
                    'period_label' => '۶ ماه',
                    'amount_base'  => (int) ($p180['price'] ?? 370000),
                ],
                'featured_365' => [
                    'label'        => 'بسته ۱۲ ماهه',
                    'plan_key'     => 'featured_365',
                    'days'         => 365,
                    'months'       => 12,
                    'period_label' => '۱۲ ماه',
                    'amount_base'  => (int) ($p365['price'] ?? 700000),
                ],
            ],
            'cancel_url'   => 'premium.php',
            'success_note' => 'پس از تأیید پرداخت، عضویت ویژه روی حساب شما فعال می‌شود.',
        ],
        'casting_call' => [
            'key'          => 'casting_call',
            'title'        => 'انتشار فراخوان کستینگ',
            'service_type' => 'فراخوان جذب بازیگر',
            'duration'     => '',
            'description'  => 'هزینه انتشار یک فراخوان کستینگ در پورتال ۷رخ بر اساس نوع پروژه محاسبه می‌شود.',
            'cancel_url'   => 'director-desk.php',
            'types'        => [
                'theater'    => [
                    'label'       => 'فراخوان تئاتر',
                    'amount_base' => 700000,
                ],
                'short_film' => [
                    'label'       => 'فراخوان فیلم کوتاه',
                    'amount_base' => 700000,
                ],
                'cinema'     => [
                    'label'       => 'فراخوان فیلم سینمایی',
                    'amount_base' => 7000000,
                ],
                'tv'         => [
                    'label'       => 'فراخوان تلویزیونی / سریال',
                    'amount_base' => 7000000,
                ],
            ],
            'success_note' => 'پس از پرداخت موفق می‌توانید فراخوان را ارسال و در فید فرصت‌ها منتشر کنید.',
        ],
        'advertising' => [
            'key'          => 'advertising',
            'title'        => 'تبلیغات',
            'service_type' => 'بنر و پوستر تبلیغاتی',
            'duration'     => '',
            'description'  => 'نمایش بنر/پوستر تبلیغاتی در محل ویژه صفحهٔ اصلی پورتال ۷رخ.',
            'cancel_url'   => 'cart.php',
            'types'        => [
                'banner_theater' => [
                    'label'       => 'بنر پوستر تئاتر',
                    'amount_base' => 1000000,
                ],
                'banner_film' => [
                    'label'       => 'بنر پوستر فیلم',
                    'amount_base' => 3000000,
                ],
            ],
            'success_note' => 'پس از پرداخت موفق، تیم پشتیبانی برای هماهنگی نمایش بنر با شما تماس می‌گیرد.',
        ],
    ];
}

/**
 * @return array{base:int,discount:int,vat:int,final:int}
 */
function casting_checkout_calc_amounts(int $base, int $discount = 0): array
{
    $base = max(0, $base);
    $discount = max(0, min($discount, $base));
    $subtotal = $base - $discount;
    $vat = (int) round($subtotal * casting_vat_rate());
    $final = $subtotal + $vat;

    return [
        'base'     => $base,
        'discount' => $discount,
        'vat'      => $vat,
        'final'    => $final,
    ];
}

/**
 * کاشی‌های فروشگاه برای سبد / مهمان
 *
 * @return list<array{group:string,label:string,meta:string,service:string,plan:string,price_base:int,vat:int,price_final:int,badge:string,image:string}>
 */
function casting_shop_catalog_tiles(): array
{
    if (!function_exists('casting_premium_plans')) {
        require_once __DIR__ . '/premium.php';
    }
    $tile_images = [
        'premium' => [
            'featured_90'  => 'images/shop-premium-3m.webp',
            'featured_180' => 'images/shop-premium-6m.webp',
            'featured_365' => 'images/shop-premium-12m.webp',
        ],
        'casting_call' => [
            'theater'    => 'images/shop-call-theater.webp',
            'short_film' => 'images/shop-call-short-film.webp',
            'cinema'     => 'images/shop-call-cinema.webp',
            'tv'         => 'images/shop-call-tv.webp',
        ],
        'advertising' => [
            'banner_theater' => 'images/shop-call-theater.webp',
            'banner_film'    => 'images/shop-call-cinema.webp',
        ],
    ];
    $tiles = [];
    foreach (casting_premium_plans() as $key => $p) {
        if ($key === 'featured_30') {
            continue;
        }
        $calc = casting_checkout_calc_amounts((int) $p['price']);
        $img = (string) ($tile_images['premium'][$key] ?? '');
        $tiles[] = [
            'group'       => 'عضویت ویژه',
            'label'       => 'عضویت ویژه — ' . (string) ($p['period_label'] ?? ''),
            'meta'        => 'ارتقای حساب کاربری · ' . (string) ($p['period_label'] ?? ''),
            'service'     => 'premium',
            'plan'        => (string) $key,
            'price_base'  => $calc['base'],
            'vat'         => $calc['vat'],
            'price_final' => $calc['final'],
            'badge'       => (string) ($p['period_label'] ?? ''),
            'image'       => $img !== '' ? casting_asset($img) : '',
        ];
    }
    $catalog = casting_paid_services_catalog();
    $call_types = is_array($catalog['casting_call']['types'] ?? null) ? $catalog['casting_call']['types'] : [];
    foreach ($call_types as $type_key => $type) {
        if (!is_array($type)) {
            continue;
        }
        $calc = casting_checkout_calc_amounts((int) ($type['amount_base'] ?? 0));
        $img = (string) ($tile_images['casting_call'][$type_key] ?? '');
        $tiles[] = [
            'group'       => 'فراخوان کستینگ',
            'label'       => (string) ($type['label'] ?? $type_key),
            'meta'        => 'انتشار یک فراخوان در پورتال ۷رخ',
            'service'     => 'casting_call',
            'plan'        => (string) $type_key,
            'price_base'  => $calc['base'],
            'vat'         => $calc['vat'],
            'price_final' => $calc['final'],
            'badge'       => '',
            'image'       => $img !== '' ? casting_asset($img) : '',
        ];
    }
    $ad_types = is_array($catalog['advertising']['types'] ?? null) ? $catalog['advertising']['types'] : [];
    foreach ($ad_types as $type_key => $type) {
        if (!is_array($type)) {
            continue;
        }
        $calc = casting_checkout_calc_amounts((int) ($type['amount_base'] ?? 0));
        $img = (string) ($tile_images['advertising'][$type_key] ?? '');
        $tiles[] = [
            'group'       => 'تبلیغات',
            'label'       => (string) ($type['label'] ?? $type_key),
            'meta'        => 'نمایش در محل ویژه صفحهٔ اصلی',
            'service'     => 'advertising',
            'plan'        => (string) $type_key,
            'price_base'  => $calc['base'],
            'vat'         => $calc['vat'],
            'price_final' => $calc['final'],
            'badge'       => 'تبلیغات',
            'image'       => $img !== '' ? casting_asset($img) : '',
        ];
    }

    return $tiles;
}

function casting_order_new_code(): string
{
    $stamp = wp_date('Ymd');
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    return '7R-' . $stamp . '-' . $rand;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function casting_order_from_row(?array $row): array
{
    if (!$row) {
        return [];
    }
    $meta = [];
    $raw = (string) ($row['meta_json'] ?? '');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    return [
        'id'             => (int) ($row['id'] ?? 0),
        'order_code'     => (string) ($row['order_code'] ?? ''),
        'user_id'        => (int) ($row['user_id'] ?? 0),
        'service_key'    => (string) ($row['service_key'] ?? ''),
        'plan_key'       => (string) ($row['plan_key'] ?? ''),
        'title'          => (string) ($row['title'] ?? ''),
        'service_type'   => (string) ($row['service_type'] ?? ''),
        'duration_label' => (string) ($row['duration_label'] ?? ''),
        'description'    => (string) ($row['description'] ?? ''),
        'amount_base'    => (int) ($row['amount_base'] ?? 0),
        'discount'       => (int) ($row['discount'] ?? 0),
        'vat_amount'     => (int) ($row['vat_amount'] ?? 0),
        'amount_final'   => (int) ($row['amount_final'] ?? 0),
        'status'         => (string) ($row['status'] ?? 'draft'),
        'gateway_ref'    => (string) ($row['gateway_ref'] ?? ''),
        'gateway_trace'  => (string) ($row['gateway_trace'] ?? ''),
        'project_id'     => (int) ($row['project_id'] ?? 0),
        'meta'           => $meta,
        'paid_at'        => (string) ($row['paid_at'] ?? ''),
        'created_at'     => (string) ($row['created_at'] ?? ''),
        'updated_at'     => (string) ($row['updated_at'] ?? ''),
    ];
}

function casting_get_order_by_code(string $code): array
{
    casting_orders_ensure_table();
    $code = sanitize_text_field($code);
    if ($code === '') {
        return [];
    }
    global $wpdb;
    $table = casting_orders_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_code = %s LIMIT 1", $code), ARRAY_A);

    return casting_order_from_row(is_array($row) ? $row : null);
}

function casting_get_order_by_id(int $id): array
{
    casting_orders_ensure_table();
    if ($id <= 0) {
        return [];
    }
    global $wpdb;
    $table = casting_orders_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id), ARRAY_A);

    return casting_order_from_row(is_array($row) ? $row : null);
}

/**
 * ساخت مشخصات سفارش از سرویس/پلن
 *
 * @return array{ok:bool,error:string,draft?:array<string,mixed>}
 */
function casting_checkout_build_draft(string $service_key, string $plan_or_type = '', int $project_id = 0, int $discount = 0): array
{
    $catalog = casting_paid_services_catalog();
    $service_key = sanitize_key($service_key);
    if (!isset($catalog[$service_key])) {
        return ['ok' => false, 'error' => 'خدمت انتخاب‌شده نامعتبر است.'];
    }
    $svc = $catalog[$service_key];

    if ($service_key === 'premium') {
        $plans = $svc['plans'];
        $plan_key = sanitize_key($plan_or_type !== '' ? $plan_or_type : 'featured_90');
        if (!isset($plans[$plan_key])) {
            $plan_key = 'featured_90';
        }
        $plan = $plans[$plan_key];
        $amounts = casting_checkout_calc_amounts((int) $plan['amount_base'], $discount);

        return [
            'ok'    => true,
            'error' => '',
            'draft' => [
                'service_key'    => 'premium',
                'plan_key'       => $plan_key,
                'title'          => (string) $svc['title'],
                'service_type'   => (string) $svc['service_type'],
                'duration_label' => (string) $plan['period_label'],
                'description'    => (string) $svc['description'] . ' بسته انتخابی: ' . (string) $plan['label'] . '.',
                'plan_label'     => (string) $plan['label'],
                'amount_base'    => $amounts['base'],
                'discount'       => $amounts['discount'],
                'vat_amount'     => $amounts['vat'],
                'amount_final'   => $amounts['final'],
                'project_id'     => 0,
                'cancel_url'     => (string) $svc['cancel_url'],
                'meta'           => [
                    'months'     => (int) $plan['months'],
                    'days'       => (int) $plan['days'],
                    'unit_price' => (int) round(((int) $plan['amount_base']) / max(1, (int) $plan['months'])),
                ],
            ],
        ];
    }

    if ($service_key === 'casting_call') {
        $types = $svc['types'];
        $type_key = sanitize_key($plan_or_type);
        if ($type_key === '' && $project_id > 0 && function_exists('casting_director_get_project')) {
            // map from project
            $type_key = '';
        }
        // legacy aliases
        if ($type_key === 'film') {
            $type_key = 'cinema';
        }
        if ($type_key === 'series') {
            $type_key = 'tv';
        }
        if ($type_key === 'other' || $type_key === '') {
            return ['ok' => false, 'error' => 'نوع پروژه فراخوان را مشخص کنید (تئاتر، فیلم کوتاه، سینمایی یا تلویزیونی).'];
        }
        if (!isset($types[$type_key])) {
            return ['ok' => false, 'error' => 'نوع فراخوان نامعتبر است.'];
        }
        $type = $types[$type_key];
        $amounts = casting_checkout_calc_amounts((int) $type['amount_base'], $discount);
        $cancel = (string) $svc['cancel_url'];
        if ($project_id > 0) {
            $cancel = 'director-desk.php?project=' . $project_id;
        }

        return [
            'ok'    => true,
            'error' => '',
            'draft' => [
                'service_key'    => 'casting_call',
                'plan_key'       => $type_key,
                'title'          => (string) $type['label'],
                'service_type'   => (string) $svc['service_type'],
                'duration_label' => 'یک‌بار انتشار فراخوان',
                'description'    => (string) $svc['description'] . ' نوع انتخابی: ' . (string) $type['label'] . '.',
                'plan_label'     => (string) $type['label'],
                'amount_base'    => $amounts['base'],
                'discount'       => $amounts['discount'],
                'vat_amount'     => $amounts['vat'],
                'amount_final'   => $amounts['final'],
                'project_id'     => max(0, $project_id),
                'cancel_url'     => $cancel,
                'meta'           => [
                    'project_type' => $type_key,
                ],
            ],
        ];
    }

    if ($service_key === 'advertising') {
        $types = is_array($svc['types'] ?? null) ? $svc['types'] : [];
        $type_key = sanitize_key($plan_or_type);
        if ($type_key === '' || !isset($types[$type_key]) || !is_array($types[$type_key])) {
            return ['ok' => false, 'error' => 'نوع تبلیغات نامعتبر است.'];
        }
        $type = $types[$type_key];
        $amounts = casting_checkout_calc_amounts((int) ($type['amount_base'] ?? 0), $discount);

        return [
            'ok'    => true,
            'error' => '',
            'draft' => [
                'service_key'    => 'advertising',
                'plan_key'       => $type_key,
                'title'          => (string) ($type['label'] ?? 'تبلیغات'),
                'service_type'   => (string) ($svc['service_type'] ?? 'تبلیغات'),
                'duration_label' => 'نمایش تبلیغاتی',
                'description'    => (string) ($svc['description'] ?? '') . ' نوع انتخابی: ' . (string) ($type['label'] ?? '') . '.',
                'plan_label'     => (string) ($type['label'] ?? ''),
                'amount_base'    => $amounts['base'],
                'discount'       => $amounts['discount'],
                'vat_amount'     => $amounts['vat'],
                'amount_final'   => $amounts['final'],
                'project_id'     => 0,
                'cancel_url'     => (string) ($svc['cancel_url'] ?? 'cart.php'),
                'meta'           => [
                    'ad_type' => $type_key,
                ],
            ],
        ];
    }

    return ['ok' => false, 'error' => 'خدمت پشتیبانی نمی‌شود.'];
}

/**
 * @param array<string, mixed> $draft
 * @return array{ok:bool,error:string,order?:array<string,mixed>}
 */
function casting_checkout_create_order(int $user_id, array $draft): array
{
    casting_orders_ensure_table();
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر نامعتبر است.'];
    }
    global $wpdb;
    $table = casting_orders_table();
    $now = current_time('mysql');
    $code = casting_order_new_code();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->insert(
        $table,
        [
            'order_code'     => $code,
            'user_id'        => $user_id,
            'service_key'    => (string) ($draft['service_key'] ?? ''),
            'plan_key'       => (string) ($draft['plan_key'] ?? ''),
            'title'          => (string) ($draft['title'] ?? ''),
            'service_type'   => (string) ($draft['service_type'] ?? ''),
            'duration_label' => (string) ($draft['duration_label'] ?? ''),
            'description'    => (string) ($draft['description'] ?? ''),
            'amount_base'    => (int) ($draft['amount_base'] ?? 0),
            'discount'       => (int) ($draft['discount'] ?? 0),
            'vat_amount'     => (int) ($draft['vat_amount'] ?? 0),
            'amount_final'   => (int) ($draft['amount_final'] ?? 0),
            'status'         => 'pending',
            'project_id'     => (int) ($draft['project_id'] ?? 0),
            'meta_json'      => wp_json_encode($draft['meta'] ?? [], JSON_UNESCAPED_UNICODE),
            'created_at'     => $now,
            'updated_at'     => $now,
        ],
        ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s']
    );
    if (!$ok) {
        return ['ok' => false, 'error' => 'ثبت سفارش ناموفق بود.'];
    }

    $order = casting_get_order_by_code($code);
    if ($order === []) {
        return ['ok' => false, 'error' => 'سفارش ثبت شد اما قابل خواندن نیست.'];
    }

    return ['ok' => true, 'error' => '', 'order' => $order];
}

function casting_order_status_label(string $status): string
{
    $map = [
        'draft'            => 'پیش‌نویس',
        'pending'          => 'در انتظار پرداخت',
        'awaiting_payment' => 'منتقل‌شده به درگاه',
        'paid'             => 'موفق',
        'failed'           => 'ناموفق',
        'cancelled'        => 'لغو شده',
    ];

    return $map[$status] ?? $status;
}

/**
 * @param array<string, mixed> $extra
 */
function casting_order_update(int $order_id, array $extra): bool
{
    casting_orders_ensure_table();
    if ($order_id <= 0) {
        return false;
    }
    global $wpdb;
    $table = casting_orders_table();
    $extra['updated_at'] = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    return $wpdb->update($table, $extra, ['id' => $order_id]) !== false;
}

/**
 * پس از پرداخت موفق — فعال‌سازی خدمت
 *
 * @return array{ok:bool,error:string}
 */
function casting_checkout_fulfill_order(array $order): array
{
    if ($order === [] || (string) ($order['status'] ?? '') === 'paid') {
        return ['ok' => true, 'error' => ''];
    }
    $user_id = (int) ($order['user_id'] ?? 0);
    $service = (string) ($order['service_key'] ?? '');
    $meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
    $cart_items = is_array($meta['cart_items'] ?? null) ? $meta['cart_items'] : [];

    // سفارش ترکیبی از سبد
    if ($service === 'cart' && $cart_items !== []) {
        foreach ($cart_items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $sk = (string) ($it['service_key'] ?? '');
            if ($sk === 'premium') {
                if (!function_exists('casting_premium_activate_for_user')) {
                    require_once __DIR__ . '/premium.php';
                }
                $days = (int) (($it['meta']['days'] ?? 0) ?: 90);
                casting_premium_activate_for_user(
                    $user_id,
                    $days,
                    (string) ($it['plan_key'] ?? 'featured_90'),
                    (int) ($it['amount_final'] ?? 0),
                    (string) $order['order_code']
                );
            }
            if ($sk === 'casting_call') {
                $project_id = (int) ($it['project_id'] ?? 0);
                $type_key = sanitize_key((string) (($it['meta']['project_type'] ?? '') ?: ($it['plan_key'] ?? '')));
                if ($project_id > 0) {
                    update_user_meta($user_id, 'casting_casting_call_credit_' . $project_id, (string) $order['order_code']);
                }
                if ($type_key !== '') {
                    update_user_meta($user_id, 'casting_casting_call_credit_type_' . $type_key, (string) $order['order_code']);
                }
                update_user_meta($user_id, 'casting_last_casting_call_credit', (string) $order['order_code']);
            }
        }
    } elseif ($service === 'premium') {
        if (!function_exists('casting_premium_activate_for_user')) {
            require_once __DIR__ . '/premium.php';
        }
        $days = (int) (($meta['days'] ?? 0) ?: 90);
        $plan_key = (string) ($order['plan_key'] ?? 'featured_90');
        $result = casting_premium_activate_for_user($user_id, $days, $plan_key, (int) $order['amount_final'], (string) $order['order_code']);
        if (!$result['ok']) {
            return $result;
        }
    } elseif ($service === 'casting_call') {
        $project_id = (int) ($order['project_id'] ?? 0);
        $type_key = sanitize_key((string) (($meta['project_type'] ?? '') ?: ($order['plan_key'] ?? '')));
        if ($project_id > 0) {
            update_user_meta($user_id, 'casting_casting_call_credit_' . $project_id, (string) $order['order_code']);
        }
        if ($type_key !== '') {
            update_user_meta($user_id, 'casting_casting_call_credit_type_' . $type_key, (string) $order['order_code']);
        }
        update_user_meta($user_id, 'casting_last_casting_call_credit', (string) $order['order_code']);
    }

    if (function_exists('casting_add_transaction')) {
        casting_add_transaction($user_id, [
            'type'   => 'gateway_payment',
            'title'  => (string) ($order['title'] ?? 'پرداخت آنلاین'),
            'amount' => (int) ($order['amount_final'] ?? 0),
            'status' => 'approved',
            'ref'    => (string) ($order['order_code'] ?? ''),
        ]);
    }

    casting_order_update((int) $order['id'], [
        'status'  => 'paid',
        'paid_at' => current_time('mysql'),
    ]);

    return ['ok' => true, 'error' => ''];
}

function casting_user_has_casting_call_credit(int $user_id, int $project_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if ($project_id > 0) {
        $code = (string) get_user_meta($user_id, 'casting_casting_call_credit_' . $project_id, true);
        if ($code !== '') {
            $order = casting_get_order_by_code($code);
            if ($order !== [] && (string) ($order['status'] ?? '') === 'paid' && (int) ($order['user_id'] ?? 0) === $user_id) {
                return true;
            }
        }
        if (function_exists('casting_director_get_project')) {
            $project = casting_director_get_project($user_id, $project_id);
            $type_key = casting_checkout_map_project_type((string) ($project['project_type'] ?? ''));
            if ($type_key !== '') {
                $type_code = (string) get_user_meta($user_id, 'casting_casting_call_credit_type_' . $type_key, true);
                if ($type_code !== '') {
                    $order = casting_get_order_by_code($type_code);
                    if ($order !== [] && (string) ($order['status'] ?? '') === 'paid' && (int) ($order['user_id'] ?? 0) === $user_id) {
                        return true;
                    }
                }
            }
        }
    }

    return false;
}

function casting_consume_casting_call_credit(int $user_id, int $project_id): void
{
    if ($user_id <= 0 || $project_id <= 0) {
        return;
    }
    $code = (string) get_user_meta($user_id, 'casting_casting_call_credit_' . $project_id, true);
    if ($code !== '') {
        delete_user_meta($user_id, 'casting_casting_call_credit_' . $project_id);
        return;
    }
    if (function_exists('casting_director_get_project')) {
        $project = casting_director_get_project($user_id, $project_id);
        $type_key = casting_checkout_map_project_type((string) ($project['project_type'] ?? ''));
        if ($type_key !== '') {
            delete_user_meta($user_id, 'casting_casting_call_credit_type_' . $type_key);
        }
    }
}

/**
 * نگاشت نوع پروژه میز کارگردان → کلید قیمت فراخوان
 */
function casting_checkout_map_project_type(string $project_type): string
{
    $project_type = sanitize_key($project_type);
    $map = [
        'theater'    => 'theater',
        'short_film' => 'short_film',
        'cinema'     => 'cinema',
        'tv'         => 'tv',
        'film'       => 'cinema',
        'series'     => 'tv',
        'other'      => '',
    ];

    return $map[$project_type] ?? '';
}

function casting_format_toman(int $amount): string
{
    return number_format($amount) . ' تومان';
}
