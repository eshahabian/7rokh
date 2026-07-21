<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/pwa.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$brand = casting_brand();
$icon192 = casting_url('assets/img/icon-192.png');
$icon512 = casting_url('assets/img/icon-512.png');

echo wp_json_encode([
    'name'             => $brand . ' — پورتال کستینگ',
    'short_name'       => $brand,
    'description'      => 'پورتال استعداد و بازیگری سینما و تئاتر',
    'start_url'        => casting_pwa_start_url(),
    'scope'            => casting_base_path() === '' ? '/' : casting_base_path() . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'dir'              => 'rtl',
    'lang'             => 'fa',
    'background_color' => casting_pwa_theme_color(),
    'theme_color'      => casting_pwa_theme_color(),
    'icons'            => [
        [
            'src'     => $icon192,
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $icon512,
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $icon512,
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
