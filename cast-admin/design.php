<?php
declare(strict_types=1);
namespace CastingCms;
use InvalidArgumentException;

function design_target_allowed(?array $user,string $page,string $scope): bool {
    return design_can($user,'design','site') || ($scope!=='site' && design_can($user,'design',$scope==='page'?'page:'.$page:'shared:'.$scope));
}
function design_elements(string $html,string $page): array {
    $nodes=text_scan($html,$page)['nodes'];$out=[];$shared=array_filter($nodes,fn($n)=>$n['tag']==='footer'||($n['tag']==='header'&&preg_match('~class=["\x27][^"\x27]*site-header~',$n['raw'])));
    foreach($nodes as $node){
        if(!isset($node['start'])||!preg_match('~^/html\[0\]/body\[0\](?:/|$)~',$node['path'])||preg_match('~/(?:script|style|noscript|svg|path|iframe|option|input|textarea|select)\[~',$node['path']))continue;
        $scope=$node['tag']==='body'?'site':'page';$path=$node['path'];
        foreach($shared as $ancestor){if(!in_array($ancestor['tag'],['header','footer'],true)||!str_starts_with($node['path'],$ancestor['path']))continue;if($ancestor['tag']==='header'&&!preg_match('~class=["\x27][^"\x27]*site-header~',$ancestor['raw']))continue;$scope=$ancestor['tag'];$path=substr($node['path'],strlen($ancestor['path']));break;}
        $id='d-'.substr(hash('sha256',template_revision().'|'.($scope==='page'?$page:'shared:'.$scope).'|'.$path),0,24);
        preg_match('~\bid=["\x27]([^"\x27]+)~',$node['raw'],$domId);preg_match('~\bclass=["\x27]([^"\x27]+)~',$node['raw'],$classes);
        $out[$id]=['id'=>$id,'scope'=>$scope,'tag'=>$node['tag'],'label'=>$node['tag'].(!empty($domId[1])?' #'.$domId[1]:(!empty($classes[1])?' .'.implode('.',array_slice(preg_split('~\s+~',trim($classes[1])),0,2)):'')),'start'=>$node['start'],'end'=>$node['end'],'raw'=>$node['raw']];
    }return $out;
}
function design_saved(array $s,string $page,array $element): array {
    $key=$element['scope']==='site'?'site':($element['scope']==='page'?'page:'.$page:'shared:'.$element['scope']);
    return $s['design'][$key]['rules'][$element['id']]??[];
}
function design_properties(): array {
    return ['color','background-color','font-size','font-weight','line-height','text-align','padding','margin','gap','border-radius','border-width','border-color','border-style','width','max-width','min-height','height','display','flex-direction','justify-content','align-items','grid-template-columns','object-fit','opacity','box-shadow','background-image','background-size','background-position','font-family','letter-spacing','order','flex-wrap','flex-grow','grid-column','grid-row','position','top','right','bottom','left','z-index','overflow','aspect-ratio','transform','background-repeat'];
}
function clean_design_properties($input): array {
    if(!is_array($input)||count($input)>count(design_properties()))throw new InvalidArgumentException('Invalid design properties');$out=[];
    foreach($input as $property=>$value){
        if(!in_array($property,design_properties(),true)||!is_string($value)||strlen($value)>240)throw new InvalidArgumentException('Invalid style');$value=trim($value);if($value==='')continue;
        if($property==='background-image'){$known=false;foreach(site_config()['images'] as $image)if($image['id']===$value)$known=true;if(!$known&&$value!=='none')throw new InvalidArgumentException('Invalid background image');}
        elseif(preg_match('~[{};<>@\\\\]|/\*|url\s*\(|expression\s*\(|javascript|!important~i',$value)||!preg_match('~^[a-zA-Z0-9#.,%()\s+*/_-]+$~D',$value))throw new InvalidArgumentException('Invalid style value');
        $out[$property]=$value;
    }return $out;
}
function clean_design_css($css): string {
    if(!is_string($css)||strlen($css)>60000||preg_match('~[<>\\\\]|/\*|@import|@charset|@font-face|https?:|image-set|url\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding~i',$css))throw new InvalidArgumentException('Invalid CSS');
    return trim($css);
}
function design_get(string $page,?array $user): array {
    page_config($page);if(!design_preview_allowed($user,$page))return [403,['error'=>'اجازهٔ مشاهدهٔ طراحی این صفحه را ندارید.']];
    $snapshot=read_json('page-'.hash('sha256',$page));$s=state();$elements=[];
    if(($snapshot['template']??'')===template_revision())foreach(design_elements($snapshot['html']??'',$page) as $e)if(design_target_allowed($user,$page,$e['scope']))$elements[]=['id'=>$e['id'],'scope'=>$e['scope'],'tag'=>$e['tag'],'label'=>$e['label'],'styles'=>design_saved($s,$page,$e)];
    return [200,['elements'=>$elements,'template'=>template_revision(),'css'=>$user['role']==='admin'?($s['design']['page:'.$page]['css']??''):'','siteCss'=>$user['role']==='admin'?($s['design']['site']['css']??''):'','canCss'=>$user['role']==='admin']];
}
function design_post(array $data,?array $user,array $s): array {
    $page=(string)($data['page']??'');page_config($page);
    if(!design_preview_allowed($user,$page))return [403,['error'=>'اجازهٔ طراحی این صفحه را ندارید.']];
    $snapshot=read_json('page-'.hash('sha256',$page));
    if(($data['template']??'')!==template_revision()||($snapshot['template']??'')!==template_revision())return [409,['error'=>'پیش‌نمایش این صفحه را دوباره بارگذاری کنید.']];
    $targets=[];
    if(array_key_exists('css',$data)||array_key_exists('siteCss',$data)){
        if($user['role']!=='admin')return [403,['error'=>'CSS پیشرفته فقط برای مدیر طراحی مجاز است.']];
        if(array_key_exists('css',$data)){$scope=($data['scope']??'page')==='site'?'site':'page:'.$page;$s['design'][$scope]['css']=clean_design_css($data['css']);$targets[]=$scope;}
        if(array_key_exists('siteCss',$data)){$s['design']['site']['css']=clean_design_css($data['siteCss']);$targets[]='site';}
    }
    if(array_key_exists('element',$data)){
        $elements=design_elements($snapshot['html'],$page);$element=$elements[$data['element']??'']??null;
        if(!$element)return [400,['error'=>'بخش انتخاب‌شده پیدا نشد.']];
        if(!design_target_allowed($user,$page,$element['scope']))return [403,['error'=>'اجازهٔ تغییر ظاهر این بخش را ندارید.']];
        $viewport=$data['viewport']??'desktop';if(!in_array($viewport,['desktop','tablet','mobile'],true))return [400,['error'=>'اندازهٔ نمایش معتبر نیست.']];
        $target=$element['scope']==='site'?'site':($element['scope']==='page'?'page:'.$page:'shared:'.$element['scope']);$properties=clean_design_properties($data['properties']??null);
        $s['design'][$target]['rules'][$element['id']][$viewport]=$properties;
        $targets[]=$target;
    }
    if(!$targets)return [400,['error'=>'تغییری برای ذخیره دریافت نشد.']];
    commit_state($s,['type'=>'design','targets'=>array_values(array_unique($targets))]);
    return [200,['ok'=>true,'message'=>'طراحی ذخیره شد و روی سایت اعمال شد.']];
}
function design_css(array $s,string $page): string {
    $css='';$media=['desktop'=>'','tablet'=>'@media(max-width:1024px){','mobile'=>'@media(max-width:600px){'];
    // Desktop rules first, then narrower overrides, regardless of the saved object order.
    foreach($media as $viewport=>$opening){$rules='';foreach(['site','shared:header','shared:footer','page:'.$page] as $key)foreach(($s['design'][$key]['rules']??[]) as $id=>$styles){if(!preg_match('~^d-[a-f0-9]{24}$~D',$id))continue;$properties=$styles[$viewport]??[];$declarations='';foreach($properties as $property=>$value){if($property==='background-image'&&$value!=='none')$value='url("'.site_base().'/cast-admin/media.php?asset='.rawurlencode($value).'")';$declarations.=$property.':'.$value.'!important;';}if($declarations!=='')$rules.='[data-cms-design="'.$id.'"]{'.$declarations.'}';}if($rules!=='')$css.=$opening.$rules.($opening!==''?'}':'');}
    return $css.($s['design']['site']['css']??'')."\n".($s['design']['page:'.$page]['css']??'');
}
function design_render(string $html,string $page,array $s,bool $preview): string {
    if(!$preview&&empty($s['design']))return $html;$patches=[];
    foreach(design_elements($html,$page) as $e){$allowed=$preview&&design_target_allowed($GLOBALS['cms_preview_user']??null,$page,$e['scope']);if(!$allowed&&!design_saved($s,$page,$e))continue;$patches[]=['start'=>$e['start'],'end'=>$e['end'],'value'=>preg_replace('~\s*/?>$~','',$e['raw']).' data-cms-design="'.$e['id'].'"'.($allowed?' data-cms-selectable="1"':'').' >'];}
    $html=patch($html,$patches);$css=design_css($s,$page);if($css!=='')$html=str_replace('</head>','<style id="casting-design-styles">'.$css.'</style></head>',$html);
    return $html;
}
