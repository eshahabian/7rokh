<?php
declare(strict_types=1);
namespace CastingCms;
require __DIR__.'/core.php';
try{$id=(string)($_GET['asset']??'');$found=false;foreach(site_config()['images'] as $i)if($i['id']===$id){$found=true;break;}if(!$found){http_response_code(404);exit;}
    $image=state()['images'][$id]??null;$file=$image?data_dir().'/media/'.$image['file']:SITE_ROOT.'/'.$id;
    $types=['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp'];header('Content-Type: '.($image['mime']??$types[strtolower(pathinfo($id,PATHINFO_EXTENSION))]));header('X-Content-Type-Options: nosniff');header('Content-Security-Policy: default-src \'none\'; sandbox');header('Cache-Control: no-cache');readfile($file);
}catch(\Throwable $e){http_response_code(503);}
