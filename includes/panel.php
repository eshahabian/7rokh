<?php
declare(strict_types=1);

require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/premium.php';
require_once __DIR__ . '/chat-rules.php';
require_once __DIR__ . '/layout.php';

/**
 * @return array<int, array{key:string,label:string,href:string,icon?:string}>
 */
function casting_panel_nav_items(): array
{
    return [
        ['key' => 'panel',      'label' => 'پنل کاربری',              'href' => 'panel.php'],
        ['key' => 'messages',   'label' => 'پیام کاربران',            'href' => 'chat.php'],
        ['key' => 'search',     'label' => 'جستجوی کاربران',          'href' => 'search-users.php'],
        ['key' => 'premium',    'label' => 'خرید و فعال‌سازی',        'href' => 'premium.php'],
        ['key' => 'receipt',    'label' => 'ثبت فیش کارت به کارت',    'href' => 'premium-receipt.php'],
        ['key' => 'newest',     'label' => 'جدیدترین کاربران',        'href' => 'newest-users.php'],
        ['key' => 'visitors',   'label' => 'بازدیدکنندگان پروفایل من', 'href' => 'profile-visitors.php'],
        ['key' => 'blocked',    'label' => 'بلاک‌شده‌های من',         'href' => 'blocked-by-me.php'],
        ['key' => 'blockers',   'label' => 'بلاک‌کنندگان من',         'href' => 'blocked-me.php'],
        ['key' => 'profile',    'label' => 'ویرایش پروفایل',          'href' => 'profile-talent.php'],
        ['key' => 'myprofile',  'label' => 'مشاهده پروفایل خودم',     'href' => 'my-profile.php'],
        ['key' => 'photo',      'label' => 'ویرایش تصویر',            'href' => 'profile-photo.php'],
        ['key' => 'password',   'label' => 'تغییر رمز عبور',          'href' => 'change-password.php'],
        ['key' => 'transactions','label' => 'تراکنش‌های مالی',        'href' => 'transactions.php'],
        ['key' => 'cancel',     'label' => 'انصراف از عضویت',         'href' => 'cancel-membership.php'],
        ['key' => 'contact',    'label' => 'تماس با ما',              'href' => 'contact.php'],
        ['key' => 'rules',      'label' => 'قوانین',                  'href' => 'rules.php'],
        ['key' => 'logout',     'label' => 'خروج',                    'href' => 'logout.php'],
    ];
}

function casting_panel_profile_url(int $user_id): string
{
    $role = casting_get_user_role($user_id);
    if ($role === 'talent') {
        return 'member.php?id=' . $user_id;
    }
    return 'member.php?id=' . $user_id;
}

function casting_render_panel_sidebar(string $active): void
{
    ?>
    <aside class="panel-sidebar" aria-label="منوی پنل کاربری">
      <p class="panel-sidebar-title">پنل کاربری</p>
      <nav class="panel-nav">
        <?php foreach (casting_panel_nav_items() as $item) : ?>
          <a class="panel-nav-link <?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= casting_e($item['href']) ?>">
            <?= casting_e($item['label']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>
    <?php
}

function casting_render_panel_start(string $title, string $active, string $body_class = 'page-panel'): void
{
    casting_render_head($title, $body_class);
    casting_render_header('panel');
    echo '<main class="wrap panel-shell">';
    casting_render_panel_sidebar($active);
    echo '<div class="panel-content">';
}

function casting_render_panel_end(): void
{
    echo '</div></main>';
    casting_render_footer();
}

/**
 * @return array{users: WP_User[], total: int}
 */
function casting_query_members(int $exclude_id, array $filters = [], int $page = 1, int $per_page = 20): array
{
    $meta_query = [
        [
            'key'     => 'casting_role',
            'compare' => 'EXISTS',
        ],
    ];

    if (!empty($filters['role']) && casting_valid_role((string) $filters['role'])) {
        $meta_query[] = [
            'key'   => 'casting_role',
            'value' => sanitize_key((string) $filters['role']),
        ];
    }

    if (!empty($filters['city'])) {
        $meta_query[] = [
            'key'     => 'casting_city',
            'value'   => sanitize_text_field((string) $filters['city']),
            'compare' => 'LIKE',
        ];
    }

    $page = max(1, $page);
    $args = [
        'number'      => $per_page,
        'paged'       => $page,
        'orderby'     => 'registered',
        'order'       => 'DESC',
        'meta_query'  => $meta_query,
        'count_total' => true,
        'exclude'     => [$exclude_id],
    ];

    if (!empty($filters['q'])) {
        $args['search'] = '*' . esc_attr(sanitize_text_field((string) $filters['q'])) . '*';
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
    $args = [
        'number'     => $limit,
        'orderby'    => 'registered',
        'order'      => 'DESC',
        'meta_query' => [
            [
                'key'     => 'casting_role',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    if ($exclude_id > 0) {
        $args['exclude'] = [$exclude_id];
    }
    $query = new WP_User_Query($args);
    $users = $query->get_results();
    return is_array($users) ? $users : [];
}

function casting_render_member_card(WP_User $member, int $viewer_id): void
{
    $id = (int) $member->ID;
    $role = casting_get_user_role($id);
    $profile = casting_get_profile($id);
    $premium = casting_user_is_premium($id);
    $photo = $profile['photo_url'] !== '' ? $profile['photo_url'] : '';
    ?>
    <article class="member-card">
      <a class="member-card-photo" href="<?= casting_e(casting_panel_profile_url($id)) ?>">
        <?php if ($photo !== '') : ?>
          <img src="<?= casting_e($photo) ?>" alt="">
        <?php else : ?>
          <span class="photo-placeholder">بدون عکس</span>
        <?php endif; ?>
      </a>
      <div class="member-card-body">
        <h3><a href="<?= casting_e(casting_panel_profile_url($id)) ?>"><?= casting_e($member->display_name) ?></a></h3>
        <p class="meta">
          <?= casting_e(casting_role_label($role)) ?>
          <?php if ($premium) : ?><span class="chip chip-premium">ویژه</span><?php endif; ?>
          <?php if ($profile['city'] !== '') : ?> · <?= casting_e($profile['city']) ?><?php endif; ?>
        </p>
        <?php if ($viewer_id !== $id && casting_can_users_chat($viewer_id, $id)['ok']) : ?>
          <a class="btn btn-ghost btn-sm" href="chat.php?with=<?= $id ?>">پیام</a>
        <?php endif; ?>
      </div>
    </article>
    <?php
}
