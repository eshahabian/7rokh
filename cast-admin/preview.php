<?php
declare(strict_types=1);
namespace CastingCms;
require __DIR__.'/core.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
try{session_boot();$u=current_user();}catch(\Throwable $e){http_response_code(503);exit('فضای خصوصی پنل در دسترس نیست.');}if(!$u||$u['mustChange']){http_response_code(403);exit('ابتدا وارد پنل شوید و رمز موقت را تغییر دهید.');}
try{$p=page_config((string)($_GET['page']??'home'));if(!design_preview_allowed($u,$p['id'])){http_response_code(403);exit('ادمین دسترسی پیش‌نمایش این صفحه را نداده است.');}$_SESSION['previews'][$p['id']]=time();header('Location: '.site_base().'/'.$p['href'].'?cms-preview=1');}catch(\Throwable $e){http_response_code(404);}
