<?php
namespace CastingCms;
use Throwable, InvalidArgumentException, JsonException;
require __DIR__.'/core.php';
header('Cache-Control: no-store');header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; frame-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
try{session_boot();$user=current_user();$template=$user&&!$user['mustChange']?'app.html':'login.html';$html=file_get_contents(__DIR__.'/'.$template);
 $html=str_replace('</head>','<link rel="stylesheet" href="'.esc(site_base()).'/cast-admin/portal.css"></head>',$html);
 $html=str_replace(['__EDITOR_TOKEN__','__BASE__','__USER__','__AUTH_MODE__'],[esc($_SESSION['csrf']),esc(site_base()),esc(json_encode($user?public_user($user):null,JSON_UNESCAPED_UNICODE)),$user?'password':'login'],$html);header('Content-Type: text/html; charset=utf-8');echo $html;
}catch(Throwable $e){error_log('FARA setup: '.$e->getMessage());http_response_code(503);echo 'PHP یا پوشهٔ خصوصی پنل آماده نیست. راهنمای نصب را بررسی کنید.';}
