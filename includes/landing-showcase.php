<?php
declare(strict_types=1);

/**
 * اسلایدرهای صفحه اول — سبک، مناسب PHP 7.4 و هاست اشتراکی.
 */

function casting_landing_cache_ttl(): int
{
    return defined('MINUTE_IN_SECONDS') ? 30 * MINUTE_IN_SECONDS : 1800;
}

/**
 * @return list<array<string, mixed>>
 */
function casting_landing_cache_get(string $key, int $limit): array
{
    if (!function_exists('get_transient')) {
        return [];
    }
    $cached = get_transient($key);
    if (!is_array($cached) || $cached === []) {
        return [];
    }

    return array_slice($cached, 0, $limit);
}

/**
 * @param list<array<string, mixed>> $items
 */
function casting_landing_cache_set(string $key, array $items): void
{
    if (function_exists('set_transient') && $items !== []) {
        set_transient($key, $items, casting_landing_cache_ttl());
    }
}

function casting_landing_e(string $value): string
{
    if (function_exists('casting_e')) {
        return casting_e($value);
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function casting_landing_budget_ok(float $deadline): bool
{
    return microtime(true) < $deadline;
}

function casting_landing_member_photo(int $user_id): string
{
    if ($user_id <= 0) {
        return '';
    }
    $keys = [
        'casting_photo_closeup_id',
        'casting_photo_medium_id',
        'casting_photo_profile_id',
        'casting_photo_long_id',
        'casting_photo_id',
    ];
    $id = 0;
    foreach ($keys as $key) {
        $id = (int) get_user_meta($user_id, $key, true);
        if ($id > 0) {
            break;
        }
    }
    if ($id <= 0) {
        return '';
    }
    $url = function_exists('wp_get_attachment_image_url') ? wp_get_attachment_image_url($id, 'medium') : '';
    if (!is_string($url) || $url === '') {
        $url = function_exists('wp_get_attachment_url') ? wp_get_attachment_url($id) : '';
    }

    return is_string($url) ? $url : '';
}

/**
 * @return list<int>
 */
function casting_landing_user_ids_with_photos(int $limit = 40): array
{
    if (!function_exists('get_users')) {
        return [];
    }
    $keys = [
        'casting_photo_closeup_id',
        'casting_photo_medium_id',
        'casting_photo_profile_id',
        'casting_photo_long_id',
        'casting_photo_id',
    ];
    $ids = [];
    foreach ($keys as $key) {
        if (count($ids) >= $limit) {
            break;
        }
        $found = get_users([
            'number'       => $limit,
            'orderby'      => 'ID',
            'order'        => 'DESC',
            'meta_key'     => $key,
            'meta_compare' => '>',
            'meta_value'   => '0',
            'fields'       => 'ID',
            'count_total'  => false,
        ]);
        if (!is_array($found)) {
            continue;
        }
        foreach ($found as $raw_id) {
            $id = (int) $raw_id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
    }

    return array_values($ids);
}

/**
 * @return list<array{id:int,name:string,role:string,role_label:string,photo:string}>
 */
function casting_landing_photo_members(int $limit = 16): array
{
    $limit = max(1, min(16, $limit));
    $cached = casting_landing_cache_get('casting_landing_photo_members_v3', $limit);
    if ($cached !== []) {
        return $cached;
    }

    $deadline = microtime(true) + 2.0;
    $ids = casting_landing_user_ids_with_photos(48);
    if ($ids === []) {
        return [];
    }

    rsort($ids, SORT_NUMERIC);
    $out = [];
    foreach ($ids as $id) {
        if (!casting_landing_budget_ok($deadline) || count($out) >= $limit) {
            break;
        }
        $role = function_exists('casting_get_user_role') ? casting_get_user_role($id) : '';
        if (!in_array($role, ['talent', 'director', 'producer'], true)) {
            continue;
        }
        if (function_exists('casting_user_profile_is_hidden') && casting_user_profile_is_hidden($id)) {
            continue;
        }
        $photo = casting_landing_member_photo($id);
        if ($photo === '') {
            continue;
        }
        $user = get_userdata($id);
        $name = '';
        $login = '';
        if ($user instanceof WP_User) {
            $name = trim((string) $user->display_name);
            $login = (string) $user->user_login;
        } elseif (is_object($user)) {
            $name = trim((string) ($user->display_name ?? ''));
            $login = (string) ($user->user_login ?? '');
        }
        if ($name === '') {
            $name = $login;
        }
        if ($name === '') {
            continue;
        }
        $role_label = 'بازیگر';
        if ($role === 'director') {
            $role_label = 'کارگردان';
        } elseif ($role === 'producer') {
            $role_label = 'تهیه‌کننده';
        }
        $out[] = [
            'id'         => $id,
            'name'       => $name,
            'role'       => $role,
            'role_label' => $role_label,
            'photo'      => $photo,
        ];
    }

    casting_landing_cache_set('casting_landing_photo_members_v3', $out);

    return $out;
}

/**
 * @return array{a:string,b:string,glow:string,ink:string}
 */
function casting_landing_poster_palette(string $title, string $type): array
{
    $palettes = [
        ['a' => '#2a1c12', 'b' => '#0d0b09', 'glow' => '#d4a574', 'ink' => '#fff8ee'],
        ['a' => '#1a2433', 'b' => '#0a0e14', 'glow' => '#8caad2', 'ink' => '#eef4ff'],
        ['a' => '#2a1218', 'b' => '#10080b', 'glow' => '#d27882', 'ink' => '#fff0f2'],
        ['a' => '#1a2618', 'b' => '#0a120c', 'glow' => '#a0be78', 'ink' => '#f2ffe8'],
        ['a' => '#24182a', 'b' => '#0e0a14', 'glow' => '#b496d2', 'ink' => '#f6eeff'],
        ['a' => '#2a220e', 'b' => '#120e08', 'glow' => '#dcbd5a', 'ink' => '#fff8e0'],
    ];
    if ($type === 'theater') {
        $palettes = array_reverse($palettes);
    }
    $idx = abs((int) crc32($title . '|' . $type)) % count($palettes);

    return $palettes[$idx];
}

/**
 * موتیف بصری پوستر بر اساس اسم اثر.
 */
function casting_landing_poster_motif(string $title): string
{
    $t = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
    $map = [
        'ماه' => 'moon',
        'قصر' => 'palace',
        'شیرین' => 'palace',
        'پروا' => 'flight',
        'دژ' => 'fortress',
        'سوق' => 'fortress',
        'هملت' => 'skull',
        'مکبث' => 'dagger',
        'مده' => 'mask',
        'آنتی' => 'mask',
        'رابین' => 'forest',
        'هود' => 'forest',
        'ترس' => 'fear',
        'نکبت' => 'fear',
        'خوشبخت' => 'warm',
        'جبهه' => 'war',
        'شمال' => 'war',
        'مصریه' => 'desert',
        'عوروب' => 'journey',
        'غریب' => 'journey',
        'بلیت' => 'stage',
        'نمایش' => 'stage',
    ];
    foreach ($map as $needle => $motif) {
        if ($needle !== '' && (function_exists('mb_strpos') ? mb_strpos($t, $needle, 0, 'UTF-8') : strpos($t, $needle)) !== false) {
            return $motif;
        }
    }
    $fallbacks = ['orb', 'beam', 'stage', 'warm', 'flight', 'mask'];
    return $fallbacks[abs((int) crc32($title)) % count($fallbacks)];
}

/**
 * اسلاگ امن برای فایل پوستر.
 */
function casting_landing_work_slug(string $title): string
{
    $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
    $map = [
        'ماه گرفته'                 => 'mah-gerefte',
        'قصرشیرین'                  => 'qasr-shirin',
        'پروا'                      => 'parva',
        'مرتضی'                     => 'morteza',
        'دژ سوق'                    => 'dezh-sooq',
        'مصریه'                     => 'mesriye',
        'هملت'                      => 'hamlet',
        'مکبث'                      => 'macbeth',
        'حالا بگویید خوشبختی'       => 'khoshbakhti',
        'ترس و نکبت'                => 'tars-nakbat',
        'سه نمایش با یک بلیت'       => 'se-namayesh',
        'مده آ'                     => 'medea',
        'مده‌آ'                     => 'medea',
        'درجبهه شمال خبری نیست'     => 'jebhe-shomal',
        'در جبهه شمال خبری نیست'    => 'jebhe-shomal',
        'آنتی گونه'                 => 'antigone',
        'آنتیگونه'                  => 'antigone',
        'رابین هود'                 => 'robin-hood',
        'عوروب در دیار غریب'        => 'oroub',
    ];
    if (isset($map[$title])) {
        return $map[$title];
    }
    foreach ($map as $needle => $slug) {
        if ($needle !== '' && (function_exists('mb_strpos') ? mb_strpos($title, $needle, 0, 'UTF-8') : strpos($title, $needle)) !== false) {
            return $slug;
        }
    }
    $raw = function_exists('remove_accents') ? remove_accents($title) : $title;
    $slug = sanitize_title($raw);
    if ($slug === '' || $slug === '0') {
        $slug = 'work-' . substr(md5($title), 0, 10);
    }

    return $slug;
}

/**
 * آدرس پوستر تصویری اثر؛ اگر فایل آماده باشد همان، وگرنه SVG ساخته‌شده.
 */
function casting_landing_work_poster_url(string $title, string $type): string
{
    $slug = casting_landing_work_slug($title);
    $rel = 'images/landing-works/work-' . $slug . '.png';
    $abs = dirname(__DIR__) . '/assets/' . $rel;
    if (is_file($abs)) {
        if (function_exists('casting_asset')) {
            return casting_asset($rel);
        }

        return 'assets/' . $rel;
    }

    return casting_landing_work_poster_svg($title, $type);
}

/**
 * SVG پوستر fallback برای عنوان‌هایی که فایل PNG ندارند.
 */
function casting_landing_work_poster_svg(string $title, string $type): string
{
    $palette = casting_landing_poster_palette($title, $type);
    $motif = casting_landing_poster_motif($title);
    $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $type_label = $type === 'theater' ? 'تئاتر' : 'فیلم';
    $a = $palette['a'];
    $b = $palette['b'];
    $glow = $palette['glow'];
    $ink = $palette['ink'];

    $art = '';
    switch ($motif) {
        case 'moon':
            $art = '<circle cx="180" cy="150" r="58" fill="' . $glow . '" opacity="0.95"/>'
                . '<circle cx="198" cy="138" r="50" fill="' . $b . '"/>'
                . '<circle cx="120" cy="90" r="3" fill="#fff" opacity="0.7"/>'
                . '<circle cx="250" cy="110" r="2" fill="#fff" opacity="0.55"/>'
                . '<circle cx="90" cy="180" r="2.5" fill="#fff" opacity="0.45"/>';
            break;
        case 'palace':
            $art = '<path d="M70 300 V160 H100 V120 H130 V160 H160 V110 H200 V160 H230 V120 H260 V160 H290 V300 Z" fill="' . $glow . '" opacity="0.35"/>'
                . '<path d="M90 300 V190 H120 V150 H150 V190 H180 V140 H220 V190 H250 V150 H280 V190 H300 V300 Z" fill="' . $glow . '" opacity="0.7"/>'
                . '<rect x="145" y="210" width="50" height="90" fill="' . $b . '" opacity="0.55"/>';
            break;
        case 'flight':
            $art = '<path d="M70 180 C140 120, 200 120, 290 160" fill="none" stroke="' . $glow . '" stroke-width="3" opacity="0.5"/>'
                . '<path d="M150 170 L210 145 L250 175 L205 165 Z" fill="' . $glow . '"/>'
                . '<path d="M210 145 L235 110 L225 150 Z" fill="' . $ink . '" opacity="0.85"/>';
            break;
        case 'fortress':
            $art = '<path d="M80 300 V140 H110 V120 H140 V140 H170 V120 H200 V140 H230 V120 H260 V140 H290 V300 Z" fill="' . $glow . '" opacity="0.55"/>'
                . '<rect x="150" y="200" width="60" height="100" fill="' . $b . '" opacity="0.6"/>'
                . '<circle cx="180" cy="100" r="18" fill="' . $glow . '" opacity="0.35"/>';
            break;
        case 'skull':
            $art = '<ellipse cx="180" cy="150" rx="48" ry="52" fill="' . $glow . '" opacity="0.85"/>'
                . '<circle cx="162" cy="145" r="8" fill="' . $b . '"/>'
                . '<circle cx="198" cy="145" r="8" fill="' . $b . '"/>'
                . '<path d="M168 175 Q180 188 192 175" fill="none" stroke="' . $b . '" stroke-width="3"/>';
            break;
        case 'dagger':
            $art = '<path d="M180 80 L188 210 L180 230 L172 210 Z" fill="' . $glow . '"/>'
                . '<rect x="155" y="200" width="50" height="10" rx="2" fill="' . $ink . '" opacity="0.8"/>'
                . '<circle cx="180" cy="70" r="10" fill="' . $glow . '" opacity="0.7"/>';
            break;
        case 'mask':
            $art = '<path d="M120 130 Q180 90 240 130 Q250 190 180 220 Q110 190 120 130 Z" fill="' . $glow . '" opacity="0.8"/>'
                . '<ellipse cx="155" cy="150" rx="10" ry="14" fill="' . $b . '"/>'
                . '<ellipse cx="205" cy="150" rx="10" ry="14" fill="' . $b . '"/>'
                . '<path d="M155 185 Q180 200 205 185" fill="none" stroke="' . $b . '" stroke-width="3"/>';
            break;
        case 'forest':
            $art = '<path d="M90 300 L130 160 L170 300 Z" fill="' . $glow . '" opacity="0.45"/>'
                . '<path d="M150 300 L200 120 L250 300 Z" fill="' . $glow . '" opacity="0.7"/>'
                . '<path d="M220 300 L260 180 L300 300 Z" fill="' . $glow . '" opacity="0.4"/>'
                . '<line x1="70" y1="210" x2="160" y2="150" stroke="' . $ink . '" stroke-width="2" opacity="0.7"/>';
            break;
        case 'fear':
            $art = '<circle cx="180" cy="140" r="70" fill="' . $glow . '" opacity="0.18"/>'
                . '<path d="M120 160 Q180 230 240 160" fill="none" stroke="' . $glow . '" stroke-width="4"/>'
                . '<circle cx="150" cy="130" r="7" fill="' . $ink . '" opacity="0.7"/>'
                . '<circle cx="210" cy="130" r="7" fill="' . $ink . '" opacity="0.7"/>';
            break;
        case 'warm':
            $art = '<circle cx="180" cy="140" r="55" fill="' . $glow . '" opacity="0.55"/>'
                . '<circle cx="180" cy="140" r="30" fill="' . $ink . '" opacity="0.25"/>'
                . '<path d="M90 250 Q180 200 270 250" fill="none" stroke="' . $glow . '" stroke-width="3" opacity="0.5"/>';
            break;
        case 'war':
            $art = '<path d="M80 220 L180 100 L280 220 Z" fill="' . $glow . '" opacity="0.35"/>'
                . '<rect x="165" y="180" width="30" height="120" fill="' . $glow . '" opacity="0.7"/>'
                . '<path d="M100 280 H260" stroke="' . $ink . '" stroke-width="2" opacity="0.4"/>';
            break;
        case 'desert':
            $art = '<path d="M40 260 Q100 220 160 260 Q220 300 280 250 Q320 230 360 260 V320 H40 Z" fill="' . $glow . '" opacity="0.45"/>'
                . '<circle cx="250" cy="110" r="28" fill="' . $glow . '" opacity="0.7"/>';
            break;
        case 'journey':
            $art = '<path d="M60 220 C120 180, 180 240, 240 190 C280 160, 310 200, 340 180" fill="none" stroke="' . $glow . '" stroke-width="4" opacity="0.7"/>'
                . '<circle cx="100" cy="205" r="6" fill="' . $ink . '"/>'
                . '<circle cx="220" cy="205" r="6" fill="' . $ink . '"/>'
                . '<circle cx="300" cy="185" r="6" fill="' . $ink . '"/>';
            break;
        case 'beam':
            $art = '<path d="M180 60 L120 300 H240 Z" fill="' . $glow . '" opacity="0.25"/>'
                . '<rect x="170" y="90" width="20" height="180" fill="' . $glow . '" opacity="0.55"/>';
            break;
        case 'stage':
            $art = '<path d="M60 250 Q180 180 300 250" fill="none" stroke="' . $glow . '" stroke-width="4" opacity="0.6"/>'
                . '<path d="M90 280 H270 L250 250 H110 Z" fill="' . $glow . '" opacity="0.45"/>'
                . '<circle cx="180" cy="150" r="22" fill="' . $ink . '" opacity="0.35"/>';
            break;
        default:
            $art = '<circle cx="180" cy="150" r="60" fill="' . $glow . '" opacity="0.35"/>'
                . '<circle cx="180" cy="150" r="28" fill="' . $glow . '" opacity="0.7"/>';
            break;
    }

    $title_y = 380;
    $lines = [];
    if (function_exists('mb_str_split')) {
        $chars = mb_str_split($title, 1, 'UTF-8');
    } else {
        $chars = preg_split('//u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($title);
    }
    $chunk = '';
    $max = 14;
    $count = 0;
    foreach ($chars as $ch) {
        $chunk .= $ch;
        $count++;
        if ($count >= $max && ($ch === ' ' || $count >= $max + 4)) {
            $lines[] = trim($chunk);
            $chunk = '';
            $count = 0;
            if (count($lines) >= 3) {
                break;
            }
        }
    }
    if ($chunk !== '' && count($lines) < 3) {
        $lines[] = trim($chunk);
    }
    if ($lines === []) {
        $lines[] = $title;
    }

    $title_nodes = '';
    foreach ($lines as $i => $line) {
        $y = $title_y + ($i * 28);
        $title_nodes .= '<text x="180" y="' . $y . '" text-anchor="middle" fill="' . $ink . '" font-size="22" font-family="Tahoma,Arial,sans-serif" font-weight="700">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</text>';
    }

    $svg = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 480" width="360" height="480" role="img" aria-label="' . $safe_title . '">'
        . '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0%" stop-color="' . $a . '"/><stop offset="100%" stop-color="' . $b . '"/>'
        . '</linearGradient>'
        . '<linearGradient id="fade" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="' . $b . '" stop-opacity="0"/>'
        . '<stop offset="55%" stop-color="' . $b . '" stop-opacity="0.15"/>'
        . '<stop offset="100%" stop-color="' . $b . '" stop-opacity="0.92"/>'
        . '</linearGradient></defs>'
        . '<rect width="360" height="480" fill="url(#bg)"/>'
        . '<circle cx="60" cy="70" r="90" fill="' . $glow . '" opacity="0.16"/>'
        . $art
        . '<rect x="18" y="18" width="324" height="444" fill="none" stroke="' . $glow . '" stroke-opacity="0.45" stroke-width="2"/>'
        . '<rect width="360" height="480" fill="url(#fade)"/>'
        . '<text x="40" y="48" fill="' . $glow . '" font-size="16" font-family="Tahoma,Arial,sans-serif" font-weight="700">' . $type_label . '</text>'
        . '<text x="320" y="48" text-anchor="end" fill="#fff" fill-opacity="0.7" font-size="15" font-family="Tahoma,Arial,sans-serif" font-weight="700">۷ رخ</text>'
        . $title_nodes
        . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * @return list<array{title:string,type:string,type_label:string,name:string}>
 */
function casting_landing_work_cards(int $limit = 16): array
{
    $limit = max(1, min(16, $limit));
    $cached = casting_landing_cache_get('casting_landing_work_cards_v3', $limit);
    if ($cached !== []) {
        return $cached;
    }
    if (!function_exists('casting_work_catalog_get')) {
        return [];
    }

    $type_labels = [
        'film'    => 'فیلم',
        'theater' => 'تئاتر',
    ];
    $deadline = microtime(true) + 0.8;
    $out = [];
    $seen = [];
    $catalog = casting_work_catalog_get();
    if (!is_array($catalog)) {
        return [];
    }

    foreach ($catalog as $entry) {
        if (!casting_landing_budget_ok($deadline) || count($out) >= $limit) {
            break;
        }
        if (!is_array($entry)) {
            continue;
        }
        $title = sanitize_text_field((string) ($entry['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $type = sanitize_key((string) ($entry['type'] ?? 'film'));
        if ($type !== 'theater') {
            $type = 'film';
        }
        $key = $type . '|' . $title;
        if (isset($seen[$key])) {
            continue;
        }
        $contributors = is_array($entry['contributors'] ?? null) ? $entry['contributors'] : [];
        $name = '';
        $ok = false;
        foreach ($contributors as $contributor) {
            if (!is_array($contributor)) {
                continue;
            }
            $uid = (int) ($contributor['user_id'] ?? 0);
            if ($uid > 0 && function_exists('casting_user_profile_is_hidden') && casting_user_profile_is_hidden($uid)) {
                continue;
            }
            $ok = true;
            $cand = trim((string) ($contributor['name'] ?? ''));
            if ($cand !== '') {
                $name = $cand;
                break;
            }
        }
        if (!$ok) {
            continue;
        }
        $seen[$key] = true;
        $out[] = [
            'title'      => $title,
            'type'       => $type,
            'type_label' => (string) ($type_labels[$type] ?? 'فیلم'),
            'name'       => $name,
        ];
    }

    casting_landing_cache_set('casting_landing_work_cards_v3', $out);

    return $out;
}

/**
 * لینک اکشن کارت اعضا: اگر عضو لاگین باشد پروفایل، وگرنه ورود/ثبت‌نام.
 */
function casting_landing_member_action_href(int $member_id): string
{
    $logged_in = false;
    if (function_exists('casting_current_user') && function_exists('casting_get_user_role')) {
        $user = casting_current_user();
        if ($user) {
            $logged_in = casting_get_user_role((int) $user->ID) !== '';
        }
    }
    if ($logged_in && $member_id > 0) {
        $path = 'member.php?id=' . $member_id;
    } else {
        $path = 'register.php';
    }
    if (function_exists('casting_url')) {
        return casting_url($path);
    }

    return $path;
}

/**
 * @param list<array<string, mixed>> $items
 */
function casting_render_landing_people_cards(array $items): void
{
    foreach ($items as $item) {
        $name = (string) ($item['name'] ?? '');
        $label = (string) ($item['role_label'] ?? '');
        $photo = (string) ($item['photo'] ?? '');
        $member_id = (int) ($item['id'] ?? 0);
        $href = casting_landing_member_action_href($member_id);
        ?>
        <article class="landing-marquee-card landing-people-card">
          <div class="landing-people-photo">
            <?php if ($photo !== '') : ?>
              <img src="<?= casting_landing_e($photo) ?>" alt="" loading="lazy" decoding="async">
            <?php endif; ?>
          </div>
          <div class="landing-people-meta">
            <strong><?= casting_landing_e($name) ?></strong>
            <span><?= casting_landing_e($label) ?></span>
          </div>
          <div class="landing-people-actions">
            <a class="landing-people-btn landing-people-btn--profile" href="<?= casting_landing_e($href) ?>">مشاهده پروفایل</a>
            <div class="landing-people-actions-row">
              <a class="landing-people-btn landing-people-btn--project" href="<?= casting_landing_e($href) ?>">دعوت به پروژه</a>
              <a class="landing-people-btn landing-people-btn--call" href="<?= casting_landing_e($href) ?>">دعوت به فراخوان</a>
              <a class="landing-people-btn landing-people-btn--msg" href="<?= casting_landing_e($href) ?>">پیام</a>
            </div>
          </div>
        </article>
        <?php
    }
}

/**
 * @param list<array<string, mixed>> $items
 */
function casting_render_landing_work_cards_html(array $items): void
{
    foreach ($items as $item) {
        $title = (string) ($item['title'] ?? '');
        $type = sanitize_key((string) ($item['type'] ?? 'film'));
        $type_label = (string) ($item['type_label'] ?? '');
        $name = (string) ($item['name'] ?? '');
        $poster = casting_landing_work_poster_url($title, $type);
        ?>
        <article class="landing-marquee-card landing-work-card">
          <div class="landing-work-poster is-illustrated">
            <img src="<?= casting_landing_e($poster) ?>" alt="<?= casting_landing_e($title) ?>" loading="lazy" decoding="async">
            <span class="landing-work-poster-caption"><?= casting_landing_e($title) ?></span>
            <?php if ($type_label !== '') : ?>
              <span class="landing-work-type"><?= casting_landing_e($type_label) ?></span>
            <?php endif; ?>
          </div>
          <div class="landing-work-meta">
            <strong><?= casting_landing_e($title) ?></strong>
            <?php if ($name !== '') : ?>
              <span><?= casting_landing_e($name) ?></span>
            <?php endif; ?>
          </div>
        </article>
        <?php
    }
}

function casting_render_landing_people_marquee(array $people, string $direction, string $title, string $lead, string $aria): void
{
    if ($people === []) {
        return;
    }
    $direction = $direction === 'right' ? 'right' : 'left';
    $animated = count($people) >= 4;
    $lead = trim($lead);
    ?>
  <section class="landing-showcase" aria-label="<?= casting_landing_e($aria) ?>">
    <header class="landing-section-head">
      <h2><?= casting_landing_e($title) ?></h2>
      <?php if ($lead !== '') : ?>
      <p><?= casting_landing_e($lead) ?></p>
      <?php endif; ?>
    </header>
    <div class="landing-marquee landing-marquee--<?= casting_landing_e($direction) ?><?= $animated ? ' is-animated' : '' ?>">
      <div class="landing-marquee-viewport">
        <div class="landing-marquee-track">
          <div class="landing-marquee-group">
            <?php casting_render_landing_people_cards($people); ?>
          </div>
          <?php if ($animated) : ?>
          <div class="landing-marquee-group" aria-hidden="true">
            <?php casting_render_landing_people_cards($people); ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
    <?php
}

function casting_render_landing_showcases(): void
{
    $people = [];
    $works = [];
    try {
        $people = casting_landing_photo_members(16);
    } catch (Throwable $e) {
        $people = [];
    }
    try {
        $works = casting_landing_work_cards(16);
    } catch (Throwable $e) {
        $works = [];
    }

    if ($people === [] && $works === []) {
        return;
    }

    if ($people !== []) {
        casting_render_landing_people_marquee(
            $people,
            'left',
            'اعضای پورتال',
            '',
            'اعضای پورتال'
        );
    }

    if ($works !== []) {
        $works_animated = count($works) >= 4;
        ?>
  <section class="landing-showcase" aria-label="آثار هنرمندان">
    <header class="landing-section-head">
      <h2>آثار هنرمندان</h2>
    </header>
    <div class="landing-marquee landing-marquee--right<?= $works_animated ? ' is-animated' : '' ?>">
      <div class="landing-marquee-viewport">
        <div class="landing-marquee-track">
          <div class="landing-marquee-group">
            <?php casting_render_landing_work_cards_html($works); ?>
          </div>
          <?php if ($works_animated) : ?>
          <div class="landing-marquee-group" aria-hidden="true">
            <?php casting_render_landing_work_cards_html($works); ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
        <?php
    }
}
