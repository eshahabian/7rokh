<?php
declare(strict_types=1);
namespace CastingCms;
require __DIR__.'/core.php';
try{$asset=(string)($_GET['asset']??'');$file=realpath(SITE_ROOT.'/'.$asset);$allowed=realpath(SITE_ROOT.'/assets');if(!$file||!str_starts_with($file,$allowed.DIRECTORY_SEPARATOR)||strtolower(pathinfo($file,PATHINFO_EXTENSION))!=='css'){http_response_code(404);exit;}
    $css=file_get_contents($file);$s=state();$css=preg_replace_callback('~url\(\s*(["\x27]?)([^)"\x27]+)\1\s*\)~i',function($m)use($file,$s){$url=trim($m[2]);if(preg_match('~^(data:|https?:|//|#)~i',$url))return $m[0];$path=realpath(dirname($file).'/'.explode('?',$url)[0]);if(!$path||!str_starts_with($path,realpath(SITE_ROOT).DIRECTORY_SEPARATOR))return $m[0];$id=str_replace(DIRECTORY_SEPARATOR,'/',substr($path,strlen(realpath(SITE_ROOT))+1));$out=isset($s['images'][$id])?site_base().'/cast-admin/media.php?asset='.rawurlencode($id).'&v='.$s['images'][$id]['file']:site_base().'/'.$id;return 'url("'.$out.'")';},$css);header('Content-Type: text/css; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-cache');echo $css;
}catch(\Throwable $e){http_response_code(503);}
