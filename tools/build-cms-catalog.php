<?php
// Run after changing PHP templates; never loads WordPress or private configuration.
declare(strict_types=1);
$root=dirname(__DIR__);$files=array_merge(glob($root.'/*.php'),glob($root.'/includes/*.php'));$sources=[];$texts=[];$pages=[];$combined='';
$titles=['index'=>'صفحهٔ اصلی عمومی','home'=>'خانهٔ اعضا','faq'=>'سوالات متداول','rules'=>'قوانین','contact'=>'تماس با ما','register'=>'ثبت‌نام','login'=>'ورود','app'=>'اپلیکیشن'];
foreach($files as $file){if(preg_match('~config(?:\.local)?\.php$~',$file))continue;$raw=file_get_contents($file);$rel=str_replace('\\','/',substr($file,strlen($root)+1));$sources[$rel]=hash('sha256',$raw);$combined.=$raw."\n";
    if(dirname($rel)==='.'&&preg_match('~casting_render_(?:head|panel_start)|<html|<main|<section|<form~',$raw)){$name=basename($rel,'.php');$pages[]=['id'=>$name==='index'?'home':($name==='home'?'member-home':$name),'href'=>$rel,'title'=>$titles[$name]??$name];}
    foreach(token_get_all($raw) as $token){if(!is_array($token))continue;$chunk='';if($token[0]===T_INLINE_HTML)$chunk=$token[1];elseif($token[0]===T_CONSTANT_ENCAPSED_STRING){$quote=$token[1][0];$inner=substr($token[1],1,-1);$chunk=$quote==="'"?str_replace(["\\'","\\\\"],["'","\\"],$inner):stripcslashes($inner);}else continue;
        $chunks=preg_split('~<[^>]*>~',$chunk);foreach($chunks as $t){$t=trim(html_entity_decode($t,ENT_QUOTES|ENT_HTML5,'UTF-8'));if($token[0]===T_CONSTANT_ENCAPSED_STRING&&!preg_match('~[\x{0600}-\x{06ff}]~u',$t))continue;if($t!==''&&strlen($t)<=120000&&preg_match('~\p{L}~u',$t))$texts[$t]=true;}
    }
}
$images=[];$assetFiles=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/assets',FilesystemIterator::SKIP_DOTS));$css='';foreach($assetFiles as $f)if($f->isFile()&&strtolower($f->getExtension())==='css')$css.=file_get_contents($f->getPathname());
$assetFiles->rewind();foreach($assetFiles as $f){if(!$f->isFile()||!preg_match('~^(png|jpe?g|webp)$~i',$f->getExtension()))continue;$rel=str_replace('\\','/',substr($f->getPathname(),strlen($root)+1));if(!str_contains($combined,basename($rel))&&!str_contains($css,basename($rel)))continue;$usage=[];foreach($sources as $src=>$hash)if(str_contains(file_get_contents($root.'/'.$src),basename($rel)))$usage[]=$src;
    $name=pathinfo($rel,PATHINFO_FILENAME);$label=$name;
    if(preg_match('~^promo-slide-(\d+)$~',$name,$m))$label='بنر معرفی شمارهٔ '.$m[1];elseif(preg_match('~^home-slide-(\d+)$~',$name,$m))$label='تصویر اسلاید خانه شمارهٔ '.$m[1];elseif(stripos($name,'background')!==false)$label='پس‌زمینهٔ سایت';elseif(stripos($name,'logo')!==false)$label='نشان سایت';
    $locations=[];$pageIds=[];foreach($usage as $src){if(str_starts_with($src,'includes/')){$locations[]='قالب مشترک سایت';continue;}foreach($pages as $p)if($p['href']===$src){$locations[]=$p['title'];$pageIds[]=$p['id'];}}
    $size=@getimagesize($f->getPathname());$images[]=['id'=>$rel,'label'=>$label,'pages'=>array_values(array_unique($pageIds)),'usage'=>array_values(array_unique($locations)),'width'=>$size[0]??null,'height'=>$size[1]??null];
}
usort($pages,fn($a,$b)=>($a['id']==='home'?-1:($b['id']==='home'?1:strcmp($a['title'],$b['title']))));
file_put_contents($root.'/cast-admin/catalog.json',json_encode(['pages'=>$pages,'sources'=>$sources,'texts'=>array_keys($texts),'images'=>$images,'menu'=>['items'=>[]]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo count($pages).' pages, '.count($images)." referenced images catalogued.\n";
