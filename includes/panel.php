<?php
declare(strict_types=1);

require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/premium.php';
require_once __DIR__ . '/director-workspace.php';
require_once __DIR__ . '/director-desk.php';
require_once __DIR__ . '/admin-access.php';
require_once __DIR__ . '/chat-rules.php';
require_once __DIR__ . '/layout.php';

/**
 * گروه‌های منوی پنل (کستینگ / شبکه / حساب)
 *
 * @return list<array{id:string,label:string,items:list<array{key:string,label:string,href:string,icon?:string,external?:bool}>}>
 */
function casting_panel_nav_groups(): array
{
    return [
        [
            'id'    => 'main',
            'label' => 'اصلی',
            'items' => [
                ['key' => 'home',  'label' => 'صفحه اصلی', 'href' => 'home.php'],
                ['key' => 'panel', 'label' => 'پروفایل من', 'href' => 'panel.php'],
            ],
        ],
        [
            'id'    => 'casting',
            'label' => 'کستینگ',
            'items' => [
                ['key' => 'opportunities', 'label' => 'فرصت‌ها',         'href' => 'opportunities.php'],
                ['key' => 'my-requests',   'label' => 'فراخوان کستینگ', 'href' => 'my-requests.php'],
                ['key' => 'desk',          'label' => 'پروژه‌ها',        'href' => 'director-desk.php'],
                ['key' => 'briefs',        'label' => 'تکالیف',         'href' => 'my-briefs.php'],
                ['key' => 'favorites',     'label' => 'لیست کاندیدا',   'href' => 'favorites.php'],
                ['key' => 'saved',         'label' => 'ذخیره‌شده‌ها',     'href' => 'saved-media.php'],
            ],
        ],
        [
            'id'    => 'network',
            'label' => 'شبکه',
            'items' => [
                ['key' => 'search',   'label' => 'جستجوی کاربران',           'href' => 'search-users.php'],
                ['key' => 'newest',   'label' => 'جدیدترین کاربران',         'href' => 'newest-users.php'],
                ['key' => 'visitors', 'label' => 'بازدیدکنندگان پروفایل من', 'href' => 'profile-visitors.php'],
                [
                    'key'      => 'news',
                    'label'    => 'اخبار ۷ رخ',
                    'href'     => casting_main_site_url(),
                    'external' => true,
                ],
            ],
        ],
        [
            'id'    => 'account',
            'label' => 'حساب',
            'items' => [
                ['key' => 'cart',         'label' => 'خرید اشتراک',          'href' => 'cart.php'],
                ['key' => 'transactions', 'label' => 'تراکنش‌های مالی',      'href' => 'transactions.php'],
                ['key' => 'cancel',       'label' => 'انصراف از عضویت',      'href' => 'cancel-membership.php'],
                ['key' => 'rules',        'label' => 'قوانین',               'href' => 'rules.php'],
                ['key' => 'contact',      'label' => 'تماس با ما',           'href' => 'contact.php'],
                ['key' => 'faq',          'label' => 'سوالات متداول',        'href' => 'faq.php'],
                ['key' => 'logout',       'label' => 'خروج',                 'href' => 'logout.php'],
            ],
        ],
    ];
}

/**
 * منوی کامل دسکتاپ (flat — سازگاری با کدهای قبلی)
 *
 * @return array<int, array{key:string,label:string,href:string,icon?:string,external?:bool}>
 */
function casting_panel_nav_items_desktop(): array
{
    $flat = [];
    foreach (casting_panel_nav_groups() as $group) {
        foreach ($group['items'] as $item) {
            $flat[] = $item;
        }
    }

    return $flat;
}

/**
 * منوی موبایل — همان ترتیب دسکتاپ
 *
 * @return array<int, array{key:string,label:string,href:string,icon?:string,external?:bool}>
 */
function casting_panel_nav_items_mobile(): array
{
    return casting_panel_nav_items_desktop();
}

/**
 * @deprecated از casting_panel_nav_items_mobile استفاده کنید
 * @return array<int, array{key:string,label:string,href:string,icon?:string}>
 */
function casting_panel_nav_items(): array
{
    return casting_panel_nav_items_mobile();
}

/**
 * کلید منوی موبایل برای هایلایت (صفحات فرعی → بخش والد)
 */
function casting_panel_nav_highlight_key(string $active): string
{
    $map = [
        'premium'      => 'cart',
        'cart'         => 'cart',
        'receipt'      => 'receipt',
        'transactions' => 'transactions',
        'cancel'       => 'cancel',
        'password'     => 'password',
        'phone'        => 'panel',
        'photo'        => 'photo',
        'gallery'      => 'gallery',
        'following'    => 'following',
        'blocked'      => 'blocked',
        'blockers'     => 'blocked',
        'contact'      => 'contact',
        'faq'          => 'faq',
        'rules'        => 'rules',
        'newest'       => 'newest',
        'my-profile'   => 'panel',
        'edit-profile' => 'panel',
        'messages'     => 'panel',
        'news'         => 'news',
    ];

    return $map[$active] ?? $active;
}

/**
 * @return list<string>
 */
function casting_panel_nav_keys_hidden_for_director(): array
{
    return ['cancel'];
}

/**
 * @param array<int, array{title:string,desc:string,href:string,badge?:int}> $tiles
 */
function casting_render_panel_hub_tiles(array $tiles): void
{
    if ($tiles === []) {
        return;
    }
    echo '<div class="panel-hub-grid">';
    foreach ($tiles as $tile) {
        $href = (string) ($tile['href'] ?? '');
        if ($href !== '' && !str_starts_with($href, 'http') && !str_starts_with($href, '#')) {
            $href = casting_url($href);
        }
        $badge = (int) ($tile['badge'] ?? 0);
        ?>
    <a class="panel-hub-tile" href="<?= casting_e($href) ?>">
      <strong><?= casting_e((string) ($tile['title'] ?? '')) ?></strong>
      <?php if ($badge > 0) : ?>
        <span class="nav-badge"><?= $badge ?></span>
      <?php endif; ?>
      <span><?= casting_e((string) ($tile['desc'] ?? '')) ?></span>
    </a>
        <?php
    }
    echo '</div>';
}

function casting_render_premium_account_links(string $wrapper_class = 'cta-row profile-premium-links'): void
{
    ?>
    <div class="<?= casting_e($wrapper_class) ?>">
      <a class="btn btn-ghost" href="<?= casting_e(casting_url('cart.php')) ?>">خرید اشتراک</a>
      <a class="btn btn-ghost" href="<?= casting_e(casting_url('transactions.php')) ?>">تراکنش‌های مالی</a>
    </div>
    <?php
}

function casting_panel_profile_url(int $user_id): string
{
    $role = casting_get_user_role($user_id);
    if ($role === 'talent') {
        return 'member.php?id=' . $user_id;
    }
    return 'member.php?id=' . $user_id;
}

/**
 * آیا آیتم منو برای این کاربر نمایش داده می‌شود؟
 *
 * @param array{key:string,label:string,href:string,external?:bool} $item
 * @param array{user:?\WP_User,can_member_search?:bool} $ctx
 */
function casting_panel_nav_item_is_visible(array $item, array $ctx): bool
{
    $user = $ctx['user'] ?? null;
    $key = (string) ($item['key'] ?? '');

    // موقتاً مخفی — صفحه cancel-membership.php حفظ شده است
    if ($key === 'cancel') {
        return false;
    }
    // ثبت فیش کارت‌به‌کارت از منوی اصلی حذف شده — فقط پرداخت آنلاین
    if ($key === 'receipt') {
        return false;
    }
    if ($key === 'desk' && (!$user || !casting_user_is_director_role((int) $user->ID))) {
        return false;
    }
    if ($key === 'favorites' && (!$user || !casting_user_is_director_role((int) $user->ID))) {
        return false;
    }
    if ($key === 'saved' && (!$user || !casting_user_is_director_role((int) $user->ID))) {
        return false;
    }
    if ($key === 'briefs' && (!$user || casting_get_user_role((int) $user->ID) !== 'talent')) {
        return false;
    }
    if ($user && casting_user_is_director_role((int) $user->ID)
        && in_array($key, casting_panel_nav_keys_hidden_for_director(), true)) {
        if ($key === 'photo' && casting_user_can_upload_portraits((int) $user->ID)) {
            return true;
        }
        return false;
    }
    if ($key === 'photo' && $user && !casting_user_can_upload_portraits((int) $user->ID)) {
        return false;
    }

    return true;
}

/**
 * @param array<int, array{key:string,label:string,href:string,external?:bool}> $items
 * @param array{
 *   user:?\WP_User,
 *   active:string,
 *   highlight:bool,
 *   can_member_search:bool,
 *   unread_peers:int,
 *   pending_receipts:int,
 *   unread_contacts:int,
 *   request_count:int,
 *   pending_brief_count:int,
 *   panel_premium_until:?int
 * } $ctx
 */
function casting_render_panel_nav_item_list(array $items, array $ctx): void
{
    $user = $ctx['user'];
    $active = (string) $ctx['active'];
    $highlight_mode = !empty($ctx['highlight']);
    $can_member_search = !empty($ctx['can_member_search']);
    $unread_peers = (int) $ctx['unread_peers'];
    $pending_receipts = (int) $ctx['pending_receipts'];
    $unread_contacts = (int) $ctx['unread_contacts'];
    $request_count = (int) $ctx['request_count'];
    $pending_brief_count = (int) $ctx['pending_brief_count'];
    $desk_response_count = (int) ($ctx['desk_response_count'] ?? 0);
    $panel_premium_until = $ctx['panel_premium_until'];
    $current = $highlight_mode ? casting_panel_nav_highlight_key($active) : $active;

    foreach ($items as $item) {
        if (!casting_panel_nav_item_is_visible($item, $ctx)) {
            continue;
        }

        $is_external = !empty($item['external']);
        $href = (string) $item['href'];
        if (!$is_external && $href !== '' && !str_starts_with($href, 'http')) {
            $href = casting_url($href);
        }
        if ($item['key'] === 'cart' && $unread_peers === 0 && $pending_receipts > 0) {
            $href .= '#admin-receipts';
        }
        if ($item['key'] === 'membership' && $unread_peers === 0 && $pending_receipts > 0) {
            $href = casting_url('cart.php#admin-receipts');
        }
        if ($item['key'] === 'search' && !$can_member_search) {
            ?>
          <span class="panel-nav-link is-disabled" aria-disabled="true" title="برای دسترسی به جستجو، کارگردان باشید یا اشتراک ویژه فعال کنید">
            <span class="panel-nav-label"><?= casting_brandify($item['label']) ?></span>
          </span>
            <?php
            continue;
        }
        ?>
          <a class="panel-nav-link<?= $is_external ? ' panel-nav-link-external' : '' ?> <?= $current === $item['key'] ? 'is-active' : '' ?>" href="<?= casting_e($href) ?>">
            <span class="panel-nav-label"><?= casting_brandify($item['label']) ?></span>
            <?php if ($item['key'] === 'membership' && $panel_premium_until !== null && $user) : ?>
              <span class="nav-premium-countdown" data-premium-until-ts="<?= (int) $panel_premium_until ?>" title="زمان باقی‌مانده حساب ویژه">
                <span data-premium-countdown><?= casting_e(casting_premium_countdown_nav_label((int) $user->ID)) ?></span>
              </span>
            <?php elseif (($item['key'] === 'cart' || $item['key'] === 'membership') && $pending_receipts > 0) : ?>
              <span class="nav-badge" aria-label="<?= casting_e((string) $pending_receipts) ?> فیش در انتظار"><?= (int) $pending_receipts ?></span>
            <?php elseif ($item['key'] === 'cart' && (int) ($ctx['cart_count'] ?? 0) > 0) : ?>
              <span class="nav-badge" aria-label="<?= (int) ($ctx['cart_count'] ?? 0) ?> مورد در خرید اشتراک"><?= (int) ($ctx['cart_count'] ?? 0) ?></span>
            <?php elseif ($item['key'] === 'my-requests' && $request_count > 0) : ?>
              <span class="nav-badge" aria-label="<?= casting_e((string) $request_count) ?> مورد جدید"><?= (int) $request_count ?></span>
            <?php elseif ($item['key'] === 'desk' && $desk_response_count > 0) : ?>
              <span class="nav-badge" aria-label="<?= casting_e((string) $desk_response_count) ?> پذیرش جدید"><?= (int) $desk_response_count ?></span>
            <?php elseif ($item['key'] === 'briefs' && $pending_brief_count > 0) : ?>
              <span class="nav-badge" aria-label="<?= casting_e((string) $pending_brief_count) ?> تکلیف"><?= (int) $pending_brief_count ?></span>
            <?php elseif (($item['key'] === 'contact' || $item['key'] === 'settings') && $unread_contacts > 0) : ?>
              <span class="nav-badge" aria-label="<?= casting_e((string) $unread_contacts) ?> پیام جدید"><?= (int) $unread_contacts ?></span>
            <?php endif; ?>
          </a>
        <?php
    }
}

/**
 * رندر منوی پنل با گروه‌بندی کستینگ / شبکه / حساب
 *
 * @param array{
 *   user:?\WP_User,
 *   active:string,
 *   highlight:bool,
 *   can_member_search:bool,
 *   unread_peers:int,
 *   pending_receipts:int,
 *   unread_contacts:int,
 *   request_count:int,
 *   pending_brief_count:int,
 *   panel_premium_until:?int
 * } $ctx
 */
function casting_render_panel_nav_groups(array $ctx): void
{
    foreach (casting_panel_nav_groups() as $group) {
        $visible = [];
        foreach ($group['items'] as $item) {
            if (casting_panel_nav_item_is_visible($item, $ctx)) {
                $visible[] = $item;
            }
        }
        if ($visible === []) {
            continue;
        }
        $group_id = 'panel-nav-group-' . (string) $group['id'];
        ?>
        <div class="panel-nav-group" data-nav-group="<?= casting_e((string) $group['id']) ?>">
          <p class="panel-nav-group-label" id="<?= casting_e($group_id) ?>"><?= casting_e((string) $group['label']) ?></p>
          <div class="panel-nav-group-items" role="group" aria-labelledby="<?= casting_e($group_id) ?>">
            <?php casting_render_panel_nav_item_list($visible, $ctx); ?>
          </div>
        </div>
        <?php
    }
}

/**
 * مجموع نشان‌های منوی همبرگری
 */
function casting_panel_menu_badge_count(): int
{
    $user = casting_current_user();
    if (!$user) {
        return 0;
    }

    $user_id = (int) $user->ID;
    $badge = 0;

    if (!function_exists('casting_dm_unread_peer_count')) {
        require_once __DIR__ . '/chat.php';
    }
    $unread_peers = casting_dm_unread_peer_count($user_id);
    $badge += $unread_peers;

    if (!function_exists('casting_user_new_request_count')) {
        require_once __DIR__ . '/request.php';
    }
    $badge += casting_user_new_request_count($user_id);

    if (casting_user_is_director_role($user_id)) {
        if (!function_exists('casting_director_new_project_response_count')) {
            require_once __DIR__ . '/director-desk.php';
        }
        $badge += casting_director_new_project_response_count($user_id);
    }

    if (!function_exists('casting_talent_pending_brief_count')) {
        require_once __DIR__ . '/talent-briefs.php';
    }
    if (casting_get_user_role($user_id) === 'talent') {
        $badge += casting_talent_pending_brief_count($user_id);
    }

    if (!function_exists('casting_contact_unread_count_for_user')) {
        require_once __DIR__ . '/contact-messages.php';
    }
    $badge += casting_contact_unread_count_for_user($user_id);

    if (!function_exists('casting_user_has_admin_permission')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if ($unread_peers === 0 && casting_user_has_admin_permission($user_id, 'approve_receipts')) {
        $badge += casting_admin_pending_receipt_count();
    }
    if ($unread_peers === 0 && casting_user_has_admin_permission($user_id, 'approve_media')) {
        if (!function_exists('casting_admin_pending_media_count')) {
            require_once __DIR__ . '/user-media.php';
        }
        $badge += casting_admin_pending_media_count();
    }

    return $badge;
}

function casting_render_panel_sidebar(string $active, string $page_title = ''): void
{
    $unread_peers = 0;
    $pending_receipts = 0;
    $pending_media = 0;
    $unread_contacts = 0;
    $request_count = 0;
    $pending_brief_count = 0;
    $desk_response_count = 0;
    $panel_premium_until = null;
    $panel_membership_number = '';
    $panel_referral_code = '';
    $user = casting_current_user();
    if ($user) {
        $user_id = (int) $user->ID;
        if (!function_exists('casting_get_membership_number')) {
            require_once __DIR__ . '/membership-number.php';
        }
        $panel_membership_number = casting_get_membership_number($user_id);
        if (!function_exists('casting_get_referral_code')) {
            require_once __DIR__ . '/referral.php';
        }
        $panel_referral_code = casting_get_referral_code($user_id);
        if (!function_exists('casting_dm_unread_peer_count')) {
            require_once __DIR__ . '/chat.php';
        }
        $unread_peers = casting_dm_unread_peer_count($user_id);
        if (!function_exists('casting_user_new_request_count')) {
            require_once __DIR__ . '/request.php';
        }
        $request_count = casting_user_new_request_count($user_id);
        if (casting_user_is_director_role($user_id)) {
            if (!function_exists('casting_director_new_project_response_count')) {
                require_once __DIR__ . '/director-desk.php';
            }
            $desk_response_count = casting_director_new_project_response_count($user_id);
        }
        if (!function_exists('casting_talent_pending_brief_count')) {
            require_once __DIR__ . '/talent-briefs.php';
        }
        if (casting_get_user_role($user_id) === 'talent') {
            $pending_brief_count = casting_talent_pending_brief_count($user_id);
        }
        if (!function_exists('casting_user_has_admin_permission')) {
            require_once __DIR__ . '/admin-access.php';
        }
        if (casting_user_has_admin_permission($user_id, 'approve_receipts')) {
            $pending_receipts = casting_admin_pending_receipt_count();
        }
        if (casting_user_has_admin_permission($user_id, 'approve_media')) {
            if (!function_exists('casting_admin_pending_media_count')) {
                require_once __DIR__ . '/user-media.php';
            }
            $pending_media = casting_admin_pending_media_count();
        }
        if (!function_exists('casting_contact_unread_count_for_user')) {
            require_once __DIR__ . '/contact-messages.php';
        }
        $unread_contacts = casting_contact_unread_count_for_user($user_id);
        if (casting_user_is_premium($user_id)) {
            $panel_premium_until = casting_premium_expire_timestamp($user_id);
        }
    }
    $can_member_search = $user && casting_user_can_member_search((int) $user->ID);
    $admin_nav = $user ? casting_panel_admin_nav_items((int) $user->ID) : [];
    $sidebar_page_title = trim($page_title);
    if ($sidebar_page_title === '') {
        $sidebar_page_title = $active === 'home' ? 'صفحه اصلی' : 'پنل کاربری';
    }
    // عنوان‌های ترکیبی document title مثل «خانه · بازیگر» → فقط بخش صفحه
    if (str_contains($sidebar_page_title, ' · ')) {
        $sidebar_page_title = trim(explode(' · ', $sidebar_page_title, 2)[0]);
    }
    $sidebar_photo = '';
    $sidebar_name = '';
    $sidebar_primary_activity = '';
    $sidebar_views = ['day' => 0, 'month' => 0];
    $sidebar_show_views = false;
    if ($user) {
        $sidebar_name = (string) $user->display_name;
        $sidebar_profile = casting_get_profile($user_id);
        $sidebar_photo = (string) ($sidebar_profile['photo_url'] ?? '');
        if ($sidebar_photo === '') {
            $profile_shot = casting_load_portrait($user_id, 'profile');
            $sidebar_photo = (string) ($profile_shot['url'] ?? '');
        }
        if ($sidebar_photo === '') {
            $closeup = casting_load_portrait($user_id, 'closeup');
            $sidebar_photo = (string) ($closeup['url'] ?? '');
        }
        $sidebar_activities = casting_normalize_activities(
            get_user_meta($user_id, 'casting_activities', true),
            $user_id
        );
        $sidebar_primary_activity = casting_user_primary_activity_label($user_id);
        if ($sidebar_primary_activity === '') {
            $sidebar_primary_activity = casting_user_public_role_label($user_id);
        }
        $sidebar_show_views = casting_activities_has_acting($sidebar_activities);
        if ($sidebar_show_views) {
            if (!function_exists('casting_profile_view_stats')) {
                require_once __DIR__ . '/visitors.php';
            }
            $sidebar_views = casting_profile_view_stats($user_id);
        }
    }

    $nav_ctx = [
        'user'                => $user,
        'active'              => $active,
        'highlight'           => false,
        'can_member_search'   => $can_member_search,
        'unread_peers'        => $unread_peers,
        'pending_receipts'    => $pending_receipts,
        'pending_media'       => $pending_media,
        'unread_contacts'     => $unread_contacts,
        'request_count'       => $request_count,
        'pending_brief_count' => $pending_brief_count,
        'desk_response_count' => $desk_response_count,
        'panel_premium_until' => $panel_premium_until,
        'cart_count'          => 0,
    ];
    if (!function_exists('casting_cart_count')) {
        $cart_file = __DIR__ . '/cart.php';
        if (is_file($cart_file)) {
            require_once $cart_file;
        }
    }
    try {
        if (function_exists('casting_cart_count')) {
            $nav_ctx['cart_count'] = (int) casting_cart_count();
        }
    } catch (Throwable $e) {
        $nav_ctx['cart_count'] = 0;
    }
    ?>
    <div class="panel-shell-nav">
    <div class="panel-drawer-backdrop" data-panel-drawer-close hidden></div>
    <div class="panel-shell-nav-stack">
    <aside class="panel-sidebar panel-drawer" id="panel-drawer" aria-label="منوی پنل کاربری">
      <div class="panel-sidebar-head">
        <div class="panel-drawer-head-row">
          <a
            class="panel-sidebar-title panel-sidebar-title-desktop panel-sidebar-home<?= in_array($active, ['panel', 'home'], true) ? ' is-active' : '' ?>"
            href="<?= casting_e(casting_url($active === 'home' ? 'home.php' : 'panel.php')) ?>"
          ><?= casting_e($sidebar_page_title) ?><?php if ($panel_premium_until !== null && $user) : ?>
            <span class="nav-premium-countdown" data-premium-until-ts="<?= (int) $panel_premium_until ?>" title="زمان باقی‌مانده حساب ویژه">
              <span data-premium-countdown><?= casting_e(casting_premium_countdown_nav_label((int) $user->ID)) ?></span>
            </span>
          <?php endif; ?></a>
          <p class="panel-sidebar-title panel-sidebar-title-mobile">منوی اصلی</p>
          <button type="button" class="panel-drawer-close" aria-label="بستن منو" data-panel-drawer-close>&times;</button>
        </div>
        <?php if ($user) : ?>
          <a class="panel-sidebar-identity" href="<?= casting_e(casting_url('panel.php')) ?>">
            <div class="panel-sidebar-avatar-wrap">
              <?php if ($sidebar_photo !== '') : ?>
                <img class="panel-sidebar-avatar" src="<?= casting_e($sidebar_photo) ?>" alt="" width="40" height="40">
              <?php else : ?>
                <span class="panel-sidebar-avatar panel-sidebar-avatar--empty" aria-hidden="true">?</span>
              <?php endif; ?>
              <?php casting_render_presence_dot((int) $user->ID, 'sm'); ?>
            </div>
            <div class="panel-sidebar-identity-text">
              <p class="panel-sidebar-display-name">
                <span class="panel-sidebar-name"><?= casting_e($sidebar_name) ?></span><?php if ($sidebar_primary_activity !== '') : ?><span class="panel-sidebar-activity"> · <?= casting_e($sidebar_primary_activity) ?></span><?php endif; ?>
              </p>
              <p class="panel-sidebar-user-meta">
                <span class="panel-sidebar-login">@<?= casting_e((string) $user->user_login) ?></span>
                <?php if ($panel_membership_number !== '') : ?>
                  <span class="panel-sidebar-membership membership-number"><?= casting_e($panel_membership_number) ?></span>
                <?php endif; ?>
                <?php if ($panel_referral_code !== '') : ?>
                  <span class="panel-sidebar-referral membership-number referral-code" title="کد معرفی">معرف: <?= casting_e($panel_referral_code) ?></span>
                <?php endif; ?>
              </p>
            </div>
          </a>
          <?php if ($sidebar_show_views) : ?>
            <p class="panel-sidebar-views" title="بازدید پروفایل شما">
              <span>امروز: <?= (int) $sidebar_views['day'] ?></span>
              <span class="panel-sidebar-views-label">تعداد بازدید</span>
              <span>این ماه: <?= (int) $sidebar_views['month'] ?></span>
            </p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <nav class="panel-nav" aria-label="بخش‌های پنل">
        <?php
        $nav_ctx['highlight'] = false;
        casting_render_panel_nav_groups($nav_ctx);
        ?>
      </nav>
      <?php if ($admin_nav) : ?>
        <p class="panel-sidebar-title panel-sidebar-title-admin">مدیریت</p>
        <nav class="panel-nav panel-nav-admin">
          <?php foreach ($admin_nav as $item) : ?>
            <a class="panel-nav-link panel-nav-link-admin <?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= casting_e($item['href']) ?>">
              <span class="panel-nav-label"><?= casting_brandify($item['label']) ?></span>
              <?php if ($item['key'] === 'admin-receipts' && $pending_receipts > 0) : ?>
                <span class="nav-badge" aria-label="<?= casting_e((string) $pending_receipts) ?> فیش در انتظار"><?= (int) $pending_receipts ?></span>
              <?php elseif ($item['key'] === 'admin-media' && $pending_media > 0) : ?>
                <span class="nav-badge" aria-label="<?= casting_e((string) $pending_media) ?> فایل در انتظار"><?= (int) $pending_media ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </aside>
    </div>
    <?php if ($user) : ?>
      <?php
      try {
          casting_render_sidebar_suggestions((int) $user->ID);
      } catch (Throwable $e) {
          // پیشنهادها نباید کل پنل را از کار بیندازد
      }
      ?>
    <?php endif; ?>
    </div>
    <?php
}

function casting_render_panel_start(string $title, string $active, string $body_class = 'page-panel'): void
{
    $GLOBALS['casting_panel_active'] = $active;
    $menu_badge = casting_panel_menu_badge_count();
    casting_render_head($title, $body_class . ' has-panel-drawer');
    casting_render_header($active === 'home' ? 'home' : 'panel', true, $menu_badge);
    echo '<main class="wrap panel-shell">';
    casting_render_panel_sidebar($active, $title);
    echo '<div class="panel-content">';
}

/**
 * @return array{href:string,label:string}|null
 */
function casting_panel_back_config(?string $active = null): ?array
{
    $active = $active ?? (string) ($GLOBALS['casting_panel_active'] ?? '');
    if ($active === '') {
        return null;
    }

    $to_panel = [
        'messages',
        'phone',
        'edit-profile',
        'my-profile',
        'gallery',
        'settings',
        'following',
    ];
    if (in_array($active, $to_panel, true)) {
        return ['href' => 'panel.php', 'label' => 'بازگشت'];
    }

    $to_settings = [
        'password',
        'photo',
        'blocked',
    ];
    if (in_array($active, $to_settings, true)) {
        return ['href' => 'settings.php', 'label' => 'بازگشت به تنظیمات'];
    }

    $parent = casting_panel_nav_highlight_key($active);
    if ($parent === $active) {
        return null;
    }

    $hubs = [
        'membership' => ['label' => 'عضویت و اعتبار', 'href' => 'membership.php'],
        'settings'   => ['label' => 'تنظیمات', 'href' => 'settings.php'],
        'panel'      => ['label' => 'پنل کاربری', 'href' => 'panel.php'],
    ];
    if (!isset($hubs[$parent])) {
        return null;
    }

    return [
        'href'  => $hubs[$parent]['href'],
        'label' => 'بازگشت به ' . $hubs[$parent]['label'],
    ];
}

/**
 * عنوان صفحه داخل کارت + دکمه بازگشت سمت چپ (روبروی عنوان)
 */
function casting_render_panel_heading(string $title, string $tag = 'h1'): void
{
    $tag = $tag === 'h2' ? 'h2' : 'h1';
    $back = casting_panel_back_config();
    ?>
  <div class="panel-page-heading">
    <<?= $tag ?> class="panel-page-heading-title"><?= casting_e($title) ?></<?= $tag ?>>
    <?php if ($back !== null) :
        $href = (string) $back['href'];
        if ($href !== '' && !str_starts_with($href, 'http')) {
            $href = casting_url($href);
        }
        ?>
      <a class="btn btn-ghost btn-sm panel-page-heading-back" href="<?= casting_e($href) ?>" data-panel-back>← <?= casting_e((string) $back['label']) ?></a>
    <?php endif; ?>
  </div>
    <?php
}

/**
 * @deprecated از casting_render_panel_heading استفاده کنید
 */
function casting_render_panel_section_back(string $active): void
{
    // دیگر بیرون از کارت رندر نمی‌شود؛ عنوان صفحه از casting_render_panel_heading استفاده کند.
    unset($active);
}

function casting_render_panel_end(): void
{
    if (!function_exists('casting_render_member_preview_lightbox_shell')) {
        require_once __DIR__ . '/member-preview.php';
    }
    casting_render_member_preview_lightbox_shell();
    if (!function_exists('casting_render_post_lightbox_shell')) {
        require_once __DIR__ . '/media-engagement.php';
    }
    casting_render_post_lightbox_shell();
    try {
        casting_render_messages_dock();
    } catch (Throwable $e) {
        // ویجت پیام نباید کل پنل را از کار بیندازد
    }
    casting_render_panel_bottom_nav((string) ($GLOBALS['casting_panel_active'] ?? ''));
    echo '</div></main>';
    casting_render_footer();
}

/**
 * تب‌های پایین موبایل (الهام Backstage)
 *
 * @return list<array{key:string,label:string,href:string,badge?:int}>
 */
function casting_panel_bottom_nav_items(): array
{
    $user = casting_current_user();
    $unread = 0;
    if ($user) {
        if (!function_exists('casting_dm_unread_peer_count')) {
            require_once __DIR__ . '/chat.php';
        }
        if (function_exists('casting_dm_unread_peer_count')) {
            $unread = (int) casting_dm_unread_peer_count((int) $user->ID);
        }
    }

    return [
        ['key' => 'opportunities', 'label' => 'فرصت‌ها', 'href' => 'opportunities.php'],
        ['key' => 'home', 'label' => 'صفحه اصلی', 'href' => 'home.php'],
        ['key' => 'search', 'label' => 'جستجو', 'href' => 'search-users.php'],
        ['key' => 'messages', 'label' => 'پیام', 'href' => 'chat.php', 'badge' => $unread],
        ['key' => 'panel', 'label' => 'پروفایل', 'href' => 'panel.php'],
    ];
}

function casting_panel_bottom_nav_active_key(string $active): string
{
    $map = [
        'home'         => 'home',
        'opportunities'=> 'opportunities',
        'search'       => 'search',
        'newest'       => 'search',
        'messages'     => 'messages',
        'panel'        => 'panel',
        'my-profile'   => 'panel',
        'edit-profile' => 'panel',
        'photo'        => 'panel',
        'gallery'      => 'panel',
        'settings'     => 'panel',
    ];

    return $map[$active] ?? '';
}

function casting_render_panel_bottom_nav(string $active): void
{
    $user = casting_current_user();
    if (!$user || casting_get_user_role((int) $user->ID) === '') {
        return;
    }
    $items = casting_panel_bottom_nav_items();
    $current = casting_panel_bottom_nav_active_key($active);
    ?>
  <nav class="panel-bottom-nav" aria-label="میانبرهای اصلی پنل">
    <?php foreach ($items as $item) :
        $key = (string) ($item['key'] ?? '');
        $href = (string) ($item['href'] ?? '');
        if ($href !== '' && !str_starts_with($href, 'http')) {
            $href = casting_url($href);
        }
        $badge = (int) ($item['badge'] ?? 0);
        $is_active = $current !== '' && $current === $key;
        ?>
      <a
        class="panel-bottom-nav-link<?= $is_active ? ' is-active' : '' ?>"
        href="<?= casting_e($href) ?>"
        <?= $is_active ? 'aria-current="page"' : '' ?>
      >
        <span class="panel-bottom-nav-label"><?= casting_e((string) ($item['label'] ?? '')) ?></span>
        <?php if ($badge > 0) : ?>
          <span class="panel-bottom-nav-badge" aria-label="<?= casting_e((string) $badge) ?> پیام جدید"><?= $badge > 9 ? '۹+' : (string) $badge ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
    <?php
}

/**
 * @return array<string, array{label:string,min:int,max:int}>
 */
function casting_search_height_range_options(): array
{
    $out = [];
    for ($min = 160; $min < 190; $min += 5) {
        $max = $min + 5;
        $key = $min . '_' . $max;
        $out[$key] = [
            'label' => $min . '-' . $max,
            'min'   => $min,
            'max'   => $max,
        ];
    }
    return $out;
}

/**
 * @return array<string, array{label:string,min:int,max:int}>
 */
function casting_search_weight_range_options(): array
{
    $out = [];
    for ($min = 50; $min < 110; $min += 10) {
        $max = $min + 10;
        $key = $min . '_' . $max;
        $out[$key] = [
            'label' => $min . '-' . $max,
            'min'   => $min,
            'max'   => $max,
        ];
    }
    return $out;
}

/**
 * @param array<string, array{label:string,min:int,max:int}> $options
 */
function casting_render_search_band_select(string $id, string $name, string $label, array $options, string $value): void
{
    ?>
    <div class="field">
      <label for="<?= casting_e($id) ?>"><?= casting_e($label) ?></label>
      <select id="<?= casting_e($id) ?>" name="<?= casting_e($name) ?>">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($options as $key => $range) : ?>
          <option value="<?= casting_e($key) ?>" <?= $value === $key ? 'selected' : '' ?>><?= casting_e($range['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}

/**
 * تبدیل اعداد فارسی/عربی به لاتین برای فیلتر محدوده
 */
function casting_normalize_search_digits(string $value): string
{
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $value = str_replace($persian, $latin, $value);
    $value = str_replace($arabic, $latin, $value);
    $value = preg_replace('/[\x{2013}\x{2014}\x{2212}]/u', '-', $value) ?? $value;

    return trim($value);
}

/**
 * @return array{min:?int,max:?int}
 */
function casting_parse_search_metric_range(string $raw, int $floor, int $ceil): array
{
    $raw = casting_normalize_search_digits($raw);
    if ($raw === '') {
        return ['min' => null, 'max' => null];
    }

    $min = null;
    $max = null;
    if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $raw, $matches) === 1) {
        $min = (int) $matches[1];
        $max = (int) $matches[2];
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
    } elseif (preg_match('/^(\d+)\s*-$/', $raw, $matches) === 1) {
        $min = (int) $matches[1];
    } elseif (preg_match('/^-\s*(\d+)$/', $raw, $matches) === 1) {
        $max = (int) $matches[1];
    } elseif (preg_match('/^\d+$/', $raw) === 1) {
        $min = (int) $raw;
    } else {
        return ['min' => null, 'max' => null];
    }

    if ($min !== null && ($min < $floor || $min > $ceil)) {
        $min = null;
    }
    if ($max !== null && ($max < $floor || $max > $ceil)) {
        $max = null;
    }

    return ['min' => $min, 'max' => $max];
}

function casting_search_metric_range_from_input(array $input, string $range_key, string $min_key, string $max_key): string
{
    $value = casting_normalize_search_digits((string) ($input[$range_key] ?? ''));
    if ($value !== '') {
        return $value;
    }

    $min = casting_normalize_search_digits((string) ($input[$min_key] ?? ''));
    $max = casting_normalize_search_digits((string) ($input[$max_key] ?? ''));
    if ($min === '' && $max === '') {
        return '';
    }
    if ($min !== '' && $max !== '') {
        return $min . '-' . $max;
    }
    if ($min !== '') {
        return $min . '-';
    }

    return '-' . $max;
}

/**
 * یک گروه متریک (سن / قد / وزن) — کرکره «از» و «تا»
 *
 * @param array<string, string> $filters
 * @param array{prefix: string, label: string, unit: string, floor: int, ceil: int, range_key: string, kind: string} $metric
 */
function casting_render_body_metric_group(array $filters, array $metric): void
{
    $parts = casting_parse_search_metric_range(
        (string) ($filters[$metric['range_key']] ?? ''),
        $metric['floor'],
        $metric['ceil']
    );
    $min_val = $parts['min'] !== null ? (string) $parts['min'] : '';
    $max_val = $parts['max'] !== null ? (string) $parts['max'] : '';
    $kind = (string) ($metric['kind'] ?? $metric['prefix']);
    ?>
    <div class="filter-metric-group">
      <div class="filter-metric-head">
        <span class="filter-metric-label"><?= casting_e($metric['label']) ?></span>
        <span class="filter-metric-unit"><?= casting_e($metric['unit']) ?></span>
      </div>
      <div class="filter-metric-range">
        <div class="field">
          <label class="sr-only" for="<?= casting_e($metric['prefix']) ?>_min"><?= casting_e($metric['label']) ?> از</label>
          <?php casting_render_body_metric_select($kind, $metric['prefix'] . '_min', $metric['prefix'] . '_min', $min_val, 'از'); ?>
        </div>
        <div class="field">
          <label class="sr-only" for="<?= casting_e($metric['prefix']) ?>_max"><?= casting_e($metric['label']) ?> تا</label>
          <?php casting_render_body_metric_select($kind, $metric['prefix'] . '_max', $metric['prefix'] . '_max', $max_val, 'تا'); ?>
        </div>
      </div>
    </div>
    <?php
}

/**
 * سن، قد و وزن — کرکره در بازه تعریف‌شده
 *
 * @param array<string, string> $filters
 * @param list<string>|null $include
 */
function casting_render_body_metric_search_fields(array $filters, ?array $include = null): void
{
    $defs = casting_body_metric_defs();
    $metrics = [
        [
            'prefix'    => 'age',
            'kind'      => 'age',
            'label'     => $defs['age']['label'],
            'unit'      => $defs['age']['unit'],
            'floor'     => $defs['age']['min'],
            'ceil'      => casting_body_metric_plus_value('age'),
            'range_key' => 'age_range',
        ],
        [
            'prefix'    => 'height',
            'kind'      => 'height',
            'label'     => $defs['height']['label'],
            'unit'      => $defs['height']['unit'],
            'floor'     => $defs['height']['min'],
            'ceil'      => casting_body_metric_plus_value('height'),
            'range_key' => 'height_range',
        ],
        [
            'prefix'    => 'weight',
            'kind'      => 'weight',
            'label'     => $defs['weight']['label'],
            'unit'      => $defs['weight']['unit'],
            'floor'     => $defs['weight']['min'],
            'ceil'      => casting_body_metric_plus_value('weight'),
            'range_key' => 'weight_range',
        ],
    ];
    if ($include !== null) {
        $allowed = array_flip($include);
        $metrics = array_values(array_filter($metrics, static fn(array $metric): bool => isset($allowed[$metric['prefix']])));
        usort(
            $metrics,
            static fn(array $a, array $b): int => ((int) array_search($a['prefix'], $include, true)) <=> ((int) array_search($b['prefix'], $include, true))
        );
    }
    if ($metrics === []) {
        return;
    }
    ?>
    <div class="filter-activity-fields" aria-label="فیلتر سن، قد و وزن">
      <?php foreach ($metrics as $metric) {
          casting_render_body_metric_group($filters, $metric);
      } ?>
    </div>
    <?php
}

/**
 * @param array<string, string> $filters
 */
function casting_render_health_search_field(array $filters): void
{
    $value = (string) ($filters['health_well'] ?? '');
    $options = [
        'healthy'   => 'بله',
        'unhealthy' => 'خیر',
    ];
    ?>
    <div class="field">
      <label for="health_well">سلامت</label>
      <select id="health_well" name="health_well">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($options as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $value === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_search_metric_range_filter(
    array &$meta_query,
    string $meta_key,
    string $filter_value,
    int $floor,
    int $exact_max
): void {
    $plus = $exact_max + 1;
    $parsed = casting_parse_search_metric_range($filter_value, $floor, $plus);
    if ($parsed['min'] !== null) {
        $min = $parsed['min'] >= $plus ? $exact_max : $parsed['min'];
        $meta_query[] = [
            'key'     => $meta_key,
            'value'   => $min,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }
    if ($parsed['max'] !== null && $parsed['max'] < $plus) {
        $meta_query[] = [
            'key'     => $meta_key,
            'value'   => $parsed['max'],
            'type'    => 'NUMERIC',
            'compare' => '<=',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_body_metric_search_filters(array &$meta_query, array $filters): void
{
    $defs = casting_body_metric_defs();
    casting_apply_search_metric_range_filter(
        $meta_query,
        'casting_age',
        (string) ($filters['age_range'] ?? ''),
        (int) $defs['age']['min'],
        (int) $defs['age']['max']
    );
    casting_apply_search_metric_range_filter(
        $meta_query,
        'casting_height',
        (string) ($filters['height_range'] ?? ''),
        (int) $defs['height']['min'],
        (int) $defs['height']['max']
    );
    casting_apply_search_metric_range_filter(
        $meta_query,
        'casting_weight',
        (string) ($filters['weight_range'] ?? ''),
        (int) $defs['weight']['min'],
        (int) $defs['weight']['max']
    );
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_experience_filters(array &$meta_query, array $filters): void
{
    $experience = (int) ($filters['experience'] ?? 0);
    if ($experience >= 0 && $experience <= 60 && ($filters['experience'] ?? '') !== '') {
        $meta_query[] = [
            'key'     => 'casting_experience',
            'value'   => $experience,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_language_filters(array &$meta_query, array $filters): void
{
    $language = sanitize_text_field((string) ($filters['language'] ?? ''));
    if ($language !== '') {
        $meta_query[] = [
            'key'     => 'casting_language_items',
            'value'   => $language,
            'compare' => 'LIKE',
        ];
    }

    $language_level = sanitize_key((string) ($filters['language_level'] ?? ''));
    if ($language_level !== '' && array_key_exists($language_level, casting_language_level_labels())) {
        $meta_query[] = [
            'key'     => 'casting_language_items',
            'value'   => '"' . $language_level . '"',
            'compare' => 'LIKE',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_education_filters(array &$meta_query, array $filters): void
{
    $degree = sanitize_key((string) ($filters['education_degree'] ?? ''));
    if ($degree !== '' && array_key_exists($degree, casting_education_degree_labels())) {
        $meta_query[] = [
            'key'     => 'casting_education_items',
            'value'   => '"' . $degree . '"',
            'compare' => 'LIKE',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_has_video_filter(array &$meta_query, string $value): void
{
    $value = sanitize_key($value);
    if ($value === '' || !array_key_exists($value, casting_yes_no_labels())) {
        return;
    }

    if ($value === 'yes') {
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => 'casting_video_id',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => 'casting_video_url',
                'value'   => '',
                'compare' => '!=',
            ],
        ];
        return;
    }

    $meta_query[] = [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            ['key' => 'casting_video_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_video_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_video_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_video_url', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_video_url', 'value' => '', 'compare' => '='],
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_has_portfolio_filter(array &$meta_query, string $value): void
{
    $value = sanitize_key($value);
    if ($value === '' || !array_key_exists($value, casting_yes_no_labels())) {
        return;
    }

    $has_portfolio = [
        'relation' => 'OR',
        [
            'relation' => 'AND',
            ['key' => 'casting_work_credits', 'compare' => 'EXISTS'],
            ['key' => 'casting_work_credits', 'value' => 'a:0:{}', 'compare' => '!='],
        ],
        ['key' => 'casting_photo_closeup_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        ['key' => 'casting_photo_medium_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        ['key' => 'casting_photo_long_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        ['key' => 'casting_photo_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        [
            'key'     => 'casting_video_id',
            'value'   => 0,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ],
        [
            'key'     => 'casting_video_url',
            'value'   => '',
            'compare' => '!=',
        ],
    ];

    if ($value === 'yes') {
        $meta_query[] = $has_portfolio;
        return;
    }

    $meta_query[] = [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            ['key' => 'casting_work_credits', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_work_credits', 'value' => 'a:0:{}', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_photo_closeup_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_photo_closeup_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_photo_closeup_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_photo_medium_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_photo_medium_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_photo_medium_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_photo_long_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_photo_long_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_photo_long_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_photo_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_photo_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_photo_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_video_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_video_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_video_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_video_url', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_video_url', 'value' => '', 'compare' => '='],
        ],
    ];
}

/**
 * @param array<string, string> $filters
 */
function casting_render_member_search_phase1_fields(array $filters): void
{
    $yes_no = casting_yes_no_labels();
    $language_levels = casting_language_level_labels();
    $education_degrees = casting_education_degree_labels();
    $languages = casting_common_languages();
    ?>
    <div class="field">
      <label for="experience">مدت سابقه</label>
      <input id="experience" name="experience" type="number" min="0" max="60" value="<?= casting_e($filters['experience']) ?>" placeholder="سال">
    </div>
    <div class="field">
      <label for="education_degree">تحصیلات</label>
      <select id="education_degree" name="education_degree">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($education_degrees as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['education_degree'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="language">زبان</label>
      <input id="language" name="language" type="search" list="casting-search-languages" value="<?= casting_e($filters['language']) ?>" placeholder="مثلاً انگلیسی">
      <datalist id="casting-search-languages">
        <?php foreach ($languages as $lang) : ?>
          <option value="<?= casting_e($lang) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="field">
      <label for="language_level">سطح زبان</label>
      <select id="language_level" name="language_level">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($language_levels as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['language_level'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="has_video">ویدئو معرفی</label>
      <select id="has_video" name="has_video">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($yes_no as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['has_video'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="has_portfolio">نمونه‌کار</label>
      <select id="has_portfolio" name="has_portfolio">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($yes_no as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['has_portfolio'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_phase2_filters(array &$meta_query, array $filters): void
{
    $eye = sanitize_key((string) ($filters['eye_color'] ?? ''));
    if ($eye !== '' && array_key_exists($eye, casting_eye_color_labels())) {
        $meta_query[] = [
            'key'   => 'casting_eye_color',
            'value' => $eye,
        ];
    }

    $hair = sanitize_key((string) ($filters['hair_color'] ?? ''));
    if ($hair !== '' && array_key_exists($hair, casting_hair_color_labels())) {
        $meta_query[] = [
            'key'   => 'casting_hair_color',
            'value' => $hair,
        ];
    }

    $accent = sanitize_key((string) ($filters['accent'] ?? ''));
    if ($accent !== '' && array_key_exists($accent, casting_accent_labels())) {
        $meta_query[] = [
            'key'   => 'casting_accent',
            'value' => $accent,
        ];
    }

    $apparent = sanitize_key((string) ($filters['apparent_age_range'] ?? ''));
    if ($apparent !== '' && array_key_exists($apparent, casting_age_range_options())) {
        $meta_query[] = [
            'key'   => 'casting_apparent_age_range',
            'value' => $apparent,
        ];
    }

    $motor = sanitize_key((string) ($filters['motor_skill'] ?? ''));
    if ($motor !== '' && isset(casting_motor_skill_filter_labels()[$motor])) {
        $meta_query[] = [
            'key'     => 'casting_skill_items',
            'value'   => '"' . $motor . '"',
            'compare' => 'LIKE',
        ];
    }

    $art_skill = sanitize_key((string) ($filters['artistic_skill'] ?? ''));
    if ($art_skill !== '' && isset(casting_artistic_skill_filter_labels()[$art_skill])) {
        $meta_query[] = [
            'key'     => 'casting_skill_items',
            'value'   => '"' . $art_skill . '"',
            'compare' => 'LIKE',
        ];
    }
}

/**
 * جنسیت — فیلد تکی برای ترتیب دلخواه جستجو
 *
 * @param array<string, string> $filters
 */
function casting_render_member_search_gender_field(array $filters): void
{
    $genders = casting_gender_labels();
    ?>
    <div class="field">
      <label for="gender">جنسیت</label>
      <select id="gender" name="gender">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($genders as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['gender'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}

/**
 * سلامت، رنگ پوست، رنگ چشم، سن ظاهری، لهجه
 *
 * @param array<string, string> $filters
 */
function casting_render_member_search_appearance_fields(array $filters): void
{
    $looks = casting_look_labels();
    $eyes = casting_eye_color_labels();
    $accents = casting_accent_labels();
    $age_ranges = casting_age_range_options();
    $health = (string) ($filters['health_well'] ?? '');
    $health_options = [
        'healthy'   => 'بله',
        'unhealthy' => 'خیر',
    ];
    ?>
    <div class="filter-activity-fields" aria-label="فیلتر ظاهر و سلامت">
      <div class="field">
        <label for="health_well">سلامت</label>
        <select id="health_well" name="health_well">
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach ($health_options as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $health === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="look">رنگ پوست</label>
        <select id="look" name="look">
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach ($looks as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['look'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="eye_color">رنگ چشم</label>
        <select id="eye_color" name="eye_color">
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach ($eyes as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['eye_color'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="apparent_age_range">سن ظاهری</label>
        <select id="apparent_age_range" name="apparent_age_range">
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach ($age_ranges as $key => $range) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['apparent_age_range'] === $key ? 'selected' : '' ?>><?= casting_e($range['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="accent">لهجه</label>
        <select id="accent" name="accent">
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach ($accents as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['accent'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php
}

/**
 * @param array<string, string> $filters
 * @deprecated از ترکیب فیلدهای جداگانه در search-users استفاده شود
 */
function casting_render_member_search_profile_cluster(array $filters): void
{
    casting_render_member_search_gender_field($filters);
    casting_render_member_search_appearance_fields($filters);
}

/**
 * @param array<string, string> $filters
 */
function casting_render_member_search_after_health_fields(array $filters): void
{
    casting_render_member_search_profile_cluster($filters);
}

/**
 * @param array<string, string> $filters
 */
function casting_render_member_search_phase2_fields(array $filters): void
{
}

/**
 * فیلدهای نوع فعالیت / تخصص برای جستجو و فراخوان
 *
 * @param array<string, string> $filters
 * @param array{category?:string,specialty?:string} $labels
 */
function casting_render_member_search_activity_fields(array $filters, array $labels = []): void
{
    $categories = casting_activity_categories();
    $category = (string) ($filters['activity_category'] ?? '');
    $specialty = (string) ($filters['activity_specialty'] ?? '');
    $subs = ($category !== '' && isset($categories[$category])) ? $categories[$category]['items'] : [];
    $map = [];
    foreach ($categories as $cat_key => $cat) {
        $map[$cat_key] = $cat['items'];
    }
    $map_json = wp_json_encode($map, JSON_UNESCAPED_UNICODE);
    if (!is_string($map_json)) {
        $map_json = '{}';
    }
    $category_label = (string) ($labels['category'] ?? 'تخصص هنری');
    $specialty_label = (string) ($labels['specialty'] ?? 'تخصص');
    ?>
    <div class="filter-activity-fields" data-activity-search data-activity-map="<?= casting_e($map_json) ?>">
      <div class="field">
        <label for="activity_category"><?= casting_e($category_label) ?></label>
        <select id="activity_category" name="activity_category" data-activity-category>
          <option value=""><?= casting_e(casting_search_filter_none_label()) ?></option>
          <?php foreach ($categories as $key => $cat) : ?>
            <option value="<?= casting_e($key) ?>" <?= $category === $key ? 'selected' : '' ?>><?= casting_e($cat['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="activity_specialty"><?= casting_e($specialty_label) ?></label>
        <select id="activity_specialty" name="activity_specialty" data-activity-specialty <?= $category === '' ? 'disabled' : '' ?>>
          <option value=""><?= casting_e(casting_search_specialty_empty_label($category !== '')) ?></option>
          <?php foreach ($subs as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $specialty === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php
}

/**
 * مهارت هنری، مهارت حرکتی، تشکل
 *
 * @param array<string, string> $filters
 */
function casting_render_member_search_skill_org_fields(array $filters): void
{
    $artistic_orgs = casting_artistic_org_labels();
    $motor_skills = casting_motor_skill_filter_labels();
    ?>
    <div class="filter-activity-fields" aria-label="مهارت و تشکل">
      <div class="field">
        <label for="artistic_skill">مهارت هنری</label>
        <select id="artistic_skill" name="artistic_skill">
          <option value=""><?= casting_e(casting_search_filter_none_label()) ?></option>
          <?php foreach (casting_artistic_skill_filter_labels() as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['artistic_skill'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="motor_skill">مهارت حرکتی</label>
        <select id="motor_skill" name="motor_skill">
          <option value=""><?= casting_e(casting_search_filter_none_label()) ?></option>
          <?php foreach ($motor_skills as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['motor_skill'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="artistic_org">تشکل</label>
        <select id="artistic_org" name="artistic_org">
          <option value=""><?= casting_e(casting_search_filter_none_label()) ?></option>
          <?php foreach ($artistic_orgs as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['artistic_org'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php
}

/**
 * @param array<string, string> $filters
 */
function casting_render_member_search_talent_cluster(array $filters): void
{
    casting_render_member_search_activity_fields($filters);
    casting_render_member_search_skill_org_fields($filters);
}

/**
 * @param array<string, string> $filters
 */
function casting_member_search_filters_active(array $filters): bool
{
    $skip = ['viewer_id', 'page', 'ajax'];
    foreach ($filters as $key => $value) {
        if (in_array((string) $key, $skip, true)) {
            continue;
        }
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        if ($key === 'city' && $value === casting_city_all_label()) {
            continue;
        }
        return true;
    }

    return false;
}

/**
 * میانبرهای یک‌کلیکی زیر فیلتر اصلی جستجو
 *
 * @param array<string, string> $filters
 * @return list<array{key:string,label:string,set:array<string,string>,match:array<string,string>}>
 */
function casting_member_search_quick_chips(array $filters = []): array
{
    unset($filters);
    $cats = casting_activity_categories();

    return [
        [
            'key'   => 'all',
            'label' => 'همه',
            'set'   => ['clear' => '1'],
            'match' => ['clear' => '1'],
        ],
        [
            'key'   => 'acting',
            'label' => (string) ($cats['acting']['label'] ?? 'بازیگری'),
            'set'   => [
                'activity_category'  => 'acting',
                'activity_specialty' => '',
            ],
            'match' => ['activity_category' => 'acting'],
        ],
        [
            'key'   => 'directing',
            'label' => (string) ($cats['directing']['label'] ?? 'کارگردانی'),
            'set'   => [
                'activity_category'  => 'directing',
                'activity_specialty' => '',
            ],
            'match' => ['activity_category' => 'directing'],
        ],
        [
            'key'   => 'tehran',
            'label' => 'تهران',
            'set'   => [
                'province' => 'tehran',
                'city'     => 'تهران',
            ],
            'match' => ['province' => 'tehran'],
        ],
        [
            'key'   => 'age_25_35',
            'label' => '۲۵–۳۵',
            'set'   => [
                'age_min' => '25',
                'age_max' => '35',
            ],
            'match' => [
                'age_min' => '25',
                'age_max' => '35',
            ],
        ],
        [
            'key'   => 'has_video',
            'label' => 'دارای ویدیو',
            'set'   => ['has_video' => 'yes'],
            'match' => ['has_video' => 'yes'],
        ],
    ];
}

/**
 * @param array<string, string> $filters
 */
function casting_member_search_quick_chip_is_active(array $chip, array $filters): bool
{
    $match = $chip['match'] ?? [];
    if (!is_array($match) || $match === []) {
        return false;
    }
    if (($match['clear'] ?? '') === '1') {
        return !casting_member_search_filters_active($filters);
    }

    $age_range = trim((string) ($filters['age_range'] ?? ''));
    $age_parts = $age_range !== '' ? casting_parse_search_metric_range($age_range, 0, 200) : ['min' => null, 'max' => null];

    foreach ($match as $key => $expected) {
        $expected = (string) $expected;
        if ($key === 'age_min') {
            $actual = $age_parts['min'] !== null ? (string) $age_parts['min'] : '';
            if ($actual !== $expected) {
                return false;
            }
            continue;
        }
        if ($key === 'age_max') {
            $actual = $age_parts['max'] !== null ? (string) $age_parts['max'] : '';
            if ($actual !== $expected) {
                return false;
            }
            continue;
        }
        if (trim((string) ($filters[$key] ?? '')) !== $expected) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, string> $filters
 */
function casting_render_member_search_quick_chips(array $filters): void
{
    $chips = casting_member_search_quick_chips($filters);
    ?>
  <nav class="search-quick-chips" aria-label="فیلتر سریع">
    <?php foreach ($chips as $chip) :
        $payload = wp_json_encode($chip['set'], JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            $payload = '{}';
        }
        $active = casting_member_search_quick_chip_is_active($chip, $filters);
        ?>
      <button
        type="button"
        class="search-quick-chip<?= $active ? ' is-active' : '' ?>"
        data-search-chip="<?= casting_e($payload) ?>"
        data-search-chip-key="<?= casting_e((string) $chip['key']) ?>"
        aria-pressed="<?= $active ? 'true' : 'false' ?>"
      ><?= casting_e((string) $chip['label']) ?></button>
    <?php endforeach; ?>
  </nav>
    <?php
}

/**
 * آیا فیلترهای پیشرفته (غیر از اصلی) فعال‌اند؟
 */
function casting_member_search_advanced_filters_active(array $filters): bool
{
    $advanced_keys = [
        'look',
        'height_range',
        'weight_range',
        'health_well',
        'artistic_org',
        'availability',
        'experience',
        'language',
        'language_level',
        'education_degree',
        'has_video',
        'has_portfolio',
        'eye_color',
        'hair_color',
        'apparent_age_range',
        'accent',
        'artistic_skill',
        'motor_skill',
        'skill',
    ];
    foreach ($advanced_keys as $key) {
        $value = trim((string) ($filters[$key] ?? ''));
        if ($value !== '') {
            return true;
        }
    }

    return false;
}

/**
 * @return array<string, string>
 */
function casting_parse_member_search_filters(array $input): array
{
    return [
        'q'                  => (string) ($input['q'] ?? ''),
        'activity_category'  => (string) ($input['activity_category'] ?? ''),
        'activity_specialty' => (string) ($input['activity_specialty'] ?? ''),
        'gender'             => (string) ($input['gender'] ?? ''),
        'look'               => (string) ($input['look'] ?? ''),
        'age_range'          => casting_search_metric_range_from_input($input, 'age_range', 'age_min', 'age_max'),
        'height_range'       => casting_search_metric_range_from_input($input, 'height_range', 'height_min', 'height_max'),
        'weight_range'       => casting_search_metric_range_from_input($input, 'weight_range', 'weight_min', 'weight_max'),
        'health_well'        => sanitize_key((string) ($input['health_well'] ?? '')),
        'province'           => (string) ($input['province'] ?? ''),
        'city'               => (string) ($input['city'] ?? ''),
        'artistic_org'       => (string) ($input['artistic_org'] ?? ''),
        'availability'       => (string) ($input['availability'] ?? ''),
        'experience'         => (string) ($input['experience'] ?? $input['experience_min'] ?? ''),
        'language'           => (string) ($input['language'] ?? ''),
        'language_level'     => (string) ($input['language_level'] ?? ''),
        'education_degree'   => (string) ($input['education_degree'] ?? ''),
        'has_video'          => (string) ($input['has_video'] ?? ''),
        'has_portfolio'      => (string) ($input['has_portfolio'] ?? ''),
        'eye_color'          => (string) ($input['eye_color'] ?? ''),
        'hair_color'         => (string) ($input['hair_color'] ?? ''),
        'accent'             => (string) ($input['accent'] ?? ''),
        'apparent_age_range' => (string) ($input['apparent_age_range'] ?? ''),
        'motor_skill'        => (string) ($input['motor_skill'] ?? ''),
        'artistic_skill'     => (string) ($input['artistic_skill'] ?? ''),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function casting_member_visible_meta_query(): array
{
    return [
        'relation' => 'OR',
        [
            'key'     => 'casting_visible',
            'value'   => '1',
            'compare' => '=',
        ],
        [
            'key'     => 'casting_visible',
            'compare' => 'NOT EXISTS',
        ],
    ];
}

/**
 * @return array{users: WP_User[], total: int}
 */
function casting_query_members(int $exclude_id, array $filters = [], int $page = 1, int $per_page = 20): array
{
    $meta_query = [
        'relation' => 'AND',
        [
            'key'     => 'casting_role',
            'compare' => 'EXISTS',
        ],
    ];
    $viewer_id = (int) ($filters['viewer_id'] ?? 0);
    $skip_visible = $viewer_id > 0 && function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($viewer_id);
    if (!$skip_visible) {
        $meta_query[] = casting_member_visible_meta_query();
    }

    if (!empty($filters['activity_specialty'])) {
        $activity_specialty = sanitize_key((string) $filters['activity_specialty']);
        $activity_labels = casting_activity_labels();
        if (isset($activity_labels[$activity_specialty])) {
            $meta_query[] = [
                'key'     => 'casting_activities',
                'value'   => '"' . $activity_specialty . '"',
                'compare' => 'LIKE',
            ];
        }
    } elseif (!empty($filters['activity_category'])) {
        $activity_category = sanitize_key((string) $filters['activity_category']);
        $activity_categories = casting_activity_categories();
        if (isset($activity_categories[$activity_category])) {
            $activity_or = ['relation' => 'OR'];
            foreach (array_keys($activity_categories[$activity_category]['items']) as $spec_key) {
                $activity_or[] = [
                    'key'     => 'casting_activities',
                    'value'   => '"' . $spec_key . '"',
                    'compare' => 'LIKE',
                ];
            }
            if (count($activity_or) > 1) {
                $meta_query[] = $activity_or;
            }
        }
    }

    if (!empty($filters['gender']) && array_key_exists($filters['gender'], casting_gender_labels())) {
        $meta_query[] = [
            'key'   => 'casting_gender',
            'value' => sanitize_key((string) $filters['gender']),
        ];
    }

    if (!empty($filters['look']) && array_key_exists($filters['look'], casting_look_labels())) {
        $meta_query[] = [
            'key'   => 'casting_look',
            'value' => sanitize_key((string) $filters['look']),
        ];
    }

    casting_apply_body_metric_search_filters($meta_query, $filters);

    $health_well = sanitize_key((string) ($filters['health_well'] ?? ''));
    if ($health_well === 'healthy' || $health_well === 'unhealthy') {
        $meta_query[] = [
            'key'   => 'casting_health_well',
            'value' => $health_well,
        ];
    }

    $province = sanitize_key((string) ($filters['province'] ?? ''));
    if ($province !== '' && array_key_exists($province, casting_province_labels())) {
        $meta_query[] = [
            'key'   => 'casting_province',
            'value' => $province,
        ];
    }

    $city = casting_city_search_filter_value((string) ($filters['city'] ?? ''));
    if ($city !== '') {
        $meta_query[] = [
            'key'     => 'casting_city',
            'value'   => $city,
            'compare' => 'LIKE',
        ];
    }

    $artistic_org = sanitize_key((string) ($filters['artistic_org'] ?? ''));
    $org_labels = casting_artistic_org_labels();
    if ($artistic_org !== '' && isset($org_labels[$artistic_org])) {
        $meta_query[] = [
            'key'     => 'casting_artistic_orgs',
            'value'   => '"' . $artistic_org . '"',
            'compare' => 'LIKE',
        ];
    }

    if (!empty($filters['availability']) && array_key_exists($filters['availability'], casting_availability_labels())) {
        $meta_query[] = [
            'key'   => 'casting_availability',
            'value' => sanitize_key((string) $filters['availability']),
        ];
    }

    casting_apply_member_experience_filters($meta_query, $filters);
    casting_apply_member_language_filters($meta_query, $filters);
    casting_apply_member_education_filters($meta_query, $filters);
    casting_apply_member_has_video_filter($meta_query, (string) ($filters['has_video'] ?? ''));
    casting_apply_member_has_portfolio_filter($meta_query, (string) ($filters['has_portfolio'] ?? ''));
    casting_apply_member_phase2_filters($meta_query, $filters);

    $page = max(1, $page);
    $per_page = max(1, $per_page);
    $args = [
        'number'      => $per_page,
        'offset'      => ($page - 1) * $per_page,
        'orderby'     => 'registered',
        'order'       => 'DESC',
        'meta_query'  => $meta_query,
        'count_total' => true,
        'exclude'     => [$exclude_id],
    ];

    $name_q = trim(sanitize_text_field((string) ($filters['q'] ?? '')));
    if ($name_q !== '') {
        $args['search'] = '*' . esc_attr($name_q) . '*';
        $args['search_columns'] = ['display_name', 'user_login'];
    }

    $query = new WP_User_Query($args);
    $users = $query->get_results();
    if (!is_array($users)) {
        $users = [];
    }

    usort($users, static function (WP_User $a, WP_User $b): int {
        $pa = casting_user_is_premium((int) $a->ID) ? 1 : 0;
        $pb = casting_user_is_premium((int) $b->ID) ? 1 : 0;
        if ($pa !== $pb) {
            return $pb <=> $pa;
        }
        return strcmp((string) $b->user_registered, (string) $a->user_registered);
    });

    return [
        'users' => $users,
        'total' => (int) $query->get_total(),
    ];
}

/**
 * @return array<int, WP_User>
 */
function casting_newest_members(int $limit = 30, int $exclude_id = 0): array
{
    $limit = max(1, min(60, $limit));
    $args = [
        'number'     => max($limit * 3, 40),
        'orderby'    => 'registered',
        'order'      => 'DESC',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key'     => 'casting_role',
                'compare' => 'EXISTS',
            ],
            casting_member_visible_meta_query(),
        ],
    ];
    if ($exclude_id > 0) {
        $args['exclude'] = [$exclude_id];
    }
    $query = new WP_User_Query($args);
    $users = $query->get_results();
    if (!is_array($users)) {
        return [];
    }

    $out = [];
    foreach ($users as $user) {
        if (casting_user_is_premium((int) $user->ID)) {
            continue;
        }
        $out[] = $user;
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * پیشنهاد دنبال‌کردن برای سایدبار (مثل Suggested for you)
 *
 * @return list<array{id:int,name:string,role:string,photo:string}>
 */
function casting_suggested_members_for(int $viewer_id, int $limit = 5): array
{
    $viewer_id = max(0, $viewer_id);
    $limit = max(1, min(8, $limit));
    if ($viewer_id <= 0) {
        return [];
    }
    if (!function_exists('casting_user_is_following')) {
        require_once __DIR__ . '/follows.php';
    }

    try {
        $pool = casting_newest_members(max(12, $limit * 4), $viewer_id);
    } catch (Throwable $e) {
        return [];
    }

    $seen = [];
    $out = [];
    foreach ($pool as $user) {
        if (!$user instanceof WP_User) {
            continue;
        }
        $id = (int) $user->ID;
        if ($id <= 0 || isset($seen[$id]) || $id === $viewer_id) {
            continue;
        }
        $seen[$id] = true;
        try {
            if (!casting_follow_can_target($viewer_id, $id) || casting_user_is_following($viewer_id, $id)) {
                continue;
            }
            $profile = casting_get_profile($id);
            $photo = casting_member_card_photo_url($id, $profile);
            $role = casting_user_primary_activity_label($id);
            if ($role === '') {
                $role = casting_user_public_role_label($id);
            }
        } catch (Throwable $e) {
            continue;
        }
        $out[] = [
            'id'    => $id,
            'name'  => (string) $user->display_name,
            'role'  => $role,
            'photo' => $photo,
        ];
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

function casting_render_sidebar_suggestions(int $viewer_id): void
{
    try {
        $items = casting_suggested_members_for($viewer_id, 5);
    } catch (Throwable $e) {
        return;
    }
    if ($items === []) {
        return;
    }
    if (!function_exists('casting_render_follow_button')) {
        require_once __DIR__ . '/follows.php';
    }
    ?>
  <section class="sidebar-suggest" aria-labelledby="sidebar-suggest-title">
    <header class="sidebar-suggest-head">
      <h3 id="sidebar-suggest-title">پیشنهادی برای شما</h3>
      <a class="sidebar-suggest-all" href="<?= casting_e(casting_url('search-users.php')) ?>">مشاهده همه</a>
    </header>
    <ul class="sidebar-suggest-list">
      <?php foreach ($items as $row) :
          $id = (int) ($row['id'] ?? 0);
          if ($id <= 0) {
              continue;
          }
          $name = (string) ($row['name'] ?? '');
          $role = (string) ($row['role'] ?? '');
          $photo = (string) ($row['photo'] ?? '');
          ?>
        <li class="sidebar-suggest-item">
          <button type="button" class="sidebar-suggest-user" data-member-preview="<?= $id ?>">
            <span class="sidebar-suggest-avatar-wrap">
              <?php if ($photo !== '') : ?>
                <img class="sidebar-suggest-avatar" src="<?= casting_e($photo) ?>" alt="" width="36" height="36" loading="lazy">
              <?php else : ?>
                <span class="sidebar-suggest-avatar sidebar-suggest-avatar--empty" aria-hidden="true">?</span>
              <?php endif; ?>
            </span>
            <span class="sidebar-suggest-text">
              <strong class="sidebar-suggest-name"><?= casting_e($name) ?></strong>
              <?php if ($role !== '') : ?>
                <span class="sidebar-suggest-role"><?= casting_e($role) ?></span>
              <?php endif; ?>
            </span>
          </button>
          <div class="sidebar-suggest-action">
            <?php casting_render_follow_button($viewer_id, $id, 'btn-sm sidebar-suggest-follow'); ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
    <?php
}

/**
 * ویجت شناور پیام‌ها (مثل Messages اینستاگرام)
 */
function casting_render_messages_dock(): void
{
    try {
        $user = casting_current_user();
        if (!$user || casting_get_user_role((int) $user->ID) === '') {
            return;
        }
        $user_id = (int) $user->ID;
        $active = (string) ($GLOBALS['casting_panel_active'] ?? '');
        if ($active === 'messages') {
            return;
        }
        if (!function_exists('casting_dm_conversations')) {
            require_once __DIR__ . '/chat.php';
        }
        if (!function_exists('casting_user_is_super_admin')) {
            require_once __DIR__ . '/admin-access.php';
        }

        $is_admin_chat = casting_user_is_portal_owner($user_id) || casting_user_is_super_admin($user_id);
        $conversations = casting_dm_conversations($user_id);
        $unread_total = casting_dm_unread_peer_count($user_id);
        $unread_map = [];
        $last_map = [];
        foreach ($conversations as $conv) {
            $pid = (int) ($conv['peer_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $unread_map[$pid] = (int) ($conv['unread'] ?? 0);
            $last_map[$pid] = trim((string) ($conv['last_message'] ?? ''));
        }

        $preview = [];
        if ($is_admin_chat) {
            $contacts = casting_dm_allowed_contacts($user_id);
            foreach ($contacts as $contact) {
                $pid = (int) ($contact['id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $role_key = (string) ($contact['role'] ?? '');
                $role = $role_key !== '' ? casting_role_label($role_key) : '';
                if ($role === '') {
                    $role = casting_dm_peer_role_label($pid);
                }
                $preview[] = [
                    'peer_id'      => $pid,
                    'name'         => (string) ($contact['name'] ?? ''),
                    'role'         => $role,
                    'avatar'       => '',
                    'unread'       => (int) ($unread_map[$pid] ?? 0),
                    'last_message' => (string) ($last_map[$pid] ?? ''),
                ];
            }
            usort($preview, static function (array $a, array $b): int {
                $ua = (int) ($a['unread'] ?? 0);
                $ub = (int) ($b['unread'] ?? 0);
                if ($ua !== $ub) {
                    return $ub <=> $ua;
                }

                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });
        } else {
            $preview = array_slice($conversations, 0, 10);
        }
    } catch (Throwable $e) {
        return;
    }
    ?>
  <div class="messages-dock" data-messages-dock>
    <button type="button" class="messages-dock-toggle" data-messages-dock-toggle aria-expanded="false" aria-controls="messages-dock-panel">
      <span class="messages-dock-icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" focusable="false">
          <path d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5A2.25 2.25 0 0 1 19.5 6.75v7.5a2.25 2.25 0 0 1-2.25 2.25H9.3L5.4 19.4a.75.75 0 0 1-1.2-.6V6.75Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="messages-dock-label">پیام‌ها</span>
      <?php if ($unread_total > 0) : ?>
        <span class="messages-dock-badge" aria-label="<?= (int) $unread_total ?> پیام خوانده‌نشده"><?= $unread_total > 9 ? '۹+' : (string) $unread_total ?></span>
      <?php endif; ?>
    </button>
    <div class="messages-dock-panel" id="messages-dock-panel" data-messages-dock-panel hidden>
      <div class="messages-dock-view is-active" data-messages-dock-list-view>
        <header class="messages-dock-panel-head">
          <strong><?= $is_admin_chat ? 'پیام به اعضا' : 'پیام‌ها' ?></strong>
          <a href="<?= casting_e(casting_url('chat.php')) ?>">صفحه کامل</a>
        </header>
        <?php if ($is_admin_chat && $preview !== []) : ?>
          <div class="messages-dock-search">
            <input type="search" placeholder="جستجوی عضو…" data-messages-dock-filter aria-label="جستجوی عضو">
          </div>
        <?php endif; ?>
        <?php if ($preview === []) : ?>
          <p class="messages-dock-empty meta">هنوز گفتگویی ندارید. از پروفایل اعضا می‌توانید پیام بفرستید.</p>
        <?php else : ?>
          <ul class="messages-dock-list">
            <?php foreach ($preview as $conv) :
                $peer = (int) ($conv['peer_id'] ?? 0);
                if ($peer <= 0 || !casting_dm_peer_is_listable($peer)) {
                    continue;
                }
                $name = trim((string) ($conv['name'] ?? ''));
                if ($name === '') {
                    $name = casting_dm_peer_display_name($peer);
                }
                if ($name === '') {
                    continue;
                }
                $role = (string) ($conv['role'] ?? '');
                if ($role === '' && !$is_admin_chat) {
                    try {
                        $role = casting_dm_peer_role_label($peer);
                    } catch (Throwable $e) {
                        $role = '';
                    }
                }
                $avatar = (string) ($conv['avatar'] ?? '');
                if ($avatar === '') {
                    $avatar = casting_chat_peer_avatar_url($peer);
                }
                $unread = (int) ($conv['unread'] ?? 0);
                $last = trim((string) ($conv['last_message'] ?? ''));
                if (function_exists('mb_strlen') && mb_strlen($last, 'UTF-8') > 40) {
                    $last = mb_substr($last, 0, 40, 'UTF-8') . '…';
                } elseif (strlen($last) > 40) {
                    $last = substr($last, 0, 40) . '…';
                }
                $initial = function_exists('mb_substr')
                    ? (string) mb_substr($name !== '' ? $name : '؟', 0, 1, 'UTF-8')
                    : substr($name !== '' ? $name : '?', 0, 1);
                ?>
              <li data-dock-user-row data-dock-user-name="<?= casting_e(function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name)) ?>">
                <button
                  type="button"
                  class="messages-dock-row<?= $unread > 0 ? ' is-unread' : '' ?>"
                  data-messages-dock-open="<?= $peer ?>"
                  data-peer-name="<?= casting_e($name) ?>"
                  data-peer-role="<?= casting_e($role) ?>"
                >
                  <span class="messages-dock-row-avatar">
                    <?php if ($avatar !== '') : ?>
                      <img src="<?= casting_e($avatar) ?>" alt="">
                    <?php else : ?>
                      <span aria-hidden="true"><?= casting_e($initial) ?></span>
                    <?php endif; ?>
                  </span>
                  <span class="messages-dock-row-text">
                    <strong><?= casting_e($name) ?></strong>
                    <?php if ($role !== '') : ?>
                      <span class="meta"><?= casting_e($role) ?></span>
                    <?php endif; ?>
                    <?php if ($last !== '') : ?>
                      <span class="messages-dock-snippet"><?= casting_e($last) ?></span>
                    <?php endif; ?>
                  </span>
                  <?php if ($unread > 0) : ?>
                    <span class="messages-dock-row-badge"><?= $unread > 9 ? '۹+' : (string) $unread ?></span>
                  <?php endif; ?>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="messages-dock-view" data-messages-dock-thread-view hidden>
        <header class="messages-dock-panel-head messages-dock-thread-head">
          <button type="button" class="messages-dock-back" data-messages-dock-back aria-label="بازگشت">→</button>
          <div class="messages-dock-thread-peer">
            <strong data-messages-dock-peer-name>گفتگو</strong>
            <span class="meta" data-messages-dock-peer-role></span>
          </div>
          <a href="<?= casting_e(casting_url('chat.php')) ?>" data-messages-dock-full>کامل</a>
        </header>
        <div class="messages-dock-thread" data-messages-dock-thread></div>
        <form class="messages-dock-compose" data-messages-dock-compose>
          <input type="hidden" name="peer_id" value="0" data-messages-dock-peer-id>
          <textarea name="message" rows="1" placeholder="پیام بنویسید…" maxlength="2000" data-messages-dock-input required></textarea>
          <button type="submit" class="btn btn-primary btn-sm">ارسال</button>
        </form>
        <p class="messages-dock-thread-error meta" data-messages-dock-error hidden></p>
      </div>
    </div>
  </div>
    <?php
}

/**
 * اعضای ویژه فعال برای داشبورد پنل
 *
 * @return array<int, WP_User>
 */
function casting_home_premium_members(int $limit = 8, int $exclude_id = 0): array
{
    $limit = max(1, min(24, $limit));
    $args = [
        'number'     => 80,
        'orderby'    => 'registered',
        'order'      => 'DESC',
        'meta_key'   => 'casting_premium_until',
        'meta_compare' => 'EXISTS',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key'     => 'casting_role',
                'compare' => 'EXISTS',
            ],
            casting_member_visible_meta_query(),
        ],
    ];
    if ($exclude_id > 0) {
        $args['exclude'] = [$exclude_id];
    }
    $query = new WP_User_Query($args);
    $users = $query->get_results();
    if (!is_array($users)) {
        return [];
    }

    $out = [];
    foreach ($users as $user) {
        $id = (int) $user->ID;
        if (!casting_user_is_premium($id)) {
            continue;
        }
        $out[] = $user;
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * کاشی عضو برای داشبورد پنل — با پیش‌نمایش و دنبال‌کردن
 */
function casting_render_panel_home_member_tile(WP_User $member, bool $premium_badge = false, int $viewer_id = 0): void
{
    if (!function_exists('casting_follow_can_target')) {
        require_once __DIR__ . '/follows.php';
    }
    $id = (int) $member->ID;
    $profile = casting_get_profile($id);
    $photo = casting_member_card_photo_url($id, $profile);
    $city = trim((string) ($profile['city'] ?? ''));
    $role_label = casting_user_public_role_label($id);
    $can_follow = $viewer_id > 0 && casting_follow_can_target($viewer_id, $id);
    $meta_bits = array_values(array_filter([
        $role_label !== '' ? $role_label : '',
        $city !== '' ? $city : '',
    ]));
    ?>
    <article class="panel-ad-card panel-ad-card--portrait" data-home-card="<?= $id ?>">
      <button type="button" class="panel-ad-card-media<?= $photo !== '' ? ' has-photo' : '' ?>" data-member-preview="<?= $id ?>" aria-label="پیش‌نمایش <?= casting_e($member->display_name) ?>"<?= $photo !== '' ? ' style="background-image:url(' . casting_e($photo) . ')"' : '' ?>>
        <?php if ($premium_badge || casting_user_is_premium($id)) : ?>
          <span class="panel-ad-badge">ویژه</span>
        <?php endif; ?>
        <?php casting_render_official_page_badge($id); ?>
        <?php casting_render_presence_dot($id, 'md'); ?>
        <span class="panel-ad-card-overlay">
          <span class="panel-ad-card-name"><?= casting_e($member->display_name) ?></span>
          <?php if ($meta_bits !== []) : ?>
            <span class="panel-ad-card-meta"><?= casting_e(implode(' · ', $meta_bits)) ?></span>
          <?php endif; ?>
        </span>
      </button>
      <?php if ($can_follow) : ?>
        <div class="panel-ad-card-actions">
          <?php casting_render_follow_button($viewer_id, $id, 'btn-sm'); ?>
        </div>
      <?php endif; ?>
    </article>
    <?php
}

/**
 * یک ردیف اعضا + لینک «بیشتر» برای نمایش بقیه
 *
 * @param array<int, WP_User> $members
 */
function casting_render_panel_home_member_row(array $members, bool $premium_badge, string $more_id, int $row_size = 4, int $viewer_id = 0): void
{
    if ($members === []) {
        return;
    }
    $row_size = max(1, $row_size);
    $first = array_slice($members, 0, $row_size);
    $rest = array_slice($members, $row_size);
    ?>
    <div class="panel-ads-grid">
      <?php foreach ($first as $member) : ?>
        <?php casting_render_panel_home_member_tile($member, $premium_badge, $viewer_id); ?>
      <?php endforeach; ?>
    </div>
    <?php if ($rest !== []) : ?>
      <div class="panel-ads-grid panel-ads-grid-more" id="<?= casting_e($more_id) ?>" hidden>
        <?php foreach ($rest as $member) : ?>
          <?php casting_render_panel_home_member_tile($member, $premium_badge, $viewer_id); ?>
        <?php endforeach; ?>
      </div>
      <div class="panel-ads-foot">
        <button type="button" class="panel-ads-more-link" data-show-more="<?= casting_e($more_id) ?>">بیشتر</button>
      </div>
    <?php endif; ?>
    <?php
}

function casting_render_member_card(WP_User $member, int $viewer_id, ?array $director_flags = null, float $director_score = 0): void
{
    $id = (int) $member->ID;
    $profile = casting_get_profile($id);
    $premium = casting_user_is_premium($id);
    $photo = casting_member_card_photo_url($id, $profile);
    $viewed = !empty($director_flags['viewed']);
    $highlight = !empty($director_flags['is_highlight']);
    $city = trim((string) ($profile['city'] ?? ''));
    $age = (int) ($profile['age'] ?? 0);
    $role_label = casting_user_public_role_label($id);
    $is_official = function_exists('casting_follow_target_is_required') && casting_follow_target_is_required($id);
    $meta_bits = [];
    if ($role_label !== '') {
        $meta_bits[] = $role_label;
    }
    if ($age > 0) {
        $meta_bits[] = (string) $age . ' سال';
    }
    if ($city !== '') {
        $meta_bits[] = $city;
    }
    ?>
    <article class="member-card member-card--headshot member-card--portrait<?= $highlight ? ' member-card--highlight' : '' ?><?= $is_official ? ' member-card--official' : '' ?>" data-member-preview="<?= $id ?>">
      <button type="button" class="member-card-photo" data-member-preview="<?= $id ?>" aria-label="نمایش پروفایل <?= casting_e($member->display_name) ?>">
        <?php if ($photo !== '') : ?>
          <img src="<?= casting_e($photo) ?>" alt="" loading="lazy">
        <?php else : ?>
          <span class="photo-placeholder">بدون عکس</span>
        <?php endif; ?>
        <?php casting_render_presence_dot($id, 'md'); ?>
        <?php if ($viewed) : ?>
          <?php casting_render_director_viewed_badge(true); ?>
        <?php endif; ?>
        <?php if ($premium) : ?>
          <span class="member-card-photo-chip">ویژه</span>
        <?php endif; ?>
        <?php if ($director_score > 0) : ?>
          <span class="member-card-photo-score" title="بهترین امتیاز شما">★ <?= casting_e(casting_director_format_score($director_score)) ?></span>
        <?php endif; ?>
        <?php casting_render_official_page_badge($id); ?>
        <span class="member-card-overlay">
          <span class="member-card-name"><?= casting_e($member->display_name) ?></span>
          <?php if ($meta_bits !== []) : ?>
            <span class="member-card-meta"><?= casting_e(implode(' · ', $meta_bits)) ?></span>
          <?php endif; ?>
        </span>
      </button>
    </article>
    <?php
}

/**
 * @return array<int, array{id:int,name:string,login:string,role:string,photo_url:string,href:string}>
 */
function casting_search_members_by_name(string $q, int $exclude_id, int $limit = 12, int $viewer_id = 0): array
{
    $q = trim(sanitize_text_field($q));
    if ($q === '' || casting_strlen($q) < 2) {
        return [];
    }

    $skip_visible = $viewer_id > 0 && function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($viewer_id);
    $meta_query = [
        'relation' => 'AND',
        [
            'key'     => 'casting_role',
            'compare' => 'EXISTS',
        ],
    ];
    if (!$skip_visible) {
        $meta_query[] = casting_member_visible_meta_query();
    }

    $args = [
        'number'         => max(1, min(20, $limit)),
        'search'         => '*' . esc_attr($q) . '*',
        'search_columns' => ['display_name', 'user_login'],
        'orderby'        => 'display_name',
        'order'          => 'ASC',
        'meta_query'     => $meta_query,
    ];
    if ($exclude_id > 0) {
        $args['exclude'] = [$exclude_id];
    }

    $query = new WP_User_Query($args);
    $users = $query->get_results();
    if (!is_array($users)) {
        return [];
    }

    $out = [];
    foreach ($users as $user) {
        $id = (int) $user->ID;
        $role = casting_get_user_role($id);
        if ($role === '') {
            continue;
        }
        if (!$skip_visible) {
            $profile = casting_get_profile($id);
            if (!$profile['visible']) {
                continue;
            }
        } else {
            $profile = casting_get_profile($id);
        }
        $out[] = [
            'id'        => $id,
            'name'      => (string) $user->display_name,
            'login'     => (string) $user->user_login,
            'role'      => $role,
            'photo_url' => (string) ($profile['photo_url'] ?? ''),
            'href'      => casting_panel_profile_url($id),
        ];
    }
    return $out;
}

/**
 * @param list<WP_User> $members
 */
function casting_render_member_search_results(array $members, int $viewer_id, int $total, int $page, int $pages, array $filters): void
{
    $director_flags = [];
    $director_scores = [];
    if (casting_user_is_director_role($viewer_id)) {
        if (!function_exists('casting_director_workspace_flags_for_talents')) {
            require_once __DIR__ . '/director-workspace.php';
        }
        if (!function_exists('casting_director_best_scores_for_talents')) {
            require_once __DIR__ . '/director-desk.php';
        }
        $talent_ids = [];
        foreach ($members as $member) {
            if ($member instanceof WP_User && casting_get_user_role((int) $member->ID) === 'talent') {
                $talent_ids[] = (int) $member->ID;
            }
        }
        $director_flags = casting_director_workspace_flags_for_talents($viewer_id, $talent_ids);
        $director_scores = casting_director_best_scores_for_talents($viewer_id, $talent_ids);
    }
    ?>
  <?php $filters_active = casting_member_search_filters_active($filters); ?>
  <p class="meta member-search-count"><?= (int) $total ?> کاربر · اعضای ویژه در اولویت نمایش</p>
  <?php if (!$members) : ?>
    <div class="empty-state empty-state--search" role="status">
      <?php if ($filters_active) : ?>
        <h2 class="empty-state-title">نتیجه‌ای پیدا نشد</h2>
        <p class="empty-state-text">با این فیلترها کسی پیدا نشد. فیلترها را کمی بازتر کنید یا از نو شروع کنید.</p>
        <div class="cta-row empty-state-actions">
          <a class="btn btn-primary" href="search-users.php">پاک کردن فیلترها</a>
        </div>
        <ul class="empty-state-tips">
          <li>فقط تخصص یا شهر را نگه دارید</li>
          <li>بازهٔ سن را وسیع‌تر کنید</li>
          <li>فیلترهای پیشرفته را موقتاً خاموش کنید</li>
        </ul>
      <?php else : ?>
        <h2 class="empty-state-title">هنوز عضوی برای نمایش نیست</h2>
        <p class="empty-state-text">به‌زودی استعدادها اینجا دیده می‌شوند. یک تخصص یا شهر انتخاب کنید و دوباره جستجو کنید.</p>
        <div class="cta-row empty-state-actions">
          <a class="btn btn-ghost" href="#member-search-filters">تنظیم فیلتر</a>
        </div>
      <?php endif; ?>
    </div>
  <?php else : ?>
    <div class="member-grid">
      <?php foreach ($members as $member) : ?>
        <?php
        $member_id = (int) $member->ID;
        casting_render_member_card(
            $member,
            $viewer_id,
            $director_flags[$member_id] ?? null,
            (float) ($director_scores[$member_id] ?? 0)
        );
        ?>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1) : ?>
      <nav class="pager" aria-label="صفحه‌بندی">
        <?php for ($p = 1; $p <= $pages; $p++) : ?>
          <a class="pager-link <?= $p === $page ? 'is-active' : '' ?>" href="search-users.php?<?= casting_e(http_build_query(array_merge($filters, ['page' => $p]))) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
    <?php
}
