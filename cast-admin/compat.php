<?php
declare(strict_types=1);
namespace CastingCms;

// Local helpers keep PHP 7.4 compatibility without changing WordPress globals.
function str_starts_with(string $value, string $prefix): bool {
    return $prefix === '' || strncmp($value, $prefix, strlen($prefix)) === 0;
}
function str_ends_with(string $value, string $suffix): bool {
    return $suffix === '' || (strlen($suffix) <= strlen($value) && substr($value, -strlen($suffix)) === $suffix);
}
function str_contains(string $value, string $needle): bool {
    return $needle === '' || strpos($value, $needle) !== false;
}
function array_is_list(array $value): bool {
    $index = 0;
    foreach ($value as $key => $_) if ($key !== $index++) return false;
    return true;
}
