<?php
namespace CastingCms;
use Throwable, InvalidArgumentException, JsonException;
require __DIR__.'/setup-error.php';
header('Cache-Control: no-store');header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; frame-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
if(PHP_VERSION_ID<70400){show_setup_error('PHP_VERSION');exit;}
if(!extension_loaded('session')||!extension_loaded('json')){show_setup_error('PHP_EXTENSIONS');exit;}
foreach(array('core.php','compat.php','runtime.php','design.php','permissions.php','config.php','catalog.json','app.html','login.html') as $requiredFile)if(!is_file(__DIR__.'/'.$requiredFile)){show_setup_error('FILES');exit;}
try{require __DIR__.'/core.php';session_boot();$user=current_user();$template=$user&&!$user['mustChange']?'app.html':'login.html';$html=file_get_contents(__DIR__.'/'.$template);
 $html=str_replace('</head>','<link rel="stylesheet" href="'.esc(site_base()).'/cast-admin/portal.css"></head>',$html);
 $html=str_replace(['__EDITOR_TOKEN__','__BASE__','__USER__','__AUTH_MODE__'],[esc($_SESSION['csrf']),esc(site_base()),esc(json_encode($user?public_user($user):null,JSON_UNESCAPED_UNICODE)),$user?'password':'login'],$html);header('Content-Type: text/html; charset=utf-8');echo $html;
}catch(Throwable $e){error_log('CAST ADMIN setup: '.$e->getMessage());show_setup_error(setup_error_reason($e));}
