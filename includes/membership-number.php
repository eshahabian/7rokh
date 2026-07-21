<?php
declare(strict_types=1);

/**
 * @return array<string, string>
 */
function casting_membership_role_prefixes(): array
{
    return [
        'director' => 'K',
        'producer' => 'P',
        'talent'   => 'H',
    ];
}

function casting_membership_role_prefix(string $role): string
{
    $role = sanitize_key($role);
    $prefixes = casting_membership_role_prefixes();
    return $prefixes[$role] ?? 'H';
}

function casting_format_membership_number(string $role, int $sequence): string
{
    $prefix = casting_membership_role_prefix($role);
    $sequence = max(1, $sequence);

    return $prefix . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
}

function casting_next_membership_sequence(string $prefix): int
{
    $prefix = strtoupper(preg_replace('/[^A-Z]/', '', $prefix) ?? '');
    if ($prefix === '') {
        $prefix = 'H';
    }

    global $wpdb;
    $option_name = 'casting_membership_seq_' . strtolower($prefix);

    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s",
            $option_name
        )
    );

    if ($updated === 0) {
        add_option($option_name, 1, '', 'no');
        return 1;
    }

    $value = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            $option_name
        )
    );

    return max(1, (int) $value);
}

function casting_assign_membership_number(int $user_id, string $role): string
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0 || !casting_valid_role($role)) {
        return '';
    }

    $existing = (string) get_user_meta($user_id, 'casting_membership_number', true);
    if ($existing !== '') {
        return $existing;
    }

    $prefix = casting_membership_role_prefix($role);
    $number = casting_format_membership_number($role, casting_next_membership_sequence($prefix));
    update_user_meta($user_id, 'casting_membership_number', $number);

    return $number;
}

function casting_get_membership_number(int $user_id): string
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0) {
        return '';
    }

    $number = (string) get_user_meta($user_id, 'casting_membership_number', true);
    if ($number !== '') {
        return $number;
    }

    $role = casting_get_user_role($user_id);
    if ($role === '') {
        return '';
    }

    return casting_assign_membership_number($user_id, $role);
}

function casting_membership_prefix_label(string $number): string
{
    $prefix = strtoupper((string) strtok($number, '-'));
    $labels = [
        'K' => 'کارگردان',
        'P' => 'تهیه‌کننده',
        'H' => 'هنرمند',
    ];

    return $labels[$prefix] ?? '';
}
