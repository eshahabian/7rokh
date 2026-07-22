<?php
declare(strict_types=1);

function casting_base_path(): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base === '/' || $base === '\\' || $base === '.') {
        return '';
    }

    return $base;
}

function casting_pwa_theme_color(): string
{
    return '#0c0e12';
}

function casting_pwa_start_url(): string
{
    $user = casting_current_user();
    if ($user && casting_get_user_role((int) $user->ID) !== '') {
        return casting_url('panel.php');
    }

    return casting_url('index.php');
}

function casting_render_pwa_head(): void
{
    $manifest = casting_e(casting_url('manifest.php'));
    $theme = casting_e(casting_pwa_theme_color());
    $icon192 = casting_e(casting_url('assets/img/icon-192.png'));
    $icon512 = casting_e(casting_url('assets/img/icon-512.png'));
    ?>
  <meta name="theme-color" content="<?= $theme ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="<?= casting_e(casting_brand()) ?>">
  <link rel="manifest" href="<?= $manifest ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= casting_e(casting_url('assets/img/icon-32.png')) ?>">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= $icon192 ?>">
  <link rel="apple-touch-icon" href="<?= $icon512 ?>">
<?php
}

function casting_render_pwa_bootstrap(): void
{
    ?>
  <script>
    window.CASTING_PWA = {
      swUrl: <?= json_encode(casting_url('service-worker.js'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      scope: <?= json_encode(casting_base_path() === '' ? '/' : casting_base_path() . '/', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
<?php
}
