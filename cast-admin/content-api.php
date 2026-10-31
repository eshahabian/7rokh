<?php
declare(strict_types=1);
namespace CastingCms;
use RuntimeException;

function content_get(string $route): array {
    $s=state();$user=current_user();$groups=array_filter(read_json('menus'),fn($g)=>design_can($user,'menus',$g['id']));
    if($route==='pages')return [200,['pages'=>array_values(array_map(fn($p)=>['id'=>$p['id'],'title'=>$p['title']],array_filter(site_config()['pages'],fn($p)=>design_preview_allowed($user,$p['id'])))),'canUndo'=>isset($s['_undo'])&&design_undo_allowed($s,$user)]];
    if($route==='page'){$id=(string)($_GET['id']??'');$p=page_config($id);if(!design_preview_allowed($user,$id))return [403,['error'=>'ادمین دسترسی این صفحه را برای حساب طراحی شما فعال نکرده است.']];$html=render_page($id,$s);return [200,['id'=>$id,'title'=>$p['title'],'url'=>site_base().'/cast-admin/preview.php?page='.rawurlencode($id),'publicUrl'=>site_base().'/'.$p['href'],'revision'=>revision($html),'fields'=>array_values(array_map(fn($f)=>array_intersect_key($f,array_flip(['id','scope','label','value'])),array_filter(fields_for($html,$id,$s),fn($f)=>design_field_allowed($user,$id,$f['scope'],$f['id'])))),'notice'=>$html===''?'برای خواندن متن‌های طراحی، پیش‌نمایش را باز کنید.':'']];}
    if($route==='menu'){$id=(string)($_GET['group']??array_key_first($groups)??'');if($id!==''&&!isset($groups[$id]))return [403,['error'=>'اجازهٔ ویرایش این منو را ندارید.']];$GLOBALS['cms_menu_id']=$id;$items=$id!==''?menu_items($s):[];return [200,['group'=>$id,'groups'=>array_values(array_map(fn($g)=>['id'=>$g['id'],'title'=>$g['title']],$groups)),'items'=>$items,'revision'=>revision(json_encode($items)),'destinations'=>site_config()['pages']]];}
    if($route==='design')return design_get((string)($_GET['page']??''),$user);
    if($route==='images')return [200,['images'=>array_values(array_filter(image_catalog(),fn($i)=>design_can($user,'images',$i['id'])))]];
    return [404,['error'=>'مسیر پیدا نشد.']];
}
function valid_link(string $href): bool {
    if(strlen($href)>2048||preg_match('~[\x00-\x20\\\\]~',$href)||preg_match('~%(?:0[0-9a-f]|1[0-9a-f]|2e|2f|5c)~i',$href))return false;
    if(preg_match('~^https?://~i',$href))return (bool)filter_var($href,FILTER_VALIDATE_URL)&&!parse_url($href,PHP_URL_USER)&&!parse_url($href,PHP_URL_PASS);
    if(preg_match('~^mailto:~i',$href))return (bool)filter_var(substr($href,7),FILTER_VALIDATE_EMAIL);
    if(preg_match('~^tel:\+?[0-9()\-]+$~i',$href))return true;
    if(str_contains($href,':')||str_starts_with($href,'//')||str_contains($href,'..'))return false;
    $parts=parse_url($href);if($parts===false||isset($parts['query']))return false;$path=$parts['path']??'';$prefix=site_base().'/';if(str_starts_with($path,$prefix))$path=substr($path,strlen($prefix));
    $target=null;foreach(site_config()['pages'] as $p)if($p['href']===$path)$target=$p;if(!$target)return false;
    if(isset($parts['fragment'])&&$parts['fragment']!==''){$anchor=$parts['fragment'];if(!preg_match('~^[\p{L}\p{N}_-]+$~u',$anchor))return false;$snap=read_json('page-'.hash('sha256',$target['id']));if(($snap['template']??'')!==template_revision())return false;return (bool)preg_match('~\bid=["\x27]'.preg_quote($anchor,'~').'["\x27]~u',$snap['html']);}
    return true;
}
function content_post(string $route,array $data): array {
    $s=state();$user=current_user();if(($data['stateVersion']??null)!==$s['version'])return [409,['error'=>'نسخهٔ سایت تغییر کرده است. پیش از ذخیره یا بازگردانی، صفحه را دوباره بارگذاری کنید.']];
    if($route==='design')return design_post($data,$user,$s);
    if($route==='undo'){if(!isset($s['_undo']))return [404,['error'=>'نسخه‌ای برای بازگردانی وجود ندارد.']];if(!design_undo_allowed($s,$user))return [403,['error'=>'اجازهٔ بازگرداندن تغییر این بخش را ندارید.']];commit_state($s['_undo'],$s['_lastChange']??[]);return [200,['ok'=>true,'message'=>'آخرین ذخیره بازگردانده شد.']];}
    if($route==='save'){
        $id=(string)($data['page']??'');page_config($id);$html=render_page($id,$s);if(($data['revision']??'')!==revision($html)||$html==='')return [409,['error'=>'صفحه تغییر کرده یا پیش‌نمایش هنوز خوانده نشده است.']];
        if(!design_preview_allowed($user,$id))return [403,['error'=>'اجازهٔ ویرایش این صفحه را ندارید.']];
        $changes=$data['changes']??null;if(!is_array($changes)||count($changes)<1||count($changes)>1000)return [400,['error'=>'ویرایش معتبر نیست.']];$fields=array_column(fields_for($html,$id,$s),null,'id');$scopes=[];$targets=[];
        foreach($changes as $change){$f=$fields[$change['id']??'']??null;if($f&&!design_field_allowed($user,$id,$f['scope'],$f['id']))return [403,['error'=>'ادمین اجازهٔ ویرایش این قسمت را نداده است.']];if($f){$scopes[]=$f['scope'];$targets[]=['id'=>$f['id'],'scope'=>$f['scope']];}}
        foreach($changes as $change){$f=$fields[$change['id']??'']??null;$value=$change['value']??null;if(!$f||!is_string($value)||strlen($value)>120000||preg_match('~[\x00-\x08\x0b\x0c\x0e-\x1f]~',$value))return [400,['error'=>'متن معتبر نیست.']];$prior=$s['edits'][$f['id']]??null;$s['edits'][$f['id']]=['original'=>$prior['original']??$f['value'],'value'=>trim($value),'label'=>$f['label'],'scope'=>$f['scope'],'nodePath'=>$f['parentPath']];}
        commit_state($s,['type'=>'text','page'=>$id,'scopes'=>array_values(array_unique($scopes)),'fields'=>$targets]);return [200,['ok'=>true,'message'=>'متن ذخیره شد.']];
    }
    if($route==='menu'){
        $id=(string)($data['group']??'');if(!design_can($user,'menus',$id))return [403,['error'=>'اجازهٔ ویرایش این منو را ندارید.']];$groups=read_json('menus');if(!isset($groups[$id]))return [400,['error'=>'ابتدا پیش‌نمایش منوی واقعی را باز کنید.']];$GLOBALS['cms_menu_id']=$id;$items=$data['items']??null;
        if(($data['revision']??'')!==revision(json_encode(menu_items($s))))return [409,['error'=>'منو تغییر کرده است.']];
        if(!is_array($items)||count($items)>40)return [400,['error'=>'حداکثر ۴۰ گزینه مجاز است.']];$seen=[];$clean=[];
        foreach($items as $item){$key=$item['id']??'';$label=$item['label']??null;$href=$item['href']??null;if(!is_string($key)||!preg_match('~^[a-zA-Z0-9-]{1,80}$~D',$key)||isset($seen[$key])||!is_string($label)||trim($label)===''||strlen($label)>320||!is_string($href)||!valid_link($href))return [400,['error'=>'عنوان یا مقصد منو معتبر نیست. لینک داخلی باید به صفحهٔ واقعی و بخش موجود آن اشاره کند.']];$seen[$key]=true;$clean[]=['id'=>$key,'label'=>trim($label),'href'=>$href];}
        $s['menus'][$id]=['items'=>$clean];commit_state($s,['type'=>'menus','id'=>$id]);return [200,['ok'=>true,'message'=>'منو در محل‌های مشترک ذخیره شد.']];
    }
    if($route==='image'){
        if(!is_string($data['id']??null)||!design_can($user,'images',$data['id']))return [403,['error'=>'اجازهٔ ویرایش این تصویر را ندارید.']];
        $item=null;foreach(image_catalog() as $i)if($i['id']===($data['id']??''))$item=$i;if(!$item)return [400,['error'=>'عکس پیدا نشد.']];if(($data['revision']??'')!==$item['revision'])return [409,['error'=>'عکس تغییر کرده است.']];
        $bytes=is_string($data['base64']??null)?base64_decode($data['base64'],true):false;if($bytes===false||strlen($bytes)>10*1024*1024||str_contains($bytes,'<?'))return [400,['error'=>'فایل عکس معتبر نیست؛ حداکثر ۱۰ مگابایت.']];
        $info=@getimagesizefromstring($bytes);$ext=strtolower(pathinfo($item['id'],PATHINFO_EXTENSION));$expected=['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp'][$ext]??'';
        if(!$info||$info['mime']!==$expected||$info[0]<1||$info[1]<1||max($info[0],$info[1])>8192||$info[0]*$info[1]>16000000)return [400,['error'=>'قالب یا ابعاد عکس معتبر نیست. JPEG، PNG و WebP با حداکثر ضلع ۸۱۹۲ پیکسل و ۱۶ میلیون پیکسل مجاز است.']];
        if(!function_exists('imagecreatefromstring'))return [503,['error'=>'برای پردازش امن تصویر، افزونهٔ GD باید در PHP هاست فعال باشد.']];
        $decoded=@imagecreatefromstring($bytes);if($decoded===false)return [400,['error'=>'محتوای تصویر قابل‌خواندن نیست.']];imagesavealpha($decoded,true);
        ob_start();$encoded=false;switch($expected){case 'image/png':$encoded=imagepng($decoded,null,6);break;case 'image/jpeg':$encoded=imagejpeg($decoded,null,90);break;case 'image/webp':$encoded=function_exists('imagewebp')&&imagewebp($decoded,null,90);break;}$bytes=ob_get_clean();if(is_resource($decoded))imagedestroy($decoded);unset($decoded);
        if(!$encoded||!is_string($bytes)||strlen($bytes)>10*1024*1024)return [400,['error'=>'تصویر در قالب یا حجم مجاز قابل‌پردازش نیست.']];
        $folder=data_dir().'/media';if(!is_dir($folder)&&!mkdir($folder,0700))throw new RuntimeException('Private media unavailable');$file=hash('sha256',$bytes).'.'.$ext;$tmp=$folder.'/'.bin2hex(random_bytes(12)).'.tmp';
        if(file_put_contents($tmp,$bytes)!==strlen($bytes)){@unlink($tmp);throw new RuntimeException('Media write failed');}chmod($tmp,0600);if(!rename($tmp,$folder.'/'.$file)){@unlink($tmp);throw new RuntimeException('Media commit failed');}
        $s['images'][$item['id']]=['file'=>$file,'mime'=>$expected];commit_state($s,['type'=>'images','id'=>$item['id']]);return [200,['ok'=>true,'message'=>'عکس در همهٔ محل‌های استفاده ذخیره شد.']];
    }
    return [404,['error'=>'مسیر پیدا نشد.']];
}
